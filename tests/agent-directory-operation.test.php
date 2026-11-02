<?php
/**
 * Target-v2 operation selection is pure policy: it never calls a native
 * directory API. Keep these checks on the real selector and the repository's
 * ordinary fake-DB harness so absence, lookup failure and authority stay
 * distinct at the decision boundary.
 *
 * Usage: php tests/agent-directory-operation.test.php
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-directory-operation');
FogTestHarness::fakeDb();

$t = new FogChecks();

function directoryOperationObservation(array $overrides = [])
{
    return array_replace([
        'role' => 'workstation',
        'dc_reachable' => true,
        'secure_channel' => 'true',
        'current_name' => 'A',
        'pending_name' => 'A',
        'domain' => 'corp.example',
        'netbios' => 'CORP',
        'dc' => 'dc1.corp.example',
        'boot' => ['marker' => 'boot-1', 'started' => '2026-10-25T00:00:00Z']
    ], $overrides);
}

function directoryOperationTarget($status, array $record = [])
{
    $target = ['status' => $status];
    if ('found' === $status) {
        $target['record'] = $record + [
            'guid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'sid' => 'S-1-5-21-1-1001',
            'account' => 'C$'
        ];
    }
    return $target;
}

function directoryOperationBlocked(callable $call)
{
    try {
        $call();
        return null;
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    }
}

$select = function ($o, $name, $deploy, $reassigned, $target, $allowCreate, $confirmed, $authorized) {
    return \FOG\Agent\DirectoryOperation::select(
        $o,
        $name,
        $deploy,
        $reassigned,
        $target,
        $allowCreate,
        $confirmed,
        $authorized
    );
};

// The selector consumes observation fields only; fresh OS aliases and PDC
// details are resolved outside this pure policy boundary.
// R1: a member may use the existing Rename path when the requested AD name is
// absent and no other FOG host owns the old name.
$renameR1 = $select(
    directoryOperationObservation(),
    'C',
    false,
    false,
    directoryOperationTarget('absent'),
    false,
    false,
    false
);
$t->check(
    'R1 selects a normal rename for healthy, unclaimed host with absent target',
    ['operation' => 'rename', 'mode' => 'rename', 'target' => null] === $renameR1
);

// Regression: before the selector fix, known-broken secure channel plus an
// absent target and create permission incorrectly selected Rebind/Create.
foreach ([true, false] as $secureChannel) {
    foreach ([true, false] as $allowCreate) {
        $normalRename = $select(
            directoryOperationObservation(['secure_channel' => $secureChannel ? 'true' : 'false']),
            'C',
            false,
            false,
            directoryOperationTarget('absent'),
            $allowCreate,
            false,
            false
        );
        $t->check(
            'normal rename with secure channel ' . ($secureChannel ? 'true' : 'false')
                . ' and create permission ' . ($allowCreate ? 'on' : 'off'),
            'rename' === ($normalRename['operation'] ?? null)
                && 'rename' === ($normalRename['mode'] ?? null)
                && null === ($normalRename['target'] ?? null)
        );
    }
}

// R2: a locally pending desired name is compatible with the same Rename
// operation; the selector does not call it complete or verified.
$renameR2 = $select(
    directoryOperationObservation(['pending_name' => 'C']),
    'C',
    false,
    false,
    directoryOperationTarget('absent'),
    false,
    false,
    false
);
$t->check(
    'R2 keeps pending desired rename on the normal rename operation',
    'rename' === ($renameR2['operation'] ?? null)
);

// A reused existing account remains an explicitly confirmed Target Rebind.
$accountA = [
    'guid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
    'sid' => 'S-1-5-21-1-1002',
    'account' => 'A$'
];
$reuseA = $select(
    directoryOperationObservation(['current_name' => 'B']),
    'A',
    false,
    false,
    directoryOperationTarget('found', $accountA),
    false,
    true,
    false
);
$t->check(
    'existing A account is selected as confirmed Target Rebind',
    'rebind' === ($reuseA['operation'] ?? null)
        && 'existing' === ($reuseA['mode'] ?? null)
        && $accountA === ($reuseA['target'] ?? null)
);
$reuseAWithBrokenTrust = $select(
    directoryOperationObservation(['current_name' => 'B', 'secure_channel' => 'false']),
    'A',
    false,
    false,
    directoryOperationTarget('found', $accountA),
    false,
    true,
    false
);
$t->check(
    'confirmed existing Target Rebind remains selectable with broken trust and unassigned old name',
    'rebind' === ($reuseAWithBrokenTrust['operation'] ?? null)
        && 'existing' === ($reuseAWithBrokenTrust['mode'] ?? null)
        && $accountA === ($reuseAWithBrokenTrust['target'] ?? null)
);

// R3/R4: once old name A is assigned to another FOG host, even a healthy
// machine currently called A must not rename that account to C.
$handoffC = $select(
    directoryOperationObservation(),
    'C',
    false,
    true,
    directoryOperationTarget('absent'),
    true,
    false,
    false
);
$t->check(
    'reassigned old name forces Rebind to C even with healthy trust',
    'rebind' === ($handoffC['operation'] ?? null)
        && 'create' === ($handoffC['mode'] ?? null)
);

// D3: target-v2 selection has no Source identity input. Same-name, authorized
// recovery can select its confirmed Target without rejecting Source=Target.
$selfTarget = [
    'guid' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
    'sid' => 'S-1-5-21-1-1003',
    'account' => 'A$'
];
$d3 = $select(
    directoryOperationObservation(['secure_channel' => 'false']),
    'A',
    false,
    false,
    directoryOperationTarget('found', $selfTarget),
    false,
    true,
    true
);
$t->check(
    'authorized same-name D3 recovery selects Target Rebind without Source identity',
    'rebind' === ($d3['operation'] ?? null)
        && 'existing' === ($d3['mode'] ?? null)
        && $selfTarget === ($d3['target'] ?? null)
);

$t->check(
    'same-name broken-trust recovery requires explicit authorization',
    'directory_recovery_confirmation_required' === directoryOperationBlocked(function () use ($select, $selfTarget) {
        $select(
            directoryOperationObservation(['secure_channel' => 'false']),
            'A',
            false,
            false,
            directoryOperationTarget('found', $selfTarget),
            false,
            true,
            false
        );
    })
);

$t->check(
    'same-name broken-trust without rename or authorization remains blocked',
    'directory_recovery_confirmation_required' === directoryOperationBlocked(function () use ($select) {
        $select(
            directoryOperationObservation(['secure_channel' => 'false']),
            'A',
            false,
            false,
            directoryOperationTarget('absent'),
            true,
            false,
            false
        );
    })
);

// Explicit absent permits create; an omitted setting keeps the established
// default. A configured lookup error is never treated as absence.
$t->check(
    'create permission defaults on through the existing helper',
    true === \FOG\Agent\DirectoryRejoin::allowsCreate([])
);
$t->check(
    'create permission can be explicitly disabled',
    false === \FOG\Agent\DirectoryRejoin::allowsCreate(['allow_create' => false])
);
$t->check(
    'healthy same-name host with no Deploy remains a no-op',
    null === $select(
        directoryOperationObservation(),
        'A',
        false,
        false,
        directoryOperationTarget('absent'),
        true,
        false,
        false
    )
);
$t->check(
    'healthy normal rename remains allowed without create permission',
    'rename' === ($select(
        directoryOperationObservation(),
        'C',
        false,
        false,
        directoryOperationTarget('absent'),
        false,
        false,
        false
    )['operation'] ?? null)
);
$t->check(
    'completed Deploy forces Rebind create despite healthy same-name trust',
    'create' === ($select(
        directoryOperationObservation(['current_name' => 'B', 'pending_name' => 'B']),
        'B',
        true,
        false,
        directoryOperationTarget('absent'),
        \FOG\Agent\DirectoryRejoin::allowsCreate([]),
        false,
        false
    )['mode'] ?? null)
);
$t->check(
    'completed Deploy still selects Rebind create with known-broken trust',
    'create' === ($select(
        directoryOperationObservation(['secure_channel' => 'false']),
        'B',
        true,
        false,
        directoryOperationTarget('absent'),
        true,
        false,
        false
    )['mode'] ?? null)
);
$t->check(
    'absent target without create permission is blocked',
    'rejoin_create_not_permitted' === directoryOperationBlocked(function () use ($select) {
        $select(
            directoryOperationObservation(['current_name' => 'B', 'pending_name' => 'B']),
            'B',
            true,
            false,
            directoryOperationTarget('absent'),
            false,
            false,
            false
        );
    })
);
$t->check(
    'unconfigured LDAP blocks normal rename without an explicit old-object guard',
    'rename_identity_unverified' === directoryOperationBlocked(function () use ($select) {
        $select(
            directoryOperationObservation(),
            'C',
            false,
            false,
            directoryOperationTarget('unconfigured'),
            true,
            false,
            false
        );
    })
);
$t->check(
    'configured LDAP lookup error is not absence or create permission',
    'rejoin_lookup_failed' === directoryOperationBlocked(function () use ($select) {
        $select(
            directoryOperationObservation(),
            'C',
            false,
            false,
            directoryOperationTarget('error'),
            true,
            false,
            false
        );
    })
);

// The observation-trust guard (unknown secure channel, unreachable DC) is
// plan()'s, and agent-directory-operation-db.test.php pins it there.
$t->check(
    'pending name conflicting with desired name stops',
    'directory_pending_name_conflict' === directoryOperationBlocked(function () use ($select) {
        $select(
            directoryOperationObservation(['pending_name' => 'B']),
            'C',
            false,
            false,
            directoryOperationTarget('absent'),
            true,
            false,
            false
        );
    })
);
$t->check(
    'existing Target without confirmation stops',
    'rejoin_target_confirmation_required' === directoryOperationBlocked(function () use ($select, $accountA) {
        $select(
            directoryOperationObservation(['current_name' => 'B']),
            'A',
            false,
            false,
            directoryOperationTarget('found', $accountA),
            true,
            false,
            false
        );
    })
);
$t->check(
    'existing Target without confirmation remains blocked with broken trust',
    'rejoin_target_confirmation_required' === directoryOperationBlocked(function () use ($select, $accountA) {
        $select(
            directoryOperationObservation(['current_name' => 'B', 'secure_channel' => 'false']),
            'A',
            false,
            false,
            directoryOperationTarget('found', $accountA),
            true,
            false,
            false
        );
    })
);

$reassignedExisting = $select(
    directoryOperationObservation(['current_name' => 'B', 'secure_channel' => 'false']),
    'A',
    false,
    true,
    directoryOperationTarget('found', $accountA),
    false,
    true,
    false
);
$t->check(
    'reassigned old name retains confirmed existing-target Rebind behavior',
    'rebind' === ($reassignedExisting['operation'] ?? null)
        && 'existing' === ($reassignedExisting['mode'] ?? null)
        && $accountA === ($reassignedExisting['target'] ?? null)
);

$manualHealthy = $select(
    directoryOperationObservation(),
    'A',
    false,
    false,
    directoryOperationTarget('found', $accountA),
    false,
    true,
    true
);
$t->check(
    'explicit confirmed retry remains actionable for healthy same-name membership',
    'rebind' === ($manualHealthy['operation'] ?? null) && 'existing' === ($manualHealthy['mode'] ?? null)
);
$t->check(
    'explicit retry still requires existing Target confirmation',
    'rejoin_target_confirmation_required' === directoryOperationBlocked(function () use ($select, $accountA) {
        $select(
            directoryOperationObservation(),
            'A',
            false,
            false,
            directoryOperationTarget('found', $accountA),
            false,
            false,
            true
        );
    })
);

$t->finish();
