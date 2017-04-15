<?php
/**
 * The fileintegiry menu item hook
 *
 * PHP version 5
 *
 * @category AddFileIntegrityMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The fileintegiry menu item hook
 *
 * @category AddFileIntegrityMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddFileIntegrityMenuItem extends Hook
{
    /**
     * The hook name
     *
     * @var string
     */
    public $name = 'AddFileIntegrityMenuItem';
    /**
     * The hook description
     *
     * @var string
     */
    public $description = 'Add menu item for File Integrity Information';
    /**
     * The active flag
     *
     * @var bool
     */
    public $active = true;
    /**
     * The node to enact within
     *
     * @var string
     */
    public $node = 'fileintegrity';
    /**
     * The menu data method
     *
     * @param array $arguments the arguments to enact upon.
     *
     * @return void
     */
    public function menuData($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        self::arrayInsertAfter(
            'storage',
            $arguments['main'],
            $this->node,
            array(
                _('File Integrity Management'),
                'fa fa-list-ol fa-2x'
            )
        );
    }
    /**
     * Adds the search functionality.
     *
     * @param array $arguments the arguments to enact upon.
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        array_push($arguments['searchPages'], $this->node);
    }
    /**
     * Removes action box from this plugin
     *
     * @param array $arguments the arguments to enact upon.
     *
     * @return void
     */
    public function removeActionBox($arguments)
    {
        if (in_array($this->node, (array)self::$pluginsinstalled)
            && $_REQUEST['node'] == $this->node
        ) {
            $arguments['actionbox'] = '';
        }
    }
    /**
     * Adds the page as it contains objects
     *
     * @param array $arguments the arguments to enact upon.
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        array_push($arguments['PagesWithObjects'], $this->node);
    }
}
$AddFileIntegrityMenuItem = new AddFileIntegrityMenuItem();
$HookManager
    ->register(
        'MAIN_MENU_DATA',
        array(
            $AddFileIntegrityMenuItem,
            'menuData'
        )
    )
    ->register(
        'SEARCH_PAGES',
        array(
            $AddFileIntegrityMenuItem,
            'addSearch'
        )
    )
    ->register(
        'ACTIONBOX',
        array(
            $AddFileIntegrityItem,
            'removeActionBox'
        )
    )
    ->register(
        'PAGES_WITH_OBJECTS',
        array(
            $AddFileIntegrityMenuItem,
            'addPageWithObject'
        )
    );
