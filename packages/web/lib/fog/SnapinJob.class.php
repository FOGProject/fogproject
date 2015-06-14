<?php
class SnapinJob extends FOGController {
    // Table
    public $databaseTable = 'snapinJobs';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'sjID',
        'hostID' => 'sjHostID',
        'stateID' => 'sjStateID',
        'createdTime' => 'sjCreateTime',
    );
    public function getHost() {return $this->getClass('Host',$this->get('hostID'));}
}
