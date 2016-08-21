<?php
class DashboardPage extends FOGPage
{
    public $node = 'home';
    public function __construct($name = '')
    {
        $this->name = 'Dashboard';
        parent::__construct($this->name);
        $this->obj = self::getClass($_REQUEST['sub'] === 'clientcount' ? 'StorageGroup' : 'StorageNode', $_REQUEST['id']);
        $this->menu = array();
        $this->subMenu = array();
        $this->notes = array();
    }
    public function index()
    {
        $pendingInfo = '<i class="fa fa-circle fa-1x notifier"></i>&nbsp;%s<br/>%s <a href="?node=%s&sub=%s">%s</a> %s';
        $hostPend = sprintf($pendingInfo, _('Pending hosts'), _('Click'), 'host', 'pending', _('here'), _('to review.'));
        $macPend = sprintf($pendingInfo, _('Pending macs'), _('Click'), 'report', 'pend-mac', _('here'), _('to review.'));
        if ($_SESSION['Pending-Hosts'] && $_SESSION['Pending-MACs']) {
            $this->setMessage("$hostPend<br/>$macPend");
        } elseif ($_SESSION['Pending-Hosts']) {
            $this->setMessage($hostPend);
        } elseif ($_SESSION['Pending-MACs']) {
            $this->setMessage($macPend);
        }
        $SystemUptime = self::$FOGCore->SystemUptime();
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
        printf('<ul id="dashboard-boxes"><li><h4>%s</h4>', _('System Overview'));
        array_walk($fields, $this->fieldsToData);
        self::$HookManager->processEvent('DashboardData', array('data'=>&$this->data, 'templates'=>&$this->templates, 'attributes'=>&$this->attributes));
        $this->render();
        echo '</li>';
        unset($this->templates, $this->attributes, $fields, $SystemUptime);
        // Activity Pane
        printf('<li><h4 class="box" title="%s">%s</h4><div class="graph pie-graph" id="graph-activity"></div><div id="graph-activity-selector">', _('The selected node\'s storage group slot usage'), _('Storage Group Activity'));
        ob_start();
        array_map(function (&$StorageGroup) {
            if (!$StorageGroup->isValid()) {
                return;
            }
            if (count($StorageGroup->get('enablednodes')) < 1) {
                return;
            }
            printf('<option value="%s">%s</option>', $StorageGroup->get('id'), $StorageGroup->get('name'));
            unset($StorageGroup);
        }, (array)self::getClass('StorageGroupManager')->find());
        printf('<select name="groupsel" style="whitespace: no-wrap; width: 100px; position: relative; top: -22px; left: 140px;">%s</select><div class="fog-variable" id="ActivityActive"></div><div class="fog-variable" id="ActivityQueued"></div><div class="fog-variable" id="ActivitySlots"></div></div></li><!-- Variables -->', ob_get_clean());
        $StorageEnabledCount = self::getClass('StorageNodeManager')->count(array('isEnabled'=>1, 'isGraphEnabled'=>(string)1));
        if ($StorageEnabledCount > 0) {
            // Disk Usage Pane
            printf('<li><h4 class="box" title="%s">%s</h4><div id="diskusage-selector">', _('The selected node\'s image storage disk usage'), _('Storage Node Disk Usage'));
            $StorageNodes = self::getClass('StorageNodeManager')->find(array('isEnabled'=>1, 'isGraphEnabled'=>1));
            ob_start();
            array_walk($StorageNodes, function (&$StorageNode, &$index) use ($data) {
                if (!$StorageNode->isValid()) {
                    return;
                }
                $ip = $StorageNode->get('ip');
                $curroot = trim(trim($StorageNode->get('webroot'), '/'));
                $webroot = sprintf('/%s', (strlen($curroot) > 1 ? sprintf('%s/', $curroot) : '/'));
                $URL = filter_var(sprintf('http://%s%sservice/getversion.php', $ip, $webroot), FILTER_SANITIZE_URL);
                unset($ip, $curroot, $webroot);
                printf('<option value="%s" urlcall="%s">%s%s ()</option>', $StorageNode->get('id'), $URL, $StorageNode->get('name'), ($StorageNode->get('isMaster') ? ' *' : ''));
                unset($version, $StorageNode);
            });
            printf('<select name="nodesel" style="whitespace: no-wrap; width: 100px; position: relative; top: 100px;">%s</select></div><a href="?node=hwinfo"><div class="graph pie-graph" id="graph-diskusage"></div></a></li>', ob_get_clean());
        }
        echo '</ul>';
        // 30 Day Usage Graph
        printf('<h3>%s</h3><div id="graph-30day" class="graph"></div>', _('Imaging Over the last 30 days'));
        ob_start();
        $dates = iterator_to_array(self::getClass('DatePeriod', self::niceDate()->modify('-30 days'), self::getClass('DateInterval', 'P1D'), self::niceDate()->setTime(23, 59, 59)));
        array_walk($dates, function (&$date, &$index) {
            printf('["%s", %s]%s', (1000 * $date->getTimestamp()), self::getClass('ImagingLogManager')->count(array('start'=>$date->format('Y-m-d%'), 'finish'=>$date->format('Y-m-d%')), 'OR'), ($index < 30 ? ', ' : ''));
            unset($date, $index);
        });
        printf('<div class="fog-variable" id="Graph30dayData">[%s]</div>', ob_get_clean());
        if ($StorageEnabledCount > 0) {
            $bandwidthtime =  self::getSetting('FOG_BANDWIDTH_TIME') * 1000;
            $datapointshour = (3600 / self::getSetting('FOG_BANDWIDTH_TIME'));
            $datapointshalf = ($datapointshour / 2);
            $datapointsten = ($datapointshour / 6);
            $datapointstwo = ($datapointshour / 30);
            // Bandwidth Graph
            printf('<input type="hidden" id="bandwidthtime" value="%s"/><h3 id="graph-bandwidth-title">%s - <span>%s</span><!-- (<span>2 Minutes</span>)--></h3><div id="graph-bandwidth-filters"><div><a href="#" id="graph-bandwidth-filters-transmit" class="l active">%s</a><a href="#" id="graph-bandwidth-filters-receive" class="l">%s</a></div><div class="spacer"></div><div><a href="#" rel="%s" class="r">%s</a><a href="#" rel="%s" class="r">%s</a><a href="#" rel="%s" class="r">%s</a><a href="#" rel="%s" class="r active">%s</a></div></div><div id="graph-bandwidth" class="graph"></div>', $bandwidthtime, self::$foglang['Bandwidth'], self::$foglang['Transmit'], self::$foglang['Transmit'], self::$foglang['Receive'], $datapointshour, _('1 hour'), $datapointshalf, _('30 Minutes'), $datapointsten, _('10 Minutes'), $datapointstwo, _('2 Minutes'));
        }
    }
    public function bandwidth()
    {
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        $URLs = $StorageName = array();
        $Nodes = self::getClass('StorageNodeManager')->find(array('isGraphEnabled'=>1, 'isEnabled'=>1));
        $bandwidthPath = self::getSetting('FOG_NFS_BANDWIDTHPATH');
        foreach ($Nodes AS $StorageNode) {
            if (!$StorageNode->isValid()) {
                continue;
            }
            $url = sprintf(
                'http://%s/%s?dev=%s',
                $StorageNode->get('ip'),
                ltrim($bandwidthPath, '/'),
                $StorageNode->get('interface')
            );
            $url = filter_var($url, FILTER_SANITIZE_URL);
            $URLs[] = $url;
            $StorageName[] = $StorageNode->get('name');
            unset($StorageNode);
        }
        $dataSet = array_map(function (&$data) {
            return json_decode($data, true);
        }, self::$FOGURLRequests->process($URLs));
        echo json_encode(array_combine($StorageName, $dataSet));
        exit;
    }
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
            $curroot = trim(trim($this->obj->get('webroot'), '/'));
            $webroot = sprintf('/%s', (strlen($curroot) > 1 ? sprintf('%s/', $curroot) : ''));
            $URL = filter_var(sprintf('http://%s%sstatus/freespace.php?path=%s', $this->obj->get('ip'), $webroot, base64_encode($this->obj->get('path'))), FILTER_SANITIZE_URL);
            unset($curroot, $webroot);
            if (!filter_var($URL, FILTER_VALIDATE_URL)) {
                throw new Exception('%s: %s', _('Invalid URL'), $URL);
            }
            $Response = self::$FOGURLRequests->process($URL);
            $Response = json_decode(array_shift($Response), true);
            $Data = array('free'=>$Response['free'],'used'=>$Response['used']);
            unset($Response);
        } catch (Exception $e) {
            $Data['error'] = $e->getMessage();
        }
        echo json_encode((array)$Data);
        unset($curroot, $webroot, $URL, $Response, $Data);
        exit;
    }
    public function clientcount()
    {
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        if (!($this->obj->isValid() && count($this->obj->get('enablednodes') > 1))) {
            return;
        }
        $ActivityActive = $ActivityQueued = $ActivityTotalClients = 0;
        $ActivityTotalClients = $this->obj->getTotalAvailableSlots();
        array_map(function (&$Node) use (&$ActivityActive, &$ActivityQueued, &$ActivityTotalClients) {
            if (!$Node->isValid()) {
                return;
            }
            $curroot = trim(trim($Node->get('webroot'), '/'));
            $webroot = sprintf('/%s', (strlen($curroot) > 1 ? sprintf('%s/', $curroot) : ''));
            $URL = filter_var(sprintf('http://%s%sindex.php', $Node->get('ip'), $webroot), FILTER_SANITIZE_URL);
            $ActivityActive += $Node->getUsedSlotCount();
            $ActivityQueued += $Node->getQueuedSlotCount();
            if ($ActivityTotalClients <= 0) {
                $ActivityTotalClients = 0;
            }
            unset($Node);
        }, self::getClass('StorageNodeManager')->find(array('id'=>$this->obj->get('enablednodes'))));
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
