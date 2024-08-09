<?php
/**
 * Presents the FOG Kernels version that the clients will use.
 *
 * PHP version 5
 *
 * @category KernelVersion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents the FOG Kernels version that the clients will use.
 *
 * @category KernelVersion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
$url = filter_input(INPUT_POST, 'url');
$userID = filter_input(INPUT_POST, 'fog_user');
parse_str(
    $userID,
    $items
);
if (
    !$currentUser->isValid() &&
    !isset($_POST['ko']) &&
    (empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest')
) {
    echo _('Unauthorized');
    exit;
}
$initrdvers = function ($initrd) {
    $currpath = sprintf(
        '%s%sservice%sipxe%s%s',
        BASEPATH,
        DS,
        DS,
        DS,
        $initrd
    );
    if (!file_exists($currpath)) {
        return _('Not Installed').'|'._('Not Installed').'|'._('Not Installed');
    }
    $basepath = escapeshellarg($currpath);
    $findstr = sprintf(
        'attr -g tag_name %s | tail -n1',
        $basepath
    );
    $tag_name = shell_exec($findstr);
    $findstr = sprintf(
        'attr -g version %s | tail -n1',
        $basepath
    );
    $buildroot = shell_exec($findstr);
    $stat = stat($currpath);
    $c_time = $stat['ctime'];

    $tag = trim(trim(trim($tag_name), '"'));
    if (!$tag) {
        $tag = _('Unknown');
    }
    $build = trim(trim(trim($buildroot), '"'));
    if (!$build) {
        $build = _('Unknown');
    }
    $create = date('Y-m-d H:i:s', $c_time);

    return "$tag|$build|$create";
};
if (isset($_POST['ko'])) {
    echo '<div class="box box-primary">';
    echo '<div class="box-header with-border">';
    echo '<h4 class="box-title">';
    echo _('Node Version');
    echo '</h4>';
    echo '</div>';
    echo '<div class="box-body">';
    echo FOG_VERSION;
    echo '</div>';
    echo '</div>';
    echo '<div class="box box-primary">';
    echo '<div class="box-header with-border">';
    echo '<h4 class="box-title">';
    echo _('Kernel Versions');
    echo '</h4>';
    echo '</div>';
    list($int64_rel, $int64_ver, $int64_ins) = explode('|', $initrdvers('bzImage'));
    list($int32_rel, $int32_ver, $int32_ins) = explode('|', $initrdvers('bzImage32'));
    list($arm64_rel, $arm64_ver, $arm64_ins) = explode('|', $initrdvers('arm_Image'));
    echo '<div class="box-body">';
    echo '<table class="table table-striped">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th>'._('Architecture').'</th>';
    echo '<th>'._('Release Version').'</th>';
    echo '<th>'._('Kernel Version').'</th>';
    echo '<th>'._('Installed Date').'</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>'. _('Intel 64 Bit'). '</td>';
    echo '<td>'. $int64_rel . '</td>';
    echo '<td>'. $int64_ver . '</td>';
    echo '<td>'. $int64_ins . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>'. _('Intel 32 Bit'). '</td>';
    echo '<td>'. $int32_rel . '</td>';
    echo '<td>'. $int32_ver . '</td>';
    echo '<td>'. $int32_ins . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>'. _('ARM 64 Bit'). '</td>';
    echo '<td>'. $arm64_rel . '</td>';
    echo '<td>'. $arm64_ver . '</td>';
    echo '<td>'. $arm64_ins . '</td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    list($int64_rel, $int64_brt, $int64_ins) = explode('|', $initrdvers('init.xz'));
    list($int32_rel, $int32_brt, $int32_ins) = explode('|', $initrdvers('init_32.xz'));
    list($arm64_rel, $arm64_brt, $arm64_ins) = explode('|', $initrdvers('arm_init.cpio.gz'));
    echo '<div class="box box-primary">';
    echo '<div class="box-header with-border">';
    echo '<h4 class="box-title">';
    echo _('InitRD Versions');
    echo '</h4>';
    echo '</div>';
    echo '<div class="box-body">';
    echo '<table class="table table-striped">';
    echo '<tbody>';
    echo '<tr>';
    echo '<th>'._('Architecture').'</th>';
    echo '<th>'._('Release Version').'</th>';
    echo '<th>'._('Buildroot Version').'</th>';
    echo '<th>'._('Installed Date').'</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>'. _('Intel 64 Bit') . '</td>';
    echo '<td>'. $int64_rel . '</td>';
    echo '<td>'. $int64_brt . '</td>';
    echo '<td>'. $int64_ins . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>'. _('Intel 32 Bit') . '</td>';
    echo '<td>'. $int32_rel . '</td>';
    echo '<td>'. $int32_brt . '</td>';
    echo '<td>'. $int32_ins . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>'. _('ARM 64 Bit') . '</td>';
    echo '<td>'. $arm64_rel . '</td>';
    echo '<td>'. $arm64_brt . '</td>';
    echo '<td>'. $arm64_ins . '</td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    exit;
}
$send_vars = [
    'node_vers' => FOG_VERSION,
    'node_version_lang' => _('Node Version'),
    'kern_version_lang' => _('Kernel Versions'),
    'init_version_lang' => _('InitRD Versions'),
    'arch_lang' => _('Architecture'),
    'kern_lang' => _('Kernel Version'),
    'build_lang' => _('Buildroot Version'),
    'rel_lang' => _('Release Version'),
    'ins_lang' => _('Installed Date'),
    'intel64_lang' => _('Intel 64 Bit'),
    'intel32_lang' => _('Intel 32 Bit'),
    'arm64_lang' => _('ARM 64 Bit'),
    'int64bit' => $initrdvers('bzImage'),
    'int32bit' => $initrdvers('bzImage32'),
    'arm64bit' => $initrdvers('arm_Image'),
    'initI64' => $initrdvers('init.xz'),
    'initI32' => $initrdvers('init_32.xz'),
    'initA64' => $initrdvers('arm_init.cpio.gz')
];
echo json_encode($send_vars);
exit;
