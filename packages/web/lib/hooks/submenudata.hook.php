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
    public $active = true;
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
        self::$HookManager
            ->register(
                'SUB_MENULINK_DATA',
                array(
                    $this,
                    'subMenu'
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
    public function subMenu($arguments)
    {
        if (!$arguments['node']) {
            return;
        }
        switch (strtolower($arguments['node'])) {
        case 'home':
            $arguments['menu'] = array();
            break;
        case 'client':
            $arguments['menu'] = array();
            break;
        case 'about':
            $arguments['menu'] = array(
                'home' => self::$foglang['Home'],
                'license' => self::$foglang['License'],
                'kernelUpdate' => self::$foglang['KernelUpdate'],
                'pxemenu' => self::$foglang['PXEBootMenu'],
                'customizepxe' => self::$foglang['PXEConfiguration'],
                'newMenu' => self::$foglang['NewMenu'],
                'clientupdater' => self::$foglang['ClientUpdater'],
                'maclist' => self::$foglang['MACAddrList'],
                'settings' => self::$foglang['FOGSettings'],
                'logviewer' => self::$foglang['LogViewer'],
                'config' => self::$foglang['ConfigSave'],
            
            );
            break;
        case 'group':
            break;
        case 'host':
            break;
        case 'image':
            $arguments['menu']['multicast'] = sprintf(
                '%s %s',
                self::$foglang['Multicast'],
                self::$foglang['Image']
            );
            break;
        case 'plugin':
            $arguments['menu'] = array(
                'home'=>self::$foglang['Home'],
                'activate'=>self::$foglang['ActivatePlugins'],
                'install'=>self::$foglang['InstallPlugins'],
                'installed'=>self::$foglang['InstalledPlugins'],
            );
            break;
        case 'printer':
            break;
        case 'report':
            $arguments['menu'] = array();
            break;
        case 'schema':
            $arguments['menu'] = array();
            break;
        case 'service':
            $arguments['menu'] = array();
            break;
        case 'snapin':
            break;
        case 'storage':
            $arguments['menu'] = array(
                'list' => self::$foglang['AllSN'],
                'addStorageNode' => self::$foglang['AddSN'],
                'storageGroup' => self::$foglang['AllSG'],
                'addStorageGroup' => self::$foglang['AddSG'],
            );
            break;
        case 'task':
            $arguments['menu'] = array(
                'active' => self::$foglang['ActiveTasks'],
                'activemulticast' => self::$foglang['ActiveMCTasks'],
                'activesnapins' => self::$foglang['ActiveSnapins'],
                'activescheduled' => self::$foglang['ScheduledTasks'],
            );
            break;
        case 'hwinfo':
            $arguments['menu'] = array();
            break;
        case 'user':
            break;
        default:
            break;
        }
    }
}
