<?php
/**
 * U-Boot (extlinux) boot menu for the FOG PXE system
 *
 * PHP version 7.4+
 *
 * @category UbootBootMenu
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Boot;

use FOG\Items\StorageNode;
use FOG\Router\Route;

/**
 * U-Boot (extlinux) boot menu for the FOG PXE system
 *
 * A Raspberry Pi -- and most ARM boards -- cannot chain iPXE: the firmware
 * hands off to U-Boot, which speaks its own PXELINUX-derived config format
 * rather than iPXE script. U-Boot's `pxe`/`sysboot` commands consume plain
 * text with `label`, `kernel`, `initrd` and `append` directives, which is
 * almost exactly the shape of the decisions BootMenuBase already makes -- so
 * this class is only the syntax, and every decision above it is shared with
 * IpxeBootMenu.
 *
 * Deliberately NOT a boot.scr. That is an mkimage-wrapped binary carrying a
 * legacy uImage header and CRC, which PHP would have to synthesise, and it is
 * imperative -- a menu becomes hand-written script logic. extlinux text needs
 * no wrapping and can be read straight off the wire with curl, which is the
 * only way anyone is going to debug an ARM board they do not own.
 *
 * What this class deliberately does NOT do, because it is board-specific and
 * therefore the administrator's to own (see the U-Boot section of the docs):
 *
 *   - Deliver a DTB. No `fdt`/`fdtdir` directive is emitted, so U-Boot boots
 *     with the device tree already at $fdtcontroladdr -- on a Pi, the one the
 *     VideoCore firmware just built for the actual board revision, which is
 *     more accurate than anything FOG could serve. An admin who needs a
 *     different tree adds `fdt` to their own bootcmd.
 *   - Choose a load address. Those are per-board and belong in the U-Boot
 *     environment.
 *   - Serve U-Boot itself, or write pxelinux.cfg files. FOG stays stateless:
 *     the board fetches this endpoint by MAC and gets an answer computed from
 *     the database, so nothing on disk can disagree with what is queued.
 *
 * @category UbootBootMenu
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UbootBootMenu extends BootMenuBase
{
    /**
     * Absolute URL of the kernel to boot
     *
     * @var string
     */
    private $_kernelUrl;
    /**
     * Absolute URL of the init to boot
     *
     * @var string
     */
    private $_initrdUrl;

    /**
     * Builds and emits the config for whatever this host should do next.
     */
    public function __construct()
    {
        parent::__construct();

        list(
            $webserver,
            $curroot,
            $bzImage,
            $imagefile,
            $kernelLogLevel,
            $kernelRamDisk,
            $kernelArgs,
            $kernelDebug,
            $keymap
        ) = self::getSetting(
            [
                'FOG_WEB_HOST',
                'FOG_WEB_ROOT',
                // Always the ARM pair. U-Boot is how ARM boards boot, and a
                // board reaching this endpoint has already told us which
                // world it is in by being unable to run iPXE. There is no
                // ?arch= to trust here: unlike iPXE, U-Boot does not report
                // one, so guessing from a query string an admin hand-wrote
                // would be worse than committing to the answer.
                'FOG_TFTP_PXE_KERNEL_ARM',
                'FOG_PXE_BOOT_IMAGE_ARM',
                'FOG_KERNEL_LOGLEVEL',
                'FOG_KERNEL_RAMDISK_SIZE',
                'FOG_KERNEL_ARGS',
                'FOG_KERNEL_DEBUG',
                'FOG_KEYMAP',
            ]
        );

        // Same normalisation as IpxeBootMenu: the value has been written by
        // the installer, by hand in FOG Settings, and by older versions, so
        // it turns up with and without either slash, and a nested webroot
        // such as '/apps/fog/' has to keep every segment.
        $bootroot = trim((string)$curroot, '/');
        $curroot = '/' . ('' === $bootroot ? '' : $bootroot . '/');
        $web = sprintf('%s://%s%s', self::$httpproto, $webserver, $curroot);

        // A host (or its group) can name its own kernel and init. No
        // _fileFitsArch() check as iPXE does: that guard exists because iPXE
        // serves three architectures from one class and an x86 filename on an
        // ARM client cannot boot. Here there is only one architecture, so an
        // override that is wrong is wrong in a way FOG cannot detect from the
        // name.
        if (self::$Host->isValid()) {
            $bzImage = self::$Host->get('kernel') ?: $bzImage;
            $imagefile = self::$Host->get('init') ?: $imagefile;
        }

        $this->_kernelUrl = $web . 'service/ipxe/' . basename($bzImage);
        $this->_initrdUrl = $web . 'service/ipxe/' . basename($imagefile);

        $StorageNodeID = self::minId(
            Route::getIds('storagenode', ['isEnabled' => 1, 'isMaster' => 1])
        );
        $StorageNode = new StorageNode($StorageNodeID);
        if ($StorageNode->isValid()) {
            $this->_storage = sprintf(
                'storage=%s:/%s/ storageip=%s',
                trim($StorageNode->get('ip')),
                trim($StorageNode->get('path'), '/'),
                trim($StorageNode->get('ip'))
            );
        }

        /*
         * The append line every task starts from. Unlike iPXE's, it carries
         * no 'kernel <file>' prefix and no initrd= -- extlinux names both on
         * their own directives -- and no setmacto=${setmacto}, because that
         * is an iPXE variable and U-Boot would pass the four literal
         * characters '${se' onward to the kernel.
         */
        $this->_kernel = sprintf(
            'loglevel=%s root=/dev/ram0 rw ramdisk_size=%s%sweb=%s '
            . 'consoleblank=0%srootfstype=ext4%s%s %s',
            $kernelLogLevel,
            $kernelRamDisk,
            strlen($keymap) ? sprintf(' keymap=%s ', $keymap) : ' ',
            $web,
            $kernelDebug ? ' debug ' : ' ',
            $kernelArgs ? sprintf(' %s', $kernelArgs) : '',
            (
                self::$Host->isValid() && self::$Host->get('kernelArgs') ?
                sprintf(' %s', self::$Host->get('kernelArgs')) :
                ''
            ),
            $this->_storage
        );

        if (self::$Host->isValid() && self::$Host->get('task')->isValid()) {
            $this->getTasking();
            exit();
        }
        $this->printDefault();
        exit();
    }

    /**
     * Emit a complete extlinux config.
     *
     * One label, always named 'fog', always the default: U-Boot's parser
     * takes `default` by label name, and a board with no console (which is
     * most of them) cannot pick from a menu anyway. `timeout` is in tenths of
     * a second and 1 is the shortest non-zero value -- 0 means "wait
     * forever", which would hang a headless board.
     *
     * @param array  $lines  the directives inside the label
     * @param string $reason a comment line explaining the config, or ''
     *
     * @return void
     */
    private function _emit(array $lines, $reason = '')
    {
        $out = ['# Generated by FOG Project. Do not edit -- it is not stored.'];
        if ('' !== $reason) {
            $out[] = '# ' . $reason;
        }
        $out[] = 'default fog';
        $out[] = 'timeout 1';
        $out[] = '';
        $out[] = 'label fog';
        foreach ($lines as $line) {
            $out[] = '    ' . $line;
        }

        echo implode("\n", $out) . "\n";
    }

    /**
     * What a host with no tasking should do: nothing, and quickly.
     *
     * `localboot` is U-Boot's own directive for "I am not booting anything,
     * carry on" -- it returns from the pxe command and lets the board's
     * bootcmd fall through to its next boot method, which is the disk. There
     * is no menu here on purpose: a FOG boot menu is a list of things a
     * technician picks with a keyboard on a screen, and the boards this
     * serves generally have neither.
     *
     * @return void
     */
    public function printDefault()
    {
        $this->_emit(
            [
                'menu label Boot from local disk',
                'localboot 0',
            ],
            'No tasking for this host.'
        );
    }

    /**
     * A MAC this host is registered under is on the image-ignore list.
     *
     * @return void
     */
    protected function _printImageIgnored()
    {
        $this->_emit(
            [
                'menu label Boot from local disk',
                'localboot 0',
            ],
            'This MAC is set to be ignored for imaging.'
        );
        /*
         * Stop here, which IpxeBootMenu's version deliberately does not --
         * it prints a notice and lets getTasking() carry on, because an iPXE
         * script is a sequence and a later chain simply wins. extlinux is a
         * single document: a second `label fog` after this one is not a
         * later instruction, it is a duplicate the parser resolves by taking
         * whichever it saw first. Emitting both would make the ignore flag
         * either meaningless or silently authoritative depending on order.
         */
        exit();
    }

    /**
     * Emit the kernel chain for a task.
     *
     * @param array $kernelArgsArray the argument set getTasking() computed
     *
     * @return void
     */
    protected function _printTasking($kernelArgsArray)
    {
        $this->_emit(
            [
                'menu label FOG Project tasking',
                'kernel ' . $this->_kernelUrl,
                'initrd ' . $this->_initrdUrl,
                /*
                 * Whitespace collapsed, not just trimmed. The base argument
                 * string is built with the same conditional-space format
                 * IpxeBootMenu uses, which leaves double spaces where an
                 * optional argument is absent -- harmless on a kernel command
                 * line, but this file is meant to be readable with curl by
                 * someone debugging a board they cannot see.
                 */
                'append ' . trim(
                    (string)preg_replace(
                        '/\s+/',
                        ' ',
                        $this->_kernel . ' '
                        . self::flattenKernelArgs($kernelArgsArray)
                    )
                ),
            ]
        );
    }

    /**
     * Memtest cannot run here.
     *
     * memdisk is a 16-bit x86 real-mode loader and memtest86+ is an x86
     * image; neither has an aarch64 build, which is why the arm64 arch
     * profile in IpxeBootMenu sets 'memdisk' => false. Say so rather than
     * emitting a config that would fail in U-Boot with nothing to explain
     * it -- a board that silently does not boot is exactly the failure that
     * made forums topic 18229 unreadable.
     *
     * @return void
     */
    protected function _printMemtestTasking()
    {
        $this->_emit(
            [
                'menu label Boot from local disk',
                'localboot 0',
            ],
            'Memtest is an x86 image; FOG ships no ARM build of it. '
            . 'Cancel this task and run memtest from the board vendor.'
        );
    }
}
