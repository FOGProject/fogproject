<?php
/**
 * Adds the Site menu item.
 *
 * PHP version 7
 *
 * @category AddSiteMenuItem
 * @package  FOGProject
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

class AddSiteMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddSiteMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add menu item for site plugin';
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
    public $node = 'site';
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
        self::arrayInsertAfter(
            'storage',
            $arguments['main'],
            $this->node,
            array(
                _('Site Manager'),
                'fa fa-key fa-2x'
            )
        );
    }
    /**
     * Adds the Site page to search elements.
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
     * Adds the location page to objects elements.
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
$AddSiteMenuItem= new AddSiteMenuItem();
$HookManager
    ->register(
        'MAIN_MENU_DATA',
        array(
            $AddSiteMenuItem,
            'menuData'
        )
    )
    ->register(
        'SEARCH_PAGES',
        array(
            $AddSiteMenuItem,
            'addSearch'
        )
    )
    ->register(
        'PAGES_WITH_OBJECTS',
        array(
        	$AddSiteMenuItem,
            'addPageWithObject'
        )
	);
