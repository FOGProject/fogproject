<?php
/**
 * Focused real-scratch-PDO checks for placement slot identity and one-use
 * dispatch permits. This file never connects to LDAP or sends a request.
 */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 slot bindings: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('slot bindings require explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-slot-bindings');

$pdo = new \PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('slot bindings require a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
}

// Install this scratch connection in the production PDODB path.
$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new FOG\Db\PDODB();
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);

$t = new FogChecks();
FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
        . substr($hex, 20, 12);
};
$guidBytes = static function ($value) {
    return hex2bin(str_replace('-', '', strtolower($value)));
};
$reason = static function (callable $operation) {
    try {
        $operation();
    } catch (FOG\Agent\DirectoryFenceRefusal $e) {
        return $e->getMessage();
    }
    return '';
};
$readSlot = static function ($targetGuid) use ($pdo, $guidBytes) {
    $q = $pdo->prepare(
        'SELECT dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult,dpiUpdated '
        . 'FROM directoryPlacementIntent WHERE dpiGUID=?'
    );
    $q->bindValue(1, $guidBytes($targetGuid), \PDO::PARAM_LOB);
    $q->execute();
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};
$readHostDirectoryID = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$hostID]);
    return (int)$q->fetchColumn();
};
$fixtures = [];
$snapshotMethod = new \ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
$snapshotMethod->setAccessible(true);
$createFixture = static function () use ($pdo, $guid, &$fixtures, $snapshotMethod) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $q->execute([$hostID]);
    } while ((int)$q->fetchColumn() !== 0);

    $name = 'P2S' . strtoupper(bin2hex(random_bytes(6)));
    $domain = 'u2p2.invalid';
    $oldDN = 'CN=' . $name . ',OU=Legacy,DC=u2p2,DC=invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $index = count($fixtures);
    $fixtures[$index] = [
        'host_id' => $hostID,
        'host_inserted' => false,
        'directory_inserted' => false,
        'target_guid' => null
    ];

    $insertHost = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,1,?,?,?)'
    );
    $insertHost->execute([$hostID, $name, $domain, 'OU=Managed,DC=u2p2,DC=invalid', $fingerprint]);
    $fixtures[$index]['host_inserted'] = true;
    $insertDirectory = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,NULL)'
    );
    $insertDirectory->execute([$hostID, $domain, $oldDN, $name . '$']);
    $fixtures[$index]['directory_inserted'] = true;

    $target = [
        'guid' => $guid(),
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
        'snapshot' => $snapshotMethod->invoke(null, $pdo, $hostID),
        'target' => $target,
        'destination' => $destination,
        'dc' => 'dc1.u2p2.invalid',
        'nc' => 'DC=u2p2,DC=invalid',
        'rdn' => 'CN=' . $name
    ];
    $fixtures[$index]['target_guid'] = $target['guid'];
    return [
        'host_id' => $hostID,
        'name' => $name,
        'target' => $target,
        'destination' => $destination,
        'payload' => $payload,
        'state' => ['operation' => 'join', 'name' => $name, 'target' => $target]
    ];
};
$readSlotImage = static function ($targetGuid) use ($readSlot) {
    $row = $readSlot($targetGuid);
    if (!is_array($row)) {
        return null;
    }
    return [
        'guid' => $row['dpiGUID'],
        'sid' => $row['dpiSID'],
        'host' => (int)$row['dpiHostID'],
        'generation' => (int)$row['dpiGeneration'],
        'status' => $row['dpiStatus'],
        'payload' => $row['dpiPayload'],
        'hash' => $row['dpiHash'],
        'result' => $row['dpiResult'],
        'updated' => $row['dpiUpdated']
    ];
};
$retargetPayload = static function (array $fixture, array $identity) {
    $payload = $fixture['payload'];
    $payload['target'] = $identity;
    $payload['rdn'] = 'CN=' . rtrim($fixture['name'], '$');
    return $payload;
};

try {
    // A GUID cannot be rebound to a different SID, and a SID cannot be
    // rebound to a different GUID. Compare the complete durable slot image.
    $guidOwner = $createFixture();
    $guidTicket = FOG\Agent\DirectoryPlacementIntent::prepare($guidOwner['payload']);
    $guidBefore = $readSlotImage($guidOwner['target']['guid']);
    $guidCollisionHost = $createFixture();
    $guidCollisionTarget = $guidOwner['target'];
    $guidCollisionTarget['sid'] = 'S-1-5-21-1048576-' . random_int(100000, 999999999);
    $guidCollisionTarget['account'] = $guidCollisionHost['name'] . '$';
    $guidCollisionTarget['dn'] = 'CN=' . $guidCollisionHost['name'] . ',OU=Legacy,DC=u2p2,DC=invalid';
    $guidCollisionPayload = $retargetPayload($guidCollisionHost, $guidCollisionTarget);
    $guidCollisionReason = $reason(static function () use ($guidCollisionPayload) {
        FOG\Agent\DirectoryPlacementIntent::prepare($guidCollisionPayload);
    });
    $t->check(
        'same GUID with a different SID is refused without changing the complete slot image',
        'directory_placement_identity_collision' === $guidCollisionReason
            && 1 === $guidTicket['generation']
            && $guidBefore === $readSlotImage($guidOwner['target']['guid'])
    );

    $sidOwner = $createFixture();
    $sidTicket = FOG\Agent\DirectoryPlacementIntent::prepare($sidOwner['payload']);
    $sidBefore = $readSlotImage($sidOwner['target']['guid']);
    $sidCollisionHost = $createFixture();
    $sidCollisionTarget = $sidOwner['target'];
    $sidCollisionTarget['guid'] = $guid();
    $sidCollisionTarget['account'] = $sidCollisionHost['name'] . '$';
    $sidCollisionTarget['dn'] = 'CN=' . $sidCollisionHost['name'] . ',OU=Legacy,DC=u2p2,DC=invalid';
    $sidCollisionPayload = $retargetPayload($sidCollisionHost, $sidCollisionTarget);
    $sidCollisionReason = $reason(static function () use ($sidCollisionPayload) {
        FOG\Agent\DirectoryPlacementIntent::prepare($sidCollisionPayload);
    });
    $t->check(
        'same SID with a different GUID is refused without changing the complete slot image',
        'directory_placement_identity_collision' === $sidCollisionReason
            && 1 === $sidTicket['generation']
            && $sidBefore === $readSlotImage($sidOwner['target']['guid'])
    );

    // Wrong-entry and wrong-parent checks each consume their own process-local
    // permit before refusing. The durable row must stay MAY_HAVE_RUN.
    $wrongEntry = $createFixture();
    $wrongEntryTicket = FOG\Agent\DirectoryPlacementIntent::prepare($wrongEntry['payload']);
    $wrongEntryLdap = new FOG\Net\FOGLdap();
    $wrongEntryToken = FOG\Agent\DirectoryPlacementIntent::dispatch($wrongEntryTicket, $wrongEntryLdap);
    $wrongEntryBefore = $readSlotImage($wrongEntry['target']['guid']);
    $wrongEntryReason = $reason(static function () use ($wrongEntryToken, $wrongEntryLdap, $wrongEntry) {
        FOG\Agent\DirectoryPlacementIntent::consumePermit(
            $wrongEntryToken,
            $wrongEntryLdap,
            FOG\Net\FOGLdap::guidDN('ffffffff-ffff-4fff-afff-ffffffffffff'),
            FOG\Net\FOGLdap::guidDN($wrongEntry['destination']['guid'])
        );
    });
    $wrongEntryReuse = $reason(static function () use ($wrongEntryToken, $wrongEntryLdap, $wrongEntry) {
        FOG\Agent\DirectoryPlacementIntent::consumePermit(
            $wrongEntryToken,
            $wrongEntryLdap,
            FOG\Net\FOGLdap::guidDN($wrongEntry['target']['guid']),
            FOG\Net\FOGLdap::guidDN($wrongEntry['destination']['guid'])
        );
    });
    $t->check(
        'wrong entry refuses and burns token while durable slot remains exact MAY_HAVE_RUN',
        'directory_move_dispatch_binding_changed' === $wrongEntryReason
            && 'directory_move_dispatch_permit_required' === $wrongEntryReuse
            && 'MAY_HAVE_RUN' === $wrongEntryBefore['status']
            && $wrongEntryBefore === $readSlotImage($wrongEntry['target']['guid'])
    );

    $wrongParent = $createFixture();
    $wrongParentTicket = FOG\Agent\DirectoryPlacementIntent::prepare($wrongParent['payload']);
    $wrongParentLdap = new FOG\Net\FOGLdap();
    $wrongParentToken = FOG\Agent\DirectoryPlacementIntent::dispatch($wrongParentTicket, $wrongParentLdap);
    $wrongParentBefore = $readSlotImage($wrongParent['target']['guid']);
    $wrongParentReason = $reason(static function () use ($wrongParentToken, $wrongParentLdap, $wrongParent) {
        FOG\Agent\DirectoryPlacementIntent::consumePermit(
            $wrongParentToken,
            $wrongParentLdap,
            FOG\Net\FOGLdap::guidDN($wrongParent['target']['guid']),
            FOG\Net\FOGLdap::guidDN('eeeeeeee-eeee-4eee-aeee-eeeeeeeeeeee')
        );
    });
    $wrongParentReuse = $reason(static function () use ($wrongParentToken, $wrongParentLdap, $wrongParent) {
        FOG\Agent\DirectoryPlacementIntent::consumePermit(
            $wrongParentToken,
            $wrongParentLdap,
            FOG\Net\FOGLdap::guidDN($wrongParent['target']['guid']),
            FOG\Net\FOGLdap::guidDN($wrongParent['destination']['guid'])
        );
    });
    $t->check(
        'wrong parent refuses and burns token while durable slot remains exact MAY_HAVE_RUN',
        'directory_move_dispatch_binding_changed' === $wrongParentReason
            && 'directory_move_dispatch_permit_required' === $wrongParentReuse
            && 'MAY_HAVE_RUN' === $wrongParentBefore['status']
            && $wrongParentBefore === $readSlotImage($wrongParent['target']['guid'])
    );

    // A consumed permit followed by a supplied false outcome is only a
    // lifecycle input. It does not make an LDAP request.
    $unknownOwner = $createFixture();
    $unknownTicket = FOG\Agent\DirectoryPlacementIntent::prepare($unknownOwner['payload']);
    $unknownLdap = new FOG\Net\FOGLdap();
    $unknownToken = FOG\Agent\DirectoryPlacementIntent::dispatch($unknownTicket, $unknownLdap);
    FOG\Agent\DirectoryPlacementIntent::consumePermit(
        $unknownToken,
        $unknownLdap,
        FOG\Net\FOGLdap::guidDN($unknownOwner['target']['guid']),
        FOG\Net\FOGLdap::guidDN($unknownOwner['destination']['guid'])
    );
    FOG\Agent\DirectoryPlacementIntent::finish($unknownToken, false, 'test-supplied-unknown');
    $unknownBefore = $readSlotImage($unknownOwner['target']['guid']);

    $sameTargetHost = $createFixture();
    $sameTarget = $unknownOwner['target'];
    $sameTarget['account'] = $sameTargetHost['name'] . '$';
    $sameTarget['dn'] = 'CN=' . $sameTargetHost['name'] . ',OU=Legacy,DC=u2p2,DC=invalid';
    $sameTargetPayload = $retargetPayload($sameTargetHost, $sameTarget);
    $sameTargetPrepareReason = $reason(static function () use ($sameTargetPayload) {
        FOG\Agent\DirectoryPlacementIntent::prepare($sameTargetPayload);
    });
    $sameTargetClaimReason = $reason(static function () use ($sameTargetHost, $unknownOwner) {
        FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use ($sameTargetHost, $unknownOwner) {
            $db->beginTransaction();
            try {
                FOG\Agent\DirectoryPlacementIntent::assertClaim(
                    $db,
                    $sameTargetHost['host_id'],
                    ['operation' => 'join', 'name' => $sameTargetHost['name'], 'target' => $unknownOwner['target']]
                );
                $db->commit();
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        });
    });
    $t->check(
        'UNKNOWN same GUID/SID refuses a different Host prepare and claim without slot mutation',
        'directory_placement_unresolved' === $sameTargetPrepareReason
            && 'directory_placement_target_protected' === $sameTargetClaimReason
            && 'UNKNOWN' === $unknownBefore['status']
            && $unknownBefore === $readSlotImage($unknownOwner['target']['guid'])
    );

    $factsBefore = $readSlotImage($unknownOwner['target']['guid']);
    $factsRead = $pdo->prepare('SELECT hdObservedAt FROM hostDirectory WHERE hdHostID=?');
    $factsRead->execute([$unknownOwner['host_id']]);
    $oldObservedAt = $factsRead->fetchColumn();
    $factsHostDirectory = new FOG\Items\HostDirectory($readHostDirectoryID($unknownOwner['host_id']));
    $newObservedAt = gmdate('Y-m-d H:i:s', time() - 7200);
    $factsResult = $factsHostDirectory->set('observedAt', $newObservedAt)->save();
    $factsRead = $pdo->prepare('SELECT hdObservedAt FROM hostDirectory WHERE hdHostID=?');
    $factsRead->execute([$unknownOwner['host_id']]);
    $factsStoredAt = $factsRead->fetchColumn();
    $t->check(
        'same-owner HostDirectory facts update succeeds without changing UNKNOWN slot generation/hash/state',
        false !== $factsResult && $factsStoredAt !== $oldObservedAt
            && $factsBefore === $readSlotImage($unknownOwner['target']['guid'])
    );

    $differentTarget = $createFixture();
    $differentTargetTicket = FOG\Agent\DirectoryPlacementIntent::prepare($differentTarget['payload']);
    $differentTargetImage = $readSlotImage($differentTarget['target']['guid']);
    $t->check(
        'different Host and different Target can prepare while prior Target remains UNKNOWN',
        1 === $differentTargetTicket['generation']
            && 'PREPARED' === $differentTargetImage['status']
            && 'UNKNOWN' === $readSlotImage($unknownOwner['target']['guid'])['status']
    );

    // Direct raw moveTo has no fallback: no permit is rejected before the
    // object's connection field or any LDAP API can be touched.
    $rawLdap = new FOG\Net\FOGLdap();
    $rawMoveReason = $reason(static function () use ($rawLdap, $differentTarget) {
        $rawLdap->moveTo(
            'CN=' . $differentTarget['name'] . ',OU=Legacy,DC=u2p2,DC=invalid',
            'OU=Managed,DC=u2p2,DC=invalid'
        );
    });
    $ldapLink = new \ReflectionProperty(FOG\Net\FOGLdap::class, '_ld');
    $ldapLink->setAccessible(true);
    $t->check(
        'raw FOGLdap moveTo without permit refuses before connection and reports not_sent',
        'directory_move_dispatch_permit_required' === $rawMoveReason
            && null === $ldapLink->getValue($rawLdap)
            && ['kind' => 'not_sent', 'code' => null] === $rawLdap->moveOutcome()
    );
} finally {
    // Remove only fixtures created above; do not truncate shared scratch data.
    foreach ($fixtures as $fixture) {
        if (!empty($fixture['target_guid'])) {
            $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?');
            $q->bindValue(1, $guidBytes($fixture['target_guid']), \PDO::PARAM_LOB);
            $q->execute();
        }
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

echo "NOTE: real scratch PDO and public intent/FOGLdap APIs only; no LDAP connection or request; no B39 barrier/transport claim\n";
$t->finish();
