<?php
require('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false);
    $Task = $Host->get('task');
    if (!$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'),$Host->get('name'),$Host->get('mac')->__toString()));
    if ($Task->get('typeID') == 8) {
        $MulticastAssociation = current($FOGCore->getClass('MulticastSessionsAssociationManager')->find(array('taskID' => $Task->get('id'))));
        $MultiSess = new MulticastSessions($MulticastAssociation->get('msID'));
    }
    if ($Task->get('stateID') == 1) {
        $Task->set('stateID',2)->set('checkInTime',$FOGCore->nice_date()->format('Y-m-d H:i:s'))->save();
        $Task->get('typeID') == 8 ? $MultiSess->set('clients',$MultiSess->get('clients')+1)->save() : null;
    }
    $Task->get('typeID') == 8 ? $MSAs = $FOGCore->getClass('MulticastSessionsAssociationManager')->count(array('msID' => $MultiSess->get('id'))) : null;
    $Task->set('stateID',3);
    if ($Task->get('typeID') == 8) {
        if ($MSAs == $MultiSess->get('clients') || ($MultiSess->get('sessclients') > 0 && $MultiSess->get('clients') > 0)) $MultiSess->set('stateID',3);
        else $MultiSess->set('stateID',1);
    }
    if ($Task->save() && ($Task->get('typeID') == 8 ? $MultiSess->save() : true)) {
        if ($MultiSess && $MultiSess->isValid()) $Host->set('imageID',$MultiSess->get('image'));
        $id = @max($FOGCore->getSubObjectIDs('ImagingLog',array('hostID'=>$Host->get('id'),'type'=>$_REQUEST['type'],'complete'=>'0000-00-00 00:00:00')));
        $FOGCore->getClass('ImagingLog',$id)
            ->set('hostID',$Host->get('id'))
            ->set('start',$FOGCore->nice_date()->format('Y-m-d H:i:s'))
            ->set('image',$Task->getImage()->get('name'))
            ->set('type',$_REQUEST['type'])
            ->save();
        $FOGCore->getClass('TaskLog',$Task)
            ->set('taskID',$Task->get('id'))
            ->set('taskStateID',$Task->get('stateID'))
            ->set('createdTime',$Task->get('createdTime'))
            ->set('createdBy',$Task->get('createdBy'))
            ->save();
        echo '##@GO';
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
