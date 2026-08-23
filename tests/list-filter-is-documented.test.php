<?php
/**
 * The generic read routes take a server-side column filter, and the document
 * has to describe it accurately enough to generate a client from.
 *
 * WHAT WAS WRONG
 *
 * The filter was never missing. Route::defineRoutes() has always registered
 * list, count, names and ids with a trailing `[*:whereItems]?` segment, and
 * Route::handleWhereItems() parse_str()s it, validates the keys against the
 * class and refuses credential fields. `GET /tasklog/list/hostID=154` has
 * worked the whole time.
 *
 * The document said so only in prose, only on `list`, and got the syntax
 * wrong:
 *
 *     "Also reachable as /list and /all. A trailing filter segment accepts
 *      field:value pairs."
 *
 * It is `field=value` -- parse_str, not a colon. Following the sentence
 * literally sends `hostID:154`, which parse_str reads as one key named
 * "hostID:154" with an empty value, so the request answers
 * 400 "Unknown filter field(s)". A reader who tries the documented form and
 * gets an error concludes the feature does not exist, which is exactly what
 * happened downstream: darksidemilk/FogApi#66 records "list routes take only
 * start/length/expand, so there is no server-side column filter at all", and
 * Get-LastImageTime was rewritten to page the whole of taskLog and filter
 * client-side.
 *
 * Prose also cannot be generated from. A code generator reads parameters.
 *
 * WHY A QUERY PARAMETER AND NOT THE PATH SEGMENT
 *
 * OpenAPI cannot mark a path parameter optional, so documenting the segment
 * means a SECOND path per operation per class -- /{class}/list/{filter} and
 * three more -- which is 204 extra paths on a 372-path document, and 204
 * extra generated functions in every client built from it. The segment is
 * unchanged and still wins when both are sent; the query parameter is what
 * the document can actually express.
 *
 * DB-free: source text, class properties, and the document itself.
 *
 * Usage: php tests/list-filter-is-documented.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('list-filter');
FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';
$openapi = file_get_contents($web . '/lib/fog/openapi.class.php');

/*
 * 1. The wrong syntax is gone. Pinned as a literal because this is the
 *    string that cost a downstream project a rewrite -- if it comes back,
 *    it comes back exactly like this.
 */
$t->check(
    'the document no longer documents field:value pairs',
    false === strpos($openapi, 'field:value')
);

/*
 * 2. handleWhereItems() falls back to ?filter= only when nothing is set.
 *
 * This is the half with teeth. search() builds an id list in PHP and passes
 * it down as ['id' => [...]]; the scope filters reach _buildSql the same
 * way. If a request-supplied ?filter= could replace a NON-EMPTY array, a
 * caller could swap out an internally-built id list -- so the guard tests
 * "is anything already set", not "is it a string".
 */
$_SERVER['QUERY_STRING'] = 'filter=' . rawurlencode('name=probe');

/*
 * 2a. An UNDISPATCHED call must not see the request filter at all.
 *
 * This is the one that shipped broken. Reading ?filter= inside
 * handleWhereItems() meant every internal caller picked it up -- including
 * FOGBase::getActivePlugins(), which lists `hookevent` from LoadGlobals
 * during boot, before any route is dispatched. `hookevent` has no hostID, so
 * _assertFilterKeys() threw and ?filter=hostID=154 turned the entire API
 * into a 500 before it could serve anything. The filter is now captured by
 * runMatches() for named routes only, so with nothing captured this returns
 * unchanged.
 */
FOG\Route::takeRequestFilter();
$boot = FOG\Route::handleWhereItems(false, 'hookevent');
$t->check(
    'with no route dispatched, false is returned unchanged',
    false === $boot
);

// Everything below is the dispatched case, so capture as runMatches() would.
FOG\Route::captureRequestFilter('list');
$fromFalse = FOG\Route::handleWhereItems(false, 'host');
$t->check(
    'false (listem default) picks the query filter up',
    is_array($fromFalse) && isset($fromFalse['name'])
        && 'probe' === $fromFalse['name']
);

FOG\Route::captureRequestFilter('names');
$fromEmpty = FOG\Route::handleWhereItems([], 'host');
$t->check(
    'an empty array (names/ids default) picks it up too',
    is_array($fromEmpty) && isset($fromEmpty['name'])
        && 'probe' === $fromEmpty['name']
);

FOG\Route::captureRequestFilter('list');
$internal = FOG\Route::handleWhereItems(['id' => [7, 8, 9]], 'host');
$t->check(
    'a NON-EMPTY array is left exactly as passed',
    $internal === ['id' => [7, 8, 9]]
);
$t->check(
    'and specifically did not acquire the request filter',
    !isset($internal['name'])
);

FOG\Route::captureRequestFilter('list');
$segment = FOG\Route::handleWhereItems('name=fromsegment', 'host');
$t->check(
    'the path segment still wins over the query string',
    is_array($segment) && isset($segment['name'])
        && 'fromsegment' === $segment['name']
);

/*
 * 2b. A route that does not advertise a filter cannot be given one, and the
 *     capture is consumed rather than left lying around for the next call.
 */
FOG\Route::captureRequestFilter('search');
$searchArgs = FOG\Route::handleWhereItems(false, 'host');
$t->check(
    'search does not capture a filter',
    false === $searchArgs
);

FOG\Route::captureRequestFilter('list');
FOG\Route::handleWhereItems(false, 'host');
$second = FOG\Route::handleWhereItems(false, 'host');
$t->check(
    'the filter is consumed once, so nested internal calls see nothing',
    false === $second
);

unset($_SERVER['QUERY_STRING']);
FOG\Route::captureRequestFilter('list');
$none = FOG\Route::handleWhereItems(false, 'host');
$t->check(
    'with no filter anywhere, false is returned unchanged',
    false === $none
);

/*
 * 3. The document. Generated per request, so it is built here rather than
 *    read off disk.
 */
$doc = FogTestHarness::callStatic('FOG\OpenAPI', 'document');
$t->check(
    'the document generated',
    is_array($doc) && isset($doc['paths'], $doc['components'])
);
$t->check(
    'components.parameters declares filter',
    isset($doc['components']['parameters']['filter'])
);
$t->check(
    'and it is an optional query parameter',
    'query' === ($doc['components']['parameters']['filter']['in'] ?? '')
        && false === ($doc['components']['parameters']['filter']['required'] ?? true)
);

/**
 * Whether an operation advertises the shared filter parameter.
 *
 * @param array $op The operation object.
 *
 * @return bool
 */
function opHasFilter(array $op)
{
    foreach ((array)($op['parameters'] ?? []) as $p) {
        if ('#/components/parameters/filter' === ($p['$ref'] ?? '')) {
            return true;
        }
    }
    return false;
}

$wantFilter = 0;
$missing = [];
$searchWithFilter = [];
foreach ((array)FOG\Route::$validClasses as $class) {
    $class = strtolower($class);
    foreach (['', '/count', '/names', '/ids'] as $suffix) {
        $path = '/' . $class . $suffix;
        if (!isset($doc['paths'][$path]['get'])) {
            continue;
        }
        $wantFilter++;
        if (!opHasFilter($doc['paths'][$path]['get'])) {
            $missing[] = $path;
        }
    }
    // search() cannot honour a filter -- it passes its matched ids down as
    // a non-empty array, which the guard above deliberately protects. So
    // advertising one there would recreate the exact defect being fixed.
    foreach ((array)$doc['paths'] as $path => $item) {
        if (0 === strpos($path, '/' . $class . '/search/')
            && isset($item['get'])
            && opHasFilter($item['get'])
        ) {
            $searchWithFilter[] = $path;
        }
    }
}

$t->check(
    'the sweep actually found the generic read operations',
    $wantFilter > 150
);
$t->check(
    'every list/count/names/ids operation advertises ?filter',
    count($missing) < 1
);
$t->check(
    'no search operation advertises a filter it cannot honour',
    count($searchWithFilter) < 1
);

/*
 * 4. No path explosion. The reason this is a query parameter at all is that
 *    the alternative adds a path per operation per class; if someone later
 *    "improves" it by emitting those paths, this is what says no.
 */
$filterPaths = [];
foreach (array_keys((array)$doc['paths']) as $path) {
    if (false !== strpos($path, '{filter}') || false !== strpos($path, '{whereItems}')) {
        $filterPaths[] = $path;
    }
}
$t->check(
    'the filter added no paths of its own',
    count($filterPaths) < 1
);

$t->finish();
