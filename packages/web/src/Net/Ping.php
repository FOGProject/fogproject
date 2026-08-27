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

namespace FOG\Net;

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
     * Which probe produced a stored hostPingCode. Constants rather than
     * literals because the service writes them, the host grid reads them
     * and the model names the column -- three files agreeing on a spelling
     * by hand is the sort of thing that fails silently in one of them.
     *
     * @var string
     */
    const METHOD_ICMP = 'icmp';
    const METHOD_TCP = 'tcp';
    /**
     * ICMP Ping packet with a pre-calculated checksum
     * This will always be the same but allow capability for user to change
     * they would like.
     *
     * Nothing sends this. It predates echoBatch() and is kept because it is
     * public and a plugin may reference it -- but the packet a live ping
     * uses is built by echoPacket(), which sets a per-request identifier
     * and sequence. This one has both zeroed, so replies to it could not be
     * matched to a target. Do not reach for it.
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
     * Does this result prove the host was ALIVE, whatever the port did?
     *
     * The distinction the whole ping rests on, and it is not "did the
     * connection succeed". This is a TCP connect to ONE port, so a
     * successful connection proves the host is up AND running a service
     * there -- two facts, of which only the first is being asked about.
     *
     * ECONNREFUSED is the second way to learn the first fact. The host's own
     * kernel answered with a TCP RST, which it can only do if it is powered
     * on, on the network, and routable from here. Nothing was listening on
     * the port; the MACHINE is up. Recording that as "unreachable" is
     * exactly the report that made a Linux host on port 445, or a Windows
     * host on port 22, permanently look switched off.
     *
     * Everything else stays not-alive, and each for a reason:
     *
     *   ETIMEDOUT      nothing came back. Off, or a firewall DROPping rather
     *                  than rejecting. Genuinely unknown, so not alive.
     *   EHOSTUNREACH   a ROUTER said so, not the host.
     *   ENETUNREACH    no route at all.
     *   ENXIO          the name did not resolve, so nothing was contacted.
     *
     * The one false positive to know about: a middlebox configured to REJECT
     * on a host's behalf sends the same RST, so "alive" would mean "the
     * firewall in front of it is alive". A firewall that DROPs -- the common
     * default -- still times out and is still reported correctly. That is a
     * better trade than the alternative, which mislabels every correctly
     * functioning host that happens not to run the chosen service.
     *
     * Single-sourced deliberately. The service decides whether to stamp
     * hostLastPing with this, and the host grid decides how to draw the
     * badge with it; two copies of "is this alive" would drift, and the
     * failure when they do is silent -- a grid that says up next to a
     * timestamp that never advances.
     *
     * @param int|string|null $code the stored hostPingCode
     *
     * @return bool
     */
    public static function isAlive($code)
    {
        if ($code === null || $code === '') {
            return false;
        }

        return 0 === (int)$code || SOCKET_ECONNREFUSED === (int)$code;
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
    /**
     * Test many hosts with a real ICMP echo request.
     *
     * The probe executeBatch() always should have been. A TCP connect asks
     * "does this machine run a service on the one port we guessed at";
     * an echo request asks "is this machine up", which is the question.
     *
     * Same batching shape as executeBatch(), for the same reason: every
     * request goes out first, then ONE deadline covers all the replies, so
     * a chunk of unreachable hosts costs $timeout rather than $timeout each.
     *
     * TWO SOCKET MODES, tried in that order:
     *
     *   SOCK_DGRAM + IPPROTO_ICMP  the unprivileged "ping socket". Needs the
     *                              caller's gid inside
     *                              net.ipv4.ping_group_range, which Fedora
     *                              ships wide open and Debian/Ubuntu ship as
     *                              "1 0" -- nobody. The KERNEL owns the
     *                              identifier field here and rewrites it, so
     *                              replies can only be matched on sequence.
     *   SOCK_RAW + IPPROTO_ICMP    needs root or CAP_NET_RAW. FOGPingHosts
     *                              runs as root under both systemd and
     *                              OpenRC, so this is the guaranteed path
     *                              today; the DGRAM attempt is there so the
     *                              service can be de-privileged later
     *                              without touching this code.
     *
     * Preferring DGRAM is not only about privilege. A RAW socket receives
     * EVERY ICMP packet the host sees, including replies to other processes'
     * pings, so its replies must be matched on identifier AND sequence --
     * the kernel demultiplexes a DGRAM socket and does no such thing for
     * RAW. Both are handled below; conflating them is how a busy server
     * starts reporting hosts up because something else pinged them.
     *
     * IPv4 only. ICMPv6 is a different protocol number with different
     * message types and is not attempted -- a v6 target is returned as
     * unanswered so the caller falls through to the TCP path, which does
     * support AF_INET6.
     *
     * @param array $targets     id => IP address. Keys are returned
     *                           untouched so the caller can map results back.
     * @param int   $timeout     Seconds to wait for a chunk.
     * @param int   $concurrency Requests in flight at once.
     *
     * @return array id => true for every host that replied. Hosts that did
     *               not reply are ABSENT rather than false, so the caller
     *               cannot mistake "no answer" for a verdict -- an
     *               unanswered echo means nothing on its own, and the TCP
     *               probe is what decides.
     */
    public static function echoBatch(
        array $targets,
        $timeout = 2,
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
        if (!($concurrency && is_numeric($concurrency)) || $concurrency < 1) {
            $concurrency = 128;
        }
        $pending = [];
        foreach ($targets as $key => $target) {
            $target = trim((string)$target);
            if (!filter_var($target, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }
            $pending[$key] = $target;
        }
        if (!$pending) {
            return [];
        }
        $results = [];
        foreach (array_chunk($pending, (int)$concurrency, true) as $chunk) {
            $results += self::_echoChunk($chunk, (int)$timeout);
        }

        return $results;
    }
    /**
     * Is an ICMP echo socket available to this process at all?
     *
     * Answered by opening one, because every other way of asking is a
     * guess: the gid test for ping_group_range, the euid test for RAW and a
     * capability check all say what SHOULD happen, and a container profile
     * or an LSM can still refuse. The caller logs this once per cycle
     * rather than once per host.
     *
     * @return bool
     */
    public static function echoAvailable()
    {
        list($sock) = self::_openEcho();
        if (null === $sock) {
            return false;
        }
        socket_close($sock);

        return true;
    }
    /**
     * Opens the best ICMP socket available.
     *
     * @return array [resource|null, bool true when the socket is RAW]
     */
    private static function _openEcho()
    {
        if (!extension_loaded('sockets')) {
            return [null, false];
        }
        $proto = getprotobyname('icmp');
        if (false === $proto) {
            return [null, false];
        }
        $sock = @socket_create(AF_INET, SOCK_DGRAM, $proto);
        if (false !== $sock) {
            return [$sock, false];
        }
        socket_clear_error();
        $sock = @socket_create(AF_INET, SOCK_RAW, $proto);
        if (false !== $sock) {
            return [$sock, true];
        }
        socket_clear_error();

        return [null, false];
    }
    /**
     * Builds an ICMP echo request with a correct checksum.
     *
     * One's complement of the one's complement sum of the message as 16-bit
     * words, computed with the checksum field zeroed. A wrong checksum does
     * not error anywhere -- the host silently discards the packet, which is
     * indistinguishable from a host that is switched off, so this is worth
     * having a test of its own.
     *
     * @param int $id  Identifier. Overwritten by the kernel on a DGRAM
     *                 socket; ours to use on a RAW one.
     * @param int $seq Sequence number, one per target in a chunk.
     *
     * @return string
     */
    public static function echoPacket($id, $seq)
    {
        $id = (int)$id & 0xffff;
        $seq = (int)$seq & 0xffff;
        $payload = 'FOGPING';
        $sum = self::_checksum(pack('C2n3', 8, 0, 0, $id, $seq) . $payload);

        return pack('C2n3', 8, 0, $sum, $id, $seq) . $payload;
    }
    /**
     * The internet checksum of a buffer.
     *
     * @param string $buf the bytes to sum
     *
     * @return int
     */
    private static function _checksum($buf)
    {
        // Odd-length buffers are padded with a zero byte for the sum only;
        // the padding is not part of the packet.
        if (strlen($buf) % 2) {
            $buf .= "\x00";
        }
        $sum = 0;
        foreach (unpack('n*', $buf) as $word) {
            $sum += $word;
        }
        while ($sum >> 16) {
            $sum = ($sum & 0xffff) + ($sum >> 16);
        }

        return ~$sum & 0xffff;
    }
    /**
     * Reads the echo reply out of a received datagram, or null.
     *
     * Split out because it is the half worth testing without a network. Two
     * traps live here:
     *
     *   - a RAW socket hands back the IP header too, and its length is the
     *     IHL field times four. It is 20 bytes in practice and assuming so
     *     is how a reply gets mis-parsed the first time someone sends
     *     options.
     *   - a RAW socket sees every ICMP packet on the machine, so a reply
     *     that is not ours -- wrong type, or another process' identifier --
     *     must be ignored rather than credited to whichever host happens to
     *     hold that sequence number.
     *
     * @param string $buf   the received bytes
     * @param bool   $raw   was this read from a RAW socket
     * @param int    $id    our identifier, checked only when $raw
     *
     * @return int|null the sequence number of our reply, or null
     */
    public static function echoReplySeq($buf, $raw, $id)
    {
        $offset = 0;
        if ($raw) {
            if (strlen($buf) < 1) {
                return null;
            }
            $offset = (ord($buf[0]) & 0x0f) * 4;
        }
        if (strlen($buf) < $offset + 8) {
            return null;
        }
        $hdr = @unpack('Ctype/Ccode/nchecksum/nident/nsequence', substr($buf, $offset, 8));
        if (!is_array($hdr) || 0 !== $hdr['type']) {
            return null;
        }
        // The identifier is only ours to check on a RAW socket. On a DGRAM
        // one the kernel chose it, so comparing would reject every reply.
        if ($raw && ((int)$id & 0xffff) !== (int)$hdr['ident']) {
            return null;
        }

        return (int)$hdr['sequence'];
    }
    /**
     * Sends one chunk of echo requests and collects the replies.
     *
     * @param array $chunk   key => IPv4 address
     * @param int   $timeout seconds for the whole chunk
     *
     * @return array key => true for each host that replied
     */
    private static function _echoChunk(array $chunk, $timeout)
    {
        list($sock, $raw) = self::_openEcho();
        if (null === $sock) {
            return [];
        }
        socket_set_nonblock($sock);
        $id = getmypid() & 0xffff;
        // Sequence numbers are per chunk and start at 1, so they cannot
        // collide inside the one window a socket is open for -- a chunk is
        // $concurrency targets and that is far below the 16-bit field.
        $seqOf = [];
        $seq = 0;
        foreach ($chunk as $key => $ip) {
            $seq++;
            $packet = self::echoPacket($id, $seq);
            if (false === @socket_sendto(
                $sock,
                $packet,
                strlen($packet),
                0,
                $ip,
                0
            )) {
                socket_clear_error($sock);
                continue;
            }
            $seqOf[$seq] = $key;
        }
        $results = [];
        $deadline = microtime(true) + $timeout;
        while ($seqOf) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                break;
            }
            $read = [$sock];
            $write = null;
            $except = null;
            $sec = (int)$remaining;
            $usec = (int)(($remaining - $sec) * 1000000);
            $ready = @socket_select($read, $write, $except, $sec, $usec);
            if (false === $ready) {
                // Interrupted by a signal -- service_persist installs SIGCHLD
                // handling in every daemon. Nothing has failed; go round
                // again against the same deadline.
                socket_clear_error();
                continue;
            }
            if (0 === $ready) {
                break;
            }
            $buf = '';
            $from = '';
            $port = 0;
            if (false === @socket_recvfrom($sock, $buf, 1500, 0, $from, $port)) {
                socket_clear_error($sock);
                continue;
            }
            $gotSeq = self::echoReplySeq($buf, $raw, $id);
            if (null === $gotSeq || !isset($seqOf[$gotSeq])) {
                // Not ours, or a duplicate. Keep waiting -- the deadline is
                // what ends this loop, not the first unrecognised packet.
                continue;
            }
            $results[$seqOf[$gotSeq]] = true;
            unset($seqOf[$gotSeq]);
        }
        socket_close($sock);

        return $results;
    }
}
