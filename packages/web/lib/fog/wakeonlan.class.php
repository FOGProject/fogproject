<?php
/**
 * Wake on lan management class
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
 * Wake on lan management class
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
     * Constant udp port default is 9
     *
     * @var int
     */
    const WOL_UDP_PORT = 9;
    /**
     * MAC Array holder
     *
     * @var array
     */
    private $_arrMAC;
    /**
     * The initializer
     *
     * @param mixed $mac the mac or macs to use
     *
     * @return void
     */
    public function __construct($mac)
    {
        parent::__construct();
        $this->_arrMAC = $this->parseMacList($mac, true);
    }
    /**
     * Send the requests
     *
     * @return void
     */
    public function send()
    {
        if ($this->_arrMAC === false || !count($this->_arrMAC)) {
            throw new Exception(self::$foglang['InvalidMAC']);
        }
        $BroadCast = array_merge(
            (array)'255.255.255.255',
            self::$FOGCore->getBroadcast()
        );
        self::$HookManager->processEvent(
            'BROADCAST_ADDR',
            array(
                'broadcast' => &$BroadCast
            )
        );
        foreach ((array)$this->_arrMAC as &$mac) {
            $addr_byte = explode(':', $mac->__toString());
            for ($a = 0; $a < 6; $a++) {
                $hw_addr .= chr(hexdec($addr_byte[$a]));
            }
            $packet = str_repeat(chr(255), 6);
            for ($a = 0; $a < 16; $a++) {
                $packet .= $hw_addr;
            }
            foreach ((array)$BroadCast as &$SendTo) {
                sleep(1);
                $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                if ($sock == false) {
                    continue;
                }
                $set_opt = @socket_set_option(
                    $sock,
                    SOL_SOCKET,
                    SO_BROADCAST,
                    true
                );
                if ($set_opt < 0) {
                    continue;
                }
                $sendto = socket_sendto(
                    $sock,
                    $packet,
                    strlen($packet),
                    0,
                    $SendTo,
                    self::WOL_UDP_PORT
                );
                if ($sendto) {
                    socket_close($sock);
                }
                unset($SendTo);
            }
            unset($mac);
        }
    }
}
