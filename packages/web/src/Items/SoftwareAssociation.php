<?php
/**
 * Host-direct software assignment.
 *
 * PHP version 7.4+
 *
 * @category SoftwareAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * Host-direct software assignment.
 *
 * @category SoftwareAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SoftwareAssociation extends FOGController
{
    /**
     * The table.
     *
     * @var string
     */
    protected $databaseTable = 'softwareAssoc';
    /**
     * The fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'swaID',
        'hostID' => 'swaHostID',
        'softwareID' => 'swaSoftwareID',
        'sequence' => 'swaSequence',
        // 'present' or 'absent' overrides the entry's own state for this
        // host; empty (NULL) uses it (GH-2129).
        'state' => 'swaState'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'hostID',
        'softwareID'
    ];
    /**
     * Saves, refusing a state that is not empty, present or absent.
     *
     * Here and not only on the page, because the REST API sets fields
     * directly.
     *
     * @return object|bool
     */
    public function save()
    {
        Software::assertLinkState($this->get('state'));
        return parent::save();
    }
    /**
     * The host.
     *
     * @return object
     */
    public function getHost()
    {
        return new Host($this->get('hostID'));
    }
    /**
     * The software entry.
     *
     * @return object
     */
    public function getSoftware()
    {
        return new Software($this->get('softwareID'));
    }
}
