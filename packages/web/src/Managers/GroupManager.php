<?php
/**
 * Group manager mass management class
 *
 * PHP version 7.4+
 *
 * @category GroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;

/**
 * Group manager mass management class
 *
 * @category GroupManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'groupMembers';
}
