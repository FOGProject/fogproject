<?php
/**
 * Schema step 458 adds os.osFamily and backfills it by osID
 * (fogproject#2083).
 *
 * FOS used to decide "Windows, Linux or macOS" from a hardcoded list of osID
 * numbers. The family now lives in the os row. This runs the REAL step 458
 * statements against a scratch database whose os table is in its pre-step
 * shape, seeded the way a 1.5 or 1.6 install seeds it, and checks:
 *   - each seeded osID gets the family the issue lists;
 *   - a row an admin inserted by hand at a non-seeded osID gets 'other',
 *     because the backfill keys on osID and never on osName.
 *
 * The pre-step os table is a fixed string below, copied from
 * commons/schema-expected.php on the base before step 458. It is NOT read
 * from the manifest or from git at run time: after this change the manifest
 * carries osFamily, and the step's ADD COLUMN would then fail as a duplicate.
 *
 * Usage:
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;port=13358' \
 *   FOG_TEST_USER=root FOG_TEST_PASS= \
 *   php tests/schema-458-os-family-db.test.php
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP schema 458 os family: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('schema-458-os-family-db');
FogTestHarness::fakeDb();

$db = 'fog_schema458_test';
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$pdo = new \PDO(
    preg_replace('/;?dbname=[^;]*/i', '', (string)getenv('FOG_TEST_DSN')),
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("DROP DATABASE IF EXISTS `$db`");
$pdo->exec("CREATE DATABASE `$db`");
$pdo->exec("USE `$db`");

// The os table before step 458.
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `os` ( `osID` mediumint(9) NOT NULL '
    . 'AUTO_INCREMENT, `osName` varchar(30) NOT NULL, `osDescription` text '
    . 'NOT NULL DEFAULT \'\', PRIMARY KEY (`osID`), UNIQUE KEY `osName` '
    . '(`osName`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 '
    . 'COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC'
);

// Required AFTER boot, as the step 450 test does.
require __DIR__ . '/lib/fog-schema-collector.php';
$steps = fogCollectSchemaSteps(
    dirname(__DIR__) . '/packages/web/commons/schema.php',
    $db,
    458
);
$step = $steps[457] ?? null;
if (!is_array($step) || !$step
    || false === strpos(implode("\n", array_filter($step, 'is_string')), 'osFamily')
) {
    fwrite(STDERR, "FAIL: schema 458 must be the osFamily step at index 457\n");
    exit(1);
}

// The seeds, as schema.php inserts them, plus an admin's hand-made rows.
$pdo->exec(
    "INSERT INTO `os` (`osID`, `osName`, `osDescription`) VALUES "
    . "(1, 'Windows 2000/XP', ''), (2, 'Windows Vista', ''), "
    . "(3, 'Windows 98', ''), (4, 'Windows Other', ''), "
    . "(5, 'Windows 7', ''), (6, 'Windows 8', ''), (7, 'Windows 8.1', ''), "
    . "(8, 'Apple Mac OS', ''), (9, 'Windows 10', ''), "
    . "(10, 'Windows 11', ''), (11, 'Windows Server', ''), "
    . "(50, 'Linux', ''), (51, 'Chromium OS', 'Chromium OS'), "
    . "(99, 'Other', ''), "
    // An admin's rows at non-seeded ids. The names say Windows and Linux on
    // purpose: the backfill must not read them.
    . "(100, 'Windows 12', ''), (77, 'My Linux', '')"
);

$failures = [];
try {
    $pdo->query('SELECT `osFamily` FROM `os`');
    $failures[] = 'the os table already has osFamily before the step';
} catch (\Throwable $e) {
    // Expected: the column does not exist yet.
}

try {
    foreach ($step as $sql) {
        if (is_string($sql)) {
            $pdo->exec($sql);
        } else {
            $failures[] = 'step 458 holds a non-string element';
        }
    }
} catch (\Throwable $e) {
    $failures[] = 'the step threw: ' . $e->getMessage();
}

$got = $pdo->query('SELECT `osID`, `osFamily` FROM `os` ORDER BY `osID`')
    ->fetchAll(\PDO::FETCH_KEY_PAIR);
$want = [
    1 => 'windows', 2 => 'windows', 3 => 'windows', 4 => 'windows',
    5 => 'windows', 6 => 'windows', 7 => 'windows', 9 => 'windows',
    10 => 'windows', 11 => 'windows',
    8 => 'macos',
    50 => 'linux', 51 => 'linux',
    99 => 'other',
    // Non-seeded ids get the column default, whatever their name says.
    100 => 'other', 77 => 'other',
];
foreach ($want as $id => $family) {
    if (($got[$id] ?? null) !== $family) {
        $failures[] = "os row $id: want '$family', got '"
            . ($got[$id] ?? 'missing') . "'";
    }
}

// A row inserted after the step, with no family named, also gets the default.
try {
    $pdo->exec(
        "INSERT INTO `os` (`osID`, `osName`, `osDescription`) "
        . "VALUES (200, 'Later', '')"
    );
    $later = $pdo->query('SELECT `osFamily` FROM `os` WHERE `osID` = 200')
        ->fetchColumn();
    if ('other' !== $later) {
        $failures[] = "a row inserted after the step: want 'other', got '$later'";
    }
} catch (\Throwable $e) {
    $failures[] = 'a row inserted after the step failed: ' . $e->getMessage();
}

$pdo->exec("DROP DATABASE IF EXISTS `$db`");

if ($failures) {
    fwrite(STDERR, "FAIL: schema 458 os family\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}
echo "PASS  schema 458 backfills osFamily by osID and defaults the rest to other\n";
exit(0);
