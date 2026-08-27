<?php
/**
 * Handles display manager
 *
 * PHP version 7.4+
 *
 * @category DisplayManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Client;

/**
 * Handles display manager
 *
 * @category DisplayManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DisplayManager extends FOGClient
{
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        return [
            'x' => self::$Host->getDispVals('width'),
            'y' => self::$Host->getDispVals('height'),
            'r' => self::$Host->getDispVals('refresh'),
        ];
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\DisplayManager', 'DisplayManager');
