<?php
/**
 * fog-agent seats: one agent binding per OS on a host.
 *
 * PHP version 7.4+
 *
 * @category Seat
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Managers\HostManager;

/**
 * fog-agent seats: one agent binding per OS on a host.
 *
 * A dual-boot machine resolves to ONE host from both OSes. The host holds one
 * seat per OS, so the second OS no longer replaces the first OS's key
 * (fog-agent#53). The OS is the `os` the agent sent at enroll
 * (agentEnrollment.aeOS: windows, linux, darwin).
 *
 * The hostAgents table is the source of truth for the binding and for the
 * per-seat state (version, update state, check-in, certificate expiry). The
 * hosts.hostAgent* columns of the same names stay as a MIRROR of the seat that
 * wrote most recently, so the host list, export, dashboard and reports read
 * them as before. This class is the only deliberate writer of the mirror;
 * Host::save() rewrites it with whatever the loaded host holds. The mirror
 * is for display and never authenticates.
 *
 * Direct SQL through self::$DB, not getIds(): the agent request has no user,
 * so getIds() would put a site scope in the WHERE that resolves to 1=0 and
 * match nothing.
 *
 * @category Seat
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Seat extends FOGBase
{
    /**
     * The seat that authenticated this request: null, or
     * ['hostID' => int, 'os' => string, 'fingerprint' => string].
     * Set by the auth gate through load().
     *
     * @var array|null
     */
    public static $current = null;

    /**
     * Seat column list, aliased to the keys every seat array uses.
     */
    const COLUMNS = '`ha`.`haHostID` AS `hostID`, `ha`.`haOS` AS `os`, '
        . '`ha`.`haFingerprint` AS `fingerprint`, '
        . '`ha`.`haNotAfter` AS `notAfter`, `ha`.`haVersion` AS `version`, '
        . '`ha`.`haUpdateState` AS `updateState`, `ha`.`haCheckin` AS `checkin`';

    /**
     * Host field => seat column, for the values record() writes.
     *
     * @var array
     */
    const FIELDS = [
        'agentNotAfter' => 'haNotAfter',
        'agentVersion' => 'haVersion',
        'agentUpdateState' => 'haUpdateState',
        'agentCheckin' => 'haCheckin'
    ];

    /**
     * Runs one statement and returns the PDODB result.
     *
     * @param string $sql    the statement, with named placeholders
     * @param array  $params the bound values
     *
     * @throws \RuntimeException when the database refuses
     *
     * @return mixed
     */
    private static function _run($sql, array $params = [])
    {
        $res = self::$DB->query($sql, [], $params);
        if (false !== $res->error) {
            throw new \RuntimeException('agent seat query failed', 500);
        }
        return $res;
    }

    /**
     * Rows of a SELECT.
     *
     * @param string $sql    the statement
     * @param array  $params the bound values
     *
     * @throws \RuntimeException when the database refuses
     *
     * @return array
     */
    private static function _rows($sql, array $params = [])
    {
        $rows = self::_run($sql, $params)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();
        return is_array($rows) ? $rows : [];
    }

    /**
     * The seats that carry a fingerprint, on live (non-pending) hosts.
     *
     * LIMIT 2: the fingerprint is unique, so two rows would mean the table
     * was edited by hand, and an ambiguous principal is no principal.
     *
     * @param string $fingerprint the certificate's key fingerprint
     *
     * @throws \RuntimeException when the database refuses
     *
     * @return array
     */
    public static function principal($fingerprint)
    {
        return self::_rows(
            'SELECT ' . self::COLUMNS . ' FROM `hostAgents` `ha`'
            . ' INNER JOIN `hosts` `h` ON `h`.`hostID` = `ha`.`haHostID`'
            . ' WHERE `ha`.`haFingerprint` = :fp AND `h`.`hostPending` = 0'
            . ' LIMIT 2',
            ['fp' => (string)$fingerprint]
        );
    }

    /**
     * Makes the seat the one this request runs as, and puts its values in the
     * in-memory host, so per-request code that reads $Host->get('agent*')
     * sees the requesting seat and not whichever seat wrote the mirror last.
     *
     * @param Host  $Host the principal
     * @param array $seat a row from principal() or forHost()
     *
     * @return void
     */
    public static function load(Host $Host, array $seat)
    {
        self::$current = [
            'hostID' => (int)$seat['hostID'],
            'os' => (string)$seat['os'],
            'fingerprint' => (string)$seat['fingerprint']
        ];
        $Host->set('agentFingerprint', (string)$seat['fingerprint'])
            ->set('agentNotAfter', $seat['notAfter'])
            ->set('agentVersion', (string)$seat['version'])
            ->set('agentUpdateState', (string)$seat['updateState'])
            ->set('agentCheckin', $seat['checkin']);
    }

    /**
     * Writes seat values for the request's own seat, and mirrors them to the
     * host. Through HostManager::update(), never Host::save(): a save
     * rewrites the MAC association and this rides every poll.
     *
     * @param Host  $Host   the principal
     * @param array $fields Host field => value: agentNotAfter, agentVersion,
     *                      agentUpdateState, agentCheckin
     *
     * @throws \RuntimeException when no seat authenticated this host
     *
     * @return void
     */
    public static function record(Host $Host, array $fields)
    {
        $hostID = (int)$Host->get('id');
        if (null === self::$current || self::$current['hostID'] !== $hostID) {
            throw new \RuntimeException('no agent seat is loaded for this host', 500);
        }
        $sets = [];
        $params = ['h' => $hostID, 'os' => self::$current['os']];
        $n = 0;
        foreach ($fields as $field => $value) {
            if (!isset(self::FIELDS[$field])) {
                throw new \RuntimeException('not a seat field: ' . $field, 500);
            }
            $sets[] = '`' . self::FIELDS[$field] . '` = :v' . $n;
            $params['v' . $n++] = $value;
        }
        if (empty($sets)) {
            return;
        }
        self::_run(
            'UPDATE `hostAgents` SET ' . implode(', ', $sets)
            . ' WHERE `haHostID` = :h AND `haOS` = :os',
            $params
        );
        self::_mirror(
            $hostID,
            $fields + ['agentFingerprint' => self::$current['fingerprint']]
        );
        foreach ($fields as $field => $value) {
            $Host->set($field, $value);
        }
    }

    /**
     * Binds a key to the host's seat for one OS. A new OS adds a seat. A new
     * key for an OS that has a seat replaces that seat's key and resets its
     * state. A blank-OS seat (left by migration) is replaced by the first
     * request that names an OS. The host's other seats are not touched.
     * Then the host's mirror takes the seat's values.
     *
     * @param int         $hostID      the host
     * @param string      $os          the agent's OS
     * @param string      $fingerprint the key's fingerprint
     * @param string|null $notAfter    the certificate's expiry
     * @param string      $version     the agent's version
     * @param string      $now         the time of the bind
     *
     * @throws \RuntimeException 409 when the key is already another seat's
     *
     * @return void
     */
    public static function bind(
        $hostID,
        $os,
        $fingerprint,
        $notAfter,
        $version,
        $now
    ) {
        $hostID = (int)$hostID;
        $os = (string)$os;
        $fingerprint = (string)$fingerprint;
        // A seat that migration left with a blank OS (no enrollment row
        // named its key) is taken over by the first request that names an
        // OS: its OS and key are replaced, so the old blank-OS key stops
        // authenticating.
        $from = $os;
        if ('' !== $os && !self::exists($hostID, $os) && self::exists($hostID, '')) {
            $from = '';
        }
        // The fingerprint is unique across seats. Refused up front, so an
        // upsert on the (host, OS) key cannot quietly leave the key bound
        // somewhere else.
        self::refuseBoundElsewhere($fingerprint, $hostID, $from);
        $values = [
            'h' => $hostID,
            'os' => $os,
            'fp' => $fingerprint,
            'na' => $notAfter,
            'v' => (string)$version,
            'now' => (string)$now
        ];
        if ($from !== $os || self::exists($hostID, $os)) {
            $values['from'] = $from;
            self::_run(
                'UPDATE `hostAgents` SET `haOS` = :os, `haFingerprint` = :fp,'
                . ' `haNotAfter` = :na, `haVersion` = :v,'
                . " `haUpdateState` = '', `haCheckin` = :now"
                . ' WHERE `haHostID` = :h AND `haOS` = :from',
                $values
            );
        } else {
            self::_run(
                'INSERT INTO `hostAgents`'
                . ' (`haHostID`,`haOS`,`haFingerprint`,`haNotAfter`,'
                . '`haVersion`,`haUpdateState`,`haCheckin`)'
                . " VALUES (:h, :os, :fp, :na, :v, '', :now)",
                $values
            );
        }
        self::_mirror(
            $hostID,
            [
                'agentFingerprint' => $fingerprint,
                'agentNotAfter' => $notAfter,
                'agentVersion' => (string)$version,
                'agentUpdateState' => '',
                'agentCheckin' => (string)$now
            ]
        );
    }

    /**
     * Refuses a key that is already a seat of another host. With $os, also
     * refuses a key that is a seat of this host for a different OS.
     *
     * @param string      $fingerprint the key's fingerprint
     * @param int         $hostID      the host that wants the key
     * @param string|null $os          the one seat of that host that may hold it
     *
     * @throws \RuntimeException 409 enrollment_key_bound_elsewhere
     *
     * @return void
     */
    public static function refuseBoundElsewhere($fingerprint, $hostID, $os = null)
    {
        $rows = self::_rows(
            'SELECT `haHostID` AS `hostID`, `haOS` AS `os` FROM `hostAgents`'
            . ' WHERE `haFingerprint` = :fp',
            ['fp' => (string)$fingerprint]
        );
        foreach ($rows as $row) {
            if ((int)$row['hostID'] !== (int)$hostID
                || (null !== $os && (string)$row['os'] !== (string)$os)
            ) {
                throw new \RuntimeException('enrollment_key_bound_elsewhere', 409);
            }
        }
    }

    /**
     * The OS of the seat that authenticated this request, or ''.
     *
     * @return string
     */
    public static function currentOS()
    {
        return null === self::$current ? '' : self::$current['os'];
    }

    /**
     * The key fingerprint of the seat that authenticated this request, or ''.
     *
     * @return string
     */
    public static function currentFingerprint()
    {
        return null === self::$current ? '' : self::$current['fingerprint'];
    }

    /**
     * The seat of this request when it belongs to this host, else null.
     *
     * @param Host $Host the host
     *
     * @return array|null
     */
    public static function currentFor(Host $Host)
    {
        if (null !== self::$current && (int)self::$current['hostID'] === (int)$Host->get('id')) {
            return self::$current;
        }
        return null;
    }

    /**
     * The certificate expiry of the host's seat for one OS, or ''.
     *
     * @param int    $hostID the host
     * @param string $os     the OS
     *
     * @return string
     */
    public static function notAfter($hostID, $os)
    {
        $rows = self::_rows(
            'SELECT `haNotAfter` AS `na` FROM `hostAgents`'
            . ' WHERE `haHostID` = :h AND `haOS` = :os LIMIT 1',
            ['h' => (int)$hostID, 'os' => (string)$os]
        );
        return count($rows) ? (string)$rows[0]['na'] : '';
    }

    /**
     * Writes the host's mirror columns.
     *
     * @param int   $hostID the host
     * @param array $fields Host field => value
     *
     * @return void
     */
    private static function _mirror($hostID, array $fields)
    {
        (new HostManager())->update(
            ['id' => (int)$hostID],
            '',
            $fields
        );
    }

    /**
     * Whether the host has a seat, or a seat for one OS.
     *
     * @param int         $hostID the host
     * @param string|null $os     the OS, or null for any seat
     *
     * @return bool
     */
    public static function exists($hostID, $os = null)
    {
        $sql = 'SELECT `haID` FROM `hostAgents` WHERE `haHostID` = :h';
        $params = ['h' => (int)$hostID];
        if (null !== $os) {
            $sql .= ' AND `haOS` = :os';
            $params['os'] = (string)$os;
        }
        return count(self::_rows($sql . ' LIMIT 1', $params)) > 0;
    }

    /**
     * The fingerprint of the host's seat for one OS, or ''.
     *
     * @param int    $hostID the host
     * @param string $os     the OS
     *
     * @return string
     */
    public static function fingerprint($hostID, $os)
    {
        $rows = self::_rows(
            'SELECT `haFingerprint` AS `fp` FROM `hostAgents`'
            . ' WHERE `haHostID` = :h AND `haOS` = :os LIMIT 1',
            ['h' => (int)$hostID, 'os' => (string)$os]
        );
        return count($rows) ? (string)$rows[0]['fp'] : '';
    }

    /**
     * The ids of the hosts that carry a fingerprint.
     *
     * @param string $fingerprint the key's fingerprint
     *
     * @return int[]
     */
    public static function boundHosts($fingerprint)
    {
        $ids = [];
        foreach (
            self::_rows(
                'SELECT DISTINCT `haHostID` AS `id` FROM `hostAgents`'
                . ' WHERE `haFingerprint` = :fp',
                ['fp' => (string)$fingerprint]
            ) as $row
        ) {
            $ids[] = (int)$row['id'];
        }
        return $ids;
    }

    /**
     * Every seat of a host, for the host page.
     *
     * @param int $hostID the host
     *
     * @return array
     */
    public static function forHost($hostID)
    {
        return self::_rows(
            'SELECT ' . self::COLUMNS . ' FROM `hostAgents` `ha`'
            . ' WHERE `ha`.`haHostID` = :h ORDER BY `ha`.`haOS`',
            ['h' => (int)$hostID]
        );
    }

    /**
     * The one seat of a host that owns the directory (join, rename, rejoin):
     * the Windows seat if the host has one, else the host's only seat, else
     * none. Two or more non-Windows seats give none: no seat owns the
     * directory.
     *
     * @param int $hostID the host
     *
     * @return array|null the seat row (see COLUMNS), or null
     */
    public static function directorySeat($hostID)
    {
        return self::directorySeatOf(self::forHost($hostID));
    }

    /**
     * The directory seat among seats already loaded with forHost(): the
     * Windows seat, else the only seat, else null.
     *
     * @param array $seats one host's seats, as forHost() returns them
     *
     * @return array|null
     */
    public static function directorySeatOf(array $seats)
    {
        foreach ($seats as $seat) {
            if ('windows' === $seat['os']) {
                return $seat;
            }
        }
        return 1 === count($seats) ? $seats[0] : null;
    }

    /**
     * The key fingerprint of the host's directory seat, or ''.
     *
     * @param int $hostID the host
     *
     * @return string
     */
    public static function directoryFingerprint($hostID)
    {
        $seat = self::directorySeat($hostID);
        return null === $seat ? '' : (string)$seat['fingerprint'];
    }

    /**
     * The same rule as directorySeat(), as an SQL expression for code that
     * reads the host row inside its own transaction. It yields the
     * directory seat's fingerprint, or '' when no seat owns the directory.
     *
     * @param string $hostIDColumn the SQL column that holds the host id
     *
     * @return string
     */
    public static function directoryFingerprintSql($hostIDColumn)
    {
        return "COALESCE((SELECT `haFingerprint` FROM `hostAgents` WHERE `haHostID` = $hostIDColumn"
            . " AND `haOS` = 'windows' LIMIT 1),"
            . " (SELECT MAX(`haFingerprint`) FROM `hostAgents` WHERE `haHostID` = $hostIDColumn"
            . ' HAVING COUNT(*) = 1), \'\')';
    }

    /**
     * Whether the agent this call concerns is the host's directory seat. On
     * an agent request for this host, it is true only when the requesting
     * seat is the directory seat. Elsewhere (admin or UI), it is true when
     * the host has a directory seat.
     *
     * @param Host $Host the host
     *
     * @return bool
     */
    public static function isDirectorySeat(Host $Host)
    {
        $hostID = (int)$Host->get('id');
        $seat = self::directorySeat($hostID);
        if (null === $seat) {
            return false;
        }
        $current = self::currentFor($Host);
        if (null !== $current) {
            return $current['fingerprint'] === (string)$seat['fingerprint'];
        }
        return true;
    }

    /**
     * Whether the agent this call concerns runs on Windows. On an agent
     * request for this host, it is the requesting seat. Elsewhere (admin or
     * UI), any seat of the host that runs on Windows.
     *
     * @param Host $Host the host
     *
     * @return bool
     */
    public static function isWindows(Host $Host)
    {
        $current = self::currentFor($Host);
        if (null !== $current) {
            return 'windows' === $current['os'];
        }
        return self::exists((int)$Host->get('id'), 'windows');
    }
}
