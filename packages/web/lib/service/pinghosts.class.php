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
            list($port, $timeout) = self::getSetting(
                [
                    'PINGHOSTPORT',
                    'PINGHOSTTIMEOUT'
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
            foreach ($hosts as $host) {
                $names[$host->id] = $host->name;
                $targets[$host->id] = self::resolveHostname($host->name);
            }
            $started = microtime(true);
            // Static call, like Route::names() above: getClass() is the
            // factory for INSTANTIATING a FOG class, and there is nothing to
            // instantiate here -- executeBatch() is static precisely because
            // a batch has no single host to be constructed around.
            $results = Ping::executeBatch(
                $targets,
                $timeout,
                $port
            );
            $elapsed = microtime(true) - $started;
            // Group by result so the whole cycle costs a handful of UPDATEs
            // instead of one per host. update() turns an array of ids into
            // an IN () clause, and in practice a fleet resolves to two or
            // three distinct codes -- 0, timed out, and refused.
            $byCode = [];
            foreach ($results as $id => $code) {
                $code = (int)$code;
                $byCode[$code][] = $id;
                self::outall(
                    sprintf(
                        ' | %s (%s): %s',
                        $names[$id] ?? $id,
                        $targets[$id],
                        (
                            $code === 0 ?
                            _('online') :
                            socket_strerror($code)
                        )
                    )
                );
            }
            $seen = self::niceDate()->format('Y-m-d H:i:s');
            foreach ($byCode as $code => $ids) {
                $update = ['pingstatus' => $code];
                // lastping records "this host answered", so only a
                // successful connect writes it. A failure must leave the
                // previous value alone -- overwriting it with the time of
                // the failed attempt would make a host that has been off for
                // a month indistinguishable from one that answered a minute
                // ago, which is the whole reason the column exists.
                if ($code === 0) {
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
            $online = count($byCode[0] ?? []);
            self::outall(
                sprintf(
                    ' * %s: %d %s, %d %s (%.2fs)',
                    _('Ping cycle complete'),
                    $online,
                    _('online'),
                    $hostCount - $online,
                    _('not reachable'),
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
