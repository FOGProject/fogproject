<?php
/**
 * Which snapins ran, where, and whether they worked.
 *
 * PHP version 7.4+
 *
 * @category Snapin_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Audit\ReportWindow;
use FOG\Audit\SnapinStats;
use FOG\Items\TaskState;
use FOG\Pages\ReportManagement;

/**
 * Which snapins ran, where, and whether they worked.
 *
 * ADR 0030's snapin subject. Snapin List, the report next to this one in the
 * menu, answers "what snapins exist"; this answers "what did they do", which
 * is the question nothing in FOG answered before -- a failed snapin was
 * visible one host at a time, on the host's own page, and only while its
 * task still existed.
 *
 * GATED ON `snapin`. Reports share the `report` node by default, which is
 * the defect ADR 0023 opens with; the rows here are snapin activity and
 * Snapin Management gates the same records on snapin.view. It narrows
 * against the default; nothing anyone holds today gets wider.
 *
 * WHY THE FAILURE COUNT IS ON A TILE AND ON A LINE. Eleven failures is a
 * crisis at twelve runs and noise at nine hundred, so the number is useless
 * without the denominator beside it -- the tiles carry both, and the chart
 * draws them on one axis.
 *
 * @category Snapin_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Snapin_Report extends ReportManagement
{
    /**
     * How far back the report looks when nobody has said.
     *
     * A month, like the imaging report and for the same reason: the
     * question is "how are we doing", which needs enough days to be a
     * trend.
     *
     * @var string
     */
    const DEFAULT_WINDOW = '-30 days';
    /**
     * How many snapins get their own slice before the rest are folded up.
     *
     * @var int
     */
    const TOP_SNAPINS = 8;
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = self::reportTitle();

        $this->headerData = [
            _('Snapin'),
            _('Host'),
            _('Completed'),
            _('Outcome'),
            _('Code'),
            _('Details'),
            _('Task state')
        ];
        $this->attributes = [
            [], [], [], [], [], [], []
        ];

        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $totals = SnapinStats::totals($start, $end);
        $perDay = SnapinStats::runsPerDay($start, $end);
        $failDay = SnapinStats::failuresPerDay($start, $end);
        $bySnapin = SnapinStats::failuresBySnapin(
            $start,
            $end,
            self::TOP_SNAPINS
        );

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        echo self::renderReportWindow(
            'snapin-report',
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        echo self::renderReportCap(
            $totals['truncated'],
            SnapinStats::MAX_ROWS
        );

        echo self::renderStatTiles(
            [
                ['value' => $totals['runs'], 'label' => _('Snapin runs')],
                [
                    'value' => $totals['failures'],
                    'label' => _('Failed'),
                    // Painted red only when there are any. A zero on a red
                    // card reads as a problem rather than as the absence of
                    // one.
                    'warn' => true
                ],
                ['value' => $totals['snapins'], 'label' => _('Snapins used')],
                ['value' => $totals['hosts'], 'label' => _('Machines')]
            ]
        );

        echo self::tabFields(
            [
                [
                    'name' => _('Activity'),
                    'id' => 'snapin-report-activity',
                    'generator' => function () use ($perDay, $failDay, $bySnapin) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'snapin-per-day',
                            _('Runs and failures per day'),
                            [
                                'type' => 'line',
                                'labels' => array_column($perDay, 'date'),
                                'series' => [
                                    [
                                        'label' => _('Runs'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($perDay, 'count')
                                        )
                                    ],
                                    [
                                        'label' => _('Failed'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($failDay, 'count')
                                        )
                                    ]
                                ]
                            ],
                            7
                        );
                        echo self::renderChartPanel(
                            'snapin-failures',
                            _('Failures by snapin'),
                            [
                                'type' => 'doughnut',
                                'labels' => array_column($bySnapin, 'snapin'),
                                'series' => [
                                    [
                                        'label' => _('Failed'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($bySnapin, 'count')
                                        )
                                    ]
                                ]
                            ],
                            5
                        );
                        echo '</div>';
                    }
                ],
                [
                    'name' => _('Runs'),
                    'id' => 'snapin-report-runs',
                    'generator' => function () {
                        $this->render(12, 'snapinreport-table');
                    }
                ]
            ],
            0
        );

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
        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $rows = SnapinStats::runs($start, $end);

        // The task's own status column knows about reboot/retry and about
        // a payload that never ran at all (hash_mismatch/timeout/
        // cannot_run), none of which the exit code alone can tell you. It
        // is the source of truth when a run recorded one; the code-based
        // guess below is only for rows from before this column existed.
        $outcomeLabels = [
            'success' => _('Succeeded'),
            'reboot' => _('Reboot'),
            'retry' => _('Retry'),
            'failed' => _('Failed'),
            'hash_mismatch' => _('Hash Mismatch'),
            'timeout' => _('Timeout'),
            'cannot_run' => _('Cannot Run')
        ];

        $states = [];
        $data = [];
        foreach ($rows as $row) {
            $stateID = (int)($row['stateID'] ?? 0);
            if ($stateID > 0 && !isset($states[$stateID])) {
                $states[$stateID] = (string)(new TaskState($stateID))
                    ->get('name');
            }
            $code = (int)($row['code'] ?? 0);
            $status = (string)($row['status'] ?? '');
            $data[] = [
                'snapin' => (string)($row['snapin'] ?? ''),
                'hostName' => (string)($row['hostName'] ?? ''),
                'completed' => (string)($row['completed'] ?? ''),
                'outcome' => '' !== $status
                    ? ($outcomeLabels[$status] ?? $status)
                    // Said in words as well as in the code, because 0
                    // meaning success is a convention rather than
                    // something the column says. The code stays in its
                    // own column for anyone who needs the actual value.
                    : (0 === $code ? _('Succeeded') : _('Failed')),
                'code' => (string)$code,
                'details' => (string)($row['details'] ?? ''),
                // Shown beside the outcome rather than instead of it: the
                // two can legitimately disagree, and that disagreement is
                // the answer to "why does this say it worked". See
                // SnapinStats' docblock.
                'state' => $states[$stateID] ?? ''
            ];
        }

        // A capped fetch is not a complete answer, and a CSV taken from
        // one looks exactly like a complete file once it is on disk. The
        // flag rides in the envelope so ReportManagement::exportAll() can
        // write the cap into the download's name. `>=` rather than `>`
        // because the rollup slices at MAX_ROWS and cannot see what it
        // dropped; the name it produces is true either way.
        return [
            'data' => $data,
            'truncated' => count($rows) >= SnapinStats::MAX_ROWS
        ];
    }
}
