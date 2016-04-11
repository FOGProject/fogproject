<?php
class PingHosts extends FOGService {
    public static $dev = '';
    public static $log = '';
    public static $zzz = '';
    public static $sleeptime = 'PINGHOSTSLEEPTIME';
    public function __construct() {
        parent::__construct();
        static::$log = sprintf('%s%s',self::$logpath,$this->getSetting('PINGHOSTLOGFILENAME'));
        static::$dev = $this->getSetting('PINGHOSTDEVICEOUTPUT');
        static::$zzz = (int)$this->getSetting(static::$sleeptime);
    }
    private function commonOutput() {
        try {
            if (!$this->getSetting('FOG_HOST_LOOKUP')) throw new Exception(_(' * Host Ping is not enabled'));
            $webServerIP = self::$FOGCore->resolveHostName($this->getSetting('FOG_WEB_HOST'));
            static::outall(sprintf(' * FOG Web Host IP: %s',$webServerIP));
            $this->getIPAddress();
            foreach ((array)self::$ips AS $i => &$ip) {
                if (!$i) static::outall(" * This server's IP Addresses");
                static::outall(" |\t$ip");
                unset($ip);
            }
            if (!in_array($webServerIP,self::$ips)) throw new Exception(_('I am not the fog web server'));
            $hostCount = self::getClass('HostManager')->count();
            static::outall(sprintf(' * %s %s %s%s',_('Attempting to ping'),self::getClass('HostManager')->count(),_('host'),($hostCount != 1 ? 's' : '')));
            foreach ((array)self::getClass('HostManager')->find() AS $i => &$Host) {
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
