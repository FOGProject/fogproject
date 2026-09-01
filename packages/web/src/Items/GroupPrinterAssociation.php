<?php
/**
 * The group printer grant class.
 *
 * PHP version 7.4+
 *
 * @category GroupPrinterAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * The group printer grant class.
 *
 * A row here says "this group grants this printer", which is a different
 * statement from printerAssoc's "this host has this printer" -- ADR 0038.
 * The grant is resolved onto a host at read time by Assign\Resolver; nothing
 * is copied onto the member hosts.
 *
 * `isDefault` is the group's answer to "which of these is the default
 * printer", and it loses to a default the host set for itself. It is
 * tinyint(1) here rather than printerAssoc's legacy varchar(2).
 *
 * @category GroupPrinterAssociation
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class GroupPrinterAssociation extends FOGController
{
    /**
     * The group printer grant table.
     *
     * @var string
     */
    protected $databaseTable = 'groupPrinterAssoc';
    /**
     * The group printer grant fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'gpaID',
        'groupID' => 'gpaGroupID',
        'printerID' => 'gpaPrinterID',
        'isDefault' => 'gpaIsDefault'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'groupID',
        'printerID'
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
     * Get's the printer object.
     *
     * @return object
     */
    public function getPrinter()
    {
        return new Printer($this->get('printerID'));
    }
}
