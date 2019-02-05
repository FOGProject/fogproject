<?php
/**
 * Remove the SubnetGroups group.
 *
 * PHP version 5
 *
 * @category RemoveSubnetGroupsGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Remove the SubnetGroups group.
 *
 * @category RemoveSubnetGroupsGroup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   sctt <none@none>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

class RemoveSubnetgroupsGroup extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'RemoveSubnetgroupsGroup';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Remove SubnetGroups Group';
    /**
     * The active flag (always true but for posterity)
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'subnetgroups';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'GROUP_DELETE_SUCCESS',
                array(
                    $this,
                    'removeSubnetgroupsGroup'
                )
            );
    }
    /**
     * Remove the subnetgroup group.
     *
     * @param mixed $arguments The arguments to evaluate.
     *
     * @return void
     */
    public function removeSubnetgroupsGroup($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }

        $Group = $arguments['Group'];

        $subnetGroupsIDs = self::getSubObjectIDs(
            'SubnetGroups',
            array('groupID' => $Group->get('id'))
        );

        foreach ($subnetGroupsIDs as $id) {
            $Subnetgroups = new Subnetgroups($id);
            $Subnetgroups->destroy();
        }
    }
}
