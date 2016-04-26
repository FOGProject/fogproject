<?php
class SnapinTask extends FOGController {
    protected $databaseTable = 'snapinTasks';
    protected $databaseFields = array(
        'id' => 'stID',
        'jobID' => 'stJobID',
        'stateID' => 'stState',
        'checkin' => 'stCheckinDate',
        'complete' => 'stCompleteDate',
        'snapinID' => 'stSnapinID',
        'return' => 'stReturnCode',
        'details' => 'stReturnDetails',
    );
    protected $databaseFieldsRequired = array(
        'jobID',
        'snapinID',
    );
    public function getSnapinJob() {
        return static::getClass('SnapinJob',$this->get('jobID'));
    }
    public function getSnapin() {
        return static::getClass('Snapin',$this->get('snapinID'));
    }
}
