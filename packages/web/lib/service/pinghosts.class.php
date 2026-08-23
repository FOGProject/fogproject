<?php
/**
 * Gets the current ping code of each host and
 * updates the hosts related to them.
 *
 * PHP version 7.4+
 *
 * @category PingHosts
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * Gets the current ping code of each host and
 * updates the hosts related to them.
 *
 * @category PingHosts
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PingHosts extends FOGService
{
    /**
     * Is the host lookup/ping enabled
     *
     * @var int
     */
    private static $_pingOn = 0;
    /**
     * The fog web host
     *
     * @var string
     */
    private static $_fogWeb = '';
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'PINGHOSTSLEEPTIME';
    /**
     * Initializes the PingHost Class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $pinghostkeys = [
            'FOG_WEB_HOST',
            'PINGHOSTDEVICEOUTPUT',
            'PINGHOSTLOGFILENAME',
            self::$sleeptime
        ];
        list(
            self::$_fogWeb,
            $dev,
            $log,
            $zzz
        ) = self::getSetting($pinghostkeys);
        static::$log = sprintf(
            '%s%s',
            (
                self::$logpath ?
                self::$logpath :
                FOG_LOG_DIR . DS
            ),
            (
                $log ?
                $log :
                'pinghost.log'
            )
        );
        // GH-497: the log used to be deleted here on every start, which threw
        // away the run that led up to a restart -- exactly the one worth
        // reading -- and made `tail -f` useless across a service restart. The
        // file is now appended to, and wlog() rotates it on size instead.
        static::$dev = (
            $dev ?
            $dev :
            '/dev/tty3'
        );
        static::$zzz = (
            $zzz ?
            $zzz :
            300
        );
    }
    /**
     * This is what almost all services have available
     * but is specific to this service
     *
     * @throws Exception
     * @return void
     */
    private function _commonOutput()
    {
        try {
            self::$_pingOn = self::getSetting('PINGHOSTGLOBALENABLED');
            if (self::$_pingOn < 1) {
                throw new \Exception(_(' * Ping hosts is globally disabled'));
            }
            // Read per run rather than in the constructor, like the enabled
            // flag above and unlike the log path and tty: those genuinely
            // need a restart to take effect, these do not, and an admin who
            // changes the port should see it apply on the next cycle.
            list($port, $timeout, $useIcmp) = self::getSetting(
                [
                    'PINGHOSTPORT',
                    'PINGHOSTTIMEOUT',
                    'PINGHOSTUSEICMP'
                ]
            );
            $webServerIP = self::resolveHostName(
                self::$_fogWeb
            );
            self::outall(
                sprintf(' * FOG Web Host IP: %s', $webServerIP)
            );
            self::getIPAddress();
            if (!in_array($webServerIP, self::$ips)) {
                throw new \Exception(
                    _('I am not the fog web server')
                );
            }
            foreach ((array)self::$ips as $index => $ip) {
                if ($index === 0) {
                    self::outall(
                        sprintf(
                            ' * %s',
                            _('This servers ip(s)')
                        )
                    );
                }
                self::outall(" |\t$ip");
            }
            // asValue(): names() has no wrapper of its own -- its payload is
            // a bare list, not a paginated envelope, so there is nothing to
            // unwrap. This is here for the other half, so a failure raises
            // rather than ending the daemon.
            $hosts = Route::asValue(
                function () {
                    Route::names('host');
                }
            );
            $hostCount = count($hosts);
            self::outall(
                sprintf(
                    ' * %s %s %s',
                    _('Attempting to ping'),
                    $hostCount,
                    (
                        $hostCount != 1 ?
                        _('hosts') :
                        _('host')
                    )
                )
            );
            // Do not ping a host that has already told us it is alive.
            //
            // A client check-in is a BETTER liveness signal than the ping:
            // it proves the machine is up AND that the agent works, where
            // the ping only proves something answered a TCP port. It also
            // costs nothing here -- no lookup, no socket -- and
            // FOGClient::__construct() has already set that host's
            // pingstatus to 0, so skipping it changes nothing anyone can
            // see on screen. It only stops the pinger spending a DNS
            // lookup re-deriving a fact it was handed a minute ago.
            //
            // The window is this service's own sleep interval, which is the
            // only self-consistent choice: "did we hear from it since the
            // last time I ran?". FOG_CLIENT_CHECKIN_TIME defaults to 60s
            // against a 300s cycle, so a client-managed host checks in
            // about five times per cycle and is skipped every time.
            //
            // What is left is hosts with no client or a broken one, which is
            // exactly the set the ping is actually for.
            //
            // getIds() answers [] rather than raising if the read fails, so
            // a failure here skips nothing and every host gets pinged --
            // which is the old behaviour, and the safe direction to fail in.
            $cutoff = self::niceDate()
                ->modify(sprintf('-%d seconds', (int)static::$zzz))
                ->format('Y-m-d H:i:s');
            $skip = [];
            $checkins = Route::getIds(
                'host',
                [],
                ['id', 'lastcheckin']
            );
            foreach ((array)$checkins as $row) {
                $when = isset($row['lastcheckin']) ? $row['lastcheckin'] : '';
                // String compare: both sides are 'Y-m-d H:i:s', which sorts
                // lexicographically, so this needs no date parsing per host.
                if ($when && self::validDate($when) && $when > $cutoff) {
                    $skip[(int)$row['id']] = true;
                }
            }
            // Resolution is still one blocking gethostbyname() per host, and
            // with the connects batched it is now the DOMINANT cost of a
            // cycle -- not a rounding error. A name that resolves is cheap; a
            // name that does NOT costs a full resolver timeout, and a fleet
            // whose hosts are not in DNS is the normal case rather than the
            // pathological one.
            //
            // Measured on an 88-host database where 86 names do not resolve:
            // the whole cycle is 5m26s, of which the ping batch is 2s and the
            // rest is here. Batching the connects still cut roughly 3 minutes
            // off that (86 x 2s of connect timeout became one 2s window), but
            // it did not make the cycle fast -- it moved the bottleneck.
            //
            // Left as it is deliberately, for now: core PHP has no
            // asynchronous resolver, so removing this cost means either
            // forking a resolver pool or giving the pinger an address that
            // does not need looking up. The second is the real fix and it is
            // a design decision, not a refactor -- hosts.hostIP exists and is
            // written by nothing at all today.
            $targets = [];
            $names = [];
            $skipped = 0;
            foreach ($hosts as $host) {
                if (isset($skip[(int)$host->id])) {
                    $skipped++;
                    continue;
                }
                $names[$host->id] = $host->name;
                $targets[$host->id] = self::resolveHostname($host->name);
            }
            if ($skipped > 0) {
                self::outall(
                    sprintf(
                        ' * %s: %d',
                        _('Skipped, checked in since the last cycle'),
                        $skipped
                    )
                );
            }
            $started = microtime(true);
            // ICMP first, TCP for whatever it could not answer.
            //
            // The order is the whole point. An echo request asks "is this
            // machine up"; a TCP connect asks "does this machine run a
            // service on the one port we guessed at". The first is the
            // question, so it is asked first, and the connect is only spent
            // on hosts the echo did not reach.
            //
            // A host that answers ICMP is therefore never TCP-probed at all,
            // which also means the cycle gets CHEAPER on a healthy fleet
            // rather than paying for both. A fleet that blocks ICMP pays two
            // batch timeouts instead of one, which is the worst case and is
            // still one timeout per batch rather than per host.
            //
            // Static calls, like Route::names() above: getClass() is the
            // factory for INSTANTIATING a FOG class, and there is nothing to
            // instantiate here -- the batch methods are static precisely
            // because a batch has no single host to be constructed around.
            $method = [];
            $results = [];
            $icmpOn = (int)$useIcmp === 1;
            if ($icmpOn && !Ping::echoAvailable()) {
                // Once per cycle, not once per host, and said out loud
                // rather than degrading quietly: an admin who turned ICMP on
                // and sees no change deserves to know the socket was
                // refused. Answered by opening a socket, because a gid or
                // euid test only says what SHOULD happen -- a container
                // profile can still say no.
                self::outall(
                    sprintf(
                        ' * %s',
                        _(
                            'ICMP is enabled but no echo socket could be '
                            . 'opened; falling back to the TCP check only'
                        )
                    )
                );
                $icmpOn = false;
            }
            if ($icmpOn) {
                foreach (Ping::echoBatch($targets, $timeout) as $id => $up) {
                    $results[$id] = 0;
                    $method[$id] = Ping::METHOD_ICMP;
                }
            }
            // echoBatch() OMITS a host it could not reach rather than
            // recording false, because an unanswered echo means nothing on
            // its own -- plenty of healthy hosts drop them. So the TCP probe
            // gets everything the echo did not positively answer, and it is
            // the TCP result that decides those.
            $tcpTargets = array_diff_key($targets, $results);
            if ($tcpTargets) {
                foreach (
                    Ping::executeBatch($tcpTargets, $timeout, $port)
                    as $id => $code
                ) {
                    $results[$id] = $code;
                    $method[$id] = Ping::METHOD_TCP;
                }
            }
            $elapsed = microtime(true) - $started;
            // Group by result so the whole cycle costs a handful of UPDATEs
            // instead of one per host. update() turns an array of ids into
            // an IN () clause, and in practice a fleet resolves to two or
            // three distinct codes -- 0, timed out, and refused.
            //
            // Grouped by code AND method now that both are stored. That is
            // at most twice as many groups, still a handful, and the pair
            // has to travel together: a host is 0/icmp or 0/tcp and writing
            // one group's method onto the other's rows would say the port
            // answered when the echo did.
            $byResult = [];
            foreach ($results as $id => $code) {
                $code = (int)$code;
                $how = $method[$id] ?? Ping::METHOD_TCP;
                $byResult[$code . '|' . $how][] = $id;
                self::outall(
                    sprintf(
                        ' | %s (%s): %s',
                        $names[$id] ?? $id,
                        $targets[$id],
                        (
                            Ping::METHOD_ICMP === $how ?
                            // Named explicitly: "online" against a fleet
                            // where some hosts answered an echo and others a
                            // TCP connect would hide the difference the
                            // column was added to record.
                            _('online (icmp echo)') :
                            (
                                $code === 0 ?
                                sprintf(
                                    '%s %d',
                                    _('online, tcp port'),
                                    $port
                                ) :
                                // Say both halves for a refused connection:
                                // the errno alone reads as a failure in the
                                // log, and it is the one failure that is
                                // really a success.
                                (
                                    Ping::isAlive($code) ?
                                    sprintf(
                                        '%s (%s)',
                                        _('up, port closed'),
                                        socket_strerror($code)
                                    ) :
                                    socket_strerror($code)
                                )
                            )
                        )
                    )
                );
            }
            $seen = self::niceDate()->format('Y-m-d H:i:s');
            foreach ($byResult as $pair => $ids) {
                list($code, $how) = explode('|', $pair, 2);
                $code = (int)$code;
                $update = [
                    'pingstatus' => $code,
                    // Written on every result, including failures. The
                    // method describes the probe that produced this verdict,
                    // so a host that used to answer ICMP and now answers
                    // nothing must not keep claiming 'icmp' -- a stale
                    // method beside a fresh code is a row that contradicts
                    // itself.
                    'pingmethod' => $how
                ];
                // lastping records "this host answered", so only a result
                // that PROVES the host answered writes it -- which is not
                // the same as a successful connection. A refused connection
                // is a TCP RST from the host's own kernel, so the machine is
                // demonstrably up and merely has nothing listening on the
                // port. Ping::isAlive() owns that judgement for both this
                // and the host grid; see its docblock for the full list.
                //
                // A failure must leave the previous value alone --
                // overwriting it with the time of the failed attempt would
                // make a host that has been off for a month indistinguishable
                // from one that answered a minute ago, which is the whole
                // reason the column exists.
                if (Ping::isAlive($code)) {
                    $update['lastping'] = $seen;
                }
                // Chunked so a large fleet cannot build an IN () list past
                // max_allowed_packet.
                foreach (array_chunk($ids, 500) as $chunk) {
                    // Scoped UPDATE only: this affects 0 rows if a host was
                    // deleted mid-cycle. Do NOT use insertBatch here -- its
                    // INSERT ... ON DUPLICATE KEY UPDATE would resurrect a
                    // deleted host as a blank, nameless row.
                    self::getClass('HostManager')
                        ->update(
                            ['id' => $chunk],
                            '',
                            $update
                        );
                }
            }
            // Counted by "was the host alive", not by "was the port open",
            // so the summary agrees with what got a lastping stamp above.
            $alive = 0;
            foreach ($byResult as $pair => $ids) {
                if (Ping::isAlive((int)strstr($pair, '|', true))) {
                    $alive += count($ids);
                }
            }
            self::outall(
                sprintf(
                    ' * %s: %d %s, %d %s, %d %s (%.2fs)',
                    _('Ping cycle complete'),
                    $alive,
                    _('up'),
                    count($results) - $alive,
                    _('not reachable'),
                    $skipped,
                    _('skipped'),
                    $elapsed
                )
            );
        } catch (\Exception $e) {
            self::outall($e->getMessage());
        }
    }
    /**
     * This is what essentially "runs" the service
     *
     * @return void
     */
    public function serviceRun()
    {
        $this->_commonOutput();
        parent::serviceRun();
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\PingHosts', 'PingHosts');
