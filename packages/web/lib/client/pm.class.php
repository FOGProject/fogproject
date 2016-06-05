<?php
class PM extends FOGClient {
    public function json() {
        if (self::getClass('PowerManagementManager')->count(array('hostID'=>$this->Host->get('id'))) < 1) return array('error'=>'na');
        return array('tasks'=>array_filter(array_map(function(&$pm) {
            if (!$pm->isValid()) return;
            if ($pm->get('action') === 'wol') return;
            if ($pm->get('onDemand')) return;
            //$onDemand = (bool)$pm->get('onDemand');
            //if ($onDemand === true) $pm->set('onDemand','0')->save();
            return array(
                'cron' => sprintf('%s %s %s %s %s',$pm->get('min'),$pm->get('hour'),$pm->get('dom'),$pm->get('month'),$pm->get('dow')),
                //'onDemand' => $onDemand,
                'action' => $pm->get('action'),
            );
        },(array)self::getClass('PowerManagementManager')->find(array('hostID'=>$this->Host->get('id'))))));
    }
}
