<?php
/**
 * The schema updater must be able to ALTER a table that carries a zero-date
 * DEFAULT.
 *
 * Forum 18266 -- 1.5.10.2253 to 1.5.10.2482 stopped at schema step 284 on
 * MySQL:
 *
 *   Update ID: 284  Error 1067: Invalid default value for 'hostSecTime'
 *   SQL: ALTER TABLE hosts MODIFY COLUMN hostLastDeploy DATETIME NULL ...
 *
 * The step names hostLastDeploy, and the error names hostSecTime. An ALTER
 * TABLE re-checks EVERY column's default, not only the one it changes. Tables
 * first built on MySQL 5.x gave a second TIMESTAMP NOT NULL column an implicit
 * DEFAULT '0000-00-00 00:00:00', and MySQL's stock sql_mode (NO_ZERO_DATE with
 * STRICT_TRANS_TABLES) refuses that default. PDODB cleared sql_mode on every
 * connection until GH-1245, so no update met the check before step 284 --
 * the step that removes the default. MariaDB's default sql_mode has no
 * NO_ZERO_DATE, which is why no MariaDB server showed it.
 *
 * Which columns carry such a default depends on each server's history, so no
 * step can list them. SchemaUpdaterPage drops NO_ZERO_DATE and
 * NO_ZERO_IN_DATE from its own session for the duration of the steps, and
 * puts the sql_mode back afterwards. This test drives those two helpers on a
 * scratch table under MySQL's stock flags. It also runs the same ALTER WITHOUT
 * them and requires 1067, so the fixture is shown to be the reported bug.
 *
 * Creates and drops one scratch table, so it runs only against a scratch
 * server and SKIPs otherwise -- never against a local install:
 *
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;port=13313;dbname=fog15' \
 *   FOG_TEST_USER=root FOG_TEST_PASS= \
 *   php tests/schema-updater-zero-date-defaults.test.php
 *
 * scripts/background_scripts/drive_schema_284_updater.sh drives the whole
 * updater against such a server.
 *
 * Usage: php tests/schema-updater-zero-date-defaults.test.php
 * Exit status 0 = pass (or skip), 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP: needs FOG_TEST_DSN (a scratch server; this test creates a table)\n";
    exit(0);
}

require __DIR__ . '/lib/scope-harness.php';

$reason = scopeHarnessDbReason();
if (null !== $reason) {
    echo "SKIP: $reason\n";
    exit(0);
}

$tmp = sys_get_temp_dir() . '/fog-zerodate-' . getmypid();
@mkdir($tmp . '/cache', 0700, true);
@mkdir($tmp . '/log', 0700, true);
@mkdir($tmp . '/plugins', 0700, true);
define('FOG_CACHE_DIR', $tmp . '/cache');
define('FOG_LOG_DIR', $tmp . '/log');
define('FOG_PLUGIN_DIR', $tmp . '/plugins');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once $web . '/commons/init.php';
new Initiator();
$dbProp = new \ReflectionProperty('FOGBase', 'DB');
$dbProp->setAccessible(true);
$db = new PDODB();
$dbProp->setValue(null, $db);

$relax = new \ReflectionMethod('SchemaUpdaterPage', '_relaxZeroDateChecks');
$relax->setAccessible(true);
$restore = new \ReflectionMethod('SchemaUpdaterPage', '_restoreSqlMode');
$restore->setAccessible(true);

$table = 'zzci_zerodate_' . getmypid();
register_shutdown_function(
    function () use ($db, $table, $tmp) {
        $db->query("DROP TABLE IF EXISTS `$table`");
        foreach (array('cache', 'log', 'plugins', '') as $d) {
            @rmdir($tmp . '/' . $d);
        }
    }
);

$mode = function () use ($db) {
    return (string) $db->query('SELECT @@SESSION.sql_mode AS `m`')
        ->fetch()
        ->get('m');
};
$alter = "ALTER TABLE `$table` MODIFY COLUMN `lastDeploy` DATETIME NULL DEFAULT NULL";
$failures = array();

// The shape forum 18266's hosts table had: the column the step changes, plus
// a second one whose default is a zero date. Built the way 1.5 built it, with
// the checks off.
$db->query("SET SESSION sql_mode = ''");
$db->query(
    "CREATE TABLE `$table` ("
    . '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,'
    . '`lastDeploy` DATETIME NOT NULL,'
    . "`secTime` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'"
    . ') ENGINE=InnoDB'
);
if ($db->sqlerror()) {
    echo 'FAIL: could not build the fixture: ' . $db->sqlerror() . "\n";
    exit(1);
}

// MySQL 8.0's stock flags, set explicitly so a MariaDB server tests the same
// thing.
$stock = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,'
    . 'ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
$db->query("SET SESSION sql_mode = '$stock'");
$before = $mode();

// Control: without the helpers this is the reported error.
$db->query($alter);
if (1067 !== (int) $db->errorCode) {
    $failures[] = sprintf(
        'control: the ALTER without the relaxed sql_mode answered %s, not 1067.'
        . ' The fixture no longer reproduces forum 18266.',
        var_export($db->errorCode, true)
    );
}

$saved = $relax->invoke(null);
$relaxed = $mode();
$db->query($alter);
$err = $db->sqlerror();
$restore->invoke(null, $saved);
$after = $mode();

if ($saved !== $before) {
    $failures[] = "_relaxZeroDateChecks() returned '$saved', not the mode it replaced ('$before').";
}
if (false !== stripos($relaxed, 'NO_ZERO')) {
    $failures[] = "relaxed sql_mode still holds a NO_ZERO flag: '$relaxed'.";
}
if (false === stripos($relaxed, 'STRICT_TRANS_TABLES')) {
    $failures[] = "relaxed sql_mode dropped STRICT_TRANS_TABLES: '$relaxed'. Only the two zero-date flags may go.";
}
if ($err) {
    $failures[] = "the ALTER failed under the relaxed sql_mode: $err";
}
if ($after !== $before) {
    $failures[] = "_restoreSqlMode() left '$after', not '$before'. Every later query on this connection runs relaxed.";
}

if ($failures) {
    echo "FAIL:\n  " . implode("\n  ", $failures) . "\n";
    exit(1);
}
echo "PASS: a zero-date default no longer blocks a schema step's ALTER (forum 18266)\n";
exit(0);
