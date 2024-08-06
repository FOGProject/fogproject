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
}
