<?php
/** U2-06 focused regular-writer checks against durable placement intent. */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-06 writer intent: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('U2-06 writer intent requires explicit MySQL scratch opt-in');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u206-writer-intent');
$pdo = new PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('U2-06 writer intent requires a fog_mj1-prefixed scratch DB');
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

$checks = new FogChecks();
$fixtures = [];
$workers = [];
$ownedHookEventIDs = [];
$ownedConstraints = [];
$barrierDir = null;
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$newTaskID = static function () use ($pdo) {
    do {
        $id = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE taskID=?');
        $q->execute([$id]);
    } while ((int)$q->fetchColumn() !== 0);
    return $id;
};
$createFixture = static function ($label) use ($pdo, $guid, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $name = 'U206' . strtoupper(bin2hex(random_bytes(5)));
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=? OR hostName=?');
        $q->execute([$hostID, $name]);
        $exists = (int)$q->fetchColumn() > 0;
        $q = $pdo->prepare('SELECT COUNT(*) FROM hostDirectory WHERE hdHostID=?');
        $q->execute([$hostID]);
        $exists = $exists || (int)$q->fetchColumn() > 0;
        $q = $pdo->prepare('SELECT COUNT(*) FROM directoryPlacementIntent WHERE dpiHostID=?');
        $q->execute([$hostID]);
        $exists = $exists || (int)$q->fetchColumn() > 0;
        $q = $pdo->prepare('SELECT COUNT(*) FROM auditLog WHERE alSubjectType=\'host\' AND alSubjectID=?');
        $q->execute([$hostID]);
        $exists = $exists || (int)$q->fetchColumn() > 0;
        $q = $pdo->prepare('SELECT COUNT(*) FROM history WHERE hSubjectType=\'host\' AND hSubjectID=?');
        $q->execute([$hostID]);
        $exists = $exists || (int)$q->fetchColumn() > 0;
    } while ($exists);

    $fingerprint = hash('sha256', random_bytes(32));
    $domain = 'u206.invalid';
    $oldDN = 'CN=' . $name . ',OU=Legacy,DC=u206,DC=invalid';
    $fixtures[] = [
        'label' => $label,
        'host_id' => $hostID,
        'host_inserted' => false,
        'directory_inserted' => false,
        'task_ids' => [],
        'target' => null
    ];
    $index = array_key_last($fixtures);
    $insert = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?, ?, 1, ?, ?, ?)'
    );
    $insert->execute([$hostID, $name, $domain, 'OU=Managed,DC=u206,DC=invalid', $fingerprint]);
    $fixtures[$index]['host_inserted'] = true;
    $insert = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,NULL)'
    );
    $insert->execute([$hostID, $domain, $oldDN, $name . '$']);
    $fixtures[$index]['directory_inserted'] = true;
    $q = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    $directoryID = (int)$q->fetchColumn();

    $snapshot = new ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
    $snapshot->setAccessible(true);
    $target = [
        'guid' => $guid(),
        'sid' => 'S-1-5-21-206-' . $hostID,
        'account' => $name . '$',
        'dn' => $oldDN
    ];
    $destination = ['guid' => $guid(), 'dn' => 'OU=Managed,DC=u206,DC=invalid'];
    $payload = [
        'version' => 1,
        'host_id' => $hostID,
        'snapshot' => $snapshot->invoke(null, $pdo, $hostID),
        'target' => $target,
        'destination' => $destination,
        'dc' => 'dc1.u206.invalid',
        'nc' => 'DC=u206,DC=invalid',
        'rdn' => 'CN=' . $name
    ];
    $fixtures[$index]['target'] = $target;
    return [
        'label' => $label,
        'host_id' => $hostID,
        'name' => $name,
        'fingerprint' => $fingerprint,
        'directory_id' => $directoryID,
        'target' => $target,
        'destination' => $destination,
        'payload' => $payload
    ];
};
$rawRows = static function ($table, $hostID) use ($pdo) {
    if ('hosts' === $table) {
        $q = $pdo->prepare('SELECT * FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    if ('hostDirectory' === $table) {
        $q = $pdo->prepare('SELECT * FROM hostDirectory WHERE hdHostID=? ORDER BY hdID');
        $q->execute([$hostID]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    if ('tasks' === $table) {
        $q = $pdo->prepare('SELECT * FROM tasks WHERE taskHostID=? ORDER BY taskID');
        $q->execute([$hostID]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    if ('directoryPlacementIntent' === $table) {
        $q = $pdo->prepare(
            'SELECT * FROM directoryPlacementIntent WHERE dpiHostID=? ORDER BY dpiGUID,dpiSID'
        );
        $q->execute([$hostID]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    throw new InvalidArgumentException('invalid U2-06 snapshot table');
};
$snapshotAll = static function ($hostID) use ($rawRows) {
    return [
        'hosts' => $rawRows('hosts', $hostID),
        'hostDirectory' => $rawRows('hostDirectory', $hostID),
        'tasks' => $rawRows('tasks', $hostID),
        'intent' => $rawRows('directoryPlacementIntent', $hostID)
    ];
};
$ensureHostDirectoryCascade = static function () use ($pdo, $database, &$ownedConstraints) {
    $q = $pdo->prepare(
        'SELECT k.CONSTRAINT_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,'
        . 'k.REFERENCED_COLUMN_NAME,r.DELETE_RULE '
        . 'FROM information_schema.KEY_COLUMN_USAGE k '
        . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
        . 'ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME '
        . 'AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME '
        . 'WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=\'hostDirectory\' '
        . 'AND k.COLUMN_NAME=\'hdHostID\''
    );
    $q->execute();
    $found = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($found) {
        if (1 !== count($found)
            || $database !== $found[0]['REFERENCED_TABLE_SCHEMA']
            || 'hosts' !== $found[0]['REFERENCED_TABLE_NAME']
            || 'hostID' !== $found[0]['REFERENCED_COLUMN_NAME']
            || 'CASCADE' !== strtoupper((string)$found[0]['DELETE_RULE'])) {
            throw new RuntimeException('existing hdHostID FK is incompatible; fixture will not replace it');
        }
        $columns = $pdo->prepare(
            'SELECT COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME '
            . 'FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() '
            . 'AND TABLE_NAME=\'hostDirectory\' AND CONSTRAINT_NAME=? ORDER BY ORDINAL_POSITION'
        );
        $columns->execute([$found[0]['CONSTRAINT_NAME']]);
        $columnRows = $columns->fetchAll(PDO::FETCH_ASSOC);
        if (1 !== count($columnRows) || 'hdHostID' !== $columnRows[0]['COLUMN_NAME']
            || $database !== $columnRows[0]['REFERENCED_TABLE_SCHEMA']
            || 'hosts' !== $columnRows[0]['REFERENCED_TABLE_NAME']
            || 'hostID' !== $columnRows[0]['REFERENCED_COLUMN_NAME']) {
            throw new RuntimeException('existing hdHostID FK is not the exact single-column parent FK');
        }
        return;
    }

    $orphans = (int)$pdo->query(
        'SELECT COUNT(*) FROM hostDirectory d LEFT JOIN hosts h ON h.hostID=d.hdHostID '
        . 'WHERE h.hostID IS NULL'
    )->fetchColumn();
    if ($orphans) {
        throw new RuntimeException('cannot install fixture cascade FK while hostDirectory has orphan rows');
    }
    do {
        $name = 'fk_u206_wi_hd_host_' . bin2hex(random_bytes(8));
        $exists = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=?'
        );
        $exists->execute([$name]);
    } while ((int)$exists->fetchColumn() > 0);
    $pdo->exec(
        'ALTER TABLE `hostDirectory` ADD CONSTRAINT `' . $name . '` '
        . 'FOREIGN KEY (`hdHostID`) REFERENCES `hosts` (`hostID`) ON DELETE CASCADE'
    );
    $ownedConstraints[] = ['table' => 'hostDirectory', 'name' => $name];
};
$attempt = static function (callable $operation) {
    try {
        return ['result' => $operation(), 'error' => null];
    } catch (Throwable $e) {
        return ['result' => null, 'error' => $e];
    }
};
$ticketForWorker = static function (array $ticket) {
    return [
        'guid' => bin2hex($ticket['guid']),
        'sid' => $ticket['sid'],
        'generation' => $ticket['generation'],
        'hash' => $ticket['hash'],
        'payload' => $ticket['payload']
    ];
};
$waitWorkerFile = static function ($path, $process, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            $data = json_decode((string)file_get_contents($path), true);
            if (is_array($data)) {
                return $data;
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
    if (!isset($status['running']) || $status['running']) {
        @proc_terminate($process, 9);
        foreach ([1, 2] as $i) {
            if (isset($pipes[$i]) && is_resource($pipes[$i])) { @fclose($pipes[$i]); }
        }
        @proc_close($process);
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

try {
    // B14/B35: protected verified D2 history has a real completed Task row,
    // and DirectoryTask reports that same high-water Task as consumed.
    $history = $createFixture('verified-highwater');
    $taskID = $newTaskID();
    $taskChanged = (new DateTimeImmutable('now', FOG\Base\FOGBase::storageTimeZone()))
        ->sub(new DateInterval('PT2M'))->format('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) '
        . 'VALUES (?,?,?,?,?,?)'
    )->execute([$taskID, 'U2-06 consumed directory Task', $history['host_id'], 4,
        FOG\Items\TaskType::DEPLOY, $taskChanged]);
    $fixtures[array_key_last($fixtures)]['task_ids'][] = $taskID;
    $verified = [
        'version' => 1,
        'protocol' => 2,
        'phase' => 'verified',
        'attempt_id' => bin2hex(random_bytes(16)),
        'host_id' => $history['host_id'],
        'fingerprint' => $history['fingerprint'],
        'operation' => 'rebind',
        'mode' => 'existing',
        'name' => $history['name'],
        'expected_current' => $history['name'],
        'expected_pending' => $history['name'],
        'target' => $history['target'],
        'last_deploy_task_id' => $taskID,
        'trigger_task_id' => $taskID
    ];
    $pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=?')
        ->execute([json_encode($verified, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $history['host_id']]);
    $hostItem = new FOG\Items\Host($history['host_id']);
    $readTaskDecision = static function () use ($hostItem, $verified) {
        return FOG\Agent\DirectoryTask::read($hostItem, $verified, []);
    };
    $decisionBefore = $readTaskDecision();
    $historyBefore = $snapshotAll($history['host_id']);
    $protectedDelete = $attempt(static function () use ($history) {
        $result = null;
        FOG\Router\Route::asValue(static function () use ($history, &$result) {
            $result = (new FOG\Items\HostDirectory($history['directory_id']))->destroy();
        });
        return $result;
    });
    $directoryManager = new FOG\Managers\HostDirectoryManager();
    $protectedReparent = $attempt(static function () use ($directoryManager, $history) {
        return $directoryManager->update(
            ['hostID' => $history['host_id']], 'AND', ['hostID' => $history['host_id'] + 1]
        );
    });
    $historyAfter = $snapshotAll($history['host_id']);
    $decisionAfter = $readTaskDecision();
    $checks->check(
        'B14/B35 verified high-water HD delete and reparent preserve the consumed Task and authority snapshots',
        'consumed' === ($decisionBefore['status'] ?? null)
            && $taskID === (int)($decisionBefore['task_id'] ?? 0)
            && $historyBefore === $historyAfter
            && 'consumed' === ($decisionAfter['status'] ?? null)
            && $taskID === (int)($decisionAfter['task_id'] ?? 0)
            && (false === $protectedDelete['result'] || $protectedDelete['error'] instanceof Throwable)
            && (false === $protectedReparent['result'] || $protectedReparent['error'] instanceof Throwable)
    );

    // B14 legacy facts-only positive control, through the normal Route value
    // wrapper and model destroy path. The parent Host must remain present.
    $legacy = $createFixture('legacy-facts-delete');
    $legacyHostBefore = $rawRows('hosts', $legacy['host_id']);
    $legacyDelete = $attempt(static function () use ($legacy) {
        $result = null;
        FOG\Router\Route::asValue(static function () use ($legacy, &$result) {
            $result = (new FOG\Items\HostDirectory($legacy['directory_id']))->destroy();
        });
        return $result;
    });
    $checks->check(
        'B14 facts-only legacy HostDirectory delete succeeds through Route::asValue and keeps Host',
        $legacyDelete['error'] === null
            && $legacyDelete['result'] instanceof FOG\Items\HostDirectory
            && [] === $rawRows('hostDirectory', $legacy['host_id'])
            && $legacyHostBefore === $rawRows('hosts', $legacy['host_id'])
    );

    // B39-23: a real HostManager authority/config write atomically cancels
    // only PREPARED; the old ticket cannot dispatch afterward.
    $prepared = $createFixture('prepared-host-config-edit');
    $preparedTicket = FOG\Agent\DirectoryPlacementIntent::prepare($prepared['payload']);
    $preparedBefore = $snapshotAll($prepared['host_id']);
    $newOU = 'OU=Reconfigured,DC=u206,DC=invalid';
    $hostManager = new FOG\Managers\HostManager();
    $preparedWrite = $attempt(static function () use ($hostManager, $prepared, $newOU) {
        return $hostManager->update(['id' => $prepared['host_id']], 'AND', ['ADOU' => $newOU]);
    });
    $preparedAfterWrite = $snapshotAll($prepared['host_id']);
    $staleDispatchReason = '';
    try {
        FOG\Agent\DirectoryPlacementIntent::dispatch($preparedTicket, new FOG\Net\FOGLdap());
    } catch (Throwable $e) {
        $staleDispatchReason = $e->getMessage();
    }
    $preparedAfterDispatch = $snapshotAll($prepared['host_id']);
    $preparedIntent = $preparedAfterDispatch['intent'][0] ?? null;
    $preparedIntentBefore = $preparedBefore['intent'][0] ?? null;
    $checks->check(
        'B39-23 HostManager config write commits exact PREPARED cancellation and old ticket remains undispatchable',
        $preparedWrite['error'] === null && true === $preparedWrite['result']
            && $newOU === ($preparedAfterWrite['hosts'][0]['hostADOU'] ?? null)
            && is_array($preparedIntent) && 'CANCELLED' === $preparedIntent['dpiStatus']
            && $preparedTicket['generation'] === (int)$preparedIntent['dpiGeneration']
            && $preparedTicket['hash'] === $preparedIntent['dpiHash']
            && is_array($preparedIntentBefore)
            && $preparedIntentBefore['dpiGUID'] === $preparedIntent['dpiGUID']
            && $preparedIntentBefore['dpiSID'] === $preparedIntent['dpiSID']
            && $preparedIntentBefore['dpiHostID'] === $preparedIntent['dpiHostID']
            && $preparedIntentBefore['dpiGeneration'] === $preparedIntent['dpiGeneration']
            && $preparedIntentBefore['dpiPayload'] === $preparedIntent['dpiPayload']
            && $preparedIntentBefore['dpiHash'] === $preparedIntent['dpiHash']
            && 'directory_placement_dispatch_changed' === $staleDispatchReason
            && $preparedAfterWrite === $preparedAfterDispatch
            && $preparedBefore['tasks'] === $preparedAfterWrite['tasks']
    );

    // MAY_HAVE_RUN and UNKNOWN remain protected against Host config edits and
    // parent deletion. Facts-only writes for the same owner stay available;
    // they must not rewrite or advance the durable intent slot.
    foreach (['MAY_HAVE_RUN', 'UNKNOWN'] as $phase) {
        $fixture = $createFixture(strtolower($phase));
        $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($fixture['payload']);
        $ldapIdentity = new FOG\Net\FOGLdap(); // Constructed only; never connected.
        $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldapIdentity);
        if ('UNKNOWN' === $phase) {
            FOG\Agent\DirectoryPlacementIntent::consumePermit(
                $token,
                $ldapIdentity,
                FOG\Net\FOGLdap::guidDN($fixture['target']['guid']),
                FOG\Net\FOGLdap::guidDN($fixture['destination']['guid'])
            );
            FOG\Agent\DirectoryPlacementIntent::finish($token, false, 'u206-synthetic-unknown-no-ldap-call');
        }

        $beforeRefusals = $snapshotAll($fixture['host_id']);
        $configWrite = $attempt(static function () use ($hostManager, $fixture, $newOU) {
            return $hostManager->update(['id' => $fixture['host_id']], 'AND', ['ADOU' => $newOU]);
        });
        $deleteAttempt = $attempt(static function () use ($fixture) {
            $result = null;
            FOG\Router\Route::asValue(static function () use ($fixture, &$result) {
                $result = (new FOG\Items\Host($fixture['host_id']))->destroy();
            });
            return $result;
        });
        $afterRefusals = $snapshotAll($fixture['host_id']);
        $checks->check(
            'B39-23/24 ' . $phase . ' refuses Host config edit and Host deletion before data mutation',
            (false === $configWrite['result'] || $configWrite['error'] instanceof Throwable)
                && (false === $deleteAttempt['result'] || $deleteAttempt['error'] instanceof Throwable)
                && $beforeRefusals === $afterRefusals
                && $phase === ($afterRefusals['intent'][0]['dpiStatus'] ?? null)
                && $ticket['generation'] === (int)($afterRefusals['intent'][0]['dpiGeneration'] ?? 0)
        );

        $intentBeforeFacts = $rawRows('directoryPlacementIntent', $fixture['host_id']);
        $hostBeforeFacts = $rawRows('hosts', $fixture['host_id']);
        $taskBeforeFacts = $rawRows('tasks', $fixture['host_id']);
        $directoryBeforeFacts = $rawRows('hostDirectory', $fixture['host_id'])[0] ?? null;
        $factsSave = (new FOG\Items\HostDirectory($fixture['directory_id']))
            ->set('domain', 'facts.' . strtolower($phase) . '.u206.invalid')
            ->set('observedAt', gmdate('Y-m-d H:i:s'))
            ->save();
        $directoryAfterFacts = $rawRows('hostDirectory', $fixture['host_id'])[0] ?? null;
        $checks->check(
            'B39-23 ' . $phase . ' permits same-owner facts save without changing intent, Host, Task, or authority fields',
            $factsSave instanceof FOG\Items\HostDirectory
                && 'facts.' . strtolower($phase) . '.u206.invalid' === ($directoryAfterFacts['hdDomain'] ?? null)
                && $hostBeforeFacts === $rawRows('hosts', $fixture['host_id'])
                && $taskBeforeFacts === $rawRows('tasks', $fixture['host_id'])
                && $intentBeforeFacts === $rawRows('directoryPlacementIntent', $fixture['host_id'])
                && is_array($directoryBeforeFacts) && is_array($directoryAfterFacts)
                && $directoryBeforeFacts['hdRejoinConfig'] === $directoryAfterFacts['hdRejoinConfig']
                && $directoryBeforeFacts['hdRejoinState'] === $directoryAfterFacts['hdRejoinState']
                && $directoryBeforeFacts['hdHostID'] === $directoryAfterFacts['hdHostID']
        );
    }

    // B39-24: pause a distinct worker after it has loaded the PREPARED ticket.
    // The normal Host destroy wins, then the old worker resumes and must fail
    // dispatch without constructing or calling a connected LDAP transport.
    $ensureHostDirectoryCascade();
    $race = $createFixture('prepared-parent-delete-race');
    $raceTicket = FOG\Agent\DirectoryPlacementIntent::prepare($race['payload']);
    $barrierDir = rtrim((string)realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'u206-writer-intent-' . bin2hex(random_bytes(10));
    if (!mkdir($barrierDir, 0700)) {
        throw new RuntimeException('cannot create private U2-06 barrier directory');
    }
    $barrierDir = realpath($barrierDir);
    $tempRoot = rtrim((string)realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (false === $barrierDir || 0 !== strpos($barrierDir, $tempRoot)
        || 0 !== strpos(basename($barrierDir), 'u206-writer-intent-')) {
        throw new RuntimeException('U2-06 barrier escaped private temp root');
    }
    $worker = __DIR__ . '/fixtures/u206-writer-intent-worker.php';
    $pipes = [];
    $input = json_encode([
        'database' => $database,
        'ticket' => $ticketForWorker($raceTicket)
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $process = proc_open(
        [PHP_BINARY, '-d', 'error_reporting=E_ALL & ~E_DEPRECATED',
            '-d', 'display_errors=stderr', $worker, $barrierDir, 'delete-race', $input],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new RuntimeException('cannot start U2-06 paused dispatch worker');
    }
    fclose($pipes[0]);
    $workers[] = [$process, $pipes];
    $ready = $waitWorkerFile($barrierDir . DIRECTORY_SEPARATOR . 'ready.json', $process, 15.0);
    $raceBeforeDelete = $snapshotAll($race['host_id']);
    $hostDelete = $attempt(static function () use ($race) {
        $result = null;
        FOG\Router\Route::asValue(static function () use ($race, &$result) {
            $result = (new FOG\Items\Host($race['host_id']))->destroy();
        });
        return $result;
    });
    $raceAfterDelete = $snapshotAll($race['host_id']);
    file_put_contents($barrierDir . DIRECTORY_SEPARATOR . 'resume', 'go', LOCK_EX);
    list($workerDone, $workerOut, $workerErr, $workerExit) = $waitWorker($process, $pipes, 20.0);
    $workers = [];
    $workerLines = preg_split('/\r?\n/', trim($workerOut));
    $workerResult = json_decode((string)end($workerLines), true);
    $raceIntent = $rawRows('directoryPlacementIntent', $race['host_id'])[0] ?? null;
    $raceAfterWorker = $snapshotAll($race['host_id']);
    $diagnosticIntent = static function ($row) {
        if (is_array($row) && is_string($row['dpiGUID'] ?? null)
            && 16 === strlen($row['dpiGUID'])) {
            $row['dpiGUID'] = bin2hex($row['dpiGUID']);
        }
        return $row;
    };
    $racePassed = is_array($ready) && true === ($ready['paused_before_dispatch'] ?? false)
            && $raceTicket['generation'] === (int)($ready['generation'] ?? 0)
            && $hostDelete['error'] === null
            && (true === $hostDelete['result'] || $hostDelete['result'] instanceof FOG\Items\Host)
            && [] === $rawRows('hosts', $race['host_id'])
            && [] === $rawRows('hostDirectory', $race['host_id'])
            && [] === ($raceAfterDelete['tasks'] ?? null)
            && [] === ($raceAfterDelete['hosts'] ?? null)
            && [] === ($raceAfterDelete['hostDirectory'] ?? null)
            && 'CANCELLED' === ($raceAfterDelete['intent'][0]['dpiStatus'] ?? null)
            && is_array($raceBeforeDelete['intent'][0] ?? null)
            && 'PREPARED' === $raceBeforeDelete['intent'][0]['dpiStatus']
            && is_array($raceIntent) && 'CANCELLED' === $raceIntent['dpiStatus']
            && ($raceAfterDelete['intent'] ?? null) === $rawRows('directoryPlacementIntent', $race['host_id'])
            && $raceTicket['generation'] === (int)$raceIntent['dpiGeneration']
            && $workerDone && 0 === $workerExit && '' === $workerErr
            && is_array($workerResult) && false === ($workerResult['dispatch_token_issued'] ?? true)
            && 'directory_placement_dispatch_changed' === ($workerResult['dispatch_reason'] ?? null)
            && 'not_connected' === ($workerResult['ldap_transport'] ?? null);
    if (!$racePassed) {
        $deleteError = $hostDelete['error'] ?? null;
        $deleteResult = $hostDelete['result'] ?? null;
        fwrite(STDERR, 'U2-06 prepared-delete race diagnostic: ' . json_encode([
            'conditions' => [
                'barrier_valid' => is_array($ready)
                    && true === ($ready['paused_before_dispatch'] ?? false)
                    && (int)($ready['host_id'] ?? 0) === $race['host_id']
                    && ($ready['target_guid'] ?? null) === $race['target']['guid']
                    && (int)($ready['generation'] ?? 0) === $raceTicket['generation']
                    && ($ready['ticket_hash'] ?? null) === $raceTicket['hash'],
                'delete_returned_success' => $hostDelete['error'] === null
                    && (true === $deleteResult || $deleteResult instanceof FOG\Items\Host),
                'parent_and_directory_absent' => [] === ($raceAfterDelete['hosts'] ?? null)
                    && [] === ($raceAfterDelete['hostDirectory'] ?? null),
                'task_rows_empty' => [] === ($raceAfterDelete['tasks'] ?? null),
                'slot_cancelled_after_delete' => 'CANCELLED' === ($raceAfterDelete['intent'][0]['dpiStatus'] ?? null),
                'slot_unchanged_by_child' => ($raceAfterDelete['intent'] ?? null)
                    === ($raceAfterWorker['intent'] ?? null),
                'worker_completed' => $workerDone,
                'worker_exit_zero' => 0 === $workerExit,
                'worker_stderr_empty' => '' === $workerErr,
                'worker_refused_dispatch' => is_array($workerResult)
                    && false === ($workerResult['dispatch_token_issued'] ?? true)
                    && 'directory_placement_dispatch_changed' === ($workerResult['dispatch_reason'] ?? null),
                'worker_has_no_ldap_connection' => is_array($workerResult)
                    && 'not_connected' === ($workerResult['ldap_transport'] ?? null)
            ],
            'barrier' => [
                'ready' => $ready,
                'valid' => is_array($ready)
                    && true === ($ready['paused_before_dispatch'] ?? false)
                    && (int)($ready['host_id'] ?? 0) === $race['host_id']
                    && ($ready['target_guid'] ?? null) === $race['target']['guid']
                    && (int)($ready['generation'] ?? 0) === $raceTicket['generation']
                    && ($ready['ticket_hash'] ?? null) === $raceTicket['hash'],
                'directory' => $barrierDir,
                'resume_sent' => is_file($barrierDir . DIRECTORY_SEPARATOR . 'resume')
            ],
            'parent_delete' => [
                'result_type' => is_object($deleteResult) ? get_class($deleteResult) : gettype($deleteResult),
                'result_bool' => is_bool($deleteResult) ? $deleteResult : null,
                'error_class' => $deleteError instanceof Throwable ? get_class($deleteError) : null,
                'error' => $deleteError instanceof Throwable ? $deleteError->getMessage() : null
            ],
            'child' => [
                'completed' => $workerDone,
                'exit_code' => $workerExit,
                'stdout' => $workerOut,
                'stderr' => $workerErr,
                'parsed' => $workerResult
            ],
            'slot' => [
                'before_delete' => $diagnosticIntent($raceBeforeDelete['intent'][0] ?? null),
                'after_delete' => $diagnosticIntent($raceAfterDelete['intent'][0] ?? null),
                'after_child' => $diagnosticIntent($raceAfterWorker['intent'][0] ?? null)
            ],
            'row_counts' => [
                'after_delete' => [
                    'hosts' => count($raceAfterDelete['hosts'] ?? []),
                    'hostDirectory' => count($raceAfterDelete['hostDirectory'] ?? []),
                    'tasks' => count($raceAfterDelete['tasks'] ?? [])
                ],
                'after_child' => [
                    'hosts' => count($raceAfterWorker['hosts'] ?? []),
                    'hostDirectory' => count($raceAfterWorker['hostDirectory'] ?? []),
                    'tasks' => count($raceAfterWorker['tasks'] ?? [])
                ]
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
    $checks->check(
        'B39-24 normal Host destroy cancels paused PREPARED ticket and resumed child cannot dispatch',
        $racePassed
    );

    // B31: a valid ready claim is invoked by a real DELETEMASS_API hook while
    // the normal Host decommission route frame is active. The claim must hit
    // assertClaim's generic-writer refusal and abort the enclosing delete.
    $hookFixture = $createFixture('delete-hook-nested-claim');
    $hookHost = new FOG\Items\Host($hookFixture['host_id']);
    $hookDC = 'dc1.u206.invalid';
    $hookConfig = [
        'version' => 1,
        'protocol' => 2,
        'allow_create' => false,
        'name' => $hookFixture['name'],
        'domain' => 'u206.invalid',
        'config_revision' => bin2hex(random_bytes(16)),
        'target' => $hookFixture['target']
    ];
    $hookRevision = FOG\Agent\DirectoryOperation::revision($hookHost, $hookConfig, $hookDC);
    $hookAttemptID = bin2hex(random_bytes(16));
    $hookObservedAt = time();
    $hookObservation = [
        'revision' => $hookRevision,
        'domain' => 'u206.invalid',
        'netbios' => 'U206',
        'role' => 'workstation',
        'secure_channel' => 'true',
        'dc' => $hookDC,
        'dc_reachable' => true,
        'boot' => ['marker' => 'u206-hook-claim', 'started' => gmdate(DATE_ATOM)]
    ];
    $hookState = [
        'version' => 1,
        'protocol' => 2,
        'operation' => 'rebind',
        'mode' => 'existing',
        'target' => $hookFixture['target'],
        'host_id' => $hookFixture['host_id'],
        'name' => $hookFixture['name'],
        'domain' => 'u206.invalid',
        'dc' => $hookDC,
        'check_dc' => $hookDC,
        'phase' => 'ready',
        'attempt_id' => $hookAttemptID,
        'config_revision' => $hookRevision,
        'fingerprint' => $hookFixture['fingerprint'],
        'expected_current' => $hookFixture['name'],
        'expected_pending' => $hookFixture['name'],
        'observed_at' => $hookObservedAt,
        'last_deploy_task_id' => 0,
        'trigger_task_id' => 0,
        'placement_hold' => false,
        'observation' => $hookObservation
    ];
    $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=?')
        ->execute([
            json_encode($hookConfig, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            json_encode($hookState, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $hookFixture['host_id']
        ]);
    $hookSaved = FOG\Agent\DirectoryRejoin::settings($hookHost);
    $hookBody = [
        'attempt_id' => $hookAttemptID,
        'config_revision' => $hookRevision,
        'operation' => 'rebind',
        'task_id' => 0,
        'target_guid' => $hookFixture['target']['guid']
    ];
    $hookClaimPreflight = $attempt(static function () use ($hookHost, $hookSaved, $hookBody) {
        return FOG\Agent\DirectoryOperation::claimable($hookHost, $hookSaved, $hookBody);
    });

    foreach (['DESTROY_HOST', 'DELETEMASS_API'] as $eventName) {
        $q = $pdo->prepare('SELECT heID FROM hookEvents WHERE heName=?');
        $q->execute([$eventName]);
        if (false === $q->fetchColumn()) {
            $insert = $pdo->prepare('INSERT INTO hookEvents (heName) VALUES (?)');
            $insert->execute([$eventName]);
            $ownedHookEventIDs[] = (int)$pdo->lastInsertId();
        }
    }
    $nestedReason = '';
    $hookCalls = 0;
    $hookManager = new FOG\Base\HookManager();
    $hookManager->register('DELETEMASS_API', static function (&$args) use (
        $hookFixture,
        &$nestedReason,
        &$hookCalls
    ) {
        if ('host' !== strtolower((string)($args['classname'] ?? ''))) {
            return;
        }
        $hookCalls++;
        $nestedHost = new FOG\Items\Host($hookFixture['host_id']);
        $nestedSaved = FOG\Agent\DirectoryRejoin::settings($nestedHost);
        $nestedBody = [
            'attempt_id' => $nestedSaved['state']['attempt_id'] ?? '',
            'config_revision' => $nestedSaved['state']['config_revision'] ?? '',
            'operation' => $nestedSaved['state']['operation'] ?? '',
            'task_id' => (int)($nestedSaved['state']['trigger_task_id'] ?? 0),
            'target_guid' => $nestedSaved['state']['target']['guid'] ?? ''
        ];
        try {
            FOG\Agent\DirectoryOperation::claim($nestedHost, $nestedSaved, $nestedBody);
        } catch (Throwable $e) {
            $nestedReason = $e->getMessage();
            throw $e;
        }
    });
    FogTestHarness::setStatic('FOGBase', 'HookManager', $hookManager);
    $catalogLoader = new FOG\Base\HookManager();
    $catalogLoader->processEvent('DESTROY_HOST', []);
    $catalogLoader->processEvent('DELETEMASS_API', []);

    $hookBefore = $snapshotAll($hookFixture['host_id']);
    $hookDelete = $attempt(static function () use ($hookFixture) {
        $result = null;
        FOG\Router\Route::asValue(static function () use ($hookFixture, &$result) {
            $result = (new FOG\Items\Host($hookFixture['host_id']))->destroy();
        });
        return $result;
    });
    $hookAfter = $snapshotAll($hookFixture['host_id']);
    $hookAfterState = isset($hookAfter['hostDirectory'][0]['hdRejoinState'])
        ? json_decode((string)$hookAfter['hostDirectory'][0]['hdRejoinState'], true) : null;
    $checks->check(
        'B31 actual delete hook nested valid claim reaches assertClaim refusal and aborts Host deletion',
        $hookClaimPreflight['error'] === null && null === $hookClaimPreflight['result']
            && 1 === $hookCalls
            && 'directory_claim_inside_generic_writer' === $nestedReason
            && ($hookDelete['error'] instanceof Throwable || false === $hookDelete['result'])
            && $hookBefore === $hookAfter
            && 'ready' === ($hookAfterState['phase'] ?? null)
            && [] === $hookAfter['tasks'] && [] === $hookAfter['intent']
    );
} finally {
    foreach ($workers as $workerEntry) {
        if (is_resource($workerEntry[0])) {
            @proc_terminate($workerEntry[0], 9);
            foreach ([1, 2] as $i) {
                if (isset($workerEntry[1][$i]) && is_resource($workerEntry[1][$i])) {
                    @fclose($workerEntry[1][$i]);
                }
            }
            @proc_close($workerEntry[0]);
        }
    }
    foreach (array_reverse($ownedConstraints) as $constraint) {
        $pdo->exec('ALTER TABLE `' . $constraint['table'] . '` DROP FOREIGN KEY `' . $constraint['name'] . '`');
    }
    foreach ($fixtures as $fixture) {
        $hostID = (int)$fixture['host_id'];
        $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiHostID=?');
        $q->execute([$hostID]);
        foreach ($fixture['task_ids'] as $taskID) {
            $q = $pdo->prepare('DELETE FROM tasks WHERE taskID=? AND taskHostID=?');
            $q->execute([(int)$taskID, $hostID]);
        }
        $q = $pdo->prepare('DELETE FROM auditLog WHERE alSubjectType=\'host\' AND alSubjectID=?');
        $q->execute([$hostID]);
        $q = $pdo->prepare('DELETE FROM history WHERE hSubjectType=\'host\' AND hSubjectID=?');
        $q->execute([$hostID]);
        if (!empty($fixture['directory_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$hostID]);
        }
        if (!empty($fixture['host_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$hostID]);
        }
    }
    foreach ($ownedHookEventIDs as $eventID) {
        $q = $pdo->prepare('DELETE FROM hookEvents WHERE heID=?');
        $q->execute([(int)$eventID]);
    }
    if (is_string($barrierDir) && is_dir($barrierDir)
        && false !== realpath($barrierDir)
        && 0 === strpos(realpath($barrierDir), rtrim((string)realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        && 0 === strpos(basename($barrierDir), 'u206-writer-intent-')) {
        foreach (glob($barrierDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) { @unlink($path); }
        }
        @rmdir($barrierDir);
    }
}

echo "NOTE: LDAP/native effects are not connected or attempted. The nested claim hook case proves the local generic-writer refusal only; external directory behavior is outside this test.\n";
$checks->finish();
