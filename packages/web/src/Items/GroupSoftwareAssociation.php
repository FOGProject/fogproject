<?php
/**
 * A group's software grant (ADR 0038 shape).
 *
 * PHP version 7.4+
 *
 * @category GroupSoftwareAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * A group's software grant (ADR 0038 shape).
 *
 * @category GroupSoftwareAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupSoftwareAssociation extends FOGController
{
    /**
     * The table.
     *
     * @var string
     */
    protected $databaseTable = 'groupSoftwareAssoc';
    /**
     * The fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'gswaID',
        'groupID' => 'gswaGroupID',
        'softwareID' => 'gswaSoftwareID',
        'sequence' => 'gswaSequence',
        // 'present' or 'absent' overrides the entry's own state for this
        // group's members; empty (NULL) uses it (GH-2129).
        'state' => 'gswaState'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'groupID',
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
}
