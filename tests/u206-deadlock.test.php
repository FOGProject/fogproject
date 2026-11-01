<?php
/**
 * U2-06: drive the real DirectoryPlacementIntent::finish into an InnoDB
 * deadlock using two scratch connections and an inert, one-use LDAP permit.
 *
 * This is an opt-in MariaDB scratch-database integration test. It makes no
 * LDAP/native call and does not edit product code or schema.
 */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-06 deadlock: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('U2-06 requires explicit scratch opt-in and a MySQL PDO DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u206-deadlock');

$pdo = new \PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '', [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_EMULATE_PREPARES => false
]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('U2-06 requires a fog_mj1-prefixed scratch database');
}
$serverVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
if (false === stripos($serverVersion, 'MariaDB')) {
    throw new \RuntimeException('U2-06 requires MariaDB to assert MariaDB deadlock behavior');
}
if (!(int)$pdo->query('SELECT @@innodb_deadlock_detect')->fetchColumn()) {
    throw new \RuntimeException('U2-06 requires MariaDB InnoDB deadlock detection enabled');
}
$pdo->exec('SET SESSION innodb_lock_wait_timeout=10');
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match the validated scratch database');
}

$engineQuery = $pdo->prepare(
    'SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME IN (?,?,?,?)'
);
$engineQuery->execute([$database, 'hosts', 'hostDirectory', 'directoryPlacementIntent', 'tasks']);
$engines = [];
foreach ($engineQuery->fetchAll(\PDO::FETCH_ASSOC) as $engineRow) {
    $engines[$engineRow['TABLE_NAME']] = strtoupper((string)$engineRow['ENGINE']);
}
foreach (['hosts', 'hostDirectory', 'directoryPlacementIntent', 'tasks'] as $table) {
    if ('INNODB' !== ($engines[$table] ?? null)) {
        throw new \RuntimeException('U2-06 requires InnoDB for ' . $table);
    }
}

$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

$checks = new FogChecks();
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$guidBytes = static function ($value) {
    return hex2bin(str_replace('-', '', strtolower((string)$value)));
};
$newHostName = static function () use ($pdo) {
    do {
        $name = 'U206' . strtoupper(bin2hex(random_bytes(5)));
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostName=?');
        $q->execute([$name]);
    } while ((int)$q->fetchColumn() > 0);
    return $name;
};
$insertHost = static function ($name, $domain, $ou, $fingerprint) use ($pdo) {
    $q = $pdo->prepare(
        'INSERT INTO hosts (hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,?,?,?)'
    );
    $q->execute([$name, $domain ? 1 : 0, $domain, $ou, $fingerprint]);
    return (int)$pdo->lastInsertId();
};
$snapshot = static function ($hostID, $taskID, $intentGuid) use ($pdo, $guidBytes) {
    $q = $pdo->prepare(
        'SELECT hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint FROM hosts WHERE hostID=?'
    );
    $q->execute([$hostID]);
    $host = $q->fetch(\PDO::FETCH_ASSOC) ?: null;
    $q = $pdo->prepare(
        'SELECT hdID,hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdPlacementAt,hdPlacementError,hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=?'
    );
    $q->execute([$hostID]);
    $directory = $q->fetch(\PDO::FETCH_ASSOC) ?: null;
    $q = $pdo->prepare(
        'SELECT taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime FROM tasks WHERE taskID=? AND taskHostID=?'
    );
    $q->execute([$taskID, $hostID]);
    $task = $q->fetch(\PDO::FETCH_ASSOC) ?: null;
    $q = $pdo->prepare(
        'SELECT dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult,dpiUpdated FROM directoryPlacementIntent WHERE dpiGUID=?'
    );
    $q->bindValue(1, $guidBytes($intentGuid), \PDO::PARAM_LOB);
    $q->execute();
    $intent = $q->fetch(\PDO::FETCH_ASSOC) ?: null;
    if (!is_array($host) || !is_array($directory) || !is_array($task) || !is_array($intent)) {
        throw new \RuntimeException('U2-06 fresh fixture snapshot is incomplete');
    }
    $intent['dpiGUID'] = bin2hex((string)$intent['dpiGUID']);
    return ['host' => $host, 'directory' => $directory, 'task' => $task, 'intent' => $intent];
};
$writeBarrier = static function ($directory, $name, array $data) {
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (false === file_put_contents($tmp, $json, LOCK_EX) || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new \RuntimeException('U2-06 could not atomically publish ' . $name);
    }
};
$lastWorkerStatus = null;
$waitForFile = static function ($directory, $name, $process, $seconds) use (&$lastWorkerStatus) {
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (is_resource($process)) {
            $state = proc_get_status($process);
            if (empty($state['running'])) {
                $lastWorkerStatus = $state;
                return null;
            }
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return null;
};

$fixtures = [];
$barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    . 'u206-deadlock-' . bin2hex(random_bytes(12));
if (!mkdir($barrierDir, 0700)) {
    throw new \RuntimeException('U2-06 cannot create its private temporary barrier directory');
}
$resolvedBarrierDir = realpath($barrierDir);
$tempRoot = rtrim((string)realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (false === $resolvedBarrierDir || 0 !== strpos($resolvedBarrierDir, $tempRoot)
    || 0 !== strpos(basename($resolvedBarrierDir), 'u206-deadlock-')) {
    @rmdir($barrierDir);
    throw new \RuntimeException('U2-06 barrier directory is outside the private temporary root');
}
$process = null;
$pipes = [];
$parentInTransaction = false;
$fixture = null;
$taskID = null;
$heavyHostIDs = [];
$failWithWorkerDiagnostics = static function ($reason) use (&$process, &$pipes, &$lastWorkerStatus) {
    $status = is_array($lastWorkerStatus) && empty($lastWorkerStatus['running'])
        ? $lastWorkerStatus
        : (is_resource($process) ? proc_get_status($process) : ['running' => false, 'exitcode' => -1]);
    if (is_resource($process) && !empty($status['running'])) {
        proc_terminate($process);
        $deadline = microtime(true) + 3.0;
        do {
            usleep(10000);
            $status = proc_get_status($process);
        } while (!empty($status['running']) && microtime(true) < $deadline);
    }
    $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
    $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';
    foreach ([1, 2] as $pipeID) {
        if (isset($pipes[$pipeID]) && is_resource($pipes[$pipeID])) {
            fclose($pipes[$pipeID]);
        }
    }
    if (is_resource($process)) {
        $closeExit = proc_close($process);
        $exitCode = isset($status['exitcode']) && (int)$status['exitcode'] >= 0
            ? (int)$status['exitcode'] : $closeExit;
    } else {
        $exitCode = isset($status['exitcode']) ? (int)$status['exitcode'] : -1;
    }
    $process = null;
    $pipes = [];
    $diagnostic = "U2-06 child diagnostics (exit " . $exitCode . ")\n"
        . "--- stdout ---\n" . $stdout . "\n--- stderr ---\n" . $stderr . "\n";
    fwrite(STDERR, $diagnostic);
    throw new \RuntimeException($reason . '; child exit=' . $exitCode
        . '; child stdout/stderr were emitted above');
};

try {
    $hostName = $newHostName();
    $fixtureGuid = $guid();
    $fingerprint = hash('sha256', random_bytes(32));
    $rootDN = 'DC=u206,DC=invalid';
    $managedDN = 'OU=Managed,' . $rootDN;
    $oldDN = 'CN=' . $hostName . ',OU=Legacy,' . $rootDN;
    $observedAt = gmdate('Y-m-d H:i:s', time() - 120);
    $hostID = $insertHost($hostName, 'U206', $managedDN, $fingerprint);
    $fixtureIndex = count($fixtures);
    $fixtures[] = ['host_id' => $hostID, 'hd_id' => null, 'task_id' => null,
        'guid' => $fixtureGuid, 'name' => $hostName, 'inserted' => false];
    $fixtures[$fixtureIndex]['inserted'] = true;
    $directoryInsert = $pdo->prepare(
        'INSERT INTO hostDirectory (hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdPlacementAt,hdPlacementError,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,?,NULL,\'\',NULL,NULL)'
    );
    $directoryInsert->execute([$hostID, 'U206', $oldDN, $hostName . '$', $observedAt]);
    $hdID = (int)$pdo->lastInsertId();
    $fixtures[$fixtureIndex]['hd_id'] = $hdID;

    $taskTime = gmdate('Y-m-d H:i:s', time() - 120);
    $taskInsert = $pdo->prepare(
        'INSERT INTO tasks (taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (?,?,?,?,?)'
    );
    $taskInsert->execute(['U2-06 inert fixture task', $hostID, 4,
        FOG\Items\TaskType::DEPLOY, $taskTime]);
    $taskID = (int)$pdo->lastInsertId();
    $fixtures[$fixtureIndex]['task_id'] = $taskID;

    $target = ['guid' => $fixtureGuid, 'sid' => 'S-1-5-21-206-' . $hostID,
        'account' => $hostName . '$', 'dn' => $oldDN];
    $destination = ['guid' => $guid(), 'dn' => $managedDN];
    $snapshotMethod = new \ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
    $snapshotMethod->setAccessible(true);
    $directorySnapshot = $snapshotMethod->invoke(null, $pdo, $hostID);
    $payload = ['version' => 1, 'host_id' => $hostID, 'snapshot' => $directorySnapshot,
        'target' => $target, 'destination' => $destination,
        'dc' => 'dc1.u206.invalid', 'nc' => $rootDN, 'rdn' => 'CN=' . $hostName];
    $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($payload);

    // Fifty independent Host row locks make the parent transaction materially
    // heavier than finish(), which has locked only the one intent row.
    for ($i = 0; $i < 50; $i++) {
        $heavyName = $newHostName();
        $heavyID = $insertHost($heavyName, '', '', '');
        $heavyHostIDs[] = $heavyID;
    }

    $workerTicket = $ticket;
    $workerTicket['guid_hex'] = bin2hex($workerTicket['guid']);
    unset($workerTicket['guid']);
    $workerInput = json_encode(['ticket' => $workerTicket], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $worker = __DIR__ . '/fixtures/u206-deadlock-worker.php';
    $process = proc_open(
        [PHP_BINARY, '-d', 'error_reporting=E_ALL & ~E_DEPRECATED', $worker, $resolvedBarrierDir, $workerInput],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new \RuntimeException('U2-06 could not start its isolated worker');
    }
    fclose($pipes[0]);
    unset($pipes[0]);

    $ldapSuccess = $waitForFile($resolvedBarrierDir, 'ldap-success.json', $process, 20.0);
    if (!is_array($ldapSuccess) || 1 !== ($ldapSuccess['simulated_count'] ?? null)
        || true !== ($ldapSuccess['permit_consumed'] ?? null)) {
        $failWithWorkerDiagnostics('U2-06 worker did not consume exactly one inert LDAP permit');
    }

    $pdo->beginTransaction();
    $parentInTransaction = true;
    $hdLock = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=? FOR UPDATE');
    $hdLock->execute([$hostID]);
    if ((int)$hdLock->fetchColumn() !== $hdID) {
        throw new \RuntimeException('U2-06 parent did not lock the exact fixture HostDirectory row');
    }
    $placeholders = implode(',', array_fill(0, count($heavyHostIDs), '?'));
    $hostLock = $pdo->prepare('SELECT hostID FROM hosts WHERE hostID IN (' . $placeholders . ') FOR UPDATE');
    $hostLock->execute($heavyHostIDs);
    if (count($hostLock->fetchAll(\PDO::FETCH_COLUMN)) !== count($heavyHostIDs)) {
        throw new \RuntimeException('U2-06 parent did not lock every allocated fixture Host row');
    }

    // Capture the exact durable MAY_HAVE_RUN state before forming the cycle.
    $beforeCycle = $snapshot($hostID, $taskID, $fixtureGuid);
    if ('MAY_HAVE_RUN' !== ($beforeCycle['intent']['dpiStatus'] ?? null)) {
        throw new \RuntimeException('U2-06 dispatch did not durably reach MAY_HAVE_RUN');
    }
    $writeBarrier($resolvedBarrierDir, 'go-finish.json', ['host_id' => $hostID]);

    $intentLocked = $waitForFile($resolvedBarrierDir, 'intent-locked.json', $process, 20.0);
    if (!is_array($intentLocked) || true !== ($intentLocked['exact_intent_locked'] ?? null)
        || !hash_equals(bin2hex($guidBytes($fixtureGuid)), (string)($intentLocked['guid_hex'] ?? ''))) {
        $failWithWorkerDiagnostics('U2-06 worker did not signal after locking the exact intent row');
    }

    $parentUpdate = ['completed' => false, 'sqlstate' => null, 'driver_code' => null];
    try {
        $intentUpdate = $pdo->prepare(
            'UPDATE directoryPlacementIntent SET dpiUpdated=dpiUpdated WHERE dpiGUID=?'
        );
        $intentUpdate->execute([$guidBytes($fixtureGuid)]);
        $parentUpdate['completed'] = true;
    } catch (\PDOException $e) {
        $info = is_array($e->errorInfo ?? null) ? $e->errorInfo : [];
        $parentUpdate['sqlstate'] = (string)($info[0] ?? $e->getCode());
        $parentUpdate['driver_code'] = isset($info[1]) ? (int)$info[1] : null;
        $parentUpdate['message'] = $e->getMessage();
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $parentInTransaction = false;
    $writeBarrier($resolvedBarrierDir, 'parent-rollback.json', $parentUpdate);

    $workerResult = $waitForFile($resolvedBarrierDir, 'worker-result.json', $process, 20.0);
    if (!is_array($workerResult)) {
        $failWithWorkerDiagnostics('U2-06 worker did not record the finish result before its deadline');
    }
    $workerStdout = stream_get_contents($pipes[1]);
    $workerStderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    unset($pipes[1], $pipes[2]);
    $workerExit = proc_close($process);
    $process = null;

    // Re-read all four authorities after both transactions have ended.
    $after = $snapshot($hostID, $taskID, $fixtureGuid);
    $intent = $after['intent'];
    $expectedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $deadlockWasVictim = '40001' === ($workerResult['sqlstate'] ?? null)
        && 1213 === ($workerResult['driver_code'] ?? null);
    $parentSurvived = true === ($parentUpdate['completed'] ?? false);
    $freshStatePreserved = $beforeCycle['host'] === $after['host']
        && $beforeCycle['directory'] === $after['directory']
        && $beforeCycle['task'] === $after['task']
        && $beforeCycle['intent'] === $after['intent'];
    $mayPayloadPreserved = 'MAY_HAVE_RUN' === ($intent['dpiStatus'] ?? null)
        && 1 === (int)($intent['dpiGeneration'] ?? 0)
        && hash_equals($expectedPayload, (string)($intent['dpiPayload'] ?? ''))
        && hash_equals(hash('sha256', $expectedPayload), (string)($intent['dpiHash'] ?? ''))
        && null === ($intent['dpiResult'] ?? null);

    $checks->check('expected child finish victim (SQLSTATE 40001 / MariaDB 1213); parent state='
        . (string)($parentUpdate['sqlstate'] ?? 'none') . ', driver='
        . (string)($parentUpdate['driver_code'] ?? 'none'),
        $deadlockWasVictim && $parentSurvived && 0 === $workerExit);
    $checks->check('the parent rollback leaves fresh Host, HostDirectory, Task, and intent snapshots unchanged',
        $freshStatePreserved);
    $checks->check('the durable intent remains generation 1, MAY_HAVE_RUN, with its original payload and hash',
        $mayPayloadPreserved);
    $checks->check('the worker consumed one inert LDAP permit and did not retry finish or LDAP',
        1 === ($workerResult['simulated_count'] ?? null)
        && true === ($workerResult['permit_consumed'] ?? null)
        && 1 === ($workerResult['finish_calls'] ?? null)
        && 1 === ($ldapSuccess['simulated_count'] ?? null));
    $checks->check('MariaDB selected the intended small finish transaction rather than the heavy parent',
        $parentSurvived && '40001' !== ($parentUpdate['sqlstate'] ?? null)
        && 1213 !== ($parentUpdate['driver_code'] ?? null));
    $checks->check('isolated worker exited cleanly and emitted no unexpected stderr',
        0 === $workerExit && '' === trim((string)$workerStderr));
    echo json_encode(['kind' => 'u206-deadlock-result', 'worker' => $workerResult,
        'parent' => $parentUpdate, 'before_sha256' => hash('sha256', serialize($beforeCycle)),
        'after_sha256' => hash('sha256', serialize($after))], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if ('' !== trim((string)$workerStdout)) {
        echo trim((string)$workerStdout) . "\n";
    }
} finally {
    if ($parentInTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_resource($process)) {
        $state = proc_get_status($process);
        if (!empty($state['running'])) {
            proc_terminate($process);
        }
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($process);
    }
    if (is_array($fixtures)) {
        foreach ($fixtures as $row) {
            if (empty($row['inserted'])) {
                continue;
            }
            $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiHostID=?');
            $q->execute([$guidBytes($row['guid']), $row['host_id']]);
            if (null !== $row['task_id']) {
                $q = $pdo->prepare('DELETE FROM tasks WHERE taskID=? AND taskHostID=?');
                $q->execute([$row['task_id'], $row['host_id']]);
            }
            if (null !== $row['hd_id']) {
                $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdID=? AND hdHostID=?');
                $q->execute([$row['hd_id'], $row['host_id']]);
            }
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=? AND hostName=?');
            $q->execute([$row['host_id'], $row['name']]);
        }
    }
    if ($heavyHostIDs) {
        $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        foreach ($heavyHostIDs as $heavyID) {
            $q->execute([$heavyID]);
        }
    }
    if (is_dir($resolvedBarrierDir)) {
        foreach (scandir($resolvedBarrierDir) ?: [] as $name) {
            if ('.' !== $name && '..' !== $name) {
                @unlink($resolvedBarrierDir . DIRECTORY_SEPARATOR . $name);
            }
        }
        @rmdir($resolvedBarrierDir);
    }
}

$checks->finish();
