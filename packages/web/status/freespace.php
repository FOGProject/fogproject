<?php
/**
 * Gets free space of disk/partition holding images from server
 *
 * PHP version 5
 *
 * @category Freespace
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
session_write_close();
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
$decodePath = base64_decode($_REQUEST['path']);
if (!(file_exists($decodePath) && is_readable($decodePath))) {
    return;
}
$hdtotal = disk_total_space($decodePath);
$hdfree = disk_free_space($decodePath);
$hdused = $hdtotal - $hdfree;
$data = array(
    'free' => $hdfree,
    'used' => $hdused,
);
echo json_encode($data);
exit;
