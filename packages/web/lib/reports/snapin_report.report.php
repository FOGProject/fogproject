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

namespace FOG;

use FOG\Audit\ReportWindow;
use FOG\Audit\SnapinStats;
use FOG\Router\HTTPResponseCodes;

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
        $this->title = _('Snapin Report');

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

        if ($totals['truncated']) {
            printf(
                '<div class="alert alert-warning">%s</div>',
                \Initiator::e(
                    sprintf(
                        _(
                            'More than %s runs match this range. Everything '
                            . 'below counts the most recent %s only -- narrow '
                            . 'the dates for exact figures.'
                        ),
                        number_format(SnapinStats::MAX_ROWS),
                        number_format(SnapinStats::MAX_ROWS)
                    )
                )
            );
        }

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
     * Serves the rows.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');
        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $rows = SnapinStats::runs($start, $end);

        $states = [];
        $data = [];
        foreach ($rows as $row) {
            $stateID = (int)($row['stateID'] ?? 0);
            if ($stateID > 0 && !isset($states[$stateID])) {
                $states[$stateID] = (string)self::getClass('TaskState', $stateID)
                    ->get('name');
            }
            $code = (int)($row['code'] ?? 0);
            $data[] = [
                'snapin' => (string)($row['snapin'] ?? ''),
                'hostName' => (string)($row['hostName'] ?? ''),
                'completed' => (string)($row['completed'] ?? ''),
                // Said in words as well as in the code, because 0 meaning
                // success is a convention rather than something the column
                // says. The code stays in its own column for anyone who
                // needs the actual value.
                'outcome' => 0 === $code ? _('Succeeded') : _('Failed'),
                'code' => (string)$code,
                'details' => (string)($row['details'] ?? ''),
                // Shown beside the outcome rather than instead of it: the
                // two can legitimately disagree, and that disagreement is
                // the answer to "why does this say it worked". See
                // SnapinStats' docblock.
                'state' => $states[$stateID] ?? ''
            ];
        }

        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode(['data' => $data]);
        exit;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Snapin_Report', 'Snapin_Report');
