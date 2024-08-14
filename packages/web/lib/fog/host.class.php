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
    protected $databaseFields = [
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
        'token' => 'hostInfoKey',
        'tokenlock' => 'hostInfoLock'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'mac',
        'primac',
        'imagename',
        'groups',
        'hostscreen',
        'hostalo',
        'optimalStorageNode',
        'printers',
        'snapins',
        'modules',
        'inventory',
        'task',
        'snapinjob',
        'users',
        'fingerprint',
        'powermanagementtasks'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'MACAddressAssociation' => [
            'hostID',
            'id',
            'primac',
            ['primary' => 1]
        ],
        'Image' => [
            'id',
            'imageID',
            'imagename'
        ],
        'HostScreenSetting' => [
            'hostID',
            'id',
            'hostscreen'
        ],
        'HostAutoLogout' => [
            'hostID',
            'id',
            'hostalo'
        ],
        'Inventory' => [
            'hostID',
            'id',
            'inventory'
        ]
    ];

    protected $sqlQueryStr = "SELECT `%s`
        FROM `%s`
        LEFT OUTER JOIN `images`
        ON `hosts`.`hostImage` = `images`.`imageID`
        LEFT JOIN `hostMAC`
        ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
        AND `hostMAC`.`hmPrimary` = '1'
        %s
        %s
        %s";
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `images`
        ON `hosts`.`hostImage` = `images`.`imageID`
        LEFT JOIN `hostMAC`
        ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
        AND `hostMAC`.`hmPrimary` = '1'
        %s";
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`
        LEFT OUTER JOIN `images`
        ON `hosts`.`hostImage` = `images`.`imageID`
        LEFT JOIN `hostMAC`
        ON `hosts`.`hostID` = `hostMAC`.`hmHostID`
        AND `hostMAC`.`hmPrimary` = '1'";
    /**
     * Display val storage
     *
     * @var array
     */
    private static $_hostscreen = [];
    /**
     * ALO time val
     *
     * @var int
     */
    private static $_hostalo = [];
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
            case 'snapinjob':
                if (!($value instanceof SnapinJob)) {
                    $value = new SnapinJob($value);
                }
                break;
            case 'task':
                if (!($value instanceof Task)) {
                    $value = new Task($value);
                }
        }
        return parent::set($key, $value);
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
        $findWhere = ['hostID' => $this->get('id')];
        Route::ids(
            'snapinjob',
            $findWhere
        );
        $SnapinJobIDs = ['jobID' => json_decode(Route::getData(), true)];
        $removeItems = [
            'nodefailure',
            'imaginglog',
            'snapintask',
            'snapinjob',
            'task',
            'scheduledtask',
            'hostautologout',
            'hostscreensetting',
            'groupassociation',
            'snapinassociation',
            'printerassociation',
            'moduleassociation',
            'inventory',
            'macaddressassociation',
            'powermanagement',
        ];
        foreach ($removeItems as &$item) {
            switch ($item) {
                case 'snapintask':
                    $find = $SnapinJobIDs;
                    break;
                default:
                    $find = $findWhere;
            }
            Route::deletemass(
                $item,
                $find
            );
            unset($item);
        }
        self::$HookManager->processEvent(
            'DESTROY_HOST',
            ['Host' => &$this]
        );
        return parent::destroy($key);
    }
    /**
     * Stores data into the database
     *
     * @return bool|object
     */
    public function save()
    {
        parent::save();
        if (array_key_exists('mac', $this->data)) {
            self::getClass('MACAddressAssociation')
                ->set('mac', $this->get('mac'))
                ->set('primary', '1')
                ->set('hostID', $this->get('id'))
                ->save();
        }
        if (array_key_exists('powermanagementtasks', $this->data)) {
            $find = ['hostID' => $this->get('id')];
            Route::ids(
                'powermanagement',
                $find
            );
            $DBPowerManagementIDs = json_decode(Route::getData(), true);
            $RemovePowerManagementIDs = array_diff(
                (array)$DBPowerManagementIDs,
                (array)$this->get('powermanagementtasks')
            );
            if (count($RemovePowerManagementIDs)) {
                Route::deletemass(
                    'powermanagement',
                    [
                        'hostID' => $this->get('id'),
                        'id'=> $RemovePowerManagementIDs
                    ]
                );
                Route::ids(
                    'powermanagement',
                    $find
                );
                $DBPowerManagementIDs = json_decode(Route::getData(), true);
                unset($RemovePowerManagementIDs);
            }
            $objNeeded = false;
            unset($DBPowerManagementIDs, $RemovePowerManagementIDs);
        }
        return $this
            ->assocSetter('Group', 'group')
            ->assocSetter('Module', 'module')
            ->assocSetter('Printer', 'printer')
            ->assocSetter('Snapin', 'snapin')
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
        $pattern = "/^[\w!@#$%^()\-'{}\.~]{1,15}$/";
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
        Route::count(
            'printerassociation',
            [
                'hostID' => $this->get('id'),
                'printerID' => $printerid,
                'isDefault' => 1
            ]
        );
        $isDefault = json_decode(Route::getData());
        return $isDefault->total > 0;
    }
    /**
     * Updates the default printer
     *
     * @param int   $printerid the printer id to update
     *
     * @return object
     */
    public function updateDefault($printerid)
    {
        $printers = array_diff(
            $this->get('printers'),
            [$printerid]
        );
        self::getClass('PrinterAssociationManager')
            ->update(
                [
                    'printerID' => $printers,
                    'hostID' => $this->get('id'),
                    'isDefault' => '1'
                ],
                '',
                ['isDefault' => 0]
            );
        if ($printerid) {
            self::getClass('PrinterAssociationManager')
                ->update(
                    [
                        'printerID' => $printerid,
                        'hostID' => $this->get('id'),
                        'isDefault' => ['0', '']
                    ],
                    '',
                    ['isDefault' => 1]
                );
        }
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
        $keys = [
            'FOG_CLIENT_DISPLAYMANAGER_R',
            'FOG_CLIENT_DISPLAYMANAGER_X',
            'FOG_CLIENT_DISPLAYMANAGER_y'
        ];
        list(
            $refresh,
            $width,
            $height
        ) = self::getSetting($keys);
        $refresh = (
            $this->get('hostscreen')->get('refresh') ?:
            $refresh
        );
        $width = (
            $this->get('hostscreen')->get('width') ?:
            $width
        );
        $height = (
            $this->get('hostscreen')->get('height') ?:
            $height
        );
        self::$_hostscreen = [
            'refresh' => $refresh,
            'width' => $width,
            'height' => $height
        ];
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
        self::$_hostalo = (
            $this->get('hostalo')->get('time') ?:
            self::getSetting('FOG_CLIENT_AUTOLOGOFF_MIN')
        );
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
     * Loads any groups this host is in
     *
     * @return void
     */
    protected function loadGroups()
    {
        $find = ['hostID' => $this->get('id')];
        Route::ids(
            'groupassociation',
            $find,
            'groupID'
        );
        $groups = json_decode(Route::getData(), true);
        $this->set('groups', (array)$groups);
    }
    /**
     * Loads any printers those host has
     *
     * @return void
     */
    protected function loadPrinters()
    {
        $find = ['hostID' => $this->get('id')];
        Route::ids(
            'printerassociation',
            $find,
            'printerID'
        );
        $printers = json_decode(Route::getData(), true);
        $this->set('printers', (array)$printers);
    }
    /**
     * Loads any snapins this host has
     *
     * @return void
     */
    protected function loadSnapins()
    {
        $find = ['hostID' => $this->get('id')];
        Route::ids(
            'snapinassociation',
            $find,
            'snapinID'
        );
        $snapins = json_decode(Route::getData(), true);
        $this->set('snapins', (array)$snapins);
    }
    /**
     * Loads any modules this host has
     *
     * @return void
     */
    protected function loadModules()
    {
        $find = ['hostID' => $this->get('id')];
        Route::ids(
            'moduleassociation',
            $find,
            'moduleID'
        );
        $modules = json_decode(Route::getData(), true);
        $this->set('modules', (array)$modules);
    }
    /**
     * Loads any powermanagement tasks this host has
     *
     * @return void
     */
    protected function loadPowermanagementtasks()
    {
        $find = ['hostID' => $this->get('id')];
        Route::ids(
            'powermanagement',
            $find
        );
        $pms = json_decode(Route::getData(), true);
        $this->set('powermanagementtasks', (array)$pms);
    }
    /**
     * Loads any users have logged in
     *
     * @return void
     */
    protected function loadUsers()
    {
        $find = ['hostID' => $this->get('id')];
        Route::ids(
            'usertracking',
            $find
        );
        $users = json_decode(Route::getData(), true);
        $this->set('users', (array)$users);
    }
    /**
     * Loads the current snapin job
     *
     * @return void
     */
    protected function loadSnapinjob()
    {
        $find = ['hostID' => $this->get('id')];
        $find['stateID'] = self::fastmerge(
            self::getQueuedStates(),
            (array)self::getProgressState()
        );
        Route::ids(
            'snapinjob',
            $find
        );
        $snapinjobs = json_decode(Route::getData(), true);
        $sjID = array_shift($snapinjobs);
        $this->set('snapinjob', new SnapinJob($sjID));
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
        $types = [
            'up',
            'down'
        ];
        $type = filter_input(INPUT_POST, 'type');
        if (!$type) {
            $type = filter_input(INPUT_GET, 'type');
        }
        $type = trim($type);
        if (in_array($type, $types)) {
            if ($type === 'up') {
                $find['typeID'] = TaskType::CAPTURETASKS;
            } else {
                $find['typeID'] = TaskType::DEPLOYTASKS;
            }
        }
        Route::ids(
            'task',
            $find
        );
        $taskID = json_decode(Route::getData(), true);
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
        $find = [
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            ),
            'hostID' => $this->get('id')
        ];
        Route::count(
            'task',
            $find
        );
        $tasks = json_decode(Route::getData());
        return $tasks->total;
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
     * @param string $taskName        the name to assign to the tasking
     * @param int    $taskTypeID      the task type id to set the tasking
     * @param string $username        the username to associate with the tasking
     * @param int    $groupID         the Storage Group ID to associate with
     * @param int    $memID           the Storage Node ID to associate with
     * @param bool   $imagingTask     if the task is an imaging type
     * @param bool   $shutdown        if the task is to be shutdown once completed
     * @param string $passreset       if the task is a password reset task
     * @param bool   $debug           if the task is a debug task
     * @param bool   $wol             if the task is to wol
     * @param bool   $bypassbitlocker bypass bitlocker checks
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
        $wol = false,
        $bypassbitlocker = false
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
            ->set('StorageNode', new StorageNode())
            ->set('bypassbitlocker', ($bypassbitlocker ? '1' : '0'));
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
        $find = [
            'hostID' => $this->get('id'),
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            )
        ];
        Route::ids(
            'snapinjob',
            $find
        );
        $SnapinJobs = json_decode(Route::getData(), true);
        self::getClass('SnapinTaskManager')
            ->update(
                [
                    'jobID' => $SnapinJobs,
                    'stateID' => self::fastmerge(
                        self::getQueuedStates(),
                        (array)self::getProgressState()
                    )
                ],
                '',
                [
                    'return' => -9999,
                    'details' => _('Cancelled due to new tasking.'),
                    'stateID' => self::getCancelledState()
                ]
            );
        self::getClass('SnapinJobManager')
            ->update(
                ['id' => $SnapinJobs],
                '',
                ['stateID' => self::getCancelledState()]
            );
        Route::ids(
            'task',
            $find
        );
        $AllTasks = json_decode(Route::getData(), true);
        $MyTask = $this->get('task')->get('id');
        self::getClass('TaskManager')
            ->update(
                [
                    'id' => array_diff(
                        (array)$AllTasks,
                        (array)$MyTask
                    )
                ],
                '',
                ['stateID' => self::getCancelledState()]
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
            if (-1 == $snapin) {
                $snapins = $this->get('snapins');
                if (count($snapins ?: []) <= 0) {
                    throw new Exception(_('No snapins associated'));
                }
            }
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
            $insert_fields = ['jobID', 'stateID', 'snapinID'];
            $insert_values = [];
            if ($snapin == -1) {
                $snapin = $this->get('snapins');
            }
            foreach ((array)$snapin as &$snapinID) {
                $insert_values[] = [
                    $SnapinJob->get('id'),
                    $this->getQueuedState(),
                    $snapinID
                ];
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
     * @param int    $TaskType        the task type
     * @param string $taskName        the name of the task
     * @param bool   $shutdown        whether to shutdown or reboot
     * @param bool   $debug           is this a debug task
     * @param mixed  $deploySnapins   snapins to deploy
     * @param bool   $isGroupTask     is the tasking a group task
     * @param string $username        the username creating the task
     * @param string $passreset       username that needs password reset
     * @param bool   $sessionjoin     is this task joining an mc task
     * @param bool   $wol             should we wake the host up
     * @param bool   $bypassbitlocker bypass bitlocker?
     *
     * @return string
     */
    public function createImagePackage(
        $TaskType,
        $taskName = '',
        $shutdown = false,
        $debug = false,
        $deploySnapins = false,
        $isGroupTask = false,
        $username = '',
        $passreset = '',
        $sessionjoin = false,
        $wol = false,
        $bypassbitlocker = false
    ) {
        if (!$sessionjoin) {
            $taskName .= ' - '
                . $this->get('name')
                . ' '
                . self::niceDate()->format('Y-m-d H:i:s');
        }
        $serverFault = false;
        try {
            if (!$this->isValid()) {
                throw new Exception(self::$foglang['HostNotValid']);
            }
            $Task = $this->get('task');
            // Basic task check for imaging type tasks.
            if ($Task->isValid() && $TaskType->isImagingTask) {
                throw new Exception(self::$foglang['InTask']);
            }

            // Snapin Tasking
            if ($TaskType->isSnapinTasking) {
                switch ($TaskType->id) {
                    case TaskType::SINGLE_SNAPIN:
                        $find = [
                            'jobID' => $this->get('snapinjob')->get('id'),
                            'stateID' => self::fastmerge(
                                $this->getQueuedStates(),
                                (array)$this->getProgressState()
                            )
                        ];
                        Route::ids(
                            'snapintask',
                            $find,
                            'snapinID'
                        );
                        $curSnapins = json_decode(Route::getData(), true);
                        if (!in_array($deploySnapins, $curSnapins)) {
                            $Task
                                ->set('name', _('Multiple Snapin -- orig Single'))
                                ->set('typeID', TaskType::ALL_SNAPINS);
                            if (!$Task->save()) {
                                $serverFault = true;
                                throw new Exception(_('Unable to update task'));
                            }
                        }
                        break;
                    case TaskType::ALL_SNAPINS:
                        $this->_cancelJobsSnapinsForHost();
                        break;
                }
            }
            $Image = $this->getImage();
            $imagingTypes = $TaskType->isImagingTask;
            $isCapture = $TaskType->isCapture;
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
                $getNode = 'getOptimalStorageNode';
                if ($isCapture) {
                    $getNode = 'getMasterStorageNode';
                }
                $StorageNode = $StorageGroup->{$getNode}();
                if (!$StorageNode->isValid()) {
                    $msg = sprintf(
                        '%s %s',
                        _('Could not find any'),
                        _('nodes containing this image')
                    );
                    throw new Exception($msg);
                }
                $imageTaskImgID = $this->get('imageID');
                Route::ids(
                    'host',
                    ['imageID' => $imageTaskImgID]
                );
                $hostsWithImgID = json_decode(Route::getData(), true);
                Route::ids(
                    'host',
                    ['id' => $this->get('id')],
                    'imageID'
                );
                $realImageID = json_decode(Route::getData(), true);
                if (!in_array($this->get('id'), $hostsWithImgID)) {
                    $realImageID = array_shift($realImageID);
                    $this->set(
                        'imageID',
                        $realImageID
                    );
                    if (!$this->save()) {
                        $serverFault = true;
                        throw new Exception(_('Could not update host'));
                    }
                }
                $this->set('imageID', $imageTaskImgID);
            }
            $username = ($username ? $username : self::$FOGUser->get('name'));
            if (!$Task->isValid()) {
                $Task = $this->_createTasking(
                    $taskName,
                    $TaskType->id,
                    $username,
                    $imagingTypes ? $StorageGroup->get('id') : 0,
                    $imagingTypes ? $StorageNode->get('id') : 0,
                    $imagingTypes,
                    $shutdown,
                    $passreset,
                    $debug,
                    $wol,
                    $bypassbitlocker
                );
                $Task->set('imageID', $this->get('imageID'));
                if (!$Task->save()) {
                    $serverFault = true;
                    throw new Exception(self::$foglang['FailedTask']);
                }
                $this->set('task', $Task);
            }
            if ($TaskType->isSnapinTask) {
                if ($deploySnapins === true) {
                    $deploySnapins = -1;
                }
                $mac = $this->get('mac');
                if ($deploySnapins) {
                    $this->_createSnapinTasking(
                        $deploySnapins,
                        $TaskType->isSnapinTasking,
                        $Task
                    );
                }
            }
            if ($TaskType->isMulticast) {
                $assoc = false;
                $showStates = self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                );
                if ($sessionjoin) {
                    Route::listem(
                        'multicastsession',
                        [
                            'name' => $taskName,
                            'stateID' => $showStates
                        ]
                    );
                    $MCSessions = json_decode(
                        Route::getData()
                    );
                    $MCSessions = $MCSessions->data;
                    $assoc = true;
                } else {
                    Route::listem(
                        'multicastsession',
                        [
                            'image' => $Image->get('id'),
                            'stateID' => $showStates
                        ]
                    );
                    $MCSessions = json_decode(
                        Route::getData()
                    );
                    $MCSessions = $MCSessions->data;
                }
                $MultiSessJoin = array_values(
                    array_filter(
                        $MCSessions
                    )
                );
                if (count($MultiSessJoin ?: [])) {
                    $MulticastSession = array_shift($MultiSessJoin);
                    $MulticastSession = new MulticastSession($MulticastSession->id);
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
                        ->set('clients', -1)
                        ->set('maxwait', self::getSetting('FOG_UDPCAST_MAXWAIT') * 60)
                        ->set('shutdown', (int)$shutdown);
                    if (!$MulticastSession->save()) {
                        $serverFault = true;
                        throw new Exception(_('Failed to create multicast task'));
                    }
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
                if ($assoc) {
                    $stat = self::getClass('MulticastSessionAssociation')
                        ->set('msID', $MulticastSession->get('id'))
                        ->set('taskID', $Task->get('id'))
                        ->save();
                    if (!$stat) {
                        $serverFault = true;
                        throw new Exception(_('Unable to create association'));
                    }
                }
            }
            if ($TaskType->id == 14) {
                $Task
                    ->set('stateID', self::getProgressState())
                    ->set('checkInTime', self::formatTime('now', 'Y-m-d H:i:s'))
                    ->save();
            }
            if ($wol || $TaskType->id == 14) {
                $this->wakeOnLAN();
            }
            if ($TaskType->id == 14) {
                $Task
                    ->set('stateID', self::getCompleteState())
                    ->save();
            }
        } catch (Exception $e) {
            $errcode = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $message = $e->getMessage();
            $title = _('Create Task Fail');
            if ($serverFault) {
                $errcode = HTTPResponseCodes::HTTP_INTERNAL_SERVER_ERROR;
            }
            self::error(sprintf(
                '%s: %s, %s: %s, %s: %s',
                _('Title'),
                $title,
                _('HTML Error Code'),
                $errcode,
                _('Message'),
                $message
            ));
            if (preg_match('#/service/ipxe/boot.php', self::$scriptname)) {
                throw new Exception($message);
            }
            http_response_code($errcode);
            echo json_encode(
                [
                    'error' => $message,
                    'title' => $title
                ]
            );
            exit;
        }
        return true;
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
     *
     * @return object
     */
    public function addMAC($addArray)
    {
        if (!is_array($addArray)) {
            $addArray = [$addArray];
        }
        $addArray = array_map('strtolower', $addArray);
        $addArray = self::parseMacList($addArray);
        $insert_fields = ['hostID', 'mac'];
        $insert_values = [];
        foreach ((array)$addArray as &$mac) {
            $insert_values[] = [$this->get('id'), $mac];
            unset($mac);
        }
        if (count($insert_values) > 0) {
            self::getClass('MACAddressAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }

        return $this;
    }
    /**
     * Removes additional macs
     *
     * @param array $removeArray the macs to remove
     *
     * @return object
     */
    public function removeMAC($removeArray)
    {
        Route::deletemass(
            'macaddressassociation',
            [
                'hostID' => $this->get('id'),
                'mac' => (array)$removeArray
            ]
        );
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
        $count = count($mac ?: []);
        if ($count < 1) {
            throw new Exception(_('No viable macs to use'));
        }
        if (is_array($mac) && $count > 0) {
            $mac = array_shift($mac);
        }
        $host = $mac->getHost();
        if ($host instanceof Host && $host->isValid()) {
            throw new Exception(
                sprintf(
                    "%s: %s => %s",
                    _('MAC address is already in use by another host'),
                    $mac,
                    $host->get('name')
                )
            );
        }
        return $this->set('mac', $mac);
    }
    /**
     * Adds pending mac
     *
     * @param string|array[] $mac the mac to add
     *
     * @return obect
     */
    public function addPendMAC($mac)
    {
        if (!is_array($mac)) {
            $mac = [$mac];
        }
        $mac = array_map('strtolower', $mac);
        $mac = self::parseMacList($mac);
        $insert_fields = ['hostID', 'mac', 'pending'];
        $insert_values = [];
        foreach ((array)$mac as &$m) {
            $insert_values[] = [$this->get('id'), $m, '1'];
            unset($m);
        }
        if (count($insert_values) > 0) {
            self::getClass('MACAddressAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }

        return $this;
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
            Route::count(
                'snapin',
                ['id' => $this->get('snapins')]
            );
            $snapinCount = json_decode(Route::getData());
            $snapinCount = $snapinCount->total;
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
        $find = [];
        if ($justme) {
            $find = ['hostID' => $this->get('id')];
        }
        Route::ids(
            'macaddressassociation',
            $find,
            'mac'
        );
        return json_decode(Route::getData(), true);
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
        $myMACs = $igMACs = $cgMACs = [];
        $macaddress = function ($mac) {
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
                [
                    'mac' => array_diff(
                        (array)$myMACs,
                        (array)$igMACs
                    ),
                    'hostID' => $this->get('id')
                ],
                '',
                ['imageIgnore' => 0]
            );
        self::getClass('MACAddressAssociationManager')
            ->update(
                [
                    'mac' => array_diff(
                        (array)$myMACs,
                        (array)$cgMACs
                    ),
                    'hostID'=>$this->get('id')
                ],
                '',
                ['clientIgnore' => 0]
            );
        if (count($igMACs) > 0) {
            self::getClass('MACAddressAssociationManager')
                ->update(
                    [
                        'mac' => $igMACs,
                        'hostID' => $this->get('id')
                    ],
                    '',
                    ['imageIgnore' => 1]
                );
        }
        if (count($cgMACs) > 0) {
            self::getClass('MACAddressAssociationManager')
                ->update(
                    [
                        'mac' => $cgMACs,
                        'hostID'=>$this->get('id')
                    ],
                    '',
                    ['clientIgnore' => 1]
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
     * @param string $productKey the product key for the host to activate
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
        $productKey = ''
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
                if (empty($productKey)) {
                    $productKey = trim($this->get('productKey'));
                }
            }
        }
        if ($pass) {
            $pass = trim($pass);
        }
        return $this
            ->set('useAD', $useAD)
            ->set('ADDomain', trim($domain))
            ->set('ADOU', trim($ou))
            ->set('ADUser', trim($user))
            ->set('ADPass', $pass)
            ->set('productKey', trim($productKey));
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
}
