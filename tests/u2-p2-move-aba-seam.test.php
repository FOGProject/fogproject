<?php
/**
 * Real FOGLdap::moveTo path with a fake namespace LDAP transport.
 * Scratch PDO only; no real LDAP connection or request is made.
 */

namespace FOG\Net {
    function ldap_rename($ld, $entry, $rdn, $parent, $deleteOldRdn)
    {
        $state =& $GLOBALS['u2p2MoveAba'];
        $call = [
            'entry' => $entry,
            'rdn' => $rdn,
            'parent' => $parent,
            'delete_old_rdn' => $deleteOldRdn,
            'resolved_by' => null,
            'object' => null
        ];
        if (preg_match('/^<GUID=[0-9a-f]{32}>$/iD', (string)$entry)) {
            // Record the attempted lookup scheme even when the GUID is deleted.
            $call['resolved_by'] = 'guid';
            $object = $state['guid_entries'][$entry] ?? null;
        } elseif (isset($state['legacy_dn_entries'][$entry])) {
            // Model an unsafe old-DN route: this DN now names a different object.
            $object = $state['legacy_dn_entries'][$entry];
            $call['resolved_by'] = 'legacy_dn';
        } else {
            $object = null;
        }
        $state['last_link'] = $ld;
        if (null === $object || !isset($state['objects'][$object])) {
            $call['object'] = $object;
            $state['calls'][] = $call;
            $state['last_error'] = 'No such object';
            $state['last_errno'] = 32;
            return false;
        }
        $call['object'] = $object;
        $state['calls'][] = $call;
        $state['objects'][$object]['rdn'] = $rdn;
        $state['objects'][$object]['parent'] = $parent;
        $state['moves'][] = $object;
        $state['last_error'] = '';
        $state['last_errno'] = 0;
        return true;
    }

    function ldap_error($ld)
    {
        if (($GLOBALS['u2p2MoveAba']['last_link'] ?? null) !== $ld) {
            return 'LDAP handle mismatch';
        }
        return (string)($GLOBALS['u2p2MoveAba']['last_error'] ?? '');
    }

    function ldap_errno($ld)
    {
        if (($GLOBALS['u2p2MoveAba']['last_link'] ?? null) !== $ld) {
            return 0;
        }
        return (int)($GLOBALS['u2p2MoveAba']['last_errno'] ?? 0);
    }
}

namespace {
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('' === $dsn) {
        echo "SKIP U2-P2 move ABA seam: MJ1_DB_DSN not set\n";
        exit(0);
    }
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
        throw new RuntimeException('move ABA seam requires explicit scratch opt-in and mysql DSN');
    }

    require_once __DIR__ . '/lib/fog-test-harness.php';
    \FogTestHarness::boot('u2-p2-move-aba-seam');
    require_once dirname(__DIR__) . '/packages/web/src/Net/FOGLdap.php';

    $pdo = new \PDO(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new RuntimeException('move ABA seam requires a fog_mj1-prefixed scratch DB');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new RuntimeException('DATABASE_NAME does not match validated scratch database');
    }

    $link = new \ReflectionProperty(\FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new \FOG\Db\PDODB();
    \FogTestHarness::setStatic('FOGBase', 'DB', $db);
    \FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    \FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

    $t = new \FogChecks();
    $guid = static function () {
        $hex = bin2hex(random_bytes(16));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
            . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-'
            . substr($hex, 20, 12);
    };
    $guidBytes = static function ($value) {
        return hex2bin(str_replace('-', '', strtolower($value)));
    };
    $fixtures = [];
    $createFixture = static function () use ($pdo, $guid, &$fixtures) {
        do {
            $hostID = random_int(1500000000, 1800000000);
            $q = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=?');
            $q->execute([$hostID]);
            $exists = (int)$q->fetchColumn();
            $q->closeCursor();
        } while (0 !== $exists || isset($fixtures[(string)$hostID]));

        $name = 'P2ABA' . strtoupper(bin2hex(random_bytes(5)));
        $domain = 'u2p2.invalid';
        $fingerprint = hash('sha256', random_bytes(32));
        $oldDN = 'CN=' . $name . ',OU=Legacy,DC=u2p2,DC=invalid';
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

            $snapshotMethod = new \ReflectionMethod(\FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
            $snapshotMethod->setAccessible(true);
            $snapshot = $snapshotMethod->invoke(null, $pdo, $hostID);
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
                'snapshot' => $snapshot,
                'target' => $target,
                'destination' => $destination,
                'dc' => 'dc1.u2p2.invalid',
                'nc' => 'DC=u2p2,DC=invalid',
                'rdn' => 'CN=' . $name
            ];
            $fixtures[(string)$hostID] = [
                'host_id' => $hostID,
                'host_inserted' => $insertedHost,
                'directory_inserted' => $insertedDirectory,
                'target' => $target,
                'destination' => $destination,
                'old_dn' => $oldDN,
                'payload' => $payload
            ];
            return $fixtures[(string)$hostID];
        } catch (\Throwable $e) {
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
    $readSlot = static function ($targetGuid) use ($pdo, $guidBytes) {
        $q = $pdo->prepare(
            'SELECT dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult '
            . 'FROM directoryPlacementIntent WHERE dpiGUID=?'
        );
        $q->bindValue(1, $guidBytes($targetGuid), \PDO::PARAM_LOB);
        $q->execute();
        $row = $q->fetch(\PDO::FETCH_ASSOC);
        $q->closeCursor();
        return is_array($row) ? $row : null;
    };
    $readState = static function (array $fixture) use ($pdo, $readSlot) {
        $readRows = static function ($sql, $hostID) use ($pdo) {
            $q = $pdo->prepare($sql);
            $q->execute([$hostID]);
            $rows = $q->fetchAll(\PDO::FETCH_ASSOC);
            $q->closeCursor();
            return $rows;
        };
        return [
            'host' => $readRows('SELECT * FROM hosts WHERE hostID=?', $fixture['host_id']),
            'directory' => $readRows('SELECT * FROM hostDirectory WHERE hdHostID=?', $fixture['host_id']),
            'tasks' => $readRows('SELECT * FROM tasks WHERE taskHostID=? ORDER BY taskID', $fixture['host_id']),
            'intent' => $readSlot($fixture['target']['guid'])
        ];
    };
    $ldapField = new \ReflectionProperty(\FOG\Net\FOGLdap::class, '_ld');
    $ldapField->setAccessible(true);
    $makeLdap = static function () use ($ldapField) {
        $ldap = new \FOG\Net\FOGLdap();
        $ldapField->setValue($ldap, new \stdClass()); // inert handle for the namespaced ldap_rename seam
        return $ldap;
    };
    $resetTransport = static function () {
        $GLOBALS['u2p2MoveAba'] = [
            'guid_entries' => [],
            'legacy_dn_entries' => [],
            'objects' => [],
            'calls' => [],
            'moves' => [],
            'last_link' => null,
            'last_error' => '',
            'last_errno' => 0
        ];
    };

    try {
        // A reused old DN points at G2, while the durable permit binds G1.
        // The real moveTo method must send the GUID DN and move only G1.
        $reuse = $createFixture();
        $g1 = $reuse['target']['guid'];
        $g2 = $guid();
        $destinationGuid = $reuse['destination']['guid'];
        $entryG1 = \FOG\Net\FOGLdap::guidDN($g1);
        $entryG2 = \FOG\Net\FOGLdap::guidDN($g2);
        $parent = \FOG\Net\FOGLdap::guidDN($destinationGuid);
        $resetTransport();
        $GLOBALS['u2p2MoveAba']['guid_entries'] = [$entryG1 => 'G1', $entryG2 => 'G2', $parent => 'OU-DST'];
        $GLOBALS['u2p2MoveAba']['legacy_dn_entries'] = [$reuse['old_dn'] => 'G2'];
        $GLOBALS['u2p2MoveAba']['objects'] = [
            'G1' => ['rdn' => 'CN=G1-CURRENT', 'parent' => 'OU=Other', 'previous_dn' => $reuse['old_dn']],
            'G2' => ['rdn' => 'CN=G2', 'parent' => 'OU=Old'],
            'OU-DST' => ['rdn' => 'OU=Managed', 'parent' => 'DC=u2p2,DC=invalid']
        ];
        $ldap = $makeLdap();
        $ticket = \FOG\Agent\DirectoryPlacementIntent::prepare($reuse['payload']);
        $permit = \FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $moved = $ldap->moveTo($entryG1, $parent, $permit);
        $outcome = $ldap->moveOutcome();
        \FOG\Agent\DirectoryPlacementIntent::finish($permit, $moved, 'ldap_success');
        $calls = $GLOBALS['u2p2MoveAba']['calls'];
        $objects = $GLOBALS['u2p2MoveAba']['objects'];
        $successRow = $readSlot($g1);
        $successResult = json_decode((string)($successRow['dpiResult'] ?? ''), true);
        $t->check(
            'real moveTo routes a reused old DN by GUID and moves G1 while leaving G2 untouched',
            true === $moved && ['kind' => 'ldap_success', 'code' => 0] === $outcome
                && 1 === count($calls) && $entryG1 === $calls[0]['entry']
                && $parent === $calls[0]['parent'] && $reuse['payload']['rdn'] === $calls[0]['rdn']
                && true === $calls[0]['delete_old_rdn'] && 'guid' === $calls[0]['resolved_by']
                && 'G1' === $calls[0]['object'] && ['G1'] === $GLOBALS['u2p2MoveAba']['moves']
                && $objects['G1']['parent'] === $parent && $objects['G1']['rdn'] === $reuse['payload']['rdn']
                && $objects['G1']['previous_dn'] === $reuse['old_dn']
                && 'CN=G2' === $objects['G2']['rdn'] && 'OU=Old' === $objects['G2']['parent']
                && 'G2' === $GLOBALS['u2p2MoveAba']['legacy_dn_entries'][$reuse['old_dn']]
                && 'SUCCEEDED' === ($successRow['dpiStatus'] ?? null)
                && is_array($successResult) && 1 === ($successResult['version'] ?? null)
                && true === ($successResult['ldap_success'] ?? null)
                && 'ldap_success' === ($successResult['ldap_result'] ?? null)
                && hash('sha256', 'ldap_success') === ($successResult['ldap_result_sha256'] ?? null)
                && 'applied' === ($successResult['facts_projection'] ?? null)
                && false === ($successResult['truncated'] ?? null)
        );

        // A valid permit is one-shot. Reusing it after a successful send must
        // be refused before ldap_rename and leave every durable row as-is.
        $consumedState = $readState($reuse);
        $consumedCalls = count($GLOBALS['u2p2MoveAba']['calls']);
        $consumedMoves = count($GLOBALS['u2p2MoveAba']['moves']);
        $consumedReason = '';
        try {
            $ldap->moveTo($entryG1, $parent, $permit);
        } catch (\FOG\Agent\DirectoryFenceRefusal $e) {
            $consumedReason = $e->getMessage();
        }
        $t->check(
            'real moveTo rejects a previously consumed permit without another send or state change',
            'directory_move_dispatch_permit_required' === $consumedReason
                && $consumedCalls === count($GLOBALS['u2p2MoveAba']['calls'])
                && $consumedMoves === count($GLOBALS['u2p2MoveAba']['moves'])
                && $consumedState === $readState($reuse)
                && ['kind' => 'not_sent', 'code' => null] === $ldap->moveOutcome()
        );
        $ldapField->setValue($ldap, null);

        // An unregistered stdClass and an omitted permit are both rejected by
        // the real moveTo gate. The issued permit remains usable afterward.
        $invalid = $createFixture();
        $invalidEntry = \FOG\Net\FOGLdap::guidDN($invalid['target']['guid']);
        $invalidParent = \FOG\Net\FOGLdap::guidDN($invalid['destination']['guid']);
        $resetTransport();
        $GLOBALS['u2p2MoveAba']['guid_entries'] = [$invalidEntry => 'G1', $invalidParent => 'OU-DST'];
        $GLOBALS['u2p2MoveAba']['objects'] = [
            'G1' => ['rdn' => 'CN=G1', 'parent' => 'OU=Old'],
            'OU-DST' => ['rdn' => 'OU=Managed', 'parent' => 'DC=u2p2,DC=invalid']
        ];
        $ldap = $makeLdap();
        $invalidTicket = \FOG\Agent\DirectoryPlacementIntent::prepare($invalid['payload']);
        $validPermit = \FOG\Agent\DirectoryPlacementIntent::dispatch($invalidTicket, $ldap);
        $invalidReason = static function ($dispatchPermit) use ($ldap, $invalidEntry, $invalidParent) {
            try {
                if (null === $dispatchPermit) {
                    $ldap->moveTo($invalidEntry, $invalidParent);
                } else {
                    $ldap->moveTo($invalidEntry, $invalidParent, $dispatchPermit);
                }
                return '';
            } catch (\FOG\Agent\DirectoryFenceRefusal $e) {
                return $e->getMessage();
            }
        };
        foreach ([
            'forged' => [new \stdClass(), 'directory_move_dispatch_permit_required'],
            'missing' => [null, 'directory_move_dispatch_permit_required']
        ] as $label => [$candidatePermit, $expectedReason]) {
            $before = $readState($invalid);
            $callsBefore = count($GLOBALS['u2p2MoveAba']['calls']);
            $movesBefore = count($GLOBALS['u2p2MoveAba']['moves']);
            $reason = $invalidReason($candidatePermit);
            $t->check(
                'real moveTo rejects ' . $label . ' permit before send and preserves Host/HD/Task/intent snapshots',
                $expectedReason === $reason
                    && $callsBefore === count($GLOBALS['u2p2MoveAba']['calls'])
                    && $movesBefore === count($GLOBALS['u2p2MoveAba']['moves'])
                    && [] === $GLOBALS['u2p2MoveAba']['calls']
                    && [] === $GLOBALS['u2p2MoveAba']['moves']
                    && $before === $readState($invalid)
                    && ['kind' => 'not_sent', 'code' => null] === $ldap->moveOutcome()
            );
        }
        $invalidMoved = $ldap->moveTo($invalidEntry, $invalidParent, $validPermit);
        $invalidOutcome = $ldap->moveOutcome();
        \FOG\Agent\DirectoryPlacementIntent::finish($validPermit, $invalidMoved, 'ldap_success');
        $t->check(
            'valid one-shot permit still succeeds after forged and missing permit refusals',
            true === $invalidMoved && ['kind' => 'ldap_success', 'code' => 0] === $invalidOutcome
                && 1 === count($GLOBALS['u2p2MoveAba']['calls'])
                && [$invalidEntry] === array_column($GLOBALS['u2p2MoveAba']['calls'], 'entry')
                && ['G1'] === $GLOBALS['u2p2MoveAba']['moves']
                && 'SUCCEEDED' === ($readState($invalid)['intent']['dpiStatus'] ?? null)
        );
        $ldapField->setValue($ldap, null);

        // Complete generation N, create and dispatch N+1 for the same target,
        // then prove N's already-consumed permit cannot cross the generation seam.
        $generation = $createFixture();
        $generationEntry = \FOG\Net\FOGLdap::guidDN($generation['target']['guid']);
        $generationParent = \FOG\Net\FOGLdap::guidDN($generation['destination']['guid']);
        $resetTransport();
        $GLOBALS['u2p2MoveAba']['guid_entries'] = [$generationEntry => 'G1', $generationParent => 'OU-DST'];
        $GLOBALS['u2p2MoveAba']['objects'] = [
            'G1' => ['rdn' => 'CN=G1', 'parent' => 'OU=Old'],
            'OU-DST' => ['rdn' => 'OU=Managed', 'parent' => 'DC=u2p2,DC=invalid']
        ];
        $ldap = $makeLdap();
        $oldTicket = \FOG\Agent\DirectoryPlacementIntent::prepare($generation['payload']);
        $oldPermit = \FOG\Agent\DirectoryPlacementIntent::dispatch($oldTicket, $ldap);
        $oldMoved = $ldap->moveTo($generationEntry, $generationParent, $oldPermit);
        \FOG\Agent\DirectoryPlacementIntent::finish($oldPermit, $oldMoved, 'ldap_success');
        $snapshotMethod = new \ReflectionMethod(\FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
        $snapshotMethod->setAccessible(true);
        $nextPayload = $generation['payload'];
        $nextPayload['snapshot'] = $snapshotMethod->invoke(null, $pdo, $generation['host_id']);
        $newTicket = \FOG\Agent\DirectoryPlacementIntent::prepare($nextPayload);
        $newPermit = \FOG\Agent\DirectoryPlacementIntent::dispatch($newTicket, $ldap);
        $generationBefore = $readState($generation);
        $generationCalls = count($GLOBALS['u2p2MoveAba']['calls']);
        $generationMoves = count($GLOBALS['u2p2MoveAba']['moves']);
        $oldGenerationReason = '';
        try {
            $ldap->moveTo($generationEntry, $generationParent, $oldPermit);
        } catch (\FOG\Agent\DirectoryFenceRefusal $e) {
            $oldGenerationReason = $e->getMessage();
        }
        $t->check(
            'consumed generation N permit is refused after generation N+1 exists, before another send',
            $newTicket['generation'] === $oldTicket['generation'] + 1
                && 'MAY_HAVE_RUN' === ($generationBefore['intent']['dpiStatus'] ?? null)
                && 'directory_move_dispatch_permit_required' === $oldGenerationReason
                && $generationCalls === count($GLOBALS['u2p2MoveAba']['calls'])
                && $generationMoves === count($GLOBALS['u2p2MoveAba']['moves'])
                && $generationBefore === $readState($generation)
                && ['kind' => 'not_sent', 'code' => null] === $ldap->moveOutcome()
        );
        $newMoved = $ldap->moveTo($generationEntry, $generationParent, $newPermit);
        \FOG\Agent\DirectoryPlacementIntent::finish($newPermit, $newMoved, 'ldap_success');
        $t->check(
            'generation N+1 permit remains usable after the stale generation N refusal',
            true === $newMoved && 'SUCCEEDED' === ($readState($generation)['intent']['dpiStatus'] ?? null)
                && 2 === count($GLOBALS['u2p2MoveAba']['calls'])
                && ['G1', 'G1'] === $GLOBALS['u2p2MoveAba']['moves']
        );
        $ldapField->setValue($ldap, null);

        // A deleted GUID must fail closed even though the old DN now resolves
        // to G2. The false LDAP result is persisted as UNKNOWN, never retried.
        $deleted = $createFixture();
        $deletedGuid = $deleted['target']['guid'];
        $deletedEntry = \FOG\Net\FOGLdap::guidDN($deletedGuid);
        $deletedParent = \FOG\Net\FOGLdap::guidDN($deleted['destination']['guid']);
        $resetTransport();
        $GLOBALS['u2p2MoveAba']['guid_entries'] = [$deletedParent => 'OU-DST'];
        $GLOBALS['u2p2MoveAba']['legacy_dn_entries'] = [$deleted['old_dn'] => 'G2'];
        $GLOBALS['u2p2MoveAba']['objects'] = [
            'G2' => ['rdn' => 'CN=G2', 'parent' => 'OU=Old'],
            'OU-DST' => ['rdn' => 'OU=Managed', 'parent' => 'DC=u2p2,DC=invalid']
        ];
        $ldap = $makeLdap();
        $ticket = \FOG\Agent\DirectoryPlacementIntent::prepare($deleted['payload']);
        $permit = \FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $moved = $ldap->moveTo($deletedEntry, $deletedParent, $permit);
        $outcome = $ldap->moveOutcome();
        \FOG\Agent\DirectoryPlacementIntent::finish(
            $permit,
            $moved,
            json_encode($outcome, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
        $calls = $GLOBALS['u2p2MoveAba']['calls'];
        $deletedRow = $readSlot($deletedGuid);
        $storedResult = json_decode((string)($deletedRow['dpiResult'] ?? ''), true);
        $storedOutcome = is_array($storedResult)
            ? json_decode((string)($storedResult['ldap_result'] ?? ''), true) : null;
        $deletedChecks = [
            'false_result' => false === $moved,
            'ldap_rejection' => 'ldap_rejection' === ($outcome['kind'] ?? null),
            'no_such_object_code' => 32 === ($outcome['code'] ?? null),
            'no_such_object_message' => 'No such object' === ($outcome['message'] ?? null)
                && 'No such object' === $ldap->error(),
            'error_helper_uses_move_handle' => isset($GLOBALS['u2p2MoveAba']['last_link'])
                && $GLOBALS['u2p2MoveAba']['last_link'] === $ldapField->getValue($ldap),
            'unknown_result_persisted' => is_array($storedOutcome)
                && is_array($storedResult) && 1 === ($storedResult['version'] ?? null)
                && false === ($storedResult['ldap_success'] ?? null)
                && hash('sha256', (string)($storedResult['ldap_result'] ?? ''))
                    === ($storedResult['ldap_result_sha256'] ?? null)
                && 'not_attempted' === ($storedResult['facts_projection'] ?? null)
                && false === ($storedResult['truncated'] ?? null)
                && 'ldap_rejection' === ($storedOutcome['kind'] ?? null)
                && 32 === ($storedOutcome['code'] ?? null)
                && 'No such object' === ($storedOutcome['message'] ?? null),
            'one_call' => 1 === count($calls),
            'guid_entry' => isset($calls[0]) && $deletedEntry === $calls[0]['entry'],
            'guid_resolution' => isset($calls[0]) && 'guid' === $calls[0]['resolved_by'],
            'unresolved_object' => isset($calls[0]) && null === $calls[0]['object'],
            'no_move' => [] === $GLOBALS['u2p2MoveAba']['moves'],
            'reused_g2_unchanged' => 'CN=G2' === ($GLOBALS['u2p2MoveAba']['objects']['G2']['rdn'] ?? null)
                && 'OU=Old' === ($GLOBALS['u2p2MoveAba']['objects']['G2']['parent'] ?? null),
            'unknown_status' => 'UNKNOWN' === ($deletedRow['dpiStatus'] ?? null)
        ];
        $deletedPass = !in_array(false, $deletedChecks, true);
        if (!$deletedPass) {
            fwrite(STDERR, 'U2-P2 deleted-GUID move diagnostic: ' . json_encode([
                'case' => 'deleted-GUID with reused legacy DN',
                'entry_guid_dn' => $deletedEntry,
                'parent_guid_dn' => $deletedParent,
                'product_reason' => $ldap->error(),
                'move_return' => $moved,
                'move_outcome' => $outcome,
                'intent_row' => $deletedRow,
                'stored_result' => $storedOutcome,
                'ldap_rename_calls' => $calls,
                'fake_handle_matches_product_object' => isset($GLOBALS['u2p2MoveAba']['last_link'])
                    && $GLOBALS['u2p2MoveAba']['last_link'] === $ldapField->getValue($ldap),
                'fake_errno' => $GLOBALS['u2p2MoveAba']['last_errno'] ?? null,
                'fake_error' => $GLOBALS['u2p2MoveAba']['last_error'] ?? null,
                'moves' => $GLOBALS['u2p2MoveAba']['moves'] ?? null,
                'legacy_dn_target' => $GLOBALS['u2p2MoveAba']['legacy_dn_entries'][$deleted['old_dn']] ?? null,
                'objects' => $GLOBALS['u2p2MoveAba']['objects'] ?? null,
                'checks' => $deletedChecks
            ], JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
        }
        $t->check(
            'deleted GUID returns LDAP no-such-object, leaves reused-DN G2 untouched, and persists UNKNOWN',
            $deletedPass
                && false === $moved && 'ldap_rejection' === ($outcome['kind'] ?? null)
                && 32 === ($outcome['code'] ?? null)
                && 'No such object' === ($outcome['message'] ?? null)
                && 'No such object' === $ldap->error()
                && is_array($storedOutcome) && 'ldap_rejection' === ($storedOutcome['kind'] ?? null)
                && 32 === ($storedOutcome['code'] ?? null)
                && 'No such object' === ($storedOutcome['message'] ?? null)
                && 1 === count($calls)
                && $deletedEntry === $calls[0]['entry'] && 'guid' === $calls[0]['resolved_by']
                && null === $calls[0]['object'] && [] === $GLOBALS['u2p2MoveAba']['moves']
                && 'CN=G2' === $GLOBALS['u2p2MoveAba']['objects']['G2']['rdn']
                && 'OU=Old' === $GLOBALS['u2p2MoveAba']['objects']['G2']['parent']
                && 'UNKNOWN' === ($readSlot($deletedGuid)['dpiStatus'] ?? null)
        );
        $ldapField->setValue($ldap, null);

        // A valid permit for G1 cannot be used to dispatch a different GUID.
        $wrongEntry = $createFixture();
        $wrongGuid = $wrongEntry['target']['guid'];
        $otherGuid = $guid();
        $wrongTargetDN = \FOG\Net\FOGLdap::guidDN($wrongGuid);
        $otherEntryDN = \FOG\Net\FOGLdap::guidDN($otherGuid);
        $wrongParent = \FOG\Net\FOGLdap::guidDN($wrongEntry['destination']['guid']);
        $resetTransport();
        $GLOBALS['u2p2MoveAba']['guid_entries'] = [$wrongTargetDN => 'G1', $otherEntryDN => 'G2', $wrongParent => 'OU-DST'];
        $GLOBALS['u2p2MoveAba']['objects'] = [
            'G1' => ['rdn' => 'CN=G1', 'parent' => 'OU=Old'],
            'G2' => ['rdn' => 'CN=G2', 'parent' => 'OU=Old'],
            'OU-DST' => ['rdn' => 'OU=Managed', 'parent' => 'DC=u2p2,DC=invalid']
        ];
        $ldap = $makeLdap();
        $ticket = \FOG\Agent\DirectoryPlacementIntent::prepare($wrongEntry['payload']);
        $permit = \FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $reason = '';
        try {
            $ldap->moveTo($otherEntryDN, $wrongParent, $permit);
        } catch (\FOG\Agent\DirectoryFenceRefusal $e) {
            $reason = $e->getMessage();
        }
        $reuseReason = '';
        try {
            $ldap->moveTo($wrongTargetDN, $wrongParent, $permit);
        } catch (\FOG\Agent\DirectoryFenceRefusal $e) {
            $reuseReason = $e->getMessage();
        }
        $t->check(
            'real moveTo refuses another GUID before ldap_rename and leaves the slot MAY_HAVE_RUN',
            'directory_move_dispatch_binding_changed' === $reason
                && 'directory_move_dispatch_permit_required' === $reuseReason
                && [] === $GLOBALS['u2p2MoveAba']['calls'] && [] === $GLOBALS['u2p2MoveAba']['moves']
                && ['kind' => 'not_sent', 'code' => null] === $ldap->moveOutcome()
                && 'CN=G1' === $GLOBALS['u2p2MoveAba']['objects']['G1']['rdn']
                && 'CN=G2' === $GLOBALS['u2p2MoveAba']['objects']['G2']['rdn']
                && 'MAY_HAVE_RUN' === ($readSlot($wrongGuid)['dpiStatus'] ?? null)
        );
        $ldapField->setValue($ldap, null);
    } finally {
        foreach ($fixtures as $fixture) {
            if (!empty($fixture['target']['guid'])) {
                $q = $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiGUID=?');
                $q->bindValue(1, $guidBytes($fixture['target']['guid']), \PDO::PARAM_LOB);
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
        $GLOBALS['u2p2MoveAba'] = [];
    }

    echo "NOTE: ldap_rename/error functions are namespaced fakes. The test exercises FOGLdap::moveTo and its real permit gate but proves no actual LDAP/AD transport behavior.\n";
    $t->finish();
}
