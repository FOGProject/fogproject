<?php
/**
 * The whole OU placement step, DirectoryPlacement::ensure(), against a real
 * server and a fictional directory: a Move is recorded before it is sent and
 * stored after, a host already in the right OU only learns its DN, a request
 * whose host or directory identity changed is never sent, and a Move whose
 * answer is lost leaves an UNKNOWN row that stops every further Move.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-placement-flow-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory placement flow DB: FOG_TEST_DSN not set\n";
    exit(0);
}

require __DIR__ . '/lib/directory-ldap-stub.php';
class_alias(DirectoryLdapStub::class, 'FOG\\Net\\FOGLdap', false);

$directoryDbName = 'agent-directory-placement-flow-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
$manifest = $GLOBALS['manifest'];
directoryTestBindPdodb($pdo);
require __DIR__ . '/lib/directory-intent-fixture.php';
$t = new FogChecks();

// Placement reads host models and writes an audit row: build what is missing.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($manifest['tables'] as $table) {
    $pdo->exec($table['create']);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$rootDN = 'DC=corp,DC=example';
$managedDN = 'OU=Managed,' . $rootDN;
DirectoryLdapStub::$context = ['domain' => 'corp.example', 'base_dn' => $rootDN, 'dc' => 'dc1.corp.example',
    'naming_contexts' => [$rootDN, 'CN=Configuration,' . $rootDN, 'CN=Schema,CN=Configuration,' . $rootDN]];

$setBase = static function ($baseDN) {
    $cache = [];
    foreach ([
        'FOG_DIRECTORY_PLACEMENT_ENABLED' => '1', 'FOG_DIRECTORY_LDAP_URI' => 'ldaps://dc1.corp.example',
        'FOG_DIRECTORY_BIND_DN' => 'cn=fixture,dc=corp,dc=example', 'FOG_DIRECTORY_BIND_PASSWORD' => 'fictional-password',
        'FOG_DIRECTORY_CA_CERT' => '', 'FOG_DIRECTORY_BASE_DN' => $baseDN
    ] as $key => $value) {
        $cache[$key] = ['value' => $value, 'ts' => time()];
    }
    FogTestHarness::setStatic('FOGBase', '_settingsCache', $cache);
    FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
};

/**
 * A joined host whose object sits at $ldapDN in the directory while the
 * database still says $dbDN. The host asks for the Managed OU.
 */
$create = static function ($ldapDN, $dbDN, $baseDN) use ($pdo, $managedDN, $setBase) {
    static $n = 0;
    $id = 5000 + ++$n;
    $name = 'PF' . $id;
    $ldapDN = str_replace('{name}', $name, $ldapDN);
    $dbDN = str_replace('{name}', $name, $dbDN);
    $fingerprint = hash('sha256', 'pf' . $id);
    $observedAt = gmdate('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)')
        ->execute([$id, $name, 'CORP', $managedDN, $fingerprint]);
    $pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt) VALUES (?,1,\'domain\',?,?,?,?)')
        ->execute([$id, 'CORP', $dbDN, $name . '$', $observedAt]);
    $host = (new FOG\Items\Host())->set('id', $id)->set('name', $name)->set('ADDomain', 'CORP')
        ->set('ADOU', $managedDN)->set('useAD', 1)->set('agentFingerprint', $fingerprint);
    $directory = (new FOG\Items\HostDirectory())->set('hostID', $id)->set('joined', 1)->set('kind', 'domain')
        ->set('domain', 'CORP')->set('computerDN', $dbDN)->set('machineAccount', $name . '$')
        ->set('observedAt', $observedAt)->set('placementAt', null)->set('placementError', '');
    $record = ['guid' => intentFixtureGuid(), 'sid' => 'S-1-5-21-44001-' . $id, 'account' => $name . '$', 'dn' => $ldapDN];
    $destination = ['guid' => intentFixtureGuid(), 'dn' => $managedDN];
    $setBase($baseDN);
    DirectoryLdapStub::reset();
    DirectoryLdapStub::$containerRecord = $destination;
    DirectoryLdapStub::$records[strtolower($name)] = ['status' => 'found', 'record' => $record];
    return compact('id', 'name', 'host', 'directory', 'record', 'destination');
};
$ensure = static function (array $f) {
    FOG\Agent\DirectoryPlacement::ensure($f['host'], $f['directory']);
};
$dirRow = static function ($id) use ($pdo) {
    return intentFixtureAll($pdo, 'SELECT hdComputerDN,hdObservedAt,hdPlacementAt,hdPlacementError FROM hostDirectory WHERE hdHostID = ?', [$id])[0];
};
$recordFor = static function (array $f, $dn = null) {
    $record = $f['record'];
    if (null !== $dn) {
        $record['dn'] = $dn;
    }
    return ['status' => 'found', 'record' => $record];
};

// A legacy NetBIOS domain with a search base below the naming context root.
$legacy = $create('CN={name},OU=Devices,' . $rootDN, '', 'OU=Devices,' . $rootDN);
DirectoryLdapStub::$recordQueues[strtolower($legacy['name'])] = [$recordFor($legacy), $recordFor($legacy)];
$ensure($legacy);
$row = $dirRow($legacy['id']);
$t->check(
    'a Move is sent once, by GUID, with the RDN, and the intent ends SUCCEEDED',
    1 === DirectoryLdapStub::$connects && 1 === count(DirectoryLdapStub::$moves)
        && DirectoryLdapStub::guidDN($legacy['record']['guid']) === DirectoryLdapStub::$moves[0]['entry']
        && DirectoryLdapStub::guidDN($legacy['destination']['guid']) === DirectoryLdapStub::$moves[0]['parent']
        && 'CN=' . $legacy['name'] === DirectoryLdapStub::$moves[0]['rdn']
        && 'SUCCEEDED' === intentFixtureStatus($pdo, $legacy['record']['guid'])
);
$t->check(
    'the stored DN is the new one and the search scope was the configured subtree',
    'CN=' . $legacy['name'] . ',' . $managedDN === $row['hdComputerDN'] && '' === $row['hdPlacementError']
        && 'OU=Devices,' . $rootDN === (DirectoryLdapStub::$containers[0]['search_base'] ?? null)
);

// Already in the right OU: the host learns its DN and nothing is recorded or sent.
foreach ([['empty-db-dn', ''], ['stale-db-dn', 'CN={name},OU=Old,' . $rootDN]] as list($label, $storedDN)) {
    $learn = $create('CN={name},' . $managedDN, $storedDN, $rootDN);
    $actual = 'CN=' . $learn['name'] . ',' . $managedDN;
    DirectoryLdapStub::$records[strtolower($learn['name'])] = $recordFor($learn, $actual);
    $ensure($learn);
    $learned = $dirRow($learn['id']);
    $connects = DirectoryLdapStub::$connects;
    $learn['directory'] = (new FOG\Items\HostDirectory())->set('hostID', $learn['id'])->set('joined', 1)->set('kind', 'domain')
        ->set('domain', 'CORP')->set('computerDN', $learned['hdComputerDN'])->set('machineAccount', $learn['name'] . '$')
        ->set('observedAt', $learned['hdObservedAt'])->set('placementAt', $learned['hdPlacementAt'])->set('placementError', '');
    $ensure($learn);
    $t->check(
        $label . ': the DN is learned, no Move and no intent row, and the next check-in does not dial the directory',
        $actual === $learned['hdComputerDN'] && !empty($learned['hdPlacementAt']) && '' === $learned['hdPlacementError']
            && 0 === count(DirectoryLdapStub::$moves) && $connects === DirectoryLdapStub::$connects
            && null === intentFixtureRow($pdo, $learn['record']['guid'])
    );
}

// UNKNOWN holds even when a fresh read finds the object already at the destination.
$held = $create('CN={name},' . $managedDN, 'CN={name},OU=Old,' . $rootDN, $rootDN);
DirectoryLdapStub::$records[strtolower($held['name'])] = $recordFor($held, 'CN=' . $held['name'] . ',' . $managedDN);
$json = json_encode(['version' => 1, 'host_id' => $held['id'], 'target' => $held['record']], JSON_UNESCAPED_SLASHES);
$pdo->prepare('INSERT INTO directoryPlacementIntent (dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult,dpiUpdated) VALUES (?,?,?,1,\'UNKNOWN\',?,?,?,UTC_TIMESTAMP(6))')
    ->execute([intentFixtureBytes($held['record']['guid']), $held['record']['sid'], $held['id'], $json, hash('sha256', $json), 'fixture unknown']);
$before = $dirRow($held['id']);
$ensure($held);
$after = $dirRow($held['id']);
$t->check(
    'an UNKNOWN row for the same object is kept and no DN is learned over it',
    'UNKNOWN' === intentFixtureStatus($pdo, $held['record']['guid'])
        && 'fixture unknown' === intentFixtureRow($pdo, $held['record']['guid'])['dpiResult']
        && $before['hdComputerDN'] === $after['hdComputerDN'] && 0 === count(DirectoryLdapStub::$moves)
);

// An admin edits the OU while the directory is being read.
$edit = $create('CN={name},OU=Devices,' . $rootDN, 'CN={name},OU=Old,' . $rootDN, $rootDN);
$applied = false;
DirectoryLdapStub::$onContainer = static function () use ($edit, $rootDN, &$applied) {
    $applied = (new FOG\Managers\HostManager())->update(['id' => $edit['id']], 'AND', ['ADOU' => 'OU=Changed,' . $rootDN]);
};
$ensure($edit);
$t->check(
    'an OU edit during the directory read is refused before any intent or Move',
    true === $applied && null === intentFixtureRow($pdo, $edit['record']['guid']) && 0 === count(DirectoryLdapStub::$moves)
        && 'placement failed: directory_placement_request_changed' === $dirRow($edit['id'])['hdPlacementError']
);

// The target or destination identity changes between prepare and dispatch.
foreach (['target-guid-changed', 'destination-guid-changed'] as $case) {
    $changed = $create('CN={name},' . $rootDN, 'CN={name},OU=Old,' . $rootDN, $rootDN);
    if ('target-guid-changed' === $case) {
        $replacement = $recordFor($changed);
        $replacement['record']['guid'] = intentFixtureGuid();
        $replacement['record']['sid'] = 'S-1-5-21-44002-' . $changed['id'];
        DirectoryLdapStub::$recordQueues[strtolower($changed['name'])] = [$recordFor($changed), $replacement];
    } else {
        DirectoryLdapStub::$containerQueue = [
            ['status' => 'found', 'guid' => $changed['destination']['guid'], 'dn' => $managedDN],
            ['status' => 'found', 'guid' => intentFixtureGuid(), 'dn' => $managedDN]
        ];
    }
    $ensure($changed);
    $row = intentFixtureRow($pdo, $changed['record']['guid']);
    $t->check(
        $case . ': the PREPARED row is canceled and nothing is sent',
        is_array($row) && 'CANCELED' === $row['dpiStatus'] && 1 === (int)$row['dpiGeneration']
            && 0 === count(DirectoryLdapStub::$moves)
            && 'placement failed: directory_placement_identity_changed_before_dispatch' === $dirRow($changed['id'])['hdPlacementError']
    );
}

// The directory refuses or the answer is lost: UNKNOWN, and no second Move ever.
$lost = $create('CN={name},' . $rootDN, 'CN={name},OU=Old,' . $rootDN, $rootDN);
DirectoryLdapStub::$moveResult = false;
DirectoryLdapStub::$recordQueues[strtolower($lost['name'])] = [$recordFor($lost), $recordFor($lost)];
$ensure($lost);
$row = intentFixtureRow($pdo, $lost['record']['guid']);
$t->check(
    'a Move that did not succeed leaves an UNKNOWN row with the outcome recorded',
    1 === count(DirectoryLdapStub::$moves) && 'UNKNOWN' === $row['dpiStatus']
        && 'ldap_success' !== (json_decode((string)$row['dpiResult'], true)['ldap_result'] ?? null)
        && 'placement failed: directory_placement_result_uncertain' === $dirRow($lost['id'])['hdPlacementError']
);
$pdo->prepare('UPDATE hostDirectory SET hdPlacementAt = NULL WHERE hdHostID = ?')->execute([$lost['id']]);
$lost['directory']->set('placementAt', null);
DirectoryLdapStub::$moveResult = true;
$ensure($lost);
$t->check(
    'with the cooldown cleared, an UNKNOWN row still stops another Move',
    1 === count(DirectoryLdapStub::$moves) && 'UNKNOWN' === intentFixtureStatus($pdo, $lost['record']['guid'])
        && 'placement failed: directory_placement_unresolved' === $dirRow($lost['id'])['hdPlacementError']
);

$t->finish();
