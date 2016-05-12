<?php
class Autologout extends FOGClient implements FOGClientSend {
    private $HostAutoLogout;
    private $time;
    public function json() {
        $time = (int)$this->Host->getAlo();
        if ($time < 5) return array('error'=>'time');
        return array('time'=>$time);
    }
    public function send() {
        $time = (int)$this->Host->getAlo();
        if ($this->newService) {
            if ($time < 5) throw new Exception('#!time');
            $this->send = $time;
        } else $this->send = base64_encode($time);
    }
}
