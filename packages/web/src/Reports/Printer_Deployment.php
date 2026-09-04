<?php
/**
 * Which assigned printers actually arrived, and which did not.
 *
 * PHP version 7.4+
 *
 * @category Printer_Deployment
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Assign\Resolver;
use FOG\Pages\ReportManagement;
use FOG\Router\Route;

/**
 * Which assigned printers actually arrived, and which did not.
 *
 * The report FOG has never been able to produce. `printerAssoc` is intent --
 * what an admin assigned -- and until design 0010 nothing recorded the other
 * half, so "did the printer I assigned install?" had no answer at any price.
 * An install that failed failed silently, and the client retried the same
 * thing on the next poll, forever.
 *
 * It matters most on Linux. UnixPrinterManager::Remove() runs `lpstat`,
 * which is CUPS' status QUERY tool and has never been able to remove a
 * printer -- so mode 2, whose entire content is removal, has reported
 * success every poll while removing nothing. Every host in that mode is in
 * this report and has never been visible before.
 *
 * A FLEET SNAPSHOT, not a history -- the same test that puts User_Sessions
 * and Directory_Membership under Lists rather than in
 * ReportManagement::AGGREGATIONS.
 *
 * GATED ON `host`, like those two and for the same reason: reports share the
 * `report` node by default (the defect ADR 0023 opens with), and this is
 * host data.
 *
 * @category Printer_Deployment
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Printer_Deployment extends ReportManagement
{
    /**
     * The most rows a grid or export will carry back.
     *
     * @var int
     */
    const MAX_ROWS = 5000;

    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = self::reportTitle();

        $this->headerData = [
            _('Host'),
            _('Mode'),
            _('Spooler'),
            _('Assigned'),
            _('Installed'),
            _('Missing'),
            _('Default'),
            _('State'),
            _('Last error'),
            _('Reported')
        ];
        $this->attributes = [
            [], [], [], [], [], [], [], [], [], []
        ];

        $payload = $this->reportRows();

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'Hosts with printer management switched on, with what '
                    . 'each one last reported about itself. A printer in '
                    . 'Missing was assigned and is not on the machine.'
                )
            )
        );

        echo self::renderReportCap(
            $payload['truncated'],
            self::MAX_ROWS
        );

        $this->render(12, 'printerdeploymentreport-table');

        echo '</div>';
        echo '</div>';
    }

    /**
     * The rows this report serves.
     *
     * Split from the emit so the grid and the CSV export run the same query
     * -- see ReportManagement::exportAll().
     *
     * @return array
     */
    protected function reportRows()
    {
        // Every host with printer management ON, left-joined to what it
        // reported. A LEFT JOIN rather than an inner one on purpose: a host
        // that has never reported is the most interesting row here, and an
        // inner join would hide exactly the machines nobody has heard from.
        //
        // hostSpooler rather than hostPrinter is what the join hangs off,
        // and that is the whole reason the table exists: a machine with a
        // working spooler and no queues has ANSWERED, and joining to
        // hostPrinter would file it with the machines that never did.
        $sql = "SELECT `hostID`,
                       `hostName`,
                       `hostPrinterLevel`,
                       `hostAgentCheckin`,
                       `hspSubsystem`,
                       `hspObservedAt`
                  FROM `hosts`
                  LEFT OUTER JOIN `hostSpooler` ON `hspHostID` = `hostID`
                 WHERE `hostPrinterLevel` <> ''
                   AND `hostPrinterLevel` <> '0'
                 ORDER BY `hostName` ASC
                 LIMIT " . (self::MAX_ROWS + 1);

        $rows = (array)self::$DB->query($sql)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        $rows = array_slice($rows, 0, self::MAX_ROWS + 1);
        $truncated = count($rows) > self::MAX_ROWS;
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        $hostIDs = [];
        foreach ($rows as $row) {
            $hostIDs[] = (int)$row['hostID'];
        }

        $assigned = self::_assigned($hostIDs);
        $installed = self::_installed($hostIDs);
        $errors = self::_errors($hostIDs);

        $data = [];
        foreach ($rows as $row) {
            $hostID = (int)$row['hostID'];
            $want = $assigned[$hostID] ?? [];
            $have = $installed[$hostID] ?? [];
            $reported = null !== ($row['hspObservedAt'] ?? null);

            $missing = array_diff(array_keys($want), array_keys($have));
            $extra = array_diff(array_keys($have), array_keys($want));
            $error = $errors[$hostID] ?? '';

            $default = '';
            foreach ($have as $name => $queue) {
                if (!empty($queue['default'])) {
                    $default = $name;
                }
            }

            $data[] = [
                'hostName' => (string)($row['hostName'] ?? ''),
                'mode' => self::mode($row['hostPrinterLevel'] ?? ''),
                'spooler' => (string)($row['hspSubsystem'] ?? ''),
                'assigned' => implode(', ', array_keys($want)),
                'installed' => implode(', ', array_keys($have)),
                'missing' => implode(', ', $missing),
                'default' => $default,
                'state' => self::state($reported, $missing, $extra, $error),
                'error' => $error,
                'observedAt' => (string)($row['hspObservedAt'] ?? '')
            ];
        }

        // A capped fetch is not a complete answer, and a CSV taken from one
        // looks like a complete file once it is on disk.
        return [
            'data' => $data,
            'truncated' => $truncated
        ];
    }

    /**
     * The printers assigned to each host, by name.
     *
     * Resolved through Resolver::resolvePrinters -- the same call
     * PrinterClient makes to build what actually goes down the wire -- so
     * the report cannot disagree with what the host is being told to have.
     * Re-implementing the group-grant and default-precedence rules here is
     * how a report starts quietly contradicting the thing it reports on.
     *
     * @param int[] $hostIDs the hosts on this page
     *
     * @return array hostID => [name => printerID]
     */
    private static function _assigned(array $hostIDs)
    {
        if (empty($hostIDs)) {
            return [];
        }
        $resolved = Resolver::resolvePrinters($hostIDs);

        $wanted = [];
        foreach ($resolved as $set) {
            foreach ((array)($set['printers'] ?? []) as $id) {
                $wanted[(int)$id] = true;
            }
        }
        // getNames, not getIds. getIds returns a FLAT list of one field --
        // the names with nothing tying them to their ids -- and the join
        // below needs both. getNames answers with stdClass rows carrying
        // ->id and ->name, which is what makes the mapping possible at all.
        $names = [];
        if (!empty($wanted)) {
            foreach (Route::getNames('printer', ['id' => array_keys($wanted)]) as $r) {
                $names[(int)($r->id ?? 0)] = (string)($r->name ?? '');
            }
        }

        $out = [];
        foreach ($resolved as $hostID => $set) {
            foreach ((array)($set['printers'] ?? []) as $id) {
                $name = (string)($names[(int)$id] ?? '');
                if ('' === $name) {
                    continue;
                }
                $out[(int)$hostID][$name] = (int)$id;
            }
        }

        return $out;
    }

    /**
     * The printers each host says it actually has.
     *
     * @param int[] $hostIDs the hosts on this page
     *
     * @return array hostID => [name => ['uri' => ..., 'default' => bool]]
     */
    private static function _installed(array $hostIDs)
    {
        if (empty($hostIDs)) {
            return [];
        }
        $rows = (array)self::$DB->query(
            'SELECT `hpHostID`,`hpName`,`hpURI`,`hpDefault` '
            . 'FROM `hostPrinter` WHERE `hpHostID` IN ('
            . implode(',', array_map('intval', $hostIDs))
            . ') ORDER BY `hpName` ASC'
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['hpHostID']][(string)$row['hpName']] = [
                'uri' => (string)($row['hpURI'] ?? ''),
                'default' => !empty($row['hpDefault'])
            ];
        }

        return $out;
    }

    /**
     * The most recent install error recorded against each host.
     *
     * The column this report exists for as much as any other. Today a
     * printer that will not install produces nothing an admin can see.
     *
     * @param int[] $hostIDs the hosts on this page
     *
     * @return array hostID => message
     */
    private static function _errors(array $hostIDs)
    {
        if (empty($hostIDs)) {
            return [];
        }
        $rows = (array)self::$DB->query(
            'SELECT `paHostID`,`paError` FROM `printerAssoc` '
            . 'WHERE `paHostID` IN ('
            . implode(',', array_map('intval', $hostIDs))
            . ") AND `paError` <> '' ORDER BY `paAppliedAt` DESC"
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();

        $out = [];
        foreach ($rows as $row) {
            $hostID = (int)$row['paHostID'];
            if (isset($out[$hostID])) {
                // Ordered newest first, so the first one seen is the one to
                // show. An admin fixing a host wants the current failure,
                // not a list of everything that has ever gone wrong on it.
                continue;
            }
            $out[$hostID] = (string)($row['paError'] ?? '');
        }

        return $out;
    }

    /**
     * The host's printer mode, in words.
     *
     * `hostPrinterLevel` stores 0, 1 or 2 and the wire has always sent 0,
     * `a` or `ar` -- two vocabularies for one setting, neither written down
     * anywhere an admin can see (design 0010 section 1.3). This is the
     * third and the only one meant for a person.
     *
     * @param string $level the stored level
     *
     * @return string
     */
    protected static function mode($level)
    {
        switch ((int)$level) {
            case 1:
                return _('assigned');
            case 2:
                return _('exclusive');
            default:
                return _('off');
        }
    }

    /**
     * The verdict for one host.
     *
     * Five states, and the distinction between "ok" and "never reported" is
     * the whole point: "never reported" is FOG not knowing, and an empty
     * State column would let it pass for agreement.
     *
     * Ordered by what an admin should act on first. A recorded error beats
     * a missing printer because it says WHY; a missing printer beats an
     * extra one because somebody asked for it and did not get it.
     *
     * @param bool     $reported whether anything was ever reported
     * @param string[] $missing  assigned and not installed
     * @param string[] $extra    installed and not assigned
     * @param string   $error    the last recorded install error
     *
     * @return string
     */
    protected static function state($reported, array $missing, array $extra, $error)
    {
        if (!$reported) {
            return _('never reported');
        }
        if ('' !== trim($error)) {
            return _('failed');
        }
        if (!empty($missing)) {
            return _('missing');
        }
        if (!empty($extra)) {
            // Not a fault on its own: only mode 2 claims to own every
            // printer on the machine, and in mode 1 an extra queue is
            // somebody's own printer that FOG was never asked to manage.
            return _('extra');
        }
        return _('ok');
    }
}
