<?php
/**
 * Focused real-DB writer guards for the U2-P2 directory authority boundary.
 * This adds fixture rows only; it never creates or alters schema and never
 * connects to LDAP. Run only against an explicitly approved fog_mj1 scratch DB.
 */
$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 writers: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('U2-P2 writers requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-writers');
$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('U2-P2 writers requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
}

// Use the actual PDODB instance and PDO path used by the application and its
// DirectoryNameFence SQL callback. No fake DB or source-order assertion.
$link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

$t = new FogChecks();
$fixtures = [];
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$createFixture = static function ($active = false, $readyMode = null) use ($pdo, $guid, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
    } while ((int)$q->fetchColumn() !== 0);
    $hostName = 'P2W' . strtoupper(bin2hex(random_bytes(6)));
    $domain = 'u2p2.invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $targetName = 'T' . strtoupper(bin2hex(random_bytes(6)));
    // A started rename's saved current name is already released in hosts;
    // the host row carries the pending name that the claim is bound to.
    $expectedCurrent = 'O' . strtoupper(bin2hex(random_bytes(6)));
    $hasState = $active || in_array($readyMode, ['legacy', 'v2'], true);
    $storedName = $hasState ? $targetName : $hostName;
    $state = null;
    if ($hasState) {
        $stateData = [
            'version' => 1,
            'phase' => $active ? 'started' : 'ready',
            'attempt_id' => bin2hex(random_bytes(16)),
            'host_id' => $hostID,
            'fingerprint' => $fingerprint,
            'operation' => 'rename',
            'mode' => 'existing',
            'name' => $targetName,
            'expected_current' => $expectedCurrent,
            'expected_pending' => $targetName,
            'target' => [
                'guid' => $guid(),
                'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
                'account' => $targetName . '$'
            ]
        ];
        // Protocol 1 was the legacy schema with no protocol field. Protocol
        // 2 ready plans are intentionally distinguishable and unclaimed.
        if (!$readyMode || 'legacy' !== $readyMode) {
            $stateData['protocol'] = 2;
        }
        $state = json_encode($stateData);
    }
    $fixtures[] = ['host_id' => $hostID, 'host_inserted' => false, 'directory_inserted' => false];
    $index = array_key_last($fixtures);
    $hostInsert = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,\'1\',?,?,?)'
    );
    $hostInsert->execute([$hostID, $storedName, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[$index]['host_inserted'] = true;
    $directoryInsert = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,?)'
    );
    $directoryInsert->execute([
        $hostID,
        $domain,
        'CN=' . $storedName . ',OU=Managed,DC=u2p2,DC=invalid',
        $storedName . '$',
        $state
    ]);
    $fixtures[$index]['directory_inserted'] = true;
    $q = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    return [
        'id' => $hostID,
        'directory_id' => (int)$q->fetchColumn(),
        'name' => $storedName,
        'expected_current' => $expectedCurrent,
        'fingerprint' => $fingerprint,
        'target_name' => $targetName,
        'active' => (bool)$active
    ];
};
$raw = static function ($table, $column, $id) use ($pdo) {
    if (!in_array($table, ['hosts', 'hostDirectory'], true)
        || !in_array($column, ['hostID', 'hdID'], true)) {
        throw new InvalidArgumentException('raw fixture selector invalid');
    }
    $q = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $column . '`=?');
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
};
$blocked = static function (callable $fn) {
    try {
        $fn();
    } catch (Throwable $e) {
        return $e;
    }
    return null;
};

try {
    $protected = $createFixture(true);
    $protectedBefore = $raw('hostDirectory', 'hdID', $protected['directory_id']);
    $protectedHash = hash('sha256', serialize($protectedBefore));

    // The standalone model DELETE reaches FOGController::destroy -> PDODB.
    $deleteResult = null;
    $deleteError = $blocked(static function () use ($protected, &$deleteResult) {
        $deleteResult = (new FOG\Items\HostDirectory($protected['directory_id']))->destroy();
    });
    $t->check(
        'active hostDirectory standalone DELETE is refused and raw row is unchanged',
        (false === $deleteResult || $deleteError instanceof Throwable)
            && hash_equals($protectedHash, hash('sha256', serialize($raw('hostDirectory', 'hdID', $protected['directory_id']))))
    );

    $hdManager = new FOG\Managers\HostDirectoryManager();
    $reparented = $hdManager->update(
        ['hostID' => $protected['id']],
        'AND',
        ['hostID' => $protected['id'] + 1]
    );
    $t->check(
        'active hostDirectory manager reparent is refused without raw mutation',
        false === $reparented
            && hash_equals($protectedHash, hash('sha256', serialize($raw('hostDirectory', 'hdID', $protected['directory_id']))))
    );
    foreach (['rejoinConfig' => '{"enabled":false}', 'rejoinState' => null] as $field => $value) {
        $result = $hdManager->update(['hostID' => $protected['id']], 'AND', [$field => $value]);
        $t->check(
            'generic manager overwrite of ' . $field . ' is refused and raw row is unchanged',
            false === $result
                && hash_equals($protectedHash, hash('sha256', serialize($raw('hostDirectory', 'hdID', $protected['directory_id']))))
        );
    }

    // Ordinary observed facts still pass via the model save path, while its
    // authority columns remain outside that generic writer.
    $facts = $createFixture(false);
    $factsBefore = $raw('hostDirectory', 'hdID', $facts['directory_id']);
    foreach (['rejoinConfig' => '{"enabled":false}', 'rejoinState' => '{"version":1}'] as $field => $value) {
        $result = $hdManager->update(['hostID' => $facts['id']], 'AND', [$field => $value]);
        $t->check(
            'generic manager cannot overwrite idle ' . $field . ' authority field',
            false === $result
                && $factsBefore === $raw('hostDirectory', 'hdID', $facts['directory_id'])
        );
    }
    $factsItem = (new FOG\Items\HostDirectory($facts['directory_id']))
        ->set('domain', 'facts.u2p2.invalid')
        ->set('observedAt', gmdate('Y-m-d H:i:s'))
        ->set('computerDN', 'CN=' . $facts['name'] . ',OU=Facts,DC=u2p2,DC=invalid');
    $factsSave = $factsItem->save();
    $factsAfter = $raw('hostDirectory', 'hdID', $facts['directory_id']);
    $t->check(
        'normal HostDirectory facts save through model and real PDODB',
        false !== $factsSave && is_array($factsAfter)
            && 'facts.u2p2.invalid' === $factsAfter['hdDomain']
            && $factsBefore['hdRejoinConfig'] === $factsAfter['hdRejoinConfig']
            && $factsBefore['hdRejoinState'] === $factsAfter['hdRejoinState']
    );

    // Exercise the ordinary non-PREPARED insert path through both full
    // models. Record each generated ID immediately after save returns, before
    // any assertion, so finally can remove only rows created by these saves.
    $savedHostName = 'P2S' . strtoupper(bin2hex(random_bytes(4)));
    $savedHostFingerprint = hash('sha256', random_bytes(32));
    $savedHost = (new FOG\Items\Host())
        ->set('name', $savedHostName)
        ->set('useAD', '1')
        ->set('ADDomain', 'u2p2.invalid')
        ->set('ADOU', 'OU=ModelSave,DC=u2p2,DC=invalid')
        ->set('agentFingerprint', $savedHostFingerprint);
    $savedHostResult = $savedHost->save();
    $savedHostID = (int)$savedHost->get('id');
    if ($savedHostID > 0) {
        $savedHostRow = $pdo->prepare('SELECT hostID FROM hosts WHERE hostID=? AND hostName=?');
        $savedHostRow->execute([$savedHostID, $savedHostName]);
        if ($savedHostRow->fetchColumn()) {
            $fixtures[] = [
                'host_id' => $savedHostID,
                'host_inserted' => true,
                'directory_inserted' => false
            ];
        }
    }
    $savedHostRaw = $savedHostID > 0 ? $raw('hosts', 'hostID', $savedHostID) : null;
    $t->check(
        'ordinary Host::save creates a new row with its real auto-increment ID',
        $savedHostResult instanceof FOG\Items\Host
            && $savedHostID > 0
            && (int)$savedHostResult->get('id') === $savedHostID
            && is_array($savedHostRaw)
            && $savedHostRaw['hostName'] === $savedHostName
    );

    $savedDirectory = (new FOG\Items\HostDirectory())
        ->set('hostID', $savedHostID)
        ->set('joined', 1)
        ->set('kind', 'domain')
        ->set('domain', 'model-save.u2p2.invalid')
        ->set('computerDN', 'CN=' . $savedHostName . ',OU=ModelSave,DC=u2p2,DC=invalid')
        ->set('machineAccount', $savedHostName . '$')
        ->set('observedAt', gmdate('Y-m-d H:i:s'));
    $savedDirectoryResult = $savedDirectory->save();
    $savedDirectoryID = (int)$savedDirectory->get('id');
    if ($savedDirectoryID > 0) {
        $savedDirectoryRow = $pdo->prepare(
            'SELECT hdID FROM hostDirectory WHERE hdID=? AND hdHostID=?'
        );
        $savedDirectoryRow->execute([$savedDirectoryID, $savedHostID]);
        if ($savedDirectoryRow->fetchColumn()) {
            foreach ($fixtures as &$savedFixture) {
                if ($savedFixture['host_id'] === $savedHostID) {
                    $savedFixture['directory_inserted'] = true;
                    $savedFixture['directory_id'] = $savedDirectoryID;
                    break;
                }
            }
            unset($savedFixture);
        }
    }
    $savedDirectoryRaw = $savedDirectoryID > 0
        ? $raw('hostDirectory', 'hdID', $savedDirectoryID) : null;
    $t->check(
        'ordinary HostDirectory::save inserts facts and returns the real new hdID',
        $savedDirectoryResult instanceof FOG\Items\HostDirectory
            && $savedDirectoryID > 0
            && (int)$savedDirectoryResult->get('id') === $savedDirectoryID
            && is_array($savedDirectoryRaw)
            && (int)$savedDirectoryRaw['hdHostID'] === $savedHostID
            && 'model-save.u2p2.invalid' === $savedDirectoryRaw['hdDomain']
            && 1 === (int)$savedDirectoryRaw['hdJoined']
    );

    // Actual Host::destroy follows Route::deletemass and its dependency
    // cascade. The active rename blocks before any host/cascade row is changed.
    $hostBefore = $raw('hosts', 'hostID', $protected['id']);
    $activeDestroyResult = null;
    $hostDeleteError = $blocked(static function () use ($protected, &$activeDestroyResult) {
        FOG\Router\Route::asValue(static function () use ($protected, &$activeDestroyResult) {
            $activeDestroyResult = (new FOG\Items\Host($protected['id']))->destroy();
        });
    });
    $t->check(
        'active Host whole DELETE is rejected before host or directory mutation',
        $hostDeleteError instanceof RuntimeException
            && false !== strpos($hostDeleteError->getMessage(), 'directory_host_operation_protected')
            && null === $activeDestroyResult
            && $hostBefore === $raw('hosts', 'hostID', $protected['id'])
            && hash_equals($protectedHash, hash('sha256', serialize($raw('hostDirectory', 'hdID', $protected['directory_id']))))
    );

    $inactive = $createFixture(false);
    $inactiveHost = null;
    FOG\Router\Route::asValue(static function () use ($inactive, &$inactiveHost) {
        $inactiveHost = (new FOG\Items\Host($inactive['id']))->destroy();
    });
    $t->check(
        'inactive Host whole DELETE reaches the real decommission cascade',
        $inactiveHost instanceof FOG\Items\Host
            && null === $raw('hosts', 'hostID', $inactive['id'])
    );

    // A claimed STARTED rename owns both names. A second host cannot be
    // reassigned to the saved current name through HostManager.
    $reassigner = $createFixture(false);
    $reassignBefore = $raw('hosts', 'hostID', $reassigner['id']);
    $hostManager = new FOG\Managers\HostManager();
    $reassign = $hostManager->update(
        ['id' => $reassigner['id']],
        'AND',
        ['name' => $protected['expected_current']]
    );
    $t->check(
        'started rename prevents another host from taking expected_current name',
        false === $reassign && $reassignBefore === $raw('hosts', 'hostID', $reassigner['id'])
    );

    // A multi-payload update has a safe first row and a protected later row.
    // Its initial preflight must inspect the entire selected PK set before the
    // first SQL update can change either row. The broad original WHERE also
    // includes a second idle row so a fixed-ID narrowing bug is observable.
    $multiSafe = $createFixture(false);
    $multiProtected = $createFixture(true);
    $multiSafeBefore = $raw('hosts', 'hostID', $multiSafe['id']);
    $multiProtectedBefore = $raw('hosts', 'hostID', $multiProtected['id']);
    $multiResult = $hostManager->update(
        ['id' => [$multiSafe['id'], $multiProtected['id']]],
        'OR',
        [
            ['name' => 'SAFE' . strtoupper(bin2hex(random_bytes(4)))],
            ['ADOU' => 'OU=Changed,DC=u2p2,DC=invalid']
        ]
    );
    $t->check(
        'multi-payload HostManager initial guard rejects a later protected tuple before any row changes',
        false === $multiResult
            && $multiSafeBefore === $raw('hosts', 'hostID', $multiSafe['id'])
            && $multiProtectedBefore === $raw('hosts', 'hostID', $multiProtected['id'])
    );

    // A protocol-2 ready plan is not yet a native claim, so the owner-idle
    // check remains available. Run this before inserting the legacy marker,
    // whose fail-closed status intentionally blocks all later fence work.
    $v2Ready = $createFixture(false, 'v2');
    $v2ReadyAllowed = false;
    FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use ($v2Ready) {
        FOG\Agent\DirectoryNameFence::assertOwnerIdle($v2Ready['id']);
    });
    $v2ReadyAllowed = true;
    $t->check('protocol-2 ready unclaimed plan does not block its owner-idle check', $v2ReadyAllowed);

    // Protocol 1 is represented by the historical marker shape with no
    // protocol key. The legacy attempt ID makes even phase=ready uncertain.
    $legacyReady = $createFixture(false, 'legacy');
    $legacyBefore = $raw('hostDirectory', 'hdID', $legacyReady['directory_id']);
    $legacyError = $blocked(static function () use ($legacyReady) {
        FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use ($legacyReady) {
            FOG\Agent\DirectoryNameFence::assertOwnerIdle($legacyReady['id']);
        });
    });
    $t->check(
        'legacy protocol-1 ready attempt fails closed and preserves the raw marker',
        $legacyError instanceof FOG\Agent\DirectoryFenceRefusal
            && 'directory_fence_uncertain_authority' === $legacyError->getMessage()
            && $legacyBefore === $raw('hostDirectory', 'hdID', $legacyReady['directory_id'])
    );
} finally {
    // Scoped cleanup only for IDs inserted above. Direct PDO cleanup avoids
    // re-entering the very guards under test; it never truncates shared data.
    foreach ($fixtures as $fixture) {
        if (!empty($fixture['directory_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$fixture['host_id']]);
        }
        if (!empty($fixture['host_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$fixture['host_id']]);
        }
    }
}

echo "NOTE: this focused writer file does not cover the B01-B39 barrier matrix, multi-process/release/crash barriers, a 500+ row batch, actual-FK closure fixtures, or LDAP/native operations; those remain separate evidence items.\n";
$t->finish();
