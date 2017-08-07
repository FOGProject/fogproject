<?php
/**
 * Handles display manager
 *
 * PHP version 5
 *
 * @category DisplayManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles display manager
 *
 * @category DisplayManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DisplayManager extends FOGClient implements FOGClientSend
{
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        return array(
            'x' => self::$Host->getDispVals('width'),
            'y' => self::$Host->getDispVals('height'),
            'r' => self::$Host->getDispVals('refresh'),
        );
    }
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $this->send = base64_encode(
            sprintf(
                '%dx%dx%d',
                self::$Host->getDispVals('width'),
                self::$Host->getDispVals('height'),
                self::$Host->getDispVals('refresh')
            )
        );
    }
}
