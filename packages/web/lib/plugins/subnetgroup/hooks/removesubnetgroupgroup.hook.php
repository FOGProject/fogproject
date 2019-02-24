<?php
/**
 * Remove the subnet group from the group.
 *
 * PHP Version 5
 *
 * @category RemoveSubnetGroupGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Remove the subnet group from the group.
 *
 * @category RemoveSubnetGroupGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class RemoveSubnetGroupGroup extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'RemoveSubnetGroupGroup';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Remove the subnet group from the group.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'subnetgroup';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::$HookManager->register(
            'GROUP_DELETE_SUCCESS',
            [$this, 'removeSubnetGroupGroup']
        );
    }
    /**
     * Remove the subnet group group.
     *
     * @param mixed $arguments The arguments to modify.
     *
     * @return void
     */
    public function removeSubnetGroupGroup($arguments)
    {
        $group = ['groupID' => $arguments['Group']->get('id')];
        Route::deletemass($this->node, $group);
    }
}
