<?php
/**
 * The image type class.
 *
 * PHP version 5
 *
 * @category ImageType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The image type class.
 *
 * @category ImageType
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageType extends FOGController
{
    /**
     * The image type table.
     *
     * @var string
     */
    protected $databaseTable = 'imageTypes';
    /**
     * The image type fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'imageTypeID',
        'name' => 'imageTypeName',
        'type' => 'imageTypeValue'
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
