<?php
/**
 * The host object (main item FOG deals with
 *
 * PHP version 5
 *
 * @category Host
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://fogproject.org
 */
/**
 * The host object (main item FOG deals with
 *
 * @category Host
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0.txt GPLv3
 * @link     https://fogproject.org
 */
class Host extends FOGController
{
    /**
     * The host table
     *
     * @var string
     */
    protected $databaseTable = 'hosts';
    /**
     * The Host table fields and common names
     *
     * @var array
     */
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
        'enforce' => 'hostEnforce'
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name'
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'mac',
        'primac',
        'imagename',
        'additionalMACs',
        'pendingMACs',
        'groups',
        'groupsnotinme',
        'hostscreen',
        'hostalo',
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
        'powermanagementtasks'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'MACAddressAssociation' => array(
            'hostID',
            'id',
            'primac',
            array('primary' => 1)
        ),
        'Image' => array(
            'id',
            'imageID',
            'imagename'
        ),
        'HostScreenSetting' => array(
            'hostID',
            'id',
            'hostscreen'
        ),
        'HostAutoLogout' => array(
            'hostID',
            'id',
            'hostalo'
        ),
        'Inventory' => array(
            'hostID',
            'id',
            'inventory'
        )
    );
    /**
     * Display val storage
     *
     * @var array
     */
    private static $_hostscreen = array();
    /**
     * ALO time val
     *
     * @var int
     */
    private static $_hostalo = array();
    /**
     * Set value to key
     *
     * @param string $key   the key to set to
     * @param mixed  $value the value to set
     *
     * @throws Exception
     * @return object
     */
    public function set($key, $value)
    {
        $key = $this->key($key);
        switch ($key) {
        case 'mac':
            if (!($value instanceof MACAddress)) {
                $value = new MACAddress($value);
                $value = $value->__toString();
            }
            break;
        case 'additionalMACs':
        case 'pendingMACs':
            $newValue = array_map(
                function (&$mac) {
                    return new MACAddress($mac);
                },
                (array)$value
            );
            $value = (array)$newValue;
            break;
        case 'snapinjob':
            if (!($value instanceof SnapinJob)) {
                $value = new SnapinJob($value);
            }
            break;
        case 'task':
            if (!($value instanceof Task)) {
                $value = new Task($value);
            }
            break;
        }
        return parent::set($key, $value);
    }
    /**
     * Add value to key (array)
     *
     * @param string $key   the key to add to
     * @param mixed  $value the value to add
     *
     * @throws Exception
     * @return object
     */
    public function add($key, $value)
    {
        $key = $this->key($key);
        switch ($key) {
        case 'additionalMACs':
        case 'pendingMACs':
            if (!($value instanceof MACAddress)) {
                $value = new MACAddress($value);
            }
            break;
        }
        return parent::add($key, $value);
    }
    /**
     * Removes the item from the database
     *
     * @param string $key the key to remove
     *
     * @throws Exception
     * @return object
     */
    public function destroy($key = 'id')
    {
        $find = array('hostID' => $this->get('id'));
        self::getClass('NodeFailureManager')
            ->destroy($find);
        self::getClass('ImagingLogManager')
            ->destroy($find);
        self::getClass('SnapinTaskManager')
            ->destroy(
                array(
                    'jobID' => self::getSubObjectIDs(
                        'SnapinJob',
                        $find,
                        'id'
                    )
                )
            );
        self::getClass('SnapinJobManager')
            ->destroy($find);
        self::getClass('TaskManager')
            ->destroy($find);
        self::getClass('ScheduledTaskManager')
            ->destroy($find);
        self::getClass('HostAutoLogoutManager')
            ->destroy($find);
        self::getClass('HostScreenSettingManager')
            ->destroy($find);
        self::getClass('GroupAssociationManager')
            ->destroy($find);
        self::getClass('SnapinAssociationManager')
            ->destroy($find);
        self::getClass('PrinterAssociationManager')
            ->destroy($find);
        self::getClass('ModuleAssociationManager')
            ->destroy($find);
        self::getClass('GreenFogManager')
            ->destroy($find);
        self::getClass('InventoryManager')
            ->destroy($find);
        self::getClass('UserTrackingManager')
            ->destroy($find);
        self::getClass('MACAddressAssociationManager')
            ->destroy($find);
        self::getClass('PowerManagementManager')
            ->destroy($find);
        self::$HookManager
            ->processEvent(
                'DESTROY_HOST',
                array(
                    'Host' => &$this
                )
            );
        return parent::destroy($key);
    }
    /**
     * Returns Valid MACs
     *
     * @param array $macs the array of macs
     * @param array $arr  the array to define
     *
     * @return array
     */
    private static function _retValidMacs($macs, &$arr)
    {
        $addMacs = array();
        foreach ((array)$macs as &$mac) {
            if (!($mac instanceof MACAddress)) {
                $mac = new MACAddress($mac);
            }
            if (!$mac->isValid()) {
                continue;
            }
            $addMacs[] = $mac->__toString();
            unset($mac);
        }
        return $arr = $addMacs;
    }
    /**
     * Stores data into the database
     *
     * @return bool|object
     */
    public function save()
    {
        parent::save();
        if ($this->isLoaded('mac')) {
            if (!$this->get('mac')->isValid()) {
                throw new Exception(self::$foglang['InvalidMAC']);
            }
            $RealPriMAC = $this->get('mac')->__toString();
            $CurrPriMAC = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'hostID' => $this->get('id'),
                    'primary' => 1
                ),
                'mac'
            );
            if (count($CurrPriMAC) === 1
                && $CurrPriMAC[0] != $RealPriMAC
            ) {
                self::getClass('MACAddressAssociationManager')
                    ->destroy(
                        array(
                            'hostID' => $this->get('id'),
                            'mac' => $CurrPriMAC[0]
                        )
                    );
            }
            $HostWithMAC = array_diff(
                (array)$this->get('id'),
                (array)self::getSubObjectIDs(
                    'MACAddressAssociation',
                    array('mac' => $RealPriMAC),
                    'hostID'
                )
            );
            if (count($HostWithMAC)
                && !in_array($this->get('id'), (array)$HostWithMAC)
            ) {
                throw new Exception(_('This MAC Belongs to another host'));
            }
            $DBPriMACs = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'hostID' => $this->get('id'),
                    'primary' => 1
                ),
                'mac'
            );
            $RemoveMAC = array_diff(
                (array)$RealPriMAC,
                (array)$DBPriMACs
            );
            if (count($RemoveMAC)) {
                self::getClass('MACAddressAssociationManager')
                    ->destroy(
                        array('mac' => $RemoveMAC)
                    );
                unset($RemoveMAC);
                $DBPriMACs = self::getSubObjectIDs(
                    'MACAddressAssociation',
                    array(
                        'hostID' => $this->get('id'),
                        'primary' => 1
                    ),
                    'mac'
                );
            }
            if (!in_array($RealPriMAC, $DBPriMACs)) {
                self::getClass('MACAddressAssociation')
                    ->set('hostID', $this->get('id'))
                    ->set('mac', $RealPriMAC)
                    ->set('primary', 1)
                    ->save();
            }
            unset(
                $DBPriMACs,
                $RealPriMAC,
                $RemoveMAC,
                $HostWithMAC
            );
        }
        if ($this->isLoaded('additionalMACs')) {
            self::_retValidMacs(
                $this->get('additionalMACs'),
                $addMacs
            );
            $RealAddMACs = array_filter($addMacs);
            unset($addMacs);
            $RealAddMACs = array_unique($RealAddMACs);
            $RealAddMACs = array_filter($RealAddMACs);
            $DBPriMACs = self::getSubObjectIDs(
                'MACAddressAssociation',
                array('primary' => 1),
                'mac'
            );
            foreach ((array)$DBPriMACs as &$mac) {
                if (self::arrayStrpos($mac, $RealAddMACs) !== false) {
                    throw new Exception(
                        _('Cannot add Primary mac as additional mac')
                    );
                }
                unset($mac);
            }
            unset($DBPriMACs);
            $PreOwnedMACs = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'hostID' => $this->get('id'),
                    'pending' => 1
                ),
                'mac',
                true
            );
            $RealAddMACs = array_diff(
                (array)$RealAddMACs,
                (array)$PreOwnedMACs
            );
            unset($PreOwnedMACs);
            $DBAddMACs = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'hostID' => $this->get('id'),
                    'primary' => 0,
                    'pending' => 0
                ),
                'mac'
            );
            $RemoveAddMAC = array_diff(
                (array)$DBAddMACs,
                (array)$RealAddMACs
            );
            if (count($RemoveAddMAC)) {
                self::getClass('MACAddressAssociationManager')
                    ->destroy(
                        array(
                            'hostID' => $this->get('id'),
                            'mac' => $RemoveAddMAC
                        )
                    );
                $DBAddMACs = self::getSubObjectIDs(
                    'MACAddressAssociation',
                    array(
                        'hostID' => $this->get('id'),
                        'primary' => 0,
                        'pending' => 0,
                        'mac'
                    )
                );
                unset($RemoveAddMAC);
            }
            $insert_fields = array(
                'hostID',
                'mac',
                'primary',
                'pending'
            );
            $insert_values = array();
            $RealAddMACs = array_diff(
                (array)$RealAddMACs,
                (array)$DBAddMACs
            );
            foreach ((array)$RealAddMACs as $index => &$mac) {
                $insert_values[] = array(
                    $this->get('id'),
                    $mac,
                    0,
                    0
                );
                unset($mac);
            }
            if (count($insert_values) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
            unset(
                $DBAddMACs,
                $RealAddMACs,
                $RemoveAddMAC
            );
        }
        if ($this->isLoaded('pendingMACs')) {
            self::_retValidMacs($this->get('pendingMACs'), $pendMacs);
            $RealPendMACs = array_filter($pendMacs);
            unset($pendMacs);
            $RealPendMACs = array_unique($RealPendMACs);
            $RealPendMACs = array_filter($RealPendMACs);
            $DBPriMACs = self::getSubObjectIDs(
                'MACAddressAssociation',
                array('primary' => 1),
                'mac'
            );
            foreach ((array)$DBPriMACs as &$mac) {
                if (self::arrayStrpos($mac, $RealPendMACs)) {
                    throw new Exception(
                        _('Cannot add a pre-existing primary mac')
                    );
                }
                unset($mac);
            }
            unset($DBPriMACs);
            $PreOwnedMACs = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'hostID' => $this->get('id'),
                    'pending' => 0,
                    'mac',
                    true
                ),
                'mac',
                true
            );
            $RealPendMACs = array_diff(
                (array)$RealPendMACs,
                (array)$PreOwnedMACs
            );
            unset($PreOwnedMACs);
            $DBPendMACs = self::getSubObjectIDs(
                'MACAddressAssociation',
                array(
                    'hostID' => $this->get('id'),
                    'primary' => 0,
                    'pending' => 1,
                ),
                'mac'
            );
            $RemovePendMAC = array_diff(
                (array)$DBPendMACs,
                (array)$RealPendMACs
            );
            if (count($RemovePendMAC)) {
                self::getClass('MACAddressAssociationManager')
                    ->destroy(
                        array(
                            'hostID' => $this->get('id'),
                            'mac' => $RemovePendMAC
                        )
                    );
                $DBPendMACs = self::getSubObjectIDs(
                    'MACAddressAssociation',
                    array(
                        'primary' => 0,
                        'pending' => 1,
                    ),
                    'mac'
                );
                unset($RemovePendMAC);
            }
            $insert_fields = array(
                'hostID',
                'mac',
                'primary',
                'pending'
            );
            $insert_values = array();
            $RealPendMACs = array_diff(
                (array)$RealPendMACs,
                (array)$DBPendMACs
            );
            foreach ((array)$RealPendMACs as &$mac) {
                $insert_values[] = array(
                    $this->get('id'),
                    $mac,
                    0,
                    1
                );
                unset($mac);
            }
            if (count($insert_values) > 0) {
                self::getClass('MACAddressAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
            unset(
                $DBPendMACs,
                $RealPendMACs,
                $RemovePendMAC
            );
        }
        if ($this->isLoaded('powermanagementtasks')) {
            $DBPowerManagementIDs = self::getSubObjectIDs(
                'PowerManagement',
                array('hostID'=>$this->get('id'))
            );
            $RemovePowerManagementIDs = array_diff(
                (array)$DBPowerManagementIDs,
                (array)$this->get('powermanagementtasks')
            );
            if (count($RemovePowerManagementIDs)) {
                self::getClass('PowerManagementManager')
                    ->destroy(
                        array(
                            'hostID' => $this->get('id'),
                            'id' => $RemovePowerManagementIDs
                        )
                    );
                $DBPowerManagementIDs = self::getSubObjectIDs(
                    'PowerManagement',
                    array('hostID' => $this->get('id'))
                );
                unset($RemovePowerManagementIDs);
            }
            $objNeeded = false;
            unset($DBPowerManagementIDs, $RemovePowerManagementIDs);
        }
        return $this
            ->assocSetter('Module')
            ->assocSetter('Printer')
            ->assocSetter('Snapin')
            ->assocSetter('Group')
            ->load();
    }
    /**
     * Defines if the host is valid
     *
     * @return bool
     */
    public function isValid()
    {
        return parent::isValid() && $this->isHostnameSafe();
    }
    /**
     * Tells us if the hostname is safe to use
     *
     * @param string $hostname the hostname to test
     *
     * @return bool
     */
    public function isHostnameSafe($hostname = '')
    {
        if (empty($hostname)) {
            $hostname = $this->get('name');
        }
        $pattern = '/^[\\w!@#$%^()\\-\'{}\\.~]{1,15}$/';
        return (bool)preg_match($pattern, $hostname);
    }
    /**
     * Returns if the printer is the default
     *
     * @param int $printerid the printer id to test
     *
     * @return bool
     */
    public function getDefault($printerid)
    {
        return (bool)self::getClass('PrinterAssociationManager')
            ->count(
                array(
                    'hostID' => $this->get('id'),
                    'printerID' => $printerid,
                    'isDefault' => 1
                )
            );
    }
    /**
     * Updates the default printer
     *
     * @param int   $printerid the printer id to update
     * @param mixed $onoff     whether to enable or disable
     *
     * @return object
     */
    public function updateDefault($printerid, $onoff)
    {
        self::getClass('PrinterAssociationManager')
            ->update(
                array(
                    'printerID' => $this->get('printers'),
                    'hostID' => $this->get('id')
                ),
                '',
                array('isDefault' => 0)
            );
        self::getClass('PrinterAssociationManager')
            ->update(
                array(
                    'printerID' => $printerid,
                    'hostID' => $this->get('id')
                ),
                '',
                array('isDefault' => $onoff)
            );
        return $this;
    }
    /**
     * Sets display vals for the host
     *
     * @return void
     */
    private function _setDispVals()
    {
        if (count(self::$_hostscreen)) {
            return;
        }
        if (!$this->get('hostscreen')->isValid()) {
            list(
                $refresh,
                $width,
                $height
            ) = self::getSubObjectIDs(
                'Service',
                array(
                    'name' => array(
                        'FOG_CLIENT_DISPLAYMANAGER_R',
                        'FOG_CLIENT_DISPLAYMANAGER_X',
                        'FOG_CLIENT_DISPLAYMANAGER_Y'
                    )
                ),
                'value',
                false,
                'AND',
                'name',
                false,
                false
            );
        } else {
            $refresh = $this->get('hostscreen')->get('refresh');
            $width = $this->get('hostscreen')->get('width');
            $height = $this->get('hostscreen')->get('height');
        }
        self::$_hostscreen = array(
            'refresh' => $refresh,
            'width' => $width,
            'height' => $height
        );
    }
    /**
     * Gets the display values
     *
     * @param string $key the key to get
     *
     * @return mixed
     */
    public function getDispVals($key = '')
    {
        $this->_setDispVals();
        return self::$_hostscreen[$key];
    }
    /**
     * Sets the display values
     *
     * @param mixed $x the width
     * @param mixed $y the height
     * @param mixed $r the refresh
     *
     * @return object
     */
    public function setDisp($x, $y, $r)
    {
        if (!$this->get('hostscreen')->isValid()) {
            $this->get('hostscreen')
                ->set('hostID', $this->get('id'));
        }
        $this->get('hostscreen')
            ->set('width', $x)
            ->set('height', $y)
            ->set('refresh', $r)
            ->save();
        return $this;
    }
    /**
     * Sets this hosts alo time (or default to global if needed
     *
     * @return void
     */
    private function _setAlo()
    {
        if (!empty(self::$_hostalo)) {
            return;
        }
        if (!$this->get('hostalo')->isValid()) {
            self::$_hostalo = self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN');
        } else {
            self::$_hostalo = $this->get('hostalo')->get('time');
        }
        return;
    }
    /**
     * Gets the auto logout time
     *
     * @return int
     */
    public function getAlo()
    {
        $this->_setAlo();
        return self::$_hostalo;
    }
    /**
     * Sets the auto logout time
     *
     * @param int $time the time to set
     *
     * @return object
     */
    public function setAlo($time)
    {
        return $this->get('hostalo')
            ->set('hostID', $this->get('id'))
            ->set('time', $time)
            ->save();
    }
    /**
     * Loads the mac additional field
     *
     * @return void
     */
    protected function loadMac()
    {
        $mac = new MACAddress($this->get('primac'));
        $this->set('mac', $mac);
    }
    /**
     * Loads any additional macs
     *
     * @return void
     */
    protected function loadAdditionalMACs()
    {
        $macs = self::getSubObjectIDs(
            'MACAddressAssociation',
            array(
                'hostID' => $this->get('id'),
                'primary' => 0,
                'pending' => 0,
            ),
            'mac'
        );
        $this->set('additionalMACs', (array)$macs);
    }
    /**
     * Loads any pending macs
     *
     * @return void
     */
    protected function loadPendingMACs()
    {
        $macs = self::getSubObjectIDs(
            'MACAddressAssociation',
            array(
                'hostID' => $this->get('id'),
                'primary' => 0,
                'pending' => 1,
            ),
            'mac'
        );
        $this->set('pendingMACs', (array)$macs);
    }
    /**
     * Loads any groups this host is in
     *
     * @return void
     */
    protected function loadGroups()
    {
        $groups = self::getSubObjectIDs(
            'GroupAssociation',
            array('hostID' => $this->get('id')),
            'groupID'
        );
        $groups = self::getSubObjectIDs(
            'Group',
            array('id' => $groups)
        );
        $this->set('groups', (array)$groups);
    }
    /**
     * Loads any groups this host is not in
     *
     * @return void
     */
    protected function loadGroupsnotinme()
    {
        $groups = array_diff(
            self::getSubObjectIDs('Group'),
            $this->get('groups')
        );
        $this->set('groupsnotinme', (array)$groups);
    }
    /**
     * Loads any printers those host has
     *
     * @return void
     */
    protected function loadPrinters()
    {
        $printers = self::getSubObjectIDs(
            'PrinterAssociation',
            array('hostID' => $this->get('id')),
            'printerID'
        );
        $printers = self::getSubObjectIDs(
            'Printer',
            array('id' => $printers)
        );
        $this->set('printers', (array)$printers);
    }
    /**
     * Loads any printers this host does not have
     *
     * @return void
     */
    protected function loadPrintersnotinme()
    {
        $printers = array_diff(
            self::getSubObjectIDs('Printer'),
            $this->get('printers')
        );
        $this->set('printersnotinme', (array)$printers);
    }
    /**
     * Loads any snapins this host has
     *
     * @return void
     */
    protected function loadSnapins()
    {
        $snapins = self::getSubObjectIDs(
            'SnapinAssociation',
            array('hostID' => $this->get('id')),
            'snapinID'
        );
        $snapins = self::getSubObjectIDs(
            'Snapin',
            array('id' => $snapins)
        );
        $this->set('snapins', (array)$snapins);
    }
    /**
     * Loads any snapins this host does not have
     *
     * @return void
     */
    protected function loadSnapinsnotinme()
    {
        $snapins = array_diff(
            self::getSubObjectIDs('Snapin'),
            $this->get('snapins')
        );
        $this->set('snapinsnotinme', (array)$snapins);
    }
    /**
     * Loads any modules this host has
     *
     * @return void
     */
    protected function loadModules()
    {
        $modules = self::getSubObjectIDs(
            'ModuleAssociation',
            array('hostID' => $this->get('id')),
            'moduleID'
        );
        $modules = self::getSubObjectIDs(
            'Module',
            array('id' => $modules)
        );
        $this->set('modules', (array)$modules);
    }
    /**
     * Loads any powermanagement tasks this host has
     *
     * @return void
     */
    protected function loadPowermanagementtasks()
    {
        $pms = self::getSubObjectIDs(
            'PowerManagement',
            array('hostID' => $this->get('id'))
        );
        $this->set('powermanagementtasks', (array)$pms);
    }
    /**
     * Loads any users have logged in
     *
     * @return void
     */
    protected function loadUsers()
    {
        $users = self::getSubObjectIDs(
            'UserTracking',
            array('hostID' => $this->get('id'))
        );
        $this->set('users', (array)$users);
    }
    /**
     * Loads the current snapin job
     *
     * @return void
     */
    protected function loadSnapinjob()
    {
        $sjID = self::getSubObjectIDs(
            'SnapinJob',
            array(
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                ),
                'hostID' => $this->get('id')
            )
        );
        $SnapinJob = new SnapinJob(@min($sjID));
        $this->set('snapinjob', $SnapinJob);
    }
    /**
     * Loads the current task
     *
     * @return void
     */
    protected function loadTask()
    {
        $find['hostID'] = $this->get('id');
        $find['stateID'] = self::fastmerge(
            self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $types = array(
            'up',
            'down'
        );
        $type = filter_input(INPUT_POST, 'type');
        if (!$type) {
            $type = filter_input(INPUT_GET, 'type');
        }
        $type = trim($type);
        if (in_array($type, $types)) {
            if ($type === 'up') {
                $find['typeID'] = array(2, 16);
            } else {
                $find['typeID'] = array(
                    1,
                    8,
                    15,
                    17,
                    24
                );
            }
        }
        $taskID = self::getSubObjectIDs(
            'Task',
            $find
        );
        $taskID = array_shift($taskID);
        $this->set('task', $taskID);
        unset($find);
    }
    /**
     * Loads the optimal storage node
     *
     * @return void
     */
    protected function loadOptimalStorageNode()
    {
        $node = $this
            ->getImage()
            ->getStorageGroup()
            ->getOptimalStorageNode();
        $this->set('optimalStorageNode', $node);
    }
    /**
     * Gets the active task count
     *
     * @return int
     */
    public function getActiveTaskCount()
    {
        $find = array(
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            ),
            'hostID' => $this->get('id')
        );
        $count = self::getClass('TaskManager')
            ->count($find);
        return (int)$count;
    }
    /**
     * Returns the optimal storage node
     *
     * @return object
     */
    public function getOptimalStorageNode()
    {
        return $this->get('optimalStorageNode');
    }
    /**
     * Creates the tasking so I don't have to keep typing it in for each element.
     *
     * @param string $taskName    the name to assign to the tasking
     * @param int    $taskTypeID  the task type id to set the tasking
     * @param string $username    the username to associate with the tasking
     * @param int    $groupID     the Storage Group ID to associate with
     * @param int    $memID       the Storage Node ID to associate with
     * @param bool   $imagingTask if the task is an imaging type
     * @param bool   $shutdown    if the task is to be shutdown once completed
     * @param string $passreset   if the task is a password reset task
     * @param bool   $debug       if the task is a debug task
     * @param bool   $wol         if the task is to wol
     *
     * @return object
     */
    private function _createTasking(
        $taskName,
        $taskTypeID,
        $username,
        $groupID,
        $memID,
        $imagingTask = true,
        $shutdown = false,
        $passreset = false,
        $debug = false,
        $wol = false
    ) {
        $Task = self::getClass('Task')
            ->set('name', $taskName)
            ->set('createdBy', $username)
            ->set('hostID', $this->get('id'))
            ->set('isForced', 0)
            ->set('stateID', self::getQueuedState())
            ->set('typeID', $taskTypeID)
            ->set('storagegroupID', $groupID)
            ->set('storagenodeID', $memID)
            ->set('wol', (string)intval($wol))
            ->set('host', $this)
            ->set('image', $this->getImage())
            ->set('tasktype', new TaskType($taskTypeID))
            ->set('TaskState', new TaskState(self::getQueuedState()))
            ->set('StorageGroup', $this->getImage()->getStorageGroup())
            ->set('StorageNode', new StorageNode());
        if ($imagingTask) {
            $Task->set('imageID', $this->getImage()->get('id'));
        }
        if ($shutdown) {
            $Task->set('shutdown', $shutdown);
        }
        if ($debug) {
            $Task->set('isDebug', $debug);
        }
        if ($passreset) {
            $Task->set('passreset', $passreset);
        }
        return $Task;
    }
    /**
     * Cancels and tasks/jobs for snapins on this host
     *
     * @return void
     */
    private function _cancelJobsSnapinsForHost()
    {
        $SnapinJobs = self::getSubObjectIDs(
            'SnapinJob',
            array(
                'hostID' => $this->get('id'),
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                )
            )
        );
        self::getClass('SnapinTaskManager')
            ->update(
                array(
                    'jobID' => $SnapinJobs,
                    'stateID' => self::fastmerge(
                        self::getQueuedStates(),
                        (array)self::getProgressState()
                    )
                ),
                '',
                array(
                    'return' => -9999,
                    'details' => _('Cancelled due to new tasking.'),
                    'stateID' => self::getCancelledState()
                )
            );
        self::getClass('SnapinJobManager')
            ->update(
                array('id' => $SnapinJobs),
                '',
                array('stateID' => self::getCancelledState())
            );
        $AllTasks = self::getSubObjectIDs(
            'Task',
            array(
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                ),
                'hostID' => $this->get('id')
            )
        );
        $MyTask = $this->get('task')->get('id');
        self::getClass('TaskManager')
            ->update(
                array(
                    'id' => array_diff(
                        (array)$AllTasks,
                        (array)$MyTask
                    )
                ),
                '',
                array('stateID' => self::getCancelledState())
            );
    }
    /**
     * Creates the snapin tasking as needed
     *
     * @param int    $snapin The snapin to create tasking on (-1 = all)
     * @param bool   $error  Whether to die on error or not
     * @param object $Task   The task object
     *
     * @return void
     */
    private function _createSnapinTasking(
        $snapin = -1,
        $error = false,
        $Task = false
    ) {
        try {
            $SnapinJob = $this->get('snapinjob');
            if (!$SnapinJob->isValid()) {
                $SnapinJob
                    ->set('hostID', $this->get('id'))
                    ->set('stateID', self::getQueuedState())
                    ->set(
                        'createdTime',
                        self::niceDate()
                        ->format('Y-m-d H:i:s')
                    );
                if (!$SnapinJob->save()) {
                    throw new Exception(_('Failed to create Snapin Job'));
                }
            }
            $insert_fields = array('jobID', 'stateID', 'snapinID');
            $insert_values = array();
            if ($snapin == -1) {
                $snapin = $this->get('snapins');
            }
            foreach ((array)$snapin as &$snapinID) {
                $insert_values[] = array(
                    $SnapinJob->get('id'),
                    $this->getQUeuedState(),
                    $snapinID
                );
                unset($snapinID);
            }
            if (count($insert_values) > 0) {
                self::getClass('SnapinTaskManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        } catch (Exception $e) {
            if ($error) {
                $Task->cancel();
                throw new Exception($e->getMessage());
            }
        }
        return $this;
    }
    /**
     * Creates tasking for the host based on the type
     *
     * @param int    $taskTypeID    the task type
     * @param string $taskName      the name of the task
     * @param bool   $shutdown      whether to shutdown or reboot
     * @param bool   $debug         is this a debug task
     * @param mixed  $deploySnapins snapins to deploy
     * @param bool   $isGroupTask   is the tasking a group task
     * @param string $username      the username creating the task
     * @param string $passreset     username that needs password reset
     * @param bool   $sessionjoin   is this task joining an mc task
     * @param bool   $wol           should we wake the host up
     *
     * @return string
     */
    public function createImagePackage(
        $taskTypeID,
        $taskName = '',
        $shutdown = false,
        $debug = false,
        $deploySnapins = false,
        $isGroupTask = false,
        $username = '',
        $passreset = '',
        $sessionjoin = false,
        $wol = false
    ) {
        if (!$sessionjoin) {
            $taskName .= ' - ' . $this->get('name');
        }
        try {
            if (!$this->isValid()) {
                throw new Exception(self::$foglang['HostNotValid']);
            }
            $Task = $this->get('task');
            $TaskType = new TaskType($taskTypeID);
            if (!$TaskType->isValid()) {
                throw new Exception(self::$foglang['TaskTypeNotValid']);
            }
            if ($Task->isValid()) {
                $iTaskType = $Task->getTaskType()->isImagingTask();
                if ($iTaskType) {
                    throw new Exception(self::$foglang['InTask']);
                } elseif ($Task->isSnapinTasking()) {
                    if ($TaskType->get('id') == '13') {
                        $currSnapins = self::getSubObjectIDs(
                            'SnapinTask',
                            array(
                                'jobID' => $this->get('snapinjob')->get('id'),
                                'stateID' => self::fastmerge(
                                    (array)$this->getQueuedStates(),
                                    (array)$this->getProgressState()
                                ),
                            ),
                            'snapinID'
                        );
                        if (!in_array($deploySnapins, $currSnapins)) {
                            $Task
                                ->set(
                                    'name',
                                    'Multiple Snapin task -- Altered after single'
                                )
                                ->set(
                                    'typeID',
                                    12
                                )->save();
                        }
                    } elseif ($TaskType->get('id') == '12') {
                        $this->_cancelJobsSnapinsForHost();
                    } else {
                        $Task->cancel();
                        $Task = new Task(0);
                        $this->set('task', $Task);
                    }
                } else {
                    $Task->cancel();
                    $Task = new Task(0);
                    $this->set('task', $Task);
                }
            }
            unset($iTaskType);
            $Image = $this->getImage();
            $imagingTypes = $TaskType->isImagingTask();
            if ($imagingTypes) {
                if (!$Image->isValid()) {
                    throw new Exception(self::$foglang['ImageNotValid']);
                }
                if (!$Image->get('isEnabled')) {
                    throw new Exception(_('Image is not enabled'));
                }
                $StorageGroup = $Image->getStorageGroup();
                if (!$StorageGroup->isValid()) {
                    throw new Exception(self::$foglang['ImageGroupNotValid']);
                }
                if ($TaskType->isCapture()) {
                    $StorageNode = $StorageGroup->getMasterStorageNode();
                } else {
                    $StorageNode = $this->getOptimalStorageNode();
                }
                if (!$StorageNode->isValid()) {
                    $msg = sprintf(
                        '%s %s',
                        _('Could not find any'),
                        _('nodes containing this image')
                    );
                    throw new Exception($msg);
                }
                $imageTaskImgID = $this->get('imageID');
                $hostsWithImgID = self::getSubObjectIDs(
                    'Host',
                    array('imageID' => $imageTaskImgID)
                );
                $realImageID = self::getSubObjectIDs(
                    'Host',
                    array('id' => $this->get('id')),
                    'imageID'
                );
                if (!in_array($this->get('id'), $hostsWithImgID)) {
                    $this->set(
                        'imageID',
                        array_shift($realImageID)
                    )->save();
                }
                $this->set('imageID', $imageTaskImgID);
            }
            $isCapture = $TaskType->isCapture();
            $username = ($username ? $username : self::$FOGUser->get('name'));
            if (!$Task->isValid()) {
                $Task = $this->_createTasking(
                    $taskName,
                    $taskTypeID,
                    $username,
                    $imagingTypes ? $StorageGroup->get('id') : 0,
                    $imagingTypes ? $StorageNode->get('id') : 0,
                    $imagingTypes,
                    $shutdown,
                    $passreset,
                    $debug,
                    $wol
                );
                $Task->set('imageID', $this->get('imageID'));
                if (!$Task->save()) {
                    throw new Exception(self::$foglang['FailedTask']);
                }
                $this->set('task', $Task);
            }
            if ($TaskType->isSnapinTask()) {
                if ($deploySnapins === true) {
                    $deploySnapins = -1;
                }
                $mac = $this->get('mac');
                if ($deploySnapins) {
                    $this->_createSnapinTasking(
                        $deploySnapins,
                        $TaskType->isSnapinTasking(),
                        $Task
                    );
                }
            }
            if ($TaskType->isMulticast()) {
                $multicastTaskReturn = function (&$MulticastSession) {
                    if (!$MulticastSession->isValid()) {
                        return;
                    }
                    return $MulticastSession;
                };
                $assoc = false;
                $showStates = self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                );
                if ($sessionjoin) {
                    $MCSessions = self::getClass('MulticastSessionManager')
                        ->find(
                            array(
                                'name' => $taskName,
                                'stateID' => $showStates
                            )
                        );
                    $assoc = true;
                } else {
                    $MCSessions = self::getClass('MulticastSessionManager')
                        ->find(
                            array(
                                'image' => $Image->get('id'),
                                'stateID' => $showStates
                            )
                        );
                }
                $MultiSessJoin = array_map(
                    $multicastTaskReturn,
                    $MCSessions
                );
                $MultiSessJoin = array_filter($MultiSessJoin);
                $MultiSessJoin = array_values($MultiSessJoin);
                if (is_array($MultiSessJoin) && count($MultiSessJoin)) {
                    $MulticastSession = array_shift($MultiSessJoin);
                }
                unset($MultiSessJoin);
                if ($MulticastSession instanceof MulticastSession
                    && $MulticastSession->isValid()
                ) {
                    $assoc = true;
                } else {
                    $port = self::getSetting('FOG_UDPCAST_STARTINGPORT');
                    $portOverride = self::getSetting('FOG_MULTICAST_PORT_OVERRIDE');
                    $MulticastSession = self::getClass('MulticastSession')
                        ->set('name', $taskName)
                        ->set('port', ($portOverride ? $portOverride : $port))
                        ->set('logpath', $this->getImage()->get('path'))
                        ->set('image', $this->getImage()->get('id'))
                        ->set('interface', $StorageNode->get('interface'))
                        ->set('stateID', 0)
                        ->set('starttime', self::niceDate()->format('Y-m-d H:i:s'))
                        ->set('percent', 0)
                        ->set('isDD', $this->getImage()->get('imageTypeID'))
                        ->set('storagegroupID', $StorageNode->get('storagegroupID'))
                        ->set('clients', -1);
                    if ($MulticastSession->save()) {
                        $assoc = true;
                        if (!self::getSetting('FOG_MULTICAST_PORT_OVERRIDE')) {
                            $randomnumber = mt_rand(24576, 32766)*2;
                            while ($randomnumber
                                == $MulticastSession->get('port')
                            ) {
                                $randomnumber = mt_rand(24576, 32766)*2;
                            }
                            self::setSetting(
                                'FOG_UDPCAST_STARTINGPORT',
                                $randomnumber
                            );
                        }
                    }
                }
                if ($assoc) {
                    self::getClass('MulticastSessionAssociation')
                        ->set('msID', $MulticastSession->get('id'))
                        ->set('taskID', $Task->get('id'))
                        ->save();
                }
            }
            if ($wol) {
                $this->wakeOnLAN();
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        if ($taskTypeID == 14) {
            $Task->destroy();
        }
        $str = '<li>';
        $str .= '<a href="#">';
        $str .= $this->get('name');
        $str .= ' &ndash; ';
        $str .= $this->getImage()->get('name');
        $str .= '</a>';
        $str .= '</li>';
        return $str;
    }
    /**
     * Returns task if host image is valid
     *
     * @return Task
     */
    public function getImageMemberFromHostID()
    {
        try {
            $Image = $this->getImage();
            if (!$Image->isValid()) {
                throw new Exception(_('No valid Image defined for this host'));
            }
            if (!$Image->get('isEnabled')) {
                throw new Exception(_('Image is not enabled'));
            }
            $StorageGroup = $Image->getStorageGroup();
            if (!$StorageGroup->isValid()) {
                throw new Exception('No StorageGroup defined for this host');
            }
            $Task = self::getClass('Task')
                ->set('hostID', $this->get('id'))
                ->set('storagegroupID', $StorageGroup->get('id'))
                ->set(
                    'storagenodeID',
                    $StorageGroup
                        ->getOptimalStorageNode()
                        ->get('id')
                )
                ->set('imageID', $Image->get('id'));
        } catch (Exception $e) {
            self::error(
                sprintf(
                    '%s():xError: %s',
                    __FUNCTION__,
                    $e->getMessage()
                )
            );
            $Task = false;
        }
        return $Task;
    }
    /**
     * Clears virus records for the host
     *
     * @return object
     */
    public function clearAVRecordsForHost()
    {
        self::getClass('VirusManager')
            ->destroy(
                array('mac' => $this->getMyMacs())
            );
        return $this;
    }
    /**
     * Wakes this host up
     *
     * @return object
     */
    public function wakeOnLAN()
    {
        self::wakeUp($this->getMyMacs());
        return $this;
    }
    /**
     * Adds additional macs
     *
     * @param array $addArray the macs to add
     * @param bool  $pending  should it be added as a pending mac
     *
     * @return object
     */
    public function addAddMAC($addArray, $pending = false)
    {
        $addArray = array_map('strtolower', (array)$addArray);
        $addArray = self::parseMacList($addArray);
        $addTo = $pending ? 'pendingMACs' : 'additionalMACs';
        foreach ((array)$addArray as &$mac) {
            $this->add($addTo, $mac);
            unset($mac);
        }
        return $this;
    }
    /**
     * Moves pending macs to additional macs
     *
     * @param array $addArray the macs to move
     *
     * @return object
     */
    public function addPendtoAdd($addArray = false)
    {
        $lowerAndTrim = function (&$MAC) {
            return trim(strtolower($MAC));
        };
        $PendMACs = array_map($lowerAndTrim, (array)$this->get('pendingMACs'));
        $MACs = array_map($lowerAndTrim, (array)$addArray);
        if ($addArray === false) {
            $matched = array_intersect(
                (array)$PendMACs,
                (array)$MACs
            );
        } else {
            $matched = $PendMACs;
        }
        unset($MACs, $PendMACs);
        return $this->addAddMAC($matched)->removePendMAC($matched);
    }
    /**
     * Removes additional macs
     *
     * @param array $removeArray the macs to remove
     *
     * @return object
     */
    public function removeAddMAC($removeArray)
    {
        foreach ((array)$removeArray as &$mac) {
            if (!$mac instanceof MACAddress) {
                $mac = new MACAddress($mac);
            }
            if (!$mac->isValid()) {
                continue;
            }
            $this->remove('additionalMACs', $mac);
            unset($mac);
        }
        return $this;
    }
    /**
     * Removes pending macs
     *
     * @param array $removeArray the macs to remove
     *
     * @return object
     */
    public function removePendMAC($removeArray)
    {
        foreach ((array)$removeArray as &$mac) {
            if (!$mac instanceof MACAddress) {
                $mac = new MACAddress($mac);
            }
            if (!$mac->isValid()) {
                continue;
            }
            $this->remove('pendingMACs', $mac);
            unset($mac);
        }
        return $this;
    }
    /**
     * Adds primary mac
     *
     * @param string $mac the mac to make as primary
     *
     * @return object
     */
    public function addPriMAC($mac)
    {
        $mac = self::parseMacList($mac);
        if (count($mac) < 1) {
            throw new Exception(_('No viable macs to use'));
        }
        if (is_array($mac) && count($mac) > 0) {
            $mac = array_shift($mac);
        }
        $host = $mac->getHost();
        if ($host instanceof Host && $host->isValid()) {
            throw new Exception(
                sprintf(
                    "%s: %s",
                    _('MAC address is already in use by another host'),
                    $host->get('name')
                )
            );
        }
        return $this->set('mac', $mac);
    }
    /**
     * Adds pending mac
     *
     * @param string $mac the mac to add
     *
     * @return obect
     */
    public function addPendMAC($mac)
    {
        return $this->addAddMAC($mac, true);
    }
    /**
     * Adds printers to the host
     *
     * @param array $addArray the printers to add
     *
     * @return object
     */
    public function addPrinter($addArray)
    {
        return $this->addRemItem(
            'printers',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes printers from the host
     *
     * @param array $removeArray the printers to remove
     *
     * @return object
     */
    public function removePrinter($removeArray)
    {
        return $this->addRemItem(
            'printers',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Adds snapins to the host
     *
     * @param array $addArray the snapins to add
     *
     * @throws Exception
     * @return object
     */
    public function addSnapin($addArray)
    {
        $limit = self::getSetting('FOG_SNAPIN_LIMIT');
        if ($limit > 0) {
            $snapinCount = self::getClass('SnapinManager')
                ->count(
                    array('id' => $this->get('snapins'))
                );
            if ($snapinCount >= $limit || count($addArray) > $limit) {
                $limitstr = sprintf(
                    '%s%s %s',
                    _('snapin'),
                    $limit == 1 ? '' : 's',
                    _('per host')
                );
                throw new Exception(
                    sprintf(
                        '%s %d %s',
                        _('You are only allowed to assign'),
                        $limit,
                        $limitstr
                    )
                );
            }
        }
        return $this->addRemItem(
            'snapins',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes snapins from the host
     *
     * @param array $removeArray the snapins to remove
     *
     * @return object
     */
    public function removeSnapin($removeArray)
    {
        return $this->addRemItem(
            'snapins',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Adds modules to the host
     *
     * @param array $addArray the modules to add
     *
     * @return object
     */
    public function addModule($addArray)
    {
        return $this->addRemItem(
            'modules',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes modules from the host
     *
     * @param array $removeArray the modules to remove
     *
     * @return object
     */
    public function removeModule($removeArray)
    {
        return $this->addRemItem(
            'modules',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Adds powermanagement tasks to the host
     *
     * @param array $addArray the powermanagement tasks to add
     *
     * @return object
     */
    public function addPowerManagement($addArray)
    {
        return $this->addRemItem(
            'powermanagementtasks',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes powermanagement tasks from the host
     *
     * @param array $removeArray the powermanagement tasks to remove
     *
     * @return object
     */
    public function removePowerManagement($removeArray)
    {
        return $this->addRemItem(
            'powermanagementtasks',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Returns the macs
     *
     * @param bool $justme should only return this or all macs
     *
     * @return array
     */
    public function getMyMacs($justme = true)
    {
        if ($justme) {
            return self::getSubObjectIDs(
                'MACAddressAssociation',
                array('hostID' => $this->get('id')),
                'mac'
            );
        }
        return self::getSubObjectIDs(
            'MACAddressAssociation',
            '',
            'mac'
        );
    }
    /**
     * Sets the ignore status of a mac for either image or client ignore
     *
     * @param array $imageIgnore  to ignore for imaging
     * @param array $clientIgnore to ignore for client
     *
     * @return object
     */
    public function ignore($imageIgnore, $clientIgnore)
    {
        $MyMACs = $this->getMyMacs();
        $myMACs = $igMACs = $cgMACs = array();
        $macaddress = function (&$mac) {
            if (!$mac instanceof MACAddress) {
                $mac = new MACAddress($mac);
            }
            if (!$mac->isValid()) {
                return;
            }
            return $mac->__toString();
        };
        $myMACs = array_map($macaddress, (array)$MyMACs);
        $igMACs = array_map($macaddress, (array)$imageIgnore);
        $cgMACs = array_map($macaddress, (array)$clientIgnore);
        $myMACs = array_filter($myMACs);
        $igMACs = array_filter($igMACs);
        $cgMACs = array_filter($cgMACs);
        $myMACs = array_unique($myMACs);
        $igMACs = array_unique($igMACs);
        $cgMACs = array_unique($cgMACs);
        self::getClass('MACAddressAssociationManager')
            ->update(
                array(
                    'mac' => array_diff(
                        (array)$myMACs,
                        (array)$igMACs
                    ),
                    'hostID' => $this->get('id')
                ),
                '',
                array('imageIgnore' => 0)
            );
        self::getClass('MACAddressAssociationManager')
            ->update(
                array(
                    'mac' => array_diff(
                        (array)$myMACs,
                        (array)$cgMACs
                    ),
                    'hostID'=>$this->get('id')
                ),
                '',
                array('clientIgnore' => 0)
            );
        if (count($igMACs) > 0) {
            self::getClass('MACAddressAssociationManager')
                ->update(
                    array(
                        'mac' => $igMACs,
                        'hostID' => $this->get('id')
                    ),
                    '',
                    array('imageIgnore' => 1)
                );
        }
        if (count($cgMACs) > 0) {
            self::getClass('MACAddressAssociationManager')
                ->update(
                    array(
                        'mac' => $cgMACs,
                        'hostID'=>$this->get('id')
                    ),
                    '',
                    array('clientIgnore' => 1)
                );
        }
    }
    /**
     * Adds host to the selected group
     * alias to addHost method
     *
     * @param array $addArray the groups to add
     *
     * @return object
     */
    public function addGroup($addArray)
    {
        return $this->addHost($addArray);
    }
    /**
     * Removes host from the selected group
     * alias to removeHost method
     *
     * @param array $removeArray the groups to remove
     *
     * @return object
     */
    public function removeGroup($removeArray)
    {
        return $this->removeHost($removeArray);
    }
    /**
     * Adds host to the selected group
     *
     * @param array $addArray the groups to add
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem(
            'groups',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes host from the selected group
     *
     * @param array $removeArray the groups to remove
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem(
            'groups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Tells if the mac is client ignored
     *
     * @param string $mac the mac to test
     *
     * @return string
     */
    public function clientMacCheck($mac = false)
    {
        if ($mac) {
            if (!$mac instanceof MACAddress) {
                $mac = new MACAddress($mac);
            }
            if ($mac->isClientIgnored()) {
                return ' checked';
            }
            return '';
        }
        return $this->get('mac')->isClientIgnored() ? ' checked' : '';
    }
    /**
     * Tells if the mac is image ignored
     *
     * @param string $mac the mac to test
     *
     * @return string
     */
    public function imageMacCheck($mac = false)
    {
        if ($mac) {
            if (!$mac instanceof MACAddress) {
                $mac = new MACAddress($mac);
            }
            if ($mac->isImageIgnored()) {
                return ' checked';
            }
            return '';
        }
        return $this->get('mac')->isImageIgnored() ? ' checked' : '';
    }
    /**
     * Sets the host settings for AD (mainly)
     *
     * @param mixed  $useAD      whether to perform joins
     * @param string $domain     the domain to associate
     * @param string $ou         the ou to bind to
     * @param string $user       the user to perform join with
     * @param string $pass       the pass to perform join with
     * @param bool   $override   should the host fields override whats passed
     * @param bool   $nosave     should we save automatically
     * @param string $legacy     the legacy client ad pass string
     * @param string $productKey the product key for the host to activate
     * @param mixed  $enforce    should the host perform changes forcibly
     *
     * @return object
     */
    public function setAD(
        $useAD = '',
        $domain = '',
        $ou = '',
        $user = '',
        $pass = '',
        $override = false,
        $nosave = false,
        $legacy = '',
        $productKey = '',
        $enforce = ''
    ) {
        $adpasspat = "/^\*{32}$/";
        $pass = (preg_match($adpasspat, $pass) ? $this->get('ADPass') : $pass);
        if ($this->get('id')) {
            if (!$override) {
                if (empty($useAD)) {
                    $useAD = $this->get('useAD');
                }
                if (empty($domain)) {
                    $domain = trim($this->get('ADDomain'));
                }
                if (empty($ou)) {
                    $ou = trim($this->get('ADOU'));
                }
                if (empty($user)) {
                    $user = trim($this->get('ADUser'));
                }
                if (empty($pass)) {
                    $pass = trim($this->get('ADPass'));
                }
                if (empty($legacy)) {
                    $legacy = trim($this->get('ADPassLegacy'));
                }
                if (empty($productKey)) {
                    $productKey = trim($this->get('productKey'));
                }
                if (empty($enforce)) {
                    $enforce = (int)$this->get('enforce');
                }
            }
        }
        if ($pass) {
            $pass = trim($pass);
        }
        $this->set('useAD', $useAD)
            ->set('ADDomain', trim($domain))
            ->set('ADOU', trim($ou))
            ->set('ADUser', trim($user))
            ->set('ADPass', $pass)
            ->set('ADPassLegacy', $legacy)
            ->set('productKey', trim($productKey))
            ->set('enforce', (string)$enforce);
        return $this;
    }
    /**
     * Returns the hosts image object
     *
     * @return Image
     */
    public function getImage()
    {
        return $this->get('imagename');
    }
    /**
     * Returns the hosts image name
     *
     * @return string
     */
    public function getImageName()
    {
        return $this
            ->get('imagename')
            ->get('name');
    }
    /**
     * Returns the hosts image os name
     *
     * @return string
     */
    public function getOS()
    {
        return $this->getImage()->getOS()->get('name');
    }
    /**
     * Returns the snapinjob
     *
     * @return SnapinJob
     */
    public function getActiveSnapinJob()
    {
        return $this->get('snapinjob');
    }
    /**
     * Translates the ping status code to string
     *
     * @return string
     */
    public function getPingCodeStr()
    {
        $val =  (int)$this->get('pingstatus');
        $socketstr = socket_strerror($val);
        $strtoupdate = '<i class="icon-ping-%s fa fa-%s %s'
            . '" data-toggle="tooltip" '
            . 'data-placement="right" '
            . 'title="%s'
            . '"></i>';

        ob_start();
        switch ($val) {
                case 0:
                        printf($strtoupdate, 'windows', 'windows', 'green', 'Windows');
                        break;
                case 111:
                        $taskID = self::getSubObjectIDs(
                            'Task',
                            array('hostID' => $this->get('id'),
                                      'stateID' => 2
                                ),
                            'id'
                        );
                        if (is_null($taskID)) {
                            printf($strtoupdate, 'linux', 'linux', 'blue', 'Linux');
                        } else {
                            printf($strtoupdate, 'fos', 'cogs', 'green', 'FOS');
                        }

                        break;
                default:
                        printf($strtoupdate, 'down', 'exclamation-circle', 'red', 'Unknown');
        }
        return ob_get_clean();
    }
}
