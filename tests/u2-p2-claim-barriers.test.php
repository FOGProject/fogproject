<?php
/** Real-scratch-PDO claim/name-writer barriers. No LDAP/native operation. */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 claim barriers: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('claim barriers require explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-claim-barriers');
$pdo = new PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('claim barriers require a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
}
$link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
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
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$guidBytes = static function ($value) {
    return hex2bin(str_replace('-', '', strtolower((string)$value)));
};
$hostModel = static function ($id, $name, $domain, $fingerprint) {
    return (new FOG\Items\Host())->set('id', $id)->set('name', $name)
        ->set('ADDomain', $domain)->set('useAD', 1)->set('agentFingerprint', $fingerprint);
};
$fixtures = [];
$createFixture = static function () use ($pdo, $guid, $hostModel, &$fixtures) {
    do {
        $primaryID = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$primaryID]);
    } while ((int)$q->fetchColumn() !== 0);
    do {
        $otherID = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$otherID]);
    } while ($otherID === $primaryID || (int)$q->fetchColumn() !== 0);

    $domain = 'corp.example';
    $primaryName = 'P2A' . strtoupper(bin2hex(random_bytes(4)));
    $otherName = 'P2C' . strtoupper(bin2hex(random_bytes(4)));
    // Each scenario gets its own short source name so committed rows from an
    // earlier fixture cannot satisfy or invalidate a later rename claim.
    $sourceName = 'SRC' . strtoupper(bin2hex(random_bytes(4)));
    $fp = hash('sha256', random_bytes(32));
    $otherFP = hash('sha256', random_bytes(32));
    $primaryInserted = false;
    $otherInserted = false;
    $primaryHDInserted = false;
    $otherHDInserted = false;
    try {
        $insertHost = $pdo->prepare(
            'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
            . 'VALUES (?,?,1,?,?,?)'
        );
        $insertHost->execute([$primaryID, $primaryName, $domain, 'OU=Managed,DC=corp,DC=example', $fp]);
        $primaryInserted = true;
        $insertHost->execute([$otherID, $otherName, $domain, 'OU=Managed,DC=corp,DC=example', $otherFP]);
        $otherInserted = true;

        $insertHD = $pdo->prepare(
            'INSERT INTO hostDirectory '
            . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
            . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)'
        );
        $insertHD->execute([$primaryID, $domain, 'CN=' . $sourceName . ',OU=Computers,DC=corp,DC=example',
            $sourceName . '$', NULL, NULL]);
        $primaryHDInserted = true;
        $insertHD->execute([$otherID, $domain, 'CN=' . $otherName . ',OU=Computers,DC=corp,DC=example',
            $otherName . '$', NULL, NULL]);
        $otherHDInserted = true;

        $primaryHost = $hostModel($primaryID, $primaryName, $domain, $fp);
        $config = [
            'version' => 1,
            'protocol' => 2,
            'allow_create' => false,
            'name' => $primaryName,
            'domain' => $domain,
            'config_revision' => bin2hex(random_bytes(16)),
            'retry_authorized' => false,
            'confirmed_by' => 'u2-p2-claim-fixture',
            'confirmed_at' => gmdate('c')
        ];
        $dc = 'dc1.corp.example';
        $revision = FOG\Agent\DirectoryOperation::revision($primaryHost, $config, $dc);
        $target = ['guid' => $guid(), 'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
            'account' => $sourceName . '$',
            'dn' => 'CN=' . $sourceName . ',OU=Computers,DC=corp,DC=example'];
        $attempt = bin2hex(random_bytes(16));
        $state = [
            'version' => 1,
            'protocol' => 2,
            'operation' => 'rename',
            'mode' => 'existing',
            'target' => $target,
            'host_id' => $primaryID,
            'name' => $primaryName,
            'domain' => $domain,
            'dc' => $dc,
            'check_dc' => $dc,
            'phase' => 'ready',
            'attempt_id' => $attempt,
            'config_revision' => $revision,
            'fingerprint' => $fp,
            'expected_current' => $sourceName,
            'expected_pending' => $primaryName,
            'observed_at' => time(),
            'last_deploy_task_id' => 0,
            'trigger_task_id' => 0,
            'placement_hold' => true,
            'observation' => [
                'revision' => $revision,
                'current_name' => $sourceName,
                'pending_name' => $primaryName,
                'domain' => $domain,
                'netbios' => 'CORP',
                'role' => 'workstation',
                'secure_channel' => 'true',
                'dc' => $dc,
                'dc_reachable' => true,
                'boot' => ['marker' => 'claim-barrier', 'started' => gmdate(DATE_ATOM)]
            ]
        ];
        $writeHD = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=?');
        $writeHD->execute([json_encode($config), json_encode($state), $primaryID]);
        $taskCheck = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE taskHostID IN (?,?)');
        $taskCheck->execute([$primaryID, $otherID]);
        if ((int)$taskCheck->fetchColumn() !== 0) {
            throw new RuntimeException('random claim fixture unexpectedly has Tasks');
        }
        $fixtures[] = ['primary' => $primaryID, 'other' => $otherID,
            'primary_hd' => $primaryHDInserted, 'other_hd' => $otherHDInserted,
            'primary_host' => $primaryInserted, 'other_host' => $otherInserted];
        return [
            'primary_id' => $primaryID,
            'primary_name' => $primaryName,
            'primary_fp' => $fp,
            'other_id' => $otherID,
            'other_name' => $otherName,
            'source_name' => $sourceName,
            'config' => $config,
            'state' => $state,
            'target' => $target,
            'body' => ['attempt_id' => $attempt, 'config_revision' => $revision,
                'operation' => 'rename', 'task_id' => 0, 'target_guid' => $target['guid']]
        ];
    } catch (Throwable $e) {
        if ($primaryHDInserted) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$primaryID]);
        }
        if ($otherHDInserted) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$otherID]);
        }
        foreach ([[$primaryInserted, $primaryID], [$otherInserted, $otherID]] as $row) {
            if ($row[0]) {
                $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
                $q->execute([$row[1]]);
            }
        }
        throw $e;
    }
};

$worker = __DIR__ . '/fixtures/u2-p2-claim-worker.php';
$barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    . 'u2-p2-claim-' . bin2hex(random_bytes(12));
if (!mkdir($barrierDir, 0700)) {
    throw new RuntimeException('cannot create private claim barrier directory');
}
$resolvedDir = realpath($barrierDir);
$tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (false === $resolvedDir || 0 !== strpos($resolvedDir, $tempRoot)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-claim-')) {
    throw new RuntimeException('claim barrier directory escaped private temp root');
}
$startWorker = static function ($mode, array $input) use ($worker, $resolvedDir) {
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, '-d', 'error_reporting=E_ALL & ~E_DEPRECATED', $worker, $mode, $resolvedDir,
            json_encode($input, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new RuntimeException('cannot start claim barrier worker');
    }
    fclose($pipes[0]);
    return [$process, $pipes];
};
$waitWorker = static function ($process, array $pipes, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if (isset($status['running']) && $status['running']) {
        proc_terminate($process, 9);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return [false, '', 'worker timed out', -1];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($process);
    return [true, trim((string)$stdout), trim((string)$stderr),
        isset($status['exitcode']) && $status['exitcode'] >= 0 ? $status['exitcode'] : $closed];
};
$readJson = static function ($output) {
    $lines = preg_split('/\r?\n/', trim((string)$output));
    $value = json_decode((string)end($lines), true);
    return is_array($value) ? $value : null;
};
$waitForFile = static function ($path, $process, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            return true;
        }
        if (!proc_get_status($process)['running']) {
            return false;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return false;
};
$waitForUserLock = static function ($connectionID, $seconds) use ($pdo) {
    $deadline = microtime(true) + $seconds;
    do {
        $q = $pdo->prepare('SELECT STATE,INFO FROM information_schema.PROCESSLIST WHERE ID=?');
        $q->execute([(int)$connectionID]);
        $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        $state = strtolower((string)($row['STATE'] ?? ''));
        $info = strtolower((string)($row['INFO'] ?? ''));
        if (false !== strpos($info, 'get_lock') && false !== strpos($state, 'lock')) {
            return true;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return false;
};
$readState = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hdRejoinState FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    return json_decode((string)$q->fetchColumn(), true);
};
$snapshotRows = static function ($sql, array $params) use ($pdo) {
    $q = $pdo->prepare($sql);
    $q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
};
$rawSnapshot = static function (array $fixture) use ($snapshotRows, $guidBytes) {
    $ids = [(int)$fixture['primary_id'], (int)$fixture['other_id']];
    sort($ids, SORT_NUMERIC);
    return [
        'hosts' => $snapshotRows(
            'SELECT * FROM hosts WHERE hostID IN (?,?) ORDER BY hostID',
            $ids
        ),
        'hostDirectory' => $snapshotRows(
            'SELECT * FROM hostDirectory WHERE hdHostID IN (?,?) ORDER BY hdHostID',
            $ids
        ),
        'tasks' => $snapshotRows(
            'SELECT * FROM tasks WHERE taskHostID IN (?,?) ORDER BY taskID',
            $ids
        ),
        'intents' => $snapshotRows(
            'SELECT * FROM directoryPlacementIntent '
            . 'WHERE dpiHostID IN (?,?) OR dpiGUID=? OR dpiSID=? ORDER BY dpiGUID',
            [$ids[0], $ids[1], $guidBytes($fixture['target']['guid']), $fixture['target']['sid']]
        )
    ];
};
$expectNameChange = static function (array $snapshot, $hostID, $name) {
    foreach ($snapshot['hosts'] as &$row) {
        if ((int)$row['hostID'] === (int)$hostID) {
            $row['hostName'] = $name;
        }
    }
    unset($row);
    return $snapshot;
};
$expectReadyClaim = static function (array $snapshot, $hostID) {
    foreach ($snapshot['hostDirectory'] as &$row) {
        if ((int)$row['hdHostID'] !== (int)$hostID) {
            continue;
        }
        $state = json_decode((string)$row['hdRejoinState'], true);
        if (!is_array($state) || 'ready' !== ($state['phase'] ?? null)) {
            throw new RuntimeException('B02 fixture did not begin in ready state');
        }
        $state['phase'] = 'started';
        $state['reason'] = 'mutation_may_have_run';
        $row['hdRejoinState'] = json_encode($state);
    }
    unset($row);
    return $snapshot;
};
$clearBarrierFiles = static function () use ($resolvedDir) {
    foreach (glob($resolvedDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('cannot clear stale barrier file: ' . basename($path));
        }
    }
};
$nameSave = static function ($hostID, $newName) use ($db) {
    $result = $db->query(
        'UPDATE `hosts` SET `hostName`=:new_name WHERE `hostID`=:host_id',
        [],
        ['new_name' => $newName, 'host_id' => $hostID]
    );
    return [false === $result->error, (int)$result->affectedRows()];
};

$claimProcess = null;
$saveProcess = null;
$retryProcess = null;
$claimPipes = [];
$savePipes = [];
$retryPipes = [];
try {
    // Direction 1: the second Host commits the fixture source name first; the planned rename
    // claim must then fail its transactional expected-current-name recheck.
    $saveFirst = $createFixture();
    $clearBarrierFiles();
    $saveFirstBefore = $rawSnapshot($saveFirst);
    $saveResult = $nameSave($saveFirst['other_id'], $saveFirst['source_name']);
    $saveName = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $saveName->execute([$saveFirst['other_id']]);
    $savedName = $saveName->fetchColumn();
    $primary = $hostModel($saveFirst['primary_id'], $saveFirst['primary_name'], 'corp.example', $saveFirst['primary_fp']);
    $saved = FOG\Agent\DirectoryRejoin::settings($primary);
    $claimReason = '';
    try {
        FOG\Agent\DirectoryOperation::claim($primary, $saved, $saveFirst['body']);
    } catch (RuntimeException $e) {
        $claimReason = $e->getMessage();
    }
    $afterSaveFirst = $readState($saveFirst['primary_id']);
    $saveFirstAfter = $rawSnapshot($saveFirst);
    $saveFirstExpected = $expectNameChange(
        $saveFirstBefore,
        $saveFirst['other_id'],
        $saveFirst['source_name']
    );
    $t->check(
        'name-save commit first causes rename claim to reject reassigned source name',
        true === $saveResult[0] && 1 === $saveResult[1] && $saveFirst['source_name'] === $savedName
            && 'rename_name_reassigned' === $claimReason
            && 'ready' === ($afterSaveFirst['phase'] ?? null)
            && 0 === ($afterSaveFirst['last_deploy_task_id'] ?? null)
    );
    $t->check(
        'B01 raw Host/HostDirectory/Task/Target-intent snapshot contains only the accepted name save',
        [] === $saveFirstBefore['tasks'] && [] === $saveFirstBefore['intents']
            && $saveFirstExpected === $saveFirstAfter
            && 'ready' === ($afterSaveFirst['phase'] ?? null)
            && 0 === ($afterSaveFirst['last_deploy_task_id'] ?? null)
    );

    // Direction 2: claim acquires the global fence and row-lock, then pauses.
    // The other Host's real PDODB name update is observed waiting on GET_LOCK.
    // Resuming A commits started; B then sees its protected name footprint.
    $claimFirst = $createFixture();
    $clearBarrierFiles();
    $claimFirstBefore = $rawSnapshot($claimFirst);
    list($claimProcess, $claimPipes) = $startWorker('claim-paused', [
        'host_id' => $claimFirst['primary_id'],
        'name' => $claimFirst['primary_name'],
        'fingerprint' => $claimFirst['primary_fp'],
        'domain' => 'corp.example',
        'body' => $claimFirst['body']
    ]);
    $claimReadyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'claim-row-locked.json';
    $claimReady = $waitForFile($claimReadyPath, $claimProcess, 12.0);
    if ($claimReady) {
        list($saveProcess, $savePipes) = $startWorker('save-name', [
            'host_id' => $claimFirst['other_id'],
            'new_name' => $claimFirst['source_name']
        ]);
        $saveReadyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'save-started.json';
        $saveReady = $waitForFile($saveReadyPath, $saveProcess, 5.0);
        $saveReadyInfo = $saveReady ? json_decode((string)file_get_contents($saveReadyPath), true) : null;
        $actuallyBlocked = $saveReady && is_array($saveReadyInfo)
            && $waitForUserLock($saveReadyInfo['connection_id'] ?? 0, 2.2);
    } else {
        $saveReady = false;
        $saveReadyInfo = null;
        $actuallyBlocked = false;
    }
    // Always release the claim worker, including on failed barrier setup.
    file_put_contents($resolvedDir . DIRECTORY_SEPARATOR . 'resume-claim', 'go', LOCK_EX);
    list($claimDone, $claimOut, $claimErr, $claimExit) =
        $waitWorker($claimProcess, $claimPipes, 12.0);
    $claimProcess = null;
    if (is_resource($saveProcess)) {
        list($saveDone, $saveOut, $saveErr, $saveExit) = $waitWorker($saveProcess, $savePipes, 12.0);
        $saveProcess = null;
    } else {
        $saveDone = false;
        $saveOut = '';
        $saveErr = '';
        $saveExit = -1;
    }
    $claimResult = $readJson($claimOut);
    $saveFault = $readJson($saveOut);
    $startedState = $readState($claimFirst['primary_id']);
    $otherAfter = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $otherAfter->execute([$claimFirst['other_id']]);
    $otherNameAfter = $otherAfter->fetchColumn();
    $claimFirstPassed = $claimReady && $saveReady && $actuallyBlocked
            && $claimDone && 0 === $claimExit && is_array($claimResult)
            && true === ($claimResult['claimed'] ?? false)
            && 'started' === ($claimResult['phase'] ?? null)
            && $saveDone && 0 === $saveExit && is_array($saveFault)
            && 'directory_name_operation_protected' === ($saveFault['reason'] ?? null)
            && $claimFirst['other_name'] === $otherNameAfter
            && 'started' === ($startedState['phase'] ?? null)
            && 0 === ($startedState['last_deploy_task_id'] ?? null);
    $claimFirstAfter = $rawSnapshot($claimFirst);
    $claimFirstExpected = $expectReadyClaim($claimFirstBefore, $claimFirst['primary_id']);
    if (!$claimFirstPassed) {
        $diagnostic = [
            'scenario' => 'claim_first',
            'barriers' => [
                'claim_row_locked_file_observed' => $claimReady,
                'save_started_file_observed' => $saveReady,
                'save_waiting_on_database_lock_observed' => $actuallyBlocked,
                'save_started_details' => $saveReadyInfo,
            ],
            'claim_worker' => [
                'completed' => $claimDone,
                'exit_code' => $claimExit,
                'stdout' => $claimOut,
                'stderr' => $claimErr,
                'parsed_result' => $claimResult,
                'reason' => is_array($claimResult) ? ($claimResult['reason'] ?? null) : null,
                'phase' => is_array($claimResult) ? ($claimResult['phase'] ?? null) : null,
            ],
            'save_worker' => [
                'completed' => $saveDone,
                'exit_code' => $saveExit,
                'stdout' => $saveOut,
                'stderr' => $saveErr,
                'parsed_result' => $saveFault,
                'reason' => is_array($saveFault) ? ($saveFault['reason'] ?? null) : null,
            ],
            'final_database' => [
                'primary_operation_state' => $startedState,
                'secondary_host_name' => $otherNameAfter,
                'expected_secondary_host_name' => $claimFirst['other_name'],
            ],
        ];
        echo "DIAG claim-first failure: ", json_encode(
            $diagnostic,
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ), "\n";
    }
    $t->check('claim commit first blocks the waiting name save on active rename footprint', $claimFirstPassed);

    $t->check(
        'B02 raw Host/HostDirectory/Task/Target-intent snapshot permits only ready-to-started claim',
        [] === $claimFirstBefore['tasks'] && [] === $claimFirstBefore['intents']
            && $claimFirstExpected === $claimFirstAfter
            && 'started' === ($startedState['phase'] ?? null)
            && 0 === ($startedState['last_deploy_task_id'] ?? null)
    );

    // B07: cover each protected native phase with the public Retry path. In
    // each case the Retry transaction pauses after the Host row lock and an
    // independent PDO demonstrably waits on GET_LOCK before both writes fail.
    foreach (['started', 'awaiting_reboot', 'unknown', 'failed'] as $retryPhase) {
        $retryVsName = $createFixture();
        $retryState = $retryVsName['state'];
        $retryState['phase'] = $retryPhase;
        $retryState['reason'] = 'u2-p2-b07-' . $retryPhase;
        $seedRetryState = $pdo->prepare(
            'UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=?'
        );
        $seedRetryState->execute([json_encode($retryState), $retryVsName['primary_id']]);
        $retryVsNameBefore = $rawSnapshot($retryVsName);
        $clearBarrierFiles();
        list($retryProcess, $retryPipes) = $startWorker('retry-paused', [
            'host_id' => $retryVsName['primary_id'],
            'name' => $retryVsName['primary_name'],
            'fingerprint' => $retryVsName['primary_fp'],
            'domain' => 'corp.example'
        ]);
        $retryReadyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'claim-row-locked.json';
        $retryReady = $waitForFile($retryReadyPath, $retryProcess, 12.0);
        if ($retryReady) {
            list($saveProcess, $savePipes) = $startWorker('save-name', [
                'host_id' => $retryVsName['other_id'],
                'new_name' => $retryVsName['source_name']
            ]);
            $saveReadyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'save-started.json';
            $saveReady = $waitForFile($saveReadyPath, $saveProcess, 5.0);
            $saveReadyInfo = $saveReady ? json_decode((string)file_get_contents($saveReadyPath), true) : null;
            $retrySaveBlocked = $saveReady && is_array($saveReadyInfo)
                && $waitForUserLock($saveReadyInfo['connection_id'] ?? 0, 2.2);
        } else {
            $saveReady = false;
            $saveReadyInfo = null;
            $retrySaveBlocked = false;
        }
        file_put_contents($resolvedDir . DIRECTORY_SEPARATOR . 'resume-retry', 'go', LOCK_EX);
        list($retryDone, $retryOut, $retryErr, $retryExit) =
            $waitWorker($retryProcess, $retryPipes, 12.0);
        $retryProcess = null;
        if (is_resource($saveProcess)) {
            list($retrySaveDone, $retrySaveOut, $retrySaveErr, $retrySaveExit) =
                $waitWorker($saveProcess, $savePipes, 12.0);
            $saveProcess = null;
        } else {
            $retrySaveDone = false;
            $retrySaveOut = '';
            $retrySaveErr = '';
            $retrySaveExit = -1;
        }
        $retryResult = $readJson($retryOut);
        $retrySaveResult = $readJson($retrySaveOut);
        $retryVsNameAfter = $rawSnapshot($retryVsName);
        $retryBothRefused = $retryReady && $saveReady && $retrySaveBlocked
            && $retryDone && 0 === $retryExit && is_array($retryResult)
            && false === ($retryResult['configured'] ?? true)
            && 'rejoin_attempt_requires_review' === ($retryResult['reason'] ?? null)
            && $retrySaveDone && 0 === $retrySaveExit && is_array($retrySaveResult)
            && false === ($retrySaveResult['saved'] ?? true)
            && 'directory_name_operation_protected' === ($retrySaveResult['reason'] ?? null)
            && false === (bool)$retryErr && false === (bool)$retrySaveErr;
        $t->check(
            'B07 ' . $retryPhase . ' Retry and competing old-name saver both refuse after a measured GET_LOCK wait',
            $retryBothRefused
        );
        $t->check(
            'B07 ' . $retryPhase . ' refusal preserves full Host/HostDirectory/Task/related-intent raw snapshots',
            [] === $retryVsNameBefore['tasks'] && [] === $retryVsNameBefore['intents']
                && $retryVsNameBefore === $retryVsNameAfter
        );
        if (!$retryBothRefused || $retryVsNameBefore !== $retryVsNameAfter) {
            echo "DIAG B07 retry/name barrier: ", json_encode([
                'phase' => $retryPhase,
                'retry_ready' => $retryReady,
                'save_ready' => $saveReady,
                'save_waiting_on_get_lock' => $retrySaveBlocked,
                'save_start' => $saveReadyInfo,
                'retry_worker' => ['done' => $retryDone, 'exit' => $retryExit, 'out' => $retryOut, 'err' => $retryErr],
                'save_worker' => ['done' => $retrySaveDone, 'exit' => $retrySaveExit,
                    'out' => $retrySaveOut, 'err' => $retrySaveErr],
                'before' => $retryVsNameBefore,
                'after' => $retryVsNameAfter
            ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
        }
    }
} finally {
    foreach ([$claimProcess, $saveProcess, $retryProcess] as $process) {
        if (is_resource($process)) {
            @proc_terminate($process, 9);
        }
    }
    foreach ($fixtures as $fixture) {
        foreach ([$fixture['primary'], $fixture['other']] as $hostID) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$hostID]);
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$hostID]);
        }
    }
    foreach (glob($resolvedDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($resolvedDir);
}

echo "NOTE: B01/B02/B07 use real scratch SQL and the production name fence; empty Tasks and related intent rows are asserted as raw empty snapshots, not N/A; no LDAP/native operation is called\n";
$t->finish();
