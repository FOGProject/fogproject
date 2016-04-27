<?php
class MulticastSessionsManager extends FOGManagerController {
    public function cancel($multicastsessionids) {
        $findWhere = array('id'=>(array)$multicastsessionids);
        $this->update($findWhere,'',array('stateID'=>$this->getCancelledState(),'completetime'=>$this->formatTime('','Y-m-d H:i:s'),'clients'=>0));
        $this->array_change_key($findWhere,'id','msID');
        self::getClass('MulticastSessionsAssociationManager')->destroy($findWhere);
    }
}
