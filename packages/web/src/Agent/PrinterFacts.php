<?php
/**
 * The installed-printer list an agent reports about its own host.
 *
 * PHP version 7.4+
 *
 * @category Printers
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
 * Reconciles a reported printer block into `hostSpooler` and `hostPrinter`
 * (design 0010 sections 3 and 4).
 *
 * A fact report like InventoryFacts, and registered the same way: an entry
 * in State::FACT_REPORTS and a block in the poll, never a route of its own
 * (the route rule, protocol-v1.md).
 *
 * The contrast to draw is with `printerAssoc` next door: that is what an
 * admin ASSIGNED. This is what the machine says it actually HAS. FOG has
 * had the first since 1.x and has never had the second, so an install that
 * failed has always failed silently and the client has always retried the
 * same thing on the next poll, forever.
 *
 * What it does NOT do is act on the difference. Deciding that an assigned
 * printer is missing is the report's job (design 0010 section 6), and
 * installing one is the agent's. This class only records.
 *
 * @category Printers
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PrinterFacts extends FOGBase
{
    /**
     * Most queues accepted from one host.
     *
     * A print server can legitimately carry hundreds, so this is generous
     * rather than tight. It is here because the list is attacker-controlled
     * input from a host that has enrolled: a machine claiming a million
     * queues must fail this check rather than the database.
     */
    const MAX_PRINTERS = 512;

    /**
     * Column widths, so an overlong value is truncated here rather than
     * failing the insert under strict mode and costing the host its poll.
     *
     * The URI is the long one. A CUPS device URI can carry a full IPP path
     * plus query parameters, and an smb:// one carries a UNC path an admin
     * chose the length of.
     */
    const WIDTHS = [
        'name' => 255,
        'uri' => 1024,
        'driver' => 255
    ];

    /**
     * The subsystems a host may report.
     *
     * An unrecognized value is stored as the empty string rather than passed
     * through, for DirectoryFacts::KINDS' reason: the report groups on it,
     * and a host inventing a value would put an uncontrolled string into a
     * page an admin reads.
     *
     * @var string[]
     */
    const SUBSYSTEMS = ['cups', 'winspool'];

    /**
     * Records the host's current printer set.
     *
     * The list is complete by contract: any queue currently recorded for
     * this host and absent from it is gone. That is why the agent sends no
     * block at all when its collector could not run -- an empty list here
     * means "this machine has no printers", and would clear every row it
     * has (design 0006 section 6).
     *
     * @param Host  $Host  the host the certificate bound
     * @param array $block the reported printer block
     *
     * @throws \RuntimeException with an HTTP code when refused
     *
     * @return void
     */
    public static function report(Host $Host, array $block)
    {
        $list = $block['installed'] ?? [];
        if (!is_array($list)) {
            $list = [];
        }
        if (count($list) > self::MAX_PRINTERS) {
            throw new \RuntimeException('printer list too large', 413);
        }

        $hostID = (int)$Host->get('id');
        $incoming = self::_clean($list, (string)($block['default'] ?? ''));
        $now = self::niceDate()->setTimezone(self::storageTimeZone())
            ->format('Y-m-d H:i:s');

        // Replace the set, in one transaction so nothing observes the
        // intermediate empty state. Unlike hostSoftware, rows are deleted
        // rather than closed: a printer that is gone is gone, and "which
        // hosts had this queue in March" is not a question anyone asks.
        // The removal itself is in the audit line below.
        self::$DB->query('START TRANSACTION');
        try {
            $before = self::_currentNames($hostID);
            self::$DB->query(
                'DELETE FROM `hostPrinter` WHERE `hpHostID`=:host',
                [],
                [':host' => $hostID]
            );
            self::_insert($hostID, $incoming, $now);
            self::_spooler($hostID, (string)($block['subsystem'] ?? ''), $now);
            self::$DB->query('COMMIT');
        } catch (\Exception $e) {
            self::$DB->query('ROLLBACK');
            throw $e;
        }

        $added = array_diff(array_keys($incoming), $before);
        $removed = array_diff($before, array_keys($incoming));
        if (empty($added) && empty($removed)) {
            // A queue whose driver or default flag moved is a change worth
            // storing and not worth a line in the audit log; the set is what
            // an admin reads a history for.
            return;
        }
        Audit::record(
            [
                'type' => 'agent.printers',
                'subjectType' => 'host',
                'subjectID' => $hostID,
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'affectedCount' => count($added) + count($removed),
                // Named, not counted. A host has single digits of printers,
                // so the names fit -- and "Accounts-HP4550 gone" is the line
                // an admin is looking for, where "1 removed" sends them
                // hunting for which one.
                'text' => substr(
                    'agent reported printers: '
                    . self::describe($added, $removed),
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }

    /**
     * Normalizes the reported list, keyed by queue name.
     *
     * Keying deduplicates: a host reporting the same queue twice would
     * otherwise hit the unique index mid-insert and roll back the whole
     * poll. A queue with no name is dropped rather than stored as a row
     * nothing can act on -- not the report, not a removal, not the admin.
     *
     * @param array  $list        the reported queues
     * @param string $defaultName the block's default queue name
     *
     * @return array name => normalized row
     */
    private static function _clean(array $list, $defaultName)
    {
        $defaultName = trim($defaultName);
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
            // The agent reports the default by NAME at the block level,
            // not as a flag on each queue. Resolving it here is what keeps
            // the stored flag and the reported name from ever disagreeing,
            // and it drops a default naming a queue that is not in the list
            // for free -- which happens after a removal that did not clear
            // the setting, and which no action could resolve.
            $row['isDefault'] = ($row['name'] === $defaultName) ? 1 : 0;
            $row['shared'] = !empty($entry['shared']) ? 1 : 0;
            $out[$row['name']] = $row;
        }

        return $out;
    }

    /**
     * The queue names currently recorded for a host, for the audit line.
     *
     * @param int $hostID the host
     *
     * @return string[]
     */
    private static function _currentNames($hostID)
    {
        $rows = self::$DB->query(
            'SELECT `hpName` FROM `hostPrinter` WHERE `hpHostID`=:host',
            [],
            [':host' => (int)$hostID]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $out = [];
        foreach ((array)$rows as $row) {
            $out[] = (string)($row['hpName'] ?? '');
        }

        return $out;
    }

    /**
     * Inserts the reported queues.
     *
     * One statement rather than a row at a time: a print server with two
     * hundred queues would otherwise cost two hundred round trips on every
     * poll where anything moved. No chunking, unlike SoftwareFacts --
     * MAX_PRINTERS caps the list at 512, which is well inside what a single
     * statement and its placeholder count accept.
     *
     * @param int    $hostID   the host
     * @param array  $incoming name => normalized row
     * @param string $now      the timestamp for this reconcile
     *
     * @return void
     */
    private static function _insert($hostID, array $incoming, $now)
    {
        if (empty($incoming)) {
            return;
        }
        $values = [];
        $binds = [];
        $i = 0;
        foreach ($incoming as $row) {
            // A distinct placeholder name per value rather than reusing one
            // for the host id and the timestamp: a real prepared statement
            // binds each name once, and a driver that is not emulating them
            // rejects the repeat with a bound-parameter count error.
            $p = ':r' . $i++ . '_';
            $values[] = '(' . $p . 'h,' . $p . 'n,' . $p . 'u,' . $p . 'v,'
                . $p . 'd,' . $p . 's,' . $p . 'o)';
            $binds[$p . 'h'] = (int)$hostID;
            $binds[$p . 'n'] = $row['name'];
            $binds[$p . 'u'] = $row['uri'];
            $binds[$p . 'v'] = $row['driver'];
            $binds[$p . 'd'] = $row['isDefault'];
            $binds[$p . 's'] = $row['shared'];
            $binds[$p . 'o'] = $now;
        }
        self::$DB->query(
            'INSERT INTO `hostPrinter` '
            . '(`hpHostID`,`hpName`,`hpURI`,`hpDriver`,`hpDefault`,'
            . '`hpShared`,`hpObservedAt`) VALUES '
            . implode(',', $values),
            [],
            $binds
        );
    }

    /**
     * Upserts the host's spooler row.
     *
     * Written even when the host reported no queues, and that is the point:
     * a machine with CUPS and nothing installed has ANSWERED, and without
     * this row the report could not tell it from a machine that has never
     * checked in (design 0010 section 6).
     *
     * @param int    $hostID    the host
     * @param string $subsystem the reported subsystem
     * @param string $now       the timestamp for this reconcile
     *
     * @return void
     */
    private static function _spooler($hostID, $subsystem, $now)
    {
        $subsystem = strtolower(trim($subsystem));
        if (!in_array($subsystem, self::SUBSYSTEMS, true)) {
            // A host that invents a subsystem gets none, not its own string.
            $subsystem = '';
        }
        self::$DB->query(
            'INSERT INTO `hostSpooler` '
            . '(`hspHostID`,`hspSubsystem`,`hspObservedAt`) '
            . 'VALUES (:host,:sub,:now) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`hspSubsystem`=VALUES(`hspSubsystem`),'
            . '`hspObservedAt`=VALUES(`hspObservedAt`)',
            [],
            [':host' => (int)$hostID, ':sub' => $subsystem, ':now' => $now]
        );
    }

    /**
     * One line naming what changed, for the audit entry.
     *
     * @param string[] $added   queues that appeared
     * @param string[] $removed queues that went away
     *
     * @return string
     */
    private static function describe(array $added, array $removed)
    {
        $parts = [];
        if (!empty($added)) {
            $parts[] = 'added ' . implode(', ', $added);
        }
        if (!empty($removed)) {
            $parts[] = 'removed ' . implode(', ', $removed);
        }

        return implode('; ', $parts);
    }
}
