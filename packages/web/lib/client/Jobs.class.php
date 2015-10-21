<?php
class Jobs extends FOGClient implements FOGClientSend {
    public function send() {
        $RebootTask = false;
        $this->send = '#!nj';
        $Task = $this->Host->get('task');
        if ($Task instanceof Task && $Task->isValid()) $RebootTask = !in_array($Task->get('typeID'),array(12,13));
        if ($RebootTask) $this->send = '#!ok';
    }
}
