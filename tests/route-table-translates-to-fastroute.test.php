<?php
/**
 * Every shape in Route::defineRoutes()'s table dispatches correctly once
 * translated into FastRoute syntax.
 *
 * This exists because event-tables-are-read-only.test.php, the only other
 * test that dispatches real routes, happens to exercise just ONE of the
 * table's shapes (a bare typed-class GET). Building this test caught a real
 * bug in _toFastRoutePattern() that nothing else in the suite saw: literal
 * text immediately before an optional bracket (the "/count" in
 * "{class}/count/[*:whereItems]?") was being folded INTO the optional group,
 * so `GET /host/count` (whereItems present) matched but the translator's
 * first draft made `/count` itself optional too -- which collided with
 * `names`/`ids`/`list`'s own bare-class variant the moment two routes
 * degenerated to the identical pattern `/{class}`.
 *
 * DB-free: the router is built from class properties, same as
 * event-tables-are-read-only.test.php.
 *
 * Usage: php tests/route-table-translates-to-fastroute.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('route-table-fastroute');
FogTestHarness::fakeDb();

$t = new FogChecks();

$define = new \ReflectionMethod('FOG\Router\Route', 'defineRoutes');
$define->setAccessible(true);
$router = \FastRoute\simpleDispatcher(
    function (\FastRoute\RouteCollector $r) use ($define) {
        $define->invoke(null, $r);
    }
);

/**
 * Dispatch and assert FOUND, returning the matched vars (or [] on failure,
 * after recording it) so a caller can assert on specific param values.
 *
 * @param FogChecks $t      The check accumulator.
 * @param string    $label  What is being asserted.
 * @param string    $method HTTP verb.
 * @param string    $uri    Request URI.
 *
 * @return array
 */
function assertFound($t, $label, $method, $uri)
{
    $result = $GLOBALS['router']->dispatch($method, $uri);
    if (!$t->check($label, \FastRoute\Dispatcher::FOUND === $result[0])) {
        return [];
    }
    return $result[2];
}

// status: HEAD|GET combined, no-colon alternation ([status|info]).
assertFound($t, 'GET /system/status', 'GET', '/system/status');
assertFound($t, 'GET /system/info', 'GET', '/system/info');
assertFound($t, 'HEAD /system/status', 'HEAD', '/system/status');

// savedfilter: typed int capture.
$vars = assertFound($t, 'GET /system/savedfilter/5', 'GET', '/system/savedfilter/5');
$t->check('savedfilter id=5', '5' === ($vars['id'] ?? null));

// indiv: dynamic class-list alternation + typed int.
$vars = assertFound($t, 'GET /host/5', 'GET', '/host/5');
$t->check('indiv class=host', 'host' === ($vars['class'] ?? null));
$t->check('indiv id=5', '5' === ($vars['id'] ?? null));

// count: mandatory literal ("/count") that sits between the mandatory
// class prefix and an OPTIONAL trailing capture -- the exact shape the bug
// above got wrong. The bare form must match (whereItems absent) and so must
// the form with a filter, but "/count" itself must never be optional.
$vars = assertFound($t, 'GET /host/count (bare)', 'GET', '/host/count');
$t->check('count bare has no whereItems', !array_key_exists('whereItems', $vars));
$vars = assertFound($t, 'GET /host/count/somefilter', 'GET', '/host/count/somefilter');
$t->check('count whereItems=somefilter', 'somefilter' === ($vars['whereItems'] ?? null));

// bandwidth: wildcard capture.
$vars = assertFound($t, 'GET /bandwidth/eth0', 'GET', '/bandwidth/eth0');
$t->check('bandwidth dev=eth0', 'eth0' === ($vars['dev'] ?? null));

// list: TWO chained trailing optionals -- the route that is SUPPOSED to
// match bare /{class}. All three depths must match.
assertFound($t, 'GET /host (bare, list route)', 'GET', '/host');
assertFound($t, 'GET /host/list', 'GET', '/host/list');
$vars = assertFound($t, 'GET /host/list/somefilter', 'GET', '/host/list/somefilter');
$t->check('list whereItems=somefilter', 'somefilter' === ($vars['whereItems'] ?? null));

// update: typed id (mandatory) + trailing no-colon literal alternation
// (optional).
assertFound($t, 'PUT /host/5 (no suffix)', 'PUT', '/host/5');
assertFound($t, 'PUT /host/5/update', 'PUT', '/host/5/update');

// create: mandatory class prefix + trailing no-colon literal (optional).
assertFound($t, 'POST /host (bare)', 'POST', '/host');
assertFound($t, 'POST /host/create', 'POST', '/host/create');

// cancel: the one route with a mandatory segment AFTER an optional one in
// AltoRouter syntax, which FastRoute's grammar cannot express as a single
// pattern -- registered as two explicit routes. Both must work, and the id
// must come through on the one that carries it.
assertFound($t, 'DELETE /task/cancel (no id)', 'DELETE', '/task/cancel');
$vars = assertFound($t, 'DELETE /task/5/cancel', 'DELETE', '/task/5/cancel');
$t->check('cancel id=5', '5' === ($vars['id'] ?? null));

// active: the third dynamic class-list ($validActiveTasks), plus a mandatory
// no-colon literal alternation with no optional marker at all.
assertFound($t, 'GET /filedeletequeue/current', 'GET', '/filedeletequeue/current');
assertFound($t, 'GET /filedeletequeue/active', 'GET', '/filedeletequeue/active');

// The no-colon alternation mechanism itself: dispatching directly (bypassing
// setMatches()) exposes the throwaway placeholder FastRoute's grammar
// required. Confirms the naming convention setMatches() strips on actually
// appears, so that strip logic has something real to filter.
$raw = $router->dispatch('GET', '/system/status');
$ignoredKeys = array_filter(
    array_keys($raw[2]),
    static function ($key) {
        return 0 === strpos((string)$key, \FOG\Router\Route::IGNORED_PARAM_PREFIX);
    }
);
$t->check(
    'no-colon alternation produces an IGNORED_PARAM_PREFIX key pre-strip',
    count($ignoredKeys) > 0
);

$t->finish();
