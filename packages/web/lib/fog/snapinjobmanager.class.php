<?php
class SnapinJobManager extends FOGManagerController {
    public function cancel($snapinjobids) {
        $findWhere = array('id'=>(array)$snapinjobids);
        return $this->update($findWhere,'',array('stateID'=>$this->getCancelledState()));
    }
}
