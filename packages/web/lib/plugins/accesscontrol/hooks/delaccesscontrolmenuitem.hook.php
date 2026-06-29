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
        $this->registerInstalled([
            ['DELETE_MENU_DATA', 'deleteMenuData'],
            ['DELETE_MENULINK_DATA', 'deleteSubMenuData'],
            ['ACTIONBOX', 'deleteActionBoxData'],
        ]);
    }
    /**
     * Get the access control rules more centrally.
     *
     * @param string $event The rule type to get
     *
     * @return []
     */
    private function getAccessControlRules($event, $anode = '')
    {
        global $node;
        $find = ['userID' => self::$FOGUser->get('id')];
        $accesscontrols = Route::getIds(
            'accesscontrolassociation',
            $find,
            'accesscontrolID'
        );
        if (!$accesscontrols) {
            $empty = new stdClass();
            $empty->data = [];
            return $empty;
        }
        $find = ['accesscontrolID' => $accesscontrols];
        $ruleIDs = Route::getIds(
            'accesscontrolruleassociation',
            $find,
            'accesscontrolruleID'
        );
        if (!$ruleIDs) {
            $empty = new stdClass();
            $empty->data = [];
            return $empty;
        }
        $nodes = [
            '',
            $node
        ];
        if ($anode) {
            array_push($nodes, $anode);
        }

        $find = ['id' => $ruleIDs, 'type' => $event, 'node' => $nodes];
        Route::listem(
            'accesscontrolrule',
            $find
        );
        $Rules = json_decode(Route::getData());
        return $Rules;
    }
    /**
     * Remove the action box
     *
     * @param mixed $arguments The arguments to change.
     */
    public function deleteActionBoxData($arguments)
    {
        $Rules = $this->getAccessControlRules($arguments['event']);
        foreach ($Rules->data as $Rule) {
            $arguments[$Rule->value] = '';
        }
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
        $Rules = $this->getAccessControlRules($arguments['event']);
        foreach ($Rules->data as $Rule) {
            unset($arguments[$Rule->parent][$Rule->value]);
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
        $Rules = $this->getAccessControlRules($arguments['event'], $arguments['node']);
        foreach ($Rules->data as $Rule) {
            unset($arguments[$Rule->parent][$Rule->value]);
        }
    }
}
