<?php
/**
 * The event tables answer no write verb. ADR 0020 decision 7.
 *
 * `history`, `taskLog` and `userTracking` are the records of what happened.
 * A record of what happened that can be edited through the same API that
 * produced it is not a record, so the four write verbs come off all three.
 * Retention pruning, if it is ever wanted, is a named operation with its own
 * permission rather than `DELETE /api/history/{id}`.
 *
 * WHY THIS FILE EXISTS SEPARATELY FROM permission-actions-declared.
 *
 * That test enforces the MECHANISM -- a class on the read-only list must not
 * also be in writableClasses() -- and it would keep passing if somebody
 * deleted `history` from the list tomorrow. The reason is worth spelling out
 * because it is counterintuitive: that test fails a routable operation whose
 * node does not DECLARE the action, and `report` declares
 * view/create/edit/delete. So `report.delete` on `history` resolves to a
 * perfectly well-declared permission. It was grantable, it worked, and it
 * removed rows from the administrative audit trail. Declaring the action is
 * the other way to satisfy that test, which is exactly what makes it blind
 * to this.
 *
 * What was reachable before, none of it by accident:
 *
 *   history      -> `report`, which declares delete. REST DELETE funnels
 *                   through deletemass() rather than destroy(), so it did
 *                   not even pass the model on the way out.
 *   tasklog      -> `task`. A task.delete grant could rewrite or remove the
 *                   imaging reports GH-1206 exists to keep findable.
 *   usertracking -> no `create` declared at all, yet create/join/update/
 *                   delete were routed, on movement records for named
 *                   people.
 *
 * Checked at the ROUTER, not just against the list. The list is the input to
 * defineRoutes(); what matters is that no URI answers, and building the
 * router is one object away. `host` is checked alongside as a control -- a
 * router that matched nothing would otherwise pass every assertion here.
 *
 * DB-free: the router is built from class properties.
 *
 * Usage: php tests/event-tables-are-read-only.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('event-readonly');
FogTestHarness::fakeDb();

$t = new FogChecks();

// The three the ADR names. Spelled out rather than derived from the
// property, which would make this test agree with whatever the property
// happens to say.
$eventClasses = ['history', 'tasklog', 'usertracking'];

foreach ($eventClasses as $class) {
    $t->check(
        "$class is on Route::\$readOnlyClasses",
        in_array($class, (array)FOG\Route::$readOnlyClasses, true)
    );
    // The read side is the reason these are listed rather than removed
    // from $validClasses outright: the activity viewer, History_Report,
    // Task Management's log pane and the Login History tabs all read them.
    $t->check(
        "$class is still served for reading",
        in_array($class, (array)FOG\Route::$validClasses, true)
    );
    $t->check(
        "$class is excluded from writableClasses()",
        !in_array($class, (array)FOG\Route::writableClasses(), true)
    );
}

/*
 * The router itself. This is the assertion that survives someone changing
 * how the lists are combined.
 */
$prop = new \ReflectionProperty('FOG\Route', 'router');
$prop->setAccessible(true);
$prop->setValue(null, new \AltoRouter([], ''));
$define = new \ReflectionMethod('FOG\Route', 'defineRoutes');
$define->setAccessible(true);
$define->invoke(null);
$router = $prop->getValue();

$writes = [
    ['POST', '/%s'],
    ['PUT', '/%s/5'],
    ['PATCH', '/%s/5'],
    ['DELETE', '/%s/5'],
];
foreach ($eventClasses as $class) {
    foreach ($writes as $w) {
        $uri = sprintf($w[1], $class);
        $t->check(
            "$w[0] $uri matches no route",
            false === $router->match($uri, $w[0])
        );
    }
    // And the read side really does still answer, or "no write route" is
    // true for the boring reason that the class is not routed at all.
    $t->check(
        "GET /$class still matches",
        false !== $router->match('/' . $class, 'GET')
    );
    $t->check(
        "GET /$class/5 still matches",
        false !== $router->match('/' . $class . '/5', 'GET')
    );
}

/*
 * The control. A writable class must still be writable, so a router that
 * had simply failed to define anything cannot pass this file.
 */
foreach ([['POST', '/host'], ['PUT', '/host/5'], ['DELETE', '/host/5']] as $c) {
    $t->check(
        "control: $c[0] $c[1] still matches",
        false !== $router->match($c[1], $c[0])
    );
}

/*
 * The published document has to agree, or it advertises operations that
 * answer 404. Checked as "derived from the same list" rather than by
 * generating the document, which needs a database: the failure being
 * guarded against is somebody keeping a second copy of the class list in
 * openapi.class.php.
 */
$openapi = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/Router/OpenAPI.php'
);
$t->check(
    'OpenAPI derives writability from Route::writableClasses()',
    false !== strpos($openapi, 'Route::writableClasses()')
);
$t->check(
    'and does not keep its own copy of the read-only list',
    false === strpos($openapi, "'usertracking',")
);

$t->finish();
