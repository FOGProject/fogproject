<?php
/**
 * Deals with the client updater files
 *
 * PHP version 5
 *
 * @category ClientUpdater
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Deals with the client updater files
 *
 * @category ClientUpdater
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ClientUpdater extends FOGController
{
    /**
     * Client Updater table
     *
     * @var string
     */
    protected $databaseTable = 'clientUpdates';
    /**
     * Client Updater fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'cuID',
        'name' => 'cuName',
        'md5' => 'cuMD5',
        'type' => 'cuType',
        'file' => 'cuFile',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'file',
    );
}
