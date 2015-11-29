<?php
class AddHostModel extends Hook {
    public $name = 'AddHostModel';
    public $description = 'Adds host model to the host lists';
    public $author = 'Rowlett/TomElliott';
    public $active = false;
    public function HostData($arguments) {
        if ($_REQUEST['node'] != 'host') return;
        foreach((array)$arguments['data'] AS $i => &$data) {
            $Host = $this->getClass('Host',@max($this->getSubObjectIDs('Host',array('name'=>$data['host_name']),'id')));
            if (!$Host->isValid()) continue;
            if (!$Host->get('inventory')->isValid()) continue;
            $arguments['templates'][5] = '${model}';
            $arguments['data'][$i]['model'] = $Host->get('inventory')->get('sysproduct');
            $arguments['attributes'][5] = array('width'=>20,'class'=>'c');
        }
    }
    public function HostTableHeader($arguments) {
        if ($_REQUEST['node'] != 'host') return;
        $arguments['headerData'][5] = _('Model');
    }
}
$AddHostModel = new AddHostModel();
$HookManager->register('HOST_DATA', array($AddHostModel, 'HostData'));
$HookManager->register('HOST_HEADER_DATA', array($AddHostModel, 'HostTableHeader'));
