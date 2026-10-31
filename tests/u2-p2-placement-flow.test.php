<?php
/**
 * Focused scratch-DB tests for the complete DirectoryPlacement::ensure flow.
 * LDAP is a fictional in-process stub; no real LDAP transport is used.
 */
class U2P2PlacementFlowLdapStub
{
    public static $connects = 0;
    public static $lookups = [];
    public static $containers = [];
    public static $moves = [];
    public static $context = [];
    public static $records = [];
    public static $recordQueues = [];
    public static $containerQueue = [];
    public static $containerRecord = null;
    public static $onContainer = null;
    private $connected = false;

    public static function reset()
    {
        self::$connects = 0;
        self::$lookups = [];
        self::$containers = [];
        self::$moves = [];
        self::$records = [];
        self::$recordQueues = [];
        self::$containerQueue = [];
        self::$containerRecord = null;
        self::$onContainer = null;
    }

    public function connect($uri, $bindDn, $password, $caFile = '')
    {
        self::$connects++;
        $this->connected = true;
        return true;
    }

    public function placementContext($searchBase)
    {
        if (!$this->connected || !self::samePlacementNC((string)$searchBase, self::$context)) {
            return ['status' => 'error', 'reason' => 'fixture_search_scope_invalid'];
        }
        self::$containers[] = ['kind' => 'context', 'search_base' => (string)$searchBase];
        return self::$context + ['status' => 'found'];
    }

    public function computerRecord($baseDn, $account)
    {
        $key = strtolower(rtrim((string)$account, '$'));
        self::$lookups[] = ['base_dn' => (string)$baseDn, 'account' => (string)$account];
        if (!$this->connected || !self::sameNamingContext((string)$baseDn, self::$context['base_dn'] ?? '')) {
            return ['status' => 'error', 'reason' => 'fixture_search_base_invalid'];
        }
        $record = !empty(self::$recordQueues[$key])
            ? array_shift(self::$recordQueues[$key])
            : (self::$records[$key] ?? ['status' => 'error', 'reason' => 'fixture_record_unspecified']);
        if ('found' === ($record['status'] ?? null)
            && !self::sameNamingContext((string)($record['record']['dn'] ?? ''), (string)$baseDn)) {
            return ['status' => 'error', 'reason' => 'fixture_search_subtree_mismatch'];
        }
        return $record;
    }

    public function containerIdentity($dn)
    {
        self::$containers[] = ['kind' => 'container', 'dn' => (string)$dn];
        if (is_callable(self::$onContainer)) {
            $callback = self::$onContainer;
            self::$onContainer = null;
            $callback();
        }
        if (self::$containerQueue) {
            return array_shift(self::$containerQueue);
        }
        $record = self::$containerRecord;
        if (is_array($record) && ($dn === $record['dn'] || !strcasecmp((string)$dn, self::guidDN($record['guid'])))) {
            return ['status' => 'found', 'guid' => $record['guid'], 'dn' => $record['dn']];
        }
        return ['status' => 'error', 'reason' => 'fixture_container_unspecified'];
    }

    public function moveTo($entry, $parent, $permit = null)
    {
        $rdn = FOG\Agent\DirectoryPlacementIntent::consumePermit($permit, $this, $entry, $parent);
        self::$moves[] = ['entry' => $entry, 'parent' => $parent, 'rdn' => $rdn];
        return true;
    }

    public function moveOutcome()
    {
        return ['kind' => 'before_call_not_sent'];
    }

    public function error()
    {
        return 'u2_p2_fictional_ldap_stub';
    }

    public function close()
    {
        $this->connected = false;
    }

    public static function guidDN($guid)
    {
        return '<GUID=' . strtolower(str_replace('-', '', (string)$guid)) . '>';
    }

    public static function rdn($dn)
    {
        return explode(',', (string)$dn, 2)[0];
    }

    public static function parentDn($dn)
    {
        return explode(',', (string)$dn, 2)[1] ?? '';
    }

    private static function sameNamingContext($dn, $nc)
    {
        $dn = trim((string)$dn);
        $nc = trim((string)$nc);
        return '' !== $nc && (0 === strcasecmp($dn, $nc)
            || (strlen($dn) > strlen($nc)
                && 0 === strcasecmp(substr($dn, -strlen($nc) - 1), ',' . $nc)));
    }

    public static function samePlacementNC($dn, array $context)
    {
        $root = (string)($context['base_dn'] ?? '');
        if ('' === $root || !self::sameNamingContext($dn, $root)) {
            return false;
        }
        $best = '';
        foreach ($context['naming_contexts'] ?? [] as $nc) {
            if (is_string($nc) && strlen($nc) > strlen($best) && self::sameNamingContext($dn, $nc)) {
                $best = $nc;
            }
        }
        return '' !== $best && 0 === strcasecmp($best, $root);
    }
}
class_alias(U2P2PlacementFlowLdapStub::class, 'FOG\\Net\\FOGLdap', false);

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 placement flow: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('U2-P2 placement flow requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-placement-flow');
require_once __DIR__ . '/lib/sc-hold-pdo.php';

$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('U2-P2 placement flow requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
}

$link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new ScHoldPdoDb($pdo);
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

$test = new FogChecks();
$rootDN = 'DC=corp,DC=invalid';
$managedDN = 'OU=Managed,' . $rootDN;
U2P2PlacementFlowLdapStub::$context = [
    'domain' => 'corp.invalid',
    'base_dn' => $rootDN,
    'dc' => 'dc1.corp.invalid',
    'naming_contexts' => [
        $rootDN,
        'CN=Configuration,' . $rootDN,
        'CN=Schema,CN=Configuration,' . $rootDN
    ]
];
$cache = [];
$setBase = static function ($baseDN) use (&$cache) {
    $settings = [
        'FOG_DIRECTORY_PLACEMENT_ENABLED' => '1',
        'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.invalid',
        'FOG_DIRECTORY_BIND_DN' => 'cn=fixture,dc=corp,dc=invalid',
        'FOG_DIRECTORY_BIND_PASSWORD' => 'fictional-password',
        'FOG_DIRECTORY_CA_CERT' => '',
        'FOG_DIRECTORY_BASE_DN' => $baseDN
    ];
    foreach ($settings as $key => $value) {
        $cache[$key] = ['value' => $value, 'ts' => time()];
    }
    FogTestHarness::setStatic('FOGBase', '_settingsCache', $cache);
    FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
};
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$fixtures = [];
$createFixture = static function ($case, $ldapDN, $dbDN, $baseDN) use ($pdo, $managedDN, $guid, &$fixtures, $setBase) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $name = 'P2F' . strtoupper(bin2hex(random_bytes(6)));
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=? OR hostName=?');
        $q->execute([$hostID, $name]);
        $hostExists = (int)$q->fetchColumn() > 0;
        $q = $pdo->prepare('SELECT COUNT(*) FROM directoryPlacementIntent WHERE dpiHostID=?');
        $q->execute([$hostID]);
        $intentExists = (int)$q->fetchColumn() > 0;
        $q = $pdo->prepare('SELECT COUNT(*) FROM hostDirectory WHERE hdHostID=?');
        $q->execute([$hostID]);
        $directoryExists = (int)$q->fetchColumn() > 0;
        $q = $pdo->prepare('SELECT COUNT(*) FROM auditLog WHERE alSubjectType=\'host\' AND alSubjectID=?');
        $q->execute([$hostID]);
        $auditExists = (int)$q->fetchColumn() > 0;
        $q = $pdo->prepare('SELECT COUNT(*) FROM history WHERE hSubjectType=\'host\' AND hSubjectID=?');
        $q->execute([$hostID]);
        $historyExists = (int)$q->fetchColumn() > 0;
    } while ($hostExists || $intentExists || $directoryExists || $auditExists || $historyExists);

    $ldapDN = str_replace('{name}', $name, $ldapDN);
    $dbDN = str_replace('{name}', $name, $dbDN);
    $sid = 'S-1-5-21-44001-' . $hostID;
    $targetGuid = $guid();
    $fingerprint = hash('sha256', random_bytes(32));
    $observedAt = gmdate('Y-m-d H:i:s');
    $hostInsert = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,\'1\',?,?,?)'
    );
    $hostInsert->execute([$hostID, $name, 'CORP', $managedDN, $fingerprint]);
    $fixtures[] = ['host_id' => $hostID, 'guid' => $targetGuid];
    $directoryInsert = $pdo->prepare(
        'INSERT INTO hostDirectory (hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdPlacementAt,hdPlacementError,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,?,NULL,\'\',NULL,NULL)'
    );
    $directoryInsert->execute([$hostID, 'CORP', $dbDN, $name . '$', $observedAt]);

    $host = (new FOG\Items\Host())->set('id', $hostID)->set('name', $name)
        ->set('ADDomain', 'CORP')->set('ADOU', $managedDN)->set('useAD', 1)
        ->set('agentFingerprint', $fingerprint);
    $directory = (new FOG\Items\HostDirectory())->set('hostID', $hostID)->set('joined', 1)
        ->set('kind', 'domain')->set('domain', 'CORP')->set('computerDN', $dbDN)
        ->set('machineAccount', $name . '$')->set('observedAt', $observedAt)
        ->set('placementAt', null)->set('placementError', '');
    $record = [
        'guid' => $targetGuid,
        'sid' => $sid,
        'account' => $name . '$',
        'dn' => $ldapDN
    ];
    $destination = ['guid' => $guid(), 'dn' => $managedDN];
    $setBase($baseDN);
    U2P2PlacementFlowLdapStub::reset();
    U2P2PlacementFlowLdapStub::$containerRecord = $destination;
    U2P2PlacementFlowLdapStub::$records[strtolower($name)] = ['status' => 'found', 'record' => $record];
    return compact('case', 'hostID', 'name', 'sid', 'host', 'directory', 'record', 'destination', 'fingerprint');
};
$readHostDirectory = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hdDomain,hdComputerDN,hdObservedAt,hdPlacementAt,hdPlacementError FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: [];
};
$readIntent = static function ($targetGuid) use ($pdo) {
    $q = $pdo->prepare('SELECT dpiGeneration,dpiStatus,dpiResult FROM directoryPlacementIntent WHERE dpiGUID=?');
    $q->execute([hex2bin(str_replace('-', '', strtolower($targetGuid)))]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
};
$ensure = static function (array $fixture) {
    FOG\Agent\DirectoryPlacement::ensure($fixture['host'], $fixture['directory']);
};
$recordFor = static function (array $fixture, $dn = null) {
    $record = $fixture['record'];
    if (null !== $dn) {
        $record['dn'] = $dn;
    }
    return ['status' => 'found', 'record' => $record];
};

try {
    // Legacy NetBIOS values coexist with an OU-subtree search base. The stub
    // validates only fictional root-DSE naming-context relationships.
    $legacy = $createFixture('legacy-netbios-subtree', 'CN={name},OU=Devices,' . $rootDN, '',
        'OU=Devices,' . $rootDN);
    U2P2PlacementFlowLdapStub::$records[strtolower($legacy['name'])] = $recordFor($legacy);
    U2P2PlacementFlowLdapStub::$recordQueues[strtolower($legacy['name'])] = [$recordFor($legacy), $recordFor($legacy)];
    $ensure($legacy);
    $legacyRow = $readHostDirectory($legacy['hostID']);
    $legacyIntent = $readIntent($legacy['record']['guid']);
    $test->check('legacy NetBIOS domain plus OU-subtree base completes one GUID move and SUCCEEDED intent',
        1 === U2P2PlacementFlowLdapStub::$connects && 1 === count(U2P2PlacementFlowLdapStub::$moves)
        && 'OU=Devices,' . $rootDN === (U2P2PlacementFlowLdapStub::$containers[0]['search_base'] ?? null)
        && 'CORP' === ($legacyRow['hdDomain'] ?? null)
        && 'SUCCEEDED' === ($legacyIntent['dpiStatus'] ?? null)
        && 'CN=' . $legacy['name'] . ',' . $managedDN === ($legacyRow['hdComputerDN'] ?? null));

    // An authoritative LDAP read already at the requested OU teaches an
    // empty or stale DB DN without minting a permit or intent slot.
    foreach ([['empty-db-dn', ''], ['stale-db-dn', 'CN=Learn,OU=Old,' . $rootDN]] as $learnCase) {
        list($label, $storedDN) = $learnCase;
        $learn = $createFixture($label, 'CN={name},' . $managedDN,
            str_replace('CN=Learn', 'CN={name}', $storedDN), $rootDN);
        $actualDN = 'CN=' . $learn['name'] . ',' . $managedDN;
        U2P2PlacementFlowLdapStub::$records[strtolower($learn['name'])] = $recordFor($learn, $actualDN);
        $ensure($learn);
        $learned = $readHostDirectory($learn['hostID']);
        $connectsAfterLearn = U2P2PlacementFlowLdapStub::$connects;
        $lookupsAfterLearn = count(U2P2PlacementFlowLdapStub::$lookups);
        $containersAfterLearn = count(U2P2PlacementFlowLdapStub::$containers);
        $firstPlacementAt = $learned['hdPlacementAt'] ?? null;
        $secondDirectory = (new FOG\Items\HostDirectory())->set('hostID', $learn['hostID'])->set('joined', 1)
            ->set('kind', 'domain')->set('domain', 'CORP')->set('computerDN', $learned['hdComputerDN'] ?? '')
            ->set('machineAccount', $learn['name'] . '$')->set('observedAt', $learned['hdObservedAt'] ?? null)
            ->set('placementAt', $firstPlacementAt)->set('placementError', $learned['hdPlacementError'] ?? '');
        $learn['directory'] = $secondDirectory;
        $ensure($learn);
        $learnIntent = $readIntent($learn['record']['guid']);
        $test->check($label . ' learns already-correct LDAP DN/cooldown and next ensure does no LDAP or move',
            $actualDN === ($learned['hdComputerDN'] ?? null)
            && !empty($firstPlacementAt) && '' === ($learned['hdPlacementError'] ?? null)
            && 1 === $connectsAfterLearn && 0 === count(U2P2PlacementFlowLdapStub::$moves)
            && $connectsAfterLearn === U2P2PlacementFlowLdapStub::$connects
            && $lookupsAfterLearn === count(U2P2PlacementFlowLdapStub::$lookups)
            && $containersAfterLearn === count(U2P2PlacementFlowLdapStub::$containers)
            && null === $learnIntent);
    }

    // UNKNOWN is a durable hold even when a fresh LDAP read says the target is
    // already at the desired container. No slot clear or permit is allowed.
    $unknown = $createFixture('unknown-at-destination', 'CN={name},' . $managedDN,
        'CN={name},OU=Old,' . $rootDN, $rootDN);
    $unknownDN = 'CN=' . $unknown['name'] . ',' . $managedDN;
    U2P2PlacementFlowLdapStub::$records[strtolower($unknown['name'])] = $recordFor($unknown, $unknownDN);
    $unknownPayload = ['version' => 1, 'host_id' => $unknown['hostID'], 'target' => $unknown['record']];
    $unknownJSON = json_encode($unknownPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $unknownInsert = $pdo->prepare(
        'INSERT INTO directoryPlacementIntent (dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult,dpiUpdated) '
        . 'VALUES (?,?,?,1,\'UNKNOWN\',?,?,?,UTC_TIMESTAMP(6))'
    );
    $unknownInsert->execute([
        hex2bin(str_replace('-', '', strtolower($unknown['record']['guid']))),
        $unknown['sid'], $unknown['hostID'], $unknownJSON, hash('sha256', $unknownJSON), 'fixture unknown'
    ]);
    $unknownBefore = $readHostDirectory($unknown['hostID']);
    $ensure($unknown);
    $unknownAfter = $readHostDirectory($unknown['hostID']);
    $unknownIntent = $readIntent($unknown['record']['guid']);
    $test->check('UNKNOWN same-GUID already-at-destination preserves slot and grants no move permit',
        'UNKNOWN' === ($unknownIntent['dpiStatus'] ?? null)
        && 'fixture unknown' === ($unknownIntent['dpiResult'] ?? null)
        && $unknownBefore['hdComputerDN'] === $unknownAfter['hdComputerDN']
        && $unknownBefore['hdPlacementAt'] === $unknownAfter['hdPlacementAt']
        && 0 === count(U2P2PlacementFlowLdapStub::$moves));

    // Simulate an admin OU edit from inside the first LDAP lookup. The loaded
    // request model must fail its snapshot check before intent prepare/Move.
    $edit = $createFixture('ou-edit-during-lookup', 'CN={name},OU=Devices,' . $rootDN,
        'CN={name},OU=Old,' . $rootDN, $rootDN);
    U2P2PlacementFlowLdapStub::$records[strtolower($edit['name'])] = $recordFor($edit);
    $editApplied = false;
    U2P2PlacementFlowLdapStub::$onContainer = static function () use ($edit, $rootDN, &$editApplied) {
        $editApplied = (new FOG\Managers\HostManager())->update(
            ['id' => $edit['hostID']], 'AND', ['ADOU' => 'OU=Changed,' . $rootDN]
        );
    };
    $ensure($edit);
    $editIntent = $readIntent($edit['record']['guid']);
    $editRow = $readHostDirectory($edit['hostID']);
    $editHostRow = $pdo->prepare('SELECT hostADOU FROM hosts WHERE hostID=?');
    $editHostRow->execute([$edit['hostID']]);
    $test->check('OU edit during LDAP lookup is refused before intent or move',
        true === $editApplied && 'OU=Changed,' . $rootDN === $editHostRow->fetchColumn()
        && null === $editIntent && 0 === count(U2P2PlacementFlowLdapStub::$moves)
        && 'directory_placement_request_changed' === ($editRow['hdPlacementError'] ?? null));

    // After PREPARED, a changed target GUID or destination GUID on the
    // authoritative second lookup must cancel only that unsent generation.
    foreach (['target-guid-changed', 'destination-guid-changed'] as $changeCase) {
        $changed = $createFixture($changeCase, 'CN={name},' . $rootDN,
            'CN={name},OU=Old,' . $rootDN, $rootDN);
        U2P2PlacementFlowLdapStub::$records[strtolower($changed['name'])] = $recordFor($changed);
        if ('target-guid-changed' === $changeCase) {
            $replacement = $recordFor($changed);
            $replacement['record']['guid'] = $guid();
            $replacement['record']['sid'] = 'S-1-5-21-44002-' . $changed['hostID'];
            U2P2PlacementFlowLdapStub::$recordQueues[strtolower($changed['name'])] = [
                $recordFor($changed), $replacement
            ];
        } else {
            $wrongDestination = ['status' => 'found', 'guid' => $guid(), 'dn' => $managedDN];
            U2P2PlacementFlowLdapStub::$containerQueue = [
                ['status' => 'found', 'guid' => $changed['destination']['guid'], 'dn' => $managedDN],
                $wrongDestination
            ];
        }
        $ensure($changed);
        $changedIntent = $readIntent($changed['record']['guid']);
        $changedRow = $readHostDirectory($changed['hostID']);
        $test->check($changeCase . ' cancels exact PREPARED ticket and does not move',
            'CANCELLED' === ($changedIntent['dpiStatus'] ?? null)
            && 1 === (int)($changedIntent['dpiGeneration'] ?? 0)
            && 0 === count(U2P2PlacementFlowLdapStub::$moves)
            && 'directory_placement_identity_changed_before_dispatch' === ($changedRow['hdPlacementError'] ?? null));
    }
} finally {
    foreach ($fixtures as $fixture) {
        $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiHostID=?')->execute([$fixture['host_id']]);
        $pdo->prepare('DELETE FROM auditLog WHERE alSubjectType=\'host\' AND alSubjectID=?')->execute([$fixture['host_id']]);
        $pdo->prepare('DELETE FROM history WHERE hSubjectType=\'host\' AND hSubjectID=?')->execute([$fixture['host_id']]);
        $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?')->execute([$fixture['host_id']]);
        $pdo->prepare('DELETE FROM hosts WHERE hostID=?')->execute([$fixture['host_id']]);
    }
}

$test->finish();
