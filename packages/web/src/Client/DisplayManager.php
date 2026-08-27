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
