<?php
/**
 * T01/T02/T07/T08/T10/T11/T12 focused DB-backed observation/placement flow.
 * Uses only the guarded fog_mj1 scratch database. No real LDAP connection.
 */
class ScHoldLdapStub
{
    public static $connects = 0;
    public static $moves = [];
    public static $records = [];
    public static $context = [
        'domain' => 'corp.example',
        'base_dn' => 'DC=corp,DC=example',
        'dc' => 'dc1.corp.example',
        'naming_contexts' => [
            'DC=corp,DC=example',
            'CN=Configuration,DC=corp,DC=example',
            'CN=Schema,CN=Configuration,DC=corp,DC=example'
        ]
    ];
    public static $containers = [
        'managed' => [
            'guid' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'dn' => 'OU=Managed,DC=corp,DC=example'
        ]
    ];
    private $connected = false;
    public function connect($uri, $bindDn, $password, $caFile = '') { self::$connects++; $this->connected = true; return true; }
    public function domainContext($wantedDnsDomain, $includeNamingContexts = false)
    {
        if (!$this->connected || strcasecmp((string)(self::$context['domain'] ?? ''), (string)$wantedDnsDomain)) {
            return ['status' => 'error', 'reason' => 'fixture_domain_mismatch'];
        }
        $context = [
            'status' => 'found',
            'base_dn' => (string)(self::$context['base_dn'] ?? ''),
            'dc' => (string)(self::$context['dc'] ?? '')
        ];
        if ($includeNamingContexts) {
            $context['naming_contexts'] = self::$context['naming_contexts'] ?? [
                $context['base_dn'],
                'CN=Configuration,' . $context['base_dn'],
                'CN=Schema,CN=Configuration,' . $context['base_dn']
            ];
        }
        return $context;
    }
    public function placementContext($searchBase)
    {
        $context = $this->domainContext((string)self::$context['domain'], true);
        if ('found' !== ($context['status'] ?? null) || !self::samePlacementNC((string)$searchBase, $context)) {
            return ['status' => 'error', 'reason' => 'fixture_search_base_mismatch'];
        }
        return $context;
    }
    public function computerRecord($baseDn, $account)
    {
        if (!$this->connected || strcasecmp((string)(self::$context['base_dn'] ?? ''), (string)$baseDn)) {
            return ['status' => 'error', 'reason' => 'fixture_scope_mismatch'];
        }
        return self::$records[strtolower(rtrim((string)$account, '$'))]
            ?? ['status' => 'error', 'reason' => 'fixture_record_unspecified'];
    }
    public function findComputer($baseDn, $account) { return (self::$records[strtolower(rtrim((string)$account, '$'))]['record']['dn'] ?? ''); }
    public function containerIdentity($dn)
    {
        foreach (self::$containers as $container) {
            if (!strcasecmp((string)$dn, $container['dn']) || !strcasecmp((string)$dn, self::guidDN($container['guid']))) {
                return ['status' => 'found', 'guid' => $container['guid'], 'dn' => $container['dn']];
            }
        }
        return ['status' => 'error', 'reason' => 'fixture_destination_unspecified'];
    }
    public function moveTo($dn, $parentDn, $dispatchPermit = null)
    {
        $rdn = FOG\Agent\DirectoryPlacementIntent::consumePermit($dispatchPermit, $this, $dn, $parentDn);
        self::$moves[] = [$dn, $parentDn, $rdn];
        return true;
    }
    public function error() { return 'sc_hold_fixture_error'; }
    public function close() { $this->connected = false; }
    public static function rdn($dn) { return explode(',', (string)$dn, 2)[0]; }
    public static function parentDn($dn)
    {
        $parts = explode(',', (string)$dn, 2);
        return $parts[1] ?? '';
    }
    public static function guidDN($guid)
    {
        return '<GUID=' . strtolower(str_replace('-', '', (string)$guid)) . '>';
    }
    public static function sameNamingContext($dn, $nc)
    {
        $dn = (string)$dn;
        $nc = (string)$nc;
        return '' !== $nc && (0 === strcasecmp($dn, $nc)
            || 0 === strcasecmp(substr($dn, -strlen($nc) - 1), ',' . $nc));
    }
    public static function samePlacementNC($dn, array $context)
    {
        $best = '';
        foreach ($context['naming_contexts'] ?? [] as $nc) {
            if (is_string($nc) && strlen($nc) > strlen($best) && self::sameNamingContext($dn, $nc)) {
                $best = $nc;
            }
        }
        return '' !== $best && 0 === strcasecmp($best, (string)($context['base_dn'] ?? ''));
    }
}
class_alias(ScHoldLdapStub::class, 'FOG\\Net\\FOGLdap', false);
require __DIR__ . '/agent-mj1-db.test.php';

$pdo = $fake->pdo;
require_once __DIR__ . '/lib/sc-hold-pdo.php';
$fake = new ScHoldPdoDb($pdo);
FogTestHarness::setStatic('FOGBase', 'DB', $fake);
FogTestHarness::setStatic('DatabaseManager', 'DB', $fake);
$t = new FogChecks();
$manifest = require dirname(__DIR__) . '/packages/web/commons/schema-expected.php';
foreach ($manifest['tables'] as $table) { $pdo->exec($table['create']); }
require_once __DIR__ . '/fixtures/u2-p2-intent-schema.php';
// agent-mj1-db.test.php resets hosts/hostDirectory; reinstall/validate the
// isolated scratch ledger afterward without deleting terminal or UNKNOWN rows.
mj1InstallIntentFixture($pdo);
$pdo->exec("INSERT IGNORE INTO hostMAC (hmHostID,hmMAC,hmPrimary,hmPending) VALUES (7,'02:00:00:00:00:71',1,0)");
$pdo->exec('CREATE TABLE IF NOT EXISTS scHoldLdapCounts (id int NOT NULL PRIMARY KEY, connects int NOT NULL, moves int NOT NULL) ENGINE=InnoDB');
$pdo->exec('REPLACE INTO scHoldLdapCounts (id,connects,moves) VALUES (1,0,0)');
$routeSettings = [
    'FOG_CLIENT_AUTOLOGOFF_ENABLED'=>'0','FOG_CLIENT_HOSTNAMECHANGER_ENABLED'=>'0',
    'FOG_CLIENT_HOSTREGISTER_ENABLED'=>'0','FOG_CLIENT_POWERMANAGEMENT_ENABLED'=>'0',
    'FOG_CLIENT_PRINTERMANAGER_ENABLED'=>'0','FOG_CLIENT_SNAPIN_ENABLED'=>'0',
    'FOG_CLIENT_SOFTWARE_ENABLED'=>'0','FOG_CLIENT_TASKREBOOT_ENABLED'=>'0',
    'FOG_CLIENT_USERTRACKER_ENABLED'=>'0','FOG_AGENT_UPDATE_MODE'=>'off'
];
$settingInsert = $pdo->prepare('INSERT IGNORE INTO globalSettings (settingKey,settingValue) VALUES (?,?)');
foreach ($routeSettings as $key=>$value) { $settingInsert->execute([$key,$value]); }
$pdo->exec("UPDATE hosts SET hostName='WS-71',hostADDomain='corp.example',hostADOU='OU=Managed,DC=corp,DC=example',hostUseAD=1,hostADUser='svc',hostADPass='pw',hostAgentFingerprint='" . str_repeat('a', 64) . "' WHERE hostID=7");
$host = (new FOG\Items\Host())->set('id', 7)->set('name', 'WS-71')->set('ADDomain', 'corp.example')
    ->set('useAD', 1)->set('ADUser', 'svc')->set('ADPass', 'pw')->set('ADOU', 'OU=Managed,DC=corp,DC=example')
    ->set('agentFingerprint', str_repeat('a', 64));
$config = ['version'=>1,'protocol'=>2,'allow_create'=>true,'name'=>'WS-71','domain'=>'corp.example','config_revision'=>str_repeat('c',32),
    'target'=>['guid'=>'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa','sid'=>'S-1-5-21-7-1001','account'=>'WS-71$','dn'=>'CN=WS-71,OU=Old,DC=corp,DC=example']];
$dc = 'dc1.corp.example';
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
$healthyObservation = [
    'revision'=>$revision,'dc'=>$dc,'dc_reachable'=>true,'current_name'=>'WS-71','pending_name'=>'WS-71',
    'domain'=>'corp.example','netbios'=>'CORP','role'=>'workstation','secure_channel'=>'true',
    'boot'=>['marker'=>'boot-71','started'=>gmdate(DATE_ATOM)]
];
$oldObservation = $healthyObservation; $oldObservation['secure_channel'] = 'true';
$state = [
    'version'=>1,'protocol'=>2,'operation'=>'rebind','mode'=>'existing','target'=>$config['target'],
    'host_id'=>7,'name'=>'WS-71','domain'=>'corp.example','dc'=>$dc,'check_dc'=>$dc,
    'phase'=>'verified','attempt_id'=>str_repeat('b',32),'config_revision'=>$revision,
    'fingerprint'=>str_repeat('a',64),'expected_current'=>'WS-71','expected_pending'=>'WS-71',
    'observed_at'=>time()-30,'last_deploy_task_id'=>654,'trigger_task_id'=>654,
    'placement_hold'=>false,'reason'=>'directory_recovery_confirmation_required','secure_channel'=>'broken',
    'native_status'=>0,'observation'=>$oldObservation
];
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=?,hdJoined=1,hdKind=?,hdDomain=?,hdComputerDN=?,hdMachineAccount=? WHERE hdHostID=7')
    ->execute([json_encode($config),json_encode($state),'domain','corp.example','CN=WS-71,OU=Old,DC=corp,DC=example','WS-71$']);
$targetRecord = ['status'=>'found','record'=>['guid'=>$config['target']['guid'],'sid'=>$config['target']['sid'],'account'=>'WS-71$','dn'=>$config['target']['dn']]];
ScHoldLdapStub::$records = ['ws-71'=>$targetRecord];
$cache = [];
foreach ($routeSettings + ['FOG_AGENT_INVENTORY_ENABLED'=>'1','FOG_HOST_IDENTIFY_SMBIOS'=>'off','FOG_DIRECTORY_PLACEMENT_ENABLED'=>'1','FOG_DIRECTORY_LDAP_URI'=>'ldaps://dc1.corp.example',
    'FOG_DIRECTORY_BIND_DN'=>'cn=svc','FOG_DIRECTORY_BIND_PASSWORD'=>'pw','FOG_DIRECTORY_BASE_DN'=>'DC=corp,DC=example','FOG_DIRECTORY_CA_CERT'=>''] as $k=>$v) {
    $cache[$k] = ['value'=>$v,'ts'=>time()];
}
FogTestHarness::setStatic('FOGBase','_settingsCache',$cache);
FogTestHarness::setStatic('FOGBase','_settingsFileChecked',true);

$runObservationPoll = static function ($body) use ($host) {
    FOG\Agent\DirectoryOperation::observe($host, array_replace(['directory_attempt'=>['phase'=>'none']], $body));
    FOG\Agent\State::facts($host, []); // The real facts path calls DirectoryFacts::place().
    return FOG\Agent\DirectoryRejoin::settings($host);
};

/** POST one isolated request through the real Route::agentPoll entry point. */
$routePoll = static function () {
    $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (false === $probe) { throw new RuntimeException('cannot reserve local poll-test port: ' . $error); }
    $address = stream_socket_get_name($probe, false); fclose($probe);
    $port = (int)substr(strrchr((string)$address, ':'), 1);
    $router = __DIR__ . '/lib/sc-hold-agent-poll-router.php';
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) { throw new RuntimeException('cannot start isolated PHP poll server'); }
    stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
    try {
        $payload = json_encode([
            'agent_version'=>'1.6.0', 'want_state'=>true,
            'features'=>['directory-join-mj1','directory-task-operations-v3'],
            'identity'=>['macs'=>['02:00:00:00:00:71']],
            'directory_attempt'=>['phase'=>'verified','host_id'=>7,
                'attempt_id'=>str_repeat('b',32),'fingerprint'=>str_repeat('a',64)]
            // Intentionally omit directory_observation: this is the T01 route case.
        ]);
        $context = stream_context_create(['http'=>[
            'method'=>'POST','header'=>"Content-Type: application/json\r\nConnection: close\r\n",
            'content'=>$payload,'timeout'=>5,'ignore_errors'=>true
        ]]);
        $url = 'http://127.0.0.1:' . $port . '/agent-poll';
        $response = false;
        for ($attempt=0; $attempt<30 && false===$response; $attempt++) {
            $response = @file_get_contents($url, false, $context);
            if (false === $response) { usleep(100000); }
        }
        if (false === $response) { throw new RuntimeException('isolated Route::agentPoll HTTP request did not return'); }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $httpStatus = $http_response_header[0] ?? 'HTTP status unavailable';
            $childStdout = stream_get_contents($pipes[1]);
            $childStderr = stream_get_contents($pipes[2]);
            throw new RuntimeException(
                'Route::agentPoll returned invalid JSON; '
                . $httpStatus
                . '; response=' . substr((string)$response, 0, 8000)
                . '; child stdout=' . substr((string)$childStdout, 0, 4000)
                . '; child stderr=' . substr((string)$childStderr, 0, 4000)
            );
        }
        if ('ok' !== ($decoded['status'] ?? '')) {
            fwrite(STDERR, stream_get_contents($pipes[2]));
        }
        return $decoded;
    } finally {
        proc_terminate($process);
        fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
        proc_close($process);
    }
};

$renderHostAd = static function () use ($host) {
    $page = (new ReflectionClass(FOG\Pages\HostManagement::class))->newInstanceWithoutConstructor();
    foreach (['obj'=>$host, 'childClass'=>'Host'] as $key=>$value) {
        $property = new ReflectionProperty(FOG\Base\FOGPage::class, $key);
        $property->setAccessible(true); $property->setValue($page, $value);
    }
    $GLOBALS['node'] = 'host';
    $method = new ReflectionMethod(FOG\Pages\HostManagement::class, 'hostAD');
    $method->setAccessible(true);
    ob_start(); $method->invoke($page); return ob_get_clean();
};
$before = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$initialHtml = $renderHostAd();
$t->check('T07 initial saved fresh True renders current health independently of old history',
    false !== strpos($initialHtml, 'Joined and secure channel healthy')
    && false === strpos($initialHtml, 'name="rejoin_retry"'));
$routeResponse = $routePoll(); // Actual poll route: beforePoll -> observe -> facts/place -> plan.
if ('ok' !== ($routeResponse['status'] ?? '')) {
    fwrite(STDERR, 'T01 route fixture response: ' . json_encode($routeResponse) . "\n");
}
$afterMissing = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T01 actual Route::agentPoll returns normally for a missing current observation',
    'ok' === ($routeResponse['status'] ?? '') && array_key_exists('directory_rejoin', $routeResponse)
    && null === $routeResponse['directory_rejoin']);
$routeCounts = $pdo->query('SELECT connects,moves FROM scHoldLdapCounts WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$t->check('T01 actual poll exposes held directory state and performs no LDAP connect or move',
    true === ($routeResponse['directory_hold'] ?? null)
    && 0 === (int)($routeCounts['connects'] ?? -1) && 0 === (int)($routeCounts['moves'] ?? -1));
$t->check('T01 saved True age 30s is held when current poll omits observation', true === ($afterMissing['placement_hold'] ?? false));
$t->check('T01 missing observation preserves saved timestamp and raw True',
    $before['observed_at'] === $afterMissing['observed_at'] && 'true' === ($afterMissing['observation']['secure_channel'] ?? null));
$t->check('T01 missing observation preserves phase, attempt, target and task high-water',
    $before['phase'] === $afterMissing['phase'] && $before['attempt_id'] === $afterMissing['attempt_id']
    && $before['target'] === $afterMissing['target'] && $before['last_deploy_task_id'] === $afterMissing['last_deploy_task_id']);
$t->check('T01 missing observation performs no LDAP connect or move', 0 === ScHoldLdapStub::$connects && [] === ScHoldLdapStub::$moves);
$missingPlan = FOG\Agent\DirectoryOperation::plan($host, ['directory_attempt'=>['phase'=>'none']]);
$t->check('T01 missing observation cannot produce a new operation plan',
    null === $missingPlan || (is_array($missingPlan) && 'blocked' === ($missingPlan['phase'] ?? '')));
$t->check('T02 explicit null observation also establishes hold',
    true === $runObservationPoll(['directory_observation'=>null])['state']['placement_hold']);

$savedBeforeMismatch = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$mismatchObservation = $healthyObservation; $mismatchObservation['revision'] = str_repeat('0', 32);
$runObservationPoll(['directory_observation'=>$mismatchObservation]);
$afterMismatch = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T04 incoming revision mismatch holds placement and preserves saved observation/time',
    true === ($afterMismatch['placement_hold'] ?? false)
    && $savedBeforeMismatch['observed_at'] === $afterMismatch['observed_at']
    && $savedBeforeMismatch['observation'] === $afterMismatch['observation']);
$t->check('T04 incoming revision mismatch has no LDAP side effect',
    0 === ScHoldLdapStub::$connects && [] === ScHoldLdapStub::$moves);

// Re-establish a known verified state before the false -> true poll sequence.
$state = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$state['placement_hold'] = false; $state['observed_at'] = time()-30; $state['observation'] = $oldObservation;
$pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=7')->execute([json_encode($state)]);
$falseObservation = $healthyObservation; $falseObservation['secure_channel'] = 'false';
$placementAtBeforeHold = $pdo->query('SELECT hdPlacementAt FROM hostDirectory WHERE hdHostID=7')->fetchColumn();
$runObservationPoll(['directory_observation'=>$falseObservation]);
$afterFalse = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$falseHtml = $renderHostAd();
$t->check('T07 same DB renders fresh False with explicit Retry and preserved history',
    false !== strpos($falseHtml, 'Latest secure channel check returned False')
    && false !== strpos($falseHtml, 'name="rejoin_retry"')
    && false !== strpos($falseHtml, 'Directory operation history')
    && false === strpos($falseHtml, 'Joined and secure channel healthy'));
$t->check('T05 fresh False holds OU placement without changing verified history',
    true === $afterFalse['placement_hold'] && 'verified' === $afterFalse['phase']
    && $before['attempt_id'] === $afterFalse['attempt_id'] && $before['last_deploy_task_id'] === $afterFalse['last_deploy_task_id']);
$t->check('T05 fresh False performs no LDAP connect or move', 0 === ScHoldLdapStub::$connects && [] === ScHoldLdapStub::$moves);
$t->check('T11 observation hold does not stamp placement cooldown',
    $placementAtBeforeHold === $pdo->query('SELECT hdPlacementAt FROM hostDirectory WHERE hdHostID=7')->fetchColumn());
$falsePlanState = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$connectsBeforeFalsePlan = ScHoldLdapStub::$connects;
$movesBeforeFalsePlan = count(ScHoldLdapStub::$moves);
$falsePlanError = '';
try {
    FOG\Agent\DirectoryOperation::plan($host, ['directory_attempt'=>['phase'=>'none']]);
} catch (RuntimeException $e) {
    $falsePlanError = $e->getMessage();
}
$falsePlanStateAfter = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$falsePlanLookupConnects = ScHoldLdapStub::$connects - $connectsBeforeFalsePlan;
$t->check('T07 fresh False blocks operation plan for operator confirmation without changing state',
    'directory_recovery_confirmation_required' === $falsePlanError
    && $falsePlanState === $falsePlanStateAfter
    && 1 === $falsePlanLookupConnects // Plan validates the candidate before choosing recovery confirmation.
    && $movesBeforeFalsePlan === count(ScHoldLdapStub::$moves));
// From here the counters measure OU-placement side effects. The candidate
// lookup above is expected plan work and must not look like a placement dial.
ScHoldLdapStub::$connects = 0;
ScHoldLdapStub::$moves = [];

$taskEnd = (new DateTimeImmutable('now', FOG\Base\FOGBase::storageTimeZone()))
    ->sub(new DateInterval('PT1M'));
$taskObservation = $healthyObservation;
$taskObservation['boot']['started'] = $taskEnd->add(new DateInterval('PT30S'))
    ->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
$pdo->prepare('INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (?,?,?,?,?,?)')
    ->execute([9009,'unconsumed directory hold fixture',7,4,FOG\Items\TaskType::DEPLOY,$taskEnd->format('Y-m-d H:i:s')]);
$taskBaseState = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$taskDecision = FOG\Agent\DirectoryTask::read($host, $taskBaseState, $taskObservation);
$taskConnectsBefore = ScHoldLdapStub::$connects;
$taskMovesBefore = count(ScHoldLdapStub::$moves);
$taskHeld = $runObservationPoll(['directory_observation'=>$taskObservation])['state'];
$t->check('T12 fresh True plus an eligible unconsumed Task keeps OU placement held',
    'eligible' === ($taskDecision['status'] ?? '') && 9009 === ($taskDecision['task_id'] ?? 0)
    && true === ($taskHeld['placement_hold'] ?? false)
    && $taskBaseState['last_deploy_task_id'] === $taskHeld['last_deploy_task_id']
    && $taskBaseState['attempt_id'] === $taskHeld['attempt_id']
    && $taskConnectsBefore === ScHoldLdapStub::$connects
    && $taskMovesBefore === count(ScHoldLdapStub::$moves));
$pdo->prepare('DELETE FROM tasks WHERE taskID=?')->execute([9009]);
$pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=7')->execute([json_encode($afterFalse)]);

$probeState = $afterFalse;
$probeState['placement_hold'] = false;
$probeState['observation'] = $healthyObservation;
$probeState['observed_at'] = time();
$hdID = (int)$pdo->query('SELECT hdID FROM hostDirectory WHERE hdHostID=7')->fetchColumn();
$loadedDirectory = (new FOG\Items\HostDirectory())->set('id', $hdID)->set('hostID', 7)->set('joined', 1)
    ->set('computerDN', 'CN=WS-71,OU=Old,DC=corp,DC=example')->set('machineAccount', 'WS-71$')
    ->set('rejoinState', json_encode($probeState));
$guardCases = [
    'T01 missing authority' => [null, null],
    'T01 malformed config' => ['{', json_encode($probeState)],
    'T01 malformed state' => [json_encode($config), '{']
];
foreach (['T03 expired True' => time()-601, 'T03 future True' => time()+60] as $label => $stamp) {
    $s = $probeState; $s['observed_at'] = $stamp;
    $guardCases[$label] = [json_encode($config), json_encode($s)];
}
$s = $probeState; $s['observation']['revision'] = str_repeat('0', 32);
$guardCases['T04 saved revision mismatch'] = [json_encode($config), json_encode($s)];
foreach (['false', 'unknown'] as $sc) {
    $s = $probeState; $s['observation']['secure_channel'] = $sc;
    $guardCases['T06 saved ' . $sc . ' with incorrect clear hold'] = [json_encode($config), json_encode($s)];
}
$s = $probeState; $s['workgroup'] = true; $s['observation']['domain'] = ''; $s['observation']['netbios'] = '';
$s['observation']['secure_channel'] = 'unknown';
$guardCases['T15 Workgroup observation before Join verification'] = [json_encode($config), json_encode($s)];
foreach ($guardCases as $label => $serialized) {
    $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7')->execute($serialized);
    FOG\Agent\DirectoryPlacement::ensure($host, $loadedDirectory);
    $t->check($label . ' stops OU entry before LDAP and cooldown',
        0 === ScHoldLdapStub::$connects && [] === ScHoldLdapStub::$moves
        && $placementAtBeforeHold === $pdo->query('SELECT hdPlacementAt FROM hostDirectory WHERE hdHostID=7')->fetchColumn());
}
$unavailable = new class extends FogFakePdo {
    public $reads = 0;
    public function prepare($sql) { $this->reads++; throw new RuntimeException('synthetic settings read failure'); }
};
$fake->pdo = $unavailable;
try { FOG\Agent\DirectoryPlacement::ensure($host, $loadedDirectory); } finally { $fake->pdo = $pdo; }
$t->check('T01 settings re-read exception is actually reached and fails closed',
    1 === $unavailable->reads && 0 === ScHoldLdapStub::$connects && [] === ScHoldLdapStub::$moves);
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7')
    ->execute([json_encode($config), json_encode($afterFalse)]);

$runObservationPoll(['directory_observation'=>$healthyObservation, 'directory_attempt'=>[
    'phase'=>'unknown', 'host_id'=>7, 'fingerprint'=>str_repeat('a',64), 'attempt_id'=>str_repeat('b',32)
]]);
$localUnknown = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T12 fresh True does not erase a local unknown-operation hold',
    true === $localUnknown['placement_hold'] && $before['attempt_id'] === $localUnknown['attempt_id']
    && 0 === ScHoldLdapStub::$connects && [] === ScHoldLdapStub::$moves);

$activeState = $afterFalse; $activeState['phase'] = 'started'; $activeState['placement_hold'] = true;
$pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=7')->execute([json_encode($activeState)]);
$runObservationPoll(['directory_observation'=>$healthyObservation]);
$afterActiveTrue = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T12 fresh True preserves active-operation hold',
    true === ($afterActiveTrue['placement_hold'] ?? false) && 'started' === ($afterActiveTrue['phase'] ?? ''));
$t->check('T12 fresh True during active operation performs no LDAP action',
    0 === ScHoldLdapStub::$connects && [] === ScHoldLdapStub::$moves);
$verifiedState = $afterFalse; $verifiedState['phase'] = 'verified'; $verifiedState['placement_hold'] = true;
$pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=7')->execute([json_encode($verifiedState)]);

// A fresh healthy observation releases only the placement hold; OU drift moves once.
$runObservationPoll(['directory_observation'=>$healthyObservation]);
$afterTrue = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$truePlanState = $afterTrue;
$connectsBeforeTruePlan = ScHoldLdapStub::$connects;
$movesBeforeTruePlan = count(ScHoldLdapStub::$moves);
$truePlan = FOG\Agent\DirectoryOperation::plan($host, ['directory_attempt'=>['phase'=>'none']]);
$truePlanStateAfter = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T07 recovered fresh True has no new operation plan and preserves operation authority',
    null === $truePlan
    && $truePlanState['phase'] === $truePlanStateAfter['phase']
    && $truePlanState['attempt_id'] === $truePlanStateAfter['attempt_id']
    && $truePlanState['last_deploy_task_id'] === $truePlanStateAfter['last_deploy_task_id']
    && $connectsBeforeTruePlan === ScHoldLdapStub::$connects
    && $movesBeforeTruePlan === count(ScHoldLdapStub::$moves));
$t->check('T07 verified True -> False -> True releases hold while preserving old reason/result',
    false === $afterTrue['placement_hold'] && 'verified' === $afterTrue['phase']
    && $before['attempt_id'] === $afterTrue['attempt_id'] && $before['last_deploy_task_id'] === $afterTrue['last_deploy_task_id']
    && 'directory_recovery_confirmation_required' === $afterTrue['reason'] && 'broken' === $afterTrue['secure_channel']);
$t->check('T08 recovery uses same pinned target and moves to the intended OU exactly once',
    1 === ScHoldLdapStub::$connects && 1 === count(ScHoldLdapStub::$moves)
    && ScHoldLdapStub::guidDN($config['target']['guid']) === ScHoldLdapStub::$moves[0][0]
    && ScHoldLdapStub::guidDN(ScHoldLdapStub::$containers['managed']['guid']) === ScHoldLdapStub::$moves[0][1]
    && 'CN=WS-71' === ScHoldLdapStub::$moves[0][2]);
$mainGuid = hex2bin(str_replace('-', '', $config['target']['guid']));
$mainIntent = $pdo->prepare('SELECT dpiGeneration,dpiStatus FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=?');
$mainIntent->execute([$mainGuid, $config['target']['sid']]);
$mainIntentRow = $mainIntent->fetch(PDO::FETCH_ASSOC);
$mainIntent->closeCursor();
$t->check('T08 placement records a terminal intent generation for the pinned target',
    is_array($mainIntentRow) && (int)$mainIntentRow['dpiGeneration'] >= 1 && 'SUCCEEDED' === $mainIntentRow['dpiStatus']);
if (1 !== count(ScHoldLdapStub::$moves)) {
    fwrite(STDERR, 'T10 fixture counters: ' . json_encode([ScHoldLdapStub::$connects, ScHoldLdapStub::$moves,
        $pdo->query('SELECT hdPlacementAt,hdPlacementError,hdComputerDN FROM hostDirectory WHERE hdHostID=7')->fetch(PDO::FETCH_ASSOC)]) . "\n");
}
$runObservationPoll(['directory_observation'=>$healthyObservation]);
$mainIntent->execute([$mainGuid, $config['target']['sid']]);
$mainIntentAfterPoll = $mainIntent->fetch(PDO::FETCH_ASSOC);
$mainIntent->closeCursor();
$t->check('T10 following healthy poll does not repeat completed OU move or intent generation',
    1 === count(ScHoldLdapStub::$moves)
    && $mainIntentRow === $mainIntentAfterPoll);

$html = $renderHostAd();
$t->check('T07/T08 real HostManagement render suppresses Retry while preserving operation history',
    false !== strpos($html, 'Joined and secure channel healthy')
    && false === strpos($html, 'name="rejoin_retry"')
    && false !== strpos($html, 'directory_recovery_confirmation_required')
    && false !== strpos($html, 'broken'));

$pdo->exec("INSERT INTO hosts (hostID,hostName,hostADDomain,hostADOU,hostUseAD,hostAgentFingerprint) VALUES (8,'LEGACY-8','corp.example','OU=Managed,DC=corp,DC=example',1,'" . str_repeat('8',64) . "')");
// Valid legacy state has no protocol field. An explicit protocol=1 is invalid
// under the unchanged decoder and must not be treated as a valid legacy row.
$legacyState = ['version'=>1];
$pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdRejoinState) VALUES (8,1,?,?,?, ?,?)')
    ->execute(['domain','corp.example','CN=LEGACY-8,OU=Old,DC=corp,DC=example','LEGACY-8$',json_encode($legacyState)]);
$legacyHost = (new FOG\Items\Host())->set('id',8)->set('name','LEGACY-8')->set('ADDomain','corp.example')
    ->set('useAD',1)->set('ADOU','OU=Managed,DC=corp,DC=example')->set('agentFingerprint',str_repeat('8',64));
$legacyDirectory = FOG\Agent\DirectoryFacts::forHost(8);
$legacyTarget = [
    'guid' => '88888888-8888-8888-8888-888888888888',
    'sid' => 'S-1-5-21-8-1001',
    'account' => 'LEGACY-8$',
    'dn' => 'CN=LEGACY-8,OU=Old,DC=corp,DC=example'
];
ScHoldLdapStub::$records['legacy-8'] = ['status'=>'found', 'record'=>$legacyTarget];
$t->check('T17 fixture resolves a joined legacy DB row with actual OU drift',
    8 === (int)$legacyDirectory->get('hostID') && !empty($legacyDirectory->get('joined'))
    && 'CN=LEGACY-8,OU=Old,DC=corp,DC=example' === $legacyDirectory->get('computerDN')
    && $legacyDirectory->ouDrifted('OU=Managed,DC=corp,DC=example'));
$legacyConnectsBefore = ScHoldLdapStub::$connects;
$legacyMovesBefore = count(ScHoldLdapStub::$moves);
FOG\Agent\DirectoryPlacement::ensure($legacyHost, $legacyDirectory);
$legacyPlacement = $pdo->query('SELECT hdComputerDN,hdPlacementError FROM hostDirectory WHERE hdHostID=8')->fetch(PDO::FETCH_ASSOC);
$t->check('T17 protocol1 legacy placement remains available without MJ-1 observation settings',
    $legacyConnectsBefore + 1 === ScHoldLdapStub::$connects
    && $legacyMovesBefore + 1 === count(ScHoldLdapStub::$moves)
    && ScHoldLdapStub::guidDN($legacyTarget['guid']) === ScHoldLdapStub::$moves[$legacyMovesBefore][0]
    && ScHoldLdapStub::guidDN(ScHoldLdapStub::$containers['managed']['guid']) === ScHoldLdapStub::$moves[$legacyMovesBefore][1]
    && 'CN=LEGACY-8' === ScHoldLdapStub::$moves[$legacyMovesBefore][2]
    && 'CN=LEGACY-8,OU=Managed,DC=corp,DC=example' === ($legacyPlacement['hdComputerDN'] ?? null));
$legacyIntent = $pdo->prepare('SELECT dpiGeneration,dpiStatus FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=?');
$legacyIntent->execute([hex2bin(str_replace('-', '', $legacyTarget['guid'])), $legacyTarget['sid']]);
$legacyIntentRow = $legacyIntent->fetch(PDO::FETCH_ASSOC);
$legacyIntent->closeCursor();
$t->check('T17 legacy placement stores its own terminal target generation',
    is_array($legacyIntentRow) && (int)$legacyIntentRow['dpiGeneration'] >= 1 && 'SUCCEEDED' === $legacyIntentRow['dpiStatus']);
if ($legacyConnectsBefore + 1 !== ScHoldLdapStub::$connects
    || $legacyMovesBefore + 1 !== count(ScHoldLdapStub::$moves)) {
    fwrite(STDERR, 'T17 placement diagnostics: ' . json_encode([
        'connect_before'=>$legacyConnectsBefore, 'connect_after'=>ScHoldLdapStub::$connects,
        'moves_before'=>$legacyMovesBefore, 'moves_after'=>ScHoldLdapStub::$moves,
        'row'=>$legacyPlacement
    ]) . "\n");
}

// A verified protocol-2 Target GUID/SID mismatch must not move the OU object.
$currentState = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$pdo->prepare('UPDATE hostDirectory SET hdComputerDN=?,hdPlacementAt=NULL,hdPlacementError=? WHERE hdHostID=7')
    ->execute(['CN=WS-71,OU=Old,DC=corp,DC=example','']);
$mismatchDirectory = FOG\Agent\DirectoryFacts::forHost(7);
$t->check('T10 target-mismatch fixture has fresh verified authority and an uncooldowned OU drift',
    'verified' === ($currentState['phase'] ?? '') && empty($currentState['placement_hold'])
    && FOG\Agent\DirectoryOperation::placementHealthy($host,
        FOG\Agent\DirectoryRejoin::settings($host)['config'], $currentState)
    && $mismatchDirectory->ouDrifted('OU=Managed,DC=corp,DC=example')
    && '' === (string)$mismatchDirectory->get('placementAt'));
$wrongTarget = $targetRecord;
$wrongTarget['record']['guid'] = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
$wrongTarget['record']['sid'] = 'S-1-5-21-7-2002';
ScHoldLdapStub::$records['ws-71'] = $wrongTarget;
$targetMismatchConnectsBefore = ScHoldLdapStub::$connects;
$targetMismatchMovesBefore = count(ScHoldLdapStub::$moves);
FOG\Agent\DirectoryPlacement::ensure($host, $mismatchDirectory);
$targetMismatchRow = $pdo->query('SELECT hdPlacementError,hdComputerDN FROM hostDirectory WHERE hdHostID=7')->fetch(PDO::FETCH_ASSOC);
$t->check('T10 verified Target GUID/SID mismatch performs no OU move',
    $targetMismatchConnectsBefore + 1 === ScHoldLdapStub::$connects
    && $targetMismatchMovesBefore === count(ScHoldLdapStub::$moves)
    && 'rejoin_target_changed' === ($targetMismatchRow['hdPlacementError'] ?? null)
    && 'CN=WS-71,OU=Old,DC=corp,DC=example' === ($targetMismatchRow['hdComputerDN'] ?? null));
$t->finish();
