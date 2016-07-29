<?php
class ImagingLog extends FOGController {
    protected $databaseTable = 'imagingLog';
    protected $databaseFields = array(
        'id' => 'ilID',
        'hostID' => 'ilHostID',
        'start' => 'ilStartTime',
        'finish' => 'ilFinishTime',
        'image' => 'ilImageName',
        'type' => 'ilType',
        'createdBy' => 'ilCreatedBy',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'start',
        'finish',
        'image',
        'type',
    );
}
