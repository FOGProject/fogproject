<?php
/**
 * Adds the windows keys menu item.
 *
 * PHP version 5
 *
 * @category AddWindowsKeyMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the windows keys menu item.
 *
 * @category AddWindowsKeyMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddWindowsKeyMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddWindowsKeyMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add menu item for windows keys';
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
    public $node = 'windowskey';
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
     * Add the new items beyond list/create.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function menuUpdate($arguments)
    {
        if ($arguments['node'] != $this->node) {
            return;
        }
        $arguments['menu']['export'] = _('Export Windows Keys');
        $arguments['menu']['import'] = _('Import Windows Keys');
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
            [_('Windows Keys'), 'fa fa-windows']
        );
    }
    /**
     * Adds the windows key page to search elements.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        array_push($arguments['searchPages'], $this->node);
    }
    /**
     * Adds the windows key page to objects elements.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        array_push($arguments['PagesWithObjects'], $this->node);
    }
}
