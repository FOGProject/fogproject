<?php
class MulticastSessionsManager extends FOGManagerController
{
    public function cancel($multicastsessionids)
    {
        $findWhere = array('id'=>(array)$multicastsessionids);
        $cancelled = $this->getCancelledState();
        $this->update($findWhere, '', array('stateID'=>$cancelled, 'name'=>''));
        $this->arrayChangeKey($findWhere, 'id', 'msID');
        self::getClass('MulticastSessionsAssociationManager')->destroy($findWhere);
    }
}
