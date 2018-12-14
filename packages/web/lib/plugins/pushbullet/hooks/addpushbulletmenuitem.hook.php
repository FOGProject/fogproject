<?php
/**
 * Adds the pushbullet menu item to the menu.
 *
 * PHP version 5
 *
 * @category AddPushbulletMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the pushbullet menu item to the menu.
 *
 * @category AddPushbulletMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Joe Schmitt <jbob182@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddPushbulletMenuItem extends Hook
{
    /**
     * The name of this hook
     *
     * @var string
     */
    public $name = 'AddPushbulletMenuItem';
    /**
     * The Description of this hook
     *
     * @var string
     */
    public $description = 'Add menu item for pushbullet';
    /**
     * The active flag
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node that enacts upon
     *
     * @var string
     */
    public $node = 'pushbullet';
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
        );
    }
    /**
     * Inserts the push bullet menu item
     *
     * @param array $arguments the arguments to alter
     *
     * @return void
     */
    public function menuData($arguments)
    {
        $arguments['hook_main'][$this->node] = [_('Pushbullet Accounts'), 'fa fa-bell'];
    }
    /**
     * Adds the pushbullet page to objects elements.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        $arguments['PagesWithObjects'][] = $this->node;
    }
    /**
     * Inserts the search
     *
     * @param array $arguments the arguments to alter
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        $arguments['searchPages'][] = $this->node;
    }
}
