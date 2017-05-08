<?php
/**
 * Creates the capone menu item.
 *
 * PHP Version 5
 *
 * @category AddBootMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Creates the capone menu item.
 *
 * @category AddBootMenuItem
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddBootMenuItem extends Hook
{
    /**
     * The name of this hook.
     *
     * @var string
     */
    public $name = 'AddBootMenuItem';
    /**
     * The description of this hook.
     *
     * @var string
     */
    public $description = 'Add capone menu item';
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
    public $node = 'capone';
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
                'BOOT_MENU_ITEM',
                array(
                    $this,
                    'addBootMenuItem'
                )
            );
    }
    /**
     * Creates the storage node.
     *
     * @return void
     */
    public function addBootMenuItem()
    {
        if (!in_array($this->node, (array)self::$pluginsinstalled)) {
            return;
        }
        $dmi = self::getSetting('FOG_PLUGIN_CAPONE_DMI');
        $shutdown = self::getSetting('FOG_PLUGIN_CAPONE_SHUTDOWN');
        if (!$dmi) {
            return;
        }
        $exists = self::getClass('PXEMenuOptionsManager')
            ->exists('fog.capone', '', 'name');
        $args = trim("mode=capone shutdown=$shutdown");
        $entry = self::getClass('PXEMenuOptions')
            ->set('name', 'fog.capone')
            ->load('name');
        if (!$exists) {
            $entry
                ->set('name', 'fog.capone')
                ->set('description', _('Capone Deploy'))
                ->set('args', $args)
                ->set('params', null)
                ->set('default', 0)
                ->set('regMenu', 2);
        }
        $setArgs = explode(' ', trim($entry->get('args')));
        $neededArgs = explode(' ', trim($args));
        $sureArgs = array();
        foreach ((array)$setArgs as &$arg) {
            if (!preg_match('#^dmi=#', $arg)) {
                $sureArgs[] = $arg;
            }
            unset($arg);
        }
        $setArgs = $sureArgs;
        foreach ((array)$neededArgs as &$arg) {
            if (!in_array($arg, $setArgs)) {
                $setArgs[] = $arg;
            }
            unset($arg);
        }
        $setArgs[] = sprintf('dmi=%s', $dmi);
        $entry
            ->set('args', implode(' ', $setArgs))
            ->save();
    }
}
