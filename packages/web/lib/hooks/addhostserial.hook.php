<?php
class AddHostSerial extends Hook {
    public $name = 'AddHostSerial';
    public $description = 'Adds host serial to the host lists';
    public $author = 'Junkhacker with edits from Tom Elliott';
    public $active = false;
    public function HostData($arguments) {
        if ($_REQUEST['node'] != 'host') return;
        foreach((array)$arguments['data'] AS $i => &$data) {
            $Host = $this->getClass('Host',@max($this->getSubObjectIDs('Host',array('name'=>$data['host_name']))));
            if (!$Host->isValid()) continue;
            if (!$Host->get('inventory')->isValid()) continue;
            $arguments['templates'][7] = '${serial}';
            $arguments['attributes'][7] = array('width'=>20,'class'=>'c');
            $arguments['data'][$i]['serial'] = $Host->get('inventory')->get('sysserial');
            unset($data);
        }
    }
    public function HostTableHeader($arguments) {
        if ($_REQUEST['node'] != 'host') return;
        $arguments['headerData'][7] = _('Serial');
    }
}
$AddHostSerial = new AddHostSerial();
$HookManager->register('HOST_DATA', array($AddHostSerial, 'HostData'));
$HookManager->register('HOST_HEADER_DATA', array($AddHostSerial, 'HostTableHeader'));
