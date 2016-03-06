<?php
class DisplayManager extends FOGClient implements FOGClientSend {
    public function send() {
        $x = (int)$this->Host->getDispVals('width');
        $y = (int)$this->Host->getDispVals('height');
        $r = (int)$this->Host->getDispVals('refresh');
        $this->send = base64_encode(sprintf('%dx%dx%d',$x,$y,$r));
        if ($this->newService) {
            if ($this->json) {
                $val['x'] = (int)$x;
                $val['y'] = (int)$y;
                $val['r'] = (int)$r;
                return $val;
            } else $this->send = sprintf("#!ok\n#x=%d#y=%d#r=%d",(int)$x,(int)$y,(int)$r);
        }
    }
}
