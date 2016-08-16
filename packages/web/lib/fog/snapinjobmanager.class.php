<?php
class SnapinJobManager extends FOGManagerController
{
    public function cancel($snapinjobids)
    {
        $findWhere = array('id'=>(array)$snapinjobids);
        $cancelled = $this->getCancelledState();
        return $this->update($findWhere, '', array('stateID'=>$cancelled));
    }
}
