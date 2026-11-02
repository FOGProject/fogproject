<?php
/**
 * Competing protocol-2 claims on a real server, no LDAP or native I/O.
 *
 * Two processes claim the same host's ready attempt at once: one wins, the
 * loser sees rejoin_plan_changed. Two different hosts whose attempts name the
 * same target GUID or SID claim one after the other: the second is refused.
 * Two different hosts claiming in the same instant can both pass the overlap
 * check (the accepted race); the account-name uniqueness of the directory
 * and the GUID/SID checks at report time send the loser to failed or unknown.
 *
 * Usage: FOG_TEST_DSN=... php tests/agent-directory-claim-race.test.php
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory claim race: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-claim-race';
require __DIR__ . '/lib/directory-db.php';
directoryTestBindPdodb($pdo);

$t = new FogChecks();
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$fixtures = [];
$activeWorkers = [];
$barrierDirs = [];
$createFixture = static function ($count, $identityKind = null) use ($pdo, $guid, &$fixtures) {
    $rows = [];
    $sharedGuid = 'same-guid' === $identityKind ? $guid() : null;
    $sharedSid = 'same-sid' === $identityKind
        ? 'S-1-5-21-1048576-' . random_int(100000, 999999999) : null;
    for ($i = 0; $i < $count; $i++) {
        do {
            $hostID = random_int(1500000000, 1800000000);
            $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
            $q->execute([$hostID]);
            $exists = (int)$q->fetchColumn();
            $q->closeCursor();
        } while (0 !== $exists || isset($fixtures[(string)$hostID]));

        $hostName = 'P2R' . strtoupper(bin2hex(random_bytes(4))) . (string)$i;
        $sourceName = 'SRC' . strtoupper(bin2hex(random_bytes(4))) . (string)$i;
        $domain = 'corp.example';
        $dc = 'dc1.corp.example';
        $fingerprint = hash('sha256', random_bytes(32));
        $targetGuid = $sharedGuid ?: $guid();
        $targetSid = $sharedSid ?: ('S-1-5-21-1048576-' . random_int(100000, 999999999));
        $target = [
            'guid' => $targetGuid,
            'sid' => $targetSid,
            'account' => $sourceName . '$',
            'dn' => 'CN=' . $sourceName . ',OU=Computers,DC=corp,DC=example'
        ];
        $host = (new FOG\Items\Host())->set('id', $hostID)->set('name', $hostName)
            ->set('ADDomain', $domain)->set('useAD', 1)->set('agentFingerprint', $fingerprint);
        $config = [
            'version' => 1,
            'protocol' => 2,
            'allow_create' => false,
            'name' => $hostName,
            'domain' => $domain,
            'config_revision' => bin2hex(random_bytes(16)),
            'confirmed_by' => 'agent-directory-claim-race-fixture',
            'confirmed_at' => gmdate('c')
        ];
        $revision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
        $attempt = bin2hex(random_bytes(16));
        $state = [
            'version' => 1,
            'protocol' => 2,
            'operation' => 'rename',
            'mode' => 'existing',
            'target' => $target,
            'host_id' => $hostID,
            'name' => $hostName,
            'domain' => $domain,
            'dc' => $dc,
            'check_dc' => $dc,
            'phase' => 'ready',
            'attempt_id' => $attempt,
            'config_revision' => $revision,
            'fingerprint' => $fingerprint,
            'expected_current' => $sourceName,
            'expected_pending' => $hostName,
            'observed_at' => time(),
            'last_deploy_task_id' => 0,
            'trigger_task_id' => 0,
            'placement_hold' => true,
            'observation' => [
                'revision' => $revision,
                'current_name' => $sourceName,
                'pending_name' => $hostName,
                'domain' => $domain,
                'netbios' => 'CORP',
                'role' => 'workstation',
                'secure_channel' => 'true',
                'dc' => $dc,
                'dc_reachable' => true,
                'boot' => ['marker' => 'claim-race', 'started' => gmdate(DATE_ATOM)]
            ]
        ];
        $hostInserted = false;
        $directoryInserted = false;
        try {
            $q = $pdo->prepare(
                'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
                . 'VALUES (?,?,1,?,?,?)'
            );
            $q->execute([$hostID, $hostName, $domain, 'OU=Managed,DC=corp,DC=example', $fingerprint]);
            $hostInserted = true;
            $q = $pdo->prepare(
                'INSERT INTO hostDirectory '
                . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
                . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)'
            );
            $q->execute([$hostID, $domain, $target['dn'], $target['account'], json_encode($config), json_encode($state)]);
            $directoryInserted = true;
            $fixture = [
                'host_id' => $hostID,
                'host_name' => $hostName,
                'source_name' => $sourceName,
                'domain' => $domain,
                'fingerprint' => $fingerprint,
                'config' => $config,
                'state' => $state,
                'body' => [
                    'attempt_id' => $attempt,
                    'config_revision' => $revision,
                    'operation' => 'rename',
                    'task_id' => 0,
                    'target_guid' => $targetGuid
                ]
            ];
            $fixtures[(string)$hostID] = [
                'host_id' => $hostID,
                'host_inserted' => $hostInserted,
                'directory_inserted' => $directoryInserted
            ];
            $rows[] = $fixture;
        } catch (\Throwable $e) {
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
    }
    return $rows;
};
$worker = __DIR__ . '/fixtures/agent-directory-claim-race-worker.php';
$startWorker = static function ($barrierDir, $workerKey, array $input) use ($worker, &$activeWorkers) {
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $worker, $barrierDir, $workerKey,
            json_encode($input, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new \RuntimeException('cannot start claim race worker');
    }
    fclose($pipes[0]);
    $activeWorkers[$workerKey] = [$process, $pipes];
    return [$process, $pipes];
};
$waitForReadyJSON = static function ($path, $process, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            $row = json_decode((string)file_get_contents($path), true);
            if (is_array($row)
                && isset($row['pid'], $row['connection_id'], $row['host_id'], $row['phase_before_go'])
                && (int)$row['pid'] > 0 && (int)$row['connection_id'] > 0
                && (int)$row['host_id'] > 0) {
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
$readResult = static function ($stdout) {
    $lines = preg_split('/\r?\n/', trim((string)$stdout));
    $row = json_decode((string)end($lines), true);
    return is_array($row) ? $row : null;
};
$readState = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hdRejoinState FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    $raw = $q->fetchColumn();
    $q->closeCursor();
    return json_decode((string)$raw, true);
};
$runRace = static function ($fixtures, $scenario) use (
    $database, $startWorker, $waitForReadyJSON, $waitWorker, $readResult,
    &$barrierDirs, &$activeWorkers
) {
    $barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'agent-directory-claim-race-' . bin2hex(random_bytes(8)) . '-' . $scenario;
    if (!mkdir($barrierDir, 0700)) {
        throw new \RuntimeException('cannot create claim race barrier directory');
    }
    $barrierDir = realpath($barrierDir);
    $barrierDirs[] = $barrierDir;
    $workers = [];
    foreach (array_values($fixtures) as $i => $fixture) {
        $key = $scenario . '-' . $i;
        list($process, $pipes) = $startWorker($barrierDir, $key, [
            'database' => $database,
            'host_id' => $fixture['host_id'],
            'name' => $fixture['host_name'],
            'domain' => $fixture['domain'],
            'fingerprint' => $fixture['fingerprint'],
            'body' => $fixture['body']
        ]);
        $workers[$key] = ['process' => $process, 'pipes' => $pipes, 'fixture' => $fixture];
    }
    $readyRows = [];
    $allReady = true;
    foreach ($workers as $key => $workerInfo) {
        $path = $barrierDir . DIRECTORY_SEPARATOR . 'ready-' . $key . '.json';
        $ready = $waitForReadyJSON($path, $workerInfo['process'], 15.0);
        $allReady = $allReady && is_array($ready);
        $readyRows[$key] = $ready;
    }
    // Release both clients even on setup failure, so the failure path cannot
    // leave a child parked behind the private barrier.
    file_put_contents($barrierDir . DIRECTORY_SEPARATOR . 'go', 'go', LOCK_EX);
    $results = [];
    foreach ($workers as $key => $workerInfo) {
        list($done, $stdout, $stderr, $exit) = $waitWorker(
            $workerInfo['process'], $workerInfo['pipes'], 20.0
        );
        unset($activeWorkers[$key]);
        $results[$key] = [
            'done' => $done,
            'exit' => $exit,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'result' => $readResult($stdout)
        ];
    }
    $pids = array_map(static function ($row) { return $row['pid'] ?? null; }, $readyRows);
    $connections = array_map(static function ($row) { return $row['connection_id'] ?? null; }, $readyRows);
    return [
        'all_ready_before_go' => $allReady,
        'two_distinct_processes' => count(array_unique($pids)) === 2 && !in_array(null, $pids, true),
        'two_distinct_connections' => count(array_unique($connections)) === 2 && !in_array(null, $connections, true),
        'ready' => $readyRows,
        'results' => $results
    ];
};

try {
    // B03: both clients load the same ready snapshot and attempt the exact same claim.
    $sameHost = $createFixture(1);
    $sameHostInput = [$sameHost[0], $sameHost[0]];
    $sameHostRace = $runRace($sameHostInput, 'same-host');
    $sameHostResults = array_column($sameHostRace['results'], 'result');
    $sameHostClaimed = array_values(array_filter($sameHostResults, static function ($row) {
        return is_array($row) && true === ($row['claimed'] ?? false);
    }));
    $sameHostRejected = array_values(array_filter($sameHostResults, static function ($row) {
        return is_array($row) && false === ($row['claimed'] ?? true);
    }));
    $sameHostAfter = $readState($sameHost[0]['host_id']);
    $sameHostTasks = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE taskHostID=?');
    $sameHostTasks->execute([$sameHost[0]['host_id']]);
    $sameHostTaskCount = (int)$sameHostTasks->fetchColumn();
    $sameHostTasks->closeCursor();
    $t->check(
        'B03 two ready processes race the same attempt: one started winner and loser plan-changed',
        $sameHostRace['all_ready_before_go'] && $sameHostRace['two_distinct_processes']
            && $sameHostRace['two_distinct_connections'] && 1 === count($sameHostClaimed)
            && 1 === count($sameHostRejected)
            && 'rejoin_plan_changed' === ($sameHostRejected[0]['reason'] ?? null)
            && 'started' === ($sameHostAfter['phase'] ?? null)
            && 0 === ($sameHostAfter['last_deploy_task_id'] ?? null)
            && 0 === ($sameHostAfter['trigger_task_id'] ?? null)
            && 0 === $sameHostTaskCount
    );

    // Negative DB-only identity fixtures deliberately model duplicate AD
    // identity evidence; these tests prove claim fencing, not LDAP identity.
    foreach ([
        ['same-guid', 'GUID'],
        ['same-sid', 'SID']
    ] as $case) {
        $pair = $createFixture(2, $case[0]);
        $leftNames = array_map('strtolower', [$pair[0]['host_name'], $pair[0]['source_name']]);
        $rightNames = array_map('strtolower', [$pair[1]['host_name'], $pair[1]['source_name']]);
        $sameNames = (bool)array_intersect($leftNames, $rightNames);
        $sameTargets = 'same-guid' === $case[0]
            ? ($pair[0]['state']['target']['guid'] === $pair[1]['state']['target']['guid']
                && $pair[0]['state']['target']['sid'] !== $pair[1]['state']['target']['sid'])
            : ($pair[0]['state']['target']['guid'] !== $pair[1]['state']['target']['guid']
                && $pair[0]['state']['target']['sid'] === $pair[1]['state']['target']['sid']);
        // One after the other: the first claim commits, the second must see it.
        $outcome = [];
        foreach ([0, 1] as $i) {
            $claimHost = (new FOG\Items\Host())->set('id', $pair[$i]['host_id'])
                ->set('name', $pair[$i]['host_name'])->set('ADDomain', $pair[$i]['domain'])
                ->set('useAD', 1)->set('agentFingerprint', $pair[$i]['fingerprint']);
            $claimSaved = FOG\Agent\DirectoryRejoin::settings($claimHost);
            try {
                FOG\Agent\DirectoryOperation::claim($claimHost, $claimSaved, $pair[$i]['body']);
                $outcome[$i] = '';
            } catch (\RuntimeException $e) {
                $outcome[$i] = $e->getMessage();
            }
        }
        $statesAfter = [$readState($pair[0]['host_id']), $readState($pair[1]['host_id'])];
        $taskQuery = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE taskHostID IN (?,?)');
        $taskQuery->execute([$pair[0]['host_id'], $pair[1]['host_id']]);
        $taskCount = (int)$taskQuery->fetchColumn();
        $taskQuery->closeCursor();
        $t->check(
            'B04 ' . $case[1] . ' collision refuses the second claim with disjoint names',
            !$sameNames && $sameTargets
                && '' === $outcome[0]
                && 'directory_target_operation_in_progress' === $outcome[1]
                && 'started' === ($statesAfter[0]['phase'] ?? null)
                && 'ready' === ($statesAfter[1]['phase'] ?? null)
                && 0 === ($statesAfter[0]['last_deploy_task_id'] ?? null)
                && 0 === ($statesAfter[1]['last_deploy_task_id'] ?? null)
                && 0 === ($statesAfter[0]['trigger_task_id'] ?? null)
                && 0 === ($statesAfter[1]['trigger_task_id'] ?? null)
                && 0 === $taskCount
        );
    }
} finally {
    foreach ($activeWorkers as $workerInfo) {
        if (is_resource($workerInfo[0])) {
            @proc_terminate($workerInfo[0], 9);
            foreach ([1, 2] as $index) {
                if (isset($workerInfo[1][$index]) && is_resource($workerInfo[1][$index])) {
                    fclose($workerInfo[1][$index]);
                }
            }
            @proc_close($workerInfo[0]);
        }
    }
    foreach ($fixtures as $fixture) {
        if (!empty($fixture['directory_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$fixture['host_id']]);
        }
        if (!empty($fixture['host_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$fixture['host_id']]);
        }
    }
    foreach ($barrierDirs as $barrierDir) {
        foreach (glob($barrierDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($barrierDir);
    }
}

echo "NOTE: B03 uses two simultaneous real-PDO claim processes, B04 two sequential claims; DB-only fixture identities, no Task rows, LDAP, or native operation\n";
$t->finish();
