<?php
/** One current Target slot; dispatch rights are never reconstructed from DB state. */
namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Items\HostDirectory;
use FOG\Net\FOGLdap;

class DirectoryPlacementIntent extends FOGBase
{
    private static $permits;
    private static $results;
    const ACTIVE = ['PREPARED', 'MAY_HAVE_RUN', 'UNKNOWN'];

    /** A schema label or a table name alone is not durable-state compatibility. */
    public static function validateStorage(\PDO $db)
    {
        $engines = DirectoryNameFence::rows($db, 'SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (\'hosts\',\'hostDirectory\',\'directoryPlacementIntent\')');
        if (count($engines) !== 3) {
            throw new DirectoryFenceRefusal('directory_fence_storage_missing');
        }
        foreach ($engines as $row) {
            if (0 !== strcasecmp((string)$row['ENGINE'], 'InnoDB')) {
                if (in_array($row['TABLE_NAME'], ['hosts', 'hostDirectory'], true)) {
                    throw new \RuntimeException('rejoin_transactional_tables_required', 409);
                }
                throw new DirectoryFenceRefusal('directory_fence_transactional_storage_required');
            }
        }
        $expected = [
            'dpiGUID' => ['binary(16)', 'NO'], 'dpiSID' => ['varbinary(184)', 'NO'],
            'dpiHostID' => ['int unsigned', 'NO'], 'dpiGeneration' => ['bigint unsigned', 'NO'],
            'dpiStatus' => ['varchar(20)', 'NO'], 'dpiPayload' => ['mediumtext', 'NO'],
            'dpiHash' => ['char(64)', 'NO'], 'dpiResult' => ['varchar(512)', 'YES'],
            'dpiUpdated' => ['datetime(6)', 'NO']
        ];
        $columns = DirectoryNameFence::rows($db, 'SHOW FULL COLUMNS FROM directoryPlacementIntent');
        if (array_column($columns, 'Field') !== array_keys($expected)) {
            throw new DirectoryFenceRefusal('directory_intent_storage_columns_invalid');
        }
        foreach ($columns as $column) {
            $type = strtolower($column['Type']);
            $type = preg_replace('/^(int|bigint)\([0-9]+\)( unsigned)?$/', '$1$2', $type);
            if ([$type, $column['Null']] !== $expected[$column['Field']]
                || (in_array($column['Field'], ['dpiStatus', 'dpiHash'], true) && $column['Collation'] !== 'ascii_bin')) {
                throw new DirectoryFenceRefusal('directory_intent_storage_type_invalid');
            }
        }
        $keys = [];
        foreach (DirectoryNameFence::rows($db, 'SHOW INDEX FROM directoryPlacementIntent') as $index) {
            $key = $index['Key_name'];
            $keys[$key]['unique'] = !(int)$index['Non_unique'];
            $keys[$key]['columns'][(int)$index['Seq_in_index']] = $index['Column_name'];
        }
        $shapes = [];
        foreach ($keys as $key => $value) {
            ksort($value['columns']);
            $shapes[] = ($key === 'PRIMARY' ? 'PRIMARY' : ($value['unique'] ? 'UNIQUE' : 'INDEX'))
                . ':' . implode(',', $value['columns']);
        }
        sort($shapes);
        $wanted = ['PRIMARY:dpiGUID', 'UNIQUE:dpiSID', 'INDEX:dpiHostID,dpiStatus'];
        sort($wanted);
        if ($shapes !== $wanted) {
            throw new DirectoryFenceRefusal('directory_intent_storage_keys_invalid');
        }
        $foreign = DirectoryNameFence::rows($db, 'SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'directoryPlacementIntent\' AND REFERENCED_TABLE_NAME IS NOT NULL');
        if ((int)$foreign[0]['n'] !== 0) {
            throw new DirectoryFenceRefusal('directory_intent_cascade_forbidden');
        }
    }

    private static function guid($guid)
    {
        if (!is_string($guid) || !DirectoryRejoin::guid($guid)) {
            throw new DirectoryFenceRefusal('directory_intent_guid_invalid');
        }
        return hex2bin(str_replace('-', '', strtolower($guid)));
    }

    private static function sid($sid)
    {
        if (!is_string($sid) || strlen($sid) > 184 || !preg_match('/^S-1-(?:0|[1-9][0-9]*)(?:-(?:0|[1-9][0-9]*))+$/D', $sid)) {
            throw new DirectoryFenceRefusal('directory_intent_sid_invalid');
        }
        return $sid;
    }

    /** Require the scratch/future exact contract, never create a table in product code. */
    private static function slots(\PDO $db)
    {
        return DirectoryNameFence::rows($db, 'SELECT * FROM directoryPlacementIntent ORDER BY dpiGUID');
    }

    private static function payload(array $row)
    {
        $payload = json_decode($row['dpiPayload'], true);
        if (!is_array($payload) || !isset($payload['target']['guid'], $payload['target']['sid'], $payload['host_id'])
            || self::guid($payload['target']['guid']) !== $row['dpiGUID']
            || self::sid($payload['target']['sid']) !== $row['dpiSID']
            || (int)$payload['host_id'] !== (int)$row['dpiHostID']
            || !hash_equals($row['dpiHash'], hash('sha256', $row['dpiPayload']))
            || !in_array($row['dpiStatus'], array_merge(self::ACTIVE, ['SUCCEEDED', 'CANCELLED']), true)
            || (int)$row['dpiGeneration'] < 1) {
            throw new DirectoryFenceRefusal('directory_intent_state_invalid');
        }
        return $payload;
    }

    public static function assertOwnerIdle(\PDO $db, $host)
    {
        foreach (self::slots($db) as $row) {
            self::payload($row);
            if ((int)$row['dpiHostID'] === $host && in_array($row['dpiStatus'], self::ACTIVE, true)) {
                throw new DirectoryFenceRefusal('directory_placement_owner_protected');
            }
        }
    }

    /** Gather cancelable reservations without changing durable state yet. */
    public static function editableOwner(\PDO $db, $host)
    {
        $cancel = [];
        foreach (self::slots($db) as $row) {
            self::payload($row);
            if ((int)$row['dpiHostID'] !== $host || !in_array($row['dpiStatus'], self::ACTIVE, true)) {
                continue;
            }
            if ('PREPARED' !== $row['dpiStatus']) {
                throw new DirectoryFenceRefusal('directory_placement_owner_protected');
            }
            $cancel[] = $row;
        }
        return $cancel;
    }

    public static function editableName(\PDO $db, $name, $own)
    {
        $cancel = [];
        foreach (self::slots($db) as $row) {
            $p = self::payload($row);
            if (!in_array($row['dpiStatus'], self::ACTIVE, true) || (int)$row['dpiHostID'] === $own
                || strcasecmp(rtrim($p['target']['account'], '$'), $name)) {
                continue;
            }
            if ('PREPARED' !== $row['dpiStatus']) {
                throw new DirectoryFenceRefusal('directory_placement_name_protected');
            }
            $cancel[] = $row;
        }
        return $cancel;
    }

    /** Caller owns both the common fence and its short SQL transaction. */
    public static function cancelPrepared(\PDO $db, array $rows)
    {
        if (!$db->inTransaction() || DirectoryNameFence::pdo() !== $db) {
            throw new DirectoryFenceRefusal('directory_placement_cancel_boundary_required');
        }
        foreach ($rows as $row) {
            $q = $db->prepare('UPDATE directoryPlacementIntent SET dpiStatus=\'CANCELLED\',dpiUpdated=UTC_TIMESTAMP(6) WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? AND dpiStatus=\'PREPARED\'');
            $q->execute([$row['dpiGUID'], $row['dpiSID'], $row['dpiGeneration'], $row['dpiHash']]);
            if (1 !== $q->rowCount()) {
                throw new DirectoryFenceRefusal('directory_placement_cancel_changed');
            }
        }
    }

    public static function assertNameIdle(\PDO $db, $name, $own)
    {
        foreach (self::slots($db) as $row) {
            $p = self::payload($row);
            if (in_array($row['dpiStatus'], self::ACTIVE, true) && (int)$row['dpiHostID'] !== $own
                && !strcasecmp(rtrim($p['target']['account'], '$'), $name)) {
                throw new DirectoryFenceRefusal('directory_placement_name_protected');
            }
        }
    }

    public static function assertClaim(\PDO $db, $own, array $state)
    {
        foreach (self::slots($db) as $row) {
            $p = self::payload($row);
            $same = !empty($state['target']['guid']) && self::guid($state['target']['guid']) === $row['dpiGUID'];
            $same = $same || (!empty($state['target']['sid']) && $state['target']['sid'] === $row['dpiSID']);
            $same = $same || !strcasecmp(rtrim($p['target']['account'], '$'), $state['name']);
            $same = $same || ('rename' === ($state['operation'] ?? '')
                && !strcasecmp(rtrim($p['target']['account'], '$'), $state['expected_current']));
            if (!$same || !in_array($row['dpiStatus'], self::ACTIVE, true)) {
                continue;
            }
            if ('PREPARED' !== $row['dpiStatus']) {
                throw new DirectoryFenceRefusal('directory_placement_target_protected');
            }
            self::cancelPrepared($db, [$row]);
        }
    }

    private static function snapshot(\PDO $db, $host)
    {
        $rows = DirectoryNameFence::rows($db, 'SELECT h.hostID,h.hostName,h.hostADDomain,h.hostADOU,h.hostUseAD,h.hostAgentFingerprint,d.hdJoined,d.hdKind,d.hdDomain,d.hdComputerDN,d.hdMachineAccount,d.hdObservedAt,d.hdRejoinConfig,d.hdRejoinState FROM hosts h JOIN hostDirectory d ON d.hdHostID=h.hostID WHERE h.hostID=?', [$host]);
        if (count($rows) !== 1 || !(int)$rows[0]['hostUseAD'] || !(int)$rows[0]['hdJoined']) {
            throw new DirectoryFenceRefusal('directory_placement_snapshot_unavailable');
        }
        $row = $rows[0];
        $state = DirectoryRejoin::decode($row['hdRejoinState']);
        if (!empty($state['placement_hold'])) {
            throw new DirectoryFenceRefusal('directory_placement_held');
        }
        if (2 === ($state['protocol'] ?? 1)) {
            $h = new Host($host);
            if (!DirectoryOperation::placementHealthy($h, DirectoryRejoin::decode($row['hdRejoinConfig']), $state)) {
                throw new DirectoryFenceRefusal('directory_placement_observation_required');
            }
        }
        // Observation timestamps are freshness evidence, not a new Target identity.
        $row['directory_authority'] = self::nativeAuthority($state);
        unset($row['hdRejoinState']);
        return $row;
    }

    private static function nativeAuthority(array $state)
    {
        return array_intersect_key($state, array_flip(['protocol', 'phase', 'attempt_id',
            'target', 'operation', 'mode', 'fingerprint', 'last_deploy_task_id',
            'trigger_task_id', 'config_revision']));
    }

    /** Do not attach an old LDAP read to a newly edited Host snapshot. */
    private static function assertRequestSnapshot(Host $host, HostDirectory $directory, array $saved, array $snapshot)
    {
        $fields = ['hostName' => $host->get('name'), 'hostADDomain' => $host->get('ADDomain'),
            'hostADOU' => $host->get('ADOU'), 'hostAgentFingerprint' => $host->get('agentFingerprint'),
            'hdDomain' => $directory->get('domain'), 'hdKind' => $directory->get('kind'),
            'hdComputerDN' => $directory->get('computerDN'), 'hdMachineAccount' => $directory->get('machineAccount'),
            'hdObservedAt' => $directory->get('observedAt')];
        foreach ($fields as $column => $value) {
            if (strcasecmp(trim((string)$snapshot[$column]), trim((string)$value))) {
                throw new DirectoryFenceRefusal('directory_placement_request_changed');
            }
        }
        if ((int)$snapshot['hostID'] !== (int)$host->get('id')
            || (int)$snapshot['hostUseAD'] !== (int)$host->get('useAD')
            || (int)$snapshot['hdJoined'] !== (int)$directory->get('joined')
            || DirectoryRejoin::decode($snapshot['hdRejoinConfig']) !== $saved['config']
            || $snapshot['directory_authority'] !== self::nativeAuthority($saved['state'])) {
            throw new DirectoryFenceRefusal('directory_placement_request_changed');
        }
    }

    private static function checkPlacementOwner(\PDO $db, array $p)
    {
        DirectoryNameFence::assertPlacement($p['host_id'], $p['target']);
        $others = DirectoryNameFence::rows($db, 'SELECT hostID FROM hosts WHERE LOWER(hostName)=LOWER(?) AND hostID<>?',
            [rtrim($p['target']['account'], '$'), $p['host_id']]);
        if ($others) {
            throw new DirectoryFenceRefusal('directory_placement_account_reassigned');
        }
    }

    /** A fresh already-correct OU read may refresh facts, but cannot resolve
     * another generation's uncertain Move or imply that it was acknowledged.
     */
    private static function learnPlacement(array $payload)
    {
        return DirectoryNameFence::run(function ($db) use ($payload) {
            if (self::snapshot($db, $payload['host_id']) !== $payload['snapshot']) {
                throw new DirectoryFenceRefusal('directory_placement_snapshot_changed');
            }
            self::checkPlacementOwner($db, $payload);
            self::assertOwnerIdle($db, $payload['host_id']);
            foreach (self::slots($db) as $row) {
                $p = self::payload($row);
                if (in_array($row['dpiStatus'], self::ACTIVE, true)
                    && (self::guid($payload['target']['guid']) === $row['dpiGUID']
                        || $payload['target']['sid'] === $row['dpiSID']
                        || !strcasecmp($payload['target']['account'], $p['target']['account']))) {
                    throw new DirectoryFenceRefusal('directory_placement_target_protected');
                }
            }
            $at = self::niceDate()->setTimezone(self::storageTimeZone())->format('Y-m-d H:i:s');
            $q = $db->prepare('UPDATE hostDirectory SET hdComputerDN=?,hdPlacementAt=?,hdPlacementError=\'\' WHERE hdHostID=? AND hdComputerDN <=> ? AND hdObservedAt <=> ?');
            $q->execute([$payload['target']['dn'], $at, $payload['host_id'],
                $payload['snapshot']['hdComputerDN'], $payload['snapshot']['hdObservedAt']]);
            return 1 === $q->rowCount() ? $at : null;
        });
    }

    /** Check the entry state separately from mutable post-begin transaction state. */
    private static function assertNoActiveTransaction(\PDO $db)
    {
        if ($db->inTransaction()) {
            throw new DirectoryFenceRefusal('directory_intent_nested_transaction');
        }
    }

    /** Persist PREPARED under the common fence; caller has no dispatch right yet. */
    public static function prepare(array $payload)
    {
        return DirectoryNameFence::run(function ($db) use ($payload) {
            self::assertNoActiveTransaction($db);
            $current = self::snapshot($db, $payload['host_id']);
            if ($current !== $payload['snapshot']) {
                throw new DirectoryFenceRefusal('directory_placement_snapshot_changed');
            }
            self::checkPlacementOwner($db, $payload);
            $guid = self::guid($payload['target']['guid']);
            $sid = self::sid($payload['target']['sid']);
            $generation = 1;
            foreach (self::slots($db) as $row) {
                self::payload($row);
                $sameGuid = $guid === $row['dpiGUID'];
                $sameSid = $sid === $row['dpiSID'];
                if ($sameGuid !== $sameSid) {
                    throw new DirectoryFenceRefusal('directory_placement_identity_collision');
                }
                if ($sameGuid) {
                    if (in_array($row['dpiStatus'], self::ACTIVE, true)) {
                        throw new DirectoryFenceRefusal('directory_placement_unresolved');
                    }
                    if ((int)$row['dpiGeneration'] >= PHP_INT_MAX) {
                        throw new DirectoryFenceRefusal('directory_placement_generation_exhausted');
                    }
                    $generation = (int)$row['dpiGeneration'] + 1;
                } elseif ((int)$row['dpiHostID'] === $payload['host_id'] && in_array($row['dpiStatus'], self::ACTIVE, true)) {
                    throw new DirectoryFenceRefusal('directory_placement_owner_protected');
                }
            }
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (strlen($encoded) > 65536) {
                throw new DirectoryFenceRefusal('directory_placement_payload_too_large');
            }
            $hash = hash('sha256', $encoded);
            DirectoryNameFence::begin($db);
            try {
                if (1 === $generation) {
                    $q = $db->prepare('INSERT INTO directoryPlacementIntent (dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiUpdated) VALUES (?,?,?,?,\'PREPARED\',?,?,UTC_TIMESTAMP(6))');
                    $q->execute([$guid, $sid, $payload['host_id'], $generation, $encoded, $hash]);
                } else {
                    $q = $db->prepare('UPDATE directoryPlacementIntent SET dpiHostID=?,dpiGeneration=?,dpiStatus=\'PREPARED\',dpiPayload=?,dpiHash=?,dpiResult=NULL,dpiUpdated=UTC_TIMESTAMP(6) WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiStatus IN (\'SUCCEEDED\',\'CANCELLED\')');
                    $q->execute([$payload['host_id'], $generation, $encoded, $hash, $guid, $sid, $generation - 1]);
                    if (1 !== $q->rowCount()) {
                        throw new DirectoryFenceRefusal('directory_placement_generation_changed');
                    }
                }
                DirectoryNameFence::commit($db);
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    DirectoryNameFence::rollback($db);
                }
                throw $e;
            }
            return ['guid' => $guid, 'sid' => $sid, 'generation' => $generation, 'hash' => $hash, 'payload' => $payload];
        });
    }

    /** Cancel only a known-unsent row under the current ordering fence. */
    private static function cancelUnsentRow(\PDO $db, array $row)
    {
        DirectoryNameFence::begin($db);
        try {
            self::cancelPrepared($db, [$row]);
            DirectoryNameFence::commit($db);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                DirectoryNameFence::rollback($db);
            }
            throw $e;
        }
    }

    /** A known-unsent request may be canceled, never resumed or resent. */
    public static function abandonUndispatched(array $ticket)
    {
        DirectoryNameFence::run(function ($db) use ($ticket) {
            $rows = DirectoryNameFence::rows($db, 'SELECT * FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=?',
                [$ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash']]);
            if (count($rows) !== 1 || self::payload($rows[0]) !== $ticket['payload']
                || 'PREPARED' !== $rows[0]['dpiStatus']) {
                throw new DirectoryFenceRefusal('directory_placement_cancel_changed');
            }
            self::cancelUnsentRow($db, $rows[0]);
        });
    }

    /** Only normal commit and normal lock release can return a process-local permit. */
    public static function dispatch(array $ticket, $ldap)
    {
        $bound = DirectoryNameFence::run(function ($db) use ($ticket) {
            DirectoryNameFence::assertDispatchBoundary();
            $stored = DirectoryNameFence::rows($db, 'SELECT * FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=?',
                [$ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash']]);
            if (count($stored) !== 1) {
                throw new DirectoryFenceRefusal('directory_placement_dispatch_changed');
            }
            if (self::payload($stored[0]) !== $ticket['payload']) {
                throw new DirectoryFenceRefusal('directory_placement_ticket_changed');
            }
            if ('PREPARED' !== $stored[0]['dpiStatus']) {
                throw new DirectoryFenceRefusal('directory_placement_dispatch_changed');
            }
            try {
                if (self::snapshot($db, $ticket['payload']['host_id']) !== $ticket['payload']['snapshot']) {
                    throw new DirectoryFenceRefusal('directory_placement_snapshot_changed');
                }
                self::checkPlacementOwner($db, $ticket['payload']);
            } catch (\Throwable $e) {
                // Under this same fence PREPARED proves there was no permit.
                // Ordinary facts/config changes must not leave a stuck slot.
                self::cancelUnsentRow($db, $stored[0]);
                throw $e;
            }
            DirectoryNameFence::begin($db);
            try {
                $q = $db->prepare('UPDATE directoryPlacementIntent SET dpiStatus=\'MAY_HAVE_RUN\',dpiUpdated=UTC_TIMESTAMP(6) WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? AND dpiStatus=\'PREPARED\'');
                $q->execute([$ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash']]);
                if (1 !== $q->rowCount()) {
                    throw new DirectoryFenceRefusal('directory_placement_dispatch_changed');
                }
                DirectoryNameFence::commit($db);
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    DirectoryNameFence::rollback($db);
                }
                throw $e;
            }
            $connection = DirectoryNameFence::rows($db, 'SELECT CONNECTION_ID() AS id');
            return ['ticket' => $ticket, 'pdo' => $db, 'connection_id' => (int)$connection[0]['id']];
        });
        if (null === self::$permits) {
            self::$permits = new \SplObjectStorage();
            self::$results = new \SplObjectStorage();
        }
        $token = new \stdClass();
        self::$permits[$token] = $bound + ['ldap' => $ldap, 'pid' => getmypid()];
        return $token;
    }

    /** A live original connection is required; never reconnect or substitute
     * another PDO to complete a receipt left by an uncertain connection.
     */
    private static function originalConnection(array $bound, $reason)
    {
        if (!isset($bound['pdo'], $bound['connection_id'])
            || !method_exists(self::$DB, 'peekLink')
            || self::$DB->peekLink() !== $bound['pdo']) {
            throw new DirectoryFenceRefusal($reason);
        }
        $connection = DirectoryNameFence::rows($bound['pdo'], 'SELECT CONNECTION_ID() AS id');
        if (count($connection) !== 1 || (int)$connection[0]['id'] !== $bound['connection_id']) {
            throw new DirectoryFenceRefusal($reason);
        }
    }

    public static function consumePermit($token, $ldap, $entry, $parent)
    {
        if (!is_object($token) || !self::$permits || !self::$permits->contains($token)) {
            throw new DirectoryFenceRefusal('directory_move_dispatch_permit_required');
        }
        $bound = self::$permits[$token];
        self::$permits->detach($token); // Consume before the LDAP call, including its failure paths.
        DirectoryNameFence::assertNoFenceAtDispatch();
        $p = $bound['ticket']['payload'];
        if ($bound['ldap'] !== $ldap || $bound['pid'] !== getmypid()
            || $entry !== FOGLdap::guidDN($p['target']['guid']) || $parent !== FOGLdap::guidDN($p['destination']['guid'])) {
            throw new DirectoryFenceRefusal('directory_move_dispatch_binding_changed');
        }
        self::originalConnection($bound, 'directory_move_dispatch_connection_changed');
        self::$results[$token] = $bound;
        return $p['rdn'];
    }

    /** Bounded diagnostics only; neither this record nor SUCCEEDED grants a
     * new dispatch/claim. Keep the full original-result digest if a diagnostic
     * string must be shortened to fit the existing column.
     */
    private static function resultRecord($success, $result, $projection)
    {
        $preview = substr((string)$result, 0, 512);
        $record = ['version' => 1, 'ldap_success' => $success,
            'ldap_result' => $preview, 'ldap_result_sha256' => hash('sha256', (string)$result),
            'facts_projection' => $projection, 'truncated' => strlen((string)$result) > 512];
        do {
            $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            if (strlen($encoded) <= 512) {
                return $encoded;
            }
            $preview = substr($preview, 0, max(0, strlen($preview) - 32));
            $record['ldap_result'] = $preview;
            $record['truncated'] = true;
        } while (true);
    }

    /** Persist this worker's original LDAP outcome atomically with its
     * conditional facts-projection diagnostic. SUCCEEDED confirms the original
     * LDAP response, not the currency of Host facts or placement metadata.
     * A failed write leaves durable MAY_HAVE_RUN protection; never replay LDAP.
     */
    public static function finish($token, $success, $result)
    {
        if (!is_bool($success)) {
            throw new DirectoryFenceRefusal('directory_placement_result_invalid');
        }
        if (!is_object($token) || !self::$results || !self::$results->contains($token)) {
            throw new DirectoryFenceRefusal('directory_placement_result_receipt_required');
        }
        $bound = self::$results[$token];
        if ($bound['pid'] !== getmypid()) {
            throw new DirectoryFenceRefusal('directory_placement_result_worker_changed');
        }
        $outcome = [$success, (string)$result];
        if (isset($bound['outcome']) && $bound['outcome'] !== $outcome) {
            throw new DirectoryFenceRefusal('directory_placement_result_changed');
        }
        $bound['outcome'] = $outcome;
        self::$results[$token] = $bound;
        self::originalConnection($bound, 'directory_placement_result_connection_changed');
        DirectoryNameFence::run(function ($db) use ($bound, $success, $result) {
            $t = $bound['ticket'];
            DirectoryNameFence::begin($db);
            try {
                $stored = DirectoryNameFence::rows($db,
                    'SELECT * FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? AND dpiStatus IN (\'MAY_HAVE_RUN\',\'UNKNOWN\') FOR UPDATE',
                    [$t['guid'], $t['sid'], $t['generation'], $t['hash']]);
                if (count($stored) !== 1 || self::payload($stored[0]) !== $t['payload']) {
                    throw new DirectoryFenceRefusal('directory_placement_result_generation_changed');
                }
                $p = $t['payload'];
                $projection = 'not_attempted';
                if ($success) {
                    $q = $db->prepare('UPDATE hostDirectory SET hdComputerDN=?,hdPlacementAt=?,hdPlacementError=\'\' WHERE hdHostID=? AND hdComputerDN <=> ? AND hdObservedAt <=> ?');
                    $q->execute([$p['rdn'] . ',' . $p['destination']['dn'], self::niceDate()->setTimezone(self::storageTimeZone())->format('Y-m-d H:i:s'), $p['host_id'],
                        $p['snapshot']['hdComputerDN'], $p['snapshot']['hdObservedAt']]);
                    $projection = 1 === $q->rowCount() ? 'applied' : 'cas_miss';
                    $q->closeCursor();
                }
                $diagnostic = self::resultRecord($success, $result, $projection);
                $q = $db->prepare('UPDATE directoryPlacementIntent SET dpiStatus=?,dpiResult=?,dpiUpdated=UTC_TIMESTAMP(6) WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? AND dpiStatus IN (\'MAY_HAVE_RUN\',\'UNKNOWN\')');
                $q->execute([$success ? 'SUCCEEDED' : 'UNKNOWN', $diagnostic, $t['guid'], $t['sid'], $t['generation'], $t['hash']]);
                if (1 !== $q->rowCount()) {
                    throw new DirectoryFenceRefusal('directory_placement_result_generation_changed');
                }
                DirectoryNameFence::commit($db);
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    DirectoryNameFence::rollback($db);
                }
                throw $e;
            }
        });
        self::$results->detach($token);
    }

    public static function ensure(Host $host, HostDirectory $directory)
    {
        if (!DirectoryPlacement::enabled() || !$host->get('useAD') || !$directory->get('joined')) {
            return;
        }
        $ou = trim((string)$host->get('ADOU'));
        if ('' === $ou || ('' !== $directory->containerDN() && !$directory->ouDrifted($ou))) {
            return;
        }
        $connection = DirectoryPlacement::connection($host);
        if ('' === $connection['uri']) {
            return;
        }
        $at = (string)$directory->get('placementAt');
        if (self::validDate($at) && time() - self::niceDate($at)->getTimestamp() < DirectoryPlacement::RETRY_AFTER) {
            return;
        }
        DirectoryNameFence::assertNoFenceAtDispatch();
        $ldap = new FOGLdap();
        if (!$ldap->connect($connection['uri'], $connection['bindDN'], $connection['password'], $connection['caCert'])) {
            throw new DirectoryFenceRefusal('directory_placement_connect_failed');
        }
        try {
            $context = $ldap->placementContext($connection['baseDN']);
            $saved = DirectoryRejoin::settings($host);
            $pinned = $saved['config']['target'] ?? $saved['state']['target'] ?? null;
            $target = $ldap->computerRecord($connection['baseDN'], $pinned
                ? (string)$host->get('name') : (string)$directory->get('machineAccount'));
            $destination = $ldap->containerIdentity($ou);
            if ('found' !== $context['status'] || 'found' !== $target['status'] || 'found' !== $destination['status']
                || !FOGLdap::samePlacementNC($target['record']['dn'], $context)
                || !FOGLdap::samePlacementNC($destination['dn'], $context)) {
                throw new DirectoryFenceRefusal('directory_placement_ldap_identity_unavailable');
            }
            if ($pinned && !DirectoryRejoin::sameObject($pinned, $target['record'])) {
                throw new DirectoryFenceRefusal('rejoin_target_changed');
            }
            $currentParent = FOGLdap::parentDn($target['record']['dn']);
            $snapshot = DirectoryNameFence::run(function ($db) use ($host) {
                return self::snapshot($db, (int)$host->get('id'));
            });
            self::assertRequestSnapshot($host, $directory, $saved, $snapshot);
            $payload = ['version' => 1, 'host_id' => (int)$host->get('id'), 'snapshot' => $snapshot,
                'target' => $target['record'], 'destination' => $destination,
                'dc' => $context['dc'], 'nc' => $context['base_dn'], 'rdn' => FOGLdap::rdn($target['record']['dn'])];
            if (!strcasecmp($currentParent, $destination['dn'])) {
                $learnedAt = self::learnPlacement($payload);
                if (null !== $learnedAt) {
                    $directory->set('computerDN', $target['record']['dn'])
                        ->set('placementAt', $learnedAt)->set('placementError', '');
                }
                return;
            }
            $ticket = self::prepare($payload);
            try {
                $again = $ldap->computerRecord($context['base_dn'], $target['record']['account']);
                $parent = $ldap->containerIdentity(FOGLdap::guidDN($destination['guid']));
                if ('found' !== $again['status'] || !DirectoryRejoin::sameObject($target['record'], $again['record'])
                    || 'found' !== $parent['status'] || strcasecmp($parent['guid'], $destination['guid'])
                    || !FOGLdap::samePlacementNC($parent['dn'], $context)) {
                    throw new DirectoryFenceRefusal('directory_placement_identity_changed_before_dispatch');
                }
            } catch (\Throwable $e) {
                self::abandonUndispatched($ticket);
                throw $e;
            }
            $token = self::dispatch($ticket, $ldap);
            try {
                $success = $ldap->moveTo(FOGLdap::guidDN($target['record']['guid']), FOGLdap::guidDN($destination['guid']), $token);
                self::finish($token, $success, $success ? 'ldap_success' : json_encode($ldap->moveOutcome()));
                if (!$success) {
                    throw new DirectoryFenceRefusal('directory_placement_result_uncertain');
                }
                try {
                    \FOG\Audit\Audit::record([
                        'type' => 'agent.directory.move', 'subjectType' => 'host',
                        'subjectID' => (int)$host->get('id'), 'subjectLabel' => (string)$host->get('name'),
                        'renderable' => 1, 'affectedCount' => 1,
                        'text' => substr('moved the computer object to ' . $ou, 0, \FOG\Audit\Audit::MAX_DETAIL),
                        'authSource' => \FOG\Agent\Principal::AUTH_SOURCE
                    ]);
                } catch (\Throwable $auditError) {
                    self::logFault('directory placement succeeded; audit persistence failed');
                }
            } catch (\Throwable $e) {
                // MAY_HAVE_RUN remains authoritative even if UNKNOWN/result persistence failed.
                throw $e;
            }
        } finally {
            $ldap->close();
        }
    }
}
