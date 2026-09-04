<?php
/**
 * The installed-program list an agent reports about its own host.
 *
 * PHP version 7.4+
 *
 * @category Software
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
 * Reconciles a reported program list into `hostSoftware` (design 0006
 * section 4).
 *
 * The contrast to draw is with SoftwareSet, next door: that one is desired
 * state, what an admin wants installed. This one is a fact, what the host
 * says is there. FOG 1.6 had nowhere to keep the second, which is why the
 * table is new rather than a reuse of `software`.
 *
 * Rows are closed, never deleted. A program that stops being reported gets
 * an `hsRemovedAt`, so "which hosts had log4j in March" is answerable after
 * the estate has been cleaned up -- the reportability the table exists for.
 * The current truth is the `hsRemovedAt IS NULL` slice.
 *
 * @category Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SoftwareFacts extends FOGBase
{
    /**
     * Most programs accepted from one host.
     *
     * A package-managed Linux host reports around 2800 (measured), so this
     * is generous rather than tight. It is here because the list is the
     * one unbounded thing in the protocol and the reconcile builds a
     * statement per chunk: a host claiming a million programs must fail
     * this check rather than the database.
     */
    const MAX_PROGRAMS = 20000;

    /**
     * Rows per INSERT. The reconcile is one transaction either way; the
     * chunking keeps a single statement, and its placeholder count, within
     * what MySQL's max_allowed_packet and prepared-statement limits accept.
     */
    const CHUNK = 250;

    /**
     * Column widths, so an overlong value is truncated here rather than
     * failing the insert under strict mode and costing the host its poll.
     */
    const WIDTHS = [
        'name' => 255,
        'version' => 128,
        'publisher' => 255,
        'source' => 16,
        'arch' => 16
    ];

    /**
     * Records the host's current program list.
     *
     * The list is complete by contract: anything currently installed for
     * this host and absent from it is marked removed. That is why the
     * agent sends no block at all when its collector could not run -- an
     * empty list here means "this host has no software", and would close
     * out every row it has.
     *
     * @param Host  $Host the host the certificate bound
     * @param array $list the reported programs
     *
     * @throws \RuntimeException with an HTTP code when refused
     *
     * @return void
     */
    public static function report(Host $Host, array $list)
    {
        if (count($list) > self::MAX_PROGRAMS) {
            throw new \RuntimeException('software list too large', 413);
        }
        $hostID = (int)$Host->get('id');
        $incoming = self::_clean($list);
        $now = self::niceDate()->format('Y-m-d H:i:s');

        // Close every open row, then let the insert reopen the ones still
        // reported. Doing it in that order is what keeps the statement
        // size constant: the alternative, an UPDATE excluding the reported
        // identities, needs a NOT IN carrying all 2800 of them. Nothing
        // observes the intermediate "everything removed" state because
        // both halves are one transaction.
        self::$DB->query('START TRANSACTION');
        try {
            $before = self::_currentKeys($hostID);
            self::_closeAll($hostID, $now);
            self::_upsert($hostID, $incoming, $now);
            self::$DB->query('COMMIT');
        } catch (\Exception $e) {
            self::$DB->query('ROLLBACK');
            throw $e;
        }

        $added = count(array_diff_key($incoming, $before));
        $removed = count(array_diff_key($before, $incoming));
        if (0 === $added && 0 === $removed) {
            // A refreshed hsLastSeen is not news. Auditing every poll that
            // reported the same list would bury the changes that matter.
            return;
        }
        Audit::record(
            [
                'type' => 'agent.software',
                'subjectType' => 'host',
                'subjectID' => $hostID,
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'affectedCount' => $added + $removed,
                'text' => sprintf(
                    'agent reported %d installed programs: %d added, %d removed',
                    count($incoming),
                    $added,
                    $removed
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }

    /**
     * Normalizes the reported list, keyed by the row's identity.
     *
     * Keying here deduplicates: a host that reports the same program twice
     * would otherwise hit the unique index mid-insert and roll back the
     * whole poll. A program with no name is dropped rather than stored as
     * an empty row nobody can read.
     *
     * @param array $list the reported programs
     *
     * @return array identity => normalized row
     */
    private static function _clean(array $list)
    {
        $out = [];
        foreach ($list as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $row = [];
            foreach (self::WIDTHS as $field => $width) {
                $row[$field] = substr(
                    trim((string)($entry[$field] ?? '')),
                    0,
                    $width
                );
            }
            if ('' === $row['name']) {
                continue;
            }
            $row['install_date'] = self::_date($entry['install_date'] ?? '');
            $out[self::_key($row['name'], $row['source'], $row['version'])] = $row;
        }

        return $out;
    }

    /**
     * The identity of one row, matching the table's unique index.
     *
     * The version is part of it on purpose: an OS package list enumerates
     * each installed version separately, two can coexist, and an upgrade
     * then reads as one version closed and another opened -- which is the
     * history a report wants (design 0006 section 4.1).
     *
     * @param string $name    the program name
     * @param string $source  the package manager it came from
     * @param string $version the version string
     *
     * @return string
     */
    private static function _key($name, $source, $version)
    {
        return $name . "\0" . $source . "\0" . $version;
    }

    /**
     * A reported install date as a storable DATE, or null.
     *
     * @param mixed $value the reported value
     *
     * @return string|null
     */
    private static function _date($value)
    {
        $value = trim((string)$value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * The identities currently installed for a host, for the audit count.
     *
     * @param int $hostID the host
     *
     * @return array identity => true
     */
    private static function _currentKeys($hostID)
    {
        $rows = self::$DB->query(
            'SELECT `hsName`,`hsSource`,`hsVersion` FROM `hostSoftware`'
            . ' WHERE `hsHostID`=:host AND `hsRemovedAt` IS NULL',
            [],
            [':host' => (int)$hostID]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $out = [];
        foreach ((array)$rows as $row) {
            $out[self::_key(
                $row['hsName'] ?? '',
                $row['hsSource'] ?? '',
                $row['hsVersion'] ?? ''
            )] = true;
        }

        return $out;
    }

    /**
     * Marks every currently-installed row for a host as removed.
     *
     * Half of the reconcile: the insert that follows reopens whatever is
     * still reported. A row already closed keeps the date it was closed on,
     * because the WHERE only touches open ones.
     *
     * @param int    $hostID the host
     * @param string $now    the timestamp for this reconcile
     *
     * @return void
     */
    private static function _closeAll($hostID, $now)
    {
        self::$DB->query(
            'UPDATE `hostSoftware` SET `hsRemovedAt`=:now'
            . ' WHERE `hsHostID`=:host AND `hsRemovedAt` IS NULL',
            [],
            [':now' => $now, ':host' => (int)$hostID]
        );
    }

    /**
     * Inserts the reported rows, refreshing the ones already known.
     *
     * ON DUPLICATE KEY is what makes this one statement per chunk instead
     * of a select and a branch per program: at 2800 programs a round trip
     * each would make the poll cost seconds. hsFirstSeen is left alone on
     * update -- it is when the version was first seen, not last -- and
     * hsRemovedAt is cleared, which is how a reinstalled program reopens
     * its own row rather than starting a second one.
     *
     * @param int    $hostID   the host
     * @param array  $incoming identity => normalized row
     * @param string $now      the timestamp for this reconcile
     *
     * @return void
     */
    private static function _upsert($hostID, array $incoming, $now)
    {
        if (empty($incoming)) {
            return;
        }
        foreach (array_chunk($incoming, self::CHUNK) as $chunk) {
            $values = [];
            $binds = [];
            foreach ($chunk as $i => $row) {
                // A distinct placeholder name per value rather than reusing
                // one for the host id and the timestamp: a real prepared
                // statement binds each name once, and a driver that is not
                // emulating them rejects the repeat with a bound-parameter
                // count error (the same trap ActivityWindow documents).
                $p = ':r' . $i . '_';
                $values[] = '(' . $p . 'h,' . $p . 'n,' . $p . 'v,' . $p . 'p,'
                    . $p . 's,' . $p . 'a,' . $p . 'd,' . $p . 'f,' . $p . 'l)';
                $binds[$p . 'h'] = (int)$hostID;
                $binds[$p . 'n'] = $row['name'];
                $binds[$p . 'v'] = $row['version'];
                $binds[$p . 'p'] = $row['publisher'];
                $binds[$p . 's'] = $row['source'];
                $binds[$p . 'a'] = $row['arch'];
                $binds[$p . 'd'] = $row['install_date'];
                $binds[$p . 'f'] = $now;
                $binds[$p . 'l'] = $now;
            }
            self::$DB->query(
                'INSERT INTO `hostSoftware` '
                . '(`hsHostID`,`hsName`,`hsVersion`,`hsPublisher`,`hsSource`,'
                . '`hsArch`,`hsInstallDate`,`hsFirstSeen`,`hsLastSeen`) VALUES '
                . implode(',', $values)
                . ' ON DUPLICATE KEY UPDATE '
                . '`hsPublisher`=VALUES(`hsPublisher`),'
                . '`hsArch`=VALUES(`hsArch`),'
                . '`hsInstallDate`=VALUES(`hsInstallDate`),'
                . '`hsLastSeen`=VALUES(`hsLastSeen`),'
                // Reopens a row that _closeAll just closed, and a program
                // reinstalled after months away. hsFirstSeen is deliberately
                // absent: it is when this version was first seen, not last.
                . '`hsRemovedAt`=NULL',
                [],
                $binds
            );
        }
    }
}
