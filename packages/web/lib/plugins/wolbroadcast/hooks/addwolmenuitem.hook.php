<?php
/**
 * Adds the wol menu item.
 *
 * PHP version 5
 *
 * @category AddWOLMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the wol menu item.
 *
 * @category AddWOLMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddWOLMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddWOLMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add menu item for WOL Broadcast';
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
    public $node = 'wolbroadcast';
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
                _('WOL Broadcast Management'),
                'fa fa-plug fa-2x'
            )
        );
    }
    /**
     * Adds the wol page to search elements.
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
     * Adds the wol page to objects elements.
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
$AddWOLMenuItem = new AddWOLMenuItem();
$HookManager
    ->register(
        'MAIN_MENU_DATA',
        array(
            $AddWOLMenuItem,
            'menuData'
        )
    );
$HookManager
    ->register(
        'SEARCH_PAGES',
        array(
            $AddWOLMenuItem,
            'addSearch'
        )
    );
$HookManager
    ->register(
        'PAGES_WITH_OBJECTS',
        array(
            $AddWOLMenuItem,
            'addPageWithObject'
        )
    );
