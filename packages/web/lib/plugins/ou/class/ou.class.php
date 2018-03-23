<?php
/**
 * The OU class.
 *
 * PHP version 5
 *
 * @category OU
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The OU class.
 *
 * @category OU
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OU extends FOGController
{
    /**
     * The location table
     *
     * @var string
     */
    protected $databaseTable = 'ou';
    /**
     * The location table fields and common names
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'ouID',
        'name' => 'ouName',
        'description' => 'ouDesc',
        'createdBy' => 'ouCreatedBy',
        'createdTime' => 'ouCreatedTime',
        'ou' => 'ouDN'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'ou'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'hosts',
        'hostsnotinme'
    ];
    /**
     * Destroy this particular object.
     *
     * @param string $key the key to destroy for match
     *
     * @return bool
     */
    public function destroy($key = 'id')
    {
        self::getClass('OUAssociationManager')
            ->destroy(['ouID' => $this->get('id')]);
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
            ->assocSetter('OU', 'host')
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
     * Loads the locations hosts.
     *
     * @return void
     */
    protected function loadHosts()
    {
        $hostIDs = self::getSubObjectIDs(
            'OUAssociation',
            ['OUID' => $this->get('id')],
            'hostID'
        );
        $hostIDs = self::getSubObjectIDs(
            'Host',
            ['id' => $hostIDs]
        );
        $this->set(
            'hosts',
            $hostIDs
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
        $this->set('hostsnotinme', $hosts);
    }
}
