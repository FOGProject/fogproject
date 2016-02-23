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
            if (!($value instanceof MACAddress)) $value = $this->getClass('MACAddress',$value);
            break;
        case 'additionalMACs':
        case 'pendingMACs':
            foreach((array)$value AS $i => &$mac) $newValue[] = $this->getClass('MACAddress',$mac);
            unset($mac);
            $value = (array)$newValue;
            break;
        case 'snapinjob':
            if (!($value instanceof SnapinJob)) $value = $this->getClass('SnapinJob',$value);
            break;
        case 'inventory':
            if (!($value instanceof Inventory)) $value = $this->getClass('Inventory',$value);
            break;
        case 'task':
            if (!($value instanceof Task)) $value = $this->getClass('Task',$value);
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
            if (!($value instanceof MACAddress)) $value = $this->getClass('MACAddress',$value);
            break;
        }
        return parent::add($key,$value);
    }
    public function destroy($field = 'id') {
        $find = array('hostID'=>$this->get('id'));
        $this->getClass('NodeFailureManager')->destroy($find);
        $this->getClass('ImagingLogManager')->destroy($find);
        $this->getClass('SnapinTaskManager')->destroy(array('jobID'=>$this->getSubObjectIDs('SnapinJob',$find,'id')));
        $this->getClass('SnapinJobManager')->destroy($find);
        $this->getClass('TaskManager')->destroy($find);
        $this->getClass('ScheduledTaskManager')->destroy($find);
        $this->getClass('HostAutoLogoutManager')->destroy($find);
        $this->getClass('HostScreenSettingsManager')->destroy($find);
        $this->getClass('GroupAssociationManager')->destroy($find);
        $this->getClass('SnapinAssociationManager')->destroy($find);
        $this->getClass('PrinterAssociationManager')->destroy($find);
        $this->getClass('ModuleAssociationManager')->destroy($find);
        $this->getClass('GreenFogManager')->destroy($find);
        $this->getClass('InventoryManager')->destroy($find);
        $this->getClass('UserTrackingManager')->destroy($find);
        $this->getClass('MACAddressAssociationManager')->destroy($find);
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
            if (count($CurrPriMAC) === 1 && $CurrPriMAC[0] != $RealPriMAC) $this->getClass('MACAddressAssociationManager')->update(array('mac'=>$CurrPriMAC[0],'hostID'=>$this->get('id'),'primary'=>1),'',array('primary'=>0));
            $HostWithMAC = array_diff((array)$this->get('id'),(array)$this->getSubObjectIDs('MACAddressAssociation',array('mac'=>$RealPriMAC),'hostID'));
            if (count($HostWithMAC) && !in_array($this->get('id'),(array)$HostWithMAC)) throw new Exception(_('This MAC Belongs to another host'));
            $DBPriMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            $RemoveMAC = array_diff((array)$RealPriMAC,(array)$DBPriMACs);
            if (count($RemoveMAC)) {
                $this->getClass('MACAddressAssociationManager')->destroy(array('mac'=>$RemoveMAC));
                unset($RemoveMAC);
                $DBPriMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>1),'mac');
            }
            if (!in_array($RealPriMAC,$DBPriMACs)) {
                $this->getClass('MACAddressAssociation')
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
                $this->getClass('MACAddressAssociationManager')->destroy(array('hostID'=>$this->get('id'),'mac'=>$RemoveAddMAC));
                $DBAddMACs = $this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(0,null),'pending'=>array(0,null)),'mac');
                unset($RemoveAddMAC);
            }
            $RealAddMACs = array_diff((array)$RealAddMACs,(array)$DBAddMACs);
            unset($DBAddMACs);
            foreach ((array)$RealAddMACs AS $i => &$RealAddMAC) {
                $this->getClass('MACAddressAssociation')
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
                $this->getClass('MACAddressAssociationManager')->destroy(array('hostID'=>$this->get('id'),'mac'=>$RemovePendMAC));
                $DBPendMACs = $this->getSubObjectIDs('MACAddressAssociation',array('primary'=>array(0,null),'pending'=>1),'mac');
                unset($RemovePendMAC);
            }
            $RealPendMACs = array_diff((array)$RealPendMACs,(array)$DBPendMACs);
            unset($DBPendMACs);
            foreach ((array)$RealPendMACs AS $i => &$RealPendMAC) {
                $this->getClass('MACAddressAssociation')
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
                $this->getClass('ModuleAssociationManager')->destroy(array('moduleID'=>$RemoveModuleIDs,'hostID'=>$this->get('id')));
                $DBModuleIDs = $this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID');
                unset($RemoveModuleIDs);
            }
            $moduleName = $this->getGlobalModuleStatus();
            foreach((array)$this->getClass('ModuleManager')->find(array('id'=>array_diff((array)$this->get('modules'),(array)$DBModuleIDs))) AS $i => &$Module) {
                if (!$Module->isValid()) continue;
                if ($moduleName[$Module->get('shortName')]) {
                    $this->getClass('ModuleAssociation')
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
                $this->getClass('PrinterAssociationManager')->destroy(array('hostID'=>$this->get('id'),'printerID'=>$RemovePrinterIDs));
                $DBPrinterIDs = $this->getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID');
                unset($RemovePrinterIDs);
            }
            foreach ((array)$this->getClass('PrinterManager')->find(array('id'=>array_diff((array)$this->get('printers'),(array)$DBPrinterIDs))) AS $i => $Printer) {
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
                $this->getClass('SnapinAssociationManager')->destroy(array('hostID'=>$this->get('id'),'snapinID'=>$RemoveSnapinIDs));
                $DBSnapinIDs = $this->getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
                unset($RemoveSnapinIDs);
            }
            foreach ((array)$this->getClass('SnapinManager')->find(array('id'=>array_diff((array)$this->get('snapins'),(array)$DBSnapinIDs))) AS $i => $Snapin) {
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
                $this->getClass('GroupAssociationManager')->destroy(array('hostID'=>$this->get('id'),'groupID'=>$RemoveGroupIDs));
                $DBGroupIDs = $this->getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID');
                unset($RemoveGroupIDs);
            }
            foreach ((array)$this->getClass('GroupManager')->find(array('id'=>array_diff((array)$this->get('groups'),(array)$DBGroupIDs))) AS $i => $Group) {
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
        $this->getClass('PrinterAssociationManager')->update(array('printerID'=>$this->get('printers'),'hostID'=>$this->get('id')),'',array('isDefault'=>0));
        $this->getClass('PrinterAssociationManager')->update(array('printerID'=>$printerid,'hostID'=>$this->get('id')),'',array('isDefault'=>$onoff));
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
        $gScreen = $this->getSetting($keyTran[$key]);
        return ($HostScreen instanceof HostScreenSettings && $HostScreen->isValid() ? $HostScreen->get($key) : $gScreen);
    }
    public function setDisp($x,$y,$r) {
        $this->getClass('HostScreenSettingsManager')->destroy(array('hostID'=>$this->get('id')));
        $this->getClass('HostScreenSettings')
            ->set('hostID',$this->get('id'))
            ->set('width',$x)
            ->set('height',$y)
            ->set('refresh',$r)
            ->save();
        return $this;
    }
    public function getAlo() {
        $HostALO = $this->getClass('HostAutoLogoutManager')->find(array('hostID'=>$this->get('id')));
        $HostALO = @array_shift($HostALO);
        $gTime = $this->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
        return ($HostALO && $HostALO->isValid() ? $HostALO->get('time') : $gTime);
    }
    public function setAlo($time) {
        $this->getClass('HostAutoLogoutManager')->destroy(array('hostID'=>$this->get('id')));
        $this->getClass('HostAutoLogout')
            ->set('hostID',$this->get('id'))
            ->set('time',$time)
            ->save();
        return $this;
    }
    protected function loadMac() {
        if ($this->get('id')) $this->set('mac',$this->getClass('MACAddress',$this->get('primac')->get('mac')));
    }
    protected function loadAdditionalMACs() {
        if ($this->get('id')) $this->set('additionalMACs',$this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>array(null,0,'')),'mac'));
    }
    protected function loadPendingMACs() {
        if ($this->get('id')) $this->set('pendingMACs',$this->getSubObjectIDs('MACAddressAssociation',array('hostID'=>$this->get('id'),'primary'=>array(null,0,''),'pending'=>1),'mac'));
    }
    protected function loadGroups() {
        if ($this->get('id')) $this->set('groups',$this->getSubObjectIDs('GroupAssociation',array('hostID'=>$this->get('id')),'groupID'));
    }
    protected function loadGroupsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('groups'));
            $this->set('groupsnotinme',$this->getSubObjectIDs('Group',$find,'id',true));
            unset($find);
        }
    }
    protected function loadPrinters() {
        if ($this->get('id')) $this->set('printers',$this->getSubObjectIDs('PrinterAssociation',array('hostID'=>$this->get('id')),'printerID'));
    }
    protected function loadPrintersnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('printers'));
            $this->set('printersnotinme',$this->getSubObjectIDs('Printer',$find,'id',true));
            unset($find);
        }
    }
    protected function loadSnapins() {
        if ($this->get('id')) {
            $AssocSnapins = $this->getSubObjectIDs('SnapinAssociation',array('hostID'=>$this->get('id')),'snapinID');
            $ValidSnapins = $this->getSubObjectIDs('Snapin',array('id'=>$AssocSnapins));
            $InvalidSnapins = array_unique(array_filter(array_diff((array)$AssocSnapins,(array)$ValidSnapins)));
            if (count($InvalidSnapins)) $this->getClass('SnapinManager')->destroy(array('id'=>$InvalidSnapins));
            $this->set('snapins',$ValidSnapins);
        }
    }
    protected function loadSnapinsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('snapins'));
            $this->set('snapinsnotinme',$this->getSubObjectIDs('Snapin',$find,'id',true));
            unset($find);
        }
    }
    protected function loadModules() {
        if ($this->get('id')) $this->set('modules',$this->getSubObjectIDs('ModuleAssociation',array('hostID'=>$this->get('id')),'moduleID'));
    }
    protected function loadUsers() {
        if ($this->get('id')) $this->set('users',$this->getSubObjectIDs('UserTracking',array('hostID'=>$this->get('id'))));
    }
    protected function loadSnapinjob() {
        if ($this->get('id')) $this->set('snapinjob',@max($this->getSubObjectIDs('SnapinJob',array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()),'hostID'=>$this->get('id')),'id')));
    }
    protected function loadInventory() {
        if ($this->get('id')) $this->set('inventory',@max($this->getSubObjectIDs('Inventory',array('hostID'=>$this->get('id')),'id')));
    }
    protected function loadTask() {
        if ($this->get('id')) {
            $find['hostID'] = $this->get('id');
            $find['stateID'] = array_merge($this->getQueuedStates(),(array)$this->getProgressState());
            if (in_array($_REQUEST['type'], array('up','down'))) $find['typeID'] = ($_REQUEST['type'] == 'up' ? array(2,16) : array(1,8,15,17,24));
            $this->set('task',@max($this->getSubObjectIDs('Task',$find,'id')));
            unset($find);
        }
    }
    protected function loadOptimalStorageNode() {
        if ($this->get('id')) $this->set('optimalStorageNode', $this->getClass('Image',$this->get('imageID'))->getStorageGroup()->getOptimalStorageNode());
    }
    public function getActiveTaskCount() {
        return $this->getClass('TaskManager')->count(array('stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()),'hostID'=>$this->get('id')));
    }
    public function isValidToImage() {
        return ($this->getImage()->isValid() && $this->getOS()->isValid() && $this->getImage()->getStorageGroup()->isValid() && $this->getImage()->getStorageGroup()->getStorageNode()->isValid());
    }
    public function getOptimalStorageNode() {
        return $this->get('optimalStorageNode');
    }
    public function checkIfExist($taskTypeID) {
        $res = true;
        $TaskType = $this->getClass('TaskType',$taskTypeID);
        $isUpload = $TaskType->isUpload();
        $Image = $this->getImage();
        $StorageGroup = $Image->getStorageGroup();
        $StorageNode = ($isUpload ? $StorageGroup->getMasterStorageNode() : $this->getOptimalStorageNode());
        if (!$isUpload)	$this->HookManager->processEvent('HOST_NEW_SETTINGS',array('Host'=>&$this,'StorageNode'=>&$StorageNode,'StorageGroup'=>&$StorageGroup));
        if (!$StorageGroup || !$StorageGroup->isValid()) throw new Exception(_('No Storage Group found for this image'));
        if (!$StorageNode || !$StorageNode->isValid()) throw new Exception(_('No Storage Node found for this image'));
        if (in_array($TaskType->get('id'),array(1,8,15,17))) {
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
        $this->getClass('SnapinTaskManager')->update(array('jobID'=>$SnapinJobs,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())),'',array('return'=>-9999,'details'=>_('Cancelled due to new tasking.')));
        $this->getClass('SnapinJobManager')->update(array('id'=>$SnapinJobs),'',array('stateID'=>$this->getCancelledState()));
    }
    private function createSnapinTasking($snapin = -1) {
        try {
            if ($this->getClass('SnapinAssociationManager')->count(array('hostID'=>$this->get('id'))) < 1) return;
            $SnapinJob = $this->getClass('SnapinJob')
                ->set('hostID',$this->get('id'))
                ->set('stateID',$this->getQueuedState())
                ->set('createdTime',$this->nice_date()->format('Y-m-d H:i:s'));
            if (!$SnapinJob->save()) throw new Exception(_('Failed to create Snapin Job'));
            if ($snapin == -1) {
                if (count($this->get('snapins')) < 1) {
                    foreach ((array)$this->getClass('SnapinManager')->find() AS $i => &$Snapin) {
                        if (!$Snapin->isValid()) continue;
                        $this->getClass('SnapinTask')
                            ->set('jobID',$SnapinJob->get('id'))
                            ->set('stateID',$this->getQueuedState())
                            ->set('snapinID',$Snapin)
                            ->save();
                        unset($Snapin);
                    }
                } else {
                    foreach ((array)$this->get('snapins') AS $i => &$Snapin) {
                        $this->getClass('SnapinTask')
                            ->set('jobID',$SnapinJob->get('id'))
                            ->set('stateID',$this->getQueuedState())
                            ->set('snapinID',$Snapin)
                            ->save();
                    }
                    unset($Snapin);
                }
            } else {
                $Snapin = $this->getClass('Snapin',$snapin);
                if (!$Snapin->isValid()) throw new Exception(_('Snapin is not valid'));
                $this->getClass('SnapinTask')
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
            if (!in_array($taskTypeID,array(12,13)) && $this->getActiveTaskCount()) throw new Exception($this->foglang['InTask']);
            $TaskType = $this->getClass('TaskType',$taskTypeID);
            if (!$TaskType->isValid()) throw new Exception($this->foglang['TaskTypeNotValid']);
            $imagingTypes = in_array($taskTypeID,array(1,2,8,15,16,17,24));
            $wolTypes = in_array($taskTypeID,array_merge(range(1,11),range(14,24)));
            if ($imagingTypes) {
                $Image = $this->getImage();
                if (!$Image->isValid()) throw new Exception($this->foglang['ImageNotValid']);
                if (!$Image->get('isEnabled')) throw new Exception(_('Image is not enabled'));
                $StorageGroup = $Image->getStorageGroup();
                if (!$StorageGroup->isValid()) throw new Exception($this->foglang['ImageGroupNotValid']);
                $StorageNode = ($isUpload ? $StorageGroup->getOptimalStorageNode() : $this->getOptimalStorageNode());
                if (!$StorageNode || !$StorageNode->isValid()) $StorageNode = $StorageGroup->getOptimalStorageNode();
                if (!$StorageNode->isValid()) throw new Exception($this->foglang['SGNotValid']);
                $imageTaskImgID = $this->get('imageID');
                $hostsWithImgID = $this->getSubObjectIDs('Host',array('imageID'=>$imageTaskImgID));
                $realImageID = $this->getSubObjectIDs('Host',array('id'=>$this->get('id')),'imageID');
                if (!in_array($this->get('id'),(array)$hostsWithImgID)) $this->set('imageID',array_shift($realImageID))->save();
                $this->set('imageID',$imageTaskImgID);
            }
            $isUpload = $TaskType->isUpload();
            $username = ($username ? $username : $_SESSION['FOG_USERNAME']);
            $Task = $this->createTasking($taskName, $taskTypeID, $username, $imagingTypes ? $StorageGroup->get('id') : 0, $imagingTypes ? $StorageGroup->getOptimalStorageNode()->get('id') : 0, $imagingTypes,$shutdown,$passreset,$debug);
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
                $MultiSessName = current((array)$this->getClass('MulticastSessionsManager')->find(array('name'=>$taskName,'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState()))));
                $MultiSessAssoc = current((array)$this->getClass('MulticastSessionsManager')->find(array('image'=>$this->getImage()->get('id'),'stateID'=>0)));
                if ($sessionjoin && $MultiSessName && $MultiSessName->isValid()) {
                    $MulticastSession = $MultiSessName;
                    $assoc = true;
                } else if ($MultiSessAssoc && $MultiSessAssoc->isValid()) {
                    $MulticastSession = $MultiSessAssoc;
                    $assoc = true;
                } else {
                    $port = $this->getSetting('FOG_UDPCAST_STARTINGPORT');
                    $portOverride = $this->getSetting('FOG_MULTICAST_PORT_OVERRIDE');
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
                        if (!$this->getSetting('FOG_MULTICAST_PORT_OVERRIDE')) {
                            $randomnumber = mt_rand(24576,32766)*2;
                            while ($randomnumber == $MulticastSession->get('port')) $randomnumber = mt_rand(24576,32766)*2;
                            $this->setSetting('FOG_UDPCAST_STARTINGPORT',$randomnumber);
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
        foreach((array)$removeArray AS $i => &$item) $this->remove('additionalMACs',$this->getClass('MACAddress',$item));
        unset($item);
        return $this;
    }
    public function removePendMAC($removeArray) {
        foreach((array)$removeArray AS $i => &$item) $this->remove('pendingMACs',$this->getClass('MACAddress',$item));
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
            if ($this->getClass('SnapinManager')->count(array('id'=>$this->get('snapins'))) >= $limit || count($addArray) > $limit) throw new Exception(sprintf('%s %d %s',_('You are only allowed to assign'),$limit,$limit == 1 ? _('snapin per host') : _('snapins per host')));
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
            $igMAC = $this->getClass('MACAddress',$igMAC);
            if (!$igMAC->isValid()) continue;
            if (in_array($igMAC->__toString(),$igMACs)) continue;
            $igMACs[] = $igMAC->__toString();
            unset($igMAC);
        }
        foreach ((array)$clientIgnore AS $i => &$cgMAC) {
            $cgMAC = $this->getClass('MACAddress',$cgMAC);
            if (!$cgMAC->isValid()) continue;
            if (in_array($cgMAC->__toString(),$cgMACs)) continue;
            $cgMACs[] = $cgMAC->__toString();
            unset($cgMAC);
        }
        $this->getClass('MACAddressAssociationManager')->update(array('mac'=>array_diff($MyMACs,$cgMACs),'hostID'=>$this->get('id')),'',array('clientIgnore'=>0));
        $this->getClass('MACAddressAssociationManager')->update(array('mac'=>array_diff($MyMACs,$igMACs),'hostID'=>$this->get('id')),'',array('imageIgnore'=>0));
        if (count($cgMACs)) $this->getClass('MACAddressAssociationManager')->update(array('mac'=>$cgMACs,'hostID'=>$this->get('id')),'',array('clientIgnore'=>1));
        if (count($igMACs)) $this->getClass('MACAddressAssociationManager')->update(array('mac'=>$igMACs,'hostID'=>$this->get('id')),'',array('imageIgnore'=>1));
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
        return $this->getClass('MACAddress',$this->getSubObjectIDs('MACAddressAssociation',array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'clientIgnore'=>1),'mac'))->isValid() ? 'checked' : '';
    }
    public function imageMacCheck($MAC = false) {
        return $this->getClass('MACAddress',$this->getSubObjectIDs('MACAddressAssociation',array('mac'=>($MAC ? $MAC : $this->get('mac')),'hostID'=>$this->get('id'),'imageIgnore'=>1),'mac'))->isValid() ? 'checked' : '';
    }
    public function setAD($useAD = '',$domain = '',$ou = '',$user = '',$pass = '',$override = false,$nosave = false,$legacy = '',$productKey = '') {
        if ($this->get('id')) {
            if (!$override) {
                if (empty($useAD)) $useAD = $this->get('useAD');
                if (empty($domain))	$domain = trim($this->get('ADDomain'));
                if (empty($ou)) $ou = trim($this->get('ADOU'));
                if (empty($user)) $user = trim($this->get('ADUser'));
                if (empty($pass)) $pass = trim($this->encryptpw($this->get('ADPass')));
                if (empty($legacy)) $legacy = trim($this->get('ADPassLegacy'));
                if (empty($productKey)) $productKey = trim($this->encryptpw($this->get('productKey')));
            }
        }
        if ($pass) $pass = trim($this->encryptpw($pass));
        $this->set('useAD',$useAD)
            ->set('ADDomain',trim($domain))
            ->set('ADOU',trim($ou))
            ->set('ADUser',trim($user))
            ->set('ADPass',trim($this->encryptpw($pass)))
            ->set('ADPassLegacy',$legacy)
            ->set('productKey',trim($this->encryptpw($productKey)));
        if (!$nosave) $this->save();
        return $this;
    }
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
    public function setPingStatus() {
        $org_ip = $this->get('ip');
        if (filter_var($this->get('ip'),FILTER_VALIDATE_IP) === false) $this->set('ip',$this->FOGCore->resolveHostname($this->get('name')));
        if (filter_var($this->get('ip'),FILTER_VALIDATE_IP) === false) $this->set('ip',$this->get('name'));
        $this->getManager()->update(array('id'=>$this->get('id')),'',array('pingstatus'=>$this->getClass('Ping',$this->get('ip'))->execute(),'ip'=>$org_ip));
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
