<?php
/**
 * The ipxe class.
 *
 * PHP version 5
 *
 * @category Ipxe
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The ipxe class.
 *
 * @category Ipxe
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Ipxe extends FOGController
{
    /**
     * The ipxe table name.
     *
     * @var string
     */
    protected $databaseTable = 'ipxeTable';
    /**
     * The ipxe table fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'ipxeID',
        'product' => 'ipxeProduct',
        'manufacturer' => 'ipxeManufacturer',
        'mac' => 'ipxeMAC',
        'success' => 'ipxeSuccess',
        'failure' => 'ipxeFailure',
        'file' => 'ipxeFilename',
        'version' => 'ipxeVersion',
    );
}
