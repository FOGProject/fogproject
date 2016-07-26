<?php
class SnapinTaskManager extends FOGManagerController {
    public function cancel($snapintaskids) {
        $findWhere = array('id'=>(array)$snapintaskids);
        $cancelled = $this->getCancelledState();
        return $this->update($findWhere,'',array('stateID'=>$cancelled,'complete'=>$this->formatTime('','Y-m-d H:i:s')));
    }
}
