<?php
/**
 * Installed software is kept per OS on a dual-boot host, on a real server.
 *
 * A host holds one agent seat per OS (hostAgents). Each seat reports the
 * complete program list of its own OS, so the reconcile must close only that
 * OS's rows, and the fact hash must be kept per seat. This drives the real
 * code against real tables:
 *
 *   - schema 469 adds hsOS, swaps the unique key, widens hfsKind, drops the
 *     old per-host software hash, and backfills hsOS only for a host with
 *     exactly one seat;
 *   - two seats of one host each report a list, and both lists stay open;
 *   - a later report closes only its own OS's missing program;
 *   - an open row with no OS is adopted by the first named seat that
 *     reports: kept with its own id and first-seen date when still
 *     installed, closed when not;
 *   - a seat with no OS touches only the rows with no OS;
 *   - State::facts() stores one software hash per seat, and a seat with no
 *     OS stores it under the bare kind.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 * The database is always fog_swos_test.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-software-per-os-db.test.php
 * Exit status 0 = pass or skip, 1 = fail.
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP agent software per OS DB: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/directory-db-connect.php';
require __DIR__ . '/lib/fog-schema-collector.php';

FogTestHarness::boot('agent-software-per-os-db');
$fake = FogTestHarness::fakeDb();

$db = 'fog_swos_test';
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

use FOG\Agent\Seat;
use FOG\Agent\SoftwareFacts;
use FOG\Agent\State;
use FOG\Items\Host;

$t = new FogChecks();

// ---------------------------------------------------------------------------
// 1. Schema 469 on tables in their pre-469 shape: every manifest table, with
//    hostSoftware lacking hsOS and keyed the old way, and hfsKind at 16.
// ---------------------------------------------------------------------------
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table => $spec) {
    if (empty($spec['create'])) {
        continue;
    }
    $create = $spec['create'];
    if ('hostSoftware' === $table) {
        $create = str_replace(" `hsOS` varchar(20) NOT NULL DEFAULT '',", '', $create);
        $create = str_replace(
            'UNIQUE KEY `hsHostOSNameSrcVer` (`hsHostID`,`hsOS`,`hsName`,`hsSource`,`hsVersion`)',
            'UNIQUE KEY `hsHostNameSrcVer` (`hsHostID`,`hsName`,`hsSource`,`hsVersion`)',
            $create
        );
    }
    if ('hostFactState' === $table) {
        $create = str_replace('`hfsKind` varchar(32)', '`hfsKind` varchar(16)', $create);
    }
    $pdo->exec($create);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$t->check('premise: hostSoftware has no hsOS before the step', 0 === count($pdo->query("SHOW COLUMNS FROM hostSoftware LIKE 'hsOS'")->fetchAll()));
$t->check('premise: hfsKind is varchar(16) before the step', 'varchar(16)' === $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hostFactState' AND COLUMN_NAME='hfsKind'")->fetchColumn());

$fpA = str_repeat('a', 64);
$fpB = str_repeat('b', 64);
$fpC = str_repeat('c', 64);
// Host 1: two seats. Host 2: one Linux seat. Host 3: no seat.
$pdo->exec(
    "INSERT INTO hostAgents (haHostID, haOS, haFingerprint) VALUES "
    . "(1, 'windows', '$fpA'), (1, 'linux', '$fpB'), (2, 'linux', '$fpC')"
);
$pdo->exec(
    "INSERT INTO hostSoftware (hsHostID, hsName, hsSource, hsVersion) VALUES "
    . "(1, 'both-seats', 'x', '1'), (2, 'one-seat', 'x', '1'), (3, 'no-seat', 'x', '1')"
);
$pdo->exec(
    "INSERT INTO hostFactState (hfsHostID, hfsKind, hfsHash) VALUES "
    . "(1, 'software', 'old'), (1, 'inventory', 'keep')"
);

$steps = fogCollectSchemaSteps(dirname(__DIR__) . '/packages/web/commons/schema.php', $db, 469);
$step = $steps[468] ?? null;
if (!is_array($step)
    || false === strpos(implode("\n", array_filter($step, 'is_string')), 'hsHostOSNameSrcVer')
) {
    fwrite(STDERR, "FAIL: schema 469 must be the hostSoftware per-OS step at index 468\n");
    exit(1);
}
foreach ($step as $sql) {
    $pdo->exec($sql);
}
$osOf = function ($name) use ($pdo) {
    $q = $pdo->prepare('SELECT hsOS FROM hostSoftware WHERE hsName=?');
    $q->execute([$name]);
    return $q->fetchColumn();
};
$t->check('step 469: a host with one seat gets that seat\'s OS', 'linux' === $osOf('one-seat'));
$t->check('step 469: a host with two seats stays unattributed', '' === $osOf('both-seats'));
$t->check('step 469: a host with no seat stays unattributed', '' === $osOf('no-seat'));
$t->check('step 469: hfsKind has room for software:<os>', 'varchar(32)' === $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hostFactState' AND COLUMN_NAME='hfsKind'")->fetchColumn());
$t->check(
    'step 469: the old software hash is gone and the other kinds stay',
    ['inventory'] === $pdo->query('SELECT hfsKind FROM hostFactState')->fetchAll(\PDO::FETCH_COLUMN)
);
$t->check(
    'step 469: the unique key names the OS',
    'hsHostOSNameSrcVer' === $pdo->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hostSoftware' AND INDEX_NAME LIKE 'hsHost%NameSrcVer' AND COLUMN_NAME='hsOS'")->fetchColumn()
);

// The pre-existing rows are not part of the cases below.
$pdo->exec('DELETE FROM hostSoftware');
$pdo->exec('DELETE FROM hostFactState');
$pdo->exec('DELETE FROM hostAgents');

directoryTestBindPdodb($pdo);
FogTestHarness::setStatic('FOGBase', 'FOGCore', new \FOG\Base\FOGCore());
$pdo->exec("INSERT INTO hosts (hostID, hostName) VALUES (1, 'dual-boot')");
$pdo->exec("INSERT INTO hostAgents (haHostID, haOS, haFingerprint) VALUES (1, 'windows', '$fpA'), (1, 'linux', '$fpB')");

$asSeat = function ($os) {
    Seat::$current = ['hostID' => 1, 'os' => $os, 'fingerprint' => 'f'];
};
$prog = function ($name) {
    return ['name' => $name, 'version' => '1', 'source' => 'x'];
};
$open = function ($os) use ($pdo) {
    $q = $pdo->prepare('SELECT hsName FROM hostSoftware WHERE hsHostID=1 AND hsOS=? AND hsRemovedAt IS NULL ORDER BY hsName');
    $q->execute([$os]);
    return $q->fetchAll(\PDO::FETCH_COLUMN);
};

// ---------------------------------------------------------------------------
// 2. Two seats, two lists. Neither report closes the other seat's rows.
// ---------------------------------------------------------------------------
$asSeat('windows');
SoftwareFacts::report(new Host(1), [$prog('winapp'), $prog('shared'), $prog('winonly')]);
$asSeat('linux');
SoftwareFacts::report(new Host(1), [$prog('linuxapp'), $prog('shared')]);
$t->check('two seats: the Windows list stays open after the Linux report', ['shared', 'winapp', 'winonly'] === $open('windows'));
$t->check('two seats: the Linux list is open', ['linuxapp', 'shared'] === $open('linux'));
$t->check('two seats: the same program on both OSes is two rows', 2 === (int)$pdo->query("SELECT COUNT(*) FROM hostSoftware WHERE hsName='shared'")->fetchColumn());

// ---------------------------------------------------------------------------
// 3. A second Windows report missing a program closes only that row.
// ---------------------------------------------------------------------------
$asSeat('windows');
SoftwareFacts::report(new Host(1), [$prog('winapp'), $prog('shared')]);
$t->check('removal: the Windows report closes only the missing Windows row', ['shared', 'winapp'] === $open('windows'));
$t->check('removal: the Linux list is untouched', ['linuxapp', 'shared'] === $open('linux'));
$t->check(
    'removal: the closed row carries a removal date',
    null !== $pdo->query("SELECT hsRemovedAt FROM hostSoftware WHERE hsName='winonly'")->fetchColumn()
);

// ---------------------------------------------------------------------------
// 4. An open row with no OS (a two-seat host before step 469) is adopted by
//    the first named seat that reports: kept, with its history, if reported,
//    closed if not.
// ---------------------------------------------------------------------------
$pdo->exec("INSERT INTO hostSoftware (hsHostID, hsOS, hsName, hsSource, hsVersion, hsFirstSeen) VALUES (1, '', 'legacy-kept', 'x', '1', '2026-01-01 00:00:00'), (1, '', 'legacy-gone', 'x', '1', '2026-01-01 00:00:00')");
$legacyID = (int)$pdo->query("SELECT hsID FROM hostSoftware WHERE hsName='legacy-kept'")->fetchColumn();
$asSeat('linux');
SoftwareFacts::report(new Host(1), [$prog('linuxapp'), $prog('shared'), $prog('legacy-kept')]);
$t->check('legacy: the unattributed rows are closed', [] === $open(''));
$t->check('legacy: a still-installed row reopens under the reporting seat\'s OS', ['legacy-kept', 'linuxapp', 'shared'] === $open('linux'));
$t->check('legacy: a row the seat no longer reports stays closed', 0 === (int)$pdo->query("SELECT COUNT(*) FROM hostSoftware WHERE hsName='legacy-gone' AND hsRemovedAt IS NULL")->fetchColumn());
$t->check('legacy: the other seat\'s rows stay open', ['shared', 'winapp'] === $open('windows'));
// Adopted, not closed and re-inserted: one row, same id, first-seen kept, no
// false removal on record.
$kept = $pdo->query("SELECT hsID, hsFirstSeen, hsRemovedAt FROM hostSoftware WHERE hsName='legacy-kept'")->fetchAll(\PDO::FETCH_ASSOC);
$t->check('legacy: a still-installed row keeps its own row', 1 === count($kept) && $legacyID === (int)$kept[0]['hsID']);
$t->check('legacy: a still-installed row keeps its first-seen date', '2026-01-01 00:00:00' === $kept[0]['hsFirstSeen']);
$t->check('legacy: a still-installed row records no removal', null === $kept[0]['hsRemovedAt']);

// ---------------------------------------------------------------------------
// 4b. A seat with no OS (one migration could not name) touches only the rows
//     with no OS, and stores its fact hash under the bare kind.
// ---------------------------------------------------------------------------
$asSeat('');
SoftwareFacts::report(new Host(1), [$prog('noos-app')]);
$t->check('no OS: its report lands on rows with no OS', ['noos-app'] === $open(''));
$t->check('no OS: the Windows list stays open', ['shared', 'winapp'] === $open('windows'));
$t->check('no OS: the Linux list stays open', ['legacy-kept', 'linuxapp', 'shared'] === $open('linux'));

// ---------------------------------------------------------------------------
// 4c. When the host really has a seat with no OS, rows with no OS are that
//     seat's own: a named seat's report neither adopts nor closes them.
// ---------------------------------------------------------------------------
$fpC = str_repeat('c', 64);
$pdo->exec("INSERT INTO hostAgents (haHostID, haOS, haFingerprint) VALUES (1, '', '$fpC')");
$asSeat('linux');
SoftwareFacts::report(new Host(1), [$prog('legacy-kept'), $prog('linuxapp'), $prog('shared')]);
$t->check('no-OS seat: its open row survives a named seat\'s report', ['noos-app'] === $open(''));
$t->check('no-OS seat: the named seat did not take its row', ['legacy-kept', 'linuxapp', 'shared'] === $open('linux'));
$pdo->exec("DELETE FROM hostAgents WHERE haHostID = 1 AND haOS = ''");
$pdo->exec("UPDATE hostSoftware SET hsRemovedAt = NOW() WHERE hsOS = ''");

// ---------------------------------------------------------------------------
// 5. State::facts() keeps one software hash per seat, and one per host for the
//    kinds that describe the machine.
// ---------------------------------------------------------------------------
$pdo->exec("INSERT INTO globalSettings (settingKey, settingValue) VALUES ('FOG_AGENT_INVENTORY_ENABLED', '1')");
$hostFor = function () {
    return new Host(1);
};
$asSeat('windows');
$answer = State::facts($hostFor(), ['software' => [$prog('winapp')]]);
$asSeat('linux');
State::facts($hostFor(), ['software' => [$prog('linuxapp')]]);
$kinds = $pdo->query('SELECT hfsKind FROM hostFactState WHERE hfsHostID=1 ORDER BY hfsKind')->fetchAll(\PDO::FETCH_COLUMN);
$t->check('fact key: each seat has its own software hash row', ['software:linux', 'software:windows'] === $kinds);
$t->check('fact key: the answer key stays want_software', array_key_exists('want_software', $answer) && false === $answer['want_software']);
$asSeat('windows');
$again = State::facts($hostFor(), []);
$t->check('fact key: a seat with a stored hash is not asked again', false === $again['want_software']);
$asSeat('');
State::facts($hostFor(), ['software' => [$prog('noos-app')]]);
$t->check(
    'fact key: a seat with no OS uses the bare software kind',
    1 === (int)$pdo->query("SELECT COUNT(*) FROM hostFactState WHERE hfsHostID=1 AND hfsKind='software'")->fetchColumn()
);
Seat::$current = null;

$t->finish();
