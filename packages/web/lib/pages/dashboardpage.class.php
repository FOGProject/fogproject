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
        foreach ((array)self::getClass('StorageNodeManager')
            ->find($find) as &$StorageNode
        ) {
            $ip = $StorageNode->get('ip');
            $url = sprintf(
                '%s/%s/',
                $ip,
                $StorageNode->get('webroot')
            );
            $url = preg_replace(
                '#/+#',
                '/',
                $url
            );
            $url = 'http://' . $url;
            $testurls[] = sprintf(
                '%smanagement/index.php',
                $url
            );
            unset($ip);
            self::$_nodeOpts[] = sprintf(
                '<option value="%s" urlcall="%s">%s%s ()</option>',
                $StorageNode->get('id'),
                sprintf(
                    '%sservice/getversion.php',
                    $url
                ),
                $StorageNode->get('name'),
                (
                    $StorageNode->get('isMaster') ?
                    ' *' :
                    ''
                )
            );
            self::$_nodeNames[] = $StorageNode->get('name');
            self::$_nodeURLs[] = sprintf(
                '%sstatus/bandwidth.php?dev=%s',
                $url,
                $StorageNode->get('interface')
            );
            unset($StorageNode);
        }
        foreach ((array)self::getClass('StorageGroupManager')
            ->find() as &$StorageGroup
        ) {
            self::$_groupOpts .= sprintf(
                '<option value="%s">%s</option>',
                $StorageGroup->get('id'),
                $StorageGroup->get('name')
            );
            unset($StorageGroup);
        }
        $test = array_filter(self::$FOGURLRequests->isAvailable($testurls));
        self::$_nodeOpts = array_intersect_key((array)self::$_nodeOpts, $test);
        self::$_nodeNames = array_intersect_key((array)self::$_nodeNames, $test);
        self::$_nodeURLs = array_intersect_key((array)self::$_nodeURLs, $test);
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
            _('TFTP Server') => self::$_tftp,
            _('Load Average') => $SystemUptime['load'],
            _('System Uptime') => $SystemUptime['uptime']
        );
        $this->templates = array(
            '${field}',
            '${input}'
        );
        $this->attributes = array(
            array(),
            array()
        );
        // Overview
        printf(
            '<ul class="dashboard-boxes"><li class="system-overview"><h5>%s</h5>',
            _('System Overview')
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
        $this->render();
        echo '</li>';
        unset(
            $this->data,
            $this->templates,
            $this->attributes,
            $fields,
            $SystemUptime,
            $tftp
        );
        // Client Count/Activity
        printf(
            '<li><h5 class="box" title="%s">%s</h5>'
            . '<div class="graph pie-graph" id="graph-activity">'
            . '</div><div class="graph-selectors" id="graph-activity-selector">',
            _('The selected node\'s storage group slot usage'),
            _('Storage Group Activity')
        );
        printf(
            '<select name="groupsel">%s</select>'
            . '<div class="fog-variable" id="ActivityActive"></div>'
            . '<div class="fog-variable" id="ActivityQueued"></div>'
            . '<div class="fog-variable" id="ActivitySlots"></div>'
            . '</div></li>',
            self::$_groupOpts
        );
        // Disk Usage
        printf(
            '<li><h5 class="box" title="%s">%s</h5>'
            . '<a href="?node=hwinfo"><div class="graph pie-graph" '
            . 'id="graph-diskusage"></div></a><div id="diskusage-selector" class="'
            . 'graph-selectors">',
            _('The selected node\'s image storage usage'),
            _('Storage Node Disk Usage')
        );
        printf(
            '<select name="nodesel">%s</select>'
            . '</div></li>',
            self::$_nodeOpts
        );
        echo '</ul>';
        // 30 day history
        printf(
            '<h3>%s</h3>'
            . '<div id="graph-30day" class="graph"></div>',
            _('Imaging Over the last 30 days')
        );
        echo '<div class="fog-variable" id="Graph30dayData"></div>';
        // Bandwidth display
        $bandwidthtime = self::$_bandwidthtime;
        $datapointshour = (3600 / $bandwidthtime);
        $bandwidthtime *= 1000;
        $datapointshalf = ($datapointshour / 2);
        $datapointsten = ($datapointshour / 6);
        $datapointstwo = ($datapointshour / 30);
        printf(
            '<input type="hidden" id="bandwidthtime" value="%d"/>'
            . '<input id="bandwidthUrls" type="hidden" value="%s"/>'
            . '<input id="nodeNames" type="hidden" value="%s"/>',
            $bandwidthtime,
            implode(',', self::$_nodeURLs),
            implode(',', self::$_nodeNames)
        );
        printf(
            '<h3 id="graph-bandwidth-title">%s - <span>%s</span></h3>'
            . '<div id="graph-bandwidth-filters">'
            . '<div>'
            . '<a href="#" id="graph-bandwidth-filters-transmit" '
            . 'class="l active">%s</a>'
            . '<a href="#" id="graph-bandwidth-filters-receive" '
            . 'class="l">%s</a>'
            . '</div>'
            . '<div class="spacer"></div>'
            . '<div>'
            . '<a href="#" rel="%s" class="r">%s</a>'
            . '<a href="#" rel="%s" class="r">%s</a>'
            . '<a href="#" rel="%s" class="r">%s</a>'
            . '<a href="#" rel="%s" class="r active">%s</a>'
            . '</div>'
            . '</div>'
            . '<div id="graph-bandwidth" class="graph"></div>',
            self::$foglang['Bandwidth'],
            self::$foglang['Transmit'],
            self::$foglang['Transmit'],
            self::$foglang['Receive'],
            $datapointshour,
            _('1 hour'),
            $datapointshalf,
            _('30 Minutes'),
            $datapointsten,
            _('10 Minutes'),
            $datapointstwo,
            _('2 Minutes')
        );
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
            'http://%s/fog/status/freespace.php?path=%s',
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
        $sent = $_REQUEST['url'];
        $names = $_REQUEST['names'];
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
