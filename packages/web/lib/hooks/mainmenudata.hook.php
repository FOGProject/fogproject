<?php
/**
 * Main menu hook changer.
 *
 * @category MainMenuData
 * @package  FOGProject
 * @author   Sebastian Roth
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
    public $active = true;
    /**
     * Position of the new main menu entry.
     *
     * @var int
     */
    public $position = 3;
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
    public $menuitem = 'fa-paperclip';
    /**
     * Initializes object.
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
                    'addToMainMenu'
                )
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
        $arguments['main'] = array_merge(
            array_slice($arguments['main'], 0, $this->position),
            array($link => array($this->menuitem, 'fa '.$this->icon.' fa-2x')),
            array_slice($arguments['main'], $this->position)
        );

    }
}
