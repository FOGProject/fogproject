<?php
/**
 * Scratch-DB flow fixture for the real target-v2 plan/start/report methods.
 * No HTTP route, production seam, or native/LDAP connection is used.
 *
 * Requires MJ1_DB_DSN, MJ1_DB_PASSWORD, and MJ1_DB_ALLOW_RESET=1. The
 * selected database must be named with the fog_mj1 prefix. JSON input may
 * select rebind_create, rebind_existing, rename_configured, self_restore,
 * reassigned_rename, task_join, or healthy_self_restore and supply typed LDAP context/records as needed.
 */
error_reporting(E_ALL & ~E_DEPRECATED);

if (getenv('MJ1_DB_DSN') === false || 0 !== strpos(getenv('MJ1_DB_DSN'), 'mysql:')
    || getenv('MJ1_DB_ALLOW_RESET') !== '1') {
    fwrite(STDERR, "scratch database and explicit reset opt-in required\n");
    exit(2);
}

$db = new \PDO(
    getenv('MJ1_DB_DSN'),
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    fwrite(STDERR, "scratch database name must begin with fog_mj1\n");
    exit(2);
}

/** Controlled, typed LDAP answers. Production FOGLdap is never loaded. */
class DirectoryOperationFlowLdapStub
{
    public static $context = [];
    public static $records = [];
    private $connected = false;

    public function connect($uri, $bindDn, $password, $caFile = '')
    {
        $this->connected = true;
        return true;
    }

    public function domainContext($wantedDnsDomain)
    {
        if (!$this->connected || strcasecmp((string)(self::$context['domain'] ?? ''), (string)$wantedDnsDomain)) {
            return ['status' => 'error', 'reason' => 'fixture_domain_mismatch'];
        }
        return [
            'status' => 'found',
            'base_dn' => (string)(self::$context['base_dn'] ?? ''),
            'dc' => (string)(self::$context['dc'] ?? '')
        ];
    }

    public function computerRecord($baseDn, $account)
    {
        if (!$this->connected || $baseDn !== (string)(self::$context['base_dn'] ?? '')) {
            return ['status' => 'error', 'reason' => 'fixture_scope_mismatch'];
        }
        $key = strtolower(rtrim((string)$account, '$'));
        return self::$records[$key] ?? ['status' => 'error', 'reason' => 'fixture_record_unspecified'];
    }

    public function close()
    {
        $this->connected = false;
    }
}

class_alias(DirectoryOperationFlowLdapStub::class, 'FOG\\Net\\FOGLdap', false);

require dirname(__DIR__) . '/lib/fog-test-harness.php';
FogTestHarness::boot('directory-operation-flow');
$fake = FogTestHarness::fakeDb();
$fake->pdo = $db;

// Recreate only the manifest tables, and only after the scratch DB guard.
$manifest = require dirname(__DIR__, 2) . '/packages/web/commons/schema-expected.php';
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (array_reverse(array_keys($manifest['tables'])) as $table) {
    $db->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
}
foreach ($manifest['tables'] as $table) {
    $db->exec($table['create']);
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');

$input = json_decode(stream_get_contents(STDIN, 32768), true);
if (!is_array($input) || !in_array($input['action'] ?? '', [
    'rebind_create', 'rebind_existing', 'rename_configured', 'self_restore',
    'reassigned_rename', 'task_join', 'healthy_self_restore'
], true)) {
    fwrite(STDERR, "fixture action must be a known directory operation flow\n");
    exit(2);
}
$action = $input['action'];
$hostId = 7001;
$name = (string)($input['name'] ?? ('reassigned_rename' === $action ? 'C' : 'B'));
$workgroupTaskJoin = 'task_join' === $action;
$taskBound = in_array($action, ['rebind_create', 'rebind_existing', 'self_restore', 'healthy_self_restore', 'task_join'], true);
$domain = (string)($input['domain'] ?? 'corp.example');
$dc = 'dc1.corp.example';
$fingerprint = str_repeat('f', 64);
$renameConfigured = 'rename_configured' === $action;
$targetRecord = $input['target_record'] ?? [
    'guid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
    'sid' => 'S-1-5-21-7-1001',
    'dn' => 'CN=' . ($renameConfigured ? 'A' : $name) . ',OU=Computers,DC=corp,DC=example',
    'account' => ($renameConfigured ? 'A' : $name) . '$',
    'dns' => strtolower($renameConfigured ? 'A' : $name) . '.corp.example',
    'uac' => 4096,
    'pwd_last_set' => '0',
    'spns' => []
];

DirectoryOperationFlowLdapStub::$context = $input['ldap_context'] ?? [
    'domain' => $domain,
    'base_dn' => 'DC=corp,DC=example',
    'dc' => $dc
];
$recordKey = strtolower(rtrim($name, '$'));
$ldapRecords = $input['ldap_records'] ?? [];
if (!array_key_exists($recordKey, $ldapRecords)) {
    $ldapRecords[$recordKey] = in_array($action, ['rebind_existing', 'self_restore', 'healthy_self_restore'], true)
        ? ['status' => 'found', 'record' => $targetRecord]
        : ['status' => 'absent'];
}
if ($renameConfigured && !array_key_exists('a', $ldapRecords)) {
    $ldapRecords['a'] = ['status' => 'found', 'record' => $targetRecord];
}
DirectoryOperationFlowLdapStub::$records = $ldapRecords;

$settings = [
    'FOG_AGENT_INVENTORY_ENABLED' => '1',
    'FOG_DIRECTORY_LDAP_URI' => 'task_join' === $action
        ? '' : 'ldaps://dc1.corp.example',
    'FOG_DIRECTORY_BIND_DN' => '',
    'FOG_DIRECTORY_BIND_PASSWORD' => '',
    'FOG_DIRECTORY_CA_CERT' => '',
    'FOG_DIRECTORY_BASE_DN' => 'DC=corp,DC=example'
];
$settingsCache = [];
foreach ($settings as $key => $value) {
    $settingsCache[$key] = ['value' => $value];
}
FogTestHarness::setStatic('FOGBase', '_settingsCache', $settingsCache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);

$insertHost = $db->prepare('INSERT INTO hosts (hostID,hostName,hostADDomain,hostUseAD,hostADUser,hostADPass,hostAgentFingerprint) VALUES (?,?,?,?,?,?,?)');
$insertHost->execute([$hostId, $name, $domain, 1, 'svc-join', 'fixture-password', $fingerprint]);
$config = [
    'version' => 1,
    'protocol' => 2,
    'allow_create' => true,
    'name' => $name,
    'domain' => strtolower($domain),
    'config_revision' => str_repeat('c', 32)
];
if (in_array($action, ['rebind_existing', 'self_restore', 'healthy_self_restore'], true)) {
    $config['target'] = $targetRecord;
}
$host = (new FOG\Items\Host())
    ->set('id', $hostId)
    ->set('name', $name)
    ->set('ADDomain', $domain)
    ->set('useAD', 1)
    ->set('ADUser', 'svc-join')
    ->set('ADPass', 'fixture-password')
    ->set('ADOU', '')
    ->set('enforce', false)
    ->set('agentFingerprint', $fingerprint);
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, $dc);
$currentName = in_array($action, ['self_restore', 'healthy_self_restore', 'task_join'], true) ? $name : 'A';
$secureChannel = 'task_join' === $action ? 'unknown'
    : (in_array($action, ['self_restore', 'rename_configured'], true) ? 'false' : 'true');
$bootStarted = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
$observationDomain = $workgroupTaskJoin ? '' : $domain;
$observationNetbios = $workgroupTaskJoin ? '' : 'CORP';
$observation = [
    'revision' => $revision,
    'dc' => $dc,
    'dc_reachable' => true,
    'current_name' => $currentName,
    'pending_name' => $currentName,
    'domain' => $observationDomain,
    'netbios' => $observationNetbios,
    'role' => 'workstation',
    'secure_channel' => $secureChannel,
    'boot' => ['marker' => 'fixture-boot', 'started' => $bootStarted->format(DATE_ATOM)]
];
$state = [
    'version' => 1,
    'protocol' => 2,
    'phase' => '',
    'name' => $name,
    'domain' => strtolower($domain),
    'check_dc' => $dc,
    'placement_hold' => true,
    'observation' => $observation,
    'observed_at' => time(),
    'last_deploy_task_id' => 0
];
$insertDirectory = $db->prepare('INSERT INTO hostDirectory (hdHostID,hdRejoinConfig,hdRejoinState) VALUES (?,?,?)');
$insertDirectory->execute([$hostId, json_encode($config), json_encode($state)]);
if ($taskBound) {
    $storageTZ = FOG\Base\FOGBase::storageTimeZone();
    $taskEnded = (new \DateTimeImmutable('now', $storageTZ))->sub(new \DateInterval('PT2M'));
    $bootStarted = $taskEnded->add(new \DateInterval('PT1M'));
    $observation['boot']['started'] = $bootStarted->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
    $state['observation'] = $observation;
    $updateDirectory = $db->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=?');
    $updateDirectory->execute([json_encode($state), $hostId]);
    $insertTask = $db->prepare('INSERT INTO tasks (taskID,taskName,taskHostID,taskStateID,taskTypeID,taskStateChangedTime) VALUES (?,?,?,?,?,?)');
    $insertTask->execute([500, 'directory deploy fixture', $hostId, 4, FOG\Items\TaskType::DEPLOY, $taskEnded->format('Y-m-d H:i:s')]);
}
if ($workgroupTaskJoin) {
    FOG\Agent\DirectoryOperation::observe($host, [
        'directory' => ['joined' => false, 'kind' => 'workgroup'],
        'directory_observation' => $observation
    ]);
}
if ('reassigned_rename' === $action) {
    $insertPeer = $db->prepare('INSERT INTO hosts (hostID,hostName,hostADDomain,hostUseAD,hostADUser,hostADPass,hostAgentFingerprint) VALUES (?,?,?,?,?,?,?)');
    $insertPeer->execute([7002, 'A', $domain, 1, 'svc-join', 'fixture-password', str_repeat('e', 64)]);
}

try {
    $plan = FOG\Agent\DirectoryOperation::plan($host);
    if (!is_array($plan) || 'ready' !== ($plan['phase'] ?? '')) {
        throw new \RuntimeException('fixture_plan_not_ready');
    }
    $expectedOperation = 'task_join' === $action
        ? 'join' : ('rename_configured' === $action ? 'rename' : 'rebind');
    $expectedMode = 'task_join' === $action
        ? 'join' : (in_array($action, ['rebind_existing', 'self_restore', 'healthy_self_restore'], true)
            ? 'existing' : ('rename_configured' === $action ? 'rename' : 'create'));
    if (($plan['operation'] ?? '') !== $expectedOperation || ($plan['mode'] ?? '') !== $expectedMode
        || array_key_exists('source', $plan)) {
        throw new \RuntimeException('fixture_plan_shape_mismatch');
    }
    if (in_array($action, ['rebind_existing', 'self_restore', 'healthy_self_restore'], true)
        && (($plan['target']['guid'] ?? '') !== ($targetRecord['guid'] ?? ''))) {
        throw new \RuntimeException('fixture_target_pin_mismatch');
    }
    if ($renameConfigured && (($plan['target']['guid'] ?? '') !== ($targetRecord['guid'] ?? '')
        || ($plan['target']['sid'] ?? '') !== ($targetRecord['sid'] ?? '')
        || strcasecmp($plan['target']['account'] ?? '', 'A$'))) {
        throw new \RuntimeException('fixture_rename_old_identity_not_pinned');
    }
    if ($renameConfigured && ('false' !== ($observation['secure_channel'] ?? '')
        || '' === ($settingsCache['FOG_DIRECTORY_LDAP_URI']['value'] ?? ''))) {
        throw new \RuntimeException('fixture_configured_rename_sc_or_ldap_mismatch');
    }
    if ('healthy_self_restore' === $action
        && ('true' !== ($observation['secure_channel'] ?? '')
            || 'rebind' !== ($plan['operation'] ?? '') || 'existing' !== ($plan['mode'] ?? ''))) {
        throw new \RuntimeException('fixture_healthy_deploy_did_not_rebind');
    }
    if ('task_join' === $action
        && ('' !== ($settingsCache['FOG_DIRECTORY_LDAP_URI']['value'] ?? 'configured')
            || '' !== ($observation['domain'] ?? 'nonempty')
            || 'unknown' !== ($observation['secure_channel'] ?? '')
            || empty($observation['dc_reachable']))) {
        throw new \RuntimeException('fixture_task_join_observation_mismatch');
    }
    if ($taskBound
        && 500 !== (FOG\Agent\DirectoryRejoin::settings($host)['state']['trigger_task_id'] ?? null)) {
        throw new \RuntimeException('fixture_deploy_task_not_selected');
    }
    if ($taskBound && 500 !== (int)($plan['task_id'] ?? 0)) {
        throw new \RuntimeException('fixture_plan_task_id_mismatch');
    }
    if ('reassigned_rename' === $action && ('rebind' !== $plan['operation'] || 'create' !== $plan['mode'])) {
        throw new \RuntimeException('fixture_reassigned_name_was_renamed');
    }

    $startBody = [
        'attempt_id' => $plan['attempt_id'],
        'config_revision' => $plan['config_revision'],
        'target_guid' => $plan['target']['guid'] ?? null,
        'operation' => $plan['operation'],
        'task_id' => (int)($plan['task_id'] ?? -1)
    ];
    $badTaskStart = $startBody;
    $badTaskStart['task_id'] = (int)$startBody['task_id'] + 1;
    $wrongTaskRefused = false;
    try {
        FOG\Agent\DirectoryOperation::start($host, $badTaskStart);
    } catch (\RuntimeException $e) {
        $wrongTaskRefused = 'rejoin_start_refused' === $e->getMessage();
    }
    if (!$wrongTaskRefused) {
        throw new \RuntimeException('fixture_wrong_task_start_was_not_refused');
    }

    $renameIdentityChangedRefused = false;
    if ($renameConfigured) {
        $changedSource = $targetRecord;
        $changedSource['guid'] = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $changedSource['sid'] = 'S-1-5-21-7-2002';
        DirectoryOperationFlowLdapStub::$records['a'] = ['status' => 'found', 'record' => $changedSource];
        try {
            FOG\Agent\DirectoryOperation::start($host, $startBody);
        } catch (\RuntimeException $e) {
            $renameIdentityChangedRefused = 'rename_identity_unverified' === $e->getMessage();
        }
        $afterChangedIdentity = FOG\Agent\DirectoryRejoin::settings($host)['state'];
        if (!$renameIdentityChangedRefused || 'ready' !== ($afterChangedIdentity['phase'] ?? '')
            || (int)($afterChangedIdentity['last_deploy_task_id'] ?? 0) !== 0) {
            throw new \RuntimeException('fixture_changed_rename_identity_was_not_refused_before_claim');
        }
        DirectoryOperationFlowLdapStub::$records['a'] = ['status' => 'found', 'record' => $targetRecord];
    }

    $started = FOG\Agent\DirectoryOperation::start($host, $startBody);
    if ('ok' !== ($started['status'] ?? '') || 'fixture-password' !== ($started['directory']['password'] ?? '')
        || (int)($started['directory']['rejoin']['task_id'] ?? -1) !== (int)($plan['task_id'] ?? -2)
        || 'started' !== (FOG\Agent\DirectoryRejoin::settings($host)['state']['phase'] ?? '')) {
        throw new \RuntimeException('fixture_start_not_claimed');
    }
    $consumedTaskID = FOG\Agent\DirectoryRejoin::settings($host)['state']['last_deploy_task_id'] ?? 0;
    if ($taskBound && 500 !== $consumedTaskID) {
        throw new \RuntimeException('fixture_deploy_task_not_consumed');
    }
    if (!$taskBound && 0 !== (int)($plan['task_id'] ?? -1)) {
        throw new \RuntimeException('fixture_non_task_operation_has_task_id');
    }

    $replayRefused = false;
    try {
        FOG\Agent\DirectoryOperation::start($host, $startBody);
    } catch (\RuntimeException $e) {
        $replayRefused = 409 === (int)$e->getCode() && 'rejoin_start_refused' === $e->getMessage();
    }
    if (!$replayRefused) {
        throw new \RuntimeException('fixture_duplicate_start_was_not_refused');
    }

    // Native APIs are not called here. Model their observed result in the
    // fixture LDAP provider, then exercise the real result transitions.
    $resultTarget = $input['result_target_record'] ?? $targetRecord;
    $baseResult = [
        'id' => $hostId,
        'attempt_id' => $plan['attempt_id'],
        'config_revision' => $plan['config_revision'],
        'task_id' => (int)($plan['task_id'] ?? -1),
        'native_status' => 0
    ];
    if ($renameConfigured) {
        // Prove a newly named object with another GUID/SID cannot complete the rename.
        $wrongIdentity = $resultTarget;
        $wrongIdentity['guid'] = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $wrongIdentity['sid'] = 'S-1-5-21-7-2002';
        $wrongIdentity['account'] = $name . '$';
        DirectoryOperationFlowLdapStub::$records[$recordKey] = ['status' => 'found', 'record' => $wrongIdentity];
        $identityMismatchRefused = false;
        try {
            FOG\Agent\DirectoryOperation::report($host, $baseResult + [
                'status' => 'awaiting_reboot', 'native_status' => 0,
                'secure_channel' => 'unhealthy', 'boot_changed' => false,
                'current_name' => $name, 'domain' => $domain, 'netbios' => 'CORP'
            ]);
        } catch (\RuntimeException $e) {
            $identityMismatchRefused = 'rename_identity_unverified' === $e->getMessage();
        }
        if (!$identityMismatchRefused) {
            throw new \RuntimeException('fixture_rename_identity_mismatch_accepted');
        }
        $resultTarget['account'] = $name . '$';
        DirectoryOperationFlowLdapStub::$records[$recordKey] = ['status' => 'found', 'record' => $resultTarget];
    } else {
        DirectoryOperationFlowLdapStub::$records[$recordKey] = ['status' => 'found', 'record' => $resultTarget];
        $identityMismatchRefused = false;
    }
    $badTaskResult = $baseResult;
    $badTaskResult['task_id'] = (int)$baseResult['task_id'] + 1;
    $wrongTaskResultRefused = false;
    try {
        FOG\Agent\DirectoryOperation::report($host, $badTaskResult + ['status' => 'unknown']);
    } catch (\RuntimeException $e) {
        $wrongTaskResultRefused = 'rejoin_result_mismatch' === $e->getMessage();
    }
    if (!$wrongTaskResultRefused) {
        throw new \RuntimeException('fixture_wrong_task_result_was_not_refused');
    }
    $result = FOG\Agent\DirectoryOperation::report($host, $baseResult + [
        'status' => 'awaiting_reboot',
        'secure_channel' => 'healthy',
        'boot_changed' => false,
        'current_name' => $name,
        'domain' => $domain,
        'netbios' => 'CORP'
    ]);
    if ('' !== $result || 'awaiting_reboot' !== (FOG\Agent\DirectoryRejoin::settings($host)['state']['phase'] ?? '')) {
        throw new \RuntimeException('fixture_awaiting_reboot_not_recorded');
    }
    $result = FOG\Agent\DirectoryOperation::report($host, $baseResult + [
        'status' => 'verified',
        'secure_channel' => 'healthy',
        'boot_changed' => true,
        'current_name' => $name,
        'domain' => $domain,
        'netbios' => 'CORP'
    ]);
    $finalState = FOG\Agent\DirectoryRejoin::settings($host)['state'];
    if ('' !== $result || 'verified' !== ($finalState['phase'] ?? '')) {
        throw new \RuntimeException('fixture_verified_result_not_recorded');
    }
    if ($taskBound && (500 !== ($finalState['trigger_task_id'] ?? null)
        || 500 !== ($finalState['last_deploy_task_id'] ?? null))) {
        throw new \RuntimeException('fixture_task_binding_not_preserved_through_result');
    }
    echo json_encode([
        'status' => 'ok',
        'plan' => $plan,
        'response_plan' => $started['directory']['rejoin'],
        'operation' => $expectedOperation,
        'mode' => $expectedMode,
        'source_omitted' => !array_key_exists('source', $plan) && !array_key_exists('source', $started['directory']['rejoin']),
        'consumed_task_id' => $consumedTaskID,
        'start_claimed' => true,
        'replay_refused' => true,
        'wrong_task_start_refused' => true,
        'wrong_task_result_refused' => true,
        'rename_identity_changed_refused' => $renameIdentityChangedRefused,
        'rename_identity_mismatch_refused' => $identityMismatchRefused,
        'reported_phase' => 'verified'
    ]), "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'fixture flow failed: ' . $e->getMessage() . "\n");
    exit(1);
}
