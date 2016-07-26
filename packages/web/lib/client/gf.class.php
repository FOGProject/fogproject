<?php
class GF extends FOGClient implements FOGClientSend {
    public function json() {
        if (self::getClass('GreenFogManager')->count() < 1) return array('error'=>'na');
        return array('tasks'=>array_filter(array_map(function(&$gf) {
            if (!$gf->isValid()) return;
            $action = (trim(strtolower($gf->get('action'))) === 's' ? 'shutdown' : (trim(strtolower($gf->get('action'))) === 'r' ? 'reboot' : ''));
            if (empty($action)) return;
            return array(
                'hour' => $gf->get('hour'),
                'min' => $gf->get('min'),
                'action' => $action,
            );
        },(array)self::getClass('GreenFogManager')->find())));
    }
    public function send() {
        if (self::getClass('GreenFogManager')->count() < 1) throw new Exception('#!na');
        $index = 0;
        $Send = array();
        array_map(function(&$gf) use (&$index,&$Send) {
            if (!$gf->isValid()) return;
            $action = (trim(strtolower($gf->get('action'))) === 's' ? 'shutdown' : (trim(strtolower($gf->get('action'))) === 'r' ? 'reboot' : ''));
            if (empty($action)) return;
            $val = sprintf('%d@%d@%s',$gf->get('hour'),$gf->get('min'),$action);
            if ($this->newService) {
                if ($index === 0) $Send[$index] = "#!ok\n";
                $Send[$index] .= sprintf("#task%d=%s\n",$index,$val);
            } else $Send[$index] = sprintf("%s\n",base64_encode($val));
            $index++;
        },(array)self::getClass('GreenFogManager')->find());
        $this->send = implode($Send);
    }
}
