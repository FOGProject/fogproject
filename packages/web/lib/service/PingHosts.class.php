<?php
class PingHosts extends FOGService {
    public $dev = PINGHOSTDEVICEOUTPUT;
    public $log = PINGHOSTLOGPATH;
    public $zzz = PINGHOSTSLEEPTIME;
    private function commonOutput() {
        try {
            if (!in_array($this->FOGCore->resolveHostname($this->FOGCore->getSetting(FOG_WEB_HOST)),$this->getIPAddress())) throw new Exception(_('I am not the fog web server'));
            $this->outall(' * Attempting to ping '.$this->getClass(HostManager)->count().' host(s).');
            $Hosts = $this->getClass(HostManager)->find();
            foreach ($Hosts AS $i => &$Host) {
                $ip = $this->FOGCore->resolveHostname($Host->get(name));
                if (!filter_var($ip,FILTER_VALIDATE_IP)) {
                    $Host->set(pingstatus,-1)->save();
                    continue;
                }
                $Host->set(pingstatus,(int)$this->getClass(Ping,$ip)->execute())->save();
                usleep(1000);
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
