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
        return self::getClass('Group',$this->get('groupID'));
    }
    public function getHost() {
        return self::getClass('Host',$this->get('hostID'));
    }
}
