<?php
class PM extends FOGClient {
    public function json() {
        $actions = self::getSubObjectIDs('PowerManagement',array('id'=>$this->Host->get('powermanagementtasks'),'onDemand'=>1),'action');
        self::getClass('PowerManagementManager')->update(array('id'=>$this->Host->get('powermanagementtasks'),'onDemand'=>1),'',array('onDemand'=>0));
        return array('tasks'=>array_filter(array_map(function(&$pm) {
            if (!$pm->isValid()) return;
            if ($pm->get('action') === 'wol') return;
            if ($pm->get('onDemand')) return;
            return array(
                'cron' => sprintf('%s %s %s %s %s',$pm->get('min'),$pm->get('hour'),$pm->get('dom'),$pm->get('month'),$pm->get('dow')),
                'action' => $pm->get('action'),
            );
        },(array)self::getClass('PowerManagementManager')->find(array('hostID'=>$this->Host->get('id'))))),
            'onDemand'=>(in_array('shutdown',$actions) ? 'shutdown' : (in_array('reboot',$actions) ? 'reboot' : '')),
        );
    }
}
