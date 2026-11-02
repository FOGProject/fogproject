<?php
/**
 * A worker is killed inside DirectoryRejoin::change() after its UPDATE and
 * before COMMIT. The row stays locked while it is paused and rolls back to
 * the exact stored bytes when it dies. Real server, scratch database only.
 *
 * Usage: FOG_TEST_DSN=... php tests/agent-directory-transaction-crash.test.php
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory transaction crash: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-transaction-crash';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);

$t = new FogChecks();
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$hostID = random_int(1500000000, 1800000000);
for (;;) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
    $q->execute([$hostID]);
    $exists = (int)$q->fetchColumn();
    $q->closeCursor();
    if (0 === $exists) {
        break;
    }
    $hostID = random_int(1500000000, 1800000000);
}
$hostName = 'P2T' . strtoupper(bin2hex(random_bytes(5)));
$domain = 'u2p2.invalid';
$fingerprint = hash('sha256', random_bytes(32));
$target = [
    'guid' => $guid(),
    'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
    'account' => $hostName . '$',
    'dn' => 'CN=' . $hostName . ',OU=Managed,DC=u2p2,DC=invalid'
];
$revision = bin2hex(random_bytes(16));
$config = [
    'version' => 1,
    'protocol' => 2,
    'allow_create' => false,
    'name' => $hostName,
    'domain' => $domain,
    'target' => $target,
    'config_revision' => $revision,
    'confirmed_by' => 'agent-directory-transaction-crash-fixture',
    'confirmed_at' => gmdate('c')
];
$dc = 'dc1.u2p2.invalid';
$host = (new FOG\Items\Host())->set('id', $hostID)->set('name', $hostName)
    ->set('ADDomain', $domain)->set('useAD', 1)->set('agentFingerprint', $fingerprint);
$observationRevision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
$state = [
    'version' => 1,
    'protocol' => 2,
    'phase' => 'verified',
    'operation' => 'rebind',
    'mode' => 'existing',
    'target' => $target,
    'host_id' => $hostID,
    'name' => $hostName,
    'domain' => $domain,
    'dc' => $dc,
    'check_dc' => $dc,
    'attempt_id' => bin2hex(random_bytes(16)),
    'config_revision' => $revision,
    'fingerprint' => $fingerprint,
    'expected_current' => $hostName,
    'expected_pending' => $hostName,
    'observed_at' => time(),
    'last_deploy_task_id' => 13,
    'trigger_task_id' => 13,
    'placement_hold' => false,
    'observation' => [
        'revision' => $observationRevision,
        'current_name' => $hostName,
        'pending_name' => $hostName,
        'domain' => $domain,
        'netbios' => 'U2P2',
        'role' => 'workstation',
        'secure_channel' => 'true',
        'dc' => $dc,
        'dc_reachable' => true,
        'boot' => ['marker' => 'transaction-crash', 'started' => gmdate(DATE_ATOM)]
    ]
];
$configRaw = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$stateRaw = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$configHash = hash('sha256', $configRaw);
$stateHash = hash('sha256', $stateRaw);
$hostInserted = false;
$directoryInserted = false;
$workerProcess = null;
$workerPipes = null;
$barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    . 'agent-directory-transaction-crash-' . bin2hex(random_bytes(12));
if (!mkdir($barrierDir, 0700)) {
    throw new \RuntimeException('cannot create private transaction crash directory');
}
$resolvedDir = realpath($barrierDir);
$tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (false === $resolvedDir || 0 !== strpos($resolvedDir, $tempRoot)
    || 0 !== strpos(basename($resolvedDir), 'agent-directory-transaction-crash-')) {
    throw new \RuntimeException('transaction crash directory escaped private temp root');
}

$waitForReadyJSON = static function ($path, $process, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            $row = json_decode((string)file_get_contents($path), true);
            if (is_array($row)
                && true === ($row['actual_update_executed'] ?? false)
                && true === ($row['transaction_open'] ?? false)
                && (int)($row['connection_id'] ?? 0) > 0
                && is_string($row['callback_phase'] ?? null)
                && array_key_exists('callback_highwater', $row)) {
                return $row;
            }
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
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
        if (!$status['running']) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if (isset($status['running']) && $status['running']) {
        proc_terminate($process, 9);
        foreach ([1, 2] as $index) {
            fclose($pipes[$index]);
        }
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
$rawDirectory = static function () use ($pdo, $hostID) {
    $q = $pdo->prepare('SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    try {
        return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
    } finally {
        $q->closeCursor();
    }
};
// Whether another session can lock the host's directory row right now. NOWAIT
// answers at once with a lock error when the worker's open transaction holds it.
$rowLocked = static function () use ($hostID) {
    $other = directoryTestPdo();
    try {
        $other->beginTransaction();
        $other->query('SELECT hdHostID FROM hostDirectory WHERE hdHostID=' . (int)$hostID . ' FOR UPDATE NOWAIT')->fetchAll();
        return false;
    } catch (\PDOException $e) {
        return true;
    } finally {
        if ($other->inTransaction()) {
            $other->rollBack();
        }
    }
};
$worker = __DIR__ . '/fixtures/agent-directory-transaction-crash-worker.php';

try {
    $insertHost = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,1,?,?,?)'
    );
    $insertHost->execute([$hostID, $hostName, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $hostInserted = true;
    $insertDirectory = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)'
    );
    $insertDirectory->execute([$hostID, $domain, $target['dn'], $target['account'], $configRaw, $stateRaw]);
    $directoryInserted = true;
    $original = $rawDirectory();
    $fixtureStoredOriginal = is_array($original)
        && $original['hdRejoinConfig'] === $configRaw && $original['hdRejoinState'] === $stateRaw;

    $pipes = [];
    $workerProcess = proc_open(
        [PHP_BINARY, $worker, $resolvedDir, (string)$hostID],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($workerProcess)) {
        throw new \RuntimeException('cannot start transaction crash worker');
    }
    fclose($pipes[0]);
    $workerPipes = $pipes;

    $readyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'updated-before-commit.json';
    $ready = $waitForReadyJSON($readyPath, $workerProcess, 15.0);
    $paused = is_array($ready);
    $lockedAtPause = $rowLocked();
    $uncommittedView = $rawDirectory();
    $oldVisibleWhilePaused = is_array($uncommittedView)
        && $uncommittedView['hdRejoinConfig'] === $configRaw
        && $uncommittedView['hdRejoinState'] === $stateRaw
        && $fixtureStoredOriginal;
    $lockHeldByWorker = is_array($ready) && (int)($ready['connection_id'] ?? 0) > 0
        && $lockedAtPause;

    $terminated = false;
    if (is_resource($workerProcess)) {
        $terminated = proc_terminate($workerProcess, 9);
        list($workerDone, $workerOut, $workerErr, $workerExit) = $waitWorker($workerProcess, $workerPipes, 10.0);
        $workerProcess = null;
        $workerPipes = null;
    } else {
        $workerDone = false;
        $workerOut = '';
        $workerErr = '';
        $workerExit = -1;
    }

    $connectionID = is_array($ready) ? (int)($ready['connection_id'] ?? 0) : 0;
    $connectionGone = false;
    if ($connectionID > 0) {
        $goneDeadline = microtime(true) + 5.0;
        do {
            $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID=?');
            $q->execute([$connectionID]);
            $connectionGone = 0 === (int)$q->fetchColumn();
            $q->closeCursor();
            if (!$connectionGone) {
                usleep(10000);
            }
        } while (!$connectionGone && microtime(true) < $goneDeadline);
    }
    $lockedAfterKill = $rowLocked();
    $afterRollback = $rawDirectory();
    $rollbackExact = is_array($afterRollback)
        && $afterRollback['hdRejoinConfig'] === $configRaw
        && $afterRollback['hdRejoinState'] === $stateRaw
        && is_array($original)
        && $afterRollback['hdRejoinConfig'] === $original['hdRejoinConfig']
        && $afterRollback['hdRejoinState'] === $original['hdRejoinState']
        && hash_equals($configHash, hash('sha256', $afterRollback['hdRejoinConfig']))
        && hash_equals($stateHash, hash('sha256', $afterRollback['hdRejoinState']))
        && 13 === (json_decode($afterRollback['hdRejoinState'], true)['last_deploy_task_id'] ?? null);

    $t->check(
        'real DirectoryRejoin::change UPDATE pauses with the row locked before commit',
        $paused && is_array($ready)
            && true === ($ready['actual_update_executed'] ?? false)
            && true === ($ready['transaction_open'] ?? false)
            && 'started' === ($ready['callback_phase'] ?? null)
            && (int)($ready['callback_highwater'] ?? 0) === 14
            && $lockHeldByWorker && $oldVisibleWhilePaused
    );
    $t->check(
        'worker death rolls back exact config/state bytes and releases the row lock',
        $terminated && $workerDone && $connectionGone
            && !$lockedAfterKill && $rollbackExact
    );
    if (!($terminated && $workerDone && $connectionGone && $rollbackExact && !$lockedAfterKill)) {
        echo "DIAG transaction-crash failure: ", json_encode([
            'pause_ready' => $paused,
            'ready_details' => $ready,
            'fixture_stored_original' => $fixtureStoredOriginal,
            'row_locked_at_pause' => $lockedAtPause,
            'old_state_visible_while_uncommitted' => $oldVisibleWhilePaused,
            'terminated' => $terminated,
            'worker_completed' => $workerDone,
            'worker_exit' => $workerExit,
            'worker_stdout' => $workerOut,
            'worker_stderr' => $workerErr,
            'connection_gone' => $connectionGone,
            'row_locked_after_kill' => $lockedAfterKill,
            'rollback_exact' => $rollbackExact,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
    }
} finally {
    if (is_resource($workerProcess)) {
        @proc_terminate($workerProcess, 9);
        if (is_array($workerPipes)) {
            foreach ([1, 2] as $index) {
                if (isset($workerPipes[$index]) && is_resource($workerPipes[$index])) {
                    fclose($workerPipes[$index]);
                }
            }
        }
        @proc_close($workerProcess);
    }
    if ($directoryInserted) {
        $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
        $q->execute([$hostID]);
    }
    if ($hostInserted) {
        $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
    }
    foreach (glob($resolvedDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($resolvedDir);
}

echo "NOTE: real DirectoryRejoin::change SQL is killed before commit; callback state mutation is fixture-only, not Task claim authorization; no LDAP is connected or called\n";
$t->finish();
