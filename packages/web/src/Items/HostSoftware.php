<?php
/**
 * A host's observed software: what the agent last reported, not what an
 * admin wants (design 0006).
 *
 * PHP version 7.4+
 *
 * @category Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * A host's observed software: what the agent last reported, not what an
 * admin wants (design 0006).
 *
 * @category Software
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class HostSoftware extends FOGController
{
    /**
     * The hostSoftware table.
     *
     * @var string
     */
    protected $databaseTable = 'hostSoftware';
    /**
     * The hostSoftware fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'hsID',
        'hostID' => 'hsHostID',
        'name' => 'hsName',
        'version' => 'hsVersion',
        'publisher' => 'hsPublisher',
        'source' => 'hsSource',
        'arch' => 'hsArch',
        'installDate' => 'hsInstallDate',
        'firstSeen' => 'hsFirstSeen',
        'lastSeen' => 'hsLastSeen',
        'removedAt' => 'hsRemovedAt'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'name',
        'source'
    ];
    /**
     * Additional fields.
     *
     * @var array
     */
    protected $additionalFields = [
        'host'
    ];
    /**
     * Return the associated host object.
     *
     * @return object
     */
    public function getHost()
    {
        if (!array_key_exists('host', $this->data)) {
            $this->set('host', new Host($this->get('hostID')));
        }
        return $this->get('host');
    }
}
