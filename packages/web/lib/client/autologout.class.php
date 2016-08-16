<?php
class Autologout extends FOGClient implements FOGClientSend
{
    private $HostAutoLogout;
    private $time;
    public function json()
    {
        $time = $this->Host->getAlo();
        if ($time < 5) {
            return array('error'=>'time');
        }
        return array('time'=>$time * 60);
    }
    public function send()
    {
        $time = $this->Host->getAlo();
        if ($this->newService) {
            if ($time < 5) {
                throw new Exception('#!time');
            }
            $this->send = $time * 60;
        } else {
            $this->send = base64_encode($time);
        }
    }
}
