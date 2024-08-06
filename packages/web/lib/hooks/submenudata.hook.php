<?php
/**
 * Sub menu hook changer.
 *
 * PHP version 5
 *
 * @category SubMenuData
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Sub menu hook changer.
 *
 * @category SubMenuData
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SubMenuData extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'SubMenuData';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Change all SubMenu data for the new gui';
    /**
     * Is this hook active or not.
     *
     * @var bool
     */
    public $active = false;
    /**
     * The node to interact with.
     *
     * @var string
     */
    public $node = '';
    /**
     * Initializes object.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager->register(
            'SUB_MENULINK_DATA',
            [$this, 'subMenu']
        );
    }
    /**
     * The changer method.
     *
     * @param mixed $arguments The items to change.
     *
     * @return void
     */
    public function subMenu($arguments)
    {
        if (!isset($arguments['node']) || !$arguments['node']) {
            return;
        }
        switch (strtolower($arguments['node'])) {
            case 'home':
            case 'client':
            case 'report':
            case 'schema':
            case 'service':
            case 'hwinfo':
                $arguments['menu'] = [];
                break;
            case 'about':
                $arguments['menu'] = [
                    'home' => self::$foglang['Home'],
                    'license' => self::$foglang['License'],
                    'kernel' => self::$foglang['KernelUpdate'],
                    'initrd' => self::$foglang['InitRdUpdate'],
                    'pxemenu' => self::$foglang['PXEBootMenu'],
                    'customizepxe' => self::$foglang['PXEConfiguration'],
                    'newMenu' => self::$foglang['NewMenu'],
                    'maclist' => self::$foglang['MACAddrList'],
                    'settings' => self::$foglang['FOGSettings'],
                    'logviewer' => self::$foglang['LogViewer'],
                    'config' => self::$foglang['ConfigSave']
                ];
                break;
            case 'plugin':
                $arguments['menu'] = [
                    'home' => self::$foglang['Home'],
                    'activate' => self::$foglang['ActivatePlugins'],
                    'install' => self::$foglang['InstallPlugins'],
                    'installed' => self::$foglang['InstalledPlugins']
                ];
                break;
            case 'task':
                $arguments['menu'] = [
                    'active' => self::$foglang['ActiveTasks'],
                    'activemulticast' => self::$foglang['ActiveMCTasks'],
                    'activesnapins' => self::$foglang['ActiveSnapins'],
                    'activescheduled' => self::$foglang['ScheduledTasks'],
                ];
                break;
            case 'image':
            case 'storagenode':
            case 'storagegroup':
            case 'group':
            case 'host':
            case 'printer':
            case 'snapin':
            case 'user':
            default:
                break;
        }
    }
}
