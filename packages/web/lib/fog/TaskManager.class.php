<?php
class TaskManager extends FOGManagerController {
    // Clean up
    public function hasActiveTaskCheckedIn($taskid) {
        $Task = $this->getClass('Task',$taskid);
        return ((strtotime($Task->get('checkInTime')) - strtotime($Task->get('createdTime'))) > 2);
    }
}
