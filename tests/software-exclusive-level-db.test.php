<?php
/**
 * The host software level, on a real server (schema 470).
 *
 * Drives the real code against real tables:
 *
 *   - schema 470 adds hostSoftwareLevel to a hosts table in its pre-470
 *     shape, empty by default, so every existing host is Assigned;
 *   - SoftwareSet::desired() carries no 'manage' key for an Assigned host
 *     (so no other host's state revision changes) and 'manage' =>
 *     'exclusive' for an Exclusive one;
 *   - disabled entries, and enabled entries an old agent cannot honor, land
 *     in 'retain' on an Exclusive host and nowhere else;
 *   - SoftwareSet::levelOf() reads a stored value one way for everyone.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 * The database is always fog_swlevel_test.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/software-exclusive-level-db.test.php
 * Exit status 0 = pass or skip, 1 = fail.
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP software exclusive level DB: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/directory-db-connect.php';
require __DIR__ . '/lib/fog-schema-collector.php';

FogTestHarness::boot('software-exclusive-level-db');
$fake = FogTestHarness::fakeDb();

$db = 'fog_swlevel_test';
$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$admin = new \PDO(
    preg_replace('/;?dbname=[^;]*/i', '', (string)getenv('FOG_TEST_DSN')),
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$admin->exec("DROP DATABASE IF EXISTS `$db`");
$admin->exec("CREATE DATABASE `$db`");
$pdo = new \PDO(
    directoryTestDsn($db),
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]
);
$manifest = require dirname(__DIR__) . '/packages/web/commons/schema-expected.php';

use FOG\Agent\SoftwareSet;
use FOG\Items\Host;

$t = new FogChecks();

// ---------------------------------------------------------------------------
// 1. Schema 470 on tables in their pre-470 shape: every manifest table, with
//    hosts lacking hostSoftwareLevel.
// ---------------------------------------------------------------------------
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table => $spec) {
    if (empty($spec['create'])) {
        continue;
    }
    $create = $spec['create'];
    if ('hosts' === $table) {
        $create = str_replace(
            " `hostSoftwareLevel` varchar(16) NOT NULL DEFAULT '',",
            '',
            $create
        );
    }
    $pdo->exec($create);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$t->check(
    'premise: hosts has no hostSoftwareLevel before the step',
    0 === count($pdo->query("SHOW COLUMNS FROM hosts LIKE 'hostSoftwareLevel'")->fetchAll())
);
$pdo->exec("INSERT INTO hosts (hostID, hostName) VALUES (1, 'assigned'), (2, 'exclusive')");

$steps = fogCollectSchemaSteps(dirname(__DIR__) . '/packages/web/commons/schema.php', $db, 470);
$step = $steps[469] ?? null;
if (!is_array($step)
    || false === strpos(implode("\n", array_filter($step, 'is_string')), 'hostSoftwareLevel')
) {
    fwrite(STDERR, "FAIL: schema 470 must be the hostSoftwareLevel step at index 469\n");
    exit(1);
}
foreach ($step as $sql) {
    $pdo->exec($sql);
}
$col = $pdo->query("SHOW COLUMNS FROM hosts LIKE 'hostSoftwareLevel'")->fetch(\PDO::FETCH_ASSOC);
$t->check(
    'step 470: the column is varchar(16) NOT NULL DEFAULT empty',
    is_array($col)
    && 'varchar(16)' === $col['Type']
    && 'NO' === $col['Null']
    && '' === $col['Default']
);
$t->check(
    'step 470: an existing host is Assigned',
    '' === $pdo->query('SELECT hostSoftwareLevel FROM hosts WHERE hostID=1')->fetchColumn()
);

// ---------------------------------------------------------------------------
// 2. desired(): the 'manage' key only for Exclusive.
// ---------------------------------------------------------------------------
directoryTestBindPdodb($pdo);
FogTestHarness::setStatic('FOGBase', 'FOGCore', new \FOG\Base\FOGCore());
$pdo->exec("UPDATE hosts SET hostSoftwareLevel='exclusive' WHERE hostID=2");

$assigned = SoftwareSet::desired(new Host(1), true);
$t->check(
    'desired: an Assigned host has no manage key',
    !array_key_exists('manage', $assigned)
);
$t->check(
    'desired: an Assigned host keeps the existing keys',
    ['drift_interval', 'bootstrap', 'entries'] === array_keys($assigned)
);
$exclusive = SoftwareSet::desired(new Host(2), true);
$t->check(
    'desired: an Exclusive host carries manage => exclusive',
    'exclusive' === ($exclusive['manage'] ?? null)
);
$t->check(
    'desired: an Exclusive host with no disabled entry has no retain key',
    !array_key_exists('retain', $exclusive)
);

// A disabled entry is not sent, but an Exclusive host retains its package.
// Hosts 1 (Assigned) and 2 (Exclusive) both link it, and both link an
// enabled entry that stays an entry and is never retained.
$pdo->exec(
    "INSERT INTO software (swID, swName, swBackend, swPackage, swEnabled) VALUES "
    . "(1, 'off', 'snap', 'Vlc', 0), (2, 'on', 'choco', 'git', 1)"
);
$pdo->exec(
    "INSERT INTO softwareAssoc (swaHostID, swaSoftwareID, swaSequence) VALUES "
    . "(1, 1, 1), (1, 2, 2), (2, 1, 1), (2, 2, 2)"
);
$assigned = SoftwareSet::desired(new Host(1), true);
$exclusive = SoftwareSet::desired(new Host(2), true);
$t->check(
    'desired: an Exclusive host retains the package of a disabled entry',
    [['backend' => 'snap', 'package' => 'Vlc']] === ($exclusive['retain'] ?? null)
);
$t->check(
    'desired: the disabled entry is not sent as an entry',
    ['git'] === array_column($exclusive['entries'], 'package')
);
$t->check(
    'desired: an Assigned host never carries retain',
    !array_key_exists('retain', $assigned)
);

// An enabled entry the agent cannot honor (no software-backends-v1) is not
// sent either, and an Exclusive host must still keep its package.
$pdo->exec("INSERT INTO hosts (hostID, hostName, hostSoftwareLevel) VALUES (4, 'old-agent', 'exclusive')");
$pdo->exec("INSERT INTO software (swID, swName, swBackend, swPackage, swEnabled) VALUES (3, 'snapapp', 'snap', 'firefox', 1)");
$pdo->exec("INSERT INTO softwareAssoc (swaHostID, swaSoftwareID, swaSequence) VALUES (4, 3, 1)");
$old = SoftwareSet::desired(new Host(4), false);
$t->check(
    'desired: an enabled snap entry dropped for an old agent lands in retain',
    [['backend' => 'snap', 'package' => 'firefox']] === ($old['retain'] ?? null)
    && [] === $old['entries']
);
$new = SoftwareSet::desired(new Host(4), true);
$t->check(
    'desired: the same entry is sent, not retained, to an agent with the feature',
    ['firefox'] === array_column($new['entries'], 'package')
    && !array_key_exists('retain', $new)
);

// ---------------------------------------------------------------------------
// 3. One reading of the stored level.
// ---------------------------------------------------------------------------
$pdo->exec("UPDATE hosts SET hostSoftwareLevel='exclusive ' WHERE hostID=2");
$t->check(
    'levelOf: a stored value with a trailing space is exclusive',
    'exclusive' === SoftwareSet::levelOf(new Host(2))
    && 'exclusive' === (SoftwareSet::desired(new Host(2), true)['manage'] ?? null)
);
$pdo->exec("UPDATE hosts SET hostSoftwareLevel='bogus' WHERE hostID=2");
$t->check(
    'levelOf: an unknown stored value is Assigned, with no manage key',
    '' === SoftwareSet::levelOf(new Host(2))
    && !array_key_exists('manage', SoftwareSet::desired(new Host(2), true))
);

$admin->exec("DROP DATABASE IF EXISTS `$db`");
$t->finish();
