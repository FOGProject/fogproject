<?php
class Accesscontrol extends FOGController {
    protected $databaseTable = 'accessControls';
    protected $databaseFields = array(
        'id' => 'acID',
        'name' => 'acName',
        'description' => 'acDesc',
        'other' => 'acOther',
        'userID' => 'acUserID',
        'groupID' => 'acGroupID',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
}
