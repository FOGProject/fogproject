<?php
/**
 * DMI Key tracker.
 *
 * PHP Version 5
 *
 * @category DMIKey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * DMI Key tracker.
 *
 * @category DMIKey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DMIKey extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'dmidecodeKeys';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'dkID',
        'name' => 'dkName'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
}
