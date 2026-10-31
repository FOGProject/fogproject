<?php
/** B39 same-ticket dispatch race and claim-cancelled PREPARED ticket barrier. */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 placement dispatch race: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('placement dispatch race requires explicit scratch opt-in and mysql DSN');
}
require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-placement-dispatch-race');
$pdo = new PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('placement dispatch race requires a fog_mj1-prefixed scratch DB');
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
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$newHostID = static function () use ($pdo) {
    do {
        $id = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$id]);
    } while ((int)$q->fetchColumn() !== 0);
    return $id;
};
$fixtures = [];
$createFixture = static function () use ($pdo, $guid, $newHostID, &$fixtures) {
    $hostID = $newHostID();
    $name = 'P2D' . strtoupper(bin2hex(random_bytes(4)));
    $domain = 'u2p2.invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $target = [
        'guid' => $guid(),
        'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
        'account' => $name . '$',
        'dn' => 'CN=' . $name . ',OU=Computers,DC=u2p2,DC=invalid'
    ];
    $host = (new FOG\Items\Host())->set('id', $hostID)->set('name', $name)
        ->set('ADDomain', $domain)->set('ADOU', 'OU=Managed,DC=u2p2,DC=invalid')
        ->set('useAD', 1)->set('agentFingerprint', $fingerprint);
    $config = [
        'version' => 1, 'protocol' => 2, 'allow_create' => false,
        'name' => $name, 'domain' => $domain, 'target' => $target,
        'config_revision' => bin2hex(random_bytes(16)),
        'confirmed_by' => 'u2-p2-placement-dispatch-fixture', 'confirmed_at' => gmdate('c')
    ];
    $revision = FOG\Agent\DirectoryOperation::revision($host, $config, 'dc1.u2p2.invalid');
    $observation = [
        'revision' => $revision, 'current_name' => $name, 'pending_name' => $name,
        'domain' => $domain, 'netbios' => 'U2P2', 'role' => 'workstation',
        'secure_channel' => 'true', 'dc' => 'dc1.u2p2.invalid', 'dc_reachable' => true,
        'boot' => ['marker' => 'placement-dispatch-race', 'started' => gmdate(DATE_ATOM)]
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
    $fixtures[] = ['host_id' => $hostID, 'target' => $target,
        'host_inserted' => false, 'directory_inserted' => false];
    $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)'
    )->execute([$hostID, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[$fixtureIndex]['host_inserted'] = true;
    $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)'
    )->execute([$hostID, $domain, $target['dn'], $target['account'],
        json_encode($config, JSON_THROW_ON_ERROR), json_encode($state, JSON_THROW_ON_ERROR)]);
    $fixtures[$fixtureIndex]['directory_inserted'] = true;
    $fixture = [
        'host_id' => $hostID, 'host' => $host, 'target' => $target,
        'config' => $config, 'state' => $state,
        'body' => ['attempt_id' => $state['attempt_id'], 'config_revision' => $revision,
            'operation' => 'rebind', 'task_id' => 0, 'target_guid' => $target['guid']]
    ];
    $fixtures[$fixtureIndex] = array_merge($fixtures[$fixtureIndex], $fixture);
    return $fixture;
};
$intentPayload = static function (array $fixture) use ($pdo, $guid) {
    $method = new ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
    $method->setAccessible(true);
    return [
        'version' => 1,
        'host_id' => $fixture['host_id'],
        'snapshot' => $method->invoke(null, $pdo, $fixture['host_id']),
        'target' => $fixture['target'],
        'destination' => ['guid' => $guid(), 'dn' => 'OU=Managed,DC=u2p2,DC=invalid'],
        'dc' => 'dc1.u2p2.invalid', 'nc' => 'DC=u2p2,DC=invalid',
        'rdn' => 'CN=' . rtrim($fixture['target']['account'], '$')
    ];
};
$ticketFor = static function ($ticket) {
    return [
        // Tickets carry the target GUID in binary DB form. JSON IPC receives
        // only its reversible hex encoding; nested payload GUIDs are text.
        'guid' => bin2hex($ticket['guid']), 'sid' => $ticket['sid'],
        'generation' => $ticket['generation'], 'hash' => $ticket['hash'],
        'payload' => $ticket['payload']
    ];
};
$readIntent = static function ($target) use ($pdo) {
    $q = $pdo->prepare('SELECT dpiGeneration,dpiStatus,dpiHostID,dpiHash FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=?');
    $q->execute([hex2bin(str_replace('-', '', strtolower($target['guid']))), $target['sid']]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
};
$taskCount = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE taskHostID=?');
    $q->execute([(int)$hostID]);
    return (int)$q->fetchColumn();
};
$workerPath = __DIR__ . '/fixtures/u2-p2-placement-dispatch-worker.php';
$barrierDir = null;
$activeWorkers = [];
$startWorker = static function ($mode, $key, array $ticket) use ($workerPath, &$barrierDir, &$activeWorkers, $database) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', $workerPath, $mode, $barrierDir,
        $key, json_encode(['database' => $database, 'ticket' => $ticket], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('cannot start placement dispatch worker');
    }
    fclose($pipes[0]);
    $activeWorkers[$key] = [$process, $pipes];
    return [$process, $pipes];
};
$waitReady = static function ($path, $process, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            $row = json_decode((string)file_get_contents($path), true);
            if (is_array($row) && isset($row['pid'], $row['connection_id'], $row['target_guid'],
                $row['target_sid'], $row['ticket_generation'], $row['ticket_hash'])
                && true === ($row['ready_before_dispatch'] ?? false)
                && (int)$row['pid'] > 0 && (int)$row['connection_id'] > 0) {
                return $row;
            }
        }
        if (!proc_get_status($process)['running']) { return null; }
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
    $exit = isset($status['exitcode']) && $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
    return [true, trim((string)$stdout), trim((string)$stderr), $exit];
};
$readOutput = static function ($stdout) {
    $lines = preg_split('/\r?\n/', trim((string)$stdout));
    $row = json_decode((string)end($lines), true);
    return is_array($row) ? $row : null;
};

try {
    $barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'u2-p2-placement-dispatch-' . bin2hex(random_bytes(10));
    if (!mkdir($barrierDir, 0700)) {
        throw new RuntimeException('cannot create private placement dispatch barrier directory');
    }
    $barrierDir = realpath($barrierDir);
    $tempRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (false === $barrierDir || 0 !== strpos($barrierDir, $tempRoot)
        || 0 !== strpos(basename($barrierDir), 'u2-p2-placement-dispatch-')) {
        throw new RuntimeException('placement dispatch barrier escaped private temp root');
    }

    // A single PREPARED ticket is loaded by two independent PHP/PDO processes.
    // Both reach a process barrier before attempting the real dispatch CAS.
    $raceFixture = $createFixture();
    $raceTicket = FOG\Agent\DirectoryPlacementIntent::prepare($intentPayload($raceFixture));
    $ticketData = $ticketFor($raceTicket);
    $raceWorkers = [];
    $raceReady = [];
    foreach (['dispatch-a', 'dispatch-b'] as $key) {
        list($process, $pipes) = $startWorker('race', $key, $ticketData);
        $raceWorkers[$key] = [$process, $pipes];
    }
    foreach ($raceWorkers as $key => $worker) {
        $raceReady[$key] = $waitReady($barrierDir . DIRECTORY_SEPARATOR . 'ready-' . $key . '.json', $worker[0], 15.0);
    }
    file_put_contents($barrierDir . DIRECTORY_SEPARATOR . 'go-race', 'go', LOCK_EX);
    $raceResults = [];
    foreach ($raceWorkers as $key => $worker) {
        list($done, $stdout, $stderr, $exit) = $waitWorker($worker[0], $worker[1], 20.0);
        unset($activeWorkers[$key]);
        if ('' !== $stderr) { fwrite(STDERR, "dispatch worker $key stderr:\n$stderr\n"); }
        $raceResults[$key] = ['done' => $done, 'exit' => $exit, 'result' => $readOutput($stdout), 'stderr' => $stderr];
    }
    $raceReadyRows = array_values($raceReady);
    $raceOutputs = array_values(array_map(static function ($row) { return $row['result']; }, $raceResults));
    $raceDurable = $readIntent($raceFixture['target']);
    $racePass = count($raceReadyRows) === 2 && is_array($raceReadyRows[0]) && is_array($raceReadyRows[1])
        && count(array_unique(array_column($raceReadyRows, 'pid'))) === 2
        && count(array_unique(array_column($raceReadyRows, 'connection_id'))) === 2
        && $raceReadyRows[0]['target_guid'] === $raceReadyRows[1]['target_guid']
        && $raceReadyRows[0]['target_sid'] === $raceReadyRows[1]['target_sid']
        && $raceReadyRows[0]['ticket_generation'] === $raceReadyRows[1]['ticket_generation']
        && $raceReadyRows[0]['ticket_hash'] === $raceReadyRows[1]['ticket_hash']
        && count($raceResults) === 2
        && count(array_filter($raceResults, static function ($row) { return $row['done'] && 0 === $row['exit']; })) === 2
        && count(array_filter($raceOutputs, static function ($row) {
            return is_array($row) && true === ($row['token_issued'] ?? false)
                && true === ($row['ldap_token_object_created'] ?? false);
        })) === 1
        && count(array_filter($raceOutputs, static function ($row) {
            return is_array($row) && false === ($row['token_issued'] ?? true)
                && true === ($row['ldap_token_object_created'] ?? false)
                && 'directory_placement_dispatch_changed' === ($row['dispatch_reason'] ?? null)
                && 'directory_move_dispatch_permit_required' === ($row['move_denied_reason'] ?? null)
                && false === ($row['move_attempted'] ?? true);
        })) === 1
        && count(array_filter($raceOutputs, static function ($row) {
            return is_array($row) && 'MAY_HAVE_RUN' === ($row['durable_status'] ?? null);
        })) === 2
        && is_array($raceDurable) && 'MAY_HAVE_RUN' === $raceDurable['dpiStatus']
        && 0 === $taskCount($raceFixture['host_id']);
    if (!$racePass) {
        fwrite(STDERR, 'dispatch race diagnostic: ' . json_encode([
            'ready' => $raceReady, 'results' => $raceResults, 'durable_row' => $raceDurable
        ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
    $t->check('two-process same-ticket dispatch yields exactly one permit and durable MAY_HAVE_RUN; loser cannot consume',
        $racePass);

    // A worker retains a serialized PREPARED ticket while a real operation
    // claim cancels it. Resuming dispatch must not recreate permission.
    $cancelFixture = $createFixture();
    $cancelTicket = FOG\Agent\DirectoryPlacementIntent::prepare($intentPayload($cancelFixture));
    $cancelTicketData = $ticketFor($cancelTicket);
    list($cancelWorker, $cancelPipes) = $startWorker('stale', 'stale-prepared', $cancelTicketData);
    $staleReady = $waitReady($barrierDir . DIRECTORY_SEPARATOR . 'ready-stale-prepared.json', $cancelWorker, 15.0);
    $saved = FOG\Agent\DirectoryRejoin::settings($cancelFixture['host']);
    FOG\Agent\DirectoryOperation::claim($cancelFixture['host'], $saved, $cancelFixture['body']);
    $cancelledRow = $readIntent($cancelFixture['target']);
    file_put_contents($barrierDir . DIRECTORY_SEPARATOR . 'go-stale', 'go', LOCK_EX);
    list($staleDone, $staleOut, $staleErr, $staleExit) = $waitWorker($cancelWorker, $cancelPipes, 20.0);
    unset($activeWorkers['stale-prepared']);
    if ('' !== $staleErr) { fwrite(STDERR, "stale ticket worker stderr:\n$staleErr\n"); }
    $staleResult = $readOutput($staleOut);
    $claimedState = FOG\Agent\DirectoryRejoin::settings($cancelFixture['host'])['state'];
    $stalePass = is_array($staleReady) && $staleDone && 0 === $staleExit
        && is_array($staleResult) && false === ($staleResult['token_issued'] ?? true)
        && 'directory_placement_dispatch_changed' === ($staleResult['dispatch_reason'] ?? null)
        && 'directory_move_dispatch_permit_required' === ($staleResult['move_denied_reason'] ?? null)
        && false === ($staleResult['move_attempted'] ?? true)
        && is_array($cancelledRow) && 'CANCELLED' === $cancelledRow['dpiStatus']
        && 'started' === ($claimedState['phase'] ?? null)
        && 'CANCELLED' === ($readIntent($cancelFixture['target'])['dpiStatus'] ?? null)
        && 0 === $taskCount($cancelFixture['host_id']);
    if (!$stalePass) {
        fwrite(STDERR, 'cancelled PREPARED ticket diagnostic: ' . json_encode([
            'ready' => $staleReady, 'result' => $staleResult, 'stderr' => $staleErr,
            'cancelled_row' => $cancelledRow, 'claimed_state' => $claimedState
        ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
    $t->check('real claim cancels PREPARED while worker waits; resumed stale ticket has no permit', $stalePass);
} finally {
    foreach ($activeWorkers as $entry) {
        if (is_resource($entry[0])) {
            @proc_terminate($entry[0], 9);
            foreach ([1, 2] as $i) {
                if (isset($entry[1][$i]) && is_resource($entry[1][$i])) { @fclose($entry[1][$i]); }
            }
            @proc_close($entry[0]);
        }
    }
    foreach ($fixtures as $fixture) {
        if (isset($fixture['target']['guid'], $fixture['target']['sid'])) {
            $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=?');
            $q->execute([hex2bin(str_replace('-', '', strtolower($fixture['target']['guid']))), $fixture['target']['sid']]);
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
        && 0 === strpos(basename($barrierDir), 'u2-p2-placement-dispatch-')) {
        foreach (glob($barrierDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) { @unlink($file); }
        @rmdir($barrierDir);
    }
}
$t->finish();
