<?php
class SnapinTaskManager extends FOGManagerController {
    public function cancel($snapintaskids) {
        $findWhere = array('id'=>(array)$snapintaskids);
        return $this->update($findWhere,'',array('stateID'=>$this->getCancelledState(),'complete'=>$this->formatTime('','Y-m-d H:i:s')));
    }
}
