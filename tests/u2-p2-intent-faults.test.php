<?php
/**
 * Real-PDO fault-boundary checks for DirectoryPlacementIntent.
 * Scratch-only; LDAP is never connected or sent a request.
 */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 intent faults: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('intent fault checks require explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-intent-faults');

$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('intent fault checks require a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
}

// Use the production wrapper and real PDO path. The scratch-only intent table
// must already be installed by the opted-in PHP test runner fixture.
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
    return hex2bin(str_replace('-', '', strtolower($value)));
};
$readIntent = static function ($targetGuid) use ($pdo, $guidBytes) {
    $q = $pdo->prepare(
        'SELECT dpiGUID,dpiSID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult '
        . 'FROM directoryPlacementIntent WHERE dpiGUID=?'
    );
    $q->bindValue(1, $guidBytes($targetGuid), PDO::PARAM_LOB);
    $q->execute();
    return $q->fetch(PDO::FETCH_ASSOC);
};
$matchesResultRecord = static function ($raw, $success, $result, $projection) {
    $record = json_decode((string)$raw, true);
    return is_array($record)
        && 1 === ($record['version'] ?? null)
        && $success === ($record['ldap_success'] ?? null)
        && $result === ($record['ldap_result'] ?? null)
        && is_string($record['ldap_result_sha256'] ?? null)
        && hash_equals(hash('sha256', $result), $record['ldap_result_sha256'])
        && $projection === ($record['facts_projection'] ?? null)
        && false === ($record['truncated'] ?? null);
};

$fixtures = [];
$createFixture = static function () use ($pdo, $guid, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
    } while ((int)$q->fetchColumn() !== 0);

    $name = 'P2F' . strtoupper(bin2hex(random_bytes(6)));
    $domain = 'u2p2.invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $oldDN = 'CN=' . $name . ',OU=Legacy,DC=u2p2,DC=invalid';
    $target = ['guid' => $guid(), 'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
        'account' => $name . '$', 'dn' => $oldDN];
    $destination = ['guid' => $guid(), 'dn' => 'OU=Managed,DC=u2p2,DC=invalid'];

    $hostInserted = false;
    $directoryInserted = false;
    try {
        $q = $pdo->prepare(
            'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
            . 'VALUES (?,?,1,?,?,?)'
        );
        $q->execute([$hostID, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
        $hostInserted = true;
        $q = $pdo->prepare(
            'INSERT INTO hostDirectory '
            . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
            . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,NULL)'
        );
        $q->execute([$hostID, $domain, $oldDN, $name . '$']);
        $directoryInserted = true;

        $snapshotMethod = new ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
        $snapshotMethod->setAccessible(true);
        $snapshot = $snapshotMethod->invoke(null, $pdo, $hostID);
        $payload = [
            'version' => 1,
            'host_id' => $hostID,
            'snapshot' => $snapshot,
            'target' => $target,
            'destination' => $destination,
            'dc' => 'dc1.u2p2.invalid',
            'nc' => 'DC=u2p2,DC=invalid',
            'rdn' => 'CN=' . $name
        ];
        $fixture = ['host_id' => $hostID, 'target' => $target, 'destination' => $destination,
            'payload' => $payload];
        $fixtures[] = ['host_id' => $hostID, 'target' => $target,
            'host_inserted' => $hostInserted, 'directory_inserted' => $directoryInserted];
        return $fixture;
    } catch (Throwable $e) {
        if ($directoryInserted) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$hostID]);
        }
        if ($hostInserted) {
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$hostID]);
        }
        throw $e;
    }
};

$worker = __DIR__ . '/fixtures/u2-p2-intent-fault-worker.php';
$barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    . 'u2-p2-intent-fault-' . bin2hex(random_bytes(12));
if (!mkdir($barrierDir, 0700)) {
    throw new RuntimeException('cannot create private intent fault directory');
}
$resolvedDir = realpath($barrierDir);
$tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (false === $resolvedDir || 0 !== strpos($resolvedDir, $tempRoot)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-intent-fault-')) {
    throw new RuntimeException('intent fault directory escaped private temp root');
}

$startWorker = static function ($mode, array $input) use ($worker, $resolvedDir) {
    // DirectoryPlacementIntent tickets intentionally carry the database GUID
    // as 16 opaque bytes. Keep the payload/hash untouched and transport only
    // that one binary field through a reversible hex envelope.
    if (isset($input['ticket']) && is_array($input['ticket'])
        && isset($input['ticket']['guid']) && is_string($input['ticket']['guid'])) {
        if (16 !== strlen($input['ticket']['guid'])) {
            throw new RuntimeException('intent ticket GUID is not the expected 16-byte value');
        }
        $input['ticket']['guid_hex'] = bin2hex($input['ticket']['guid']);
        unset($input['ticket']['guid']);
    }
    $encoded = json_encode($input, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $worker, $mode, $resolvedDir, $encoded],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new RuntimeException('cannot start intent fault worker');
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
$readJson = static function ($output) {
    $lines = preg_split('/\r?\n/', trim((string)$output));
    $decoded = json_decode((string)end($lines), true);
    return is_array($decoded) ? $decoded : null;
};
$waitForSignal = static function ($path, $process, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path) && is_array(json_decode((string)file_get_contents($path), true))) {
            return true;
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
            return false;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return false;
};

$ackProcess = null;
$crashProcess = null;
$falseCommitProcess = null;
$successAckProcess = null;
$successPreCommitProcess = null;
$releaseProcess = null;
$freshReleaseProcess = null;
$dbKillProcess = null;
try {
    // prepare() persists PREPARED before returning its ticket. Simulate its
    // commit ACK being lost, then prove a fresh caller cannot reconstruct or
    // retrieve the ticket by repeating prepare().
    $prepareFixture = $createFixture();
    list($prepareProcess, $preparePipes) = $startWorker(
        'prepare-commit-ack-loss',
        ['payload' => $prepareFixture['payload']]
    );
    list($prepareDone, $prepareOut, $prepareErr, $prepareExit) =
        $waitWorker($prepareProcess, $preparePipes, 15.0);
    $prepareResult = $readJson($prepareOut);
    $prepareRow = $readIntent($prepareFixture['target']['guid']);
    $t->check(
        'prepare commit ACK loss leaves durable PREPARED but returns no ticket',
        $prepareDone && 0 === $prepareExit && is_array($prepareResult)
            && true === ($prepareResult['simulated_commit_ack_loss'] ?? false)
            && 1 === ($prepareResult['parent_commit_count'] ?? null)
            && false === ($prepareResult['ticket_returned'] ?? null)
            && is_array($prepareRow) && 'PREPARED' === $prepareRow['dpiStatus']
    );
    list($prepareRetryProcess, $prepareRetryPipes) = $startWorker(
        'fresh-worker-prepare',
        ['payload' => $prepareFixture['payload']]
    );
    list($prepareRetryDone, $prepareRetryOut, $prepareRetryErr, $prepareRetryExit) =
        $waitWorker($prepareRetryProcess, $prepareRetryPipes, 15.0);
    $prepareRetry = $readJson($prepareRetryOut);
    $prepareAfterRetry = $readIntent($prepareFixture['target']['guid']);
    $t->check(
        'fresh prepare cannot mint a ticket from committed PREPARED after lost ACK',
        $prepareRetryDone && 0 === $prepareRetryExit && is_array($prepareRetry)
            && false === ($prepareRetry['ticket_returned'] ?? null)
            && 'directory_placement_unresolved' === ($prepareRetry['reason'] ?? null)
            && is_array($prepareAfterRetry) && 'PREPARED' === $prepareAfterRetry['dpiStatus']
    );

    // The worker calls parent::commit() successfully, then throws once before
    // returning to dispatch(). This is an injected ACK-loss simulation, not a
    // network/driver failure.
    $ackFixture = $createFixture();
    $ackTicket = FOG\Agent\DirectoryPlacementIntent::prepare($ackFixture['payload']);
    list($ackProcess, $ackPipes) = $startWorker('dispatch-commit-ack-loss', ['ticket' => $ackTicket]);
    list($ackDone, $ackOut, $ackErr, $ackExit) = $waitWorker($ackProcess, $ackPipes, 15.0);
    $ackProcess = null;
    $ack = $readJson($ackOut);
    $ackRow = $readIntent($ackFixture['target']['guid']);
    $t->check(
        'post-parent-commit exception leaves durable MAY_HAVE_RUN and returns no token',
        $ackDone && 0 === $ackExit && is_array($ack)
            && true === ($ack['simulated_commit_ack_loss'] ?? false)
            && 1 === ($ack['parent_commit_count'] ?? null)
            && false === ($ack['token_returned'] ?? null)
            && is_array($ackRow) && 'MAY_HAVE_RUN' === $ackRow['dpiStatus']
    );
    list($freshAckProcess, $freshAckPipes) = $startWorker(
        'fresh-worker-dispatch', ['ticket' => $ackTicket]
    );
    list($freshAckDone, $freshAckOut, $freshAckErr, $freshAckExit) =
        $waitWorker($freshAckProcess, $freshAckPipes, 15.0);
    $freshAck = $readJson($freshAckOut);
    $freshAckRow = $readIntent($ackFixture['target']['guid']);
    $t->check(
        'fresh worker cannot redispatch after MAY COMMIT exception',
        $freshAckDone && 0 === $freshAckExit && is_array($freshAck)
            && false === ($freshAck['token_returned'] ?? null)
            && 'directory_placement_dispatch_changed' === ($freshAck['reason'] ?? null)
            && 'MAY_HAVE_RUN' === ($freshAck['status'] ?? null)
            && is_array($freshAckRow) && 'MAY_HAVE_RUN' === $freshAckRow['dpiStatus']
    );

    // PDO::commit() can report false after the server committed. The strict
    // DirectoryNameFence wrapper must refuse to issue a permit anyway.
    $falseCommitFixture = $createFixture();
    $falseCommitTicket = FOG\Agent\DirectoryPlacementIntent::prepare($falseCommitFixture['payload']);
    list($falseCommitProcess, $falseCommitPipes) = $startWorker(
        'dispatch-commit-false', ['ticket' => $falseCommitTicket]
    );
    list($falseCommitDone, $falseCommitOut, $falseCommitErr, $falseCommitExit) =
        $waitWorker($falseCommitProcess, $falseCommitPipes, 15.0);
    $falseCommitProcess = null;
    $falseCommit = $readJson($falseCommitOut);
    $falseCommitRow = $readIntent($falseCommitFixture['target']['guid']);
    $t->check(
        'false COMMIT acknowledgement after parent commit leaves MAY without permit',
        $falseCommitDone && 0 === $falseCommitExit && is_array($falseCommit)
            && true === ($falseCommit['injected_false_after_parent_commit'] ?? false)
            && 1 === ($falseCommit['parent_commit_count'] ?? null)
            && true === ($falseCommit['parent_commit_succeeded'] ?? false)
            && false === ($falseCommit['token_returned'] ?? null)
            && 'directory_fence_transaction_commit_unknown' === ($falseCommit['reason'] ?? null)
            && is_array($falseCommitRow) && 'MAY_HAVE_RUN' === $falseCommitRow['dpiStatus']
    );
    list($freshFalseCommitProcess, $freshFalseCommitPipes) = $startWorker(
        'fresh-worker-dispatch', ['ticket' => $falseCommitTicket]
    );
    list($freshFalseCommitDone, $freshFalseCommitOut, $freshFalseCommitErr, $freshFalseCommitExit) =
        $waitWorker($freshFalseCommitProcess, $freshFalseCommitPipes, 15.0);
    $freshFalseCommit = $readJson($freshFalseCommitOut);
    $freshFalseCommitRow = $readIntent($falseCommitFixture['target']['guid']);
    $t->check(
        'fresh worker cannot redispatch after false MAY COMMIT acknowledgement',
        $freshFalseCommitDone && 0 === $freshFalseCommitExit && is_array($freshFalseCommit)
            && false === ($freshFalseCommit['token_returned'] ?? null)
            && 'directory_placement_dispatch_changed' === ($freshFalseCommit['reason'] ?? null)
            && 'MAY_HAVE_RUN' === ($freshFalseCommit['status'] ?? null)
            && is_array($freshFalseCommitRow) && 'MAY_HAVE_RUN' === $freshFalseCommitRow['dpiStatus']
    );

    // The result transaction can commit SUCCEEDED before its acknowledgement
    // is lost. The worker must keep the receipt frozen, reject a changed result,
    // and never recreate the consumed permit. The result is injected; LDAP is
    // not connected or called in this fixture.
    $successAckFixture = $createFixture();
    $successAckTicket = FOG\Agent\DirectoryPlacementIntent::prepare($successAckFixture['payload']);
    list($successAckProcess, $successAckPipes) = $startWorker(
        'finish-success-commit-ack-loss', ['ticket' => $successAckTicket]
    );
    list($successAckDone, $successAckOut, $successAckErr, $successAckExit) =
        $waitWorker($successAckProcess, $successAckPipes, 15.0);
    $successAckProcess = null;
    $successAck = $readJson($successAckOut);
    $successAckRow = $readIntent($successAckFixture['target']['guid']);
    $t->check(
        'lost SUCCESS-result COMMIT acknowledgement freezes receipt and prevents replay',
        $successAckDone && 0 === $successAckExit && is_array($successAck)
            && 'fixture_simulated_commit_ack_loss_after_parent_commit' === ($successAck['finish_reason'] ?? null)
            && 2 === ($successAck['parent_commit_count'] ?? null)
            && 'SUCCEEDED' === ($successAck['durable_status'] ?? null)
            && 'SUCCEEDED' === ($successAckRow['dpiStatus'] ?? null)
            && $matchesResultRecord($successAck['durable_result'] ?? null, true,
                'injected-success-result', 'applied')
            && 'directory_placement_result_changed' === ($successAck['changed_outcome_reason'] ?? null)
            && 'directory_placement_result_generation_changed' === ($successAck['same_outcome_retry_reason'] ?? null)
            && 'directory_move_dispatch_permit_required' === ($successAck['duplicate_consume_reason'] ?? null)
            && true === ($successAck['permit_consumed_once'] ?? false)
            && false === ($successAck['ldap_connected'] ?? true)
            && false === ($successAck['ldap_request_sent'] ?? true)
    );
    list($freshSuccessAckProcess, $freshSuccessAckPipes) = $startWorker(
        'fresh-worker-dispatch', ['ticket' => $successAckTicket]
    );
    list($freshSuccessAckDone, $freshSuccessAckOut, $freshSuccessAckErr, $freshSuccessAckExit) =
        $waitWorker($freshSuccessAckProcess, $freshSuccessAckPipes, 15.0);
    $freshSuccessAck = $readJson($freshSuccessAckOut);
    $successAckAfterFresh = $readIntent($successAckFixture['target']['guid']);
    $t->check(
        'fresh worker refuses redispatch after SUCCESS-result COMMIT acknowledgement loss',
        $freshSuccessAckDone && 0 === $freshSuccessAckExit && is_array($freshSuccessAck)
            && false === ($freshSuccessAck['token_returned'] ?? null)
            && 'directory_placement_dispatch_changed' === ($freshSuccessAck['reason'] ?? null)
            && 'SUCCEEDED' === ($freshSuccessAck['status'] ?? null)
            && is_array($successAckAfterFresh) && 'SUCCEEDED' === $successAckAfterFresh['dpiStatus']
            && $matchesResultRecord($successAckAfterFresh['dpiResult'], true,
                'injected-success-result', 'applied')
    );

    // Contrast the post-parent ACK-loss terminal case above: this exception is
    // injected before PDO::commit(), leaving the transaction open for the
    // production rollback path. Retrying the identical receipt/outcome on the
    // same healthy PDO should then persist SUCCEEDED exactly once.
    $successPreCommitFixture = $createFixture();
    $successPreCommitTicket = FOG\Agent\DirectoryPlacementIntent::prepare($successPreCommitFixture['payload']);
    list($successPreCommitProcess, $successPreCommitPipes) = $startWorker(
        'finish-before-parent-commit-throw', ['ticket' => $successPreCommitTicket]
    );
    list($successPreCommitDone, $successPreCommitOut, $successPreCommitErr, $successPreCommitExit) =
        $waitWorker($successPreCommitProcess, $successPreCommitPipes, 15.0);
    $successPreCommitProcess = null;
    $successPreCommit = $readJson($successPreCommitOut);
    $successPreCommitRow = $readIntent($successPreCommitFixture['target']['guid']);
    $t->check(
        'pre-parent result COMMIT exception rolls back, then identical receipt succeeds once',
        $successPreCommitDone && 0 === $successPreCommitExit && is_array($successPreCommit)
            && 'fixture_commit_exception_before_parent_commit' === ($successPreCommit['first_finish_reason'] ?? null)
            && 'MAY_HAVE_RUN' === ($successPreCommit['status_after_rollback'] ?? null)
            && false === ($successPreCommit['transaction_open_after_rollback'] ?? true)
            && '' === ($successPreCommit['retry_reason'] ?? null)
            && 3 === ($successPreCommit['commit_attempts'] ?? null)
            && 2 === ($successPreCommit['parent_commit_count'] ?? null)
            && 'SUCCEEDED' === ($successPreCommit['final_status'] ?? null)
            && 'SUCCEEDED' === ($successPreCommitRow['dpiStatus'] ?? null)
            && $matchesResultRecord($successPreCommit['final_result'] ?? null, true,
                'injected-success-result', 'applied')
            && 'directory_move_dispatch_permit_required' === ($successPreCommit['duplicate_consume_reason'] ?? null)
            && true === ($successPreCommit['permit_consumed_once'] ?? false)
            && false === ($successPreCommit['ldap_connected'] ?? true)
            && false === ($successPreCommit['ldap_request_sent'] ?? true)
    );

    // A separate worker obtains and consumes the opaque permit, but never
    // calls LDAP. Terminating that process closes its owning PDO session before
    // finish(); a fresh worker must not reconstruct a permit from durable MAY.
    $crashFixture = $createFixture();
    $crashTicket = FOG\Agent\DirectoryPlacementIntent::prepare($crashFixture['payload']);
    list($crashProcess, $crashPipes) = $startWorker('dispatch-consume-hold', ['ticket' => $crashTicket]);
    $readyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'consumed.json';
    $ready = $waitForSignal($readyPath, $crashProcess, 15.0);
    $readyInfo = $ready ? json_decode((string)file_get_contents($readyPath), true) : null;
    $t->check(
        'worker consumed the one-shot permit without LDAP I/O before termination',
        $ready && is_array($readyInfo) && true === ($readyInfo['consumed'] ?? false)
            && 'MAY_HAVE_RUN' === ($readyInfo['status'] ?? null)
            && false === ($readyInfo['ldap_connected'] ?? true)
    );
    if (is_resource($crashProcess)) {
        proc_terminate($crashProcess, 9);
        list($crashDone, $crashOut, $crashErr, $crashExit) = $waitWorker($crashProcess, $crashPipes, 10.0);
        $crashProcess = null;
    } else {
        $crashDone = false;
        $crashExit = -1;
    }
    $crashRow = $readIntent($crashFixture['target']['guid']);
    $connectionGone = false;
    if (is_array($readyInfo) && isset($readyInfo['connection_id'])) {
        $connectionDeadline = microtime(true) + 5.0;
        do {
            $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID=?');
            $q->execute([(int)$readyInfo['connection_id']]);
            $connectionGone = 0 === (int)$q->fetchColumn();
            if (!$connectionGone) {
                usleep(10000);
            }
        } while (!$connectionGone && microtime(true) < $connectionDeadline);
    }
    $t->check(
        'terminated permit owner connection is gone and durable MAY_HAVE_RUN remains before finish',
        $ready && $crashDone && $connectionGone && is_array($crashRow)
            && 'MAY_HAVE_RUN' === $crashRow['dpiStatus']
    );

    // RELEASE_LOCK executes on the server, then the statement fixture throws
    // before PHP receives its result. Dispatch must return no permit and poison
    // this process despite the lock now being free; a fresh process still sees
    // durable MAY_HAVE_RUN and cannot reconstruct a permit.
    $releaseFixture = $createFixture();
    $releaseTicket = FOG\Agent\DirectoryPlacementIntent::prepare($releaseFixture['payload']);
    list($releaseProcess, $releasePipes) = $startWorker(
        'dispatch-release-ack-loss', ['ticket' => $releaseTicket]
    );
    list($releaseDone, $releaseOut, $releaseErr, $releaseExit) =
        $waitWorker($releaseProcess, $releasePipes, 15.0);
    $releaseProcess = null;
    $release = $readJson($releaseOut);
    $releaseRow = $readIntent($releaseFixture['target']['guid']);
    $t->check(
        'executed RELEASE_LOCK with lost reply returns no permit and poisons same process',
        $releaseDone && 0 === $releaseExit && is_array($release)
            && true === ($release['release_execute_fault_injected'] ?? false)
            && 'fixture_release_ack_loss_after_parent_release' === ($release['dispatch_reason'] ?? null)
            && false === ($release['token_returned'] ?? null)
            && 'directory_fence_poisoned' === ($release['same_process_reason'] ?? null)
            && array_key_exists('lock_owner', $release) && null === $release['lock_owner']
            && is_array($releaseRow) && 'MAY_HAVE_RUN' === $releaseRow['dpiStatus']
    );
    list($freshReleaseProcess, $freshReleasePipes) = $startWorker(
        'fresh-worker-dispatch', ['ticket' => $releaseTicket]
    );
    list($freshReleaseDone, $freshReleaseOut, $freshReleaseErr, $freshReleaseExit) =
        $waitWorker($freshReleaseProcess, $freshReleasePipes, 15.0);
    $freshReleaseProcess = null;
    $freshRelease = $readJson($freshReleaseOut);
    $releaseAfterFresh = $readIntent($releaseFixture['target']['guid']);
    $t->check(
        'fresh process refuses dispatch after RELEASE_LOCK reply loss',
        $freshReleaseDone && 0 === $freshReleaseExit && is_array($freshRelease)
            && false === ($freshRelease['token_returned'] ?? null)
            && 'directory_placement_dispatch_changed' === ($freshRelease['reason'] ?? null)
            && 'MAY_HAVE_RUN' === ($freshRelease['status'] ?? null)
            && is_array($releaseAfterFresh) && 'MAY_HAVE_RUN' === $releaseAfterFresh['dpiStatus']
    );

    // Kill the worker's real PDO session after it has consumed the opaque
    // permit, then inject only the finish() result. The admin and verification
    // connections are independent PDO objects; the worker's PDODB link is not
    // replaced or repaired.
    $dbKillFixture = $createFixture();
    $dbKillTicket = FOG\Agent\DirectoryPlacementIntent::prepare($dbKillFixture['payload']);
    list($dbKillProcess, $dbKillPipes) = $startWorker(
        'dispatch-consume-kill-before-finish', ['ticket' => $dbKillTicket]
    );
    $killReadyPath = $resolvedDir . DIRECTORY_SEPARATOR . 'consumed-awaiting-kill.json';
    $killReady = $waitForSignal($killReadyPath, $dbKillProcess, 15.0);
    $killReadyInfo = $killReady
        ? json_decode((string)file_get_contents($killReadyPath), true) : null;
    $killIssued = false;
    $killConnectionGone = false;
    $killError = '';
    $adminPDO = null;
    if ($killReady && is_array($killReadyInfo) && !empty($killReadyInfo['connection_id'])) {
        try {
            $adminPDO = new PDO(
                $dsn,
                getenv('MJ1_DB_USER') ?: 'root',
                getenv('MJ1_DB_PASSWORD') ?: '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $adminDatabase = (string)$adminPDO->query('SELECT DATABASE()')->fetchColumn();
            if ($adminDatabase !== $database) {
                throw new RuntimeException('admin PDO selected a different scratch database');
            }
            $adminPDO->exec('KILL CONNECTION ' . (int)$killReadyInfo['connection_id']);
            $killIssued = true;
            $killDeadline = microtime(true) + 5.0;
            do {
                $q = $adminPDO->prepare(
                    'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID=?'
                );
                $q->execute([(int)$killReadyInfo['connection_id']]);
                $killConnectionGone = 0 === (int)$q->fetchColumn();
                if (!$killConnectionGone) {
                    usleep(10000);
                }
            } while (!$killConnectionGone && microtime(true) < $killDeadline);
        } catch (Throwable $e) {
            $killError = $e->getMessage();
        }
    }
    file_put_contents($resolvedDir . DIRECTORY_SEPARATOR . 'finish-after-kill', 'go', LOCK_EX);
    list($dbKillDone, $dbKillOut, $dbKillErr, $dbKillExit) =
        $waitWorker($dbKillProcess, $dbKillPipes, 15.0);
    $dbKillProcess = null;
    if ($adminPDO instanceof PDO) {
        $adminPDO = null;
    }
    $dbKillResult = $readJson($dbKillOut);

    $freshReadPDO = new PDO(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $freshReadDatabase = (string)$freshReadPDO->query('SELECT DATABASE()')->fetchColumn();
    $freshIntentQuery = $freshReadPDO->prepare(
        'SELECT dpiGUID,dpiSID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult '
        . 'FROM directoryPlacementIntent WHERE dpiGUID=?'
    );
    $freshIntentQuery->bindValue(1, $guidBytes($dbKillFixture['target']['guid']), PDO::PARAM_LOB);
    $freshIntentQuery->execute();
    $dbKillRow = $freshIntentQuery->fetch(PDO::FETCH_ASSOC) ?: null;
    $dbKillPayload = is_array($dbKillRow) ? json_decode($dbKillRow['dpiPayload'], true) : null;
    $dbKillPayloadIntact = is_array($dbKillRow)
        && hash_equals($dbKillTicket['guid'], $dbKillRow['dpiGUID'])
        && $dbKillTicket['sid'] === $dbKillRow['dpiSID']
        && (int)$dbKillRow['dpiGeneration'] === (int)$dbKillTicket['generation']
        && hash_equals($dbKillTicket['hash'], $dbKillRow['dpiHash'])
        && hash_equals($dbKillRow['dpiHash'], hash('sha256', $dbKillRow['dpiPayload']))
        && $dbKillPayload === $dbKillFixture['payload'];
    $t->check(
        'KILLed worker DB session makes finish throw while fresh PDO sees intact MAY_HAVE_RUN',
        $killReady && is_array($killReadyInfo)
            && true === ($killReadyInfo['consumed'] ?? false)
            && 'MAY_HAVE_RUN' === ($killReadyInfo['status'] ?? null)
            && false === ($killReadyInfo['ldap_connected'] ?? true)
            && false === ($killReadyInfo['ldap_request_sent'] ?? true)
            && $killIssued && $killConnectionGone
            && $dbKillDone && 0 === $dbKillExit && is_array($dbKillResult)
            && (int)($dbKillResult['connection_id'] ?? 0) === (int)$killReadyInfo['connection_id']
            && true === ($dbKillResult['finish_threw'] ?? false)
            && '' !== ($dbKillResult['finish_reason'] ?? '')
            && true === ($dbKillResult['same_worker_retry_threw'] ?? false)
            && '' !== ($dbKillResult['same_worker_retry_reason'] ?? '')
            && 'directory_placement_result_changed' === ($dbKillResult['changed_outcome_reason'] ?? null)
            && true === ($dbKillResult['connection_probe_threw'] ?? false)
            && null === ($dbKillResult['connection_probe_id'] ?? null)
            && true === ($dbKillResult['original_pdodb_link_same_before_kill'] ?? false)
            && true === ($dbKillResult['original_pdodb_link_same_after_retry'] ?? false)
            && 'injected-ldap-success' === ($dbKillResult['injected_result'] ?? null)
            && false === ($dbKillResult['ldap_connected'] ?? true)
            && false === ($dbKillResult['ldap_request_sent'] ?? true)
            && $freshReadDatabase === $database
            && is_array($dbKillRow) && 'MAY_HAVE_RUN' === $dbKillRow['dpiStatus']
            && (int)$dbKillRow['dpiGeneration'] === (int)$dbKillTicket['generation']
            && null === $dbKillRow['dpiResult']
            && $dbKillPayloadIntact
    );
    if (!($killReady && $killIssued && $killConnectionGone && $dbKillDone
        && is_array($dbKillResult) && true === ($dbKillResult['finish_threw'] ?? false)
        && is_array($dbKillRow) && 'MAY_HAVE_RUN' === $dbKillRow['dpiStatus']
        && $dbKillPayloadIntact)) {
        echo "DIAG DB-session-kill failure: ", json_encode([
            'worker_ready' => $killReady,
            'ready_details' => $killReadyInfo,
            'kill_issued' => $killIssued,
            'worker_connection_gone' => $killConnectionGone,
            'kill_error' => $killError,
            'worker_completed' => $dbKillDone,
            'worker_exit' => $dbKillExit,
            'worker_stdout' => $dbKillOut,
            'worker_stderr' => $dbKillErr,
            'worker_result' => $dbKillResult,
            'fresh_database' => $freshReadDatabase,
            'intent_status' => is_array($dbKillRow) ? $dbKillRow['dpiStatus'] : null,
            'payload_hash_intact' => $dbKillPayloadIntact,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
    }

    // A separate live scratch-PDO writer contending after the worker session
    // is gone must still observe the durable placement hold.
    $dbKillHostBeforeQuery = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $dbKillHostBeforeQuery->execute([$dbKillFixture['host_id']]);
    $dbKillHostBefore = $dbKillHostBeforeQuery->fetchColumn();
    $blockedRenameName = 'P2R' . strtoupper(bin2hex(random_bytes(4)));
    $blockedRename = (new FOG\Managers\HostManager())->update(
        ['id' => $dbKillFixture['host_id']],
        'AND',
        ['name' => $blockedRenameName]
    );
    $dbKillHostAfterQuery = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $dbKillHostAfterQuery->execute([$dbKillFixture['host_id']]);
    $t->check(
        'post-KILL HostManager rename remains blocked by durable MAY_HAVE_RUN',
        false === $blockedRename && $dbKillHostBefore === $dbKillHostAfterQuery->fetchColumn()
            && 'MAY_HAVE_RUN' === ($readIntent($dbKillFixture['target']['guid'])['dpiStatus'] ?? null)
    );

    // Seed a valid ready claim snapshot for this same Host/Target in the
    // scratch fixture. DirectoryOperation::claim must stop at the active
    // placement intent before Task/native state can advance.
    $claimHost = new FOG\Items\Host($dbKillFixture['host_id']);
    $claimConfig = ['version' => 1, 'enabled' => true, 'allow_create' => false,
        'name' => (string)$claimHost->get('name'), 'domain' => (string)$claimHost->get('ADDomain')];
    $claimDC = 'dc1.u2p2.invalid';
    $claimRevision = FOG\Agent\DirectoryOperation::revision($claimHost, $claimConfig, $claimDC);
    $claimAttempt = bin2hex(random_bytes(16));
    $claimState = [
        'version' => 1, 'protocol' => 2, 'operation' => 'rebind', 'mode' => 'existing',
        'target' => $dbKillFixture['target'], 'host_id' => (int)$dbKillFixture['host_id'],
        'name' => (string)$claimHost->get('name'), 'domain' => (string)$claimHost->get('ADDomain'),
        'dc' => $claimDC, 'check_dc' => $claimDC, 'phase' => 'ready',
        'attempt_id' => $claimAttempt, 'config_revision' => $claimRevision,
        'fingerprint' => (string)$claimHost->get('agentFingerprint'),
        'expected_current' => (string)$claimHost->get('name'),
        'expected_pending' => (string)$claimHost->get('name'),
        'observed_at' => time(), 'last_deploy_task_id' => 0, 'trigger_task_id' => 0,
        'observation' => [
            'revision' => $claimRevision,
            'domain' => (string)$claimHost->get('ADDomain'),
            'netbios' => 'U2P2',
            'current_name' => (string)$claimHost->get('name'),
            'pending_name' => (string)$claimHost->get('name'),
            'secure_channel' => 'true', 'dc_reachable' => true,
            'role' => 'member_server', 'dc' => $claimDC
        ]
    ];
    $seedClaimState = $pdo->prepare(
        'UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=?'
    );
    $seedClaimState->execute([
        json_encode($claimConfig, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        json_encode($claimState, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        $dbKillFixture['host_id']
    ]);
    $claimSaved = FOG\Agent\DirectoryRejoin::settings($claimHost);
    $claimBody = [
        'attempt_id' => $claimAttempt, 'config_revision' => $claimRevision,
        'operation' => 'rebind', 'task_id' => 0,
        'target_guid' => $dbKillFixture['target']['guid']
    ];
    $claimReason = '';
    try {
        FOG\Agent\DirectoryOperation::claim($claimHost, $claimSaved, $claimBody);
    } catch (Throwable $e) {
        $claimReason = $e->getMessage();
    }
    $claimAfter = FOG\Agent\DirectoryRejoin::settings($claimHost);
    $t->check(
        'valid post-KILL claim is blocked by its active placement Target without state advance',
        'directory_placement_target_protected' === $claimReason
            && $claimSaved === $claimAfter
            && 'MAY_HAVE_RUN' === ($readIntent($dbKillFixture['target']['guid'])['dpiStatus'] ?? null)
    );

    // Fixture-only PDODB link replacement must not let an original process
    // receipt finish through a different PDO/session, even to the same scratch DB.
    $linkChangeFixture = $createFixture();
    $linkChangeTicket = FOG\Agent\DirectoryPlacementIntent::prepare($linkChangeFixture['payload']);
    list($linkChangeProcess, $linkChangePipes) = $startWorker(
        'dispatch-consume-replace-link-before-finish', ['ticket' => $linkChangeTicket]
    );
    list($linkChangeDone, $linkChangeOut, $linkChangeErr, $linkChangeExit) =
        $waitWorker($linkChangeProcess, $linkChangePipes, 15.0);
    $linkChange = $readJson($linkChangeOut);
    $linkChangeRow = $readIntent($linkChangeFixture['target']['guid']);
    $t->check(
        'original receipt refuses fixture-replaced PDO and preserves MAY with NULL result',
        $linkChangeDone && 0 === $linkChangeExit && is_array($linkChange)
            && true === ($linkChange['fixture_replacement_installed'] ?? false)
            && (int)($linkChange['original_connection_id'] ?? 0) > 0
            && (int)($linkChange['replacement_connection_id'] ?? 0) > 0
            && (int)$linkChange['original_connection_id'] !== (int)$linkChange['replacement_connection_id']
            && $database === ($linkChange['selected_database'] ?? null)
            && true === ($linkChange['permit_consumed_before_replacement'] ?? false)
            && 'directory_placement_result_connection_changed' === ($linkChange['finish_reason'] ?? null)
            && 'MAY_HAVE_RUN' === ($linkChange['original_status'] ?? null)
            && null === ($linkChange['original_result'] ?? null)
            && false === ($linkChange['ldap_connected'] ?? true)
            && false === ($linkChange['ldap_request_sent'] ?? true)
            && is_array($linkChangeRow) && 'MAY_HAVE_RUN' === $linkChangeRow['dpiStatus']
            && hash_equals($linkChangeTicket['guid'], $linkChangeRow['dpiGUID'])
            && $linkChangeTicket['sid'] === $linkChangeRow['dpiSID']
            && null === $linkChangeRow['dpiResult']
            && (int)$linkChangeRow['dpiGeneration'] === (int)$linkChangeTicket['generation']
            && hash_equals($linkChangeTicket['hash'], $linkChangeRow['dpiHash'])
    );

    list($freshKillProcess, $freshKillPipes) = $startWorker(
        'fresh-worker-dispatch', ['ticket' => $dbKillTicket]
    );
    list($freshKillDone, $freshKillOut, $freshKillErr, $freshKillExit) =
        $waitWorker($freshKillProcess, $freshKillPipes, 15.0);
    $freshKill = $readJson($freshKillOut);
    $freshAfterKill = $readIntent($dbKillFixture['target']['guid']);
    $t->check(
        'fresh worker refuses redispatch after DB loss during finish',
        $freshKillDone && 0 === $freshKillExit && is_array($freshKill)
            && false === ($freshKill['token_returned'] ?? null)
            && 'directory_placement_dispatch_changed' === ($freshKill['reason'] ?? null)
            && 'MAY_HAVE_RUN' === ($freshKill['status'] ?? null)
            && is_array($freshAfterKill) && 'MAY_HAVE_RUN' === $freshAfterKill['dpiStatus']
    );

    list($freshProcess, $freshPipes) = $startWorker('fresh-worker-dispatch', ['ticket' => $crashTicket]);
    list($freshDone, $freshOut, $freshErr, $freshExit) = $waitWorker($freshProcess, $freshPipes, 15.0);
    $fresh = $readJson($freshOut);
    $crashAfterFresh = $readIntent($crashFixture['target']['guid']);
    $t->check(
        'fresh process cannot mint a permit for the prior MAY_HAVE_RUN generation',
        $freshDone && 0 === $freshExit && is_array($fresh)
            && false === ($fresh['token_returned'] ?? null)
            && 'directory_placement_dispatch_changed' === ($fresh['reason'] ?? null)
            && is_array($crashAfterFresh) && 'MAY_HAVE_RUN' === $crashAfterFresh['dpiStatus']
    );

    // Existing false-result lifecycle semantics: the result is injected by
    // this test and does not represent an LDAP attempt or response.
    $unknownFixture = $createFixture();
    $unknownTicket = FOG\Agent\DirectoryPlacementIntent::prepare($unknownFixture['payload']);
    $ldapToken = new FOG\Net\FOGLdap(); // Construction only; never connect().
    $permit = FOG\Agent\DirectoryPlacementIntent::dispatch($unknownTicket, $ldapToken);
    $entry = FOG\Net\FOGLdap::guidDN($unknownFixture['target']['guid']);
    $parent = FOG\Net\FOGLdap::guidDN($unknownFixture['destination']['guid']);
    FOG\Agent\DirectoryPlacementIntent::consumePermit($permit, $ldapToken, $entry, $parent);
    FOG\Agent\DirectoryPlacementIntent::finish($permit, false, 'fixture-injected-false-result');
    $unknownRow = $readIntent($unknownFixture['target']['guid']);
    $unknownRefused = '';
    try {
        FOG\Agent\DirectoryPlacementIntent::dispatch($unknownTicket, $ldapToken);
    } catch (FOG\Agent\DirectoryFenceRefusal $e) {
        $unknownRefused = $e->getMessage();
    }
    $unknownAfter = $readIntent($unknownFixture['target']['guid']);
    $t->check(
        'injected false result persists UNKNOWN and blocks redispatch',
        is_array($unknownRow) && 'UNKNOWN' === $unknownRow['dpiStatus']
            && $matchesResultRecord($unknownRow['dpiResult'], false,
                'fixture-injected-false-result', 'not_attempted')
            && 'directory_placement_dispatch_changed' === $unknownRefused
            && is_array($unknownAfter) && 'UNKNOWN' === $unknownAfter['dpiStatus']
    );
} finally {
    foreach ([$ackProcess, $crashProcess, $falseCommitProcess, $successAckProcess,
        $successPreCommitProcess, $releaseProcess,
        $freshReleaseProcess, $dbKillProcess] as $process) {
        if (is_resource($process)) {
            @proc_terminate($process, 9);
        }
    }
    foreach ($fixtures as $fixture) {
        if (!empty($fixture['target']['guid'])) {
            $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?');
            $q->bindValue(1, $guidBytes($fixture['target']['guid']), PDO::PARAM_LOB);
            $q->execute();
        }
        if (!empty($fixture['directory_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$fixture['host_id']]);
        }
        if (!empty($fixture['host_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$fixture['host_id']]);
        }
    }
    foreach (glob($resolvedDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($resolvedDir);
}

echo "NOTE: COMMIT return/exception and RELEASE_LOCK reply faults are injected after real PDO operations; worker termination is real process/session closure; no LDAP connection or request is made\n";
$t->finish();
