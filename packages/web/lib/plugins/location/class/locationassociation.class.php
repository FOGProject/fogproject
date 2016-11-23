<?php
/**
 * The association between hosts and locations.
 *
 * PHP version 5
 *
 * @category LocationAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The association between hosts and locations.
 *
 * @category LocationAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class LocationAssociation extends FOGController
{
    /**
     * The association table.
     *
     * @var string
     */
    protected $databaseTable = 'locationAssoc';
    /**
     * The association fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'laID',
        'locationID' => 'laLocationID',
        'hostID' => 'laHostID',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'locationID',
        'hostID',
    );
    /**
     * Return the associated location.
     *
     * @return object
     */
    public function getLocation()
    {
        return new Location($this->get('locationID'));
    }
    /**
     * Return the associated host.
     *
     * @return host
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
    /**
     * Return the locations storage group.
     *
     * @return object
     */
    public function getStorageGroup()
    {
        return $this->getLocation()->getStorageGroup();
    }
    /**
     * Return the locations storage node.
     *
     * @return object
     */
    public function getStorageNode()
    {
        return $this->getLocation()->getStorageNode();
    }
    /**
     * Return the location is using inits/kernels.
     *
     * @return bool
     */
    public function isTFTP()
    {
        return (bool)$this->getLocation()->get('tftp');
    }
}
