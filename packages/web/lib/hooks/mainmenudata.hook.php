<?php
/**
 * Main menu hook changer.
 *
 * PHP version 7.4+
 *
 * @category MainMenuData
 * @package  FOGProject
 * @author   Sebastian Roth <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Main menu hook changer.
 *
 * @category MainMenuData
 * @package  FOGProject
 * @author   Sebastian Roth <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MainMenuData extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'MainMenuData';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Example to show how to change MainMenu data.';
    /**
     * Is this hook active or not.
     *
     * @var bool
     */
    public $active = false;
    /**
     * Position of the new main menu entry.
     *
     * @var string
     */
    public $insertAfter = 'task';
    /**
     * Name/link for the new menu entry.
     *
     * @var string
     */
    public $menuitem = 'Inventory';
    /**
     * Icon for the new menu entry.
     *
     * @var string
     */
    public $icon = 'fa-paperclip';
    /**
     * Initializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register(
            'MAIN_MENU_DATA',
            [$this, 'addToMainMenu']
        );
    }
    /**
     * The changer method.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function addToMainMenu($arguments)
    {
        $link = strtolower($this->menuitem);
        self::arrayInsertAfter(
            $this->insertAfter,
            $arguments['main'],
            $link,
            [_($this->menuitem), 'fa ' . $this->icon . ' fa-2x']
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\MainMenuData', 'MainMenuData');
