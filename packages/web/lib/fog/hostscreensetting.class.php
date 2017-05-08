<?php
/**
 * Host screen settings class.
 *
 * PHP version 5
 *
 * @category HostScreenSetting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Host screen settings class.
 *
 * @category HostScreenSetting
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostScreenSetting extends FOGController
{
    /**
     * The host screen settings table name.
     *
     * @var string
     */
    protected $databaseTable = 'hostScreenSettings';
    /**
     * The host screen settings fields and common names.
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'hssID',
        'hostID' => 'hssHostID',
        'width' => 'hssWidth',
        'height' => 'hssHeight',
        'refresh' => 'hssRefresh',
        'orientation' => 'hssOrientation',
        'other1' => 'hssOther1',
        'other2' => 'hssOther2',
    );
    /**
     * The required fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'hostID',
    );
    /**
     * Gets the host object.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
}
