<?php
/**
 * Adds task type edit menu item.
 *
 * PHP Version 5
 *
 * @category AddTasktypeeditMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds task type edit menu item.
 *
 * @category AddTasktypeeditMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddTasktypeeditMenuItem extends Hook
{
    /**
     * Name of the hook.
     *
     * @var string
     */
    public $name = 'AddTasktypeeditMenuItem';
    /**
     * Description
     *
     * @var string
     */
    public $description = 'Add menu item for Task Type editing';
    /**
     * Active?
     *
     * @var bool
     */
    public $active = true;
    /**
     * Node to work with.
     *
     * @var string
     */
    public $node = 'tasktypeedit';
    /**
     * Update the menu data.
     *
     * @param mixed $arguments The items to modify
     *
     * @return void
     */
    public function menuData($arguments)
    {
        if (!in_array($this->node, (array)$_SESSION['PluginsInstalled'])) {
            return;
        }
        self::arrayInsertAfter(
            'task',
            $arguments['main'],
            $this->node,
            array(
                _('Task Type Management'),
                'fa fa-th-list fa-2x'
            )
        );
    }
    /**
     * Add search
     *
     * @param mixed $arguments The items to modify
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
     * Remove action box
     *
     * @param mixed $arguments The items to modify
     *
     * @return void
     */
    public function removeActionBox($arguments)
    {
        if (in_array($this->node, (array)$_SESSION['PluginsInstalled'])
            && $_REQUEST['node'] == $this->node
        ) {
            $arguments['actionbox'] = '';
        }
    }
    /**
     * Add pages with objects
     *
     * @param mixed $arguments The items to modify
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
$AddTasktypeeditMenuItem = new AddTasktypeeditMenuItem();
$HookManager
    ->register(
        'MAIN_MENU_DATA',
        array(
            $AddTasktypeeditMenuItem,
            'menuData'
        )
    )
    ->register(
        'SEARCH_PAGES',
        array(
            $AddTasktypeeditMenuItem,
            'addSearch'
        )
    )
    ->register(
        'ACTIONBOX',
        array(
            $AddTasktypeeditMenuItem,
            'removeActionBox'
        )
    )
    ->register(
        'PAGES_WITH_OBJECTS',
        array(
            $AddTasktypeeditMenuItem,
            'addPageWithObject'
        )
    );
