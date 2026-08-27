<?php
/**
 * Pxe menu items manager class.
 *
 * PHP version 7.4+
 *
 * @category PXEMenuOptionsManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Managers;

use FOG\Base\FOGManagerController;

/**
 * Pxe menu items manager class.
 *
 * @category PXEMenuOptionsManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PXEMenuOptionsManager extends FOGManagerController
{
    /**
     * The Storage point for the registration items.
     *
     * @var array
     */
    private static $_regVals = [];
    /**
     * Builds the array.
     *
     * @return array
     */
    private static function _regText()
    {
        return self::$_regVals = [
            0 => self::$foglang['NotRegHost'],
            1 => self::$foglang['RegHost'],
            2 => self::$foglang['AllHosts'],
            3 => self::$foglang['DebugOpts'],
            4 => self::$foglang['AdvancedOpts'],
            5 => self::$foglang['AdvancedLogOpts'],
            6 => self::$foglang['PendRegHost'],
            7 => self::$foglang['DoNotList']
        ];
    }
    /**
     * The menu select list item.
     *
     * @param string $request Which item is currently selected.
     * @param string $id      Should we send an id.
     *
     * @return string
     */
    public function regSelect($request = '', $id = '')
    {
        self::$selected = $request;
        ob_start();
        $sender = self::_regText();
        array_walk(
            $sender,
            self::$buildSelectBox
        );
        return sprintf(
            '<select name="regmenu" class="form-control"'
            . (
                $id ?
                ' id="'
                . $id
                . '"' :
                ''
            )
            . '>%s</select>',
            ob_get_clean()
        );
    }
    /**
     * Simple text output of reg selected.
     *
     * @param int $id The id to return string for.
     *
     * @return string
     */
    public static function regText($id)
    {
        self::_regText();
        return self::$_regVals[$id];
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\PXEMenuOptionsManager', 'PXEMenuOptionsManager');
