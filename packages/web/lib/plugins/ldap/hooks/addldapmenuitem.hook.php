<?php
/**
 * Adds the menu item for this plugin
 *
 * PHP version 5
 *
 * @category AddLDAPMenuItem
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the menu item for this plugin
 *
 * @category AddLDAPMenuItem
 * @package  FOGProject
 * @author   Fernando Gietz <nah@nah.com>
 * @author   george1421 <nah@nah.com>
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLDAPMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddLDAPMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add menu item for LDAP';
    /**
     * The active flag.
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to enact on.
     *
     * @var string
     */
    public $node = 'ldap';
    /**
     * Initialize object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->node, self::$pluginsinstalled)) {
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
        $arguments['menu']['globalsettings'] = _('Global Options');
        $arguments['menu']['export'] = self::$foglang['Export'] . ' ' . _('LDAP');
        $arguments['menu']['import'] = self::$foglang['Import'] . ' ' . _('LDAP'); 
    }
    /**
     * Sets the menu item into the menu
     *
     * @param mixed $arguments the item to adjust
     *
     * @return void
     */
    public function menuData($arguments)
    {
        $arguments['hook_main'][$this->node]
            = [_('LDAP Servers'), 'fa fa-key'];
    }
    /**
     * Adds the plugin page to the search page lists
     *
     * @param mixed $arguments the item to adjust
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        $arguments['searchPages'][] = $this->node;
    }
    /**
     * Adds the plugin page to use internalized objects
     *
     * @param mixed $arguments the item to adjust
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        $arguments['PagesWithObjects'][] = $this->node;
    }
}
