<?php
class PingHosts extends FOGService {
    public $dev = PINGHOSTDEVICEOUTPUT;
    public $log = PINGHOSTLOGPATH;
    public $zzz = PINGHOSTSLEEPTIME;
    private function commonOutput() {
        try {
            $webServerIP = $this->FOGCore->resolveHostName($this->FOGCore->getSetting('FOG_WEB_HOST'));
            $this->outall(sprintf(' * FOG Web Host IP: %s',$webServerIP));
            $serverIPs = $this->getIPAddress();
            foreach ((array)$serverIPs AS $i => &$ip) {
                if (!$i) $this->outall(" * This server's IP Addresses");
                $this->outall(" |\t$ip");
            }
            if (!in_array($this->FOGCore->resolveHostname($this->FOGCore->getSetting('FOG_WEB_HOST')),$this->getIPAddress())) throw new Exception(_('I am not the fog web server'));
            $this->outall(' * Attempting to ping '.$this->getClass('HostManager')->count().' host(s).');
            $Hosts = $this->getClass('HostManager')->getSubObjectIDs('','','id');
            foreach ($Hosts AS $i => &$Host) {
                // Ensures the hostIP regardless of how it is entered,
                // to remove any beginning/ending white space
                $hostIP = trim($this->getClass('Host',$Host)->get('ip'));
                // Test IP Value and if valid, use it as the pinging source
                if (filter_var($hostIP,FILTER_VALIDATE_IP)) $ip = $hostIP;
                // Otherwise attempt to get the hostname resolved.
                else $ip = $this->FOGCore->resolveHostname($this->getClass('Host',$Host)->get('name'));
                // If the host still isn't found, set value to -1
                // Allows us to clarify what is up.
                if (!filter_var($ip,FILTER_VALIDATE_IP)) {
                    $this->getClass('Host',$Host)->set('pingstatus',-1)->save();
                    continue;
                }
                // If all above makes it here, perform the ping
                $this->getClass('Host',$Host)->set('pingstatus',(int)$this->getClass('Ping',$ip)->execute())->save();
                // Give CPU a little breather between pings
                usleep(100000);
            }
            unset($Host);
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
