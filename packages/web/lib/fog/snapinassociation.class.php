<?php
/**
 * The snapin association class.
 *
 * PHP version 5
 *
 * @category SnapinAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The snapin association class.
 *
 * @category SnapinAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinAssociation extends FOGController
{
    /**
     * The snapin assoc table.
     *
     * @var string
     */
    protected $databaseTable = 'snapinAssoc';
    /**
     * The snapin assoc fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'saID',
        'hostID' => 'saHostID',
        'snapinID' => 'saSnapinID',
    );
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
        'snapinID',
    );
    /**
     * Get's the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
    /**
     * Get's the snapin object.
     *
     * @return object
     */
    public function getSnapin()
    {
        return new Snapin($this->get('snapinID'));
    }
}
