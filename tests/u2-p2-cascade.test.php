<?php
/**
 * Real SQL FK-closure checks for the U2-P2 host decommission guard.
 * The test may add only the three approved CASCADE constraints when absent;
 * it drops only constraints it created. It never changes migration sources.
 */
$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 cascade: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('U2-P2 cascade requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-cascade');
$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('U2-P2 cascade requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
}

$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
$t = new FogChecks();

// Identifiers below are constants or generated from a fixed prefix and hex;
// no user-controlled identifier reaches DDL.
$edges = [
    ['table' => 'agentWake', 'column' => 'awTargetID', 'name' => 'fk_u2p2c_aw_target'],
    ['table' => 'agentWake', 'column' => 'awSenderID', 'name' => 'fk_u2p2c_aw_sender'],
    ['table' => 'hostDirectory', 'column' => 'hdHostID', 'name' => 'fk_u2p2c_hd_host']
];
$ownedConstraints = [];
$fixtures = [];
$fixtureToken = bin2hex(random_bytes(5));
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$raw = static function ($table, $column, $id) use ($pdo) {
    $allowed = ['hosts' => 'hostID', 'hostDirectory' => 'hdID', 'agentWake' => 'awID'];
    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        throw new \InvalidArgumentException('raw fixture selector invalid');
    }
    $q = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $column . '`=?');
    $q->execute([$id]);
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};
$ensureEdge = static function (array $edge) use ($pdo, &$ownedConstraints, $fixtureToken) {
    $q = $pdo->prepare(
        'SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, k.REFERENCED_TABLE_NAME, '
        . 'k.REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE k '
        . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
        . 'ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME '
        . 'AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME '
        . 'WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.COLUMN_NAME=?'
    );
    $q->execute([$edge['table'], $edge['column']]);
    $found = $q->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($found as $fk) {
        if ('hosts' === $fk['REFERENCED_TABLE_NAME'] && 'hostID' === $fk['REFERENCED_COLUMN_NAME']
            && 'CASCADE' === strtoupper($fk['DELETE_RULE'])) {
            return;
        }
    }
    if ($found) {
        throw new \RuntimeException('Existing FK for ' . $edge['table'] . '.' . $edge['column']
            . ' is incompatible; test will not replace it');
    }
    $orphans = (int)$pdo->query(
        'SELECT COUNT(*) FROM `' . $edge['table'] . '` c LEFT JOIN hosts h '
        . 'ON h.hostID=c.`' . $edge['column'] . '` WHERE h.hostID IS NULL'
    )->fetchColumn();
    if ($orphans) {
        throw new \RuntimeException('Cannot add approved FK while orphan rows exist in ' . $edge['table']);
    }
    $name = $edge['name'] . '_' . $fixtureToken;
    $pdo->exec('ALTER TABLE `' . $edge['table'] . '` ADD CONSTRAINT `' . $name . '` '
        . 'FOREIGN KEY (`' . $edge['column'] . '`) REFERENCES `hosts` (`hostID`) ON DELETE CASCADE');
    $ownedConstraints[] = ['table' => $edge['table'], 'name' => $name];
};
$createHost = static function ($active) use ($pdo, $guid, &$fixtures) {
    do {
        $id = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$id]);
    } while ((int)$q->fetchColumn() !== 0);
    $name = 'C' . strtoupper(bin2hex(random_bytes(6)));
    $domain = 'u2p2.invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $state = null;
    if ($active) {
        $state = json_encode([
            'version' => 1,
            'protocol' => 2,
            'phase' => 'started',
            'attempt_id' => bin2hex(random_bytes(16)),
            'host_id' => $id,
            'fingerprint' => $fingerprint,
            'operation' => 'rename',
            'mode' => 'existing',
            'name' => $name,
            'expected_current' => 'O' . strtoupper(bin2hex(random_bytes(5))),
            'expected_pending' => $name,
            'target' => [
                'guid' => $guid(),
                'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
                'account' => $name . '$'
            ]
        ]);
    }
    $fixtures[] = ['host_id' => $id, 'directory_id' => null, 'inserted_host' => false];
    $index = array_key_last($fixtures);
    $insert = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?, ?, \'1\', ?, ?, ?)'
    );
    $insert->execute([$id, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[$index]['inserted_host'] = true;
    $directory = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,?)'
    );
    $directory->execute([$id, $domain, 'CN=' . $name . ',OU=Managed,DC=u2p2,DC=invalid', $name . '$', $state]);
    $q = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$id]);
    $fixtures[$index]['directory_id'] = (int)$q->fetchColumn();
    return ['id' => $id, 'name' => $name, 'fingerprint' => $fingerprint, 'directory_id' => $fixtures[$index]['directory_id']];
};
$blocked = static function (callable $fn) {
    try {
        $fn();
    } catch (\Throwable $e) {
        return $e;
    }
    return null;
};

try {
    foreach ($edges as $edge) {
        $ensureEdge($edge);
    }

    // A blank facts row has no D2 authority marker, so this specifically
    // exercises the decommission-parent scope check rather than the separate
    // verified/active-authority refusal.
    $decommissionParent = $createHost(false);
    $outsideOwner = $createHost(false);
    $outsideDirectoryBefore = $raw('hostDirectory', 'hdID', $outsideOwner['directory_id']);
    $outsideDeleteResult = null;
    $outsideDeleteError = $blocked(static function () use (
        $decommissionParent,
        $outsideOwner,
        &$outsideDeleteResult
    ) {
        FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use (
            $decommissionParent,
            $outsideOwner,
            &$outsideDeleteResult
        ) {
            FOG\Agent\DirectoryNameFence::preflightDecommission('host', [$decommissionParent['id']]);
            $outsideDeleteResult = (new FOG\Items\HostDirectory($outsideOwner['directory_id']))->destroy();
        });
    });
    $t->check(
        'decommission context rejects standalone DELETE of another host blank facts row',
        null === $outsideDeleteError
            && false === $outsideDeleteResult
            && $outsideDirectoryBefore === $raw('hostDirectory', 'hdID', $outsideOwner['directory_id'])
    );

    $a = $createHost(false);
    $b = $createHost(true);
    $wake = $pdo->prepare('INSERT INTO agentWake (awTargetID,awSenderID) VALUES (?,?)');
    $wake->execute([$a['id'], $b['id']]);
    $wakeID = (int)$pdo->lastInsertId();

    $before = [
        'hostA' => $raw('hosts', 'hostID', $a['id']),
        'hostB' => $raw('hosts', 'hostID', $b['id']),
        'directoryA' => $raw('hostDirectory', 'hdID', $a['directory_id']),
        'directoryB' => $raw('hostDirectory', 'hdID', $b['directory_id']),
        'wake' => $raw('agentWake', 'awID', $wakeID)
    ];

    // A verified authority marker is protected from standalone row mutation,
    // including attempts to change its owner key through the manager.
    $verified = [
        'version' => 1,
        'protocol' => 2,
        'phase' => 'verified',
        'attempt_id' => bin2hex(random_bytes(16)),
        'host_id' => $a['id'],
        'fingerprint' => $a['fingerprint'],
        'operation' => 'rename',
        'mode' => 'existing',
        'name' => $a['name'],
        'expected_current' => 'OLD' . strtoupper(bin2hex(random_bytes(4))),
        'target' => ['guid' => $guid(), 'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999), 'account' => $a['name'] . '$']
    ];
    $pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=?')
        ->execute([json_encode($verified), $a['id']]);
    $authorityA = $raw('hostDirectory', 'hdID', $a['directory_id']);
    $standaloneResult = null;
    $standaloneError = $blocked(static function () use ($a, &$standaloneResult) {
        $standaloneResult = (new FOG\Items\HostDirectory($a['directory_id']))->destroy();
    });
    $t->check(
        'verified authority hostDirectory standalone DELETE is refused without raw mutation',
        null === $standaloneError && false === $standaloneResult
            && $authorityA === $raw('hostDirectory', 'hdID', $a['directory_id'])
    );
    $hdManager = new FOG\Managers\HostDirectoryManager();
    $reparent = $hdManager->update(['hostID' => $a['id']], 'AND', ['hostID' => $b['id']]);
    $t->check(
        'verified authority hostDirectory reparent is refused without raw mutation',
        false === $reparent && $authorityA === $raw('hostDirectory', 'hdID', $a['directory_id'])
    );
    $newHostKey = $a['id'] + 1000;
    $pkChange = (new FOG\Managers\HostManager())->update(['id' => $a['id']], 'AND', ['id' => $newHostKey]);
    $t->check(
        'verified authority parent primary-key change is refused without raw mutation',
        false === $pkChange
            && $before['hostA'] === $raw('hosts', 'hostID', $a['id'])
            && null === $raw('hosts', 'hostID', $newHostKey)
            && $authorityA === $raw('hostDirectory', 'hdID', $a['directory_id'])
    );

    // Restore the verified marker as fixture setup while retaining its status
    // as protected D2 history. B's started rename owns its shared wake row.
    $activeB = $before['directoryB'];
    $activeWake = $raw('agentWake', 'awID', $wakeID);
    $hostAItem = new FOG\Items\Host($a['id']);
    $routeError = $blocked(static function () use ($hostAItem) {
        FOG\Router\Route::asValue(static function () use ($hostAItem) {
            $hostAItem->destroy();
        });
    });
    $t->check(
        'actual Host::destroy refuses shared agentWake cascade while other endpoint has started rename',
        $routeError instanceof \RuntimeException
            && false !== strpos($routeError->getMessage(), 'directory_host_operation_protected')
            && $before['hostA'] === $raw('hosts', 'hostID', $a['id'])
            && $authorityA === $raw('hostDirectory', 'hdID', $a['directory_id'])
            && $activeB === $raw('hostDirectory', 'hdID', $b['directory_id'])
            && $activeWake === $raw('agentWake', 'awID', $wakeID)
    );

    // Mark B completed using fixture SQL, then prove the actual host delete
    // relies on the real FK cascade and leaves B's complete row untouched.
    $verifiedB = [
        'version' => 1,
        'protocol' => 2,
        'phase' => 'verified',
        'attempt_id' => bin2hex(random_bytes(16)),
        'host_id' => $b['id'],
        'fingerprint' => $b['fingerprint'],
        'operation' => 'rename',
        'mode' => 'existing',
        'name' => $b['name'],
        'expected_current' => 'OLD' . strtoupper(bin2hex(random_bytes(4))),
        'target' => ['guid' => $guid(), 'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999), 'account' => $b['name'] . '$']
    ];
    $pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdHostID=?')
        ->execute([json_encode($verifiedB), $b['id']]);
    $directoryBAfterVerification = $raw('hostDirectory', 'hdID', $b['directory_id']);
    $deleteResult = null;
    FOG\Router\Route::asValue(static function () use ($a, &$deleteResult) {
        $deleteResult = (new FOG\Items\Host($a['id']))->destroy();
    });
    $t->check(
        'inactive parent delete cascades its own hostDirectory and shared relay only',
        $deleteResult instanceof FOG\Items\Host
            && null === $raw('hosts', 'hostID', $a['id'])
            && null === $raw('hostDirectory', 'hdID', $a['directory_id'])
            && null === $raw('agentWake', 'awID', $wakeID)
            && $before['hostB'] === $raw('hosts', 'hostID', $b['id'])
            && $directoryBAfterVerification === $raw('hostDirectory', 'hdID', $b['directory_id'])
    );
} finally {
    // Delete only rows inserted by this test. Then drop only temporary FK
    // constraints this process recorded as newly created.
    try {
        foreach ($fixtures as $fixture) {
            if (null !== $fixture['directory_id']) {
                $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdID=?');
                $q->execute([$fixture['directory_id']]);
            }
            if (!empty($fixture['inserted_host'])) {
                $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
                $q->execute([$fixture['host_id']]);
            }
        }
    } finally {
        foreach (array_reverse($ownedConstraints) as $constraint) {
            $pdo->exec('ALTER TABLE `' . $constraint['table'] . '` DROP FOREIGN KEY `' . $constraint['name'] . '`');
        }
    }
}

echo "NOTE: this test uses real MySQL foreign-key closure and does not cover plugin-owned dependency-table fixtures.\n";
$t->finish();
