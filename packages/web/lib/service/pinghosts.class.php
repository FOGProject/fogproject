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
        // By name, not by position -- see the note in MulticastManager's
        // constructor (issue #728). getSubObjectIDs() returns only the rows
        // that exist, so one missing Service row shifted every later value
        // left and left the last undefined.
        self::$_fogWeb = self::getSetting('FOG_WEB_HOST');
        $dev = self::getSetting('PINGHOSTDEVICEOUTPUT');
        $log = self::getSetting('PINGHOSTLOGFILENAME');
        $zzz = self::getSetting(self::$sleeptime);
        static::$log = sprintf(
            '%s%s',
            (
                self::$logpath ?
                self::$logpath :
                '/opt/fog/log/'
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
                throw new Exception(_(' * Ping hosts is globally disabled'));
            }
            $webServerIP = self::resolveHostName(
                self::$_fogWeb
            );
            self::outall(
                sprintf(' * FOG Web Host IP: %s', $webServerIP)
            );
            self::getIPAddress();
            if (!in_array($webServerIP, self::$ips)) {
                throw new Exception(
                    _('I am not the fog web server')
                );
            }
            foreach ((array)self::$ips as $index => &$ip) {
                if ($index === 0) {
                    self::outall(
                        sprintf(
                            ' * %s',
                            _('This servers ip(s)')
                        )
                    );
                }
                self::outall(" |\t$ip");
                unset($ip, $index);
            }
            $hostCount = self::getClass('HostManager')->count();
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
            $hostids = (array)self::getsubObjectIDs('Host');
            $hostnames = (array)self::getSubObjectIDs(
                'Host',
                array('id' => $hostids),
                'name'
            );
            $hostips = (array)self::getSubObjectIDs(
                'Host',
                array(
                    'id' => $hostids,
                    'name' => $hostnames
                ),
                'ip',
                false,
                'AND',
                'name',
                false,
                ''
            );
            foreach ((array)$hostids as $index => &$hostid) {
                if (false === array_key_exists($index, $hostips)
                    || false === array_key_exists($index, $hostnames)
                ) {
                    continue;
                }
                $ip = $hostips[$index];
                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    $ip = self::resolveHostname($hostnames[$index]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    $ip = $hostnames[$index];
                }
                unset($hostnames[$index], $hostips[$index]);
                $ping = self::getClass('Ping', $ip)
                    ->execute();
                self::getClass('HostManager')
                    ->update(
                        array('id' => $hostid),
                        '',
                        array('pingstatus' => $ping)
                    );
                unset($hostid, $hostids[$index]);
            }
            self::outall(' * All hosts updated');
        } catch (Exception $e) {
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
