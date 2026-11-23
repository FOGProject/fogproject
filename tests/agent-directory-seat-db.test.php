<?php
/**
 * One seat per host owns the directory (fog-agent#53). The directory seat is
 * the Windows seat, else the host's only seat, else none. Against a real
 * scratch database:
 *   - a dual-boot host: the Linux seat gets no join block and its directory
 *     facts are ignored; the Windows seat gets the join block and its facts
 *     are stored
 *   - a Linux-only host: its one seat is the directory seat and gets the join
 *   - a Linux plus macOS host: no seat owns the directory, so no join block
 *   - the directory fingerprint SQL used inside the rejoin transaction
 *     follows the same rule as Seat::directorySeat()
 * The claim guard for a non-directory seat is in
 * tests/agent-directory-operation-db.test.php.
 *
 * Usage: FOG_TEST_DSN=... php tests/agent-directory-seat-db.test.php
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory seat DB: FOG_TEST_DSN not set\n";
    exit(0);
}
// The directory lookups behind a rejoin result, stubbed the way
// tests/agent-directory-report-db.test.php does.
class DirectorySeatFakeLdap
{
    public static $record = [];
    public function connect($uri, $bind, $password, $ca = '') { return true; }
    public function domainContext($domain)
    {
        return ['status' => 'found', 'base_dn' => 'DC=corp,DC=example', 'dc' => 'dc1.corp.example'];
    }
    public function computerRecord($base, $account)
    {
        if (0 !== strcasecmp(rtrim((string)$account, '$'),
            rtrim((string)(self::$record['account'] ?? ''), '$'))) {
            return ['status' => 'error'];
        }
        return ['status' => 'found', 'record' => self::$record];
    }
    public function close() { }
}
class_alias(DirectorySeatFakeLdap::class, 'FOG\\Net\\FOGLdap', false);

$directoryDbName = 'agent-directory-seat-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
$t = new FogChecks();

use FOG\Agent\DirectoryFacts;
use FOG\Agent\DirectoryJoin;
use FOG\Agent\Seat;

$pdo->exec('DELETE FROM hostDirectory');
$pdo->exec('DELETE FROM hosts');
$pdo->exec('DELETE FROM hostAgents');

$fpW = str_repeat('1', 64);
$fpL = str_repeat('2', 64);
$fpM = str_repeat('3', 64);

// host id => [name, seats [os => fingerprint]]
$layout = [
    201 => ['DUALBOOT', ['windows' => $fpW, 'linux' => $fpL]],
    202 => ['LINUXONLY', ['linux' => str_repeat('4', 64)]],
    203 => ['LINMAC', ['linux' => str_repeat('5', 64), 'darwin' => $fpM]],
    204 => ['NOSEAT', []]
];
foreach ($layout as $id => $def) {
    $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADUser,hostADPass) VALUES (?,?,1,?,?,?)'
    )->execute([$id, $def[0], 'corp.example', 'svc-join', 'pw']);
    foreach ($def[1] as $os => $fp) {
        directorySeat($pdo, $id, $fp, $os);
    }
    // Observed, and not joined: the host is waiting for a join.
    $pdo->prepare(
        'INSERT INTO hostDirectory (hdHostID,hdJoined,hdKind,hdObservedAt) VALUES (?,0,\'workgroup\',UTC_TIMESTAMP())'
    )->execute([$id]);
}

$hostObj = function ($id) use ($layout) {
    return (new FOG\Items\Host())->set('id', $id)->set('name', $layout[$id][0])
        ->set('useAD', 1)->set('ADDomain', 'corp.example')
        ->set('ADUser', 'svc-join')->set('ADPass', 'pw');
};
$polling = function ($id, $os) use ($layout) {
    Seat::$current = ['hostID' => $id, 'os' => $os, 'fingerprint' => $layout[$id][1][$os]];
};
$joinFor = function ($id, $os) use ($hostObj, $polling) {
    $polling($id, $os);
    $block = DirectoryJoin::desired($hostObj($id));
    Seat::$current = null;
    return $block;
};

// The rule itself.
$t->check('dual-boot: the directory seat is the Windows seat', 'windows' === Seat::directorySeat(201)['os']);
$t->check('Linux-only: the only seat is the directory seat', 'linux' === Seat::directorySeat(202)['os']);
$t->check('Linux plus macOS: no seat owns the directory', null === Seat::directorySeat(203));
$t->check('no seat: no directory seat', null === Seat::directorySeat(204) && '' === Seat::directoryFingerprint(204));
$sqlFp = function ($id) use ($pdo) {
    $q = $pdo->prepare('SELECT ' . Seat::directoryFingerprintSql('hosts.hostID') . ' FROM hosts WHERE hostID = ?');
    $q->execute([$id]);
    return (string)$q->fetchColumn();
};
$t->check(
    'the SQL form gives the same answer for all four hosts',
    $fpW === $sqlFp(201) && str_repeat('4', 64) === $sqlFp(202) && '' === $sqlFp(203) && '' === $sqlFp(204)
);

// The join block.
$t->check('dual-boot: the Windows seat gets the join block', is_array($joinFor(201, 'windows')));
$t->check('dual-boot: the Linux seat gets no join block', null === $joinFor(201, 'linux'));
$t->check('Linux-only host: its Linux seat gets the join block', is_array($joinFor(202, 'linux')));
$t->check('Linux plus macOS: the Linux seat gets no join block', null === $joinFor(203, 'linux'));
$t->check('Linux plus macOS: the macOS seat gets no join block', null === $joinFor(203, 'darwin'));

// Directory facts. A Linux seat that reports joined=0 must not rewrite the
// shared row; the Windows seat's report is stored.
$joined = function ($id) use ($pdo) {
    $q = $pdo->prepare('SELECT hdJoined, hdDomain FROM hostDirectory WHERE hdHostID = ?');
    $q->execute([$id]);
    $row = $q->fetch(\PDO::FETCH_NUM);
    return [(int)$row[0], (string)$row[1]];
};
$report = function ($id, $os, array $block) use ($hostObj, $polling) {
    $polling($id, $os);
    DirectoryFacts::report($hostObj($id), $block);
    Seat::$current = null;
};
$report(201, 'windows', ['joined' => true, 'kind' => 'ad', 'domain' => 'corp.example']);
$t->check('dual-boot: the Windows seat\'s facts are stored', [1, 'corp.example'] === $joined(201));
$report(201, 'linux', ['joined' => false, 'kind' => 'none']);
$t->check('dual-boot: the Linux seat\'s joined=0 is ignored', [1, 'corp.example'] === $joined(201));
$report(202, 'linux', ['joined' => true, 'kind' => 'ad', 'domain' => 'corp.example']);
$t->check('Linux-only host: its seat\'s facts are stored', [1, 'corp.example'] === $joined(202));
$report(203, 'linux', ['joined' => true, 'kind' => 'ad', 'domain' => 'corp.example']);
$t->check('Linux plus macOS: no seat owns the directory, so facts are ignored', [0, ''] === $joined(203));

// ---------------------------------------------------------------------------
// The poll, the join result and the rejoin result of a dual-boot host. The
// Linux seat is loaded; none of the three may write the shared directory
// row. The Windows seat is the control: each still works.
// ---------------------------------------------------------------------------
$settingsCache = [
    'FOG_AGENT_INVENTORY_ENABLED' => ['value' => '1'],
    'FOG_DIRECTORY_LDAP_URI' => ['value' => 'ldaps://dc1.corp.example'],
    'FOG_DIRECTORY_BIND_DN' => ['value' => 'CN=fixture'],
    'FOG_DIRECTORY_BIND_PASSWORD' => ['value' => 'fixture-only'],
    'FOG_DIRECTORY_BASE_DN' => ['value' => 'DC=corp,DC=example'],
    'FOG_DIRECTORY_CA_CERT' => ['value' => '']
];
FogTestHarness::setStatic('FOGBase', '_settingsCache', $settingsCache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
$pdo->exec('DROP TABLE IF EXISTS hostMAC');
$pdo->exec($GLOBALS['manifest']['tables']['hostMAC']['create']);
$pdo->exec("INSERT INTO hostMAC (hmHostID,hmMAC,hmPrimary,hmPending) VALUES (201,'02:46:00:00:02:01',1,0)");
$stateRow = function () use ($pdo) {
    return $pdo->query('SELECT hdRejoinConfig, hdRejoinState FROM hostDirectory WHERE hdHostID = 201')->fetch(\PDO::FETCH_NUM);
};
$joinRow = function () use ($pdo) {
    return $pdo->query('SELECT hdJoinAt, hdJoinError FROM hostDirectory WHERE hdHostID = 201')->fetch(\PDO::FETCH_NUM);
};
$dual = $hostObj(201);

// (a) beforePoll: the directory attempt of a poll is observed only for the
// directory seat.
$pollBody = [
    'features' => ['directory-join-target-v1', 'directory-task-operations-v1'],
    'identity' => ['macs' => ['02:46:00:00:02:01']],
    'directory_attempt' => ['phase' => 'none']
];
$polling(201, 'linux');
$beforeA = $stateRow();
FOG\Agent\DirectoryRejoin::beforePoll($dual, $pollBody);
$t->check('poll: the Linux seat\'s directory attempt writes no rejoin state', $beforeA === $stateRow() && [null, null] === $stateRow());
$polling(201, 'windows');
FOG\Agent\DirectoryRejoin::beforePoll($dual, $pollBody);
$t->check('poll: the Windows seat\'s directory attempt is observed', null !== $stateRow()[1]);
Seat::$current = null;
$pdo->exec('UPDATE hostDirectory SET hdRejoinConfig = NULL, hdRejoinState = NULL WHERE hdHostID = 201');

// (b) DirectoryJoin::report without an attempt id.
$joinResult = function ($os) use ($dual, $polling) {
    $polling(201, $os);
    try {
        return DirectoryJoin::report($dual, 201, ['status' => 'failed', 'details' => 'seat test']);
    } catch (\RuntimeException $e) {
        return $e->getCode() . ' ' . $e->getMessage();
    } finally {
        Seat::$current = null;
    }
};
$joinBefore = $joinRow();
$t->check('join result: the Linux seat is refused with 409', '409 not this seat\'s membership' === $joinResult('linux'));
$t->check('join result: the refusal leaves joinAt and joinError unchanged', $joinBefore === $joinRow());
$t->check('join result: the Windows seat\'s result is recorded', 'failed' === $joinResult('windows') && $joinBefore !== $joinRow());

// (c) DirectoryOperation::report for the Windows seat's plan.
$target = ['guid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'sid' => 'S-1-5-21-1048576-4242',
    'account' => 'DUALBOOT$', 'dn' => 'CN=DUALBOOT,OU=Computers,DC=corp,DC=example'];
DirectorySeatFakeLdap::$record = $target;
$dualConfig = ['version' => 1, 'protocol' => 2, 'allow_create' => false, 'name' => 'DUALBOOT',
    'domain' => 'corp.example', 'target' => $target, 'config_revision' => bin2hex(random_bytes(16)),
    'confirmed_by' => 'seat-fixture', 'confirmed_at' => gmdate('c')];
$dualRevision = FOG\Agent\DirectoryOperation::revision($dual, $dualConfig, 'dc1.corp.example');
$attempt = bin2hex(random_bytes(16));
$dualState = ['version' => 1, 'protocol' => 2, 'operation' => 'rename', 'mode' => 'existing',
    'target' => $target, 'host_id' => 201, 'name' => 'DUALBOOT', 'domain' => 'corp.example',
    'dc' => 'dc1.corp.example', 'check_dc' => 'dc1.corp.example', 'phase' => 'started',
    'attempt_id' => $attempt, 'config_revision' => $dualRevision, 'fingerprint' => $fpW,
    'expected_current' => 'OLDNAME', 'expected_pending' => 'DUALBOOT',
    'last_deploy_task_id' => 0, 'trigger_task_id' => 0, 'placement_hold' => true,
    'secure_channel' => 'unknown', 'reason' => 'started'];
$dualBody = ['id' => 201, 'attempt_id' => $attempt, 'config_revision' => $dualRevision,
    'task_id' => 0, 'status' => 'awaiting_reboot', 'native_status' => 0,
    'secure_channel' => 'unknown', 'boot_changed' => false,
    'current_name' => 'DUALBOOT', 'domain' => 'corp.example', 'netbios' => 'CORP'];
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig = ?, hdRejoinState = ? WHERE hdHostID = 201')
    ->execute([json_encode($dualConfig), json_encode($dualState)]);
$rejoinResult = function ($os) use ($dual, $dualBody, $polling) {
    $polling(201, $os);
    try {
        FOG\Agent\DirectoryOperation::report($dual, $dualBody);
        return null;
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    } finally {
        Seat::$current = null;
    }
};
$planBefore = $stateRow();
$t->check('rejoin result: the Linux seat is refused with rejoin_result_mismatch', 'rejoin_result_mismatch' === $rejoinResult('linux'));
$t->check('rejoin result: the refusal leaves the state unchanged', $planBefore === $stateRow());
$t->check(
    'rejoin result: the Windows seat\'s result is accepted',
    null === $rejoinResult('windows')
        && 'awaiting_reboot' === (json_decode($stateRow()[1], true)['phase'] ?? '')
);

// (d) State::facts() runs the placement move (DirectoryFacts::place) only on
// the directory seat's poll. The host is joined, its computer object is not
// in its OU, and no operation holds placement, so a placement attempt leaves
// a mark (placement time or error) on the shared row.
$settingsCache['FOG_DIRECTORY_PLACEMENT_ENABLED'] = ['value' => '1'];
FogTestHarness::setStatic('FOGBase', '_settingsCache', $settingsCache);
$pdo->exec('DROP TABLE IF EXISTS hostFactState');
$pdo->exec($GLOBALS['manifest']['tables']['hostFactState']['create']);
$pdo->exec(
    "UPDATE hosts SET hostADOU = 'OU=Managed,DC=corp,DC=example' WHERE hostID = 201"
);
$pdo->exec(
    "UPDATE hostDirectory SET hdJoined = 1, hdKind = 'ad', hdDomain = 'corp.example',"
    . " hdComputerDN = 'CN=DUALBOOT,OU=Legacy,DC=corp,DC=example', hdMachineAccount = 'DUALBOOT\$',"
    . " hdObservedAt = UTC_TIMESTAMP(), hdPlacementAt = NULL, hdPlacementError = '',"
    . " hdRejoinConfig = NULL, hdRejoinState = NULL WHERE hdHostID = 201"
);
$placementMark = function () use ($pdo) {
    return $pdo->query('SELECT hdPlacementAt, hdPlacementError FROM hostDirectory WHERE hdHostID = 201')->fetch(\PDO::FETCH_NUM);
};
$placed = $hostObj(201)->set('ADOU', 'OU=Managed,DC=corp,DC=example');
$polling(201, 'linux');
FOG\Agent\State::facts($placed, []);
Seat::$current = null;
$t->check('placement: the Linux seat\'s poll does not try to place the host', [null, ''] === $placementMark());
$polling(201, 'windows');
FOG\Agent\State::facts($placed, []);
Seat::$current = null;
$t->check('placement: the Windows seat\'s poll does try', [null, ''] !== $placementMark());

// (e) The directory fact hash is per host, but only the directory seat's
// block is stored. A Linux seat's block must not store the hash: the Windows
// seat's next identical block would then be skipped as already recorded, and
// hostDirectory would keep the older value.
$settingsCache['FOG_DIRECTORY_PLACEMENT_ENABLED'] = ['value' => '0'];
FogTestHarness::setStatic('FOGBase', '_settingsCache', $settingsCache);
$pdo->exec('DELETE FROM hostFactState');
$pdo->exec("UPDATE hostDirectory SET hdJoined = 0, hdKind = 'workgroup', hdDomain = '' WHERE hdHostID = 201");
$joinedBlock = ['directory' => ['joined' => true, 'kind' => 'ad', 'domain' => 'corp.example',
    'netbios' => 'CORP', 'computer_dn' => '', 'machine_account' => 'DUALBOOT$', 'site' => '']];
$directoryHash = function () use ($pdo) {
    return $pdo->query("SELECT COUNT(*) FROM hostFactState WHERE hfsHostID = 201 AND hfsKind = 'directory'")->fetchColumn();
};
$polling(201, 'linux');
$linuxAnswer = FOG\Agent\State::facts($hostObj(201), $joinedBlock);
Seat::$current = null;
$t->check(
    'fact hash: the Linux seat\'s block stores no directory hash and is never asked for',
    0 === (int)$directoryHash() && false === ($linuxAnswer['want_directory'] ?? null)
);
$polling(201, 'windows');
FOG\Agent\State::facts($hostObj(201), $joinedBlock);
Seat::$current = null;
$t->check(
    'fact hash: the Windows seat\'s identical block is still recorded',
    1 === (int)$directoryHash()
        && 'ad' === $pdo->query('SELECT hdKind FROM hostDirectory WHERE hdHostID = 201')->fetchColumn()
);

$t->finish();
