<?php
/** Same Target native-operation exclusion for placement-intent preparation. */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 native-target placement: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('native-target placement requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-native-target-placement');

$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('native-target placement requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
}

$link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

$t = new FogChecks();
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$guidBytes = static function ($value) {
    return hex2bin(str_replace('-', '', strtolower($value)));
};
$createHost = static function ($prefix) use ($pdo) {
    do {
        $id = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$id]);
        $exists = (int)$q->fetchColumn();
        $q->closeCursor();
    } while (0 !== $exists);

    $name = $prefix . strtoupper(bin2hex(random_bytes(5)));
    $domain = 'u2p2.invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $q = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,1,?,?,?)'
    );
    $q->execute([$id, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $q = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,NULL)'
    );
    $q->execute([$id, $domain, 'CN=' . $name . ',OU=Legacy,DC=u2p2,DC=invalid', $name . '$']);

    $host = (new FOG\Items\Host())
        ->set('id', $id)
        ->set('name', $name)
        ->set('ADDomain', $domain)
        ->set('ADOU', 'OU=Managed,DC=u2p2,DC=invalid')
        ->set('useAD', 1)
        ->set('agentFingerprint', $fingerprint);
    return ['id' => $id, 'name' => $name, 'domain' => $domain, 'host' => $host];
};
$setRejoinState = static function (array $fixture, array $state) {
    FOG\Agent\DirectoryRejoin::change($fixture['host'], static function (&$config, &$saved) use ($state) {
        $saved = $state;
    });
};
$readAuthority = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    $q->closeCursor();
    return $row ?: null;
};
$intentCount = static function ($targetGuid) use ($pdo, $guidBytes) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM directoryPlacementIntent WHERE dpiGUID=?');
    $q->bindValue(1, $guidBytes($targetGuid), PDO::PARAM_LOB);
    $q->execute();
    $count = (int)$q->fetchColumn();
    $q->closeCursor();
    return $count;
};

$placement = null;
$other = null;
$targetGuid = $guid();
$targetSID = 'S-1-5-21-1048576-' . random_int(100000, 999999999);
$target = null;
try {
    $placement = $createHost('P2PT');
    $other = $createHost('P2OT');
    $target = [
        'guid' => $targetGuid,
        'sid' => $targetSID,
        'account' => $placement['name'] . '$',
        'dn' => 'CN=' . $placement['name'] . ',OU=Legacy,DC=u2p2,DC=invalid'
    ];
    $snapshotMethod = new ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
    $snapshotMethod->setAccessible(true);
    $snapshot = $snapshotMethod->invoke(null, $pdo, $placement['id']);
    $payload = [
        'version' => 1,
        'host_id' => $placement['id'],
        'snapshot' => $snapshot,
        'target' => $target,
        'destination' => ['guid' => $guid(), 'dn' => 'OU=Managed,DC=u2p2,DC=invalid'],
        'dc' => 'dc1.u2p2.invalid',
        'nc' => 'DC=u2p2,DC=invalid',
        'rdn' => 'CN=' . $placement['name']
    ];

    foreach (['started', 'awaiting_reboot', 'unknown', 'failed'] as $phase) {
        $state = [
            'version' => 1,
            'protocol' => 2,
            'host_id' => $other['id'],
            'attempt_id' => bin2hex(random_bytes(16)),
            'phase' => $phase,
            'operation' => 'join',
            'name' => 'OTHER' . strtoupper(bin2hex(random_bytes(3))),
            'fingerprint' => hash('sha256', random_bytes(32)),
            'target' => ['guid' => $targetGuid, 'sid' => $targetSID]
        ];
        $setRejoinState($other, $state);
        $authorityBefore = $readAuthority($other['id']);
        $rowsBefore = $intentCount($targetGuid);
        $reason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::prepare($payload);
        } catch (FOG\Agent\DirectoryFenceRefusal $e) {
            $reason = $e->getMessage();
        }
        $authorityAfter = $readAuthority($other['id']);
        $rowsAfter = $intentCount($targetGuid);
        $t->check(
            'same GUID/SID with other Host phase ' . $phase . ' refuses placement and preserves authority',
            'directory_placement_native_in_progress' === $reason
                && $authorityBefore === $authorityAfter
                && $rowsBefore === $rowsAfter
        );
    }

    // A terminal legacy state with no in-flight attempt on a different Target
    // is not a global placement ban.
    $legacyState = [
        'version' => 1,
        'phase' => 'verified',
        'operation' => 'join',
        'host_id' => $other['id'],
        'name' => $other['name'],
        'target' => ['guid' => $guid(), 'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999)]
    ];
    $setRejoinState($other, $legacyState);
    $authorityBefore = $readAuthority($other['id']);
    $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($payload);
    $authorityAfter = $readAuthority($other['id']);
    $q = $pdo->prepare('SELECT dpiStatus,dpiGeneration FROM directoryPlacementIntent WHERE dpiGUID=?');
    $q->bindValue(1, $guidBytes($targetGuid), PDO::PARAM_LOB);
    $q->execute();
    $intent = $q->fetch(PDO::FETCH_ASSOC);
    $q->closeCursor();
    $t->check(
        'unrelated terminal legacy state permits normal placement preparation',
        is_array($intent) && 'PREPARED' === $intent['dpiStatus']
            && 1 === (int)$intent['dpiGeneration'] && 1 === $ticket['generation']
            && $authorityBefore === $authorityAfter
    );
} finally {
    if (is_array($placement) && is_array($target)) {
        $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?');
        $q->bindValue(1, $guidBytes($targetGuid), PDO::PARAM_LOB);
        $q->execute();
    }
    foreach ([$other, $placement] as $fixture) {
        if (is_array($fixture)) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$fixture['id']]);
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$fixture['id']]);
        }
    }
}

echo "NOTE: scratch PDO only; DirectoryRejoin.change and placement preparation are real, with no LDAP/native request.\n";
$t->finish();
