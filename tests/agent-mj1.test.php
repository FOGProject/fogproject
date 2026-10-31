<?php
require __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('agent-mj1');
FogTestHarness::fakeDb();
$t = new FogChecks();
use FOG\Agent\DirectoryRejoin as R;
use FOG\Agent\MachineIdentity as I;

$t->check('unique MAC resolves a host', ['status' => 'resolved', 'host_id' => 2] === I::choose(null, false, [2]));
$t->check('same host across NICs resolves', ['status' => 'resolved', 'host_id' => 2] === I::choose(2, false, [2, 2]));
$t->check('SMBIOS never overrides another MAC host', 'conflict' === I::choose(1, false, [2])['status']);
$t->check('no majority vote', 'conflict' === I::choose(1, false, [1, 2])['status']);
$t->check('ambiguous firmware is not no firmware', 'conflict' === I::choose(null, true, [2])['status']);
$t->check('NIC replacement with unique firmware', 2 === I::choose(2, false, [])['host_id']);
$t->check('empty identifiers unresolved', 'unresolved' === I::choose(null, false, [])['status']);
$t->check('Route getList stdClass candidate', 7 === I::candidateHostID((object)['hostID' => 7]));
$t->check('array candidate', 7 === I::candidateHostID(['hostID' => 7]));
$t->check('unknown candidate is not a host', 0 === I::candidateHostID((object)[]));
$hardware = ['macs' => ['00:11:22:33:44:55']];
$saved = $hardware + ['_mj1' => ['required' => true]];
$t->check('legacy unmanaged enrollment unchanged', 'allowed' === I::enrollmentGate($hardware, $hardware, false));
$t->check('legacy pending cannot silently acquire feature', 'upgrade_blocked' === I::enrollmentGate($hardware, $hardware, true));
$t->check('required enrollment cannot downgrade', 'feature_required' === I::enrollmentGate($saved, $hardware, false));
$t->check('same required request', 'allowed' === I::enrollmentGate($saved, $hardware, true));
$t->check('changed pending identity cannot be signed', 'upgrade_blocked' === I::enrollmentGate($saved, ['macs' => ['00:11:22:33:44:66']], true));
$saved['_mj1']['upgrade_blocked'] = true;
$t->check('cached certificate cannot bypass saved upgrade block', 'upgrade_blocked' === I::enrollmentGate($saved, $hardware, true));

function mj1Refuses(callable $fn) {
    try { $fn(); return false; } catch (\RuntimeException $e) { return true; }
}
$legacy = ['version' => 1, 'phase' => 'ready', 'attempt_id' => str_repeat('a', 32),
    'source' => ['guid' => '11111111-1111-1111-1111-111111111111', 'sid' => 'S-1-5-21-1-1'],
    'target' => ['guid' => '22222222-2222-2222-2222-222222222222', 'sid' => 'S-1-5-21-1-2'],
    'clone_status' => 'detected', 'clone_origin' => ['host_id' => 99], 'origin_host_id' => 99];
$legacyDecoded = $legacy;
unset($legacyDecoded['clone_status'], $legacyDecoded['clone_origin'], $legacyDecoded['origin_host_id']);
$t->check('legacy v1 state remains reviewable and ignores clone classification',
    $legacyDecoded === R::decode(json_encode($legacy)));
$t->check('legacy source execution methods are removed', !method_exists(R::class, 'makePlan')
    && !method_exists(R::class, 'claimable') && !method_exists(R::class, 'observeState'));
foreach (['{"version":2}', '{"version":1', 'null', str_repeat('x', 16385)] as $raw) {
    $t->check('corrupt state is rejected', mj1Refuses(function () use ($raw) { R::decode($raw); }));
}
foreach (['{"version":1,"placement_hold":"false"}', '{"version":1,"password":"not-a-state-field"}'] as $raw) {
    $t->check('unknown or wrongly typed state is rejected', mj1Refuses(function () use ($raw) { R::decode($raw); }));
}
$blocked = (new FOG\Items\AgentEnrollment())->set('identity', json_encode(['_mj1' => ['required' => true, 'upgrade_blocked' => true]]));
$t->check('pending upgrade block cannot be approved', mj1Refuses(function () use ($blocked) { FOG\Agent\Enrollment::guardMJ1($blocked); }));
$v3OnlyHost = (new FOG\Items\Host())->set('id', 123);
$v3Error = function (array $body) use ($v3OnlyHost) {
    try {
        R::beforePoll($v3OnlyHost, $body);
        return null;
    } catch (\RuntimeException $e) {
        return [$e->getMessage(), $e->getCode()];
    }
};
$t->check('v3-only request cannot bypass identity gating on an unmanaged host',
    ['mj1_feature_required', 409] === $v3Error(['features' => [FOG\Agent\DirectoryOperation::FEATURE],
        'directory_attempt' => ['phase' => 'none']]));
$t->check('v3-only request without local marker summary also fails before legacy handling',
    ['mj1_feature_required', 409] === $v3Error(['features' => [FOG\Agent\DirectoryOperation::FEATURE]]));
$t->finish();
