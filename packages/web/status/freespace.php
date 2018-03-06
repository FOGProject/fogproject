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
    $error = _('File or path does not exist');
    $title = _('File Not Found');
    http_response_code(404);
    echo json_encode(
        [
            'free' => 0,
            'used' => 0,
            'error' => $error,
            'title' => $title
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
    http_response_code(201);
    $error = _('No data found');
    $title = _('No Data Available');
    echo json_encode(
        [
            'free' => 0,
            'used' => 0,
            'error' => $error,
            'title' => $title
        ]
    );
    exit;
}
http_response_code(201);
echo json_encode(
    [
        'free' => (int)$match[2],
        'used' => (int)$match[1]
    ]
);
exit;
