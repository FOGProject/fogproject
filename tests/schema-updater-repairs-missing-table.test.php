<?php
/**
 * A step that names a table the database never got repairs and retries.
 *
 * `schemaVersion`.`vValue` is a count of applied array elements, and 1.5.x
 * and 1.6 fill the same positions with different migrations (see
 * SchemaReconciler's docblock). 1.5.10.2518 arrives at 288, so 1.6's steps
 * `// 287` (CREATE TABLE fileDeleteQueue) and `// 288` are skipped and
 * `// 290` ALTERs a table that is not there: error 1146. 1146 is not a
 * tolerated code, so the update stopped, the reconcile after the loop then
 * created the table, and the installer still printed "Failed!" with the
 * server at 289. A second run went through -- after the admin had been told
 * the one-way upgrade failed.
 *
 * SchemaUpdaterPage::_repairAndRetry() takes that second run's path inside
 * the first: on 1146 it runs the repair (SchemaReconciler::reconcile() in
 * production) and executes the same statement once more. These checks pin
 * that, and pin that nothing else changes: any other error is left to the
 * caller, a failed repair is not retried, and a retry that still fails is
 * not retried again.
 *
 * No database: FogFakeDb plays the server and the repair is injected.
 *
 * Usage: php tests/schema-updater-repairs-missing-table.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('schema-repair-retry');

$t = new FogChecks();
$db = FogTestHarness::fakeDb();

$retry = new \ReflectionMethod('FOG\\Pages\\SchemaUpdaterPage', '_repairAndRetry');
$retry->setAccessible(true);

$skiperrs = [1050, 1054, 1060, 1061, 1062, 1091];
$alter = 'ALTER TABLE `fileDeleteQueue` ADD `fdqPathType` VARCHAR(255) NOT NULL';

/**
 * Counters the closures below write into. An object rather than by-reference
 * ints, so each check reads what the code under test actually did.
 */
$seen = new \stdClass();

/**
 * Answer $alter with $codes in turn (0 = success) and everything else with
 * success. Resets the counters.
 */
$serve = static function (array $codes) use ($db, $alter, $seen) {
    $seen->runs = 0;
    $seen->repairs = 0;
    $db->responder = static function ($sql) use ($db, $alter, &$codes, $seen) {
        $code = 0;
        if ($sql === $alter) {
            $seen->runs++;
            $code = $codes ? array_shift($codes) : 0;
        }
        $db->error = $code ? "error $code" : false;
        $db->errorCode = $code ?: false;
        return [];
    };
};

$repairOk = static function () use ($seen) {
    $seen->repairs++;
    return true;
};

// --- 1146, the repair creates the table, the retry succeeds -----------------
$serve([0]);
$ok = $retry->invoke(null, $alter, 1146, $skiperrs, $repairOk);
$t->check('1146: reported as handled', $ok === true);
$t->check('1146: the repair ran once', $seen->repairs === 1);
$t->check('1146: the statement ran again', $seen->runs === 1);

// --- 1146, the retry hits a tolerated code (the repair added the column) ----
$serve([1060]);
$ok = $retry->invoke(null, $alter, 1146, $skiperrs, $repairOk);
$t->check('1146 then 1060: handled like any tolerated duplicate', $ok === true);

// --- 1146, the retry still fails --------------------------------------------
$serve([1146]);
$ok = $retry->invoke(null, $alter, 1146, $skiperrs, $repairOk);
$t->check('1146 twice: left to the caller', $ok === false);
$t->check('1146 twice: retried once, not looped', $seen->runs === 1 && $seen->repairs === 1);
$t->check('1146 twice: the caller sees the statement\'s own error', $db->errorCode === 1146);

// --- 1146, the repair itself fails ------------------------------------------
$serve([1146]);
$repairFails = static function () use ($seen) {
    $seen->repairs++;
    return 'reconcile failed';
};
$ok = $retry->invoke(null, $alter, 1146, $skiperrs, $repairFails);
$t->check('failed repair: left to the caller', $ok === false);
$t->check('failed repair: the caller sees the statement\'s own error', $db->errorCode === 1146);

// --- any other error is not this method's business --------------------------
foreach ([1064, 1452, 1005] as $code) {
    $serve([]);
        $ok = $retry->invoke(null, $alter, $code, $skiperrs, $repairOk);
    $t->check("$code: left to the caller", $ok === false);
    $t->check("$code: no repair, no retry", $seen->repairs === 0 && $seen->runs === 0);
}

// --- the production call site ------------------------------------------------
$src = file_get_contents(dirname(__DIR__) . '/packages/web/src/Pages/SchemaUpdaterPage.php');
$t->check(
    'update() consults _repairAndRetry() after the tolerated codes',
    (bool)preg_match(
        '/in_array\(\$err, \$skiperrs\)\)\s*\{\s*continue;\s*\}\s*if \(self::_repairAndRetry\(\$update, \$err, \$skiperrs\)\)\s*\{\s*continue;\s*\}/',
        $src
    )
);

$t->finish();
