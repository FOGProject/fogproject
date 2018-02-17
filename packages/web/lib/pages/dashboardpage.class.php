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
        $Nodes = $Nodes->data;
        foreach ((array)$Nodes as &$StorageNode) {
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
            $testurls[] = $ip;
            unset($ip);
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
            'report',
            'file&f=cGVuZGluZyBtYWMgbGlzdA==',
            _('here'),
            _('to review.')
        );
        $setMesg = '';
        if (self::$pendingHosts > 0) {
            $title = self::$pendingHosts . ' ' . _('Pending hosts');
            self::displayAlert($title, $hostPend, 'warning', true, true);
        }
        if (self::$pendingMACs > 0) {
            $title = self::$pendingMACs . ' ' . _('Pending macs');
            self::displayAlert($title, $macPend, 'warning', true, true);
        }
        $SystemUptime = self::$FOGCore->systemUptime();
        $fields = array(
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
        $fields = (array)$fields;
        self::$HookManager
            ->processEvent(
                'DashboardData',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes' => &$this->attributes
                )
            );

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
            $this->data,
            $this->templates,
            $this->attributes,
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
        echo '<a href="?node=hwinfo">';
        echo '<div id="graph-diskusage"></div>';
        echo '</a>';
        echo '</div>';
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
        // 30 day row.
        echo '<div class="col-xs-12">';
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Imaging Over the last 30 days');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo '<div id="graph-30day"></div>';
        echo '<div class="fog-variable" id="Graph30dayData"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Bandwidth display
        $bandwidthtime = self::$_bandwidthtime;
        $datapointshour = (3600 / $bandwidthtime);
        $bandwidthtime *= 1000;
        $datapointshalf = ($datapointshour / 2);
        $datapointsten = ($datapointshour / 6);
        $datapointstwo = ($datapointshour / 30);
        echo '<div class="col-xs-12">';
        printf(
            '<input type="hidden" id="bandwidthtime" value="%d"/>'
            . '<input type="hidden" id="bandwidthUrls" type="hidden" value="%s"/>'
            . '<input type="hidden" id="nodeNames" type="hidden" value="%s"/>',
            $bandwidthtime,
            implode(',', self::$_nodeURLs),
            implode(',', self::$_nodeNames)
        );
        echo '<div class="box box-primary">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo self::$foglang['Bandwidth'];
        echo '</h4>';
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
        $relhour = (3600 / self::$_bandwidthtime);
        $relhalf = ($relhour / 2);
        $rel10 = ($relhour / 6);
        $rel5 = ($relhour / 12);
        $rel2 = ($relhour / 30);
        echo '<a href="#" id="graph-bandwidth-time-filters-2min" '
            . 'class="time-filters graph-filters active" rel="' . $rel2 . '">';
        echo _('2 Minutes');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-time-filters-5min" '
            . 'class="time-filters graph-filters active" rel="' . $rel5 . '">';
        echo _('5 Minutes');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-time-filters-10min" '
            . 'class="time-filters graph-filters active" rel="' . $rel10 . '">';
        echo _('10 Minutes');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-time-filters-30min" '
            . 'class="time-filters graph-filters active" rel="' . $relhalf . '">';
        echo _('30 Minutes');
        echo '</a>';
        echo '&nbsp;&nbsp;';
        echo '<a href="#" id="graph-bandwidth-time-filters-1hr" '
            . 'class="time-filters graph-filters active" rel="' . $relhour . '">';
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
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        $ActivityActive = $ActivityQueued = $ActivityTotalClients = 0;
        $ActivityTotalClients = $this->obj->getTotalAvailableSlots();
        $ActivityQueued = $this->obj->getQueuedSlots();
        $ActivityActive = $this->obj->getUsedSlots();
        $data = array(
            'ActivityActive' => &$ActivityActive,
            'ActivityQueued' => &$ActivityQueued,
            'ActivitySlots' => &$ActivityTotalClients
        );
        unset(
            $ActivityActive,
            $ActivityQueued,
            $ActivityTotalClients
        );
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
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        $url = sprintf(
            '%s://%s/fog/status/freespace.php?path=%s',
            self::$httpproto,
            $this->obj->get('ip'),
            base64_encode($this->obj->get('path'))
        );
        $data = self::$FOGURLRequests
            ->process($url);
        $data = json_decode(
            array_shift($data),
            true
        );
        $data = array(
            'free' => $data['free'],
            'used' => $data['used']
        );
        unset($url);
        echo json_encode((array)$data);
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
                    array(
                        'start' => $date->format('Y-m-d%'),
                        'finish' => $date->format('Y-m-d%')
                    ),
                    'OR'
                );
            $data[] = array(
                ($date->getTimestamp() * 1000),
                $count
            );
            unset($date);
        }
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
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
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
        $urls = array();
        foreach ((array)$sent as &$url) {
            $urls[] = $url;
            unset($url);
        }
        $datas = self::$FOGURLRequests
            ->process($urls);
        $dataSet = array();
        foreach ((array)$datas as &$data) {
            $dataSet[] = json_decode($data, true);
            unset($data);
        }
        echo json_encode(
            array_combine(
                $names,
                $dataSet
            )
        );
        exit;
    }
}
