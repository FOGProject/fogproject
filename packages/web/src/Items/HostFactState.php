<?php
/**
 * What a host last reported facts for, and the hash it reported: the
 * server's memory of when it last heard inventory or software from a host
 * (design 0006).
 *
 * PHP version 7.4+
 *
 * @category Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * What a host last reported facts for, and the hash it reported: the
 * server's memory of when it last heard inventory or software from a host
 * (design 0006).
 *
 * @category Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostFactState extends FOGController
{
    /**
     * The hostFactState table.
     *
     * @var string
     */
    protected $databaseTable = 'hostFactState';
    /**
     * The hostFactState fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hfsID',
        'hostID' => 'hfsHostID',
        'kind' => 'hfsKind',
        'hash' => 'hfsHash',
        'updated' => 'hfsUpdated'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'kind'
    ];
}
