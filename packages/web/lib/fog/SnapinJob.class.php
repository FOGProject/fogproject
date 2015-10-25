<?php
class SnapinJob extends FOGController {
    protected $databaseTable = 'snapinJobs';
    protected $databaseFields = array(
        'id' => 'sjID',
        'hostID' => 'sjHostID',
        'stateID' => 'sjStateID',
        'createdTime' => 'sjCreateTime',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'stateID',
    );
    public function getHost() {
        return $this->getClass('Host',$this->get('hostID'));
    }
}
