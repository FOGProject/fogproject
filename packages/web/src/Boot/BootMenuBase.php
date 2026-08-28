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
                $mac = $_REQUEST['mac'];
            }
            $clamav = '';
            if (in_array($TaskType->get('id'), [21, 22])) {
                $clamav = sprintf(
                    '%s:%s',
                    $ip,
                    FOG_BASE_DIR . DS . 'clamav'
                );
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
                    'value' => "clamav=" . (isset($clamav) ? $clamav : ''),
                    'active' => in_array($TaskType->get('id'), [21, 22]),
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
                // step 323), which is what lets it enrol automatically in Setup
                // Mode and stage a request non-interactively otherwise -- so it
                // takes the ordinary kernel-chain path like any other task.
                //
                // _enrollSecureBootChoice() and pxeID 14 both stay: chaining
                // directly to MokManager is still how a technician answers a
                // pending request, or enrols from local FAT media on a machine
                // FOS cannot boot.
                $this->_printTasking($kernelArgsArray);
            }
        }
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
