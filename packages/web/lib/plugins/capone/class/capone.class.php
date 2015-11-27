<?php
class Capone extends FOGController {
    protected $databaseTable = 'capone';
    protected $databaseFields = array(
        'id' => 'cID',
        'imageID' => 'cImageID',
        'osID' => 'cOSID',
        'key' => 'cKey',
    );
}
