<?php
class DisplayManager extends FOGClient implements FOGClientSend {
    public function send() {
        $x = $this->Host->getDispVals('width');
        $y = $this->Host->getDispVals('height');
        $r = $this->Host->getDispVals('refresh');
        $this->send = base64_encode(sprintf('%sx%sx%s',$x,$y,$r));
        if ($this->newService) $this->send = sprintf("#!ok\n#x=%s#y=%s#r=%s",$x,$y,$r);
    }
}
