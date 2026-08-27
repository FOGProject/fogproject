<?php
/**
 * The architecture class.
 *
 * PHP version 7.4+
 *
 * @category Architecture
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * The architecture class.
 *
 * One row per instruction set a host can boot or an image can be captured
 * from. Hosts and images both reference it (`hosts.hostArchID`,
 * `images.imageArchID`, schema step 372); before that step each stored its
 * own free-text copy of the same three literals.
 *
 * `access` mirrors `taskTypes.ttIsAccess`: it says which side of the pairing
 * an architecture may be picked on -- `host`, `image`, or `both`. The pickers
 * on Host General and Image General filter on it, so an architecture this
 * server can image but never boot (or the reverse) can be said out loud
 * rather than being offered everywhere and corrected by hand.
 *
 * @category Architecture
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Architecture extends FOGController
{
    /**
     * The architecture table.
     *
     * @var string
     */
    protected $databaseTable = 'architectures';
    /**
     * The architecture fields and common names.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'archID',
        'name' => 'archName',
        'description' => 'archDescription',
        'access' => 'archIsAccess'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
    /**
     * Name to id, memoised for the request.
     *
     * @var array
     */
    private static $_ids = [];
    /**
     * Id to name, memoised for the request.
     *
     * @var array
     */
    private static $_names = [];
    /**
     * Folds an architecture spelling onto the one this table stores.
     *
     * Three sources report an architecture and none of them agree. iPXE sets
     * `${buildarch}` to i386/x86_64/arm64 and that is the spelling seeded into
     * `architectures`. FOS reports `uname -m`, which says aarch64 for the same
     * hardware iPXE calls arm64. Anything reading a Debian-flavoured tool gets
     * amd64 for x86_64, and 32-bit x86 has been spelled i486/i586/i686 for
     * thirty years.
     *
     * Folding rather than seeding a row per spelling is deliberate: a host and
     * an image describing the same machine must land on the SAME row, or the
     * compatibility test below compares two names that are equal in fact and
     * different as strings, and refuses a deploy that would have worked.
     *
     * An architecture this does not recognise is returned unchanged rather
     * than guessed at. idFromName() then finds no row and answers 0, which
     * reads as "not recorded" -- see canRun() for why that must never refuse.
     *
     * @param string $arch the spelling as it arrived
     *
     * @return string the spelling this table stores
     */
    public static function normalizeName($arch)
    {
        $arch = strtolower(trim((string)$arch));
        switch ($arch) {
            case 'aarch64':
                return 'arm64';
            case 'amd64':
                return 'x86_64';
            case 'i486':
            case 'i586':
            case 'i686':
                return 'i386';
        }
        return $arch;
    }
    /**
     * May a host of one architecture run an image of another?
     *
     * COMPATIBILITY, not equality, and the difference is the whole reason
     * this method exists:
     *
     *   - 32-bit x86 code runs unchanged on a 64-bit x86 CPU, so an i386
     *     image onto an x86_64 host is a legitimate deploy that `!==` would
     *     wrongly refuse. Genuine i386 hosts are 32-bit-only hardware --
     *     iPXE's cpuid promotion reports x86_64 for anything 64-bit capable
     *     -- so the case is rare, which is exactly why a later
     *     "simplification" back to inequality would pass review and break
     *     somebody's lab of old machines.
     *
     *   - ARM and x86 are different instruction sets in BOTH directions, so
     *     neither substitutes for the other.
     *
     *   - UNKNOWN on either side is ALLOWED. Every image captured before
     *     schema step 370 and every host that has not PXE booted since step
     *     369 reads NULL here and always will unless someone sets it by hand.
     *     A refusal must rest on two observed facts, never on a missing one;
     *     refusing on absence would turn an upgrade into a fleet-wide outage.
     *
     * @param string $imageArch the image's architecture name
     * @param string $hostArch  the host's architecture name
     *
     * @return bool
     */
    public static function canRun($imageArch, $hostArch)
    {
        $imageArch = self::normalizeName($imageArch);
        $hostArch = self::normalizeName($hostArch);
        if ('' === $imageArch || '' === $hostArch) {
            return true;
        }
        if ($imageArch === $hostArch) {
            return true;
        }
        return 'i386' === $imageArch && 'x86_64' === $hostArch;
    }
    /**
     * The id of the row holding this name, or 0.
     *
     * Deliberately does NOT create a missing row. The busiest caller is
     * IpxeBootMenu::_recordHostArch(), which runs on an unauthenticated request
     * (boot.php has to be reachable before a host has any identity), so a row
     * created here would be a row created by anything that can reach the boot
     * menu. 0 reads as "not recorded", which is a state the schema and
     * canRun() already handle.
     *
     * @param string $name the architecture name, in any known spelling
     *
     * @return int
     */
    public static function idFromName($name)
    {
        $name = self::normalizeName($name);
        if ('' === $name) {
            return 0;
        }
        if (!array_key_exists($name, self::$_ids)) {
            $ids = Route::getIds('architecture', ['name' => $name]);
            self::$_ids[$name] = count($ids) > 0 ? (int)array_shift($ids) : 0;
        }
        return self::$_ids[$name];
    }
    /**
     * The name on this row, or '' when there is no such row.
     *
     * @param mixed $id the architecture id
     *
     * @return string
     */
    public static function nameFromId($id)
    {
        $id = (int)$id;
        if ($id < 1) {
            return '';
        }
        if (!array_key_exists($id, self::$_names)) {
            $Arch = self::getClass('Architecture', $id);
            self::$_names[$id] = $Arch->isValid()
                ? (string)$Arch->get('name')
                : '';
        }
        return self::$_names[$id];
    }
    /**
     * The architectures pickable on one side of the pairing.
     *
     * @param string $side 'host' or 'image'
     *
     * @return array id => name, ordered by name
     */
    public static function pickable($side)
    {
        $out = [];
        $Archs = Route::getList(
            'architecture',
            ['access' => [$side, 'both']],
            'AND',
            'name'
        );
        // Route::getList() hands back stdClass rows, not Architecture
        // objects -- ->get() is not available here.
        foreach ((array)$Archs as &$Arch) {
            $out[(int)$Arch->id] = (string)$Arch->name;
            unset($Arch);
        }
        return $out;
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\Architecture', 'Architecture');
