<?php
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
 */

namespace FOG;

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
        $Nodes = Route::getList('storagenode');
        foreach ($Nodes as &$StorageNode) {
            if (!($StorageNode->isEnabled && $StorageNode->isGraphEnabled)) {
                continue;
            }
            $ip = $StorageNode->ip;
            /**
             * GH-529: was a literal '/fog/', so a node served from any other
             * webroot was graphed against a URL that does not exist. Each node
             * carries its own webroot (ngmWebroot) and they need not agree with
             * this server's, so use the node's. The collapse below tidies the
             * slashes whichever way the value was stored.
             */
            $url = $ip . '/' . self::webrootPath($StorageNode->webroot ?? null) . '/';
            $url = preg_replace(
                '#/+#',
                '/',
                $url
            );
            $url = self::$httpproto.'://' . $url;
            self::$_nodeOpts[] = sprintf(
                '<option value="%s" data-name="%s"%s>%s</option>',
                \Initiator::e($StorageNode->id),
                \Initiator::e($StorageNode->name),
                ($StorageNode->isMaster ? ' data-master="1"' : ''),
                \Initiator::e($StorageNode->name)
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
        $Groups = Route::getList('storagegroup');
        foreach ($Groups as &$StorageGroup) {
            self::$_groupOpts .= sprintf(
                '<option value="%s">%s</option>',
                \Initiator::e($StorageGroup->id),
                \Initiator::e($StorageGroup->name)
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
        $pendingHosts = Route::getCount(
            'host',
            ['pending' => 1]
        );
        if (DatabaseManager::getColumns('hostMAC', 'hmMAC')) {
            $pendingMACs = Route::getCount(
                'macaddressassociation',
                ['pending' => 1]
            );
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
        $pluginsNeedingUpdate = self::getClass('PluginManager')
            ->getPluginsNeedingUpdate();
        $pluginUpdateCount = count($pluginsNeedingUpdate);
        if ($pluginUpdateCount > 0) {
            $title = $pluginUpdateCount
                . ' '
                . (
                    $pluginUpdateCount != 1 ?
                    _('plugins need a database update') :
                    _('plugin needs a database update')
                );
            $pluginPend = sprintf(
                '%s <a href="?node=%s"><b>%s</b></a> %s',
                _('Click'),
                'plugin',
                _('here'),
                _('to apply the update.')
            );
            self::displayAlert($title, $pluginPend, 'warning', true, true);
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

        // gy-3 because every card on this page is a column of this one row,
        // and a BS5 row is --bs-gutter-y: 0 by default -- so the stacked
        // full-width cards sat flush against the three across the top. A
        // standalone .card carries no bottom margin of its own in BS5; the
        // only rule that sets one is scoped to .card-group. gy- rather than
        // g- keeps the horizontal gutter at its default 1.5rem.
        echo '<div class="row gy-3">';
        echo '<!-- FOG Overview Boxes -->';
        // Server info basic.
        echo '<div class="col-md-4">';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('System Overview');
        echo '</h4>';
        echo '</div>';
        echo '<div class="card-body">';
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
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Storage Group Activity');
        echo '</h4>';
        echo '<div class="graph-selectors float-end" id="graph-activity-selector">';
        printf(
            '<select class="activity-count" name="groupsel">%s</select>',
            self::$_groupOpts
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
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
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo _('Storage Node Disk Usage');
        echo '</h4>';
        echo '<div class="graph-selectors float-end" id="diskusage-selector">';
        printf(
            '<select name="nodesel" class="nodeid">%s</select>',
            self::$_nodeOpts
        );
        echo '</div>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<a href="?node=hwinfo" id="hwinfolink">';
        echo '<div id="graph-diskusage"></div>';
        echo '</a>';
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
        echo '<div class="col-12">';
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
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
        echo '<div class="card-body">';
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
        echo '<div class="col-12">';
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
        echo '<div class="card card-primary card-outline">';
        echo '<div class="card-header">';
        echo '<h4 class="card-title">';
        echo self::$foglang['Bandwidth'];
        echo '</h4>';
        echo '<div class="card-tools float-end">';
        echo _('Real Time');
        echo '<div class="btn-group" id="realtime" data-toggle="btn-toggle">';
        echo self::makeButton(
            'btn-on',
            _('On'),
            'btn btn-secondary btn-sm active',
            ' data-toggle="on"'
        );
        echo self::makeButton(
            'btn-off',
            _('Off'),
            'btn btn-secondary btn-sm',
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
        echo '<div class="offset-md-4 col-md-6">';
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
        echo '<div class="offset-md-4 col-md-6">';
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
        echo '<div class="card-body">';
        echo '<div id="graph-bandwidth"></div>';
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
        if (isset($error) && $error) {
            $data['error'] = $error;
            $data['title'] = _('No Data Available');
        }
        unset(
            $ActivityActive,
            $ActivityQueued,
            $ActivityTotalClients
        );
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($data));
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
            $this->jsonSend(HTTPResponseCodes::HTTP_BAD_REQUEST, json_encode(
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
            ));
        }
        $data = self::$FOGURLRequests
            ->process($url);
        $data = json_decode(
            array_shift($data)
        );
        if (!is_object($data)) {
            // Node was reachable but returned non-JSON; mirror the offline
            // fallback above instead of reading ->free/->used off a non-object.
            $data = (object)[
                'free' => 0,
                'used' => 0,
                'error' => _('Node is unavailable'),
                'title' => _('Node Offline')
            ];
        }
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
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($datatmp));
    }
    /**
     * Gets the 30 day graph.
     *
     * @return void
     */
    public function get30day()
    {
        header('Content-type: application/json');
        $days = (int)filter_input(INPUT_POST, 'days', FILTER_VALIDATE_INT);
        if ($days < 1) {
            $days = 30;
        }
        $start = self::niceDate()
            ->setTime(00, 00, 00)
            ->modify("-$days days");
        $end = self::niceDate()
            ->setTime(23, 59, 59);
        // One grouped query instead of a COUNT per day (was up to 365 queries
        // for the "1 Year" view). Images are counted by the day they started.
        $rows = self::$DB->query(
            "SELECT DATE(`ilStartTime`) AS `d`, COUNT(`ilID`) AS `c`
               FROM `imagingLog`
              WHERE `ilStartTime` BETWEEN :start AND :end
              GROUP BY DATE(`ilStartTime`)",
            [],
            [
                ':start' => $start->format('Y-m-d H:i:s'),
                ':end' => $end->format('Y-m-d H:i:s')
            ]
        )->fetch(\PDO::FETCH_ASSOC, 'fetch_all')->get();
        $counts = [];
        foreach ((array)$rows as $row) {
            $counts[$row['d']] = (int)$row['c'];
        }
        // Emit a continuous, zero-filled series so every day has a point.
        $int = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $int, $end);
        $data = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $data[] = [
                ($date->getTimestamp() * 1000),
                isset($counts[$key]) ? $counts[$key] : 0
            ];
        }
        unset($counts, $rows, $int, $period);
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($data));
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
        $datas = self::$FOGURLRequests->process(
            $urls,
            'GET',
            null,
            false,
            false,
            false,
            false,
            false
        );
        $dataSet = [];
        foreach ((array)$datas as $i => $data) {
            $d = json_decode($data);
            $data = [
                'dev' => $d->dev ?? '',
                'name' => $names[$i],
                'rx' => $d->rx ?? 0,
                'tx' => $d->tx ?? 0
            ];
            $dataSet[] = $data;
            unset($data, $d);
        }
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($dataSet));
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
        // Same class of bug as forums 18210: this probes the node's ftp, so a
        // moved ftp port made the node drop off the dashboard entirely.
        list($ftpPort) = self::getSetting(['FOG_FTP_PORT']);
        $tests = self::$FOGURLRequests->isAvailable($testurls, 1, (int)$ftpPort ?: 21);
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

        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode(
            [
                'names' => $names,
                'urls' => $sent
            ]
        ));
    }
    /**
     * Returns the running FOG version of each graph-enabled storage node,
     * keyed by node id. Nodes that cannot be reached are simply omitted so
     * the dashboard leaves their version blank.
     *
     * @return void
     */
    public function nodeversions()
    {
        header('Content-type: application/json');
        $Nodes = Route::getList('storagenode');
        $ids = [];
        $urls = [];
        foreach ($Nodes as &$StorageNode) {
            if (!($StorageNode->isEnabled && $StorageNode->isGraphEnabled)) {
                continue;
            }
            $url = preg_replace(
                '#/+#',
                '/',
                $StorageNode->ip
                . '/' . self::webrootPath($StorageNode->webroot ?? null)
                . '/service/getversion.php'
            );
            $ids[] = $StorageNode->id;
            $urls[] = self::$httpproto . '://' . $url;
            unset($StorageNode);
        }
        $versions = [];
        if (count($urls)) {
            $datas = (array)self::$FOGURLRequests->process(
                $urls,
                'GET',
                null,
                false,
                false,
                false,
                false,
                3
            );
            foreach ($ids as $i => $id) {
                $ver = isset($datas[$i]) ? trim((string)$datas[$i]) : '';
                // Only accept a plausible version string; otherwise leave it
                // blank (unreachable node returns empty/garbage).
                if ($ver !== '' && preg_match('#^[0-9][0-9A-Za-z._-]*$#', $ver)) {
                    $versions[$id] = $ver;
                }
            }
        }
        unset($ids, $urls, $datas);
        $this->jsonSend(HTTPResponseCodes::HTTP_SUCCESS, json_encode($versions));
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\DashboardPage', 'DashboardPage');
