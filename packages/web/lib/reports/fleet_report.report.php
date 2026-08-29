<?php
/**
 * How current the fleet is, and which machines have fallen behind.
 *
 * PHP version 7.4+
 *
 * @category Fleet_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Audit\FleetStats;
use FOG\Audit\ReportWindow;
use FOG\Router\HTTPResponseCodes;

/**
 * How current the fleet is, and which machines have fallen behind.
 *
 * ADR 0030's fleet subject. Host List, the report next to this one, answers
 * "what machines exist"; this answers "which of them need attention", which
 * is a question FOG could only be asked one host at a time.
 *
 * THE WINDOW MEANS SOMETHING DIFFERENT HERE and the page says so out loud.
 * On every other report it selects events that happened between two dates.
 * Staleness is a state, not an event: a machine that did nothing at all in
 * the window is exactly the machine this report is about. So the END is an
 * as-of date and the START is what counts as current. Leaving that implicit
 * would make one control mean two things, which is worse than having no
 * control.
 *
 * GATED ON `host`. Reports share the `report` node by default -- the defect
 * ADR 0023 opens with -- and these are host records, gated on host.view
 * everywhere else in FOG. It narrows against the default; nothing anyone
 * holds today gets wider.
 *
 * @category Fleet_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Fleet_Report extends ReportManagement
{
    /**
     * How far back "current" reaches when nobody has said.
     *
     * A quarter. Long enough that a fleet on a normal refresh cycle does
     * not open the page painted red, short enough that a machine nobody has
     * touched since last year is not counted as fine.
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
        $this->title = _('Fleet Report');

        $this->headerData = [
            _('Host'),
            _('Image'),
            _('Last imaged'),
            _('Days'),
            _('Last check-in'),
            _('Registered'),
            _('Inventory')
        ];
        $this->attributes = [
            [], [], [], [], [], [], []
        ];

        [$start, $end] = ReportWindow::fromRequest(self::DEFAULT_WINDOW);
        $totals = FleetStats::totals($start, $end);
        $buckets = FleetStats::ageBuckets($start, $end);
        $added = FleetStats::addedPerDay($start, $end);

        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';

        echo self::renderReportWindow(
            'fleet-report',
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        // Said on the page, not just in the docblock. See the class note.
        printf(
            '<p class="text-muted">%s</p>',
            \Initiator::e(
                _(
                    'A host imaged inside this range counts as current. '
                    . 'Everything else is measured back from the end date.'
                )
            )
        );

        echo self::renderStatTiles(
            [
                ['value' => $totals['hosts'], 'label' => _('Hosts')],
                ['value' => $totals['current'], 'label' => _('Imaged in range')],
                [
                    'value' => $totals['never'],
                    'label' => _('Never imaged'),
                    'warn' => true
                ],
                [
                    'value' => $totals['noInventory'],
                    'label' => _('No inventory'),
                    'warn' => true
                ]
            ]
        );

        echo self::tabFields(
            [
                [
                    'name' => _('Currency'),
                    'id' => 'fleet-report-currency',
                    'generator' => function () use ($buckets, $added) {
                        echo '<div class="row">';
                        echo self::renderChartPanel(
                            'fleet-age',
                            _('Time since last imaged'),
                            [
                                'type' => 'doughnut',
                                'labels' => array_column($buckets, 'label'),
                                'series' => [
                                    [
                                        'label' => _('Hosts'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($buckets, 'count')
                                        )
                                    ]
                                ]
                            ],
                            5
                        );
                        echo self::renderChartPanel(
                            'fleet-added',
                            _('Hosts registered per day'),
                            [
                                'type' => 'line',
                                'labels' => array_column($added, 'date'),
                                'series' => [
                                    [
                                        'label' => _('Registered'),
                                        'data' => array_map(
                                            'intval',
                                            array_column($added, 'count')
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
                    'name' => _('Hosts'),
                    'id' => 'fleet-report-hosts',
                    'generator' => function () {
                        $this->render(12, 'fleetreport-table');
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
        $rows = FleetStats::hosts($start, $end);

        $never = _('Never');
        $data = [];
        foreach ($rows as $row) {
            $age = $row['ageDays'];
            $data[] = [
                'hostName' => (string)($row['hostName'] ?? ''),
                'imageName' => (string)($row['imageName'] ?? ''),
                // Both forms of "no date" become the same word. A blank
                // cell reads as missing data; "Never" is the answer.
                'lastDeploy' => self::_orNever($row['lastDeploy'] ?? null, $never),
                'ageDays' => null === $age ? $never : (string)(int)$age,
                'lastCheckin' => self::_orNever($row['lastCheckin'] ?? null, $never),
                'created' => (string)($row['created'] ?? ''),
                'hasInventory' => ((int)($row['hasInventory'] ?? 0)) > 0
                    ? _('Yes')
                    : _('No')
            ];
        }

        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode(['data' => $data]);
        exit;
    }
    /**
     * A stored date, or the word for not having one.
     *
     * The zero date is the trap: it is not NULL, so it survives every ??
     * and reaches the page as "0000-00-00 00:00:00" -- which sorts and
     * reads as a real date from the year zero. An upgraded server carries
     * both spellings until schema step 344 has run, so both have to be
     * recognized.
     *
     * Asked of FOGBase::validDate() rather than pattern-matched here.
     * There is one definition of what an empty date means and it is that
     * one -- tests/date-columns-nullable.test.php fails a literal written
     * anywhere else, which is how this method came to be wrong once.
     *
     * @param mixed  $value the stored value
     * @param string $never the translated word for absence
     *
     * @return string
     */
    private static function _orNever($value, $never)
    {
        return self::validDate($value) ? (string)$value : $never;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Fleet_Report', 'Fleet_Report');
