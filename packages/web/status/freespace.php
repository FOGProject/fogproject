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
/**
 * Gets free space of disk/partition holding images from server
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
header('Content-Type: application/json');
$decodePath = filter_input(INPUT_GET, 'path');
$path = base64_decode($decodePath);
if (!(file_exists($path) && is_dir($path) && is_readable($path))) {
    echo json_encode(
        [
            'error' => _('File or path does not exist')
        ]
    );
    exit;
}
$folder = escapeshellarg($path);
$output = `df -PB1 $folder | tail -1`;
$test = preg_match(
    '/\d+\s+(\d+)\s+(\d+)\s+\d+\%.*$/',
    $output,
    $match
);
if (!$test) {
    echo json_encode(
        [
            'error' => _('No data returned')
        ]
    );
    exit;
}
$hdfree = $match[2];
$hdused = $match[1];
$data = [
    'free' => $hdfree,
    'used' => $hdused,
];
echo json_encode($data);
exit;
