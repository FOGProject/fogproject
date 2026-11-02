<?php
/** Local directory marker authority and lost-ACK recovery against guarded scratch DB. */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory local marker DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-local-marker-db';
require __DIR__ . '/lib/directory-db.php';

$t = new FogChecks();
$pdo = $fake->pdo;
$fake->pdo = $pdo;
$host = (new FOG\Items\Host())
    ->set('id', 7)
    ->set('name', 'B')
    ->set('ADDomain', 'corp.example')
    ->set('useAD', 1)
    ->set('agentFingerprint', str_repeat('f', 64));
$pdo->exec("UPDATE hosts SET hostName='B',hostADDomain='corp.example',hostUseAD=1,hostAgentFingerprint='" . str_repeat('f', 64) . "' WHERE hostID=7");
$pdo->exec("UPDATE hostDirectory SET hdRejoinConfig=NULL,hdRejoinState=NULL WHERE hdHostID=7");
$pdo->exec('DROP TABLE IF EXISTS tasks');
$pdo->exec($manifest['tables']['tasks']['create']);

FogTestHarness::setStatic('FOGBase', '_settingsCache', [
    'FOG_AGENT_INVENTORY_ENABLED' => ['value' => '1']
]);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
$configure = new \ReflectionMethod(FOG\Agent\DirectoryRejoin::class, 'configureTarget');
$configure->setAccessible(true);
$configure->invoke(null, $host, [], 'local-marker-db-test');
$config = FOG\Agent\DirectoryRejoin::settings($host)['config'];
$dc = 'dc1.corp.example';
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
$writeState = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7');
$writeState->execute([json_encode($config), json_encode([
    'version' => 1,
    'protocol' => 2,
    'check_dc' => $dc,
    'last_deploy_task_id' => 0
])]);
$freshObservation = [
    'revision' => $revision,
    'current_name' => 'B',
    'pending_name' => 'B',
    'domain' => 'corp.example',
    'netbios' => 'CORP',
    'role' => 'workstation',
    'secure_channel' => 'true',
    'dc' => $dc,
    'dc_reachable' => true,
    'boot' => ['marker' => 'local-marker-boot', 'started' => gmdate(DATE_ATOM)]
];
$local = [
    'phase' => 'unknown',
    'attempt_id' => str_repeat('a', 32),
    'host_id' => 7,
    'fingerprint' => str_repeat('f', 64)
];
$body = ['directory_attempt' => $local, 'directory_observation' => $freshObservation];

// A local marker can be transient evidence during the request. Its hold is
// persisted as derived state; no attempt or native target is created here.
FOG\Agent\DirectoryOperation::observe($host, $body);
$afterObserve = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$blocked = FOG\Agent\DirectoryOperation::plan($host, $body);
$t->check(
    'local unknown marker on a healthy no-operation host keeps placement held',
    true === ($afterObserve['placement_hold'] ?? false)
        && 'true' === ($afterObserve['observation']['secure_channel'] ?? null)
        && empty($afterObserve['attempt_id'])
        && 'blocked' === ($blocked['phase'] ?? null)
        && 'directory_local_operation_requires_review' === ($blocked['reason'] ?? null)
);
$t->check(
    'local marker block does not create a native operation target or attempt',
    empty($afterObserve['target']) && empty($afterObserve['attempt_id'])
        && empty(FOG\Agent\DirectoryRejoin::settings($host)['state']['target'])
);
$unreadableBody = $body;
$unreadableBody['directory_attempt'] = ['phase' => 'unreadable'];
FOG\Agent\DirectoryOperation::observe($host, $unreadableBody);
$unreadablePlan = FOG\Agent\DirectoryOperation::plan($host, $unreadableBody);
$t->check(
    'unreadable local marker also blocks planning without a server attempt',
    'blocked' === ($unreadablePlan['phase'] ?? null)
        && 'directory_local_operation_requires_review' === ($unreadablePlan['reason'] ?? null)
        && empty(FOG\Agent\DirectoryRejoin::settings($host)['state']['attempt_id'])
);

// Explicit admin retry may bridge a readable same-host native marker only
// when there is no current server operation. It must not create a plan without
// a fresh observation, and it cannot waive unreadable or identity mismatches.
$retryState = [
    'version' => 1,
    'protocol' => 2,
    'check_dc' => $dc,
    'last_deploy_task_id' => 0,
    'placement_hold' => true
];
$retryMarker = [
    'phase' => 'unknown',
    'attempt_id' => str_repeat('c', 32),
    'host_id' => 7,
    'fingerprint' => str_repeat('f', 64)
];
$runRetryMarkerPlan = function (array $savedConfig, array $marker) use ($writeState, $retryState, $host) {
    $writeState->execute([json_encode($savedConfig), json_encode($retryState)]);
    return FOG\Agent\DirectoryOperation::plan($host, ['directory_attempt' => $marker]);
};
$noRetryConfig = $config;
$noRetryConfig['retry_authorized'] = false;
$noRetryPlan = $runRetryMarkerPlan($noRetryConfig, $retryMarker);
$t->check(
    'local pending marker without explicit retry remains blocked',
    'blocked' === ($noRetryPlan['phase'] ?? null)
        && 'directory_local_operation_requires_review' === ($noRetryPlan['reason'] ?? null)
        && $retryState === FOG\Agent\DirectoryRejoin::settings($host)['state']
);

$retryConfig = $config;
$retryConfig['retry_authorized'] = true;
$unknownRetryPlan = $runRetryMarkerPlan($retryConfig, $retryMarker);
$t->check(
    'explicit retry cannot override a same-host/key local unknown marker',
    'blocked' === ($unknownRetryPlan['phase'] ?? null)
        && 'directory_local_operation_requires_review' === ($unknownRetryPlan['reason'] ?? null)
        && $retryState === FOG\Agent\DirectoryRejoin::settings($host)['state']
        && empty(FOG\Agent\DirectoryRejoin::settings($host)['state']['attempt_id'])
);

$reservedMarker = $retryMarker;
$reservedMarker['phase'] = 'reserved';
$reservedPlan = $runRetryMarkerPlan($retryConfig, $reservedMarker);
$t->check(
    'explicit retry for same-host/key reserved marker proceeds only to observation-required',
    'blocked' === ($reservedPlan['phase'] ?? null)
        && 'directory_observation_required' === ($reservedPlan['reason'] ?? null)
        && $retryState === FOG\Agent\DirectoryRejoin::settings($host)['state']
        && empty(FOG\Agent\DirectoryRejoin::settings($host)['state']['attempt_id']));

$unreadableRetry = $runRetryMarkerPlan($retryConfig, ['phase' => 'unreadable']);
$wrongHostMarker = $retryMarker;
$wrongHostMarker['host_id'] = 8;
$wrongHostRetry = $runRetryMarkerPlan($retryConfig, $wrongHostMarker);
$wrongKeyMarker = $retryMarker;
$wrongKeyMarker['fingerprint'] = str_repeat('d', 64);
$wrongKeyRetry = $runRetryMarkerPlan($retryConfig, $wrongKeyMarker);
$t->check(
    'explicit retry does not waive unreadable, wrong-host, or wrong-key local markers',
    'directory_local_operation_requires_review' === ($unreadableRetry['reason'] ?? null)
        && 'directory_local_operation_requires_review' === ($wrongHostRetry['reason'] ?? null)
        && 'directory_local_operation_requires_review' === ($wrongKeyRetry['reason'] ?? null)
        && $retryState === FOG\Agent\DirectoryRejoin::settings($host)['state']
);

// A matching real server attempt remains authoritative while its native marker
// is unknown; the local summary cannot downgrade or replace it.
$unknownState = [
    'version' => 1,
    'protocol' => 2,
    'operation' => 'rebind',
    'mode' => 'existing',
    'host_id' => 7,
    'name' => 'B',
    'domain' => 'corp.example',
    'dc' => $dc,
    'check_dc' => $dc,
    'phase' => 'unknown',
    'attempt_id' => $local['attempt_id'],
    'config_revision' => $revision,
    'fingerprint' => $local['fingerprint'],
    'expected_current' => 'B',
    'expected_pending' => 'B',
    'observed_at' => time(),
    'last_deploy_task_id' => 0,
    'trigger_task_id' => 0,
    'placement_hold' => true,
    'observation' => $freshObservation
];
$writeState->execute([json_encode($config), json_encode($unknownState)]);
$serverUnknown = FOG\Agent\DirectoryOperation::plan($host, $body);
$t->check(
    'matching local unknown summary preserves the actual Server unknown attempt',
    'unknown' === ($serverUnknown['phase'] ?? null)
        && $local['attempt_id'] === ($serverUnknown['attempt_id'] ?? null)
);

// Awaiting-reboot is the lost-terminal-ACK case. A newly completed Task must
// not replace that verified result until the local marker itself says verified.
$end = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->sub(new \DateInterval('PT2M'));
$bootStarted = $end->add(new \DateInterval('PT1M'));
$insertTask = $pdo->prepare('INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (?,?,?,?,?,?)');
$insertTask->execute([500, 'local marker lost ACK fixture', 7, 4, FOG\Items\TaskType::DEPLOY, $end->format('Y-m-d H:i:s')]);
$workgroupObservation = [
    'revision' => $revision,
    'current_name' => 'B',
    'pending_name' => 'B',
    'domain' => '',
    'netbios' => '',
    'role' => 'standalone_server',
    'secure_channel' => 'unknown',
    'dc' => $dc,
    'dc_reachable' => true,
    'boot' => ['marker' => 'lost-ack-boot', 'started' => $bootStarted->format(DATE_ATOM)]
];
$verifiedState = [
    'version' => 1,
    'protocol' => 2,
    'operation' => 'join',
    'mode' => 'join',
    'host_id' => 7,
    'name' => 'B',
    'domain' => 'corp.example',
    'dc' => $dc,
    'check_dc' => $dc,
    'phase' => 'verified',
    'attempt_id' => str_repeat('b', 32),
    'config_revision' => $revision,
    'fingerprint' => str_repeat('f', 64),
    'expected_current' => 'B',
    'expected_pending' => 'B',
    'observed_at' => time(),
    'last_deploy_task_id' => 499,
    'trigger_task_id' => 499,
    'placement_hold' => false,
    'workgroup' => true,
    'observation' => $workgroupObservation
];
$writeState->execute([json_encode($config), json_encode($verifiedState)]);
$awaitingBody = ['directory_attempt' => [
    'phase' => 'awaiting_reboot',
    'attempt_id' => $verifiedState['attempt_id'],
    'host_id' => 7,
    'fingerprint' => $verifiedState['fingerprint']
]];
$lostAck = FOG\Agent\DirectoryOperation::plan($host, $awaitingBody);
$t->check(
    'matching awaiting-reboot marker returns the same verified result despite a newer Task',
    'verified' === ($lostAck['phase'] ?? null)
        && $verifiedState['attempt_id'] === ($lostAck['attempt_id'] ?? null)
        && 499 === ($lostAck['task_id'] ?? null)
);
$verifiedBody = ['directory_attempt' => [
    'phase' => 'verified',
    'attempt_id' => $verifiedState['attempt_id'],
    'host_id' => 7,
    'fingerprint' => $verifiedState['fingerprint']
]];
$nextTaskPlan = FOG\Agent\DirectoryOperation::plan($host, $verifiedBody);
$nextTaskState = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check(
    'verified local marker releases lost-ACK gate and permits the newer Task',
    'ready' === ($nextTaskPlan['phase'] ?? null)
        && 500 === ($nextTaskPlan['task_id'] ?? null)
        && 500 === ($nextTaskState['trigger_task_id'] ?? null)
        && $verifiedState['attempt_id'] !== ($nextTaskPlan['attempt_id'] ?? null)
);

$malformed = $body;
$malformed['directory_attempt']['unexpected'] = 'not-allowed';
$malformedRejected = false;
try {
    FOG\Agent\DirectoryOperation::plan($host, $malformed);
} catch (\RuntimeException $e) {
    $malformedRejected = 'directory_local_attempt_invalid' === $e->getMessage();
}
$t->check('malformed local marker summary is rejected', $malformedRejected);
$t->check(
    'internal direct helper call without a summary retains its documented optional-none behavior',
    ['phase' => 'none'] === FOG\Agent\DirectoryOperation::localAttempt([])
);

$t->finish();
