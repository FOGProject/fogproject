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
    protected $databaseFields = array(
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
        'srvsize' => 'imageServerSize',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'path',
        'imageTypeID',
        'osID',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
        'storagegroups',
        'storagegroupsnotinme',
        'os',
        'imagepartitiontype',
        'imagetype',
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'OS' => array(
            'id',
            'osID',
            'os'
        ),
        'ImagePartitionType' => array(
            'id',
            'imagePartitionTypeID',
            'imagepartitiontype'
        ),
        'ImageType' => array(
            'id',
            'imageTypeID',
            'imagetype'
        )
    );
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
        $find = array('imageID' => $this->get('id'));
        self::getClass('HostManager')
            ->update(
                $find,
                '',
                array('imageID' => 0)
            );
        self::getClass('ImageAssociationManager')
            ->destroy($find);
        self::$HookManager
            ->processEvent(
                'DESTROY_IMAGE',
                array(
                    'Image' => &$this
                )
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
            if (count($this->get('hosts')) > 0) {
                $DBIDs = self::getSubObjectIDs(
                    'Host',
                    array('imageID' => $this->get('id'))
                );
            } else {
                $RemIDs = self::getSubObjectIDs(
                    'Host',
                    array('imageID' => $this->get('id'))
                );
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
                self::getClass('HostManager')
                    ->update(
                        array(
                            'imageID' => $this->get('id'),
                            'id' => $RemIDs
                        ),
                        '',
                        array('imageID' => 0)
                    );
                unset($RemIDs);
            }
            if (count($this->get('hosts')) < 1) {
                return $this;
            }
            self::getClass('HostManager')
                ->update(
                    array('id' => $this->get('hosts')),
                    '',
                    array('imageID' => $this->get('id'))
                );
        }
        $primary = self::getSubObjectIDs(
            'ImageAssociation',
            array(
                'imageID' => $this->get('id'),
                'primary' => 1
            ),
            'storagegroupID'
        );
        $this->assocSetter('Image', 'storagegroup');
        if (count($primary) > 0) {
            $primary = array_shift($primary);
            $this->setPrimaryGroup($primary);
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
        foreach ((array)self::getClass('StorageNodeManager')
            ->find(
                array(
                    'storagegroupID' => $this->get('storagegroups'),
                    'isEnabled' => 1
                )
            ) as &$StorageNode
        ) {
            $ftppath = $StorageNode->get('ftppath');
            $ftppath = trim($ftppath, '/');
            $deleteFile = sprintf(
                '/%s/%s',
                $ftppath,
                $this->get('path')
            );
            $ip = $StorageNode->get('ip');
            $user = $StorageNode->get('user');
            $pass = $StorageNode->get('pass');
            self::$FOGFTP
                ->set('host', $ip)
                ->set('username', $user)
                ->set('password', $pass);
            if (!self::$FOGFTP->connect()) {
                continue;
            }
            self::$FOGFTP
                ->delete($deleteFile)
                ->close();
            unset($StorageNode);
        }
    }
    /**
     * Loads hosts
     *
     * @return void
     */
    protected function loadHosts()
    {
        $hostids = self::getSubObjectIDs(
            'Host',
            array('imageID' => $this->get('id'))
        );
        $this->set('hosts', $hostids);
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
     * Loads items not with this object
     *
     * @return void
     */
    protected function loadHostsnotinme()
    {
        $find = array('id'=>$this->get('hosts'));
        $hostids = self::getSubObjectIDs(
            'Host',
            $find,
            'id',
            true
        );
        $this->set('hostsnotinme', $hostids);
    }
    /**
     * Loads storage groups with this object
     *
     * @return void
     */
    protected function loadStoragegroups()
    {
        $groupids = self::getSubObjectIDs(
            'ImageAssociation',
            array('imageID' => $this->get('id')),
            'storagegroupID'
        );
        $groupids = self::getSubObjectIDs(
            'StorageGroup',
            array('id' => $groupids)
        );
        $groupids = array_filter($groupids);
        if (count($groupids) < 1) {
            $groupids = self::getSubObjectIDs('StorageGroup');
            $groupids = @min($groupids);
        }
        $this->set('storagegroups', $groupids);
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
     * Loads groups not with this image
     *
     * @return void
     */
    protected function loadStoragegroupsnotinme()
    {
        $find = array('id' => $this->get('storagegroups'));
        $groupids = self::getSubObjectIDs(
            'StorageGroup',
            $find,
            'id',
            true
        );
        $this->set('storagegroupsnotinme', $groupids);
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
            $groupids = self::getSubObjectIDs('StorageGroup');
            $groupids = @min($groupids);
            if ($groupids < 1) {
                throw new Exception(_('No viable storage groups found'));
            }
        }
        $primaryGroup = array();
        foreach ((array)$groupids as &$groupid) {
            if (!$this->getPrimaryGroup($groupid)) {
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
     *
     * @return bool
     */
    public function getPrimaryGroup($groupID)
    {
        $primaryCount = self::getClass('ImageAssociationManager')
            ->count(
                array(
                    'imageID' => $this->get('id'),
                    'primary' => 1
                )
            );
        if ($primaryCount < 1) {
            $primaryCount = self::getClass('ImageAssociationManager')
                ->count(
                    array('imageID' => $this->get('id'))
                );
        }
        if ($primaryCount < 1) {
            $groupid = self::getSubObjectIDs('StorageGroup');
            $groupid = @min($groupid);
            $this->setPrimaryGroup($groupid);
        }
        $assocID = self::getSubObjectIDs(
            'ImageAssociation',
            array(
                'storagegroupID' => $groupID,
                'imageID' => $this->get('id')
            )
        );
        $assocID = @min((array) $assocID);

        return self::getClass('ImageAssociation', $assocID)->isPrimary();
    }
    /**
     * Sets the primary group for the image
     *
     * @param int $groupID the id to set as primary
     *
     * @return array
     */
    public function setPrimaryGroup($groupID)
    {
        $exists = self::getSubObjectIDs(
            'ImageAssociation',
            array(
                'imageID' => $this->get('id'),
                'storagegroupID' => $groupID
            ),
            'storagegroupID'
        );
        if (count($exists) < 1) {
            self::getClass('ImageAssociation')
                ->set('imageID', $this->get('id'))
                ->set('storagegroupID', $groupID)
                ->save();
        }
        /**
         * Unset all current groups to non-primary
         */
        self::getClass('ImageAssociationManager')
            ->update(
                array(
                    'imageID' => $this->get('id'),
                    'storagegroupID' => $this->get('storagegroups')
                ),
                '',
                array('primary' => 0)
            );
        /**
         * Set the passed group as primary
         */
        self::getClass('ImageAssociationManager')
            ->update(
                array(
                    'imageID' => $this->get('id'),
                    'storagegroupID' => $groupID
                ),
                '',
                array('primary' => 1)
            );
    }
}
