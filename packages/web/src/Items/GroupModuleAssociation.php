<?php
/**
 * The group module grant class.
 *
 * PHP version 7.4+
 *
 * @category GroupModuleAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * The group module grant class.
 *
 * A row here says "this group turns this module on", and it is
 * presence-only: there is no state column, because a group cannot turn a
 * module off. Only a host can do that, with a moduleStatusByHost row at
 * msState=0, which beats every grant (ADR 0038, and the tiers are resolved
 * in Assign\Resolver::resolveModules).
 *
 * @category GroupModuleAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupModuleAssociation extends FOGController
{
    /**
     * The group module grant table.
     *
     * @var string
     */
    protected $databaseTable = 'groupModuleAssoc';
    /**
     * The group module grant fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'gmaID',
        'groupID' => 'gmaGroupID',
        'moduleID' => 'gmaModuleID'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'groupID',
        'moduleID'
    ];
    /**
     * Get's the group object.
     *
     * @return object
     */
    public function getGroup()
    {
        return new Group($this->get('groupID'));
    }
    /**
     * Get's the module object.
     *
     * @return object
     */
    public function getModule()
    {
        return new Module($this->get('moduleID'));
    }
}
