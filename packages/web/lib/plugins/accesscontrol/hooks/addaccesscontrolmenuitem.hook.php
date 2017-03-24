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
     * The menu data to change.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function menuData($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        $this->arrayInsertAfter(
            'storage',
            $arguments['main'],
            $this->node,
            array(
                _('Access Control Manager'),
                'fa fa-user-secret fa-2x'
            )
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        array_push($arguments['searchPages'], $this->node);
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
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        array_push($arguments['PagesWithObjects'], $this->node);
    }
}
$AddAccessControlMenuItem = new AddAccessControlMenuItem();
$HookManager
    ->register(
        'MAIN_MENU_DATA',
        array(
            $AddAccessControlMenuItem,
            'menuData'
        )
    );
$HookManager
    ->register(
        'SEARCH_PAGES',
        array(
            $AddAccessControlMenuItem,
            'addSearch'
        )
    );
$HookManager
    ->register(
        'PAGES_WITH_OBJECTS',
        array(
            $AddAccessControlMenuItem,
            'addPageWithObject'
        )
    );
