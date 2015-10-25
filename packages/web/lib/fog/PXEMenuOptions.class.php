<?php
class PXEMenuOptions extends FOGController {
    protected $databaseTable = 'pxeMenu';
    protected $databaseFields = array(
        'id' => 'pxeID',
        'name' => 'pxeName',
        'description' => 'pxeDesc',
        'params' => 'pxeParams',
        'default' => 'pxeDefault',
        'regMenu' => 'pxeRegOnly',
        'args' => 'pxeArgs',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
}
