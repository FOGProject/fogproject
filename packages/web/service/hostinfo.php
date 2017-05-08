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
    $Host = FOGCore::getHostItem(false);
    $Task = $Host->get('task');
    if (FOGCore::$useragent) {
        throw new Exception(_('Cannot view from browser'));
    }
    if (!$Task->isValid()) {
        throw new Exception(_('Invalid tasking!'));
    }
    $TaskType = FOGCore::getClass(
        'TaskType',
        $Task->get('typeID')
    );
    $Image = $Task->getImage();
    if ($TaskType->isInitNeededTasking()) {
        if ($TaskType->isMulticast()) {
            $MulticastSession = FOGCore::getClass(
                'MulticastSession',
                @max(
                    FOGCore::getSubObjectIDs(
                        'MulticastSessionAssociation',
                        array('taskID' => $Task->get('id'))
                    )
                )
            );
            $taskImgID = $Task->get('imageID');
            $mcImgID = $MulticastSession->get('image');
            if ($taskImgID != $mcImgID) {
                $Task
                    ->set('imageID', $mcImgID)
                    ->save();
                $Host
                    ->set('imageID', $mcImgID);
                $Image = new Image($mcImgID);
            }
            $port = $MulticastSession->get('port');
        }
        $StorageGroup = $StorageNode = null;
        $HookManager->processEvent(
            'BOOT_TASK_NEW_SETTINGS',
            array(
                'Host' => &$Host,
                'StorageNode' => &$StorageNode,
                'StorageGroup' => &$StorageGroup
            )
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
        list(
            $ignorepg,
            $pct,
            $hostearly,
            $ftp
        ) = FOGCore::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_CAPTUREIGNOREPAGEHIBER',
                    'FOG_CAPTURERESIZEPCT',
                    'FOG_CHANGE_HOSTNAME_EARLY',
                    'FOG_TFTP_HOST'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
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
    $fdrive = $Host
        ->get('kernelDevice');
    $Inventory = $Host
        ->get('inventory');
    $mac = $_REQUEST['mac'];
    $MACs = $Host
        ->getMyMacs();
    $clientMacs = array_filter(
        (array)FOGCore::parseMacList(
            implode('|', (array)$MACs),
            false,
            true
        )
    );
    $repFields = array(
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
        'hostname' => $Host->get('name'),
        'hostdesc' => $Host->get('description'),
        'hostip' => $Host->get('ip'),
        'hostimageid' => $Host->get('imageID'),
        'hostbuilding' => $Host->get('building'),
        'hostusead' => $Host->get('useAD'),
        'hostaddomain' => $Host->get('ADDomain'),
        'hostaduser' => $Host->get('ADUser'),
        'hostadpass' => trim(FOGCore::aesdecrypt($Host->get('ADPass'))),
        'hostadou' => str_replace(';', '', $Host->get('ADOU')),
        'hostproductkey' => trim(FOGCore::aesdecrypt($Host->get('productKey'))),
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
    );
    $TaskArgs = preg_split(
        '#[\s]+#',
        trim($TaskType->get('kernelArgs'))
    );
    foreach ((array)$TaskArgs as $key => &$val) {
        $val = trim($val);
        if (strpos($val, '=') === false) {
            continue;
        }
        $nums = explode('=', $val);
        if (count($nums) > 0) {
            $repFields[$nums[0]] = $nums[1];
        }
        unset($val);
    }
    $HookManager->processEvent(
        'HOST_INFO_EXPOSE',
        array(
            'repFields' => &$repFields,
            'Host'=>&$Host
        )
    );
    foreach ((array)$repFields as $key => &$val) {
        printf(
            "[[ -z $%s ]] && export %s=%s\n",
            $key,
            $key,
            escapeshellarg($val)
        );
        unset($val);
    }
} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}
