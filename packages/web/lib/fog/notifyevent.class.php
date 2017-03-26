<?php
/**
 * Notify event tracker.
 *
 * PHP Version 5
 *
 * @category NotifyEvent
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Notify event tracker.
 *
 * @category NotifyEvent
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class NotifyEvent extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'notifyEvents';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'neID',
        'name' => 'neName'
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name'
    );
}
