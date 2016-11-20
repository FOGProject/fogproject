<?php
/**
 * MAC Address associations
 *
 * PHP version 5
 *
 * @category MACAddressAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * MAC Address associations
 *
 * @category MACAddressAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MACAddressAssociation extends FOGController
{
    /**
     * The database table associated for this class
     *
     * @var string
     */
    protected $databaseTable = 'hostMAC';
    /**
     * The fields the table contains and commonizing names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'hmID',
        'hostID' => 'hmHostID',
        'mac' => 'hmMAC',
        'description' => 'hmDesc',
        'pending' => 'hmPending',
        'primary' => 'hmPrimary',
        'clientIgnore' => 'hmIgnoreClient',
        'imageIgnore' => 'hmIgnoreImaging',
    );
    /**
     * The fields required for the db
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
        'mac',
    );
    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'host'
    );
    /**
     * Returns the host associated
     *
     * @return object
     */
    public function getHost()
    {
        if (!$this->isLoaded('host')) {
            $this->set('host', new Host($this->get('hostID')));
        }
        return $this->get('host');
    }
    /**
     * Returns if mac is pending
     *
     * @return bool
     */
    public function isPending()
    {
        return (bool)$this->get('pending');
    }
    /**
     * Returns if mac is ignored for the client
     *
     * @return bool
     */
    public function isClientIgnored()
    {
        return (bool)$this->get('clientIgnore');
    }
    /**
     * Returns if mac is ignored for imaging
     *
     * @return bool
     */
    public function isImageIgnored()
    {
        return (bool)$this->get('imageIgnore');
    }
    /**
     * Returns if mac is primary
     *
     * @return bool
     */
    public function isPrimary()
    {
        return (bool)$this->get('primary')
            && !$this->get('pending');
    }
}
