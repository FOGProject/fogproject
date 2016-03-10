<?php
class Jobs extends FOGClient implements FOGClientSend {
    public function send() {
        $this->send = '#!nj';
        $Task = $this->Host->get('task');
        if ($Task->isValid() && $Task->isInitNeededTasking()) $this->send = '#!ok';
        if ($this->json) return array('error'=>preg_replace('/^[#][!]/','',$this->send));
    }
}
