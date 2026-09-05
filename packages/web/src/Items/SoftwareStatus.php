<?php
/**
 * What a host last reported for one software entry.
 *
 * PHP version 7.4+
 *
 * @category SoftwareStatus
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * What a host last reported for one software entry.
 *
 * @category SoftwareStatus
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SoftwareStatus extends FOGController
{
    /**
     * The table.
     *
     * @var string
     */
    protected $databaseTable = 'softwareStatus';
    /**
     * The fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'sstID',
        'hostID' => 'sstHostID',
        'softwareID' => 'sstSoftwareID',
        'installedVersion' => 'sstInstalledVersion',
        'status' => 'sstStatus',
        'return' => 'sstReturnCode',
        'details' => 'sstDetails',
        'checked' => 'sstChecked'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'softwareID'
    ];
}
