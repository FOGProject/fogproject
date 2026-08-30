<?php
/**
 * How much the image estate weighs, and where it is meant to live.
 *
 * PHP version 7.4+
 *
 * @category Storage_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Audit\ReportWindow;
use FOG\Audit\StorageStats;
use FOG\Pages\ReportManagement;

/**
 * How much the image estate weighs, and where it is meant to live.
 *
 * ADR 0030's storage subject. Image Management lists images and shows one
 * size on one line; nothing has ever added them up. "What is this costing
 * us in disk", "which images are we replicating to every node that nobody
 * has deployed in a year", "what is the single biggest thing we keep" are
 * all answerable from `images` and `imageGroupAssoc`, and none of them were
 * askable.
 *
 * THE SIZES ARE WHAT THE GROUP SHOULD HOLD, not what a node currently
 * does, and the page says so. `imageServerSize` is the size measured when
 * the image was captured; replication copies it to the rest of the group.
 * Actual free space needs a live call to each node, which is the
 * dashboard's job. Printing "allocated" bytes as "used" would be wrong in
 * a way nobody reading it could detect.
 *
 * GATED ON `storagenode`. It reads `images`, `imageGroupAssoc`, `nfsGroups`
 * and `nfsGroupMembers`, and of those the storage estate is the part not
 * already visible elsewhere -- image names reach a `host`-gated screen
 * already, group and node names do not. Narrows against the default
 * `report` node (ADR 0030 decision 4); nothing anyone holds gets wider.
 *
 * @category Storage_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Storage_Report extends ReportManagement
{
    /**
     * How far back "recently deployed" and "recently added" reach.
     *
     * A year. An image is a much slower-moving thing than a task: a
     * quarter's worth of deploys says almost nothing about whether an
     * image is still in use, and the stale tile is the one somebody acts
     * on by deleting things.
     *
     * @var string
     */
    const DEFAULT_WINDOW = '-365 days';
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = self::reportTitle();

        $this->headerData = [
            _('Image'),
            _('Size'),
            _('Groups'),
            _('Replicates'),
            _('Enabled'),
            _('Created'),
            _('Last deployed'),
            // Hidden, and it has to have a header cell anyway: the table is
            // built from headerData and DataTables matches columns to <th>
            // by position, so a column without one silently shifts every
            // cell after it one place left. It exists because "9 GiB" and
            // "10 GiB" sort the wrong way round as strings -- the size
            // column sorts on this instead.
            _('Bytes')
        ];
        $this->attributes = [
            [], [], [], [], [], [], [], []
        ];

        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $totals = StorageStats::totals($start, $end);
        $groups = StorageStats::sizeByGroup($start, $end);
        $largest = StorageStats::largest($start, $end);
        $added = StorageStats::addedPerDay($start, $end);

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        echo self::renderReportWindow(
            'storage-report',
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        // Said on the page, not just in the docblock. See the class note.
        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'Sizes are what each group is configured to hold, not '
                    . 'current node usage. The range selects images added '
                    . 'inside it, and marks an image stale if it has not '
                    . 'been deployed since it opened.'
                )
            )
        );

        echo self::renderReportCap(
            $totals['truncated'],
            StorageStats::MAX_ROWS
        );

        echo self::renderStatTiles(
            [
                ['value' => $totals['images'], 'label' => _('Images')],
                [
                    'value' => self::formatByteSize((float)$totals['bytes']),
                    'label' => _('Allocated')
                ],
                [
                    'value' => $totals['stale'],
                    'label' => _('Stale'),
                    'warn' => true
                ],
                [
                    'value' => $totals['nodes'],
                    'label' => _('Storage nodes')
                ]
            ],
            4
        );

        echo self::tabFields(
            [
                [
                    'name' => _('Where it lives'),
                    'id' => 'storage-report-where',
                    'generator' => function () use ($groups, $largest) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'storage-group',
                            _('Allocated bytes per storage group'),
                            [
                                'type' => 'doughnut',
                                'labels' => array_column($groups, 'label'),
                                'series' => [
                                    [
                                        'label' => _('Bytes'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($groups, 'count')
                                        )
                                    ]
                                ]
                            ],
                            5
                        );
                        echo self::renderChartPanel(
                            'storage-largest',
                            _('Largest images'),
                            [
                                'type' => 'bar',
                                'labels' => array_column($largest, 'label'),
                                'series' => [
                                    [
                                        'label' => _('Bytes'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($largest, 'count')
                                        )
                                    ]
                                ]
                            ],
                            7
                        );
                        echo '</div>';
                    }
                ],
                [
                    'name' => _('Growth'),
                    'id' => 'storage-report-growth',
                    'generator' => function () use ($added) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'storage-added',
                            _('Images added per day'),
                            [
                                'type' => 'line',
                                'labels' => array_column($added, 'date'),
                                'series' => [
                                    [
                                        'label' => _('Images'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($added, 'count')
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
                    'name' => _('Images'),
                    'id' => 'storage-report-images',
                    'generator' => function () {
                        $this->render(12, 'storagereport-table');
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
        $rows = StorageStats::images($start, $end);

        $never = _('Never');
        $yes = _('Yes');
        $no = _('No');
        $data = [];
        foreach ($rows as $row) {
            $last = (string)($row['lastDeploy'] ?? '');
            $data[] = [
                'imageName' => (string)($row['imageName'] ?? ''),
                // Bytes are formatted here rather than in the browser so
                // the CSV export carries the same string the page shows.
                // The raw count goes out beside it for sorting.
                'size' => self::formatByteSize((float)($row['bytes'] ?? 0)),
                'bytes' => (string)(int)($row['bytes'] ?? 0),
                'groups' => (string)(int)($row['groups'] ?? 0),
                'replicate' => ((int)($row['replicate'] ?? 0)) > 0
                    ? $yes
                    : $no,
                'enabled' => ((int)($row['enabled'] ?? 0)) > 0 ? $yes : $no,
                'created' => (string)($row['created'] ?? ''),
                // Asked of FOGBase::validDate() rather than compared to
                // a literal: an upgraded server carries the zero date in
                // two spellings until schema step 344 has run, and there is
                // one definition of what an empty date means.
                'lastDeploy' => self::validDate($last) ? $last : $never
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
            'truncated' => count($rows) >= StorageStats::MAX_ROWS
        ];
    }
}
