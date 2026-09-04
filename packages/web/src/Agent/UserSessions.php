<?php
/**
 * The user sessions an agent reports about its own host.
 *
 * PHP version 7.4+
 *
 * @category UserTracking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\Host;

/**
 * Reconciles a reported session set into `hostUserSession` (design 0008).
 *
 * The contrast to draw is with `userTracking` next door, which this does not
 * replace: that table is an append-only log of login and logout EVENTS, and
 * it cannot answer who is logged in now. A logout event needs a network round
 * trip at the one moment a machine is least able to make one, so events go
 * missing -- six of eleven sessions on the lab server have no logout at all.
 *
 * A session here is one row with two ends. The open set is re-reported, so a
 * machine that lost power closes its stale rows on the next contact instead
 * of leaving them open forever. A close the agent did not witness is marked
 * `inferred` and dated to the last time the session was seen, because
 * "we never found out" and "logged out at 11:54" are different facts and the
 * legacy table could not tell them apart.
 *
 * @category UserTracking
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UserSessions extends FOGBase
{
    /**
     * Most sessions accepted from one host in a single report.
     *
     * A busy terminal server genuinely carries dozens; this is generous
     * rather than tight. It is here because the set is attacker-controlled
     * input and the reconcile builds a statement from it, so a host claiming
     * a million sessions must fail this check rather than the database.
     */
    const MAX_SESSIONS = 512;

    /**
     * Column widths, so an overlong value is truncated here rather than
     * failing the insert under strict mode and costing the host its poll.
     */
    const WIDTHS = [
        'key' => 191,
        'user' => 255,
        'domain' => 255,
        'sid' => 191,
        'type' => 32,
        'state' => 32,
        'remote_host' => 255,
        'end_reason' => 32
    ];

    /**
     * End reasons an agent may claim. `inferred` is deliberately absent: only
     * this class sets that, and an agent that sent it would be asserting it
     * watched something it did not.
     */
    const AGENT_END_REASONS = ['logout', 'disconnect', 'service_stop'];

    /**
     * The reason recorded for a session the agent never saw end.
     */
    const END_INFERRED = 'inferred';

    /**
     * Whether to mirror sessions into the legacy `userTracking` table.
     */
    const COMPAT_SETTING = 'FOG_USERTRACKING_COMPAT_WRITE';

    /**
     * Records the host's current sessions.
     *
     * The open set is complete by contract: any session open for this host
     * and absent from it is closed. That is why the agent sends no block at
     * all when its collector could not run -- an empty open set here means
     * "nobody is logged on", and closes every session the host has.
     *
     * @param Host  $Host  the host the certificate bound
     * @param array $block the reported sessions: open[] and closed[]
     *
     * @throws \RuntimeException with an HTTP code when refused
     *
     * @return void
     */
    public static function report(Host $Host, array $block)
    {
        $open = self::_clean($block['open'] ?? [], false);
        $closed = self::_clean($block['closed'] ?? [], true);
        if (count($open) + count($closed) > self::MAX_SESSIONS) {
            throw new \RuntimeException('session set too large', 413);
        }
        $hostID = (int)$Host->get('id');
        $now = self::niceDate()->format('Y-m-d H:i:s');

        self::$DB->query('START TRANSACTION');
        try {
            // Order matters. Closures land first so a session that opened
            // and ended between two polls is closed rather than reopened by
            // its own stale presence in a previous open set. Then the open
            // set refreshes what is live, and only what is left over -- open
            // on the server, unreported by the host -- is inferred closed.
            foreach ($closed as $s) {
                self::_closeReported($hostID, $s);
            }
            $opened = self::_upsertOpen($hostID, $open, $now);
            $inferred = self::_closeUnreported($hostID, $open);
            self::$DB->query('COMMIT');
        } catch (\Exception $e) {
            self::$DB->query('ROLLBACK');
            throw $e;
        }

        if (self::compatWrites()) {
            foreach ($opened as $s) {
                self::_legacyRow($Host, $s, 1, $s['started_at']);
            }
            foreach ($closed as $s) {
                self::_legacyRow($Host, $s, 0, $s['ended_at']);
            }
            // An inferred close writes NO legacy logout row. The legacy
            // table cannot express "we inferred this", so a row there would
            // read as a witnessed logout at a time nobody observed -- the
            // exact defect design 0008 exists to stop reproducing.
        }

        if (empty($opened) && empty($closed) && 0 === $inferred) {
            // A refreshed husLastSeen is not news. Auditing every poll that
            // reported the same sessions would bury the changes that matter.
            return;
        }
        Audit::record(
            [
                'type' => 'agent.usersession',
                'subjectType' => 'host',
                'subjectID' => $hostID,
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'affectedCount' => count($opened) + count($closed) + $inferred,
                'text' => sprintf(
                    'agent reported %d open session(s): %d opened, %d ended, '
                    . '%d closed without a reported end',
                    count($open),
                    count($opened),
                    count($closed),
                    $inferred
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }

    /**
     * Whether the legacy mirror is on.
     *
     * Defaults to ON: an estate migrating to fog-agent keeps the Activity
     * page it already uses, and one running both client generations gets a
     * single merged view. It is a setting so a fully migrated estate can
     * stop paying for the duplicate rows.
     *
     * @return bool
     */
    public static function compatWrites()
    {
        $set = self::getSetting(self::COMPAT_SETTING);
        return null === $set || '' === $set ? true : (bool)$set;
    }

    /**
     * Validates and normalizes reported sessions.
     *
     * Anything unusable is dropped rather than stored wrong: a session with
     * no key cannot be reconciled against later, and one with no start has
     * no duration. Truncation is to the column width so strict mode cannot
     * reject the insert and cost the host its whole poll.
     *
     * @param array $list       the reported entries
     * @param bool  $wantClosed whether ended_at is required
     *
     * @return array normalized entries, keyed by session key + start
     */
    private static function _clean(array $list, $wantClosed)
    {
        $out = [];
        foreach ($list as $s) {
            if (!is_array($s)) {
                continue;
            }
            $key = self::_trim($s['key'] ?? '', 'key');
            $user = self::_trim($s['user'] ?? '', 'user');
            $start = self::_stamp($s['started_at'] ?? '');
            if ('' === $key || '' === $user || null === $start) {
                continue;
            }
            $row = [
                'key' => $key,
                'user' => $user,
                'domain' => self::_trim($s['domain'] ?? '', 'domain'),
                'sid' => self::_trim($s['sid'] ?? '', 'sid'),
                'type' => self::_trim($s['type'] ?? '', 'type'),
                'state' => self::_trim($s['state'] ?? '', 'state'),
                'remote_host' => self::_trim($s['remote_host'] ?? '', 'remote_host'),
                'started_at' => $start,
                'ended_at' => null,
                'end_reason' => ''
            ];
            if ($wantClosed) {
                $end = self::_stamp($s['ended_at'] ?? '');
                if (null === $end) {
                    continue;
                }
                $reason = self::_trim($s['end_reason'] ?? '', 'end_reason');
                if (!in_array($reason, self::AGENT_END_REASONS, true)) {
                    // An agent claiming `inferred`, or anything unknown, is
                    // not taken at its word: it witnessed the end, so the
                    // honest generic label is a logout.
                    $reason = 'logout';
                }
                $row['ended_at'] = $end;
                $row['end_reason'] = $reason;
            }
            $out[$key . "\0" . $start] = $row;
        }
        return $out;
    }

    /**
     * Truncates one value to its column width.
     *
     * @param mixed  $val    the reported value
     * @param string $column the WIDTHS key
     *
     * @return string
     */
    private static function _trim($val, $column)
    {
        return substr(trim((string)$val), 0, self::WIDTHS[$column]);
    }

    /**
     * Parses a reported RFC3339 timestamp into a DATETIME string.
     *
     * Returns null rather than "now" on anything unparsable. A fabricated
     * timestamp silently becomes a session duration in a report, which is
     * worse than a session that was dropped and can be re-reported.
     *
     * @param mixed $raw the reported value
     *
     * @return string|null
     */
    private static function _stamp($raw)
    {
        $raw = trim((string)$raw);
        if ('' === $raw) {
            return null;
        }
        try {
            $d = new \DateTime($raw);
        } catch (\Exception $e) {
            return null;
        }
        // storageTimeZone(), NOT the PHP default: it is the clock niceDate()
        // writes husLastSeen and every other date column on, and an inferred
        // close copies husLastSeen into husEndedAt. Converting to the PHP
        // default here put two clocks in one table -- on the lab server a
        // one-second session read as five hours, a start in local time and an
        // end in UTC. A duration that wrong is the exact failure this whole
        // design exists to stop, so the conversion is pinned by
        // tests/agent-user-sessions.test.php.
        $d->setTimezone(self::storageTimeZone());
        return $d->format('Y-m-d H:i:s');
    }

    /**
     * Closes a session the agent watched end.
     *
     * A close with no matching open row is inserted already closed: the
     * agent restarted, or the row was cleaned up, and a complete session is
     * worth more than a tidy state machine.
     *
     * @param int   $hostID the host
     * @param array $s      the normalized closed entry
     *
     * @return void
     */
    private static function _closeReported($hostID, array $s)
    {
        self::$DB->query(
            'UPDATE `hostUserSession` SET `husEndedAt`=:ended,'
            . '`husEndReason`=:reason,`husState`=:state,`husLastSeen`=:ended '
            . 'WHERE `husHostID`=:host AND `husSessionKey`=:key '
            . 'AND `husStartedAt`=:started AND `husEndedAt` IS NULL',
            [],
            [
                ':ended' => $s['ended_at'],
                ':reason' => $s['end_reason'],
                ':state' => $s['state'],
                ':host' => $hostID,
                ':key' => $s['key'],
                ':started' => $s['started_at']
            ]
        );
        if (self::$DB->affectedRows() > 0) {
            return;
        }
        self::$DB->query(
            'INSERT INTO `hostUserSession` '
            . '(`husHostID`,`husSessionKey`,`husUserName`,`husDomain`,'
            . '`husUserSID`,`husType`,`husState`,`husRemoteHost`,'
            . '`husStartedAt`,`husEndedAt`,`husEndReason`,`husLastSeen`) '
            . 'VALUES (:host,:key,:user,:domain,:sid,:type,:state,:remote,'
            . ':started,:ended,:reason,:ended) '
            . 'ON DUPLICATE KEY UPDATE `husEndedAt`=VALUES(`husEndedAt`),'
            . '`husEndReason`=VALUES(`husEndReason`)',
            [],
            [
                ':host' => $hostID,
                ':key' => $s['key'],
                ':user' => $s['user'],
                ':domain' => $s['domain'],
                ':sid' => $s['sid'],
                ':type' => $s['type'],
                ':state' => $s['state'],
                ':remote' => $s['remote_host'],
                ':started' => $s['started_at'],
                ':ended' => $s['ended_at'],
                ':reason' => $s['end_reason']
            ]
        );
    }

    /**
     * Opens or refreshes the reported live sessions.
     *
     * @param int    $hostID the host
     * @param array  $open   the normalized open entries
     * @param string $now    the reconcile timestamp
     *
     * @return array the entries that were not already open
     */
    private static function _upsertOpen($hostID, array $open, $now)
    {
        $existing = self::_openKeys($hostID);
        $new = [];
        foreach ($open as $ident => $s) {
            if (!isset($existing[$ident])) {
                $new[] = $s;
            }
            self::$DB->query(
                'INSERT INTO `hostUserSession` '
                . '(`husHostID`,`husSessionKey`,`husUserName`,`husDomain`,'
                . '`husUserSID`,`husType`,`husState`,`husRemoteHost`,'
                . '`husStartedAt`,`husEndedAt`,`husEndReason`,`husLastSeen`) '
                . 'VALUES (:host,:key,:user,:domain,:sid,:type,:state,'
                . ':remote,:started,NULL,\'\',:now) '
                . 'ON DUPLICATE KEY UPDATE `husState`=VALUES(`husState`),'
                . '`husRemoteHost`=VALUES(`husRemoteHost`),'
                . '`husUserSID`=VALUES(`husUserSID`),'
                . '`husLastSeen`=VALUES(`husLastSeen`)',
                [],
                [
                    ':host' => $hostID,
                    ':key' => $s['key'],
                    ':user' => $s['user'],
                    ':domain' => $s['domain'],
                    ':sid' => $s['sid'],
                    ':type' => $s['type'],
                    ':state' => $s['state'],
                    ':remote' => $s['remote_host'],
                    ':started' => $s['started_at'],
                    ':now' => $now
                ]
            );
        }
        return $new;
    }

    /**
     * Closes rows still open on the server that the host did not report.
     *
     * Dated to `husLastSeen`, not to now: the session ended at some point
     * between the last time it was seen and this report, and the last sighting
     * is the only defensible end. `inferred` says exactly that, so a report
     * can label the duration a lower bound instead of presenting a guess as a
     * measurement.
     *
     * @param int   $hostID the host
     * @param array $open   the normalized open entries
     *
     * @return int rows closed
     */
    private static function _closeUnreported($hostID, array $open)
    {
        $sql = 'UPDATE `hostUserSession` SET `husEndedAt`=`husLastSeen`,'
            . '`husEndReason`=:reason WHERE `husHostID`=:host '
            . 'AND `husEndedAt` IS NULL';
        $params = [':reason' => self::END_INFERRED, ':host' => $hostID];
        if (!empty($open)) {
            $keep = [];
            $i = 0;
            foreach ($open as $s) {
                $k = ':k' . $i;
                $t = ':t' . $i;
                $keep[] = '(`husSessionKey`<>' . $k
                    . ' OR `husStartedAt`<>' . $t . ')';
                $params[$k] = $s['key'];
                $params[$t] = $s['started_at'];
                ++$i;
            }
            $sql .= ' AND ' . implode(' AND ', $keep);
        }
        self::$DB->query($sql, [], $params);
        return (int)self::$DB->affectedRows();
    }

    /**
     * The identities of this host's currently open sessions.
     *
     * @param int $hostID the host
     *
     * @return array identity => true
     */
    private static function _openKeys($hostID)
    {
        $rows = (array)self::$DB->query(
            'SELECT `husSessionKey`,`husStartedAt` FROM `hostUserSession` '
            . 'WHERE `husHostID`=:host AND `husEndedAt` IS NULL',
            [],
            [':host' => $hostID]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['husSessionKey'] . "\0" . $r['husStartedAt']] = true;
        }
        return $out;
    }

    /**
     * Mirrors one session edge into the legacy `userTracking` table.
     *
     * COMPATIBILITY SHIM. It exists so an estate migrating to fog-agent does
     * not lose the Activity page it already reads, and so an estate running
     * both client generations sees one merged view. Nothing new should be
     * built on `userTracking`; build on `hostUserSession`.
     *
     * The legacy columns are narrower and lossier than the session row --
     * the username is stored without its domain there, as the legacy client
     * always sent it -- which is the point: this writes what that table can
     * hold and nothing more.
     *
     * @param Host   $Host   the host
     * @param array  $s      the normalized session entry
     * @param int    $action 1 login, 0 logout
     * @param string $when   the event time
     *
     * @return void
     */
    private static function _legacyRow(Host $Host, array $s, $action, $when)
    {
        self::$DB->query(
            'INSERT INTO `userTracking` '
            . '(`utHostID`,`utUserName`,`utAction`,`utDateTime`,`utDate`,'
            . '`utIP`,`utHostName`,`utCreatedBy`) '
            . 'VALUES (:host,:user,:action,:when,:date,:ip,:name,:by)',
            [],
            [
                ':host' => (int)$Host->get('id'),
                ':user' => substr(strtolower($s['user']), 0, 50),
                ':action' => (string)$action,
                ':when' => $when,
                ':date' => substr($when, 0, 10),
                ':ip' => substr($s['remote_host'], 0, 50),
                ':name' => substr((string)$Host->get('name'), 0, 16),
                ':by' => 'fog-agent'
            ]
        );
    }
}
