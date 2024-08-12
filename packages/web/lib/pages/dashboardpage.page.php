<?php
/**
 * Presents the home/dashboard page.
 *
 * PHP version 5
 *
 * @category DashboardPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents the home/dashboard page.
 *
 * @category DashboardPage
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DashboardPage extends FOGPage
{
    /**
     * The tftp variable.
     *
     * @var string
     */
    private static $_tftp = '';
    /**
     * The node urls
     *
     * @var array
     */
    private static $_nodeURLs = [];
    /**
     * The node names
     *
     * @var array
     */
    private static $_nodeNames = [];
    /**
     * The node options
     *
     * @var mixed
     */
    private static $_nodeOpts;
    /**
     * The node colors
     *
     * @var mixed
     */
    private static $_nodeColors;
    /**
     * The group options
     *
     * @var string
     */
    private static $_groupOpts;
    /**
     * The node to display page for.
     *
     * @var string
     */
    public $node = 'home';
    /**
     * Initialize the dashboard page
     *
     * @param string $name the name to initialize with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = self::$foglang['Dashboard'];
        parent::__construct($this->name);
        global $sub;
        global $id;
        $objName = 'StorageNode';
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
        Route::listem('storagenode');
        $Nodes = json_decode(
            Route::getData()
        );
        foreach ($Nodes->data as &$StorageNode) {
            if (!($StorageNode->isEnabled && $StorageNode->isGraphEnabled)) {
                continue;
            }
            $ip = $StorageNode->ip;
            $url = $ip . '/fog/';
            $url = preg_replace(
                '#/+#',
                '/',
                $url
            );
            $url = self::$httpproto.'://' . $url;
            self::$_nodeOpts[] = sprintf(
                '<option value="%s">%s%s</option>',
                $StorageNode->id,
                $StorageNode->name,
                (
                    $StorageNode->isMaster ?
                    ' *' :
                    ''
                )
            );
            self::$_nodeNames[] = $StorageNode->name;
            self::$_nodeURLs[] = sprintf(
                '%sstatus/bandwidth.php?dev=%s',
                $url,
                $StorageNode->interface
            );
            self::$_nodeColors[] = $StorageNode->graphcolor;
            unset($StorageNode);
        }
        Route::listem('storagegroup');
        $Groups = json_decode(
            Route::getData()
        );
        $Groups = $Groups->data;
        foreach ((array)$Groups as &$StorageGroup) {
            self::$_groupOpts .= sprintf(
                '<option value="%s">%s</option>',
                $StorageGroup->id,
                $StorageGroup->name
            );
            unset($StorageGroup);
        }
        self::$_nodeOpts = implode((array)self::$_nodeOpts);
        self::$_tftp = self::getSetting('FOG_TFTP_HOST');
    }
    /**
     * The index to display.
     *
     * @return void
     */
    public function index(...$args)
    {
        Route::count(
            'host',
            ['pending' => 1]
        );
        $pendingHosts = json_decode(Route::getData());
        $pendingHosts = $pendingHosts->total;
        if (DatabaseManager::getColumns('hostMAC', 'hmMAC')) {
            Route::count(
                'macaddressassociation',
                ['pending' => 1]
            );
            $pendingMACs = json_decode(Route::getData());
            $pendingMACs = $pendingMACs->total;
        }
        $pendingInfo = '%s <a href="?node=%s&sub=%s"><b>%s</b></a> %s';
        $hostPend = sprintf(
            $pendingInfo,
            _('Click'),
            'host',
            'pending',
            _('here'),
            _('to review.')
        );
        $macPend = sprintf(
            $pendingInfo,
            _('Click'),
            'host',
            'pendingMacs',
            _('here'),
            _('to review.')
        );
        $setMesg = '';
        if ($pendingHosts > 0) {
            $title = $pendingHosts
                . ' '
                . (
                    $pendingHosts != 1 ?
                    _('Pending hosts') :
                    _('Pending host')
                );
            self::displayAlert($title, $hostPend, 'warning', true, true);
        }
        if ($pendingMACs > 0) {
            $title = $pendingMACs . ' ' . _('Pending macs');
            self::displayAlert($title, $macPend, 'warning', true, true);
        }
        $SystemUptime = self::$FOGCore->systemUptime();
        $fields = [
            _('Web Server') => filter_input(
                INPUT_SERVER,
                'SERVER_ADDR'
            ),
            _('Load Average') => $SystemUptime['load'],
            _('System Uptime') => $SystemUptime['uptime']
        ];
        $fields = (array)$fields;
        self::$HookManager
            ->processEvent(
                'DASHBOARD_SYSTEM_FIELDS',
                ['fields' => &$fields]
            );

        echo '<div class="box-group">';
        echo '<!-- FOG Overview Boxes -->';
        // Server info basic.
        echo '<div class="col-md-4">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('System Overview');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div class="dl-horizontal">';
        foreach ($fields as $field => &$input) {
            echo '<dt>' . $field . '</dt>'
                . '<dd>' . $input . '</dd>';
            unset($input);
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Group Activity
        echo '<div class="col-md-4">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Storage Group Activity');
        echo '</h4>';
        echo '<div class="graph-selectors pull-right" id="graph-activity-selector">';
        printf(
            '<select class="activity-count" name="groupsel">%s</select>',
            self::$_groupOpts
        );
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
        unset(
            $fields,
            $SystemUptime,
            $tftp
        );
        // Storage Usage
        echo '<div class="col-md-4">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Storage Node Disk Usage');
        echo '</h4>';
        echo '<div class="graph-selectors pull-right" id="diskusage-selector">';
        printf(
            '<select name="nodesel" class="nodeid">%s</select>',
            self::$_nodeOpts
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<a href="?node=hwinfo" id="hwinfolink">';
        echo '<div id="graph-diskusage"></div>';
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        unset(
            $fields,
            $SystemUptime,
            $tftp
        );
        // 30 day row.
        $onemonth = 30;
        $twomonth = 60;
        $tremonth = 90;
        $sixmonth = 183;
        $oneyears = 365;
        echo '<div class="col-xs-12">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Imaging Over the last');
        echo '</h4>';
        echo '<div class="row">';
        echo '<div class="col-md-3">';
        echo '<a href="#" id="graph-day-filters-30" '
            . 'class="type-days graph-days active" rel="'
            . $onemonth
            . '">';
        echo _('30 Days');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-day-filters-60" class='
            . '"type-days graph-days" rel="'
            . $twomonth
            . '">';
        echo _('60 Days');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-day-filters-90" class='
            . '"type-days graph-days" rel="'
            . $tremonth
            . '">';
        echo _('90 Days');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-day-filters-90" class='
            . '"type-days graph-days" rel="'
            . $sixmonth
            . '">';
        echo _('6 Months');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-day-filters-90" class='
            . '"type-days graph-days" rel="'
            . $oneyears
            . '">';
        echo _('1 Year');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div id="graph-30day"></div>';
        echo '<div class="fog-variable" id="Graph30dayData"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Bandwidth display
        $relhour = 3600;
        $rel30 = 1800;
        $rel10 = 600;
        $rel5 = 300;
        $rel2 = 120;
        echo '<div class="col-xs-12">';
        echo self::makeInput(
            '',
            '',
            '',
            'hidden',
            'bandwidthUrls',
            implode(',', self::$_nodeURLs)
        );
        echo self::makeInput(
            '',
            '',
            '',
            'hidden',
            'nodeNames',
            implode(',', self::$_nodeNames)
        );
        echo self::makeInput(
            '',
            '',
            '',
            'hidden',
            'nodeColors',
            implode(',', (array)self::$_nodeColors)
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo self::$foglang['Bandwidth'];
        echo '</h4>';
        echo '<div class="box-tools" pull-right>';
        echo _('Real Time');
        echo '<div class="btn-group" id="realtime" data-toggle="btn-toggle">';
        echo self::makeButton(
            'btn-on',
            _('On'),
            'btn btn-default btn-xs active',
            ' data-toggle="on"'
        );
        echo self::makeButton(
            'btn-off',
            _('Off'),
            'btn btn-default btn-xs',
            ' data-toggle="off"'
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="row">';
        echo '<div id="graph-bandwidth-filters-type">';
        echo '<div class="col-md-2">';
        echo '<div id="graph-bandwidth-title">';
        echo self::$foglang['Bandwidth'];
        echo ' - ';
        echo '<span>';
        echo self::$foglang['Transmit'];
        echo '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div id="graph-bandwidth-filters-time"></div>';
        echo '<div class="col-md-offset-4 col-md-6">';
        echo '<div class="category" id="graph-bandwidth-time-title">';
        echo _('Time');
        echo ' - ';
        echo '<span>';
        echo _('2 Minutes');
        echo '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="row">';
        echo '<div class="col-md-2">';
        echo '<a href="#" id="graph-bandwidth-filters-transmit" '
            . 'class="type-filters graph-filters active">';
        echo self::$foglang['Transmit'];
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-filters-receive" class='
            . '"type-filters graph-filters">';
        echo self::$foglang['Receive'];
        echo '</a>';
        echo '</div>';
        echo '<div class="col-md-offset-4 col-md-6">';
        echo '<a href="#" id="graph-bandwidth-time-filters-2min" '
            . 'class="time-filters graph-filters active" rel="' . $rel2 . '">';
        echo _('2 Minutes');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-time-filters-5min" '
            . 'class="time-filters graph-filters" rel="' . $rel5 . '">';
        echo _('5 Minutes');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-time-filters-10min" '
            . 'class="time-filters graph-filters" rel="' . $rel10 . '">';
        echo _('10 Minutes');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-time-filters-30min" '
            . 'class="time-filters graph-filters" rel="' . $rel30 . '">';
        echo _('30 Minutes');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-time-filters-1hr" '
            . 'class="time-filters graph-filters" rel="' . $relhour . '">';
        echo _('1 Hour');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div id="graph-bandwidth"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    /**
     * Gets the client count active/used/queued
     *
     * @return void
     */
    public function clientcount()
    {
        header('Content-type: application/json');
        $ActivityActive = $ActivityQueued = $ActivityTotalClients = 0;
        $ActivityTotalClients = $this->obj->getTotalAvailableSlots();
        $ActivityQueued = $this->obj->getQueuedSlots();
        $ActivityActive = $this->obj->getUsedSlots();
        if (!$ActivityActive && !$ActivityTotalClients && !$ActivityQueued) {
            $error = _('No activity information available for this group');
        }
        $data = [
            '_labels' => [
                _('Free'),
                _('Queued'),
                _('Active')
            ],
            'ActivityActive' => &$ActivityActive,
            'ActivityQueued' => &$ActivityQueued,
            'ActivitySlots' => &$ActivityTotalClients
        ];
        if (isset($error) && $error) {
            $data['error'] = $error;
            $data['title'] = _('No Data Available');
        }
        unset(
            $ActivityActive,
            $ActivityQueued,
            $ActivityTotalClients
        );
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode($data);
        unset($data);
        exit;
    }
    /**
     * Gets the disk usage of the selected node.
     *
     * @return void
     */
    public function diskusage()
    {
        header('Content-type: application/json');
        $url = sprintf(
            '%s://%s/fog/status/freespace.php?path=%s',
            self::$httpproto,
            $this->obj->get('ip'),
            base64_encode($this->obj->get('path'))
        );
        if (!$this->obj->get('online')) {
            http_response_code(HTTPResponseCodes::HTTP_BAD_REQUEST);
            echo json_encode(
                [
                    '_labels' => [
                        _('Free'),
                        _('used')
                    ],
                    'free' => 0,
                    'used' => 0,
                    'error' => _('Node is unavailable'),
                    'title' => _('Node Offline')
                ]
            );
            exit;
        }
        $data = self::$FOGURLRequests
            ->process($url);
        $data = json_decode(
            array_shift($data)
        );
        $datatmp = [
            '_labels' => [
                _('Free'),
                _('Used')
            ],
            'free' => $data->free,
            'used' => $data->used
        ];
        if (isset($data->error) && $data->error) {
            $datatmp['error'] = $data->error;
            $datatmp['title'] = $data->title;
        }
        unset($url);
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode($datatmp);
        unset($data);
        exit;
    }
    /**
     * Gets the 30 day graph.
     *
     * @return void
     */
    public function get30day()
    {
        header('Content-type: application/json');
        $days = filter_input(INPUT_POST, 'days');
        $start = self::niceDate()
            ->setTime(00, 00, 00)
            ->modify("-$days days");
        $end = self::niceDate()
            ->setTime(23, 59, 59);
        $int = new DateInterval('P1D');
        $period = new DatePeriod($start, $int, $end);
        $dates = iterator_to_array($period);
        unset(
            $start,
            $end,
            $int,
            $period
        );
        foreach ((array)$dates as $index => &$date) {
            Route::count(
                'imaginglog',
                [
                    'start' => $date->format('Y-m-d%'),
                    'finish' => $date->format('Y-m-d%')
                ],
                false,
                'OR'
            );
            $count = json_decode(Route::getData());
            $count = $count->total;
            $data[] = [
                ($date->getTimestamp() * 1000),
                $count
            ];
            unset($date);
        }
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode($data);
        exit;
    }
    /**
     * Gets the bandwidth of the nodes
     *
     * @return void
     */
    public function bandwidth()
    {
        header('Content-type: application/json');
        $sent = filter_input(
            INPUT_POST,
            'url',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );
        $names = filter_input(
            INPUT_POST,
            'names',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );
        $urls = [];
        foreach ((array)$sent as &$url) {
            $urls[] = $url;
            unset($url);
        }
        $urls = array_values(
            array_filter($urls)
        );
        $datas = self::$FOGURLRequests->process($urls);
        $dataSet = [];
        foreach ((array)$datas as $i => &$data) {
            $d = json_decode($data);
            $data = [
                'dev' => property_exists($d, 'dev') ? $d->dev : '',
                'name' => $names[$i],
                'rx' => property_exists($d, 'rx') ? $d->rx : 0,
                'tx' => property_exists($d, 'tx') ? $d->tx : 0
            ];
            $dataSet[] = $data;
            unset($data, $d);
        }
        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode($dataSet);
        exit;
    }
    /**
     * Test if the urls are available.
     *
     * @return array
     */
    public function testUrls()
    {
        header('Content-type: application/json');
        $sent = filter_input(
            INPUT_POST,
            'url',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );
        $names = filter_input(
            INPUT_POST,
            'names',
            FILTER_DEFAULT,
            FILTER_REQUIRE_ARRAY
        );
        $testurls = [];
        foreach ((array)$sent as &$url) {
            $testurls[] = parse_url($url, PHP_URL_HOST);
            unset($url);
        }
        $tests = self::$FOGURLRequests->isAvailable($testurls, 1, 21, 'tcp');
        unset($testurls);
        foreach ($tests as $index => &$test) {
            if (!$test) {
                unset(
                    $sent[$index],
                    $names[$index]
                );
            }
            unset($test);
        }
        $names = array_values(
            array_filter($names)
        );

        $sent = array_values(
            array_filter($sent)
        );

        http_response_code(HTTPResponseCodes::HTTP_SUCCESS);
        echo json_encode(
            [
                'names' => $names,
                'urls' => $sent
            ]
        );
        exit;
    }
}
