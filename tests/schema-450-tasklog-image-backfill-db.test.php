<?php
/**
 * Schema step 450 backfills taskLog.logImageName on rows written before
 * step 351 added the column (forum 18305).
 *
 * ImagingStats' fold counts only rows with an image name, so an upgraded
 * 1.5 install showed none of its history on the dashboard chart. This runs
 * the REAL step 450 closure against a scratch database seeded with 1.5-era
 * rows, and checks that it fills only what recordState() would have filled:
 * state rows, of an imaging task, whose task and image still exist, that
 * are still blank.
 *
 * Usage:
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' \
 *   FOG_TEST_USER=root FOG_TEST_PASS= \
 *   php tests/schema-450-tasklog-image-backfill-db.test.php
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP schema 450 backfill: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('schema-450-tasklog-image-backfill-db');
FogTestHarness::fakeDb();

$db = 'fog_schema450_test';
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

// The three tables the step joins, as the release ships them.
$manifest = require dirname(__DIR__) . '/packages/web/commons/schema-expected.php';
foreach (['taskLog', 'tasks', 'images'] as $table) {
    $pdo->exec($manifest['tables'][$table]['create']);
}

// Required AFTER boot, so FOG's own autoloader answers TaskType and TaskLog
// and the collector only stubs what FOG does not have.
require __DIR__ . '/lib/fog-schema-collector.php';
$steps = fogCollectSchemaSteps(
    dirname(__DIR__) . '/packages/web/commons/schema.php',
    $db,
    450
);
$step = $steps[449] ?? null;
if (!is_array($step) || 1 !== count($step) || !($step[0] instanceof \Closure)
    || false === strpos(
        file_get_contents((new \ReflectionFunction($step[0]))->getFileName()),
        'Forum 18305: backfill taskLog.logImageName'
    )
) {
    fwrite(STDERR, "FAIL: schema 450 must be the logImageName backfill closure at index 449\n");
    exit(1);
}

// The closure reaches the database as self::$DB, which is the collector's.
class Schema450Db
{
    public $pdo;
    public function query($sql, ...$rest)
    {
        $this->pdo->exec($sql);
        return $this;
    }
}
SchemaCollector::$DB = new Schema450Db();
SchemaCollector::$DB->pdo = $pdo;

// Seeds. Task types: 1 deploy, 2 capture, 12 snapin (not imaging). Seeded
// with sql_mode cleared, so the seed names only the columns the step reads;
// the step itself runs under strict mode below.
$pdo->exec("SET SESSION sql_mode = ''");
$pdo->exec(
    "INSERT INTO `images` (`imageID`, `imageName`, `imagePath`) VALUES "
    . "(7, 'BigBox', 'BigBox'), (8, 'Small', 'Small')"
);
$pdo->exec(
    "INSERT INTO `tasks` (`taskID`, `taskTypeID`, `taskImageID`) VALUES "
    . "(1, 1, 7), (2, 2, 8), (3, 12, 7), (4, 1, 99), (6, 1, 7)"
);
$pdo->exec(
    "INSERT INTO `taskLog` (`id`, `taskID`, `logType`, `logImageName`) VALUES "
    // a 1.5-era deploy and capture
    . "(1, 1, 'state', ''), (2, 1, 'state', ''), (3, 2, 'state', ''), "
    // a snapin task: not imaging, stays blank
    . "(4, 3, 'state', ''), "
    // image deleted since: nothing records which image ran
    . "(5, 4, 'state', ''), "
    // task deleted since
    . "(6, 5, 'state', ''), "
    // a FOS report row: recordState() never names an image on these
    . "(7, 1, 'error', ''), "
    // already named: not overwritten
    . "(8, 6, 'state', 'Renamed')"
);

$pdo->exec(
    "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,"
    . "NO_ENGINE_SUBSTITUTION'"
);

$failures = [];
try {
    $step[0]();
    // A re-run must converge, not error or change anything.
    $step[0]();
} catch (\Throwable $e) {
    $failures[] = 'the step threw: ' . $e->getMessage();
}

$got = $pdo->query('SELECT `id`, `logImageName` FROM `taskLog` ORDER BY `id`')
    ->fetchAll(\PDO::FETCH_KEY_PAIR);
$want = [
    1 => 'BigBox', 2 => 'BigBox', 3 => 'Small',
    4 => '', 5 => '', 6 => '', 7 => '', 8 => 'Renamed'
];
foreach ($want as $id => $name) {
    if (($got[$id] ?? null) !== $name) {
        $failures[] = "taskLog row $id: want '$name', got '"
            . ($got[$id] ?? 'missing') . "'";
    }
}

$pdo->exec("DROP DATABASE IF EXISTS `$db`");

if ($failures) {
    fwrite(STDERR, "FAIL: schema 450 backfill\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}
echo "PASS  schema 450 backfills logImageName only where recordState() would\n";
exit(0);
