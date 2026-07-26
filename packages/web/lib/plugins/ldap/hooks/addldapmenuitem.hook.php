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
     * The second node this plugin owns: the directory groups whose
     * associations decide what a signing-in user receives.
     *
     * Refs https://github.com/FOGProject/fogproject/issues/882
     */
    const GROUP_NODE = 'ldapgroup';
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
        $this->registerInstalled([
            ['MAIN_MENU_DATA', 'menuData'],
            ['SEARCH_PAGES', 'addSearch'],
            ['PAGES_WITH_OBJECTS', 'addPageWithObject'],
            ['PERMISSION_REGISTRY_DATA', 'permData'],
            ['SUB_MENULINK_DATA', 'menuUpdate'],
        ]);
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
        // Directory groups are a node of their own, so they need their own
        // Export entry. FOGPage::export() is inherited and works for any
        // node; what the core menu builder keys off ($foglang[$refNode])
        // has no entry for a plugin node, so without this the page exists
        // and is permission-gated but nothing links to it.
        if ($arguments['node'] == self::GROUP_NODE) {
            $arguments['menu']['export'] = self::$foglang['Export']
                . ' ' . _('LDAP Groups');
            return;
        }
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
        // Groups get their own node because granting a role or a user group
        // is an ordinary association, and the shared association tab needs
        // the group itself to be the owning object. See
        // LDAPGroupManagement.
        $arguments['hook_main'][self::GROUP_NODE]
            = [_('LDAP Groups'), 'fa fa-users'];
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
        $arguments['searchPages'][] = self::GROUP_NODE;
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
        $arguments['PagesWithObjects'][] = self::GROUP_NODE;
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
        // Registered separately so a role can be given the ability to
        // manage servers without the ability to change what a directory
        // group grants -- the latter is the one that hands out access.
        $arguments['registry'][self::GROUP_NODE] = [
            'view', 'create', 'edit', 'delete'
        ];
    }
}
