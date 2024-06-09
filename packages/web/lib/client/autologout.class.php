<?php
/**
 * Handles auto log information as requested.
 *
 * PHP version 5
 *
 * @category AutoLogout
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles auto log information as requested.
 *
 * @category AutoLogout
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Autologout extends FOGClient
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'autologout';
    /**
     * Stores the data to send
     *
     * @var string
     */
    protected $send;
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json(): array
    {
        $time = self::$Host->getAlo();
        if ($time < 5) {
            return ['error' => 'time'];
        }
        return ['time' => $time * 60];
    }
}
