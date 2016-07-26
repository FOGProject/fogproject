<?php
class DisplayManager extends FOGClient implements FOGClientSend {
    public function json() {
        return array(
            'x'=>$this->Host->getDispVals('width'),
            'y'=>$this->Host->getDispVals('height'),
            'r'=>$this->Host->getDispVals('refresh'),
        );
    }
    public function send() {
        if ($this->newService) {
            $this->send = sprintf("#!ok\n#x=%d\n#y=%d\n#r=%d",
                $this->Host->getDispVals('width'),
                $this->Host->getDispVals('height'),
                $this->Host->getDispVals('refresh')
            );
        } else {
            $this->send = base64_encode(sprintf('%dx%dx%d',
                $this->Host->getDispVals('width'),
                $this->Host->getDispVals('height'),
                $this->Host->getDispVals('refresh')
            ));
        }
    }
}
