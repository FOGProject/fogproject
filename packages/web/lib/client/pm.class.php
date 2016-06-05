<?php
class PM extends FOGClient implements FOGClientSend {
    public function json() {
        if (self::getClass('PowerManagementManager')->count(array('hostID'=>$this->Host->get('id'))) < 1) return array('error'=>'na');
        return array('tasks'=>array_filter(array_map(function(&$pm) {
            if (!$pm->isValid()) return;
            if ($pm->get('onDemand') > 0) return;
            if ($pm->get('action') === 'wol') return;
            return array(
                'cron' => sprintf('%s %s %s %s %s',$pm->get('min'),$pm->get('hour'),$pm->get('dom'),$pm->get('month'),$pm->get('dow')),
                'action' => $pm->get('action'),
            );
        },(array)self::getClass('PowerManagementManager')->find(array('hostID'=>$this->Host->get('id'))))));
    }
    public function send() {
    }
}
