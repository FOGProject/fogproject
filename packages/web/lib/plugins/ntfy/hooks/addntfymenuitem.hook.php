<?php
/**
 * Adds the ntfy menu item to the menu.
 *
 * PHP version 5
 *
 * @category AddNtfyMenuItem
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the ntfy menu item to the menu.
 *
 * @category AddNtfyMenuItem
 * @package  FOGProject
 * @author   Tony Lam <tonylam5349@gmail.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddNtfyMenuItem extends Hook
{
    /**
     * The name of this hook
     *
     * @var string
     */
    public $name = 'AddNtfyMenuItem';
    /**
     * The Description of this hook
     *
     * @var string
     */
    public $description = 'Add menu item for ntfy';
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
    public $node = 'ntfy';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerInstalled([
            ['MAIN_MENU_DATA', 'menuData'],
            ['SEARCH_PAGES', 'addSearch'],
            ['PAGES_WITH_OBJECTS', 'addPageWithObject'],
            ['PERMISSION_REGISTRY_DATA', 'permData'],
        ]);
    }
    /**
     * Inserts the ntfy menu item
     *
     * @param array $arguments the arguments to alter
     *
     * @return void
     */
    public function menuData($arguments)
    {
        $arguments['hook_main'][$this->node]
            = [_('ntfy Accounts'), 'fa fa-comment'];
    }
    /**
     * Adds the ntfy page to objects elements.
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

    /**
     * Registers this plugin's permission node and actions so its pages
     * are gated by RBAC and shown in the role permission matrix.
     *
     * @param mixed $arguments The permission registry to modify.
     *
     * @return void
     */
    public function permData($arguments)
    {
        $arguments['registry'][$this->node] = [
            'view', 'create', 'edit', 'delete'
        ];
    }
}
