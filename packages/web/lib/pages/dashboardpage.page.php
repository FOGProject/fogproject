<?php
declare(strict_types=1);

/**
 * Presents the home/dashboard page.
 *
 * PHP version 7.4+
 *
 * @category DashboardPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 * @version  1.1
 */

class DashboardPage extends FOGPage
{
    /**
     * The TFTP host.
     *
     * @var string
     */
    private $tftp = '';

    /**
     * The node URLs.
     *
     * @var array
     */
    private $nodeURLs = [];

    /**
     * The node names.
     *
     * @var array
     */
    private $nodeNames = [];

    /**
     * The node options for the dropdown.
     *
     * @var array
     */
    private $nodeOpts = [];

    /**
     * The node colors for graph representation.
     *
     * @var array
     */
    private $nodeColors = [];

    /**
     * The group options for the dropdown.
     *
     * @var string
     */
    private $groupOpts = '';

    /**
     * The node to display the page for.
     *
     * @var string
     */
    public $node = 'home';

    /**
     * Initialize the dashboard page.
     *
     * @param string $name The name to initialize with.
     */
    public function __construct(string $name = '')
    {
        $this->name = self::$foglang['Dashboard'];
        parent::__construct($this->name);

        global $sub, $id;

        switch ($sub) {
            case 'clientcount':
                $this->obj = new StorageGroup($id);
                break;
            case 'diskusage':
                $this->obj = new StorageNode($id);
                break;
            default:
                $this->obj = new StorageNode();
        }

        if (self::$ajax) {
            return;
        }

        $this->initializeNodes();
        $this->initializeGroups();
        $this->tftp = self::getSetting('FOG_TFTP_HOST');
    }

    /**
     * Initialize storage nodes and their properties.
     *
     * @return void
     */
    private function initializeNodes(): void
    {
        Route::listem('storagenode');
        $Nodes = json_decode(Route::getData());

        foreach ($Nodes->data as $StorageNode) {
            if (!($StorageNode->isEnabled && $StorageNode->isGraphEnabled)) {
                continue;
            }

            $url = rtrim($StorageNode->ip, '/') . '/fog/';
            $url = self::$httpproto . '://' . preg_replace('#/+#', '/', $url);

            $this->nodeOpts[] = sprintf(
                '<option value="%s">%s%s</option>',
                $StorageNode->id,
                $StorageNode->name,
                $StorageNode->isMaster ? ' *' : ''
            );
            $this->nodeNames[] = $StorageNode->name;
            $this->nodeURLs[] = sprintf('%sstatus/bandwidth.php?dev=%s', $url, $StorageNode->interface);
            $this->nodeColors[] = $StorageNode->graphcolor;
        }

        $this->nodeOpts = implode('', $this->nodeOpts);
    }

    /**
     * Initialize storage groups and their properties.
     *
     * @return void
     */
    private function initializeGroups(): void
    {
        Route::listem('storagegroup');
        $Groups = json_decode(Route::getData())->data;

        foreach ((array)$Groups as $StorageGroup) {
            $this->groupOpts .= sprintf(
                '<option value="%s">%s</option>',
                $StorageGroup->id,
                $StorageGroup->name
            );
        }
    }

    /**
     * The index to display.
     *
     * @param mixed ...$args
     * @return void
     */
    public function index(...$args): void
    {
        $pendingHosts = $this->getPendingHostsCount();
        $pendingMACs = $this->getPendingMACsCount();

        $this->displayPendingAlerts($pendingHosts, $pendingMACs);
        $this->displaySystemOverview();
        $this->displayStorageGroupActivity();
        $this->displayStorageNodeDiskUsage();
        $this->displayImagingStats();
        $this->displayBandwidthUsage();
    }

    /**
     * Get the count of pending hosts.
     *
     * @return int
     */
    private function getPendingHostsCount(): int
    {
        Route::count('host', ['pending' => 1]);
        $data = json_decode(Route::getData());
        return $data->total ?? 0;
    }

    /**
     * Get the count of pending MAC addresses.
     *
     * @return int
     */
    private function getPendingMACsCount(): int
    {
        if (DatabaseManager::getColumns('hostMAC', 'hmMAC')) {
            Route::count('macaddressassociation', ['pending' => 1]);
            $data = json_decode(Route::getData());
            return $data->total ?? 0;
        }
        return 0;
    }

    /**
     * Display alerts for pending hosts and MAC addresses.
     *
     * @param int $pendingHosts
     * @param int $pendingMACs
     * @return void
     */
    private function displayPendingAlerts(int $pendingHosts, int $pendingMACs): void
    {
        if ($pendingHosts > 0) {
            $title = $pendingHosts . ' ' . ($pendingHosts !== 1 ? _('Pending hosts') : _('Pending host'));
            $message = sprintf('%s <a href="?node=host&sub=pending"><b>%s</b></a> %s', _('Click'), _('here'), _('to review.'));
            self::displayAlert($title, $message, 'warning', true, true);
        }

        if ($pendingMACs > 0) {
            $title = $pendingMACs . ' ' . _('Pending macs');
            $message = sprintf('%s <a href="?node=host&sub=pendingMacs"><b>%s</b></a> %s', _('Click'), _('here'), _('to review.'));
            self::displayAlert($title, $message, 'warning', true, true);
        }
    }

    /**
     * Display the system overview section.
     *
     * @return void
     */
    private function displaySystemOverview(): void
    {
        $systemUptime = self::$FOGCore->systemUptime();
        $fields = [
            _('Web Server') => filter_input(INPUT_SERVER, 'SERVER_ADDR', FILTER_SANITIZE_STRING),
            _('Load Average') => $systemUptime['load'],
            _('System Uptime') => $systemUptime['uptime'],
        ];

        self::$HookManager->processEvent('DASHBOARD_SYSTEM_FIELDS', ['fields' => &$fields]);

        echo '<div class="box-group">';
        echo '<div class="col-md-4">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">' . _('System Overview') . '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div class="dl-horizontal">';
        foreach ($fields as $field => $value) {
            echo '<dt>' . $field . '</dt><dd>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</dd>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display the storage group activity section.
     *
     * @return void
     */
    private function displayStorageGroupActivity(): void
    {
        echo '<div class="col-md-4">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">' . _('Storage Group Activity') . '</h4>';
        echo '<div class="graph-selectors pull-right" id="graph-activity-selector">';
        printf('<select class="activity-count" name="groupsel">%s</select>', $this->groupOpts);
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div id="graph-activity"></div>';
        echo '<div id="ActivityActive"></div>';
        echo '<div id="ActivityQueued"></div>';
        echo '<div id="ActivitySlots"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display the storage node disk usage section.
     *
     * @return void
     */
    private function displayStorageNodeDiskUsage(): void
    {
        echo '<div class="col-md-4">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">' . _('Storage Node Disk Usage') . '</h4>';
        echo '<div class="graph-selectors pull-right" id="diskusage-selector">';
        printf('<select name="nodesel" class="nodeid">%s</select>', $this->nodeOpts);
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<a href="?node=hwinfo" id="hwinfolink"><div id="graph-diskusage"></div></a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display imaging statistics over different time periods.
     *
     * @return void
     */
    private function displayImagingStats(): void
    {
        $timePeriods = [
            30 => _('30 Days'),
            60 => _('60 Days'),
            90 => _('90 Days'),
            183 => _('6 Months'),
            365 => _('1 Year'),
        ];

        echo '<div class="col-xs-12">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">' . _('Imaging Over the Last') . '</h4>';
        echo '<div class="row"><div class="col-md-3">';
        foreach ($timePeriods as $period => $label) {
            echo '<a href="#" class="type-days graph-days' . ($period == 30 ? ' active' : '') . '" rel="' . $period . '">' . $label . '</a>&nbsp;&nbsp;';
        }
        echo '</div></div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div id="graph-30day"></div>';
        echo '<div class="fog-variable" id="Graph30dayData"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display bandwidth usage section.
     *
     * @return void
     */
    private function displayBandwidthUsage(): void
    {
        $timeFilters = [
            120 => _('2 Minutes'),
            300 => _('5 Minutes'),
            600 => _('10 Minutes'),
            1800 => _('30 Minutes'),
            3600 => _('1 Hour'),
        ];

        echo '<div class="col-xs-12">';
        echo self::makeInput('', '', '', 'hidden', 'bandwidthUrls', implode(',', $this->nodeURLs));
        echo self::makeInput('', '', '', 'hidden', 'nodeNames', implode(',', $this->nodeNames));
        echo self::makeInput('', '', '', 'hidden', 'nodeColors', implode(',', (array)$this->nodeColors));
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">' . self::$foglang['Bandwidth'] . '</h4>';
        echo '<div class="box-tools pull-right">' . _('Real Time') . '<div class="btn-group" id="realtime" data-toggle="btn-toggle">';
        echo self::makeButton('btn-on', _('On'), 'btn btn-default btn-xs active', ' data-toggle="on"');
        echo self::makeButton('btn-off', _('Off'), 'btn btn-default btn-xs', ' data-toggle="off"');
        echo '</div></div>';
        echo '<div class="row"><div class="col-md-2">';
        echo '<div id="graph-bandwidth-title">' . self::$foglang['Bandwidth'] . ' - <span>' . self::$foglang['Transmit'] . '</span></div>';
        echo '</div>';
        echo '<div class="col-md-offset-4 col-md-6">';
        echo '<div class="category" id="graph-bandwidth-time-title">' . _('Time') . ' - <span>' . _('2 Minutes') . '</span></div>';
        echo '</div></div>';
        echo '</div>';
        echo '<div class="box-body"><div id="graph-bandwidth"></div></div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Gets the client count (active/used/queued).
     *
     * @return void
     */
    public function clientCount(): void
    {
        $this->sendJsonResponse(function () {
            $activityActive = $this->obj->getUsedSlots();
            $activityQueued = $this->obj->getQueuedSlots();
            $activityTotalClients = $this->obj->getTotalAvailableSlots();

            $data = [
                '_labels' => [_('Free'), _('Queued'), _('Active')],
                'ActivityActive' => $activityActive,
                'ActivityQueued' => $activityQueued,
                'ActivitySlots' => $activityTotalClients
            ];

            if (!$activityActive && !$activityTotalClients && !$activityQueued) {
                $data['error'] = _('No activity information available for this group');
                $data['title'] = _('No Data Available');
            }

            return $data;
        });
    }

    /**
     * Gets the disk usage of the selected node.
     *
     * @return void
     */
    public function diskUsage(): void
    {
        $this->sendJsonResponse(function () {
            if (!$this->obj->get('online')) {
                return [
                    '_labels' => [_('Free'), _('Used')],
                    'free' => 0,
                    'used' => 0,
                    'error' => _('Node is unavailable'),
                    'title' => _('Node Offline')
                ];
            }

            $url = sprintf(
                '%s://%s/fog/status/freespace.php?path=%s',
                self::$httpproto,
                $this->obj->get('ip'),
                base64_encode($this->obj->get('path'))
            );

            $data = self::$FOGURLRequests->process($url);
            $data = json_decode(array_shift($data));

            $datatmp = [
                '_labels' => [_('Free'), _('Used')],
                'free' => $data->free ?? 0,
                'used' => $data->used ?? 0,
            ];

            if (isset($data->error) && $data->error) {
                $datatmp['error'] = $data->error;
                $datatmp['title'] = $data->title;
            }

            return $datatmp;
        });
    }

    /**
     * Gets the 30-day graph data.
     *
     * @return void
     */
    public function get30Day(): void
    {
        $this->sendJsonResponse(function () {
            $days = (int)filter_input(INPUT_POST, 'days', FILTER_VALIDATE_INT);

            $start = self::niceDate()->setTime(0, 0, 0)->modify("-$days days");
            $end = self::niceDate()->setTime(23, 59, 59);
            $int = new DateInterval('P1D');
            $period = new DatePeriod($start, $int, $end);

            $data = [];
            foreach ($period as $date) {
                Route::count(
                    'imaginglog',
                    ['start' => $date->format('Y-m-d%'), 'finish' => $date->format('Y-m-d%')],
                    false,
                    'OR'
                );
                $count = json_decode(Route::getData())->total ?? 0;
                $data[] = [$date->getTimestamp() * 1000, $count];
            }

            return $data;
        });
    }

    /**
     * Gets the bandwidth of the nodes.
     *
     * @return void
     */
    public function bandwidth(): void
    {
        $this->sendJsonResponse(function () {
            $sent = filter_input(INPUT_POST, 'url', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];
            $names = filter_input(INPUT_POST, 'names', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];

            $urls = array_values(array_filter($sent));
            $datas = self::$FOGURLRequests->process($urls);
            $dataSet = [];

            foreach ($datas as $i => $data) {
                $decodedData = json_decode($data);
                $dataSet[] = [
                    'dev' => $decodedData->dev ?? '',
                    'name' => $names[$i] ?? '',
                    'rx' => $decodedData->rx ?? 0,
                    'tx' => $decodedData->tx ?? 0,
                ];
            }

            return $dataSet;
        });
    }

    /**
     * Test if the URLs are available.
     *
     * @return void
     */
    public function testUrls(): void
    {
        $this->sendJsonResponse(function () {
            $sent = filter_input(INPUT_POST, 'url', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];
            $names = filter_input(INPUT_POST, 'names', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];

            $testurls = array_map(static fn ($url) => parse_url($url, PHP_URL_HOST), $sent);
            $tests = self::$FOGURLRequests->isAvailable($testurls, 1, 21, 'tcp');

            foreach ($tests as $index => $test) {
                if (!$test) {
                    unset($sent[$index], $names[$index]);
                }
            }

            return [
                'names' => array_values(array_filter($names)),
                'urls' => array_values(array_filter($sent)),
            ];
        });
    }

    /**
     * Utility function to send a JSON response with proper headers.
     *
     * @param callable $callback A callback function to generate the response data.
     * @return void
     */
    private function sendJsonResponse(callable $callback): void
    {
        ob_start();
        header('Content-Type: application/json');

        try {
            $data = $callback();
            http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        } catch (Exception $e) {
            $data = ['error' => $e->getMessage()];
            http_response_code(HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR);
        }

        echo json_encode($data);
        ob_end_flush();
        exit;
    }
}
