<?php
class PingHosts extends FOGService {
    public static $logpath = '';
    public static $dev = '';
    public static $log = '';
    public static $zzz = '';
    public static $sleeptime = 'PINGHOSTSLEEPTIME';
    public function __construct() {
        parent::__construct();
        self::$log = sprintf('%s%s',self::$logpath,self::getSetting('PINGHOSTLOGFILENAME'));
        if (file_exists(self::$log)) @unlink(self::$log);
        self::$dev = self::getSetting('PINGHOSTDEVICEOUTPUT');
        self::$zzz = (int)self::getSetting(self::$sleeptime);
    }
    private function commonOutput() {
        try {
            if (!self::getSetting('FOG_HOST_LOOKUP')) throw new Exception(_(' * Host Ping is not enabled'));
            $webServerIP = self::$FOGCore->resolveHostName(self::getSetting('FOG_WEB_HOST'));
            self::outall(sprintf(' * FOG Web Host IP: %s',$webServerIP));
            $this->getIPAddress();
            foreach ((array)self::$ips AS $i => &$ip) {
                if (!$i) self::outall(" * This server's IP Addresses");
                self::outall(" |\t$ip");
                unset($ip);
            }
            if (!in_array($webServerIP,self::$ips)) throw new Exception(_('I am not the fog web server'));
            $hostCount = self::getClass('HostManager')->count();
            self::outall(sprintf(' * %s %s %s%s',_('Attempting to ping'),self::getClass('HostManager')->count(),_('host'),($hostCount != 1 ? 's' : '')));
            foreach ((array)self::getClass('HostManager')->find() AS $i => &$Host) {
                if (!$Host->isValid()) continue;
                $Host->setPingStatus();
                unset($Host);
            }
            self::outall(' * All status\' have been updated');
        } catch (Exception $e) {
            self::outall($e->getMessage());
        }
    }
    public function serviceRun() {
        self::out(' ',self::$dev);
        self::out(' +---------------------------------------------------------',self::$dev);
        $this->commonOutput();
        self::out(' +---------------------------------------------------------',self::$dev);
    }
}
