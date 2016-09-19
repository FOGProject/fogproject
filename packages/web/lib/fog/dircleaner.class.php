<?php
/**
 * Dir Cleaner handles directory cleanup
 *
 * PHP version 5
 *
 * @category DirCleaner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Dir Cleaner handles directory cleanup
 *
 * @category DirCleaner
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DirCleaner extends FOGController
{
    /**
     * Directory Cleaner table
     *
     * @var string
     */
    protected $databaseTable = 'dirCleaner';
    /**
     * Directory Cleaner fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'dcID',
        'path' => 'dcPath',
    );
    /**
     * Directory Cleaner required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'path',
    );
}
