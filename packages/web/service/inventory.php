<?php
/**
 * Inventory, stores the host inventory.
 *
 * PHP version 7.4+
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Agent\MachineIdentity;
use FOG\Audit\Audit;
use FOG\Base\FOGCore;
use FOG\Base\SmbiosIdentity;
use FOG\Items\Host;
use FOG\Items\Inventory;
use FOG\Managers\HostManager;

/**
 * Inventory, stores the host inventory.
 *
 * @category Inventory
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: the caller is a booting NIC, FOS, the fog-client
 * or a storage node, none of which can present a credential. Declared per
 * file rather than inferred from the absence of one -- see
 * Authorization::_hasNoPrincipal() for what it licenses and why the
 * distinction matters.
 */
define('FOG_MACHINE_REQUEST', true);

require '../commons/base.inc.php';
header('Content-Type: text/plain');
// The client base64-encodes every value, so decode (and sanitize) the
// request up front before any field is read. stripAndDecode() takes the
// array by reference and decodes it in place, so run it on both sources
// that FOGCore::requestParam() reads.
FOGCore::stripAndDecode($_POST);
FOGCore::stripAndDecode($_GET);
try {
    // Authenticate by host/MAC the same way the other FOS-facing service
    // endpoints do; getHostItem() reads the mac itself (via filter_input on
    // the raw request) and normalizes it, so it handles the base64-encoded
    // mac fog.inventory sends on registration/deploy as well as the plain mac
    // the standalone inventory task sends. It throws on an unknown/invalid
    // host.
    FOGCore::getHostItem(false);
    if (!FOGCore::$Host->isValid()) {
        throw new \Exception(_('Invalid Host'));
    }
    // Register mode (forum 18319). After a registration, FOS posts the
    // inventory with the MAC of the NIC it booted through. The MAC of a
    // shared USB adapter belongs to another host. If the reported serial
    // contradicts the serial of that host, the firmware decides the host.
    // If the firmware names no host, nothing is written. The inventory of
    // one machine must not replace the inventory of another.
    $smbiosMode = strtolower(
        trim((string)FOGCore::getSetting('FOG_HOST_IDENTIFY_SMBIOS'))
    );
    if ($smbiosMode === 'register') {
        $posted = [];
        foreach (SmbiosIdentity::FIELDS as $field) {
            $posted[$field] = (string)FOGCore::requestParam($field, '');
        }
        $macID = (int)FOGCore::$Host->get('id');
        $clash = MachineIdentity::serialClash($macID, $posted);
        if (null !== $clash) {
            $otherID = (int)HostManager::resolveHostBySmbios($posted);
            $moved = $otherID > 0 && $otherID !== $macID;
            error_log(
                sprintf(
                    'FOG host identity (inventory, register): MAC resolved '
                    . '"%s" (id %d) but its %s contradicts the firmware; %s',
                    FOGCore::$Host->get('name'),
                    $macID,
                    $clash,
                    $moved
                        ? sprintf('stored on SMBIOS host id %d', $otherID)
                        : 'no SMBIOS host, nothing stored'
                )
            );
            if (!$moved) {
                throw new \Exception(
                    _('This machine is not the host its MAC belongs to')
                );
            }
            FOGCore::$Host = new Host($otherID);
        }
    }
    // Before the fields are set, so the auditChange rows carry what moved.
    // On a re-inventory that is the whole point: a swapped disk, a changed
    // serial or a machine that came back as different hardware is exactly
    // the kind of thing this endpoint is asked about after the fact, and
    // the inventory row itself only ever holds the latest answer.
    Audit::record([
        'type' => 'host.inventory',
        'authSource' => Audit::SOURCE_ANONYMOUS,
        'subjectType' => 'host',
        'subjectID' => (int)FOGCore::$Host->get('id'),
        'subjectLabel' => (string)FOGCore::$Host->get('name'),
        'renderable' => 1
    ]);
    $Inventory = FOGCore::$Host->get('inventory');
    if (!$Inventory instanceof Inventory
        || !$Inventory->isValid()
    ) {
        $Inventory = (new Inventory())
            ->set('hostID', FOGCore::$Host->get('id'));
    }
    // Explicit allowlist of fields a client is permitted to write. Server
    // managed fields (id, hostID, createdTime, deleteDate) are intentionally
    // excluded so a request cannot overwrite a different inventory row or
    // reassign this inventory to another host via mass-assignment.
    $allowedFields = [
        'primaryUser',
        'other1',
        'other2',
        'sysman',
        'sysproduct',
        'sysversion',
        'sysserial',
        'sysuuid',
        'systype',
        'biosversion',
        'biosvendor',
        'biosdate',
        'mbman',
        'mbproductname',
        'mbversion',
        'mbserial',
        'mbasset',
        'cpuman',
        'cpuversion',
        'cpucurrent',
        'cpumax',
        'mem',
        'caseman',
        'casever',
        'caseserial',
        'caseasset',
        'gpuvendors',
        'gpuproducts',
    ];
    // Identifier fields that may be matched against iPXE/SMBIOS values at
    // boot time. Canonicalize on write so the stored value compares
    // reliably with what iPXE reports (see SmbiosIdentity::canonicalize).
    $identifierFields = ['sysuuid', 'sysserial', 'mbserial'];
    foreach ($allowedFields as $field) {
        if (!FOGCore::hasRequestParam($field)) {
            continue;
        }
        $value = FOGCore::requestParam($field);
        if (in_array($field, $identifierFields, true)) {
            $value = SmbiosIdentity::canonicalize($value);
        }
        $Inventory->set($field, $value);
    }
    // hdinfo is a compound string that is parsed out into separate columns.
    if (FOGCore::hasRequestParam('hdinfo')) {
        $val = FOGCore::requestParam('hdinfo');
        preg_match(
            '#model=(.*?),#i',
            $val,
            $hdmodel
        );
        preg_match(
            '#fwrev=(.*?),#i',
            $val,
            $hdfirmware
        );
        preg_match(
            '#serialno=.*#i',
            $val,
            $hdserial
        );
        $hdmodel = (
            count($hdmodel) > 1 ?
            trim($hdmodel[1]) :
            ''
        );
        $hdfirmware = (
            count($hdfirmware) > 1 ?
            trim($hdfirmware[1]) :
            ''
        );
        $hdserial = (
            count($hdserial) ?
            trim(
                str_ireplace(
                    'serialno=',
                    '',
                    trim($hdserial[0])
                )
            ) :
            ''
        );
        $Inventory
            ->set('hdmodel', $hdmodel)
            ->set('hdfirmware', $hdfirmware)
            ->set('hdserial', $hdserial);
    }
    if (!$Inventory->save()) {
        Audit::markOutcome(Audit::FAILED, 'inventory save failed');
        throw new \Exception(
            _('Failed to create inventory for this host')
        );
    }
    echo _('Done');
} catch (\Exception $e) {
    echo Initiator::e($e->getMessage());
}
exit;
