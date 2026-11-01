<?php
/** B39 placement-intent / operation-claim ordering on real scratch PDO. No LDAP. */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 placement claim barriers: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('placement claim barriers require explicit scratch opt-in and mysql DSN');
}
require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-placement-claim-barriers');
$pdo = new \PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('placement claim barriers require a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME differs from validated scratch DB');
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
$guidBytes = static function ($value) { return hex2bin(str_replace('-', '', strtolower($value))); };
$newHostID = static function () use ($pdo) {
    do {
        $id = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$id]);
    } while ((int)$q->fetchColumn() !== 0);
    return $id;
};
$fixtures = [];
$createFixture = static function ($targetAccount = null) use ($pdo, $guid, $newHostID, &$fixtures) {
    $hostID = $newHostID();
    $name = 'P2C' . strtoupper(bin2hex(random_bytes(4)));
    $domain = 'u2p2.invalid';
    $targetName = null === $targetAccount ? $name : rtrim((string)$targetAccount, '$');
    $fingerprint = hash('sha256', random_bytes(32));
    $target = [
        'guid' => $guid(),
        'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
        'account' => $targetName . '$',
        'dn' => 'CN=' . $targetName . ',OU=Computers,DC=u2p2,DC=invalid'
    ];
    $host = (new FOG\Items\Host())->set('id', $hostID)->set('name', $name)
        ->set('ADDomain', $domain)->set('ADOU', 'OU=Managed,DC=u2p2,DC=invalid')
        ->set('useAD', 1)->set('agentFingerprint', $fingerprint);
    $config = [
        'version' => 1, 'protocol' => 2, 'allow_create' => false,
        'name' => $name, 'domain' => $domain, 'target' => $target,
        'config_revision' => bin2hex(random_bytes(16)),
        'confirmed_by' => 'u2-p2-placement-claim-fixture', 'confirmed_at' => gmdate('c')
    ];
    $revision = FOG\Agent\DirectoryOperation::revision($host, $config, 'dc1.u2p2.invalid');
    $observation = [
        'revision' => $revision, 'current_name' => $name, 'pending_name' => $name,
        'domain' => $domain, 'netbios' => 'U2P2', 'role' => 'workstation',
        'secure_channel' => 'true', 'dc' => 'dc1.u2p2.invalid', 'dc_reachable' => true,
        'boot' => ['marker' => 'placement-claim', 'started' => gmdate(DATE_ATOM)]
    ];
    $state = [
        'version' => 1, 'protocol' => 2, 'operation' => 'rebind', 'mode' => 'existing',
        'target' => $target, 'host_id' => $hostID, 'name' => $name, 'domain' => $domain,
        'dc' => 'dc1.u2p2.invalid', 'check_dc' => 'dc1.u2p2.invalid', 'phase' => 'ready',
        'attempt_id' => bin2hex(random_bytes(16)), 'config_revision' => $revision,
        'fingerprint' => $fingerprint, 'expected_current' => $name, 'expected_pending' => $name,
        'observed_at' => time(), 'observation' => $observation,
        'last_deploy_task_id' => 0, 'trigger_task_id' => 0, 'placement_hold' => false
    ];
    $fixtureIndex = count($fixtures);
    $fixtures[] = [
        'host_id' => $hostID, 'target' => $target,
        'host_inserted' => false, 'directory_inserted' => false
    ];
    $insertHost = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)'
    );
    $insertHost->execute([$hostID, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[$fixtureIndex]['host_inserted'] = true;
    $insertDirectory = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)'
    );
    $insertDirectory->execute([$hostID, $domain, $target['dn'], $target['account'],
        json_encode($config, JSON_THROW_ON_ERROR), json_encode($state, JSON_THROW_ON_ERROR)]);
    $fixtures[$fixtureIndex]['directory_inserted'] = true;
    $fixture = [
        'host_id' => $hostID, 'host_name' => $name, 'domain' => $domain,
        'fingerprint' => $fingerprint, 'host' => $host, 'target' => $target,
        'config' => $config, 'state' => $state,
        'body' => ['attempt_id' => $state['attempt_id'], 'config_revision' => $revision,
            'operation' => 'rebind', 'task_id' => 0, 'target_guid' => $target['guid']],
        'host_inserted' => true, 'directory_inserted' => true
    ];
    $fixtures[$fixtureIndex] = $fixture;
    return $fixture;
};
$intentPayload = static function (array $fixture) use ($pdo, $guid) {
    $method = new \ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
    $method->setAccessible(true);
    $snapshot = $method->invoke(null, $pdo, $fixture['host_id']);
    return [
        'version' => 1,
        'host_id' => $fixture['host_id'],
        'snapshot' => $snapshot,
        'target' => $fixture['target'],
        'destination' => ['guid' => $guid(), 'dn' => 'OU=Managed,DC=u2p2,DC=invalid'],
        'dc' => 'dc1.u2p2.invalid', 'nc' => 'DC=u2p2,DC=invalid',
        'rdn' => 'CN=' . rtrim($fixture['target']['account'], '$')
    ];
};
$readIntent = static function ($targetGuid) use ($pdo, $guidBytes) {
    $q = $pdo->prepare('SELECT dpiGeneration,dpiStatus,dpiHostID,dpiHash FROM directoryPlacementIntent WHERE dpiGUID=?');
    $q->bindValue(1, $guidBytes($targetGuid), \PDO::PARAM_LOB);
    $q->execute();
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};
$taskCount = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE taskHostID=?');
    $q->execute([(int)$hostID]);
    return (int)$q->fetchColumn();
};
$claim = static function (array $fixture) {
    $saved = FOG\Agent\DirectoryRejoin::settings($fixture['host']);
    return FOG\Agent\DirectoryOperation::claim($fixture['host'], $saved, $fixture['body']);
};
$barrierDir = null;
$workerProcess = null;
$workerPipes = null;
try {
    // B39-03: a real claim cancels a known-unsent PREPARED target before it
    // records started. The old ticket can no longer issue a dispatch permit.
    $preparedForClaim = $createFixture();
    $preparedPayload = $intentPayload($preparedForClaim);
    $preparedTicket = FOG\Agent\DirectoryPlacementIntent::prepare($preparedPayload);
    $claim($preparedForClaim);
    $preparedRowAfterClaim = $readIntent($preparedForClaim['target']['guid']);
    $preparedStateAfterClaim = FOG\Agent\DirectoryRejoin::settings($preparedForClaim['host'])['state'];
    $staleDispatchReason = '';
    try {
        FOG\Agent\DirectoryPlacementIntent::dispatch($preparedTicket, new \stdClass());
    } catch (\Throwable $e) {
        $staleDispatchReason = $e->getMessage();
    }
    $t->check('B39-03 real claim cancels PREPARED before dispatch and starts the exact operation',
        is_array($preparedRowAfterClaim) && 'CANCELLED' === $preparedRowAfterClaim['dpiStatus']
        && 'started' === ($preparedStateAfterClaim['phase'] ?? null)
        && 'directory_placement_dispatch_changed' === $staleDispatchReason
        && 'CANCELLED' === ($readIntent($preparedForClaim['target']['guid'])['dpiStatus'] ?? null)
        && 0 === $taskCount($preparedForClaim['host_id']));

    // B39-04: once dispatch has durably crossed MAY_HAVE_RUN, a same-target
    // claim is refused and the operation snapshot remains unchanged.
    $mayForClaim = $createFixture();
    $mayPayload = $intentPayload($mayForClaim);
    $mayTicket = FOG\Agent\DirectoryPlacementIntent::prepare($mayPayload);
    FOG\Agent\DirectoryPlacementIntent::dispatch($mayTicket, new \stdClass());
    $mayBeforeState = FOG\Agent\DirectoryRejoin::settings($mayForClaim['host']);
    $mayBeforeRow = $readIntent($mayForClaim['target']['guid']);
    $mayClaimReason = '';
    try {
        $claim($mayForClaim);
    } catch (\Throwable $e) {
        $mayClaimReason = $e->getMessage();
    }
    $t->check('B39-04 real claim refuses MAY_HAVE_RUN placement and preserves both durable rows',
        'directory_placement_target_protected' === $mayClaimReason
        && $mayBeforeState === FOG\Agent\DirectoryRejoin::settings($mayForClaim['host'])
        && $mayBeforeRow === $readIntent($mayForClaim['target']['guid'])
        && 'MAY_HAVE_RUN' === ($mayBeforeRow['dpiStatus'] ?? null)
        && 0 === $taskCount($mayForClaim['host_id']));

    // B39-05: active work for Target A does not serialize an unrelated Target B.
    $targetA = $createFixture();
    $ticketA = FOG\Agent\DirectoryPlacementIntent::prepare($intentPayload($targetA));
    FOG\Agent\DirectoryPlacementIntent::dispatch($ticketA, new \stdClass());
    $targetBClaim = $createFixture();
    $claim($targetBClaim);
    $targetCDispatch = $createFixture();
    $ticketC = FOG\Agent\DirectoryPlacementIntent::prepare($intentPayload($targetCDispatch));
    FOG\Agent\DirectoryPlacementIntent::dispatch($ticketC, new \stdClass());
    $t->check('B39-05 different Targets permit independent claim and dispatch while A remains MAY_HAVE_RUN',
        'MAY_HAVE_RUN' === ($readIntent($targetA['target']['guid'])['dpiStatus'] ?? null)
        && 'started' === (FOG\Agent\DirectoryRejoin::settings($targetBClaim['host'])['state']['phase'] ?? null)
        && 'MAY_HAVE_RUN' === ($readIntent($targetCDispatch['target']['guid'])['dpiStatus'] ?? null)
        && $targetA['target']['guid'] !== $targetBClaim['target']['guid']
        && $targetA['target']['sid'] !== $targetBClaim['target']['sid']
        && $targetA['target']['guid'] !== $targetCDispatch['target']['guid']
        && $targetA['target']['sid'] !== $targetCDispatch['target']['sid']
        && 0 === $taskCount($targetA['host_id'])
        && 0 === $taskCount($targetBClaim['host_id'])
        && 0 === $taskCount($targetCDispatch['host_id']));

    // B39-07/B39-21: the worker carries a payload built from its earlier
    // lookup, pauses before real prepare, and loses to a committed common
    // name-writer update that reassigns the target account.
    $staleLookup = $createFixture('LOOK' . strtoupper(bin2hex(random_bytes(3))));
    $targetName = rtrim($staleLookup['target']['account'], '$');
    do {
        $competitorID = $newHostID();
        $competitorName = 'CMP' . strtoupper(bin2hex(random_bytes(4)));
        $nameCheck = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE LOWER(hostName)=LOWER(?)');
        $nameCheck->execute([$targetName]);
        $targetNameAvailable = (int)$nameCheck->fetchColumn() === 0;
    } while (!$targetNameAvailable);
    $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)'
    )->execute([$competitorID, $competitorName, $staleLookup['domain'],
        'OU=Managed,DC=u2p2,DC=invalid', hash('sha256', random_bytes(32))]);
    $fixtures[] = ['host_id' => $competitorID, 'host_inserted' => true, 'directory_inserted' => false];
    $payload = $intentPayload($staleLookup);
    $barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'u2-p2-placement-claim-' . bin2hex(random_bytes(10));
    if (!mkdir($barrierDir, 0700)) {
        throw new \RuntimeException('cannot create placement claim barrier directory');
    }
    $barrierDir = realpath($barrierDir);
    $tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (false === $barrierDir || 0 !== strpos($barrierDir, $tempRoot)
        || 0 !== strpos(basename($barrierDir), 'u2-p2-placement-claim-')) {
        throw new \RuntimeException('placement claim barrier escaped private temp root');
    }
    $worker = __DIR__ . '/fixtures/u2-p2-placement-claim-worker.php';
    $pipes = [];
    $workerProcess = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', $worker, $barrierDir,
        json_encode(['database' => $database, 'payload' => $payload], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($workerProcess)) {
        throw new \RuntimeException('cannot start stale placement lookup worker');
    }
    fclose($pipes[0]);
    $workerPipes = $pipes;
    $readyPath = $barrierDir . DIRECTORY_SEPARATOR . 'lookup-ready.json';
    $ready = null;
    $deadline = microtime(true) + 15.0;
    do {
        if (is_file($readyPath)) {
            $candidate = json_decode((string)file_get_contents($readyPath), true);
            if (is_array($candidate) && isset($candidate['pid'], $candidate['connection_id'],
                $candidate['host_id'], $candidate['target_guid'])
                && true === ($candidate['old_lookup_payload'] ?? false)
                && true === ($candidate['paused_before_prepare'] ?? false)
                && (int)$candidate['pid'] > 0 && (int)$candidate['connection_id'] > 0) {
                $ready = $candidate;
                break;
            }
        }
        if (!proc_get_status($workerProcess)['running']) { break; }
        usleep(10000);
    } while (microtime(true) < $deadline);
    $saveResult = $db->query('UPDATE `hosts` SET `hostName`=:new_name WHERE `hostID`=:host_id', [],
        ['new_name' => $targetName, 'host_id' => $competitorID]);
    $competitorAfter = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $competitorAfter->execute([$competitorID]);
    $savedTargetName = $competitorAfter->fetchColumn();
    $nameSaveWon = false === $saveResult->error && 1 === (int)$saveResult->affectedRows()
        && $targetName === $savedTargetName;
    file_put_contents($barrierDir . DIRECTORY_SEPARATOR . 'go-prepare', 'go', LOCK_EX);
    $workerStatus = null;
    $workerDeadline = microtime(true) + 20.0;
    do {
        $workerStatus = proc_get_status($workerProcess);
        if (!$workerStatus['running']) { break; }
        usleep(10000);
    } while (microtime(true) < $workerDeadline);
    if (isset($workerStatus['running']) && $workerStatus['running']) {
        proc_terminate($workerProcess, 9);
        foreach ([1, 2] as $i) { fclose($workerPipes[$i]); }
        proc_close($workerProcess);
        $workerProcess = null;
        $workerOut = '';
        $workerErr = 'placement worker timed out';
        $workerExit = -1;
    } else {
        $workerOut = stream_get_contents($workerPipes[1]);
        $workerErr = stream_get_contents($workerPipes[2]);
        fclose($workerPipes[1]); fclose($workerPipes[2]);
        $closed = proc_close($workerProcess);
        $workerExit = isset($workerStatus['exitcode']) && $workerStatus['exitcode'] >= 0
            ? $workerStatus['exitcode'] : $closed;
        $workerProcess = null;
    }
    if ('' !== trim((string)$workerErr)) {
        fwrite(STDERR, "placement prepare worker stderr:\n" . trim((string)$workerErr) . "\n");
    }
    $workerResult = json_decode((string)$workerOut, true);
    $intentAfterStale = $readIntent($staleLookup['target']['guid']);
    $stalePreparePassed = is_array($ready) && $nameSaveWon
        && 0 === $workerExit && is_array($workerResult)
        && false === ($workerResult['prepared'] ?? true)
        && 'directory_placement_account_reassigned' === ($workerResult['reason'] ?? null)
        && null === $intentAfterStale
        && $targetName === $savedTargetName;
    if (!$stalePreparePassed) {
        fwrite(STDERR, 'B39 stale-lookup diagnostic: ' . json_encode([
            'ready' => $ready, 'name_save_error' => $saveResult->error,
            'name_save_affected' => $saveResult->affectedRows(), 'name_save_won' => $nameSaveWon,
            'worker_exit' => $workerExit, 'worker_out' => $workerOut, 'worker_err' => $workerErr,
            'target_name' => $targetName, 'intent_after' => $intentAfterStale
        ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
    $t->check('B39-07/B39-21 committed name save beats stale pre-prepare lookup; no intent is created',
        $stalePreparePassed);
} finally {
    if (is_resource($workerProcess)) {
        @proc_terminate($workerProcess, 9);
        if (is_array($workerPipes)) {
            foreach ([1, 2] as $i) {
                if (isset($workerPipes[$i]) && is_resource($workerPipes[$i])) { @fclose($workerPipes[$i]); }
            }
        }
        @proc_close($workerProcess);
    }
    foreach ($fixtures as $fixture) {
        if (isset($fixture['target']['guid'], $fixture['target']['sid'])) {
            $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=?');
            $q->execute([$guidBytes($fixture['target']['guid']), $fixture['target']['sid']]);
        }
    }
    foreach ($fixtures as $fixture) {
        if (!empty($fixture['directory_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([(int)$fixture['host_id']]);
        }
        if (!empty($fixture['host_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([(int)$fixture['host_id']]);
        }
    }
    if (is_string($barrierDir) && is_dir($barrierDir)
        && 0 === strpos(realpath($barrierDir), rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        && 0 === strpos(basename($barrierDir), 'u2-p2-placement-claim-')) {
        foreach (glob($barrierDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) { @unlink($file); }
        @rmdir($barrierDir);
    }
}
$t->finish();
