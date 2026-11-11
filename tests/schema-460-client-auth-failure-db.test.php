<?php
/**
 * Schema step 460 adds the legacy client's failure run to `hosts`, and the
 * SQL that writes it does what Host says it does (forum topic 18312).
 *
 * Against a real server:
 *   - the REAL step 460 runs on a pre-step `hosts` table;
 *   - the four columns it makes are defined exactly as the manifest says,
 *     so a fresh install and an upgraded one agree;
 *   - Host::recordAuthFailure()'s statement, executed twice, counts two,
 *     keeps the FIRST failure time and the LAST code and address;
 *   - Host::clearAuthFailure()'s statement puts all four back to their
 *     defaults, the time to NULL rather than a zero date.
 *
 * The statements are the ones Host actually builds: they are captured from
 * the harness's fake connection and replayed here with their bound values.
 *
 * `hosts` here is a TEMPORARY table, so it shadows any real table of that
 * name for this connection only and vanishes when the test ends. That lets
 * the test run against a live FOG database with an account that cannot
 * create databases. The pre-step table is the manifest's own `create` with
 * the four columns taken out, and the test fails if they were not there to
 * take out.
 *
 * Usage:
 *   FOG_TEST_DSN='mysql:host=127.0.0.1;dbname=fog' \
 *   FOG_TEST_USER=... FOG_TEST_PASS=... \
 *   php tests/schema-460-client-auth-failure-db.test.php
 *
 * With no dbname in the DSN, a scratch database is created and dropped.
 *
 * Exit status 0 = pass or skip, 1 = fail.
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP schema 460 client auth failure: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('schema-460-client-auth-failure-db');
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
    $scratch = 'fog_schema460_test';
    $pdo->exec("DROP DATABASE IF EXISTS `$scratch`");
    $pdo->exec("CREATE DATABASE `$scratch`");
    $pdo->exec("USE `$scratch`");
}

$failures = [];
$added = [
    'hostAuthFailSince' => "`hostAuthFailSince` datetime DEFAULT NULL, ",
    'hostAuthFailCode' => "`hostAuthFailCode` varchar(8) NOT NULL DEFAULT '', ",
    'hostAuthFailCount' => "`hostAuthFailCount` int(10) unsigned NOT NULL DEFAULT 0, ",
    'hostAuthFailIP' => "`hostAuthFailIP` varchar(45) NOT NULL DEFAULT '', ",
];

// ---- the table before the step ---------------------------------------------

$manifest = require dirname(__DIR__) . '/packages/web/commons/schema-expected.php';
$create = (string)($manifest['tables']['hosts']['create'] ?? '');
$pre = str_replace(array_values($added), '', $create, $removed);
if (4 !== $removed) {
    fwrite(STDERR, "FAIL: the manifest's hosts create does not carry the four columns ($removed found)\n");
    exit(1);
}
$pre = preg_replace(
    '/^CREATE TABLE IF NOT EXISTS/',
    'CREATE TEMPORARY TABLE',
    $pre
);
$pdo->exec($pre);

// Required AFTER boot, as the step 450 and 458 tests do.
require __DIR__ . '/lib/fog-schema-collector.php';
$steps = fogCollectSchemaSteps(
    dirname(__DIR__) . '/packages/web/commons/schema.php',
    'fog',
    460
);
$step = $steps[459] ?? null;
if (!is_array($step) || !$step
    || false === strpos(implode("\n", array_filter($step, 'is_string')), 'hostAuthFail')
) {
    fwrite(STDERR, "FAIL: schema 460 must be the hostAuthFail step at index 459\n");
    exit(1);
}

// ---- run the step, compare with the manifest --------------------------------

try {
    foreach ($step as $sql) {
        if (is_string($sql)) {
            $pdo->exec($sql);
        } else {
            $failures[] = 'step 460 holds a non-string element';
        }
    }
} catch (\Throwable $e) {
    $failures[] = 'the step threw: ' . $e->getMessage();
}

$shown = (string)$pdo->query('SHOW CREATE TABLE `hosts`')->fetch(\PDO::FETCH_NUM)[1];
foreach ($manifest['tables']['hosts']['columns'] as $name => $definition) {
    if (!isset($added[$name])) {
        continue;
    }
    if (false === strpos($shown, "`$name` $definition")) {
        $failures[] = "$name: the server made something other than the manifest's '$definition'";
    }
}

// ---- the writers' real statements -------------------------------------------

$fake = FogTestHarness::getStatic('FOGBase', 'DB');
$captured = [];
$fake->responder = function ($sql, $params) use (&$captured) {
    if (0 === strpos($sql, 'UPDATE `hosts`')) {
        $captured[] = [$sql, $params];
    }
    return null;
};
$replay = function () use (&$captured, $pdo) {
    foreach ($captured as $c) {
        $pdo->prepare($c[0])->execute($c[1]);
    }
    $captured = [];
};
$read = function () use ($pdo) {
    return $pdo->query(
        'SELECT `hostAuthFailSince` AS s, `hostAuthFailCode` AS c, '
        . '`hostAuthFailCount` AS n, `hostAuthFailIP` AS ip '
        . 'FROM `hosts` WHERE `hostID` = 41'
    )->fetch(\PDO::FETCH_ASSOC);
};

$pdo->exec("INSERT INTO `hosts` (`hostID`, `hostName`) VALUES (41, 'LAB-41')");
$Host = new \FOG\Items\Host();
$Host->set('id', 41)->set('name', 'LAB-41');

FogTestHarness::setStatic('FOGBase', 'remoteaddr', '10.1.2.3');
$Host->recordAuthFailure('ist');
$replay();
$first = $read();
// Move the stored time back, so a second write that overwrote it would show.
$pdo->exec("UPDATE `hosts` SET `hostAuthFailSince` = '2026-01-02 03:04:05' WHERE `hostID` = 41");

FogTestHarness::setStatic('FOGBase', 'remoteaddr', '10.9.9.9');
$Host->recordAuthFailure('dec');
$replay();
$second = $read();

if (null === ($first['s'] ?? null) || '1' !== (string)($first['n'] ?? '')) {
    $failures[] = 'the first failure did not set a time and a count of 1';
}
if ('2026-01-02 03:04:05' !== ($second['s'] ?? null)) {
    $failures[] = "the second failure moved the run's start: " . var_export($second['s'] ?? null, true);
}
if ('2' !== (string)($second['n'] ?? '')
    || 'dec' !== ($second['c'] ?? null)
    || '10.9.9.9' !== ($second['ip'] ?? null)
) {
    $failures[] = 'the second failure did not count 2 with the last code and address: '
        . json_encode($second);
}

$Host->set('authFailCount', 2);
$Host->clearAuthFailure();
$replay();
$cleared = $read();
if (null !== $cleared['s'] || '' !== $cleared['c']
    || '0' !== (string)$cleared['n'] || '' !== $cleared['ip']
) {
    $failures[] = 'the clear left: ' . json_encode($cleared);
}

$pdo->exec('DROP TEMPORARY TABLE IF EXISTS `hosts`');
if (null !== $scratch) {
    $pdo->exec("DROP DATABASE IF EXISTS `$scratch`");
}

if ($failures) {
    fwrite(STDERR, "FAIL: schema 460 client auth failure\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}
echo "PASS  schema 460 matches the manifest, and the run is counted, kept and cleared\n";
exit(0);
