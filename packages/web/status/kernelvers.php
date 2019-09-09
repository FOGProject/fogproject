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
$url = filter_input(INPUT_GET, 'url');
if (!isset($_POST['ko']) && (empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')
) {
    echo _('Unauthorized');
    exit;
}
if ($url) {
    $res = $FOGURLRequests
        ->process($url);
    foreach ((array)$res as &$response) {
        echo $response;
        unset($response);
    }
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
    $basepath = escapeshellarg($currpath);
    $findstr = sprintf(
        'strings %s | grep -A1 "%s:" | tail -1 | awk \'{print $1}\'',
        $basepath,
        'Undefined video mode number'
    );
    return shell_exec($findstr);
};
if (isset($_POST['ko'])) {
    echo '<div class="box box-primary">';
    echo '<div class="box-header with-border">';
    echo '<h4 class="box-title">';
    echo _('bzImage - 64 bit');
    echo '</h4>';
    echo '</div>';
    echo '<div class="box-body">';
    echo $kernelvers('bzImage');
    echo '</div>';
    echo '</div>';
    echo '<div class="box box-primary">';
    echo '<div class="box-header with-border">';
    echo '<h4 class="box-title">';
    echo _('bzImage - 32 bit');
    echo '</h4>';
    echo '</div>';
    echo '<div class="box-body">';
    echo $kernelvers('bzImage32');
    echo '</div>';
    echo '</div>';
    exit;
}
printf(
    "%s\n",
    FOG_VERSION
);
printf(
    "bzImage Version: %s\n",
    $kernelvers('bzImage')
);
printf(
    "bzImage32 Version: %s",
    $kernelvers('bzImage32')
);
