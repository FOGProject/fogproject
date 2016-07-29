<?php
class ImageType extends FOGController {
    protected $databaseTable = 'imageTypes';
    protected $databaseFields = array(
        'id' => 'imageTypeID',
        'name' => 'imageTypeName',
        'type' => 'imageTypeValue'
    );
    protected $databaseFieldsRequired = array(
        'name',
        'type',
    );
}
