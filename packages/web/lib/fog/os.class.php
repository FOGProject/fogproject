<?php
/**
 * The os class.
 *
 * PHP version 5
 *
 * @category OS
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * The os class.
 *
 * @category OS
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class OS extends FOGController
{
    /**
     * The os table name.
     *
     * @var string
     */
    protected $databaseTable = 'os';
    /**
     * The os fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'osID',
        'name' => 'osName',
        'description' => 'osDescription'
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

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\OS', 'OS');
