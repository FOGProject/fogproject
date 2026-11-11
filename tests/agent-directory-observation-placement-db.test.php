<?php
/**
 * T01-T07, T11 and T12: the observation and placement-hold flow against a real
 * server. A missing, stale, mismatched or unhealthy observation holds OU
 * placement (no directory connect, no move, no cooldown stamp); a fresh healthy
 * one releases the hold and nothing else; an eligible deploy task or an active
 * or locally unknown operation keeps it held. Includes the real
 * Route::agentPoll() over HTTP for the missing-observation case. The directory
 * is a stub that counts connects and moves.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-observation-placement-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory observation placement DB: FOG_TEST_DSN not set\n";
    exit(0);
}

/** A directory that finds the host's account and counts connects and moves. */
class ObsPlacementLdap
{
    public static $connects = 0;
    public static $moves = [];
    public static $records = [];
    public function connect($uri, $bindDn, $password, $caFile = '') { self::$connects++; return true; }
    public function domainContext($domain)
    {
        return ['status' => 'found', 'base_dn' => 'DC=corp,DC=example', 'dc' => 'dc1.corp.example'];
    }
    public function computerRecord($baseDn, $account)
    {
        return self::$records[strtolower(rtrim((string)$account, '$'))] ?? ['status' => 'error', 'reason' => 'fixture_record_unspecified'];
    }
    public function containerIdentity($dn) { return ['status' => 'error', 'reason' => 'fixture_destination_unspecified']; }
    public function moveTo($dn, $parentDn) { self::$moves[] = [$dn, $parentDn]; return true; }
    public function error() { return 'obs_placement_fixture_error'; }
    public function close() { }
}
class_alias(ObsPlacementLdap::class, 'FOG\\Net\\FOGLdap', false);

/** A PDO that can be told to fail its next settings read. */
class ObsPlacementPdo extends \PDO
{
    public $failReads = false;
    public $failedReads = 0;
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        if ($this->failReads && false !== stripos((string)$query, 'SELECT hdRejoinConfig, hdRejoinState FROM hostDirectory')) {
            $this->failedReads++;
            throw new \RuntimeException('synthetic settings read failure');
        }
        return parent::prepare($query, $options);
    }
}

$directoryDbName = 'agent-directory-observation-placement-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
$manifest = $GLOBALS['manifest'];
$t = new FogChecks();

$user = getenv('FOG_TEST_USER');
$pass = getenv('FOG_TEST_PASS');
$db = new ObsPlacementPdo(
    directoryTestDsn(),
    false === $user ? 'root' : $user,
    false === $pass ? '' : $pass,
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]
);
directoryTestBindPdodb($db);

// Loading a Host or a HostDirectory joins many tables; create what is missing.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table) {
    $pdo->exec($table['create']);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$pdo->exec('DELETE FROM tasks');
$pdo->exec('DELETE FROM hostMAC');
$pdo->exec("INSERT INTO hostMAC (hmHostID,hmMAC,hmPrimary,hmPending) VALUES (7,'02:00:00:00:00:71',1,0)");
// The poll route reads the client module switches from the settings table.
foreach (['AUTOLOGOFF', 'HOSTNAMECHANGER', 'HOSTREGISTER', 'POWERMANAGEMENT', 'PRINTERMANAGER', 'SNAPIN', 'SOFTWARE', 'TASKREBOOT', 'USERTRACKER'] as $module) {
    $pdo->exec("REPLACE INTO globalSettings (settingKey,settingValue) VALUES ('FOG_CLIENT_" . $module . "_ENABLED','0')");
}
$pdo->exec('CREATE TABLE IF NOT EXISTS scHoldLdapCounts (id int NOT NULL PRIMARY KEY, connects int NOT NULL, moves int NOT NULL) ENGINE=InnoDB');
$pdo->exec('REPLACE INTO scHoldLdapCounts (id,connects,moves) VALUES (1,0,0)');
$fingerprint = str_repeat('a', 64);
$pdo->exec("UPDATE hosts SET hostName='WS-71',hostADDomain='corp.example',hostADOU='OU=Managed,DC=corp,DC=example',hostUseAD=1,hostADUser='svc',hostADPass='pw',hostAgentFingerprint='" . $fingerprint . "' WHERE hostID=7");
$host = (new FOG\Items\Host())->set('id', 7)->set('name', 'WS-71')->set('ADDomain', 'corp.example')
    ->set('useAD', 1)->set('ADUser', 'svc')->set('ADPass', 'pw')->set('ADOU', 'OU=Managed,DC=corp,DC=example')
    ->set('agentFingerprint', $fingerprint);

$config = ['version' => 1, 'protocol' => 2, 'allow_create' => true, 'name' => 'WS-71', 'domain' => 'corp.example',
    'config_revision' => str_repeat('c', 32),
    'target' => ['guid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'sid' => 'S-1-5-21-7-1001', 'account' => 'WS-71$',
        'dn' => 'CN=WS-71,OU=Old,DC=corp,DC=example']];
$dc = 'dc1.corp.example';
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
$healthyObservation = [
    'revision' => $revision, 'dc' => $dc, 'dc_reachable' => true, 'current_name' => 'WS-71', 'pending_name' => 'WS-71',
    'domain' => 'corp.example', 'netbios' => 'CORP', 'role' => 'workstation', 'secure_channel' => 'true',
    'boot' => ['marker' => 'boot-71', 'started' => gmdate(DATE_ATOM)]
];
$oldObservation = $healthyObservation;
$state = [
    'version' => 1, 'protocol' => 2, 'operation' => 'rebind', 'mode' => 'existing', 'target' => $config['target'],
    'host_id' => 7, 'name' => 'WS-71', 'domain' => 'corp.example', 'dc' => $dc, 'check_dc' => $dc,
    'phase' => 'verified', 'attempt_id' => str_repeat('b', 32), 'config_revision' => $revision,
    'fingerprint' => $fingerprint, 'expected_current' => 'WS-71', 'expected_pending' => 'WS-71',
    'observed_at' => time() - 30, 'last_deploy_task_id' => 654, 'trigger_task_id' => 654,
    'placement_hold' => false, 'reason' => 'directory_recovery_confirmation_required', 'secure_channel' => 'broken',
    'native_status' => 0, 'observation' => $oldObservation
];
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=?,hdJoined=1,hdKind=?,hdDomain=?,hdComputerDN=?,hdMachineAccount=? WHERE hdHostID=7')
    ->execute([json_encode($config), json_encode($state), 'domain', 'corp.example', 'CN=WS-71,OU=Old,DC=corp,DC=example', 'WS-71$']);
ObsPlacementLdap::$records = ['ws-71' => ['status' => 'found', 'record' => [
    'guid' => $config['target']['guid'], 'sid' => $config['target']['sid'], 'account' => 'WS-71$', 'dn' => $config['target']['dn']]]];

$cache = [];
foreach ([
    'FOG_AGENT_INVENTORY_ENABLED' => '1', 'FOG_HOST_IDENTIFY_SMBIOS' => 'off', 'FOG_DIRECTORY_PLACEMENT_ENABLED' => '1',
    'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.example', 'FOG_DIRECTORY_BIND_DN' => 'cn=svc',
    'FOG_DIRECTORY_BIND_PASSWORD' => 'pw', 'FOG_DIRECTORY_BASE_DN' => 'DC=corp,DC=example', 'FOG_DIRECTORY_CA_CERT' => ''
] as $key => $value) {
    $cache[$key] = ['value' => $value, 'ts' => time()];
}
FogTestHarness::setStatic('FOGBase', '_settingsCache', $cache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);

/** One poll's server work: store the observation, then the placement step. */
$runObservationPoll = static function ($body) use ($host) {
    FOG\Agent\DirectoryOperation::observe($host, array_replace(['directory_attempt' => ['phase' => 'none']], $body));
    FOG\Agent\DirectoryFacts::place($host);
    return FOG\Agent\DirectoryRejoin::settings($host);
};
$noDirectory = static function () {
    return 0 === ObsPlacementLdap::$connects && [] === ObsPlacementLdap::$moves;
};

/** POST one request through the real Route::agentPoll() on a php -S router. */
$routePoll = static function () {
    $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (false === $probe) {
        throw new \RuntimeException('cannot reserve a local port: ' . $error);
    }
    $address = stream_socket_get_name($probe, false);
    fclose($probe);
    $port = (int)substr(strrchr((string)$address, ':'), 1);
    $log = tempnam(sys_get_temp_dir(), 'fog-poll-route-');
    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/fixtures/agent-directory-poll-route.php'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
        $pipes,
        dirname(__DIR__)
    );
    if (!is_resource($process)) {
        throw new \RuntimeException('cannot start the poll route server');
    }
    try {
        $payload = json_encode([
            'agent_version' => '1.6.0', 'want_state' => true,
            'features' => ['directory-join-target-v1', 'directory-task-operations-v1'],
            'identity' => ['macs' => ['02:00:00:00:00:71']],
            // No directory_observation: the missing-observation case.
            'directory_attempt' => ['phase' => 'verified', 'host_id' => 7,
                'attempt_id' => str_repeat('b', 32), 'fingerprint' => str_repeat('a', 64)]
        ]);
        $context = stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Content-Type: application/json\r\nConnection: close\r\n",
            'content' => $payload, 'timeout' => 10, 'ignore_errors' => true
        ]]);
        $response = false;
        for ($attempt = 0; $attempt < 30 && false === $response; $attempt++) {
            $response = @file_get_contents('http://127.0.0.1:' . $port . '/agent-poll', false, $context);
            if (false === $response) {
                usleep(100000);
            }
        }
        $decoded = false === $response ? null : json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Route::agentPoll did not answer JSON: ' . substr((string)$response, 0, 2000)
                . ' log=' . substr((string)file_get_contents($log), 0, 2000));
        }
        return $decoded;
    } finally {
        proc_terminate($process);
        proc_close($process);
        @unlink($log);
    }
};

$renderHostAd = static function () use ($host) {
    $page = (new \ReflectionClass(FOG\Pages\HostManagement::class))->newInstanceWithoutConstructor();
    foreach (['obj' => $host, 'childClass' => 'Host'] as $key => $value) {
        $property = new \ReflectionProperty(FOG\Base\FOGPage::class, $key);
        $property->setAccessible(true);
        $property->setValue($page, $value);
    }
    $knownEvents = new \ReflectionProperty(FOG\Base\HookManager::class, 'knownEvents');
    $knownEvents->setAccessible(true);
    $knownEvents->setValue(null, ['HOST_EDIT_AD_FIELDS' => true]);
    $GLOBALS['node'] = 'host';
    $method = new \ReflectionMethod(FOG\Pages\HostManagement::class, 'hostAD');
    $method->setAccessible(true);
    ob_start();
    $method->invoke($page);
    return ob_get_clean();
};

$before = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$initialHtml = $renderHostAd();
$t->check('T07 initial saved fresh True renders current health independently of old history',
    false !== strpos($initialHtml, 'Joined and secure channel healthy')
    && false === strpos($initialHtml, 'name="rejoin_retry"'));

// T01 over HTTP: the real poll route with no current observation.
$routeResponse = $routePoll();
$afterMissing = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T01 Route::agentPoll returns normally for a missing current observation',
    'ok' === ($routeResponse['status'] ?? '') && array_key_exists('directory_rejoin', $routeResponse)
    && null === $routeResponse['directory_rejoin']);
$counts = $pdo->query('SELECT connects,moves FROM scHoldLdapCounts WHERE id=1')->fetch(\PDO::FETCH_ASSOC);
$t->check('T01 the poll answer says directory_hold and the poll made no directory connect or move',
    true === ($routeResponse['directory_hold'] ?? null)
    && 0 === (int)($counts['connects'] ?? -1) && 0 === (int)($counts['moves'] ?? -1));
$t->check('T01 saved True aged 30 s is held when the poll omits the observation', true === ($afterMissing['placement_hold'] ?? false));
$t->check('T01 a missing observation keeps the saved timestamp and the raw True',
    $before['observed_at'] === $afterMissing['observed_at'] && 'true' === ($afterMissing['observation']['secure_channel'] ?? null));
$t->check('T01 a missing observation keeps phase, attempt, target and task high-water',
    $before['phase'] === $afterMissing['phase'] && $before['attempt_id'] === $afterMissing['attempt_id']
    && $before['target'] === $afterMissing['target'] && $before['last_deploy_task_id'] === $afterMissing['last_deploy_task_id']);
$missingPlan = FOG\Agent\DirectoryOperation::plan($host, ['directory_attempt' => ['phase' => 'none']]);
$t->check('T01 a missing observation cannot produce a new operation plan',
    null === $missingPlan || 'blocked' === ($missingPlan['phase'] ?? ''));
$t->check('T02 an explicit null observation also establishes the hold',
    true === $runObservationPoll(['directory_observation' => null])['state']['placement_hold']);
$t->check('T01 and T02 made no directory connect or move', $noDirectory());

// T04: an incoming revision that is not the current one.
$savedBeforeMismatch = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$mismatchObservation = $healthyObservation;
$mismatchObservation['revision'] = str_repeat('0', 32);
$runObservationPoll(['directory_observation' => $mismatchObservation]);
$afterMismatch = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T04 an incoming revision mismatch holds placement and keeps the saved observation and time',
    true === ($afterMismatch['placement_hold'] ?? false)
    && $savedBeforeMismatch['observed_at'] === $afterMismatch['observed_at']
    && $savedBeforeMismatch['observation'] === $afterMismatch['observation']);
$t->check('T04 an incoming revision mismatch has no directory side effect', $noDirectory());

// T05, T07, T11: fresh False.
$state = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$state['placement_hold'] = false;
$state['observed_at'] = time() - 30;
$state['observation'] = $oldObservation;
$pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=7')->execute([json_encode($state)]);
$falseObservation = $healthyObservation;
$falseObservation['secure_channel'] = 'false';
$placementAtBeforeHold = $pdo->query('SELECT hdPlacementAt FROM hostDirectory WHERE hdHostID=7')->fetchColumn();
$runObservationPoll(['directory_observation' => $falseObservation]);
$afterFalse = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$falseHtml = $renderHostAd();
$t->check('T07 fresh False renders with an explicit Retry and the preserved history',
    false !== strpos($falseHtml, 'Latest secure channel check returned False')
    && false !== strpos($falseHtml, 'name="rejoin_retry"')
    && false !== strpos($falseHtml, 'Directory operation history')
    && false === strpos($falseHtml, 'Joined and secure channel healthy'));
$t->check('T05 fresh False holds OU placement without changing the verified history',
    true === $afterFalse['placement_hold'] && 'verified' === $afterFalse['phase']
    && $before['attempt_id'] === $afterFalse['attempt_id'] && $before['last_deploy_task_id'] === $afterFalse['last_deploy_task_id']);
$t->check('T05 fresh False made no directory connect or move', $noDirectory());
$t->check('T11 an observation hold does not stamp the placement cooldown',
    $placementAtBeforeHold === $pdo->query('SELECT hdPlacementAt FROM hostDirectory WHERE hdHostID=7')->fetchColumn());
$falsePlanState = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$falsePlanError = '';
try {
    FOG\Agent\DirectoryOperation::plan($host, ['directory_attempt' => ['phase' => 'none']]);
} catch (\RuntimeException $e) {
    $falsePlanError = $e->getMessage();
}
$t->check('T07 fresh False blocks the operation plan for operator confirmation and changes no state',
    'directory_recovery_confirmation_required' === $falsePlanError
    && $falsePlanState === FOG\Agent\DirectoryRejoin::settings($host)['state']);
// From here the counters measure placement. The candidate lookup above is
// plan work, not a placement dial.
ObsPlacementLdap::$connects = 0;
ObsPlacementLdap::$moves = [];

// T12: fresh True plus an eligible, unconsumed deploy task keeps the hold.
$taskEnd = (new \DateTimeImmutable('now', FOG\Base\FOGBase::storageTimeZone()))->sub(new \DateInterval('PT1M'));
$taskObservation = $healthyObservation;
$taskObservation['boot']['started'] = $taskEnd->add(new \DateInterval('PT30S'))->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
$pdo->prepare('INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (?,?,?,?,?,?)')
    ->execute([9009, 'unconsumed directory hold fixture', 7, 4, FOG\Items\TaskType::DEPLOY, $taskEnd->format('Y-m-d H:i:s')]);
$taskBaseState = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$taskDecision = FOG\Agent\DirectoryTask::read($host, $taskBaseState, $taskObservation);
$taskHeld = $runObservationPoll(['directory_observation' => $taskObservation])['state'];
$t->check('T12 fresh True plus an eligible unconsumed Task keeps OU placement held',
    'eligible' === ($taskDecision['status'] ?? '') && 9009 === ($taskDecision['task_id'] ?? 0)
    && true === ($taskHeld['placement_hold'] ?? false)
    && $taskBaseState['last_deploy_task_id'] === $taskHeld['last_deploy_task_id']
    && $taskBaseState['attempt_id'] === $taskHeld['attempt_id']
    && $noDirectory());
$pdo->exec('DELETE FROM tasks WHERE taskID=9009');
$pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=7')->execute([json_encode($afterFalse)]);

// The hold gate in DirectoryPlacement::ensure(): each unusable authority stops
// OU entry before the directory is dialed and before the cooldown is stamped.
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
foreach (['T03 expired True' => time() - 601, 'T03 future True' => time() + 60] as $label => $stamp) {
    $s = $probeState;
    $s['observed_at'] = $stamp;
    $guardCases[$label] = [json_encode($config), json_encode($s)];
}
$s = $probeState;
$s['observation']['revision'] = str_repeat('0', 32);
$guardCases['T04 saved revision mismatch'] = [json_encode($config), json_encode($s)];
foreach (['false', 'unknown'] as $sc) {
    $s = $probeState;
    $s['observation']['secure_channel'] = $sc;
    $guardCases['T06 saved ' . $sc . ' with the hold incorrectly clear'] = [json_encode($config), json_encode($s)];
}
$s = $probeState;
$s['workgroup'] = true;
$s['observation']['domain'] = '';
$s['observation']['netbios'] = '';
$s['observation']['secure_channel'] = 'unknown';
$guardCases['T06 workgroup observation before the join is verified'] = [json_encode($config), json_encode($s)];
foreach ($guardCases as $label => $serialized) {
    $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7')->execute($serialized);
    FOG\Agent\DirectoryPlacement::ensure($host, $loadedDirectory);
    $t->check($label . ' stops OU entry before the directory and the cooldown',
        $noDirectory()
        && $placementAtBeforeHold === $pdo->query('SELECT hdPlacementAt FROM hostDirectory WHERE hdHostID=7')->fetchColumn());
}
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7')
    ->execute([json_encode($config), json_encode($probeState)]);
$db->failReads = true;
try {
    FOG\Agent\DirectoryPlacement::ensure($host, $loadedDirectory);
} finally {
    $db->failReads = false;
}
$t->check('T01 an exception on the settings re-read is reached and fails closed',
    1 === $db->failedReads && $noDirectory());
$pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7')
    ->execute([json_encode($config), json_encode($afterFalse)]);

// T12: a fresh True does not erase a local unknown-operation hold, nor an active operation's.
$runObservationPoll(['directory_observation' => $healthyObservation, 'directory_attempt' => [
    'phase' => 'unknown', 'host_id' => 7, 'fingerprint' => $fingerprint, 'attempt_id' => str_repeat('b', 32)
]]);
$localUnknown = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T12 fresh True does not erase a local unknown-operation hold',
    true === $localUnknown['placement_hold'] && $before['attempt_id'] === $localUnknown['attempt_id'] && $noDirectory());
$activeState = $afterFalse;
$activeState['phase'] = 'started';
$activeState['placement_hold'] = true;
$pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=7')->execute([json_encode($activeState)]);
$runObservationPoll(['directory_observation' => $healthyObservation]);
$afterActiveTrue = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T12 fresh True preserves the hold of an active operation',
    true === ($afterActiveTrue['placement_hold'] ?? false) && 'started' === ($afterActiveTrue['phase'] ?? ''));
$t->check('T12 fresh True during an active operation made no directory connect or move', $noDirectory());

// T07: verified True after False releases the hold and nothing else.
$verifiedState = $afterFalse;
$verifiedState['phase'] = 'verified';
$verifiedState['placement_hold'] = true;
$pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=7')->execute([json_encode($verifiedState)]);
$runObservationPoll(['directory_observation' => $healthyObservation]);
$afterTrue = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$truePlan = FOG\Agent\DirectoryOperation::plan($host, ['directory_attempt' => ['phase' => 'none']]);
$afterTruePlan = FOG\Agent\DirectoryRejoin::settings($host)['state'];
$t->check('T07 recovered fresh True has no new operation plan and keeps the operation authority',
    null === $truePlan
    && $afterTrue['phase'] === $afterTruePlan['phase'] && $afterTrue['attempt_id'] === $afterTruePlan['attempt_id']
    && $afterTrue['last_deploy_task_id'] === $afterTruePlan['last_deploy_task_id']);
$t->check('T07 verified True, then False, then True releases the hold and keeps the old reason and result',
    false === $afterTrue['placement_hold'] && 'verified' === $afterTrue['phase']
    && $before['attempt_id'] === $afterTrue['attempt_id'] && $before['last_deploy_task_id'] === $afterTrue['last_deploy_task_id']
    && 'directory_recovery_confirmation_required' === $afterTrue['reason'] && 'broken' === $afterTrue['secure_channel']);
$html = $renderHostAd();
$t->check('T07 the real host render suppresses Retry and keeps the operation history',
    false !== strpos($html, 'Joined and secure channel healthy')
    && false === strpos($html, 'name="rejoin_retry"')
    && false !== strpos($html, 'directory_recovery_confirmation_required')
    && false !== strpos($html, 'broken'));

$t->finish();
