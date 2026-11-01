<?php
/** Real-PDO Route::_joiningUpdate preflight before host/association writes. */
use FOG\Router\Route;

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 joining preflight: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('U2-P2 joining preflight requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-joining-preflight');
$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('U2-P2 joining preflight requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
}

// Use real PDODB, real Host models and Route's real database-backed list path.
$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

$admin = (new FOG\Items\User())->set('id', 1)->set('name', 'u2-p2-w13-admin');
$GLOBALS['currentUser'] = $admin;
foreach (['FOGBase', 'Authorization', 'Route'] as $class) {
    FogTestHarness::setStatic($class, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

$t = new FogChecks();
$fixtures = [];
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$createHost = static function ($prefix, $fixedName = null) use ($pdo, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
    } while ((int)$q->fetchColumn() !== 0);
    $name = null === $fixedName ? $prefix . strtoupper(bin2hex(random_bytes(5))) : $fixedName;
    $domain = 'u2p2.invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $host = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?, ?, \'1\', ?, ?, ?)'
    );
    $host->execute([$hostID, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[] = ['host_id' => $hostID, 'directory_id' => null];
    return [
        'id' => $hostID,
        'name' => $name,
        'domain' => $domain,
        'fingerprint' => $fingerprint,
        'directory_id' => null
    ];
};
$read = static function ($table, $column, $id) use ($pdo) {
    if (!in_array($table, ['hosts', 'hostDirectory'], true)
        || !in_array($column, ['hostID', 'hdID'], true)) {
        throw new \InvalidArgumentException('fixture row selector invalid');
    }
    $q = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $column . '`=?');
    $q->execute([$id]);
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};

try {
    $idle = $createHost('P2W13A');
    $attemptID = bin2hex(random_bytes(16));
    $pendingName = 'P2W13Z' . strtoupper(bin2hex(random_bytes(5)));
    $expectedCurrent = 'P2W13O' . strtoupper(bin2hex(random_bytes(5)));
    $protected = $createHost('P2W13Z', $pendingName);
    $protectedState = [
        'version' => 1,
        'protocol' => 2,
        'phase' => 'started',
        'attempt_id' => $attemptID,
        'host_id' => $protected['id'],
        'fingerprint' => $protected['fingerprint'],
        'operation' => 'rename',
        'mode' => 'existing',
        'name' => $pendingName,
        'expected_current' => $expectedCurrent,
        'expected_pending' => $pendingName,
        'target' => [
            'guid' => $guid(),
            'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
            'account' => $pendingName . '$'
        ]
    ];
    // Add a legitimate started marker for the second host.
    $directory = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,?)'
    );
    $directory->execute([
        $protected['id'], $protected['domain'],
        'CN=' . $protected['name'] . ',OU=Managed,DC=u2p2,DC=invalid',
        $protected['name'] . '$', json_encode($protectedState, JSON_UNESCAPED_SLASHES)
    ]);
    $lookup = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $lookup->execute([$protected['id']]);
    $protected['directory_id'] = (int)$lookup->fetchColumn();
    $fixtures[array_key_last($fixtures)]['directory_id'] = $protected['directory_id'];

    $idleBefore = $read('hosts', 'hostID', $idle['id']);
    $protectedBefore = $read('hosts', 'hostID', $protected['id']);
    $protectedDirectoryBefore = $read('hostDirectory', 'hdID', $protected['directory_id']);
    $newName = 'P2W13N' . strtoupper(bin2hex(random_bytes(5)));
    $newMac = '02:' . implode(':', str_split(strtoupper(bin2hex(random_bytes(5))), 2));
    $macCount = static function () use ($pdo, $newMac, $idle, $protected) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM hostMAC WHERE hmHostID IN (?,?) AND hmMAC=?');
        $q->execute([$idle['id'], $protected['id'], $newMac]);
        return (int)$q->fetchColumn();
    };

    // Make the first ID/name the ordinary host and the second the protected
    // one. Route's real listem() orders by host name; neither is a fake row.
    $ids = [$idle['id'], $protected['id']];
    $classVars = FogTestHarness::callStatic('Route', '_classVars', ['host']);
    $body = (object)[
        'ids' => $ids,
        'name' => $newName,
        'macs' => [$newMac]
    ];
    $failure = null;
    try {
        Route::asValue(static function () use ($classVars, $body) {
            FogTestHarness::callStatic('Route', '_joiningUpdate', ['host', $classVars, $body]);
        });
    } catch (\Throwable $e) {
        $failure = $e;
    }
    $idleAfter = $read('hosts', 'hostID', $idle['id']);
    $protectedAfter = $read('hosts', 'hostID', $protected['id']);
    $protectedDirectoryAfter = $read('hostDirectory', 'hdID', $protected['directory_id']);
    $t->check(
        'joining update refuses the protected target during the all-target preflight',
        $failure instanceof FOG\Agent\DirectoryFenceRefusal
            && in_array($failure->getMessage(), [
                'directory_host_operation_protected',
                'directory_name_operation_protected',
                'directory_target_operation_in_progress'
            ], true)
    );
    $t->check(
        'ordinary first host was not renamed before the protected later host was checked',
        $idleBefore === $idleAfter
    );
    $t->check(
        'protected host and its authority row remain byte-for-byte unchanged',
        $protectedBefore === $protectedAfter
            && $protectedDirectoryBefore === $protectedDirectoryAfter
    );
    $t->check(
        'join association field did not insert a related hostMAC row before preflight completed',
        0 === $macCount()
    );

} finally {
    foreach (array_reverse($fixtures) as $fixture) {
        if (!empty($fixture['host_id'])) {
            // Restrict cleanup to the generated host IDs; delete dependent
            // fixture rows explicitly even if the scratch schema lacks FK CASCADE.
            $delete = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $delete->execute([$fixture['host_id']]);
            $delete = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $delete->execute([$fixture['host_id']]);
        }
    }
}

$t->finish();
