<?php
/**
 * Scratch-only negative checks: incomplete intent storage must block callback
 * execution and dispatch while preserving all original intent rows.
 */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 intent schema negative: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('intent schema negative checks require explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
\FogTestHarness::boot('u2-p2-intent-schema-negative');

$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('intent schema negative checks require a fog_mj1-prefixed scratch DB');
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
$tableImage = static function ($table) use ($pdo) {
    if (!preg_match('/^directoryPlacementIntent(?:_backup_[a-f0-9]{16})?$/D', $table)) {
        throw new RuntimeException('intent image table name is outside this test\'s fixed allowlist');
    }
    $q = $pdo->query(
        'SELECT dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult,dpiUpdated '
        . 'FROM `' . $table . '` ORDER BY dpiGUID'
    );
    $rows = $q->fetchAll(PDO::FETCH_NUM);
    $q->closeCursor();
    return ['count' => count($rows), 'sha256' => hash('sha256', serialize($rows))];
};
$readSlot = static function ($table, $targetGuid) use ($pdo, $guidBytes) {
    if (!preg_match('/^directoryPlacementIntent(?:_backup_[a-f0-9]{16})?$/D', $table)) {
        throw new RuntimeException('intent row table name is outside this test\'s fixed allowlist');
    }
    $q = $pdo->prepare('SELECT dpiStatus,dpiPayload,dpiHash,dpiResult FROM `' . $table . '` WHERE dpiGUID=?');
    $q->bindValue(1, $guidBytes($targetGuid), PDO::PARAM_LOB);
    $q->execute();
    $row = $q->fetch(PDO::FETCH_ASSOC);
    $q->closeCursor();
    return is_array($row) ? $row : null;
};

$fixtures = [];
$backupTable = null;
$tableRenamed = false;
$extraColumnAdded = false;
$createPreparedFixture = static function () use ($pdo, $guid, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
        $exists = (int)$q->fetchColumn();
        $q->closeCursor();
    } while (0 !== $exists);

    $name = 'P2ISN' . strtoupper(bin2hex(random_bytes(5)));
    $domain = 'u2p2.invalid';
    $oldDN = 'CN=' . $name . ',OU=Legacy,DC=u2p2,DC=invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $targetGuid = null;
    $insertedHost = false;
    $insertedDirectory = false;
    try {
        $q = $pdo->prepare(
            'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
            . 'VALUES (?, ?, 1, ?, ?, ?)'
        );
        $q->execute([$hostID, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
        $insertedHost = true;
        $q = $pdo->prepare(
            'INSERT INTO hostDirectory '
            . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
            . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,NULL)'
        );
        $q->execute([$hostID, $domain, $oldDN, $name . '$']);
        $insertedDirectory = true;

        $snapshotMethod = new ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
        $snapshotMethod->setAccessible(true);
        $snapshot = $snapshotMethod->invoke(null, $pdo, $hostID);
        $target = [
            'guid' => $guid(),
            'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
            'account' => $name . '$',
            'dn' => $oldDN
        ];
        $targetGuid = $target['guid'];
        $destination = ['guid' => $guid(), 'dn' => 'OU=Managed,DC=u2p2,DC=invalid'];
        $payload = [
            'version' => 1,
            'host_id' => $hostID,
            'snapshot' => $snapshot,
            'target' => $target,
            'destination' => $destination,
            'dc' => 'dc1.u2p2.invalid',
            'nc' => 'DC=u2p2,DC=invalid',
            'rdn' => 'CN=' . $name
        ];
        $fixture = [
            'host_id' => $hostID,
            'host_inserted' => $insertedHost,
            'directory_inserted' => $insertedDirectory,
            'target' => $target,
            'payload' => $payload
        ];
        $fixtures[] = $fixture;
        $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($payload);
        return [$fixture, $ticket];
    } catch (Throwable $e) {
        if (is_string($targetGuid)) {
            $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?');
            $q->bindValue(1, hex2bin(str_replace('-', '', strtolower($targetGuid))), PDO::PARAM_LOB);
            $q->execute();
        }
        if ($insertedDirectory) {
            $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $q->execute([$hostID]);
        }
        if ($insertedHost) {
            $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $q->execute([$hostID]);
        }
        throw $e;
    }
};
$captureReason = static function (callable $operation) {
    try {
        $operation();
        return ['reason' => null, 'returned' => true];
    } catch (Throwable $e) {
        return ['reason' => $e->getMessage(), 'returned' => false];
    }
};

try {
    list($fixture, $ticket) = $createPreparedFixture();
    $targetGuid = $fixture['target']['guid'];
    $originalImage = $tableImage('directoryPlacementIntent');
    $originalRow = $readSlot('directoryPlacementIntent', $targetGuid);
    if (!is_array($originalRow) || 'PREPARED' !== $originalRow['dpiStatus']) {
        throw new RuntimeException('schema-negative fixture did not start in PREPARED');
    }

    // Rename only this scratch intent table. Its exact original row image is
    // measured while hidden and must be unchanged before restoring the name.
    do {
        $backupTable = 'directoryPlacementIntent_backup_' . bin2hex(random_bytes(8));
        $q = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        );
        $q->execute([$backupTable]);
        $collision = (int)$q->fetchColumn() !== 0;
        $q->closeCursor();
    } while ($collision);
    $pdo->exec('RENAME TABLE `directoryPlacementIntent` TO `' . $backupTable . '`');
    $tableRenamed = true;
    $hiddenBefore = $tableImage($backupTable);
    $callbackEntered = false;
    $missingFence = $captureReason(static function () use (&$callbackEntered) {
        FOG\Agent\DirectoryNameFence::run(static function () use (&$callbackEntered) {
            $callbackEntered = true;
        });
    });
    $missingDispatch = $captureReason(static function () use ($ticket) {
        FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, new stdClass());
    });
    $hiddenAfter = $tableImage($backupTable);
    $hiddenRow = $readSlot($backupTable, $targetGuid);
    $missingPass = !$callbackEntered
        && 'directory_fence_storage_missing' === $missingFence['reason']
        && 'directory_fence_storage_missing' === $missingDispatch['reason']
        && $hiddenBefore === $hiddenAfter
        && $originalImage === $hiddenAfter
        && $originalRow === $hiddenRow
        && 'PREPARED' === ($hiddenRow['dpiStatus'] ?? null);
    if (!$missingPass) {
        fwrite(STDERR, 'U2-P2 missing-intent-table diagnostic: ' . json_encode([
            'callback_entered' => $callbackEntered,
            'fence_reason' => $missingFence,
            'dispatch_reason' => $missingDispatch,
            'original_image' => $originalImage,
            'hidden_before' => $hiddenBefore,
            'hidden_after' => $hiddenAfter,
            'original_slot' => $originalRow,
            'hidden_slot_after' => $hiddenRow
        ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
    $t->check('missing intent table refuses common-fence callback and dispatch without changing any row', $missingPass);
    $pdo->exec('RENAME TABLE `' . $backupTable . '` TO `directoryPlacementIntent`');
    $tableRenamed = false;
    $t->check('restored intent table has the exact pre-rename row image', $originalImage === $tableImage('directoryPlacementIntent'));

    // Add one nullable, unexpected column. The schema validator must refuse
    // before either a generic callback or the prepared-ticket dispatch runs.
    $imageBeforeMalformed = $tableImage('directoryPlacementIntent');
    $rowBeforeMalformed = $readSlot('directoryPlacementIntent', $targetGuid);
    $pdo->exec('ALTER TABLE `directoryPlacementIntent` ADD COLUMN `dpiUnexpected` VARCHAR(1) NULL');
    $extraColumnAdded = true;
    $malformedCallbackEntered = false;
    $malformedFence = $captureReason(static function () use (&$malformedCallbackEntered) {
        FOG\Agent\DirectoryNameFence::run(static function () use (&$malformedCallbackEntered) {
            $malformedCallbackEntered = true;
        });
    });
    $malformedDispatch = $captureReason(static function () use ($ticket) {
        FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, new stdClass());
    });
    $imageAfterMalformed = $tableImage('directoryPlacementIntent');
    $rowAfterMalformed = $readSlot('directoryPlacementIntent', $targetGuid);
    $malformedPass = !$malformedCallbackEntered
        && 'directory_intent_storage_columns_invalid' === $malformedFence['reason']
        && 'directory_intent_storage_columns_invalid' === $malformedDispatch['reason']
        && $imageBeforeMalformed === $imageAfterMalformed
        && $rowBeforeMalformed === $rowAfterMalformed
        && 'PREPARED' === ($rowAfterMalformed['dpiStatus'] ?? null);
    if (!$malformedPass) {
        fwrite(STDERR, 'U2-P2 malformed-intent-table diagnostic: ' . json_encode([
            'callback_entered' => $malformedCallbackEntered,
            'fence_reason' => $malformedFence,
            'dispatch_reason' => $malformedDispatch,
            'image_before' => $imageBeforeMalformed,
            'image_after' => $imageAfterMalformed,
            'slot_before' => $rowBeforeMalformed,
            'slot_after' => $rowAfterMalformed
        ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
    $t->check('unexpected nullable column refuses callback and dispatch without changing original rows', $malformedPass);
} finally {
    if ($tableRenamed && is_string($backupTable)) {
        $pdo->exec('RENAME TABLE `' . $backupTable . '` TO `directoryPlacementIntent`');
        $tableRenamed = false;
    }
    if ($extraColumnAdded) {
        $pdo->exec('ALTER TABLE `directoryPlacementIntent` DROP COLUMN `dpiUnexpected`');
        $extraColumnAdded = false;
    }
    foreach ($fixtures as $fixture) {
        $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?');
        $q->bindValue(1, $guidBytes($fixture['target']['guid']), PDO::PARAM_LOB);
        $q->execute();
        $q = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
        $q->execute([$fixture['host_id']]);
        $q = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
        $q->execute([$fixture['host_id']]);
    }
}

echo "NOTE: both schema faults are applied only to the opted-in fog_mj1 scratch database and restored in finally; no LDAP/native request is made.\n";
$t->finish();
