<?php
/**
 * Presents the client with auto logout info.
 *
 * PHP version 7.4+
 *
 * @category HostAutoLogout
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

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
    protected $databaseFields = [
        'id' => 'haloID',
        'hostID' => 'haloHostID',
        'time' => 'haloTime'
    ];
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'time'
    ];
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
