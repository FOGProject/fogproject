<?php
/** Real HookManager/Route ownership checks for U2-P2 decommission dependencies. */
$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 hook owner: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('U2-P2 hook-owner test requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-hook-owner');
$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('U2-P2 hook-owner test requires a fog_mj1-prefixed scratch DB');
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
$hostIDs = [];
$directoryHostIDs = [];
$imageIDs = [];
$ownedImageTypeIDs = [];
$ownedPartitionTypeIDs = [];
$ownedHookEventIDs = [];
$outsideOwnerID = 0;
$imageID = 0;
$hookMode = 'harmless';
$rootCallbackOrder = [];
$hookDispatches = 0;
$observerMaps = [];
$hookManager = new FOG\Base\HookManager();
$hookManager->register('DELETEMASS_API', static function (&$args) use (
    &$hookMode,
    &$rootCallbackOrder,
    &$hookDispatches,
    &$outsideOwnerID,
    &$imageID
) {
    $hookDispatches++;
    if ('host' !== strtolower((string)($args['classname'] ?? ''))) {
        return;
    }
    $rootCallbackOrder[] = 'mutator';
    if ('outside_hostdirectory' === $hookMode) {
        $args['removeItems']['hostdirectory'] = ['hostID' => [$outsideOwnerID]];
    } elseif ('unknown_image' === $hookMode) {
        $args['removeItems']['image'] = ['id' => [$imageID]];
    }
});
$hookManager->register('DELETEMASS_API', static function (&$args) use (
    &$rootCallbackOrder,
    &$hookDispatches,
    &$observerMaps
) {
    $hookDispatches++;
    if ('host' !== strtolower((string)($args['classname'] ?? ''))) {
        return;
    }
    $rootCallbackOrder[] = 'observer';
    $observerMaps[] = [
        'classname' => $args['classname'] ?? null,
        'removeItems' => $args['removeItems'] ?? null
    ];
});
FogTestHarness::setStatic('FOGBase', 'HookManager', $hookManager);

$name = static function () {
    return 'P2H' . strtoupper(bin2hex(random_bytes(5)));
};
$insertHost = static function ($description = '') use ($pdo, $name, &$hostIDs) {
    $q = $pdo->prepare(
        'INSERT INTO hosts (hostName,hostDesc,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?, ?, \'0\', \'\', \'\', \'\')'
    );
    $q->execute([$name(), $description]);
    $id = (int)$pdo->lastInsertId();
    $hostIDs[] = $id;
    return $id;
};
$rawHost = static function ($id) use ($pdo) {
    $q = $pdo->prepare('SELECT * FROM hosts WHERE hostID=?');
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
$hookEventNames = ['DESTROY_HOST', 'DELETEMASS_API'];

try {
    // HookManager records event names in the real hookEvents table. Seed only
    // missing catalog rows so processEvent does not create unrelated residue.
    foreach ($hookEventNames as $eventName) {
        $q = $pdo->prepare('SELECT heID FROM hookEvents WHERE heName=?');
        $q->execute([$eventName]);
        if (false === $q->fetchColumn()) {
            $insertEvent = $pdo->prepare('INSERT INTO hookEvents (heName) VALUES (?)');
            $insertEvent->execute([$eventName]);
            $ownedHookEventIDs[] = (int)$pdo->lastInsertId();
        }
    }
    // Populate HookManager's real process-local catalog cache from those DB
    // rows before attaching listeners, avoiding a duplicate catalog save from
    // inside the delete hook path.
    $catalogLoader = new FOG\Base\HookManager();
    $catalogLoader->processEvent('DESTROY_HOST', []);
    $catalogLoader->processEvent('DELETEMASS_API', []);

    // Negative 1: owner B has a normal blank facts row (no D2 authority).
    // The real delete hook adds that row to A's dependency map; the route
    // preflight must reject it before either host or directory row is removed.
    $deleteA = $insertHost('hook-owner-a');
    $outsideOwnerID = $insertHost('hook-owner-b');
    $insertDirectory = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',\'u2p2.invalid\',\'CN=blank,OU=Test,DC=u2p2,DC=invalid\',\'blank$\',UTC_TIMESTAMP(),NULL,NULL)'
    );
    $insertDirectory->execute([$outsideOwnerID]);
    $directoryHostIDs[] = $outsideOwnerID;
    $beforeA = $rawHost($deleteA);
    $beforeB = $rawHost($outsideOwnerID);
    $beforeDirectoryB = $pdo->prepare('SELECT * FROM hostDirectory WHERE hdHostID=?');
    $beforeDirectoryB->execute([$outsideOwnerID]);
    $beforeDirectoryB = $beforeDirectoryB->fetch(PDO::FETCH_ASSOC);
    $hookMode = 'outside_hostdirectory';
    $rootCallbackOrder = [];
    $hookDispatches = 0;
    $observerMaps = [];
    $outsideError = $blocked(static function () use ($deleteA) {
        FOG\Router\Route::asValue(static function () use ($deleteA) {
            (new FOG\Items\Host($deleteA))->destroy();
        });
    });
    $outsideMap = $observerMaps[0]['removeItems'] ?? [];
    $t->check(
        'real delete hook cannot add another host blank HostDirectory row to A decommission',
        $outsideError instanceof RuntimeException
            && false !== strpos($outsideError->getMessage(), 'directory_dependency_outside_decommission')
            && is_array($beforeA) && $beforeA === $rawHost($deleteA)
            && is_array($beforeB) && $beforeB === $rawHost($outsideOwnerID)
            && is_array($beforeDirectoryB)
            && $beforeDirectoryB === (function () use ($pdo, $outsideOwnerID) {
                $q = $pdo->prepare('SELECT * FROM hostDirectory WHERE hdHostID=?');
                $q->execute([$outsideOwnerID]);
                return $q->fetch(PDO::FETCH_ASSOC) ?: null;
            })()
            && ['mutator', 'observer'] === $rootCallbackOrder
            && 2 === $hookDispatches
            && isset($outsideMap['hostdirectory'])
            && ['hostID' => [$outsideOwnerID]] === $outsideMap['hostdirectory']
    );

    // Negative 2: an injected core image row has no owner path back to a
    // host. It must be rejected by dependency ownership preflight; never call
    // Image::destroy() or touch an image file in this test.
    $deleteC = $insertHost('hook-owner-c');
    // Scratch databases may intentionally start without catalog seeds. Add
    // disposable FK parents here instead of relying on unrelated baseline
    // data, and remove only the IDs created by this fixture.
    $typeName = 'u2p2-hook-type-' . bin2hex(random_bytes(5));
    $typeValue = 'u' . bin2hex(random_bytes(4));
    $insertType = $pdo->prepare('INSERT INTO imageTypes (imageTypeName,imageTypeValue) VALUES (?,?)');
    $insertType->execute([$typeName, $typeValue]);
    $types = (int)$pdo->lastInsertId();
    $ownedImageTypeIDs[] = $types;
    $partitionName = 'u2p2-hook-part-' . bin2hex(random_bytes(5));
    $partitionValue = 'u' . bin2hex(random_bytes(4));
    $insertPartition = $pdo->prepare(
        'INSERT INTO imagePartitionTypes (imagePartitionTypeName,imagePartitionTypeValue) VALUES (?,?)'
    );
    $insertPartition->execute([$partitionName, $partitionValue]);
    $partitions = (int)$pdo->lastInsertId();
    $ownedPartitionTypeIDs[] = $partitions;
    $imageName = 'u2p2-hook-' . bin2hex(random_bytes(5));
    $insertImage = $pdo->prepare(
        'INSERT INTO images (imageName,imageDesc,imagePath,imageTypeID,imagePartitionTypeID,imageOSID) '
        . 'VALUES (?,\'hook fixture\',?,?,?,NULL)'
    );
    $insertImage->execute([$imageName, '/u2-p2-hook-owner-no-file-operation', $types, $partitions]);
    $imageID = (int)$pdo->lastInsertId();
    $imageIDs[] = $imageID;
    $beforeC = $rawHost($deleteC);
    $imageBeforeQuery = $pdo->prepare('SELECT * FROM images WHERE imageID=?');
    $imageBeforeQuery->execute([$imageID]);
    $imageBefore = $imageBeforeQuery->fetch(PDO::FETCH_ASSOC);
    $hookMode = 'unknown_image';
    $rootCallbackOrder = [];
    $hookDispatches = 0;
    $observerMaps = [];
    $imageError = $blocked(static function () use ($deleteC) {
        FOG\Router\Route::asValue(static function () use ($deleteC) {
            (new FOG\Items\Host($deleteC))->destroy();
        });
    });
    $imageMap = $observerMaps[0]['removeItems'] ?? [];
    $imageAfterQuery = $pdo->prepare('SELECT * FROM images WHERE imageID=?');
    $imageAfterQuery->execute([$imageID]);
    $t->check(
        'real delete hook cannot add an ownerless image dependency to Host decommission',
        $imageError instanceof RuntimeException
            && false !== strpos($imageError->getMessage(), 'directory_dependency_owner_unknown')
            && is_array($beforeC) && $beforeC === $rawHost($deleteC)
            && is_array($imageBefore) && $imageBefore === $imageAfterQuery->fetch(PDO::FETCH_ASSOC)
            && ['mutator', 'observer'] === $rootCallbackOrder
            && 2 === $hookDispatches
            && isset($imageMap['image'])
            && ['id' => [$imageID]] === $imageMap['image']
    );

    // Positive control: a no-op real plugin hook preserves the normal map and
    // an ordinary root Host decommission still reaches its real DELETE.
    $deleteD = $insertHost('hook-owner-d');
    $beforeD = $rawHost($deleteD);
    $hookMode = 'harmless';
    $rootCallbackOrder = [];
    $hookDispatches = 0;
    $observerMaps = [];
    $destroyResult = null;
    $positiveError = $blocked(static function () use ($deleteD, &$destroyResult) {
        FOG\Router\Route::asValue(static function () use ($deleteD, &$destroyResult) {
            $destroyResult = (new FOG\Items\Host($deleteD))->destroy();
        });
    });
    $positiveMap = $observerMaps[0]['removeItems'] ?? [];
    $normalKeys = [
        'nodefailure', 'snapintask', 'snapinjob', 'task', 'scheduledtask', 'hostautologout',
        'groupassociation', 'snapinassociation', 'printerassociation', 'moduleassociation',
        'inventory', 'macaddressassociation', 'powermanagement', 'sitehostmember'
    ];
    sort($normalKeys);
    $seenKeys = array_keys($positiveMap);
    sort($seenKeys);
    $t->check(
        'harmless plugin hook preserves normal cascade map and root Host deletion succeeds',
        null === $positiveError && $destroyResult instanceof FOG\Items\Host
            && is_array($beforeD) && null === $rawHost($deleteD)
            && ['mutator', 'observer'] === $rootCallbackOrder
            && $hookDispatches > 2
            && $normalKeys === $seenKeys
            && !array_key_exists('hostdirectory', $positiveMap)
            && !array_key_exists('image', $positiveMap)
    );
} finally {
    foreach ($imageIDs as $id) {
        $q = $pdo->prepare('DELETE FROM images WHERE imageID=?');
        $q->execute([$id]);
    }
    foreach ($ownedImageTypeIDs as $id) {
        $q = $pdo->prepare('DELETE FROM imageTypes WHERE imageTypeID=?');
        $q->execute([$id]);
    }
    foreach ($ownedPartitionTypeIDs as $id) {
        $q = $pdo->prepare('DELETE FROM imagePartitionTypes WHERE imagePartitionTypeID=?');
        $q->execute([$id]);
    }
    foreach ($directoryHostIDs as $hostID) {
        $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
        $q->execute([$hostID]);
    }
    foreach (array_values(array_unique($hostIDs)) as $hostID) {
        $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
    }
    foreach ($ownedHookEventIDs as $eventID) {
        $q = $pdo->prepare('DELETE FROM hookEvents WHERE heID=?');
        $q->execute([$eventID]);
    }
}

echo "NOTE: this exercises two real DELETEMASS_API closures; it is not a full plugin inventory or filesystem-operation test.\n";
$t->finish();
