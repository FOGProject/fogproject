<?php
class ALOBG extends FOGClient implements FOGClientSend {
    private $image;
    public function send() {
        throw new Exception(self::getSetting('FOG_CLIENT_AUTOLOGOFF_BGIMAGE'));
    }
}
