<?php
class PowerManagement extends FOGController {
    protected $databaseTable = 'powerManagement';
    protected $databaseFields = array(
        'id' => 'pmID',
        'hostID' => 'pmHostID',
        'min' => 'pmMin',
        'hour' => 'pmHour',
        'dom' => 'pmDom',
        'month' => 'pmMonth',
        'dow' => 'pmDow',
        'action' => 'pmAction',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'min',
        'hour',
        'dom',
        'month',
        'dow',
        'action',
    );
}
