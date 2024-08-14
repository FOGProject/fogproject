<?php
/**
 * Main class for group objects.
 *
 * PHP version 5
 *
 * @category Group
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Main class for group objects.
 *
 * @category Group
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Group extends FOGController
{
    /**
     * The database table.
     *
     * @var string
     */
    protected $databaseTable = 'groups';
    /**
     * Common to db field associations.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'groupID',
        'name' => 'groupName',
        'description' => 'groupDesc',
        'createdBy' => 'groupCreateBy',
        'createdTime' => 'groupDateTime',
        'building' => 'groupBuilding',
        'kernel' => 'groupKernel',
        'kernelArgs' => 'groupKernelArgs',
        'kernelDevice' => 'groupPrimaryDisk',
        'init' => 'groupInit'
    ];
    /**
     * Required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'hosts'
    ];
    protected $sqlQueryStr = "SELECT `%s`,COUNT(`gmHostID`) AS `gmMembers`
        FROM `%s`
        LEFT OUTER JOIN `groupMembers`
        ON `groups`.`groupID` = `groupMembers`.`gmGroupID`
        %s
        GROUP BY (`groups`.`groupID`)
        %s
        %s";
    protected $sqlFilterStr = "SELECT COUNT(`%s`)
        FROM `%s`
        %s";
    protected $sqlTotalStr = "SELECT COUNT(`%s`)
        FROM `%s`";
    /**
     * Destroy the group object and all associations.
     *
     * @param string $field the field to scan for
     *
     * @return bool
     */
    public function destroy($field = 'id')
    {
        Route::deletemass(
            'groupassociation',
            ['groupID' => $this->get('id')]
        );
        return parent::destroy($field);
    }
    /**
     * Saves the group elements.
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('Group', 'host')
            ->load();
    }
    /**
     * Returns the host count.
     *
     * @return int
     */
    public function getHostCount()
    {
        Route::count(
            'host',
            ['id' => $this->get('hosts')]
        );
        $total = json_decode(Route::getData());
        return $total->total;
    }
    /**
     * Adds printers to hosts in this group
     *
     * @param array $addArray the printers to add
     *
     * @return object
     */
    public function addPrinter($addArray)
    {
        if (count($addArray ?: []) > 0) {
            $insert_fields = ['hostID', 'printerID'];
            $insert_values = [];
            $hosts = $this->get('hosts');
            if (count($hosts ?: []) > 0) {
                foreach ((array)$hosts as $ind => &$hostID) {
                    foreach ((array)$addArray as &$printerID) {
                        $insert_values[] = [$hostID, $printerID];
                        unset($printerID);
                    }
                    unset($hostID);
                }
            }
            if (count($insert_values ?: []) > 0) {
                self::getClass('PrinterAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        }

        return $this;
    }
    /**
     * Removes printers from all hosts in this group.
     *
     * @param array $removeArray The array of items to remove.
     *
     * @return object
     */
    public function removePrinter($removeArray)
    {
        Route::deletemass(
            'printerassociation',
            [
                'printerID' => $removeArray,
                'hostID' => $this->get('hosts')
            ]
        );
        return $this;
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
        Route::ids(
            'printerassociation',
            ['hostID' => $this->get('hosts')],
            'printerID'
        );
        $printers = json_decode(Route::getData(), true);
        $printers = array_diff(
            $printers,
            [$printerid]
        );
        self::getClass('PrinterAssociationManager')
            ->update(
                [
                    'printerID' => $printers,
                    'hostID' => $this->get('hosts'),
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
                        'hostID' => $this->get('hosts'),
                        'isDefault' => ['0', '']
                    ],
                    '',
                    ['isDefault' => 1]
                );
        }
        return $this;
    }
    /**
     * Add Snapins to all hosts in the group.
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addSnapin($addArray)
    {
        $insert_fields = ['hostID', 'snapinID'];
        $insert_values = [];
        $hosts = $this->get('hosts');
        if (count($hosts ?: []) > 0) {
            array_walk(
                $hosts,
                function (
                    &$hostID,
                    $index
                ) use (
                    &$insert_values,
                    $addArray
                ) {
                    foreach ($addArray as $snapinID) {
                        $insert_values[] = [$hostID, $snapinID];
                    }
                }
            );
        }
        if (count($insert_values ?: []) > 0) {
            self::getClass('SnapinAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
        }

        return $this;
    }
    /**
     * Remove snapin from all hosts in group.
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeSnapin($removeArray)
    {
        Route::deletemass(
            'snapinassociation',
            [
                'snapinID' => $removeArray,
                'hostID' => $this->get('hosts')
            ]
        );
        return $this;
    }
    /**
     * Add modules to all hosts in group.
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addModule($addArray)
    {
        $insert_fields = ['hostID', 'moduleID', 'state'];
        $insert_values = [];
        $hostids = $this->get('hosts');
        foreach ((array) $hostids as &$hostid) {
            foreach ((array) $addArray as &$moduleid) {
                $insert_values[] = [$hostid, $moduleid, 1];
                unset($moduleid);
            }
            unset($hostid);
        }
        if (count($insert_values ?: []) > 0) {
            self::getClass('ModuleAssociationManager')
                ->insertBatch(
                    $insert_fields,
                    $insert_values
                );
            unset($insert_value);
        }

        return $this;
    }
    /**
     * Remove modules from hosts in group.
     *
     * @param array $removeArray The items to remove
     *
     * @return object
     */
    public function removeModule($removeArray)
    {
        Route::deletemass(
            'moduleassociation',
            [
                'moduleID' => $removeArray,
                'hostID' => $this->get('hosts')
            ]
        );

        return $this;
    }
    /**
     * Set's the display for all hosts in group.
     *
     * @param mixed $x the width to set
     * @param mixed $y the height to set
     * @param mixed $r the refresh rate to set
     *
     * @return object
     */
    public function setDisp(
        $x,
        $y,
        $r
    ) {
        Route::deletemass(
            'hostscreensetting',
            ['hostID' => $this->get('hosts')]
        );
        $insert_fields = [
            'hostID',
            'width',
            'height',
            'refresh',
        ];
        $insert_items = [];
        foreach ((array) $this->get('hosts') as &$hostID) {
            $insert_items[] = [$hostID, $x, $y, $r];
            unset($hostID);
        }
        self::getClass('HostScreenSettingManager')
            ->insertBatch(
                $insert_fields,
                $insert_items
            );

        return $this;
    }
    /**
     * Set's the auto logout time for all hosts.
     *
     * @param mixed $time the time to set to
     *
     * @return object
     */
    public function setAlo($time)
    {
        Route::deletemass(
            'hostautologout',
            ['hostID' => $this->get('hosts')]
        );
        $insert_fields = [
            'hostID',
            'time',
        ];
        $insert_items = [];
        foreach ((array) $this->get('hosts') as &$hostID) {
            $insert_items[] = [
                $hostID,
                $time,
            ];
            unset($hostID);
        }
        self::getClass('HostAutoLogoutManager')
            ->insertBatch(
                $insert_fields,
                $insert_items
            );

        return $this;
    }
    /**
     * Add host to the group.
     *
     * @param array $addArray the host to add
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem(
            'hosts',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Remove host from the group.
     *
     * @param array $removeArray the host to remove
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem(
            'hosts',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Add image to all hosts.
     *
     * @param int $imageID the image id to associate
     *
     * @throws Exception
     *
     * @return object
     */
    public function addImage($imageID)
    {
        $Image = new Image($imageID);
        if (!$Image->isValid() && is_numeric($imageID)) {
            throw new Exception(_('Select a valid image'));
        }
        $states = self::fastmerge(
            self::getQueuedStates(),
            (array)self::getProgressState()
        );
        Route::count(
            'task',
            [
                'hostID' => $this->get('hosts'),
                'stateID' => $states
            ]
        );
        $total = json_decode(Route::getData());
        $TaskCount = $total->total;
        if ($TaskCount > 0) {
            throw new Exception(_('There is a host in a tasking'));
        }
        self::getClass('HostManager')
            ->update(
                ['id' => $this->get('hosts')],
                '',
                ['imageID' => $imageID]
            );

        return $this;
    }
    /**
     * Creates image packages for all hosts associated.
     *
     * @param int    $TaskType      the task type id
     * @param string $taskName      the name of the tasking
     * @param bool   $shutdown      whether to shutdown the hosts
     * @param bool   $debug         is tasking debug
     * @param mixed  $deploySnapins All, false, or specified snapin
     * @param bool   $isGroupTask   will always be true here
     * @param string $username      username creating the task
     * @param string $passreset     which account to reset if pass reset
     * @param mixed  $sessionjoin   the multicast session to join
     * @param bool   $wol           whether to wake on lan or not
     *
     * @return array
     */
    public function createImagePackage(
        $TaskType,
        $taskName = '',
        $shutdown = false,
        $debug = false,
        $deploySnapins = false,
        $isGroupTask = true,
        $username = '',
        $passreset = '',
        $sessionjoin = false,
        $wol = false
    ) {
        $taskName .= ' - '
            . $this->get('name')
            . ' '
            . self::niceDate()->format('Y-m-d H:i:s');
        $hostCount = $this->getHostCount();
        if ($hostCount < 1) {
            throw new Exception(_('No hosts to task'));
        }
        $hostids = $this->get('hosts');
        $find = [
            'hostID' => $hostids,
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            ),
            'typeID' => $TaskType->initIDs
        ];
        Route::ids(
            'task',
            $find,
            'hostID'
        );
        $hostids = array_diff(
            $hostids,
            json_decode(Route::getData(), true)
        );
        if (count($hostids ?: []) < 1) {
            throw new Exception(_('No hosts available to task'));
        }
        $imagingTypes = $TaskType->isImagingTask;
        $now = $this->niceDate();
        if ($imagingTypes) {
            $find = ['id' => $hostids];
            Route::ids(
                'host',
                $find,
                'imageID'
            );
            $imageID = @min(json_decode(Route::getData(), true));
            $Image = new Image($imageID);
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
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode->isValid()) {
                throw new Exception(_('Unable to find master Storage Node'));
            }
            if ($TaskType->isMulticast) {
                $keys = [
                    'FOG_MULTICAST_PORT_OVERRIDE',
                    'FOG_UDPCAST_STARTINGPORT'
                ];
                list(
                    $portOverride,
                    $defaultPort
                ) = self::getSetting($keys);
                if ($portOverride) {
                    $port = $portOverride;
                } else {
                    $port = $defaultPort;
                }
                $MulticastSession = self::getClass('MulticastSession')
                    ->set('name', $taskName)
                    ->set('port', $port)
                    ->set('logpath', $Image->get('path'))
                    ->set('image', $Image->get('id'))
                    ->set('interface', $StorageNode->get('interface'))
                    ->set('stateID', 0)
                    ->set('starttime', $now->format('Y-m-d H:i:s'))
                    ->set('percent', 0)
                    ->set('isDD', $Image->get('imageTypeID'))
                    ->set('maxwait', self::getSetting('FOG_UDPCAST_MAXWAIT') * 60)
                    ->set('storagegroupID', $StorageGroup->get('id'));
                if ($MulticastSession->save()) {
                    Route::deletemass(
                        'multicastsessionassociation',
                        ['hostID' => $hostids]
                    );
                    $randomnumber = mt_rand(24576, 32766) * 2;
                    while ($randomnumber == $MulticastSession->get('port')) {
                        $randomnumber = mt_rand(24576, 32766) * 2;
                    }
                    self::setSetting('FOG_UDPCAST_STARTINGPORT', $randomnumber);
                }
                $hostIDs = array_values($hostids);
                $hostCount = count($hostIDs);
                $batchFields = [
                    'name',
                    'createdBy',
                    'hostID',
                    'isForced',
                    'stateID',
                    'typeID',
                    'storagenodeID',
                    'wol',
                    'imageID',
                    'shutdown',
                    'isDebug',
                    'passreset',
                ];
                $batchTask = [];
                for ($i = 0; $i < $hostCount; ++$i) {
                    $batchTask[] = [
                        $taskName,
                        $username,
                        $hostIDs[$i],
                        0,
                        self::getQueuedState(),
                        $TaskType->id,
                        $StorageNode->get('id'),
                        $wol,
                        $Image->get('id'),
                        $shutdown,
                        $debug,
                        $passreset,
                    ];
                }
                if (count($batchTask ?: []) > 0) {
                    list(
                        $first_id,
                        $affected_rows
                    ) = self::getClass('TaskManager')
                    ->insertBatch(
                        $batchFields,
                        $batchTask
                    );
                    $ids = range($first_id, ($first_id + $affected_rows - 1));
                    $multicastsessionassocs = [];
                    foreach ((array) $batchTask as $index => &$val) {
                        $multicastsessionassocs[] = [
                            $MulticastSession->get('id'),
                            $ids[$index],
                        ];
                        unset($val);
                    }
                    if (count($multicastsessionassocs ?: []) > 0) {
                        self::getClass('MulticastSessionAssociationManager')
                            ->insertBatch(
                                [
                                    'msID',
                                    'taskID',
                                ],
                                $multicastsessionassocs
                            );
                    }
                }
                unset(
                    $hostCount,
                    $batchTask,
                    $first_id,
                    $affected_rows,
                    $ids,
                    $multicastsessionassocs
                );
                $this->_createSnapinTasking($now, -1);
            } elseif ($TaskType->isDeploy) {
                $hostIDs = array_values($hostids);
                $hostCount = count($hostIDs);
                $find = ['id' => $hostIDs];
                Route::ids(
                    'host',
                    $find,
                    'imageID'
                );
                $imageIDs = json_decode(Route::getData(), true);
                $batchFields = [
                    'name',
                    'createdBy',
                    'hostID',
                    'isForced',
                    'stateID',
                    'typeID',
                    'storagenodeID',
                    'wol',
                    'imageID',
                    'shutdown',
                    'isDebug',
                    'passreset'
                ];
                $batchTask = [];
                for ($i = 0; $i < $hostCount; ++$i) {
                    $batchTask[] = [
                        $taskName,
                        $username,
                        $hostIDs[$i],
                        0,
                        self::getQueuedState(),
                        $TaskType->id,
                        $StorageNode->get('id'),
                        $wol,
                        $imageIDs[$i],
                        $shutdown,
                        $debug,
                        $passreset,
                    ];
                }
                if (count($batchTask ?: []) > 0) {
                    self::getClass('TaskManager')
                        ->insertBatch(
                            $batchFields,
                            $batchTask
                        );
                }
                unset(
                    $hostCount,
                    $batchTask,
                    $first_id,
                    $affected_rows,
                    $ids,
                    $multicastsessionassocs
                );
                if ($TaskType->isSnapinTask) {
                    $this->_createSnapinTasking($now, $deploySnapins);
                }
            }
        } elseif ($TaskType->isSnapinTasking) {
            $hostIDs = $this->_createSnapinTasking($now, $deploySnapins);
            $hostCount = count($hostIDs ?: []);
            $batchFields = [
                'name',
                'createdBy',
                'hostID',
                'stateID',
                'typeID',
                'wol',
                'shutdown'
            ];
            $batchTask = [];
            for ($i = 0; $i < $hostCount; ++$i) {
                $batchTask[] = [
                    $taskName,
                    $username,
                    $hostIDs[$i],
                    self::getQueuedState(),
                    $TaskType->id,
                    $wol,
                    $shutdown
                ];
            }
            if (count($batchTask ?: []) > 0) {
                self::getClass('TaskManager')
                    ->insertBatch($batchFields, $batchTask);
            }
        } else {
            if ($TaskType->id != TaskType::WAKE_UP) {
                $hostIDs = $this->get('hosts');
                $hostCount = count($hostIDs ?: []);
                $batchFields = [
                    'name',
                    'createdBy',
                    'hostID',
                    'stateID',
                    'typeID',
                    'wol'
                ];
                $batchTask = [];
                for ($i = 0; $i < $hostCount; ++$i) {
                    $batchTask[] = [
                        $taskName,
                        $username,
                        $hostIDs[$i],
                        self::getQueuedState(),
                        $TaskType->id,
                        $wol,
                    ];
                }
                if (count($batchTask ?: []) > 0) {
                    $stat = self::getClass('TaskManager')
                        ->insertBatch($batchFields, $batchTask);
                }
            }
        }
        if ($wol) {
            ignore_user_abort(true);
            set_time_limit(0);
            $this->wakeOnLAN();
        }
        return $stat;
    }
    /**
     * Perform wake on lan to all hosts in group.
     *
     * @return void
     */
    public function wakeOnLAN()
    {
        $find = [
            'hostID' => $this->get('hosts'),
            'pending' => [0, '']
        ];
        Route::ids(
            'macaddressassociation',
            $find,
            'mac'
        );
        $hostMACs = json_decode(Route::getData(), true);
        $hostMACs = self::parseMacList($hostMACs);
        if (count($hostMACs ?: []) > 0) {
            $macStr = implode(
                '|',
                $hostMACs
            );
            self::wakeUp($hostMACs);
        }
    }
    /**
     * Create snapin tasks for hosts.
     *
     * @param mixed $now    the current time
     * @param int   $snapin the snapin to task (all is -1)
     *
     * @return array
     */
    private function _createSnapinTasking($now, $snapin = -1)
    {
        if ($snapin === false) {
            return;
        }
        $find = ['hostID' => $this->get('hosts')];
        /*Route::ids(
            'snapinassociation',
            $find,
            'hostID'
        );
        $hostIDs = json_decode(Route::getData(), true);*/
        $hostIDs = $find['hostID'];
        $hostCount = count($hostIDs ?: []);
        $snapinJobs = [];
        for ($i = 0; $i < $hostCount; ++$i) {
            $hostID = $hostIDs[$i];
            if ($snapin == -1) {
                $find = ['hostID' => $hostID];
                Route::ids(
                    'snapinassociation',
                    $find,
                    'snapinID'
                );
                $snapins[$hostID] = json_decode(Route::getData(), true);
            } else {
                $snapins[$hostID] = [$snapin];
            }
            if (count($snapins[$hostID] ?: []) < 1) {
                continue;
            }
            $snapinJobs[] = [
                $hostID,
                self::getQueuedState(),
                $now->format('Y-m-d H:i:s'),
            ];
        }
        if (count($snapinJobs ?: []) > 0) {
            list(
                $first_id,
                $affected_rows
            ) = self::getClass('SnapinJobManager')
            ->insertBatch(
                [
                    'hostID',
                    'stateID',
                    'createdTime',
                ],
                $snapinJobs
            );
            $ids = range($first_id, ($first_id + $affected_rows - 1));
            for ($i = 0; $i < $hostCount; ++$i) {
                $hostID = $hostIDs[$i];
                $jobID = $ids[$i];
                $snapinCount = count($snapins[$hostID] ?: []);
                for ($j = 0; $j < $snapinCount; ++$j) {
                    $snapinTasks[] = [
                        $jobID,
                        self::getQueuedState(),
                        $snapins[$hostID][$j],
                    ];
                }
            }
            if (count($snapinTasks ?: []) > 0) {
                self::getClass('SnapinTaskManager')
                    ->insertBatch(
                        [
                            'jobID',
                            'stateID',
                            'snapinID',
                        ],
                        $snapinTasks
                    );
            }
        }

        return $hostIDs;
    }
    /**
     * Sets all hosts AD information.
     *
     * @param int    $useAD  tells whether to enable/disable AD
     * @param string $domain the domain to associate
     * @param string $ou     the ou to associate
     * @param string $user   the user to join domain with
     * @param string $pass   the user password for domain join
     *
     * @return object
     */
    public function setAD(
        $useAD,
        $domain,
        $ou,
        $user,
        $pass
    ) {
        $pass = trim($pass);
        $adpasspat = "/^\*{32}$/";
        $pass = (preg_match($adpasspat, $pass) ? $this->get('ADPass') : $pass);
        self::getClass('HostManager')
            ->update(
                ['id' => $this->get('hosts')],
                '',
                [
                    'useAD' => $useAD,
                    'ADDomain' => trim($domain),
                    'ADOU' => trim($ou),
                    'ADUser' => trim($user),
                    'ADPass' => $pass,
                ]
            );

        return $this;
    }
    /**
     * Checks all hosts have the same image associated.
     *
     * @return bool
     */
    public function doMembersHaveUniformImages()
    {
        $test = self::getClass('HostManager')
            ->distinct(
                'imageID',
                ['id' => $this->get('hosts')]
            );

        return $test == 1;
    }
    /**
     * Loads hosts in this group.
     *
     * @return void
     */
    protected function loadHosts()
    {
        $find = ['groupID' => $this->get('id')];
        Route::ids(
            'groupassociation',
            $find,
            'hostID'
        );
        $hosts = json_decode(Route::getData(), true);
        $this->set('hosts', (array)$hosts);
        $this->getHostCount();
    }
}
