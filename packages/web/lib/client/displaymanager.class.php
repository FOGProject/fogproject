<?php
class DisplayManager extends FOGClient implements FOGClientSend {
    public function json() {
        return array(
            'x'=>(int)$this->Host->getDispVals('width'),
            'y'=>(int)$this->Host->getDispVals('height'),
            'r'=>(int)$this->Host->getDispVals('refresh'),
        );
    }
    public function send() {
        if ($this->newService) {
            $this->send = sprintf("#!ok\n#x=%d\n#y=%d\n#r=%d",
                (int)$this->Host->getDispVals('width'),
                (int)$this->Host->getDispVals('height'),
                (int)$this->Host->getDispVals('refresh')
            );
        } else {
            $this->send = base64_encode(sprintf('%dx%dx%d',
                (int)$this->Host->getDispVals('width'),
                (int)$this->Host->getDispVals('height'),
                (int)$this->Host->getDispVals('refresh')
            ));
        }
    }
}
