<?php
class Jobs extends FOGClient implements FOGClientSend {
    public function send() {
        $RebootTask = false;
        $this->send = '#!nj';
        $Task = $this->Host->get('task');
        if ($Task->isValid() && !in_array($Task->get('typeID'),array(12,13))) $this->send = '#!ok';
    }
}
