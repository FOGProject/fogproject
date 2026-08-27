<?php
/**
 * Role user association manager class.
 *
 * PHP version 7.4+
 *
 * @category RoleUserAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;

/**
 * Role user association manager class.
 *
 * @category RoleUserAssociationManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RoleUserAssociationManager extends FOGManagerController
{
    /**
     * The base table name.
     *
     * @var string
     */
    public $tablename = 'roleUserAssoc';
}
