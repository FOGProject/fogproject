<?php
class MulticastSessionsManager extends FOGManagerController {
    public function cancel($multicastsessionids) {
        $findWhere = array('msID'=>(array)$multicastsessionids);
        $this->getClass('MulticastSessionsAssociationManager')->destroy($findWhere);
        $this->array_change_key($findWhere,'msID','id');
        return $this->update($findWhere,'',array('stateID'=>$this->getCancelledState(),'completetime'=>$this->formatTime('','Y-m-d H:i:s'),'clients'=>0));
    }
}
