<?php
/**
 * How much imaging happened, of what, and to how many machines.
 *
 * PHP version 7.4+
 *
 * @category Imaging_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Audit\ImagingStats;
use FOG\Audit\ReportWindow;
use FOG\Items\TaskState;
use FOG\Pages\ReportManagement;

/**
 * How much imaging happened, of what, and to how many machines.
 *
 * ADR 0030's first report, and the one the shape was designed against: a
 * window in the URL, headline numbers, charts over the same window, and a
 * grid of the rows underneath. Everything on the page comes from one fold
 * of `taskLog` in ImagingStats, so a tile, a bar and a row cannot disagree
 * about what a run is -- which they did before, because the dashboard's
 * 30-day count carried the counting rules in a comment inside one method
 * and nothing else could reach them.
 *
 * WHY taskLog AND NOT tasks. A task row is overwritten as it progresses,
 * so it records the CURRENT state of the tasks that still exist; a machine
 * imaged last month whose task has since been cleared leaves no row at all.
 * `taskLog` keeps one row per state TRANSITION, which is why the three
 * counting rules in ImagingStats exist and why they belong somewhere they
 * can be tested rather than in a page.
 *
 * GATED ON `task`, NOT `report`, for the same reason Run History is: this
 * is task activity, Task Management's own log pane is gated on task.view,
 * and Authorization::REPORT_NODES is the seam for saying so (ADR 0030
 * decision 4). It narrows against the report default; nothing anyone holds
 * today gets wider.
 *
 * NOT serverSide, like Run History and unlike the older reports. The rows
 * are the same bounded fold the charts above them are drawn from -- asking
 * for them a page at a time through Route::listem() would be a second,
 * differently-shaped query answering a slightly different question, which
 * is the disagreement this report exists to avoid.
 *
 * @category Imaging_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Imaging_Report extends ReportManagement
{
    /**
     * How far back the report looks when nobody has said.
     *
     * A month rather than Run History's day. The question this report is
     * opened with is "how are we doing", which needs enough days for the
     * trend line to be a trend; a day of imaging is three points and a
     * shape nobody can read.
     *
     * @var string
     */
    const DEFAULT_WINDOW = '-30 days';
    /**
     * How many images get their own bar before the rest are folded up.
     *
     * @var int
     */
    const TOP_IMAGES = 8;
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
            _('Image'),
            _('Type'),
            _('Started'),
            _('Finished'),
            _('State'),
            _('By')
        ];
        $this->attributes = [
            [], [], [], [], [], [], []
        ];

        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $totals = ImagingStats::totals($start, $end);
        $perDay = ImagingStats::runsPerDay($start, $end);
        $byImage = ImagingStats::runsByImage($start, $end, self::TOP_IMAGES);

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        echo self::renderReportWindow(
            'imaging',
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        echo self::renderReportCap(
            $totals['truncated'],
            ImagingStats::MAX_ROWS
        );

        echo self::renderStatTiles(
            [
                ['value' => $totals['runs'], 'label' => _('Imaging runs')],
                ['value' => $totals['hosts'], 'label' => _('Machines imaged')],
                ['value' => $totals['images'], 'label' => _('Images used')],
                [
                    'value' => count($perDay) > 0
                        ? round($totals['runs'] / count($perDay), 1)
                        : 0,
                    'label' => _('Runs per day')
                ]
            ]
        );

        // 0, not the -1 default: that default resolves the current node
        // and id into an object, and a falsy one skips tabFields'
        // TABDATA_HOOK and plugin-injection blocks. Those exist to hang
        // extra tabs off an entity being edited; a report is not an entity
        // and has no id to give them, so `report`/'' would be looked up as
        // a class and found not to be one.
        echo self::tabFields(
            [
                [
                    'name' => _('Activity'),
                    'id' => 'imaging-activity',
                    'generator' => function () use ($perDay, $byImage) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'imaging-per-day',
                            _('Runs per day'),
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
                                    ]
                                ]
                            ],
                            7
                        );
                        echo self::renderChartPanel(
                            'imaging-by-image',
                            _('Runs by image'),
                            [
                                'type' => 'doughnut',
                                'labels' => array_column($byImage, 'image'),
                                'series' => [
                                    [
                                        'label' => _('Runs'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($byImage, 'count')
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
                    'id' => 'imaging-runs',
                    'generator' => function () {
                        $this->render(12, 'imaging-table');
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
        $rows = ImagingStats::runs($start, $end);

        // State ids become names through the model, memoized on the distinct
        // id -- there are a handful of them and they are hook-overridable,
        // so this is the same thing Route's statename formatter does rather
        // than a join that would freeze the labels.
        $states = [];
        // The states after which nothing more is written for a task. The
        // fold's `ended` is MAX(createTime) over the run's rows, which is a
        // finish time only once the run has finished -- for one still in
        // progress it is the last transition, and printing that under
        // "Finished" says a deploy completed when it is still copying.
        $terminal = [
            (int)TaskState::getCompleteState(),
            (int)TaskState::getCancelledState(),
            (int)TaskState::getFailedState()
        ];
        $data = [];
        foreach ($rows as $row) {
            $stateID = (int)($row['stateID'] ?? 0);
            if ($stateID > 0 && !isset($states[$stateID])) {
                $states[$stateID] = (string)(new TaskState($stateID))
                    ->get('name');
            }
            $data[] = [
                // The name as it was AT THE TIME, out of the log row, not
                // resolved from `hosts` now. A machine that has since been
                // renamed or deleted still has to name itself in a report
                // about what happened last month.
                'hostName' => (string)($row['hostName'] ?? ''),
                'imageName' => (string)($row['imageName'] ?? ''),
                'taskTypeName' => (string)($row['taskTypeName'] ?? ''),
                'started' => (string)($row['started'] ?? ''),
                'ended' => in_array($stateID, $terminal, true)
                    ? (string)($row['ended'] ?? '')
                    : '',
                'state' => $states[$stateID] ?? '',
                'createdBy' => (string)($row['createdBy'] ?? '')
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
            'truncated' => count($rows) >= ImagingStats::MAX_ROWS
        ];
    }
}
