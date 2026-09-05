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

use FOG\Agent\WakeRelay;
use FOG\Assign\Resolver;
use FOG\Base\FOGController;
use FOG\Boot\SecureBootState;
use FOG\Boot\UbootTftpSync;
use FOG\Managers\GroupModuleAssociationManager;
use FOG\Managers\GroupPrinterAssociationManager;
use FOG\Managers\GroupSnapinAssociationManager;
use FOG\Managers\GroupSoftwareAssociationManager;
use FOG\Managers\HostAutoLogoutManager;
use FOG\Managers\HostManager;
use FOG\Managers\HostScreenSettingManager;
use FOG\Managers\MulticastSessionAssociationManager;
use FOG\Managers\SnapinJobManager;
use FOG\Managers\SnapinTaskManager;
use FOG\Managers\TaskManager;
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
        // ADR 0038. FOG\Assign\Resolver orders the groups a host belongs to
        // by this column before falling back to name, so it decides which
        // group wins when two grant conflicting defaults. It shipped with the
        // resolver but with nothing able to write it, which left every group
        // on the default of 0 and made the fallback the only behavior.
        'order' => 'groupOrder',
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
     * @return bool|object false when the row itself could not be written
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
     * Grants printers to this group.
     *
     * ADR 0038: the row lands on the GROUP (groupPrinterAssoc), not on each
     * member host. A host added to the group afterward gains the printer,
     * and a host removed loses it, because Assign\Resolver unions the grant
     * with the host's own printerAssoc rows at read time. Before this the
     * method wrote one printerAssoc row per host that existed at the moment
     * the button was pressed, and nothing recorded that it had happened.
     *
     * @param array $addArray the printers to grant
     *
     * @return object
     */
    public function addPrinter($addArray)
    {
        // Drop any stale/blank ids (e.g. a 0 from an empty submission) so a
        // grant can't seed a phantom gpaPrinterID=0 row. insertBatch builds
        // its statement directly and nothing else is filtering it.
        $addArray = self::positiveIntIds($addArray);
        $groupID = (int)$this->get('id');
        if ($groupID < 1 || count($addArray) < 1) {
            return $this;
        }
        $insert_values = [];
        foreach ($addArray as $printerID) {
            $insert_values[] = [$groupID, $printerID];
        }
        // isDefault is deliberately NOT in the field list. insertBatch
        // upserts on the (gpaGroupID, gpaPrinterID) unique key and sets every
        // column it is given, so naming it would reset a default the admin
        // had already chosen every time the printer was re-sent.
        (new GroupPrinterAssociationManager())
            ->insertBatch(
                ['groupID', 'printerID'],
                $insert_values
            );

        return $this;
    }
    /**
     * Revokes printers from this group.
     *
     * @param array $removeArray The array of items to remove.
     *
     * @return object
     */
    public function removePrinter($removeArray)
    {
        Route::deletemass(
            'groupprinterassociation',
            [
                'printerID' => $removeArray,
                'groupID' => $this->get('id')
            ]
        );
        return $this;
    }
    /**
     * Sets which of the group's granted printers is the default.
     *
     * One row at a time rather than a member sweep: the group owns exactly
     * one row per printer now, so "the default" is a column on the grant.
     * A host that has set its own default still wins -- the group's answer
     * is only consulted when the host has none (Assign\Resolver).
     *
     * @param int $printerid the printer id to make default, or 0 for none
     *
     * @return object
     */
    public function updateDefault($printerid)
    {
        $groupID = (int)$this->get('id');
        if ($groupID < 1) {
            return $this;
        }
        $printerid = (int)$printerid;
        // Clear first, unconditionally: passing 0 is how the page says "no
        // default", and that has to be expressible.
        (new GroupPrinterAssociationManager())
            ->update(
                ['groupID' => $groupID],
                '',
                ['isDefault' => 0]
            );
        if ($printerid > 0) {
            (new GroupPrinterAssociationManager())
                ->update(
                    [
                        'groupID' => $groupID,
                        'printerID' => $printerid
                    ],
                    '',
                    ['isDefault' => 1]
                );
        }
        return $this;
    }
    /**
     * Grants snapins to this group.
     *
     * ADR 0038: one row on the group, not one row per member host. See
     * addPrinter() for the shape of the change.
     *
     * @param array $addArray the items to grant
     *
     * @return object
     */
    public function addSnapin($addArray)
    {
        // Drop any stale/blank ids (e.g. a 0 from an empty submission) so a
        // grant can't seed a phantom gsaSnapinID=0 row.
        $addArray = self::positiveIntIds($addArray);
        $groupID = (int)$this->get('id');
        if ($groupID < 1 || count($addArray) < 1) {
            return $this;
        }
        $insert_values = [];
        foreach ($addArray as $snapinID) {
            $insert_values[] = [$groupID, $snapinID];
        }
        // sequence is deliberately NOT in the field list, for the same
        // reason isDefault is not in addPrinter's: insertBatch upserts on the
        // unique key and would overwrite the run order the admin set on the
        // Snapin Run Order card. New rows therefore land at the column
        // default of 0, and the sweep below numbers them.
        (new GroupSnapinAssociationManager())
            ->insertBatch(
                ['groupID', 'snapinID'],
                $insert_values
            );
        $this->appendSnapinSequence();

        return $this;
    }
    /**
     * Revokes snapins from this group.
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeSnapin($removeArray)
    {
        Route::deletemass(
            'groupsnapinassociation',
            [
                'snapinID' => $removeArray,
                'groupID' => $this->get('id')
            ]
        );
        return $this;
    }
    /**
     * Grants software to this group.
     *
     * ADR 0038: one row on the group, not one row per member host. See
     * addPrinter() for the shape of the change.
     *
     * @param array $addArray the items to grant
     *
     * @return object
     */
    public function addSoftware($addArray)
    {
        // Drop any stale/blank ids (e.g. a 0 from an empty submission) so a
        // grant can't seed a phantom gswaSoftwareID=0 row.
        $addArray = self::positiveIntIds($addArray);
        $groupID = (int)$this->get('id');
        if ($groupID < 1 || count($addArray) < 1) {
            return $this;
        }
        $insert_values = [];
        foreach ($addArray as $softwareID) {
            $insert_values[] = [$groupID, $softwareID];
        }
        // sequence is deliberately NOT in the field list, for the same
        // reason isDefault is not in addPrinter's: insertBatch upserts on the
        // unique key and would overwrite the run order the admin set on the
        // Software Order card. New rows therefore land at the column
        // default of 0, and the sweep below numbers them.
        (new GroupSoftwareAssociationManager())
            ->insertBatch(
                ['groupID', 'softwareID'],
                $insert_values
            );
        $this->appendSoftwareSequence();

        return $this;
    }
    /**
     * Revokes software from this group.
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeSoftware($removeArray)
    {
        Route::deletemass(
            'groupsoftwareassociation',
            [
                'softwareID' => $removeArray,
                'groupID' => $this->get('id')
            ]
        );
        return $this;
    }
    /**
     * Numbers any of this group's snapin grants that have no sequence yet.
     *
     * The host-side twin is Host::appendSnapinSequence() and this is the same
     * mechanism for the same reason: a row inserted without a sequence sits
     * at the column default of 0, which sorts it ahead of every deliberately
     * ordered snapin. Numbering after the insert rather than during it is
     * what lets addSnapin() leave an existing row's order alone.
     *
     * @return object
     */
    public function appendSnapinSequence()
    {
        $groupID = (int)$this->get('id');
        if ($groupID < 1) {
            return $this;
        }
        $associations = Route::getList(
            'groupsnapinassociation',
            ['groupID' => $groupID],
            'AND',
            'sequence'
        );
        $maxSequence = 0;
        $unsequenced = [];
        foreach ($associations as $association) {
            $sequence = (int)$association->sequence;
            if ($sequence > 0) {
                $maxSequence = max($maxSequence, $sequence);
            } else {
                $unsequenced[] = $association->snapinID;
            }
        }
        foreach ($unsequenced as $snapinID) {
            (new GroupSnapinAssociationManager())
                ->update(
                    [
                        'groupID' => $groupID,
                        'snapinID' => $snapinID
                    ],
                    '',
                    ['sequence' => ++$maxSequence]
                );
        }
        return $this;
    }
    /**
     * Sets the run order of the snapins this group grants.
     *
     * The submitted ids are the group's own grants, so this writes
     * gsaSequence and touches no host. Before ADR 0038 it had to reconstruct
     * an order per member host -- "shared snapins first, host-specific ones
     * after" -- because the group owned nothing to order.
     *
     * @param array $snapinIDs the ordered snapin ids (first runs first)
     *
     * @return object
     */
    public function setSnapinOrder($snapinIDs)
    {
        $groupID = (int)$this->get('id');
        if ($groupID < 1) {
            return $this;
        }
        $sequence = 0;
        foreach ((array)$snapinIDs as $snapinID) {
            $snapinID = (int)$snapinID;
            if ($snapinID < 1) {
                continue;
            }
            ++$sequence;
            (new GroupSnapinAssociationManager())
                ->update(
                    [
                        'groupID' => $groupID,
                        'snapinID' => $snapinID
                    ],
                    '',
                    ['sequence' => $sequence]
                );
        }
        return $this;
    }
    /**
     * Numbers any of this group's software grants that have no sequence yet.
     *
     * Mirrors appendSnapinSequence() above over groupSoftwareAssoc.
     *
     * @return object
     */
    public function appendSoftwareSequence()
    {
        $groupID = (int)$this->get('id');
        if ($groupID < 1) {
            return $this;
        }
        $associations = Route::getList(
            'groupsoftwareassociation',
            ['groupID' => $groupID],
            'AND',
            'sequence'
        );
        $maxSequence = 0;
        $unsequenced = [];
        foreach ($associations as $association) {
            $sequence = (int)$association->sequence;
            if ($sequence > 0) {
                $maxSequence = max($maxSequence, $sequence);
            } else {
                $unsequenced[] = $association->softwareID;
            }
        }
        foreach ($unsequenced as $softwareID) {
            (new GroupSoftwareAssociationManager())
                ->update(
                    [
                        'groupID' => $groupID,
                        'softwareID' => $softwareID
                    ],
                    '',
                    ['sequence' => ++$maxSequence]
                );
        }
        return $this;
    }
    /**
     * Sets the run order of the software this group grants.
     *
     * Mirrors setSnapinOrder() above.
     *
     * @param array $softwareIDs the ordered software ids (first runs first)
     *
     * @return object
     */
    public function setSoftwareOrder($softwareIDs)
    {
        $groupID = (int)$this->get('id');
        if ($groupID < 1) {
            return $this;
        }
        $sequence = 0;
        foreach ((array)$softwareIDs as $softwareID) {
            $softwareID = (int)$softwareID;
            if ($softwareID < 1) {
                continue;
            }
            ++$sequence;
            (new GroupSoftwareAssociationManager())
                ->update(
                    [
                        'groupID' => $groupID,
                        'softwareID' => $softwareID
                    ],
                    '',
                    ['sequence' => $sequence]
                );
        }
        return $this;
    }
    /**
     * Grants modules to this group.
     *
     * ADR 0038: presence is the whole statement. There is no state column on
     * groupModuleAssoc because a group cannot turn a module OFF -- only a
     * host can, with a moduleStatusByHost row at msState=0, and that beats
     * every grant. The old version wrote msState=1 onto every member host,
     * which is exactly the copy that made a host's own OFF unrepresentable.
     *
     * @param array $addArray the items to grant
     *
     * @return object
     */
    public function addModule($addArray)
    {
        $addArray = self::positiveIntIds($addArray);
        $groupID = (int)$this->get('id');
        if ($groupID < 1 || count($addArray) < 1) {
            return $this;
        }
        $insert_values = [];
        foreach ($addArray as $moduleID) {
            $insert_values[] = [$groupID, $moduleID];
        }
        (new GroupModuleAssociationManager())
            ->insertBatch(
                ['groupID', 'moduleID'],
                $insert_values
            );

        return $this;
    }
    /**
     * Revokes modules from this group.
     *
     * A host that had the module only through this grant loses it. A host
     * that stated the module on for itself keeps it, because its own row is
     * a separate fact.
     *
     * @param array $removeArray The items to remove
     *
     * @return object
     */
    public function removeModule($removeArray)
    {
        Route::deletemass(
            'groupmoduleassociation',
            [
                'moduleID' => $removeArray,
                'groupID' => $this->get('id')
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
        (new HostScreenSettingManager())
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
        (new HostAutoLogoutManager())
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
        (new HostManager())
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
     * @param object $TaskType      the tasktype getter's object, as
     *                              Route::getItem('tasktype') returns it --
     *                              not a TaskType, and never an id
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
                $MulticastSession = (new MulticastSession())
                    ->set('name', $taskName)
                    ->set('port', MulticastSession::allocatePort())
                    ->set('logpath', $Image->get('path'))
                    ->set('image', $Image->get('id'))
                    ->set('interface', $StorageNode->get('interface'))
                    // A session that has not started names no state. NULL
                    // rather than 0 since schema step 386 -- taskStates
                    // has no row with tsID 0.
                    ->set('stateID', null)
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
                    ) = (new TaskManager())
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
                        (new MulticastSessionAssociationManager())
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
            } elseif ($TaskType->isDeploy || $TaskType->isCapture) {
                // Capture shares the deploy insert: the two differ only by
                // typeID, which is already a parameter. There used to be no
                // capture arm at all, so a capture arrived here, matched
                // nothing, and left without a row -- and the caller then
                // reported "Tasking created" over an empty tasks table
                // (#1677). It was reachable from the host list, whose
                // selection tasking goes through this method on an unsaved
                // Group for every task type, and from a POST /group/{id}/task.
                //
                // Capture is a one-host task type (ttIsAccess='host'):
                // several hosts writing the same image at once would corrupt
                // it. The host list refuses a bigger selection before it gets
                // here; the API does not, so refuse it here too rather than
                // silently create the race.
                //
                // ->name, not ->get('name'): both callers hand this method
                // the tasktype getter's object, not a TaskType.
                if ($TaskType->isCapture && count($hostids ?: []) > 1) {
                    throw new \Exception(
                        sprintf(
                            _('%s can only be run on one host at a time'),
                            $TaskType->name
                        )
                    );
                }
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
                    $stat = (new TaskManager())
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
                $stat = (new TaskManager())
                    ->insertBatch($batchFields, $batchTask);
            }
        } else {
            if ($TaskType->id != TaskType::WAKE_UP) {
                $hostIDs = $this->get('hosts');
                // Drop members the Secure Boot enrollment task cannot run on.
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
                // Unreported members are KEPT, matching isEnrollmentTarget():
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
                // runs only for an explicitly scheduled enrollment, which is a
                // rare and considered operation, and the batch insert that
                // follows dominates it on any group big enough to notice.
                if (TaskType::ENROLL_SECUREBOOT == $TaskType->id) {
                    $eligible = [];
                    foreach ((array)$hostIDs as $hostID) {
                        $Host = new Host($hostID);
                        if (!$Host->isValid()) {
                            continue;
                        }
                        if (SecureBootState::isEnrollmentTarget(
                            $Host->get('sbstate')
                        )) {
                            $eligible[] = $hostID;
                        }
                    }
                    if (count($eligible) < 1) {
                        throw new \Exception(
                            _(
                                'No host in this group can run Secure Boot '
                                . 'enrollment. Every member last reported a '
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
                    $stat = (new TaskManager())
                        ->insertBatch($batchFields, $batchTask);
                }
            }
        }
        if ($wol) {
            ignore_user_abort(true);
            set_time_limit(0);
            $this->wakeOnLAN();
        }
        // Batched, not one materialize() per host: queuing this group task
        // may have just tasked 100+ hosts, and that should open one SSH
        // session here, not one inside each iteration of the branches
        // above. Re-derived from the database rather than tracked through
        // the branches -- every branch above computes its own $hostIDs
        // under a different filter (WAKE_UP tasks nothing at all, Secure
        // Boot enrollment drops ineligible members), so asking "who among
        // $hostids actually has a task now" is correct regardless of which
        // branch ran, where threading a per-branch list through would not
        // be.
        UbootTftpSync::materializeMany(
            Route::getIds(
                'task',
                [
                    'hostID' => $hostids,
                    'stateID' => self::fastmerge(
                        self::getQueuedStates(),
                        (array)self::getProgressState()
                    ),
                ],
                'hostID'
            )
        );

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
        // Design 0011, additional to the fan-out above. Per host rather
        // than per MAC, because a relay request names the machine being
        // woken so its result has a row to land on -- and because the
        // neighbor that can reach one host on a link is not necessarily
        // the one that can reach another.
        foreach ((array)$this->get('hosts') as $hostID) {
            WakeRelay::request(new Host($hostID), 'group wake');
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
        $hostIDs = $this->get('hosts');
        $snapins = [];
        $snapinJobs = [];
        // ADR 0038 decision 4: the "all snapins" list is RESOLVED here, at
        // task creation, and what it resolves to is written onto the task.
        // `snapinTasks` is already the snapshot -- it carries the snapin and
        // its sequence per row -- so nothing about the task side changes.
        // What changed is only where the list comes from: the host's own
        // associations plus whatever its groups GRANT, in the one order
        // Resolver promises. A group edited while a task is in flight does
        // not change that task; re-tasking is the only way to pick a change
        // up.
        //
        // Still one pass over the whole membership, which is not incidental.
        // GH-707 was this exact code querying snapinAssoc once per member
        // host inside the loop below -- a thousand round trips for a
        // thousand-host group -- and a resolver taking one host at a time
        // would have reintroduced it here first. That is why resolveSnapins()
        // takes a set.
        $assocByHost = [];
        if ($snapin == -1) {
            $assocByHost = Resolver::resolveSnapins($hostIDs);
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
            ) = (new SnapinJobManager())
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
                // such a row can never be shown, run or canceled; it just
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
                (new SnapinTaskManager())
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
            (new HostManager())
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
        $test = (new HostManager())
            ->distinct(
                'imageID',
                ['id' => $this->get('hosts')]
            );

        return $test == 1;
    }
    /**
     * Loads hosts in this group.
     *
     * A group with no id has no associations to load, and asking anyway is
     * two pointless queries whose filters are both the empty-value shape
     * that means "no filter" somewhere else in this codebase: `gmGroupID =
     * ''` against groupMembers, then a COUNT of hosts filtered by an empty
     * id list. Neither is currently wrong, and neither is worth relying on.
     *
     * Not a hypothetical. HostManagement::deployMulti() drives a Group that
     * is deliberately never saved -- it is the carrier for an ad-hoc host
     * selection, so that one multicast session covers the whole selection
     * the way it does for a real group -- and set('hosts', ...) reaches
     * here before the ids it is about to write land.
     *
     * @return void
     */
    protected function loadHosts()
    {
        if (!$this->get('id')) {
            $this->set('hosts', []);

            return;
        }
        $this->_loadHostIds(
            'groupassociation',
            ['groupID' => $this->get('id')],
            'hostID'
        );
        $this->getHostCount();
    }
}
