<?php
/**
 * Main class for group objects.
 *
 * PHP version 7.4+
 *
 * @category Group
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;
use FOG\Boot\SecureBootState;
use FOG\Router\Route;

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
        // Funnel cleanup through the single cascade authority (the group case in
        // Route::deletemass removes groupassociation rows and fires
        // DELETEMASS_API for plugins). deletemass also deletes the group row; the
        // trailing parent::destroy() is a harmless no-op preserving the history.
        Route::deletemass('group', ['id' => $this->get('id')]);
        return parent::destroy($field);
    }
    /**
     * Saves the group elements.
     *
     * @return object
     */
    public function save()
    {
        // Propagate a failed write rather than reporting success; the
        // association work below has no row to attach to either. See
        // tests/save-propagates-failure.test.php.
        if (!parent::save()) {
            return false;
        }
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
        return Route::getCount(
            'host',
            ['id' => $this->get('hosts')]
        );
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
        // Drop any stale/blank ids (e.g. a 0 from an empty submission) so a
        // group push can't seed phantom paPrinterID=0 rows on member hosts.
        // The host-side path is already covered -- FOGController::addRemItem()
        // array_filter()s before adding -- but this builds its insert batch
        // directly, so nothing was filtering it. addSnapin() has carried the
        // same guard for the same reason; addModule() now does too.
        $addArray = self::positiveIntIds($addArray);
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
        $printers = Route::getIds(
            'printerassociation',
            ['hostID' => $this->get('hosts')],
            'printerID'
        );
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
        // Drop any stale/blank ids (e.g. a 0 from an empty submission) so a
        // group push can't seed phantom saSnapinID=0 rows on member hosts.
        $addArray = self::positiveIntIds($addArray);
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
            // insertBatch cannot carry the sequence: it upserts on the
            // (saHostID, saSnapinID) unique key, so a snapin the host already
            // had would have its deliberate run-order overwritten. The rows
            // therefore land at sequence 0, which sorts them ahead of every
            // deliberately ordered snapin (Host::loadSnapins orders by
            // sequence ASC, and createSnapinTasking numbers the tasks in that
            // order). Number them per host afterwards instead.
            //
            // Host::save() used to sweep these up incidentally -- its
            // appendSnapinSequence() gate was always true, because
            // assocSetter() had already consumed the isLoaded() flag it
            // tested. Fixing that gate (#906/#910) correctly stopped the
            // sweep, so the sequence is assigned here at the source.
            foreach ($hosts as $hostID) {
                Host::appendSnapinSequence($hostID);
            }
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
     * Sets the run order of the snapins shared by every host in the group.
     *
     * The submitted ids are the snapins common to all member hosts. On each
     * host those shared snapins are sequenced first (1..N in the submitted
     * order); any host-specific snapins are then renumbered to follow,
     * preserving their existing relative order ("shared first, extras after").
     *
     * @param array $snapinIDs the ordered shared snapin ids
     *
     * @return object
     */
    public function setSnapinOrder($snapinIDs)
    {
        $shared = self::positiveIntIds($snapinIDs);
        if (count($shared) < 1) {
            return $this;
        }
        $hosts = (array)$this->get('hosts');
        foreach ($hosts as $hostID) {
            $Host = new Host($hostID);
            if (!$Host->isValid()) {
                continue;
            }
            // get('snapins') is already ordered by sequence.
            $hostSnapins = array_map('intval', (array)$Host->get('snapins'));
            // Shared snapins this host actually has, in the submitted order,
            // followed by the host's remaining snapins in their current order.
            $sharedOnHost = array_values(array_intersect($shared, $hostSnapins));
            $extras = array_values(array_diff($hostSnapins, $shared));
            $Host->setSnapinOrder(array_merge($sharedOnHost, $extras));
            unset($Host);
        }
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
        // Same guard as addPrinter/addSnapin: this builds its insert batch
        // directly rather than going through addRemItem()'s array_filter(),
        // so a blank submission would seed phantom moduleID=0 rows.
        $addArray = self::positiveIntIds($addArray);
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
            throw new \Exception(_('Select a valid image'));
        }
        $states = self::fastmerge(
            self::getQueuedStates(),
            (array)self::getProgressState()
        );
        $TaskCount = Route::getCount(
            'task',
            [
                'hostID' => $this->get('hosts'),
                'stateID' => $states
            ]
        );
        if ($TaskCount > 0) {
            throw new \Exception(_('There is a host in a tasking'));
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
     * @param bool   $bypassbitlocker reserved to align Host/Group signatures.
     *                                Group tasking does not use this value.
     * @param bool   $snapinAbortOnFailure abort remaining snapins on failure?
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
        $wol = false,
        $bypassbitlocker = false,
        $snapinAbortOnFailure = false
    ) {
        $taskName .= ' - '
            . $this->get('name')
            . ' '
            . self::niceDate()->format('Y-m-d H:i:s');
        $hostCount = $this->getHostCount();
        if ($hostCount < 1) {
            throw new \Exception(_('No hosts to task'));
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
        $hostids = array_diff(
            $hostids,
            Route::getIds('task', $find, 'hostID')
        );
        if (count($hostids ?: []) < 1) {
            throw new \Exception(_('No hosts available to task'));
        }
        $imagingTypes = $TaskType->isImagingTask;
        $now = $this->niceDate();
        // insertBatch() returns [insertID, affectedRows], which is what this
        // method documents itself as returning. Only one of the three
        // branches below was capturing it, so every other path fell off the
        // end reading an undefined $stat -- a warning on PHP 8, and a null
        // return where an array was promised. A wake-up task runs no batch at
        // all, so the empty array is its honest answer.
        $stat = [];
        if ($imagingTypes) {
            $find = ['id' => $hostids];
            $imageID = self::minId(Route::getIds('host', $find, 'imageID'));
            $Image = new Image($imageID);
            if (!$Image->isValid()) {
                throw new \Exception(self::$foglang['ImageNotValid']);
            }
            if (!$Image->get('isEnabled')) {
                throw new \Exception(_('Image is not enabled'));
            }
            $StorageGroup = $Image->getStorageGroup();
            if (!$StorageGroup->isValid()) {
                throw new \Exception(self::$foglang['ImageGroupNotValid']);
            }
            $StorageNode = $StorageGroup->getMasterStorageNode();
            if (!$StorageNode->isValid()) {
                throw new \Exception(_('Unable to find master Storage Node'));
            }
            if ($TaskType->isMulticast) {
                MulticastSession::assertCapacity();
                $MulticastSession = self::getClass('MulticastSession')
                    ->set('name', $taskName)
                    ->set('port', MulticastSession::allocatePort())
                    ->set('logpath', $Image->get('path'))
                    ->set('image', $Image->get('id'))
                    ->set('interface', $StorageNode->get('interface'))
                    ->set('stateID', 0)
                    ->set('starttime', $now->format('Y-m-d H:i:s'))
                    ->set('percent', 0)
                    ->set('isDD', $Image->get('imageTypeID'))
                    ->set('maxwait', self::getSetting('FOG_UDPCAST_MAXWAIT') * 60)
                    ->set('storagegroupID', $StorageGroup->get('id'));
                // A deletemass() of multicastsessionassociation by 'hostID'
                // used to hang off this save(). That table has only ever had
                // msaID/msID/tID -- there is no hostID -- so _buildSql mapped
                // the unknown key to an empty identifier and MariaDB rejected
                // the statement outright (ER_BAD_FIELD_ERROR). deletemass()
                // never checks its return and PDODB::$throwOnQueryError is
                // off, so it failed silently and had done since it was added
                // (222c247a8 merely converted an equally wrong ->destroy()).
                // Nothing depended on it: assoc rows are cleared by taskID in
                // TaskManager::cancel() and by msID in MulticastSession's
                // cancel()/complete(). Removed rather than repaired -- a
                // taskID rewrite would duplicate that cleanup and start
                // deleting rows this path has never deleted.
                $MulticastSession->save();
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
                $this->_createSnapinTasking($now, -1, $snapinAbortOnFailure);
            } elseif ($TaskType->isDeploy) {
                $hostIDs = array_values($hostids);
                $hostCount = count($hostIDs);
                $Hosts = Route::getList(
                    'host',
                    ['id' => $hostIDs]
                );
                $imageMap = [];
                foreach ($Hosts as $Host) {
                    $imageMap[$Host->id] = $Host->imageID;
                }
                $batchFields = [
                    'name',
                    'createdBy',
                    'hostID',
                    'isForced',
                    'stateID',
                    'typeID',
                    'storagegroupID',
                    'storagenodeID',
                    'wol',
                    'imageID',
                    'shutdown',
                    'isDebug',
                    'passreset',
                    'NFSLastMemberID'
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
                        $StorageNode->getStorageGroup()->get('id'),
                        $StorageNode->get('id'),
                        $wol,
                        $imageMap[$hostIDs[$i]] ?? 0,
                        $shutdown,
                        $debug,
                        $passreset,
                        $StorageNode->get('id')
                    ];
                }
                if (count($batchTask ?: []) > 0) {
                    $stat = self::getClass('TaskManager')
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
                    $this->_createSnapinTasking(
                        $now,
                        $deploySnapins,
                        $snapinAbortOnFailure
                    );
                }
            }
        } elseif ($TaskType->isSnapinTasking) {
            $hostIDs = $this->_createSnapinTasking(
                $now,
                $deploySnapins,
                $snapinAbortOnFailure
            );
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
                $stat = self::getClass('TaskManager')
                    ->insertBatch($batchFields, $batchTask);
            }
        } else {
            if ($TaskType->id != TaskType::WAKE_UP) {
                $hostIDs = $this->get('hosts');
                // Drop members the Secure Boot enrolment task cannot run on.
                //
                // The same refusal Host::createImagePackage() makes, at the
                // only other place it can be made: a non-imaging group task
                // never calls that method -- it batch-inserts straight over
                // the member ids -- so a check written only there covers the
                // single-host path and silently misses every group.
                //
                // FILTERS rather than throwing, which is the one way this
                // deliberately differs from the single-host case. A group is
                // a mixed bag by nature and ADR 0008 gives ttIsAccess='both'
                // precisely so a batch in the same state can be scheduled at
                // once; refusing the whole group because one member came back
                // from a firmware change with Secure Boot already on would
                // make the group path useless for the case it exists for.
                //
                // Unreported members are KEPT, matching isEnrolmentTarget():
                // nothing is known until a host PXE boots, and silently
                // dropping every not-yet-seen host would empty the group on
                // the first day this shipped.
                //
                // Only reads a value each host reported about itself, so the
                // worst a spoofed state does is add or remove that one host
                // from its own task. See ADR 0029.
                //
                // One row-load per member, matching setSnapinOrder() above
                // rather than reaching for a lighter query. Deliberate: this
                // runs only for an explicitly scheduled enrolment, which is a
                // rare and considered operation, and the batch insert that
                // follows dominates it on any group big enough to notice.
                if (TaskType::ENROLL_SECUREBOOT == $TaskType->id) {
                    $eligible = [];
                    foreach ((array)$hostIDs as $hostID) {
                        $Host = new Host($hostID);
                        if (!$Host->isValid()) {
                            continue;
                        }
                        if (SecureBootState::isEnrolmentTarget(
                            $Host->get('sbstate')
                        )) {
                            $eligible[] = $hostID;
                        }
                    }
                    if (count($eligible) < 1) {
                        throw new \Exception(
                            _(
                                'No host in this group can run Secure Boot '
                                . 'enrolment. Every member last reported a '
                                . 'firmware state the task cannot work on -- '
                                . 'see the Secure Boot column on the host '
                                . 'list.'
                            )
                        );
                    }
                    $hostIDs = $eligible;
                }
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
        $hostMACs = Route::getIds(
            'macaddressassociation',
            $find,
            'mac'
        );
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
    private function _createSnapinTasking($now, $snapin = -1, $abortOnFailure = false)
    {
        if ($snapin === false) {
            return;
        }
        $find = ['hostID' => $this->get('hosts')];
        $hostIDs = $find['hostID'];
        $snapins = [];
        $snapinJobs = [];
        // GH-707: the "all snapins" case used to query snapinAssoc once per
        // member host inside the loop below -- a thousand round trips for a
        // thousand-host group. The association table answers every host in
        // one pass, so read it once here and index it by host; the loop then
        // just looks the host up.
        $assocByHost = [];
        if ($snapin == -1) {
            $assocs = Route::getIds(
                'snapinassociation',
                $find,
                ['hostID', 'snapinID'],
                'AND',
                'sequence'
            );
            foreach ($assocs as $assoc) {
                $assocByHost[$assoc['hostID']][] = $assoc['snapinID'];
            }
            unset($assocs);
        }
        foreach ($hostIDs as $hostID) {
            if ($snapin == -1) {
                $assoc_snapins = $assocByHost[$hostID] ?? [];
                if (count($assoc_snapins ?: []) < 1) {
                    continue;
                }
                $snapins[$hostID] = $assoc_snapins;
            } else {
                $snapins[$hostID] = [$snapin];
            }
            // Drop 0/blank snapin ids (legacy snapinAssoc rows predating the
            // save() guard) so they never become phantom "null" snapintasks.
            // Must unset the whole host entry when nothing survives: the
            // insert loop below zips array_keys($snapins) against $snapinJobs
            // by index, so a lingering empty key would misalign job -> tasks.
            $snapins[$hostID] = self::positiveIntIds($snapins[$hostID]);
            if (count($snapins[$hostID] ?: []) < 1) {
                unset($snapins[$hostID]);
                continue;
            }
            $snapinJobs[] = [
                $hostID,
                self::getQueuedState(),
                (int)(bool)$abortOnFailure,
                $now->format('Y-m-d H:i:s')
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
                    'abortOnFail',
                    'createdTime',
                ],
                $snapinJobs
            );
            $ids = range($first_id, $first_id + $affected_rows - 1);
            $snapinTasks = [];
            foreach (array_keys($snapins) as $i => $hostID) {
                // Only insert against a job id we actually got back.
                //
                // $ids is positional over the batch, so if insertBatch
                // returned fewer rows than there are hosts, $ids[$i] is simply
                // absent and the task lands with a jobID of 0 -- and range()
                // makes that worse, because range(0, -1) counts DOWN and hands
                // back [0, -1]. A task is only reachable through its job, so
                // such a row can never be shown, run or cancelled; it just
                // sits there, and until #895 it took the snapin task list down
                // with it. One jobID-0 row on the 1.6 lab box is what put us
                // onto this.
                //
                // Mirrors the positiveIntIds() guard the snapin id already
                // gets a few lines up in Host::_createSnapinTasking().
                $jobID = isset($ids[$i]) ? (int)$ids[$i] : 0;
                if ($jobID < 1) {
                    self::info(
                        sprintf(
                            'Skipping snapin tasking for host %s: no snapin '
                            . 'job id was returned for it.',
                            $hostID
                        )
                    );
                    continue;
                }
                $snapinCount = count($snapins[$hostID] ?: []);
                for ($j = 0; $j < $snapinCount; ++$j) {
                    $snapinTasks[] = [
                        $jobID,
                        self::getQueuedState(),
                        $snapins[$hostID][$j],
                        $j + 1,
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
                            'sequence',
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
        $adstate,
        $domain,
        $ou,
        $user,
        $pass
    ) {
        // No-clobber: only push fields the admin actually set. Text fields are
        // already resolved by the caller (null = leave host alone, '' = clear,
        // value = set). useAD is tri-state: '' = no change, '1'/'0' = force.
        $update = [];
        if ($adstate === '1' || $adstate === '0') {
            $update['useAD'] = (int)$adstate;
        }
        if ($domain !== null) {
            $update['ADDomain'] = $domain;
        }
        if ($ou !== null) {
            $update['ADOU'] = $ou;
        }
        if ($user !== null) {
            $update['ADUser'] = $user;
        }
        if ($pass !== null) {
            $update['ADPass'] = $pass;
        }
        if (count($update) > 0) {
            self::getClass('HostManager')
                ->update(
                    ['id' => $this->get('hosts')],
                    '',
                    $update
                );
        }

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
        $this->_loadHostIds(
            'groupassociation',
            ['groupID' => $this->get('id')],
            'hostID'
        );
        $this->getHostCount();
    }
}
