<?php
class PingHosts extends FOGService {
    public static $sleeptime = 'PINGHOSTSLEEPTIME';
    public function __construct() {
        parent::__construct();
        list($dev,$log,$zzz) = self::getSubObjectIDs('Service',array('name'=>array('PINGHOSTDEVICEOUTPUT','PINGHOSTLOGFILENAME',self::$sleeptime)),'value',false,'AND','name',false,'');
        static::$log = sprintf('%s%s',self::$logpath ? self::$logpath : '/opt/fog/log/',$log ? $log : 'pinghost.log');
        if (file_exists(static::$log)) unlink(static::$log);
        static::$dev = $dev ? $dev : '/dev/tty3';
        static::$zzz = ($zzz ? $zzz : 300);
    }
    private function commonOutput() {
        try {
            if (!self::getSetting('FOG_HOST_LOOKUP')) throw new Exception(_(' * Host Ping is not enabled'));
            $webServerIP = self::$FOGCore->resolveHostName(self::getSetting('FOG_WEB_HOST'));
            self::outall(sprintf(' * FOG Web Host IP: %s',$webServerIP));
            self::getIPAddress();
            if (!in_array($webServerIP,self::$ips)) throw new Exception(_('I am not the fog web server'));
            array_walk(self::$ips,function(&$ip,&$index) {
                if ($index === 0) self::outall(" * This server's IP Addresses");
                self::outall(" |\t$ip");
                unset($ip,$index);
            });
            $hostCount = self::getClass('HostManager')->count();
            self::outall(sprintf(' * %s %s %s%s',_('Attempting to ping'),$hostCount,_('host'),($hostCount != 1 ? 's' : '')));
            $Hosts = self::getClass('HostManager')->find();
            array_walk($Hosts,function(&$Host,&$index) {
                if (!$Host->isValid()) return;
                $Host->setPingStatus();
                unset($Host,$index);
            });
            self::outall(" * All status' have ben updated");
        } catch (Exception $e) {
            self::outall($e->getMessage());
        }
    }
    public function serviceRun() {
        self::out(' ',static::$dev);
        self::out(' +---------------------------------------------------------',static::$dev);
        $this->commonOutput();
        self::out(' +---------------------------------------------------------',static::$dev);
        parent::serviceRun();
    }
}
