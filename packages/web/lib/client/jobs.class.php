<?php
class Jobs extends FOGClient implements FOGClientSend {
    public function json() {
        if ($this->Host->get('task')->isInitNeededTasking()) return stripos(strtolower($_SERVER['SCRIPT_NAME']),'jobs.php') ? array('error'=>'ok') : array('job'=>true);
        return stripos(strtolower($_SERVER['SCRIPT_NAME']),'jobs.php') ? array('error'=>'nj') : array('job' => false);
    }
    public function send() {
        if ($this->Host->get('task')->isInitNeededTasking()) $this->send = '#!ok';
        else throw new Exception('#!nj');
    }
}
