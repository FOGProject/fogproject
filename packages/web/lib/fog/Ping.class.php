<?php
class Ping {
    // ICMP Ping packet with a pre-calculated checksum
    public static $packet = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
    private $host;
    private $port = '445';	// Microsoft netbios port
    private $timeout;
    /**
     * @function __construct() Send a ping request to a host.
     *
     * @param string $host Host name or IP address to ping
     * @param string int $timeout Timeout for ping in seconds
     * @return bool true if ping succeeds, false if not
     */
    public function __construct($host, $timeout = 2,$port = 445) {
        $this->host = trim($host);
        if (!$timeout || !is_numeric($timeout)) $timeout = 2;
        if (!$port || !is_numeric($port)) $port = 445;
        $this->timeout = $timeout;
        $this->port = $port;
    }
    /**
     * @function sockErrToString() error code to string
     * @param $errCode the code to translate
     * @returns the error string
     */
    protected static function sockErrToString() {
    }
    /**
     * @function execSend()
     * Use original methods to ping host
     * @param string $host IP Address or Hostname of host to ping
     * @param int $timeout Timeout for ping in seconds
     * @param int $port Port number to send
     * @return error codes
     */
    protected static function execSend($host,$timeout,$port) {
        $fsocket = @fsockopen($host,$port,$errno,$errstr,$timeout);
        if (!$fsocket) $status = 111;
        else $status = $errno;
        @fclose($fsocket);
        return ($errno == 0 ? true : $errno);
    }
    public function execute() {
        return self::execSend($this->host,$this->timeout,$this->port);
    }
}
