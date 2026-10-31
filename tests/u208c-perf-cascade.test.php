<?php
/**
 * U2-08c real-FK integration coverage for performance rows during Host
 * decommission. Run only against an explicitly approved, full-fresh schema
 * 447 fog_mj1 scratch database. This test never changes schema or connects to
 * LDAP; cleanup is limited to rows owned by its randomized fixture hosts.
 */
$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-08c performance cascade: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('U2-08c performance cascade requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u208c-perf-cascade');
$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('U2-08c performance cascade requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
}

$version = (int)$pdo->query('SELECT `vValue` FROM `schemaVersion` LIMIT 1')->fetchColumn();
if (447 !== $version) {
    throw new RuntimeException('U2-08c performance cascade requires a full-fresh schema 447 scratch DB');
}
$fk = $pdo->prepare(
    'SELECT k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE '
    . 'FROM information_schema.KEY_COLUMN_USAGE k '
    . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
    . 'ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME '
    . 'AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME '
    . 'WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.COLUMN_NAME=?'
);
$fk->execute(['hostPerfTests', 'hptHostID']);
$fkRows = $fk->fetchAll(PDO::FETCH_ASSOC);
if (1 !== count($fkRows)
    || 'hosts' !== $fkRows[0]['REFERENCED_TABLE_NAME']
    || 'hostID' !== $fkRows[0]['REFERENCED_COLUMN_NAME']
    || 'CASCADE' !== strtoupper($fkRows[0]['DELETE_RULE'])
) {
    throw new RuntimeException('hostPerfTests.hptHostID must already have its installed CASCADE FK to hosts.hostID');
}

// Install the actual application PDO path used by Host and the decommission
// guard. No fake SQL behavior is involved in these assertions.
$link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

$checks = new FogChecks();
$fixtures = [];
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$createFixture = static function ($label) use ($pdo, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $hostName = 'PFC' . strtoupper(bin2hex(random_bytes(6)));
        $q = $pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM hosts WHERE hostID=? OR hostName=?) '
            . '+ (SELECT COUNT(*) FROM hostDirectory WHERE hdHostID=?) '
            . '+ (SELECT COUNT(*) FROM hostPerfTests WHERE hptHostID=?) '
            . '+ (SELECT COUNT(*) FROM auditLog WHERE alSubjectType=\'host\' AND alSubjectID=?) '
            . '+ (SELECT COUNT(*) FROM history WHERE hSubjectType=\'host\' AND hSubjectID=?)'
        );
        $q->execute([$hostID, $hostName, $hostID, $hostID, $hostID, $hostID]);
    } while ((int)$q->fetchColumn() !== 0);

    $fingerprint = hash('sha256', random_bytes(32));
    $fixtures[] = [
        'label' => $label,
        'host_id' => $hostID,
        'host_inserted' => false,
        'directory_inserted' => false,
        'perf_inserted' => false
    ];
    $index = array_key_last($fixtures);
    $insert = $pdo->prepare(
        'INSERT INTO hosts '
        . '(hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,0,\'\',\'\',?)'
    );
    $insert->execute([$hostID, $hostName, $fingerprint]);
    $fixtures[$index]['host_inserted'] = true;

    $insert = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,0,\'\',\'\',\'\',\'\',UTC_TIMESTAMP(),NULL,?)'
    );
    $insert->execute([$hostID, null]);
    $fixtures[$index]['directory_inserted'] = true;
    $q = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    $directoryID = (int)$q->fetchColumn();

    $insert = $pdo->prepare(
        'INSERT INTO hostPerfTests (hptHostID,hptDisk) VALUES (?,?)'
    );
    $insert->execute([$hostID, 'u208c-' . $label]);
    $fixtures[$index]['perf_inserted'] = true;
    $perfID = (int)$pdo->lastInsertId();

    return [
        'id' => $hostID,
        'name' => $hostName,
        'fingerprint' => $fingerprint,
        'directory_id' => $directoryID,
        'perf_id' => $perfID
    ];
};
$raw = static function ($table, $column, $id) use ($pdo) {
    $allowed = [
        'hosts' => 'hostID',
        'hostDirectory' => 'hdID',
        'hostPerfTests' => 'hptID'
    ];
    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        throw new InvalidArgumentException('raw fixture selector invalid');
    }
    $q = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $column . '`=?');
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
};
$snapshot = static function (array $fixture) use ($raw) {
    return [
        'host' => $raw('hosts', 'hostID', $fixture['id']),
        'directory' => $raw('hostDirectory', 'hdID', $fixture['directory_id']),
        'perf' => $raw('hostPerfTests', 'hptID', $fixture['perf_id'])
    ];
};
$attempt = static function (callable $fn) {
    try {
        return ['result' => $fn(), 'error' => null];
    } catch (Throwable $e) {
        return ['result' => null, 'error' => $e];
    }
};

try {
    $inactive = $createFixture('inactive');
    $protected = $createFixture('protected');
    $currentName = 'OLD' . strtoupper(bin2hex(random_bytes(5)));
    $domain = 'u208c.invalid';
    $target = [
        'guid' => $guid(),
        'sid' => 'S-1-5-21-208-' . $protected['id'],
        'account' => $protected['name'] . '$'
    ];
    $protectedState = json_encode([
        'version' => 1,
        'protocol' => 2,
        'phase' => 'unknown',
        'attempt_id' => bin2hex(random_bytes(16)),
        'host_id' => $protected['id'],
        'fingerprint' => $protected['fingerprint'],
        'operation' => 'rename',
        'mode' => 'existing',
        'name' => $protected['name'],
        'domain' => $domain,
        'expected_current' => $currentName,
        'expected_pending' => $protected['name'],
        'target' => $target,
        'reason' => 'unknown',
        'secure_channel' => 'unknown',
        'updated_at' => gmdate('c')
    ]);
    // Direct SQL writes only the marker column of this owned fixture row.
    $update = $pdo->prepare('UPDATE hostDirectory SET hdRejoinState=? WHERE hdID=?');
    $update->execute([$protectedState, $protected['directory_id']]);
    $decodedProtectedState = FOG\Agent\DirectoryRejoin::decode($protectedState);
    $checks->check(
        'UNKNOWN fixture is a decodable protocol-2 rename marker bound to its Host fingerprint and footprint',
        'unknown' === ($decodedProtectedState['phase'] ?? null)
            && 2 === ($decodedProtectedState['protocol'] ?? null)
            && $protected['id'] === ($decodedProtectedState['host_id'] ?? null)
            && $protected['fingerprint'] === ($decodedProtectedState['fingerprint'] ?? null)
            && $protected['name'] === ($decodedProtectedState['name'] ?? null)
            && '' !== ($decodedProtectedState['expected_current'] ?? '')
            && $protected['name'] === ($decodedProtectedState['expected_pending'] ?? null)
            && FOG\Agent\DirectoryRejoin::guid($decodedProtectedState['target']['guid'] ?? null)
            && $protected['name'] . '$' === ($decodedProtectedState['target']['account'] ?? null)
    );

    $other = $createFixture('other');
    $otherBefore = $snapshot($other);

    $delete = $attempt(static function () use ($inactive) {
        $host = null;
        FOG\Router\Route::asValue(static function () use ($inactive, &$host) {
            $host = (new FOG\Items\Host($inactive['id']))->destroy();
        });
        return $host;
    });
    $checks->check(
        'inactive Host deletion cascades its hostPerfTests and HostDirectory rows through the installed FK',
        $delete['error'] === null
            && $delete['result'] instanceof FOG\Items\Host
            && null === $raw('hosts', 'hostID', $inactive['id'])
            && null === $raw('hostDirectory', 'hdID', $inactive['directory_id'])
            && null === $raw('hostPerfTests', 'hptID', $inactive['perf_id'])
    );
    $checks->check(
        'inactive Host decommission leaves another Host and its directory/performance rows unchanged',
        $otherBefore === $snapshot($other)
    );

    $protectedBefore = $snapshot($protected);
    $blockedDelete = $attempt(static function () use ($protected) {
        $host = null;
        FOG\Router\Route::asValue(static function () use ($protected, &$host) {
            $host = (new FOG\Items\Host($protected['id']))->destroy();
        });
        return $host;
    });
    $protectedAfter = $snapshot($protected);
    // Host::destroy goes through Route::asValue, whose public boundary wraps
    // the internal DirectoryFenceRefusal as this RuntimeException response.
    $protectedRefusal = $blockedDelete['error'] instanceof RuntimeException
        && 409 === $blockedDelete['error']->getCode()
        && '{"error":"directory_host_operation_protected"}'
            === $blockedDelete['error']->getMessage();
    $protectedRowsUnchanged = $protectedBefore === $protectedAfter;
    if (!$protectedRefusal || !$protectedRowsUnchanged) {
        $changedRows = [];
        foreach (['host', 'directory', 'perf'] as $rowName) {
            if ($protectedBefore[$rowName] !== $protectedAfter[$rowName]) {
                $changedRows[] = $rowName;
            }
        }
        $failure = $blockedDelete['error'];
        $storedDirectory = $protectedAfter['directory'];
        $storedState = [];
        $markerDecodeFailure = null;
        try {
            $storedState = is_array($storedDirectory)
                ? FOG\Agent\DirectoryRejoin::decode($storedDirectory['hdRejoinState'] ?? null)
                : [];
        } catch (Throwable $e) {
            $markerDecodeFailure = [get_class($e), $e->getMessage()];
        }
        // Emit only the refusal identity and per-row change names. Do not dump
        // fixture values, marker JSON, exception traces, or database rows.
        fwrite(STDERR, 'DIAG UNKNOWN Host delete: ' . json_encode([
            'exception_class' => $failure ? get_class($failure) : null,
            'exception_reason' => $failure ? $failure->getMessage() : null,
            'exception_code' => $failure ? $failure->getCode() : null,
            'result_class' => is_object($blockedDelete['result'])
                ? get_class($blockedDelete['result']) : null,
            'protected_refusal' => $protectedRefusal,
            'raw_rows_changed' => $changedRows,
            'marker_decode_failure' => $markerDecodeFailure,
            'marker_phase' => $storedState['phase'] ?? null,
            'marker_protocol' => $storedState['protocol'] ?? null,
            'marker_host_matches' => (int)($storedDirectory['hdHostID'] ?? 0)
                === (int)($storedState['host_id'] ?? -1),
            'marker_attempt_present' => !empty($storedState['attempt_id'])
        ], JSON_UNESCAPED_SLASHES) . "\n");
    }
    $checks->check(
        'UNKNOWN-protected Host deletion is refused with host, marker, and performance row unchanged',
        $protectedRefusal && $protectedRowsUnchanged
    );
    $checks->check(
        'blocked Host decommission leaves the unrelated Host unchanged',
        $otherBefore === $snapshot($other)
    );

    // A standalone non-Host delete remains usable while the Host itself is
    // protected. This uses the real HostPerfTest model and FOGController path.
    $perfDelete = (new FOG\Items\HostPerfTest($protected['perf_id']))->destroy();
    $checks->check(
        'standalone HostPerfTest CRUD remains available while its Host is protected',
        $perfDelete instanceof FOG\Items\HostPerfTest
            && null === $raw('hostPerfTests', 'hptID', $protected['perf_id'])
            && $raw('hosts', 'hostID', $protected['id']) !== null
            && $raw('hostDirectory', 'hdID', $protected['directory_id']) !== null
            && $otherBefore === $snapshot($other)
    );
} finally {
    // Raw SQL cleanup touches only the randomized fixtures created above.
    foreach ($fixtures as $fixture) {
        $hostID = (int)$fixture['host_id'];
        if (!empty($fixture['perf_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hostPerfTests WHERE hptHostID=?');
            $q->execute([$hostID]);
        }
        if (!empty($fixture['directory_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$hostID]);
        }
        if (!empty($fixture['host_inserted'])) {
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$hostID]);
        }
        $q = $pdo->prepare(
            'DELETE FROM auditLog WHERE alSubjectType=\'host\' AND alSubjectID=?'
        );
        $q->execute([$hostID]);
        $q = $pdo->prepare(
            'DELETE FROM history WHERE hSubjectType=\'host\' AND hSubjectID=?'
        );
        $q->execute([$hostID]);
    }
}

echo "NOTE: full-fresh schema 447 only; actual hostPerfTests CASCADE FK; no DDL, FK changes, LDAP, or non-fixture cleanup.\n";
$checks->finish();
