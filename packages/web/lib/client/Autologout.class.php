<?php
class Autologout extends FOGClient implements FOGClientSend {
    private $HostAutoLogout;
    private $time;
    public function send() {
        $this->time = $this->FOGCore->getSetting('FOG_SERVICE_AUTOLOGOFF_MIN');
        if ($this->Host->getAlo() instanceof HostAutoLogout) $this->time = $this->Host->getAlo()->get('time');
        $this->send = base64_encode($this->time);
        if ($this->newService) {
            $time = "#!ok\n#time=".($this->time * 60);
            if ($this->time < 5) $time = '#!time';
            $this->send = $time;
        }
    }
}
