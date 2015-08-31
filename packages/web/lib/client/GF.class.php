<?php
class GF extends FOGClient implements FOGClientSend {
    public function send() {
        $SendEnc = '';
        $SendMe = array();
        $this->send = '#!na';
        $GreenFogs = $this->getClass(GreenFogManager)->find();
        foreach ($GreenFogs AS $i => &$gf) {
            $val = sprintf('%s@%s@%s',$gf->get(hour),$gf->get(min),$gf->get(action));
            $SendMe[$i] = base64_encode($val);
            if ($this->newService) {
                if (!$i) $SendMe[$i] = "#!ok\n";
                $SendMe[$i] .= sprintf("#task%s=%s\n",$i,$val);
            }
        }
        unset($gf);
        if (count($SendMe)) $this->send = implode($SendMe);
    }
}
