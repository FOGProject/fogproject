<?php
/**
 * Image partition type class.
 *
 * PHP version 7.4+
 *
 * @category ImagePartitionType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * Image partition type class.
 *
 * @category ImagePartitionType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImagePartitionType extends FOGController
{
    /**
     * The partition type table.
     *
     * @var string
     */
    protected $databaseTable = 'imagePartitionTypes';
    /**
     * The partitoin type fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'imagePartitionTypeID',
        'name' => 'imagePartitionTypeName',
        'type' => 'imagePartitionTypeValue'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name',
        'type'
    ];
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ImagePartitionType', 'ImagePartitionType');
