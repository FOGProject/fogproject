<?php
/**
 * Role user group association manager class.
 *
 * PHP version 7.4+
 *
 * @category RoleUserGroupAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;

/**
 * Role user group association manager class.
 *
 * @category RoleUserGroupAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RoleUserGroupAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'roleUserGroupAssoc';
}
