<?php
class Jobs extends FOGClient implements FOGClientSend {
    public function json() {
        if ($this->Host->get('task')->isValid() && $this->Host->get('task')->isInitNeededTasking()) return stripos(strtolower($_SERVER['SCRIPT_NAME']),'jobs.php') ? '#!ok' : array('job'=>'ok');
        return stripos(strtolower($_SERVER['SCRIPT_NAME']),'jobs.php') ? '#!nj' : array('error' => 'nj');
    }
    public function send() {
        if ($this->Host->get('task')->isValid() && $this->Host->get('task')->isInitNeededTasking()) $this->send = '#!ok';
        else throw new Exception('#!nj');
    }
}
