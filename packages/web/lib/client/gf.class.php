<?php
class GF extends FOGClient implements FOGClientSend {
    public function send() {
        $SendEnc = '';
        $SendMe = array();
        $this->send = '#!na';
        $vals = array();
        foreach ($this->getClass('GreenFogManager')->find() AS &$gf) {
            if (!$gf->isValid()) continue;
            $val = sprintf('%s@%s@%s',$gf->get('hour'),$gf->get('min'),$gf->get('action'));
            $SendMe[$i] = base64_encode($val);
            if ($this->newService) {
                if ($this->json) {
                    $vals["task$i"] = array(
                        'hour' => $gf->get('hour'),
                        'min' => $gf->get('min'),
                        'action' => $gf->get('action'),
                    );
                    continue;
                }
                if (!$i) $SendMe[$i] = "#!ok\n";
                $SendMe[$i] .= sprintf("#task%s=%s\n",$i,$val);
            }
            unset($gf);
        }
        if ($this->json) return $vals;
        if (count($SendMe)) $this->send = implode($SendMe);
    }
}
