<?php
/**
 * Hostinfo returns the host information
 *
 * PHP version 7.4+
 *
 * @category Hostinfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Agent\DirectoryJoin;
use FOG\Base\FOGCore;
use FOG\Boot\BootMenuBase;
use FOG\Items\MulticastSession;
use FOG\Router\Route;
use FOG\Util\DebugSshKeys;

/**
 * Hostinfo returns the host information
 *
 * @category Hostinfo
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
try {
    FOGCore::getHostItem(false);
    $Task = FOGCore::$Host->get('task');
    //if (FOGCore::$useragent) {
    //    throw new Exception(_('Cannot view from browser'));
    //}
    if (!$Task->isValid()) {
        throw new \Exception(_('Invalid tasking!'));
    }
    $hosttoken = filter_input(INPUT_POST, 'hosttoken');
    if (!$hosttoken) {
        $hosttoken = filter_input(INPUT_GET, 'hosttoken');
    }
    if (!$hosttoken) {
        throw new \Exception(_('No token passed to authenticate this host'));
    }
    if (!FOGCore::$Host->get('tokenlock')) {
        throw new \Exception(_('Have not locked the host for access'));
    }
    // A token is good for HOST_TOKEN_TTL seconds from the time it was issued.
    if (time() - (int)FOGCore::$Host->get('tokenlocktime') >= FOGCore::HOST_TOKEN_TTL) {
        throw new \Exception(_('Invalid token passed for host'));
    }
    // The stored value is the sha256 of the token. A constant-time compare:
    // a loose != also equates numeric strings.
    $tokenHash = hash('sha256', (string)$hosttoken);
    if (!hash_equals((string)FOGCore::$Host->get('token'), $tokenHash)) {
        throw new \Exception(_('Invalid token passed for host'));
    }
    /*
     * Consume the token now, before the reply is built, so a reply that then
     * throws still leaves no usable token. Raw SQL, not HostManager::update():
     * the manager cannot report affected rows. Two requests with the same
     * token cannot both change the row, so only one gets a reply.
     */
    global $DB;
    $consumedToken = FOGCore::createSecToken();
    $DB->query(
        'UPDATE `hosts` SET `hostInfoKey`=:new,`hostInfoLock`=0,'
        . '`hostInfoLockTime`=0 '
        . 'WHERE `hostID`=:id AND `hostInfoKey`=:hash AND `hostInfoLock`=1',
        [],
        [
            ':new' => $consumedToken,
            ':id' => FOGCore::$Host->get('id'),
            ':hash' => $tokenHash
        ]
    );
    if ($DB->affectedRows() !== 1) {
        throw new \Exception(_('Invalid token passed for host'));
    }
    // The raw UPDATE left the loaded host holding the used token and its
    // lock. A listener that saves this object (HOST_INFO_EXPOSE, or the clone
    // BOOT_ITEM_NEW_SETTINGS gets) would write them back and revive the
    // token, so the object takes the consumed values before anything shares it.
    FOGCore::$Host
        ->set('token', $consumedToken)
        ->set('tokenlock', false)
        ->set('tokenlocktime', 0);
    // $Task was loaded above with its own Host built from the old row. A hook
    // reaches that copy through $Host->get('task')->getHost() and through a
    // clone of the task, so the task takes this host object too.
    $Task->set('host', FOGCore::$Host);
    $TaskType = $Task->getTaskType();
    $Image = $Task->getImage();
    if ($TaskType->isInitNeededTasking()) {
        if ($TaskType->isMulticast()) {
            $ids = Route::getList(
                'multicastsessionassociation',
                ['taskID' => $Task->get('id')]
            );
            $msIDs = [];
            foreach ($ids as $id) {
                $msIDs[] = $id->msID;
            }

            $MulticastSession = new MulticastSession(FOGCore::maxId($msIDs));
            if (!$MulticastSession->isValid()) {
                throw new \Exception(_('Invalid Multicast Session'));
            }
            // The same image the boot menu names: the stream's. The helper
            // only sets the values, so save the task when the image changed.
            $TaskImage = $Image;
            $Image = BootMenuBase::reconcileMulticastImage(
                $Task,
                $MulticastSession,
                $Image
            );
            if ($Image !== $TaskImage) {
                $Task->save();
            }
        }
        $StorageGroup = $StorageNode = null;
        /*
         * FOS sends taskinfo=1 only when its kernel line came from the boot
         * menu's getTasking(). That boot already chose the node (plugin hook,
         * overflow move) and recorded it on the task, so use it: the hook and
         * the group's current optimal node could pick another, and a value
         * here overrides the kernel line in FOS. No server state tells the two
         * boots apart, because task creation records a node too. A task made
         * by registration has not been through getTasking(), so the hook
         * still chooses its node (#2188).
         */
        $RecordedNode = $Task->getStorageNode();
        if ('1' === (string)FOGCore::requestParam('taskinfo', '')
            && $RecordedNode
            && $RecordedNode->isValid()
        ) {
            $StorageNode = $RecordedNode;
        } else {
            $HookManager->processEvent(
                'BOOT_TASK_NEW_SETTINGS',
                [
                    'Host' => &FOGCore::$Host,
                    'StorageNode' => &$StorageNode,
                    'StorageGroup' => &$StorageGroup
                ]
            );
        }
        if (!$StorageGroup || !$StorageGroup->isValid()) {
            $StorageGroup = $Image->getStorageGroup();
        }
        // The node the task was queued against, so FOS mounts the node the
        // boot menu counted this task on, not whichever one is optimal now.
        if (!$StorageNode || !$StorageNode->isValid()) {
            $StorageNode = (
                $RecordedNode && $RecordedNode->isValid() ?
                $RecordedNode :
                $StorageGroup->getOptimalStorageNode()
            );
        }
        $osid = $Image->get('osID');
        $img = $Image
            ->get('path');
    }
    $sameBoot = true;
    foreach ([
        FOGCore::$Host->get('kernel'),
        FOGCore::$Host->get('init'),
        FOGCore::$Host->get('kernelArgs'),
        $TaskType->get('kernel'),
        $TaskType->get('initrd')
    ] as $override) {
        if ('' !== trim((string)$override)) {
            $sameBoot = false;
        }
    }
    /*
     * A plugin on BOOT_ITEM_NEW_SETTINGS can change how a registered host
     * boots. This used to set samebootok=0 whenever any plugin listened, so
     * the Location plugin alone sent every host back through a reboot.
     *
     * The FOS asking here booted as an unregistered host, and the boot menu
     * runs the hook only for a registered one (IpxeBootMenu), so this boot
     * had the defaults. Dry-run the hook on those defaults and compare only
     * what FOS gets from it: the kernel and init file names, and the
     * loglevel, ramdisk size and keymap arguments.
     *
     * The same file name from another server is the same boot: the kernel
     * and init are already loaded, and that is all Location's TFTP option
     * changes. 'webserver' is not compared either: it is iPXE's boot-url, and
     * IpxeBootMenu builds FOS's web= before the hook runs.
     *
     * The hook gets a clone of the host, so no plugin can change the host
     * this reply is built from. A plugin that saves the host still writes
     * the row: SubnetGroup sets group membership here, as the PXE boot that
     * this replaces would have done.
     *
     * The defaults are the x86_64 settings. A plugin sees the same value
     * before and after, so the arch matters only to a plugin that chooses by
     * the input file name, and none does.
     */
    if ($sameBoot && $HookManager->listenerCount('BOOT_ITEM_NEW_SETTINGS') > 0) {
        list($kernel, $init, $loglevel, $ramsize, $keymap, $memtest, $webserver, $webroot)
            = FOGCore::getSetting([
                'FOG_TFTP_PXE_KERNEL',
                'FOG_PXE_BOOT_IMAGE',
                'FOG_KERNEL_LOGLEVEL',
                'FOG_KERNEL_RAMDISK_SIZE',
                'FOG_KEYMAP',
                'FOG_MEMTEST_KERNEL',
                'FOG_WEB_HOST',
                'FOG_WEB_ROOT',
            ]);
        // The keys IpxeBootMenu passes. memtest through keySequence are not
        // compared: they are here because a plugin reads and rewrites them.
        $before = [
            'bzImage' => $kernel,
            'imagefile' => $init,
            'initrd' => $init,
            'loglevel' => $loglevel,
            'ramsize' => $ramsize,
            'keymap' => $keymap,
            'memtest' => $memtest,
            'memdisk' => 'memdisk',
            'webserver' => $webserver,
            'webroot' => trim((string)$webroot, '/'),
            'timeout' => 0,
            'keySequence' => '',
        ];
        $after = $before;
        // clone is shallow: the loaded task would stay shared, and the reply
        // reads isDebug and bypassbitlocker from it after the hook.
        $dryHost = clone FOGCore::$Host;
        $dryHost->set('task', clone $Task);
        // The boot menu passes the master node and its group. They are not
        // compared, and a hook that needs them for a choice reads its own.
        $dryGroup = $dryNode = null;
        $dryArgs = [
            'Host' => &$dryHost,
            'StorageGroup' => &$dryGroup,
            'StorageNode' => &$dryNode,
        ];
        foreach (array_keys($after) as $key) {
            $dryArgs[$key] = &$after[$key];
        }
        $HookManager->processEvent('BOOT_ITEM_NEW_SETTINGS', $dryArgs);
        unset($dryArgs);
        // IpxeBootMenu boots 'initrd' when a plugin changed it, else 'imagefile'.
        $afterInit = $after['initrd'] !== $before['initrd']
            ? $after['initrd']
            : $after['imagefile'];
        if (basename((string)$after['bzImage']) !== basename((string)$before['bzImage'])
            || basename((string)$afterInit) !== basename((string)$before['imagefile'])
            // The boot menu reads the host's kernel arguments after the hook.
            || '' !== trim((string)$dryHost->get('kernelArgs'))
        ) {
            $sameBoot = false;
        }
        foreach (['loglevel', 'ramsize', 'keymap'] as $key) {
            if ((string)$after[$key] !== (string)$before[$key]) {
                $sameBoot = false;
            }
        }
    }
    $Inventory = FOGCore::$Host
        ->get('inventory');
    $MACs = FOGCore::$Host
        ->getMyMacs();
    $clientMacs = array_filter(
        (array)FOGCore::parseMacList(
            implode('|', (array)$MACs),
            false,
            true
        )
    );
    // The join account goes to FOS only for a host that joins a domain.
    // With useAD off nothing on the client consumes it, so it stays here.
    $adUser = $pass = '';
    if (FOGCore::$Host->get('useAD')) {
        $pass = FOGCore::$Host->get('ADPass');
        $passtest = FOGCore::aesdecrypt($pass);
        if ($test_base64 = base64_decode($passtest)) {
            if (mb_detect_encoding($test_base64, 'utf-8', true)) {
                $pass = $test_base64;
            }
        } elseif (mb_detect_encoding($passtest, 'utf-8', true)) {
            $pass = $passtest;
        }
        // The account for the host's domain, which a plugin may supply
        // (GH-1887): FOS gets the same join account the clients do.
        list($adUser, $pass) = DirectoryJoin::account(
            FOGCore::$Host,
            (string)FOGCore::$Host->get('ADUser'),
            (string)$pass
        );
    }
    $productKey = FOGCore::$Host->get('productKey');
    $productKeytest = FOGCore::aesdecrypt($productKey);
    if ($test_base64 = base64_decode($productKeytest)) {
        if (mb_detect_encoding($test_base64, 'utf-8', true)) {
            $productKey = $test_base64;
        }
    } elseif (mb_detect_encoding($productKeytest, 'utf-8', true)) {
        $productKey = $productKeytest;
    }
    // GHSA-666x-5fjv-v8j9: the token that opens this reply comes from an
    // unauthenticated, MAC-resolved request (status/hostgetkey.php), and FOS
    // holds no secret that could make it otherwise. So the join password and
    // the product key are sent only where the admin has declared the imaging
    // networks in FOG_HOSTKEY_ALLOWED_SOURCES, and only to a caller inside
    // them. FOS itself reads neither value; a post download script can, and
    // gets empty values until the setting is set. The empty check is made
    // here because hostKeySourceAllowed() allows every source when the
    // setting is empty, which is right for the token and wrong for these.
    $hostKeySources = trim((string)FOGCore::getSetting('FOG_HOSTKEY_ALLOWED_SOURCES'));
    if ('' === $hostKeySources
        || !FOGCore::hostKeySourceAllowed(filter_input(INPUT_SERVER, 'REMOTE_ADDR'))
    ) {
        $pass = $productKey = '';
    }
    $repFields = [
        // No task variable is set here. The boot menu's own code sends them
        // after the hook below, and only the ones the kernel line carries: FOS
        // lets a value here override the kernel line (ADR 0023).
        // '1' only when the boot already in progress equals the one this
        // task would boot: no kernel, init or argument override on the host
        // or the task type, and no plugin that rewrites the boot item.
        'samebootok' => $sameBoot ? '1' : '0',
        // Exposed other elements
        'hostdesc' => FOGCore::$Host->get('description'),
        'hostip' => FOGCore::$Host->get('ip'),
        'hostimageid' => FOGCore::$Host->get('imageID'),
        'hostbuilding' => FOGCore::$Host->get('building'),
        'hostusead' => FOGCore::$Host->get('useAD'),
        'hostaddomain' => FOGCore::$Host->get('ADDomain'),
        'hostaduser' => $adUser,
        'hostadou' => str_replace(';', '', FOGCore::$Host->get('ADOU')),
        'hostadpass' => trim($pass),
        'hostproductkey' => trim($productKey),
        'imagename' => $Image->get('name'),
        'imagedesc' => $Image->get('description'),
        'imageosid' => $osid,
        'imagepath' => $img,
        'primaryuser' => $Inventory->get('primaryUser'),
        'othertag' => $Inventory->get('other1'),
        'othertag1' => $Inventory->get('other2'),
        'sysman' => $Inventory->get('sysman'),
        'sysproduct' => $Inventory->get('sysproduct'),
        'sysserial' => $Inventory->get('sysserial'),
        'mbman' => $Inventory->get('mbman'),
        'mbserial' => $Inventory->get('mbserial'),
        'mbasset' => $Inventory->get('mbasset'),
        'mbproductname' => $Inventory->get('mbproductname'),
        'caseman' => $Inventory->get('caseman'),
        'caseserial' => $Inventory->get('caseserial'),
        'caseasset' => $Inventory->get('caseasset'),
    ];
    $HookManager->processEvent(
        'HOST_INFO_EXPOSE',
        [
            'repFields' => &$repFields,
            'Host'=>&FOGCore::$Host
        ]
    );
    // A listener on the hook must not send a task variable that the kernel
    // line leaves out: FOS lets a reply value override the kernel line.
    foreach (BootMenuBase::TASK_KEYS as $taskKey) {
        unset($repFields[$taskKey]);
    }
    // The task variables of the kernel line (ADR 0023). The boot menu's own
    // code builds them, so this reply and the kernel line cannot drift. They
    // come after the hook, so the kernel line's order wins here too: the task
    // type, then FOG_KERNEL_ARGS, then the host's kernelArgs, last token last.
    // There is no chain request here, so extraargs is empty. A token with no =
    // is a kernel flag and is skipped. A value keeps the backslashes of
    // escapeshellcmd(), as on the kernel line: FOS reads /proc/cmdline as is.
    $taskArgs = BootMenuBase::flattenKernelArgs(
        BootMenuBase::taskKernelArgs(
            $Task,
            $TaskType,
            $Image,
            $StorageNode ?? null,
            $MulticastSession ?? null,
            ''
        )
    );
    foreach ((array)preg_split('#\s+#', trim($taskArgs)) as $token) {
        if (false === strpos($token, '=')) {
            continue;
        }
        // Split at the first = only: the kernel line keeps the full value.
        list($name, $value) = explode('=', $token, 2);
        $repFields[$name] = $value;
    }
    // The SSH public keys FOS installs for root on a debug task (#2193).
    // Last, after the hook and the kernel line's variables, and always sent,
    // empty when the task is not a debug task: FOS lets a reply value override
    // the kernel line, so only this line decides. A debugsshkeys= in the host's
    // kernelArgs, or from a listener, never reaches FOS.
    $repFields['debugsshkeys'] = DebugSshKeys::forTask(
        $Task,
        $TaskType,
        (string)FOGCore::getSetting(DebugSshKeys::SETTING)
    );
    foreach ((array)$repFields as $key => $val) {
        printf(
            "[[ -z $%s ]] && export %s=%s\n",
            $key,
            $key,
            escapeshellarg($val)
        );
    }
} catch (\Exception $e) {
    echo Initiator::e($e->getMessage());
    exit(1);
}
