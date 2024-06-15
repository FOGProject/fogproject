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
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'XMLHttpRequest')
) {
    echo _('Unauthorized');
    exit;
}
$kernelvers = function ($kernel) {
    $currpath = sprintf(
        '%s%sservice%sipxe%s%s',
        BASEPATH,
        DS,
        DS,
        DS,
        $kernel
    );
    if (!file_exists($currpath)) {
        return _('Not Installed');
    }
    $basepath = escapeshellarg($currpath);
    $findstr = sprintf(
        'strings %s | grep -P "\d+\.\d+\.\d+ .*(SMP|aarch64)"'
        . '| tail -1 | awk \'{if ($0 ~ /^Linux version /) {print $3} else {print $1}}\'',
        $basepath
    );
    return trim(shell_exec($findstr));
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
    echo '<div class="box-body">';
    echo '<dl>';
    echo '<dt>Intel - 64 Bit</dt>';
    echo '<dd>' . $kernelvers('bzImage') . '</dd>';
    echo '<dt>Intel - 32 Bit</dt>';
    echo '<dd>' . $kernelvers('bzImage32') . '</dd>';
    echo '<dt>ARM - 64 Bit</dt>';
    echo '<dd>' . $kernelvers('arm_Image') . '</dd>';
    echo '</div>';
    echo '</div>';
    echo '<div class="box box-primary">';
    echo '<div class="box-header with-border">';
    echo '<h4 class="box-title">';
    echo _('InitRD Versions');
    echo '</h4>';
    echo '</div>';
    echo '<div class="box-body">';
    echo '<dl>';
    echo '<dt>64 Bit</dt>';
    echo '<dd>' . $kernelvers('bzImage') . '</dd>';
    echo '<dt>32 Bit</dt>';
    echo '<dd>' . $kernelvers('bzImage32') . '</dd>';
    echo '<dt>ARM 64 Bit</dt>';
    echo '<dd>' . $kernelvers('arm_Image') . '</dd>';
    echo '</div>';
    echo '</div>';
    exit;
}
$send_vars = [
    'node_vers' => FOG_VERSION,
    'node_version_lang' => _('Node Version'),
    'kern_version_lang' => _('Kernel Versions'),
    'init_version_lang' => _('InitRD Versions'),
    'int64bit' => $kernelvers('bzImage'),
    'int32bit' => $kernelvers('bzImage32'),
    'arm64bit' => $kernelvers('arm_Image')
];
echo json_encode($send_vars);
exit;
