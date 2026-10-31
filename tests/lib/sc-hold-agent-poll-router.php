<?php
/** HTTP router fixture: invoke the real authenticated-agent poll method in isolation. */
error_reporting(E_ALL & ~E_DEPRECATED);
class ScHoldRouteLdapStub
{
    public static $connects = 0;
    public static $moves = 0;
    public static $pdo;
    public static $context = [
        'base_dn'=>'DC=corp,DC=example',
        'dc'=>'dc1.corp.example',
        'naming_contexts'=>[
            'DC=corp,DC=example',
            'CN=Configuration,DC=corp,DC=example',
            'CN=Schema,CN=Configuration,DC=corp,DC=example'
        ]
    ];
    public static $records = [];
    public static $containers = [];
    public function connect($uri, $bindDn, $password, $caFile = '') { self::$connects++; return true; }
    public function error() { return 'sc_route_fixture_error'; }
    public function close() {}
    public function placementContext($searchBase)
    {
        if (!self::samePlacementNC((string)$searchBase, self::$context)) {
            return ['status'=>'error', 'reason'=>'fixture_search_base_mismatch'];
        }
        return ['status'=>'found'] + self::$context;
    }
    public function moveTo($dn, $parentDn, $dispatchPermit = null)
    {
        \FOG\Agent\DirectoryPlacementIntent::consumePermit($dispatchPermit, $this, $dn, $parentDn);
        self::$moves++;
        return true;
    }
    public function findComputer($baseDn, $account) { return ''; }
    public function computerRecord($baseDn, $account)
    {
        return self::$records[strtolower(rtrim((string)$account, '$'))] ?? ['status'=>'error'];
    }
    public function containerIdentity($dn)
    {
        foreach (self::$containers as $container) {
            if (!strcasecmp((string)$dn, $container['dn']) || !strcasecmp((string)$dn, self::guidDN($container['guid']))) {
                return ['status'=>'found', 'guid'=>$container['guid'], 'dn'=>$container['dn']];
            }
        }
        return ['status'=>'error', 'reason'=>'fixture_destination_unspecified'];
    }
    public static function rdn($dn) { return explode(',', (string)$dn, 2)[0]; }
    public static function parentDn($dn) { $parts = explode(',', (string)$dn, 2); return $parts[1] ?? ''; }
    public static function guidDN($guid) { return '<GUID=' . strtolower(str_replace('-', '', (string)$guid)) . '>'; }
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
class_alias(ScHoldRouteLdapStub::class, 'FOG\\Net\\FOGLdap', false);
require dirname(__DIR__) . '/lib/fog-test-harness.php';
require dirname(__DIR__) . '/lib/sc-hold-pdo.php';
FogTestHarness::boot('sc-hold-agent-poll-http');
FogTestHarness::fakeDb();
$db = new \PDO(getenv('MJ1_DB_DSN'), getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
if (getenv('MJ1_DB_ALLOW_RESET') !== '1' || 0 !== strpos((string)getenv('MJ1_DB_DSN'), 'mysql:')) {
    throw new \RuntimeException('T01 HTTP fixture requires explicit MySQL scratch database opt-in');
}
$database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('T01 HTTP fixture database must begin with fog_mj1');
}
$identity = $db->prepare('SELECT hmHostID FROM hostMAC WHERE hmMAC=? AND hmPending=0');
$identity->execute(['02:00:00:00:00:71']);
if (7 !== (int)$identity->fetchColumn()) {
    throw new \RuntimeException('T01 HTTP fixture MAC identity is not pinned to the expected scratch host');
}
$fake = new ScHoldPdoDb($db);
FogTestHarness::setStatic('FOGBase', 'DB', $fake);
FogTestHarness::setStatic('DatabaseManager', 'DB', $fake);
ScHoldRouteLdapStub::$pdo = $db;
register_shutdown_function(static function () use ($db) {
    $q = $db->prepare('UPDATE scHoldLdapCounts SET connects=?,moves=? WHERE id=1');
    $q->execute([ScHoldRouteLdapStub::$connects, ScHoldRouteLdapStub::$moves]);
});
$settings = [
    'FOG_AGENT_INVENTORY_ENABLED'=>'1', 'FOG_HOST_IDENTIFY_SMBIOS'=>'off',
    'FOG_DIRECTORY_PLACEMENT_ENABLED'=>'1', 'FOG_DIRECTORY_LDAP_URI'=>'ldaps://dc1.corp.example',
    'FOG_DIRECTORY_BIND_DN'=>'cn=svc', 'FOG_DIRECTORY_BIND_PASSWORD'=>'pw',
    'FOG_DIRECTORY_BASE_DN'=>'DC=corp,DC=example', 'FOG_DIRECTORY_CA_CERT'=>'',
    'FOG_CLIENT_CHECKIN_TIME'=>'300', 'FOG_AGENT_UPDATE_MODE'=>'off',
    'FOG_CLIENT_AUTOLOGOFF_ENABLED'=>'0','FOG_CLIENT_HOSTNAMECHANGER_ENABLED'=>'0',
    'FOG_CLIENT_HOSTREGISTER_ENABLED'=>'0','FOG_CLIENT_POWERMANAGEMENT_ENABLED'=>'0',
    'FOG_CLIENT_PRINTERMANAGER_ENABLED'=>'0','FOG_CLIENT_SNAPIN_ENABLED'=>'0',
    'FOG_CLIENT_SOFTWARE_ENABLED'=>'0','FOG_CLIENT_TASKREBOOT_ENABLED'=>'0',
    'FOG_CLIENT_USERTRACKER_ENABLED'=>'0'
];
$cache = [];
foreach ($settings as $key => $value) { $cache[$key] = ['value'=>$value, 'ts'=>time()]; }
FogTestHarness::setStatic('FOGBase', '_settingsCache', $cache);
FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
\FOG\Router\Route::$agentHost = new \FOG\Items\Host(7);
\FOG\Router\Route::agentPoll();
