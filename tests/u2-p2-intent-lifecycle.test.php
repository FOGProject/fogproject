<?php
/**
 * Real scratch-DB lifecycle checks for DirectoryPlacementIntent.
 * LDAP objects are inert identity tokens here: this test never connects or
 * sends LDAP, and it does not create or alter schema.
 */

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-P2 intent lifecycle: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new RuntimeException('intent lifecycle requires explicit scratch opt-in and mysql DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u2-p2-intent-lifecycle');

$pdo = new PDO(
    $dsn,
    getenv('MJ1_DB_USER') ?: 'root',
    getenv('MJ1_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new RuntimeException('intent lifecycle requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
}

// Install the live scratch PDO in the actual PDODB wrapper. DirectoryNameFence
// and DirectoryPlacementIntent therefore use their production PDO path.
$link = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
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
$throwReason = static function ($label, $expected, callable $operation) use ($t) {
    $reason = '';
    try {
        $operation();
    } catch (FOG\Agent\DirectoryFenceRefusal $e) {
        $reason = $e->getMessage();
    }
    $t->check($label, $expected === $reason);
};
$readIntent = static function ($targetGuid) use ($pdo, $guidBytes) {
    $q = $pdo->prepare(
        'SELECT dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult '
        . 'FROM directoryPlacementIntent WHERE dpiGUID=?'
    );
    $q->bindValue(1, $guidBytes($targetGuid), PDO::PARAM_LOB);
    $q->execute();
    return $q->fetch(PDO::FETCH_ASSOC);
};
$readHost = static function ($hostID) use ($pdo) {
    $q = $pdo->prepare('SELECT hostID,hostName FROM hosts WHERE hostID=?');
    $q->execute([$hostID]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
};
$expectClaimRefusal = static function ($hostID, array $state, $expected) use ($throwReason) {
    $throwReason($expected, $expected, static function () use ($hostID, $state) {
        FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use ($hostID, $state) {
            $db->beginTransaction();
            try {
                FOG\Agent\DirectoryPlacementIntent::assertClaim($db, $hostID, $state);
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        });
    });
};
$payloadForReservedName = static function (array $fixture, $name) {
    $payload = $fixture['payload'];
    $payload['target']['account'] = $name . '$';
    $payload['target']['dn'] = 'CN=' . $name . ',OU=Legacy,DC=u2p2,DC=invalid';
    $payload['rdn'] = 'CN=' . $name;
    return $payload;
};

$fixtures = [];
$cleanupNames = [];
$createFixture = static function () use ($pdo, $guid, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $check = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
        $check->execute([$hostID]);
    } while ((int)$check->fetchColumn() !== 0);

    $hostName = 'P2I' . strtoupper(bin2hex(random_bytes(6)));
    $domain = 'u2p2.invalid';
    $oldDN = 'CN=' . $hostName . ',OU=Legacy,DC=u2p2,DC=invalid';
    $fingerprint = hash('sha256', random_bytes(32));
    $fixtures[] = [
        'host_id' => $hostID,
        'host_inserted' => false,
        'directory_inserted' => false,
        'target' => ['guid' => null]
    ];
    $fixtureIndex = array_key_last($fixtures);
    $insertHost = $pdo->prepare(
        'INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) '
        . 'VALUES (?,?,\'1\',?,?,?)'
    );
    $insertHost->execute([
        $hostID,
        $hostName,
        $domain,
        'OU=Managed,DC=u2p2,DC=invalid',
        $fingerprint
    ]);
    $fixtures[$fixtureIndex]['host_inserted'] = true;
    $insertDirectory = $pdo->prepare(
        'INSERT INTO hostDirectory '
        . '(hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),NULL,NULL)'
    );
    $insertDirectory->execute([$hostID, $domain, $oldDN, $hostName . '$']);
    $fixtures[$fixtureIndex]['directory_inserted'] = true;

    $host = (new FOG\Items\Host())
        ->set('id', $hostID)
        ->set('name', $hostName)
        ->set('ADDomain', $domain)
        ->set('ADOU', 'OU=Managed,DC=u2p2,DC=invalid')
        ->set('useAD', 1)
        ->set('agentFingerprint', $fingerprint);
    $snapshotMethod = new ReflectionMethod(
        FOG\Agent\DirectoryPlacementIntent::class,
        'snapshot'
    );
    $snapshotMethod->setAccessible(true);
    $snapshot = $snapshotMethod->invoke(null, $pdo, $hostID);

    $targetGuid = $guid();
    $destinationGuid = $guid();
    $target = [
        'guid' => $targetGuid,
        'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
        'account' => $hostName . '$',
        'dn' => $oldDN
    ];
    $destination = [
        'guid' => $destinationGuid,
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
        'rdn' => 'CN=' . $hostName
    ];
    $fixture = [
        'host_id' => $hostID,
        'host_name' => $hostName,
        'target' => $target,
        'destination' => $destination,
        'payload' => $payload,
        'state' => [
            'operation' => 'join',
            'name' => $hostName,
            'target' => $target
        ]
    ];
    $fixture['host_inserted'] = true;
    $fixture['directory_inserted'] = true;
    $fixtures[$fixtureIndex] = $fixture;
    return $fixture;
};

try {
    // PREPARED is cancellable by the same claim-side authority check; the
    // ticket alone is not a dispatch permit.
    $cancel = $createFixture();
    $cancelTicket = FOG\Agent\DirectoryPlacementIntent::prepare($cancel['payload']);
    $cancelRow = $readIntent($cancel['target']['guid']);
    $t->check(
        'prepare persists PREPARED at generation 1 with a payload hash',
        is_array($cancelRow) && 'PREPARED' === $cancelRow['dpiStatus']
            && 1 === (int)$cancelRow['dpiGeneration']
            && hash_equals($cancelRow['dpiHash'], hash('sha256', $cancelRow['dpiPayload']))
            && 1 === $cancelTicket['generation']
    );
    FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use ($cancel) {
        $db->beginTransaction();
        try {
            FOG\Agent\DirectoryPlacementIntent::assertClaim(
                $db,
                $cancel['host_id'],
                $cancel['state']
            );
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    });
    $cancelled = $readIntent($cancel['target']['guid']);
    $t->check(
        'assertClaim cancels PREPARED without a dispatch token',
        is_array($cancelled) && 'CANCELLED' === $cancelled['dpiStatus']
            && 1 === (int)$cancelled['dpiGeneration']
    );
    $cancelNext = FOG\Agent\DirectoryPlacementIntent::prepare($cancel['payload']);
    $cancelNextRow = $readIntent($cancel['target']['guid']);
    $t->check(
        'terminal generation CAS advances the same GUID/SID slot',
        2 === $cancelNext['generation'] && is_array($cancelNextRow)
            && 2 === (int)$cancelNextRow['dpiGeneration']
            && 'PREPARED' === $cancelNextRow['dpiStatus']
    );
    $throwReason('stale terminal ticket cannot dispatch into the next generation', 'directory_placement_dispatch_changed', static function () use ($cancelTicket) {
        FOG\Agent\DirectoryPlacementIntent::dispatch(
            $cancelTicket,
            new FOG\Net\FOGLdap()
        );
    });
    $afterStaleDispatch = $readIntent($cancel['target']['guid']);
    $t->check(
        'stale dispatch leaves the current generation unchanged',
        is_array($afterStaleDispatch) && 2 === (int)$afterStaleDispatch['dpiGeneration']
            && 'PREPARED' === $afterStaleDispatch['dpiStatus']
    );

    // Ordinary facts may change after reservation. Dispatch must cancel only
    // its exact PREPARED generation and return no token; a later caller may
    // prepare again from a freshly loaded snapshot as a new generation.
    $snapshotMethod = new ReflectionMethod(
        FOG\Agent\DirectoryPlacementIntent::class,
        'snapshot'
    );
    $snapshotMethod->setAccessible(true);
    $snapshotChanged = $createFixture();
    $snapshotTicket = FOG\Agent\DirectoryPlacementIntent::prepare($snapshotChanged['payload']);
    $directoryIDQuery = $pdo->prepare('SELECT hdID FROM hostDirectory WHERE hdHostID=?');
    $directoryIDQuery->execute([$snapshotChanged['host_id']]);
    $snapshotDirectory = (new FOG\Items\HostDirectory((int)$directoryIDQuery->fetchColumn()))
        ->set('observedAt', gmdate('Y-m-d H:i:s', time() - 86400));
    $factsUpdateResult = $snapshotDirectory->save();
    $updatedObservedQuery = $pdo->prepare('SELECT hdObservedAt FROM hostDirectory WHERE hdHostID=?');
    $updatedObservedQuery->execute([$snapshotChanged['host_id']]);
    $updatedObservedAt = $updatedObservedQuery->fetchColumn();
    $unissuedToken = null;
    $throwReason('snapshot change during ordinary facts update cancels PREPARED before dispatch', 'directory_placement_snapshot_changed', static function () use ($snapshotTicket, &$unissuedToken) {
        $unissuedToken = FOG\Agent\DirectoryPlacementIntent::dispatch(
            $snapshotTicket,
            new FOG\Net\FOGLdap()
        );
    });
    $snapshotCancelled = $readIntent($snapshotChanged['target']['guid']);
    $t->check(
        'changed facts cancel only the saved PREPARED row and return no dispatch token',
        false !== $factsUpdateResult && null === $unissuedToken
            && $updatedObservedAt !== $snapshotTicket['payload']['snapshot']['hdObservedAt']
            && is_array($snapshotCancelled) && 'CANCELLED' === $snapshotCancelled['dpiStatus']
            && $snapshotTicket['generation'] === (int)$snapshotCancelled['dpiGeneration']
    );
    $freshSnapshot = $snapshotMethod->invoke(null, $pdo, $snapshotChanged['host_id']);
    $freshPayload = $snapshotChanged['payload'];
    $freshPayload['snapshot'] = $freshSnapshot;
    $freshTicket = FOG\Agent\DirectoryPlacementIntent::prepare($freshPayload);
    $freshPrepared = $readIntent($snapshotChanged['target']['guid']);
    $throwReason('old request cannot dispatch after facts changed and fresh generation prepared', 'directory_placement_dispatch_changed', static function () use ($snapshotTicket) {
        FOG\Agent\DirectoryPlacementIntent::dispatch(
            $snapshotTicket,
            new FOG\Net\FOGLdap()
        );
    });
    $t->check(
        'freshly loaded facts permit a new generation without reviving the old request',
        2 === $freshTicket['generation'] && is_array($freshPrepared)
            && 2 === (int)$freshPrepared['dpiGeneration']
            && 'PREPARED' === $freshPrepared['dpiStatus']
            && $freshSnapshot !== $snapshotTicket['payload']['snapshot']
    );

    // A real HostManager rename takes a PREPARED name reservation in the
    // same short SQL transaction as the hostname write. A stale ticket then
    // cannot dispatch against the newly claimed account name.
    $renameCancel = $createFixture();
    $renameClaim = 'N' . strtoupper(bin2hex(random_bytes(5)));
    $renamePayload = $payloadForReservedName($renameCancel, $renameClaim);
    $renameTicket = FOG\Agent\DirectoryPlacementIntent::prepare($renamePayload);
    $renameHost = $createFixture();
    $renameBefore = $readHost($renameHost['host_id']);
    $renameResult = (new FOG\Managers\HostManager())->update(
        ['id' => $renameHost['host_id']],
        'AND',
        ['name' => $renameClaim]
    );
    $renameCancelled = $readIntent($renameCancel['target']['guid']);
    $t->check(
        'normal HostManager rename commits PREPARED cancellation with the name change',
        true === $renameResult && is_array($renameCancelled)
            && 'CANCELLED' === $renameCancelled['dpiStatus']
            && is_array($renameBefore) && $renameBefore['hostName'] !== $renameClaim
            && $renameClaim === ($readHost($renameHost['host_id'])['hostName'] ?? null)
    );
    $throwReason('cancelled reservation ticket cannot dispatch after name reassignment', 'directory_placement_dispatch_changed', static function () use ($renameTicket) {
        FOG\Agent\DirectoryPlacementIntent::dispatch(
            $renameTicket,
            new FOG\Net\FOGLdap()
        );
    });
    $t->check(
        'failed stale dispatch leaves the name-cancelled intent terminal',
        'CANCELLED' === ($readIntent($renameCancel['target']['guid'])['dpiStatus'] ?? null)
            && $renameClaim === ($readHost($renameHost['host_id'])['hostName'] ?? null)
    );

    // Model an intervening name claim after reservation, then use the real
    // HostManager UPDATE path. The unique-name SQL failure must roll back both
    // the cancellation CAS and hostname write.
    $rollbackIntent = $createFixture();
    $rollbackClaim = 'R' . strtoupper(bin2hex(random_bytes(5)));
    $rollbackTicket = FOG\Agent\DirectoryPlacementIntent::prepare(
        $payloadForReservedName($rollbackIntent, $rollbackClaim)
    );
    $rollbackHost = $createFixture();
    $rollbackHostBefore = $readHost($rollbackHost['host_id']);
    $collisionHost = $createFixture();
    $seedCollision = $pdo->prepare('UPDATE hosts SET hostName=? WHERE hostID=?');
    $seedCollision->execute([$rollbackClaim, $collisionHost['host_id']]);
    $rollbackResult = (new FOG\Managers\HostManager())->update(
        ['id' => $rollbackHost['host_id']],
        'AND',
        ['name' => $rollbackClaim]
    );
    $rolledBackIntent = $readIntent($rollbackIntent['target']['guid']);
    $t->check(
        'failed hostname SQL rolls back PREPARED cancellation and leaves both names unchanged',
        false === $rollbackResult && !$pdo->inTransaction()
            && is_array($rolledBackIntent) && 'PREPARED' === $rolledBackIntent['dpiStatus']
            && $rollbackHostBefore === $readHost($rollbackHost['host_id'])
            && $rollbackClaim === ($readHost($collisionHost['host_id'])['hostName'] ?? null)
            && $rollbackTicket['generation'] === (int)$rolledBackIntent['dpiGeneration']
    );

    // A real new Host INSERT whose name collides with a PREPARED target name
    // commits the cancellation and preserves the INSERT's auto-increment ID
    // across the cancellation transaction's COMMIT.
    $insertIntent = $createFixture();
    $insertClaim = 'I' . strtoupper(bin2hex(random_bytes(5)));
    $insertTicket = FOG\Agent\DirectoryPlacementIntent::prepare(
        $payloadForReservedName($insertIntent, $insertClaim)
    );
    $cleanupNames[] = $insertClaim;
    $newHost = (new FOG\Items\Host())->set('name', $insertClaim);
    $insertResult = $newHost->save();
    $insertedHostID = (int)$newHost->get('id');
    $insertCancelled = $readIntent($insertIntent['target']['guid']);
    $t->check(
        'name-colliding Host INSERT returns its real auto-increment ID and commits cancellation',
        $insertResult instanceof FOG\Items\Host && $insertedHostID > 0
            && $insertClaim === ($readHost($insertedHostID)['hostName'] ?? null)
            && is_array($insertCancelled) && 'CANCELLED' === $insertCancelled['dpiStatus']
            && 1 === (int)$insertCancelled['dpiGeneration']
            && $insertTicket['generation'] === (int)$insertCancelled['dpiGeneration']
    );

    // MAY_HAVE_RUN survives both the owner-edit and same-target claim probes.
    $binding = $createFixture();
    $bindingTicket = FOG\Agent\DirectoryPlacementIntent::prepare($binding['payload']);
    $forgedTicket = $bindingTicket;
    $forgedTicket['payload']['destination']['guid'] = 'dddddddd-dddd-4ddd-addd-dddddddddddd';
    $throwReason('dispatch rejects payload mutation with unchanged ticket hash', 'directory_placement_ticket_changed', static function () use ($forgedTicket) {
        FOG\Agent\DirectoryPlacementIntent::dispatch(
            $forgedTicket,
            new FOG\Net\FOGLdap()
        );
    });
    $preparedAfterForgery = $readIntent($binding['target']['guid']);
    $t->check(
        'forged ticket leaves persisted PREPARED row and original hash unchanged',
        is_array($preparedAfterForgery)
            && 'PREPARED' === $preparedAfterForgery['dpiStatus']
            && hash_equals($bindingTicket['hash'], $preparedAfterForgery['dpiHash'])
    );
    $ldap = new FOG\Net\FOGLdap(); // Construction only; connect() is never called.
    $token = FOG\Agent\DirectoryPlacementIntent::dispatch($bindingTicket, $ldap);
    $mayRun = $readIntent($binding['target']['guid']);
    $t->check(
        'dispatch commits MAY_HAVE_RUN before returning an in-process token',
        is_array($mayRun) && 'MAY_HAVE_RUN' === $mayRun['dpiStatus']
    );
    $mayBeforeAbandon = $readIntent($binding['target']['guid']);
    $throwReason('MAY_HAVE_RUN cannot be abandoned as undispatched', 'directory_placement_cancel_changed', static function () use ($bindingTicket) {
        FOG\Agent\DirectoryPlacementIntent::abandonUndispatched($bindingTicket);
    });
    $t->check(
        'MAY_HAVE_RUN abandon refusal preserves the exact durable row',
        $mayBeforeAbandon === $readIntent($binding['target']['guid'])
    );
    $throwReason('MAY_HAVE_RUN protects its owner', 'directory_placement_owner_protected', static function () use ($binding) {
        FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use ($binding) {
            FOG\Agent\DirectoryPlacementIntent::assertOwnerIdle($db, $binding['host_id']);
        });
    });
    $expectClaimRefusal(
        $binding['host_id'] + 1,
        $binding['state'],
        'directory_placement_target_protected'
    );

    $entryDN = FOG\Net\FOGLdap::guidDN($binding['target']['guid']);
    $parentDN = FOG\Net\FOGLdap::guidDN($binding['destination']['guid']);
    $otherLdap = new FOG\Net\FOGLdap();
    $throwReason('permit rejects a different LDAP object', 'directory_move_dispatch_binding_changed', static function () use ($token, $otherLdap, $entryDN, $parentDN) {
        FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $otherLdap, $entryDN, $parentDN);
    });
    $throwReason('consumed permit cannot be reused', 'directory_move_dispatch_permit_required', static function () use ($token, $ldap, $entryDN, $parentDN) {
        FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $ldap, $entryDN, $parentDN);
    });
    $t->check(
        'binding rejection leaves durable MAY_HAVE_RUN and does not send LDAP',
        'MAY_HAVE_RUN' === ($readIntent($binding['target']['guid'])['dpiStatus'] ?? null)
    );

    $wrongEntry = $createFixture();
    $wrongEntryTicket = FOG\Agent\DirectoryPlacementIntent::prepare($wrongEntry['payload']);
    $wrongEntryLdap = new FOG\Net\FOGLdap();
    $wrongEntryToken = FOG\Agent\DirectoryPlacementIntent::dispatch($wrongEntryTicket, $wrongEntryLdap);
    $wrongEntryDN = FOG\Net\FOGLdap::guidDN($wrongEntry['target']['guid']);
    $wrongParentDN = FOG\Net\FOGLdap::guidDN($wrongEntry['destination']['guid']);
    $throwReason('permit rejects a different entry GUID DN', 'directory_move_dispatch_binding_changed', static function () use ($wrongEntryToken, $wrongEntryLdap, $wrongEntryDN, $wrongParentDN) {
        FOG\Agent\DirectoryPlacementIntent::consumePermit(
            $wrongEntryToken,
            $wrongEntryLdap,
            FOG\Net\FOGLdap::guidDN('ffffffff-ffff-4fff-afff-ffffffffffff'),
            $wrongParentDN
        );
    });
    $t->check(
        'wrong-entry rejection consumes permit but keeps MAY_HAVE_RUN',
        'MAY_HAVE_RUN' === ($readIntent($wrongEntry['target']['guid'])['dpiStatus'] ?? null)
    );

    $wrongParent = $createFixture();
    $wrongParentTicket = FOG\Agent\DirectoryPlacementIntent::prepare($wrongParent['payload']);
    $wrongParentLdap = new FOG\Net\FOGLdap();
    $wrongParentToken = FOG\Agent\DirectoryPlacementIntent::dispatch($wrongParentTicket, $wrongParentLdap);
    $wrongParentEntryDN = FOG\Net\FOGLdap::guidDN($wrongParent['target']['guid']);
    $wrongParentDN = FOG\Net\FOGLdap::guidDN($wrongParent['destination']['guid']);
    $throwReason('permit rejects a different parent GUID DN', 'directory_move_dispatch_binding_changed', static function () use ($wrongParentToken, $wrongParentLdap, $wrongParentEntryDN, $wrongParentDN) {
        FOG\Agent\DirectoryPlacementIntent::consumePermit(
            $wrongParentToken,
            $wrongParentLdap,
            $wrongParentEntryDN,
            FOG\Net\FOGLdap::guidDN('eeeeeeee-eeee-4eee-aeee-eeeeeeeeeeee')
        );
    });
    $t->check(
        'wrong-parent rejection consumes permit but keeps MAY_HAVE_RUN',
        'MAY_HAVE_RUN' === ($readIntent($wrongParent['target']['guid'])['dpiStatus'] ?? null)
    );

    // A supplied false result is only a lifecycle input. No LDAP operation or
    // transport/commit fault is claimed by this test.
    $unknown = $createFixture();
    $unknownTicket = FOG\Agent\DirectoryPlacementIntent::prepare($unknown['payload']);
    $unknownLdap = new FOG\Net\FOGLdap();
    $unknownToken = FOG\Agent\DirectoryPlacementIntent::dispatch($unknownTicket, $unknownLdap);
    $unknownEntryDN = FOG\Net\FOGLdap::guidDN($unknown['target']['guid']);
    $unknownParentDN = FOG\Net\FOGLdap::guidDN($unknown['destination']['guid']);
    $rdn = FOG\Agent\DirectoryPlacementIntent::consumePermit(
        $unknownToken,
        $unknownLdap,
        $unknownEntryDN,
        $unknownParentDN
    );
    $t->check('valid binding consumes one token and returns payload RDN', $rdn === $unknown['payload']['rdn']);
    $throwReason('finish rejects non-boolean success without consuming the result receipt', 'directory_placement_result_invalid', static function () use ($unknownToken) {
        FOG\Agent\DirectoryPlacementIntent::finish($unknownToken, 'false', 'invalid-result-type');
    });
    $t->check(
        'invalid finish type leaves durable MAY_HAVE_RUN unchanged',
        'MAY_HAVE_RUN' === ($readIntent($unknown['target']['guid'])['dpiStatus'] ?? null)
    );
    FOG\Agent\DirectoryPlacementIntent::finish($unknownToken, false, 'test-supplied-false-result');
    $unknownRow = $readIntent($unknown['target']['guid']);
    $unknownAgain = $readIntent($unknown['target']['guid']);
    $unknownResult = is_array($unknownRow)
        ? json_decode((string)$unknownRow['dpiResult'], true) : null;
    $t->check(
        'false finish persists UNKNOWN across independent reads',
        is_array($unknownRow) && 'UNKNOWN' === $unknownRow['dpiStatus']
            && is_array($unknownResult)
            && 1 === ($unknownResult['version'] ?? null)
            && false === ($unknownResult['ldap_success'] ?? null)
            && 'test-supplied-false-result' === ($unknownResult['ldap_result'] ?? null)
            && hash('sha256', 'test-supplied-false-result') === ($unknownResult['ldap_result_sha256'] ?? null)
            && 'not_attempted' === ($unknownResult['facts_projection'] ?? null)
            && false === ($unknownResult['truncated'] ?? null)
            && $unknownRow === $unknownAgain
    );
    $throwReason('finish result token cannot be recreated after terminal save', 'directory_placement_result_receipt_required', static function () use ($unknownToken) {
        FOG\Agent\DirectoryPlacementIntent::finish($unknownToken, true, 'must-not-overwrite');
    });
    $throwReason('UNKNOWN ticket cannot mint a second dispatch token', 'directory_placement_dispatch_changed', static function () use ($unknownTicket, $unknownLdap) {
        FOG\Agent\DirectoryPlacementIntent::dispatch($unknownTicket, $unknownLdap);
    });
    $t->check(
        'rejected re-dispatch leaves the UNKNOWN generation unchanged',
        'UNKNOWN' === ($readIntent($unknown['target']['guid'])['dpiStatus'] ?? null)
            && (int)($readIntent($unknown['target']['guid'])['dpiGeneration'] ?? 0) === $unknownTicket['generation']
    );
    $unknownBeforeAbandon = $readIntent($unknown['target']['guid']);
    $throwReason('UNKNOWN cannot be abandoned as undispatched', 'directory_placement_cancel_changed', static function () use ($unknownTicket) {
        FOG\Agent\DirectoryPlacementIntent::abandonUndispatched($unknownTicket);
    });
    $t->check(
        'UNKNOWN abandon refusal preserves the exact durable row',
        $unknownBeforeAbandon === $readIntent($unknown['target']['guid'])
    );
    $throwReason('UNKNOWN also protects the owner from writer release', 'directory_placement_owner_protected', static function () use ($unknown) {
        FOG\Agent\DirectoryNameFence::run(static function (PDO $db) use ($unknown) {
            FOG\Agent\DirectoryPlacementIntent::assertOwnerIdle($db, $unknown['host_id']);
        });
    });

    // Characterize the intentional facts CAS: a newer ordinary directory
    // observation between dispatch and finish is preserved. This is not an
    // assertion that SUCCEEDED and the placement cache update are atomic when
    // that facts CAS affects zero rows.
    $factsCas = $createFixture();
    $factsCasTicket = FOG\Agent\DirectoryPlacementIntent::prepare($factsCas['payload']);
    $factsCasLdap = new FOG\Net\FOGLdap(); // Opaque token only; never connected.
    $factsCasPermit = FOG\Agent\DirectoryPlacementIntent::dispatch($factsCasTicket, $factsCasLdap);
    $factsCasEntry = FOG\Net\FOGLdap::guidDN($factsCas['target']['guid']);
    $factsCasParent = FOG\Net\FOGLdap::guidDN($factsCas['destination']['guid']);
    FOG\Agent\DirectoryPlacementIntent::consumePermit(
        $factsCasPermit,
        $factsCasLdap,
        $factsCasEntry,
        $factsCasParent
    );
    $factsBeforeQuery = $pdo->prepare(
        'SELECT hdComputerDN,hdObservedAt,hdPlacementAt FROM hostDirectory WHERE hdHostID=?'
    );
    $factsBeforeQuery->execute([$factsCas['host_id']]);
    $factsBefore = $factsBeforeQuery->fetch(PDO::FETCH_ASSOC);
    $factsBeforeQuery->closeCursor();
    $newerDN = 'CN=' . $factsCas['host_name'] . ',OU=NewerFacts,DC=u2p2,DC=invalid';
    $newerObservedAt = gmdate('Y-m-d H:i:s');
    $factsMutation = $pdo->prepare(
        'UPDATE hostDirectory SET hdComputerDN=?,hdObservedAt=? WHERE hdHostID=?'
    );
    $factsMutation->execute([$newerDN, $newerObservedAt, $factsCas['host_id']]);
    $factsMutationRows = $factsMutation->rowCount();
    FOG\Agent\DirectoryPlacementIntent::finish(
        $factsCasPermit,
        true,
        'characterization-injected-success-result'
    );
    $factsAfterQuery = $pdo->prepare(
        'SELECT hdComputerDN,hdObservedAt,hdPlacementAt FROM hostDirectory WHERE hdHostID=?'
    );
    $factsAfterQuery->execute([$factsCas['host_id']]);
    $factsAfter = $factsAfterQuery->fetch(PDO::FETCH_ASSOC);
    $factsAfterQuery->closeCursor();
    $factsCasResult = $readIntent($factsCas['target']['guid']);
    echo "CONTRACT H-01: finish atomically records LDAP outcome and facts-CAS diagnostic; CAS miss preserves newer facts and old placementAt\n";
    $t->check(
        'H-01: SUCCEEDED persists while a zero-row facts CAS preserves newer facts and old placementAt',
        1 === $factsMutationRows && is_array($factsBefore) && is_array($factsAfter)
            && is_array($factsCasResult) && 'SUCCEEDED' === $factsCasResult['dpiStatus']
            && $newerDN === $factsAfter['hdComputerDN']
            && $newerObservedAt === $factsAfter['hdObservedAt']
            && $factsBefore['hdPlacementAt'] === $factsAfter['hdPlacementAt']
    );
} finally {
    // Remove only rows inserted by this test; never truncate or reset shared
    // scratch tables. Binary GUID predicates keep cleanup fixture-scoped.
    foreach ($fixtures as $fixture) {
        if (!empty($fixture['target']['guid'])) {
            $deleteIntent = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?');
            $deleteIntent->bindValue(1, $guidBytes($fixture['target']['guid']), PDO::PARAM_LOB);
            $deleteIntent->execute();
        }
        if (!empty($fixture['directory_inserted'])) {
            $deleteDirectory = $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?');
            $deleteDirectory->execute([$fixture['host_id']]);
        }
        if (!empty($fixture['host_inserted'])) {
            $deleteHost = $pdo->prepare('DELETE FROM hosts WHERE hostID=?');
            $deleteHost->execute([$fixture['host_id']]);
        }
    }
    foreach ($cleanupNames as $name) {
        $deleteDirectory = $pdo->prepare(
            'DELETE FROM hostDirectory WHERE hdHostID IN (SELECT hostID FROM hosts WHERE hostName=?)'
        );
        $deleteDirectory->execute([$name]);
        $deleteHost = $pdo->prepare('DELETE FROM hosts WHERE hostName=?');
        $deleteHost->execute([$name]);
    }
}

echo "NOTE: no LDAP connect/rename, commit-ACK-loss, or real transport fault was exercised\n";
$t->finish();
