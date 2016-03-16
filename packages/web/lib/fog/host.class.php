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
        if (!$this->isLoaded($key)) $this->loadItem($key);
        switch ($key) {
        case 'mac':
            if (!($value instanceof MACAddress)) $value = self::getClass('MACAddress',$value);
            break;
        case 'additionalMACs':
        case 'pendingMACs':
            foreach((array)$value AS $i => &$mac) $newValue[] = self::getClass('MACAddress',$mac);
            unset($mac);
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
        if (!$this->isLoaded($key)) $this->loadItem($key);
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
        self::getClass('SnapinTaskManager')->destroy(array('jobID'=>$this->getSubObjectIDs('SnapinJob',$find,'id')));
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
        return parent::destroy($field);
    }
    public function save($mainObject = true) {
        if ($mainObject) parent::save();
        switch ($this->get('id')) {
        case 0:
        case null:
        case false:
        case '0':
        case '':
            $this->destroy();
            throw new Exception(_('Host ID was not set, or unable to be created'));
            break;
        case ($this->isLoaded('mac')):
            if (!(($this->get('mac') instanceof MACAddress) && $this->get('mac')->isValid())) throw new Exception($this->foglang['InvalidMAC']);
            $RealPriMAC = $this->get('mac')->__toString();
            $CurrPriMAC = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            if (count($CurrPriMAC) === 1 && $CurrPriMAC[0] != $RealPriMAC) self::getClass('MACAddressAssociationManager')->update(array('mac'=>$CurrPriMAC[0],'hostID'=>$this->get('id'),'primary'=>1),'',array('primary'=>0));
            $HostWithMAC = array_diff((array)$this->get('id'),(array)$this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$RealPriMAC),'hostID'));
            if (count($HostWithMAC) && !in_array($this->get('id'),(array)$HostWithMAC)) throw new Exception(_('This MAC Belongs to another host'));
            $DBPriMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            $RemoveMAC = array_diff((array)$RealPriMAC,(array)$DBPriMACs);
            if (count($RemoveMAC)) {
                self::getClass('MACAddressAssociationManager')->destroy(array('mac'=>$RemoveMAC));
                unset($RemoveMAC);
                $DBPriMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
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
            $theseMACs = $this->get('additionalMACs');
            $RealAddMACs = $PreOwnedMACs = array();
            foreach ((array)$theseMACs AS $i => &$thisMAC) {
                if (($thisMAC instanceof MACAddress) && $thisMAC->isValid() && !in_array($thisMAC->__toString(),(array)$RealAddMACs)) $RealAddMACs[] = $thisMAC->__toString();
                unset($thisMAC);
            }
            unset($theseMACs);
            $DBPriMACs = $this->getSubObjectIDs('MACAddressAssociation',array('primary'=>1),'mac');
            foreach ((array)$DBPriMACs AS $i => &$DBPriMAC) {
                if ($this->array_strpos($DBPriMAC,$RealAddMACs) !== false) throw new Exception(_('Cannot add a pre-existing Primary MAC as an additional MAC'));
                unset($DBPriMAC);
            }
            unset($DBPriMACs);
            $PreOwnedMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'pending'=>1),'mac',true);
            $RealAddMACs = array_diff((array)$RealAddMACs,(array)$PreOwnedMACs);
            unset($PreOwnedMACs);
            $DBAddMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(0,null),'pending'=>array(0,null)),'mac');
            $RemoveAddMAC = array_diff((array)$DBAddMACs,(array)$RealAddMACs);
            if (count($RemoveAddMAC)) {
                self::getClass('MACAddressAssociationManager')->destroy(array('hostID'=>$this->get('id'),'mac'=>$RemoveAddMAC));
                $DBAddMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(0,null),'pending'=>array(0,null)),'mac');
                unset($RemoveAddMAC);
            }
            $RealAddMACs = array_diff((array)$RealAddMACs,(array)$DBAddMACs);
            unset($DBAddMACs);
            foreach ((array)$RealAddMACs AS $i => &$RealAddMAC) {
                self::getClass('MACAddressAssociation')
                    ->set('hostID',$this->get('id'))
                    ->set('mac',$RealAddMAC)
                    ->set('primary',0)
                    ->set('pending',0)
                    ->save();
                unset($RealAddMAC);
            }
            unset($RealAddMACs);
        case ($this->isLoaded('pendingMACs')):
            $theseMACs = $this->get('pendingMACs');
            $RealPendMACs = $PreOwnedMACs = array();
            foreach ((array)$theseMACs AS $i => &$thisMAC) {
                if (($thisMAC instanceof MACAddress) && $thisMAC->isValid() && false === $this->array_strpos($thisMAC->__toString(),$RealPendMACs)) $RealPendMACs[] = $thisMAC->__toString();
                unset($thisMAC);
            }
            unset($theseMACs);
            $DBPriMACs = $this->getSubObjectIDs('MACAddressAssociation',array('primary'=>1),'mac');
            foreach ((array)$DBPriMACs AS $i => &$DBPriMAC) {
                if ($this->array_strpos($DBPriMAC,$RealPendMACs) !== false) throw new Exception(_('Cannot add a pre-existing Primary MAC as a pending MAC'));
                unset($DBPriMAC);
            }
            unset($DBPriMACs);
            $PreOwnedMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'pending'=>array(0,null)),'mac',true);
            $RealPendMACs = array_diff((array)$RealPendMACs,(array)$PreOwnedMACs);
            unset($PreOwnedMACs);
            $DBPendMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(0,null),'pending'=>1),'mac');
            $RemovePendMAC = array_diff((array)$DBPendMACs,(array)$RealPendMACs);
            if (count($RemovePendMAC)) {
                self::getClass('MACAddressAssociationManager')->destroy(array('hostID'=>$this->get('id'),'mac'=>$RemovePendMAC));
                $DBPendMACs = $this->getSubObjectIDs('MACAddressAssociation',array('primary'=>array(0,null),'pending'=>1),'mac');
                unset($RemovePendMAC);
            }
            $RealPendMACs = array_diff((array)$RealPendMACs,(array)$DBPendMACs);
            unset($DBPendMACs);
            foreach ((array)$RealPendMACs AS $i => &$RealPendMAC) {
                self::getClass('MACAddressAssociation')
                    ->set('hostID',$this->get('id'))
                    ->set('mac',$RealPendMAC)
                    ->set('primary',0)
                    ->set('pending',1)
                    ->save();
                unset($RealPendMAC);
            }
            unset($RealPendMACs);
        case ($this->isLoaded('modules')):
            $DBModuleIDs = $this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
            $RemoveModuleIDs = array_diff((array)$DBModuleIDs,(array)$this->get('modules'));
            if (count($RemoveModuleIDs)) {
                self::getClass('ModuleAssociationManager')->destroy(array('moduleID'=>$RemoveModuleIDs,'hostID'=>$this->get('id')));
                $DBModuleIDs = $this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
                unset($RemoveModuleIDs);
            }
            $moduleName = $this->getGlobalModuleStatus();
            foreach((array)self::getClass('ModuleManager')->find(array('id'=>array_diff((array)$this->get('modules'),(array)$DBModuleIDs))) AS $i => &$Module) {
                if (!$Module->isValid()) continue;
                if ($moduleName[$Module->get('shortName')]) {
                    self::getClass('ModuleAssociation')
                        ->set('hostID',$this->get('id'))
                        ->set('moduleID',$Module->get('id'))
                        ->set('state',1)
                        ->save();
                }
                unset($Module);
            }
            unset($DBModuleIDs,$RemoveModuleIDs);
        case ($this->isLoaded('printers')):
            $DBPrinterIDs = $this->getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
            $RemovePrinterIDs = array_diff((array)$DBPrinterIDs,(array)$this->get('printers'));
            if (count($RemovePrinterIDs)) {
                self::getClass('PrinterAssociationManager')->destroy(array('hostID'=>$this->get('id'),'printerID'=>$RemovePrinterIDs));
                $DBPrinterIDs = $this->getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
                unset($RemovePrinterIDs);
            }
            foreach ((array)self::getClass('PrinterManager')->find(array('id'=>array_diff((array)$this->get('printers'),(array)$DBPrinterIDs))) AS $i => $Printer) {
                if (!$Printer->isValid()) {
                    $Printer->destroy();
                    continue;
                }
                $Printer->addHost($this->get('id'))->save(false);
                unset($Printer);
            }
            unset($DBPrinterIDs,$RemovePrinterIDs);
        case ($this->isLoaded('snapins')):
            $DBSnapinIDs = $this->getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
            $RemoveSnapinIDs = array_diff((array)$DBSnapinIDs,(array)$this->get('snapins'));
            if (count($RemoveSnapinIDs)) {
                self::getClass('SnapinAssociationManager')->destroy(array('hostID'=>$this->get('id'),'snapinID'=>$RemoveSnapinIDs));
                $DBSnapinIDs = $this->getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
                unset($RemoveSnapinIDs);
            }
            foreach ((array)self::getClass('SnapinManager')->find(array('id'=>array_diff((array)$this->get('snapins'),(array)$DBSnapinIDs))) AS $i => $Snapin) {
                if (!$Snapin->isValid()) {
                    $Snapin->destroy();
                    continue;
                }
                $Snapin->addHost($this->get('id'))->save(false);
                unset($Snapin);
            }
            unset($DBSnapinIDs,$RemoveSnapinIDs);
        case ($this->isLoaded('groups')):
            $DBGroupIDs = $this->getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
            $RemoveGroupIDs = array_diff((array)$DBGroupIDs,(array)$this->get('groups'));
            if (count($RemoveGroupIDs)) {
                self::getClass('GroupAssociationManager')->destroy(array('hostID'=>$this->get('id'),'groupID'=>$RemoveGroupIDs));
                $DBGroupIDs = $this->getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
                unset($RemoveGroupIDs);
            }
            foreach ((array)self::getClass('GroupManager')->find(array('id'=>array_diff((array)$this->get('groups'),(array)$DBGroupIDs))) AS $i => $Group) {
                if (!$Group->isValid()) {
                    $Group->destroy();
                    continue;
                }
                $Group->addHost($this->get('id'))->save(false);
                unset($Group);
            }
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
        return (bool)count($this->getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id'),'printerID'=>$printerid,'isDefault'=>1),'printerID'));
    }
    public function updateDefault($printerid,$onoff) {
        self::getClass('PrinterAssociationManager')->update(array('printerID'=>$this->get('printers'),'hostID'=>$this->get('id')),'',array('isDefault'=>0));
        self::getClass('PrinterAssociationManager')->update(array('printerID'=>$printerid,'hostID'=>$this->get('id')),'',array('isDefault'=>$onoff));
        return $this;
    }
    public function getDispVals($key = '') {
        $keyTran = array(
            'width'=>'FOG_SERVICE_DISPLAYMANAGER_X',
            'height'=>'FOG_SERVICE_DISPLAYMANAGER_Y',
            'refresh'=>'FOG_SERVICE_DISPLAYMANAGER_R',
        );
        $HostScreen = self::getClass('HostScreenSettingsManager')->find(array('hostID'=>$this->get('id')));
        $HostScreen = @array_shift($HostScreen);
        $gScreen = $this->getSetting($keyTran[$key]);
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
        $HostALO = @array_shift($HostALO);
        $gTime = $this->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
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
        if (!$this->get('id')) return;
        $this->set('mac',self::getClass('MACAddress',$this->get('primac')->get('mac')));
    }
    protected function loadAdditionalMACs() {
        if (!$this->get('id')) return;
        $this->set('additionalMACs',$this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>array(null,0,'')),'mac'));
    }
    protected function loadPendingMACs() {
        if (!$this->get('id')) return;
        $this->set('pendingMACs',$this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>1),'mac'));
    }
    protected function loadGroups() {
        if (!$this->get('id')) return;
        $this->set('groups',$this->getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID'));
    }
    protected function loadGroupsnotinme() {
        if (!$this->get('id')) return;
        $find = array('id'=>$this->get('groups'));
        $this->set('groupsnotinme',$this->getSubObjectIDs('Group',$find,'id',true));
        unset($find);
    }
    protected function loadPrinters() {
        if (!$this->get('id')) return;
        $this->set('printers',$this->getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID'));
    }
    protected function loadPrintersnotinme() {
        if (!$this->get('id')) return;
        $find = array('id'=>$this->get('printers'));
        $this->set('printersnotinme',$this->getSubObjectIDs('Printer',$find,'id',true));
        unset($find);
    }
    protected function loadSnapins() {
        if (!$this->get('id')) return;
        $AssocSnapins = $this->getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
        $ValidSnapins = $this->getSubObjectIDs('Snapin',array('id'=>$AssocSnapins));
        $InvalidSnapins = array_unique(array_filter(array_diff((array)$AssocSnapins,(array)$ValidSnapins)));
        if (count($InvalidSnapins)) self::getClass('SnapinManager')->destroy(array('id'=>$InvalidSnapins));
        $this->set('snapins',$ValidSnapins);
    }
    protected function loadSnapinsnotinme() {
        if (!$this->get('id')) return;
        $find = array('id'=>$this->get('snapins'));
        $this->set('snapinsnotinme',$this->getSubObjectIDs('Snapin',$find,'id',true));
        unset($find);
    }
    protected function loadModules() {
        if (!$this->get('id')) return;
        $this->set('modules',$this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID'));
    }
    protected function loadUsers() {
        if (!$this->get('id')) return;
        $this->set('users',$this->getSubObjectIDs('UserTracking',array('hostID'=>$this->get('id'))));
    }
    protected function loadSnapinjob() {
        if (!$this->get('id')) return;
        $this->set('snapinjob',@max($this->getSubObjectIDs('SnapinJob',array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()),'hostID'=>$this->get('id')),'id')));
    }
    protected function loadInventory() {
        if (!$this->get('id')) return;
        $this->set('inventory',@max($this->getSubObjectIDs('Inventory',array('hostID'=>$this->get('id')),'id')));
    }
    protected function loadTask() {
        if (!$this->get('id')) return;
        $find['hostID'] = $this->get('id');
        $find['stateID'] = array_merge($this->getQueuedStates(),(array)$this->getProgressState());
        if (in_array($_REQUEST['type'], array('up','down'))) $find['typeID'] = ($_REQUEST['type'] == 'up' ? array(2,16) : array(1,8,15,17,24));
        $this->set('task',@max($this->getSubObjectIDs('Task',$find,'id')));
        unset($find);
    }
    protected function loadOptimalStorageNode() {
        if (!$this->get('id')) return;
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
        $this->HookManager->processEvent('HOST_NEW_SETTINGS',array('Host'=>&$this,'StorageNode'=>&$StorageNode,'StorageGroup'=>&$StorageGroup));
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
    private function createTasking($taskName, $taskTypeID, $username, $groupID, $memID, $imagingTask = true,$shutdown = false, $passreset = false, $debug = false) {
        $Task = self::getClass('Task')
            ->set('name',$taskName)
            ->set('createdBy',$username)
            ->set('hostID',$this->get('id'))
            ->set('isForced',0)
            ->set('stateID',$this->getQueuedState())
            ->set('typeID',$taskTypeID)
            ->set('NFSGroupID',$groupID)
            ->set('NFSMemberID',$memID);
        if ($imagingTask) $Task->set('imageID',$this->getImage()->get('id'));
        if ($shutdown) $Task->set('shutdown',$shutdown);
        if ($debug) $Task->set('isDebug',$debug);
        if ($passreset) $Task->set('passreset',$passreset);
        return $Task;
    }
    private function cancelJobsSnapinsForHost() {
        $SnapinJobs = $this->getSubObjectIDs('SnapinJob',array('hostID'=>$this->get('id'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())));
        self::getClass('SnapinTaskManager')->update(array('jobID'=>$SnapinJobs,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())),'',array('return'=>-9999,'details'=>_('Cancelled due to new tasking.')));
        self::getClass('SnapinJobManager')->update(array('id'=>$SnapinJobs),'',array('stateID'=>$this->getCancelledState()));
    }
    private function createSnapinTasking($snapin = -1) {
        try {
            if (self::getClass('SnapinAssociationManager')->count(array('hostID'=>$this->get('id'))) < 1) return;
            $SnapinJob = self::getClass('SnapinJob')
                ->set('hostID',$this->get('id'))
                ->set('stateID',$this->getQueuedState())
                ->set('createdTime',$this->nice_date()->format('Y-m-d H:i:s'));
            if (!$SnapinJob->save()) throw new Exception(_('Failed to create Snapin Job'));
            if ($snapin == -1) {
                if (count($this->get('snapins')) < 1) {
                    foreach ((array)self::getClass('SnapinManager')->find() AS $i => &$Snapin) {
                        if (!$Snapin->isValid()) continue;
                        self::getClass('SnapinTask')
                            ->set('jobID',$SnapinJob->get('id'))
                            ->set('stateID',$this->getQueuedState())
                            ->set('snapinID',$Snapin)
                            ->save();
                        unset($Snapin);
                    }
                } else {
                    foreach ((array)$this->get('snapins') AS $i => &$Snapin) {
                        self::getClass('SnapinTask')
                            ->set('jobID',$SnapinJob->get('id'))
                            ->set('stateID',$this->getQueuedState())
                            ->set('snapinID',$Snapin)
                            ->save();
                    }
                    unset($Snapin);
                }
            } else {
                $Snapin = self::getClass('Snapin',$snapin);
                if (!$Snapin->isValid()) throw new Exception(_('Snapin is not valid'));
                self::getClass('SnapinTask')
                    ->set('jobID',$SnapinJob->get('id'))
                    ->set('stateID',$this->getQueuedState())
                    ->set('snapinID',$snapin)
                    ->save();
                unset($Snapin);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        return $this;
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false) {
        try {
            if (!$this->isValid()) throw new Exception($this->foglang['HostNotValid']);
            $TaskType = self::getClass('TaskType',$taskTypeID);
            if (!$TaskType->isValid()) throw new Exception($this->foglang['TaskTypeNotValid']);
            if (!$TaskType->isSnapinTasking() && $this->getActiveTaskCount()) throw new Exception($this->foglang['InTask']);
            $imagingTypes = in_array($taskTypeID,array(1,2,8,15,16,17,24));
            $wolTypes = in_array($taskTypeID,array_merge(range(1,11),range(14,24)));
            if ($imagingTypes) {
                $Image = $this->getImage();
                if (!$Image->isValid()) throw new Exception($this->foglang['ImageNotValid']);
                if (!$Image->get('isEnabled')) throw new Exception(_('Image is not enabled'));
                $StorageGroup = $Image->getStorageGroup();
                if (!$StorageGroup->isValid()) throw new Exception($this->foglang['ImageGroupNotValid']);
                $StorageNode = ($TaskType->isUpload() ? $StorageGroup->getMasterStorageNode() : $this->getOptimalStorageNode());
                if (!$StorageNode || !$StorageNode->isValid()) $StorageNode = $StorageGroup->getOptimalStorageNode($this->get('imageID'));
                if (!$StorageNode || !$StorageNode->isValid()) throw new Exception($this->foglang['SGNotValid']);
                $imageTaskImgID = $this->get('imageID');
                $hostsWithImgID = $this->getSubObjectIDs('Host',array('imageID'=>$imageTaskImgID));
                $realImageID = $this->getSubObjectIDs('Host',array('id'=>$this->get('id')),'imageID');
                if (!in_array($this->get('id'),(array)$hostsWithImgID)) $this->set('imageID',array_shift($realImageID))->save();
                $this->set('imageID',$imageTaskImgID);
            }
            $isUpload = $TaskType->isUpload();
            $username = ($username ? $username : $_SESSION['FOG_USERNAME']);
            $Task = $this->createTasking($taskName, $taskTypeID, $username, $imagingTypes ? $StorageGroup->get('id') : 0, $imagingTypes ? $StorageNode->get('id') : 0, $imagingTypes,$shutdown,$passreset,$debug);
            $Task->set('imageID',$this->get('imageID'));
            if (!$Task->save()) throw new Exception($this->foglang['FailedTask']);
            if ($TaskType->isSnapinTask()) {
                if ($deploySnapins === true) $deploySnapins = -1;
                $this->cancelJobsSnapinsForHost();
                $mac = $this->get('mac');
                if ($deploySnapins) $this->createSnapinTasking($deploySnapins);
            }
            if ($TaskType->isMulticast()) {
                $assoc = false;
                $MultiSessName = current((array)self::getClass('MulticastSessionsManager')->find(array('name'=>$taskName,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))));
                $MultiSessAssoc = current((array)self::getClass('MulticastSessionsManager')->find(array('image'=>$this->getImage()->get('id'),'stateID'=>0)));
                if ($sessionjoin && $MultiSessName && $MultiSessName->isValid()) {
                    $MulticastSession = $MultiSessName;
                    $assoc = true;
                } else if ($MultiSessAssoc && $MultiSessAssoc->isValid()) {
                    $MulticastSession = $MultiSessAssoc;
                    $assoc = true;
                } else {
                    $port = $this->getSetting('FOG_UDPCAST_STARTINGPORT');
                    $portOverride = $this->getSetting('FOG_MULTICAST_PORT_OVERRIDE');
                    $MulticastSession = self::getClass('MulticastSessions')
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
                        if (!$this->getSetting('FOG_MULTICAST_PORT_OVERRIDE')) {
                            $randomnumber = mt_rand(24576,32766)*2;
                            while ($randomnumber == $MulticastSession->get('port')) $randomnumber = mt_rand(24576,32766)*2;
                            $this->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
                        }
                    }
                    $assoc = true;
                }
                if ($assoc) {
                    self::getClass('MulticastSessionsAssociation')
                        ->set('msID',$MulticastSession->get('id'))
                        ->set('taskID',$Task->get('id'))
                        ->save();
                }
            }
            if ($wolTypes) $this->wakeOnLAN();
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
            $this->FOGCore->error(sprintf('%s():xError: %s', __FUNCTION__, $e->getMessage()));
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
        $mac = $this->getMyMacs();
        $Nodes = self::getClass('StorageNodeManager')->find(array('isEnabled'=>1));
        foreach ($Nodes AS $i => &$Node) {
            $curroot = trim(trim($Node->get('webroot'),'/'));
            $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
            $URLs[] = sprintf('http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s',$Node->get('ip'),$webroot,implode('|',(array)$mac));
        }
        $curroot = trim(trim($this->getSetting('FOG_WEB_ROOT'),'/'));
        $webroot = sprintf('/%s',(strlen($curroot) > 1 ? sprintf('%s/',$curroot) : ''));
        $URLs[] = sprintf('http://%s%smanagement/index.php?node=client&sub=wakeEmUp&mac=%s',$this->getSetting('FOG_WEB_HOST'),$webroot,implode('|',(array)$mac));
        $this->FOGURLRequests->process($URLs,'GET');
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
        return $this;
    }
    public function removeAddMAC($removeArray) {
        foreach((array)$removeArray AS $i => &$item) $this->remove('additionalMACs',self::getClass('MACAddress',$item));
        unset($item);
        return $this;
    }
    public function removePendMAC($removeArray) {
        foreach((array)$removeArray AS $i => &$item) $this->remove('pendingMACs',self::getClass('MACAddress',$item));
        unset($item);
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
    public function addPrinter($addArray) {
        $this->set('printers',array_unique(array_merge((array)$this->get('printers'),(array)$addArray)));
        return $this;
    }
    public function removePrinter($removeArray) {
        $this->set('printers',array_unique(array_diff((array)$this->get('printers'),(array)$removeArray)));
        return $this;
    }
    public function addSnapin($addArray) {
        $limit = $this->getSetting('FOG_SNAPIN_LIMIT');
        if ($limit > 0) {
            if (self::getClass('SnapinManager')->count(array('id'=>$this->get('snapins'))) >= $limit || count($addArray) > $limit) throw new Exception(sprintf('%s %d %s',_('You are only allowed to assign'),$limit,$limit == 1 ? _('snapin per host') : _('snapins per host')));
        }
        $this->set('snapins',array_unique(array_merge((array)$this->get('snapins'),(array)$addArray)));
        return $this;
    }
    public function removeSnapin($removeArray) {
        $this->set('snapins',array_unique(array_diff((array)$this->get('snapins'),(array)$removeArray)));
        return $this;
    }
    public function addModule($addArray) {
        $this->set('modules',array_unique(array_merge((array)$this->get('modules'),(array)$addArray)));
        return $this;
    }
    public function removeModule($removeArray) {
        $this->set('modules',array_unique(array_diff((array)$this->get('modules'),(array)$removeArray)));
        return $this;
    }
    public function getMyMacs($justme = true) {
        if ($justme) return $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id')),'mac');
        return $this->getSubObjectIDs('MACAddressAssociation','','mac');
    }
    public function ignore($imageIgnore,$clientIgnore) {
        $MyMACs = $this->getMyMacs();
        $igMACs = $cgMACs = array();
        foreach ((array)$imageIgnore AS $i => &$igMAC) {
            $igMAC = self::getClass('MACAddress',$igMAC);
            if (!$igMAC->isValid()) continue;
            if (in_array($igMAC->__toString(),$igMACs)) continue;
            $igMACs[] = $igMAC->__toString();
            unset($igMAC);
        }
        foreach ((array)$clientIgnore AS $i => &$cgMAC) {
            $cgMAC = self::getClass('MACAddress',$cgMAC);
            if (!$cgMAC->isValid()) continue;
            if (in_array($cgMAC->__toString(),$cgMACs)) continue;
            $cgMACs[] = $cgMAC->__toString();
            unset($cgMAC);
        }
        self::getClass('MACAddressAssociationManager')->update(array('mac'=>array_diff($MyMACs,$cgMACs),'hostID'=>$this->get('id')),'',array('clientIgnore'=>0));
        self::getClass('MACAddressAssociationManager')->update(array('mac'=>array_diff($MyMACs,$igMACs),'hostID'=>$this->get('id')),'',array('imageIgnore'=>0));
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
        $this->set('groups',array_unique(array_merge((array)$this->get('groups'),(array)$addArray)));
        return $this;
    }
    public function removeHost($removeArray) {
        $this->set('groups',array_unique(array_diff((array)$this->get('groups'),(array)$removeArray)));
        return $this;
    }
    public function clientMacCheck($MAC = false) {
        return self::getClass('MACAddress',$this->getSubObjectIDs('MACAddressAssociation',array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'clientIgnore'=>1),'mac'))->isValid() ? 'checked' : '';
    }
    public function imageMacCheck($MAC = false) {
        return self::getClass('MACAddress',$this->getSubObjectIDs('MACAddressAssociation',array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'imageIgnore'=>1),'mac'))->isValid() ? 'checked' : '';
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
        if (filter_var($this->get('ip'),FILTER_VALIDATE_IP) === false) $this->set('ip',$this->FOGCore->resolveHostname($this->get('name')));
        if (filter_var($this->get('ip'),FILTER_VALIDATE_IP) === false) $this->set('ip',$this->get('name'));
        $this->getManager()->update(array('id'=>$this->get('id')),'',array('pingstatus'=>self::getClass('Ping',$this->get('ip'))->execute(),'ip'=>$org_ip));
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
