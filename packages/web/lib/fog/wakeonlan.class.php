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
            $packet = sprintf(
                '%s%s',
                str_repeat(
                    chr(255),
                    6
                ),
                str_repeat(
                    pack(
                        'H12',
                        str_replace(
                            array(
                                '-',
                                ':'
                            ),
                            '',
                            $MAC
                        )
                    ),
                    16
                )
            );
            foreach ((array)$BroadCast as &$SendTO) {
                if (!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))) {
                    throw new Exception(_('Socket error'));
                }
                stream_set_blocking(
                    $sock,
                    false
                );
                $options = socket_set_option(
                    $sock,
                    SOL_SOCKET,
                    SO_BROADCAST,
                    true
                );
                if ($options >= 0
                    && socket_sendto(
                        $sock,
                        $packet,
                        strlen($packet),
                        0,
                        $SendTo,
                        self::WOL_UDP_PORT
                    )
                ) {
                    socket_close($sock);
                }
                unset($SendTo);
            }
            unset($MAC);
        }
    }
}
