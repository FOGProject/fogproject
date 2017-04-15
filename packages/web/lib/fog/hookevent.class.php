<?php
/**
 * Hook event tracker.
 *
 * PHP Version 5
 *
 * @category HookEvent
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hook event tracker.
 *
 * @category HookEvent
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HookEvent extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'hookEvents';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'heID',
        'name' => 'heName'
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
