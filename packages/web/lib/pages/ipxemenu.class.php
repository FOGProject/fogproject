<?php
/**
 * Handles iPXE menu generation
 *
 * PHP version 5
 *
 * @category IPXEMenu
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles iPXE menu generation
 *
 * @category IPXEMenu
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class IPXEMenu extends FOGPage
{
    private static $_send          = array();
    private static $_host          = null;
    private static $_kernel        = '';
    private static $_initrd        = '';
    private static $_booturl       = '';
    private static $_memdisk       = '';
    private static $_memtest       = '';
    private static $_hiddenmenu    = false;
    private static $_web           = '';
    private static $_defaultChoice = '';
    private static $_bootexittype  = '';
    private static $_storage       = '';
    private static $_shutdown      = '';
    private static $_path          = '';
    private static $_timeout       = 5;
    private static $_ks            = '';
    private static $_debug         = false;
    /**
     * The node this works off of.
     *
     * @var string
     */
    public $node = 'ipxe';
    /**
     * Initializes the server information.
     *
     * @param string $name The name this initializes with.
     *
     * @return void
     */
    public function __construct($name = '')
    {
        parent::__construct();
        self::$showhtml = false;
        header("Content-Type: text/plain");
        self::$_host = self::getHostItem(
            false,
            false,
            true
        );
        $web = self::$_web = self::getSetting('FOG_WEB_HOST');
        self::$_send['booturl'] = array(
            '#!ipxe',
            "set fog-ip $web",
            'set boot-url http://${fog-ip}/fog/'
        );
        self::_authenticated();
    }
    /**
     * Setup for hidden menu.
     *
     * @return void
     */
    private static function _hidden()
    {
        self::$_send['chainhide'] = array(
            self::cpuid(),
            self::_startparams(
                'param menuaccess 1'
            ),
        );
    }
    /**
     * Setup for not hidden menu.
     *
     * @return void
     */
    private static function _nothidden()
    {
        self::$_send['chainnohide'] = array(
            self::_cpuid(),
            self::_startparams(
                'param menuAccess 1'
            ),
            self::_bootme()
        );
    }
    /**
     * Check authentication.
     *
     * @return bool
     */
    private static function _authenticated()
    {
        $valid = true;
        $username = $_REQUEST['username'];
        $password = $_REQUEST['password'];
        $valid = (bool)self::getClass('User')
            ->passwordValidate($username, $password);
        if (false === $valid) {
            self::$_send['invalidlogin'] = array(
                '#!ipxe',
                'echo Invalid login!',
                'clear username',
                'clear password',
                'sleep 3'
            );
            self::_parseMe();
            self::_chainBoot();
        }
    }
    /**
     * Parses the information for us.
     *
     * @return void
     */
    private static function _parseMe()
    {
        self::$HookManager->processEvent(
            'IPXE_EDIT',
            array(
                'ipxe'          => &self::$_send,
                'Host'          => &self::$_host,
                'kernel'        => &self::$_kernel,
                'initrd'        => &self::$_initrd,
                'booturl'       => &self::$_booturl,
                'memdisk'       => &self::$_memdisk,
                'memtest'       => &self::$_memtest,
                'hiddenmenu'    => &self::$_hiddenmenu,
                'web'           => &self::$_web,
                'defaultChoice' => &self::$_defaultChoice,
                'bootexittype'  => &self::$_bootexittype,
                'storage'       => &self::$_storage,
                'shutdown'      => &self::$_shutdown,
                'path'          => &self::$_path,
                'timeout'       => &self::$_timeout,
                'KS'            => &self::$_ks
            )
        );
        array_walk_recursive(
            self::$_send,
            function (
                &$val,
                &$key
            ) {
                printf(
                    "%s\n",
                    implode(
                        "\n",
                        (array)$val
                    )
                );
                unset(
                    $val,
                    $key
                );
            }
        );
        self::$_send = array();
    }
    /**
     * The boot chaining function
     *
     * @param bool $shortCircuit To force display or not.
     *
     * @return void
     */
    private static function _chainBoot(
        $shortCircuit = false
    ) {
        if (!self::$_hiddenmenu
            || $shortCircuit
        ) {
            self::$_send['chainnohide'] = array(
                self::_cpuid(),
                self::_startparams(
                    'param menuAccess 1'
                ),
                self::_bootme()
            );
        } else {
            $kskey = '0x1b';
            $ksname = _('Escape');
            if (self::$_ks->isValid()) {
                $kskey = self::$_ks->get('ascii');
                $ksname = self::$_ks->get('name');
            }
            self::$_send['chainhide'] = array(
                self::_cpuid(),
                sprintf(
                    'iseq ${platform} efi && set key %s || set key %s',
                    '0x1b',
                    $kskey
                ),
                sprintf(
                    'iseq ${platform} efi && set keyName %s || set keyName %s',
                    _('Escape'),
                    $ksname
                ),
                sprintf(
                    'prompt --key ${key} --timeout %d %s... (%s ${keyName} %s) &&'
                    . 'goto menuAccess || %s',
                    self::$_timeout,
                    _('Booting'),
                    _('Press'),
                    _('to access the menu'),
                    self::$_bootexittype
                ),
                ':menuAccess',
                self::_startparams(
                    'param menuaccess 1'
                ),
                self::_bootme()
            );
        }
        self::_parseMe();
    }
    /**
     * Presents the exit types.
     *
     * @return void
     */
    private static function _exitTypes()
    {
        $grubChain = 'chain --replace --autofree ${boot'
            . '-url}/service/ipxe/grub.exe --config-file="%s"';
        $sanboot = 'sanboot --no-describe --drive 0x80';
        $grub = array(
            'basic' => sprintf(
                $grubChain,
                'rootnoverify (hd0);chainloader +1'
            ),
            '1cd' => sprintf(
                $grubChain,
                'cdrom --init;map --hook; root (cd0);chainloader (cd0)"'
            ),
            '1fw' => sprintf(
                $grubChain,
                'find --set-root /BOOTMGR;chainloader /BOOTMGR"'
            )
        );
        $refind = sprintf(
            'imgfetch ${boot-url}/service/ipxe/refind.conf%s'
            . 'chain --replace --autofree ${boot-url}/service'
            . '/ipxe/refind.efi',
            "\n"
        );
        self::$_exitTypes = array(
            'sanboot'                  => $sanboot,
            'grub'                     => $grub['basic'],
            'grub_first_hdd'           => $grub['basic'],
            'grub_first_cdrom'         => $grub['1cd'],
            'grub_first_found_windows' => $grub['1fw'],
            'refind_efi'               => $refind,
            'exit'                     => 'exit'
        );
    }
    /**
     * Starts our params caller.
     *
     * @return void
     */
    private static function _startparams()
    {
        self::$_send['params'] = self::fastmerge(
            array(
                'params',
                'isset ${net0/mac} && param mac0 ${net0/mac} ||',
                'isset ${net1/mac} && param mac1 ${net1/mac} ||',
                'isset ${net2/mac} && param mac2 ${net2/mac} ||',
                'param arch ${arch}',
                'param platform ${platform}',
                sprintf(
                    'param debug %s',
                    (int)self::$_debug
                )
            ),
            func_get_args()
        );
    }
    /**
     * Generates our cpuid string.
     *
     * @return void
     */
    private static function _cpuid()
    {
        self::$_send['cpuid'] = array(
            'cpuid --ext 29 && set arch x86_64 || set arch i386'
        );
    }
    /**
     * Generates our bootme ipxe function.
     *
     * @return void
     */
    private static function _bootme()
    {
        self::$_send['bootme'] = array(
            ':bootme',
            'chain --replace --autofree ${boot-url}/ipxe/boot.php##params'
        );
    }
}
