<?php
/**
 * iPXE boot menu for the FOG PXE system
 *
 * PHP version 7.4+
 *
 * @category IpxeBootMenu
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Boot;

use FOG\Base\FOGBase;
use FOG\Items\Architecture;
use FOG\Items\Host;
use FOG\Items\Image;
use FOG\Items\MulticastSession;
use FOG\Items\MulticastSessionAssociation;
use FOG\Items\PXEMenuOptions;
use FOG\Items\StorageNode;
use FOG\Items\TaskType;
use FOG\Router\Route;

/**
 * iPXE boot menu for the FOG PXE system
 *
 * Named for what it builds: an iPXE script, and nothing else. The generic
 * name it carried until 1.6.0-beta told readers otherwise for years.
 *
 * @category IpxeBootMenu
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class IpxeBootMenu extends BootMenuBase
{
    /**
     * The init string
     *
     * @var string
     */
    private $_initrd;
    /**
     * The boot url string
     *
     * @var string
     */
    private $_booturl;
    /**
     * The mem disk string
     *
     * @var string
     */
    private $_memdisk;
    /**
     * The memtest string
     *
     * @var string
     */
    private $_memtest;
    /**
     * The web string
     *
     * @var string
     */
    private $_web;
    /**
     * The default choice
     *
     * @var string
     */
    private $_defaultChoice;
    /**
     * The boot exit type
     *
     * @var string
     */
    private $_bootexittype;
    /**
     * The log level string
     *
     * @var string
     */
    private $_loglevel;
    /**
     * The shutdown string
     *
     * @var string
     */
    private $_shutdown;
    /**
     * The path string
     *
     * @var string
     */
    private $_path;
    /**
     * The hidden menu storage
     *
     * @var bool
     */
    private $_hiddenmenu;
    /**
     * The timeout of the menu
     *
     * @var int
     */
    private $_timeout;
    /**
     * The key sequance storage
     *
     * @var string
     */
    private $_KS;
    /**
     * The selectable exit types
     *
     * @var array
     */
    private static $_exitTypes = [];
    /**
     * The resolved architecture profile, memoised by _arch()
     *
     * @var array|null
     */
    private static $_archProfile = null;
    /**
     * Lines to show the operator about how this boot was resolved
     *
     * @var array
     */
    private $_notices = [];
    /**
     * Everything that varies with the booting machine's architecture.
     *
     * iPXE tells us what it is: default.ipxe posts "param arch ${arch}",
     * derived from ${buildarch} (with a cpuid promotion of a 32-bit build
     * running on a 64-bit CPU), and every chain this class emits carries it
     * forward. The value is therefore the architecture of the iPXE binary
     * DHCP handed the machine -- see the option-93 classes the installer
     * writes into dhcpd.conf -- not a guess.
     *
     * This used to be six separate stripos($_REQUEST['arch'], 'arm') tests
     * scattered through the file, one beside each thing that varies, so
     * adding an architecture meant finding all of them and an architecture
     * FOG has no kernel for silently got the x86_64 one. One table instead:
     * every consumer looks its answer up here.
     *
     * 'warn' is set only when the client named an architecture FOG ships no
     * FOS kernel for. An ABSENT arch is not that -- plenty of internal
     * chains post no arch at all -- so it resolves to x86_64 silently, the
     * behaviour that has always been in place.
     *
     * @return array the profile for this request
     */
    /**
     * Records the architecture this machine just reported.
     *
     * FOG has always been told this and never kept it, so nothing away from a
     * live boot -- a host edit page, a group kernel assignment, a deploy task
     * -- could know what kind of machine it was dealing with. That is the gap
     * _fileFitsArch() below has to paper over with a filename guess, and it is
     * why an x86 image could be sent to an ARM host with nothing able to
     * object. See schema step 369.
     *
     * Three things this deliberately does NOT do:
     *
     * 1. It does not trust the profile. _arch() falls back to x86_64 when no
     *    arch arrives at all, which is right for picking a kernel and wrong
     *    for recording a fact -- it would stamp x86_64 on every host booting
     *    an iPXE too old to send the parameter. Only a raw value that IS one
     *    of the three FOG builds for is stored.
     *
     * 2. It does not feed the boot decision. boot.php is unauthenticated by
     *    necessity, so this value is attacker-controlled; the kernel is still
     *    chosen from the live request every time. That is what keeps a
     *    poisoned value costing a wrong warning on a form rather than an
     *    unbootable machine.
     *
     * 3. It does not write unless the value changed. This runs on every PXE
     *    boot of every host, so the steady state is a comparison.
     *
     * @return void
     */
    private static function _recordHostArch()
    {
        if (!self::$Host instanceof Host || !self::$Host->isValid()) {
            return;
        }
        $raw = strtolower(trim((string)($_REQUEST['arch'] ?? '')));
        // The whitelist is the point, not a formality: this is the only
        // guard between an unauthenticated request body and a stored value.
        if (!in_array($raw, ['i386', 'x86_64', 'arm64'], true)) {
            return;
        }
        // Resolve to a row rather than storing the string (schema step 372).
        // idFromName() deliberately never creates one: this is the only
        // caller that runs unauthenticated, and a row created here would be a
        // row created by anything that can reach boot.php. 0 means the seed
        // was deleted, and the right answer to that is to record nothing.
        $archID = Architecture::idFromName($raw);
        if ($archID < 1) {
            return;
        }
        if ($archID === (int)self::$Host->get('archID')) {
            return;
        }
        self::getClass('HostManager')->update(
            ['id' => self::$Host->get('id')],
            '',
            ['archID' => $archID]
        );
        self::$Host->set('archID', $archID);
    }
    private static function _arch()
    {
        if (null !== self::$_archProfile) {
            return self::$_archProfile;
        }
        $x86 = [
            'id' => 'x86_64',
            'label' => '64-bit x86',
            'isArm' => false,
            'kernelKey' => 'FOG_TFTP_PXE_KERNEL',
            'initKey' => 'FOG_PXE_BOOT_IMAGE',
            'grubBinary' => 'grub.exe',
            'grubConfigFlag' => '--config-file',
            'refindBinary' => 'refind_x64.efi',
            'refindConf' => true,
            'mokManager' => 'secureboot/mmx64.efi',
            'memdisk' => true,
            'warn' => '',
        ];
        $profiles = [
            'x86_64' => $x86,
            'i386' => array_merge(
                $x86,
                [
                    'id' => 'i386',
                    'label' => '32-bit x86',
                    'kernelKey' => 'FOG_TFTP_PXE_KERNEL_32',
                    'initKey' => 'FOG_PXE_BOOT_IMAGE_32',
                    'refindBinary' => 'refind_ia32.efi',
                    // No signed 32-bit UEFI shim exists to chain to.
                    'mokManager' => '',
                ]
            ),
            'arm64' => array_merge(
                $x86,
                [
                    'id' => 'arm64',
                    'label' => '64-bit ARM',
                    'isArm' => true,
                    'kernelKey' => 'FOG_TFTP_PXE_KERNEL_ARM',
                    'initKey' => 'FOG_PXE_BOOT_IMAGE_ARM',
                    'grubBinary' => 'grub_aa64.exe',
                    // --configfile, not --config-file. Kept as it has always
                    // been emitted rather than "corrected" to match the x86
                    // spelling: which one grub_aa64.exe accepts is a property
                    // of that binary, and changing it here would be a silent
                    // behaviour change on the one path that cannot be tested
                    // from an x86 machine.
                    'grubConfigFlag' => '--configfile',
                    'refindBinary' => 'refind_aa64.efi',
                    // The arm build chains rEFInd directly; there is no
                    // aarch64 refind.conf staged beside it to imgfetch.
                    'refindConf' => false,
                    // arm64's MokManager is mmaa64.efi, NOT mmx64.efi -- the
                    // binary is named for the architecture it runs on, and
                    // fog-ipxe stages it under that name. Chaining to
                    // arm64-efi/mmx64.efi is a file that has never existed.
                    'mokManager' => 'secureboot/arm64-efi/mmaa64.efi',
                    // memdisk is a 16-bit x86 real-mode loader. There is no
                    // aarch64 build of it and there never will be.
                    'memdisk' => false,
                ]
            ),
        ];
        $raw = strtolower(trim((string)($_REQUEST['arch'] ?? '')));
        if ('' === $raw || 'x86_64' === $raw) {
            self::$_archProfile = $profiles['x86_64'];
            return self::$_archProfile;
        }
        if ('i386' === $raw) {
            self::$_archProfile = $profiles['i386'];
            return self::$_archProfile;
        }
        $profile = false !== stripos($raw, 'arm')
            ? $profiles['arm64']
            : $profiles['x86_64'];
        // Anything that is not one of the three FOG builds a kernel for.
        // arm32 lands here too: it matches on 'arm' and so is handed the
        // aarch64 files, which is the same thing that used to happen
        // silently -- the difference is that the operator is now told, on
        // screen, why the boot is about to fail.
        if (!in_array($raw, ['x86_64', 'i386', 'arm64'], true)) {
            $profile['warn'] = sprintf(
                'echo FOG ships no boot kernel for %s -- trying the %s one.',
                self::_echoSafe($raw),
                $profile['id']
            );
        }
        self::$_archProfile = $profile;

        return self::$_archProfile;
    }
    /**
     * Whether a kernel or init filename belongs to this machine's
     * architecture.
     *
     * A per-host or per-group kernel/init override is a bare filename with
     * no architecture in it, and `hosts` stores no architecture at all, so
     * nothing at edit time can warn an admin that the kernel they picked is
     * wrong for some of the machines it will reach. Setting a kernel on a
     * mixed group therefore handed an x86 bzImage to every ARM member,
     * silently, undoing the arch selection made moments earlier.
     *
     * Only the arm/non-arm split is policed. i386 code runs on x86_64, so a
     * deliberate 32-bit override is a legitimate choice and is left alone;
     * aarch64 and x86 are not the same instruction set in either direction,
     * so an override across that line can only ever fail to boot.
     *
     * The test is the `arm` filename prefix -- the convention every kernel
     * and init FOG ships follows (arm_Image, arm_init.cpio.gz), and the same
     * one FOGPage::kernelFileList() already keys off.
     *
     * @param string $file the stored override
     *
     * @return bool
     */
    private static function _fileFitsArch($file)
    {
        $file = trim((string)$file);
        if ('' === $file) {
            return true;
        }

        return (0 === stripos(basename($file), 'arm')) === self::_arch()['isArm'];
    }
    /**
     * Applies a host's kernel/init override to the arch-selected default.
     *
     * @param string $field   'kernel' or 'init'
     * @param string $default what the architecture profile selected
     *
     * @return string the filename to boot
     */
    private function _hostOverride($field, $default)
    {
        $override = trim((string)self::$Host->get($field));
        if ('' === $override) {
            return $default;
        }
        if (self::_fileFitsArch($override)) {
            return $override;
        }
        // Say so on screen rather than just ignoring it: from the operator's
        // side an ignored override and an honoured one look identical, and
        // the machine that is misconfigured is the one that needs telling.
        $this->_notices[] = sprintf(
            'echo Ignoring host %s %s -- this machine is %s. Using %s.',
            $field,
            self::_echoSafe($override),
            self::_arch()['label'],
            self::_echoSafe($default)
        );

        return $default;
    }
    /**
     * Builds the std class property and value items as appropriate
     *
     * @param object $object
     * @return array
     */
    public static function generateIpxeItems($object)
    {
        $ignore_keys = [
            'description',
            'id',
            'hostID',
            'DT_RowId',
            'createdTime',
            'deleteDate',
            'sec_time',
            'deployed',
            'hostLink',
            'token',
            'tokenlock',
            'ADUser',
            'ADPass',
            'ADOU',
            'ADDomain',
            'createdBy',
        ];
        // service/ipxe/boot.php is unauthenticated by necessity -- a booting
        // NIC has no credential to present -- so what leaves here is the only
        // control available. The hand-maintained list above had drifted from
        // the router's and was emitting pub_key (the host's symmetric AES-256
        // session key, not a public key), sec_tok, productKey and
        // ADPassLegacy to anyone who POSTed a known mac. Source the secret
        // names from the router's own tiers so the two lists cannot drift
        // again when a new secret field is added.
        // Reported by Aisle Research (086 / 4.28.2).
        // Both tiers: the 'always' tier holds the secrets no client may
        // read back at all, which this endpoint least of all -- it is the
        // unauthenticated one. Iterating only the first tier would let a
        // field silently leave this blocklist the moment it was promoted to
        // the stricter tier, which is the exact drift this loop exists to
        // prevent.
        // Via sensitiveFieldMap() rather than the raw properties, so a
        // secret a plugin declares through API_SENSITIVE_FIELDS is blocked
        // here too. Reading the properties direct would silently exempt
        // every plugin-declared field from the one unauthenticated endpoint.
        foreach (Route::sensitiveFieldMap() as $tier) {
            foreach ($tier as $fields) {
                $ignore_keys = array_merge($ignore_keys, $fields);
            }
        }
        $output = [];
        foreach ($object as $property => $value) {
            /**
             * Absent, not merely falsy. '0' is falsy in PHP and meaningful
             * in iPXE, and skipping it meant a host with enforce=0 emitted
             * no `set enforce` at all -- indistinguishable from a host where
             * the field does not apply. Conditional menus in iPXE are built
             * on isset/iseq, so a flag that cannot be read as false is a
             * flag that cannot be branched on, which is half of what GH-572
             * asked for.
             */
            if (in_array($property, $ignore_keys)
                || is_object($value)
                || null === $value
                || '' === $value
                || [] === $value
            ) {
                continue;
            }
            if (is_array($value)) {
                $count = 0;
                foreach ($value as $item) {
                    // ?expand reaches this endpoint too, and an expanded
                    // relation is an array of objects. Interpolating one is a
                    // fatal ("could not be converted to string"), which turned
                    // ?expand=all into an unauthenticated 500.
                    if (!is_scalar($item)) {
                        continue;
                    }
                    $safe = self::_setSafe($item);
                    if ('' === $safe) {
                        continue;
                    }
                    $output[] = "set {$property}{$count} {$safe}";
                    $count++;
                }
            } else {
                if ($property == 'name') {
                    $property = 'host' . $property;
                }
                $safe = self::_setSafe($value);
                if ('' === $safe) {
                    continue;
                }
                $output[] = "set {$property} {$safe}";
            }
        }

        return $output;
    }
    /**
     * Makes a value safe to interpolate into an iPXE `set` line.
     *
     * The same threat as _echoSafe() below, over a different character set.
     * An iPXE script is newline-delimited commands with `&&` and `||` as
     * in-line separators, and Initiator::sanitizeOutput() collapses a RUN of
     * whitespace to its first character rather than removing newlines -- so a
     * newline, or a bare `&&`, inside a value ends the `set` and begins a new
     * command that iPXE then runs.
     *
     * Which is reachable: boot.php is unauthenticated by necessity, and
     * service/inventory.php authenticates by MAC alone, so the values these
     * lines carry are not all under an admin's control. Unsanitized, a
     * stored separator ended the `set` and left whatever followed it running
     * as its own command -- emitted above the menu, so before the operator
     * sees anything.
     *
     * `$`, `{` and `}` go for a lesser reason: iPXE expands ${...} at use, so
     * a stored value could otherwise read back another variable in the menu.
     *
     * A whitelist is not usable here the way it is for _echoSafe(). Those
     * values are architecture names and filenames; these are SMBIOS strings,
     * image names, memory sizes and dates, which legitimately carry spaces,
     * slashes, commas and colons. So what is removed is exactly iPXE's own
     * syntax, and the value is length-capped so one long field cannot push
     * the rest of the menu off screen.
     *
     * @param mixed $value the value to render
     *
     * @return string
     */
    private static function _setSafe($value)
    {
        /**
         * Before the cast, not after: (string)false is '' and would be
         * dropped by the caller's empty check, silently reintroducing the
         * very omission the falsy guard above was widened to fix.
         */
        if (is_bool($value)) {
            $value = (int)$value;
        }
        $value = preg_replace(
            '/[\x00-\x1F\x7F&|${}]/',
            '',
            (string)$value
        );

        return trim(substr((string)$value, 0, 255));
    }
    /**
     * Initializes the boot menu class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        // Before anything else uses it: the machine has just told us
        // what it is, and this is the only moment FOG ever hears it.
        self::_recordHostArch();
        $arch = self::_arch();
        /**
         * GH: the arch-specific grub binary used to be swapped in AFTER the
         * $grub array below had already been sprintf()'d from the x86 one,
         * so the aarch64 branch was dead code and an ARM client choosing any
         * grub exit type was handed grub.exe. Resolving the architecture
         * first is what makes that branch actually reach the exit types.
         */
        $grubChain = sprintf(
            'chain -ar ${boot-url}/service/ipxe/%s %s="%%s"',
            $arch['grubBinary'],
            $arch['grubConfigFlag']
        );

        /** Booting to hard disk via sanboot
         * The default for both FOG_BOOT_EXIT_TYPE and FOG_EFI_BOOT_EXIT_TYPE, and
         * the fallback below when neither names a type this class knows. UEFI used
         * to default to refind_efi instead, because iPXE's sanboot could not then
         * boot the next UEFI boot entry and leaving FOG meant chainloading rEFInd
         * over HTTP to do it. It can now, so the third-party boot manager buys
         * nothing -- see the migration labelled 337 in commons/schema.php.
         * reset console to detected display resolution first to avoid graphical anamolies
         * --drive 0 will boot the first drive found with an efi boot file at the default file path of \EFI\Boot\bootx64.efi (side note, removable install media is found before local disks)
         * could add additional linux detections per distro like `sanboot --drive 0 --no-describe --extra \EFI\rocky` and EFI\centos and EFI\debian etc. but need an || entry for every distro.
         * If the --drive 0 option fails to find a boot option it then just tries the first 3 found local drives sequentially, after that it will fail if it hasn't booted.
         * See also https://ipxe.org/cmd/console and https://ipxe.org/cmd/sanboot
        */
        
        $sanboot = 'console && sanboot --drive 0 --no-describe || sanboot --no-describe --drive 0x80 || sanboot --no-describe --drive 0x81 || sanboot --no-describe --drive 0x82';
        $grub = [
            'basic' => sprintf(
                $grubChain,
                'rootnoverify (hd0);chainloader +1'
            ),
            '1cd' => sprintf(
                $grubChain,
                'cdrom --init;map --hook;root (cd0);chainloader (cd0)"'
            ),
            '1fw' => sprintf(
                $grubChain,
                'find --set-root /BOOTMGR;chainloader /BOOTMGR"'
            )
        ];
        /**
         * The generic refind.efi is preferred over the arch-suffixed build
         * when one is staged, but only where the arch-suffixed name would
         * have been refind_x64.efi -- the unsuffixed binary is an x86_64
         * one, so substituting it for the ia32 or aa64 build would hand the
         * machine a loader it cannot execute.
         */
        $refindfile = $arch['refindBinary'];
        if ('refind_x64.efi' === $refindfile
            && file_exists(BASEPATH . '/service/ipxe/refind.efi')
        ) {
            $refindfile = 'refind.efi';
        }
        $refind = sprintf(
            'chain -ar ${boot-url}/service/ipxe/%s',
            $refindfile
        );
        if ($arch['refindConf']) {
            $refind = sprintf(
                'imgfetch ${boot-url}/service/ipxe/refind.conf%s%s',
                "\n",
                $refind
            );
        }
        if ($arch['warn']) {
            $this->_notices[] = $arch['warn'];
        }
        self::$_exitTypes = [
            'sanboot' => $sanboot,
            'grub' => $grub['basic'],
            'grub_first_hdd' => $grub['basic'],
            'grub_first_cdrom' => $grub['1cd'],
            'grub_first_found_windows' => $grub['1fw'],
            'refind_efi' => $refind,
            'exit' => 'exit 0',
        ];
        list(
            $webserver,
            $curroot
        ) = self::getSetting(['FOG_WEB_HOST','FOG_WEB_ROOT']);
        /**
         * GH-529: FOG_WEB_ROOT was read and then immediately overwritten with a
         * literal '/fog/', so every boot URL handed to iPXE ignored the setting
         * -- this is why a custom webroot broke PXE booting (GH-502).
         *
         * Normalise instead of trusting the stored form: the value has been
         * written by the installer, by hand in FOG Settings, and by older
         * versions, so it turns up with and without either slash. $curroot is
         * the path form ('/fog/') used to build absolute URLs; $bootroot is the
         * bare form ('fog') because the iPXE template already supplies the
         * separator in '${fog-ip}/${fog-webroot}'.
         *
         * basename() used to produce that bare form, which also meant a nested
         * webroot such as '/apps/fog/' silently collapsed to 'fog'. trim keeps
         * every segment.
         */
        $bootroot = trim((string)$curroot, '/');
        $curroot = '/' . ($bootroot === '' ? '' : $bootroot . '/');
        /**
         * BOOT_ITEM_NEW_SETTINGS passes 'webroot' by reference, but no
         * $webroot was ever assigned, so PHP created it at the call and
         * every plugin reading it saw NULL. Bind it to the bare form that
         * accompanies 'webserver' in the same payload -- the value
         * 'set fog-webroot' emits -- so the argument means what its name
         * says.
         */
        $webroot = $bootroot;
        $this->_web = sprintf('%s://%s%s', self::$httpproto, $webserver, $curroot);
        /**
         * setmacto is the MAC FOS forces onto whichever interface it manages
         * to reach us on, so it has to be the MAC iPXE actually booted with.
         * ${net0/mac} was wrong on any machine whose first enumerated NIC has
         * no link: iPXE gets its lease over the NIC that does, FOS then
         * rewrites that NIC to the unplugged one's MAC and the re-DHCP fails.
         * ${netX} is iPXE's alias for the last opened network device, so it
         * follows the interface that got us here and still resolves to net0
         * on a single-NIC machine.
         */
        $Send['booturl'] = [
            '#!ipxe',
            "set fog-ip $webserver",
            sprintf('set fog-webroot %s', $bootroot),
            'set boot-url '
            . self::$httpproto
            . '://${fog-ip}/${fog-webroot}',
            'set setmacto ${netX/mac}',
        ];
        $sysuuid = filter_input(INPUT_POST, 'sysuuid') ?: filter_input(INPUT_GET, 'sysuuid') ?: '';
        if (self::$Host->isValid()) {
            // Aisle 019: validate EVERY write, not just the first one. The
            // non-empty guard this replaces (removed by b0b303828e) meant a host
            // that already had a sysuuid took the POSTed value UNVALIDATED, so a
            // second unauthenticated boot.php POST could store arbitrary HTML --
            // which the Inventory Report then rendered raw. A bare `mac=` POST
            // with no sysuuid at all also blanked a good stored value.
            // Skipping the write on a bad/absent value (rather than blanking it)
            // keeps the legitimate motherboard-swap case that b0b303828e was
            // presumably after: a well-formed UUID that differs from the stored
            // one still updates. A malformed or missing one can no longer
            // destroy what is already there.
            if ($sysuuid
                && preg_match(
                    '/^[0-9A-Fa-f]{8}-'
                    . '[0-9A-Fa-f]{4}-'
                    . '[0-9A-Fa-f]{4}-'
                    . '[0-9A-Fa-f]{4}-'
                    . '[0-9A-Fa-f]{12}$/',
                    $sysuuid
                )
                && self::$Host->get('inventory')->get('sysuuid') != $sysuuid
            ) {
                self::$Host->get('inventory')->getManager()->update(
                    ['hostID' => self::$Host->get('id')],
                    '',
                    ['sysuuid' => $sysuuid]
                );
            }
            // getItem(), not indiv(): a miss answers null rather than
            // reaching breakHead()'s exit, which on a boot request means the
            // machine gets a truncated iPXE script instead of a menu.
            $host = Route::getItem('host', self::$Host->get('id'));
            if ($host) {
                $host_items = self::generateIpxeItems($host);
                foreach ($host_items as $item) {
                    $Send['hostinfo'][] = $item;
                }
            }
            $inventory = Route::getList(
                'inventory',
                ['hostID' => self::$Host->get('id')]
            );
            if (!empty($inventory)) {
                $inventory_items = self::generateIpxeItems($inventory[0]);
                foreach ($inventory_items as $item) {
                    $Send['inventoryinfo'][] = $item;
                }
            }
        }
        $host_field_test = 'biosexit';
        $global_field_test = 'FOG_BOOT_EXIT_TYPE';
        if (isset($_REQUEST['platform']) && $_REQUEST['platform'] == 'efi') {
            $host_field_test = 'efiexit';
            $global_field_test = 'FOG_EFI_BOOT_EXIT_TYPE';
        }
        $StorageNodeID = self::minId(Route::getIds('storagenode', ['isEnabled' => 1, 'isMaster' => 1]));
        $StorageNode = new StorageNode($StorageNodeID);
        $serviceNames = [
            'FOG_EFI_BOOT_EXIT_TYPE',
            'FOG_KERNEL_ARGS',
            'FOG_KERNEL_DEBUG',
            'FOG_KERNEL_LOGLEVEL',
            'FOG_KERNEL_RAMDISK_SIZE',
            'FOG_KEYMAP',
            'FOG_KEY_SEQUENCE',
            'FOG_MEMTEST_KERNEL',
            'FOG_PXE_HIDDENMENU_TIMEOUT',
            'FOG_PXE_MENU_HIDDEN',
            'FOG_PXE_MENU_TIMEOUT',
            // Named by the architecture profile rather than fetching all
            // six and choosing afterwards, so there is exactly one place
            // that decides which kernel an architecture boots.
            $arch['kernelKey'],
            $arch['initKey'],
        ];
        list(
            $exit,
            $kernelArgs,
            $kernelDebug,
            $kernelLogLevel,
            $kernelRamDisk,
            $keymap,
            $keySequence,
            $memtest,
            $hiddenTimeout,
            $hiddenmenu,
            $menuTimeout,
            $bzImage,
            $imagefile
        ) = self::getSetting($serviceNames);
        $memdisk = 'memdisk';
        $loglevel = $kernelLogLevel;
        $ramsize = $kernelRamDisk;
        $timeout = (
            $hiddenmenu > 0 && empty($_REQUEST['menuAccess']) ?
            $hiddenTimeout :
            $menuTimeout
        ) * 1000;
        $keySequence = (
            $hiddenmenu > 0 && empty($_REQUEST['menuAccess']) ?
            $keySequence :
            ''
        );
        /**
         * A host (or the group that wrote to it) can name its own kernel and
         * init, and that override deliberately wins over the arch default --
         * but only where it CAN win. See _fileFitsArch(): an x86 filename on
         * an ARM client, or the reverse, is an override that cannot boot, and
         * taking it silently discarded the correct arch-selected kernel that
         * had just been chosen.
         *
         * Say so on screen rather than just ignoring it: from the operator's
         * side an ignored override and an honoured one look identical, and
         * the machine that is misconfigured is the one that needs telling.
         */
        $bzImage = $this->_hostOverride('kernel', $bzImage);
        $imagefile = $this->_hostOverride('init', $imagefile);
        $StorageGroup = $StorageNode->getStorageGroup();
        $exit = trim(
            (
                self::$Host->get($host_field_test) ?:
                self::getSetting($global_field_test)
            )
        );
        if (!$exit || !in_array($exit, array_keys(self::$_exitTypes))) {
            $exit = 'sanboot';
        }
        $initrd = $imagefile;
        $hookInitrd = $initrd;
        if (self::$Host->isValid()) {
            self::$HookManager->processEvent(
                'BOOT_ITEM_NEW_SETTINGS',
                [
                    'Host' => &self::$Host,
                    'StorageGroup' => &$StorageGroup,
                    'StorageNode' => &$StorageNode,
                    'webserver' => &$webserver,
                    'webroot' => &$webroot,
                    'memtest' => &$memtest,
                    'memdisk' => &$memdisk,
                    'bzImage' => &$bzImage,
                    'imagefile' => &$imagefile,
                    'initrd' => &$initrd,
                    'loglevel' => &$loglevel,
                    'ramsize' => &$ramsize,
                    'keymap' => &$keymap,
                    'timeout' => &$timeout,
                    'keySequence' => &$keySequence,
                ]
            );
        }
        /**
         * 'initrd' and 'imagefile' are both passed to the hook by
         * reference, and this used to reassign $initrd = $imagefile
         * unconditionally -- so a plugin that set 'initrd' had its value
         * discarded on the very next line, while one that set 'imagefile'
         * was honoured. Nothing said which of the two to write to, and
         * the one named after the thing being chosen was the dead one.
         *
         * Follow 'imagefile' only when the hook left 'initrd' alone, so
         * the working argument keeps working and the documented one
         * starts to. With no plugin listening both are equal here and
         * this is a no-op, which is what the golden file pins.
         */
        if ($initrd === $hookInitrd) {
            $initrd = $imagefile;
        }
        $this->_timeout = $timeout;
        $this->_hiddenmenu = ($hiddenmenu && empty($_REQUEST['menuAccess']));
        $this->_bootexittype = self::$_exitTypes[$exit];
        $this->_loglevel = "loglevel=$loglevel";
        $this->_KS = self::getClass('KeySequence', $keySequence);
        $this->_booturl = self::$httpproto
            . "://{$webserver}/fog/service";
        $this->_memdisk = "kernel $memdisk initrd=$memtest";
        $this->_memtest = "initrd $memtest";
        $StorageNodes = Route::getList(
            'storagenode',
            ['ip' => [$webserver, self::resolveHostname($webserver)]]
        );
        if (count($StorageNodes) < 1) {
            $StorageNodes = Route::getList('storagenode');
            foreach ($StorageNodes as &$StorageNode) {
                $hostname = self::resolveHostname($StorageNode->ip);
                if ($hostname == $webserver
                    || $hostname == self::resolveHostname($webserver)
                ) {
                    $StorageNode = self::getClass('StorageNode', $StorageNode->id);
                    break;
                }
                $StorageNode = new StorageNode(0);
            }
            if (!$StorageNode->isValid()) {
                $storageNodeIDs = Route::getIds(
                    'storagenode',
                    ['isMaster' => 1]
                );
                if (count($storageNodeIDs ?: []) < 1) {
                    $storageNodeIDs = Route::getIds('storagenode', false);
                }
                $StorageNode = new StorageNode(self::minId($storageNodeIDs));
            }
        } else {
            $first = array_shift($StorageNodes);
            $StorageNode = new StorageNode($first->id);
        }
        if ($StorageNode->isValid()) {
            $Send['storage-ip'] = [
                sprintf(
                    'set storage-ip %s',
                    trim($StorageNode->get('ip'))
                )
            ];
            $this->_storage = sprintf(
                'storage=%s:/%s/ storageip=%s',
                trim($StorageNode->get('ip')),
                trim($StorageNode->get('path'), '/'),
                trim($StorageNode->get('ip'))
            );
        }
        /**
         * Outside the guard above, which it used to sit inside. This is the
         * only _parseMe() in the constructor and it flushes the whole header
         * -- '#!ipxe' first of all, then fog-ip, fog-webroot, boot-url,
         * setmacto and the host/inventory variables. An install with no
         * enabled storage node therefore emitted a script with no shebang,
         * which iPXE rejects outright: not a menu missing its imaging
         * options, but a machine that does not boot, and no clue on screen
         * as to why. Only storage-ip is genuinely conditional, so only
         * storage-ip is built under the condition.
         */
        $this->_parseMe($Send);
        $this->_kernel = sprintf(
            'kernel %s %s initrd=%s root=/dev/ram0 rw '
            . 'ramdisk_size=%s%sweb=%s consoleblank=0%s rootfstype=ext4%s%s '
            . '%s nvme_core.default_ps_max_latency_us=0 '
            . 'setmacto=${setmacto}',
            $bzImage,
            $this->_loglevel,
            basename($initrd),
            $ramsize,
            strlen($keymap) ? sprintf(' keymap=%s ', $keymap) : ' ',
            $this->_web,
            $kernelDebug ? ' debug' : ' ',
            $kernelArgs ? sprintf(' %s', $kernelArgs) : '',
            (
                self::$Host->isValid() && self::$Host->get('kernelArgs') ?
                sprintf(' %s', self::$Host->get('kernelArgs')) :
                ''
            ),
            $this->_storage
        );
        $this->_initrd = "imgfetch $initrd";
        self::$HookManager
            ->processEvent('BOOT_MENU_ITEM');
        $PXEMenuID = self::maxId(Route::getIds('pxemenuoptions', ['default' => 1]));
        $defaultMenu = new PXEMenuOptions($PXEMenuID);
        $menuname = (
            $defaultMenu->isValid() ?
            trim($defaultMenu->get('name')) :
            'fog.local'
        );
        unset($defaultMenu);
        self::_getDefaultMenu(
            $this->_timeout,
            $menuname,
            $this->_defaultChoice
        );
        $this->_ipxeLog();
        if (self::$Host->isValid() && self::$Host->get('task')->isValid()) {
            $this->getTasking();
            exit();
        }
        self::$HookManager->processEvent(
            'ALTERNATE_BOOT_CHECKS'
        );
        if (isset($_REQUEST['username'])) {
            $this->verifyCreds();
        } elseif (isset($_REQUEST['key'])) {
            $this->keyset();
        } elseif (!self::$Host->isValid()) {
            $this->printDefault();
        } else {
            $this->getTasking();
        }
    }
    /**
     * Makes a value safe to interpolate into an iPXE `echo` line.
     *
     * boot.php is unauthenticated by necessity -- a booting NIC has no
     * credential to present -- so $_REQUEST['arch'] is attacker-controlled,
     * and the notices below are the first thing in this class ever to echo
     * it back. iPXE scripts are newline-delimited commands, and
     * Initiator::sanitizeOutput() collapses a RUN of whitespace to its
     * first character rather than removing newlines, so a lone "\n" in a
     * request value survives into the emitted script as a command
     * separator. Anything past it would be executed by iPXE -- a chain to
     * an attacker's URL, for one.
     *
     * Whitelist rather than escape: these values are architecture names and
     * kernel/init filenames, so the safe set is small and known, and there
     * is no iPXE escaping mechanism to lean on (its tokenizer strips quotes
     * outright, which is why nothing here is quoted). Length-capped as well,
     * so a long value cannot push the rest of the menu off screen.
     *
     * @param string $value the value to render
     *
     * @return string
     */
    private static function _echoSafe($value)
    {
        $value = preg_replace(
            '/[^A-Za-z0-9._-]/',
            '',
            (string)$value
        );

        return substr((string)$value, 0, 64);
    }
    /**
     * Whether a kernel-argument string asks for debug mode.
     *
     * @param string $args the argument string to test
     *
     * @return bool
     */
    private static function _wantsDebug($args)
    {
        return (bool)preg_match(
            '#isdebug=yes|mode=debug|mode=onlydebug#i',
            (string)$args
        );
    }
    /**
     * The lines that establish ${arch} at the top of an emitted script.
     *
     * ${buildarch} is the architecture iPXE itself was built for. The cpuid
     * test promotes a 32-bit iPXE running on a 64-bit CPU to x86_64, which
     * is what lets a machine that was handed the i386 binary still boot the
     * 64-bit FOS kernel. Identical to what default.ipxe does before the
     * very first chain, and repeated here because each script this class
     * emits is evaluated fresh.
     *
     * @return array
     */
    private static function _archDetect()
    {
        return [
            'set arch ${buildarch}',
            'iseq ${arch} i386 && cpuid --ext 29 && set arch x86_64 ||',
        ];
    }
    /**
     * The POST body carried by every chain back into boot.php.
     *
     * This was open-coded at seven call sites, each with its own slightly
     * different ordering and its own subset of the common params, which is
     * how two of them came to omit ${platform} -- the value printDefault()
     * gates the Secure Boot menu entries on, so the follow-up request could
     * not tell UEFI from BIOS. Order is not significant to iPXE (these all
     * become POST fields), so one canonical order serves every caller.
     *
     * The mac1..mac7 lines are conditional because iPXE errors on an unset
     * variable; jumping to :bootme on the first absent NIC is how the
     * original avoided that, and it is preserved exactly. The enumeration
     * used to stop at net2, which made a host registered under only its
     * fourth NIC invisible to the lookup in boot.php.
     *
     * macboot is ${netX/mac}, iPXE's alias for the device it booted from,
     * and is an ADDITION to mac0 rather than a replacement -- netX is a
     * pointer at one of net0..netN, so substituting it would drop net0 from
     * the set on a machine that booted off net1. boot.php unions every mac*
     * field and array_unique()s the result, so the overlap is free. It is
     * emitted above the net1..net7 chain because that chain short-circuits
     * to :bootme on the first absent interface, which on a single-NIC
     * machine is net1.
     *
     * product/manufacturer/ipxever/filename ride along because _ipxeLog()
     * keys its lookup on file+product+manufacturer+mac. default.ipxe posts
     * them; this block did not, so every re-chain failed to match the row
     * the first request had written and inserted a fresh blank one instead.
     *
     * @param array $params call-specific 'param ...' lines
     * @param bool  $tail   emit the :bootme label and the chain itself.
     *                      False where the caller shares one :bootme across
     *                      several blocks.
     *
     * @return array
     */
    private function _chainParams(array $params = [], $tail = true)
    {
        $block = array_merge(
            [
                'params',
                'param mac0 ${net0/mac}',
                'param arch ${arch}',
                'param platform ${platform}',
                'param product ${product}',
                'param manufacturer ${manufacturer}',
                'param ipxever ${version}',
                'param filename ${filename}',
            ],
            $params,
            [
                'param sysuuid ${uuid}',
                'isset ${netX/mac} && param macboot ${netX/mac} ||',
                'isset ${net1/mac} && param mac1 ${net1/mac} || goto bootme',
                'isset ${net2/mac} && param mac2 ${net2/mac} || goto bootme',
                'isset ${net3/mac} && param mac3 ${net3/mac} || goto bootme',
                'isset ${net4/mac} && param mac4 ${net4/mac} || goto bootme',
                'isset ${net5/mac} && param mac5 ${net5/mac} || goto bootme',
                'isset ${net6/mac} && param mac6 ${net6/mac} || goto bootme',
                'isset ${net7/mac} && param mac7 ${net7/mac} || goto bootme',
            ]
        );
        if (!$tail) {
            return $block;
        }

        return array_merge(
            $block,
            [
                'goto bootme',
                ':bootme',
                "chain -ar $this->_booturl/ipxe/boot.php##params",
            ]
        );
    }
    /**
     * Sets the default menu item
     *
     * @param int    $timeout the timeout interval
     * @param string $name    the name to default to
     * @param mixed  $default the default item to set
     *
     * @return void
     */
    private static function _getDefaultMenu($timeout, $name, &$default)
    {
        $default = sprintf(
            'choose --default %s --timeout %s target && goto ${target}',
            $name,
            $timeout
        );
    }
    /**
     * Log's the current ipxe request
     *
     * @return void
     */
    private function _ipxeLog()
    {
        $filename = isset($_REQUEST['filename']) ? trim(basename($_REQUEST['filename'])) : '';
        $product = isset($_REQUEST['product']) ? trim($_REQUEST['product']) : '';
        $manufacturer = isset($_REQUEST['manufacturer']) ? trim($_REQUEST['manufacturer']) : '';
        $findWhere = [
            'file' => sprintf('%s', $filename ? $filename : ''),
            'product' => sprintf('%s', $product ? $product : ''),
            'manufacturer' => sprintf('%s', $manufacturer ? $manufacturer : ''),
            'mac' => (
                self::$Host->isValid() ?
                self::$Host->get('mac')->__toString() :
                ''
            ),
        ];
        $id = Route::getIds(
            'ipxe',
            $findWhere
        );
        $id = count($id ?: []) ? max($id) : 0;
        self::getClass('Ipxe', $id)
            ->set('product', $findWhere['product'])
            ->set('manufacturer', $findWhere['manufacturer'])
            ->set('mac', $findWhere['mac'])
            ->set('success', 1)
            ->set('failure', 0)
            ->set('file', $findWhere['file'])
            ->set('version', isset($_REQUEST['ipxever']) ? trim($_REQUEST['ipxever']) : '')
            ->save();
    }
    /**
     * The boot chaining function
     *
     * @param bool $debug        to show debu gor not
     * @param bool $shortCircuit to force display
     *
     * @return void
     */
    private function _chainBoot($debug = false, $shortCircuit = false)
    {
        if (!$this->_hiddenmenu || $shortCircuit) {
            $Send['chainnohide'] = array_merge(
                self::_archDetect(),
                // menuAccess, capital A. The hidden-menu branch below posts
                // 'menuaccess' instead, and the two are read at different
                // points -- the constructor tests menuAccess when deciding
                // the prompt timeout, verifyCreds() tests menuaccess after
                // a login. PHP request keys are case-sensitive, so these
                // are two distinct flags and neither may be folded into
                // the other.
                $this->_chainParams(
                    [
                        'param menuAccess 1',
                        "param debug $debug",
                    ]
                )
            );
        } else {
            $KSKey = (
                $this->_KS->isValid() ?
                trim($this->_KS->get('ascii')) :
                '0x1b'
            );
            $KSName = (
                $this->_KS->isValid() ?
                trim($this->_KS->get('name')) :
                'Escape'
            );
            $Send['chainhide'] = array_merge(
                self::_archDetect(),
                [
                    "iseq \${platform} efi && set key 0x1b || set key $KSKey",
                    "iseq \${platform} efi && set keyName ESC || "
                    . "set keyName $KSName",
                    "prompt --key \${key} --timeout $this->_timeout "
                    . "Booting... (Press \${keyName} to access the menu) && "
                    . "goto menuAccess || $this->_bootexittype",
                    ':menuAccess',
                    'login',
                ],
                // menuaccess, lower case -- see the note in the branch above.
                $this->_chainParams(
                    [
                        'param username ${username}',
                        'param password ${password}',
                        'param menuaccess 1',
                        "param debug $debug",
                    ]
                )
            );
        }
        $this->_parseMe($Send);
    }
    /**
     * Print if this host is image ignored
     *
     * @return void
     */
    protected function _printImageIgnored()
    {
        $this->_say(
            'ignored',
            ['echo The MAC Address is set to be ignored for imaging tasks'],
            15,
            false
        );
        $this->printDefault();
    }
    /**
     * Prints a memtest tasking for the host
     *
     * No '|| goto MENU' tail: a tasked boot has no menu to return to. On an
     * architecture without memdisk this says why and stops, rather than
     * dropping the machine to a bare iPXE prompt with no explanation.
     *
     * @return void
     */
    protected function _printMemtestTasking()
    {
        $Send = [];
        $Send['memtest'] = $this->_memtestChoice('');
        $this->_parseMe($Send);
    }
    /**
     * Prints the current tasking for the host
     *
     * @param array $kernelArgsArray the kernel args data
     *
     * @return void
     */
    protected function _printTasking($kernelArgsArray)
    {
        $kernelArgs = self::flattenKernelArgs($kernelArgsArray);
        $Send['task'][(
            self::$Host->isValid() ?
            self::$Host->get('task')->get('typeID') :
            1
        )] = [
            "$this->_kernel $kernelArgs",
            $this->_initrd,
            'boot',
        ];
        $this->_parseMe($Send);
    }
    /**
     * Allows user to specify a product key at the ipxe menu
     *
     * @return void
     */
    public function keyreg()
    {
        $Send['keyreg'] = array_merge(
            self::_archDetect(),
            [
                'echo -n Please enter the product key : ',
                'read key',
            ],
            $this->_chainParams(['param key ${key}'])
        );
        $this->_parseMe($Send);
    }
    /**
     * Checks that a session is valid and integrates the host to that
     * tasking.
     *
     * @return void
     */
    public function sesscheck()
    {
        // The operator has already been told the name does not exist and
        // answered the size and wait prompts, so this is a create, not a
        // lookup.
        if (isset($_REQUEST['sessclients'])) {
            $this->sesscreate();
            return;
        }
        // cancel() and complete() blank msName, so an empty name is not a
        // harmless miss -- it can match a real row, and it would create a
        // session nobody could ever name to join.
        if ('' === trim($_REQUEST['sessname'])) {
            $this->_sessRename();
            return;
        }
        $findWhere = [
            'name' => trim($_REQUEST['sessname']),
            'stateID' => self::fastmerge(
                self::getQueuedStates(),
                (array)self::getProgressState()
            ),
        ];
        $Sessions = Route::getList(
            'multicastsession',
            $findWhere
        );
        $MulticastSessionID = 0;
        foreach ($Sessions as &$MulticastSession) {
            if ($MulticastSession->sessclients < 1) {
                $MulticastSessionID = 0;
                unset($MulticastSession);
                continue;
            }
            $MulticastSessionID = $MulticastSession->id;
            unset($MulticastSession);
            break;
        }
        $MulticastSession = new MulticastSession($MulticastSessionID);
        if (!$MulticastSession->isValid()) {
            // No such session. Rather than dumping the operator back out,
            // offer to create it -- but only with a size and a wait, since
            // a session with neither can never be joined by anyone else.
            $this->_sessChain(
                [
                    'echo No session found with that name.',
                    'echo -n Clients expected to join, including this one '
                    . '(0 to re-enter the name) > ',
                    'read sessclients',
                    'echo -n Minutes to wait for them before starting > ',
                    'read sessminutes',
                ],
                [
                    'param sessname ${sessname}',
                    'param sessclients ${sessclients}',
                    'param sessminutes ${sessminutes}',
                ]
            );
            return;
        }
        if (!$MulticastSession->isJoinable()) {
            $this->_sessRename(
                [
                    'echo That session has already started and can no '
                    . 'longer be joined.',
                    'sleep 3',
                ]
            );
            return;
        }
        $this->multijoin($MulticastSession->get('id'));
    }
    /**
     * Builds and sends an iPXE chain back into the session join flow.
     *
     * @param array $pre         lines emitted before the params block
     * @param array $extraParams additional param lines to carry back
     *
     * @return void
     */
    private function _sessChain($pre, $extraParams = [])
    {
        $Send['checksession'] = array_merge(
            $pre,
            self::_archDetect(),
            $this->_chainParams(
                array_merge(
                    [
                        'param sessionJoin 1',
                        // iPXE keeps these set after the initial login, so
                        // every hop back into this flow stays
                        // authenticated. Without them the follow-up request
                        // arrived anonymous and the session lookup ran with
                        // no idea who was asking.
                        'param username ${username}',
                        'param password ${password}',
                    ],
                    $extraParams
                )
            )
        );
        $this->_parseMe($Send);
    }
    /**
     * Sends the operator back to the session name prompt.
     *
     * @param array $pre lines emitted before the prompt
     *
     * @return void
     */
    private function _sessRename($pre = [])
    {
        $this->_sessChain(
            array_merge(
                $pre,
                [
                    'echo -n Please enter the session name to join > ',
                    'read sessname',
                ]
            ),
            ['param sessname ${sessname}']
        );
    }
    /**
     * Creates a named multicast session from a booting machine.
     *
     * Reached when the operator answered the size and wait prompts after
     * naming a session that does not exist yet. Both answers are required:
     * sessclients is what lets anyone else join the session by name and
     * what udp-sender holds for, and the wait is the window in which they
     * can do it.
     *
     * @return void
     */
    /**
     * Tells the operator a seeded task type is missing, on screen.
     *
     * The three createImagePackage() callers below fetch a task type by its
     * seeded id. getItem() answers null when the row is gone, where indiv()
     * used to reach breakHead()'s exit -- which on a boot request truncates
     * the iPXE script mid-stream, so the machine drops to a bare prompt with
     * nothing said about why. See ADR 0011.
     *
     * @param int $id The task type id that could not be loaded.
     *
     * @return void
     */
    private function _noTaskType($id)
    {
        $this->_say(
            'fail',
            [
                '#!ipxe',
                sprintf(
                    'echo Task type %d is missing from this server.',
                    $id
                ),
            ],
            5,
            false
        );
    }
    public function sesscreate()
    {
        $sessname = trim($_REQUEST['sessname']);
        $expected = (int)$_REQUEST['sessclients'];
        $minutes = isset($_REQUEST['sessminutes'])
            ? (int)$_REQUEST['sessminutes']
            : 0;
        if ('' === $sessname || $expected < 1 || $minutes < 1) {
            $this->_sessRename();
            return;
        }
        if (!self::$Host->isValid() || self::$Host->get('pending')) {
            $this->_sessRename(
                [
                    'echo Only a registered host can create a session.',
                    'sleep 3',
                ]
            );
            return;
        }
        $shutdown = self::_wantsShutdown(self::_extraArgs());
        $isdebug = self::_wantsDebug(self::_extraArgs());
        $tasktype = Route::getItem('tasktype', TaskType::MULTICAST);
        if (!$tasktype) {
            $this->_noTaskType(TaskType::MULTICAST);
            return;
        }
        self::$Host->createImagePackage(
            $tasktype,
            $sessname,
            $shutdown,
            $isdebug,
            -1,
            false,
            isset($_REQUEST['username']) ? $_REQUEST['username'] : '',
            '',
            true,
            true,
            false,
            false,
            $expected,
            $minutes * 60
        );
        $this->_chainBoot(false, true);
    }
    /**
     * Asks user what the name of the session is they want to join
     *
     * @return void
     */
    public function sessjoin()
    {
        // Same prompt and same chain as every other return into this flow,
        // which is what carries the credentials forward. The hand-rolled
        // block this replaced forwarded neither them nor sessionJoin, so
        // the name the operator typed came back to an anonymous request.
        $this->_sessRename();
    }
    /**
     * False taskings are taskings for hosts that may not be
     * registered to the FOG Server.  This function allows actions
     * still occur
     *
     * @param mixed $mc    If the task is a multicast or not
     * @param mixed $Image The image to use for this false tasking
     *
     * @return void
     */
    public function falseTasking($mc = false, $Image = false)
    {
        $this->_kernel = str_replace(
            $this->_storage,
            '',
            $this->_kernel
        );
        $TaskType = new TaskType(1);
        $shutdown = false;
        if ($mc) {
            $Image = $mc->getImage();
            $TaskType = new TaskType(8);
            $shutdown = (bool)$mc->get('shutdown');
        }
        $serviceNames = [
            'FOG_DISABLE_CHKDSK',
            'FOG_KERNEL_ARGS',
            'FOG_KERNEL_DEBUG',
            'FOG_MULTICAST_RENDEZVOUS',
            'FOG_NONREG_DEVICE'
        ];
        list(
            $chkdsk,
            $kargs,
            $kdebug,
            $mcastrdv,
            $nondev
        ) = self::getSetting($serviceNames);
        $shutdown = $shutdown
            || self::_wantsShutdown($TaskType->get('kernelArgs'))
            || self::_wantsShutdown(self::_extraArgs());
        $StorageGroup = $Image->getStorageGroup();
        $StorageNode = $StorageGroup->getOptimalStorageNode();
        $osid = $Image->get('osID');
        $storage = escapeshellcmd(
            sprintf(
                '%s:/%s/%s',
                trim($StorageNode->get('ip')),
                trim($StorageNode->get('path'), '/'),
                ''
            )
        );
        $storageip = $StorageNode->get('ip');
        $img = escapeshellcmd($Image->get('path'));
        $imgFormat = (int)$Image->get('format');
        $imgType = $Image->getImageType()->get('type');
        $imgPartitionType = $Image->getPartitionType();
        $imgid = $Image->get('id');
        $chkdsk = $chkdsk == 1 ? 0 : 1;
        $ftp = $StorageNode->get('ip');
        $port = ($mc ? $mc->get('port') : null);
        // $mac was never assigned in this method, so every false tasking
        // booted FOS with an empty mac= kernel argument. Resolved the same
        // way getTasking() does it: a false tasking is by definition a host
        // that may not be registered, and getHostItem() leaves an invalid
        // Host(0) rather than null, so isValid() is the test.
        $mac = self::$Host->isValid()
            ? self::$Host->get('mac')
            : (string) (
                filter_input(INPUT_GET, 'mac')
                ?: filter_input(INPUT_POST, 'mac')
                ?: ''
            );
        $kernelArgsArray = [
            "mac=$mac",
            "ftp=$ftp",
            "storage=$storage",
            "storageip=$storageip",
            "osid=$osid",
            "irqpoll",
            "chkdsk=$chkdsk",
            "img=$img",
            "imgType=$imgType",
            "imgPartitionType=$imgPartitionType",
            "imgid=$imgid",
            "imgFormat=$imgFormat",
            [
                'value' => 'shutdown=1',
                'active' => $shutdown
            ],
            [
                'value' => "mcastrdv=$mcastrdv",
                'active' => !empty($mcastrdv)
            ],
            [
                'value' => "capone=1",
                'active' => !self::$Host || !self::$Host->isValid(),
            ],
            [
                'value' => "port=$port mc=yes",
                'active' => $mc,
            ],
            [
                'value' => 'debug',
                'active' => $kdebug,
            ],
            [
                'value' => 'fdrive='.$nondev,
                'active' => $nondev,
            ],
            $TaskType->get('kernelArgs'),
            $kargs
        ];
        $this->_printTasking($kernelArgsArray);
    }
    /**
     * Prints the image list for the ipxe menu
     *
     * @return void
     */
    public function printImageList()
    {
        $Send['ImageListing'] = [
            'goto MENU',
            ':MENU',
            'menu',
        ];
        $defItem = 'choose target && goto ${target}';
        /**
         * Sort a list.
         */
        $imgFind = ['isEnabled' => 1];
        if (!self::getSetting('FOG_IMAGE_LIST_MENU')) {
            if (!self::$Host->isValid()
                || !self::$Host->getImage()->isValid()
            ) {
                $imgFind = false;
            } else {
                $imgFind['id'] = self::$Host->getImage()->get('id');
            }
        }
        if ($imgFind === false) {
            $Images = false;
        } else {
            $Images = Route::getList(
                'image',
                $imgFind
            );
        }
        if (!$Images) {
            $this->_say(
                'NoImages',
                [
                    'echo Host is not valid, host has no image assigned, or'
                    . ' there are no images defined on the server.',
                ]
            );
        } else {
            array_map(
                function ($Image) use (&$Send, &$defItem) {
                    $Send['ImageListing'][] = sprintf(
                        'item %s %s (%s)',
                        $Image->path,
                        $Image->name,
                        $Image->id
                    );
                    if (!self::$Host->isValid()) {
                        return;
                    }
                    if (!self::$Host->getImage()->isValid()) {
                        return;
                    }
                    if (self::$Host->getImage()->get('id') === $Image->id) {
                        $defItem = sprintf(
                            'choose --default %s --timeout %d target && '
                            . 'goto ${target}',
                            $Image->path,
                            $this->_timeout
                        );
                    }
                },
                (array)$Images
            );
            $Send['ImageListing'][] = 'item return Return to menu';
            $Send['ImageListing'][] = $defItem;
            array_map(
                function ($Image) use (&$Send) {
                    $Send[sprintf(
                        'pathofimage%s',
                        $Image->name
                    )] = array_merge(
                        [
                            sprintf(
                                ':%s',
                                $Image->path
                            ),
                            sprintf(
                                'set imageID %d',
                                $Image->id
                            ),
                        ],
                        // No tail: every image block falls through to the
                        // single :bootme emitted once below.
                        $this->_chainParams(
                            [
                                'param imageID ${imageID}',
                                'param qihost 1',
                                'param username ${username}',
                                'param password ${password}',
                            ],
                            false
                        )
                    );
                },
                (array)$Images
            );
            $Send['returnmenu'] = array_merge(
                [':return'],
                $this->_chainParams([], false)
            );
            $Send['bootmefunc'] = [
                ':bootme',
                "chain -ar $this->_booturl/ipxe/boot.php##params",
                'goto MENU',
            ];
            $this->_parseMe($Send);
        }
    }
    /**
     * Joins the host with a session
     *
     * @param int $msid the session to join
     *
     * @return void
     */
    public function multijoin($msid)
    {
        $MultiSess = new MulticastSession($msid);
        if (!$MultiSess->isValid()) {
            return;
        }
        $msImage = $MultiSess->getImage()->get('id');
        if (self::$Host->isValid() && !self::$Host->get('pending')) {
            $h_Image = 0;
            $Image = self::$Host->getImage();
            if ($Image instanceof Image) {
                $h_Image = self::$Host->getImage()->get('id');
            }
            if ($msImage != $h_Image) {
                self::$Host
                    ->set('imagename', $MultiSess->getImage())
                    ->set('imageID', $msImage);
            }
        }
        $shutdown = self::_wantsShutdown(self::_extraArgs());
        $isdebug = self::_wantsDebug(self::_extraArgs());
        if (self::$Host->isValid() && !self::$Host->get('pending')) {
            $tasktype = Route::getItem('tasktype', TaskType::MULTICAST);
            if (!$tasktype) {
                $this->_noTaskType(TaskType::MULTICAST);
                return;
            }
            self::$Host->createImagePackage(
                $tasktype,
                $MultiSess->get('name'),
                $shutdown,
                $isdebug,
                -1,
                false,
                $_REQUEST['username'],
                '',
                true,
                true
            );
            $this->_chainBoot(false, true);
        } else {
            $this->falseTasking($MultiSess);
        }
    }
    /**
     * Set's the product key
     *
     * @return void
     */
    public function keyset()
    {
        if (!self::$Host->isValid()) {
            return;
        }
        // Aisle 029: boot.php is unauthenticated and $_REQUEST is neither escaped
        // nor stripAndDecode'd on this path, so this was a raw write of arbitrary
        // attacker bytes into hostProductKey (which the Host Export table then
        // rendered unescaped). Use the same contract the host/group save paths
        // already enforce rather than inventing a second rule. An explicitly
        // empty key is still accepted so clearing a key from the iPXE prompt
        // keeps working; only non-empty malformed input is refused, and it
        // reuses the existing keychangefail branch so the iPXE chain still
        // gets a message.
        $key = (string)($_REQUEST['key'] ?? '');
        if ($key !== '' && !self::productKeyIsValid($key)) {
            $this->_say('keychangefail', ['echo Failed to change key']);
            return;
        }
        $update = self::$Host->getManager()->update(
            ['id' => self::$Host->get('id')],
            '',
            ['productKey' => self::productKeyFormat($key)]
        );
        if (!$update) {
            $this->_say('keychangefail', ['echo Failed to change key']);
            return;
        }
        $this->_say('keychangesuccess', ['echo Successfully changed key']);
    }
    /**
     * Says something on screen and chains back into the boot menu.
     *
     * The "echo a line or two, sleep, _parseMe(), _chainBoot()" shape was
     * open-coded at every point that has to refuse something -- a bad
     * login, a key that would not save, an account without task rights,
     * a server with no images. One spelling of it means the sleep cannot
     * drift between them and a caller cannot forget the _chainBoot() that
     * puts the operator back somewhere useful.
     *
     * @param string $key   the $Send key, kept distinct per caller so a
     *                      plugin listening on IPXE_EDIT can still tell
     *                      which message it is looking at
     * @param array  $lines the message, without the trailing sleep
     * @param int    $sleep seconds to leave it on screen
     * @param bool   $chain chain back into the menu afterwards
     *
     * @return void
     */
    private function _say($key, array $lines, $sleep = 3, $chain = true)
    {
        $Send = [$key => array_merge($lines, ["sleep $sleep"])];
        $this->_parseMe($Send);
        if ($chain) {
            $this->_chainBoot();
        }
    }
    /**
     * Parses the information for us
     *
     * @param array $Send the data to parse
     *
     * @return void
     */
    private function _parseMe($Send)
    {
        /**
         * Anything _arch() or _hostOverride() decided the operator needs to
         * know, emitted once, on whichever path actually runs. Appended
         * rather than prepended because the very first batch through here
         * opens with '#!ipxe', which has to stay the first line of the
         * script; and drained so a later batch does not repeat them.
         */
        if (count($this->_notices ?: []) > 0) {
            $Send['archnotices'] = $this->_notices;
            $this->_notices = [];
        }
        self::$HookManager->processEvent(
            'IPXE_EDIT',
            [
                'ipxe' => &$Send,
                'Host' => &self::$Host,
                'kernel' => &$this->_kernel,
                'initrd' => &$this->_initrd,
                'booturl' => &$this->_booturl,
                'memdisk' => &$this->_memdisk,
                'memtest' => &$this->_memtest,
                'web' => &$this->_web,
                'defaultChoice' => &$this->_defaultChoice,
                'bootexittype' => &$this->_bootexittype,
                'storage' => &$this->_storage,
                'shutdown' => &$this->_shutdown,
                'path' => &$this->_path,
                'timeout' => &$this->_timeout,
                'KS' => $this->_KS
            ]
        );
        if (count($Send ?: []) > 0) {
            array_walk_recursive(
                $Send,
                function ($val, $key) {
                    printf('%s%s', implode("\n", (array)$val), "\n");
                }
            );
        }
    }
    /**
     * For advancemenu if we require login
     *
     * @return void
     */
    public function advLogin()
    {
        $Send['advancedlogin'] = [
            "chain -ar $this->_booturl/ipxe/advanced.php"
        ];
        $this->_parseMe($Send);
    }
    /**
     * Sets menus up with isdebug options
     *
     * @return void
     */
    private function _debugAccess()
    {
        $Send['debugaccess'] = [
            "$this->_kernel isdebug=yes",
            "$this->_initrd",
            "boot",
        ];
        $this->_parseMe($Send);
    }
    /**
     * Verifies credentials for us
     *
     * @return void
     */
    public function verifyCreds()
    {
        list(
            $advLogin,
            $noMenu
        ) = self::getSetting(['FOG_ADVANCED_MENU_LOGIN', 'FOG_NO_MENU']);
        if ($noMenu) {
            $this->noMenu();
        }
        // authenticateOnly: iPXE holds no cookie, so a session established
        // here could never be presented back -- it would just be an
        // authenticated session nobody owns. The isValid() test below is
        // the whole point of the call.
        $tmpUser = self::authenticateOnly(
            $_REQUEST['username'] ?? '',
            $_REQUEST['password'] ?? ''
        );
        if ($tmpUser->isValid()) {
            self::$HookManager
                ->processEvent('ALTERNATE_LOGIN_BOOT_MENU_PARAMS');
            if ($advLogin && isset($_REQUEST['advLog']) && $_REQUEST['advLog']) {
                $this->advLogin();
            }
            if (isset($_REQUEST['keyreg']) && $_REQUEST['keyreg']) {
                $this->keyreg();
            } elseif (isset($_REQUEST['qihost']) && $_REQUEST['qihost']) {
                $this->setTasking($_REQUEST['imageID'] ?? '');
            } elseif (isset($_REQUEST['sessionJoin']) && $_REQUEST['sessionJoin']) {
                // Joining or creating a session builds real tasking against
                // a host, so holding a valid password is not enough -- the
                // account has to actually be allowed to task. Unrestricted
                // accounts short-circuit inside can() and are unaffected.
                if (!$tmpUser->can('task.task')) {
                    $this->_say(
                        'nosessperm',
                        ['echo That account may not join multicast sessions.']
                    );
                    return;
                }
                // The name can arrive with the credentials, in which case
                // there is nothing left to prompt for.
                if (isset($_REQUEST['sessname'])) {
                    $this->sesscheck();
                } else {
                    $this->sessjoin();
                }
            } elseif (isset($_REQUEST['menuaccess']) && $_REQUEST['menuaccess']) {
                //unset($this->_hiddenmenu);
                $this->_hiddenmenu = false;
                $this->_chainBoot(true);
            } elseif (isset($_REQUEST['debugAccess']) && $_REQUEST['debugAccess']) {
                $this->_debugAccess();
            } else {
                $this->printDefault();
            }
        } else {
            $this->_say(
                'invalidlogin',
                [
                    'echo Invalid login!',
                    'clear username',
                    'clear password',
                ]
            );
        }
    }
    /**
     * Sets a tasking element as needed
     *
     * @param mixed $imgID The image id to associate
     *
     * @return void
     */
    public function setTasking($imgID = '')
    {
        $shutdown = self::_wantsShutdown(self::_extraArgs());
        $isdebug = self::_wantsDebug(self::_extraArgs());
        if (!$imgID) {
            $this->printImageList();
            return;
        }
        if (!self::$Host->isValid()) {
            $this->falseTasking('', self::getClass('Image', $imgID));
            return;
        }
        if (self::$Host->getImage()->get('id') != $imgID) {
            self::$Host
                ->set('imageID', $imgID)
                ->set('imagename', new Image($imgID));
        }
        if (!self::$Host->getImage()->isValid()) {
            return;
        }
        try {
            $tasktype = Route::getItem('tasktype', TaskType::DEPLOY);
            if (!$tasktype) {
                // Thrown rather than handed to _noTaskType(): the catch below
                // already renders a message from the exception, and this is
                // the one of the three sites that has one.
                throw new \Exception(
                    sprintf(
                        _('Task type %d is missing from this server.'),
                        TaskType::DEPLOY
                    )
                );
            }
            self::$Host->createImagePackage(
                $tasktype,
                'AutoRegTask',
                $shutdown,
                $isdebug,
                -1,
                false,
                $_REQUEST['username']
            );
            $this->_chainBoot(false, true);
        } catch (\Exception $e) {
            $this->_say(
                'fail',
                [
                    '#!ipxe',
                    sprintf('echo %s', $e->getMessage()),
                ],
                3,
                false
            );
        }
    }
    /**
     * No menu definition
     *
     * @return void
     */
    public function noMenu()
    {
        $Send['nomenu'] = [
            "$this->_bootexittype"
        ];
        $this->_parseMe($Send);
        exit();
    }
    /**
     * Generates a menu item listing
     *
     * @param object $option the menu item to work with
     * @param string $desc   the description
     *
     * @return array
     */
    private function _menuItem($option, $desc)
    {
        $name = preg_replace('#[\s]+#', '_', $option->name);
        $hotkey = ' ';
        if ($option->hotkey) {
            if ($option->keysequence) {
                $hotkey = sprintf(
                    ' --key %s ',
                    $option->keysequence
                );
            }
        }
        return ["item{$hotkey}{$name} {$desc}"];
    }
    /**
     * The options of the menu
     *
     * @param object $option the menu item to work with
     * @param mixed  $type   the type of the menu
     *
     * @return array
     */
    private function _menuOpt($option, $type)
    {
        $name = preg_replace('#[\s]+#', '_', $option->name);
        $name = trim(":$name");
        $type = trim($type);
        $Send = [$name];
        $params = array_filter(
            array_map(
                'trim',
                explode(
                    "\n",
                    $option->params
                )
            )
        );
        if (count($params ?: [])) {
            if ($type) {
                $index = array_search('params', $params);
                if ($index !== false && is_numeric($index)) {
                    self::arrayInsertAfter(
                        $index,
                        $params,
                        'extra',
                        "param extraargs \"$type\""
                    );
                }
            }
            $params = trim(implode("\n", (array)$params));
            $params .= "\n"
                . 'param sysuuid ${uuid}\n'
                . 'goto bootme';
            $Send = self::fastmerge($Send, [$params]);
        }
        // Keyed on pxeName, NOT pxeID.
        //
        // pxeMenu has an auto_increment primary key and is user-writable from
        // iPXE Menu Customization, so "the next free id" is only ever true of
        // a pristine install. Any site that had added a custom menu item held
        // id 14 already, the seeding INSERT was silently ignored (INSERT
        // IGNORE against a taken primary key does nothing), and the item never
        // appeared -- while a fresh install got it every time. Worse in the
        // other direction: keying the behaviour on the id meant that site's
        // OWN custom entry would have started chaining to MokManager.
        //
        // Matching on the name removes the id from the contract entirely, so
        // the row can be seeded with whatever id auto_increment hands out and
        // nothing has to be moved out of the way.
        //
        // The numeric cases below are left alone deliberately: 1, 2 and 11 are
        // seeded by the very first pxeMenu INSERT, which every install on
        // earth ran before any admin could have added a row, so their ids are
        // settled history rather than an assumption.
        if ('fog.enrollsecureboot' === $option->name) {
            return self::fastmerge($Send, $this->_enrollSecureBootChoice());
        }
        switch ($option->id) {
            case 1:
                $Send = self::fastmerge(
                    $Send,
                    ["$this->_bootexittype || goto MENU"]
                );
                break;
            case 2:
                $Send = self::fastmerge($Send, $this->_memtestChoice());
                break;
            case 11:
                $Send = self::fastmerge(
                    $Send,
                    [
                        "chain -ar $this->_booturl/ipxe/advanced.php || "
                        . "goto MENU"
                    ]
                );
                break;
            default:
                if (!$params) {
                    $Send = self::fastmerge(
                        $Send,
                        [
                            "$this->_kernel $this->_loglevel $type",
                            $this->_initrd,
                            'boot || goto MENU'
                        ]
                    );
                }
        }
        return $Send;
    }
    /**
     * The memtest boot lines, or a refusal on an architecture that cannot
     * run memdisk.
     *
     * _filterMenus() normally keeps the menu entry off an ARM screen
     * entirely, so the refusal is only reached by a scheduled memtest task
     * (which is created server-side, where the host's architecture is not
     * knowable) or by a site that pointed a custom entry at this behaviour.
     *
     * @param string $onFail what to append when the boot fails or is refused
     *
     * @return array
     */
    private function _memtestChoice($onFail = ' || goto MENU')
    {
        if (!self::_arch()['memdisk']) {
            return [
                sprintf(
                    'echo Memtest needs memdisk, which is x86-only -- it '
                    . 'cannot run on %s.',
                    self::_arch()['label']
                ),
                'sleep 5' . $onFail,
            ];
        }

        return [
            "$this->_memdisk iso raw",
            $this->_memtest,
            'boot' . $onFail,
        ];
    }
    /**
     * Builds the iPXE choice for pxeID 14, "Enroll Secure Boot Key".
     *
     * Always shown (pxeRegOnly=2), so a technician never has to repoint a
     * client's boot file just to enroll its MOK -- the same signed
     * snponly.efi/shim chain every other client reaches already gets them
     * here. If this server never configured kernel signing there is
     * nothing to enroll, so this returns a message rather than attempting
     * a chain to a target that was never staged.
     *
     * MokManager (mmx64.efi) only shows its "Enroll key from disk" menu
     * when it is the boot target itself -- shim only invokes it when a
     * pending MOK request already exists, which nothing here stages -- so
     * this chains to it directly rather than through the normal shim gate.
     *
     * @return array
     */
    private function _enrollSecureBootChoice()
    {
        if (!file_exists(BASEPATH . 'service/secureboot' . DS . 'MOK.der')) {
            return [
                'echo Secure Boot signing is not configured on this FOG '
                . 'server.',
                'echo Nothing to enroll -- returning to the menu...',
                'sleep 5',
                'goto MENU'
            ];
        }
        // An empty mokManager is an architecture with no signed shim to
        // chain -- 32-bit UEFI today. The per-arch binary name lives in
        // _arch(); this only has to know whether there is one.
        $arch = self::_arch();
        if (!$arch['mokManager']) {
            return [
                sprintf(
                    'echo No signed Secure Boot shim exists for %s UEFI.',
                    $arch['label']
                ),
                'echo Returning to the menu...',
                'sleep 5',
                'goto MENU'
            ];
        }
        $mmTarget = "$this->_booturl/" . $arch['mokManager'];
        // iPXE's EFI filesystem driver exposes every image it has downloaded
        // -- kernel, initrd, or a plain imgfetch like this one -- to any EFI
        // app that enumerates SimpleFileSystemProtocol handles. MokManager's
        // own "Enroll key from disk" browser does exactly that, and a normal
        // netboot already puts bzImage/init.xz in front of it this way, so
        // fetching MOK.der here makes it selectable too, without a USB stick
        // -- confirmed on physical hardware. Keep the USB fallback text below
        // regardless, in case a given machine's MokManager only walks handles
        // backed by a real block device.
        return [
            "imgfetch $this->_booturl/secureboot/MOK.der MOK.der || "
            . "echo Could not fetch MOK.der over the network.",
            // No quotes in the text: iPXE's tokenizer treats them as quoting
            // and strips them, so they would vanish from the output anyway.
            'echo MOK.der has been downloaded into memory as MOK.der --',
            'echo look for it under Enroll key from disk.',
            'echo MokManager gives up after about 10 seconds with no',
            'echo keypress, and reboots if left idle for a few minutes --',
            'echo be ready before it appears.',
            'echo If it is not listed there, have MOK.der on a',
            'echo FAT-formatted USB stick in this machine instead.',
            'sleep 8',
            "chain -ar $mmTarget || "
            . "echo Could not load the Secure Boot enrolment menu. && "
            . "sleep 5 && goto MENU"
        ];
    }
    /**
     * Drops the menu entries this machine could not act on.
     *
     * Three reasons, one pass. They were three near-identical
     * array_filter() blocks; the reasons differ, the mechanism does not.
     *
     * Every one matches on pxeName, NEVER pxeID. pxeMenu is user-writable
     * from iPXE Menu Customization and has an auto_increment primary key,
     * so the seeded rows only sit at their documented ids on a pristine
     * install. Keyed by id, a site whose own custom entry happened to land
     * on 14, 15 or 2 would have had THAT entry hidden instead.
     *
     * @param array $Menus the menu rows to filter
     *
     * @return array
     */
    private function _filterMenus($Menus)
    {
        $hide = [];
        /**
         * "Enroll Secure Boot Key (MOK attended setup)" and its unattended
         * sibling are both meaningless on a legacy BIOS boot: there is no
         * UEFI variable store to enrol into, so every route out of them --
         * MokManager, and the FOS task behind mode=enrollsb -- can only
         * fail. Both carry pxeRegOnly=2 so a technician never has to
         * repoint a client's boot file to reach them, and that "always
         * shown" is what puts them in front of BIOS clients too.
         *
         * Gate on platform, not arch: ia32 UEFI is still UEFI (it gets a
         * different refusal, with its own reason, in
         * _enrollSecureBootChoice()), and a 64-bit CPU booted in CSM mode
         * is not UEFI at all -- so arch answers a different question than
         * the one being asked here.
         *
         * Only hide when the platform is positively known not to be EFI.
         * default.ipxe and every chain this class emits post
         * "param platform ${platform}", so it is reliably present; but an
         * absent value means unknown, and hiding a working option from a
         * UEFI client is a worse failure than showing a dead one to a BIOS
         * client.
         *
         * FOS keeps its own check (fog.enrollsb refuses BIOS/CSM
         * explicitly). This is not redundant: a task scheduled server-side
         * cannot know how the host will next boot, and a host that is BIOS
         * today may be UEFI tomorrow. This hides what cannot work; FOS
         * explains what happened.
         */
        if (isset($_REQUEST['platform'])
            && $_REQUEST['platform'] != 'efi'
        ) {
            $hide[] = 'fog.enrollsecureboot';
            $hide[] = 'fog.enrollsecurebootunattended';
        }
        /**
         * The unattended enrol (mode=enrollsb) only auto-enrols when
         * PK.auth, KEK.auth and db.auth all exist in service/secureboot/ --
         * fog-build-sb-authvars' output, the same directory MOK.der already
         * lives in. Without all three the task type itself has nothing
         * valid to write and refuses (see schema step 323), so hide the PXE
         * entry point rather than advertise a choice that can only fail.
         * The attended item is unaffected: it only ever needed MOK.der,
         * checked separately inside _enrollSecureBootChoice().
         */
        $authDir = BASEPATH . 'service/secureboot' . DS;
        if (!file_exists($authDir . 'PK.auth')
            || !file_exists($authDir . 'KEK.auth')
            || !file_exists($authDir . 'db.auth')
        ) {
            $hide[] = 'fog.enrollsecurebootunattended';
        }
        /**
         * Memtest is memdisk plus a 16-bit x86 image. There is no aarch64
         * memdisk and there never will be, so on ARM the entry could only
         * ever drop the machine back to the menu with an iPXE error. Hidden
         * here rather than refused inside _menuOpt() for the same reason
         * the Secure Boot items are: an option that cannot work should not
         * be on the menu. _menuOpt() keeps its own guard for the case where
         * a site has pointed a custom entry at the same behaviour.
         */
        if (!self::_arch()['memdisk']) {
            $hide[] = 'fog.memtest';
        }
        if (count($hide ?: []) < 1) {
            return $Menus;
        }

        return array_values(
            array_filter(
                (array)$Menus,
                function ($Menu) use ($hide) {
                    return !in_array($Menu->name, $hide, true);
                }
            )
        );
    }
    /**
     * Print the default information for all hosts
     *
     * @return void
     */
    public function printDefault()
    {
        if (self::$Host->isValid()
            && self::getSetting('FOG_NO_MENU')
        ) {
            $this->noMenu();
        }
        if ($this->_hiddenmenu) {
            $this->_chainBoot(true);
            return;
        }

        $ipxeGrabs = [
            'FOG_ADVANCED_MENU_LOGIN',
            'FOG_IPXE_BG_FILE',
            'FOG_IPXE_HOST_CPAIRS',
            'FOG_IPXE_INVALID_HOST_COLOURS',
            'FOG_IPXE_MAIN_COLOURS',
            'FOG_IPXE_MAIN_CPAIRS',
            'FOG_IPXE_MAIN_FALLBACK_CPAIRS',
            'FOG_IPXE_VALID_HOST_COLOURS',
            'FOG_PXE_ADVANCED',
            'FOG_REGISTRATION_ENABLED',
        ];
        list(
            $AdvLogin,
            $bgfile,
            $hostCpairs,
            $hostInvalid,
            $mainColors,
            $mainCpairs,
            $mainFallback,
            $hostValid,
            $Advanced,
            $regEnabled
        ) = self::getSetting($ipxeGrabs);
        $Send['head'] = self::fastmerge(
            self::_archDetect(),
            [
                'goto get_console',
                ':console_set',
            ],
            explode("\n", $mainColors),
            explode("\n", $mainCpairs),
            [
                'goto MENU',
                ':alt_console'
            ],
            explode("\n", $mainFallback),
            [
                'goto MENU',
                ':get_console',
                "console --picture $this->_booturl/ipxe/$bgfile --left 100 "
                . "--right 80 && goto console_set || goto alt_console"
            ]
        );
        $showDebug = isset($_REQUEST['debug']);
        $hostRegColor = self::$Host->isValid() ? $hostValid : $hostInvalid;
        $reg_string = 'NOT registered!';
        if (self::$Host->isValid()) {
            $reg_string = (
                self::$Host->get('pending') ?
                'pending approval!' :
                'registered as ${hostname}!'
            );
        }
        $Send['menustart'] = self::fastmerge(
            [
                ':MENU',
                'menu',
                $hostRegColor,
            ],
            explode("\n", $hostCpairs),
            [
                "item --gap Host is $reg_string",
                'item --gap -- -------------------------------------'
            ]
        );
        $RegArrayOfStuff = [
            (
                self::$Host->isValid() ?
                (
                    self::$Host->get('pending') ?
                    6 :
                    1
                ) :
                0
            ),
            2
        ];
        if (!$regEnabled) {
            $RegArrayOfStuff = array_diff($RegArrayOfStuff, [0]);
        }
        if ($showDebug) {
            $RegArrayOfStuff[] = 3;
        }
        if ($Advanced) {
            $RegArrayOfStuff[] = (
                $AdvLogin ?
                5 :
                4
            );
        }
        $Menus = Route::getList(
            'pxemenuoptions',
            ['regMenu' => $RegArrayOfStuff],
            'AND',
            'id'
        );
        $Menus = $this->_filterMenus($Menus);
        array_map(
            function ($Menu) use (&$Send) {
                $desc = trim($Menu->description);
                if (!$desc) {
                    $desc = $Menu->name;
                }
                $Send['item-' . $Menu->name] = $this->_menuItem(
                    $Menu,
                    $desc
                );
            },
            $Menus
        );
        $Send['default'] = [$this->_defaultChoice];
        array_map(
            function ($Menu) use (&$Send) {
                $Send['choice-'.$Menu->name] = $this->_menuOpt(
                    $Menu,
                    trim($Menu->args)
                );
            },
            $Menus
        );
        $Send['bootme'] = [
            ':bootme',
            "chain -ar $this->_booturl/ipxe/boot.php##params ||",
            'goto MENU',
            'autoboot',
        ];
        $this->_parseMe($Send);
    }
}
