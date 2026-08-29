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

namespace FOG;

use FOG\Audit\InventoryStats;
use FOG\Audit\ReportWindow;
use FOG\Router\HTTPResponseCodes;

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
        $this->title = _('Hardware Report');

        $this->headerData = [
            _('Host'),
            _('Manufacturer'),
            _('Model'),
            _('Serial'),
            _('CPU'),
            _('Memory'),
            _('Recorded')
        ];
        $this->attributes = [
            [], [], [], [], [], [], []
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
                    'name' => _('Machines'),
                    'id' => 'hardware-report-machines',
                    'generator' => function () {
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
     * Serves the rows.
     *
     * @return void
     */
    public function getList()
    {
        header('Content-type: application/json');
        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $rows = InventoryStats::hosts($start, $end);

        $unknown = _('Not reported');
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'hostName' => (string)($row['hostName'] ?? ''),
                'vendor' => self::_orUnknown($row['vendor'] ?? '', $unknown),
                'model' => self::_orUnknown($row['model'] ?? '', $unknown),
                'serial' => self::_orUnknown($row['serial'] ?? '', $unknown),
                'cpu' => self::_orUnknown($row['cpu'] ?? '', $unknown),
                'memory' => self::_orUnknown($row['memory'] ?? '', $unknown),
                'recorded' => (string)($row['recorded'] ?? '')
            ];
        }

        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode(['data' => $data]);
        exit;
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
    /**
     * A reported value, or the word for not having one.
     *
     * Every inventory column is NOT NULL DEFAULT '', so absence arrives as
     * an empty string and renders as an empty cell -- which reads as a
     * rendering fault rather than as the answer. See InventoryStats.
     *
     * @param mixed  $value   the stored value
     * @param string $unknown the translated word for absence
     *
     * @return string
     */
    private static function _orUnknown($value, $unknown)
    {
        $value = trim((string)$value);

        return '' === $value ? $unknown : $value;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Hardware_Report', 'Hardware_Report');
