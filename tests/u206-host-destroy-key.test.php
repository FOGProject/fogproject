<?php
/** U2-06 regression for Host::destroy(non-id) preflight ordering. */
$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-06 Host destroy key: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('U2-06 Host destroy key requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u206-host-destroy-key');
$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('U2-06 Host destroy key requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
}
require_once __DIR__ . '/fixtures/u2-p2-intent-schema.php';
mj1InstallIntentFixture($pdo);

$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
$t = new FogChecks();

$fixtureToken = bin2hex(random_bytes(5));
$ownedConstraints = [];
$hostIDs = [];
$directoryIDs = [];
$wakeIDs = [];
$intentGUIDs = [];
$imageIDs = [];
$typeIDs = [];
$partitionIDs = [];
$edges = [
    ['table' => 'hostDirectory', 'column' => 'hdHostID', 'name' => 'fk_u206_hd_host'],
    ['table' => 'agentWake', 'column' => 'awTargetID', 'name' => 'fk_u206_aw_target'],
    ['table' => 'agentWake', 'column' => 'awSenderID', 'name' => 'fk_u206_aw_sender']
];
$ensureCascade = static function (array $edge) use ($pdo, &$ownedConstraints, $fixtureToken) {
    $q = $pdo->prepare(
        'SELECT k.CONSTRAINT_NAME,r.DELETE_RULE,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME '
        . 'FROM information_schema.KEY_COLUMN_USAGE k '
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
        . 'ON h.hostID=c.`' . $edge['column'] . '` WHERE c.`' . $edge['column'] . '` IS NOT NULL AND h.hostID IS NULL'
    )->fetchColumn();
    if ($orphans) {
        throw new \RuntimeException('Cannot add approved FK while orphan rows exist in ' . $edge['table']);
    }
    $name = $edge['name'] . '_' . $fixtureToken;
    $pdo->exec('ALTER TABLE `' . $edge['table'] . '` ADD CONSTRAINT `' . $name . '` '
        . 'FOREIGN KEY (`' . $edge['column'] . '`) REFERENCES hosts (hostID) ON DELETE CASCADE');
    $ownedConstraints[] = ['table' => $edge['table'], 'name' => $name];
};
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$blocked = static function (callable $fn) {
    try {
        $fn();
    } catch (\Throwable $e) {
        return $e;
    }
    return null;
};
$createImage = static function () use ($pdo, &$typeIDs, &$partitionIDs, &$imageIDs) {
    $token = bin2hex(random_bytes(5));
    $q = $pdo->prepare('INSERT INTO imageTypes (imageTypeName,imageTypeValue) VALUES (?,?)');
    $q->execute(['u206-type-' . $token, 'u' . substr($token, 0, 9)]);
    $typeID = (int)$pdo->lastInsertId();
    $typeIDs[] = $typeID;
    $q = $pdo->prepare('INSERT INTO imagePartitionTypes (imagePartitionTypeName,imagePartitionTypeValue) VALUES (?,?)');
    $q->execute(['u206-part-' . $token, 'u' . substr($token, 0, 9)]);
    $partitionID = (int)$pdo->lastInsertId();
    $partitionIDs[] = $partitionID;
    $q = $pdo->prepare(
        'INSERT INTO images (imageName,imageDesc,imagePath,imageTypeID,imagePartitionTypeID,imageOSID) '
        . 'VALUES (?,\'U2-06 fixture\',?,?,?,NULL)'
    );
    $q->execute(['u206-image-' . $token, '/u2-06-no-file-operation/' . $token, $typeID, $partitionID]);
    $imageID = (int)$pdo->lastInsertId();
    $imageIDs[] = $imageID;
    return $imageID;
};
$createHost = static function ($imageID, $active, $prefix) use ($pdo, $guid, &$hostIDs, &$directoryIDs, &$wakeIDs) {
    do {
        $id = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$id]);
    } while ((int)$q->fetchColumn() !== 0);
    $name = $prefix . strtoupper(bin2hex(random_bytes(5)));
    $fingerprint = hash('sha256', random_bytes(32));
    $q = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostImage,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,?, ?,\'u206.invalid\',\'OU=Managed,DC=u206,DC=invalid\',?)'
    );
    $q->execute([$id, $name, $imageID, $active ? 1 : 0, $fingerprint]);
    $hostIDs[] = $id;
    $state = null;
    if ($active) {
        $targetGUID = $guid();
        $targetSID = 'S-1-5-21-1048576-' . random_int(100000, 999999999);
        $state = json_encode([
            'version' => 1, 'protocol' => 2, 'phase' => 'started',
            'attempt_id' => bin2hex(random_bytes(16)), 'host_id' => $id,
            'fingerprint' => $fingerprint, 'operation' => 'rename', 'mode' => 'existing',
            'name' => $name, 'expected_current' => 'O' . strtoupper(bin2hex(random_bytes(4))),
            'expected_pending' => $name,
            'target' => ['guid' => $targetGUID, 'sid' => $targetSID, 'account' => $name . '$']
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    $q = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,?)'
    );
    $q->execute([$id, 'u206.invalid', 'CN=' . $name . ',OU=Managed,DC=u206,DC=invalid', $name . '$', $state]);
    $q = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$id]);
    $directoryID = (int)$q->fetchColumn();
    $directoryIDs[] = $directoryID;
    $wake = $pdo->prepare('INSERT INTO agentWake (awTargetID,awSenderID) VALUES (?,?)');
    $wake->execute([$id, $id]);
    $wakeIDs[] = (int)$pdo->lastInsertId();
    return ['id' => $id, 'name' => $name, 'fingerprint' => $fingerprint,
        'directory_id' => $directoryID, 'image_id' => $imageID];
};
$snapshot = static function (array $ids, $imageID) use ($pdo) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $imageIDs = array_values(array_unique(array_map('intval', (array)$imageID)));
    $imageMarks = implode(',', array_fill(0, count($imageIDs), '?'));
    $read = static function ($sql, array $params) use ($pdo) {
        $q = $pdo->prepare($sql);
        $q->execute($params);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            ksort($row);
        }
        unset($row);
        usort($rows, static function ($a, $b) { return strcmp(serialize($a), serialize($b)); });
        return ['count' => count($rows), 'rows' => $rows];
    };
    $out = [
        'hosts' => $read('SELECT * FROM hosts WHERE hostID IN (' . $marks . ')', $ids),
        'hostDirectory' => $read('SELECT * FROM hostDirectory WHERE hdHostID IN (' . $marks . ')', $ids),
        'tasks' => $read('SELECT * FROM tasks WHERE taskHostID IN (' . $marks . ')', $ids),
        'directoryPlacementIntent' => $read('SELECT * FROM directoryPlacementIntent WHERE dpiHostID IN (' . $marks . ')', $ids),
        'image' => $read('SELECT * FROM images WHERE imageID IN (' . $imageMarks . ')', $imageIDs),
        'dependencies' => []
    ];

    // Snapshot every mapped core dependency and every installed FK dependency
    // that names hosts, retaining every column and a row count for each table.
    $columnsByTable = [];
    $availableTables = array_fill_keys($pdo->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()'
    )->fetchAll(\PDO::FETCH_COLUMN), true);
    $constraints = require dirname(__DIR__) . '/packages/web/commons/schema-constraints.php';
    foreach ($constraints as $edge) {
        if ('hosts' === ($edge['parent'] ?? null) && isset($edge['child'], $edge['column'])
            && isset($availableTables[$edge['child']])) {
            $columnsByTable[$edge['child']][$edge['column']] = true;
        }
    }
    $fkRows = $pdo->query(
        'SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=\'hosts\' AND REFERENCED_COLUMN_NAME=\'hostID\''
    )->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($fkRows as $edge) {
        $columnsByTable[$edge['TABLE_NAME']][$edge['COLUMN_NAME']] = true;
    }
    unset($columnsByTable['hosts']);
    ksort($columnsByTable);
    foreach ($columnsByTable as $table => $columns) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $table)) {
            throw new \RuntimeException('unsafe dependency table name in schema map');
        }
        $predicates = [];
        $params = [];
        foreach (array_keys($columns) as $column) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $column)) {
                throw new \RuntimeException('unsafe dependency column name in schema map');
            }
            $predicates[] = '`' . $column . '` IN (' . $marks . ')';
            array_push($params, ...$ids);
        }
        if ($predicates) {
            $out['dependencies'][$table] = $read('SELECT * FROM `' . $table . '` WHERE '
                . implode(' OR ', $predicates), $params);
        }
    }
    ksort($out['dependencies']);
    return $out;
};

try {
    foreach ($edges as $edge) {
        $ensureCascade($edge);
    }

    // Defect reproducer: Host::destroy(non-id) first cascades only the loaded
    // Host ID, then its parent DELETE applies the non-unique hostImage value.
    $imageID = $createImage();
    $a = $createHost($imageID, false, 'A');
    $b = $createHost($imageID, true, 'B');
    $before = $snapshot([$a['id'], $b['id']], $imageID);
    $hostA = new FOG\Items\Host($a['id']);
    $singleError = $blocked(static function () use ($hostA) {
        FOG\Router\Route::asValue(static function () use ($hostA) {
            $hostA->destroy('imageID');
        });
    });
    $after = $snapshot([$a['id'], $b['id']], $imageID);
    $t->check(
        'A->destroy(imageID) refuses mixed inactive/protected matches before any Host/HD/task/intent/dependency row changes',
        $singleError instanceof \Throwable && $before === $after
    );
    if ($singleError && $before !== $after) {
        echo 'DIAGNOSTIC non-ID Host destroy changed snapshot before refusal: '
            . json_encode(['error' => $singleError->getMessage(), 'before' => $before, 'after' => $after], JSON_UNESCAPED_SLASHES)
            . "\n";
    }

    // Positive compatibility: with both matches inactive, the non-ID
    // Host::destroy path still removes the full hostImage match set.
    $inactiveImageID = $createImage();
    $inactiveA = $createHost($inactiveImageID, false, 'C');
    $inactiveB = $createHost($inactiveImageID, false, 'D');
    $inactiveHost = new FOG\Items\Host($inactiveA['id']);
    $inactiveError = $blocked(static function () use ($inactiveHost) {
        FOG\Router\Route::asValue(static function () use ($inactiveHost) {
            $inactiveHost->destroy('imageID');
        });
    });
    $inactiveAfter = $snapshot([$inactiveA['id'], $inactiveB['id']], $inactiveImageID);
    $t->check(
        'all-inactive non-ID Host destroy retains full-match deletion semantics',
        null === $inactiveError && 0 === $inactiveAfter['hosts']['count']
            && 0 === $inactiveAfter['hostDirectory']['count']
            && 0 === $inactiveAfter['tasks']['count']
            && 0 === $inactiveAfter['directoryPlacementIntent']['count']
            && 0 === $inactiveAfter['dependencies']['agentWake']['count']
            && 1 === $inactiveAfter['image']['count']
    );

    // The Route bulk path is the control: it resolves all matching Host IDs
    // before any hook/cascade and must preserve the complete mixed fixture.
    $routeImageID = $createImage();
    $routeA = $createHost($routeImageID, false, 'E');
    $routeB = $createHost($routeImageID, true, 'F');
    $routeBefore = $snapshot([$routeA['id'], $routeB['id']], $routeImageID);
    $routeError = $blocked(static function () use ($routeImageID) {
        FOG\Router\Route::asValue(static function () use ($routeImageID) {
            FOG\Router\Route::deletemass('host', ['imageID' => $routeImageID]);
        });
    });
    $routeAfter = $snapshot([$routeA['id'], $routeB['id']], $routeImageID);
    $t->check(
        'Route mass non-unique imageID filter rejects mixed inactive/protected set without any row mutation',
        $routeError instanceof \Throwable && $routeBefore === $routeAfter
    );

    // Empty target-set regression: the Host model still holds image X, while
    // a real concurrent DB facts writer has reassigned it to image Y. The
    // stale non-ID destroy must not fall back to deleting the loaded Host ID.
    $sourceImageID = $createImage();
    $currentImageID = $createImage();
    $stale = $createHost($sourceImageID, false, 'G');
    $staleHost = new FOG\Items\Host($stale['id']);
    $pdo->prepare('UPDATE hosts SET hostImage=? WHERE hostID=?')->execute([$currentImageID, $stale['id']]);
    $staleBefore = $snapshot([$stale['id']], [$sourceImageID, $currentImageID]);
    $staleError = $blocked(static function () use ($staleHost) {
        FOG\Router\Route::asValue(static function () use ($staleHost) {
            $staleHost->destroy('imageID');
        });
    });
    $staleAfter = $snapshot([$stale['id']], [$sourceImageID, $currentImageID]);
    $t->check(
        'stale Host non-ID destroy with empty current predicate target is a no-op/refusal and preserves all rows',
        $staleBefore === $staleAfter
    );
    if ($staleBefore !== $staleAfter) {
        echo 'DIAGNOSTIC stale empty-target Host destroy changed snapshot: '
            . json_encode(['error' => $staleError ? $staleError->getMessage() : null,
                'before' => $staleBefore, 'after' => $staleAfter], JSON_UNESCAPED_SLASHES)
            . "\n";
    }
} finally {
    // This fixture owns only the generated IDs above. Remove its rows directly
    // from the explicitly opted-in scratch database, then drop only the FKs it added.
    foreach ($hostIDs as $hostID) {
        $pdo->prepare('DELETE FROM agentWake WHERE awTargetID=? OR awSenderID=?')->execute([$hostID, $hostID]);
        $pdo->prepare('DELETE FROM tasks WHERE taskHostID=?')->execute([$hostID]);
        $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?')->execute([$hostID]);
        $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiHostID=?')->execute([$hostID]);
        $pdo->prepare('DELETE FROM hosts WHERE hostID=?')->execute([$hostID]);
    }
    foreach ($imageIDs as $imageID) {
        $pdo->prepare('DELETE FROM images WHERE imageID=?')->execute([$imageID]);
    }
    foreach ($typeIDs as $typeID) {
        $pdo->prepare('DELETE FROM imageTypes WHERE imageTypeID=?')->execute([$typeID]);
    }
    foreach ($partitionIDs as $partitionID) {
        $pdo->prepare('DELETE FROM imagePartitionTypes WHERE imagePartitionTypeID=?')->execute([$partitionID]);
    }
    foreach (array_reverse($ownedConstraints) as $constraint) {
        $pdo->exec('ALTER TABLE `' . $constraint['table'] . '` DROP FOREIGN KEY `' . $constraint['name'] . '`');
    }
}

$t->finish();
