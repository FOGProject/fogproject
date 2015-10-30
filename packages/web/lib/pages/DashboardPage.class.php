<?php
class DashboardPage extends FOGPage {
    public $node = 'home';
    public function __construct($name = '') {
        $this->name = 'Dashboard';
        parent::__construct($this->name);
        if ($_REQUEST['id']) $this->obj = $this->getClass('StorageNode',$_REQUEST['id']);
        $this->menu = array();
        $this->subMenu = array();
        $this->notes = array();
    }
    public function index() {
        $hostPend = '<i class="fa fa-circle fa-1x notifier"></i>&nbsp;'._('Pending hosts').'<br/>'._('Click').' '.'<a href="?node=host&sub=pending">'._('here').'</a> '._('to review.');
        $macPend = '<i class="fa fa-circle fa-1x notifier"></i>&nbsp;'._('Pending macs').'<br/>'._('Click').' '.'<a href="?node=report&sub=pend-mac">'._('here').'</a> '._('to review');
        if ($_SESSION['Pending-Hosts'] && $_SESSION['Pending-MACs']) $this->setMessage($hostPend.'<br/>'.$macPend);
        else if ($_SESSION['Pending-Hosts']) $this->setMessage($hostPend);
        else if ($_SESSION['Pending-MACs']) $this->setMessage($macPend);
        $SystemUptime = $this->FOGCore->SystemUptime();
        $fields = array(
            _('Username') => $_SESSION['FOG_USERNAME'],
            _('Web Server') => $this->getSetting('FOG_WEB_HOST'),
            _('TFTP Server') => $this->getSetting('FOG_TFTP_HOST'),
            _('Load Average') => $SystemUptime['load'],
            _('System Uptime') => $SystemUptime['uptime'],
        );
        $this->templates = array(
            '${field}',
            '${fielddata}',
        );
        $this->attributes = array(
            array(),
            array(),
        );
        echo '<ul id="dashboard-boxes"><li><h4>'._('System Overview').'</h4>';
        foreach ((array)$fields AS $field => &$fielddata) {
            $this->data[] = array(
                'field' => $field,
                'fielddata' => $fielddata,
            );
            unset($field);
        }
        unset($fields);
        $this->HookManager->processEvent('DashboardData',array('data'=>&$this->data,'templates'=>&$this->templates,'attributes'=>&$this->attributes));
        $this->render();
        echo '</li><li><h4>'._('System Activity').'</h4><div class="graph pie-graph" id="graph-activity"></div></li><li><h4>'._('Disk Information').'</h4><div id="diskusage-selector">';
        $Nodes = $this->getClass('StorageNodeManager')->find(array('isEnabled'=>1,'isGraphEnabled'=>1));
        foreach ($Nodes AS $i => &$StorageNode) {
            if (!$StorageNode->isValid()) continue;
            $curroot = trim(trim($StorageNode->get('webroot'),'/'));
            $webroot = '/'.(strlen($curroot) > 1 ? $curroot.'/' : '');
            $version = $this->FOGURLRequests->process($StorageNode->get('ip').$webroot.'/service/getversion.php','GET');
            $options .= '<option value="'.$StorageNode->get('id').'">'.$StorageNode->get('name').($StorageNode->get('isMaster') == 1 ? " * " : ' ')."(${version[0]})".'</option>';
            unset($StorageNode);
        }
        echo ($options ? '<select name="storagesel" style="whitespace: no-wrap; width: 100px; position: relative; top: 100px;">'.$options.'</select>' : null);
        echo '</div><a href="?node=hwinfo"><div class="graph pie-graph" id="graph-diskusage"></div></a></li></ul><h3>'._('Imaging over the last 30 days').'</h3><div id="graph-30day" class="graph"></div><h3 id="graph-bandwidth-title">'.$this->foglang['Bandwidth'].'- <span>'.$this->foglang['Transmit'].'</span><!-- (<span>2 Minutes</span>)--></h3><div id="graph-bandwidth-filters"><div><a href="#" id="graph-bandwidth-filters-transmit" class="l active">'.$this->foglang['Transmit'].'</a><a href="#" id="graph-bandwidth-filters-receive" class="l">'.$this->foglang['Receive'].'</a></div><div class="spacer"></div><div><a href="#" rel="3600" class="r">'._('1 hour').'</a><a href="#" rel="1800" class="r">'._('30 Minutes').'</a><a href="#" rel="600" class="r">'._('10 Minutes').'</a><a href="#" rel="120" class="r active">'._('2 Minutes').'</a></div></div><div id="graph-bandwidth" class="graph"></div>';
        for ($i = 0; $i <= 30; $i++) $Graph30dayData .= '["'.(1000*$this->nice_date()->modify("-$i days")->getTimestamp()).'", '.$this->getClass('ImagingLogManager')->count(array('start'=>$this->nice_date()->modify("-$i days")->format('Y-m-d%'),'finish'=>$this->nice_date()->modify("-$i days")->format('Y-m-d%')),'OR').']'.($i < 30 ? ', ' : '');
        echo '<div class="fog-variable" id="ActivityActive"></div><div class="fog-variable" id="ActivityQueued"></div><div class="fog-variable" id="ActivitySlots"></div><!-- Variables --><div class="fog-variable" id="Graph30dayData">['.$Graph30dayData.']</div>';
    }
    public function bandwidth() {
        $URLs = array();
        $Nodes = $this->getClass('StorageNodeManager')->find(array('isGraphEnabled'=>1));
        foreach((array)$Nodes AS $i => &$StorageNode) {
            if (!$StorageNode->isValid()) continue;
            $URLs[] = sprintf('http://%s/%s?dev=%s',$StorageNode->get('ip'),ltrim($this->getSetting('FOG_NFS_BANDWIDTHPATH'),'/'),$StorageNode->get('interface'));
            unset($StorageNode);
        }
        $fetchedData = $this->FOGURLRequests->process($URLs,'GET');
        foreach((array)$fetchedData AS $i => &$dataSet) {
            if (preg_match('/(.*)##(.*)/U', $dataSet,$match)) $data[$Nodes[$i]->get('name')] = array('rx'=>$match[1],'tx'=>$match[2]);
            else $data[$Nodes[$i]->get('name')] = json_decode($dataSet,true);
        }
        unset($Nodes);
        unset($dataSet);
        echo json_encode((array)$data);
        exit;
    }
    public function diskusage() {
        if (!($this->obj->isValid() && $this->obj->get('isGraphEnabled'))) exit;
        try {
            $curroot = trim(trim($this->obj->get('webroot'),'/'));
            $webroot = '/'.(strlen($curroot) > 1 ? $curroot.'/' : '');
            $URL = sprintf('http://%s%sstatus/freespace.php?path=%s',$this->obj->get('ip'),$webroot,base64_encode($this->obj->get('path')));
            if ($Response = $this->FOGURLRequests->process($URL,'GET')) {
                if (preg_match('#(.*)@(.*)#', $Response[0], $match)) $Data = array('free'=>$match[1],'used'=>$match[2]);
                else {
                    $Response = json_decode($Response[0], true);
                    $Data = array('free'=>$Response['free'],'used'=>$Response['used']);
                }
            } else throw new Exception('Failed to connect to '.$this->obj->get('name'));
        } catch (Exception $e) {
            $Data['error'] = $e->getMessage();
        }
        echo json_encode((array)$Data);
        exit;
    }
    public function clientcount() {
        if (!($this->obj->isValid() && $this->obj->get('isGraphEnabled'))) exit;
        $StorageGroup = $this->getClass('StorageGroup',$this->obj->get('storageGroupID'));
        if (!$StorageGroup->isValid()) exit;
        $ActivityActive = $ActivityQueued = $ActivityTotalClients = 0;
        $ActivityTotalClients = $StorageGroup->getTotalSupportedClients();
        $Nodes = $this->getClass('StorageNodeManager')->find(array('id'=>$StorageGroup->get('enablednodes')));
        foreach ($Nodes AS $i => &$Node) {
            if (!$Node->isValid()) continue;
            $ActivityActive += $Node->getUsedSlotCount();
            $ActivityQueued += $Node->getQueuedSlotCount();
            unset($Node);
        }
        unset($Nodes);
        $data = array(
            'ActivityActive'=>$ActivityActive,
            'ActivityQueued'=>$ActivityQueued,
            'ActivitySlots'=>$ActivityTotalClients,
        );
        echo json_encode($data);
        exit;
    }
}
