<?php
/**
 * system/info and the OpenAPI document publish the real list row cap.
 *
 * OpenAPI::pagingLimits() read FOGManagerController::MAX_ROWS through
 * `new \ReflectionClass('FOGManagerController')`. Since ADR 0013 the core
 * classes are no longer aliased into the global namespace, so that bare name
 * does not resolve: the ReflectionException was caught, maxRows came back
 * null on every server, and each call logged "FOG autoloader:
 * "FOGManagerController" is a core class and core is no longer aliased".
 * A client reading system/info was told there is no row cap while a list
 * request was in fact capped at MAX_ROWS.
 *
 * The spelling itself is guarded tree-wide by
 * tests/string-class-literals-are-qualified.test.php; this pins the value.
 *
 * DB-free: the method only reflects two class constants.
 *
 * Usage: php tests/paging-limits-report-max-rows.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('paging-limits');
FogTestHarness::fakeDb();

$t = new FogChecks();

$limits = FOG\Router\OpenAPI::pagingLimits();

$t->check(
    'maxRows is the FOGManagerController::MAX_ROWS constant, not null',
    $limits['maxRows'] === FOG\Base\FOGManagerController::MAX_ROWS
);
$t->check(
    'expandMaxItems is the Route::EXPAND_MAX_ITEMS constant',
    $limits['expandMaxItems'] === FOG\Router\Route::EXPAND_MAX_ITEMS
);

$t->finish();
