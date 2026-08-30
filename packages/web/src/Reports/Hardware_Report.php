<?php
/**
 * What hardware the fleet is actually made of.
 *
 * PHP version 7.4+
 *
 * @category Hardware_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Reports;

use FOG\Audit\InventoryStats;
use FOG\Audit\ReportWindow;
use FOG\Pages\ReportManagement;
use FOG\Router\Route;

/**
 * What hardware the fleet is actually made of.
 *
 * ADR 0030's hardware subject. Inventory Report, the report next to this
 * one, prints all 36 inventory columns for every machine -- it answers
 * "what did we record about this host" and is exactly the shape ADR 0030
 * was opened about. This answers "what is the fleet made of": how many
 * machines are still on 4GB, which models are about to need replacing,
 * whether the last batch of registrations got inventoried at all. Same
 * rows, and none of those were askable.
 *
 * ITS GRID IS A SUMMARY, not a second copy of that one. Seven columns, so
 * a slice of a chart can be read as machines; the full record stays one
 * click away on Inventory Report and on the host's own tab.
 *
 * THE WINDOW IS AS-OF, the same meaning Fleet Report gives it, and the page
 * says so out loud for the same reason. The breakdowns describe the whole
 * estate; the range picks out inventory RECORDED inside it, which is the
 * freshness half of the picture -- a census whose data is two years old is
 * a census of a fleet that no longer exists.
 *
 * GATED ON `host`. Authorization already maps the `inventory` node onto
 * `host`, so this lands where the Inventory tab already is. Narrows against
 * the default `report` node (ADR 0030 decision 4); nothing anyone holds
 * today gets wider.
 *
 * @category Hardware_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Hardware_Report extends ReportManagement
{
    /**
     * How far back "recently inventoried" reaches when nobody has said.
     *
     * A quarter, matching Fleet Report, because the two are read together
     * and a different default on each would make the same date range mean
     * different things depending on which tab you came from.
     *
     * @var string
     */
    const DEFAULT_WINDOW = '-90 days';
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = self::reportTitle();

        $this->headerData = [
            _('Host Name'),
            // User set information
            _('Primary User'),
            _('Other Primary'),
            _('Other Secondary'),
            // System
            _('System Manufacturer'),
            _('System Product'),
            _('System Version'),
            _('System Serial'),
            _('System UUID'),
            _('System Type'),
            // BIOS
            _('BIOS Version'),
            _('BIOS Vendor'),
            _('BIOS Date'),
            // Motherboard
            _('Motherboard Manufacturer'),
            _('Motherboard Product Name'),
            _('Motherboard Version'),
            _('Motherboard Serial'),
            _('Motherboard Asset'),
            // CPU
            _('CPU Manufacturer'),
            _('CPU Version'),
            _('CPU Current Speed'),
            _('CPU Maximum Speed'),
            // Memory
            _('System Memory Available'),
            // Hard Disk
            _('Hard Disk Model'),
            _('Hard Disk Serial'),
            _('Hard Disk Firmware'),
            // Case
            _('Case Manufacturer'),
            _('Case Version'),
            _('Case Serial'),
            _('Case Asset'),
            // GPU
            _('GPU Vendors'),
            _('GPU Products'),
            // Name of host
            _('Hostname'),
        ];
        $this->attributes = [
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            ['width' => 40],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
        ];

        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $totals = InventoryStats::totals($start, $end);
        $vendors = InventoryStats::breakdown('manufacturer', $start, $end);
        $models = InventoryStats::breakdown('model', $start, $end);
        $memory = InventoryStats::breakdown('memory', $start, $end);
        $cpus = InventoryStats::breakdown('cpu', $start, $end);
        $recorded = InventoryStats::recordedPerDay($start, $end);

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        echo self::renderReportWindow(
            'hardware-report',
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        // Said on the page, not just in the docblock. See the class note.
        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'The breakdowns cover every inventoried machine. '
                    . 'The range selects inventory recorded inside it.'
                )
            )
        );

        echo self::renderStatTiles(
            [
                ['value' => $totals['records'], 'label' => _('Inventoried')],
                ['value' => $totals['vendors'], 'label' => _('Manufacturers')],
                ['value' => $totals['models'], 'label' => _('Models')],
                [
                    'value' => $totals['recorded'],
                    'label' => _('Recorded in range')
                ]
            ],
            4
        );

        echo self::tabFields(
            [
                [
                    'name' => _('Make and model'),
                    'id' => 'hardware-report-make',
                    'generator' => function () use ($vendors, $models) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'hardware-vendor',
                            _('Manufacturers'),
                            self::_pie(_('Machines'), $vendors),
                            6
                        );
                        echo self::renderChartPanel(
                            'hardware-model',
                            _('Models'),
                            self::_pie(_('Machines'), $models),
                            6
                        );
                        echo '</div>';
                    }
                ],
                [
                    'name' => _('Capability'),
                    'id' => 'hardware-report-capability',
                    'generator' => function () use ($memory, $cpus) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'hardware-memory',
                            _('Memory'),
                            self::_pie(_('Machines'), $memory),
                            6
                        );
                        echo self::renderChartPanel(
                            'hardware-cpu',
                            _('Processors'),
                            self::_pie(_('Machines'), $cpus),
                            6
                        );
                        echo '</div>';
                    }
                ],
                [
                    'name' => _('Freshness'),
                    'id' => 'hardware-report-freshness',
                    'generator' => function () use ($recorded) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'hardware-recorded',
                            _('Inventory recorded per day'),
                            [
                                'type' => 'line',
                                'labels' => array_column($recorded, 'date'),
                                'series' => [
                                    [
                                        'label' => _('Records'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($recorded, 'count')
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
                    'name' => _('Inventory'),
                    'id' => 'hardware-report-inventory',
                    'generator' => function () {
                        // The whole record, not a summary of it. Five
                        // columns show by default and the other 28 are
                        // behind the Column Visibility button, which is
                        // already in reportButtons -- so a slice of a chart
                        // above can be read as machines without this
                        // becoming a wall of 33 columns.
                        $this->render(12, 'hardwarereport-table');
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
        Route::listem('inventory');

        // Decoded rather than echoed straight through, because exportAll()
        // needs the rows as data and Route hands its payload back encoded.
        // getList() re-encodes; that round trip costs a fraction of the
        // query it wraps and buys one read path instead of two.
        return (array) json_decode(Route::getData(), true);
    }
    /**
     * One breakdown as a doughnut, which is the same shape four times.
     *
     * @param string $label the series label
     * @param array  $slices the breakdown rows
     *
     * @return array the chart definition renderChartPanel() takes
     */
    private static function _pie($label, array $slices)
    {
        return [
            'type' => 'doughnut',
            'labels' => array_column($slices, 'label'),
            'series' => [
                [
                    'label' => $label,
                    'data' => array_map(
                        'intval',
                        array_column($slices, 'count')
                    )
                ]
            ]
        ];
    }
}
