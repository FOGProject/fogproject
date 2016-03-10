<?php
class ALOBG extends FOGClient implements FOGClientSend {
    private $image;
    public function send() {
        throw new Exception($this->getSetting('FOG_SERVICE_AUTOLOGOFF_BGIMAGE'));
    }
}
