<?php
class PingHosts extends FOGService {
    public static $logpath = '';
    public static $dev = '';
    public static $log = '';
    public static $zzz = '';
    public static $sleeptime = 'PINGHOSTSLEEPTIME';
    public function __construct() {
        parent::__construct();
        static::$log = sprintf('%s%s',static::$logpath,static::getSetting('PINGHOSTLOGFILENAME'));
        if (file_exists(static::$log)) @unlink(static::$log);
        static::$dev = static::getSetting('PINGHOSTDEVICEOUTPUT');
        static::$zzz = (int)static::getSetting(static::$sleeptime);
    }
    private function commonOutput() {
        try {
            if (!static::getSetting('FOG_HOST_LOOKUP')) throw new Exception(_(' * Host Ping is not enabled'));
            $webServerIP = static::$FOGCore->resolveHostName(static::getSetting('FOG_WEB_HOST'));
            static::outall(sprintf(' * FOG Web Host IP: %s',$webServerIP));
            $this->getIPAddress();
            foreach ((array)static::$ips AS $i => &$ip) {
                if (!$i) static::outall(" * This server's IP Addresses");
                static::outall(" |\t$ip");
                unset($ip);
            }
            if (!in_array($webServerIP,static::$ips)) throw new Exception(_('I am not the fog web server'));
            $hostCount = static::getClass('HostManager')->count();
            static::outall(sprintf(' * %s %s %s%s',_('Attempting to ping'),static::getClass('HostManager')->count(),_('host'),($hostCount != 1 ? 's' : '')));
            foreach ((array)static::getClass('HostManager')->find() AS $i => &$Host) {
                if (!$Host->isValid()) continue;
                $Host->setPingStatus();
                unset($Host);
            }
            static::outall(' * All status\' have been updated');
        } catch (Exception $e) {
            static::outall($e->getMessage());
        }
    }
    public function serviceRun() {
        static::out(' ',static::$dev);
        static::out(' +---------------------------------------------------------',static::$dev);
        $this->commonOutput();
        static::out(' +---------------------------------------------------------',static::$dev);
    }
}
