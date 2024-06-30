<?php
/**
 * The image object
 *
 * PHP version 5
 *
 * @category Image
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The image object
 *
 * @category Image
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Image extends FOGController
{
    /**
     * The image table
     *
     * @var string
     */
    protected $databaseTable = 'images';
    /**
     * The Image table fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'imageID',
        'name' => 'imageName',
        'description' => 'imageDesc',
        'path' => 'imagePath',
        'createdTime' => 'imageDateTime',
        'createdBy' => 'imageCreateBy',
        'building' => 'imageBuilding',
        'size' => 'imageSize',
        'imageTypeID' => 'imageTypeID',
        'imagePartitionTypeID' => 'imagePartitionTypeID',
        'osID' => 'imageOSID',
        'size' => 'imageSize',
        'deployed' => 'imageLastDeploy',
        'format' => 'imageFormat',
        'magnet' => 'imageMagnetUri',
        'protected' => 'imageProtect',
        'compress' => 'imageCompress',
        'isEnabled' => 'imageEnabled',
        'toReplicate' => 'imageReplicate',
        'srvsize' => 'imageServerSize'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'path',
        'imageTypeID',
        'osID'
    ];
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = [
        'hosts',
        'storagegroups',
        'os',
        'imagepartitiontype',
        'imagetype'
    ];
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = [
        'OS' => [
            'id',
            'osID',
            'os'
        ],
        'ImagePartitionType' => [
            'id',
            'imagePartitionTypeID',
            'imagepartitiontype'
        ],
        'ImageType' => [
            'id',
            'imageTypeID',
            'imagetype'
        ]
    ];
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
        $find = ['imageID' => $this->get('id')];
        self::getClass('HostManager')->update(
            $find,
            '',
            ['imageID' => 0]
        );
        Route::deletemass(
            'imageassociation',
            $find
        );
        self::$HookManager->processEvent(
            'DESTROY_IMAGE',
            ['Image' => &$this]
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
        if ($this->isLoaded('hosts')) {
            Route::ids(
                'host',
                ['imageID' => $this->get('id')]
            );
            $ids = json_decode(Route::getData(), true);
            if (count($this->get('hosts')) > 0) {
                $DBIDs = $ids;
            } else {
                $RemIDs = $ids;
            }
            if (!isset($RemIDs)) {
                $DBCount = count($DBIDs);
                $tokeep = count($this->get('hosts'));
                if ($DBCount > 0
                    && $DBCount != $tokeep
                ) {
                    $RemIDs = array_diff(
                        (array)$DBIDs,
                        (array)$this->get('hosts')
                    );
                }
            }
            $RemIDs = array_filter($RemIDs);
            if (count($RemIDs) > 0) {
                self::getClass('HostManager')->update(
                    [
                        'imageID' => $this->get('id'),
                        'id' => $RemIDs
                    ],
                    '',
                    ['imageID' => 0]
                );
                unset($RemIDs);
            }
            if (count($this->get('hosts')) < 1) {
                return $this;
            }
            self::getClass('HostManager')
                ->update(
                    ['id' => $this->get('hosts')],
                    '',
                    ['imageID' => $this->get('id')]
                );
        }
        $find = [
            'imageID' => $this->get('id'),
            'primary' => 1
        ];
        Route::ids(
            'imageassociation',
            $find,
            'storagegroupID'
        );
        $primary = json_decode(Route::getData(), true);
        $this->assocSetter('Image', 'storagegroup');
        if (count($primary) > 0) {
            $primary = array_shift($primary);
            self::setPrimaryGroup($primary, $this->get('id'));
        }
        return $this->load();
    }
    /**
     * Deletes the image file
     *
     * @return bool
     */
    public function deleteFile()
    {
        if ($this->get('protected')) {
            throw new Exception(self::$foglang['ProtectedImage']);
        }
        foreach ($this->get('storagegroups') as $storagegroupID) {
            self::getClass('filedeletequeue')
                ->set('path', $this->get('path'))
                ->set('pathtype', 'Image')
                ->set('createdTime', self::formatTime('now', 'Y-m-d H:i:s'))
                ->set('stateID', self::getQueuedState())
                ->set('createdBy', self::$FOGUser->get('name'))
                ->set('storagegroupID', $storagegroupID)
                ->save();
        }
        return true;
    }
    /**
     * Loads hosts
     *
     * @return void
     */
    protected function loadHosts()
    {
        $find = ['imageID' => $this->get('id')];
        Route::ids(
            'host',
            $find
        );
        $hosts = json_decode(Route::getData(), true);
        $this->set('hosts', (array)$hosts);
    }
    /**
     * Add hosts to image object
     *
     * @param array $addArray the items to add
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
     * Remove hosts from image object
     *
     * @param array $removeArray the items to remove
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
     * Loads storage groups with this object
     *
     * @return void
     */
    protected function loadStoragegroups()
    {
        $find = ['imageID' => $this->get('id')];
        Route::ids(
            'imageassociation',
            $find,
            'storagegroupID'
        );
        $groups = json_decode(Route::getData(), true);
        if (count($groups) < 1) {
            Route::ids('storagegroup', false);
            $groups = json_decode(Route::getData(), true);
            $groups = [@min($groups)];
        }
        $this->set('storagegroups', $groups);
    }
    /**
     * Adds groups to this object
     *
     * @param array $addArray the items to add
     *
     * @return object
     */
    public function addGroup($addArray)
    {
        return $this->addRemItem(
            'storagegroups',
            (array)$addArray,
            'merge'
        );
    }
    /**
     * Removes groups from this object
     *
     * @param array $removeArray the items to remove
     *
     * @return object
     */
    public function removeGroup($removeArray)
    {
        return $this->addRemItem(
            'storagegroups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Gets the storage group
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
     * Returns the OS object
     *
     * @return object
     */
    public function getOS()
    {
        return $this->get('os');
    }
    /**
     * Returns the ImageType object
     *
     * @return object
     */
    public function getImageType()
    {
        return $this->get('imagetype');
    }
    /**
     * Returns the ImagePartitionType object
     *
     * @return object
     */
    public function getImagePartitionType()
    {
        return $this->get('imagepartitiontype');
    }
    /**
     * Returns the partition type
     *
     * @return string
     */
    public function getPartitionType()
    {
        return $this->getImagePartitionType()->get('type');
    }
    /**
     * Gets the image's primary group
     *
     * @param int $groupID the group id to check
     * @param int $imageID the image id to check
     *
     * @return bool
     */
    public static function getPrimaryGroup($groupID, $imageID)
    {
        if (!$imageID) {
            return true;
        }
        $find = [
            'imageID' => $imageID,
            'primary' => 1
        ];
        Route::count(
            'imageassociation',
            $find
        );
        $primaryCount = json_decode(Route::getData());
        $primaryCount = $primaryCount->total;
        if ($primaryCount < 1) {
            unset($find['primary']);
            Route::count(
                'imageassociation',
                $find
            );
            $primaryCount = json_decode(Route::getData());
            $primaryCount = $primaryCount->total;
        }
        if ($primaryCount < 1) {
            Route::indiv('image', $imageID);
            $image = json_decode(Route::getData());
            Route::ids(
                'storagegroup',
                ['id' => $image->storagegroups]
            );
            $groupid = json_decode(Route::getData(), true);
            $groupid = @min($groupid);
            self::setPrimaryGroup($groupid, $imageID);
        }
        $find = [
            'storagegroupID' => $groupID,
            'imageID' => $imageID
        ];
        Route::ids(
            'imageassociation',
            $find
        );
        $assocID = json_decode(Route::getData(), true);
        $assocID = @min($assocID);

        return self::getClass('ImageAssociation', $assocID)->isPrimary();
    }
    /**
     * Gets the primary storage group.
     *
     * @return object
     */
    public function getPrimaryStorageGroup()
    {
        Route::ids(
            'imageassociation',
            [
                'imageID' => $this->get('id'),
                'storagegroupID' => $this->get('storagegroups'),
                'primary' => [1],
            ],
            'storagegroupID'
        );
        $groupids = json_decode(Route::getData(), true);
        if (count($groupids ?: []) < 1) {
            $groupid = @min($this->get('storagegroups'));
        } else {
            $groupid = @min($groupids);
        }
        return new StorageGroup($groupid);
    }
    /**
     * Sets the primary group for the image
     *
     * @param int $groupID the id to set as primary
     * @param int $imageID the id to use with primary group
     *
     * @return array
     */
    public static function setPrimaryGroup($groupID, $imageID)
    {
        $find = [
            'storagegroupID' => $groupID,
            'imageID' => $imageID
        ];
        Route::ids(
            'imageassociation',
            $find,
            'storagegroupID'
        );
        $exists = json_decode(Route::getData(), true);
        if (count($exists) < 1) {
            self::getClass('ImageAssociation')
                ->set('imageID', $imageID)
                ->set('storagegroupID', $groupID)
                ->save();
        }
        /**
         * Unset all current groups to non-primary
         */
        self::getClass('ImageAssociationManager')->update(
            ['imageID' => $imageID],
            '',
            ['primary' => 0]
        );
        /**
         * Set the passed group as primary
         */
        self::getClass('ImageAssociationManager')->update(
            [
                'imageID' => $imageID,
                'storagegroupID' => $groupID
            ],
            '',
            ['primary' => 1]
        );
    }
}
