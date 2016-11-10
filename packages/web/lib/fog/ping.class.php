<?php
/**
 * Handles pinging hosts.
 *
 * PHP version 5
 *
 * @category Ping
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles pinging hosts.
 *
 * @category Ping
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Ping
{
    /**
     * ICMP Ping packet with a pre-calculated checksum
     * This will always be the same but allow capability for user to change
     * they would like.
     *
     * @var string
     */
    public static $packet = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
    /**
     * The host to ping
     *
     * @var string
     */
    private $_host = '';
    /**
     * The port to use.
     * Netbios port 445 default.
     *
     * @var int
     */
    private $_port = 445;
    /**
     * The time to wait for host.
     *
     * @var int
     */
    private $_timeout = 5;
    /**
     * Initializes the ping class.
     *
     * @param string $host    Host name or IP address to ping.
     * @param int    $timeout Timeout for ping in seconds.
     * @param int    $port    The port to use.
     *
     * @return void
     */
    public function __construct(
        $host,
        $timeout = 2,
        $port = 445
    ) {
        $this->_host = trim($host);
        if (!($timeout
            && is_numeric($timeout))
        ) {
            $timeout = 2;
        }
        if (!($port
            && is_numeric($port))
        ) {
            $port = 445;
        }
        $this->_timeout = $timeout;
        $this->_port = $port;
    }
    /**
     * Use original methods to ping host
     *
     * @param string $host    IP Address or Hostname of host to ping
     * @param int    $timeout Timeout for ping in seconds
     * @param int    $port    Port number to send
     *
     * @return error codes
     */
    protected static function execSend(
        $host,
        $timeout,
        $port
    ) {
        $fsocket = @fsockopen(
            $host,
            $port,
            $errno,
            $errstr,
            $timeout
        );
        if ($fsocket !== false) {
            fclose($fsocket);
        }
        if ($errno === 0 && trim($errstr)) {
            return 6;
        }

        return  $errno;
    }
    /**
     * Execute the ping.
     *
     * @return int
     */
    public function execute()
    {
        return self::execSend(
            $this->_host,
            $this->_timeout,
            $this->_port
        );
    }
}
