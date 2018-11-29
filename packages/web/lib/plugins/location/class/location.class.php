<?php
/**
 * The location class.
 *
 * PHP version 5
 *
 * @category Location
 * @package  FOGProject
 * @author   Lee Rowlett <nope@nope.nope>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The location class.
 *
 * @category Location
 * @package  FOGProject
 * @author   Lee Rowlett <nope@nope.nope>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Location extends FOGController
{
    /**
     * The location table
     *
     * @var string
     */
    protected $databaseTable = 'location';
    /**
     * The location table fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'lID',
        'name' => 'lName',
        'description' => 'lDesc',
        'createdBy' => 'lCreatedBy',
        'createdTime' => 'lCreatedTime',
        'storagegroupID' => 'lStorageGroupID',
        'storagenodeID' => 'lStorageNodeID',
        'tftp' => 'lTftpEnabled',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'storagegroupID',
    );
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
        'storagenode',
        'storagegroup'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'StorageGroup' => array(
            'id',
            'storagegroupID',
            'storagegroup'
        ),
        'StorageNode' => array(
            'id',
            'storagenodeID',
            'storagenode'
        )
    );
    /**
     * Destroy this particular object.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        self::getClass('LocationAssociationManager')
            ->destroy(
                array(
                    'locationID' => $this->get('id')
                )
            );
        return parent::destroy($key);
    }
    /**
     * Stores the item in the DB either stored or updated.
     *
     * @return object
     */
    public function save()
    {
        parent::save();
        return $this
            ->assocSetter('Location', 'host')
            ->load();
    }
    /**
     * Add host to the location.
     *
     * @param array $addArray the items to add.
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
     * Remove host from the location.
     *
     * @param array $removeArray the items to remove.
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
     * Get the current location group.
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return $this->get('storagegroup');
    }
    /**
     * Get the storage node.
     *
     * @return object
     */
    public function getStorageNode()
    {
        if ($this->get('storagenodeID')) {
            return $this->get('storagenode');
        }
        return $this->getStorageGroup()->getOptimalStorageNode();
    }
    /**
     * Loads the locations hosts.
     *
     * @return void
     */
    protected function loadHosts()
    {
        $hostIDs = self::getSubObjectIDs(
            'LocationAssociation',
            array('locationID' => $this->get('id')),
            'hostID'
        );
        $hostIDs = self::getSubObjectIDs(
            'Host',
            array('id' => $hostIDs)
        );
        $this->set(
            'hosts',
            (array)$hostIDs
        );
    }
    /**
     * Load the hosts not with this hosts in me.
     *
     * @return void
     */
    protected function loadHostsnotinme()
    {
        $hosts = array_diff(
            self::getSubObjectIDs('Host'),
            $this->get('hosts')
        );
        $this->set('hostsnotinme', (array)$hosts);
    }
}
