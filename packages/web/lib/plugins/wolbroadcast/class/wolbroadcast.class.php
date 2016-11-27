<?php
/**
 * Wolbroadcast Class handler.
 *
 * PHP version 5
 *
 * @category Wolbroadcast
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Wolbroadcast Class handler.
 *
 * @category Wolbroadcast
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Wolbroadcast extends FOGController
{
    /**
     * The wolbroadcast table
     *
     * @var string
     */
    protected $databaseTable = 'wolbroadcast';
    /**
     * The wolbroadcast fields and common names
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'wbID',
        'name' => 'wbName',
        'description' => 'wbDesc',
        'broadcast' => 'wbBroadcast',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'broadcast',
    );
}
