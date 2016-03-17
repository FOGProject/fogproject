<?php
class DashboardPage extends FOGPage {
    public $node = 'home';
    public function __construct($name = '') {
        $this->name = 'Dashboard';
        parent::__construct($this->name);
        if (isset($_REQUEST['id'])) $this->obj = self::getClass('StorageNode',$_REQUEST['id']);
        $this->menu = array();
        $this->subMenu = array();
        $this->notes = array();
    }
    public function index() {
        $pendingInfo = '<i class="fa fa-circle fa-1x notifier"></i>&nbsp;%s<br/>%s <a href="?node=%s&sub=%s">%s</a> %s';
        $hostPend = sprintf($pendingInfo,_('Pending hosts'),_('Click'),'host','pending',_('here'),_('to review.'));
        $macPend = sprintf($pendingInfo,_('Pending macs'),_('Click'),'report','pend-mac',_('here'),_('to review.'));
        if ($_SESSION['Pending-Hosts'] && $_SESSION['Pending-MACs']) $this->setMessage("$hostPend<br/>$macPend");
        else if ($_SESSION['Pending-Hosts']) $this->setMessage($hostPend);
        else if ($_SESSION['Pending-MACs']) $this->setMessage($macPend);
        $SystemUptime = self::$FOGCore->SystemUptime();
        $fields = array(
            _('Username') => $_SESSION['FOG_USERNAME'],
            _('Web Server') => $this->getSetting('FOG_WEB_HOST'),
            _('TFTP Server') => $this->getSetting('FOG_TFTP_HOST'),
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
        printf('<ul id="dashboard-boxes"><li><h4>%s</h4>',_('System Overview'));
        array_walk($fields,$this->fieldsToData);
        $this->HookManager->processEvent('DashboardData',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</li>';
        unset($this->templates,$this->attributes,$fields,$SystemUptime);
        $StorageEnabledCount = self::getClass('StorageNodeManager')->count(array('isEnabled'=>1,'isGraphEnabled'=>1));
        if (self::getClass('StorageNodeManager')->count(array('isEnabled'=>1,'isGraphEnabled'=>1)) > 0) {
            // Activity Pane
            printf('<li><h4 class="box" title="%s">%s</h4><div class="graph pie-graph" id="graph-activity"></div></li>',_('The selected node\'s storage group slot usage'),_('Storage Group Activity'));
            // Disk Usage Pane
            printf('<li><h4 class="box" title="%s">%s</h4><div id="diskusage-selector">',_('The selected node\'s image storage disk usage'),_('Storage Node Disk Usage'));
            ob_start();
            array_map(function(&$StorageNode) {
                if (!$StorageNode->isValid()) {
                    unset($StorageNode);
                    return;
                }
                $ip = $StorageNode->get('ip');
                $curroot = trim(trim($StorageNode->get('webroot'),'/'));
                $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
                $URL = filter_var("http://$ip{$webroot}service/getversion.php",FILTER_SANITIZE_URL);
                unset($curroot,$webroot,$ip);
                $version = $this->FOGURLRequests->process($URL,'POST');
                $version = array_shift($version);
                printf('<option value="%s">%s%s (%s)</option>',$StorageNode->get('id'),$StorageNode->get('name'),($StorageNode->get('isMaster') ? ' *' : ''),$version);
                unset($version,$StorageNode);
            },self::getClass('StorageNodeManager')->find(array('isEnabled'=>1,'isGraphEnabled'=>1)));
            printf('<select name="storagesel" style="whitespace: no-wrap; width: 100px; position: relative; top: 100px;">%s</select></div><a href="?node=hwinfo"><div class="graph pie-graph" id="graph-diskusage"></div></a></li>',ob_get_clean());
        }
        echo '</ul>';
        echo '<div class="fog-variable" id="ActivityActive"></div><div class="fog-variable" id="ActivityQueued"></div><div class="fog-variable" id="ActivitySlots"></div><!-- Variables -->';
        // 30 Day Usage Graph
        printf('<h3>%s</h3><div id="graph-30day" class="graph"></div>',_('Imaging Over the last 30 days'));
        ob_start();
        array_walk(iterator_to_array(self::getClass('DatePeriod',$this->nice_date()->modify('-30 days'),self::getClass('DateInterval','P1D'),$this->nice_date()->setTime(23,59,59))),function(&$date,&$index) {
            printf('["%s", %s]%s',(1000 * $date->getTimestamp()),self::getClass('ImagingLogManager')->count(array('start'=>$date->format('Y-m-d%'),'finish'=>$date->format('Y-m-d%')),'OR'),($index < 30 ? ', ' : ''));
            unset($date,$index);
        });
        printf('<div class="fog-variable" id="Graph30dayData">[%s]</div>',ob_get_clean());
        if ($StorageEnabledCount > 0) {
            $bandwidthtime = (int) $this->getSetting('FOG_BANDWIDTH_TIME') * 1000;
            $datapointshour = (int)(3600 / $this->getSetting('FOG_BANDWIDTH_TIME'));
            $datapointshalf = (int)($datapointshour / 2);
            $datapointsten = (int)($datapointshour / 6);
            $datapointstwo = (int)($datapointshour / 30);
            // Bandwidth Graph
            printf('<input type="hidden" id="bandwidthtime" value="%s"/><h3 id="graph-bandwidth-title">%s - <span>%s</span><!-- (<span>2 Minutes</span>)--></h3><div id="graph-bandwidth-filters"><div><a href="#" id="graph-bandwidth-filters-transmit" class="l active">%s</a><a href="#" id="graph-bandwidth-filters-receive" class="l">%s</a></div><div class="spacer"></div><div><a href="#" rel="%s" class="r">%s</a><a href="#" rel="%s" class="r">%s</a><a href="#" rel="%s" class="r">%s</a><a href="#" rel="%s" class="r active">%s</a></div></div><div id="graph-bandwidth" class="graph"></div>',$bandwidthtime,self::$foglang['Bandwidth'],self::$foglang['Transmit'],self::$foglang['Transmit'],self::$foglang['Receive'],$datapointshour,_('1 hour'),$datapointshalf,_('30 Minutes'),$datapointsten,_('10 Minutes'),$datapointstwo,_('2 Minutes'));
        }
    }
    public function bandwidth() {
        $data = array();
        array_map(function(&$StorageNode) use (&$data) {
            if (!$StorageNode->isValid()) return;
            $URL = filter_var(sprintf('http://%s/%s?dev=%s',$StorageNode->get('ip'),ltrim($this->getSetting('FOG_NFS_BANDWIDTHPATH'),'/'),$StorageNode->get('interface')),FILTER_SANITIZE_URL);
            $dataSet = $this->FOGURLRequests->process($URL,'GET');
            unset($URL);
            $data[$StorageNode->get('name')] = json_decode(array_shift($dataSet));
            unset($dataSet,$StorageNode);
        },self::getClass('StorageNodeManager')->find(array('isGraphEnabled'=>1,'isEnabled'=>1)));
        echo json_encode((array)$data);
        unset($data);
        exit;
    }
    public function diskusage() {
        try {
            if (!$this->obj->isValid()) throw new Exception(_('Invalid storage node'));
            if ($this->obj->get('isGraphEnabled') < 1) throw new Exception(_('Graph is disabled for this node'));
            $curroot = trim(trim($this->obj->get('webroot'),'/'));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            $URL = filter_var(sprintf('http://%s%sstatus/freespace.php?path=%s',$this->obj->get('ip'),$webroot,base64_encode($this->obj->get('path'))),FILTER_SANITIZE_URL);
            unset($curroot,$webroot);
            if (!filter_var($URL,FILTER_VALIDATE_URL)) throw new Exception('%s: %s',_('Invalid URL'),$URL);
            $Response = $this->FOGURLRequests->process($URL,'GET');
            $Response = json_decode(array_shift($Response), true);
            $Data = array('free'=>$Response['free'],'used'=>$Response['used']);
            unset($Response);
        } catch (Exception $e) {
            $Data['error'] = $e->getMessage();
        }
        echo json_encode((array)$Data);
        unset($curroot,$webroot,$URL,$Response,$Data);
        exit;
    }
    public function clientcount() {
        if (!($this->obj->isValid() && $this->obj->get('isGraphEnabled'))) return;
        $StorageGroup = self::getClass('StorageGroup',$this->obj->get('storageGroupID'));
        if (!$StorageGroup->isValid()) return;
        $ActivityActive = $ActivityQueued = $ActivityTotalClients = 0;
        $ActivityTotalClients = $StorageGroup->getTotalSupportedClients();
        array_map(function(&$Node) use (&$ActivityActive,&$ActivityQueued,&$ActivityTotalClients) {
            if (!$Node->isValid()) return;
            $ActivityActive += $Node->getUsedSlotCount();
            $ActivityQueued += $Node->getQueuedSlotCount();
            $ActivityTotalClients -= $ActivityActive;
            if ($ActivityTotalClients <= 0) $ActivityTotalClients = 0;
            unset($Node);
        },self::getClass('StorageNodeManager')->find(array('id'=>$StorageGroup->get('enablednodes'))));
        $data = array(
            'ActivityActive'=>$ActivityActive,
            'ActivityQueued'=>$ActivityQueued,
            'ActivitySlots'=>$ActivityTotalClients,
        );
        unset($ActivityActive,$ActivityQueued,$ActivityTotalClients);
        echo json_encode($data);
        unset($data);
        exit;
    }
}
