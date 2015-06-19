<?php
class GroupAssociation extends FOGController {
    // Table
    public $databaseTable = 'groupMembers';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'gmID',
        'hostID' => 'gmHostID',
        'groupID' => 'gmGroupID',
    );
    public function getGroup() {return $this->getClass('Group',$this->get('groupID'));}
    public function getHost() {return $this->getClass('Host',$this->get('hostID'));}
}
