<?php
/**
 * Adds task state edit menu item.
 *
 * PHP Version 5
 *
 * @category AddTaskstateeditMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds task state edit menu item.
 *
 * @category AddTaskstateeditMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddTaskstateeditMenuItem extends Hook
{
    /**
     * Name of the hook.
     *
     * @var string
     */
    public $name = 'AddTaskstateeditMenuItem';
    /**
     * Description of the hook.
     *
     * @var string
     */
    public $description = 'Add menu item for Task State editing';
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
    public $node = 'taskstateedit';
    /**
     * Adds the menu item.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function menuData($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::arrayInsertAfter(
            'task',
            $arguments['main'],
            $this->node,
            array(
                _('Task State Management'),
                'fa fa-hourglass-start fa-2x'
            )
        );
    }
    /**
     * Adds search element.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        array_push($arguments['searchPages'], $this->node);
    }
    /**
     * Removes action box.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function removeActionBox($arguments)
    {
        if (in_array($this->node, (array)self::$pluginsinstalled)
            && $_REQUEST['node'] == $this->node
        ) {
            $arguments['actionbox'] = '';
        }
    }
    /**
     * Adds page with object.
     *
     * @param mixed $arguments The items to modify.
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        array_push($arguments['PagesWithObjects'], $this->node);
    }
}
$AddTaskstateeditMenuItem = new AddTaskstateeditMenuItem();
$HookManager
    ->register(
        'MAIN_MENU_DATA',
        array(
            $AddTaskstateeditMenuItem,
            'menuData'
        )
    )
    ->register(
        'SEARCH_PAGES',
        array(
            $AddTaskstateeditMenuItem,
            'addSearch'
        )
    )
    ->register(
        'ACTIONBOX',
        array(
            $AddTaskstateeditMenuItem,
            'removeActionBox'
        )
    )
    ->register(
        'PAGES_WITH_OBJECTS',
        array(
            $AddTaskstateeditMenuItem,
            'addPageWithObject'
        )
    );
