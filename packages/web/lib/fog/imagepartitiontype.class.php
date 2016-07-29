<?php
class ImagePartitionType extends FOGController {
    protected $databaseTable = 'imagePartitionTypes';
    protected $databaseFields = array(
        'id' => 'imagePartitionTypeID',
        'name' => 'imagePartitionTypeName',
        'type' => 'imagePartitionTypeValue',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'type',
    );
}
