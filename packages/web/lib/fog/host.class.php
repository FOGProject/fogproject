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
        'printers',
        'printersnotinme',
        'snapins',
        'snapinsnotinme',
        'modules',
        'users',
    );
    public function set($key, $value) {
        $key = $this->key($key);
        if (!static::isLoaded($key)) $this->loadItem($key);
        switch ($key) {
        case 'mac':
            if (!($value instanceof MACAddress)) $value = static::getClass('MACAddress',$value);
            break;
        case 'additionalMACs':
        case 'pendingMACs':
            $newValue = array_map(function(&$mac) {
                return static::getClass('MACAddress',$mac);
            },(array)$value);
            $value = (array)$newValue;
            break;
        case 'snapinjob':
            if (!($value instanceof SnapinJob)) $value = static::getClass('SnapinJob',$value);
            break;
        case 'inventory':
            if (!($value instanceof Inventory)) $value = static::getClass('Inventory',$value);
            break;
        case 'task':
            if (!($value instanceof Task)) $value = static::getClass('Task',$value);
            break;
        }
        return parent::set($key, $value);
    }
    public function add($key, $value) {
        $key = $this->key($key);
        if (!static::isLoaded($key)) $this->loadItem($key);
        switch ($key) {
        case 'additionalMACs':
        case 'pendingMACs':
            if (!($value instanceof MACAddress)) $value = static::getClass('MACAddress',$value);
            break;
        }
        return parent::add($key,$value);
    }
    public function destroy($field = 'id') {
        $find = array('hostID'=>$this->get('id'));
        static::getClass('NodeFailureManager')->destroy($find);
        static::getClass('ImagingLogManager')->destroy($find);
        static::getClass('SnapinTaskManager')->destroy(array('jobID'=>static::getSubObjectIDs('SnapinJob',$find,'id')));
        static::getClass('SnapinJobManager')->destroy($find);
        static::getClass('TaskManager')->destroy($find);
        static::getClass('ScheduledTaskManager')->destroy($find);
        static::getClass('HostAutoLogoutManager')->destroy($find);
        static::getClass('HostScreenSettingsManager')->destroy($find);
        static::getClass('GroupAssociationManager')->destroy($find);
        static::getClass('SnapinAssociationManager')->destroy($find);
        static::getClass('PrinterAssociationManager')->destroy($find);
        static::getClass('ModuleAssociationManager')->destroy($find);
        static::getClass('GreenFogManager')->destroy($find);
        static::getClass('InventoryManager')->destroy($find);
        static::getClass('UserTrackingManager')->destroy($find);
        static::getClass('MACAddressAssociationManager')->destroy($find);
        return parent::destroy($field);
    }
    public function save($mainObject = true) {
        if ($mainObject) parent::save();
        $itemSetter = function(&$item) {
            if (!$item->isValid()) return;
            $item->addHost($this->get('id'))->save(false);
            unset($item);
        };
        switch ($this->get('id')) {
        case 0:
        case null:
        case false:
        case '0':
        case '':
            $this->destroy();
            throw new Exception(_('Host ID was not set, or unable to be created'));
            break;
        case (static::isLoaded('mac')):
            if (!(($this->get('mac') instanceof MACAddress) && $this->get('mac')->isValid())) throw new Exception(static::$foglang['InvalidMAC']);
            $RealPriMAC = $this->get('mac')->__toString();
            $CurrPriMAC = static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            if (count($CurrPriMAC) === 1 && $CurrPriMAC[0] != $RealPriMAC) static::getClass('MACAddressAssociationManager')->update(array('mac'=>$CurrPriMAC[0],'hostID'=>$this->get('id'),'primary'=>1),'',array('primary'=>0));
            $HostWithMAC = array_diff((array)$this->get('id'),(array)static::getSubObjectIDs('MACAddressAssociation',array('mac'=>$RealPriMAC),'hostID'));
            if (count($HostWithMAC) && !in_array($this->get('id'),(array)$HostWithMAC)) throw new Exception(_('This MAC Belongs to another host'));
            $DBPriMACs = static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            $RemoveMAC = array_diff((array)$RealPriMAC,(array)$DBPriMACs);
            if (count($RemoveMAC)) {
                static::getClass('MACAddressAssociationManager')->destroy(array('mac'=>$RemoveMAC));
                unset($RemoveMAC);
                $DBPriMACs = static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            }
            if (!in_array($RealPriMAC,$DBPriMACs)) {
                static::getClass('MACAddressAssociation')
                    ->set('hostID',$this->get('id'))
                    ->set('mac',$RealPriMAC)
                    ->set('primary',1)
                    ->save();
            }
            unset($DBPriMACs,$RealPriMAC,$RemoveMAC,$HostWithMAC);
        case (static::isLoaded('additionalMACs')):
            $RealAddMACs = array_values(array_unique(array_filter(array_map(function(&$MAC) {
                if ($MAC instanceof MACAddress && $MAC->isValid()) return $MAC->__toString();
            },(array)$this->get('additionalMACs')))));
            $DBPriMACs = static::getSubObjectIDs('MACAddressAssociation',array('primary'=>1),'mac');
            array_map(function(&$MAC) use ($RealAddMACs) {
                if ($this->array_strpos($MAC,$RealAddMACs) !== false) throw new Exception(_('Cannot add Primary mac as additional mac'));
                unset($MAC);
            },(array)$DBPriMACs);
            unset($DBPriMACs);
            $PreOwnedMACs = static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'pending'=>1),'mac',true);
            $RealAddMACs = array_diff((array)$RealAddMACs,(array)$PreOwnedMACs);
            unset($PreOwnedMACs);
            $DBAddMACs = static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(0,null),'pending'=>array(0,null)),'mac');
            $RemoveAddMAC = array_diff((array)$DBAddMACs,(array)$RealAddMACs);
            if (count($RemoveAddMAC)) {
                static::getClass('MACAddressAssociationManager')->destroy(array('hostID'=>$this->get('id'),'mac'=>$RemoveAddMAC));
                $DBAddMACs = static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(0,null),'pending'=>array(0,null)),'mac');
                unset($RemoveAddMAC);
            }
            array_map(function(&$RealAddMAC) {
                static::getClass('MACAddressAssociation')
                    ->set('hostID',$this->get('id'))
                    ->set('mac',$RealAddMAC)
                    ->set('primary',0)
                    ->set('pending',0)
                    ->save();
                unset($RealAddMAC);
            },(array)array_diff((array)$RealAddMACs,(array)$DBAddMACs));
            unset($DBAddMACs,$RealAddMACs,$RemoveAddMAC);
        case (static::isLoaded('pendingMACs')):
            $RealPendMACs = array_map(function(&$MAC) {
                if ($MAC instanceof MACAddress && $MAC->isValid()) return $MAC->__toString();
            },(array)$this->get('pendingMACs'));
            $DBPriMACs = static::getSubObjectIDs('MACAddressAssociation',array('primary'=>1),'mac');
            array_map(function(&$DBPriMAC) use ($RealPendMACs) {
                if ($this->array_strpos($DBPriMAC,$RealPendMACs) !== false) throw new Exception(_('Cannot add a pre-existing Primary MAC as a pending MAC'));
                unset($DBPriMAC);
            },(array)$DBPriMACs);
            unset($DBPriMACs);
            $PreOwnedMACs = static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'pending'=>array(0,null)),'mac',true);
            $RealPendMACs = array_diff((array)$RealPendMACs,(array)$PreOwnedMACs);
            unset($PreOwnedMACs);
            $DBPendMACs = static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(0,null),'pending'=>1),'mac');
            $RemovePendMAC = array_diff((array)$DBPendMACs,(array)$RealPendMACs);
            if (count($RemovePendMAC)) {
                static::getClass('MACAddressAssociationManager')->destroy(array('hostID'=>$this->get('id'),'mac'=>$RemovePendMAC));
                $DBPendMACs = static::getSubObjectIDs('MACAddressAssociation',array('primary'=>array(0,null),'pending'=>1),'mac');
                unset($RemovePendMAC);
            }
            array_map(function(&$RealPendMAC) {
                static::getClass('MACAddressAssociation')
                    ->set('hostID',$this->get('id'))
                    ->set('mac',$RealPendMAC)
                    ->set('primary',0)
                    ->set('pending',1)
                    ->save();
                unset($RealPendMAC);
            },(array)array_diff((array)$RealPendMACs,(array)$DBPendMACs));
            $RealPendMACs = array_diff((array)$RealPendMACs,(array)$DBPendMACs);
            unset($DBPendMACs,$RealPendMACs,$RemovePendMAC);
        case (static::isLoaded('modules')):
            $DBModuleIDs = static::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
            $ValidModuleIDs = static::getSubObjectIDs('Module');
            $notValid = array_diff((array)$DBModuleIDs,(array)$ValidModuleIDs);
            if (count($notValid)) static::getClass('ModuleAssociationManager')->destroy(array('moduleID'=>$notValid));
            unset($ValidModuleIDs,$DBModuleIDs);
            $DBModuleIDs = static::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
            $RemoveModuleIDs = array_diff((array)$DBModuleIDs,(array)$this->get('modules'));
            if (count($RemoveModuleIDs)) {
                static::getClass('ModuleAssociationManager')->destroy(array('moduleID'=>$RemoveModuleIDs,'hostID'=>$this->get('id')));
                $DBModuleIDs = static::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
                unset($RemoveModuleIDs);
            }
            $moduleName = $this->getGlobalModuleStatus();
            array_map(function(&$Module) use ($moduleName) {
                if (!$Module->isValid()) return;
                if ($moduleName[$Module->get('shortName')]) {
                    static::getClass('ModuleAssociation')
                        ->set('hostID',$this->get('id'))
                        ->set('moduleID',$Module->get('id'))
                        ->set('state',1)
                        ->save();
                }
                unset($Module);
            },(array)static::getClass('ModuleManager')->find(array('id'=>array_diff((array)$this->get('modules'),(array)$DBModuleIDs))));
            unset($DBModuleIDs,$RemoveModuleIDs,$moduleName);
        case (static::isLoaded('printers')):
            $DBPrinterIDs = static::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
            $ValidPrinterIDs = static::getSubObjectIDs('Printer');
            $notValid = array_diff((array)$DBPrinterIDs,(array)$ValidPrinterIDs);
            if (count($notValid)) static::getClass('PrinterAssociationManager')->destroy(array('printerID'=>$notValid));
            unset($ValidPrinterIDs,$DBPrinterIDs);
            $DBPrinterIDs = static::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
            $RemovePrinterIDs = array_diff((array)$DBPrinterIDs,(array)$this->get('printers'));
            if (count($RemovePrinterIDs)) {
                static::getClass('PrinterAssociationManager')->destroy(array('hostID'=>$this->get('id'),'printerID'=>$RemovePrinterIDs));
                $DBPrinterIDs = static::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
                unset($RemovePrinterIDs);
            }
            array_map($itemSetter,(array)static::getClass('PrinterManager')->find(array('id'=>array_diff((array)$this->get('printers'),(array)$DBPrinterIDs))));
            unset($DBPrinterIDs,$RemovePrinterIDs);
        case (static::isLoaded('snapins')):
            $DBSnapinIDs = static::getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
            $RemoveSnapinIDs = array_diff((array)$DBSnapinIDs,(array)$this->get('snapins'));
            if (count($RemoveSnapinIDs)) {
                static::getClass('SnapinAssociationManager')->destroy(array('hostID'=>$this->get('id'),'snapinID'=>$RemoveSnapinIDs));
                $DBSnapinIDs = static::getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
                unset($RemoveSnapinIDs);
            }
            array_map($itemSetter,(array)static::getClass('SnapinManager')->find(array('id'=>array_diff((array)$this->get('snapins'),(array)$DBSnapinIDs))));
            unset($DBSnapinIDs,$RemoveSnapinIDs);
        case (static::isLoaded('groups')):
            $DBGroupIDs = static::getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
            $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('groups'));
            if (count($RemoveGroupIDs)) {
                static::getClass('GroupAssociationManager')->destroy(array('hostID'=>$this->get('id'),'groupID'=>$RemoveGroupIDs));
                $DBGroupIDs = static::getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
                unset($RemoveGroupIDs);
            }
            array_map($itemSetter,(array)static::getClass('GroupManager')->find(array('id'=>array_diff((array)$this->get('groups'),(array)$DBGroupIDs))));
            unset($DBGroupIDs,$RemoveGroupIDs);
        }
        return $this;
    }
    public function isValid() {
        return parent::isValid() && $this->isHostnameSafe() && $this->get('mac')->isValid();
    }
    public function isHostnameSafe($hostname = '') {
        if (empty($hostname)) $hostname = $this->get('name');
        return (strlen($hostname) > 0 && strlen($hostname) <= 15 && preg_replace('#[\w+\-]#', '', $hostname) == '');
    }
    public function getDefault($printerid) {
        return (bool)count(static::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id'),'printerID'=>$printerid,'isDefault'=>1),'printerID'));
    }
    public function updateDefault($printerid,$onoff) {
        static::getClass('PrinterAssociationManager')->update(array('printerID'=>$this->get('printers'),'hostID'=>$this->get('id')),'',array('isDefault'=>0));
        static::getClass('PrinterAssociationManager')->update(array('printerID'=>$printerid,'hostID'=>$this->get('id')),'',array('isDefault'=>$onoff));
        return $this;
    }
    public function getDispVals($key = '') {
        $keyTran = array(
            'width'=>'FOG_SERVICE_DISPLAYMANAGER_X',
            'height'=>'FOG_SERVICE_DISPLAYMANAGER_Y',
            'refresh'=>'FOG_SERVICE_DISPLAYMANAGER_R',
        );
        $HostScreen = static::getClass('HostScreenSettingsManager')->find(array('hostID'=>$this->get('id')));
        $HostScreen = @array_shift($HostScreen);
        $gScreen = static::getSetting($keyTran[$key]);
        return ($HostScreen instanceof HostScreenSettings && $HostScreen->isValid() ? $HostScreen->get($key) : $gScreen);
    }
    public function setDisp($x,$y,$r) {
        static::getClass('HostScreenSettingsManager')->destroy(array('hostID'=>$this->get('id')));
        static::getClass('HostScreenSettings')
            ->set('hostID',$this->get('id'))
            ->set('width',$x)
            ->set('height',$y)
            ->set('refresh',$r)
            ->save();
        return $this;
    }
    public function getAlo() {
        $HostALO = static::getClass('HostAutoLogoutManager')->find(array('hostID'=>$this->get('id')));
        $HostALO = @array_shift($HostALO);
        $gTime = static::getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
        return ($HostALO && $HostALO->isValid() ? $HostALO->get('time') : $gTime);
    }
    public function setAlo($time) {
        static::getClass('HostAutoLogoutManager')->destroy(array('hostID'=>$this->get('id')));
        static::getClass('HostAutoLogout')
            ->set('hostID',$this->get('id'))
            ->set('time',$time)
            ->save();
        return $this;
    }
    protected function loadMac() {
        if (!$this->get('id')) return;
        $this->set('mac',static::getClass('MACAddress',$this->get('primac')->get('mac')));
    }
    protected function loadAdditionalMACs() {
        if (!$this->get('id')) return;
        $this->set('additionalMACs',static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>array(null,0,'')),'mac'));
    }
    protected function loadPendingMACs() {
        if (!$this->get('id')) return;
        $this->set('pendingMACs',static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>1),'mac'));
    }
    protected function loadGroups() {
        if (!$this->get('id')) return;
        $this->set('groups',static::getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID'));
    }
    protected function loadGroupsnotinme() {
        if (!$this->get('id')) return;
        $find = array('id'=>$this->get('groups'));
        $this->set('groupsnotinme',static::getSubObjectIDs('Group',$find,'id',true));
        unset($find);
    }
    protected function loadPrinters() {
        if (!$this->get('id')) return;
        $this->set('printers',static::getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID'));
    }
    protected function loadPrintersnotinme() {
        if (!$this->get('id')) return;
        $find = array('id'=>$this->get('printers'));
        $this->set('printersnotinme',static::getSubObjectIDs('Printer',$find,'id',true));
        unset($find);
    }
    protected function loadSnapins() {
        if (!$this->get('id')) return;
        $AssocSnapins = static::getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
        $ValidSnapins = static::getSubObjectIDs('Snapin',array('id'=>$AssocSnapins));
        $InvalidSnapins = array_unique(array_filter(array_diff((array)$AssocSnapins,(array)$ValidSnapins)));
        if (count($InvalidSnapins)) static::getClass('SnapinManager')->destroy(array('id'=>$InvalidSnapins));
        $this->set('snapins',$ValidSnapins);
    }
    protected function loadSnapinsnotinme() {
        if (!$this->get('id')) return;
        $find = array('id'=>$this->get('snapins'));
        $this->set('snapinsnotinme',static::getSubObjectIDs('Snapin',$find,'id',true));
        unset($find);
    }
    protected function loadModules() {
        if (!$this->get('id')) return;
        $this->set('modules',static::getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID'));
    }
    protected function loadUsers() {
        if (!$this->get('id')) return;
        $this->set('users',static::getSubObjectIDs('UserTracking',array('hostID'=>$this->get('id'))));
    }
    protected function loadSnapinjob() {
        if (!$this->get('id')) return;
        $this->set('snapinjob',@max(static::getSubObjectIDs('SnapinJob',array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()),'hostID'=>$this->get('id')),'id')));
    }
    protected function loadInventory() {
        if (!$this->get('id')) return;
        $this->set('inventory',@max(static::getSubObjectIDs('Inventory',array('hostID'=>$this->get('id')),'id')));
    }
    protected function loadTask() {
        if (!$this->get('id')) return;
        $find['hostID'] = $this->get('id');
        $find['stateID'] = array_merge($this->getQueuedStates(),(array)$this->getProgressState());
        if (in_array($_REQUEST['type'], array('up','down'))) $find['typeID'] = ($_REQUEST['type'] == 'up' ? array(2,16) : array(1,8,15,17,24));
        $this->set('task',@max(static::getSubObjectIDs('Task',$find,'id')));
        unset($find);
    }
    protected function loadOptimalStorageNode() {
        if (!$this->get('id')) return;
        $this->set('optimalStorageNode', static::getClass('Image',$this->get('imageID'))->getStorageGroup()->getOptimalStorageNode($this->get('imageID')));
    }
    public function getActiveTaskCount() {
        return static::getClass('TaskManager')->count(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()),'hostID'=>$this->get('id')));
    }
    public function isValidToImage() {
        return ($this->getImage()->isValid() && $this->getOS()->isValid() && $this->getImage()->getStorageGroup()->isValid() && $this->getImage()->getStorageGroup()->getStorageNode()->isValid());
    }
    public function getOptimalStorageNode() {
        return $this->get('optimalStorageNode');
    }
    public function checkIfExist($taskTypeID) {
        $TaskType = static::getClass('TaskType',$taskTypeID);
        $isUpload = $TaskType->isUpload();
        $Image = $this->getImage();
        $StorageGroup = $Image->getStorageGroup();
        $StorageNode = $StorageGroup->getMasterStorageNode();
        static::$HookManager->processEvent('HOST_NEW_SETTINGS',array('Host'=>&$this,'StorageNode'=>&$StorageNode,'StorageGroup'=>&$StorageGroup));
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
        $Task = static::getClass('Task')
            ->set('name',$taskName)
            ->set('createdBy',$username)
            ->set('hostID',$this->get('id'))
            ->set('isForced',0)
            ->set('stateID',$this->getQueuedState())
            ->set('typeID',$taskTypeID)
            ->set('NFSGroupID',$groupID)
            ->set('NFSMemberID',$memID)
            ->set('wol',(int)$wol);
        if ($imagingTask) $Task->set('imageID',$this->getImage()->get('id'));
        if ($shutdown) $Task->set('shutdown',$shutdown);
        if ($debug) $Task->set('isDebug',$debug);
        if ($passreset) $Task->set('passreset',$passreset);
        return $Task;
    }
    private function cancelJobsSnapinsForHost() {
        $SnapinJobs = static::getSubObjectIDs('SnapinJob',array('hostID'=>$this->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())));
        static::getClass('SnapinTaskManager')->update(array('jobID'=>$SnapinJobs,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())),'',array('return'=>-9999,'details'=>_('Cancelled due to new tasking.')));
        static::getClass('SnapinJobManager')->update(array('id'=>$SnapinJobs),'',array('stateID'=>$this->getCancelledState()));
    }
    private function createSnapinTasking($snapin = -1) {
        try {
            if (count($this->get('snapins')) < 1) return $this;
            $SnapinJob = static::getClass('SnapinJob')
                ->set('hostID',$this->get('id'))
                ->set('stateID',$this->getQueuedState())
                ->set('createdTime',static::nice_date()->format('Y-m-d H:i:s'));
            if (!$SnapinJob->save()) throw new Exception(_('Failed to create Snapin Job'));
            if ($snapin == -1) {
                array_map(function(&$Snapin) {
                    static::getClass('SnapinTask')
                        ->set('jobID',$this->get('snapinjob')->get('id'))
                        ->set('stateID',$this->getQueuedState())
                        ->set('snapinID',$Snapin)
                        ->save();
                    unset($Snapin);
                },(array)$this->get('snapins'));
                return $this;
            }
            $Snapin = static::getClass('Snapin',$snapin);
            if (!$Snapin->isValid()) throw new Exception(_('Snapin is not valid'));
            static::getClass('SnapinTask')
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
            if (!$this->isValid()) throw new Exception(static::$foglang['HostNotValid']);
            $TaskType = static::getClass('TaskType',$taskTypeID);
            if (!$TaskType->isValid()) throw new Exception(static::$foglang['TaskTypeNotValid']);
            if (!$TaskType->isSnapinTasking() && $this->getActiveTaskCount()) throw new Exception(static::$foglang['InTask']);
            $imagingTypes = in_array($taskTypeID,array(1,2,8,15,16,17,24));
            if ($imagingTypes) {
                $Image = $this->getImage();
                if (!$Image->isValid()) throw new Exception(static::$foglang['ImageNotValid']);
                if (!$Image->get('isEnabled')) throw new Exception(_('Image is not enabled'));
                $StorageGroup = $Image->getStorageGroup();
                if (!$StorageGroup->isValid()) throw new Exception(static::$foglang['ImageGroupNotValid']);
                $StorageNode = ($TaskType->isUpload() ? $StorageGroup->getMasterStorageNode() : $this->getOptimalStorageNode($this->get('imageID')));
                if (!$StorageNode->isValid()) $StorageNode = $StorageGroup->getOptimalStorageNode($this->get('imageID'));
                if (!$StorageNode->isValid()) throw new Exception(_('Could not find any nodes containing this image'));
                $imageTaskImgID = $this->get('imageID');
                $hostsWithImgID = static::getSubObjectIDs('Host',array('imageID'=>$imageTaskImgID));
                $realImageID = static::getSubObjectIDs('Host',array('id'=>$this->get('id')),'imageID');
                if (!in_array($this->get('id'),(array)$hostsWithImgID)) $this->set('imageID',array_shift($realImageID))->save();
                $this->set('imageID',$imageTaskImgID);
            }
            $isUpload = $TaskType->isUpload();
            $username = ($username ? $username : $_SESSION['FOG_USERNAME']);
            $Task = $this->createTasking($taskName, $taskTypeID, $username, $imagingTypes ? $StorageGroup->get('id') : 0, $imagingTypes ? $StorageNode->get('id') : 0, $imagingTypes,$shutdown,$passreset,$debug,$wol);
            $Task->set('imageID',$this->get('imageID'));
            if (!$Task->save()) throw new Exception(static::$foglang['FailedTask']);
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
                    $MultiSessJoin = array_values(array_filter(array_map($multicastTaskReturn,(array)static::getClass('MulticastSessionsManager')->find(array('name'=>$taskName,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))))));
                    $MulticastSession = array_shift($MultiSessJoin);
                    $assoc = true;
                } else {
                    $MultiSessJoin = array_values(array_filter(array_map($multicastTaskReturn,(array)static::getClass('MulticastSessionsManager')->find(array('image'=>$this->getImage()->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))))));
                }
                if (count($MultiSessJoin)) $MulticastSession = array_shift($MultiSessJoin);
                if ($MulticastSession instanceof MulticastSessions && $MulticastSession->isValid()) $assoc = true;
                if (!($MulticastSession instanceof MulticastSessions && $MulticastSession->isValid())) {
                    $port = static::getSetting('FOG_UDPCAST_STARTINGPORT');
                    $portOverride = static::getSetting('FOG_MULTICAST_PORT_OVERRIDE');
                    $MulticastSession = static::getClass('MulticastSessions')
                        ->set('name',$taskName)
                        ->set('port',($portOverride ? $portOverride : $port))
                        ->set('logpath',$this->getImage()->get('path'))
                        ->set('image',$this->getImage()->get('id'))
                        ->set('interface',$StorageNode->get('interface'))
                        ->set('stateID',0)
                        ->set('starttime',static::nice_date()->format('Y-m-d H:i:s'))
                        ->set('percent',0)
                        ->set('isDD',$this->getImage()->get('imageTypeID'))
                        ->set('NFSGroupID',$StorageNode->get('storageGroupID'));
                    if ($MulticastSession->save()) {
                        $assoc = true;
                        if (!static::getSetting('FOG_MULTICAST_PORT_OVERRIDE')) {
                            $randomnumber = mt_rand(24576,32766)*2;
                            while ($randomnumber == $MulticastSession->get('port')) $randomnumber = mt_rand(24576,32766)*2;
                            $this->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
                        }
                    }
                }
                if ($assoc) {
                    static::getClass('MulticastSessionsAssociation')
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
            $Task = static::getClass('Task')
                ->set('hostID',$this->get('id'))
                ->set('NFSGroupID',$StorageGroup->get('id'))
                ->set('NFSMemberID',$StorageGroup->getOptimalStorageNode($this->get('imageID'))->get('id'))
                ->set('imageID',$Image->get('id'));
        } catch (Exception $e) {
            static::$FOGCore->error(sprintf('%s():xError: %s', __FUNCTION__, $e->getMessage()));
            $Task = false;
        }
        return $Task;
    }
    public function clearAVRecordsForHost() {
        $MACs = $this->getMyMacs();
        static::getClass('VirusManager')->destroy(array('hostMAC'=>$MACs));
        unset($MACs);
    }
    public function wakeOnLAN() {
        $URLs = array();
        $URLs = array_map(function(&$Node) {
            $curroot = trim(trim($Node->get('webroot'),'/'));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            return sprintf('http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s',$Node->get('ip'),$webroot,implode('|',(array)$this->getMyMacs()));
        },(array)static::getClass('StorageNodeManager')->find(array('isEnabled'=>1)));
        $curroot = trim(trim(static::getSetting('FOG_WEB_ROOT'),'/'));
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        $URLs[] = sprintf('http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s',static::getSetting('FOG_WEB_HOST'),$webroot,implode('|',(array)$this->getMyMacs()));
        $URLs = array_values(array_filter(array_unique((array)$URLs)));
        static::$FOGURLRequests->process($URLs,'GET');
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
            $item = $item instanceof MACAddress ? $item : static::getClass('MACAddress',$item);
            $this->remove('additionalMACs',$item);
            unset($item);
        },(array)$removeArray);
        return $this;
    }
    public function removePendMAC($removeArray) {
        array_map(function(&$item) {
            $item = $item instanceof MACAddress ? $item : static::getClass('MACAddress',$item);
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
        $limit = static::getSetting('FOG_SNAPIN_LIMIT');
        if ($limit > 0) {
            if (static::getClass('SnapinManager')->count(array('id'=>$this->get('snapins'))) >= $limit || count($addArray) > $limit) throw new Exception(sprintf('%s %d %s',_('You are only allowed to assign'),$limit,$limit == 1 ? _('snapin per host') : _('snapins per host')));
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
    public function getMyMacs($justme = true) {
        if ($justme) return static::getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id')),'mac');
        return static::getSubObjectIDs('MACAddressAssociation','','mac');
    }
    public function ignore($imageIgnore,$clientIgnore) {
        $MyMACs = $this->getMyMacs();
        $myMACs = $igMACs = $cgMACs = array();
        $macaddress = function(&$item) {
            $item = $item instanceof MACAddress ? $item : static::getClass('MACAddress',$item);
            if (!$item->isValid()) return;
            return trim(strtolower($item->__toString()));
        };
        $myMACs = array_values(array_filter(array_unique(array_map($macaddress,(array)$this->getMyMacs()))));
        $igMACs = array_values(array_filter(array_unique(array_map($macaddress,(array)$imageIgnore))));
        $cgMACs = array_values(array_filter(array_unique(array_map($macaddress,(array)$clientIgnore))));
        static::getClass('MACAddressAssociationManager')->update(array('mac'=>array_diff($myMACs,$cgMACs),'hostID'=>$this->get('id')),'',array('clientIgnore'=>0));
        static::getClass('MACAddressAssociationManager')->update(array('mac'=>array_diff($myMACs,$igMACs),'hostID'=>$this->get('id')),'',array('imageIgnore'=>0));
        if (count($cgMACs)) static::getClass('MACAddressAssociationManager')->update(array('mac'=>$cgMACs,'hostID'=>$this->get('id')),'',array('clientIgnore'=>1));
        if (count($igMACs)) static::getClass('MACAddressAssociationManager')->update(array('mac'=>$igMACs,'hostID'=>$this->get('id')),'',array('imageIgnore'=>1));
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
        return static::getClass('MACAddress',static::getSubObjectIDs('MACAddressAssociation',array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'clientIgnore'=>1),'mac'))->isValid() ? 'checked' : '';
    }
    public function imageMacCheck($MAC = false) {
        return static::getClass('MACAddress',static::getSubObjectIDs('MACAddressAssociation',array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'imageIgnore'=>1),'mac'))->isValid() ? 'checked' : '';
    }
    public function setAD($useAD = '',$domain = '',$ou = '',$user = '',$pass = '',$override = false,$nosave = false,$legacy = '',$productKey = '',$enforce = '') {
        if ($this->get('id')) {
            if (!$override) {
                if (empty($useAD)) $useAD = $this->get('useAD');
                if (empty($domain))	$domain = trim($this->get('ADDomain'));
                if (empty($ou)) $ou = trim($this->get('ADOU'));
                if (empty($user)) $user = trim($this->get('ADUser'));
                if (empty($pass)) $pass = trim($this->encryptpw($this->get('ADPass')));
                if (empty($legacy)) $legacy = trim($this->get('ADPassLegacy'));
                if (empty($productKey)) $productKey = trim($this->encryptpw($this->get('productKey')));
                if (empty($enforce)) $enforce = (int)$this->get('enforce');
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
            ->set('enforce',$enforce);
        if (!$nosave) $this->save();
        return $this;
    }
    public function getImage() {
        return static::getClass('Image',$this->get('imageID'));
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
        if (filter_var($this->get('ip'),FILTER_VALIDATE_IP) === false) $this->set('ip',static::$FOGCore->resolveHostname($this->get('name')));
        if (filter_var($this->get('ip'),FILTER_VALIDATE_IP) === false) $this->set('ip',$this->get('name'));
        $this->getManager()->update(array('id'=>$this->get('id')),'',array('pingstatus'=>static::getClass('Ping',$this->get('ip'))->execute(),'ip'=>$org_ip));
        unset($org_ip);
        return $this;
    }
    public function getPingCodeStr() {
        $val = (int) $this->get('pingstatus');
        $socketstr = socket_strerror($val);
        $strtoupdate = "<i class=\"icon-ping-%s fa fa-exclamation-circle fa-1x\" style=\"color: %s\" title=\"$socketstr\"></i>";
        ob_start();
        if ($val === 0) printf($strtoupdate,'up','#18f008');
        else printf($strtoupdate,'down','#ce0f0f');
        return ob_get_clean();
    }
}
