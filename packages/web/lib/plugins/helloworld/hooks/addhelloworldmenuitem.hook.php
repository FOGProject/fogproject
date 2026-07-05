<?php
/**
 * Adds the Hello World menu item and wires the page into search/objects.
 *
 * Hooks register callbacks against named events in their constructor, but
 * ONLY after confirming the plugin is installed (the $pluginsinstalled
 * guard). Class AddHelloWorldMenuItem must live in the file
 * addhelloworldmenuitem.hook.php (lowercased class name + .hook.php).
 *
 * PHP version 5
 *
 * @category AddHelloWorldMenuItem
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the Hello World menu item.
 *
 * @category AddHelloWorldMenuItem
 * @package  FOGProject
 * @author   FOG Project <info@fogproject.org>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddHelloWorldMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddHelloWorldMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add menu item for Hello World.';
    /**
     * For posterity.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node this hook enacts with.
     *
     * @var string
     */
    public $node = 'helloworld';
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
     * Adds the top-level menu entry.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function menuData($arguments)
    {
        $arguments['hook_main'][$this->node]
            = [_('Hello World'), 'fa fa-cube'];
    }
    /**
     * Registers the page as searchable.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        $arguments['searchPages'][] = $this->node;
    }
    /**
     * Registers the page as using internalized objects (enables edit/delete).
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
