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
     * The shortest auto-logout the client honors; anything below this saves
     * as 0 (disabled).
     *
     * It lives on the model rather than on a page because three places have
     * to agree about it and none of them owns it: the group page enforces it
     * on save and hands it to its JS so the readout can predict the result
     * without a round trip, and the host mass edit enforces the same rule on
     * the same column. It was on GroupManagement while that was the only
     * writer; the mass edit is the second, and ADR 0038 decision 10 removes
     * the first.
     *
     * @var int
     */
    const MIN_MINUTES = 5;
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
