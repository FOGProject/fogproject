<?php
class TaskState extends FOGController {
    protected $databaseTable = 'taskStates';
    protected $databaseFields = array(
        'id' => 'tsID',
        'name' => 'tsName',
        'description' => 'tsDescription',
        'order' => 'tsOrder',
        'icon' => 'tsIcon',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    public function getIcon() {
        return $this->get('icon');
    }
    public function getQueuedStates() {
        $queuedStates = range(0,2);
        static::$HookManager->processEvent('QUEUED_STATES',array('queuedStates'=>&$queuedStates));
        return $queuedStates;
    }
    public function getQueuedState() {
        $queuedState = 1;
        static::$HookManager->processEvent('QUEUED_STATE',array('queuedState'=>&$queuedState));
        return $queuedState;
    }
    public function getCheckedInState() {
        $checkedInState = 2;
        static::$HookManager->processEvent('CHECKEDIN_STATE',array('checkedInState'=>&$checkedInState));
        return $checkedInState;
    }
    public function getProgressState() {
        $progressState = 3;
        static::$HookManager->processEvent('PROGRESS_STATE',array('progressState'=>&$progressState));
        return $progressState;
    }
    public function getCompleteState() {
        $completeState = 4;
        static::$HookManager->processEvent('COMPLETE_STATE',array('completeState'=>&$completeState));
        return $completeState;
    }
    public function getCancelledState() {
        $cancelledState = 5;
        static::$HookManager->processEvent('CANCELLED_STATE',array('cancelledState'=>&$cancelledState));
        return $cancelledState;
    }
}
