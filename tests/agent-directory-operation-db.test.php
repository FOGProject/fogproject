<?php
/**
 * Real scratch-DB coverage for Target-v2 configuration, state and task gates.
 * tests/lib/directory-db.php builds the scratch schema. Skips without
 * FOG_TEST_DSN.
 *
 * Usage: FOG_TEST_DSN=... php tests/agent-directory-operation-db.test.php
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory operation DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-operation-db';
require __DIR__ . '/lib/directory-db.php';
$fake = $GLOBALS['fake']; // left by directory-db.php
$manifest = $GLOBALS['manifest'];

$t = new FogChecks();
$pdo = $fake->pdo;
$fake->pdo = $pdo;
$configure = new \ReflectionMethod(FOG\Agent\DirectoryRejoin::class, 'configure');
$configure->setAccessible(true);

$host = (new FOG\Items\Host())
    ->set('id', 7)
    ->set('name', 'B')
    ->set('ADDomain', 'corp.example')
    ->set('useAD', 1)
    ->set('agentFingerprint', str_repeat('f', 64));
$pdo->exec("UPDATE hosts SET hostName='B',hostADDomain='corp.example',hostUseAD=1,hostAgentFingerprint='" . str_repeat('f', 64) . "' WHERE hostID=7");
$pdo->exec("UPDATE hostDirectory SET hdRejoinConfig=NULL,hdRejoinState=NULL WHERE hdHostID=7");

// Configuration persistence is DB-only; it must not require LDAP to be
// configured merely to preserve the create-permission choice.
$configure->invoke(null, $host, [], 'db-test');
$saved = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'missing create permission defaults on and saves without LDAP configuration',
    true === $saved['config']['allow_create'] && 'B' === $saved['config']['name']
);
$configure->invoke(null, $host, ['rejoin_allow_create' => true], 'db-test');
$t->check(
    'explicit create permission ON persists',
    true === FOG\Agent\DirectoryRejoin::settings($host)['config']['allow_create']
);
$configure->invoke(null, $host, ['rejoin_allow_create' => false], 'db-test');
$t->check(
    'explicit create permission OFF persists',
    false === FOG\Agent\DirectoryRejoin::settings($host)['config']['allow_create']
);
$configure->invoke(null, $host, [], 'db-test');
$t->check(
    'omitting create setting preserves explicit OFF',
    false === FOG\Agent\DirectoryRejoin::settings($host)['config']['allow_create']
);

$config = FOG\Agent\DirectoryRejoin::settings($host)['config'];
$dc = 'dc1.corp.example';
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
$target = [
    'guid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
    'sid' => 'S-1-5-21-1-1001',
    'account' => 'B$',
    'dn' => 'CN=B,OU=Computers,DC=corp,DC=example'
];
$state = [
    'version' => 1,
    'protocol' => 2,
    'operation' => 'rebind',
    'mode' => 'existing',
    'target' => $target,
    'host_id' => 7,
    'name' => 'B',
    'domain' => 'corp.example',
    'dc' => $dc,
    'check_dc' => $dc,
    'phase' => 'ready',
    'attempt_id' => str_repeat('a', 32),
    'config_revision' => $revision,
    'fingerprint' => str_repeat('f', 64),
    'expected_current' => 'B',
    'expected_pending' => 'B',
    'observed_at' => time(),
    'last_deploy_task_id' => 0,
    'trigger_task_id' => 0,
    'placement_hold' => true,
    'observation' => [
        'revision' => $revision,
        'domain' => 'corp.example',
        'netbios' => 'CORP',
        'boot' => ['marker' => 'db-test-boot', 'started' => gmdate(DATE_ATOM)]
    ]
];
$body = [
    'attempt_id' => $state['attempt_id'],
    'config_revision' => $revision,
    'operation' => 'rebind',
    'task_id' => 0,
    'target_guid' => $target['guid']
];
$pdo->exec('DROP TABLE IF EXISTS tasks');
$pdo->exec($manifest['tables']['tasks']['create']);
$write = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7');
$write->execute([json_encode($config), json_encode($state)]);
$read = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'v2 config and state round-trip',
    $state === $read['state'] && $config === $read['config']
);

// Exercise the actual DB-only production claim boundary. LDAP and credentials
// are deliberately not part of this test.
$saved = FOG\Agent\DirectoryRejoin::settings($host);
$claimableResult = static function (array $snapshot, array $claimBody) use ($host) {
    try {
        FOG\Agent\DirectoryOperation::claimable($host, $snapshot, $claimBody);
        return null;
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    }
};
$age599Snapshot = $saved;
$age599Snapshot['state']['observed_at'] = time() - 599;
$t->check(
    'T03 claimable accepts fresh observation at age 599',
    null === $claimableResult($age599Snapshot, $body)
);
$claimFreshnessRejections = [];
$age601Snapshot = $saved;
$age601Snapshot['state']['observed_at'] = time() - 601;
$claimFreshnessRejections['T03 age 601'] = $age601Snapshot;
$futureObservationSnapshot = $saved;
$futureObservationSnapshot['state']['observed_at'] = time() + 60;
$claimFreshnessRejections['T03 future timestamp'] = $futureObservationSnapshot;
$missingObservationTimeSnapshot = $saved;
unset($missingObservationTimeSnapshot['state']['observed_at']);
$claimFreshnessRejections['T03 missing observation timestamp'] = $missingObservationTimeSnapshot;
$nonIntegerObservationTimeSnapshot = $saved;
$nonIntegerObservationTimeSnapshot['state']['observed_at'] = (string)time();
$claimFreshnessRejections['T03 non-integer observation timestamp'] = $nonIntegerObservationTimeSnapshot;
$observationRevisionMismatchSnapshot = $saved;
$observationRevisionMismatchSnapshot['state']['observation']['revision'] = str_repeat('0', 32);
$claimFreshnessRejections['T04 observation revision mismatch'] = $observationRevisionMismatchSnapshot;
foreach ($claimFreshnessRejections as $label => $snapshot) {
    $t->check(
        $label . ' refuses claim with rejoin_start_refused',
        'rejoin_start_refused' === $claimableResult($snapshot, $body)
    );
}
$afterClaimFreshnessChecks = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'claimable freshness cases leave the persisted config and ready state unchanged',
    $saved === $afterClaimFreshnessChecks
);

$guardChangedHost = function ($column, $value) use ($pdo, $host, $saved, $body) {
    $pdo->exec("UPDATE hosts SET {$column}=" . $pdo->quote($value) . ' WHERE hostID=7');
    try {
        FOG\Agent\DirectoryOperation::claim($host, $saved, $body);
        return null;
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    } finally {
        $pdo->exec("UPDATE hosts SET hostName='B',hostADDomain='corp.example',hostUseAD=1,hostAgentFingerprint='" . str_repeat('f', 64) . "' WHERE hostID=7");
    }
};
$t->check(
    'transaction rejects a changed Host name before claim',
    'rejoin_plan_changed' === $guardChangedHost('hostName', 'C')
);
$t->check(
    'transaction rejects useAD OFF before claim',
    'rejoin_plan_changed' === $guardChangedHost('hostUseAD', '0')
);
$t->check(
    'transaction rejects a changed agent fingerprint before claim',
    'rejoin_plan_changed' === $guardChangedHost('hostAgentFingerprint', str_repeat('e', 64))
);
$t->check(
    'failed Host-row guards roll back and leave the ready state unchanged',
    $state === FOG\Agent\DirectoryRejoin::settings($host)['state']
);

$mismatch = function (array $changedBody) use ($host, $saved) {
    try {
        FOG\Agent\DirectoryOperation::claim($host, $saved, $changedBody);
        return null;
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    }
};
$badTarget = $body;
$badTarget['target_guid'] = 'dddddddd-dddd-dddd-dddd-dddddddddddd';
$t->check(
    'claim rejects a mismatched Target GUID',
    'rejoin_start_refused' === $mismatch($badTarget)
);
$badRevision = $body;
$badRevision['config_revision'] = str_repeat('0', 32);
$t->check(
    'claim rejects a mismatched config revision',
    'rejoin_start_refused' === $mismatch($badRevision)
);
$badOperation = $body;
$badOperation['operation'] = 'rename';
$t->check(
    'claim rejects a mismatched operation',
    'rejoin_start_refused' === $mismatch($badOperation)
);
$t->check(
    'mismatched claims leave the attempt ready',
    'ready' === FOG\Agent\DirectoryRejoin::settings($host)['state']['phase']
);

// A real competing-claim test. The winner consumes the trigger Task ID and
// changes phase in the same DirectoryRejoin transaction; the second process
// must observe the committed state and refuse its claim.
$state['trigger_task_id'] = 42;
$state['last_deploy_task_id'] = 41;
$write->execute([json_encode($config), json_encode($state)]);
$taskEnd = (new \DateTimeImmutable('now', FOG\Base\FOGBase::storageTimeZone()))
    ->sub(new \DateInterval('PT2M'));
$taskInsert = $pdo->prepare('INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (?,?,?,?,?,?)');
$taskInsert->execute([42, 'directory claim fixture', 7, 4, FOG\Items\TaskType::DEPLOY, $taskEnd->format('Y-m-d H:i:s')]);
$state['observation']['boot']['started'] = $taskEnd->add(new \DateInterval('PT1M'))
    ->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
$write->execute([json_encode($config), json_encode($state)]);
$taskBody = array_replace($body, ['task_id' => 42]);
$saved = FOG\Agent\DirectoryRejoin::settings($host);
$hdIDQuery = $pdo->query('SELECT hdID FROM hostDirectory WHERE hdHostID=7');
$hdID = (int)$hdIDQuery->fetchColumn();
$hdIDQuery->closeCursor();
$staleDirectory = (new FOG\Items\HostDirectory())
    ->set('id', $hdID)
    ->set('hostID', 7)
    ->set('joined', 1)
    ->set('kind', 'ad')
    ->set('domain', 'corp.example')
    ->set('netbios', 'CORP')
    ->set('computerDN', 'CN=stale,OU=Computers,DC=corp,DC=example')
    ->set('machineAccount', 'STALE$')
    ->set('site', 'STALE-SITE')
    ->set('observedAt', date('Y-m-d H:i:s'))
    ->set('placementAt', date('Y-m-d H:i:s'))
    ->set('placementError', 'stale placement result')
    ->set('joinAt', date('Y-m-d H:i:s'))
    ->set('joinError', 'stale join result')
    ->set('rejoinConfig', json_encode($saved['config']))
    ->set('rejoinState', json_encode($saved['state']));
$claim = function () use ($host, $saved, $taskBody) {
    FOG\Agent\DirectoryOperation::claim($host, $saved, $taskBody);
};
if (!function_exists('pcntl_fork') || !function_exists('stream_socket_pair')) {
    throw new \RuntimeException('pcntl and stream_socket_pair required for real Target-v2 claim race');
}
$children = [];
$releaseSockets = [];
for ($i = 0; $i < 2; $i++) {
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if (false === $pair) {
        throw new \RuntimeException('cannot create claim-race barrier');
    }
    $parentSocket = $pair[0];
    $childSocket = $pair[1];
    $pid = pcntl_fork();
    if (0 === $pid) {
        fclose($parentSocket);
        foreach ($releaseSockets as $otherParentSocket) {
            fclose($otherParentSocket);
        }
        $release = fread($childSocket, 1);
        fclose($childSocket);
        if ('g' !== $release) {
            exit(23);
        }
        $fake->pdo = directoryTestPdo();
        try {
            $claim();
            exit(20);
        } catch (\RuntimeException $e) {
            exit(in_array($e->getMessage(), ['rejoin_plan_changed','rejoin_start_refused'], true) ? 21 : 22);
        }
    }
    if ($pid < 0) {
        fclose($parentSocket);
        fclose($childSocket);
        throw new \RuntimeException('fork failed');
    }
    fclose($childSocket);
    $releaseSockets[] = $parentSocket;
    $children[] = $pid;
}
// No parent PDO transaction is open across fork. Release both processes from
// the socket barrier so the real claim transactions contend on the same row.
foreach ($releaseSockets as $socket) {
    fwrite($socket, 'g');
    fclose($socket);
}
$codes = [];
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
    $codes[] = pcntl_wexitstatus($status);
}
sort($codes);
if ([20, 21] !== $codes) {
    throw new \RuntimeException('Target-v2 CAS claim result: ' . json_encode($codes));
}
$pdo = directoryTestPdo();
$fake->pdo = $pdo;
$write = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7');
$claimed = FOG\Agent\DirectoryRejoin::settings($host);
$stored = $claimed['state'];
$t->check(
    'only one process claims and atomically consumes the deploy high-water ID',
    'started' === $stored['phase'] && 42 === $stored['last_deploy_task_id']
);
$claimedConfig = $claimed['config'];
$t->check(
    'claim consumes retry authorization in its config transaction',
    !array_key_exists('retry_authorized', $config)
        && array_key_exists('retry_authorized', $claimedConfig)
        && false === $claimedConfig['retry_authorized']
);

// Save the ORM object populated before claim through the real SQL statement
// parameters. Non-hostDirectory queries continue through FogFakeDb unchanged.
$fake->responder = function ($sql, $params) use ($pdo) {
    if (preg_match('/^\s*(?:INSERT\s+INTO|UPDATE)\s+`hostDirectory`/i', $sql)) {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return [];
    }
    return null;
};
try {
    $staleDirectory->save();
} finally {
    $fake->responder = null;
}
$afterStaleSaveQuery = $pdo->query('SELECT hdRejoinConfig,hdRejoinState,hdJoined,hdMachineAccount,hdPlacementError FROM hostDirectory WHERE hdHostID=7');
$afterStaleSave = $afterStaleSaveQuery->fetch(\PDO::FETCH_ASSOC);
$afterStaleSaveQuery->closeCursor();
$t->check(
    'stale ORM save preserves the config after actual claim',
    json_decode($afterStaleSave['hdRejoinConfig'], true) === $claimedConfig
);
$afterStaleState = json_decode($afterStaleSave['hdRejoinState'], true);
$t->check(
    'stale ORM save preserves started phase and consumed high-water ID',
    'started' === ($afterStaleState['phase'] ?? null)
        && 42 === ($afterStaleState['last_deploy_task_id'] ?? null)
);
$t->check(
    'stale ORM save still persists facts and placement fields',
    1 === (int)$afterStaleSave['hdJoined']
        && 'STALE$' === $afterStaleSave['hdMachineAccount']
        && 'stale placement result' === $afterStaleSave['hdPlacementError']
);

$freshSaved = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'started attempt cannot be claimed again from fresh state',
    'rejoin_start_refused' === (function () use ($host, $freshSaved, $taskBody) {
        try {
            FOG\Agent\DirectoryOperation::claim($host, $freshSaved, $taskBody);
            return '';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })()
);

$stored['phase'] = 'unknown';
$write->execute([json_encode($config), json_encode($stored)]);
$unknownSaved = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'unknown outcome cannot be claimed again from fresh state',
    'rejoin_start_refused' === (function () use ($host, $unknownSaved, $taskBody) {
        try {
            FOG\Agent\DirectoryOperation::claim($host, $unknownSaved, $taskBody);
            return '';
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    })()
);
$stored = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$stored['phase'] = 'failed';
$write->execute([json_encode($config), json_encode($stored)]);

// A failed native result is protected history: Retry must reject without
// clearing its attempt, target, config, or consumed Task high-water mark.
$failedRetryError = null;
try {
    $configure->invoke(null, $host, ['rejoin_retry' => true], 'db-test');
} catch (\ReflectionException $e) {
    throw $e;
} catch (\RuntimeException $e) {
    $failedRetryError = $e->getMessage();
}
$afterFailedRetry = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'failed Retry is refused and preserves config, complete state, and consumed Task high-water',
    'rejoin_attempt_requires_review' === $failedRetryError
        && $config === $afterFailedRetry['config']
        && $stored === $afterFailedRetry['state']
        && 42 === ($afterFailedRetry['state']['last_deploy_task_id'] ?? null)
);

// Verified Retry may authorize a fresh config revision, but it must retain the
// complete verified operation history, including the consumed Task ID.
$verifiedState = $stored;
$verifiedState['phase'] = 'verified';
$write->execute([json_encode($config), json_encode($verifiedState)]);
$configure->invoke(null, $host, ['rejoin_retry' => true], 'db-test');
$afterVerifiedRetry = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'verified Retry authorizes a new config revision and preserves the complete state history',
    true === ($afterVerifiedRetry['config']['retry_authorized'] ?? false)
        && ($afterVerifiedRetry['config']['config_revision'] ?? null) !== ($config['config_revision'] ?? null)
        && $verifiedState === $afterVerifiedRetry['state']
        && 42 === ($afterVerifiedRetry['state']['last_deploy_task_id'] ?? null)
);

// Restore the failed fixture so following Task/claim cases keep their original
// phase, config revision, and high-water assumptions.
$write->execute([json_encode($config), json_encode($stored)]);

// Exercise DirectoryOperation::deploy against the actual tasks schema.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS tasks');
$pdo->exec($manifest['tables']['tasks']['create']);
$storageTZ = FOG\Base\FOGBase::storageTimeZone();
$taskEnd = (new \DateTimeImmutable('now', $storageTZ))->sub(new \DateInterval('PT2H'));
$bootStarted = $taskEnd->add(new \DateInterval('PT30M'));
$taskTime = $taskEnd->format('Y-m-d H:i:s');
$boot = ['started' => $bootStarted->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM)];
$insertTask = function ($id, $hostID, $typeID, $stateID) use ($pdo, $taskTime) {
    $q = $pdo->prepare('INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (?,?,?,?,?,?)');
    $q->execute([$id, 'directory operation DB fixture', $hostID, $stateID, $typeID, $taskTime]);
};
$emptyState = ['last_deploy_task_id' => 0];
/** The deploy task id DirectoryTask treats as eligible, or 0. */
function directoryEligibleDeploy($host, array $state, array $observation)
{
    $task = FOG\Agent\DirectoryTask::read($host, $state, $observation);
    return 'eligible' === $task['status'] ? $task['task_id'] : 0;
}
$pdo->exec('DELETE FROM tasks');
$insertTask(101, 7, FOG\Items\TaskType::DEPLOY, 4);
$t->check(
    'completed host-7 Deploy before boot within 24 hours is eligible',
    101 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);
$pdo->exec('DELETE FROM tasks');
$insertTask(101, 7, FOG\Items\TaskType::DEPLOY, 4);
$insertTask(102, 7, FOG\Items\TaskType::DEPLOY, FOG\Items\TaskState::getQueuedState());
$t->check(
    'newer queued Deploy shadows an older unconsumed completed Deploy',
    0 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);
$pdo->exec('DELETE FROM tasks');
$insertTask(101, 7, FOG\Items\TaskType::DEPLOY, 4);
$insertTask(103, 7, FOG\Items\TaskType::DEPLOY, FOG\Items\TaskState::getCancelledState());
$t->check(
    'newer canceled Deploy shadows an older unconsumed completed Deploy',
    0 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);
$pdo->exec('DELETE FROM tasks');
$insertTask(101, 7, FOG\Items\TaskType::DEPLOY, 4);
$insertTask(102, 7, FOG\Items\TaskType::CAPTURE, 4);
$t->check(
    'newer completed Capture does not hide an unconsumed completed Deploy',
    101 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);
$pdo->exec('DELETE FROM tasks');
$insertTask(101, 7, FOG\Items\TaskType::DEPLOY, 4);
$insertTask(104, 7, FOG\Items\TaskType::DEPLOY, 4);
$t->check(
    'newer completed Deploy 104 is selected over older unconsumed Deploy 101',
    104 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);
$pdo->exec('DELETE FROM tasks');
$insertTask(101, 7, FOG\Items\TaskType::DEPLOY, 4);
$insertTask(102, 7, FOG\Items\TaskType::ALL_SNAPINS, 4);
$t->check(
    'newer Snapin task is outside the imaging-operation high-water query',
    101 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);

$deployID = 110;
foreach (FOG\Items\TaskType::DEPLOYTASKS as $deployType) {
    $pdo->exec('DELETE FROM tasks');
    $insertTask($deployID, 7, $deployType, 4);
    $t->check(
        'completed deploy task type ' . $deployType . ' advances high-water candidate',
        $deployID === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
    );
    $t->check(
        'consumed deploy task type ' . $deployType . ' is not reused',
        0 === directoryEligibleDeploy(
            $host,
            ['last_deploy_task_id' => $deployID],
            ['boot' => $boot]
        )
    );
    $deployID++;
}

$pdo->exec('DELETE FROM tasks');
$insertTask(102, 7, FOG\Items\TaskType::DEPLOY, FOG\Items\TaskState::getCancelledState());
$t->check(
    'canceled Deploy is not eligible',
    0 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);
$pdo->exec('DELETE FROM tasks');
$insertTask(103, 7, FOG\Items\TaskType::CAPTURE, 4);
$t->check(
    'completed Capture is not eligible',
    0 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);
$pdo->exec('DELETE FROM tasks');
$insertTask(104, 8, FOG\Items\TaskType::DEPLOY, 4);
$t->check(
    'Deploy belonging to another host is not eligible',
    0 === directoryEligibleDeploy($host, $emptyState, ['boot' => $boot])
);
$pdo->exec('DELETE FROM tasks');
$insertTask(105, 7, FOG\Items\TaskType::DEPLOY, 4);
$t->check(
    'already-consumed Deploy ID is not eligible again',
    0 === directoryEligibleDeploy($host, ['last_deploy_task_id' => 105], ['boot' => $boot])
);

// Exercise the transactional claim recheck against real, guarded scratch DB
// rows. Each case starts from a ready snapshot whose Task 120 was eligible.
FogTestHarness::setStatic('FOGBase', '_settingsCache', [
    'FOG_AGENT_INVENTORY_ENABLED' => ['value' => '1']
]);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
$taskClaimConfig = FOG\Agent\DirectoryRejoin::settings($host)['config'];
$taskClaimDC = 'dc1.corp.example';
$taskClaimRevision = FOG\Agent\DirectoryOperation::revision($host, $taskClaimConfig, $taskClaimDC);
$storeTaskClaimSnapshot = function (
    array $tasks,
    \DateTimeImmutable $bootStarted,
    $phase = 'ready',
    $triggerTaskID = 120,
    $highWater = 119,
    $attemptID = null
) use ($pdo, $host, $taskClaimConfig, $taskClaimRevision, $taskClaimDC, $write) {
    $pdo->exec('DELETE FROM tasks');
    $insert = $pdo->prepare('INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (?,?,?,?,?,?)');
    foreach ($tasks as $task) {
        $insert->execute([
            $task['id'],
            'directory claim recheck fixture',
            7,
            $task['state'] ?? 4,
            FOG\Items\TaskType::DEPLOY,
            $task['changed']->format('Y-m-d H:i:s')
        ]);
    }
    $attemptID = $attemptID ?: str_repeat('d', 32);
    $observation = [
        'revision' => $taskClaimRevision,
        'current_name' => 'B',
        'pending_name' => 'B',
        'domain' => 'corp.example',
        'netbios' => 'CORP',
        'role' => 'workstation',
        'secure_channel' => 'true',
        'dc' => $taskClaimDC,
        'dc_reachable' => true,
        'boot' => [
            'marker' => 'claim-recheck-boot',
            'started' => $bootStarted->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM)
        ]
    ];
    $state = [
        'version' => 1,
        'protocol' => 2,
        'operation' => 'rebind',
        'mode' => 'create',
        'host_id' => 7,
        'name' => 'B',
        'domain' => 'corp.example',
        'dc' => $taskClaimDC,
        'check_dc' => $taskClaimDC,
        'phase' => $phase,
        'attempt_id' => $attemptID,
        'config_revision' => $taskClaimRevision,
        'fingerprint' => str_repeat('f', 64),
        'expected_current' => 'B',
        'expected_pending' => 'B',
        'observed_at' => time(),
        'last_deploy_task_id' => $highWater,
        'trigger_task_id' => $triggerTaskID,
        'placement_hold' => true,
        'observation' => $observation
    ];
    $write->execute([json_encode($taskClaimConfig), json_encode($state)]);
    $saved = FOG\Agent\DirectoryRejoin::settings($host);
    $body = [
        'attempt_id' => $attemptID,
        'config_revision' => $taskClaimRevision,
        'operation' => 'rebind',
        'task_id' => $triggerTaskID,
        'target_guid' => null
    ];
    return [$saved, $body, $state];
};
$claimError = function (array $saved, array $body) use ($host) {
    try {
        FOG\Agent\DirectoryOperation::claim($host, $saved, $body);
        return null;
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    }
};
$assertClaimUnchanged = function ($label, $expectedError, array $saved, array $body) use ($t, $claimError, $host) {
    $t->check(
        $label . ' rejects the stale or mismatched claim',
        $expectedError === $claimError($saved, $body)
    );
    $after = FOG\Agent\DirectoryRejoin::settings($host)['state'];
    $t->check(
        $label . ' does not start or consume the snapshot Task',
        'ready' === ($after['phase'] ?? null)
            && 119 === ($after['last_deploy_task_id'] ?? null)
            && 120 === ($after['trigger_task_id'] ?? null)
    );
};
$claimNow = new \DateTimeImmutable('now', $storageTZ);
$freshClaimTaskEnd = $claimNow->sub(new \DateInterval('PT2M'));
$freshClaimBoot = $freshClaimTaskEnd->add(new \DateInterval('PT1M'));
$expiredClaimTaskEnd = $claimNow->sub(new \DateInterval('PT25H'));
$expiredClaimBoot = $expiredClaimTaskEnd->add(new \DateInterval('PT1M'));

list($expiredSaved, $expiredBody) = $storeTaskClaimSnapshot(
    [['id' => 120, 'changed' => $expiredClaimTaskEnd]],
    $expiredClaimBoot
);
$assertClaimUnchanged('expired-after-ready Task', 'directory_task_claim_changed', $expiredSaved, $expiredBody);

$newerTaskEnd = $claimNow->sub(new \DateInterval('PT1M'));
list($newerSaved, $newerBody, $newerReadyState) = $storeTaskClaimSnapshot(
    [
        ['id' => 120, 'changed' => $freshClaimTaskEnd],
        ['id' => 121, 'changed' => $newerTaskEnd]
    ],
    $newerTaskEnd->add(new \DateInterval('PT30S'))
);
$assertClaimUnchanged('newer completed Deploy after ready', 'directory_task_claim_changed', $newerSaved, $newerBody);
$stalePlan = FOG\Agent\DirectoryOperation::plan($host);
$staleState = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check(
    'stale ready plan is blocked when a newer completed Deploy supersedes it',
    'blocked' === ($stalePlan['phase'] ?? null)
);
$t->check(
    'stale ready plan preserves the original attempt without rewriting state',
    $newerReadyState['attempt_id'] === ($staleState['attempt_id'] ?? null)
        && $newerReadyState === $staleState
);

list($matchingSaved, $mismatchedBody) = $storeTaskClaimSnapshot(
    [['id' => 120, 'changed' => $freshClaimTaskEnd]],
    $freshClaimBoot
);
$mismatchedBody['task_id'] = 121;
$assertClaimUnchanged('mismatched task_id in start body', 'rejoin_start_refused', $matchingSaved, $mismatchedBody);

list($deletedSaved, $deletedBody) = $storeTaskClaimSnapshot([], $freshClaimBoot);
$assertClaimUnchanged('deleted Task after ready', 'directory_task_claim_changed', $deletedSaved, $deletedBody);

// A placement hold with no attempt is derived state. Fresh healthy
// facts plus no imaging Tasks must clear it without manufacturing a plan.
$pdo->exec('DELETE FROM tasks');
$derivedHoldConfig = $taskClaimConfig;
$derivedHoldConfig['retry_authorized'] = false;
$derivedHoldRevision = FOG\Agent\DirectoryOperation::revision($host, $derivedHoldConfig, $taskClaimDC);
$taskClaimObservation = [
    'revision' => $derivedHoldRevision,
    'current_name' => 'B',
    'pending_name' => 'B',
    'domain' => 'corp.example',
    'netbios' => 'CORP',
    'role' => 'workstation',
    'secure_channel' => 'true',
    'dc' => $taskClaimDC,
    'dc_reachable' => true,
    'boot' => ['marker' => 'healthy-no-task-boot', 'started' => gmdate(DATE_ATOM)]
];
$derivedHoldState = [
    'version' => 1,
    'protocol' => 2,
    'phase' => '',
    'name' => 'B',
    'domain' => 'corp.example',
    'check_dc' => $taskClaimDC,
    'observed_at' => time(),
    'last_deploy_task_id' => 0,
    'placement_hold' => true,
    'observation' => $taskClaimObservation
];
$write->execute([json_encode($derivedHoldConfig), json_encode($derivedHoldState)]);
FOG\Agent\DirectoryOperation::observe($host, ['directory_observation' => $taskClaimObservation]);
$clearedDerivedHold = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check(
    'fresh healthy observation clears a derived placement hold',
    false === ($clearedDerivedHold['placement_hold'] ?? true)
);
$t->check(
    'healthy no-Task state returns no operation plan',
    null === FOG\Agent\DirectoryOperation::plan($host)
        && empty(FOG\Agent\DirectoryRejoin::settings($host)['state']['attempt_id'])
);

// plan() applies the stricter DirectoryRejoin::validName() to every operation:
// an underscore passes Host::isHostnameSafe() but is not an AD computer name.
$host->set('name', 'B_1');
$badName = $derivedHoldState;
$badName['observed_at'] = time();
$badName['observation']['current_name'] = 'B_1';
$badName['observation']['pending_name'] = 'B_1';
$badName['observation']['revision'] = FOG\Agent\DirectoryOperation::revision($host, $derivedHoldConfig, $taskClaimDC);
$write->execute([json_encode($derivedHoldConfig), json_encode($badName)]);
$badNameError = '';
try {
    FOG\Agent\DirectoryOperation::plan($host);
} catch (\RuntimeException $e) {
    $badNameError = $e->getMessage();
}
$host->set('name', 'B');
$t->check('plan refuses a host name that is not a valid computer account name', 'rejoin_name_invalid' === $badNameError);

// plan() owns the observation-trust guard: an observation with an unknown
// secure channel or an unreachable DC is blocked before any operation is chosen.
foreach (['secure_channel' => 'unknown', 'dc_reachable' => false] as $field => $value) {
    $untrusted = $derivedHoldState;
    $untrusted['observed_at'] = time();
    $untrusted['observation'][$field] = $value;
    $write->execute([json_encode($derivedHoldConfig), json_encode($untrusted)]);
    $untrustedPlan = FOG\Agent\DirectoryOperation::plan($host);
    $t->check(
        'plan blocks an observation with ' . $field . ' ' . json_encode($value) . ' before choosing an operation',
        'blocked' === ($untrustedPlan['phase'] ?? null)
            && 'directory_observation_unknown' === ($untrustedPlan['reason'] ?? null)
    );
}

// A genuine uncertain attempt remains authoritative even when a later Deploy
// becomes eligible; it is not replaced by a new operation plan.
$unknownTaskEnd = (new \DateTimeImmutable('now', $storageTZ))->sub(new \DateInterval('PT2M'));
list(, , $unknownAttemptState) = $storeTaskClaimSnapshot(
    [['id' => 130, 'changed' => $unknownTaskEnd]],
    $unknownTaskEnd->add(new \DateInterval('PT1M')),
    'unknown',
    120,
    120,
    str_repeat('e', 32)
);
$unknownAttemptPlan = FOG\Agent\DirectoryOperation::plan($host);
$unknownAttemptAfter = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check(
    'new completed Deploy does not replace an actual unknown attempt',
    'unknown' === ($unknownAttemptPlan['phase'] ?? null)
        && str_repeat('e', 32) === ($unknownAttemptPlan['attempt_id'] ?? null)
        && 120 === ($unknownAttemptPlan['task_id'] ?? null)
);
$t->check(
    'unknown attempt and consumed high-water remain unchanged with newer Task present',
    $unknownAttemptState === $unknownAttemptAfter
        && 130 > ($unknownAttemptAfter['trigger_task_id'] ?? 0)
);
$pdo->exec('DELETE FROM tasks');

// A normal FOG name edit changes the Target-v2 context. Exercise the actual
// observe transaction directly; MachineIdentity is the caller's preceding
// gate and is intentionally outside this focused state test.
$host->set('name', 'A');
$pdo->exec("UPDATE hosts SET hostName='A' WHERE hostID=7");
$oldTarget = [
    'guid' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
    'sid' => 'S-1-5-21-1-2001',
    'account' => 'A$',
    'dn' => 'CN=A,OU=Computers,DC=corp,DC=example'
];
$oldRevision = str_repeat('c', 32);
$oldConfig = [
    'version' => 1,
    'protocol' => 2,
    'allow_create' => false,
    'name' => 'A',
    'domain' => 'corp.example',
    'config_revision' => $oldRevision,
    'target' => $oldTarget
];
$oldState = [
    'version' => 1,
    'protocol' => 2,
    'operation' => 'rebind',
    'mode' => 'existing',
    'target' => $oldTarget,
    'host_id' => 7,
    'name' => 'A',
    'domain' => 'corp.example',
    'dc' => 'dc1.corp.example',
    'check_dc' => 'dc1.corp.example',
    'phase' => 'verified',
    'attempt_id' => str_repeat('e', 32),
    'config_revision' => $oldRevision,
    'fingerprint' => str_repeat('f', 64),
    'last_deploy_task_id' => 77,
    'placement_hold' => false,
    'observation' => ['domain' => 'corp.example', 'netbios' => 'CORP']
];
$write->execute([json_encode($oldConfig), json_encode($oldState)]);
$pdo->exec("UPDATE hosts SET hostName='C' WHERE hostID=7");
$host->set('name', 'C');
FOG\Agent\DirectoryOperation::observe($host, []);
$renamedContext = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'verified old-name context refreshes config to the new Host name',
    'C' === ($renamedContext['config']['name'] ?? null)
);
$t->check(
    'verified context refresh drops old Target and rotates config revision',
    !isset($renamedContext['config']['target'])
        && ($renamedContext['config']['config_revision'] ?? null) !== $oldRevision
);
$t->check(
    'verified context refresh preserves deploy high-water state',
    77 === ($renamedContext['state']['last_deploy_task_id'] ?? null)
);

foreach (['unknown', 'started'] as $pendingPhase) {
    $pendingConfig = $oldConfig;
    $pendingState = $oldState;
    $pendingState['phase'] = $pendingPhase;
    $pendingState['attempt_id'] = str_repeat('f', 32);
    $write->execute([json_encode($pendingConfig), json_encode($pendingState)]);
    FOG\Agent\DirectoryOperation::observe($host, []);
    $pendingAfter = FOG\Agent\DirectoryRejoin::settings($host);
    $t->check(
        $pendingPhase . ' attempt preserves its old config and Target on Host rename',
        $pendingConfig === $pendingAfter['config']
            && $pendingPhase === ($pendingAfter['state']['phase'] ?? null)
            && $pendingState['attempt_id'] === ($pendingAfter['state']['attempt_id'] ?? null)
            && $oldTarget['guid'] === ($pendingAfter['state']['target']['guid'] ?? null)
    );
}

$t->finish();
