<?php
class ImagePartitionType extends FOGController {
    /** @var $databaseTable the table to work with */
    public $databaseTable = 'imagePartitionTypes';
    /** @var $databaseFields the fields within the table */
    public $databaseFields = array(
        'id' => 'imagePartitionTypeID',
        'name' => 'imagePartitionTypeName',
        'type' => 'imagePartitionTypeValue',
    );
}
