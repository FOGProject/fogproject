<?php
/**
 * Router for php -S: runs the real Route::agentPoll() against the scratch
 * database of tests/agent-directory-observation-placement-db.test.php, with a
 * directory stub that only counts connects and moves into scHoldLdapCounts.
 * Never use against another database.
 */
error_reporting(E_ALL & ~E_DEPRECATED);

class PollRouteLdapStub
{
    public static $connects = 0;
    public static $moves = 0;
    public function connect($uri, $bindDn, $password, $caFile = '') { self::$connects++; return true; }
    public function error() { return 'poll_route_fixture_error'; }
    public function close() { }
    public function domainContext($domain)
    {
        return ['status' => 'found', 'base_dn' => 'DC=corp,DC=example', 'dc' => 'dc1.corp.example'];
    }
    public function computerRecord($baseDn, $account) { return ['status' => 'error']; }
    public function containerIdentity($dn) { return ['status' => 'error']; }
    public function moveTo($dn, $parentDn) { self::$moves++; return true; }
}
class_alias(PollRouteLdapStub::class, 'FOG\\Net\\FOGLdap', false);

require dirname(__DIR__) . '/lib/directory-db-connect.php';
FogTestHarness::boot('agent-directory-poll-route');
FogTestHarness::fakeDb();
$pdo = directoryTestPdo();
directoryTestBindPdodb($pdo);

register_shutdown_function(static function () use ($pdo) {
    $q = $pdo->prepare('UPDATE scHoldLdapCounts SET connects=?,moves=? WHERE id=1');
    $q->execute([PollRouteLdapStub::$connects, PollRouteLdapStub::$moves]);
});

$settings = [
    'FOG_AGENT_INVENTORY_ENABLED' => '1', 'FOG_HOST_IDENTIFY_SMBIOS' => 'off',
    'FOG_DIRECTORY_PLACEMENT_ENABLED' => '1', 'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.example',
    'FOG_DIRECTORY_BIND_DN' => 'cn=svc', 'FOG_DIRECTORY_BIND_PASSWORD' => 'pw',
    'FOG_DIRECTORY_BASE_DN' => 'DC=corp,DC=example', 'FOG_DIRECTORY_CA_CERT' => '',
    'FOG_CLIENT_CHECKIN_TIME' => '300', 'FOG_AGENT_UPDATE_MODE' => 'off',
    'FOG_CLIENT_AUTOLOGOFF_ENABLED' => '0', 'FOG_CLIENT_HOSTNAMECHANGER_ENABLED' => '0',
    'FOG_CLIENT_HOSTREGISTER_ENABLED' => '0', 'FOG_CLIENT_POWERMANAGEMENT_ENABLED' => '0',
    'FOG_CLIENT_PRINTERMANAGER_ENABLED' => '0', 'FOG_CLIENT_SNAPIN_ENABLED' => '0',
    'FOG_CLIENT_SOFTWARE_ENABLED' => '0', 'FOG_CLIENT_TASKREBOOT_ENABLED' => '0',
    'FOG_CLIENT_USERTRACKER_ENABLED' => '0'
];
$cache = [];
foreach ($settings as $key => $value) {
    $cache[$key] = ['value' => $value, 'ts' => time()];
}
FogTestHarness::setStatic('FOGBase', '_settingsCache', $cache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
\FOG\Router\Route::$agentHost = new \FOG\Items\Host(7);
\FOG\Router\Route::agentPoll();
