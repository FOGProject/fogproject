<?php
/**
 * Hostinfo returns the host information
 *
 * PHP version 5
 *
 * @category Hostinfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Hostinfo returns the host information
 *
 * @category Hostinfo
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
header('Content-Type: text/plain');
try {
    FOGCore::getHostItem(false);
    $Task = FOGCore::$Host->get('task');
    //if (FOGCore::$useragent) {
    //    throw new Exception(_('Cannot view from browser'));
    //}
    if (!$Task->isValid()) {
        throw new Exception(_('Invalid tasking!'));
    }
    $hosttoken = filter_input(INPUT_POST, 'hosttoken');
    if (!$hosttoken) {
        $hosttoken = filter_input(INPUT_GET, 'hosttoken');
    }
    if (!$hosttoken) {
        throw new Exception(_('No token passed to authenticate this host'));
    }
    if (!FOGCore::$Host->get('tokenlock')) {
        throw new Exception(_('Have not locked the host for access'));
    }
    if ($hosttoken != FOGCore::$Host->get('token')) {
        throw new Exception(_('Invalid token passed for host'));
    }
    $TaskType = $Task->getTaskType();
    $Image = $Task->getImage();
    if ($TaskType->isInitNeededTasking()) {
        if ($TaskType->isMulticast()) {
            Route::listem(
                'multicastsessionassociation',
                ['taskID' => $Task->get('id')]
            );
            $ids = json_decode(
                Route::getData()
            );
            $msIDs = [];
            foreach ($ids->data as $id) {
                $msIDs[] = $id->msID;
            }

            $MulticastSession = FOGCore::getClass(
                'MulticastSession',
                @max($msIDs)
            );
            $taskImgID = $Task->get('imageID');
            $mcImgID = $MulticastSession->get('image');
            if ($taskImgID != $mcImgID) {
                $Task
                    ->set('imageID', $mcImgID)
                    ->save();
                FOGCore::$Host
                    ->set('imageID', $mcImgID);
                $Image = new Image($mcImgID);
            }
            $port = $MulticastSession->get('port');
        }
        $StorageGroup = $StorageNode = null;
        $HookManager->processEvent(
            'BOOT_TASK_NEW_SETTINGS',
            [
                'Host' => &FOGCore::$Host,
                'StorageNode' => &$StorageNode,
                'StorageGroup' => &$StorageGroup
            ]
        );
        if (!$StorageGroup || !$StorageGroup->isValid()) {
            $StorageGroup = $Image->getStorageGroup();
        }
        if (!$StorageNode || !$StorageNode->isValid()) {
            $StorageNode = $StorageGroup->getOptimalStorageNode();
        }
        $osid = $Image->get('osID');
        $storage = sprintf(
            '%s:/%s/%s',
            trim($StorageNode->get('ip')),
            trim($StorageNode->get('path'), '/'),
            (
                $TaskType->isCapture() ?
                'dev/' :
                ''
            )
        );
        $storageip = FOGCore::resolveHostname(
            $StorageNode
            ->get('ip')
        );
        $img = $Image
            ->get('path');
        $imgFormat = $Image
            ->get('format');
        $imgType = $Image
            ->getImageType()
            ->get('type');
        $imgPartitionType = $Image
            ->getImagePartitionType()
            ->get('type');
        $imgid = $Image
            ->get('id');
        $PIGZ_COMP = $Image
            ->get('compress');
        $shutdown = intval(
            (bool)$Task
            ->get('shutdown')
        );
        $find = [
            'name' => [
                'FOG_CAPTUREIGNOREPAGEHIBER',
                'FOG_CAPTURERESIZEPCT',
                'FOG_CHANGE_HOSTNAME_EARLY',
                'FOG_TFTP_HOST'
            ]
        ];
        Route::ids(
            'setting',
            $find,
            'value'
        );
        $items = json_decode(
            Route::getData()
        );
        $values = [];
        foreach ($items as $item) {
            $values[] = $item->value;
        }
        list(
            $ignorepg,
            $pct,
            $hostearly,
            $ftp
        ) = $values;
        $ftp = (
            $StorageNode->isValid() ?
            $StorageNode->get('ip') :
            $ftp
        );
        if (!$pct < 100
            && !$pct > 4
        ) {
            $pct = 5;
        }
        if ($TaskType->get('id') === 11) {
            $winuser = $Task
                ->get('passreset');
        }
    }
    $fdrive = FOGCore::$Host
        ->get('kernelDevice');
    $Inventory = FOGCore::$Host
        ->get('inventory');
    $mac = $_REQUEST['mac'];
    $MACs = FOGCore::$Host
        ->getMyMacs();
    $clientMacs = array_filter(
        (array)FOGCore::parseMacList(
            implode('|', (array)$MACs),
            false,
            true
        )
    );
    $pass = FOGCore::$Host->get('ADPass');
    $passtest = FOGCore::aesdecrypt($pass);
    if ($test_base64 = base64_decode($passtest)) {
        if (mb_detect_encoding($test_base64, 'utf-8', true)) {
            $pass = $test_base64;
        }
    } elseif (mb_detect_encoding($passtest, 'utf-8', true)) {
        $pass = $passtest;
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
    $repFields = [
        // Imaging items to set
        'mac' => $mac,
        'ftp' => $ftp,
        'osid' => $osid,
        'storage' => $storage,
        'storageip' => $storageip,
        'img' => $img,
        'imgFormat' => $imgFormat,
        'imgType' => $imgType,
        'imgPartitionType' => $imgPartitionType,
        'imgid' => $imgid,
        'PIGZ_COMP' => sprintf(
            '-%s',
            $PIGZ_COMP
        ),
        'shutdown' => $shutdown,
        'hostearly' => $hostearly,
        'pct' => $pct,
        'ignorepg' => $ignorepg,
        'winuser' => $winuser,
        // Really only needed for multicast
        'port' => $port,
        // Implicit device to use
        'fdrive' => $fdrive,
        // Exposed other elements
        'hostname' => FOGCore::$Host->get('name'),
        'hostdesc' => FOGCore::$Host->get('description'),
        'hostip' => FOGCore::$Host->get('ip'),
        'hostimageid' => FOGCore::$Host->get('imageID'),
        'hostbuilding' => FOGCore::$Host->get('building'),
        'hostusead' => FOGCore::$Host->get('useAD'),
        'hostaddomain' => FOGCore::$Host->get('ADDomain'),
        'hostaduser' => FOGCore::$Host->get('ADUser'),
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
    $TaskArgs = preg_split(
        '#[\s]+#',
        trim($TaskType->get('kernelArgs'))
    );
    foreach ((array)$TaskArgs as $key => $val) {
        $val = trim($val);
        if (strpos($val, '=') === false) {
            continue;
        }
        $nums = explode('=', $val);
        if (count($nums) > 0) {
            $repFields[$nums[0]] = $nums[1];
        }
    }
    $HookManager->processEvent(
        'HOST_INFO_EXPOSE',
        [
            'repFields' => &$repFields,
            'Host'=>&FOGCore::$Host
        ]
    );
    foreach ((array)$repFields as $key => $val) {
        printf(
            "[[ -z $%s ]] && export %s=%s\n",
            $key,
            $key,
            escapeshellarg($val)
        );
    }
    // Unset lock and update token
    $new_tok = FOGCore::createSecToken();
    FOGCore::$Host->getManager()->update(
        ['id' => FOGCore::$Host->get('id')],
        '',
        [
            'token' => $new_tok,
            'tokenlock' => false
        ]
    );
} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}
