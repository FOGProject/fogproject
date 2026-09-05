<?php
/**
 * What the fleet actually has installed, one row per name/version pair.
 *
 * PHP version 7.4+
 *
 * @category Installed_Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Pages\ReportManagement;

/**
 * What the fleet actually has installed, one row per name/version pair.
 *
 * NOT Software_Report.php. That report is the desired-state install
 * feature (design 0003): what FOG is configured to push, and whether each
 * host converged to it. This one is design 0006's agent-reported inventory
 * (the hostSoftware table): what a host's own package manager told the
 * agent is on the machine, whether or not FOG ever asked for it. "Who has
 * what" rather than "did the install work".
 *
 * A FLEET-WIDE ROLL-UP, not a per-host list. hostSoftware is one row per
 * host per package; this groups it down to one row per name/version with a
 * count of the hosts currently reporting it, which is the shape "who still
 * has the old version of X" needs. The per-host detail stays on each
 * host's own Installed Software tab (HostManagement::hostInstalledSoftware).
 *
 * GATED ON `host`. Reports share the `report` node by default -- the defect
 * ADR 0023 opens with -- and hostSoftware is host data, gated on host.view
 * everywhere else (the tab above reads it under the same permission). It
 * narrows against the default; nothing anyone holds today gets wider.
 *
 * NOT IN ReportManagement::AGGREGATIONS. That const separates dashboards
 * (tiles/charts over a window) from row dumps for the sidebar. This report
 * has no window -- "currently installed" is a state, not a range of
 * events -- and no chart, only a grid, so by the same test Software_Report
 * and Snapin_List are already sorted by, it belongs under Lists.
 *
 * @category Installed_Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Installed_Software extends ReportManagement
{
    /**
     * The most rows a grid or export will carry back.
     *
     * Same stance as Software_Report/Snapin_Report: a report says so on
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
            _('Name'),
            _('Version'),
            _('Hosts')
        ];
        $this->attributes = [
            [], [], []
        ];

        $payload = $this->reportRows();

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        // Said on the page, not just in the docblock. See the class note.
        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'This is what hosts report as installed '
                    . '(agent-reported), not the software FOG is '
                    . 'configured to install.'
                )
            )
        );

        echo self::renderReportCap(
            $payload['truncated'],
            self::MAX_ROWS
        );

        $this->render(12, 'installedsoftwarereport-table');

        echo '</div>';
        echo '</div>';
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
        // hostSoftware rows are CLOSED (hsRemovedAt set), not deleted, once
        // a host stops reporting a package -- so this IS NULL clause is
        // what makes the count "hosts that have it now" rather than "hosts
        // that ever had it".
        $sql = "SELECT `hsName` AS `name`,
                       `hsVersion` AS `version`,
                       COUNT(DISTINCT `hsHostID`) AS `hostCount`
                  FROM `hostSoftware`
                 WHERE `hsRemovedAt` IS NULL
                 GROUP BY `hsName`, `hsVersion`
                 ORDER BY `hostCount` DESC, `hsName` ASC
                 LIMIT " . (self::MAX_ROWS + 1);

        $rows = (array)self::$DB->query($sql)
            ->fetch(\PDO::FETCH_ASSOC, 'fetch_all')
            ->get();

        $data = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $data[] = [
                'name' => (string)($row['name'] ?? ''),
                'version' => (string)($row['version'] ?? ''),
                'hostCount' => (string)(int)($row['hostCount'] ?? 0)
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
