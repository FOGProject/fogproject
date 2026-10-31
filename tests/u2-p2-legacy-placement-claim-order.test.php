<?php
/** B39-21 legacy placement intent versus Existing-Target claim orderings. */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 legacy placement/claim ordering: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('legacy placement/claim test requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-legacy-placement-claim-order');
$pdo = new PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('legacy placement/claim test requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME differs from validated scratch DB');
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
    return hex2bin(str_replace('-', '', strtolower($value)));
};
$fixtures = [];
$newHostID = static function () use ($pdo) {
    do {
        $id = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$id]);
        $exists = (int)$q->fetchColumn();
        $q->closeCursor();
    } while (0 !== $exists);
    return $id;
};
$createHost = static function ($name, $machineAccount, $computerDN, $state) use ($pdo, $newHostID, &$fixtures) {
    $id = $newHostID();
    $fingerprint = hash('sha256', random_bytes(32));
    $fixtureIndex = count($fixtures);
    $fixtures[] = ['id' => $id, 'inserted_host' => false, 'inserted_directory' => false];
    $insert = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,1,?,?,?)'
    );
    $insert->execute([$id, $name, 'u2p2.invalid', 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[$fixtureIndex]['inserted_host'] = true;
    $insert = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,?)'
    );
    $insert->execute([$id, 'u2p2.invalid', $computerDN, $machineAccount,
        null === $state ? null : json_encode($state, JSON_THROW_ON_ERROR)]);
    $fixtures[$fixtureIndex]['inserted_directory'] = true;
    $host = (new FOG\Items\Host($id));
    return ['id' => $id, 'name' => $name, 'fingerprint' => $fingerprint, 'host' => $host];
};
$targetFor = static function ($name) use ($guid) {
    return [
        'guid' => $guid(),
        'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
        'account' => $name . '$',
        'dn' => 'CN=' . $name . ',OU=Legacy,DC=u2p2,DC=invalid'
    ];
};
$placementPayload = static function (array $legacy, array $target) use ($pdo, $guid) {
    $snapshotMethod = new ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
    $snapshotMethod->setAccessible(true);
    return [
        'version' => 1,
        'host_id' => $legacy['id'],
        'snapshot' => $snapshotMethod->invoke(null, $pdo, $legacy['id']),
        'target' => $target,
        'destination' => ['guid' => $guid(), 'dn' => 'OU=Managed,DC=u2p2,DC=invalid'],
        'dc' => 'dc1.u2p2.invalid',
        'nc' => 'DC=u2p2,DC=invalid',
        'rdn' => 'CN=' . rtrim($target['account'], '$')
    ];
};
$readHostName = static function ($id) use ($pdo) {
    $q = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
    $q->execute([$id]);
    $name = $q->fetchColumn();
    $q->closeCursor();
    return false === $name ? null : $name;
};
$readLegacyIdentity = static function ($id) use ($pdo) {
    $q = $pdo->prepare(
        'SELECT h.hostName,d.hdMachineAccount,d.hdRejoinState '
        . 'FROM hosts h JOIN hostDirectory d ON d.hdHostID=h.hostID WHERE h.hostID=?'
    );
    $q->execute([$id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    $q->closeCursor();
    if (!$row) {
        return null;
    }
    $row['decoded_state'] = FOG\Agent\DirectoryRejoin::decode($row['hdRejoinState']);
    return $row;
};
$readIntent = static function ($target) use ($pdo, $guidBytes) {
    $q = $pdo->prepare(
        'SELECT dpiGeneration,dpiStatus,dpiHostID,dpiSID,dpiPayload,dpiHash '
        . 'FROM directoryPlacementIntent WHERE dpiGUID=?'
    );
    $q->bindValue(1, $guidBytes($target['guid']), PDO::PARAM_LOB);
    $q->execute();
    $row = $q->fetch(PDO::FETCH_ASSOC);
    $q->closeCursor();
    return $row ?: null;
};
$countName = static function ($name) use ($pdo) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE LOWER(hostName)=LOWER(?)');
    $q->execute([$name]);
    $count = (int)$q->fetchColumn();
    $q->closeCursor();
    return $count;
};
$saveHostName = static function (array $fixture, $name) {
    $host = new FOG\Items\Host((int)$fixture['id']);
    $host->set('name', $name);
    try {
        return ['result' => $host->save(), 'error' => null];
    } catch (Throwable $e) {
        return ['result' => false, 'error' => $e];
    }
};
$attemptNameGuard = static function ($name, $ownID) {
    try {
        FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use ($name, $ownID) {
            FOG\Agent\DirectoryPlacementIntent::assertNameIdle($db, $name, $ownID);
        });
        return '';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
};
$startPausedPrepare = static function (array $payload) use ($database) {
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'u2-p2-placement-claim-' . bin2hex(random_bytes(10));
    if (!mkdir($dir, 0700)) {
        throw new RuntimeException('cannot create private stale-placement barrier directory');
    }
    $resolved = realpath($dir);
    $tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (false === $resolved || 0 !== strpos($resolved, $tempRoot)
        || 0 !== strpos(basename($resolved), 'u2-p2-placement-claim-')) {
        throw new RuntimeException('stale-placement barrier escaped private temp root');
    }
    $worker = __DIR__ . '/fixtures/u2-p2-placement-claim-worker.php';
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', $worker, $resolved,
        json_encode(['database' => $database, 'payload' => $payload], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('cannot start stale-placement worker');
    }
    fclose($pipes[0]);
    return [$resolved, $process, $pipes];
};
$awaitReady = static function ($dir, $process) {
    $readyPath = $dir . DIRECTORY_SEPARATOR . 'lookup-ready.json';
    $deadline = microtime(true) + 15.0;
    do {
        if (is_file($readyPath)) {
            $ready = json_decode((string)file_get_contents($readyPath), true);
            if (is_array($ready) && isset($ready['pid'], $ready['connection_id'], $ready['host_id'], $ready['target_guid'])
                && true === ($ready['old_lookup_payload'] ?? false)
                && true === ($ready['paused_before_prepare'] ?? false)) {
                return $ready;
            }
        }
        if (!proc_get_status($process)['running']) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return null;
};
$resumePrepare = static function ($dir, $process, array $pipes) {
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'go-prepare', 'go', LOCK_EX);
    $deadline = microtime(true) + 20.0;
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
    $exit = isset($status['exitcode']) && $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
    return [true, trim((string)$stdout), trim((string)$stderr), $exit];
};
$freshExistingPlan = static function (array $fixture, array $target) {
    $host = new FOG\Items\Host((int)$fixture['id']);
    $name = (string)$host->get('name');
    $observedName = (string)$fixture['name'];
    $fingerprint = (string)$host->get('agentFingerprint');
    $domain = trim((string)$host->get('ADDomain'));
    $dc = 'dc1.u2p2.invalid';
    $config = [
        'version' => 1,
        'protocol' => 2,
        'allow_create' => false,
        'name' => $name,
        'domain' => $domain,
        'target' => $target,
        'config_revision' => bin2hex(random_bytes(16)),
        'confirmed_by' => 'u2-p2-b39-21-fixture',
        'confirmed_at' => gmdate('c')
    ];
    $revision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
    $observation = [
        'revision' => $revision,
        'current_name' => $observedName,
        'pending_name' => $observedName,
        'domain' => $domain,
        'netbios' => 'U2P2',
        'role' => 'workstation',
        'secure_channel' => 'true',
        'dc' => $dc,
        'dc_reachable' => true,
        'boot' => ['marker' => 'b39-21', 'started' => gmdate(DATE_ATOM)]
    ];
    $state = [
        'version' => 1,
        'protocol' => 2,
        'operation' => 'rebind',
        'mode' => 'existing',
        'target' => $target,
        'host_id' => (int)$fixture['id'],
        'name' => $name,
        'domain' => $domain,
        'dc' => $dc,
        'check_dc' => $dc,
        'phase' => 'ready',
        'attempt_id' => bin2hex(random_bytes(16)),
        'config_revision' => $revision,
        'fingerprint' => $fingerprint,
        'expected_current' => $observedName,
        'expected_pending' => $observedName,
        'observed_at' => time(),
        'observation' => $observation,
        'last_deploy_task_id' => 0,
        'trigger_task_id' => 0,
        'placement_hold' => false
    ];
    FOG\Agent\DirectoryRejoin::change($host, static function (&$savedConfig, &$savedState) use ($config, $state) {
        $savedConfig = $config;
        $savedState = $state;
    });
    $saved = FOG\Agent\DirectoryRejoin::settings($host);
    return [
        'host' => $host,
        'saved' => $saved,
        'body' => [
            'attempt_id' => $state['attempt_id'],
            'config_revision' => $revision,
            'operation' => 'rebind',
            'task_id' => 0,
            'target_guid' => $target['guid']
        ]
    ];
};

$barrierDir = null;
$workerProcess = null;
$workerPipes = null;
try {
    // Ordering 1: A's Host name is Y but its legacy computer account is X.
    // Its legacy state has version 1 and deliberately omits protocol.
    $legacyA = $createHost('AY' . strtoupper(bin2hex(random_bytes(4))), '', '',
        ['version' => 1]);
    $targetA = $targetFor('AX' . strtoupper(bin2hex(random_bytes(4))));
    // The machine-account facts identify Target X while the FOG Host stays Y.
    $pdo->prepare('UPDATE hostDirectory SET hdMachineAccount=?,hdComputerDN=? WHERE hdHostID=?')
        ->execute([$targetA['account'], $targetA['dn'], $legacyA['id']]);
    $initialBName = 'BZ' . strtoupper(bin2hex(random_bytes(4)));
    $targetName = rtrim($targetA['account'], '$');
    $legacyB = $createHost($initialBName, $targetA['account'], $targetA['dn'], null);
    $payloadA = $placementPayload($legacyA, $targetA);
    $uniqueBefore = $countName($targetName);
    $ticketA = FOG\Agent\DirectoryPlacementIntent::prepare($payloadA);
    $prepared = $readIntent($targetA);
    $permit = FOG\Agent\DirectoryPlacementIntent::dispatch($ticketA, new stdClass());
    $may = $readIntent($targetA);
    $nameGuardReason = $attemptNameGuard($targetName, $legacyB['id']);
    $saveB = $saveHostName($legacyB, $targetName);
    $aName = $readHostName($legacyA['id']);
    $bName = $readHostName($legacyB['id']);
    $legacyAIdentity = $readLegacyIdentity($legacyA['id']);
    $t->check(
        'A legacy Target X reaches MAY first; B save to X is refused by the intent footprint, not a FOG-name collision',
        0 === $uniqueBefore && is_object($permit)
            && is_array($prepared) && 'PREPARED' === $prepared['dpiStatus']
            && is_array($may) && 'MAY_HAVE_RUN' === $may['dpiStatus']
            && 'directory_placement_name_protected' === $nameGuardReason
            && false === $saveB['result']
            && $aName === $legacyA['name'] && 0 !== strcasecmp($aName, $targetName)
            && is_array($legacyAIdentity) && 1 === ($legacyAIdentity['decoded_state']['version'] ?? null)
            && !array_key_exists('protocol', $legacyAIdentity['decoded_state'])
            && $targetA['account'] === $legacyAIdentity['hdMachineAccount']
            && $initialBName === $bName
            && $targetA['guid'] === json_decode($may['dpiPayload'], true)['target']['guid']
    );

    // Ordering 2: B commits the FOG name first. A's pre-save lookup payload is
    // paused in a separate PDO worker and must lose at real intent preparation.
    $legacyA2 = $createHost('AY' . strtoupper(bin2hex(random_bytes(4))), '', '',
        ['version' => 1]);
    $targetA2 = $targetFor('AX' . strtoupper(bin2hex(random_bytes(4))));
    $pdo->prepare('UPDATE hostDirectory SET hdMachineAccount=?,hdComputerDN=? WHERE hdHostID=?')
        ->execute([$targetA2['account'], $targetA2['dn'], $legacyA2['id']]);
    $legacyB2 = $createHost('BZ' . strtoupper(bin2hex(random_bytes(4))), $targetA2['account'], $targetA2['dn'], null);
    $stalePayload = $placementPayload($legacyA2, $targetA2);
    $targetName2 = rtrim($targetA2['account'], '$');
    if (0 !== $countName($targetName2)) {
        throw new RuntimeException('Target X unexpectedly already has a FOG hostname before the save-first case');
    }
    list($barrierDir, $workerProcess, $workerPipes) = $startPausedPrepare($stalePayload);
    $ready = $awaitReady($barrierDir, $workerProcess);
    $saveB2 = $saveHostName($legacyB2, $targetName2);
    $savedBName = $readHostName($legacyB2['id']);
    list($workerDone, $workerOut, $workerErr, $workerExit) = $resumePrepare($barrierDir, $workerProcess, $workerPipes);
    $workerProcess = null;
    $workerPipes = null;
    if ('' !== $workerErr) {
        fwrite(STDERR, "legacy placement worker stderr:\n$workerErr\n");
    }
    $workerResult = json_decode($workerOut, true);
    $intentAfterStale = $readIntent($targetA2);
    $legacyA2Identity = $readLegacyIdentity($legacyA2['id']);
    $staleRefused = is_array($ready) && is_array($workerResult) && $workerDone && 0 === $workerExit
        && false === ($workerResult['prepared'] ?? true)
        && 'directory_placement_account_reassigned' === ($workerResult['reason'] ?? null)
        && null === $intentAfterStale && $targetName2 === $savedBName
        && is_array($legacyA2Identity) && 1 === ($legacyA2Identity['decoded_state']['version'] ?? null)
        && !array_key_exists('protocol', $legacyA2Identity['decoded_state'])
        && $targetA2['account'] === $legacyA2Identity['hdMachineAccount']
        && $saveB2['result'] instanceof FOG\Items\Host && 1 === $countName($targetName2);

    // Build a complete current v2 Existing-X plan only after B's save, then
    // prove the actual claim succeeds while stale A has no prepared intent.
    $bPlan = $freshExistingPlan($legacyB2, $targetA2);
    $claimReason = '';
    try {
        FOG\Agent\DirectoryOperation::claim($bPlan['host'], $bPlan['saved'], $bPlan['body']);
    } catch (Throwable $e) {
        $claimReason = $e->getMessage();
    }
    $bAfter = FOG\Agent\DirectoryRejoin::settings($bPlan['host']);
    $t->check(
        'B save wins first; stale A prepare is account_reassigned and B fresh Existing-X claim starts',
        $staleRefused && '' === $claimReason
            && 'started' === ($bAfter['state']['phase'] ?? null)
            && 'rebind' === ($bAfter['state']['operation'] ?? null)
            && 'existing' === ($bAfter['state']['mode'] ?? null)
            && $targetA2['guid'] === ($bAfter['state']['target']['guid'] ?? null)
            && $targetA2['sid'] === ($bAfter['state']['target']['sid'] ?? null)
            && $targetName2 === $readHostName($legacyB2['id'])
            && null === $readIntent($targetA2)
    );
} finally {
    if (is_resource($workerProcess)) {
        @proc_terminate($workerProcess, 9);
        if (is_array($workerPipes)) {
            foreach ([1, 2] as $index) {
                if (isset($workerPipes[$index]) && is_resource($workerPipes[$index])) {
                    @fclose($workerPipes[$index]);
                }
            }
        }
        @proc_close($workerProcess);
    }
    foreach ([$targetA ?? null, $targetA2 ?? null] as $target) {
        if (is_array($target)) {
            $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=?');
            $q->bindValue(1, $guidBytes($target['guid']), PDO::PARAM_LOB);
            $q->bindValue(2, $target['sid'], PDO::PARAM_STR);
            $q->execute();
        }
    }
    $hostIDs = array_column($fixtures, 'id');
    if ($hostIDs) {
        $placeholders = implode(',', array_fill(0, count($hostIDs), '?'));
        $pdo->prepare("DELETE FROM auditLog WHERE alSubjectType='host' AND alSubjectID IN ($placeholders)")
            ->execute($hostIDs);
        $pdo->prepare("DELETE FROM history WHERE hSubjectType='host' AND hSubjectID IN ($placeholders)")
            ->execute($hostIDs);
    }
    foreach ($fixtures as $fixture) {
        if (!empty($fixture['inserted_directory'])) {
            $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?')->execute([(int)$fixture['id']]);
        }
        if (!empty($fixture['inserted_host'])) {
            $pdo->prepare('DELETE FROM hosts WHERE hostID=?')->execute([(int)$fixture['id']]);
        }
    }
    if (is_string($barrierDir) && is_dir($barrierDir)
        && 0 === strpos(realpath($barrierDir), rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        && 0 === strpos(basename($barrierDir), 'u2-p2-placement-claim-')) {
        foreach (glob($barrierDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($barrierDir);
    }
}

echo "NOTE: scratch MariaDB/PDO only; real placement prepare/dispatch, Host::save and DirectoryOperation::claim; no LDAP/native request.\n";
$t->finish();
