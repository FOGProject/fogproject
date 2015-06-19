<?php
class DirCleaner extends FOGController {
    /** @var $databaseTable the table within the database to
     * perform the lookup on.
     */
    public $databaseTable = 'dirCleaner';
    /** @var $databaseFields the associative array.  Makes
     * so we can use common names and associate with the relevant
     * database calls back to the system.
     */
    public $databaseFields = array(
        'id'		=> 'dcID',
        'path'		=> 'dcPath',
    );
}
