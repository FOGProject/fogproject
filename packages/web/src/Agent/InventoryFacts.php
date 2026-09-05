<?php
/**
 * Hardware inventory an agent reports about its own host.
 *
 * PHP version 7.4+
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Agent;

use FOG\Audit\Audit;
use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Items\Inventory;

/**
 * Writes a reported hardware block into FOG's existing `inventory` row
 * (design 0006 section 3).
 *
 * The table is reused rather than replaced: the Host "Inventory" tab and
 * the Hardware report already read it, so an agent-reported machine and an
 * FOS-imaged one look the same to every consumer. What is not reused is the
 * legacy transport -- base64 form fields authenticated by a MAC address --
 * because the agent already has an mTLS channel bound to exactly one host.
 *
 * Named InventoryFacts, not Inventory, to stay distinct from
 * FOG\Items\Inventory, which is the row this writes.
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class InventoryFacts extends FOGBase
{
    /**
     * The only properties an agent may set.
     *
     * A whitelist, not a filter over the class's field map: `inventory`
     * also carries `primaryUser`, `other1`, `other2` and `deleteDate`,
     * which are an admin's to set. Passing a reported block straight into
     * set() would let a host rewrite its own asset tags.
     *
     * @var string[]
     */
    const FIELDS = [
        'sysman', 'sysproduct', 'sysversion', 'sysserial', 'sysuuid',
        'systype', 'biosvendor', 'biosversion', 'biosdate',
        'mbman', 'mbproductname', 'mbversion', 'mbserial', 'mbasset',
        'cpuman', 'cpuversion', 'cpucurrent', 'cpumax', 'mem',
        'hdmodel', 'hdserial', 'hdfirmware',
        'caseman', 'casever', 'caseserial', 'caseasset',
        'gpuvendors', 'gpuproducts'
    ];

    /**
     * Longest value stored for any one property. The columns are varchars
     * and MySQL in strict mode refuses an overlong value, failing the whole
     * poll; truncating keeps a hostile or merely odd DMI string from
     * costing the host its check-in.
     */
    const MAX_VALUE = 250;

    /**
     * Records a reported hardware block on the host's inventory row.
     *
     * Upsert rather than insert: a host has exactly one inventory row, and
     * enrollment has usually already created it with the four SMBIOS
     * identity fields. Only the whitelisted properties move.
     *
     * @param Host  $Host  the host the certificate bound
     * @param array $block the reported properties
     *
     * @return void
     */
    public static function report(Host $Host, array $block)
    {
        $hostID = (int)$Host->get('id');
        $Inventory = $Host->get('inventory');
        if (!$Inventory instanceof \FOG\Items\Inventory
            || !$Inventory->isValid()
        ) {
            $Inventory = (new Inventory())->set('hostID', $hostID);
        }
        $changed = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $block)) {
                continue;
            }
            $value = substr(trim((string)$block[$field]), 0, self::MAX_VALUE);
            if ((string)$Inventory->get($field) === $value) {
                continue;
            }
            $changed[] = $field;
            $Inventory->set($field, $value);
        }
        if (empty($changed)) {
            return;
        }
        $Inventory->save();
        // One renderable line on the host, so a disk swap or a BIOS update
        // shows up where an admin already looks for what changed. The
        // field names, not the values: an inventory row is a page of
        // strings and the audit text is not the place to copy it.
        Audit::record(
            [
                'type' => 'agent.inventory',
                'subjectType' => 'host',
                'subjectID' => $hostID,
                'subjectLabel' => (string)$Host->get('name'),
                'renderable' => 1,
                'affectedCount' => count($changed),
                'text' => substr(
                    'agent reported inventory: ' . implode(', ', $changed),
                    0,
                    Audit::MAX_DETAIL
                ),
                'authSource' => Principal::AUTH_SOURCE
            ]
        );
    }
}
