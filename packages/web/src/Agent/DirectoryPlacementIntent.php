<?php
/**
 * The durable record of each OU placement (Move) request.
 *
 * PHP version 7.4+
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Items\HostDirectory;
use FOG\Net\FOGLdap;

/**
 * Keeps one row per computer object that FOG ever asked the directory to
 * move, keyed by the object's GUID.
 *
 * A Move is sent to somebody else's server, and the answer can be lost after
 * the server acted on it. So the row is written BEFORE the request leaves:
 * PREPARED while FOG still holds the right to send, MAY_HAVE_RUN from the
 * moment it may have been sent, and SUCCEEDED, UNKNOWN or CANCELED after.
 * Nothing re-sends a MAY_HAVE_RUN or UNKNOWN row. An operator reads the
 * directory and clears an UNKNOWN row by hand.
 *
 * No hook sits in the ORM. Each path that touches the table takes its own
 * short transaction and locks the rows it needs in one order: hosts, then
 * hostDirectory, then directoryPlacementIntent. The LDAP request itself
 * always runs outside any transaction.
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DirectoryPlacementIntent extends FOGBase
{
    /**
     * The statuses of a row that still blocks another Move of the object.
     */
    const ACTIVE = ['PREPARED', 'MAY_HAVE_RUN', 'UNKNOWN'];

    /**
     * Seconds a MAY_HAVE_RUN row is protected from an operator's clear.
     *
     * A Move that was just sent may still be running: its worker waits at
     * most FOGLdap::MOVE_TIMEOUT for the directory. Clearing the row inside
     * that window lets the next check-in send a second Move while the first
     * can still apply. Ten minutes is comfortably above the timeout, so by
     * then the first Move has either answered or been abandoned. The age is
     * read from the DB clock, the one that wrote dpiUpdated.
     */
    const CLEAR_MAY_HAVE_RUN_AFTER = 600;

    /**
     * The quoted SQL list of the ACTIVE statuses. The values are constants,
     * never input.
     *
     * @return string
     */
    private static function _activeList()
    {
        return "'" . implode("','", self::ACTIVE) . "'";
    }

    /**
     * Moves one row from one status to another, only if it still matches the
     * ticket and is still in the status the caller read.
     *
     * @param \PDO   $db         the connection, inside the caller's transaction
     * @param string $guid       dpiGUID
     * @param string $sid        dpiSID
     * @param int    $generation dpiGeneration
     * @param string $hash       dpiHash
     * @param string $from       the status the row must hold now
     * @param string $to         the status to set
     *
     * @return int the number of rows changed: 1, or 0 when the row moved on
     */
    private static function _cas(\PDO $db, $guid, $sid, $generation, $hash, $from, $to)
    {
        $q = $db->prepare('UPDATE directoryPlacementIntent SET dpiStatus=?,dpiUpdated=UTC_TIMESTAMP(6) WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? AND dpiStatus=?');
        $q->execute([$to, $guid, $sid, $generation, $hash, $from]);
        return $q->rowCount();
    }

    /**
     * Writes the new DN of a moved object into the host's directory row, only
     * if the row still holds what the request read.
     *
     * @param \PDO   $db       the connection, inside the caller's transaction
     * @param array  $snapshot the request's snapshot
     * @param int    $host     the owner's host id
     * @param string $dn       the DN to store
     * @param string $at       the placement time, in the storage time zone
     *
     * @return int the number of rows changed: 1, or 0 when the row moved on
     */
    private static function _projectDn(\PDO $db, array $snapshot, $host, $dn, $at)
    {
        $q = $db->prepare('UPDATE hostDirectory SET hdComputerDN=?,hdPlacementAt=?,hdPlacementError=\'\' WHERE hdHostID=? AND hdComputerDN <=> ? AND hdObservedAt <=> ?');
        $q->execute([$dn, $at, $host, $snapshot['hdComputerDN'], $snapshot['hdObservedAt']]);
        return $q->rowCount();
    }

    /**
     * Runs $fn inside one short DB-only transaction.
     *
     * A deadlock (1213) or a changed-since-read error (1020) answers 409, as
     * DirectoryRejoin::change() does, because nothing was written.
     *
     * @param callable $fn function (\PDO $db): mixed
     *
     * @return mixed whatever $fn returns
     *
     * @throws \RuntimeException directory_intent_nested_transaction,
     *                           rejoin_deadlock_retry or rejoin_plan_changed
     *                           (all 409), or whatever $fn throws
     */
    private static function _transaction(callable $fn)
    {
        $db = self::$DB->link();
        if ($db->inTransaction()) {
            throw new \RuntimeException('directory_intent_nested_transaction', 409);
        }
        $db->beginTransaction();
        try {
            $result = $fn($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            // Not inTransaction(): PHPStan cannot see that beginTransaction()
            // changes the answer. rollBack() throws when the server already
            // ended the transaction, which is the same end state.
            try {
                $db->rollBack();
            } catch (\PDOException $ignored) {
                // No transaction left to roll back.
            }
            if ($e instanceof \PDOException) {
                $driverCode = (int)($e->errorInfo[1] ?? 0);
                if (1213 === $driverCode) {
                    throw new \RuntimeException('rejoin_deadlock_retry', 409, $e);
                }
                if (1020 === $driverCode) {
                    throw new \RuntimeException('rejoin_plan_changed', 409, $e);
                }
            }
            throw $e;
        }
    }

    /**
     * Runs one statement and returns every row, leaving no open cursor.
     *
     * @param \PDO   $db     the connection
     * @param string $sql    the statement
     * @param array  $params its bound values
     *
     * @return array<int,array<string,mixed>>
     */
    private static function _rows(\PDO $db, $sql, array $params = [])
    {
        $q = $db->prepare($sql);
        $q->execute($params);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC);
        $q->closeCursor();
        return $rows;
    }

    /**
     * The 16 raw bytes of a GUID given as a UUID.
     *
     * @param mixed $guid the UUID
     *
     * @return string
     *
     * @throws \RuntimeException directory_intent_guid_invalid (409)
     */
    private static function _guid($guid)
    {
        if (!is_string($guid) || !DirectoryRejoin::guid($guid)) {
            throw new \RuntimeException('directory_intent_guid_invalid', 409);
        }
        return FOGLdap::guidBytes($guid);
    }

    /**
     * A GUID's UUID text, from its stored 16 bytes.
     *
     * @param string $bytes the dpiGUID value
     *
     * @return string
     */
    private static function _uuid($bytes)
    {
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    /**
     * Checks a SID string.
     *
     * @param mixed $sid the SID
     *
     * @return string
     *
     * @throws \RuntimeException directory_intent_sid_invalid (409)
     */
    private static function _sid($sid)
    {
        if (!is_string($sid) || strlen($sid) > 184
            || !preg_match('/^S-1-(?:0|[1-9][0-9]*)(?:-(?:0|[1-9][0-9]*))+$/D', $sid)
        ) {
            throw new \RuntimeException('directory_intent_sid_invalid', 409);
        }
        return $sid;
    }

    /**
     * Decodes and validates a stored row's payload against its own columns.
     *
     * @param array $row a directoryPlacementIntent row
     *
     * @return array the payload
     *
     * @throws \RuntimeException directory_intent_state_invalid (409)
     */
    private static function _payload(array $row)
    {
        $payload = json_decode((string)$row['dpiPayload'], true);
        if (!is_array($payload) || !isset($payload['target']['guid'], $payload['target']['sid'], $payload['host_id'])
            || self::_guid($payload['target']['guid']) !== $row['dpiGUID']
            || self::_sid($payload['target']['sid']) !== $row['dpiSID']
            || (int)$payload['host_id'] !== (int)$row['dpiHostID']
            || !hash_equals((string)$row['dpiHash'], hash('sha256', (string)$row['dpiPayload']))
            || !in_array($row['dpiStatus'], array_merge(self::ACTIVE, ['SUCCEEDED', 'CANCELED']), true)
            || (int)$row['dpiGeneration'] < 1
        ) {
            throw new \RuntimeException('directory_intent_state_invalid', 409);
        }
        return $payload;
    }

    /**
     * Cancels PREPARED rows the caller has locked. A PREPARED row proves no
     * Move was sent, so it is safe to drop. Any other status is refused.
     *
     * @param \PDO  $db   the connection, inside the caller's transaction
     * @param array $rows locked directoryPlacementIntent rows
     *
     * @return void
     *
     * @throws \RuntimeException directory_placement_cancel_boundary_required
     *                           or directory_placement_cancel_changed (409)
     */
    public static function cancelPrepared(\PDO $db, array $rows)
    {
        if (!$db->inTransaction()) {
            throw new \RuntimeException('directory_placement_cancel_boundary_required', 409);
        }
        foreach ($rows as $row) {
            if (1 !== self::_cas($db, $row['dpiGUID'], $row['dpiSID'], $row['dpiGeneration'], $row['dpiHash'], 'PREPARED', 'CANCELED')) {
                throw new \RuntimeException('directory_placement_cancel_changed', 409);
            }
        }
    }

    /**
     * Cancels the host's PREPARED rows. Called inside the config change of
     * DirectoryRejoin::configure(), which already holds the host and
     * hostDirectory locks. A row that may have run refuses the change.
     *
     * @param \PDO $db   the connection, inside the caller's transaction
     * @param int  $host the owner's host id
     *
     * @return void
     *
     * @throws \RuntimeException directory_placement_owner_protected (409)
     */
    public static function cancelOwnerPrepared(\PDO $db, $host)
    {
        $cancel = [];
        foreach (self::_rows($db, 'SELECT * FROM directoryPlacementIntent WHERE dpiHostID=? AND dpiStatus IN (' . self::_activeList() . ') FOR UPDATE', [(int)$host]) as $row) {
            if ('PREPARED' !== $row['dpiStatus']) {
                throw new \RuntimeException('directory_placement_owner_protected', 409);
            }
            $cancel[] = $row;
        }
        self::cancelPrepared($db, $cancel);
    }

    /**
     * Refuses a native operation that touches the same computer object as an
     * active placement row. Called inside the claim's transaction, after the
     * host and hostDirectory rows are locked. A PREPARED row is canceled so
     * the operation can go on. A row that may have run refuses it.
     *
     * A row with an unreadable payload still matches by its GUID and SID
     * columns, so one bad row never blocks a claim for a different object.
     *
     * @param \PDO  $db    the connection, inside the claim's transaction
     * @param int   $own   the claiming host's id
     * @param array $state the state being claimed
     *
     * @return void
     *
     * @throws \RuntimeException directory_placement_target_protected (409)
     */
    public static function assertClaim(\PDO $db, $own, array $state)
    {
        $cancel = [];
        foreach (self::_rows($db, 'SELECT * FROM directoryPlacementIntent WHERE dpiStatus IN (' . self::_activeList() . ') FOR UPDATE') as $row) {
            $same = !empty($state['target']['guid']) && DirectoryRejoin::guid($state['target']['guid'])
                && self::_guid($state['target']['guid']) === $row['dpiGUID'];
            $same = $same || (!empty($state['target']['sid']) && $state['target']['sid'] === $row['dpiSID']);
            try {
                $account = rtrim((string)(self::_payload($row)['target']['account'] ?? ''), '$');
                $same = $same || ('' !== $account && !strcasecmp($account, (string)($state['name'] ?? '')));
                $same = $same || ('rename' === ($state['operation'] ?? '')
                    && '' !== $account && !strcasecmp($account, (string)($state['expected_current'] ?? '')));
            } catch (\RuntimeException $e) {
                self::logFault('directory placement row unreadable for host ' . (int)$row['dpiHostID']);
            }
            if (!$same) {
                continue;
            }
            if ('PREPARED' !== $row['dpiStatus']) {
                throw new \RuntimeException('directory_placement_target_protected', 409);
            }
            $cancel[] = $row;
        }
        self::cancelPrepared($db, $cancel);
    }

    /**
     * The hosts among $ids that own a placement row which may have run, or
     * whose result is unknown. An operator clears these before a delete.
     *
     * @param int[] $ids host ids
     *
     * @return int[]
     */
    public static function protectedOwners(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $rows = self::_rows(
            self::$DB->link(),
            'SELECT DISTINCT dpiHostID FROM directoryPlacementIntent WHERE dpiStatus IN (\'MAY_HAVE_RUN\',\'UNKNOWN\') AND dpiHostID IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );
        return array_map('intval', array_column($rows, 'dpiHostID'));
    }

    /**
     * The placement requests of a host that need an operator: those that may
     * have run and those whose result is unknown.
     *
     * @param Host $host the host
     *
     * @return array<int,array{guid:string,status:string,updated:string,destination:string}>
     */
    public static function pendingReview(Host $host)
    {
        $out = [];
        foreach (self::_rows(
            self::$DB->link(),
            'SELECT * FROM directoryPlacementIntent WHERE dpiHostID=? AND dpiStatus IN (\'MAY_HAVE_RUN\',\'UNKNOWN\') ORDER BY dpiUpdated',
            [(int)$host->get('id')]
        ) as $row) {
            try {
                $destination = (string)(self::_payload($row)['destination']['dn'] ?? '');
            } catch (\RuntimeException $e) {
                $destination = '';
            }
            $out[] = ['guid' => self::_uuid($row['dpiGUID']), 'status' => (string)$row['dpiStatus'],
                'updated' => (string)$row['dpiUpdated'], 'destination' => $destination];
        }
        return $out;
    }

    /**
     * Records an operator's decision about a Move whose result is unknown or
     * that may still be running: UNKNOWN or MAY_HAVE_RUN becomes CANCELED, so
     * the next check-in may try again and a crashed worker cannot protect the
     * host forever. Nothing is undone in the directory. A worker that later
     * calls finish() on the cleared row loses its CAS. The row stays as
     * history, and the audit text names the status that was cleared.
     *
     * @param Host   $host         the host
     * @param string $expectedGuid the GUID the operator was shown
     * @param string $actor        the operator's name
     *
     * @return void
     *
     * @throws \RuntimeException directory_intent_review_not_required,
     *                           directory_intent_review_changed or
     *                           directory_placement_move_may_be_running (409);
     *                           the last when a MAY_HAVE_RUN row is younger
     *                           than CLEAR_MAY_HAVE_RUN_AFTER
     */
    public static function clearIntentReview(Host $host, $expectedGuid, $actor)
    {
        $id = (int)$host->get('id');
        $cleared = self::_transaction(function ($db) use ($id, $expectedGuid) {
            self::_lockOwner($db, $id);
            $rows = self::_rows($db, 'SELECT *,TIMESTAMPDIFF(SECOND,dpiUpdated,UTC_TIMESTAMP(6)) AS dpiAge FROM directoryPlacementIntent WHERE dpiHostID=? AND dpiStatus IN (\'UNKNOWN\',\'MAY_HAVE_RUN\') FOR UPDATE', [$id]);
            if (!$rows) {
                throw new \RuntimeException('directory_intent_review_not_required', 409);
            }
            $row = null;
            foreach ($rows as $candidate) {
                if (!strcasecmp(self::_uuid($candidate['dpiGUID']), (string)$expectedGuid)) {
                    $row = $candidate;
                }
            }
            if (null === $row) {
                throw new \RuntimeException('directory_intent_review_changed', 409);
            }
            // The Move of a MAY_HAVE_RUN row may still be waiting on the
            // directory. UNKNOWN has an answer, so it clears at once.
            if ('MAY_HAVE_RUN' === $row['dpiStatus'] && (int)$row['dpiAge'] < self::CLEAR_MAY_HAVE_RUN_AFTER) {
                throw new \RuntimeException('directory_placement_move_may_be_running', 409);
            }
            if (1 !== self::_cas($db, $row['dpiGUID'], $row['dpiSID'], $row['dpiGeneration'], $row['dpiHash'], $row['dpiStatus'], 'CANCELED')) {
                throw new \RuntimeException('directory_intent_review_changed', 409);
            }
            return [self::_uuid($row['dpiGUID']), (string)$row['dpiStatus']];
        });
        Audit::record([
            'type' => 'host.directory.intent_review_cleared',
            'subjectType' => 'host',
            'subjectID' => $id,
            'subjectLabel' => (string)$host->get('name'),
            'permission' => 'host.edit',
            'affectedCount' => 1,
            'renderable' => 1,
            'text' => substr('cleared the ' . $cleared[1] . ' OU placement review of computer object ' . $cleared[0] . ' by ' . substr((string)$actor, 0, 100), 0, Audit::MAX_DETAIL)
        ]);
    }

    /**
     * Locks the owner's hosts row, then its hostDirectory row.
     *
     * @param \PDO $db   the connection, inside a transaction
     * @param int  $host the owner's host id
     *
     * @return void
     *
     * @throws \RuntimeException rejoin_host_missing (409)
     */
    private static function _lockOwner(\PDO $db, $host)
    {
        if (!self::_rows($db, 'SELECT hostID FROM hosts WHERE hostID=? FOR UPDATE', [(int)$host])) {
            throw new \RuntimeException('rejoin_host_missing', 409);
        }
        self::_rows($db, 'SELECT hdID FROM hostDirectory WHERE hdHostID=? FOR UPDATE', [(int)$host]);
    }

    /**
     * What a placement request was based on. A request is valid only while
     * this still reads the same. Observation timestamps are freshness
     * evidence, not part of the Target's identity, so they are left out of
     * the authority subset.
     *
     * @param \PDO $db   the connection
     * @param int  $host the owner's host id
     * @param bool $lock true to take the row locks, in order hosts then
     *                   hostDirectory
     *
     * @return array
     *
     * @throws \RuntimeException directory_placement_snapshot_unavailable,
     *                           directory_placement_held or
     *                           directory_placement_observation_required (409)
     */
    private static function _snapshot(\PDO $db, $host, $lock = false)
    {
        $suffix = $lock ? ' FOR UPDATE' : '';
        $hosts = self::_rows($db, 'SELECT hostID,hostName,hostADDomain,hostADOU,hostUseAD,hostAgentFingerprint FROM hosts WHERE hostID=?' . $suffix, [(int)$host]);
        $dirs = self::_rows($db, 'SELECT hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=?' . $suffix, [(int)$host]);
        if (1 !== count($hosts) || 1 !== count($dirs) || !(int)$hosts[0]['hostUseAD'] || !(int)$dirs[0]['hdJoined']) {
            throw new \RuntimeException('directory_placement_snapshot_unavailable', 409);
        }
        $row = $hosts[0] + $dirs[0];
        $state = DirectoryRejoin::decode($row['hdRejoinState']);
        if (!empty($state['placement_hold'])) {
            throw new \RuntimeException('directory_placement_held', 409);
        }
        if (2 === ($state['protocol'] ?? 1)) {
            $h = new Host($host);
            if (!DirectoryOperation::placementHealthy($h, DirectoryRejoin::decode($row['hdRejoinConfig']), $state)) {
                throw new \RuntimeException('directory_placement_observation_required', 409);
            }
        }
        $row['directory_authority'] = self::_nativeAuthority($state);
        unset($row['hdRejoinState']);
        return $row;
    }

    /**
     * The part of a native state that decides who owns the Target.
     *
     * @param array $state the decoded state
     *
     * @return array
     */
    private static function _nativeAuthority(array $state)
    {
        return array_intersect_key($state, array_flip(['protocol', 'phase', 'attempt_id',
            'target', 'operation', 'mode', 'fingerprint', 'last_deploy_task_id',
            'trigger_task_id', 'config_revision']));
    }

    /**
     * Refuses a request made from a Host or directory row that has changed
     * since the caller loaded it, so an old LDAP read is never attached to a
     * newly edited Host.
     *
     * @param Host          $host      the host the caller loaded
     * @param HostDirectory $directory the observation the caller loaded
     * @param array         $saved     the config and state the caller loaded
     * @param array         $snapshot  the current snapshot
     *
     * @return void
     *
     * @throws \RuntimeException directory_placement_request_changed (409)
     */
    private static function _assertRequestSnapshot(Host $host, HostDirectory $directory, array $saved, array $snapshot)
    {
        $fields = ['hostName' => $host->get('name'), 'hostADDomain' => $host->get('ADDomain'),
            'hostADOU' => $host->get('ADOU'), 'hostAgentFingerprint' => $host->get('agentFingerprint'),
            'hdDomain' => $directory->get('domain'), 'hdKind' => $directory->get('kind'),
            'hdComputerDN' => $directory->get('computerDN'), 'hdMachineAccount' => $directory->get('machineAccount'),
            'hdObservedAt' => $directory->get('observedAt')];
        foreach ($fields as $column => $value) {
            if (strcasecmp(trim((string)$snapshot[$column]), trim((string)$value))) {
                throw new \RuntimeException('directory_placement_request_changed', 409);
            }
        }
        if ((int)$snapshot['hostID'] !== (int)$host->get('id')
            || (int)$snapshot['hostUseAD'] !== (int)$host->get('useAD')
            || (int)$snapshot['hdJoined'] !== (int)$directory->get('joined')
            || DirectoryRejoin::decode($snapshot['hdRejoinConfig']) !== $saved['config']
            || $snapshot['directory_authority'] !== self::_nativeAuthority($saved['state'])
        ) {
            throw new \RuntimeException('directory_placement_request_changed', 409);
        }
    }

    /**
     * Refuses a Move when another host now holds the target account's name.
     *
     * @param \PDO  $db the connection
     * @param array $p  the request payload
     *
     * @return void
     *
     * @throws \RuntimeException directory_placement_account_reassigned (409)
     */
    private static function _checkPlacementOwner(\PDO $db, array $p)
    {
        $others = self::_rows(
            $db,
            'SELECT hostID FROM hosts WHERE hostName=? AND hostID<>?',
            [rtrim($p['target']['account'], '$'), $p['host_id']]
        );
        if ($others) {
            throw new \RuntimeException('directory_placement_account_reassigned', 409);
        }
    }

    /**
     * Whether a PREPARED row was made from a host state that no longer holds.
     * A snapshot that cannot be read counts as stale.
     *
     * @param \PDO  $db       the connection
     * @param array $row      the PREPARED row
     * @param int   $owner    the host the caller is preparing for
     * @param array $snapshot the caller's locked snapshot of that host
     *
     * @return bool
     */
    private static function _stale(\PDO $db, array $row, $owner, array $snapshot)
    {
        $payload = self::_payload($row);
        if ((int)$row['dpiHostID'] === (int)$owner) {
            return $payload['snapshot'] !== $snapshot;
        }
        try {
            return $payload['snapshot'] !== self::_snapshot($db, (int)$row['dpiHostID']);
        } catch (\RuntimeException $e) {
            return true;
        }
    }

    /**
     * A fresh already-correct OU read may refresh facts, but cannot resolve
     * another generation's uncertain Move or imply that it was acknowledged.
     *
     * @param array $payload the request payload
     *
     * @return string|null the stored time, or null when the facts changed
     *
     * @throws \RuntimeException when the request no longer holds
     */
    private static function _learnPlacement(array $payload)
    {
        return self::_transaction(function ($db) use ($payload) {
            if (self::_snapshot($db, $payload['host_id'], true) !== $payload['snapshot']) {
                throw new \RuntimeException('directory_placement_snapshot_changed', 409);
            }
            self::_checkPlacementOwner($db, $payload);
            $active = self::_rows(
                $db,
                'SELECT dpiGUID FROM directoryPlacementIntent WHERE dpiStatus IN (' . self::_activeList() . ') AND (dpiHostID=? OR dpiGUID=? OR dpiSID=?) FOR UPDATE',
                [$payload['host_id'], self::_guid($payload['target']['guid']), $payload['target']['sid']]
            );
            if ($active) {
                throw new \RuntimeException('directory_placement_target_protected', 409);
            }
            $at = self::niceDate()->setTimezone(self::storageTimeZone())->format('Y-m-d H:i:s');
            return 1 === self::_projectDn($db, $payload['snapshot'], $payload['host_id'], $payload['target']['dn'], $at) ? $at : null;
        });
    }

    /**
     * Persists a PREPARED row. The caller has no right to send yet.
     *
     * Under the owner's row locks it re-reads the snapshot, refuses a native
     * operation in a protected phase or one that overlaps the Target, and
     * then claims the object's row by GUID. A PREPARED row whose owner
     * snapshot is stale is canceled here, not by every edit path.
     *
     * @param array $payload the request: version, host_id, snapshot, target,
     *                       destination, dc, nc and rdn
     *
     * @return array the ticket for dispatch(), finish() and
     *               abandonUndispatched()
     *
     * @throws \RuntimeException a directory_* or rejoin_* refusal (409)
     */
    public static function prepare(array $payload)
    {
        $guid = self::_guid($payload['target']['guid']);
        $sid = self::_sid($payload['target']['sid']);
        $owner = (int)$payload['host_id'];
        // Non-ASCII stays escaped: the table is utf8mb3, which cannot store a
        // four-byte character such as an emoji in an OU name.
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 65536) {
            throw new \RuntimeException('directory_placement_payload_too_large', 409);
        }
        $hash = hash('sha256', $encoded);
        return self::_transaction(function ($db) use ($payload, $guid, $sid, $owner, $encoded, $hash) {
            $current = self::_snapshot($db, $owner, true);
            if ($current !== $payload['snapshot']) {
                throw new \RuntimeException('directory_placement_snapshot_changed', 409);
            }
            self::_checkPlacementOwner($db, $payload);
            if (in_array($current['directory_authority']['phase'] ?? '', DirectoryRejoin::PROTECTED_PHASES, true)) {
                throw new \RuntimeException('directory_placement_native_in_progress', 409);
            }
            DirectoryOperation::assertNoOverlap($owner, ['name' => (string)$current['hostName'], 'target' => $payload['target']]);
            $generation = 1;
            $existing = false;
            foreach (self::_rows(
                $db,
                'SELECT * FROM directoryPlacementIntent WHERE dpiHostID=? OR dpiGUID=? OR dpiSID=? FOR UPDATE',
                [$owner, $guid, $sid]
            ) as $row) {
                $sameGuid = $guid === $row['dpiGUID'];
                $sameSid = $sid === $row['dpiSID'];
                if ($sameGuid !== $sameSid) {
                    throw new \RuntimeException('directory_placement_identity_collision', 409);
                }
                if (in_array($row['dpiStatus'], self::ACTIVE, true)) {
                    if ('PREPARED' !== $row['dpiStatus'] || !self::_stale($db, $row, $owner, $current)) {
                        throw new \RuntimeException($sameGuid ? 'directory_placement_unresolved' : 'directory_placement_owner_protected', 409);
                    }
                    self::cancelPrepared($db, [$row]);
                }
                if ($sameGuid) {
                    if ((int)$row['dpiGeneration'] >= PHP_INT_MAX) {
                        throw new \RuntimeException('directory_placement_generation_exhausted', 409);
                    }
                    $generation = (int)$row['dpiGeneration'] + 1;
                    $existing = true;
                }
            }
            if (!$existing) {
                $q = $db->prepare('INSERT INTO directoryPlacementIntent (dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiUpdated) VALUES (?,?,?,?,\'PREPARED\',?,?,UTC_TIMESTAMP(6))');
                $q->execute([$guid, $sid, $owner, $generation, $encoded, $hash]);
            } else {
                $q = $db->prepare('UPDATE directoryPlacementIntent SET dpiHostID=?,dpiGeneration=?,dpiStatus=\'PREPARED\',dpiPayload=?,dpiHash=?,dpiResult=NULL,dpiUpdated=UTC_TIMESTAMP(6) WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiStatus IN (\'SUCCEEDED\',\'CANCELED\')');
                $q->execute([$owner, $generation, $encoded, $hash, $guid, $sid, $generation - 1]);
                if (1 !== $q->rowCount()) {
                    throw new \RuntimeException('directory_placement_generation_changed', 409);
                }
            }
            return ['guid' => $guid, 'sid' => $sid, 'generation' => $generation, 'hash' => $hash, 'payload' => $payload];
        });
    }

    /**
     * Cancels a request that never left. A known-unsent request is canceled,
     * never resumed or re-sent.
     *
     * @param array $ticket the ticket from prepare()
     *
     * @return void
     *
     * @throws \RuntimeException directory_placement_cancel_changed (409)
     */
    public static function abandonUndispatched(array $ticket)
    {
        self::_transaction(function ($db) use ($ticket) {
            self::_lockOwner($db, (int)$ticket['payload']['host_id']);
            $rows = self::_rows(
                $db,
                'SELECT * FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? FOR UPDATE',
                [$ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash']]
            );
            if (1 !== count($rows) || 'PREPARED' !== $rows[0]['dpiStatus']
                || self::_payload($rows[0]) !== $ticket['payload']
            ) {
                throw new \RuntimeException('directory_placement_cancel_changed', 409);
            }
            self::cancelPrepared($db, [$rows[0]]);
        });
    }

    /**
     * Takes the right to send: PREPARED becomes MAY_HAVE_RUN in a committed
     * transaction of its own. Only the caller whose update changes the row
     * may then send the request, and only once.
     *
     * If the host changed since prepare(), the row is canceled instead and
     * the reason is thrown after that cancel commits.
     *
     * @param array $ticket the ticket from prepare()
     *
     * @return void
     *
     * @throws \RuntimeException directory_placement_dispatch_changed,
     *                           directory_placement_ticket_changed or the
     *                           reason the host no longer matches (409)
     */
    public static function dispatch(array $ticket)
    {
        $refusal = null;
        self::_transaction(function ($db) use ($ticket, &$refusal) {
            $p = $ticket['payload'];
            $current = null;
            try {
                $current = self::_snapshot($db, (int)$p['host_id'], true);
            } catch (\RuntimeException $e) {
                $refusal = $e;
            }
            $stored = self::_rows(
                $db,
                'SELECT * FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? FOR UPDATE',
                [$ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash']]
            );
            if (1 !== count($stored) || 'PREPARED' !== $stored[0]['dpiStatus']) {
                throw new \RuntimeException('directory_placement_dispatch_changed', 409);
            }
            if (self::_payload($stored[0]) !== $p) {
                throw new \RuntimeException('directory_placement_ticket_changed', 409);
            }
            if (null === $refusal) {
                try {
                    if ($current !== $p['snapshot']) {
                        throw new \RuntimeException('directory_placement_snapshot_changed', 409);
                    }
                    self::_checkPlacementOwner($db, $p);
                } catch (\RuntimeException $e) {
                    $refusal = $e;
                }
            }
            if (null !== $refusal) {
                // A PREPARED row proves no request was sent, so an ordinary
                // edit must not leave the object stuck behind it.
                self::cancelPrepared($db, [$stored[0]]);
                return;
            }
            if (1 !== self::_cas($db, $ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash'], 'PREPARED', 'MAY_HAVE_RUN')) {
                throw new \RuntimeException('directory_placement_dispatch_changed', 409);
            }
        });
        if (null !== $refusal) {
            throw $refusal;
        }
    }

    /**
     * Bounded diagnostics only; neither this record nor SUCCEEDED grants a
     * new dispatch or claim. The full digest of the original result stays in
     * the record when a long diagnostic must be shortened to fit the column.
     *
     * @param bool   $success    whether the directory accepted the Move
     * @param string $result     the directory's answer
     * @param string $projection applied, cas_miss or not_attempted
     *
     * @return string
     */
    private static function _resultRecord($success, $result, $projection)
    {
        $preview = substr((string)$result, 0, 512);
        $record = ['version' => 1, 'ldap_success' => $success,
            'ldap_result' => $preview, 'ldap_result_sha256' => hash('sha256', (string)$result),
            'facts_projection' => $projection, 'truncated' => strlen((string)$result) > 512];
        do {
            // ASCII only (\uXXXX escapes): the column is utf8mb3 and a
            // four-byte character in a directory answer would fail the write.
            $encoded = json_encode($record, JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            if (strlen($encoded) <= 512) {
                return $encoded;
            }
            $preview = substr($preview, 0, max(0, strlen($preview) - 32));
            $record['ldap_result'] = $preview;
            $record['truncated'] = true;
        } while (true);
    }

    /**
     * Stores the original outcome of the sent request, together with a
     * conditional update of the host's stored DN. SUCCEEDED confirms the
     * directory's answer, not the currency of the host's facts.
     *
     * Only a MAY_HAVE_RUN row changes. If this write fails, MAY_HAVE_RUN
     * stays and keeps protecting the object. Nothing sends the request again.
     *
     * @param array  $ticket  the ticket from prepare()
     * @param mixed  $success true when the directory accepted the Move; any
     *                        other value is refused
     * @param string $result  the directory's answer
     *
     * @return void
     *
     * @throws \RuntimeException directory_placement_result_invalid or
     *                           directory_placement_result_generation_changed
     *                           (409)
     */
    public static function finish(array $ticket, $success, $result)
    {
        if (!is_bool($success)) {
            throw new \RuntimeException('directory_placement_result_invalid', 409);
        }
        self::_transaction(function ($db) use ($ticket, $success, $result) {
            $p = $ticket['payload'];
            self::_lockOwner($db, (int)$p['host_id']);
            $stored = self::_rows(
                $db,
                'SELECT * FROM directoryPlacementIntent WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? AND dpiStatus=\'MAY_HAVE_RUN\' FOR UPDATE',
                [$ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash']]
            );
            if (1 !== count($stored) || self::_payload($stored[0]) !== $p) {
                throw new \RuntimeException('directory_placement_result_generation_changed', 409);
            }
            $projection = 'not_attempted';
            if ($success) {
                $projection = 1 === self::_projectDn(
                    $db,
                    $p['snapshot'],
                    $p['host_id'],
                    $p['rdn'] . ',' . $p['destination']['dn'],
                    self::niceDate()->setTimezone(self::storageTimeZone())->format('Y-m-d H:i:s')
                ) ? 'applied' : 'cas_miss';
            }
            $q = $db->prepare('UPDATE directoryPlacementIntent SET dpiStatus=?,dpiResult=?,dpiUpdated=UTC_TIMESTAMP(6) WHERE dpiGUID=? AND dpiSID=? AND dpiGeneration=? AND dpiHash=? AND dpiStatus=\'MAY_HAVE_RUN\'');
            $q->execute([$success ? 'SUCCEEDED' : 'UNKNOWN', self::_resultRecord($success, $result, $projection),
                $ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash']]);
            if (1 !== $q->rowCount()) {
                throw new \RuntimeException('directory_placement_result_generation_changed', 409);
            }
        });
    }

    /**
     * Moves the host's computer object into the OU its host record asks for,
     * if it is not there already. Throws; DirectoryPlacement::ensure() records
     * the failure. The directory is read and written outside any transaction.
     *
     * @param Host          $host      the host
     * @param HostDirectory $directory what the machine reported
     *
     * @return void
     *
     * @throws \RuntimeException a directory_* refusal (409) or the connection
     *                           error
     */
    public static function ensure(Host $host, HostDirectory $directory)
    {
        if (!DirectoryPlacement::enabled() || !$host->get('useAD') || !$directory->get('joined')) {
            return;
        }
        $ou = trim((string)$host->get('ADOU'));
        if ('' === $ou) {
            // No OU expressed. An admin who never set one is not a machine in
            // the wrong place, and moving it somewhere would be FOG inventing
            // an intention.
            return;
        }
        if ('' !== $directory->containerDN() && !$directory->ouDrifted($ou)) {
            // The host reported its own DN and it is the right one. Nothing to
            // ask the directory, so no connection and no cooldown: a healthy
            // Windows fleet never dials LDAP at all.
            return;
        }
        // Resolved only now, past the free checks above, because a plugin may
        // answer it from its own table and a healthy host should not cost
        // that query on every poll.
        $connection = DirectoryPlacement::connection($host);
        if ('' === $connection['uri']) {
            return;
        }
        $at = (string)$directory->get('placementAt');
        if (self::validDate($at) && time() - self::niceDate($at)->getTimestamp() < DirectoryPlacement::RETRY_AFTER) {
            return;
        }
        if (self::$DB->link()->inTransaction()) {
            throw new \RuntimeException('directory_ldap_inside_transaction', 409);
        }
        $ldap = new FOGLdap();
        if (!$ldap->connect($connection['uri'], $connection['bindDN'], $connection['password'], $connection['caCert'])) {
            throw new \RuntimeException($ldap->error(), 409);
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
                || !FOGLdap::samePlacementNC($destination['dn'], $context)
            ) {
                throw new \RuntimeException('directory_placement_ldap_identity_unavailable', 409);
            }
            if ($pinned && !DirectoryRejoin::sameObject($pinned, $target['record'])) {
                throw new \RuntimeException('rejoin_target_changed', 409);
            }
            $currentParent = FOGLdap::parentDn($target['record']['dn']);
            $snapshot = self::_snapshot(self::$DB->link(), (int)$host->get('id'));
            self::_assertRequestSnapshot($host, $directory, $saved, $snapshot);
            $payload = ['version' => 1, 'host_id' => (int)$host->get('id'), 'snapshot' => $snapshot,
                'target' => $target['record'], 'destination' => $destination,
                'dc' => $context['dc'], 'nc' => $context['base_dn'], 'rdn' => FOGLdap::rdn($target['record']['dn'])];
            if (!strcasecmp($currentParent, $destination['dn'])) {
                $learnedAt = self::_learnPlacement($payload);
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
                    || !FOGLdap::samePlacementNC($parent['dn'], $context)
                ) {
                    throw new \RuntimeException('directory_placement_identity_changed_before_dispatch', 409);
                }
            } catch (\Throwable $e) {
                self::abandonUndispatched($ticket);
                throw $e;
            }
            self::dispatch($ticket);
            $success = $ldap->moveTo(
                FOGLdap::guidDN($target['record']['guid']),
                FOGLdap::guidDN($destination['guid']),
                $payload['rdn']
            );
            // MAY_HAVE_RUN stays authoritative if this write fails.
            self::finish($ticket, $success, $success ? 'ldap_success' : json_encode($ldap->moveOutcome()));
            if (!$success) {
                throw new \RuntimeException('directory_placement_result_uncertain', 409);
            }
            try {
                Audit::record([
                    'type' => 'agent.directory.move', 'subjectType' => 'host',
                    'subjectID' => (int)$host->get('id'), 'subjectLabel' => (string)$host->get('name'),
                    'renderable' => 1, 'affectedCount' => 1,
                    'text' => substr('moved the computer object to ' . $ou, 0, Audit::MAX_DETAIL),
                    'authSource' => Principal::AUTH_SOURCE
                ]);
            } catch (\Throwable $auditError) {
                self::logFault('directory placement succeeded; audit persistence failed');
            }
        } finally {
            $ldap->close();
        }
    }
}
