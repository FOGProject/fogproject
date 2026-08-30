<?php
/**
 * Sub menu hook changer.
 *
 * PHP version 7.4+
 *
 * @category SubMenuData
 * @package  FOGProject
 * @author   Peter Gilchrist <nah@nah.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

use FOG\Base\Hook;

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
                    // Sits beside the two things it acts on: Secure Boot
                    // signing is a property of the kernel this server serves.
                    'secureBoot' => _('Secure Boot'),
                    'certificates' => _('Certificates'),
                    'pxemenu' => self::$foglang['PXEBootMenu'],
                    'customizepxe' => self::$foglang['PXEConfiguration'],
                    'newMenu' => self::$foglang['NewMenu'],
                    'maclist' => self::$foglang['MACAddrList'],
                    // Beside FOG Settings, which is where the server-wide
                    // fog-api-token lives -- so both halves of API
                    // credentials are found in one place.
                    'apitokens' => _('API Tokens'),
                    'settings' => self::$foglang['FOGSettings'],
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SubMenuData', 'SubMenuData');
