<?php
/**
 * PDODB::query() reports the error code of the statement that just failed,
 * not the one before it.
 *
 * FOG connects with native prepares (ATTR_EMULATE_PREPARES false), so a
 * statement the server rejects at PREPARE time -- a missing table, a syntax
 * error, DDL the server cannot run -- throws from prepare() and leaves no
 * statement handle. query() only read the code from a statement handle, so
 * it kept the previous failure's number. The schema updater acts on that
 * number: a stale 1060 or 1061 made it skip a real failure as "already
 * applied", and a missing table (1146) never reached the repair step.
 *
 * Usage: FOG_TEST_DSN=... php tests/pdodb-error-code-db.test.php
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP PDODB error code DB: FOG_TEST_DSN not set\n";
    exit(0);
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('pdodb-error-code-db');

$database = 'fog_pdodb_errcode_test';
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$dsn = rtrim(preg_replace('/;?dbname=[^;]*/i', '', (string)getenv('FOG_TEST_DSN')), ';');
$admin = new \PDO(
    $dsn,
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$admin->exec("DROP DATABASE IF EXISTS `$database`");
$admin->exec("CREATE DATABASE `$database`");
$admin = null;

// Not tests/lib/directory-db-connect.php: its helpers pin fog_directory_test
// and do not turn emulated prepares off. The same attributes
// PDODB::_connect() sets, because native prepares are the point.
$pdo = new \PDO(
    $dsn . ';dbname=' . $database,
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
// Drop the scratch database even if a check throws.
register_shutdown_function(static function () use ($pdo, $database) {
    $pdo->exec("DROP DATABASE IF EXISTS `$database`");
});
$pdo->exec('CREATE TABLE `t` (`id` int NOT NULL PRIMARY KEY)');
// query()'s failure path logs. The logger reads globalSettings, which is
// created here. It also reads storageEpoch for the log timestamp, and
// storageEpoch is NOT created, on purpose: every schema step before the one
// that creates storageEpoch runs without it. The logger's SELECT on
// storageEpoch then fails with 1146 on the first log line, and that must not
// replace the code of the statement being logged.
// Only the two columns getSetting() reads, so the table builds on MySQL too.
$pdo->exec(
    'CREATE TABLE `globalSettings` (`settingKey` varchar(255) NOT NULL PRIMARY KEY,'
    . ' `settingValue` text)'
);

FogTestHarness::setStatic(FOG\Db\PDODB::class, '_link', $pdo);
FogTestHarness::setStatic(FOG\Db\PDODB::class, '_dbName', $database);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);

$t = new FogChecks();

$db->query('INSERT INTO `t` VALUES (1)');
$db->query('INSERT INTO `t` VALUES (1)');
$t->check('a duplicate key fails at EXECUTE with 1062, not the logger\'s own 1146', 1062 === (int)$db->errorCode);
$t->check('and lastErrorInfo still describes it', 1062 === (int)($db->lastErrorInfo[1] ?? 0));

$db->query('SELECT * FROM `no_such_table`');
$t->check('a missing table fails at PREPARE: errorCode is 1146, not the stale 1062', 1146 === (int)$db->errorCode);

$db->query('INSERT INTO `t` VALUES (1)');
$db->query('ALTER TABLE `t` ADD COLUMN `x` int,,');
$t->check('a syntax error fails at PREPARE: errorCode is 1064, not the stale 1062', 1064 === (int)$db->errorCode);

$t->finish();
