<?php
/**
 * Adds the ntfy menu item to the menu.
 *
 * PHP version 5
 *
 * @category AddNtfyMenuItem
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the ntfy menu item to the menu.
 *
 * @category AddNtfyMenuItem
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddNtfyMenuItem extends Hook
{
    /**
     * the name of this hook
     * 
     * @var string
     */
    public $name = 'AddNtfyMenuItem';
    /**
     * the description of this hook
     * 
     * @var string
     */
    public $description = 'Add Menu Item for ntfy';
    /**
     * active flag
     * 
     * @var bool
     */
    public $active = true;
    /**
     * node enacted upon
     * 
     * @var string
     */
    public $node = 'ntfy';
    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->register(
                'MAIN_MENU_DATA',
                array(
                    $this,
                    'menuData'
                )
            )
            ->register(
                'SEARCH_PAGES',
                array(
                    $this,
                    'addSearch'
                )
            )
            ->register(
                'PAGES_WITH_OBJECTS',
                array(
                    $this,
                    'addPageWithObject'
                )
            );
    }
    /**
     * Insert ntfy menu item
     * 
     * @param array $arguments to be altered
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
                _('ntfy Accounts'),
                'fa fa-comment'
            )
            );
    }
    /**
     * Inserts the pages with objects element
     *
     * @param array $arguments the arguments to alter
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if (!isset($arguments['PagesWithObjects'])) {
            return;
        }
        array_push(
            $arguments['PagesWithObjects'],
            $this->node
        );
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
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        if (!isset($arguments['searchPages'])) {
            return;
        }
        array_push(
            $arguments['searchPages'],
            $this->node
        );
    }
}