<?php
/** Pure MJ-1 observation freshness and placement-health contract checks. */
require __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('sc-observation');
FogTestHarness::fakeDb();
$t = new FogChecks();

$host = (new FOG\Items\Host())
    ->set('id', 71)->set('name', 'WS-71')->set('ADDomain', 'corp.example')
    ->set('agentFingerprint', str_repeat('a', 64));
$config = ['protocol' => 2, 'config_revision' => str_repeat('c', 32), 'name' => 'WS-71', 'domain' => 'corp.example'];
$dc = 'dc1.corp.example';
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
$observation = [
    'revision' => $revision, 'secure_channel' => 'false', 'dc_reachable' => true,
    'role' => 'workstation', 'current_name' => 'OLD-NAME', 'pending_name' => 'WS-71',
    'domain' => '', 'netbios' => '', 'dc' => $dc
];
$state = ['protocol' => 2, 'check_dc' => $dc, 'observation' => $observation, 'observed_at' => 1000];
$callFresh = static function ($s, $now = 1000) use ($host, $config) {
    return FOG\Agent\DirectoryOperation::observationFresh($host, $config, $s, $now);
};
foreach ([['age 599 is fresh', 599, 1599, true], ['age 600 is fresh', 600, 1600, true], ['age 601 is stale', 601, 1601, false]] as $case) {
    $s = $state; $s['observed_at'] = 1000;
    $t->check($case[0], $case[3] === $callFresh($s, $case[2]));
}
$s = $state; $s['observed_at'] = 1001;
$t->check('future timestamp is stale', false === $callFresh($s, 1000));
$s = $state; unset($s['observed_at']);
$t->check('missing timestamp is stale', false === $callFresh($s, 1000));
$s = $state; unset($s['observation']);
$t->check('T03 missing observation is stale', false === $callFresh($s, 1000));
$s = $state; $s['observation'] = 'not-an-observation';
$t->check('T03 non-array observation is stale', false === $callFresh($s, 1000));
$s = $state; unset($s['observation']['revision']);
$t->check('T04 missing revision is stale', false === $callFresh($s, 1000));
$s = $state; $s['observation']['revision'] = 123;
$t->check('T04 non-string revision is stale', false === $callFresh($s, 1000));
foreach (['string' => '1000', 'float' => 1000.0, 'null' => null] as $type => $stamp) {
    $s = $state; $s['observed_at'] = $stamp;
    $t->check($type . ' timestamp is stale', false === $callFresh($s, 1000));
}
$t->check('freshness does not require SC=True, current name, or joined domain', true === $callFresh($state, 1000));

$changedHost = clone $host; $changedHost->set('name', 'WS-72');
$t->check('changed Host name invalidates revision', false === FOG\Agent\DirectoryOperation::observationFresh($changedHost, $config, $state, 1000));
$changedHost = clone $host; $changedHost->set('id', 72);
$t->check('T04 changed Host ID invalidates revision', false === FOG\Agent\DirectoryOperation::observationFresh($changedHost, $config, $state, 1000));
$changedHost = clone $host; $changedHost->set('ADDomain', 'other.example');
$t->check('changed Host domain invalidates revision', false === FOG\Agent\DirectoryOperation::observationFresh($changedHost, $config, $state, 1000));
$changedHost = clone $host; $changedHost->set('agentFingerprint', str_repeat('b', 64));
$t->check('changed Host fingerprint invalidates revision', false === FOG\Agent\DirectoryOperation::observationFresh($changedHost, $config, $state, 1000));
$changedConfig = $config; $changedConfig['config_revision'] = str_repeat('d', 32);
$t->check('changed config invalidates revision', false === FOG\Agent\DirectoryOperation::observationFresh($host, $changedConfig, $state, 1000));
$changedDcState = $state; $changedDcState['check_dc'] = 'dc2.corp.example';
$t->check('changed check_dc invalidates revision', false === FOG\Agent\DirectoryOperation::observationFresh($host, $config, $changedDcState, 1000));

$healthy = $state;
$healthy['observed_at'] = time();
$healthy['observation'] = [
    'revision' => $revision, 'secure_channel' => 'true', 'dc_reachable' => true,
    'role' => 'workstation', 'current_name' => 'WS-71', 'pending_name' => 'WS-71',
    'domain' => 'corp.example', 'netbios' => 'CORP', 'dc' => $dc
];
$t->check('healthy fresh member observation permits placement', true === FOG\Agent\DirectoryOperation::placementHealthy($host, $config, $healthy));
foreach ([
    'false SC' => ['secure_channel' => 'false'], 'unknown SC' => ['secure_channel' => 'unknown'],
    'unreachable DC' => ['dc_reachable' => false], 'unsupported role' => ['role' => 'domain_controller'],
    'name mismatch' => ['current_name' => 'OTHER'], 'domain mismatch' => ['domain' => 'other.example', 'netbios' => 'OTHER']
] as $label => $override) {
    $bad = $healthy; $bad['observation'] = array_merge($healthy['observation'], $override);
    $t->check($label . ' prevents placement', false === FOG\Agent\DirectoryOperation::placementHealthy($host, $config, $bad));
}
$pendingMismatch = $healthy;
$pendingMismatch['observation']['pending_name'] = 'OTHER';
$t->check('T06 pending name mismatch prevents placement', false === FOG\Agent\DirectoryOperation::placementHealthy($host, $config, $pendingMismatch));
$emptyDc = $healthy;
$emptyDc['observation']['dc'] = '';
$t->check('T06 empty observed DC prevents placement', false === FOG\Agent\DirectoryOperation::placementHealthy($host, $config, $emptyDc));
$mismatchedDc = $healthy;
$mismatchedDc['observation']['dc'] = 'dc2.corp.example';
$t->check('T06 observed DC mismatch prevents placement', false === FOG\Agent\DirectoryOperation::placementHealthy($host, $config, $mismatchedDc));
$discoveredDc = $healthy;
$discoveredDc['check_dc'] = '';
$discoveredDc['observation']['dc'] = 'dc2.corp.example';
$discoveredDc['observation']['revision'] = FOG\Agent\DirectoryOperation::revision($host, $config, '');
$t->check('T06 discovered DC is accepted when check_dc is empty and revision matches', true === FOG\Agent\DirectoryOperation::placementHealthy($host, $config, $discoveredDc));
$aliasHost = clone $host;
$aliasHost->set('ADDomain', 'CORP');
$netbiosAlias = $healthy;
$netbiosAlias['observation']['domain'] = 'corp.example';
$netbiosAlias['observation']['netbios'] = 'CORP';
$netbiosAlias['observation']['revision'] = FOG\Agent\DirectoryOperation::revision(
    $aliasHost,
    $config,
    $netbiosAlias['check_dc']
);
$t->check('T06 matching NetBIOS domain alias permits placement', true === FOG\Agent\DirectoryOperation::placementHealthy($aliasHost, $config, $netbiosAlias));
$stale = $healthy; $stale['observed_at'] = time() - 601;
$t->check('stale otherwise healthy observation prevents placement', false === FOG\Agent\DirectoryOperation::placementHealthy($host, $config, $stale));
$t->finish();
