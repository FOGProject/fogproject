<?php
/**
 * The rules for identifying a machine by what its firmware reports.
 *
 * PHP version 7.4+
 *
 * @category SmbiosIdentity
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Base;

/**
 * The rules for identifying a machine by what its firmware reports (#198).
 *
 * Pure functions over strings and arrays: no database, no globals, nothing
 * inherited. That is deliberate twice over. tests/smbios-host-identity.test.php
 * drives the whole decision with arrays, and the boot-menu render harness
 * (tests/lib/bootmenu-harness.php), which stubs FOGBase and loads no manager,
 * can require this file as-is. HostManager::resolveHostBySmbios() adds the
 * inventory query and the pending check around pick().
 *
 * @category SmbiosIdentity
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
final class SmbiosIdentity
{
    /**
     * The inventory fields a booting machine can be identified by, and the
     * order they are reported in. iPXE reads all four straight out of SMBIOS
     * (${uuid}, ${serial}, ${board-serial}, ${asset}) and FOS stores the
     * same four from dmidecode, so a value seen at boot and a value stored
     * from inventory are the same bytes once canonicalized.
     *
     * `caseasset` is the CHASSIS asset tag (SMBIOS type 3). That is what
     * iPXE's ${asset} reads, not the baseboard tag FOS stores as `mbasset`.
     * On the Dell this was written on, the chassis tag is set and the board
     * tag is empty. It is the one value an administrator can set by hand in
     * firmware, which is why it is scored at all: it is the human-settable
     * tiebreaker for machines whose other three fields are placeholders.
     *
     * @var array
     */
    const FIELDS = [
        'sysuuid',
        'sysserial',
        'mbserial',
        'caseasset'
    ];
    /**
     * The fields that say whether a booting machine is the stored host,
     * in rank order (see contradicts()).
     *
     * @var array
     */
    const SERIALS = [
        'sysserial',
        'mbserial'
    ];
    /**
     * The FOG_HOST_IDENTIFY_SMBIOS values that read the firmware at all.
     * Anything else, a missing setting included, is `off`.
     *
     * @var array
     */
    const MODES = [
        'log',
        'enforce',
        'register'
    ];
    /**
     * Values firmware ships when a field was never programmed. Compared
     * case-insensitively after canonicalization. Every entry here was seen
     * on real hardware -- most are from issue #198's thread -- and each
     * one, left in, would match every unit of that make and model to one
     * host. Values made of ONE REPEATED CHARACTER ('0', '000000000',
     * 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF', the MSI UUID that got the
     * first attempt at this reverted) are caught by a rule in isUsable(),
     * not listed.
     *
     * @var array
     */
    const PLACEHOLDERS = [
        '00020003-0004-0005-0006-000700080009',
        '12345678-1234-5678-90AB-CDDEEFAABBCC',
        '123456789',
        'Not Present',
        'Not Settable',
        'Not Specified',
        'Not Applicable',
        'Not Available',
        'None',
        'N/A',
        'Unknown',
        'Default string',
        'To be filled by O.E.M.',
        'To Be Set By OEM',
        'Enter Serial',
        'Type2 - Board Serial Number',
        'Base Board Serial Number',
        'System Serial Number',
        'Chassis Serial Number',
        'Chassis Asset Tag',
        'Asset Tag',
        'No Asset Tag',
        'No Asset Information',
        '.PCIE2'
    ];
    /**
     * Canonicalize a hardware identifier so a value read by iPXE and the
     * value stored from dmidecode compare reliably.
     *
     * Trims surrounding whitespace and collapses internal runs of
     * whitespace to a single space. Case is intentionally left untouched:
     * the DB uses a case-insensitive collation and every comparison here
     * folds case itself, so the stored value stays faithful for display.
     *
     * @param string|null $value The raw identifier value.
     *
     * @return string
     */
    public static function canonicalize($value)
    {
        return trim(preg_replace('/\s+/', ' ', (string)($value ?? '')));
    }
    /**
     * Whether one SMBIOS value is worth matching on.
     *
     * Empty, a known placeholder, or a single character repeated (with the
     * UUID's dashes and Dell's slashes removed first, so '00000000-0000-...'
     * and '/0000000/' both fall out) are all "the firmware did not say".
     *
     * @param string $value the CANONICALIZED value
     *
     * @return bool
     */
    public static function isUsable($value)
    {
        $value = (string)$value;
        if ($value === '') {
            return false;
        }
        foreach (self::PLACEHOLDERS as $bad) {
            if (strcasecmp($value, $bad) === 0) {
                return false;
            }
        }
        $bare = str_replace(['-', '/', ' '], '', $value);
        if ($bare === '' || preg_match('/^(.)\1*$/i', $bare)) {
            return false;
        }
        return true;
    }
    /**
     * Canonicalize the identifiers a machine reported and drop the ones
     * that cannot identify anything.
     *
     * @param array $ids field => raw value, keyed by FIELDS
     *
     * @return array field => canonical value, usable entries only
     */
    public static function usable(array $ids)
    {
        $filter = [];
        foreach (self::FIELDS as $field) {
            $value = self::canonicalize($ids[$field] ?? '');
            if (self::isUsable($value)) {
                $filter[$field] = $value;
            }
        }
        return $filter;
    }
    /**
     * The serial that shows a booting machine is not the stored host, or
     * null. The highest-ranked serial both sides know decides alone.
     *
     * Boot records the firmware onto whichever host the boot resolved to.
     * A USB Ethernet adapter moved between laptops (forum 18319) resolves
     * by its MAC to the host it was last on, so a DIFFERENT machine reports
     * there -- and its UUID used to replace that host's, because a changed
     * UUID was read as a motherboard swap. A system serial that both
     * sides know and that differs says the machine is not this host, so the
     * caller writes nothing. The board serial says it only when one side
     * has no usable system serial.
     *
     * Only the two serials decide, the system serial first. The UUID is
     * the value in question, and the asset tag is set by people. A
     * placeholder on either side is "the firmware did not say", never a
     * contradiction.
     *
     * @param array $stored   field => value from the host's inventory
     * @param array $reported field => value the booting machine sent
     *
     * @return string|null 'sysserial' or 'mbserial', or null
     */
    public static function contradicts(array $stored, array $reported)
    {
        $stored = self::usable($stored);
        $reported = self::usable($reported);
        // The system serial outranks the board serial: a board swap keeps
        // the service tag (vendors re-program it onto the new board) but
        // changes the board serial, and that is exactly the case the UUID
        // replace exists for. The board serial decides only when one side
        // has no usable system serial.
        foreach (self::SERIALS as $field) {
            if (isset($stored[$field], $reported[$field])) {
                return strcasecmp($stored[$field], $reported[$field]) !== 0
                    ? $field
                    : null;
            }
        }
        return null;
    }
    /**
     * Whether a FOG_HOST_IDENTIFY_SMBIOS value lets a unique firmware match
     * win over the MAC. `register` is `enforce` plus the shared-adapter
     * rule in registrationAction(), so both answer yes.
     *
     * @param string $mode the setting, lowercased
     *
     * @return bool
     */
    public static function enforces($mode)
    {
        return in_array($mode, ['enforce', 'register'], true);
    }
    /**
     * What registration does with a firmware match (#198).
     *
     * Registration used to know only the MAC, so a machine whose NIC was
     * replaced came back as a brand-new host and its old record -- image,
     * groups, snapins, AD join -- became a leftover. Now the firmware is
     * asked as well. This decides what its answer is worth, on its own so
     * the gating can be proven with integers and no database:
     *
     *   'none'   nothing to say: the mode is off, the firmware found no
     *            host, or it found the same host the MAC found.
     *   'log'    write the disagreement and let the MAC decide, as before.
     *            Every outcome in log mode, and an enforce-mode disagreement
     *            where the MAC DID find a host: a known MAC is never
     *            overruled at registration, because "already registered as
     *            Y" is at worst an inconvenience while re-pointing Y's MACs
     *            is not.
     *   'attach' enforce or register mode. The MAC found nothing, and the
     *            firmware found exactly one host. This machine IS that host
     *            with a new NIC. Its MACs are added to the host. The
     *            answer is "already registered", as for a known MAC.
     *
     * Register mode adds two outcomes for a MAC that the machine does not
     * own. Example: a USB adapter shared between laptops (forum 18319).
     * They apply only when the MAC found a host and the reported serial
     * contradicts the stored serial of that host (contradicts()):
     *
     *   'other'  the firmware found a different host. The machine is that
     *            host. The answer is "already registered" as that host.
     *            No MAC moves.
     *   'new'    the firmware found nothing. The machine is new. It
     *            registers as a new host. The new host does not get the
     *            MACs of the MAC host.
     *
     * @param string $mode         the FOG_HOST_IDENTIFY_SMBIOS value,
     *                             lowercased
     * @param int    $macID        host the MACs resolved to, 0 for none
     * @param int    $smbiosID     host the firmware resolved to, 0 for none
     * @param bool   $contradicted the reported serial contradicts the MAC
     *                             host's stored serial
     *
     * @return string 'none', 'log', 'attach', 'other' or 'new'
     */
    public static function registrationAction(
        $mode,
        $macID,
        $smbiosID,
        $contradicted = false
    ) {
        $macID = (int)$macID;
        $smbiosID = (int)$smbiosID;
        if ($mode === 'register' && $macID > 0 && $contradicted) {
            if ($smbiosID < 1) {
                return 'new';
            }
            if ($smbiosID !== $macID) {
                return 'other';
            }
        }
        if (!in_array($mode, self::MODES, true)
            || $smbiosID < 1
        ) {
            return 'none';
        }
        if ($macID > 0) {
            return $macID === $smbiosID ? 'none' : 'log';
        }
        return self::enforces($mode) ? 'attach' : 'log';
    }
    /**
     * The reported identifiers that are safe to store, field => value.
     *
     * The same rules boot applies before it writes (Aisle 019): the values
     * come from an unauthenticated request, so the UUID must be a UUID and
     * every other field printable ASCII that fits the column.
     *
     * @param array $ids field => raw value, keyed by FIELDS
     *
     * @return array field => canonical value
     */
    public static function storable(array $ids)
    {
        $keep = [];
        foreach (self::usable($ids) as $field => $value) {
            $ok = $field === 'sysuuid'
                ? preg_match(
                    '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-'
                    . '[0-9A-F]{12}$/i',
                    $value
                )
                : preg_match('/^[\x20-\x7E]{1,250}$/', $value);
            if ($ok) {
                $keep[$field] = $value;
            }
        }
        return $keep;
    }
    /**
     * A MAC address for a machine that has no NIC of its own, or null.
     *
     * For a whole task, FOS names itself to the server by the host's
     * stored MAC (the kernel's mac= argument). The server finds the host by
     * that MAC. A laptop that boots only through a shared USB adapter
     * (forum 18319) has no MAC that another host does not already own.
     * Register mode gives it this MAC. The MAC is locally administered
     * (02:...), so no vendor ships it. The MAC comes from the firmware, so
     * a machine that registers again gets the same value. The network
     * never sees it. FOS sets the booting NIC to the MAC that iPXE booted
     * with (setmacto).
     *
     * The asset tag is left out because people change it.
     *
     * @param array $ids field => raw value, keyed by FIELDS
     *
     * @return string|null
     */
    public static function syntheticMac(array $ids)
    {
        $usable = self::usable($ids);
        $parts = [];
        foreach (['sysuuid', 'sysserial', 'mbserial'] as $field) {
            if (isset($usable[$field])) {
                $parts[] = strtolower($usable[$field]);
            }
        }
        if (!$parts) {
            return null;
        }
        $hash = substr(md5(implode('|', $parts)), 0, 10);
        return '02:' . implode(':', str_split($hash, 2));
    }
    /**
     * Pick the one host the reported identifiers point at, or nothing.
     *
     * The rules, each of which exists because its absence has already
     * misidentified a machine:
     *
     *  - Every field is scored INDEPENDENTLY, one point per match, compared
     *    per field (array_intersect_assoc): a vendor that writes the same
     *    string into the system and board serials scores once, not twice,
     *    and a system serial can never satisfy a board-serial match.
     *  - The winner must hold the top score ALONE. A tie is two machines
     *    the firmware cannot tell apart -- cloned VMs sharing a UUID, a batch
     *    of boards with one serial -- and the answer is "no opinion", so
     *    the caller falls back to the MAC. This guard is what the reverted
     *    UUID-only attempt lacked.
     *  - The asset tag cannot win on its own. It is set by people, so it is
     *    the tiebreaker between hosts that already share a firmware field,
     *    never an identity by itself.
     *
     * @param array $filter field => canonical value, from usable()
     * @param array $rows   inventory rows: each an array with hostID and the
     *                      identity fields (objects are accepted too)
     *
     * @return int|null the host id, or null for none or ambiguous
     */
    public static function pick(array $filter, array $rows)
    {
        if (empty($filter) || empty($rows)) {
            return null;
        }
        $want = array_map('strtolower', $filter);
        $best = 0;
        $bestHost = null;
        $holders = 0;
        foreach ($rows as $row) {
            $row = (array)$row;
            $have = [];
            foreach (self::FIELDS as $field) {
                $have[$field] = strtolower(
                    self::canonicalize($row[$field] ?? '')
                );
            }
            $matched = array_intersect_assoc($have, $want);
            unset($matched['caseasset']);
            // A firmware field has to match before the asset tag counts.
            if (empty($matched)) {
                continue;
            }
            $score = count($matched)
                + (isset($want['caseasset'])
                    && $have['caseasset'] === $want['caseasset'] ? 1 : 0);
            if ($score > $best) {
                $best = $score;
                $bestHost = (int)($row['hostID'] ?? 0);
                $holders = 1;
            } elseif ($score === $best) {
                $holders++;
            }
        }
        if ($best < 1 || $holders !== 1 || $bestHost < 1) {
            return null;
        }
        return $bestHost;
    }
}
