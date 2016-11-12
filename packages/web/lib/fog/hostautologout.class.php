<?php
/**
 * Presents the client with auto logout info.
 *
 * PHP version 5
 *
 * @category HostAutoLogout
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents the client with auto logout info.
 *
 * @category HostAutoLogout
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostAutoLogout extends FOGController
{
    /**
     * The host auto logout table.
     *
     * @var string
     */
    protected $databaseTable = 'hostAutoLogOut';
    /**
     * The host auto logout fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'haloID',
        'hostID' => 'haloHostID',
        'time' => 'haloTime',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
        'time',
    );
    /**
     * Return the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
}
