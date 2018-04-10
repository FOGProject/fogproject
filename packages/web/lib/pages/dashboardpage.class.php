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
     * The bandwidth time variable.
     *
     * @var int
     */
    private static $_bandwidthtime = 1;
    /**
     * The node urls
     *
     * @var array
     */
    private static $_nodeURLs = array();
    /**
     * The node names
     *
     * @var array
     */
    private static $_nodeNames = array();
    /**
     * The node options
     *
     * @var mixed
     */
    private static $_nodeOpts;
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
        $this->menu = array();
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
        $find = array(
            'isEnabled' => 1,
            'isGraphEnabled' => 1
        );
        Route::listem(
            'storagenode',
            'name',
            false,
            $find
        );
        $Nodes = json_decode(
            Route::getData()
        );
        $Nodes = $Nodes->storagenodes;
        foreach ((array)$Nodes as &$StorageNode) {
            if (!self::getClass('StorageNode', $StorageNode->id)->get('online')) {
                continue;
            }
            $ip = $StorageNode->ip;
            $url = sprintf(
                '%s/%s/',
                $ip,
                $StorageNode->webroot
            );
            $url = preg_replace(
                '#/+#',
                '/',
                $url
            );
            $url = self::$httpproto.'://' . $url;
            unset($ip);
            self::$_nodeOpts[] = sprintf(
                '<option value="%s" urlcall="%s">%s%s ()</option>',
                $StorageNode->id,
                sprintf(
                    '%sservice/getversion.php',
                    $url
                ),
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
            unset($StorageNode);
        }
        Route::listem('storagegroup');
        $Groups = json_decode(
            Route::getData()
        );
        $Groups = $Groups->storagegroups;
        foreach ((array)$Groups as &$StorageGroup) {
            self::$_groupOpts .= sprintf(
                '<option value="%s">%s</option>',
                $StorageGroup->id,
                $StorageGroup->name
            );
            unset($StorageGroup);
        }
        self::$_nodeOpts = implode((array)self::$_nodeOpts);
        list(
            self::$_bandwidthtime,
            self::$_tftp
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_BANDWIDTH_TIME',
                    'FOG_TFTP_HOST'
                )
            ),
            'value'
        );
    }
    /**
     * The index to display.
     *
     * @return void
     */
    public function index()
    {
        $pendingInfo = '<i></i>'
            . '&nbsp;%s&nbsp;%s <a href="?node=%s&sub=%s"><b>%s</b></a> %s';
        $hostPend = sprintf(
            $pendingInfo,
            _('Pending hosts'),
            _('Click'),
            'host',
            'pending',
            _('here'),
            _('to review.')
        );
        $macPend = sprintf(
            $pendingInfo,
            _('Pending macs'),
            _('Click'),
            'report',
            'file&f=cGVuZGluZyBtYWMgbGlzdA==',
            _('here'),
            _('to review.')
        );
        $setMesg = '';
        if (self::$pendingHosts > 0) {
            $setMesg = $hostPend;
        }
        if (self::$pendingMACs > 0) {
            if (empty($setMesg)) {
                $setMesg = $macPend;
            } else {
                $setMesg .= "<br/>$macPend";
            }
        }
        if (!empty($setMesg)) {
            self::setMessage($setMesg);
        }
        $SystemUptime = self::$FOGCore->systemUptime();
        $fields = array(
            _('Username') => self::$FOGUser->get('name'),
            _('Web Server') => filter_input(
                INPUT_SERVER,
                'SERVER_ADDR'
            ),
            _('Load Average') => $SystemUptime['load'],
            _('System Uptime') => $SystemUptime['uptime']
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8')
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'DashboardData',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );
        // Dashboard boxes row.
        echo '<div class="row">';
        // Overview
        echo '<div class="col-md-4">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('System Overview');
        echo '</h4>';
        echo '<p class="category">';
        echo _('Server information at a glance.');
        echo '</p>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->render();
        echo '</div>';
        echo '</div>';
        unset(
            $this->data,
            $this->templates,
            $this->attributes,
            $fields,
            $SystemUptime,
            $tftp
        );
        echo '</div>';
        // Activity
        echo '<div class="col-md-4">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Storage Group Activity');
        echo '</h4>';
        echo '<p class="category">';
        echo _('Selected groups\'s current activity');
        echo '</p>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div class="graph pie-graph fogdashbox" id="graph-activity"></div>';
        echo '<div class="graph-selectors" id="graph-activity-selector">';
        printf(
            '<select name="groupsel">%s</select>',
            self::$_groupOpts
        );
        echo '<div id="ActivityActive"></div>';
        echo '<div id="ActivityQueued"></div>';
        echo '<div id="ActivitySlots"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Disk usage
        echo '<div class="col-md-4">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Storage Node Disk Usage');
        echo '</h4>';
        echo '<p class="category">';
        echo _('Selected node\'s disk usage');
        echo '</p>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<a href="?node=hwinfo">';
        echo '<div class="graph pie-graph fogdashbox" id="graph-diskusage"></div>';
        echo '</a>';
        echo '<div class="graph-selectors" id="diskusage-selector">';
        printf(
            '<select name="nodesel">%s</select>',
            self::$_nodeOpts
        );
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // 30 day row.
        echo '<div class="row">';
        echo '<div class="col-xs-12">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading">';
        echo '<div class="row text-center">';
        echo '<h4 class="title">';
        echo _('Imaging Over the last 30 days');
        echo '</h4>';
        echo '</div>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div id="graph-30day" class="graph fogdashbox"></div>';
        echo '<div class="fog-variable" id="Graph30dayData"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        $datapointshour = 3600;
        $datapointshalf = 1800;
        $datapointsten = 600;
        $datapointstwo = 120;
        // 30 day row.
        echo '<div class="row">';
        echo '<div class="col-xs-12">';
        printf(
            '<input type="hidden" id="bandwidthUrls" type="hidden" value="%s"/>'
            . '<input type="hidden" id="nodeNames" type="hidden" value="%s"/>',
            implode(',', self::$_nodeURLs),
            implode(',', self::$_nodeNames)
        );
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading">';
        echo '<h4 class="title">';
        echo self::$foglang['Bandwidth'];
        echo '</h4>';
        echo '<div id="graph-bandwidth-filters-type">';
        echo '<div class="col-xs-2">';
        echo '<p class="category" id="graph-bandwidth-title">';
        echo self::$foglang['Bandwidth'];
        echo ' - ';
        echo '<span>';
        echo self::$foglang['Transmit'];
        echo '</span>';
        echo '</p>';
        echo '</div>';
        echo '<div class="col-xs-2">';
        echo '<a href="#" id="graph-bandwidth-filters-transmit" '
            . 'class="type-filters graph-filters active">';
        echo self::$foglang['Transmit'];
        echo '</a>';
        echo '<a href="#" id="graph-bandwidth-filters-receive" class='
            . '"type-filters graph-filters">';
        echo self::$foglang['Receive'];
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '<div class="row">';
        echo '<div id="graph-bandwidth-filters-time">';
        echo '<div class="col-xs-2">';
        echo '<p class="category" id="graph-bandwidth-time">';
        echo _('Time');
        echo ' - ';
        echo '<span>';
        echo _('2 Minutes');
        echo '</span>';
        echo '</p>';
        echo '</div>';
        echo '<div class="col-xs-4">';
        echo '<a href="#" rel="'
            . $datapointstwo
            . '" class="time-filters graph-filters active">';
        echo _('2 Minutes');
        echo '</a>';
        echo '<a href="#" rel="'
            . $datapointsten
            . '" class="time-filters graph-filters">';
        echo _('10 Minutes');
        echo '</a>';
        echo '<a href="#" rel="'
            . $datapointshalf
            . '" class="time-filters graph-filters">';
        echo _('30 Minutes');
        echo '</a>';
        echo '<a href="#" rel="'
            . $datapointshour
            . '" class="time-filters graph-filters">';
        echo _('1 Hour');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div id="graph-bandwidth" class="graph fogdashbox"></div>';
        echo '</div>';
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
        if ($error) {
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
        $url = sprintf(
            '%s://%s/fog/status/freespace.php?path=%s',
            self::$httpproto,
            $this->obj->get('ip'),
            base64_encode($this->obj->get('path'))
        );
        if (!$this->obj->get('online')) {
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
        if ($data->error) {
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
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        $start = self::niceDate()
            ->setTime(00, 00, 00)
            ->modify('-30 days');
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
            $count = self::getClass('ImagingLogManager')
                ->count(
                    [
                        'start' => $date->format('Y-m-d%'),
                        'finish' => $date->format('Y-m-d%')
                    ],
                    'OR'
                );
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
                'dev' => $d->dev,
                'name' => $names[$i],
                'rx' => $d->rx,
                'tx' => $d->tx
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
        $tests = self::$FOGURLRequests->isAvailable($testurls, 1);
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
