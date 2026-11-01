<?php
/** B06/B18 real report transaction and name-writer barrier; LDAP is stubbed. */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 report barrier: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('report barrier requires explicit scratch opt-in and mysql DSN');
}

class U2P2ReportTestFakeLdap
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
class_alias(U2P2ReportTestFakeLdap::class, 'FOG\\Net\\FOGLdap', false);

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-report-barrier');
$pdo = new \PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('report barrier requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME differs from validated scratch database');
}
$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);
$t = new FogChecks();

$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$newName = 'P2R' . strtoupper(bin2hex(random_bytes(4)));
$oldName = 'SRC' . strtoupper(bin2hex(random_bytes(4)));
$domain = 'corp.example';
$fingerprint = hash('sha256', random_bytes(32));
$hostID = random_int(1500000000, 1800000000);
$competitorID = random_int(1500000000, 1800000000);
while ($competitorID === $hostID) {
    $competitorID = random_int(1500000000, 1800000000);
}
$target = [
    'guid' => $guid(),
    'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
    'account' => $newName . '$',
    'dn' => 'CN=' . $newName . ',OU=Computers,DC=corp,DC=example'
];
$host = (new FOG\Items\Host())->set('id', $hostID)->set('name', $newName)
    ->set('ADDomain', $domain)->set('useAD', 1)->set('agentFingerprint', $fingerprint);
$config = [
    'version' => 1, 'protocol' => 2, 'allow_create' => false,
    'name' => $newName, 'domain' => $domain, 'target' => $target,
    'config_revision' => bin2hex(random_bytes(16)),
    'confirmed_by' => 'u2-p2-report-fixture', 'confirmed_at' => gmdate('c')
];
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, 'dc1.corp.example');
$attempt = bin2hex(random_bytes(16));
$state = [
    'version' => 1, 'protocol' => 2, 'operation' => 'rename', 'mode' => 'existing',
    'target' => $target, 'host_id' => $hostID, 'name' => $newName, 'domain' => $domain,
    'dc' => 'dc1.corp.example', 'check_dc' => 'dc1.corp.example', 'phase' => 'started',
    'attempt_id' => $attempt, 'config_revision' => $revision, 'fingerprint' => $fingerprint,
    'expected_current' => $oldName, 'expected_pending' => $newName,
    'last_deploy_task_id' => 0, 'trigger_task_id' => 0, 'placement_hold' => true,
    'secure_channel' => 'unknown', 'reason' => 'started'
];
$body = [
    'id' => $hostID, 'attempt_id' => $attempt, 'config_revision' => $revision,
    'task_id' => 0, 'status' => 'awaiting_reboot', 'native_status' => 0,
    'secure_channel' => 'unknown', 'boot_changed' => false,
    'current_name' => $newName, 'domain' => $domain, 'netbios' => 'CORP'
];
U2P2ReportTestFakeLdap::$record = $target;
$settingsCache = [];
foreach ([
    'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.example',
    'FOG_DIRECTORY_BIND_DN' => 'CN=fixture',
    'FOG_DIRECTORY_BIND_PASSWORD' => 'fixture-only',
    'FOG_DIRECTORY_BASE_DN' => 'DC=corp,DC=example',
    'FOG_DIRECTORY_CA_CERT' => ''
] as $key => $value) {
    $settingsCache[$key] = ['value' => $value];
}
FogTestHarness::setStatic('FOGBase', '_settingsCache', $settingsCache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);

$insertedHostIDs = [];
$insertedDirectoryIDs = [];
$barrierDir = null;
$processes = [];
try {
    $insertHost = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)'
    );
    $insertHost->execute([$hostID, $newName, $domain, 'OU=Managed,DC=corp,DC=example', $fingerprint]);
    $insertedHostIDs[] = $hostID;
    $insertHost->execute([$competitorID, 'CMP' . strtoupper(bin2hex(random_bytes(3))), $domain,
        'OU=Managed,DC=corp,DC=example', hash('sha256', random_bytes(32))]);
    $insertedHostIDs[] = $competitorID;
    $insertHD = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)'
    );
    $insertHD->execute([$hostID, $domain, 'CN=' . $oldName . ',OU=Computers,DC=corp,DC=example',
        $oldName . '$', json_encode($config, JSON_THROW_ON_ERROR), json_encode($state, JSON_THROW_ON_ERROR)]);
    $insertedDirectoryIDs[] = $hostID;

    $awaitingBody = $body;
    FOG\Agent\DirectoryOperation::report($host, $awaitingBody);
    $awaiting = FOG\Agent\DirectoryRejoin::settings($host);
    $t->check('B06 report persists awaiting_reboot with identity and task highwater retained',
        'awaiting_reboot' === ($awaiting['state']['phase'] ?? null)
        && $target['guid'] === ($awaiting['state']['target']['guid'] ?? null)
        && $target['sid'] === ($awaiting['state']['target']['sid'] ?? null)
        && 0 === ($awaiting['state']['last_deploy_task_id'] ?? null)
        && 0 === ($awaiting['state']['trigger_task_id'] ?? null));

    $saveBlockedReason = '';
    try {
        $db->query('UPDATE `hosts` SET `hostName`=:new_name WHERE `hostID`=:host_id', [],
            ['new_name' => $oldName, 'host_id' => $competitorID]);
    } catch (\Throwable $e) {
        $saveBlockedReason = $e->getMessage();
    }
    $competitorNameQ = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $competitorNameQ->execute([$competitorID]);
    $competitorBeforeRelease = $competitorNameQ->fetchColumn();
    $t->check('B06 awaiting_reboot footprint blocks another Host from taking expected current name',
        'directory_name_operation_protected' === $saveBlockedReason
        && $competitorBeforeRelease !== $oldName);

    $beforeBroken = FOG\Agent\DirectoryRejoin::settings($host);
    $brokenBody = $body;
    $brokenBody['status'] = 'verified';
    $brokenBody['secure_channel'] = 'broken';
    $brokenBody['boot_changed'] = true;
    $brokenBody['current_name'] = $newName;
    $reason = '';
    try {
        FOG\Agent\DirectoryOperation::report($host, $brokenBody);
    } catch (\Throwable $e) {
        $reason = $e->getMessage();
    }
    $t->check('B18 verified report rejects broken secure channel without changing phase or footprint',
        'rejoin_verification_incomplete' === $reason
        && $beforeBroken === FOG\Agent\DirectoryRejoin::settings($host));

    $barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'u2-p2-report-barrier-' . bin2hex(random_bytes(10));
    if (!mkdir($barrierDir, 0700)) {
        throw new \RuntimeException('cannot create report barrier directory');
    }
    $barrierDir = realpath($barrierDir);
    $tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (false === $barrierDir || 0 !== strpos($barrierDir, $tempRoot)
        || 0 !== strpos(basename($barrierDir), 'u2-p2-report-barrier-')) {
        throw new \RuntimeException('report barrier directory escaped private temp root');
    }
    $worker = __DIR__ . '/fixtures/u2-p2-report-barrier-worker.php';
    $verifiedBody = $body;
    $verifiedBody['status'] = 'verified';
    $verifiedBody['secure_channel'] = 'healthy';
    $verifiedBody['boot_changed'] = true;
    $verifiedBody['current_name'] = $newName;
    $input = [
        'database' => $database, 'host_id' => $hostID, 'name' => $newName,
        'domain' => $domain, 'fingerprint' => $fingerprint, 'directory_target' => $target,
        'old_name' => $oldName, 'body' => $verifiedBody
    ];
    $start = static function ($mode, array $payload) use ($worker, $barrierDir, &$processes) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', $worker, $mode, $barrierDir,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new \RuntimeException('cannot start report barrier worker');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
        return [$process, $pipes];
    };
    $waitJSON = static function ($path, $process, $seconds, array $required) {
        $deadline = microtime(true) + $seconds;
        do {
            if (is_file($path)) {
                $row = json_decode((string)file_get_contents($path), true);
                $valid = is_array($row);
                foreach ($required as $key) {
                    $valid = $valid && array_key_exists($key, $row);
                }
                if ($valid && isset($row['pid'])) {
                    $valid = (int)$row['pid'] > 0;
                }
                if ($valid && isset($row['connection_id'])) {
                    $valid = (int)$row['connection_id'] > 0;
                }
                if ($valid) {
                    return $row;
                }
            }
            if (!proc_get_status($process)['running']) {
                return null;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        return null;
    };
    $waitWorker = static function ($process, array $pipes, $seconds) {
        $deadline = microtime(true) + $seconds;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) { break; }
            usleep(10000);
        } while (microtime(true) < $deadline);
        if (!isset($status['running']) || $status['running']) {
            proc_terminate($process, 9);
            foreach ([1, 2] as $i) { fclose($pipes[$i]); }
            proc_close($process);
            return [false, '', 'worker timed out', -1];
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $closed = proc_close($process);
        $exit = isset($status['exitcode']) && $status['exitcode'] >= 0
            ? $status['exitcode'] : $closed;
        return [true, trim((string)$stdout), trim((string)$stderr), $exit];
    };

    list($reportProcess, $reportPipes) = $start('report-pause', $input);
    $readyPath = $barrierDir . DIRECTORY_SEPARATOR . 'report-updated-before-commit.json';
    $ready = $waitJSON($readyPath, $reportProcess, 20.0,
        ['pid','connection_id','actual_update_executed','transaction_open','phase_in_update','last_deploy_task_id','target_guid','target_sid']);
    $namespace = (string)$pdo->query('SELECT CASE WHEN @@lower_case_table_names=0 THEN DATABASE() ELSE LOWER(DATABASE()) END')->fetchColumn();
    $lockKey = 'fog.mj1.name.v1:' . substr(hash('sha256', $namespace), 0, 40);
    $ownerQ = $pdo->prepare('SELECT IS_USED_LOCK(?)');
    $ownerQ->execute([$lockKey]);
    $lockOwner = (int)$ownerQ->fetchColumn();
    $rawStateQ = $pdo->prepare('SELECT hdRejoinState FROM hostDirectory WHERE hdHostID=?');
    $rawStateQ->execute([$hostID]);
    $stateWhilePaused = json_decode((string)$rawStateQ->fetchColumn(), true);
    $startedPath = $barrierDir . DIRECTORY_SEPARATOR . 'save-started.json';
    list($saveProcess, $savePipes) = $start('save-name', $input + ['competitor_id' => $competitorID]);
    $saveReady = $waitJSON($startedPath, $saveProcess, 10.0, ['pid','connection_id']);
    $waitingOnLock = false;
    if (is_array($saveReady)) {
        $deadline = microtime(true) + 10.0;
        do {
            $q = $pdo->prepare('SELECT STATE,INFO FROM information_schema.PROCESSLIST WHERE ID=?');
            $q->execute([(int)$saveReady['connection_id']]);
            $proc = $q->fetch(\PDO::FETCH_ASSOC) ?: [];
            $waitingOnLock = false !== strpos(strtolower((string)($proc['INFO'] ?? '')), 'get_lock')
                && false !== strpos(strtolower((string)($proc['STATE'] ?? '')), 'lock');
            if (!$waitingOnLock) { usleep(10000); }
        } while (!$waitingOnLock && microtime(true) < $deadline);
    }
    file_put_contents($barrierDir . DIRECTORY_SEPARATOR . 'go-report', 'go', LOCK_EX);
    list($reportDone, $reportOut, $reportErr, $reportExit) = $waitWorker($reportProcess, $reportPipes, 25.0);
    list($saveDone, $saveOut, $saveErr, $saveExit) = $waitWorker($saveProcess, $savePipes, 25.0);
    $reportProcess = $saveProcess = null;
    if ('' !== $reportErr) {
        fwrite(STDERR, "report worker stderr:\n" . $reportErr . "\n");
    }
    if ('' !== $saveErr) {
        fwrite(STDERR, "name-save worker stderr:\n" . $saveErr . "\n");
    }
    $reportResult = json_decode((string)$reportOut, true);
    $saveResult = json_decode((string)$saveOut, true);
    $after = FOG\Agent\DirectoryRejoin::settings($host);
    $savedCompetitorQ = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $savedCompetitorQ->execute([$competitorID]);
    $savedCompetitor = $savedCompetitorQ->fetchColumn();
    $b18Passed = is_array($ready) && true === ($ready['actual_update_executed'] ?? false)
        && true === ($ready['transaction_open'] ?? false) && 'verified' === ($ready['phase_in_update'] ?? null)
        && (int)$ready['last_deploy_task_id'] === 0
        && $target['guid'] === ($ready['target_guid'] ?? null) && $target['sid'] === ($ready['target_sid'] ?? null)
        && $lockOwner === (int)$ready['connection_id']
        && 'awaiting_reboot' === ($stateWhilePaused['phase'] ?? null)
        && is_array($saveReady) && $waitingOnLock
        && $reportDone && 0 === $reportExit && is_array($reportResult) && true === ($reportResult['reported'] ?? false)
        && 'verified' === ($reportResult['phase'] ?? null)
        && $target['guid'] === ($reportResult['target_guid'] ?? null)
        && $target['sid'] === ($reportResult['target_sid'] ?? null)
        && $saveDone && 0 === $saveExit && is_array($saveResult) && true === ($saveResult['saved'] ?? false)
        && $oldName === $savedCompetitor
        && 'verified' === ($after['state']['phase'] ?? null)
        && $target['guid'] === ($after['state']['target']['guid'] ?? null)
        && $target['sid'] === ($after['state']['target']['sid'] ?? null)
        && 0 === ($after['state']['last_deploy_task_id'] ?? null)
        && 0 === ($after['state']['trigger_task_id'] ?? null)
        && false === ($after['state']['placement_hold'] ?? true)
        && $target['guid'] === ($after['config']['target']['guid'] ?? null)
        && $target['sid'] === ($after['config']['target']['sid'] ?? null);
    if (!$b18Passed) {
        fwrite(STDERR, 'B18 diagnostic: ' . json_encode([
            'ready' => $ready,
            'lock_owner' => $lockOwner,
            'state_while_paused' => $stateWhilePaused,
            'save_ready' => $saveReady,
            'waiting_on_lock' => $waitingOnLock,
            'report_done' => $reportDone,
            'report_exit' => $reportExit,
            'report_out' => $reportOut,
            'report_err' => $reportErr,
            'save_done' => $saveDone,
            'save_exit' => $saveExit,
            'save_out' => $saveOut,
            'save_err' => $saveErr,
            'saved_competitor' => $savedCompetitor,
            'after_state' => $after['state'] ?? null,
            'after_config_target' => $after['config']['target'] ?? null
        ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
    $t->check('B18 real report UPDATE holds namespace lock until verified COMMIT; name writer waits then succeeds',
        $b18Passed);
} finally {
    foreach ($processes as $entry) {
        if (is_resource($entry[0])) {
            @proc_terminate($entry[0], 9);
            foreach ([1, 2] as $i) {
                if (is_resource($entry[1][$i])) { @fclose($entry[1][$i]); }
            }
            @proc_close($entry[0]);
        }
    }
    foreach ($insertedDirectoryIDs as $id) {
        $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
        $q->execute([$id]);
    }
    foreach ($insertedHostIDs as $id) {
        $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        $q->execute([$id]);
    }
    if (is_string($barrierDir) && is_dir($barrierDir)
        && 0 === strpos(realpath($barrierDir), rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        && 0 === strpos(basename($barrierDir), 'u2-p2-report-barrier-')) {
        foreach (glob($barrierDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) { @unlink($file); }
        @rmdir($barrierDir);
    }
}
$t->finish();
