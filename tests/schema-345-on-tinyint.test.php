<?php
/**
 * Schema step 345 (the GH-1245 ENUM repair) must run when a column it names
 * is already TINYINT(1).
 *
 * SchemaUpdaterPage::update() runs SchemaReconciler::reconcile() even after a
 * step fails, and the reconciler adds a missing column in its schema-expected
 * type. So a server whose earlier update stopped short reaches 345 with
 * 1.6-only columns such as `snapinJobs`.`sjAbortOnFail` already TINYINT(1).
 * `WHERE tinyint_col = ''` then raises 1292 "Truncated incorrect DECIMAL
 * value" under strict sql_mode, and the schema deploy answers HTTP 500.
 *
 * Builds every table the step names in a scratch database: the hostMAC
 * columns as ENUM('0','1') holding the error value, so the repair itself is
 * still proven; every other boolean column as TINYINT(1) with rows; the three
 * non-boolean enums with their error value. Then runs the step's statements
 * under strict sql_mode.
 *
 * Usage:
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' \
 *   FOG_TEST_USER=root FOG_TEST_PASS= \
 *   php tests/schema-345-on-tinyint.test.php
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

$dsn = getenv('FOG_TEST_DSN');
if ($dsn === false || $dsn === '') {
    echo "SKIP  no FOG_TEST_DSN set; schema step 345 not checked on a server\n";
    exit(0);
}
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$user = ($user === false) ? 'root' : $user;
$pass = ($pass === false) ? '' : $pass;

$db = 'fog_schema345_test';

require __DIR__ . '/lib/fog-schema-collector.php';
$steps = fogCollectSchemaSteps(
    dirname(__DIR__) . '/packages/web/commons/schema.php',
    $db,
    0
);

// Found by content, not index: the step that repairs sjAbortOnFail.
$step = null;
foreach ($steps as $s) {
    foreach ((array)$s as $q) {
        if (is_string($q) && preg_match('/^UPDATE `snapinJobs` SET `sjAbortOnFail`/', $q)) {
            $step = $s;
            break 2;
        }
    }
}
if (null === $step) {
    fwrite(STDERR, "FAIL: could not find the sjAbortOnFail repair step\n");
    exit(1);
}

// table => column => [type, value to seed]. Seeded under sql_mode='' so an
// ENUM can hold the error value ('').
$columns = [];
foreach ($step as $q) {
    if (!preg_match("/^UPDATE `(\w+)` SET `(\w+)` = '(\w+)'/", $q, $m)) {
        continue;
    }
    if ('0' !== $m[3]) {
        $columns[$m[1]][$m[2]] = ["ENUM('" . $m[3] . "','x') NOT NULL", "''"];
    } elseif ('hostMAC' === $m[1]) {
        $columns[$m[1]][$m[2]] = ["ENUM('0','1') NOT NULL DEFAULT '0'", "''"];
    } else {
        $columns[$m[1]][$m[2]] = ['TINYINT(1) NOT NULL DEFAULT 0', '1'];
    }
}

try {
    $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("DROP DATABASE IF EXISTS `$db`");
    $pdo->exec("CREATE DATABASE `$db`");
    $pdo->exec("USE `$db`");
    $pdo->exec("SET SESSION sql_mode = ''");
    foreach ($columns as $table => $cols) {
        $defs = [];
        $vals = [];
        foreach ($cols as $col => $spec) {
            $defs[] = "`$col` " . $spec[0];
            $vals[] = $spec[1];
        }
        $pdo->exec("CREATE TABLE `$table` (" . implode(', ', $defs) . ')');
        $pdo->exec("INSERT INTO `$table` VALUES (" . implode(', ', $vals) . ')');
    }
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
} catch (\PDOException $e) {
    fwrite(STDERR, 'FAIL: cannot build the scratch database: ' . $e->getMessage() . "\n");
    exit(1);
}

$failures = [];
foreach ($step as $q) {
    if (!is_string($q)) {
        continue;
    }
    try {
        $pdo->exec($q);
    } catch (\PDOException $e) {
        $failures[] = "$q\n      " . $e->getMessage();
    }
}

// The repair still repairs: the ENUM error value becomes '0'.
$left = (int)$pdo->query(
    "SELECT COUNT(*) FROM `hostMAC` WHERE CAST(`hmPrimary` AS CHAR) <> '0'"
)->fetchColumn();
if (0 !== $left) {
    $failures[] = 'hostMAC.hmPrimary error value was not repaired to 0';
}
// And a TINYINT keeps its value.
$abort = (int)$pdo->query('SELECT `sjAbortOnFail` FROM `snapinJobs`')->fetchColumn();
if (1 !== $abort) {
    $failures[] = "snapinJobs.sjAbortOnFail changed from 1 to $abort";
}

$pdo->exec("DROP DATABASE IF EXISTS `$db`");

if ($failures) {
    fwrite(STDERR, "FAIL: schema step 345 on TINYINT columns\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}
echo 'PASS  schema step 345 runs on TINYINT columns (' . count($step) . " statements)\n";
exit(0);
