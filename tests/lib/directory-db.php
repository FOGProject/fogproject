<?php
/**
 * Scratch-database bootstrap for the directory DB tests.
 *
 * Included by a test that has already checked FOG_TEST_DSN, never run on its
 * own. It creates the database fog_directory_test on the server FOG_TEST_DSN
 * names, builds the tables the directory code reads (hosts, hostDirectory as
 * schema 446 had it plus the schema 447 ALTERs, tasks, agentEnrollment) and
 * leaves host 7 ("B") with an empty hostDirectory row. It never touches any
 * other database: every statement below runs after DATABASE() is checked.
 *
 * Leaves these globals for the including test: $fake (the harness DB double,
 * already pointing at the real PDO), $pdo, $manifest and $database.
 * directory-db-connect.php, included here, has directoryTestPdo() for another
 * connection and directoryTestBindPdodb() for code that reaches the database
 * through the application's PDODB.
 *
 * Environment: FOG_TEST_DSN (mysql:host=...;port=...), FOG_TEST_USER (default
 * root) and FOG_TEST_PASS (default empty). The server needs CREATE on
 * fog_directory_test.
 */

require_once __DIR__ . '/directory-db-connect.php';
require_once __DIR__ . '/fog-schema-collector.php';

FogTestHarness::boot(isset($directoryDbName) ? $directoryDbName : 'directory-db');
$fake = FogTestHarness::fakeDb();

$admin = new \PDO(
    preg_replace('/;?dbname=[^;]*/i', '', (string)getenv('FOG_TEST_DSN')),
    false === getenv('FOG_TEST_USER') ? 'root' : getenv('FOG_TEST_USER'),
    false === getenv('FOG_TEST_PASS') ? '' : getenv('FOG_TEST_PASS'),
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$admin->exec('CREATE DATABASE IF NOT EXISTS `fog_directory_test`');
$admin = null;

$pdo = directoryTestPdo();
$fake->pdo = $pdo;
$database = 'fog_directory_test';

$manifest = require dirname(__DIR__, 2) . '/packages/web/commons/schema-expected.php';
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['hostDirectory', 'hosts', 'multicastSessions', 'tasks', 'agentEnrollment', 'globalSettings'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
// globalSettings: the application's own PDODB reads it on every model load.
foreach (['hosts', 'multicastSessions', 'tasks', 'agentEnrollment', 'globalSettings'] as $table) {
    $pdo->exec($manifest['tables'][$table]['create']);
}
// hostDirectory as schema 446 left it, then the real schema 447 statements.
$pdo->exec(
    preg_replace(
        '/`hdRejoin(?:Config|State)`\s+text\s+(?:DEFAULT NULL|NULL DEFAULT NULL),\s*/i',
        '',
        $manifest['tables']['hostDirectory']['create']
    )
);
$steps = fogCollectSchemaSteps(
    dirname(__DIR__, 2) . '/packages/web/commons/schema.php',
    $database,
    447
);
$step447 = $steps[446] ?? null;
if (!is_array($step447) || array_keys($step447) !== [0, 1]
    || strpos($step447[0], 'ALTER TABLE `hostDirectory` ADD COLUMN `hdRejoinConfig`') !== 0
    || strpos($step447[1], 'ALTER TABLE `hostDirectory` ADD COLUMN `hdRejoinState`') !== 0) {
    throw new \RuntimeException('schema 447 must be the two hostDirectory ALTERs at index 446');
}
foreach ($step447 as $statement) {
    $pdo->exec($statement);
}
$expectedColumns = $manifest['tables']['hostDirectory']['columns'];
$actualColumns = $pdo->query('SHOW COLUMNS FROM hostDirectory')->fetchAll(\PDO::FETCH_ASSOC);
if (array_column($actualColumns, 'Field') !== array_keys($expectedColumns)) {
    throw new \RuntimeException('migrated hostDirectory columns differ from schema-expected.php');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec(
    "INSERT INTO hosts (hostID,hostName,hostADDomain,hostUseAD,hostAgentFingerprint)"
    . " VALUES (7,'B','corp.example',1,'" . str_repeat('f', 64) . "')"
);
$pdo->exec('INSERT INTO hostDirectory (hdHostID) VALUES (7)');
