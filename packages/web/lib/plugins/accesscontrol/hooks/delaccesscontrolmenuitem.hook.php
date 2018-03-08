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
            'MAIN_MENU_DATA',
            [$this, 'deleteMenuData']
        )->register(
            'SUB_MENULINK_DATA',
            [$this, 'deleteSubMenuData']
        );
    }
    /**
     * The menu data to change.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function deleteMenuData($arguments)
    {
        $userID = self::getSubObjectIDs(
            'User',
            ['name' => self::$FOGUser->get('name')],
            'id'
        );
        $acID = self::getSubObjectIDs(
            'AccessControlAssociation',
            ['userID' => $userID],
            'accesscontrolID'
        );
        $rules = self::getSubObjectIDs(
            'AccessControl',
            ['id' => $acID],
            'accesscontrolrules'
        );

        /*foreach ((array)self::getClass('AccessControlRuleManager')
            ->find(
                array('id' => $rules)
            ) as $Rule
        ) {
            unset(
                $arguments[$Rule->get('parent')][$Rule->get('value')],
                $Rule
            );
        }*/
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
        $userID = self::getSubObjectIDs(
            'User',
            ['name' => self::$FOGUser->get('name')],
            'id'
        );
        $acID = self::getSubObjectIDs(
            'AccessControlAssociation',
            ['userID' => $userID],
            'accesscontrolID'
        );
        /*foreach ((array)self::getClass('AccessControlRuleAssociationManager')
            ->find(array('accesscontrolID' => $acID)) as
            &$AccessControlRuleAssociation
        ) {
            $AccessControlRule = new AccessControlRule(
                $AccessControlRuleAssociation->get('accesscontrolruleID')
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
        unset($AccessControlRuleAssociation);*/
    }
}
