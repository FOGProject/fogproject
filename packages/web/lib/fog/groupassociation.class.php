<?php
class GroupAssociation extends FOGController {
    protected $databaseTable = 'groupMembers';
    protected $databaseFields = array(
        'id' => 'gmID',
        'hostID' => 'gmHostID',
        'groupID' => 'gmGroupID',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'groupID',
    );
    public function getGroup() {
        return static::getClass('Group',$this->get('groupID'));
    }
    public function getHost() {
        return static::getClass('Host',$this->get('hostID'));
    }
}
