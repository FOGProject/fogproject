<?php
/**
 * Add slack menu item.
 *
 * PHP version 5
 *
 * @category AddSlackMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Add slack menu item.
 *
 * @category AddSlackMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddSlackMenuItem extends Hook
{
    /**
     * Name of the hook.
     *
     * @var string
     */
    public $name = 'AddSlackMenuItem';
    /**
     * Description of the hook.
     *
     * @var string
     */
    public $description = 'Add menu item for slack';
    /**
     * Active or not?
     *
     * @var bool
     */
    public $active = true;
    /**
     * Node to work with.
     *
     * @var string
     */
    public $node = 'slack';
    /**
     * Create menu data.
     *
     * @param mixed $arguments The items to modify.
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
                _('Slack Management'),
                'fa fa-slack fa-2x'
            )
        );
    }
    /**
     * Add page with objects.
     *
     * @param mixed $arguments The items to modify.
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
    /**
     * Add search for this item.
     *
     * @param mixed $arguments The items to modify.
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
}
$AddSlackMenuItem = new AddSlackMenuItem();
$HookManager
    ->register(
        'MAIN_MENU_DATA',
        array($AddSlackMenuItem, 'menuData')
    )
    ->register(
        'SEARCH_PAGES',
        array($AddSlackMenuItem, 'addSearch')
    )
    ->register(
        'PAGES_WITH_OBJECTS',
        array($AddSlackMenuItem, 'addPageWithObject')
    );
