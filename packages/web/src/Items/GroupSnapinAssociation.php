<?php
/**
 * The group snapin grant class.
 *
 * PHP version 7.4+
 *
 * @category GroupSnapinAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * The group snapin grant class.
 *
 * A row here says "this group grants this snapin", which is a different
 * statement from snapinAssoc's "this host has this snapin" -- ADR 0038. The
 * grant is resolved onto a host at read time by Assign\Resolver, so adding a
 * host to the group is enough for it to gain the snapin and removing it is
 * enough to lose it. Nothing is copied onto the member hosts.
 *
 * @category GroupSnapinAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupSnapinAssociation extends FOGController
{
    /**
     * The group snapin grant table.
     *
     * @var string
     */
    protected $databaseTable = 'groupSnapinAssoc';
    /**
     * The group snapin grant fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'gsaID',
        'groupID' => 'gsaGroupID',
        'snapinID' => 'gsaSnapinID',
        'sequence' => 'gsaSequence'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'groupID',
        'snapinID'
    ];
    /**
     * Get's the group object.
     *
     * @return object
     */
    public function getGroup()
    {
        return new Group($this->get('groupID'));
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
