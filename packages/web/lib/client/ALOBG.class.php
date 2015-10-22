<?php
class ALOBG extends FOGClient implements FOGClientSend {
    private $image;
    public function send() {
        $this->send = $this->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE');
        if (!$this->newService) $this->send = base64_encode($this->send);
    }
}
