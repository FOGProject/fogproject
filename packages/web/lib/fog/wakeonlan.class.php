<?php
/**
 * Wake on lan management class.
 *
 * PHP version 5
 *
 * @category WakeOnLan
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Wake on lan management class.
 *
 * @category WakeOnLan
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class WakeOnLan extends FOGBase
{
    /**
     * UDP port default is 9.
     *
     * @var int
     */
    private static $_port = 9;
    /**
     * MAC Array holder.
     *
     * @var array
     */
    private static $_arrMAC;
    /**
     * The initializer.
     *
     * @param mixed $mac the mac or macs to use
     */
    public function __construct($mac)
    {
        parent::__construct();
        self::$_arrMAC = self::parseMacList($mac, true);
    }
    /**
     * Send the requests.
     *
     * @return void
     */
    public function send()
    {
        if (self::$_arrMAC === false
            || count(self::$_arrMAC) < 0
        ) {
            throw new Exception(self::$foglang['InvalidMAC']);
        }
        $BroadCast = self::fastmerge(
            (array) '255.255.255.255',
            self::getBroadcast()
        );
        self::$HookManager->processEvent(
            'BROADCAST_ADDR',
            array(
                'broadcast' => &$BroadCast,
            )
        );
        foreach ((array) self::$_arrMAC as &$mac) {
            foreach ((array) $BroadCast as &$SendTo) {
                $mac->wake($SendTo, self::$_port);
                unset($SendTo);
            }
            unset($mac);
        }
    }
}
