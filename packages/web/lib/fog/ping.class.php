<?php
/**
 * Handles pinging hosts.
 *
 * PHP version 7.4+
 *
 * @category Ping
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

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
    /**
     * Test many hosts at once and return each one's errno.
     *
     * execute() above tests exactly one host and blocks for up to $timeout
     * doing it, which is fine for the single-host callers but is what made
     * FOGPingHosts unable to finish a cycle: 500 powered-off hosts is 500 x
     * 2s = ~17 minutes against a 300s sleep, so the service simply ran
     * continuously and always lagged. This opens every connection in a chunk
     * at once and waits for all of them together, so the wall clock for a
     * chunk is one timeout rather than one timeout per host.
     *
     * The errno vocabulary is deliberately identical to execute()'s, because
     * hostPingCode rows written by the old code are still on disk and the
     * host grid renders both through socket_strerror(). SO_ERROR on a
     * finished non-blocking connect is the same errno the blocking connect
     * inside fsockopen() would have reported, and an unresolvable name is
     * still ENXIO (6) -- see the resolution note below.
     *
     * Requires ext-sockets, which is not a new dependency: the installer
     * enables it (lib/common/functions.sh) and the host grid already calls
     * socket_strerror() unguarded. It is checked explicitly rather than
     * wrapped in a try/catch with a fallback -- two implementations of the
     * same measurement would drift, and a silent fallback would turn a
     * missing extension into plausible-looking wrong data instead of a
     * message saying what to install.
     *
     * @param array $targets     id => host name or IP. Keys are returned
     *                           untouched so the caller can map results back.
     * @param int   $timeout     Seconds to wait for a chunk.
     * @param int   $port        TCP port to connect to.
     * @param int   $concurrency Sockets open at once. Kept well under a
     *                           typical `ulimit -n` of 1024 because each
     *                           pending connect holds a file descriptor.
     *
     * @throws Exception
     *
     * @return array id => errno, 0 meaning the host answered
     */
    public static function executeBatch(
        array $targets,
        $timeout = 2,
        $port = 445,
        $concurrency = 128
    ) {
        if (!extension_loaded('sockets')) {
            throw new \Exception(
                _('The PHP sockets extension is required to ping hosts')
            );
        }
        if (!($timeout && is_numeric($timeout)) || $timeout < 1) {
            $timeout = 2;
        }
        if (!($port && is_numeric($port))) {
            $port = 445;
        }
        if (!($concurrency && is_numeric($concurrency)) || $concurrency < 1) {
            $concurrency = 128;
        }
        $timeout = (int)$timeout;
        $port = (int)$port;
        $results = [];
        $pending = [];
        foreach ($targets as $key => $target) {
            $target = trim((string)$target);
            // gethostbyname() hands back the name it was given when
            // resolution fails, so a value that is not an IP by this point
            // is a DNS failure. The old code still opened a socket to that
            // name; fsockopen() then returned errno 0 with an errstr set,
            // which execSend() translated to 6. Recording 6 directly is the
            // same answer without the wasted connect.
            if (!filter_var($target, FILTER_VALIDATE_IP)) {
                $results[$key] = SOCKET_ENXIO;
                continue;
            }
            $pending[$key] = $target;
        }
        foreach (array_chunk($pending, $concurrency, true) as $chunk) {
            $results += self::_connectChunk($chunk, $timeout, $port);
        }

        return $results;
    }
    /**
     * Open every connection in one chunk at once and wait for them together.
     *
     * @param array $chunk   id => IP address
     * @param int   $timeout Seconds to wait for the whole chunk
     * @param int   $port    TCP port to connect to
     *
     * @return array id => errno
     */
    private static function _connectChunk(array $chunk, $timeout, $port)
    {
        $results = [];
        $sockets = [];
        foreach ($chunk as $key => $ip) {
            $domain = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6
            ) ? AF_INET6 : AF_INET;
            $sock = @socket_create($domain, SOCK_STREAM, SOL_TCP);
            if ($sock === false) {
                $results[$key] = socket_last_error();
                socket_clear_error();
                continue;
            }
            socket_set_nonblock($sock);
            // Expected to return false with EINPROGRESS -- the connect is
            // still running. A true return is a same-host connect that
            // completed before the call returned, which is a success.
            if (@socket_connect($sock, $ip, $port) === true) {
                $results[$key] = 0;
                socket_close($sock);
                continue;
            }
            $err = socket_last_error($sock);
            socket_clear_error($sock);
            if ($err !== SOCKET_EINPROGRESS && $err !== SOCKET_EALREADY) {
                // Refused or unroutable before the call even returned.
                $results[$key] = $err;
                socket_close($sock);
                continue;
            }
            $sockets[$key] = $sock;
        }
        // One deadline for the whole chunk, not one per socket. That is the
        // entire point: a chunk of 128 unreachable hosts costs $timeout, not
        // 128 x $timeout.
        $deadline = microtime(true) + $timeout;
        while (count($sockets) > 0) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                break;
            }
            $read = [];
            $write = array_values($sockets);
            $except = array_values($sockets);
            $sec = (int)$remaining;
            $usec = (int)(($remaining - $sec) * 1000000);
            $ready = @socket_select($read, $write, $except, $sec, $usec);
            if ($ready === false) {
                // Interrupted by a signal (service_persist installs SIGCHLD
                // handling in every daemon). Nothing has failed -- go round
                // again against the same deadline.
                socket_clear_error();
                continue;
            }
            if ($ready === 0) {
                break;
            }
            $signalled = array_merge($write, $except);
            foreach ($sockets as $key => $sock) {
                if (!in_array($sock, $signalled, true)) {
                    continue;
                }
                // A non-blocking connect reports its outcome here rather
                // than as a return value: 0 means it completed, anything
                // else is the errno the blocking call would have returned.
                $err = socket_get_option($sock, SOL_SOCKET, SO_ERROR);
                $results[$key] = ($err === false ? SOCKET_ECONNREFUSED : $err);
                socket_close($sock);
                unset($sockets[$key]);
            }
        }
        // Whatever never answered ran out the clock.
        foreach ($sockets as $key => $sock) {
            $results[$key] = SOCKET_ETIMEDOUT;
            socket_close($sock);
        }

        return $results;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Ping', 'Ping');
