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
        'storageGroups',
        'storageGroupsnotinme',
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
            $DBHostIDs = self::getSubObjectIDs(
                'Host',
                array('imageID' => $this->get('id'))
            );
            $RemoveHostIDs = array_diff(
                (array)$DBHostIDs,
                (array)$this->get('hosts')
            );
            if (count($RemoveHostIDs) > 0) {
                self::getClass('HostManager')
                    ->update(
                        array(
                            'imageID' => $this->get('id'),
                            'id' => $RemoveHostIDs
                        ),
                        '',
                        array('imageID' => 0)
                    );
                $DBHostIDs = self::getSubObjectIDs(
                    'Host',
                    array('imageID' => $this->get('id'))
                );
                unset($RemoveHostIDs);
            }
            $DBHostIDs = array_diff(
                (array)$this->get('hosts'),
                (array)$DBHostIDs
            );
            self::getClass('HostManager')
                ->update(
                    array('id' => $DBHostIDs),
                    '',
                    array('imageID' => $this->get('id'))
                );
            unset($DBHostIDs);
        }
        if ($this->isLoaded('storageGroups')) {
            $DBGroupIDs = self::getSubObjectIDs(
                'ImageAssociation',
                array('imageID' => $this->get('id')),
                'storageGroupID'
            );
            $RemoveGroupIDs = array_diff(
                (array)$DBGroupIDs,
                (array)$this->get('storageGroups')
            );
            if (count($RemoveGroupIDs) > 0) {
                self::getClass('ImageAssociationManager')
                    ->destroy(
                        array(
                            'imageID' => $this->get('id'),
                            'storageGroupID' => $RemoveGroupIDs
                        )
                    );
                unset($RemoveGroupIDs);
                $DBGroupIDs = self::getSubObjectIDs(
                    'ImageAssociation',
                    array('imageID' => $this->get('id')),
                    'storageGroupID'
                );
            }
            $primaryGroupIDs = self::getSubObjectIDs(
                'ImageAssociation',
                array(
                    'imageID' => $this->get('id'),
                    'primary' => 1
                ),
                'storageGroupID'
            );
            $insert_fields = array('imageID', 'storageGroupID', 'primary');
            $insert_values = array();
            $DBGroupIDs = array_diff(
                (array)$this->get('storageGroups'),
                (array)$DBGroupIDs
            );
            foreach ((array)$DBGroupIDs as &$groupID) {
                $insert_values[] = array(
                    $this->get('id'),
                    $groupID,
                    in_array($groupID, $primaryGroupIDs) ? 1 : 0
                );
                unset($groupID);
            }
            unset($DBGroupIDs);
            if (count($insert_values) > 0) {
                self::getClass('ImageAssociationManager')
                    ->insertBatch(
                        $insert_fields,
                        $insert_values
                    );
            }
        }
        return $this;
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
        $StorageNodes = self::getClass('StorageNodeManager')
            ->find(
                array(
                    'storageGroupID' => $this->get('storageGroups'),
                    'isEnabled' => 1
                )
            );
        foreach ((array)$StorageNodes as &$StorageNode) {
            if (!$StorageNode->isValid()) {
                continue;
            }
            $ftppath = $StorageNode->get('ftppath');
            $ftppath = trim($ftppath, '/');
            $deleteFile = sprintf(
                '/%s/%s',
                $deleteFile,
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
                ->delete($delete)
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
    protected function loadStorageGroups()
    {
        $groupids = self::getSubObjectIDs(
            'ImageAssociation',
            array('imageID' => $this->get('id')),
            'storageGroupID'
        );
        $groupids = self::getSubObjectIDs(
            'StorageGroup',
            array('id' => $groupids)
        );
        $this->set('storageGroups', $groupids);
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
            'storageGroups',
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
            'storageGroups',
            (array)$removeArray,
            'diff'
        );
    }
    /**
     * Loads groups not with this image
     *
     * @return void
     */
    protected function loadStorageGroupsnotinme()
    {
        $find = array('id' => $this->get('storageGroups'));
        $groupids = self::getSubObjectIDs(
            'StorageGroup',
            $find,
            'id',
            true
        );
        $this->set('storageGroupsnotinme', $groupids);
    }
    /**
     * Gets the storage group
     *
     * @throws Exception
     * @return object
     */
    public function getStorageGroup()
    {
        $groupids = $this->get('storageGroups');
        $count = count($groupids);
        if ($count < 1) {
            $groupids = self::getSubObjectIDs('StorageGroup');
            $groupids = @min($groupids);
            if ($groupids < 1) {
                throw new Exception(_('No viable storage groups found'));
            }
            $this->set('storageGroups', (array)$groupids);
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
            $primaryGroup = @min((array)$groupids);
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
        return new OS($this->get('osID'));
    }
    /**
     * Returns the ImageType object
     *
     * @return object
     */
    public function getImageType()
    {
        return new ImageType($this->get('imageTypeID'));
    }
    /**
     * Returns the ImagePartitionType object
     *
     * @return object
     */
    public function getImagePartitionType()
    {
        $iptID = $this->get('imagePartitionTypeID');
        if ($iptID < 1) {
            $iptID = 1;
        }
        return new ImagePartitionType($iptID);
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
     * Gets the image's primar group
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
                'storageGroupID' => $groupID,
                'imageID' => $this->get('id')
            )
        );
        $assocID = @min($assocID);
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
        self::getClass('ImageAssociationManager')
            ->update(
                array(
                    'imageID' => $this->get('id'),
                    'storageGroupID' => array_diff(
                        (array)$this->get('storageGroups'),
                        (array)$groupID
                    )
                ),
                '',
                array('primary' => 0)
            );
        self::getClass('ImageAssociationManager')
            ->update(
                array(
                    'imageID' => $this->get('id'),
                    'storageGroupID' => $groupID
                ),
                '',
                array('primary' => 1)
            );
    }
}
