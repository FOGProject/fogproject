<?php
class Autologout extends FOGClient implements FOGClientSend {
    private $HostAutoLogout;
    private $time;
    public function send() {
        $this->time = $this->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
        if ($this->Host->getAlo() > 4) $this->time = $this->Host->getAlo();
        $this->send = base64_encode($this->time);
        if ($this->newService) {
            if ($this->json) {
                $this->send = null;
                if ($this->time < 5) $this->time = 0;
                $val = array(
                    'time'=>$this->time * 60,
                );
                return $val;
            }
            $time = sprintf("#!ok\n#time=%s",($this->time * 60));
            if ($this->time < 5) $time = '#!time';
            $this->send = $time;
        }
    }
}
