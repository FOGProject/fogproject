<?php
class Host extends FOGController {
    protected $databaseTable = 'hosts';
    protected $databaseFields = array(
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
        'kernelArgs' => 'hostKernelArgs',
        'kernel' => 'hostKernel',
        'kernelDevice' => 'hostDevice',
        'init' => 'hostInit',
        'pending' => 'hostPending',
        'pub_key' => 'hostPubKey',
        'sec_tok' => 'hostSecToken',
        'sec_time' => 'hostSecTime',
        'pingstatus' => 'hostPingCode',
        'biosexit' => 'hostExitBios',
        'efiexit' => 'hostExitEfi',
        'enforce' => 'hostEnforce',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    protected $additionalFields = array(
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
        'inventory',
        'task',
        'snapinjob',
        'users',
        'fingerprint',
        'powermanagementtasks',
    );
    protected $databaseFieldClassRelationships = array(
        'MACAddressAssociation' => array('hostID','id','primac',array('primary'=>1)),
        'Image' => array('id','imageID','imagename'),
    );
    private $arrayKeys = array(
        'additionalMACs',
        'pendingMACs',
        'groups',
        'groupsnotinme',
        'powermanagementtasks',
        'printers',
        'printersnotinme',
        'snapins',
        'snapinsnotinme',
        'modules',
        'users',
    );
    public function set($key, $value) {
        $key = $this->key($key);
        switch ($key) {
        case 'mac':
            if (!($value instanceof MACAddress)) $value = self::getClass('MACAddress',$value);
            break;
        case 'additionalMACs':
        case 'pendingMACs':
            $newValue = array_map(function(&$mac) {
                return self::getClass('MACAddress',$mac);
            },(array)$value);
            $value = (array)$newValue;
            break;
        case 'snapinjob':
            if (!($value instanceof SnapinJob)) $value = self::getClass('SnapinJob',$value);
            break;
        case 'inventory':
            if (!($value instanceof Inventory)) $value = self::getClass('Inventory',$value);
            break;
        case 'task':
            if (!($value instanceof Task)) $value = self::getClass('Task',$value);
            break;
        }
        return parent::set($key, $value);
    }
    public function add($key, $value) {
        $key = $this->key($key);
        switch ($key) {
        case 'additionalMACs':
        case 'pendingMACs':
            if (!($value instanceof MACAddress)) $value = self::getClass('MACAddress',$value);
            break;
        }
        return parent::add($key,$value);
    }
    public function destroy($field = 'id') {
        $find = array('hostID'=>$this->get('id'));
        self::getClass('NodeFailureManager')->destroy($find);
        self::getClass('ImagingLogManager')->destroy($find);
        self::getClass('SnapinTaskManager')->destroy(array('jobID'=>self::getSubObjectIDs('SnapinJob',$find,'id')));
        self::getClass('SnapinJobManager')->destroy($find);
        self::getClass('TaskManager')->destroy($find);
        self::getClass('ScheduledTaskManager')->destroy($find);
        self::getClass('HostAutoLogoutManager')->destroy($find);
        self::getClass('HostScreenSettingsManager')->destroy($find);
        self::getClass('GroupAssociationManager')->destroy($find);
        self::getClass('SnapinAssociationManager')->destroy($find);
        self::getClass('PrinterAssociationManager')->destroy($find);
        self::getClass('ModuleAssociationManager')->destroy($find);
        self::getClass('GreenFogManager')->destroy($find);
        self::getClass('InventoryManager')->destroy($find);
        self::getClass('UserTrackingManager')->destroy($find);
        self::getClass('MACAddressAssociationManager')->destroy($find);
        self::getClass('PowerManagementManager')->destroy($find);
        return parent::destroy($field);
    }
    public function save() {
        parent::save();
        switch (true) {
        case ($this->isLoaded('mac')):
            if (!$this->get('mac')->isValid()) throw new Exception(self::$foglang['InvalidMAC']);
            $RealPriMAC = $this->get('mac')->__toString();
            $CurrPriMAC = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            if (count($CurrPriMAC) === 1 && $CurrPriMAC[0] != $RealPriMAC) self::getClass('MACAddressAssociationManager')->update(array('mac'=>$CurrPriMAC[0],'hostID'=>$this->get('id'),'primary'=>1),'',array('primary'=>0));
            $HostWithMAC = array_diff((array)$this->get('id'),(array)self::getSubObjectIDs('MACAddressAssociation',array('mac'=>$RealPriMAC),'hostID'));
            if (count($HostWithMAC) && !in_array($this->get('id'),(array)$HostWithMAC)) throw new Exception(_('This MAC Belongs to another host'));
            $DBPriMACs = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            $RemoveMAC = array_diff((array)$RealPriMAC,(array)$DBPriMACs);
            if (count($RemoveMAC)) {
                self::getClass('MACAddressAssociationManager')->destroy(array('mac'=>$RemoveMAC));
                unset($RemoveMAC);
                $DBPriMACs = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            }
            if (!in_array($RealPriMAC,$DBPriMACs)) {
                self::getClass('MACAddressAssociation')
                    ->set('hostID',$this->get('id'))
                    ->set('mac',$RealPriMAC)
                    ->set('primary',1)
                    ->save();
            }
            unset($DBPriMACs,$RealPriMAC,$RemoveMAC,$HostWithMAC);
        case ($this->isLoaded('additionalMACs')):
            $RealAddMACs = array_values(array_unique(array_filter(array_map(function(&$MAC) {
                if ($MAC instanceof MACAddress && $MAC->isValid()) return $MAC->__toString();
            },(array)$this->get('additionalMACs')))));
            $DBPriMACs = self::getSubObjectIDs('MACAddressAssociation',array('primary'=>1),'mac');
            array_map(function(&$MAC) use ($RealAddMACs) {
                if ($this->array_strpos($MAC,$RealAddMACs) !== false) throw new Exception(_('Cannot add Primary mac as additional mac'));
                unset($MAC);
            },(array)$DBPriMACs);
            unset($DBPriMACs);
            $PreOwnedMACs = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'pending'=>(string)1),'mac',true);
            $RealAddMACs = array_diff((array)$RealAddMACs,(array)$PreOwnedMACs);
            unset($PreOwnedMACs);
            $DBAddMACs = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array((string)0,(string)'',null),'pending'=>array((string)0,(string)'',null)),'mac');
            $RemoveAddMAC = array_diff((array)$DBAddMACs,(array)$RealAddMACs);
            if (count($RemoveAddMAC)) {
                self::getClass('MACAddressAssociationManager')->destroy(array('hostID'=>$this->get('id'),'mac'=>$RemoveAddMAC));
                $DBAddMACs = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array((string)0,(string)'',null),'pending'=>array((string)0,(string)'',null)),'mac');
                unset($RemoveAddMAC);
            }
            $insert_fields = array('hostID','mac','primary','pending');
            $insert_values = array();
            $RealAddMACs = array_diff((array)$RealAddMACs,(array)$DBAddMACs);
            array_walk($RealAddMACs,function(&$mac,$index) use (&$insert_values) {
                $insert_values[] = array($this->get('id'),$mac,'0','0');
            });
            if (count($insert_values) > 0) self::getClass('MACAddressAssociationManager')->insert_batch($insert_fields,$insert_values);
            unset($DBAddMACs,$RealAddMACs,$RemoveAddMAC);
        case ($this->isLoaded('pendingMACs')):
            $RealPendMACs = array_map(function(&$MAC) {
                if ($MAC instanceof MACAddress && $MAC->isValid()) return $MAC->__toString();
            },(array)$this->get('pendingMACs'));
            $DBPriMACs = self::getSubObjectIDs('MACAddressAssociation',array('primary'=>1),'mac');
            array_map(function(&$DBPriMAC) use ($RealPendMACs) {
                if ($this->array_strpos($DBPriMAC,$RealPendMACs) !== false) throw new Exception(_('Cannot add a pre-existing Primary MAC as a pending MAC'));
                unset($DBPriMAC);
            },(array)$DBPriMACs);
            unset($DBPriMACs);
            $PreOwnedMACs = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'pending'=>array((string)0,(string)'',null)),'mac',true);
            $RealPendMACs = array_diff((array)$RealPendMACs,(array)$PreOwnedMACs);
            unset($PreOwnedMACs);
            $DBPendMACs = self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array((string)0,(string)'',null),'pending'=>(string)1),'mac');
            $RemovePendMAC = array_diff((array)$DBPendMACs,(array)$RealPendMACs);
            if (count($RemovePendMAC)) {
                self::getClass('MACAddressAssociationManager')->destroy(array('hostID'=>$this->get('id'),'mac'=>$RemovePendMAC));
                $DBPendMACs = self::getSubObjectIDs('MACAddressAssociation',array('primary'=>array((string)0,(string)'',null),'pending'=>(string)1),'mac');
                unset($RemovePendMAC);
            }
            $insert_fields = array('hostID','mac','primary','pending');
            $insert_values = array();
            $RealPendMACs = array_diff((array)$RealPendMACs,(array)$DBPendMACs);
            array_walk($RealPendMACs,function(&$mac,$index) use (&$insert_values) {
                $insert_values[] = array($this->get('id'),$mac,'0','1');
            });
            if (count($insert_values) > 0) self::getClass('MACAddressAssociationManager')->insert_batch($insert_fields,$insert_values);
            unset($DBPendMACs,$RealPendMACs,$RemovePendMAC);
        case ($this->isLoaded('modules')):
            $DBModuleIDs = self::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
            $ValidModuleIDs = self::getSubObjectIDs('Module');
            $notValid = array_diff((array)$DBModuleIDs,(array)$ValidModuleIDs);
            if (count($notValid)) self::getClass('ModuleAssociationManager')->destroy(array('moduleID'=>$notValid));
            unset($ValidModuleIDs,$DBModuleIDs);
            $DBModuleIDs = self::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
            $RemoveModuleIDs = array_diff((array)$DBModuleIDs,(array)$this->get('modules'));
            if (count($RemoveModuleIDs)) {
                self::getClass('ModuleAssociationManager')->destroy(array('moduleID'=>$RemoveModuleIDs,'hostID'=>$this->get('id')));
                $DBModuleIDs = self::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
                unset($RemoveModuleIDs);
            }
            $moduleName = $this->getGlobalModuleStatus();
            $insert_fields = array('hostID','moduleID','state');
            $insert_values = array();
            $DBModuleIDs = array_diff((array)$this->get('modules'),(array)$DBModuleIDs);
            array_walk($DBModuleIDs,function(&$moduleID,$index) use (&$insert_values) {
                $insert_values[] = array($this->get('id'),$moduleID,'1');
            });
            if (count($insert_values)) self::getClass('ModuleAssociationManager')->insert_batch($insert_fields,$insert_values);
            unset($DBModuleIDs,$RemoveModuleIDs,$moduleName);
        case ($this->isLoaded('printers')):
            $DBPrinterIDs = self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
            $ValidPrinterIDs = self::getSubObjectIDs('Printer');
            $notValid = array_diff((array)$DBPrinterIDs,(array)$ValidPrinterIDs);
            if (count($notValid)) self::getClass('PrinterAssociationManager')->destroy(array('printerID'=>$notValid));
            unset($ValidPrinterIDs,$DBPrinterIDs);
            $DBPrinterIDs = self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
            $RemovePrinterIDs = array_diff((array)$DBPrinterIDs,(array)$this->get('printers'));
            if (count($RemovePrinterIDs)) {
                self::getClass('PrinterAssociationManager')->destroy(array('hostID'=>$this->get('id'),'printerID'=>$RemovePrinterIDs));
                $DBPrinterIDs = self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
                unset($RemovePrinterIDs);
            }
            $insert_fields = array('hostID','printerID');
            $insert_values = array();
            $DBPrinterIDs = array_diff((array)$this->get('printers'),(array)$DBPrinterIDs);
            array_walk($DBPrinterIDs,function(&$printerID,$index) use (&$insert_values) {
                $insert_values[] = array($this->get('id'),$printerID);
            });
            if (count($insert_values) > 0) self::getClass('PrinterAssociationManager')->insert_batch($insert_fields,$insert_values);
            unset($DBPrinterIDs,$RemovePrinterIDs);
        case ($this->isLoaded('powermanagementtasks')):
            $DBPowerManagementIDs = self::getSubObjectIDs('PowerManagement',array('hostID'=>$this->get('id')));
            $RemovePowerManagementIDs = array_diff((array)$DBPowerManagementIDs,(array)$this->get('powermanagementtasks'));
            if (count($RemovePowerManagementIDs)) {
                self::getClass('PowerManagementManager')->destroy(array('hostID'=>$this->get('id'),'id'=>$RemovePowerManagementIDs));
                $DBPowerManagementIDs = self::getSubObjectIDs('PowerManagement',array('hostID'=>$this->get('id')));
                unset($RemovePowerManagementIDs);
            }
            $objNeeded = false;
            unset($DBPowerManagementIDs,$RemovePowerManagementIDs);
        case ($this->isLoaded('snapins')):
            $DBSnapinIDs = self::getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
            $ValidSnapinIDs = self::getSubObjectIDs('Snapin');
            $notValid = array_diff((array)$DBSnapinIDs,(array)$ValidSnapinIDs);
            if (count($notValid)) self::getClass('SnapinAssociationManager')->destroy(array('snapinID'=>$notValid));
            unset($ValidSnapinIDs,$DBSnapinIDs);
            $DBSnapinIDs = self::getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
            $RemoveSnapinIDs = array_diff((array)$DBSnapinIDs,(array)$this->get('snapins'));
            if (count($RemoveSnapinIDs)) {
                self::getClass('SnapinAssociationManager')->destroy(array('hostID'=>$this->get('id'),'snapinID'=>$RemoveSnapinIDs));
                $DBSnapinIDs = self::getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
                unset($RemoveSnapinIDs);
            }
            $insert_fields = array('hostID','snapinID');
            $insert_values = array();
            $DBSnapinIDs = array_diff((array)$this->get('snapins'),(array)$DBSnapinIDs);
            array_walk($DBSnapinIDs,function(&$snapinID,$index) use (&$insert_values) {
                $insert_values[] = array($this->get('id'),$snapinID);
            });
            if (count($insert_values) > 0) self::getClass('SnapinAssociationManager')->insert_batch($insert_fields,$insert_values);
            unset($DBSnapinIDs,$RemoveSnapinIDs);
        case ($this->isLoaded('groups')):
            $DBGroupIDs = self::getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
            $ValidGroupIDs = self::getSubObjectIDs('Group');
            $notValid = array_diff((array)$DBGroupIDs,(array)$ValidGroupIDs);
            if (count($notValid)) self::getClass('GroupAssociationManager')->destroy(array('groupID'=>$notValid));
            unset($ValidGroupIDs,$DBGroupIDs);
            $DBGroupIDs = self::getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
            $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('groups'));
            if (count($RemoveGroupIDs)) {
                self::getClass('GroupAssociationManager')->destroy(array('hostID'=>$this->get('id'),'groupID'=>$RemoveGroupIDs));
                $DBGroupIDs = self::getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
                unset($RemoveGroupIDs);
            }
            $insert_fields = array('hostID','groupID');
            $insert_values = array();
            $DBGroupIDs = array_diff((array)$this->get('groups'),(array)$DBGroupIDs);
            array_walk($DBGroupIDs,function(&$groupID,$index) use (&$insert_values) {
                $insert_values[] = array($this->get('id'),$groupID);
            });
            if (count($insert_values) > 0) self::getClass('GroupAssociationManager')->insert_batch($insert_fields,$insert_values);
            unset($DBGroupIDs,$RemoveGroupIDs);
        }
        return $this;
    }
    public function isValid() {
        return parent::isValid() && $this->isHostnameSafe();
    }
    public function isHostnameSafe($hostname = '') {
        if (empty($hostname)) $hostname = $this->get('name');
        $pattern = '/^[\\w!@#$%^()\\-\'{}\\.~]{1,15}$/';
        return (bool)preg_match($pattern,$hostname);
    }
    public function getDefault($printerid) {
        return (bool)count(self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id'),'printerID'=>$printerid,'isDefault'=>1),'printerID'));
    }
    public function updateDefault($printerid,$onoff) {
        self::getClass('PrinterAssociationManager')->update(array('printerID'=>$this->get('printers'),'hostID'=>$this->get('id')),'',array('isDefault'=>0));
        self::getClass('PrinterAssociationManager')->update(array('printerID'=>$printerid,'hostID'=>$this->get('id')),'',array('isDefault'=>$onoff));
        return $this;
    }
    public function getDispVals($key = '') {
        $keyTran = array(
            'width'=>'FOG_CLIENT_DISPLAYMANAGER_X',
            'height'=>'FOG_CLIENT_DISPLAYMANAGER_Y',
            'refresh'=>'FOG_CLIENT_DISPLAYMANAGER_R',
        );
        $HostScreen = self::getClass('HostScreenSettingsManager')->find(array('hostID'=>$this->get('id')));
        $HostScreen = array_shift($HostScreen);
        $gScreen = self::getSetting($keyTran[$key]);
        return ($HostScreen instanceof HostScreenSettings && $HostScreen->isValid() ? $HostScreen->get($key) : $gScreen);
    }
    public function setDisp($x,$y,$r) {
        self::getClass('HostScreenSettingsManager')->destroy(array('hostID'=>$this->get('id')));
        self::getClass('HostScreenSettings')
            ->set('hostID',$this->get('id'))
            ->set('width',$x)
            ->set('height',$y)
            ->set('refresh',$r)
            ->save();
        return $this;
    }
    public function getAlo() {
        $HostALO = self::getClass('HostAutoLogoutManager')->find(array('hostID'=>$this->get('id')));
        $HostALO = array_shift($HostALO);
        $gTime = self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN');
        return ($HostALO && $HostALO->isValid() ? $HostALO->get('time') : $gTime);
    }
    public function setAlo($time) {
        self::getClass('HostAutoLogoutManager')->destroy(array('hostID'=>$this->get('id')));
        self::getClass('HostAutoLogout')
            ->set('hostID',$this->get('id'))
            ->set('time',$time)
            ->save();
        return $this;
    }
    protected function loadMac() {
        $this->set('mac',self::getClass('MACAddress',$this->get('primac')));
    }
    protected function loadAdditionalMACs() {
        $this->set('additionalMACs',self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>array(null,0,'')),'mac'));
    }
    protected function loadPendingMACs() {
        $this->set('pendingMACs',self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>1),'mac'));
    }
    protected function loadGroups() {
        $this->set('groups',self::getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID'));
    }
    protected function loadGroupsnotinme() {
        $find = array('id'=>$this->get('groups'));
        $this->set('groupsnotinme',self::getSubObjectIDs('Group',$find,'id',true));
        unset($find);
    }
    protected function loadPrinters() {
        $this->set('printers',self::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID'));
    }
    protected function loadPrintersnotinme() {
        $find = array('id'=>$this->get('printers'));
        $this->set('printersnotinme',self::getSubObjectIDs('Printer',$find,'id',true));
        unset($find);
    }
    protected function loadSnapins() {
        $this->set('snapins',self::getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID'));
    }
    protected function loadSnapinsnotinme() {
        $find = array('id'=>$this->get('snapins'));
        $this->set('snapinsnotinme',self::getSubObjectIDs('Snapin',$find,'id',true));
        unset($find);
    }
    protected function loadModules() {
        $this->set('modules',self::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID'));
    }
    protected function loadPowermanagementtasks() {
        $this->set('powermanagementtasks',self::getSubObjectIDs('PowerManagement',array('hostID'=>$this->get('id'))));
    }
    protected function loadUsers() {
        $this->set('users',self::getSubObjectIDs('UserTracking',array('hostID'=>$this->get('id'))));
    }
    protected function loadSnapinjob() {
        $this->set('snapinjob',@max(self::getSubObjectIDs('SnapinJob',array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()),'hostID'=>$this->get('id')),'id')));
    }
    protected function loadInventory() {
        $this->set('inventory',@max(self::getSubObjectIDs('Inventory',array('hostID'=>$this->get('id')),'id')));
    }
    protected function loadTask() {
        $find['hostID'] = $this->get('id');
        $find['stateID'] = array_merge($this->getQueuedStates(),(array)$this->getProgressState());
        if (in_array($_REQUEST['type'], array('up','down'))) $find['typeID'] = ($_REQUEST['type'] == 'up' ? array(2,16) : array(1,8,15,17,24));
        $this->set('task',@max(self::getSubObjectIDs('Task',$find,'id')));
        unset($find);
    }
    protected function loadOptimalStorageNode() {
        $this->set('optimalStorageNode', self::getClass('Image',$this->get('imageID'))->getStorageGroup()->getOptimalStorageNode($this->get('imageID')));
    }
    public function getActiveTaskCount() {
        return self::getClass('TaskManager')->count(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()),'hostID'=>$this->get('id')));
    }
    public function isValidToImage() {
        return ($this->getImage()->isValid() && $this->getOS()->isValid() && $this->getImage()->getStorageGroup()->isValid() && $this->getImage()->getStorageGroup()->getStorageNode()->isValid());
    }
    public function getOptimalStorageNode() {
        return $this->get('optimalStorageNode');
    }
    public function checkIfExist($taskTypeID) {
        $TaskType = self::getClass('TaskType',$taskTypeID);
        $isUpload = $TaskType->isUpload();
        $Image = $this->getImage();
        $StorageGroup = $Image->getStorageGroup();
        $StorageNode = $StorageGroup->getMasterStorageNode();
        self::$HookManager->processEvent('HOST_NEW_SETTINGS',array('Host'=>&$this,'StorageNode'=>&$StorageNode,'StorageGroup'=>&$StorageGroup));
        if (!$StorageGroup || !$StorageGroup->isValid()) throw new Exception(_('No Storage Group found for this image'));
        if (!$StorageNode || !$StorageNode->isValid()) throw new Exception(_('No Storage Node found for this image'));
        if (!in_array($TaskType->get('id'),array(1,8,15,17,24))) return true;
        if (!in_array($Image->get('id'),$StorageNode->get('images'))) {
            throw new Exception(sprintf('%s: %s',_('Image not found on node'),$StorageNode->get('name')));
            return false;
        }
        return true;
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
    private function createTasking($taskName, $taskTypeID, $username, $groupID, $memID, $imagingTask = true,$shutdown = false, $passreset = false, $debug = false,$wol = false) {
        $Task = self::getClass('Task')
            ->set('name',$taskName)
            ->set('createdBy',$username)
            ->set('hostID',$this->get('id'))
            ->set('isForced',0)
            ->set('stateID',$this->getQueuedState())
            ->set('typeID',$taskTypeID)
            ->set('NFSGroupID',$groupID)
            ->set('NFSMemberID',$memID)
            ->set('wol',(string)intval($wol));
        if ($imagingTask) $Task->set('imageID',$this->getImage()->get('id'));
        if ($shutdown) $Task->set('shutdown',$shutdown);
        if ($debug) $Task->set('isDebug',$debug);
        if ($passreset) $Task->set('passreset',$passreset);
        return $Task;
    }
    private function cancelJobsSnapinsForHost() {
        $SnapinJobs = self::getSubObjectIDs('SnapinJob',array('hostID'=>$this->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())));
        self::getClass('SnapinTaskManager')->update(array('jobID'=>$SnapinJobs,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())),'',array('return'=>-9999,'details'=>_('Cancelled due to new tasking.')));
        self::getClass('SnapinJobManager')->update(array('id'=>$SnapinJobs),'',array('stateID'=>$this->getCancelledState()));
    }
    private function createSnapinTasking($snapin = -1) {
        try {
            if (count($this->get('snapins')) < 1) return $this;
            $SnapinJob = self::getClass('SnapinJob')
                ->set('hostID',$this->get('id'))
                ->set('stateID',$this->getQueuedState())
                ->set('createdTime',self::nice_date()->format('Y-m-d H:i:s'));
            if (!$SnapinJob->save()) throw new Exception(_('Failed to create Snapin Job'));
            if ($snapin == -1) {
                array_map(function(&$Snapin) {
                    self::getClass('SnapinTask')
                        ->set('jobID',$this->get('snapinjob')->get('id'))
                        ->set('stateID',$this->getQueuedState())
                        ->set('snapinID',$Snapin)
                        ->save();
                    unset($Snapin);
                },(array)$this->get('snapins'));
                return $this;
            }
            $Snapin = self::getClass('Snapin',$snapin);
            if (!$Snapin->isValid()) throw new Exception(_('Snapin is not valid'));
            self::getClass('SnapinTask')
                ->set('jobID',$SnapinJob->get('id'))
                ->set('stateID',$this->getQueuedState())
                ->set('snapinID',$snapin)
                ->save();
            unset($Snapin);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        return $this;
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false,$wol = false) {
        try {
            if (!$this->isValid()) throw new Exception(self::$foglang['HostNotValid']);
            $TaskType = self::getClass('TaskType',$taskTypeID);
            if (!$TaskType->isValid()) throw new Exception(self::$foglang['TaskTypeNotValid']);
            if (!$TaskType->isSnapinTasking() && $this->getActiveTaskCount()) throw new Exception(self::$foglang['InTask']);
            $imagingTypes = in_array($taskTypeID,array(1,2,8,15,16,17,24));
            if ($imagingTypes) {
                $Image = $this->getImage();
                if (!$Image->isValid()) throw new Exception(self::$foglang['ImageNotValid']);
                if (!$Image->get('isEnabled')) throw new Exception(_('Image is not enabled'));
                $StorageGroup = $Image->getStorageGroup();
                if (!$StorageGroup->isValid()) throw new Exception(self::$foglang['ImageGroupNotValid']);
                $StorageNode = ($TaskType->isUpload() ? $StorageGroup->getMasterStorageNode() : $this->getOptimalStorageNode($this->get('imageID')));
                if (!$StorageNode->isValid()) $StorageNode = $StorageGroup->getOptimalStorageNode($this->get('imageID'));
                if (!$StorageNode->isValid()) throw new Exception(_('Could not find any nodes containing this image'));
                $imageTaskImgID = $this->get('imageID');
                $hostsWithImgID = self::getSubObjectIDs('Host',array('imageID'=>$imageTaskImgID));
                $realImageID = self::getSubObjectIDs('Host',array('id'=>$this->get('id')),'imageID');
                if (!in_array($this->get('id'),(array)$hostsWithImgID)) $this->set('imageID',array_shift($realImageID))->save();
                $this->set('imageID',$imageTaskImgID);
            }
            $isUpload = $TaskType->isUpload();
            $username = ($username ? $username : $_SESSION['FOG_USERNAME']);
            $Task = $this->createTasking($taskName, $taskTypeID, $username, $imagingTypes ? $StorageGroup->get('id') : 0, $imagingTypes ? $StorageNode->get('id') : 0, $imagingTypes,$shutdown,$passreset,$debug,$wol);
            $Task->set('imageID',$this->get('imageID'));
            if (!$Task->save()) throw new Exception(self::$foglang['FailedTask']);
            if ($TaskType->isSnapinTask()) {
                if ($deploySnapins === true) $deploySnapins = -1;
                $this->cancelJobsSnapinsForHost();
                $mac = $this->get('mac');
                if ($deploySnapins) $this->createSnapinTasking($deploySnapins);
            }
            if ($TaskType->isMulticast()) {
                $multicastTaskReturn = function(&$MulticastSessions) {
                        if (!$MulticastSessions->isValid()) return;
                        return $MulticastSessions;
                };
                $assoc = false;
                if ($sessionjoin) {
                    $MultiSessJoin = array_values(array_filter(array_map($multicastTaskReturn,(array)self::getClass('MulticastSessionsManager')->find(array('name'=>$taskName,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))))));
                    $MulticastSession = array_shift($MultiSessJoin);
                    $assoc = true;
                } else {
                    $MultiSessJoin = array_values(array_filter(array_map($multicastTaskReturn,(array)self::getClass('MulticastSessionsManager')->find(array('image'=>$this->getImage()->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))))));
                }
                if (count($MultiSessJoin)) $MulticastSession = array_shift($MultiSessJoin);
                if ($MulticastSession instanceof MulticastSessions && $MulticastSession->isValid()) $assoc = true;
                if (!($MulticastSession instanceof MulticastSessions && $MulticastSession->isValid())) {
                    $port = self::getSetting('FOG_UDPCAST_STARTINGPORT');
                    $portOverride = self::getSetting('FOG_MULTICAST_PORT_OVERRIDE');
                    $MulticastSession = self::getClass('MulticastSessions')
                        ->set('name',$taskName)
                        ->set('port',($portOverride ? $portOverride : $port))
                        ->set('logpath',$this->getImage()->get('path'))
                        ->set('image',$this->getImage()->get('id'))
                        ->set('interface',$StorageNode->get('interface'))
                        ->set('stateID',0)
                        ->set('starttime',self::nice_date()->format('Y-m-d H:i:s'))
                        ->set('percent',0)
                        ->set('isDD',$this->getImage()->get('imageTypeID'))
                        ->set('NFSGroupID',$StorageNode->get('storageGroupID'));
                    if ($MulticastSession->save()) {
                        $assoc = true;
                        if (!self::getSetting('FOG_MULTICAST_PORT_OVERRIDE')) {
                            $randomnumber = mt_rand(24576,32766)*2;
                            while ($randomnumber == $MulticastSession->get('port')) $randomnumber = mt_rand(24576,32766)*2;
                            $this->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
                        }
                    }
                }
                if ($assoc) {
                    self::getClass('MulticastSessionsAssociation')
                        ->set('msID',$MulticastSession->get('id'))
                        ->set('taskID',$Task->get('id'))
                        ->save();
                }
            }
            if ($wol) $this->wakeOnLAN();
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
            if (!$Image->get('isEnabled')) throw new Exception(_('Image is not enabled'));
            $StorageGroup = $Image->getStorageGroup();
            if(!$StorageGroup->get('id')) throw new Exception('No StorageGroup defined for this host');
            $Task = self::getClass('Task')
                ->set('hostID',$this->get('id'))
                ->set('NFSGroupID',$StorageGroup->get('id'))
                ->set('NFSMemberID',$StorageGroup->getOptimalStorageNode($this->get('imageID'))->get('id'))
                ->set('imageID',$Image->get('id'));
        } catch (Exception $e) {
            self::$FOGCore->error(sprintf('%s():xError: %s', __FUNCTION__, $e->getMessage()));
            $Task = false;
        }
        return $Task;
    }
    public function clearAVRecordsForHost() {
        $MACs = $this->getMyMacs();
        self::getClass('VirusManager')->destroy(array('hostMAC'=>$MACs));
        unset($MACs);
    }
    public function wakeOnLAN() {
        $URLs = array();
        $URLs = array_map(function(&$Node) {
            $curroot = trim(trim($Node->get('webroot'),'/'));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            return sprintf('http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s',$Node->get('ip'),$webroot,implode('|',(array)$this->getMyMacs()));
        },(array)self::getClass('StorageNodeManager')->find(array('isEnabled'=>1)));
        $curroot = trim(trim(self::getSetting('FOG_WEB_ROOT'),'/'));
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        $URLs[] = sprintf('http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s',self::getSetting('FOG_WEB_HOST'),$webroot,implode('|',(array)$this->getMyMacs()));
        $URLs = array_values(array_filter(array_unique((array)$URLs)));
        self::$FOGURLRequests->process($URLs);
        return $this;
    }
    public function addAddMAC($addArray,$pending = false) {
        $addArray = array_map(function(&$item) {
            return trim(strtolower($item));
        },(array)$addArray);
        $addTo = $pending ? 'pendingMACs' : 'additionalMACs';
        $pushItem = function(&$item) use (&$addTo) {
            $this->add($addTo,$item);
            unset($item);
        };
        array_map($pushItem,(array)$addArray);
        return $this;
    }
    public function addPendtoAdd($MACs = false) {
        $lowerAndTrim = function(&$MAC) {
            return trim(strtolower($MAC));
        };
        $PendMACs = array_map($lowerAndTrim,(array)$this->get('pendingMACs'));
        $MACs = array_map($lowerAndTrim,(array)$MACs);
        $matched = ($MACs === false ? array_intersect((array)$PendMACs,(array)$MACs) : $PendMACs);
        unset($MACs,$PendMACs);
        return $this->addAddMAC($matched)->removePendMAC($matched);
    }
    public function removeAddMAC($removeArray) {
        array_map(function(&$item) {
            $item = $item instanceof MACAddress ? $item : self::getClass('MACAddress',$item);
            $this->remove('additionalMACs',$item);
            unset($item);
        },(array)$removeArray);
        return $this;
    }
    public function removePendMAC($removeArray) {
        array_map(function(&$item) {
            $item = $item instanceof MACAddress ? $item : self::getClass('MACAddress',$item);
            $this->remove('pendingMACs',$item);
            unset($item);
        },(array)$removeArray);
        return $this;
    }
    public function addPriMAC($MAC) {
        return $this->set('mac',$MAC);
    }
    public function addPendMAC($MAC) {
        return $this->addAddMAC($MAC,true);
    }
    public function addPrinter($addArray) {
        return $this->set('printers',array_unique(array_merge((array)$this->get('printers'),(array)$addArray)));
    }
    public function removePrinter($removeArray) {
        return $this->set('printers',array_unique(array_diff((array)$this->get('printers'),(array)$removeArray)));
    }
    public function addSnapin($addArray) {
        $limit = self::getSetting('FOG_SNAPIN_LIMIT');
        if ($limit > 0) {
            if (self::getClass('SnapinManager')->count(array('id'=>$this->get('snapins'))) >= $limit || count($addArray) > $limit) throw new Exception(sprintf('%s %d %s',_('You are only allowed to assign'),$limit,$limit == 1 ? _('snapin per host') : _('snapins per host')));
        }
        return $this->set('snapins',array_unique(array_merge((array)$this->get('snapins'),(array)$addArray)));
    }
    public function removeSnapin($removeArray) {
        return $this->set('snapins',array_unique(array_diff((array)$this->get('snapins'),(array)$removeArray)));
    }
    public function addModule($addArray) {
        return $this->set('modules',array_unique(array_merge((array)$this->get('modules'),(array)$addArray)));
    }
    public function removeModule($removeArray) {
        return $this->set('modules',array_unique(array_diff((array)$this->get('modules'),(array)$removeArray)));
    }
    public function addPowerManagement($addArray) {
        return $this->set('powermanagementtasks',array_unique(array_merge((array)$this->get('powermanagementtasks'),(array)$addArray)));
    }
    public function removePowerManagement($removeArray) {
        return $this->set('powermanagementtasks',array_unique(array_diff((array)$this->get('powermanagementtasks'),(array)$removeArray)));
    }
    public function getMyMacs($justme = true) {
        if ($justme) return self::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id')),'mac');
        return self::getSubObjectIDs('MACAddressAssociation','','mac');
    }
    public function ignore($imageIgnore,$clientIgnore) {
        $MyMACs = $this->getMyMacs();
        $myMACs = $igMACs = $cgMACs = array();
        $macaddress = function(&$item) {
            $item = $item instanceof MACAddress ? $item : self::getClass('MACAddress',$item);
            if (!$item->isValid()) return;
            return trim(strtolower($item->__toString()));
        };
        $myMACs = array_values(array_filter(array_unique(array_map($macaddress,(array)$this->getMyMacs()))));
        $igMACs = array_values(array_filter(array_unique(array_map($macaddress,(array)$imageIgnore))));
        $cgMACs = array_values(array_filter(array_unique(array_map($macaddress,(array)$clientIgnore))));
        self::getClass('MACAddressAssociationManager')->update(array('mac'=>array_diff($myMACs,$cgMACs),'hostID'=>$this->get('id')),'',array('clientIgnore'=>0));
        self::getClass('MACAddressAssociationManager')->update(array('mac'=>array_diff($myMACs,$igMACs),'hostID'=>$this->get('id')),'',array('imageIgnore'=>0));
        if (count($cgMACs)) self::getClass('MACAddressAssociationManager')->update(array('mac'=>$cgMACs,'hostID'=>$this->get('id')),'',array('clientIgnore'=>1));
        if (count($igMACs)) self::getClass('MACAddressAssociationManager')->update(array('mac'=>$igMACs,'hostID'=>$this->get('id')),'',array('imageIgnore'=>1));
    }
    public function addGroup($addArray) {
        return $this->addHost($addArray);
    }
    public function removeGroup($removeArray) {
        return $this->removeHost($removeArray);
    }
    public function addHost($addArray) {
        return $this->set('groups',array_unique(array_merge((array)$this->get('groups'),(array)$addArray)));
    }
    public function removeHost($removeArray) {
        return $this->set('groups',array_unique(array_diff((array)$this->get('groups'),(array)$removeArray)));
    }
    public function clientMacCheck($MAC = false) {
        return self::getClass('MACAddress',self::getSubObjectIDs('MACAddressAssociation',array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'clientIgnore'=>1),'mac'))->isValid() ? 'checked' : '';
    }
    public function imageMacCheck($MAC = false) {
        return self::getClass('MACAddress',self::getSubObjectIDs('MACAddressAssociation',array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'imageIgnore'=>1),'mac'))->isValid() ? 'checked' : '';
    }
    public function setAD($useAD = '',$domain = '',$ou = '',$user = '',$pass = '',$override = false,$legacy = '',$productKey = '',$enforce = '') {
        if ($this->get('id')) {
            if (!$override) {
                if (empty($useAD)) $useAD = $this->get('useAD');
                if (empty($domain))	$domain = trim($this->get('ADDomain'));
                if (empty($ou)) $ou = trim($this->get('ADOU'));
                if (empty($user)) $user = trim($this->get('ADUser'));
                if (empty($pass)) $pass = trim($this->encryptpw($this->get('ADPass')));
                if (empty($legacy)) $legacy = trim($this->get('ADPassLegacy'));
                if (empty($productKey)) $productKey = trim($this->encryptpw($this->get('productKey')));
                if (empty($enforce)) $enforce = (string)intval($this->get('enforce'));
            }
        }
        if ($pass) $pass = trim($this->encryptpw($pass));
        $this->set('useAD',$useAD)
            ->set('ADDomain',trim($domain))
            ->set('ADOU',trim($ou))
            ->set('ADUser',trim($user))
            ->set('ADPass',trim($this->encryptpw($pass)))
            ->set('ADPassLegacy',$legacy)
            ->set('productKey',trim($this->encryptpw($productKey)))
            ->set('enforce',(string)intval($enforce));
        return $this;
    }
    public function getImage() {
        return self::getClass('Image',$this->get('imageID'));
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
    public function setPingStatus() {
        $org_ip = $this->get('ip');
        if (filter_var($this->get('ip'),FILTER_VALIDATE_IP) === false) $this->set('ip',self::$FOGCore->resolveHostname($this->get('name')));
        if (filter_var($this->get('ip'),FILTER_VALIDATE_IP) === false) $this->set('ip',$this->get('name'));
        $this->getManager()->update(array('id'=>$this->get('id')),'',array('pingstatus'=>self::getClass('Ping',$this->get('ip'))->execute(),'ip'=>$org_ip));
        unset($org_ip);
        return $this;
    }
    public function getPingCodeStr() {
        $val =  intval($this->get('pingstatus'));
        $socketstr = socket_strerror($val);
        $strtoupdate = "<i class=\"icon-ping-%s fa fa-exclamation-circle fa-1x\" style=\"color: %s\" title=\"$socketstr\"></i>";
        ob_start();
        if ($val === 0) printf($strtoupdate,'up','#18f008');
        else printf($strtoupdate,'down','#ce0f0f');
        return ob_get_clean();
    }
}
