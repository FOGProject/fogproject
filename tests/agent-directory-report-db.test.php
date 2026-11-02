<?php
/**
 * DirectoryOperation::report() against a real server, with the directory
 * lookups stubbed: an awaiting_reboot report persists with the target identity
 * and task counters kept, and a verified report whose secure channel is
 * broken is refused and changes nothing.
 *
 * Usage: FOG_TEST_DSN=... php tests/agent-directory-report-db.test.php
 */

if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory report DB: FOG_TEST_DSN not set\n";
    exit(0);
}

class DirectoryReportFakeLdap
{
    public static $record = [];
    public function connect($uri, $bind, $password, $ca = '') { return true; }
    public function domainContext($domain)
    {
        return ['status' => 'found', 'base_dn' => 'DC=corp,DC=example', 'dc' => 'dc1.corp.example'];
    }
    public function computerRecord($base, $account)
    {
        if (0 !== strcasecmp(rtrim((string)$account, '$'),
            rtrim((string)(self::$record['account'] ?? ''), '$'))) {
            return ['status' => 'error'];
        }
        return ['status' => 'found', 'record' => self::$record];
    }
    public function close() { }
}
class_alias(DirectoryReportFakeLdap::class, 'FOG\\Net\\FOGLdap', false);

$directoryDbName = 'agent-directory-report-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
$t = new FogChecks();

$newName = 'RPT' . strtoupper(bin2hex(random_bytes(4)));
$oldName = 'SRC' . strtoupper(bin2hex(random_bytes(4)));
$domain = 'corp.example';
$fingerprint = hash('sha256', random_bytes(32));
$hostID = 71;
$target = [
    'guid' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    'sid' => 'S-1-5-21-1048576-4242',
    'account' => $newName . '$',
    'dn' => 'CN=' . $newName . ',OU=Computers,DC=corp,DC=example'
];
$host = (new FOG\Items\Host())->set('id', $hostID)->set('name', $newName)
    ->set('ADDomain', $domain)->set('useAD', 1)->set('agentFingerprint', $fingerprint);
$config = [
    'version' => 1, 'protocol' => 2, 'allow_create' => false,
    'name' => $newName, 'domain' => $domain, 'target' => $target,
    'config_revision' => bin2hex(random_bytes(16)),
    'confirmed_by' => 'report-fixture', 'confirmed_at' => gmdate('c')
];
$revision = FOG\Agent\DirectoryOperation::revision($host, $config, 'dc1.corp.example');
$attempt = bin2hex(random_bytes(16));
$state = [
    'version' => 1, 'protocol' => 2, 'operation' => 'rename', 'mode' => 'existing',
    'target' => $target, 'host_id' => $hostID, 'name' => $newName, 'domain' => $domain,
    'dc' => 'dc1.corp.example', 'check_dc' => 'dc1.corp.example', 'phase' => 'started',
    'attempt_id' => $attempt, 'config_revision' => $revision, 'fingerprint' => $fingerprint,
    'expected_current' => $oldName, 'expected_pending' => $newName,
    'last_deploy_task_id' => 0, 'trigger_task_id' => 0, 'placement_hold' => true,
    'secure_channel' => 'unknown', 'reason' => 'started'
];
$body = [
    'id' => $hostID, 'attempt_id' => $attempt, 'config_revision' => $revision,
    'task_id' => 0, 'status' => 'awaiting_reboot', 'native_status' => 0,
    'secure_channel' => 'unknown', 'boot_changed' => false,
    'current_name' => $newName, 'domain' => $domain, 'netbios' => 'CORP'
];
DirectoryReportFakeLdap::$record = $target;
$settingsCache = [];
foreach ([
    'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.example',
    'FOG_DIRECTORY_BIND_DN' => 'CN=fixture',
    'FOG_DIRECTORY_BIND_PASSWORD' => 'fixture-only',
    'FOG_DIRECTORY_BASE_DN' => 'DC=corp,DC=example',
    'FOG_DIRECTORY_CA_CERT' => ''
] as $key => $value) {
    $settingsCache[$key] = ['value' => $value];
}
FogTestHarness::setStatic('FOGBase', '_settingsCache', $settingsCache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);

$pdo->prepare(
    'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)'
)->execute([$hostID, $newName, $domain, 'OU=Managed,DC=corp,DC=example', $fingerprint]);
$pdo->prepare(
    'INSERT INTO hostDirectory '
    . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
    . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)'
)->execute([
    $hostID, $domain, 'CN=' . $oldName . ',OU=Computers,DC=corp,DC=example',
    $oldName . '$', json_encode($config, JSON_THROW_ON_ERROR), json_encode($state, JSON_THROW_ON_ERROR)
]);

FOG\Agent\DirectoryOperation::report($host, $body);
$awaiting = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'B06 report persists awaiting_reboot with identity and task highwater retained',
    'awaiting_reboot' === ($awaiting['state']['phase'] ?? null)
    && $target['guid'] === ($awaiting['state']['target']['guid'] ?? null)
    && $target['sid'] === ($awaiting['state']['target']['sid'] ?? null)
    && 0 === ($awaiting['state']['last_deploy_task_id'] ?? null)
    && 0 === ($awaiting['state']['trigger_task_id'] ?? null)
    && !$pdo->inTransaction()
);

$beforeBroken = FOG\Agent\DirectoryRejoin::settings($host);
$brokenBody = $body;
$brokenBody['status'] = 'verified';
$brokenBody['secure_channel'] = 'broken';
$brokenBody['boot_changed'] = true;
$reason = '';
try {
    FOG\Agent\DirectoryOperation::report($host, $brokenBody);
} catch (\Throwable $e) {
    $reason = $e->getMessage();
}
$t->check(
    'B18 verified report rejects broken secure channel without changing the stored state',
    'rejoin_verification_incomplete' === $reason
    && $beforeBroken === FOG\Agent\DirectoryRejoin::settings($host)
);

$verifiedBody = $body;
$verifiedBody['status'] = 'verified';
$verifiedBody['secure_channel'] = 'healthy';
$verifiedBody['boot_changed'] = true;
FOG\Agent\DirectoryOperation::report($host, $verifiedBody);
$verified = FOG\Agent\DirectoryRejoin::settings($host);
$t->check(
    'a healthy verified report ends the hold and records the target on the config',
    'verified' === ($verified['state']['phase'] ?? null)
    && false === ($verified['state']['placement_hold'] ?? null)
    && $target['guid'] === ($verified['config']['target']['guid'] ?? null)
);

$t->finish();
