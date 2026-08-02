<?php
/**
 * Adds the location menu item.
 *
 * PHP version 5
 *
 * @category AddLocationMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Adds the location menu item.
 *
 * @category AddLocationMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Lee Rowlett <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddLocationMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddLocationMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add menu item for location';
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
    public $node = 'location';
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
        if ($arguments['node'] != $this->node) {
            return;
        }
        $arguments['menu']['export'] = _('Export Locations');
        $arguments['menu']['import'] = _('Import Locations');
    }
    /**
     * The menu data to change.
     *
     * @param mixed $arguments The arguments to change.
     *
     * @return void
     */
    public function menuData($arguments)
    {
        $arguments['hook_main'][$this->node]
            = [_('Locations'), 'fa fa-globe'];
        // Existence check goes through the cached settings reader, not a raw
        // Setting->load(). This hook only ever needs to know whether the row
        // has been seeded yet, but it fired on every main-menu build -- which
        // is every request, including JSON/AJAX endpoints that render no menu
        // -- and each fire was an uncached SELECT on globalSettings. getSetting()
        // answers from the request-warmed cache for a key that exists, so the
        // steady state (seeded long ago) now costs nothing. A genuinely absent
        // key still falls through to the one-time create below.
        if (self::getSetting('FOG_SNAPIN_LOCATION_SEND_ENABLED') !== null) {
            return;
        }
        $Setting = self::getClass('Setting')
            ->set(
                'name',
                'FOG_SNAPIN_LOCATION_SEND_ENABLED'
            )->load('name');
        if ($Setting->isValid()) {
            return;
        }
        $Setting->set(
            'description',
            sprintf(
                '%s %s. %s %s. %s.',
                _('This setting defines sending the'),
                _('location url based on the host that checks in'),
                _('It tells the client to download snapins from'),
                _('the host defined location where available'),
                _('Default is disabled')
            )
        )->set('value', 0)
        ->set('category', 'FOG Client - Snapins')
        ->save();
    }
    /**
     * Adds the location page to search elements.
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
     * Adds the location page to objects elements.
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
