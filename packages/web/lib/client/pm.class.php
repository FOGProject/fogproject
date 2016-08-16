<?php
class PM extends FOGClient
{
    public function json()
    {
        $actions = self::getSubObjectIDs('PowerManagement', array('id'=>$this->Host->get('powermanagementtasks'), 'onDemand'=>'1'), 'action');
        return array(
            'onDemand'=>(in_array('shutdown', $actions) ? 'shutdown' : (in_array('reboot', $actions) ? 'restart' : '')),
            'tasks'=>array_filter(
                array_map(function (&$pm) {
                    if (!$pm->isValid()) {
                        return;
                    }
                    if ($pm->get('action') === 'wol') {
                        return;
                    }
                    if ($pm->get('onDemand') > 0) {
                        $pm->destroy();
                        return;
                    }
                    $min = trim($pm->get('min'));
                    $hour = trim($pm->get('hour'));
                    $dom = trim($pm->get('dom'));
                    $month = trim($pm->get('month'));
                    $dow = trim($pm->get('dow'));
                    $cron = sprintf('%s %s %s %s %s', $min, $hour, $dom, $month, $dow);
                    return array(
                        'cron' => $cron,
                        'action' => $pm->get('action'),
                    );
                }, (array)self::getClass('PowerManagementManager')->find(array('hostID'=>$this->Host->get('id'))))
        ));
    }
}
