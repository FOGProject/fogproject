<?php
/** Result-sidecar format, size, digest, and projection lifecycle checks. */
$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 intent result format: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('intent result format requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-intent-result-format');
$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('intent result format requires a fog_mj1-prefixed scratch DB');
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
FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

$t = new FogChecks();
$fixtures = [];
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$guidBytes = static function ($value) {
    return hex2bin(str_replace('-', '', strtolower($value)));
};
$createFixture = static function () use ($pdo, $guid, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $check = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $check->execute([$hostID]);
    } while ((int)$check->fetchColumn() !== 0);

    $name = 'P2RF' . strtoupper(bin2hex(random_bytes(6)));
    $domain = 'u2p2.invalid';
    $oldDN = 'CN=' . $name . ',OU=Legacy,DC=u2p2,DC=invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $fixture = [
        'host_id' => $hostID,
        'host_inserted' => false,
        'directory_inserted' => false,
        'guid' => $guid(),
        'slot_inserted' => false,
        'payload' => null
    ];
    $fixtures[] = $fixture;
    $index = array_key_last($fixtures);
    $host = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,\'1\',?,?,?)'
    );
    $host->execute([$hostID, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[$index]['host_inserted'] = true;
    $directory = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,NULL)'
    );
    $directory->execute([$hostID, $domain, $oldDN, $name . '$']);
    $fixtures[$index]['directory_inserted'] = true;

    $snapshotMethod = new \ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
    $snapshotMethod->setAccessible(true);
    $snapshot = $snapshotMethod->invoke(null, $pdo, $hostID);
    $target = [
        'guid' => $fixtures[$index]['guid'],
        'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
        'account' => $name . '$',
        'dn' => $oldDN
    ];
    $destination = [
        'guid' => $guid(),
        'dn' => 'OU=Managed,DC=u2p2,DC=invalid'
    ];
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
    $fixtures[$index]['payload'] = $payload;
    return $fixtures[$index];
};
$readSlot = static function ($targetGuid) use ($pdo, $guidBytes) {
    $q = $pdo->prepare(
        'SELECT dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult '
        . 'FROM directoryPlacementIntent WHERE dpiGUID=?'
    );
    $q->bindValue(1, $guidBytes($targetGuid), \PDO::PARAM_LOB);
    $q->execute();
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};
$readDirectory = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare(
        'SELECT hdComputerDN,hdObservedAt,hdPlacementAt,hdPlacementError '
        . 'FROM hostDirectory WHERE hdHostID=?'
    );
    $q->execute([$hostID]);
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};
$finish = static function (array $fixture, $success, $rawResult, $mutateFacts = false) use (
    $pdo,
    $readSlot,
    $readDirectory
) {
    $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($fixture['payload']);
    $ldapTokenObject = new FOG\Net\FOGLdap(); // Inert token only; no connect or LDAP call.
    $permit = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldapTokenObject);
    FOG\Agent\DirectoryPlacementIntent::consumePermit(
        $permit,
        $ldapTokenObject,
        FOG\Net\FOGLdap::guidDN($fixture['payload']['target']['guid']),
        FOG\Net\FOGLdap::guidDN($fixture['payload']['destination']['guid'])
    );
    $factsBefore = null;
    if ($mutateFacts) {
        $factsBefore = $pdo->prepare(
            'SELECT hdComputerDN,hdObservedAt,hdPlacementAt FROM hostDirectory WHERE hdHostID=?'
        );
        $factsBefore->execute([$fixture['host_id']]);
        $factsBefore = $factsBefore->fetch(\PDO::FETCH_ASSOC);
        $update = $pdo->prepare(
            'UPDATE hostDirectory SET hdComputerDN=?,hdObservedAt=? WHERE hdHostID=?'
        );
        $update->execute([
            'CN=' . $fixture['payload']['target']['account'] . ',OU=NewerFacts,DC=u2p2,DC=invalid',
            gmdate('Y-m-d H:i:s', time() - 30),
            $fixture['host_id']
        ]);
    }
    FOG\Agent\DirectoryPlacementIntent::finish($permit, $success, $rawResult);
    return [$readSlot($fixture['guid']), $readDirectory($fixture['host_id']), $factsBefore, $ticket];
};

try {
    $long = $createFixture();
    $rawLong = "ldap\n\t\x01é🌱|" . str_repeat("漢字𐍈/\\\r\n", 300);
    list($longSlot, $longFacts) = $finish($long, true, $rawLong);
    $longResult = is_array($longSlot)
        ? json_decode((string)$longSlot['dpiResult'], true) : null;
    $t->check(
        'long UTF-8/control result persists as valid JSON within the 512-character column bound',
        is_array($longResult) && JSON_ERROR_NONE === json_last_error()
            && strlen((string)$longSlot['dpiResult']) <= 512
            && 1 === ($longResult['version'] ?? null)
            && true === ($longResult['ldap_success'] ?? null)
            && true === ($longResult['truncated'] ?? null)
    );
    $t->check(
        'long result hashes the complete untruncated raw UTF-8/control string',
        is_array($longResult)
            && hash('sha256', $rawLong) === ($longResult['ldap_result_sha256'] ?? null)
            && is_string($longResult['ldap_result'] ?? null)
            && strlen($longResult['ldap_result']) < strlen($rawLong)
            && 1 === preg_match('//u', $longResult['ldap_result'])
            && "ldap\n\t\x01é🌱|" === substr($longResult['ldap_result'], 0, strlen("ldap\n\t\x01é🌱|"))
    );
    $t->check(
        'successful facts compare-and-set is recorded as applied independently of terminal status',
        is_array($longSlot) && 'SUCCEEDED' === $longSlot['dpiStatus']
            && is_array($longResult) && 'applied' === ($longResult['facts_projection'] ?? null)
            && is_array($longFacts)
            && ($long['payload']['rdn'] . ',' . $long['payload']['destination']['dn']) === $longFacts['hdComputerDN']
    );

    $casMiss = $createFixture();
    $rawCas = "ldap_success\tcontrol=\x02";
    list($casSlot, $casFacts, $casBefore) = $finish($casMiss, true, $rawCas, true);
    $casResult = is_array($casSlot)
        ? json_decode((string)$casSlot['dpiResult'], true) : null;
    $t->check(
        'facts snapshot miss is reported separately while LDAP success remains SUCCEEDED',
        is_array($casSlot) && 'SUCCEEDED' === $casSlot['dpiStatus']
            && is_array($casResult) && true === ($casResult['ldap_success'] ?? null)
            && 'cas_miss' === ($casResult['facts_projection'] ?? null)
            && $rawCas === ($casResult['ldap_result'] ?? null)
            && hash('sha256', $rawCas) === ($casResult['ldap_result_sha256'] ?? null)
            && false === ($casResult['truncated'] ?? null)
            && is_array($casBefore) && is_array($casFacts)
            && $casFacts['hdComputerDN'] !== ($casMiss['payload']['rdn'] . ',' . $casMiss['payload']['destination']['dn'])
            && $casFacts['hdComputerDN'] !== $casBefore['hdComputerDN']
    );

    // The raw text intentionally says success while the authoritative boolean
    // is false. The result sidecar must not grant a dispatch permission.
    $unknown = $createFixture();
    $rawUnknown = 'ldap_success';
    list($unknownSlot, , , $unknownTicket) = $finish($unknown, false, $rawUnknown);
    $unknownResult = is_array($unknownSlot)
        ? json_decode((string)$unknownSlot['dpiResult'], true) : null;
    $retryReason = '';
    try {
        FOG\Agent\DirectoryPlacementIntent::dispatch($unknownTicket, new FOG\Net\FOGLdap());
    } catch (FOG\Agent\DirectoryFenceRefusal $e) {
        $retryReason = $e->getMessage();
    }
    $afterRetry = $readSlot($unknown['guid']);
    $t->check(
        'UNKNOWN status blocks redispatch even when raw result text says ldap_success',
        is_array($unknownSlot) && 'UNKNOWN' === $unknownSlot['dpiStatus']
            && is_array($unknownResult) && false === ($unknownResult['ldap_success'] ?? null)
            && 'ldap_success' === ($unknownResult['ldap_result'] ?? null)
            && 'not_attempted' === ($unknownResult['facts_projection'] ?? null)
            && 'directory_placement_dispatch_changed' === $retryReason
            && $unknownSlot === $afterRetry
    );

    // Dispatch is bound to the original PDO object and MySQL connection ID.
    // Swapping the wrapper's link before consume must burn the process-local
    // permit without consuming or changing the durable MAY_HAVE_RUN row.
    $pdoSwap = $createFixture();
    $pdoSwapTicket = FOG\Agent\DirectoryPlacementIntent::prepare($pdoSwap['payload']);
    $pdoSwapLdap = new FOG\Net\FOGLdap();
    $pdoSwapPermit = FOG\Agent\DirectoryPlacementIntent::dispatch($pdoSwapTicket, $pdoSwapLdap);
    $pdoSwapBefore = $readSlot($pdoSwap['guid']);
    $alternatePDO = new \PDO(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
    $dispatchReason = '';
    $link->setValue(null, $alternatePDO);
    try {
        FOG\Agent\DirectoryPlacementIntent::consumePermit(
            $pdoSwapPermit,
            $pdoSwapLdap,
            FOG\Net\FOGLdap::guidDN($pdoSwap['payload']['target']['guid']),
            FOG\Net\FOGLdap::guidDN($pdoSwap['payload']['destination']['guid'])
        );
    } catch (FOG\Agent\DirectoryFenceRefusal $e) {
        $dispatchReason = $e->getMessage();
    } finally {
        $link->setValue(null, $pdo);
    }
    $pdoSwapAfter = $readSlot($pdoSwap['guid']);
    $reuseReason = '';
    try {
        FOG\Agent\DirectoryPlacementIntent::consumePermit(
            $pdoSwapPermit,
            $pdoSwapLdap,
            FOG\Net\FOGLdap::guidDN($pdoSwap['payload']['target']['guid']),
            FOG\Net\FOGLdap::guidDN($pdoSwap['payload']['destination']['guid'])
        );
    } catch (FOG\Agent\DirectoryFenceRefusal $e) {
        $reuseReason = $e->getMessage();
    }
    $t->check(
        'PDO swap before consume burns permit but preserves the MAY_HAVE_RUN slot',
        'directory_move_dispatch_connection_changed' === $dispatchReason
            && 'directory_move_dispatch_permit_required' === $reuseReason
            && is_array($pdoSwapBefore) && 'MAY_HAVE_RUN' === $pdoSwapBefore['dpiStatus']
            && $pdoSwapBefore === $pdoSwapAfter
    );
} finally {
    foreach (array_reverse($fixtures) as $fixture) {
        if (!empty($fixture['guid'])) {
            $delete = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?');
            $delete->bindValue(1, $guidBytes($fixture['guid']), \PDO::PARAM_LOB);
            $delete->execute();
        }
        if (!empty($fixture['directory_inserted'])) {
            $delete = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $delete->execute([$fixture['host_id']]);
        }
        if (!empty($fixture['host_inserted'])) {
            $delete = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $delete->execute([$fixture['host_id']]);
        }
    }
}

echo "NOTE: inert FOGLdap objects only; no LDAP connection or native move is exercised\n";
$t->finish();
