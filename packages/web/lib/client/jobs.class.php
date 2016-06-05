<?php
class Jobs extends FOGClient implements FOGClientSend {
    public function json() {
        if ($this->Host->get('task')->isInitNeededTasking()) return stripos(strtolower($_SERVER['SCRIPT_NAME']),'jobs.php') ? array('error'=>'ok') : array('job'=>true);
        if (!self::getClass('PowerManagementManager')->count(array('id'=>$this->Host->get('powermanagementtasks'),'onDemand'=>1))) return stripos(strtolower($_SERVER['SCRIPT_NAME']),'jobs.php') ? array('error'=>'nj') : array('job' => false);
        $actions = self::getSubObjectIDs('PowerManagement',array('id'=>$this->Host->get('powermanagementtasks'),'onDemand'=>1),'action');
        self::getClass('PowerManagementManager')->update(array('id'=>$this->Host->get('powermanagementtasks'),'onDemand'=>1),'',array('onDemand'=>0));
        if (in_array('shutdown',$actions)) return array('action'=>'shutdown');
        if (in_array('reboot',$actions)) return array('action'=>'reboot');
    }
    public function send() {
        if ($this->Host->get('task')->isInitNeededTasking()) $this->send = '#!ok';
        else throw new Exception('#!nj');
    }
}
