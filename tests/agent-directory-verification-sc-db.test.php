<?php
/** T14: the real result handler must not verify a False/unknown postboot check. */
class VerificationScLdapStub
{
    public static $record;
    public function connect($uri, $bind, $password, $ca = '') { return true; }
    public function domainContext($domain) { return ['status' => 'found', 'base_dn' => 'DC=corp,DC=example', 'dc' => 'dc1.corp.example']; }
    public function computerRecord($base, $account) { return ['status' => 'found', 'record' => self::$record]; }
    public function close() { }
}
class_alias(VerificationScLdapStub::class, 'FOG\\Net\\FOGLdap', false);
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory verification DB: FOG_TEST_DSN not set\n";
    exit(0);
}
$directoryDbName = 'agent-directory-verification-sc-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
$t = new FogChecks();
$host = (new FOG\Items\Host())->set('id', 7)->set('name', 'B')->set('ADDomain', 'corp.example')
    ->set('useAD', 1)->set('agentFingerprint', str_repeat('f', 64));
$pdo->exec("UPDATE hosts SET hostName='B',hostADDomain='corp.example',hostUseAD=1,hostAgentFingerprint='" . str_repeat('f', 64) . "' WHERE hostID=7");
$cache = [];
foreach (['FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.example', 'FOG_DIRECTORY_BIND_DN' => 'CN=fixture',
    'FOG_DIRECTORY_BIND_PASSWORD' => 'fixture-only', 'FOG_DIRECTORY_BASE_DN' => 'DC=corp,DC=example', 'FOG_DIRECTORY_CA_CERT' => ''] as $key => $value) {
    $cache[$key] = ['value' => $value];
}
FogTestHarness::setStatic('FOGBase', '_settingsCache', $cache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
$target = ['guid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'sid' => 'S-1-5-21-7-1001',
    'account' => 'B$', 'dn' => 'CN=B,DC=corp,DC=example'];
VerificationScLdapStub::$record = $target;
$config = ['version' => 1, 'protocol' => 2, 'name' => 'B', 'domain' => 'corp.example', 'allow_create' => false, 'target' => $target];
$write = $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=7');
foreach (['join', 'rebind', 'rename'] as $operation) {
    $state = ['version' => 1, 'protocol' => 2, 'operation' => $operation, 'mode' => 'rebind' === $operation ? 'existing' : $operation,
        'host_id' => 7, 'fingerprint' => str_repeat('f', 64), 'attempt_id' => str_repeat('a', 32),
        'config_revision' => str_repeat('b', 32), 'phase' => 'awaiting_reboot', 'name' => 'B', 'domain' => 'corp.example',
        'target' => $target, 'trigger_task_id' => 71, 'last_deploy_task_id' => 71, 'placement_hold' => true];
    foreach (['broken', 'unknown'] as $secure) {
        $write->execute([json_encode($config), json_encode($state)]);
        $before = FOG\Agent\DirectoryRejoin::settings($host);
        $body = ['id' => 7, 'attempt_id' => $state['attempt_id'], 'config_revision' => $state['config_revision'],
            'task_id' => 71, 'status' => 'verified', 'native_status' => 0, 'secure_channel' => $secure,
            'boot_changed' => true, 'current_name' => 'B', 'domain' => 'corp.example', 'netbios' => 'CORP'];
        $reason = '';
        try { FOG\Agent\DirectoryOperation::report($host, $body); } catch (\RuntimeException $e) { $reason = $e->getMessage(); }
        $t->check('T14 ' . $operation . ' rejects postboot ' . $secure,
            'rejoin_verification_incomplete' === $reason && $before === FOG\Agent\DirectoryRejoin::settings($host));
    }
    $body['secure_channel'] = 'healthy';
    FOG\Agent\DirectoryOperation::report($host, $body);
    $after = FOG\Agent\DirectoryRejoin::settings($host)['state'];
    $t->check('T14 ' . $operation . ' verifies only after healthy postboot result',
        'verified' === $after['phase'] && 71 === $after['last_deploy_task_id'] && $target['guid'] === $after['target']['guid']);
}
$t->finish();
