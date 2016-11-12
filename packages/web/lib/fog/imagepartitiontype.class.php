<?php
/**
 * Image partition type class.
 *
 * PHP version 5
 *
 * @category ImagePartitionType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
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
    protected $databaseFields = array(
        'id' => 'imagePartitionTypeID',
        'name' => 'imagePartitionTypeName',
        'type' => 'imagePartitionTypeValue',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'type',
    );
}
