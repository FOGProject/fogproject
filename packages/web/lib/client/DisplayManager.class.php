<?php
class DisplayManager extends FOGClient implements FOGClientSend {
    public function send() {
        $HostDisplay = $this->getClass(HostScreenSettingsManager)->find(array(hostID=>$this->Host->get(id)));
        $HostDisplay = @array_shift($HostDisplay);
        $x = $this->FOGCore->getSetting(FOG_SERVICE_DISPLAYMANAGER_X);
        $y = $this->FOGCore->getSetting(FOG_SERVICE_DISPLAYMANAGER_Y);
        $r = $this->FOGCore->getSetting(FOG_SERVICE_DISPLAYMANAGER_R);
        if ($HostDisplay) {
            $x = $HostDisplay->get(width);
            $y = $HostDisplay->get(height);
            $r = $HostDisplay->get(refresh);
        }
        $this->send = base64_encode(sprintf('%sx%sx%s',$x,$y,$r));
        if ($this->newService) $this->send = sprintf("#!ok\n#x=%s#y=%s#r=%s",$x,$y,$r);
    }
}
