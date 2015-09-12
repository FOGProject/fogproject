<?php
class ALOBG extends FOGClient implements FOGClientSend {
    private $image;
    public function send() {
        $this->image = $this->FOGCore->getSetting(FOG_SERVICE_AUTOLOGOFF_BGIMAGE);
        if (!$this->newService) $this->image = base64_encode($this->image);
    }
}
