<?php
/**
 * Pure Task policy coverage for the deploy-driven directory contract.
 *
 * Usage: php tests/agent-directory-task.test.php
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-directory-task');

$t = new FogChecks();
$now = (new \DateTimeImmutable('2026-10-28T12:00:00Z'))->getTimestamp();

function directoryTaskPolicyRow($id, $type, $state, $changed)
{
    return [
        'taskID' => $id,
        'taskHostID' => 7,
        'taskStateID' => $state,
        'taskTypeID' => $type,
        'taskStateChangedTime' => $changed
    ];
}

function directoryTaskPolicyCall($imaging, $deploy, array $state, array $observation, $now)
{
    return \FOG\Agent\DirectoryTask::decision(
        $imaging,
        $deploy,
        $state,
        $observation,
        $now
    );
}

function directoryTaskPolicyCheck(
    FogChecks $checks,
    $label,
    $expectedStatus,
    $imaging,
    $deploy,
    array $state,
    array $observation,
    $now,
    $expectedTaskId = null
) {
    $result = directoryTaskPolicyCall(
        $imaging,
        $deploy,
        $state,
        $observation,
        $now
    );
    $checks->check(
        $label . ' returns the specified status',
        is_array($result) && $expectedStatus === ($result['status'] ?? null)
    );
    $checks->check(
        $label . ' returns a numeric task_id',
        is_array($result) && isset($result['task_id']) && is_numeric($result['task_id'])
    );
    if (null !== $expectedTaskId) {
        $checks->check(
            $label . ' identifies the authoritative Deploy task',
            (int)$expectedTaskId === (int)($result['task_id'] ?? 0)
        );
    }
    return $result;
}

$deployRecent = directoryTaskPolicyRow(
    101,
    \FOG\Items\TaskType::DEPLOY,
    4,
    '2026-10-28T10:00:00Z'
);
$bootAfterDeploy = ['boot' => ['started' => '2026-10-28T11:00:00Z']];

// A fresh completed Deploy authorizes the Task decision independently of
// secure-channel health; this pure API receives boot evidence, not SC state.
directoryTaskPolicyCheck(
    $t,
    'recent completed Deploy with fresh boot and no prior high-water',
    'eligible',
    [],
    $deployRecent,
    [],
    $bootAfterDeploy,
    $now,
    101
);

directoryTaskPolicyCheck(
    $t,
    'completed Deploy already at the high-water mark',
    'consumed',
    [],
    $deployRecent,
    ['last_deploy_task_id' => 101],
    $bootAfterDeploy,
    $now,
    101
);

$deployOld = directoryTaskPolicyRow(
    80,
    \FOG\Items\TaskType::DEPLOY,
    4,
    '2026-10-26T10:00:00Z'
);
directoryTaskPolicyCheck(
    $t,
    'old completed Deploy establishes initial historical baseline',
    'baseline',
    [],
    $deployOld,
    [],
    $bootAfterDeploy,
    $now,
    80
);
directoryTaskPolicyCheck(
    $t,
    'old completed Deploy after an existing baseline remains an explicit blocker',
    'expired',
    [],
    $deployOld,
    ['last_deploy_task_id' => 79],
    $bootAfterDeploy,
    $now,
    80
);

directoryTaskPolicyCheck(
    $t,
    'boot predating Deploy completion is unknown',
    'unknown',
    [],
    $deployRecent,
    [],
    ['boot' => ['started' => '2026-10-28T09:00:00Z']],
    $now,
    101
);
directoryTaskPolicyCheck(
    $t,
    'invalid Deploy completion date is unknown',
    'unknown',
    [],
    directoryTaskPolicyRow(102, \FOG\Items\TaskType::DEPLOY, 4, 'not-a-date'),
    [],
    $bootAfterDeploy,
    $now,
    102
);
directoryTaskPolicyCheck(
    $t,
    'future Deploy completion date is unknown',
    'unknown',
    [],
    directoryTaskPolicyRow(103, \FOG\Items\TaskType::DEPLOY, 4, '2026-10-29T12:00:00Z'),
    [],
    $bootAfterDeploy,
    $now,
    103
);

directoryTaskPolicyCheck(
    $t,
    'newer Capture does not hide a pending completed Deploy',
    'eligible',
    directoryTaskPolicyRow(102, \FOG\Items\TaskType::CAPTURE, 4, '2026-10-28T11:30:00Z'),
    $deployRecent,
    [],
    $bootAfterDeploy,
    $now,
    101
);

foreach ([0, 1, 2, 3] as $activeState) {
    directoryTaskPolicyCheck(
        $t,
        'active latest imaging state ' . $activeState . ' blocks AD task decision',
        'in_progress',
        directoryTaskPolicyRow(102, \FOG\Items\TaskType::CAPTURE, $activeState, '2026-10-28T11:30:00Z'),
        $deployRecent,
        [],
        $bootAfterDeploy,
        $now,
        101
    );
}

directoryTaskPolicyCheck(
    $t,
    'failed latest Deploy is unknown',
    'unknown',
    [],
    directoryTaskPolicyRow(104, \FOG\Items\TaskType::DEPLOY, 6, '2026-10-28T11:30:00Z'),
    ['last_deploy_task_id' => 101],
    $bootAfterDeploy,
    $now,
    104
);

// A canceled latest Deploy is no trigger, at any age and with or without a
// baseline. It used to answer unknown, which blocked the host for good.
$deployCanceled =directoryTaskPolicyRow(105, \FOG\Items\TaskType::DEPLOY, 5, '2026-10-28T11:30:00Z');
directoryTaskPolicyCheck(
    $t,
    'recent canceled latest Deploy after a baseline is no trigger',
    'none',
    [],
    $deployCanceled,
    ['last_deploy_task_id' => 101],
    $bootAfterDeploy,
    $now,
    105
);
directoryTaskPolicyCheck(
    $t,
    'old canceled latest Deploy with no baseline is no trigger',
    'none',
    [],
    directoryTaskPolicyRow(106, \FOG\Items\TaskType::DEPLOY, 5, '2026-09-05T10:00:00Z'),
    [],
    $bootAfterDeploy,
    $now,
    106
);

$none = directoryTaskPolicyCheck(
    $t,
    'no imaging or Deploy Task',
    'none',
    [],
    [],
    ['last_deploy_task_id' => 0],
    $bootAfterDeploy,
    $now
);
$t->check('no Task decision has task_id zero', 0 === (int)($none['task_id'] ?? -1));

$t->finish();
