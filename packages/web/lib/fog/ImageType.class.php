<?php
class ImageType extends FOGController {
    // Table
    public $databaseTable = 'imageTypes';
    // Name -> Database field name
    public $databaseFields = array(
        'id' => 'imageTypeID',
        'name' => 'imageTypeName',
        'type' => 'imageTypeValue'
    );
}
