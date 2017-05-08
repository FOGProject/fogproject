<?php
/**
 * How to edit the boot menu via hooks.
 *
 * PHP version 5
 *
 * @category BootItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * How to edit the boot menu via hooks.
 *
 * @category BootItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class BootItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var $name
     */
    public $name = 'BootItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Example how to tweak boot menu items.';
    /**
     * Is this hook active.
     *
     * @var bool
     */
    public $active = false;
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
                'IPXE_EDIT',
                array(
                    $this,
                    'tweaktask'
                )
            )
            ->register(
                'IPXE_EDIT',
                array(
                    $this,
                    'tweakmenu'
                )
            );
    }
    /**
     * Tweaks the taskings.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function tweaktask($arguments)
    {
        if ($arguments['ipxe']['task']) {
            $arguments['ipxe']['task'][1] .= " capone=1";
        }
    }
    /**
     * Tweaks the menu.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function tweakmenu($arguments)
    {
        /**
         * This is How the menu get's displayed:
         * 'ipxe' 'head' key's followed by the item.
         */
        if ($arguments['ipxe']['head']) {
            $arguments['ipxe']['head'][0]
                = '#!ipxeishereherherherher';
            $arguments['ipxe']['head'][1]
                = 'cpuid --ext 29 && set arch x86_64 || set arch i386';
            $arguments['ipxe']['head'][2]
                = 'goto get_console';
            $arguments['ipxe']['head'][3]
                = ':console_set';
            $arguments['ipxe']['head'][4]
                = 'colour --rgb 0xff6600 2';
            $arguments['ipxe']['head'][5]
                = 'cpair --foreground 7 --background 2 2';
            $arguments['ipxe']['head'][6]
                = 'goto MENU';
            $arguments['ipxe']['head'][7]
                = ':alt_console';
            $arguments['ipxe']['head'][8]
                = 'cpair --background 0 1 && cpair --background 1 2';
            $arguments['ipxe']['head'][9]
                = 'goto MENU';
            $arguments['ipxe']['head'][10]
                = ':get_console';
            $arguments['ipxe']['head'][11]
                = 'console --picture '
                . $arguments['booturl']
                . ' --left 100 --right 80 --top 80 && goto console_set '
                . '|| goto alt_console';
        }
        // This is the start of the MENU information.
        // 'ipxe' 'menustart' key's followed by the item
        if ($arguments['ipxe']['menustart']) {
            $arguments['ipxe']['menustart'][0]
                = ':MENU';
            $arguments['ipxe']['menustart'][1]
                = 'menu';
            if ($arguments['Host']
                && $arguments['Host']->isValid()
            ) {
                $arguments['ipxe']['menustart'][2]
                    = 'colour --rgb 0x00ff00 0';
                $arguments['ipxe']['menustart'][4]
                    = 'item --gap Host is registered as '
                    . $arguments['Host']->get('name');
            } else {
                $arguments['ipxe']['menustart'][2]
                    = 'colour --rgb 0xff0000 0';
                $arguments['ipxe']['menustart'][4]
                    = 'item --gap Host is NOT registered!';
            }
            $arguments['ipxe']['menustart'][3]
                = 'cpair --foreground 0 3';
            $arguments['ipxe']['menustart'][5]
                = 'item --gap -- -------------------------------------';
        }
        /**
         * The next subset of informations is about the item labels.
         * This is pulled from the db so some common values may be like:
         * item-<label-name>  so fog.local has item value of: item-fog.local
         * inside of the item label is an arrayed item of value [0] containing
         * the label so to tweak:
         */
        foreach ((array)self::getClass('PXEMenuOptionsManager')
            ->find() as $i => &$Menu
        ) {
            if ($arguments['ipxe']['item-'.$Menu->get('name')]
                && $Menu->get('name') == 'fog.local'
            ) {
                $arguments['ipxe']['item-fog.local'][0]
                    = 'item fog.local THIS BOOTS TO DISK';
            }
            /**
             * Similar to the item-<label-name>
             * The choices follow similar constructs
             */
            if ($arguments['ipxe']['choice-'.$Menu->get('name')]
                && $Menu->get('name') == 'fog.local'
            ) {
                $arguments['ipxe']['choice-fog.local'][0]
                    = ':fog.local';
                $arguments['ipxe']['choice-fog.local'][1]
                    = $arguments['bootexittype']
                    . ' || goto MENU';
            }
            unset($Menu);
        }
        // Default item is set to: 'ipxe' 'default'
        if ($arguments['ipxe']['default']) {
            $arguments['ipxe']['default'][0]
                = $arguments['defaultChoice'];
        }
    }
}
