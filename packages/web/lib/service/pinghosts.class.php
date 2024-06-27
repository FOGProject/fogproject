<?php
/**
 * Gets the current ping code of each host and
 * updates the hosts related to them.
 *
 * PHP version 5
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
                '/opt/fog/log/'
            ),
            (
                $log ?
                $log :
                'pinghost.log'
            )
        );
        if (file_exists(static::$log)) {
            unlink(static::$log);
        }
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
            Route::names('host');
            $hosts = json_decode(Route::getData());
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
            $insert_fields = ['pingstatus', 'id'];
            $insert_values = [];
            foreach ($hosts as $host) {
                self::outall(
                    ' | '
                    . _('Attempting to ping host')
                    . ': '
                    . $host->name
                );
                $ip = self::resolveHostname($host->name);
                self::outall(
                    ' | '
                    . _('IP of host appears to be')
                    . ': '
                    . $ip
                );
                self::outall(
                    ' | '
                    . _('Performing ping')
                );
                $ping = self::getClass('Ping', $ip)
                    ->execute();
                self::outall(
                    ' | '
                    . _('Ping completed with status')
                    . ': '
                    . $ping
                );
                $insert_values[] = [$ping, $host->id];
                self::getClass('HostManager')
                    ->update(
                        ['id' => $host->id],
                        '',
                        ['pingstatus' => $ping]
                    );
            }
            self::outall(
                ' | '
                . _('Ping hosts completed, updating information on all hosts')
            );
            self::getClass('HostManager')->insertBatch(
                $insert_fields,
                $insert_values
            );
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
