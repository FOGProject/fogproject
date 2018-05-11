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
        $find = ['userID' => self::$FOGUser->get('id')];
        Route::ids(
            'accesscontrolassociation',
            $find,
            'accesscontrolID'
        );
        $accesscontrols = json_decode(
            Route::getData(),
            true
        );
        $find = ['accesscontrolID' => $accesscontrols];
        Route::listem(
            'accesscontrolruleassociation',
            $find
        );
        $Rules = json_decode(
            Route::getData()
        );
        foreach ($Rules->data as &$Rule) {
            Route::indiv('accesscontrolrule', $Rule->accesscontrolruleID);
            $Rule = json_decode(
                Route::getData()
            );
            unset(
                $arguments[$Rule->parent][$Rule->value],
                $Rule
            );
        }
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
        $find = ['userID' => self::$FOGUser->get('id')];
        Route::ids(
            'accesscontrolassociation',
            $find,
            'accesscontrolruleID'
        );
        $accesscontrols = json_decode(
            Route::getData(),
            true
        );
        $find = ['accesscontrolruleID' => $accesscontrols];
        Route::listem(
            'accesscontrolruleassociation',
            $find
        );
        $Rules = json_decode(
            Route::getData()
        );
        foreach ($Rules->data as &$Rule) {
            Route::indiv('accesscontrolrule', $Rule->accesscontrolruleID);
            $Rule = json_decode(
                Route::getData()
            );
            unset($arguments[$Rule->parent][$Rule->value]);
            unset($Rule);
        }
    }
}
