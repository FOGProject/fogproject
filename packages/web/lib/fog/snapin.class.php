<?php
/**
 * The snapin object.
 *
 * PHP version 5
 *
 * @category Snapin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The snapin object.
 *
 * @category Snapin
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Snapin extends FOGController
{
    /**
     * The snapin table.
     *
     * @var string
     */
    protected $databaseTable = 'snapins';
    /**
     * The snapin table fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sID',
        'name' => 'sName',
        'description' => 'sDesc',
        'file' => 'sFilePath',
        'args' => 'sArgs',
        'createdTime' => 'sCreateDate',
        'createdBy' => 'sCreator',
        'reboot' => 'sReboot',
        'shutdown' => 'sShutdown',
        'runWith' => 'sRunWith',
        'runWithArgs' => 'sRunWithArgs',
        'protected' => 'snapinProtect',
        'isEnabled' => 'sEnabled',
        'toReplicate' => 'sReplicate',
        'hide' => 'sHideLog',
        'timeout' => 'sTimeout',
        'packtype' => 'sPackType',
        'hash' => 'sHash',
        'size' => 'sSize',
        'anon3' => 'sAnon3'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'file'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'hosts',
        'storagegroups',
        'path'
    ];
    /**
     * Removes the item from the database.
     *
     * @param string $key the key to remove
     *
     * @throws Exception
     *
     * @return object
     */
    public function destroy($key = 'id')
    {
        $find = ['snapinID' => $this->get('id')];
        $snapinJobIDs = self::getSubObjectIDs(
            'SnapinTask',
            $find,
            'jobID'
        );
        self::getClass('SnapinTaskManager')
            ->destroy($find);
        $snapinJobIDs = self::getSubObjectIDs(
            'SnapinJob',
            [
                'id' => $snapinJobIDs,
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                ),
            ]
        );
        foreach ((array)$snapinJobIDs as &$sjID) {
            $jobCount = self::getClass('SnapinTaskManager')
                ->count(['jobID' => $sjID]);
            if ($jobCount > 0) {
                continue;
            }
            $sjIDs[] = $sjID;
        }
        if (count($sjIDs) > 0) {
            self::getClass('SnapinJobManager')
                ->cancel($sjID);
        }
        self::getClass('SnapinGroupAssociationManager')
            ->destroy($find);
        self::getClass('SnapinAssociationManager')
            ->destroy($find);

        return parent::destroy($key);
    }
    /**
     * Stores data into the database.
     *
     * @return bool|object
     */
    public function save()
    {
        parent::save();

        $primary = self::getSubObjectIDs(
            'SnapinGroupAssociation',
            [
                'snapinID' => $this->get('id'),
                'primary' => 1
            ],
            'storagegroupID'
        );
        $this
            ->assocSetter('Snapin', 'host')
            ->assocSetter('SnapinGroup', 'storagegroup');
        if (count($primary) > 0) {
            $primary = array_shift($primary);
            self::setPrimaryGroup($primary, $this->get('id'));
        }
        return $this->load();
    }
    /**
     * Deletes the snapin file.
     *
     * @return bool
     */
    public function deleteFile()
    {
        if ($this->get('protected')) {
            throw new Exception(self::$foglang['ProtectedSnapin']);
        }
        Route::listem(
            'storagenode',
            [
                'ngmGroupID' => $this->get('storagegroups'),
                'ngmIsEnabled' => 1
            ]
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        foreach ($StorageNodes->data as &$StorageNode) {
            $ftppath = trim(
                $StorageNode->snapinpath,
                '/'
            );
            $deleteFile = sprintf(
                '/%s/%s',
                $ftppath,
                $this->get('file')
            );
            $ip = $StorageNode->ip;
            $user = $StorageNode->user;
            $pass = $StorageNode->pass;
            self::$FOGFTP->username = $user;
            self::$FOGFTP->password = $pass;
            self::$FOGFTP->host = $ip;
            if (!self::$FOGFTP->connect()) {
                continue;
            }
            if (!self::$FOGFTP->delete($deleteFile)) {
                continue;
            }
            self::$FOGFTP->close();
            unset($StorageNode);
        }
        return true;
    }
    /**
     * Loads hosts.
     *
     * @return void
     */
    protected function loadHosts()
    {
        $hostids = self::getSubObjectIDs(
            'SnapinAssociation',
            ['snapinID' => $this->get('id')],
            'hostID'
        );
        $hostids = self::getSubObjectIDs(
            'Host',
            ['id' => $hostids]
        );
        $this->set('hosts', $hostids);
    }
    /**
     * Add hosts to snapin object.
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addHost($addArray)
    {
        return $this->addRemItem(
            'hosts',
            (array) $addArray,
            'merge'
        );
    }
    /**
     * Remove hosts from snapin object.
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeHost($removeArray)
    {
        return $this->addRemItem(
            'hosts',
            (array) $removeArray,
            'diff'
        );
    }
    /**
     * Loads storage groups with this object.
     *
     * @return void
     */
    protected function loadStoragegroups()
    {
        $groupids = self::getSubObjectIDs(
            'SnapinGroupAssociation',
            ['snapinID' => $this->get('id')],
            'storagegroupID'
        );
        $groupids = self::getSubObjectIDs(
            'StorageGroup',
            ['id' => $groupids]
        );
        $groupids = array_filter($groupids);
        if (count($groupids) < 1) {
            $groupids = self::getSubObjectIDs('StorageGroup');
            $groupids = @min($groupids);
        }
        $this->set('storagegroups', $groupids);
    }
    /**
     * Adds groups to this object.
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addGroup($addArray)
    {
        return $this->addRemItem(
            'storagegroups',
            (array) $addArray,
            'merge'
        );
    }
    /**
     * Removes groups from this object.
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeGroup($removeArray)
    {
        return $this->addRemItem(
            'storagegroups',
            (array) $removeArray,
            'diff'
        );
    }
    /**
     * Gets the storage group.
     *
     * @throws Exception
     * @return object
     */
    public function getStorageGroup()
    {
        $groupids = $this->get('storagegroups');
        $count = count($groupids);
        if ($count < 1) {
            $groupids = self::getSubObjectIDs('StorageGroup');
            $groupids = @min($groupids);
            if ($groupids < 1) {
                throw new Exception(_('No viable storage groups found'));
            }
        }
        $primaryGroup = [];
        foreach ((array) $groupids as &$groupid) {
            if (!self::getPrimaryGroup($groupid, $this->get('id'))) {
                continue;
            }
            $primaryGroup[] = $groupid;
            unset($groupid);
        }
        if (count($primaryGroup) < 1) {
            $primaryGroup = @min((array) $groupids);
        } else {
            $primaryGroup = array_shift($primaryGroup);
        }

        return new StorageGroup($primaryGroup);
    }
    /**
     * Gets the snapin's primary group.
     *
     * @param int $groupID  the group id to check
     * @param int $snapinID the snapin id to check
     *
     * @return bool
     */
    public static function getPrimaryGroup($groupID, $snapinID)
    {
        $primaryCount = self::getClass('SnapinGroupAssociationManager')
            ->count(
                [
                    'snapinID' => $snapinID,
                    'primary' => 1,
                ]
            );
        if ($primaryCount < 1) {
            $primaryCount = self::getClass('SnapinGroupAssociationManager')
                ->count(['snapinID' => $snapinID]);
        }
        if ($primaryCount < 1) {
            $groupid = self::getSubObjectIDs('StorageGroup');
            $groupid = @min($groupid);
            self::setPrimaryGroup($groupid, $snapinID);
        }
        $assocID = self::getSubObjectIDs(
            'SnapinGroupAssociation',
            [
                'storagegroupID' => $groupID,
                'snapinID' => $snapinID,
            ]
        );
        $assocID = @min((array) $assocID);

        return self::getClass('SnapinGroupAssociation', $assocID)->isPrimary();
    }
    /**
     * Sets the primary group for the snapin.
     *
     * @param int $groupID  the id to set as primary
     * @param int $snapinID the id to use with primary group
     *
     * @return array
     */
    public static function setPrimaryGroup($groupID, $snapinID)
    {
        $exists = self::getSubObjectIDs(
            'SnapinGroupAssociation',
            [
                'snapinID' => $snapinID,
                'storagegroupID' => $groupID
            ],
            'storagegroupID'
        );
        if (count($exists) < 1) {
            self::getClass('SnapinGroupAssociation')
                ->set('snapinID', $snapinID)
                ->set('storagegroupID', $groupID)
                ->save();
        }
        /**
         * Unset all current groups to non-primary
         */
        self::getClass('SnapinGroupAssociationManager')->update(
            ['snapinID' => $snapinID],
            '',
            ['primary' => 0]
        );
        /**
         * Set the passed group as primary
         */
        self::getClass('SnapinGroupAssociationManager')->update(
            [
                'snapinID' => $snapinID,
                'storagegroupID' => $groupID,
            ],
            '',
            ['primary' => 1]
        );
    }
    /**
     * Loads the Path as the file for commonality
     * in some methods.
     *
     * @return void
     */
    protected function loadPath()
    {
        $this->set('path', $this->get('file'));

        return $this;
    }
}
