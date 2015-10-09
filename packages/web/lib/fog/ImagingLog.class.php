<?php
class ImagingLog extends FOGController {
    /** @var $databaseTable the table to work with */
    public $databaseTable = 'imagingLog';
    /** @var $databaseFields the fields within the table */
    public $databaseFields = array(
        'id' => 'ilID',
        'hostID' => 'ilHostID',
        'start' => 'ilStartTime',
        'finish' => 'ilFinishTime',
        'image' => 'ilImageName',
        'type' => 'ilType',
    );
    // Required database fields
    public $databaseFieldsRequired = array(
        'hostID',
        'start',
        'finish',
        'image',
        'type',
    );
}
