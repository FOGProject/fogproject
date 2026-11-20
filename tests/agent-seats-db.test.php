<?php
/**
 * One agent seat per OS on a host (fog-agent#53), on a real server.
 *
 * A dual-boot machine resolves to one FOG host from both OSes. The host holds
 * one seat per OS (hostAgents), so the second OS no longer replaces the first
 * OS's key. This drives the real code against real tables:
 *
 *   - schema 466 copies an existing host binding to a seat, with the OS from
 *     the agentEnrollment row whose key it is, and a re-run adds nothing;
 *   - the auth lookup resolves a key to its seat and loads the seat values;
 *   - enroll: no seat is known-host-no-agent, a seat for another OS is
 *     new-seat, a seat for the same OS is rebind;
 *   - the deploy window approves new-seat; an identity conflict still waits;
 *   - binding a new-seat key keeps the other seat's key, and a rebind
 *     replaces only that OS's seat;
 *   - a poll's writes land on its own seat, and the host mirror follows;
 *   - the product key reaches only a Windows seat;
 *   - the snapin queue skips a snapin limited to another OS.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 * The database is always fog_seats_test.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-seats-db.test.php
 * Exit status 0 = pass or skip, 1 = fail.
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP agent seats DB: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/directory-db-connect.php';
require __DIR__ . '/lib/fog-schema-collector.php';

FogTestHarness::boot('agent-seats-db');
$fake = FogTestHarness::fakeDb();

$db = 'fog_seats_test';
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

use FOG\Agent\Enrollment;
use FOG\Agent\Seat;
use FOG\Agent\Snapins;
use FOG\Items\AgentEnrollment;
use FOG\Items\Host;

$t = new FogChecks();

// ---------------------------------------------------------------------------
// 1. Schema 466 on a database in its pre-466 shape. Every manifest table
//    except hostAgents, and snapins without sOS; then the REAL step.
// ---------------------------------------------------------------------------
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table => $spec) {
    if ('hostAgents' === $table || empty($spec['create'])) {
        continue;
    }
    $create = $spec['create'];
    if ('snapins' === $table) {
        $create = str_replace(" `sOS` varchar(20) NOT NULL DEFAULT '',", '', $create);
    }
    $pdo->exec($create);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$t->check('premise: snapins has no sOS before the step', 0 === count($pdo->query("SHOW COLUMNS FROM snapins LIKE 'sOS'")->fetchAll()));

$fpA = str_repeat('a', 64);
$fpB = str_repeat('b', 64);
$fpC = str_repeat('c', 64);
$fpD = str_repeat('d', 64);
$fpE = str_repeat('e', 64);
$ins = $pdo->prepare(
    'INSERT INTO hosts (hostID, hostName, hostAgentFingerprint, hostAgentNotAfter, hostAgentVersion, '
    . 'hostAgentUpdateState, hostAgentCheckin) VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$ins->execute([1, 'mig-linux', $fpA, '2027-01-01 00:00:00', '0.1.9', 'current', '2026-11-01 10:00:00']);
$ins->execute([2, 'mig-noenroll', $fpB, null, '0.1.8', '', null]);
$ins->execute([3, 'mig-noagent', '', null, '', '', null]);
$pdo->exec(
    "INSERT INTO agentEnrollment (aeHostID, aeFingerprint, aeCSR, aeOS, aeState) "
    . "VALUES (1, '$fpA', 'x', 'linux', 'issued')"
);

$steps = fogCollectSchemaSteps(dirname(__DIR__) . '/packages/web/commons/schema.php', $db, 466);
$step = $steps[465] ?? null;
if (!is_array($step)
    || false === strpos(implode("\n", array_filter($step, 'is_string')), 'hostAgents')
) {
    fwrite(STDERR, "FAIL: schema 466 must be the hostAgents step at index 465\n");
    exit(1);
}
foreach ($step as $sql) {
    $pdo->exec($sql);
}
$seats = $pdo->query('SELECT haHostID, haOS, haFingerprint, haNotAfter, haVersion, haUpdateState, haCheckin FROM hostAgents ORDER BY haHostID')
    ->fetchAll(\PDO::FETCH_ASSOC);
$t->check('migration: one seat per bound host', 2 === count($seats));
$t->check(
    'migration: the OS comes from the enrollment row of that key, and the state is copied',
    isset($seats[0]) && [1, 'linux', $fpA, '2027-01-01 00:00:00', '0.1.9', 'current', '2026-11-01 10:00:00']
        === array_values($seats[0])
);
$t->check(
    'migration: a binding with no enrollment row gets the empty OS',
    isset($seats[1]) && 2 === (int)$seats[1]['haHostID'] && '' === $seats[1]['haOS'] && $fpB === $seats[1]['haFingerprint']
);
$t->check('migration: an unbound host gets no seat', 0 === (int)$pdo->query('SELECT COUNT(*) FROM hostAgents WHERE haHostID=3')->fetchColumn());
$rerun = array_values(array_filter($step, static function ($sql) {
    return is_string($sql) && 0 === strpos($sql, 'INSERT IGNORE INTO `hostAgents`');
}));
$pdo->exec($rerun[0]);
$t->check('migration: a re-run adds nothing', 2 === (int)$pdo->query('SELECT COUNT(*) FROM hostAgents')->fetchColumn());
$t->check('migration: the hosts mirror columns stay', $fpA === $pdo->query('SELECT hostAgentFingerprint FROM hosts WHERE hostID=1')->fetchColumn());
$t->check('migration: snapins gets sOS defaulting to any OS', "''" === (string)$pdo->query("SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='snapins' AND COLUMN_NAME='sOS'")->fetchColumn());

// The pre-existing rows are not part of the cases below.
$pdo->exec('DELETE FROM hostAgents');
$pdo->exec('DELETE FROM agentEnrollment');
$pdo->exec('DELETE FROM hosts');

directoryTestBindPdodb($pdo);
FogTestHarness::setStatic('FOGBase', 'FOGCore', new \FOG\Base\FOGCore());
$pdo->exec("INSERT INTO globalSettings (settingKey, settingValue) VALUES ('FOG_HOST_IDENTIFY_SMBIOS', 'off')");
$pdo->exec("INSERT INTO globalSettings (settingKey, settingValue) VALUES ('FOG_AGENT_ENROLL_DEPLOY_WINDOW', '24')");

$seatRows = function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT haOS, haFingerprint FROM hostAgents WHERE haHostID=? ORDER BY haOS');
    $q->execute([$hostID]);
    return $q->fetchAll(\PDO::FETCH_KEY_PAIR);
};
$mirror = function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hostAgentFingerprint FROM hosts WHERE hostID=?');
    $q->execute([$hostID]);
    return (string)$q->fetchColumn();
};
$addHost = function ($id, $name, $mac) use ($pdo) {
    $pdo->prepare('INSERT INTO hosts (hostID, hostName) VALUES (?, ?)')->execute([$id, $name]);
    $pdo->prepare('INSERT INTO hostMAC (hmHostID, hmMAC, hmPrimary, hmPending) VALUES (?, ?, 1, 0)')->execute([$id, $mac]);
};

// ---------------------------------------------------------------------------
// 2. Binding. A new OS adds a seat and leaves the other seat's key alone; a
//    new key for an OS replaces only that OS's seat.
// ---------------------------------------------------------------------------
$addHost(10, 'dual-boot', '02:46:00:00:00:10');
Seat::bind(10, 'linux', $fpA, '2027-01-01 00:00:00', '0.1.9', '2026-11-20 10:00:00');
Seat::bind(10, 'windows', $fpB, '2027-02-01 00:00:00', '0.2.0', '2026-11-20 11:00:00');
$t->check('bind: a new-seat request keeps the other seat\'s key', ['linux' => $fpA, 'windows' => $fpB] === $seatRows(10));
$t->check('bind: the host mirror follows the seat bound last', $fpB === $mirror(10));
Seat::bind(10, 'linux', $fpC, '2027-03-01 00:00:00', '0.2.1', '2026-11-20 12:00:00');
$t->check('bind: a rebind replaces only that OS\'s seat', ['linux' => $fpC, 'windows' => $fpB] === $seatRows(10));
$t->check('bind: the host mirror follows the rebind', $fpC === $mirror(10));
$t->check(
    'bind: the Windows seat\'s own values are untouched by the Linux rebind',
    ['0.2.0', '2027-02-01 00:00:00'] === array_values(
        $pdo->query("SELECT haVersion, haNotAfter FROM hostAgents WHERE haHostID=10 AND haOS='windows'")->fetch(\PDO::FETCH_NUM)
    )
);
$refused = null;
try {
    Seat::bind(10, 'darwin', $fpB, null, '', '2026-11-20 13:00:00');
} catch (\RuntimeException $e) {
    $refused = $e->getCode();
}
$t->check('bind: a key that is already another seat\'s is refused', 409 === $refused);
$t->check('bind: the refused bind added no seat', ['linux' => $fpC, 'windows' => $fpB] === $seatRows(10));
$t->check('exists: any seat, and a seat for one OS', Seat::exists(10) && Seat::exists(10, 'windows') && !Seat::exists(10, 'darwin') && !Seat::exists(99));
$t->check('fingerprint: that seat\'s key, or empty', $fpB === Seat::fingerprint(10, 'windows') && '' === Seat::fingerprint(10, 'darwin'));
$t->check('boundHosts: the hosts carrying a key', [10] === Seat::boundHosts($fpC) && [] === Seat::boundHosts($fpE));

// ---------------------------------------------------------------------------
// 3. Auth: a key resolves to its seat, and the seat's values reach the host.
// ---------------------------------------------------------------------------
$rows = Seat::principal($fpB);
$t->check('principal: the key resolves to exactly one seat', 1 === count($rows) && 10 === (int)$rows[0]['hostID'] && 'windows' === $rows[0]['os']);
$t->check('principal: an unknown key resolves to nothing', [] === Seat::principal($fpE));
$pdo->exec('UPDATE hosts SET hostPending=1 WHERE hostID=10');
$t->check('principal: a pending host is no principal', [] === Seat::principal($fpB));
$pdo->exec('UPDATE hosts SET hostPending=0 WHERE hostID=10');

$HostWin = new Host(10);
Seat::load($HostWin, $rows[0]);
$t->check(
    'load: the seat is current and its values are in the host',
    ['hostID' => 10, 'os' => 'windows', 'fingerprint' => $fpB] === Seat::$current
        && $fpB === $HostWin->get('agentFingerprint')
        && '0.2.0' === $HostWin->get('agentVersion')
        && '2027-02-01 00:00:00' === $HostWin->get('agentNotAfter')
);
$t->check('isWindows: a Windows seat on an agent request', true === Seat::isWindows($HostWin));

// A poll's writes land on the requesting seat, and the mirror follows.
Seat::record($HostWin, ['agentCheckin' => '2026-11-20 14:00:00', 'agentVersion' => '0.2.2', 'agentUpdateState' => 'current']);
$winSeat = $pdo->query("SELECT haVersion, haUpdateState, haCheckin FROM hostAgents WHERE haHostID=10 AND haOS='windows'")->fetch(\PDO::FETCH_NUM);
$linSeat = $pdo->query("SELECT haVersion, haUpdateState, haCheckin FROM hostAgents WHERE haHostID=10 AND haOS='linux'")->fetch(\PDO::FETCH_NUM);
$t->check('record: the requesting seat takes the values', ['0.2.2', 'current', '2026-11-20 14:00:00'] === $winSeat);
$t->check('record: the other seat is untouched', ['0.2.1', '', '2026-11-20 12:00:00'] === $linSeat);
$t->check(
    'record: the host mirror takes the same values, with the seat\'s key',
    ['0.2.2', 'current', '2026-11-20 14:00:00', $fpB] === array_values(
        $pdo->query('SELECT hostAgentVersion, hostAgentUpdateState, hostAgentCheckin, hostAgentFingerprint FROM hosts WHERE hostID=10')->fetch(\PDO::FETCH_NUM)
    )
);
$t->check('record: the in-memory host follows', '0.2.2' === $HostWin->get('agentVersion'));
$other = null;
try {
    Seat::record(new Host(11), ['agentCheckin' => '2026-11-20 14:00:00']);
} catch (\RuntimeException $e) {
    $other = $e->getMessage();
}
$t->check('record: refuses a host that is not the authenticated seat\'s', null !== $other);

// A product key reaches only the Windows seat (State::_activation).
$productKey = 'W269N-WFGWX-YVC9B-4J6C9-T83GX';
$activation = new \ReflectionMethod(\FOG\Agent\State::class, '_activation');
$activation->setAccessible(true);
$HostWin->set('productKey', $productKey);
$t->check('activation: the Windows seat gets the product key', ['key' => $productKey] === $activation->invoke(null, $HostWin));
$linRow = $pdo->query("SELECT haHostID AS hostID, haOS AS os, haFingerprint AS fingerprint, haNotAfter AS notAfter, haVersion AS version, haUpdateState AS updateState, haCheckin AS checkin FROM hostAgents WHERE haHostID=10 AND haOS='linux'")->fetch(\PDO::FETCH_ASSOC);
$HostLin = new Host(10);
Seat::load($HostLin, $linRow);
$HostLin->set('productKey', $productKey);
$t->check('activation: the Linux seat of the same host gets no product key', null === $activation->invoke(null, $HostLin));
$t->check('isWindows: a Linux seat is not Windows', false === Seat::isWindows($HostLin));
Seat::$current = null;
$t->check('isWindows: outside an agent request, any Windows seat counts', true === Seat::isWindows(new Host(10)));

// ---------------------------------------------------------------------------
// 4. Enroll reasons, through the real handle(). Legacy path: the MAC finds
//    the host. The request OS decides which reason it is.
// ---------------------------------------------------------------------------
$addHost(20, 'no-seat', '02:46:00:00:00:20');
$addHost(21, 'linux-seat', '02:46:00:00:00:21');
$addHost(22, 'windows-seat', '02:46:00:00:00:22');
Seat::bind(21, 'linux', $fpD, null, '0.2.0', '2026-11-20 10:00:00');
Seat::bind(22, 'windows', $fpE, null, '0.2.0', '2026-11-20 10:00:00');
$enrollReason = function ($mac, $os) {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_csr_export(openssl_csr_new(['commonName' => 'seat-test'], $key, ['digest_alg' => 'sha256']), $csr);
    $body = [
        'protocol' => 1, 'csr_pem' => $csr, 'os' => $os, 'hostname' => 'seat-test',
        'identity' => ['macs' => [$mac]]
    ];
    list($code, $payload) = Enrollment::handle($body, '203.0.113.9');
    return $code . ' ' . ($payload['status'] ?? '') . ' ' . ($payload['reason'] ?? '');
};
$t->check('enroll: a host with no seat is known-host-no-agent', '202 pending known-host-no-agent' === $enrollReason('02:46:00:00:00:20', 'windows'));
$t->check('enroll: a seat for another OS is new-seat', '202 pending new-seat' === $enrollReason('02:46:00:00:00:21', 'windows'));
$t->check('enroll: a seat for the same OS is rebind', '202 pending rebind' === $enrollReason('02:46:00:00:00:22', 'windows'));

// A seat that migration left with a blank OS: a request for any OS replaces it.
$fpOld = str_repeat('1', 64);
$fpNew = str_repeat('2', 64);
$addHost(23, 'blank-os', '02:46:00:00:00:23');
Seat::bind(23, '', $fpOld, null, '0.1.0', '2026-11-20 10:00:00');
$t->check('enroll: a request for an OS against a blank-OS seat is a rebind', '202 pending rebind' === $enrollReason('02:46:00:00:00:23', 'windows'));
Seat::bind(23, 'windows', $fpNew, null, '0.2.0', '2026-11-20 11:00:00');
$t->check('bind: the blank-OS seat takes the OS and the new key', ['windows' => $fpNew] === $seatRows(23));
$t->check('bind: the old blank-OS key no longer authenticates', [] === Seat::principal($fpOld) && 1 === count(Seat::principal($fpNew)));

// ---------------------------------------------------------------------------
// 5. The deploy window approves a first binding, a rebind and a new seat. A
//    conflict still waits for an admin.
// ---------------------------------------------------------------------------
$pdo->exec("UPDATE hosts SET hostLastDeploy = UTC_TIMESTAMP() WHERE hostID IN (20, 21, 22)");
$auto = new \ReflectionMethod(Enrollment::class, '_autoApproval');
$auto->setAccessible(true);
$autoFor = function ($hostID, $reason) use ($auto) {
    $Row = (new AgentEnrollment())
        ->set('hostID', $hostID)
        ->set('reason', $reason)
        ->set('fingerprint', str_repeat('9', 64))
        ->set('identity', '{}');
    return $auto->invoke(null, $Row, '');
};
$t->check('deploy window: approves known-host-no-agent', 'deploy' === $autoFor(20, Enrollment::REASON_KNOWN));
$t->check('deploy window: approves rebind', 'deploy' === $autoFor(22, Enrollment::REASON_REBIND));
$t->check('deploy window: approves new-seat', 'deploy' === $autoFor(21, Enrollment::REASON_NEW_SEAT));
$t->check('deploy window: an identity conflict still waits', '' === $autoFor(21, Enrollment::REASON_CONFLICT));
$t->check('deploy window: a reissue still waits', '' === $autoFor(21, Enrollment::REASON_REISSUE));

// ---------------------------------------------------------------------------
// 6. The snapin queue: a snapin limited to one OS is skipped for the other
//    OS's seat and stays queued for the matching one.
// ---------------------------------------------------------------------------
$addHost(30, 'snapin-host', '02:46:00:00:00:30');
$pdo->exec("INSERT INTO snapins (sID, sName, sFilePath, sOS) VALUES (1, 'win-only', 'w.ps1', 'windows'), (2, 'any-os', 'a.sh', ''), (3, 'lin-only', 'l.sh', 'linux')");
$queued = (int)Snapins::getQueuedState();
$pdo->exec("INSERT INTO snapinJobs (sjID, sjHostID, sjStateID) VALUES (5, 30, $queued)");
foreach ([[1, 1], [2, 2], [3, 3]] as list($taskID, $snapinID)) {
    $pdo->exec("INSERT INTO snapinTasks (stID, stJobID, stState, stSnapinID) VALUES ($taskID, 5, $queued, $snapinID)");
}
$queueNames = function ($os) {
    Seat::$current = ['hostID' => 30, 'os' => $os, 'fingerprint' => 'f'];
    $names = array_column(Snapins::queue(new Host(30)), 'name');
    Seat::$current = null;
    sort($names);
    return $names;
};
$t->check('snapin queue: a Windows seat skips the Linux snapin', ['any-os', 'win-only'] === $queueNames('windows'));
$t->check('snapin queue: a Linux seat skips the Windows snapin', ['any-os', 'lin-only'] === $queueNames('linux'));
$t->check('snapin queue: a macOS seat gets only the snapin for any OS', ['any-os'] === $queueNames('darwin'));
$t->check(
    'snapin queue: a skipped task stays queued',
    3 === (int)$pdo->query("SELECT COUNT(*) FROM snapinTasks WHERE stJobID=5 AND stState=$queued")->fetchColumn()
);

// ---------------------------------------------------------------------------
// 7. Release platform: a dual-boot host whose Linux enrollment was issued
//    last. The Windows seat that polls is still a Windows platform.
// ---------------------------------------------------------------------------
$addHost(40, 'dual-platform', '02:46:00:00:00:40');
$fpWin = str_repeat('7', 64);
$fpLin = str_repeat('8', 64);
$pdo->exec(
    "INSERT INTO agentEnrollment (aeHostID, aeFingerprint, aeCSR, aeOS, aeArch, aeState) VALUES "
    . "(40, '$fpWin', 'x', 'windows', 'amd64', 'issued'), (40, '$fpLin', 'x', 'linux', 'arm64', 'issued')"
);
Seat::bind(40, 'windows', $fpWin, null, '0.2.0', '2026-11-20 10:00:00');
Seat::bind(40, 'linux', $fpLin, null, '0.2.0', '2026-11-20 11:00:00');
$HostPlat = new Host(40);
Seat::load($HostPlat, Seat::principal($fpWin)[0]);
$t->check('platform: the Windows seat polling gets windows/amd64', ['windows', 'amd64'] === \FOG\Agent\Releases::platform($HostPlat));
Seat::load($HostPlat, Seat::principal($fpLin)[0]);
$t->check('platform: the Linux seat polling gets linux/arm64', ['linux', 'arm64'] === \FOG\Agent\Releases::platform($HostPlat));
Seat::$current = null;
$t->check('platform: with no seat, the newest issued enrollment', ['linux', 'arm64'] === \FOG\Agent\Releases::platform($HostPlat));

// The key-bound-elsewhere refusal is one helper: another host, or another OS.
$refuse = function ($fp, $host, $os = null) {
    try {
        Seat::refuseBoundElsewhere($fp, $host, $os);
        return null;
    } catch (\RuntimeException $e) {
        return $e->getMessage() . ' ' . $e->getCode();
    }
};
$t->check('bound elsewhere: a key on another host is refused', 'enrollment_key_bound_elsewhere 409' === $refuse($fpWin, 41));
$t->check('bound elsewhere: a key on this host is allowed', null === $refuse($fpWin, 40));
$t->check('bound elsewhere: a key on this host\'s other OS is refused when an OS is named', 'enrollment_key_bound_elsewhere 409' === $refuse($fpWin, 40, 'linux'));
$t->check('notAfter: the seat\'s expiry, or empty', '' === Seat::notAfter(40, 'windows') && '' === Seat::notAfter(40, 'darwin'));

$t->finish();
