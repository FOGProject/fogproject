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
     * Inserts the push bullet menu item
     *
     * @param array $arguments the arguments to alter
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
                _('Pushbullet Management'),
                'fa fa-bell fa-2x'
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
