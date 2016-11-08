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
     * The node to display page for
     *
     * @var string
     */
    public $node = 'home';
    /**
     * Initialize the dashboard page
     *
     * @param string $name the name to initialize with
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = 'Dashboard';
        parent::__construct($this->name);
        global $sub;
        global $id;
        $objName = 'StorageNode';
        if ($sub === 'clientcount') {
            $objName = 'StorageGroup';
        }
        $this->obj = new $objName($id);
        $this->menu = array();
        $this->subMenu = array();
        $this->notes = array();
        if (!self::$ajax) {
            $StorageNodes = self::getClass('StorageNodeManager')
                ->find(
                    array(
                        'isEnabled' => 1,
                        'isGraphEnabled' => 1
                    )
                );
            foreach ((array)$StorageNodes as $i => &$StorageNode) {
                if (!$StorageNode->isValid()) {
                    continue;
                }
                $ip = $StorageNode->get('ip');
                $curroot = trim(
                    trim(
                        $StorageNode->get('webroot'),
                        '/'
                    )
                );
                $webroot = sprintf(
                    '/%s',
                    (
                        strlen($curroot) > 1 ?
                        sprintf(
                            '%s/',
                            $curroot
                        ) :
                        '/'
                    )
                );
                $URL = sprintf(
                    'http://%s%s',
                    $ip,
                    $webroot
                );
                $testurls[] = sprintf(
                    'http://%s/fog/management/index.php',
                    $ip
                );
                unset($ip, $curroot, $webroot);
                self::$_nodeOpts[] = sprintf(
                    '<option value="%s" urlcall="%s">%s%s ()</option>',
                    $StorageNode->get('id'),
                    sprintf(
                        '%sservice/getversion.php',
                        $URL
                    ),
                    $StorageNode->get('name'),
                    (
                        $StorageNode->get('isMaster') ?
                        ' *' :
                        ''
                    )
                );
                $URL = sprintf(
                    '%sstatus/bandwidth.php?dev=%s',
                    $URL,
                    $StorageNode->get('interface')
                );
                self::$_nodeNames[] = $StorageNode->get('name');
                self::$_nodeURLs[] = $URL;
                unset($StorageNode);
            }
            $test = array_filter(self::$FOGURLRequests->isAvailable($testurls));
            self::$_nodeOpts = array_intersect_key(self::$_nodeOpts, $test);
            self::$_nodeOpts = implode(self::$_nodeOpts);
            self::$_nodeNames = array_intersect_key(self::$_nodeNames, $test);
            self::$_nodeURLs = array_intersect_key(self::$_nodeURLs, $test);
            printf(
                '<input id="bandwidthUrls" type="hidden" value="%s"/>',
                implode(',', self::$_nodeURLs)
            );
            printf(
                '<input id="nodeNames" type="hidden" value="%s"/>',
                implode(',', self::$_nodeNames)
            );
        }
    }
    /**
     * The index to display
     *
     * @return void
     */
    public function index()
    {
        $pendingInfo = '<i class="fa fa-circle fa-1x notifier"></i>'
            . '&nbsp;%s<br/>%s <a href="?node=%s&sub=%s">%s</a> %s';
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
            'pend-mac',
            _('here'),
            _('to review.')
        );
        if ($_SESSION['Pending-Hosts'] && $_SESSION['Pending-MACs']) {
            $this->setMessage("$hostPend<br/>$macPend");
        } elseif ($_SESSION['Pending-Hosts']) {
            $this->setMessage($hostPend);
        } elseif ($_SESSION['Pending-MACs']) {
            $this->setMessage($macPend);
        }
        $SystemUptime = self::$FOGCore->systemUptime();
        $fields = array(
            _('Username') => $_SESSION['FOG_USERNAME'],
            _('Web Server') => self::getSetting('FOG_WEB_HOST'),
            _('TFTP Server') => self::getSetting('FOG_TFTP_HOST'),
            _('Load Average') => $SystemUptime['load'],
            _('System Uptime') => $SystemUptime['uptime'],
        );
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        // Overview Pane
        printf(
            '<ul id="dashboard-boxes"><li><h4>%s</h4>',
            _('System Overview')
        );
        array_walk($fields, $this->fieldsToData);
        self::$HookManager
            ->processEvent(
                'DashboardData',
                array(
                    'data' => &$this->data,
                    'templates' => &$this->templates,
                    'attributes'=>&$this->attributes
                )
            );
        $this->render();
        echo '</li>';
        unset($this->templates, $this->attributes, $fields, $SystemUptime);
        // Activity Pane
        printf(
            '<li><h4 class="box" title="%s">%s</h4>'
            . '<div class="graph pie-graph" id="graph-activity">'
            . '</div><div id="graph-activity-selector">',
            _('The selected node\'s storage group slot usage'),
            _('Storage Group Activity')
        );
        ob_start();
        $StorageGroups = self::getClass('StorageGroupManager')
            ->find();
        foreach ((array)$StorageGroups as &$StorageGroup) {
            if (!$StorageGroup->isValid()) {
                continue;
            }
            if (count($StorageGroup->get('enablednodes')) < 1) {
                continue;
            }
            printf(
                '<option value="%s">%s</option>',
                $StorageGroup->get('id'),
                $StorageGroup->get('name')
            );
            unset($StorageGroup);
        }
        printf(
            '<select name="groupsel" style="whitespace: no-wrap; width: 100px; '
            . 'position: relative; top: -22px; left: 140px;">%s</select>'
            . '<div class="fog-variable" id="ActivityActive"></div>'
            . '<div class="fog-variable" id="ActivityQueued"></div>'
            . '<div class="fog-variable" id="ActivitySlots"></div>'
            . '</div></li><!-- Variables -->',
            ob_get_clean()
        );
        $StorageEnabledCount = self::getClass('StorageNodeManager')
            ->count(
                array(
                    'isEnabled' => 1,
                    'isGraphEnabled' => 1
                )
            );
        if ($StorageEnabledCount > 0) {
            // Disk Usage Pane
            printf(
                '<li><h4 class="box" title="%s">%s</h4>'
                . '<div id="diskusage-selector">',
                _('The selected node\'s image storage disk usage'),
                _('Storage Node Disk Usage')
            );
            $StorageNodes = self::getClass('StorageNodeManager')
                ->find(
                    array(
                        'isEnabled' => 1,
                        'isGraphEnabled' => 1
                    )
                );
            printf(
                '<select name="nodesel" style="whitespace: no-wrap; '
                . 'width: 100px; position: relative; top: 100px;">%s</select>'
                . '</div><a href="?node=hwinfo"><div class="graph pie-graph" '
                . 'id="graph-diskusage"></div></a></li>',
                self::$_nodeOpts
            );
        }
        echo '</ul>';
        // 30 Day Usage Graph
        printf(
            '<h3>%s</h3><div id="graph-30day" class="graph"></div>',
            _('Imaging Over the last 30 days')
        );
        ob_start();
        $start = self::niceDate()->modify('-30 days');
        $end = self::niceDate()->setTime(23, 25, 59);
        $int = new DateInterval('P1D');
        $DatePeriod = new DatePeriod($start, $int, $end);
        $dates = iterator_to_array($DatePeriod);
        foreach ((array)$dates as $index => &$date) {
            $count = self::getClass('ImagingLogManager')
                ->count(
                    array(
                        'start' => $date->format('Y-m-d%'),
                        'finish' => $date->format('Y-m-d%')
                    ),
                    'OR'
                );
            printf(
                '["%s", %s]%s',
                (1000 * $date->getTimestamp()),
                $count,
                (
                    $index < 30 ?
                    ', ' :
                    ''
                )
            );
            unset($date, $index);
        }
        printf(
            '<div class="fog-variable" id="Graph30dayData">[%s]</div>',
            ob_get_clean()
        );
        if ($StorageEnabledCount > 0) {
            $bandwidthtime =  self::getSetting('FOG_BANDWIDTH_TIME') * 1000;
            $datapointshour = (3600 / self::getSetting('FOG_BANDWIDTH_TIME'));
            $datapointshalf = ($datapointshour / 2);
            $datapointsten = ($datapointshour / 6);
            $datapointstwo = ($datapointshour / 30);
            // Bandwidth Graph
            printf(
                '<input type="hidden" id="bandwidthtime" value="%s"/>'
                . '<h3 id="graph-bandwidth-title">%s - <span>%s</span>'
                . '<!-- (<span>2 Minutes</span>)--></h3>'
                . '<div id="graph-bandwidth-filters"><div>'
                . '<a href="#" id="graph-bandwidth-filters-transmit" '
                . 'class="l active">%s</a><a href="#" '
                . 'id="graph-bandwidth-filters-receive" class="l">%s</a></div>'
                . '<div class="spacer"></div>'
                . '<div><a href="#" rel="%s" class="r">%s</a>'
                . '<a href="#" rel="%s" class="r">%s</a>'
                . '<a href="#" rel="%s" class="r">%s</a>'
                . '<a href="#" rel="%s" class="r active">%s</a>'
                . '</div></div><div id="graph-bandwidth" class="graph"></div>',
                $bandwidthtime,
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
        self::$_nodeURLs = (array)$_REQUEST['url'];
        self::$_nodeNames = (array)$_REQUEST['names'];
        $datas = self::$FOGURLRequests
            ->process(self::$_nodeURLs);
        $dataSet = array();
        foreach ((array)$datas as &$data) {
            $dataSet[] = json_decode($data, true);
            unset($data);
        }
        echo json_encode(
            array_combine(
                self::$_nodeNames,
                $dataSet
            )
        );
        exit;
    }
    /**
     * Gets the disk usage of the nodes
     *
     * @return void
     */
    public function diskusage()
    {
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        try {
            if (!$this->obj->isValid()) {
                throw new Exception(_('Invalid storage node'));
            }
            if ($this->obj->get('isGraphEnabled') < 1) {
                throw new Exception(_('Graph is disabled for this node'));
            }
            $curroot = trim(
                trim(
                    $this->obj->get('webroot'),
                    '/'
                )
            );
            $webroot = sprintf(
                '/%s',
                (
                    strlen($curroot) > 1 ?
                    sprintf(
                        '%s/',
                        $curroot
                    ) :
                    ''
                )
            );
            $URL = sprintf(
                'http://%s%sstatus/freespace.php?path=%s',
                $this->obj->get('ip'),
                $webroot,
                base64_encode($this->obj->get('path'))
            );
            $testurl = sprintf(
                'http://%s/fog/management/index.php',
                $this->obj->get('ip')
            );
            $test = self::$FOGURLRequests->isAvailable($testurl);
            $test = array_shift($test);
            if (false !== $test) {
                unset($curroot, $webroot);
                $Response = self::$FOGURLRequests
                    ->process($URL);
                $Response = json_decode(
                    array_shift($Response),
                    true
                );
                $Data = array(
                    'free' => $Response['free'],
                    'used' => $Response['used']
                );
                unset($Response);
            }
        } catch (Exception $e) {
            $Data['error'] = $e->getMessage();
        }
        echo json_encode((array)$Data);
        unset(
            $curroot,
            $webroot,
            $URL,
            $Response,
            $Data
        );
        exit;
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
        if (!($this->obj->isValid()
            && count($this->obj->get('enablednodes') > 1))
        ) {
            return;
        }
        $ActivityActive = $ActivityQueued = $ActivityTotalClients = 0;
        $ActivityTotalClients = $this->obj->getTotalAvailableSlots();
        $ActivityQueued = $this->obj->getQueuedSlots();
        $ActivityActive = $this->obj->getUsedSlots();
        $data = array(
            'ActivityActive'=>$ActivityActive,
            'ActivityQueued'=>$ActivityQueued,
            'ActivitySlots'=>$ActivityTotalClients,
        );
        unset($ActivityActive, $ActivityQueued, $ActivityTotalClients);
        echo json_encode($data);
        unset($data);
        exit;
    }
}
