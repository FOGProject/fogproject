<?php
/**
 * Adds the Access control menu item.
 *
 * PHP version 5
 *
 * @category AddAccessControlMenuItem
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the Access control menu item.
 *
 * @category AddAccessControlMenuItem
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DelAccessControlMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'DelAccessControlMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Delete menus item for access control';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'accesscontrol';
    /**
     * The menu data to change.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function deleteMenuData($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $userID = self::getSubObjectIDs(
            'User',
            array(
                'name' => $_SESSION['FOG_USERNAME']
            ),
            'id'
        );
        $roleID = self::getSubObjectIDs(
            'AccessControlAssociation',
            array(
                'userID' => $userID
            ),
            'roleID'
        );
        foreach ((array)self::getClass('AccessControlRuleAssociationManager')
            ->find(array('roleID' => $roleID)) as
            &$AccessControlRuleAssociation
        ) {
            $AccessControlRule = new AccessControlRule(
                $AccessControlRuleAssociation->get('ruleID')
            );
            unset(
                $arguments[
                    $AccessControlRule->get('parent')
                ]
                [
                    $AccessControlRule->get('value')
                ],
                $AccessControlRule
            );
        }
        unset($AccessControlRuleAssociation);
    }
    /**
     * The menu data to change.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function deleteSubMenuData($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $userID = self::getSubObjectIDs(
            'User',
            array(
                'name' => $_SESSION['FOG_USERNAME']
            ),
            'id'
        );
        $roleID = self::getSubObjectIDs(
            'AccessControlAssociation',
            array('userID' => $userID),
            'roleID'
        );
        foreach ((array)self::getClass('AccessControlRuleAssociationManager')
            ->find(array('roleID' => $roleID)) as
            &$AccessControlRuleAssociation
        ) {
            $AccessControlRule = new AccessControlRule(
                $AccessControlRuleAssociation->get('ruleID')
            );
            unset(
                $arguments[
                    $AccessControlRule->get('parent')
                ]
                [
                    $AccessControlRule->get('value')
                ],
                $AccessControlRule
            );
        }
        unset($AccessControlRuleAssociation);
        //unset($arguments['menu']['list']);
    }
}
$DelAccessControlMenuItem = new DelAccessControlMenuItem();
$HookManager
    ->register(
        'MAIN_MENU_DATA',
        array(
            $DelAccessControlMenuItem,
            'DeleteMenuData'
        )
    )
    ->register(
        'SUB_MENULINK_DATA',
        array(
            $DelAccessControlMenuItem,
            'DeleteSubMenuData'
        )
    );
