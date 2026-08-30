<?php
/**
 * Who changed what, who was refused, and when.
 *
 * PHP version 7.4+
 *
 * @category Audit_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Audit\AuditStats;
use FOG\Audit\ReportWindow;
use FOG\Pages\ReportManagement;

/**
 * Who changed what, who was refused, and when.
 *
 * ADR 0030's change-and-access subject, and the last of the six. The Audit
 * Log page lists the rows and filters them well; it cannot answer a SHAPE
 * question. "Are we being probed", "who has been editing storage nodes
 * this month", "did the refusals start when that role went out" are all
 * shape questions, and no amount of paging through 40,000 rows answers
 * one.
 *
 * IT SUMMARIZES THE AUDIT LOG, it does not reimplement it. The grid here
 * is deliberately narrower than that page's: the stored prose, the change
 * detail and the correlation chain stay there, and this grid exists so a
 * spike in the chart above it can be read as events. ADR 0023's defect is
 * two screens over the same rows gated differently, not two screens.
 *
 * DENIED AND FAILED ARE NEVER ADDED TOGETHER. Denied is authorization
 * refusing an action; failed is an action that was permitted and then went
 * wrong. One is a security signal, the other operational, and a combined
 * "errors" number would be actionable as neither. Two tiles, and only
 * denials are drawn against the event line -- a refusal curve that tracks
 * the activity curve is normal, and one that does not is the thing worth
 * seeing.
 *
 * GATED ON `audit`. This one is not a narrowing of convenience: an audit
 * row necessarily discloses attempted usernames, which is exactly why ADR
 * 0021 gave `audit.view` its own permission rather than folding it into
 * settings. Serving the same rows under `report.view` would have handed
 * that to every report holder.
 *
 * @category Audit_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Audit_Report extends ReportManagement
{
    /**
     * How far back the report looks when nobody has said.
     *
     * A month. Short enough that the daily series is readable at one point
     * per day, long enough to show a weekly rhythm and to make a change of
     * rhythm visible against it.
     *
     * @var string
     */
    const DEFAULT_WINDOW = '-30 days';
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = self::reportTitle();

        $this->headerData = [
            _('When'),
            _('Who'),
            _('Source'),
            _('Address'),
            _('Event'),
            _('Subject'),
            _('Permission'),
            _('Outcome')
        ];
        $this->attributes = [
            [], [], [], [], [], [], [], []
        ];

        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $totals = AuditStats::totals($start, $end);
        $events = AuditStats::eventsPerDay($start, $end);
        $denied = AuditStats::deniedPerDay($start, $end);
        $actors = AuditStats::byActor($start, $end);
        $types = AuditStats::byType($start, $end);

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        echo self::renderReportWindow(
            'audit-report',
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        echo self::renderReportCap(
            $totals['truncated'],
            AuditStats::MAX_ROWS
        );

        echo self::renderStatTiles(
            [
                ['value' => $totals['events'], 'label' => _('Events')],
                ['value' => $totals['actors'], 'label' => _('People')],
                [
                    'value' => $totals['denied'],
                    'label' => _('Refused'),
                    'warn' => true
                ],
                [
                    'value' => $totals['failed'],
                    'label' => _('Failed'),
                    'warn' => true
                ]
            ],
            3
        );

        echo self::tabFields(
            [
                [
                    'name' => _('Activity'),
                    'id' => 'audit-report-activity',
                    'generator' => function () use ($events, $denied, $types) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'audit-per-day',
                            _('Events per day'),
                            [
                                'type' => 'line',
                                'labels' => array_column($events, 'date'),
                                'series' => [
                                    [
                                        'label' => _('Events'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($events, 'count')
                                        )
                                    ],
                                    [
                                        'label' => _('Refused'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($denied, 'count')
                                        )
                                    ]
                                ]
                            ],
                            7
                        );
                        echo self::renderChartPanel(
                            'audit-by-type',
                            _('Event types'),
                            [
                                'type' => 'doughnut',
                                'labels' => array_column($types, 'label'),
                                'series' => [
                                    [
                                        'label' => _('Events'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($types, 'count')
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
                    'name' => _('People'),
                    'id' => 'audit-report-people',
                    'generator' => function () use ($actors) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'audit-by-actor',
                            _('Events by account'),
                            [
                                'type' => 'doughnut',
                                'labels' => array_column($actors, 'label'),
                                'series' => [
                                    [
                                        'label' => _('Events'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($actors, 'count')
                                        )
                                    ]
                                ]
                            ],
                            12
                        );
                        echo '</div>';
                    }
                ],
                [
                    'name' => _('Events'),
                    'id' => 'audit-report-events',
                    'generator' => function () {
                        $this->render(12, 'auditreport-table');
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
        $rows = AuditStats::events($start, $end);

        $unattributed = _('Unattributed');
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'at' => (string)($row['at'] ?? ''),
                'actor' => '' === (string)($row['actor'] ?? '')
                    ? $unattributed
                    : (string)$row['actor'],
                'source' => (string)($row['source'] ?? ''),
                'ip' => (string)($row['ip'] ?? ''),
                'type' => (string)($row['type'] ?? ''),
                // The two subject columns joined here rather than given a
                // column each: on their own "host" and "lab-07" are two
                // cells nobody sorts by, and together they are the thing
                // the row is about.
                'subject' => self::_subject($row),
                'permission' => (string)($row['permission'] ?? ''),
                'outcome' => (string)($row['outcome'] ?? '')
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
            'truncated' => count($rows) >= AuditStats::MAX_ROWS
        ];
    }
    /**
     * One row's subject as a person would say it.
     *
     * A row need not have either half: an auth event has no subject at all,
     * and a mass action carries a type with no single label.
     *
     * @param array $row one event row
     *
     * @return string
     */
    private static function _subject(array $row)
    {
        $type = trim((string)($row['subjectType'] ?? ''));
        $label = trim((string)($row['subjectLabel'] ?? ''));

        if ('' === $label) {
            return $type;
        }
        if ('' === $type) {
            return $label;
        }

        return $type . ': ' . $label;
    }
}
