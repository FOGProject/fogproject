<?php
class Host extends FOGController {
    // Table
    public $databaseTable = 'hosts';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'hostID',
        'name' => 'hostName',
        'description' => 'hostDesc',
        'ip' => 'hostIP',
        'imageID' => 'hostImage',
        'building' => 'hostBuilding',
        'createdTime' => 'hostCreateDate',
        'deployed' => 'hostLastDeploy',
        'createdBy' => 'hostCreateBy',
        'useAD' => 'hostUseAD',
        'ADDomain' => 'hostADDomain',
        'ADOU' => 'hostADOU',
        'ADUser' => 'hostADUser',
        'ADPass' => 'hostADPass',
        'ADPassLegacy' => 'hostADPassLegacy',
        'productKey' => 'hostProductKey',
        'printerLevel' => 'hostPrinterLevel',
        'kernel' => 'hostKernel',
        'kernelArgs' => 'hostKernelArgs',
        'kernelDevice' => 'hostDevice',
        'pending' => 'hostPending',
        'pub_key' => 'hostPubKey',
        'sec_tok' => 'hostSecToken',
        'sec_time' => 'hostSecTime',
        'pingstatus' => 'hostPingCode',
        'biosexit' => 'hostExitBios',
        'efiexit' => 'hostExitEfi',
    );
    // Allow setting / getting of these additional fields
    public $additionalFields = array(
        'mac',
        'primac',
        'imagename',
        'additionalMACs',
        'pendingMACs',
        'groups',
        'groupsnotinme',
        'optimalStorageNode',
        'printers',
        'printersnotinme',
        'snapins',
        'snapinsnotinme',
        'modules',
        'hardware',
        'inventory',
        'task',
        'snapinjob',
        'users',
        'fingerprint',
    );
    // Required database fields
    public $databaseFieldsRequired = array(
        'id',
        'name',
    );
    public $databaseFieldClassRelationships = array(
        'MACAddressAssociation' => array('hostID','id','primac',array('primary'=>1)),
        'Image' => array('id','imageID','imagename'),
    );
    // Load the items
    public function load($field = 'id') {
        parent::load($field);
        $methods = get_class_methods($this);
        foreach($methods AS $i => &$method) {
            if (strlen($method) > 5 && strpos($method,'load')) $this->$method();
        }
        unset($method);
    }
    // Overrides
    public function get($key = '') {
        $arrayKeys = array(
            'additionalMACs',
            'pendingMACs',
            'modules',
            'users',
            'printers',
            'printersnotinme',
            'snapins',
            'snapinsnotinme',
            'groups',
            'groupsnotinme',
        );
        switch ($this->key($key)) {
            case 'additionalMACs':
                $this->loadAdditional();
                break;
            case 'pendingMACs':
                $this->loadPending();
                break;
            case 'modules':
                $this->loadModules();
                break;
            case 'users':
                $this->loadUsers();
                break;
            case 'printers':
            case 'printersnotinme':
                $this->loadPrinters();
                break;
            case 'snapins':
            case 'snapinsnotinme':
                $this->loadSnapins();
                break;
            case 'groups':
            case 'groupsnotinme':
                $this->loadGroups();
                break;
            case 'mac':
                $this->loadPrimary();
                break;
            case 'snapinjob':
                $this->loadSnapinJob();
                break;
            case 'inventory':
                $this->loadInventory();
                break;
            case 'task':
                $this->loadTask();
                break;
            case 'optimalStorageNode':
                $this->loadOptimalStorageNode();
                break;
        }
        if (in_array($this->key($key),(array)$arrayKeys)) {
            unset($arrayKeys);
            return (array)parent::get($key);
        }
        return parent::get($key);
    }
    public function set($key, $value) {
        switch ($this->key($key)) {
            case 'mac':
                $this->loadPrimary();
                if (!($value instanceof MACAddress)) $value = $this->getClass('MACAddress',$value);
                break;
            case 'additionalMACs':
            case 'pendingMACs':
                $this->key($key) == 'additionalMACs' ? $this->loadAdditional() : $this->loadPending();
                foreach((array)$value AS $i => &$mac) $newValue[] = $this->getClass('MACAddress',$this->getClass('MACAddressAssociation',$mac));
                unset($mac);
                $value = (array)$newValue;
                break;
            case 'snapinjob':
                $this->loadSnapinJob();
                if (!($value instanceof SnapinJob)) $value = $this->getClass('SnapinJob',$value);
                break;
            case 'inventory':
                $this->loadInventory();
                if (!($value instanceof Inventory)) $value = $this->getClass('Inventory',$value);
                break;
            case 'task':
                $this->loadTask();
                if (!($value instanceof Task)) $value = $this->getClass('Task',$value);
                break;
            case 'printers':
                $this->loadPrinters();
                break;
            case 'snapins':
                $this->loadSnapins();
                break;
            case 'modules':
                $this->loadModules();
                break;
            case 'groups':
                $this->loadGroups();
                break;
            case 'users':
                $this->loadUsers();
                break;
        }
        // Set
        return parent::set($key, $value);
    }
    public function add($key, $value) {
        switch ($this->key($key)) {
            case 'additionalMACs':
            case 'pendingMACs':
                $this->key($key) == 'additionalMACs' ? $this->loadAdditional() : $this->loadPending();
                if (!($value instanceof MACAddress)) $value = $this->getClass('MACAddress',$value);
                break;
            case 'printers':
                $this->loadPrinters();
                break;
            case 'snapins':
                $this->loadSnapins();
                break;
            case 'modules':
                $this->loadModules();
                break;
            case 'groups':
                $this->loadGroups();
                break;
            case 'users':
                $this->loadUsers();
                break;
        }
        return parent::add($key,$value);
    }
    public function remove($key, $object) {
        switch ($this->key($key)) {
            case 'mac':
                $this->loadPrimary();
                break;
            case 'additionalMACs':
                $this->loadAdditional();
                break;
            case 'pendingMACs':
                $this->loadPending();
                break;
            case 'printers':
                $this->loadPrinters();
                break;
            case 'snapins':
                $this->loadSnapins();
                break;
            case 'modules':
                $this->loadModules();
                break;
            case 'groups':
                $this->loadGroups();
                break;
            case 'users':
                $this->loadUsers();
                break;
        }
        // Remove
        return parent::remove($key, $object);
    }
    public function save() {
        parent::save();
        switch (true) {
            case ($this->isLoaded('mac')):
                if (!(($this->get('mac') instanceof MACAddress) && $this->get('mac')->isValid())) throw new Exception($this->foglang['InvalidMAC']);
                $RealPriMAC = $this->get('mac')->__toString();
                $HostWithMAC = $this->getClass('MACAddressAssociationManager')->find(array('mac'=>$RealPriMAC),'','','','','','','hostID');
                $DBPriMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>1),'','','','','','','mac');
                if (count($HostWithMAC) && !in_array($this->get('id'),$HostWithMAC)) throw new Exception(_('This MAC Belongs to another host'));
                $DBMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id')),'','','','','','','mac');
                if (in_array($RealPriMAC,(array)$DBMACs)) {
                    foreach ((array)$DBMACs AS $i => $DBMAC) {
                        if ($RealPriMAC === $DBMAC) {
                            $this->removeAddMAC($RealPriMAC);
                            $this->removePendMAC($RealPriMAC);
                        }
                    }
                }
                $RemoveMAC = array_diff((array)$DBPriMACs,(array)$RealPriMAC);
                if (in_array($RealPriMAC,$DBMACs)) $RemoveMAC = array_merge((array)$RemoveMAC,(array)$RealPriMAC);
                if (count($RemoveMAC)) {
                    $this->getClass('MACAddressAssociationManager')->destroy(array('mac'=>$RemoveMAC));
                    unset($RemoveMAC);
                    $DBPriMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>1),'','','','','','','mac');
                }
                if (!in_array($RealPriMAC,$DBPriMACs)) {
                    $this->getClass('MACAddressAssociation')
                        ->set('hostID',$this->get('id'))
                        ->set('mac',$RealPriMAC)
                        ->set('primary',1)
                        ->save();
                }
            case ($this->isLoaded('additionalMACs')):
                $theseMACs = $this->get('additionalMACs');
                $RealAddMACs = $PreOwnedMACs = array();
                foreach ((array)$theseMACs AS $i => &$thisMAC) {
                    if (($thisMAC instanceof MACAddress) && $thisMAC->isValid() && !in_array($thisMAC->__toString(),(array)$RealAddMACs)) $RealAddMACs[] = $thisMAC->__toString();
                }
                $RealAddMACs = array_diff((array)$RealAddMACs,(array)$PreOwnedMACs);
                $DBPriMACs = $this->getClass('MACAddressAssociationManager')->find(array('primary'=>1),'','','','','','','mac');
                foreach ((array)$DBPriMACs AS $i => &$DBPriMAC) {
                    if (false !== $this->array_strpos($DBPriMAC,$RealAddMACs)) throw new Exception(_('Cannot add a pre-existing Primary MAC as an additional MAC'));
                }
                $HostsWithMACs = $this->getClass('MACAddressAssociationManager')->find(array('mac'=>$RealAddMACs));
                foreach ((array)$HostsWithMACs AS $i => $HostWithMAC) {
                    if ($HostWithMAC->get('hostID') && $HostWithMAC->get('hostID') != $this->get('id') && !in_array($this->getClass('MACAddress',$HostWithMAC)->__toString(),(array)$PreOwnedMACs)) $PreOwnedMACs[] = $this->getClass('MACAddress',$HostWithMAC)->__toString();
                }
                $DBAddMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>array(0,null,''),'pending'=>array(0,null,'')),'','','','','','','mac');
                $DBMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>array(0,null,'')),'','','','','','','mac');
                $RemoveAddMAC = array_diff((array)$DBAddMACs,(array)$RealAddMACs);
                if (count($RemoveAddMAC)) {
                    $this->getClass('MACAddressAssociationManager')->destroy(array('mac'=>$RemoveAddMAC));
                    $DBAddMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>array(0,null,''),'pending'=>array(0,null,'')),'','','','','','','mac');
                    unset($RemoveAddMAC);
                }
                $RealAddMACs = array_diff((array)$RealAddMACs,(array)$DBAddMACs);
                foreach ((array)$RealAddMACs AS $i => &$RealAddMAC) {
                    $this->getClass('MACAddressAssociation')
                        ->set('hostID',$this->get('id'))
                        ->set('mac',$RealAddMAC)
                        ->save();
                }
            case ($this->isLoaded('pendingMACs')):
                $theseMACs = $this->get('pendingMACs');
                $RealPendMACs = $PreOwnedMACs = array();
                foreach ((array)$theseMACs AS $i => &$thisMAC) {
                    if (($thisMAC instanceof MACAddress) && $thisMAC->isValid() && !in_array($thisMAC->__toString(),(array)$RealPendMACs)) $RealPendMACs[] = $thisMAC->__toString();
                }
                $RealPendMACs = array_diff((array)$RealPendMACs,(array)$PreOwnedMACs);
                $DBPriMACs = $this->getClass('MACAddressAssociationManager')->find(array('primary'=>1),'','','','','','','mac');
                foreach ((array)$DBPriMACs AS $i => &$DBPriMAC) {
                    if (false !== $this->array_strpos($DBPriMAC,$RealPendMACs)) throw new Exception(_('Cannot add a pre-existing Primary MAC as a pending MAC'));
                }
                $HostsWithMACs = $this->getClass('MACAddressAssociationManager')->find(array('mac'=>$RealPendMACs));
                foreach ((array)$HostsWithMACs AS $i => $HostWithMAC) {
                    if ($HostWithMAC->get('hostID') && $HostWithMAC->get('hostID') != $this->get('id') && !in_array($this->getClass('MACAddress',$HostWithMAC)->__toString(),(array)$PreOwnedMACs)) $PreOwnedMACs[] = $this->getClass('MACAddress',$HostWithMAC)->__toString();
                }
                $DBPendMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>array(0,null,''),'pending'=>1),'','','','','','','mac');
                $DBMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>array(0,null,'')),'','','','','','','mac');
                $RemovePendMAC = array_diff((array)$DBPendMACs,(array)$RealPendMACs);
                if (count($RemovePendMAC)) {
                    $this->getClass('MACAddressAssociationManager')->destroy(array('mac'=>$RemovePendMAC));
                    $DBPendMACs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>array(0,null,''),'pending'=>1),'','','','','','','mac');
                    unset($RemovePendMAC);
                }
                $RealPendMACs = array_diff((array)$RealPendMACs,(array)$DBPendMACs);
                foreach ((array)$RealPendMACs AS $i => &$RealPendMAC) {
                    $this->getClass('MACAddressAssociation')
                        ->set('hostID',$this->get('id'))
                        ->set('mac',$RealPendMAC)
                        ->set('pending',1)
                        ->save();
                }
            case ($this->isLoaded('modules')):
                $DBModuleIDs = $this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
                $RemoveModuleIDs = array_diff((array)$DBModuleIDs,(array)$this->get('modules'));
                if (count($RemoveModuleIDs)) {
                    $this->getClass('ModuleAssociationManager')->destroy(array('moduleID'=>$RemoveModuleIDs,'hostID'=>$this->get('id')));
                    $DBModuleIDs = $this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
                    unset($RemoveModuleIDs);
                }
                $ModuleIDs = array_diff((array)$this->get('modules'),(array)$DBModuleIDs);
                $moduleName = $this->getGlobalModuleStatus();
                foreach((array)$ModuleIDs AS $i => &$Module) {
                    if ($moduleName[$this->getClass('Module',$Module)->get('shortName')]) {
                        $this->getClass('ModuleAssociation')
                            ->set('hostID',$this->get('id'))
                            ->set('moduleID',$Module)
                            ->set('state',1)
                            ->save();
                    }
                }
                unset($Module);
            case ($this->isLoaded('printers')):
                $DBPrinterIDs = $this->getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
                $RemovePrinterIDs = array_diff((array)$DBPrinterIDs,(array)$this->get('printers'));
                if (count($RemovePrinterIDs)) {
                    $this->getClass('PrinterAssociationManager')->destroy(array('hostID'=>$this->get('id'),'printerID'=>$RemovePrinterIDs));
                    $DBPrinterIDs = $this->getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
                    unset($RemovePrinterIDs);
                }
                $PrinterIDs = array_diff((array)$this->get('printers'),(array)$DBPrinterIDs);
                foreach ((array)$PrinterIDs AS $i => $Printer) $this->getClass('Printer',$Printer)->addHost($this->get('id'))->save();
                unset($Printer);
            case ($this->isLoaded('snapins')):
                $DBSnapinIDs = $this->getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
                $RemoveSnapinIDs = array_diff((array)$DBSnapinIDs,(array)$this->get('snapins'));
                if (count($RemoveSnapinIDs)) {
                    $this->getClass('SnapinAssociationManager')->destroy(array('hostID'=>$this->get('id'),'snapinID'=>$RemoveSnapinIDs));
                    $DBSnapinIDs = $this->getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
                    unset($RemoveSnapinIDs);
                }
                $Snapins = array_diff((array)$this->get('snapins'),(array)$DBSnapinIDs);
                foreach ((array)$Snapins AS $i => $Snapin) $this->getClass('Snapin',$Snapin)->addHost($this->get('id'))->save();
                unset($Snapin);
            case ($this->isLoaded('groups')):
                $DBGroupIDs = $this->getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
                $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('groups'));
                if (count($RemoveGroupIDs)) {
                    $this->getClass('GroupAssociationManager')->destroy(array('hostID'=>$this->get('id'),'groupID'=>$RemoveGroupIDs));
                    $DBGroupIDs = $this->getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
                    unset($RemoveGroupIDs);
                }
                $Groups = array_diff((array)$this->get('groups'),(array)$DBGroupIDs);
                foreach ((array)$Groups AS $i => $Group) $this->getClass('Group',$Group)->addHost($this->get('id'))->save();
                unset($Group);
        }
        return $this;
    }
    public function isValid() {
        return parent::isValid() && $this->isHostnameSafe() && $this->get('mac') instanceof MACAddress && $this->get('mac')->isValid();
    }
    // Custom functons
    public function isHostnameSafe($hostname = '') {
        if (empty($hostname)) $hostname = $this->get('name');
        return (strlen($hostname) > 0 && strlen($hostname) <= 15 && preg_replace('#[0-9a-zA-Z_\-]#', '', $hostname) == '');
    }
    public function getDefault($printerid) {
        return $this->getClass('Printer',@max($this->getClass('PrinterAssociationManager')->find(array('hostID'=>$this->get('id'),'printerID'=>$printerid,'isDefault'=>1),'','','','','','','printerID')))->isValid();
    }
    public function updateDefault($printerid,$onoff) {
        // Unset all default
        $this->getClass('PrinterAssociationManager')->update(array('printerID'=>$this->get('printers'),'hostID'=>$this->get('id')),'',array('isDefault'=>0));
        $this->getClass('PrinterAssociationManager')->update(array('printerID'=>$printerid,'hostID'=>$this->get('id')),'',array('isDefault'=>(int)$onoff));
        return $this;
    }
    public function getDispVals($key = '') {
        $keyTran = array(
            'width'=>'FOG_SERVICE_DISPLAYMANAGER_X',
            'height'=>'FOG_SERVICE_DISPLAYMANAGER_Y',
            'refresh'=>'FOG_SERVICE_DISPLAYMANAGER_R',
        );
        $HostScreen = $this->getClass('HostScreenSettingsManager')->find(array('hostID'=>$this->get('id')));
        $HostScreen = @array_shift($HostScreen);
        $gScreen = $this->FOGCore->getSetting($keyTran[$key]);
        return ($HostScreen instanceof HostScreenSettings && $HostScreen->isValid() ? $HostScreen->get($key) : $gScreen);
    }
    public function setDisp($x,$y,$r) {
        $this->getClass('HostScreenSettingsManager')->destroy(array('hostID'=>$this->get('id')));
        $this->getClass('HostScreenSettings')
            ->set('hostID',$this->get('id'))
            ->set('width',$x)
            ->set('height', $y)
            ->set('refresh',$r)
            ->save();
        return $this;
    }
    public function getAlo() {
        $HostALO = $this->getClass('HostAutoLogoutManager')->find(array('hostID'=>$this->get('id')));
        $HostALO = @array_shift($HostALO);
        $gTime = $this->FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
        return ($HostALO && $HostALO->isValid() ? $HostALO->get('time') : $gTime);
    }
    public function setAlo($time) {
        // Clear Current setting
        $this->getClass('HostAutoLogoutManager')->destroy(array('hostID'=>$this->get('id')));
        // Set new setting
        $this->getClass('HostAutoLogout')
            ->set('hostID',$this->get('id'))
            ->set('time',$time)
            ->save();
        return $this;
    }
    private function loadADPass() {
        if ($this->get('id') && $this->get('ADPass')) $this->set('ADPass',$this->encryptpw($this->get('ADPass')));
    }
    private function loadSnapinJob() {
        if (!$this->isLoaded('snapinjob') && $this->get('id'))
            $this->set('snapinjob',$this->getClass('SnapinJob',@max($this->getClass('SnapinJobManager')->find(array('stateID'=>array(-1,0,1,2,3),'hostID'=>$this->get('id')),'','','','','','','id'))));
    }
    private function loadPrimary() {
        if (!$this->isLoaded('mac') && $this->get('id')) {
            $this->set('mac',$this->getClass('MACAddress',$this->get('primac')->get('mac')));
        }
        return $this;
    }
    private function loadAdditional() {
        if (!$this->isLoaded('additionalMACs') && $this->get('id')) {
            $MACAssocs = $this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>array(null,0,'')),'','','','','','','mac');
            foreach ((array)$MACAssocs AS $i => &$MACAssoc) $this->add('additionalMACs',$this->getClass('MACAddress',$MACAssoc));
            unset($MACAssoc);
        }
        return $this;
    }
    private function loadPending() {
        if (!$this->isLoaded('pendingMACs') && $this->get('id')) $this->set('pendingMACs',$this->getClass('MACAddressAssociationManager')->find(array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>1),'','','','','','','id'));
    }
    private function loadPrinters() {
        if (!$this->isLoaded('printers') && $this->get('id')) {
            // Printers I have
            $PrinterIDs = array_unique($this->getClass('PrinterAssociationManager')->find(array('hostID'=>$this->get('id')),'','','','','','','printerID'));
            $this->set('printers',$PrinterIDs);
            $this->set('printersnotinme',array_unique($this->getClass('PrinterManager')->find(array('id'=>$PrinterIDs),'','','','','',true,'id')));
            unset($PrinterIDs);
        }
    }
    private function loadGroups() {
        if (!$this->isLoaded('groups') && $this->get('id')) {
            // Groups I am in
            $GroupIDs = array_unique($this->getClass('GroupAssociationManager')->find(array('hostID'=>$this->get('id')),'','','','','','','groupID'));
            $this->set('groups',$GroupIDs);
            $this->set('groupsnotinme',array_unique($this->getClass('GroupManager')->find(array('id'=>$GroupIDs),'','','','','',true,'id')));
            unset($GroupIDs);
        }
    }
    private function loadInventory() {
        if (!$this->isLoaded('inventory') && $this->get('id')) $this->set('inventory',$this->getClass('Inventory',@max($this->getClass('InventoryManager')->find(array('hostID'=>$this->get('id')),'','','','','','','id'))));
    }
    private function loadModules() {
        if (!$this->isLoaded('modules') && $this->get('id')) {
            $ModuleIDs = array_unique($this->getClass('ModuleAssociationManager')->find(array('hostID'=>$this->get('id')),'','','','','','','moduleID'));
            $this->set('modules',$ModuleIDs);
            unset($ModuleIDs);
        }
    }
    private function loadSnapins() {
        if ($this->get('id') && !$this->isLoaded('snapins')) {
            $SnapinIDs = array_unique($this->getClass('SnapinAssociationManager')->find(array('hostID'=>$this->get('id')),'','','','','','','snapinID'));
            $this->set('snapins',$SnapinIDs);
            $this->set('snapinsnotinme',array_unique($this->getClass('SnapinManager')->find(array('id'=>$SnapinIDs),'','','','','',true,'id')));
            unset($SnapinIDs);
        }
    }
    private function loadTask() {
        if (!$this->isLoaded('task') && $this->get('id')) {
            $findWhere['hostID'] = $this->get('id');
            $findWhere['stateID'] = array(0,1,2,3);
            if (in_array($_REQUEST['type'],array('up','down'))) $findWhere['typeID'] = ($_REQUEST['type'] == 'up' ? array(2,16) : array(1,8,15,17,24));
            $this->set('task',$this->getClass('Task',@max($this->getClass('TaskManager')->find($findWhere,'','','','','','','id'))));
        }
    }
    private function loadUsers() {
        if (!$this->isLoaded('users') && $this->get('id')) $this->set('users',$this->getClass('UserTrackingManager')->find(array('hostID'=>$this->get('id'),'action'=>array(null,0,1)),'','datetime','','','','','id'));
    }
    private function loadOptimalStorageNode() {
        if (!$this->isLoaded('optimalStorageNode') && $this->get('id') && $this->getImage() && $this->getImage()->isValid()) $this->set('optimalStorageNode',$this->getImage()->getStorageGroup()->getOptimalStorageNode());
    }
    public function getActiveTaskCount() {
        return $this->getClass('TaskManager')->count(array('stateID' => array(1, 2, 3),'hostID' => $this->get('id')));
    }
    public function isValidToImage() {
        return ($this->getImage()->isValid() && $this->getOS()->isValid() && $this->getImage()->getStorageGroup()->isValid() && $this->getImage()->getStorageGroup()->getStorageNode()->isValid());
    }
    public function getOptimalStorageNode() {
        return $this->get('optimalStorageNode');
    }
    public function checkIfExist($taskTypeID) {
        $res = true;
        // TaskType: Variables
        $TaskType = $this->getClass('TaskType',$taskTypeID);
        $isUpload = $TaskType->isUpload();
        // Image: Variables
        $Image = $this->getImage();
        $StorageGroup = $Image->getStorageGroup();
        $StorageNode = ($isUpload ? $StorageGroup->getMasterStorageNode() : $this->getOptimalStorageNode());
        if (!$isUpload)	$this->HookManager->processEvent('HOST_NEW_SETTINGS',array('Host'=>&$this,'StorageNode'=>&$StorageNode,'StorageGroup'=>&$StorageGroup));
        if (!$StorageGroup || !$StorageGroup->isValid()) throw new Exception(_('No Storage Group found for this image'));
        if (!$StorageNode || !$StorageNode->isValid()) throw new Exception(_('No Storage Node found for this image'));
        if (in_array($TaskType->get('id'),array(1,8,15,17))) {
            // FTP
            $this->FOGFTP
                ->set('username',$StorageNode->get('user'))
                ->set('password',$StorageNode->get('pass'))
                ->set('host',$StorageNode->get('ip'));
            if (!$this->FOGFTP->connect() || !$this->FOGFTP->exists('/'.trim($StorageNode->get('ftppath'),'/').'/'.$Image->get('path'))) $res = false;
            $this->FOGFTP->close();
        }
        return $res;
    }
    /** createTasking creates the tasking so I don't have to keep typing it in for each element.
     * @param $taskName the name to assign to the tasking
     * @param $taskTypeID the task type id to set the tasking
     * @param $username the username to associate with the tasking
     * @param $groupID the Storage Group ID to associate with
     * @param $memID the Storage Node ID to associate with
     * @param $imagingTask if the task is an imaging type, defaults as true.
     * @param $shutdown if the task is to be shutdown once completed, defaults as false.
     * @param $passreset if the task is a password reset task, defaults as false.
     * @param $debug if the task is a debug task, defaults as false.
     * @return $Task returns the tasking generated to be saved later
     */
    private function createTasking($taskName, $taskTypeID, $username, $groupID, $memID, $imagingTask = true,$shutdown = false, $passreset = false, $debug = false) {
        $Task = $this->getClass('Task')
            ->set('name',$taskName)
            ->set('createdBy',$username)
            ->set('hostID',$this->get('id'))
            ->set('isForced',0)
            ->set('stateID',1)
            ->set('typeID',$taskTypeID)
            ->set('NFSGroupID',$groupID)
            ->set('NFSMemberID',$memID);
        if ($imagingTask) $Task->set('imageID',$this->getImage()->get('id'));
        if ($shutdown) $Task->set('shutdown',$shutdown);
        if ($debug) $Task->set('isDebug',$debug);
        if ($passreset) $Task->set('passreset',$passreset);
        return $Task;
    }
    /** cancelJobsSnapinsForHost cancels all jobs and tasks that are snapins associate
     * with this particular host
     * @return void
     */
    private function cancelJobsSnapinsForHost() {
        $SnapinJobs = $this->getClass('SnapinJobManager')->find(array('hostID'=>$this->get('id'),'stateID'=>array(-1,0,1,2,3)));
        foreach($SnapinJobs AS $i => &$SJ) {
            $SnapinTasks = $this->getClass('SnapinTaskManager')->find(array('jobID'=>$SJ->get('id'),'stateID'=>array(-1,0,1,2,3)));
            foreach($SnapinTasks AS $i => &$ST) $ST->set('stateID',5)->save();
            unset($ST);
            $SJ->set('stateID',5)->set('return',-9999)->set('details',_('Cancelled due to new tasking'))->save();
        }
        unset($SJ);
    }
    /** createSnapinTasking creates the snapin tasking or taskings as needed
     * @param $snapin usually -1 or the valid snapin identifier, defaults to all snapins (-1)
     * @return void
     */
    private function createSnapinTasking($snapin = -1) {
        // Error Checking
        // If there are no snapins associated to the host fail out.
        try {
            $snapinAssocCount = $this->getClass('SnapinAssociationManager')->count(array('hostID'=>$this->get('id')));
            if (in_array($this->get('task')->get('taskTypeID'),array(12,13)) && !$snapinAssocCount) throw new Exception($this->foglang['SnapNoAssoc']);
            $SnapinJob = $this->getClass('SnapinJob')
                ->set('hostID',$this->get('id'))
                ->set('stateID',0)
                ->set('createdTime',$this->nice_date()->format('Y-m-d H:i:s'));
            // Create Snapin Tasking
            if (!$SnapinJob->save()) throw new Exception(_('Failed to create Snapin Job'));
            if ($snapin == -1) {
                foreach ((array)$this->get('snapins') AS $i => &$Snapin) {
                    $this->getClass('SnapinTask')
                        ->set('jobID',$SnapinJob->get('id'))
                        ->set('stateID',0)
                        ->set('snapinID',$Snapin)
                        ->save();
                }
                unset($Snapin);
            } else {
                $Snapin = $this->getClass('Snapin',$snapin);
                if ($this->getClass('Snapin',$snapin)->isValid()) {
                    $this->getClass('SnapinTask')
                        ->set('jobID',$SnapinJob->get('id'))
                        ->set('stateID',0)
                        ->set('snapinID',$snapin)
                        ->save();
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        return $this;
    }
    // Should be called: createDeployTask
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false) {
        try {
            // Error checking
            if (!$this->isValid()) throw new Exception($this->foglang['HostNotValid']);
            if (!in_array($taskTypeID,array(12,13)) && $this->getActiveTaskCount()) throw new Exception($this->foglang['InTask']);
            // TaskType: Variables
            $TaskType = $this->getClass('TaskType',$taskTypeID);
            // TaskType: Error checking
            if (!$TaskType->isValid()) throw new Exception($this->foglang['TaskTypeNotValid']);
            // Imaging types.
            $imagingTypes = in_array($taskTypeID,array(1,2,8,15,16,17,24));
            // WOL Types:
            $wolTypes = in_array($taskTypeID,array_merge(range(1,11),range(14,24)));
            // Image: Error checking
            if ($imagingTypes) {
                // Image: Variables
                $Image = $this->getImage();
                $StorageGroup = $Image->getStorageGroup();
                $StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $this->getOptimalStorageNode());
                if (!$StorageNode || !$StorageNode->isValid()) $StorageNode = $StorageGroup->getOptimalStorageNode();
                if (!$Image->isValid()) throw new Exception($this->foglang['ImageNotValid']);
                else if (!$Image->getStorageGroup()->isValid()) throw new Exception($this->foglang['ImageGroupNotValid']);
                else if (!$StorageNode || !($StorageNode instanceof StorageNode)) throw new Exception($this->foglang['NoFoundSG']);
                else if (!$StorageNode->isValid()) throw new Exception($this->foglang['SGNotValid']);
                else {
                    $imageTaskImgID = $this->get('imageID');
                    $hostsWithImgID = $this->getClass('HostManager')->find(array('imageID'=>$imageTaskImgID),'','','','','','','id');
                    if (!in_array($this->get('id'),(array)$hostsWithImgID)) $this->set('imageID',$this->getClass('Host',$this->get('id'))->get('imageID'));
                    $this->save();
                    $this->set('imageID',$imageTaskImgID);
                }
            }
            $isUpload = $TaskType->isUpload();
            $username = ($this->FOGUser ? $this->FOGUser->get('name') : ($username ? $username : ''));
            // Task: Create Task Object
            $Task = $this->createTasking($taskName, $taskTypeID, $username, $imagingTypes ? $StorageGroup->get('id') : 0, $imagingTypes ? $StorageGroup->getOptimalStorageNode()->get('id') : 0, $imagingTypes,$shutdown,$passreset,$debug);
            // Task: Save to database
            if (!$Task->save()) {
                $this->FOGCore->logHistory(sprintf('Task failed: Task ID: %s, Task Name: %s, Host ID: %s, HostName: %s, Host MAC: %s',$Task->get('id'),$Task->get('name'),$this->get('id'),$this->get('name'),$this->get('mac')));
                throw new Exception($this->foglang['FailedTask']);
            }
            if ($TaskType->isSnapinTask()) {
                // if deploySnapins is exactly compared with boolean true, set to -1 for all snapins
                if ($deploySnapins === true) $deploySnapins = -1;
                // Cancel any tasks and jobs that the host hasn't completed
                $this->cancelJobsSnapinsForHost();
                // Variables
                $mac = $this->get('mac');
                // Snapin deploy/cancel after deploy
                if ($deploySnapins) $this->createSnapinTasking($deploySnapins);
            }
            if ($Image && $Image->isValid()) $Task->set('imageID',$Image->get('id'));
            // Task: Save to database
            if (!$Task->save()) {
                $this->FOGCore->logHistory(sprintf('Task failed: Task ID: %s, Task Name: %s, Host ID: %s, HostName: %s, Host MAC: %s',$Task->get('id'),$Task->get('name'),$this->get('id'),$this->get('name'),$this->get('mac')));
                throw new Exception($this->foglang['FailedTask']);
            }
            // If task is multicast create the tasking for multicast
            if ($TaskType->isMulticast()) {
                $assoc = false;
                $MultiSessName = current((array)$this->getClass('MulticastSessionsManager')->find(array('name'=>$taskName,'stateID'=>array(0,1,2,3))));
                $MultiSessAssoc = current((array)$this->getClass('MulticastSessionsManager')->find(array('image'=>$this->getImage()->get('id'),'stateID'=>0)));
                if ($sessionjoin && $MultiSessName && $MultiSessName->isValid()) {
                    $MulticastSession = $MultiSessName;
                    $assoc = true;
                } else if ($MultiSessAssoc && $MultiSessAssoc->isValid()) {
                    $MulticastSession = $MultiSessAssoc;
                    $assoc = true;
                } else {
                    $port = $this->FOGCore->getSetting('FOG_UDPCAST_STARTINGPORT');
                    $portOverride = $this->FOGCore->getSetting('FOG_MULTICAST_PORT_OVERRIDE');
                    // Create New Multicast Session Job
                    $MulticastSession = $this->getClass('MulticastSessions')
                        ->set('name',$taskName)
                        ->set('port',($portOverride ? $portOverride : $port))
                        ->set('logpath',$this->getImage()->get('path'))
                        ->set('image',$this->getImage()->get('id'))
                        ->set('interface',$StorageNode->get('interface'))
                        ->set('stateID',0)
                        ->set('starttime',$this->nice_date()->format('Y-m-d H:i:s'))
                        ->set('percent',0)
                        ->set('isDD',$this->getImage()->get('imageTypeID'))
                        ->set('NFSGroupID',$StorageNode->get('storageGroupID'));
                    if ($MulticastSession->save()) {
                        // Sets a new port number so you can create multiple Multicast Tasks.
                        if (!$this->FOGCore->getSetting('FOG_MULTICAST_PORT_OVERRIDE')) {
                            $randomnumber = mt_rand(24576,32766)*2;
                            while ($randomnumber == $MulticastSession->get('port')) $randomnumber = mt_rand(24576,32766)*2;
                            $this->FOGCore->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
                        }
                    }
                    $assoc = true;
                }
                if ($assoc) {
                    $this->getClass('MulticastSessionsAssociation')
                        ->set('msID',$MulticastSession->get('id'))
                        ->set('taskID',$Task->get('id'))
                        ->save();
                }
            }
            // Wake Host
            if ($wolTypes) $this->wakeOnLAN();
            // Log History event
            $this->FOGCore->logHistory(sprintf('Task Created: Task ID: %s, Task Name: %s, Host ID: %s, Host Name: %s, Host MAC: %s, Image ID: %s, Image Name: %s', $Task->get('id'), $Task->get('name'), $this->get('id'), $this->get('name'), $this->get('mac'), $this->getImage()->get('id'), $this->getImage()->get('name')));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        if ($taskTypeID == 14) $Task->destroy();
        return sprintf('<li>%s &ndash; %s</li>',$this->get('name'),$this->getImage()->get('name'));
    }
    public function getImageMemberFromHostID() {
        try {
            $Image = $this->getImage();
            if(!$Image->isValid() || !$Image->get('id')) throw new Exception(_('No Image defined for this host'));
            $StorageGroup = $Image->getStorageGroup();
            if(!$StorageGroup->get('id')) throw new Exception('No StorageGroup defined for this host');
            $Task = $this->getClass('Task')
                ->set('hostID',$this->get('id'))
                ->set('NFSGroupID',$StorageGroup->get('id'))
                ->set('NFSMemberID',$StorageGroup->getOptimalStorageNode()->get('id'))
                ->set('imageID',$Image->get('id'));
        } catch (Exception $e) {
            $this->FOGCore->error(sprintf('%s():xError: %s', __FUNCTION__, $e->getMessage()));
            $Task = false;
        }
        return $Task;
    }
    public function clearAVRecordsForHost() {
        $MACs = $this->getMyMacs();
        $this->getClass('VirusManager')->destroy(array('hostMAC'=>$MACs));
        unset($MACs);
    }
    public function wakeOnLAN() {
        $URLs = array();
        $mac = $this->getMyMacs();
        $Nodes = $this->getClass('StorageNodeManager')->find(array('isEnabled'=>1));
        foreach ($Nodes AS $i => &$Node) {
            $curroot = trim(trim($Node->get('webroot'),'/'));
            $webroot = '/'.(strlen($curroot) > 1 ? $curroot.'/' : '');
            $URLs[] = sprintf('http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s',$Node->get('ip'),$webroot,implode('|',(array)$mac));
        }
        $curroot = trim(trim($this->FOGCore->getSetting('FOG_WEB_ROOT'),'/'));
        $webroot = '/'.(strlen($curroot) > 1 ? $curroot.'/':'');
        $URLs[] = sprintf('http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s',$this->FOGCore->getSetting('FOG_WEB_HOST'),$webroot,implode('|',(array)$mac));
        $this->FOGURLRequests->process($URLs,'GET');
        return $this;
    }
    public function addPrinter($addArray) {
        $Prints = array_unique(array_diff((array)$addArray,(array)$this->get('printers')));
        // Add
        if (count($Prints)) {
            $Prints = array_merge((array)$this->get('printers'),(array)$Prints);
            $this->set('printers',$Prints);
        }
        // Return
        return $this;
    }
    public function removePrinter($removeArray) {
        $Prints = array_unique(array_diff((array)$this->get('printers'),(array)$removeArray));
        if (count($Prints)) $this->set('printers',$Prints);
        // Return
        return $this;
    }
    public function addAddMAC($addArray,$pending = false) {
        if ($pending) foreach((array)$addArray AS $i => &$item) $this->add('pendingMACs', $item);
        else foreach((array)$addArray AS $i => &$item) $this->add('additionalMACs', $item);
        unset($item);
        return $this;
    }
    public function addPendtoAdd($MACs = false) {
        $MAClist = array();
        if (!$MACs) {
            $PendMACs = $this->get('pendingMACs');
            foreach ((array)$PendMACs AS $i => &$MAC) $MAClist[] = $MAC;
        } else {
            $MACs = array_map('strtolower',(array)$MACs);
            $PendMACs = $this->get('pendingMACs');
            foreach ((array)$PendMACs AS $i => &$MAC) if (in_array(strtolower($MAC),$MACs)) $MAClist[] = $MAC;
        }
        unset($MAC);
        $this->addAddMAC($MAClist);
        $this->removePendMAC($MAClist);
        // Return
        return $this;
    }
    public function removeAddMAC($removeArray) {
        foreach((array)$removeArray AS $i => &$item) $this->remove('additionalMACs',$this->getClass('MACAddress',$item));
        unset($item);
        // Return
        return $this;
    }
    public function removePendMAC($removeArray) {
        foreach((array)$removeArray AS $i => &$item) $this->remove('pendingMACs',$this->getClass('MACAddress',$item));
        unset($item);
        // Return
        return $this;
    }
    public function addPriMAC($MAC) {
        $this->set('mac',$MAC);
        return $this;
    }
    public function addPendMAC($MAC) {
        $this->addAddMAC($MAC,true);
        return $this;
    }
    public function addSnapin($addArray) {
        $limit = $this->FOGCore->getSetting('FOG_SNAPIN_LIMIT');
        if ($limit > 0) {
            if ($this->getClass('SnapinManager')->count(array('id'=>$this->get('snapins'))) >= $limit || count($addArray) > $limit) throw new Exception(sprintf('%s %d %s',_('You are only allowed to assign'),$limit,$limit == 1 ? _('snapin per host') : _('snapins per host')));
        }
        $Snaps = array_unique(array_diff((array)$addArray,(array)$this->get('snapins')));
        // Add
        if (count($Snaps)) {
            $Snaps = array_merge((array)$this->get('snapins'),(array)$Snaps);
            $this->set('snapins',$Snaps);
        }
        // Return
        return $this;
    }
    public function removeSnapin($removeArray) {
        $Snaps = array_unique(array_diff((array)$this->get('snapins'),(array)$removeArray));
        if (count($Snaps)) $this->set('snapins',$Snaps);
        // Return
        return $this;
    }
    public function addModule($addArray) {
        $Mods = array_unique(array_diff((array)$addArray,(array)$this->get('modules')));
        // Add
        if (count($Mods)) {
            $Mods = array_merge((array)$this->get('modules'),$Mods);
            $this->set('modules',$Mods);
        }
        // Return
        return $this;
    }
    public function removeModule($removeArray) {
        $Mods = array_unique(array_diff((array)$this->get('modules'),(array)$removeArray));
        // Remove
        if (count($Mods)) $this->set('modules',$Mods);
        // Return
        return $this;
    }
    public function getMyMacs($justme = true) {
        $KnownMacs[] = strtolower($this->get('mac'));
        foreach((array)$this->get('additionalMACs') AS $i => &$MAC) {
            if ($MAC instanceof MACAddress && $MAC->isValid()) $KnownMACs[] = $MAC->__toString();
        }
        unset($MAC);
        foreach((array)$this->get('pendingMACs') AS $i => &$MAC) {
            if ($MAC instanceof MACAddress && $MAC->isValid()) $KnownMacs[] = $MAC->__toString();
        }
        unset($MAC);
        if ($justme) return $KnownMacs;
        $MACs = $this->getClass('MACAddressAssociationManager')->find();
        foreach ((array)$MACs AS $i => &$MAC) {
            $MAC = $this->getClass('MACAddress',$MAC);
            if ($MAC instanceof MACAddress && $MAC->isValid() && !in_array($MAC->__toString(),$KnownMacs)) $KnownMacs[] = $MAC->__toString();
        }
        unset($MAC);
        return array_unique($KnownMacs);
    }
    public function ignore($imageIgnore,$clientIgnore) {
        $MyMACs = $this->getMyMacs();
        $igMACs = $cgMACs = array();
        foreach((array)$imageIgnore AS $i => &$igMAC) {
            $igMAC = $this->getClass('MACAddress',$igMAC);
            if ($igMAC->isValid()) $igMACs[] = $igMAC->__toString();
        }
        unset($igMAC);
        foreach((array)$clientIgnore AS $i => &$cgMAC) {
            $cgMAC = $this->getClass('MACAddress',$cgMAC);
            if ($cgMAC->isValid()) $cgMACs[] = $cgMAC->__toString();
        }
        unset($cgMAC);
        foreach((array)$MyMACs AS $i => &$MAC) {
            $ignore = current((array)$this->getClass('MACAddressAssociationManager')->find(array('mac'=>$MAC,'hostID'=>$this->get('id'))));
            $ME = $this->getClass('MACAddress',$ignore);
            if ($ME->isValid()) {
                $mac = $ME->__toString();
                $ignore->set('imageIgnore',in_array($mac,(array)$igMACs))->save();
                $ignore->set('clientIgnore',in_array($mac,(array)$cgMACs))->save();
            }
        }
        unset($MAC);
    }
    public function addGroup($addArray) {
        return $this->addHost($addArray);
    }
    public function removeGroup($removeArray) {
        return $this->removeHost($removeArray);
    }
    public function addHost($addArray) {
        $Groups = array_unique(array_diff((array)$addArray,(array)$this->get('groups')));
        // Add
        if (count($Groups)) {
            $Groups = array_merge((array)$this->get('groups'),(array)$Groups);
            $this->set('groups',$Groups);
        }
        // Return
        return $this;
    }
    public function removeHost($removeArray) {
        $Groups = array_unique(array_diff((array)$this->get('groups'),(array)$removeArray));
        // Iterate array (or other as array)
        if (count($Groups)) $this->set('groups',$Groups);
        // Return
        return $this;
    }
    public function clientMacCheck($MAC = false) {
        $mac = $this->getClass('MACAddress',current($this->getClass('MACAddressAssociationManager')->find(array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'clientIgnore'=>1))));
        return $mac->isValid() ? 'checked' : '';
    }
    public function imageMacCheck($MAC = false) {
        $mac = $this->getClass('MACAddress',current($this->getClass('MACAddressAssociationManager')->find(array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'imageIgnore'=>1))));
        return $mac->isValid() ? 'checked' : '';
    }
    public function setAD($useAD = '',$domain = '',$ou = '',$user = '',$pass = '',$override = false,$nosave = false,$legacy = '') {
        if ($this->get('id')) {
            if (!$override) {
                if (empty($useAD)) $useAD = $this->get('useAD');
                if (empty($domain))	$domain = trim($this->get('ADDomain'));
                if (empty($ou)) $ou = trim($this->get('ADOU'));
                if (empty($user)) $user = trim($this->get('ADUser'));
                if (empty($pass)) $pass = trim($this->encryptpw($this->get('ADPass')));
                if (empty($legacy)) $legacy = trim($this->get('ADPassLegacy'));
            }
        }
        if ($pass) $pass = trim($this->encryptpw($pass));
        $this->set('useAD',$useAD)
            ->set('ADDomain',trim($domain))
            ->set('ADOU',trim($ou))
            ->set('ADUser',trim($user))
            ->set('ADPass',trim($this->encryptpw($pass)))
            ->set('ADPassLegacy',$legacy);
        if (!$nosave) $this->save();
        return $this;
    }
    public function destroy($field = 'id') {
        // Complete active tasks
        if ($this->get('task')->isValid()) $this->get('task')->set('stateID',5)->save();
        // Remove Snapinjob Associations
        if ($this->get('snapinjob')->isValid()) $this->get('snapinjob')->set('stateID',5)->save();
        $assocs = array(
            'GroupAssociation',
            'ModuleAssociation',
            'MACAddressAssociation',
            'SnapinAssociation',
        );
        foreach ($assocs AS $i => &$AssocRem) $this->getClass($AssocRem)->getManager()->destroy(array('hostID'=>$this->get('id')));
        unset($AssocRem);
        // Update inventory to know when it was deleted
        if ($this->get('inventory')) $this->get('inventory')->set('deleteDate',$this->nice_date()->format('Y-m-d H:i:s'))->save();
        $this->HookManager->processEvent('DESTROY_HOST',array('Host'=>&$this));
        // Return
        return parent::destroy($field);
    }
    // Custom functions
    public function getImage() {
        return $this->getClass('Image',$this->get('imageID'));
    }
    public function getImageName() {
        return $this->get('imagename')->isValid() ? $this->get('imagename')->get('name') : '';
    }
    public function getOS() {
        return $this->getImage()->getOS()->get('name');
    }
    public function getActiveSnapinJob() {
        return $this->get('snapinjob');
    }
    public function getPingCodeStr() {
        if ((int)$this->get('pingstatus') === 0) return '<span class="icon-ping-down fa fa-exclamation-circle fa-1x" style="color: #ce0f0f" title="'._('No Response').'"></span>';
        if ((int)$this->get('pingstatus') === -1) return '<span class="icon-ping-down fa fa-exclamation-circle fa-1x" style="color: #ce0f0f" title="'._('Unable to resolve hostname').'"></span>';
        if ((int)$this->get('pingstatus') === 1) return '<span class="icon-ping-up fa fa-exclamation-circle fa-1x" style="color: #18f008" title="'._('Host Up').'"></span>';
        return '<span class="icon-ping-down fa fa-exclamation-circle fa-1x" style="color: #ce0f0f" title="'._(socket_strerror((int)$this->get('pingstatus'))).'"></span>';
    }
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
