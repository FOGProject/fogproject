<?php
class PingHosts extends FOGService {
    public $dev = PINGHOSTDEVICEOUTPUT;
    public $log = PINGHOSTLOGPATH;
    public $zzz = PINGHOSTSLEEPTIME;
    private function commonOutput() {
        try {
            if (!$this->getSetting('FOG_HOST_LOOKUP')) throw new Exception(_('Host Ping is not enabled'));
            $webServerIP = $this->FOGCore->resolveHostName($this->getSetting('FOG_WEB_HOST'));
            $this->outall(sprintf(' * FOG Web Host IP: %s',$webServerIP));
            $this->getIPAddress();
            foreach ((array)$this->ips AS $i => &$ip) {
                if (!$i) $this->outall(" * This server's IP Addresses");
                $this->outall(" |\t$ip");
                unset($ip);
            }
            if (!in_array($webServerIP,$this->ips)) throw new Exception(_('I am not the fog web server'));
            $this->outall(' * Attempting to ping '.$this->getClass('HostManager')->count().' host(s).');
            $Hosts = $this->getClass('HostManager')->find();
            foreach ((array)$Hosts AS $i => &$Host) {
                if (!$Host->isValid()) {
                    unset($Host);
                    continue;
                }
                $Host->setPingStatus()->save();
                unset($Host);
            }
            unset($Hosts);
            $this->outall(' * All status\' have been updated');
        } catch (Exception $e) {
            $this->outall($e->getMessage());
        }
    }
    public function serviceRun() {
        $this->out(' ',$this->dev);
        $this->out(' +---------------------------------------------------------',$this->dev);
        $this->commonOutput();
        $this->out(' +---------------------------------------------------------',$this->dev);
    }
}
