<?php
/** Protected retry and local-marker bridge coverage on the guarded scratch DB. */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory retry protection DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-retry-protection-db';
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
$pdo->exec('UPDATE hostDirectory SET hdRejoinConfig=NULL,hdRejoinState=NULL WHERE hdHostID=7');
FogTestHarness::setStatic('FOGBase', '_settingsCache', [
    'FOG_AGENT_INVENTORY_ENABLED' => ['value' => '1']
]);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);

$retryPhaseCases = [
    'verified state' => [['phase' => 'verified', 'attempt_id' => str_repeat('a', 32)], true],
    'empty state without attempt' => [['phase' => ''], true],
    'empty state with attempt' => [['phase' => '', 'attempt_id' => str_repeat('a', 32)], false],
    'ready state' => [['phase' => 'ready', 'attempt_id' => str_repeat('a', 32)], false],
    'started state' => [['phase' => 'started', 'attempt_id' => str_repeat('a', 32)], false],
    'awaiting-reboot state' => [['phase' => 'awaiting_reboot', 'attempt_id' => str_repeat('a', 32)], false],
    'unknown state' => [['phase' => 'unknown', 'attempt_id' => str_repeat('a', 32)], false],
    'failed state' => [['phase' => 'failed', 'attempt_id' => str_repeat('a', 32)], false],
    'may-have-run state' => [['phase' => 'may_have_run', 'attempt_id' => str_repeat('a', 32)], false],
    'missing phase with attempt' => [['attempt_id' => str_repeat('a', 32)], false]
];
foreach ($retryPhaseCases as $label => $case) {
    $t->check(
        'canConfirmRetry accepts only ' . $label . ' per the explicit boundary',
        $case[1] === FOG\Agent\DirectoryRejoin::canConfirmRetry($case[0])
    );
}

$configure = new \ReflectionMethod(FOG\Agent\DirectoryRejoin::class, 'configureTarget');
$configure->setAccessible(true);
$configure->invoke(null, $host, [], 'retry-protection-db-test');
$baseConfig = FOG\Agent\DirectoryRejoin::settings($host)['config'];
$target = [
    'guid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
    'sid' => 'S-1-5-21-1-1001',
    'account' => 'B$',
    'dn' => 'CN=B,OU=Computers,DC=corp,DC=example'
];
$verifiedState = [
    'version' => 1,
    'protocol' => 2,
    'operation' => 'rebind',
    'mode' => 'existing',
    'target' => $target,
    'host_id' => 7,
    'name' => 'B',
    'domain' => 'corp.example',
    'dc' => 'dc1.corp.example',
    'phase' => 'verified',
    'attempt_id' => str_repeat('b', 32),
    'config_revision' => str_repeat('c', 32),
    'fingerprint' => str_repeat('f', 64),
    'expected_current' => 'B',
    'expected_pending' => 'B',
    'observed_at' => time(),
    'last_deploy_task_id' => 73,
    'trigger_task_id' => 72,
    'placement_hold' => false,
    'reason' => 'verified',
    'native_status' => 0
];
$write = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7');
$write->execute([json_encode($baseConfig), json_encode($verifiedState)]);
$configure->invoke(null, $host, ['rejoin_retry' => true], 'retry-protection-db-test');
$verifiedRetry = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'verified Retry authorizes a new config revision and preserves the complete old state',
    true === ($verifiedRetry['config']['retry_authorized'] ?? false)
        && ($verifiedRetry['config']['config_revision'] ?? null) !== ($baseConfig['config_revision'] ?? null)
        && $verifiedState === $verifiedRetry['state']
);

$protectedConfig = $verifiedRetry['config'];
$protectedState = $verifiedState;
foreach (['started', 'awaiting_reboot', 'unknown', 'failed', 'may_have_run', 'ready'] as $phase) {
    $protectedState['phase'] = $phase;
    $write->execute([json_encode($protectedConfig), json_encode($protectedState)]);
    $before = FOG\Agent\DirectoryRejoin::settings($host);
    $error = null;
    try {
        $configure->invoke(null, $host, ['rejoin_retry' => true], 'retry-protection-db-test');
    } catch (\ReflectionException $e) {
        throw $e;
    } catch (\RuntimeException $e) {
        $error = $e->getMessage();
    }
    $after = FOG\Agent\DirectoryRejoin::settings($host);
    $t->check(
        'configureTarget rejects destructive Retry for ' . $phase . ' and preserves config/state',
        'rejoin_attempt_requires_review' === $error
            && $before['config'] === $after['config']
            && $before['state'] === $after['state']
            && $before['config'] === $protectedConfig
            && $before['state'] === $protectedState
    );
}

$pdo->exec('DROP TABLE IF EXISTS tasks');
$pdo->exec($manifest['tables']['tasks']['create']);
$retryConfig = $protectedConfig;
$retryConfig['retry_authorized'] = true;
$retryConfig['config_revision'] = str_repeat('d', 32);
$storePlanState = static function (array $state) use ($write, $retryConfig, $host) {
    $write->execute([json_encode($retryConfig), json_encode($state)]);
    return FOG\Agent\DirectoryRejoin::settings($host);
};
$localMarker = static function ($phase, $hostID = 7, $fingerprint = null, $attempt = null) {
    return [
        'phase' => $phase,
        'attempt_id' => $attempt ?: str_repeat('e', 32),
        'host_id' => $hostID,
        'fingerprint' => $fingerprint ?: str_repeat('f', 64)
    ];
};
$planState = static function ($phase, $attempt = null) use ($target) {
    $state = [
        'version' => 1,
        'protocol' => 2,
        'phase' => $phase,
        'last_deploy_task_id' => 120,
        'placement_hold' => true
    ];
    if (null !== $attempt) {
        $state += [
            'operation' => 'rebind',
            'mode' => 'existing',
            'target' => $target,
            'host_id' => 7,
            'name' => 'B',
            'domain' => 'corp.example',
            'dc' => 'dc1.corp.example',
            'attempt_id' => $attempt,
            'config_revision' => str_repeat('d', 32),
            'fingerprint' => str_repeat('f', 64),
            'expected_current' => 'B',
            'expected_pending' => 'B'
        ];
    }
    return $state;
};
$runPlan = static function (array $state, array $marker) use ($storePlanState, $host) {
    $saved = $storePlanState($state);
    $result = FOG\Agent\DirectoryOperation::plan($host, ['directory_attempt' => $marker]);
    return [$result, FOG\Agent\DirectoryRejoin::settings($host), $saved];
};

foreach ([
    'empty' => $planState('', null),
    'verified' => $planState('verified', str_repeat('1', 32))
] as $serverPhase => $state) {
    list($result, $after) = $runPlan($state, $localMarker('reserved'));
    $t->check(
        'same-host/key reserved marker bridges Server ' . $serverPhase . ' only to fresh observation requirement',
        'blocked' === ($result['phase'] ?? null)
            && 'directory_observation_required' === ($result['reason'] ?? null)
            && $state === $after['state']
            && 120 === ($after['state']['last_deploy_task_id'] ?? null)
    );
}
$readyState = $planState('ready', str_repeat('2', 32));
list($readyPlan, $readyAfter) = $runPlan($readyState, $localMarker('reserved'));
$t->check(
    'same-host/key reserved marker does not replace a Server ready snapshot',
    'ready' === ($readyPlan['phase'] ?? null)
        && $readyState === $readyAfter['state']
        && 120 === ($readyAfter['state']['last_deploy_task_id'] ?? null)
);

foreach (['unknown', 'failed', 'may_have_run', 'unreadable'] as $localPhase) {
    foreach (['', 'ready', 'verified'] as $serverPhase) {
        $state = $planState($serverPhase, '' === $serverPhase ? null : str_repeat('3', 32));
        list($result, $after) = $runPlan($state, $localMarker($localPhase));
        $t->check(
            'local ' . $localPhase . ' cannot authorize a new plan for Server ' . ($serverPhase ?: 'empty'),
            'blocked' === ($result['phase'] ?? null)
                && 'directory_local_operation_requires_review' === ($result['reason'] ?? null)
                && $state === $after['state']
                && 120 === ($after['state']['last_deploy_task_id'] ?? null)
        );
    }
}
foreach ([
    'wrong host' => $localMarker('reserved', 8),
    'wrong fingerprint' => $localMarker('reserved', 7, str_repeat('9', 64))
] as $identityCase => $marker) {
    $state = $planState('', null);
    list($result, $after) = $runPlan($state, $marker);
    $t->check(
        'reserved bridge refuses ' . $identityCase . ' and preserves state/high-water',
        'blocked' === ($result['phase'] ?? null)
            && 'directory_local_operation_requires_review' === ($result['reason'] ?? null)
            && $state === $after['state']
            && 120 === ($after['state']['last_deploy_task_id'] ?? null)
    );
}

$verifiedLostAck = $planState('verified', str_repeat('4', 32));
$verifiedLostAck['attempt_id'] = str_repeat('4', 32);
$verifiedLostAck['trigger_task_id'] = 119;
$awaitingMarker = $localMarker('awaiting_reboot', 7, str_repeat('f', 64), str_repeat('4', 32));
list($lostAckPlan, $lostAckAfter) = $runPlan($verifiedLostAck, $awaitingMarker);
$t->check(
    'matching awaiting-reboot plus Server verified retains lost-ACK result before Retry bridge',
    'verified' === ($lostAckPlan['phase'] ?? null)
        && str_repeat('4', 32) === ($lostAckPlan['attempt_id'] ?? null)
        && $verifiedLostAck === $lostAckAfter['state']
        && 120 === ($lostAckAfter['state']['last_deploy_task_id'] ?? null)
);

$pdo->exec("INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (121,'retry protection newer task',7,4," . (int)FOG\Items\TaskType::DEPLOY . ",'" . date('Y-m-d H:i:s') . "')");
$unknownServerState = $planState('unknown', str_repeat('5', 32));
$unknownServerState['operation'] = 'rebind';
$unknownServerState['mode'] = 'existing';
$unknownServerState['trigger_task_id'] = 120;
$unknownServerState['target'] = $target;
$unknownServerState['host_id'] = 7;
$unknownServerState['name'] = 'B';
$unknownServerState['domain'] = 'corp.example';
$unknownServerState['dc'] = 'dc1.corp.example';
$unknownServerState['config_revision'] = str_repeat('d', 32);
$unknownServerState['fingerprint'] = str_repeat('f', 64);
$unknownServerState['expected_current'] = 'B';
$unknownServerState['expected_pending'] = 'B';
list($unknownPlan, $unknownAfter) = $runPlan($unknownServerState, $localMarker('reserved'));
$t->check(
    'reserved retry cannot replace Server unknown with a newer Task or reset high-water',
    'blocked' === ($unknownPlan['phase'] ?? null)
        && 'directory_local_operation_requires_review' === ($unknownPlan['reason'] ?? null)
        && $unknownServerState === $unknownAfter['state']
        && 120 === ($unknownAfter['state']['last_deploy_task_id'] ?? null)
        && 121 === (int)$pdo->query('SELECT MAX(taskID) FROM tasks WHERE taskHostID=7')->fetchColumn()
);

$t->finish();
