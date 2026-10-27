<?php
/**
 * Transport-agnostic half of the FOG boot menu
 *
 * PHP version 7.4+
 *
 * @category BootMenuBase
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Boot;

use FOG\Base\FOGBase;
use FOG\Items\MulticastSessionAssociation;
use FOG\Items\StorageNode;
use FOG\Router\Route;

/**
 * Transport-agnostic half of the FOG boot menu
 *
 * getTasking() decides what a tasked host should boot and with which kernel
 * arguments. None of that reasoning is iPXE's -- it is FOG's -- but it lived
 * inside IpxeBootMenu because iPXE was the only consumer. A second bootloader
 * (U-Boot/extlinux, for ARM boards whose firmware cannot chain iPXE) needs the
 * identical decisions and a completely different output syntax, so the
 * decisions live here and each subclass renders them.
 *
 * Subclasses supply the four rendering hooks below and nothing else about
 * tasking: if a change belongs in one renderer only, it belongs in a hook.
 *
 * @category BootMenuBase
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
abstract class BootMenuBase extends FOGBase
{
    /**
     * The kernel string
     *
     * @var string
     */
    protected $_kernel;
    /**
     * The storage prefix stripped back out of the kernel string
     *
     * @var string
     */
    protected $_storage;

    /**
     * The fogpin= kernel argument for FOS, or '' when there is none to give.
     *
     * FOS used to call this server with curl -k on every request. It now
     * verifies the server's TLS key against fogpin= (fos#102, fos ADR-0020),
     * because a FOG server's certificate is usually signed by the CA the
     * installer generated, and no CA bundle in the init carries that CA. The
     * pin is the base64 SHA-256 of the served certificate's
     * SubjectPublicKeyInfo, which is what curl --pinnedpubkey takes.
     *
     * The key is read from the server as a client sees it, at each render,
     * so a renewed or replaced certificate needs no other step. It is read
     * twice: once over loopback, which no network attacker can answer, and
     * once at the address in $web, which is what FOS will reach. Only a key
     * both agree on is pinned. A reverse proxy, a web host that is not this
     * machine, or a poisoned name lookup gives two different keys, and then
     * no pin is sent and FOS falls back to its CA bundle. A wrong pin would
     * stop every task; a missing one costs only the stronger check.
     *
     * Plain http has no key to pin.
     *
     * @param string $web the web= URL the kernel line carries
     *
     * @return string ' fogpin=<base64>' or ''
     */
    protected static function fosPinArg($web)
    {
        $parts = parse_url((string)$web);
        if (!is_array($parts)
            || 'https' !== strtolower($parts['scheme'] ?? '')
            || empty($parts['host'])
        ) {
            return '';
        }
        $host = trim($parts['host'], '[]');
        $port = (int)($parts['port'] ?? 443);
        $local = self::_servedKeyPin('127.0.0.1', $port, $host);
        if ('' === $local
            || $local !== self::_servedKeyPin($host, $port, $host)
        ) {
            return '';
        }
        return ' fogpin=' . $local;
    }

    /**
     * The base64 SHA-256 of the key a TLS server presents, or ''.
     *
     * Verification is off on purpose: this reads the key in order to pin
     * it, and the caller's loopback comparison is what makes it trustworthy.
     *
     * @param string $connect address to connect to
     * @param int    $port    TLS port
     * @param string $name    server name to send, so a name-based vhost
     *                        answers with the certificate FOS will see
     *
     * @return string
     */
    private static function _servedKeyPin($connect, $port, $name)
    {
        $context = stream_context_create(
            [
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'peer_name' => $name,
                ],
            ]
        );
        $target = false !== strpos($connect, ':') ? "[$connect]" : $connect;
        $sock = @stream_socket_client(
            sprintf('ssl://%s:%d', $target, $port),
            $errno,
            $errstr,
            2,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (false === $sock) {
            return '';
        }
        $params = stream_context_get_params($sock);
        fclose($sock);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $pub = $cert ? openssl_pkey_get_public($cert) : false;
        $details = $pub ? openssl_pkey_get_details($pub) : false;
        if (!is_array($details) || empty($details['key'])) {
            return '';
        }
        // The PEM body IS the DER SubjectPublicKeyInfo, base64-encoded.
        $der = base64_decode(
            preg_replace('/-----[^-]+-----|\s+/', '', $details['key'])
        );
        return base64_encode(hash('sha256', $der, true));
    }

    /**
     * Render the menu a host with no tasking should see.
     *
     * @return void
     */
    abstract public function printDefault();

    /**
     * Render the "this MAC is image-ignored" refusal.
     *
     * @return void
     */
    abstract protected function _printImageIgnored();

    /**
     * Render a kernel chain for a task.
     *
     * @param array $kernelArgsArray the argument set getTasking() computed
     *
     * @return void
     */
    abstract protected function _printTasking($kernelArgsArray);

    /**
     * Render a memtest tasking (task type 4).
     *
     * Memtest is not a kernel-and-init chain -- it is a memdisk image -- so
     * the argument set getTasking() computed does not apply to it and the
     * hook takes no arguments.
     *
     * @return void
     */
    abstract protected function _printMemtestTasking();

    /**
     * Get's a current tasking if any
     *
     * @return void
     */
    public function getTasking()
    {
        $Task = self::$Host->get('task');
        if (!$Task->isValid() || $Task->isSnapinTasking()) {
            $this->printDefault();
        } else {
            if (self::$Host->get('mac')->isImageIgnored()) {
                $this->_printImageIgnored();
            }
            $this->_kernel = str_replace(
                $this->_storage,
                '',
                $this->_kernel
            );
            $TaskType = $Task->getTaskType();
            $imagingTasks = $TaskType->isImagingTask();
            if ($TaskType->isMulticast()) {
                $msaID = self::maxId(Route::getIds('multicastsessionassociation', ['taskID' => $Task->get('id')]));
                $MulticastSessionAssoc = new MulticastSessionAssociation($msaID);
                $MulticastSession = $MulticastSessionAssoc->getMulticastSession();
                if ($MulticastSession && $MulticastSession->isValid()) {
                    self::$Host->set('imageID', $MulticastSession->get('image'));
                }
            }
            if ($TaskType->isInitNeededTasking()) {
                $Image = $Task->getImage();
                $StorageGroup = null;
                $StorageNode = null;
                self::$HookManager->processEvent(
                    'BOOT_TASK_NEW_SETTINGS',
                    [
                        'Host' => &self::$Host,
                        'StorageNode' => &$StorageNode,
                        'StorageGroup' => &$StorageGroup,
                        'TaskType' => &$TaskType
                    ]
                );
                if (!$StorageGroup || !$StorageGroup->isValid()) {
                    $StorageGroup = $Image->getStorageGroup();
                }
                $getter = 'getOptimalStorageNode';
                if ($Task->isCapture()
                    || $TaskType->isCapture()
                ) {
                    $StorageGroup = $Image->getPrimaryStorageGroup();
                    $getter = 'getMasterStorageNode';
                }
                if ($TaskType->isMulticast()) {
                    $getter = 'getMasterStorageNode';
                }
                if (!$StorageNode || !$StorageNode->isValid()) {
                    $StorageNode = $StorageGroup->{$getter}();
                }
                if ($Task->get('storagenodeID') != $StorageNode->get('id')) {
                    $Task->set('storagenodeID', $StorageNode->get('id'));
                }
                if ($Task->get('storagegroupID') != $StorageGroup->get('id')) {
                    $Task->set('storagegroupID', $StorageGroup->get('id'));
                }
                $Task->save();
                self::$HookManager->processEvent(
                    'BOOT_TASK_NEW_SETTINGS',
                    [
                        'Host' => &self::$Host,
                        'StorageNode' => &$StorageNode,
                        'StorageGroup' => &$StorageGroup,
                        'TaskType' => &$TaskType
                    ]
                );
                $osid = (int)$Image->get('osID');
                $storage = '';
                $img = '';
                $imgFormat = '';
                $imgType = '';
                $imgPartitionType = '';
                $serviceNames = [
                    'FOG_CAPTUREIGNOREPAGEHIBER',
                    'FOG_CAPTURERESIZEPCT',
                    'FOG_CHANGE_HOSTNAME_EARLY',
                    'FOG_DISABLE_CHKDSK',
                    'FOG_KERNEL_ARGS',
                    'FOG_KERNEL_DEBUG',
                    'FOG_MULTICAST_RENDEZVOUS',
                    'FOG_PIGZ_COMP',
                    'FOG_TFTP_HOST',
                    'FOG_WIPE_TIMEOUT'
                ];
                list(
                    $cappage,
                    $capresz,
                    $hosterl,
                    $chkdsk,
                    $kargs,
                    $kdebug,
                    $mcastrdv,
                    $pigz,
                    $tftp,
                    $timeout
                ) = self::getSetting($serviceNames);
                $shutdown = self::_wantsShutdown($TaskType->get('kernelArgs'))
                    || self::_wantsShutdown(self::_extraArgs());
                $globalPIGZ = $pigz;
                $PIGZ_COMP = $globalPIGZ;
                if ($StorageNode instanceof StorageNode && $StorageNode->isValid()) {
                    $ip = trim($StorageNode->get('ip'));
                    $ftp = $ip;
                }
                if ($imagingTasks) {
                    if (!($StorageNode instanceof StorageNode
                        && $StorageNode->isValid())
                    ) {
                        throw new \Exception(_('No valid storage nodes found'));
                    }
                    $storage = escapeshellcmd(
                        sprintf(
                            '%s:/%s/%s',
                            $ip,
                            trim($StorageNode->get('path'), '/'),
                            (
                                $TaskType->isCapture() ?
                                'dev/' :
                                ''
                            )
                        )
                    );
                    $storageip = $ip;
                    $img = escapeshellcmd(
                        $Image->get('path')
                    );
                    $imgFormat = (int)$Image
                        ->get('format');
                    $imgType = $Image
                        ->getImageType()
                        ->get('type');
                    $imgPartitionType = $Image
                        ->getPartitionType();
                    $imgid = $Image
                        ->get('id');
                    $image_PIGZ = $Image->get('compress');
                    if (is_numeric($image_PIGZ) && $image_PIGZ > -1) {
                        $PIGZ_COMP = $image_PIGZ;
                    }
                    if (in_array($imgFormat, ['', null, 0, 1, 2, 3, 4])) {
                        if ($PIGZ_COMP > 9) {
                            $PIGZ_COMP = 9;
                        }
                    }
                } else {
                    // These setup so postinit scripts can operate.
                    if ($StorageNode instanceof StorageNode
                        && $StorageNode->isValid()
                    ) {
                        $ip = trim($StorageNode->get('ip'));
                        $ftp = $ip;
                    } else {
                        $ip = $tftp;
                        $ftp = $tftp;
                    }
                    $storage = escapeshellcmd(
                        sprintf(
                            '%s:/%s/dev/',
                            $ip,
                            trim($StorageNode->get('path'), '/')
                        )
                    );
                    $storageip = $ip;
                }
            }
            if (self::$Host->isValid()) {
                $mac = self::$Host->get('mac');
            } else {
                // Same correction as falseTasking(): there is no 'mac'
                // request field to read -- the iPXE menu posts mac0,
                // macboot and mac1..mac7 -- so this arm warned on an
                // undefined index and produced an empty argument.
                $mac = self::$requestMacs[0] ?? '';
            }
            $chkdsk = !isset($chkdsk) || $chkdsk == 1 ? 0 : 1;
            $MACs = self::$Host->getMyMacs();
            $clientMacs = array_filter(
                (array)self::parseMacList(
                    implode(
                        '|',
                        (array)$MACs
                    ),
                    false,
                    true
                )
            );
            $fdrive = self::$Host->get('kernelDevice');
            $imagingTaskActive = (isset($imagingTasks) && $imagingTasks);
            $kernelArgsArray = [
                "mac=$mac",
                "ftp=" . (isset($ftp) ? $ftp : ''),
                "storage=" . (isset($storage) ? $storage : ''),
                "storageip=" . (isset($storageip) ? $storageip : ''),
                "osid=" . (isset($osid) ? $osid : ''),
                "irqpoll",
                [
                    'value' => "mcastrdv=". (isset($mcastrdv) ? $mcastrdv : ''),
                    'active' => isset($mcastrdv) && !empty($mcastrdv)
                ],
                [
                    'value' => "hostname=" . self::$Host->get('name'),
                    'active' => (
                        count($clientMacs ?: []) > 0
                        || (
                            self::$Host->isValid()
                            && self::$Host->get('id') > 0
                        )
                    ),
                ],
                [
                    'value' => "chkdsk=" . (isset($chkdsk) ? $chkdsk : ''),
                    'active' => $imagingTaskActive,
                ],
                [
                    'value' => "img=" . (isset($img) ? $img : ''),
                    'active' => $imagingTaskActive,
                ],
                [
                    'value' => "imgType=" . (isset($imgType) ? $imgType : ''),
                    'active' => $imagingTaskActive,
                ],
                [
                    'value' => "imgPartitionType=" . (isset($imgPartitionType) ? $imgPartitionType : ''),
                    'active' => $imagingTaskActive,
                ],
                [
                    'value' => "imgid=". (isset($imgid) ? $imgid : ''),
                    'active' => $imagingTaskActive,
                ],
                [
                    'value' => "imgFormat=". (isset($imgFormat) ? $imgFormat : ''),
                    'active' => $imagingTaskActive,
                ],
                [
                    'value' => "PIGZ_COMP=-". (isset($PIGZ_COMP) ? $PIGZ_COMP : '0'),
                    'active' => $imagingTaskActive,
                ],
                [
                    'value' => 'shutdown=1',
                    'active' => $Task->get('shutdown') || (isset($shutdown) ? $shutdown : false),
                ],
                [
                    'value' => "fdrive=$fdrive",
                    'active' => self::$Host->get('kernelDevice'),
                ],
                [
                    'value' => 'hostearly=1',
                    'active' => (
                        isset($hosterl)
                        && $hosterl
                        && $imagingTaskActive ?
                        true :
                        false
                    ),
                ],
                [
                    'value' => sprintf(
                        'pct=%d',
                        (
                            isset($capresz)
                            && is_numeric($capresz)
                            && $capresz >= 5
                            && $capresz < 100 ?
                            $capresz :
                            '5'
                        )
                    ),
                    'active' => $imagingTaskActive && $TaskType->isCapture(),
                ],
                [
                    'value' => sprintf(
                        'ignorepg=%d',
                        (
                            isset($cappage) && $cappage ?
                            1 :
                            0
                        )
                    ),
                    'active' => $imagingTaskActive && $TaskType->isCapture(),
                ],
                [
                    'value' => sprintf(
                        'port=%s',
                        (
                            $TaskType->isMulticast() ?
                            $MulticastSession->get('port') :
                            null
                        )
                    ),
                    'active' => $TaskType->isMulticast(),
                ],
                [
                    // FOS sizes udp-receiver's --start-timeout from this.
                    'value' => sprintf(
                        'mcastMaxWait=%d',
                        (
                            $TaskType->isMulticast() ?
                            $MulticastSession->maxwaitMinutes() :
                            0
                        )
                    ),
                    'active' => $TaskType->isMulticast(),
                ],
                [
                    'value' => sprintf(
                        'winuser=%s',
                        $Task->get('passreset')
                    ),
                    'active' => $TaskType->get('id') == '11',
                ],
                [
                    'value' => 'isdebug=yes',
                    'active' => $Task->get('isDebug'),
                ],
                [
                    'value' => 'debug',
                    'active' => isset($kdebug) && $kdebug,
                ],
                [
                    'value' => 'seconds='. (isset($timeout) ? $timeout : 300),
                    'active' => in_array($TaskType->get('id'), range(18, 20)),
                ],
                [
                    'value' => 'bitlockerbypass=1',
                    'active' => $Task->get('bypassbitlocker') > 0
                ],
                $TaskType->get('kernelArgs'),
                isset($kargs) ? $kargs : '',
                self::$Host->get('kernelArgs'),
            ];
            if ($Task->get('typeID') == 4) {
                // Memtest is a memdisk image, not a kernel-and-init
                // chain, so none of the arguments computed above apply to
                // it and the renderer is handed no argument set at all.
                $this->_printMemtestTasking();
            } else {
                // ENROLL_SECUREBOOT used to be special-cased here alongside
                // Memtest, chaining straight to _enrollSecureBootChoice() so a
                // scheduled task landed on the same MokManager screen as PXE
                // menu item 14. It now boots FOS instead (mode=enrollsb, schema
                // step 323), which is what lets it enroll automatically in Setup
                // Mode and stage a request non-interactively otherwise -- so it
                // takes the ordinary kernel-chain path like any other task.
                //
                // _enrollSecureBootChoice() and pxeID 14 both stay: chaining
                // directly to MokManager is still how a technician answers a
                // pending request, or enrolls from local FAT media on a machine
                // FOS cannot boot.
                $this->_printTasking($kernelArgsArray);
            }
        }
    }

    /**
     * Flatten the argument set getTasking() computed into one kernel command
     * line.
     *
     * An entry is either a plain string that is always included, or a
     * ['value' => ..., 'active' => ...] pair that is included only when
     * active. Both then get the same debug rewrite, which is why the two
     * branches this replaces were identical apart from where the string came
     * from.
     *
     * By value, not by reference: an earlier loop took &$arg over a (array)
     * cast and unset() the reference inside the body, which does nothing
     * useful here and is a trap the moment anyone writes through it -- the
     * cast produces a temporary, so the write lands nowhere.
     *
     * This is arithmetic on FOG's own arguments, not syntax belonging to any
     * one bootloader, so it lives here rather than being copied into each
     * renderer -- a second copy is a second place for the debug rewrite or
     * the de-duplication to drift.
     *
     * @param array $kernelArgsArray the argument set to flatten
     *
     * @return string
     */
    protected static function flattenKernelArgs($kernelArgsArray)
    {
        $kernelArgs = [];
        foreach ((array)$kernelArgsArray as $arg) {
            if (is_array($arg)) {
                if (empty($arg['value']) || empty($arg['active'])) {
                    continue;
                }
                $arg = $arg['value'];
            }
            if (empty($arg)) {
                continue;
            }
            $kernelArgs[] = preg_replace(
                '#mode=debug|mode=onlydebug#i',
                'isdebug=yes',
                $arg
            );
        }

        return implode(
            ' ',
            array_values(
                array_unique(
                    array_filter($kernelArgs)
                )
            )
        );
    }

    /**
     * The extraargs the chain arrived with, '' when there were none.
     *
     * Not every chain back into a flow carries them, and passing an unset
     * key to stripos()/preg_match() emits a PHP warning straight into the
     * iPXE script this class is building.
     *
     * @return string
     */
    protected static function _extraArgs()
    {
        return (string)($_REQUEST['extraargs'] ?? '');
    }

    /**
     * Whether a kernel-argument string asks for a shutdown when the task
     * ends.
     *
     * stripos()'s arguments were the wrong way round at all five call sites
     * this replaces: stripos('shutdown=1', $args) searches for the
     * ARGUMENTS inside the literal, not the other way about. Two silent
     * consequences, in opposite directions.
     *
     * A real 'shutdown=1 mode=debug' was never detected -- the ten-character
     * literal cannot contain it -- so a custom iPXE menu entry that asks for
     * a shutdown never produced one. Only an extraargs of EXACTLY
     * 'shutdown=1' worked, by accident.
     *
     * And an empty string matches at offset 0, so the
     * `false !== stripos(...)` spelling reported a shutdown for any task
     * type with no kernel arguments at all. The four stock task types with
     * empty ttKernelArgs are all routed elsewhere before reaching that
     * test, which is why this has not been visible; a site-added task type,
     * or a chain posting an empty extraargs, reaches it.
     *
     * @param string $args the argument string to test
     *
     * @return bool
     */
    protected static function _wantsShutdown($args)
    {
        $args = trim((string)$args);

        return '' !== $args && false !== stripos($args, 'shutdown=1');
    }
}
