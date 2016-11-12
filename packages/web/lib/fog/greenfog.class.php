<?php
/**
 * GreenFog handler, specific to legacy client now.
 *
 * PHP version 5
 *
 * @category GreenFog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * GreenFog handler, specific to legacy client now.
 *
 * @category GreenFog
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GreenFog extends FOGController
{
    /**
     * Green fog table name.
     *
     * @var string
     */
    public $databaseTable = 'greenFog';
    /**
     * Green fog field names and common names.
     *
     * @var array
     */
    public $databaseFields = array(
        'id'    => 'gfID',
        'hostID' => 'gfHostID',
        'hour'    => 'gfHour',
        'min'    => 'gfMin',
        'action' => 'gfAction',
        'days'    => 'gfDays',
    );
    /**
     * Returns the Host object.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
}
