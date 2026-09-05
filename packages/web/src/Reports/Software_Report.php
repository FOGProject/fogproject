<?php
/**
 * Software status across hosts: what's desired, what's installed, and
 * whether the last check converged.
 *
 * PHP version 7.4+
 *
 * @category Software_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Audit\ReportWindow;
use FOG\Pages\ReportManagement;

/**
 * Software status across hosts: what's desired, what's installed, and
 * whether the last check converged.
 *
 * One row per softwareStatus check-in, joined to the software entry it
 * belongs to and the host that reported it. This is deliberately a row
 * dump windowed on when the host last checked (design 0003 section 6),
 * not a tiles/charts dashboard like Snapin_Report -- there is no per-day
 * trend or top-N grouping this question needs, only "which of these rows
 * fell in the window", so it is closer in shape to Snapin_List with a
 * date filter added.
 *
 * NOT IN ReportManagement::AGGREGATIONS. That const separates dashboards
 * from row dumps for the sidebar (its own docblock says so); it does not
 * change how a report page renders -- every report shares the same
 * file()/getList()/exportAll() scaffold regardless of membership. This
 * report has no tiles, no per-day series and no top-N fold, so by the
 * same test Snapin_Report and Snapin_List are already sorted by, it
 * belongs with Snapin_List under Lists.
 *
 * @category Software_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Software_Report extends ReportManagement
{
    /**
     * How far back the report looks when nobody has said.
     *
     * A week, not Snapin_Report's month: a software drift check runs every
     * few hours (FOG_SOFTWARE_DRIFT_INTERVAL, design 0003 section 5), so a
     * week already holds several check-ins per host.
     *
     * @var string
     */
    const DEFAULT_WINDOW = '-7 days';
    /**
     * The most rows a grid or export will carry back.
     *
     * Same stance as Snapin_Report/WindowedStats: a report says so on
     * screen when it truncates rather than quietly showing a prefix.
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
            _('Software'),
            _('Package'),
            _('Desired'),
            _('Installed'),
            _('Status'),
            _('Exit code'),
            _('Checked')
        ];
        $this->attributes = [
            [], [], [], [], [], [], [], []
        ];

        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $payload = $this->reportRows();

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        echo self::renderReportWindow(
            'software-report',
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        echo self::renderReportCap(
            $payload['truncated'],
            self::MAX_ROWS
        );

        $this->render(12, 'softwarereport-table');

        echo '</div>';
        echo '</div>';
    }
    /**
     * Human words for the version policy half of "Desired".
     *
     * Mirrors SoftwareManagement::_versionPolicyFor()'s reading of the same
     * stored value; kept as a small local copy rather than a shared helper
     * because the two format it for different audiences -- a form field
     * there, a table cell here.
     *
     * @param string $version the stored version value
     *
     * @return string
     */
    private static function _desiredVersion($version)
    {
        if ('latest' === $version) {
            return _('latest');
        }
        if ('' === (string)$version) {
            return _('any version');
        }
        return (string)$version;
    }
    /**
     * The rows this report serves.
     *
     * Split from the emit so the grid and the CSV export run the same
     * query -- see ReportManagement::exportAll().
     *
     * @return array
     */
    protected function reportRows()
    {
        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);

        $stateLabels = [
            'present' => _('Present'),
            'absent' => _('Absent')
        ];
        // design 0003 section 3: what the agent reports back per check-in.
        $statusLabels = [
            'converged' => _('Converged'),
            'installed' => _('Installed'),
            'upgraded' => _('Upgraded'),
            'removed' => _('Removed'),
            'failed' => _('Failed'),
            'retry' => _('Retry'),
            'reboot' => _('Reboot'),
            'cannot_run' => _('Cannot Run')
        ];

        $sql = "SELECT `hosts`.`hostName` AS `hostName`,
                       `software`.`swName` AS `softwareName`,
                       `software`.`swPackage` AS `package`,
                       `software`.`swState` AS `state`,
                       `software`.`swVersion` AS `version`,
                       `softwareStatus`.`sstInstalledVersion` AS `installed`,
                       `softwareStatus`.`sstStatus` AS `status`,
                       `softwareStatus`.`sstReturnCode` AS `code`,
                       `softwareStatus`.`sstChecked` AS `checked`
                  FROM `softwareStatus`
                  LEFT OUTER JOIN `software`
                         ON `software`.`swID` = `softwareStatus`.`sstSoftwareID`
                  LEFT OUTER JOIN `hosts`
                         ON `hosts`.`hostID` = `softwareStatus`.`sstHostID`
                 WHERE `softwareStatus`.`sstChecked` BETWEEN :start AND :end
                 ORDER BY `softwareStatus`.`sstChecked` DESC,
                          `softwareStatus`.`sstID` DESC
                 LIMIT " . (self::MAX_ROWS + 1);

        $rows = (array)self::$DB->query(
            $sql,
            [],
            [
                ':start' => $start->format('Y-m-d H:i:s'),
                ':end' => $end->format('Y-m-d H:i:s')
            ]
        )
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        $data = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $state = (string)($row['state'] ?? '');
            $data[] = [
                'hostName' => (string)($row['hostName'] ?? ''),
                'softwareName' => (string)($row['softwareName'] ?? ''),
                'package' => (string)($row['package'] ?? ''),
                'desired' => 'absent' === $state
                    ? $stateLabels['absent']
                    : sprintf(
                        '%s (%s)',
                        $stateLabels[$state] ?? ($state ?: _('Present')),
                        self::_desiredVersion((string)($row['version'] ?? ''))
                    ),
                'installed' => (string)($row['installed'] ?? ''),
                'status' => $statusLabels[(string)($row['status'] ?? '')]
                    ?? (string)($row['status'] ?? ''),
                'code' => (string)($row['code'] ?? ''),
                'checked' => (string)($row['checked'] ?? '')
            ];
        }

        // A capped fetch is not a complete answer, and a CSV taken from one
        // looks exactly like a complete file once it is on disk -- see
        // ReportManagement::_exportFilename(). The query asks for one row
        // past the cap as a sentinel, so `>` here means that sentinel came
        // back -- exactly MAX_ROWS actual rows does not falsely trip this.
        return [
            'data' => $data,
            'truncated' => count($rows) > self::MAX_ROWS
        ];
    }
}
