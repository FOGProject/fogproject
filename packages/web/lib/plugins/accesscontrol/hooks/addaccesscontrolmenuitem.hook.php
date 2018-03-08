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
class AddAccessControlMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddAccessControlMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add menu item for access control';
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
            [$this, 'menuData']
        )->register(
            'SEARCH_PAGES',
            [$this, 'addSearch']
        )->register(
            'PAGES_WITH_OBJECTS',
            [$this, 'addPageWithObject']
        )->register(
            'SUB_MENULINK_DATA',
            [$this, 'menuUpdate']
        );
    }
    /**
     * Add the enw items beyond list/create.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function menuUpdate($arguments)
    {
        if ($arguments['node'] == $this->node) {
            $arguments['menu']['list'] = _('List All Roles');
            $arguments['menu']['add'] = _('Create New Role');
            $arguments['menu']['export'] = _('Export Roles');
            $arguments['menu']['import'] = _('Import Roles');
        }
        if ($arguments['node'] == 'accesscontrolrule') {
            $arguments['menu']['list'] = _('List All Rules');
            $arguments['menu']['add'] = _('Create New Rule');
            $arguments['menu']['export'] = _('Export Rules');
            $arguments['menu']['import'] = _('Import Rules');
        }
    }
    /**
     * The menu data to change.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function menuData($arguments)
    {
        self::arrayInsertAfter(
            'storagegroup',
            $arguments['main'],
            $this->node,
            [_('Access Controls'), 'fa fa-user-secret']
        );
        self::arrayInsertAfter(
            $this->node,
            $arguments['main'],
            'accesscontrolrule',
            [_('Access Control Rules'), 'fa fa-user-times']
        );
    }
    /**
     * Adds the Access Control page to search elements.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        array_push($arguments['searchPages'], $this->node);
        array_push($arguments['searchPages'], 'accesscontrolrule');
    }
    /**
     * Adds the location page to objects elements.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        array_push($arguments['PagesWithObjects'], $this->node);
        array_push($arguments['PagesWithObjects'], 'accesscontrolrule');
    }
}
