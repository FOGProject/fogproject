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
        Route::ids(
            'snapintask',
            $find,
            'jobID'
        );
        $snapinJobIDs = json_decode(Route::getData(), true);
        Route::deletemass(
            'snapintask',
            $find
        );
        Route::ids(
            'snapinjob',
            [
                'id' => $snapinJobIDs,
                'stateID' => self::fastmerge(
                    self::getQueuedStates(),
                    (array)self::getProgressState()
                )
            ]
        );
        $snapinJobIDs = json_decode(Route::getData(), true);
        $sjIDs = [];
        foreach ((array)$snapinJobIDs as &$sjID) {
            Route::count(
                'snapintask',
                ['jobID' => $sjID]
            );
            $jobCount = json_decode(Route::getData());
            $jobCount = $jobCount->total;
            if ($jobCount > 0) {
                continue;
            }
            $sjIDs[] = $sjID;
        }
        if (count($sjIDs ?: [])) {
            self::getClass('SnapinJobManager')->cancel($sjIDs);
        }
        Route::deletemass(
            'snapingroupassociation',
            $find
        );
        Route::deletemass(
            'snapinassociation',
            $find
        );

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

        Route::ids(
            'snapingroupassociation',
            [
                'snapinID' => $this->get('id'),
                'primary' => 1
            ],
            'storagegroupID'
        );
        $primary = json_decode(Route::getData(), true);
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
                'storagegroupID' => $this->get('storagegroups'),
                'isEnabled' => 1
            ]
        );
        $StorageNodes = json_decode(
            Route::getData()
        );
        foreach ($StorageNodes->data as $StorageNode) {
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
            self::$FOGSSH->username = $user;
            self::$FOGSSH->password = $pass;
            self::$FOGSSH->host = $ip;
            if (!self::$FOGSSH->connect()) {
                error_log(_('Unable to login via SSH'));
                continue;
            }
            if (!self::$FOGSSH->delete($deleteFile)) {
                error_log(_('Unable to delete remote file').': '.$deleteFile);
                continue;
            }
            self::$FOGSSH->disconnect();
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
        $find = ['snapinID' => $this->get('id')];
        Route::ids(
            'snapinassociation',
            $find,
            'hostID'
        );
        $hosts = json_decode(Route::getData(), true);
        $this->set('hosts', (array)$hosts);
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
        $find = ['snapinID' => $this->get('id')];
        Route::ids(
            'snapingroupassociation',
            $find,
            'storagegroupID'
        );
        $groups = json_decode(Route::getData(), true);
        if (count($groups ?: []) < 1) {
            Route::ids('storagegroup', false);
            $groups = json_decode(Route::getData(), true);
            $groups = [@min($groups)];
        }
        $this->set('storagegroups', $groups);
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
            Route::ids('storagegroup', false);
            $groupids = json_decode(Route::getData(), true);
            $groupids = [@min($groupids)];
            if (count($groupids) < 1) {
                throw new Exception(_('No viable storage groups found'));
            }
        }
        $primaryGroup = [];
        foreach ($groupids as &$groupid) {
            if (!self::getPrimaryGroup($groupid, $this->get('id'))) {
                continue;
            }
            $primaryGroup[] = $groupid;
            unset($groupid);
        }
        if (count($primaryGroup) < 1) {
            $primaryGroup = @min($groupids);
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
        $find = [
            'snapinID' => $snapinID,
            'primary' => 1
        ];
        Route::count(
            'snapingroupassociation',
            $find
        );
        $primaryCount = json_decode(Route::getData());
        $primaryCount = $primaryCount->total;
        if ($primaryCount < 1) {
            unset($find['primary']);
            Route::count(
                'snapingroupassociation',
                $find
            );
            $primaryCount = json_decode(Route::getData());
            $primaryCount = $primaryCount->total;
        }
        if ($primaryCount < 1) {
            Route::ids('storagegroup', false);
            $groupid = json_decode(Route::getData(), true);
            $groupid = @min($groupid);
            self::setPrimaryGroup($groupid, $snapinID);
        }
        $find = [
            'storagegroupID' => $groupID,
            'snapinID' => $snapinID
        ];
        Route::ids(
            'snapingroupassociation',
            $find
        );
        $assocID = json_decode(Route::getData(), true);
        $assocID = @min($assocID);

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
        $find = [
            'storagegroupID' => $groupID,
            'snapinID' => $snapinID
        ];
        Route::ids(
            'snapingroupassociation',
            $find,
            'storagegroupID'
        );
        $exists = json_decode(Route::getData(), true);
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
