<?php
class GreenFog extends FOGController {
    public $databaseTable = 'greenFog';
    public $databaseFields = array(
        'id'	=> 'gfID',
        'hostID' => 'gfHostID',
        'hour'	=> 'gfHour',
        'min'	=> 'gfMin',
        'action' => 'gfAction',
        'days'	=> 'gfDays',
    );
}
