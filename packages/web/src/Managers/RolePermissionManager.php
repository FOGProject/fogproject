<?php
/**
 * Role permission manager class.
 *
 * PHP version 7.4+
 *
 * @category RolePermissionManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;

/**
 * Role permission manager class.
 *
 * @category RolePermissionManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RolePermissionManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'rolePermissions';
}
