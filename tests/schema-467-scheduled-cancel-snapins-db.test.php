<?php
/**
 * Schema step 467 adds scheduledTasks.stCancelSnapins and turns it on for the
 * scheduled Capture rows that already exist (forum 18325).
 *
 * Against a real server:
 *   - the REAL step 467 runs on a pre-step `scheduledTasks` table;
 *   - the column it makes is defined exactly as the manifest says, so a
 *     fresh install and an upgraded one agree;
 *   - rows of task type 2 (Capture) and 16 (Capture - Debug) get 1, and every
 *     other row (Deploy 1, Multicast 8, ...) keeps 0. Those rows were
 *     scheduled when a Capture canceled queued snapins silently (6c8aa43cb);
 *     a new row keeps the refusal unless its owner ticks the box.
 *
 * `scheduledTasks` here is a TEMPORARY table, so it shadows any real table
 * of that name for this connection only and vanishes when the test ends.
 *
 * Usage:
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;dbname=fog' \
 *   FOG_TEST_USER=... FOG_TEST_PASS=... \
 *   php tests/schema-467-scheduled-cancel-snapins-db.test.php
 *
 * With no dbname in the DSN, a scratch database is created and dropped.
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP schema 467 scheduled cancel snapins: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('schema-467-scheduled-cancel-snapins-db');
FogTestHarness::fakeDb();

$dsn = (string)getenv('FOG_TEST_DSN');
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$pdo = new \PDO(
    $dsn,
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$scratch = null;
if (!preg_match('/dbname=/i', $dsn)) {
    $scratch = 'fog_schema467_test';
    $pdo->exec("DROP DATABASE IF EXISTS `$scratch`");
    $pdo->exec("CREATE DATABASE `$scratch`");
    $pdo->exec("USE `$scratch`");
}

$failures = [];
$column = "`stCancelSnapins` tinyint(1) NOT NULL DEFAULT 0, ";

// ---- the table before the step ---------------------------------------------

$manifest = require dirname(__DIR__) . '/packages/web/commons/schema-expected.php';
$create = (string)($manifest['tables']['scheduledTasks']['create'] ?? '');
$pre = str_replace($column, '', $create, $removed);
if (1 !== $removed) {
    fwrite(STDERR, "FAIL: the manifest's scheduledTasks create does not carry the column ($removed found)\n");
    exit(1);
}
// The manifest's table has foreign-key indexes only (the keys are declared
// elsewhere), so the temporary copy needs no parent tables.
$pre = preg_replace(
    '/^CREATE TABLE IF NOT EXISTS/',
    'CREATE TEMPORARY TABLE',
    $pre
);
$pdo->exec($pre);

// Rows that exist before the step: types 1 Deploy, 2 Capture, 8 Multicast,
// 16 Capture - Debug.
$ids = [1 => 101, 2 => 102, 8 => 108, 16 => 116];
foreach ($ids as $type => $id) {
    $pdo->exec(
        'INSERT INTO `scheduledTasks` (`stID`, `stType`, `stTaskTypeID`, '
        . '`stGroupHostID`) VALUES (' . $id . ", 'C', $type, 1)"
    );
}

// Required AFTER boot, as the other step tests do.
require __DIR__ . '/lib/fog-schema-collector.php';
$steps = fogCollectSchemaSteps(
    dirname(__DIR__) . '/packages/web/commons/schema.php',
    'fog',
    467
);
$step = $steps[466] ?? null;
if (!is_array($step) || !$step
    || false === strpos(implode("\n", array_filter($step, 'is_string')), 'stCancelSnapins')
) {
    fwrite(STDERR, "FAIL: schema 467 must be the stCancelSnapins step at index 466\n");
    exit(1);
}

// ---- run the step ------------------------------------------------------------

try {
    foreach ($step as $sql) {
        if (is_string($sql)) {
            $pdo->exec($sql);
        } else {
            $failures[] = 'step 467 holds a non-string element';
        }
    }
} catch (\Throwable $e) {
    $failures[] = 'the step threw: ' . $e->getMessage();
}

$shown = (string)$pdo->query('SHOW CREATE TABLE `scheduledTasks`')->fetch(\PDO::FETCH_NUM)[1];
$definition = $manifest['tables']['scheduledTasks']['columns']['stCancelSnapins'] ?? '';
if (false === strpos($shown, "`stCancelSnapins` $definition")) {
    $failures[] = "the server made something other than the manifest's '$definition'";
}

$got = $pdo->query(
    'SELECT `stTaskTypeID` AS t, `stCancelSnapins` AS c FROM `scheduledTasks` ORDER BY `stID`'
)->fetchAll(\PDO::FETCH_KEY_PAIR);
$want = [1 => '0', 2 => '1', 8 => '0', 16 => '1'];
foreach ($want as $type => $value) {
    if ((string)($got[$type] ?? 'missing') !== $value) {
        $failures[] = "task type $type: stCancelSnapins is "
            . var_export($got[$type] ?? null, true) . ", want $value";
    }
}

// A row written after the step, without the column, keeps the refusal.
$pdo->exec(
    'INSERT INTO `scheduledTasks` (`stID`, `stType`, `stTaskTypeID`, '
    . "`stGroupHostID`) VALUES (200, 'C', 2, 1)"
);
$new = (string)$pdo->query('SELECT `stCancelSnapins` FROM `scheduledTasks` WHERE `stID` = 200')->fetchColumn();
if ('0' !== $new) {
    $failures[] = "a new Capture row defaults to $new, want 0";
}

$pdo->exec('DROP TEMPORARY TABLE IF EXISTS `scheduledTasks`');
if (null !== $scratch) {
    $pdo->exec("DROP DATABASE IF EXISTS `$scratch`");
}

if ($failures) {
    fwrite(STDERR, "FAIL: schema 467 scheduled cancel snapins\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}
echo "PASS  schema 467 adds the column and sets 1 only on Capture rows\n";
exit(0);
