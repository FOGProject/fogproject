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
require '../commons/base.inc.php';
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: application/json');

// Allow local authenticated users and trusted node-to-node requests.
$isAuthorizedUser = FOGCore::is_authorized(true);
$remoteIP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$isTrustedCaller = FOGCore::isTrustedNodeIp($remoteIP);
if (!$isAuthorizedUser && !$isTrustedCaller) {
    http_response_code(HTTPResponseCodes::HTTP_UNAUTHORIZED);
    echo json_encode(
        [
            'free' => 0,
            'used' => 0,
            'error' => _('Unauthorized'),
            'title' => _('Access Denied')
        ]
    );
    exit;
}
$decodePath = filter_input(INPUT_GET, 'path');
$path = base64_decode((string)$decodePath, true);
if (!$path) {
    $error = _('Invalid path');
    $title = _('Invalid Path');
    http_response_code(400);
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
$path = realpath($path);
if (!(is_string($path) && is_dir($path) && is_readable($path))) {
    $error = _('File or path does not exist');
    $title = _('File Not Found');
    http_response_code(401);
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

Route::ids('storagenode', [], 'path');
$imagePaths = json_decode(Route::getData(), true) ?: [];
Route::ids('storagenode', [], 'snapinpath');
$snapinPaths = json_decode(Route::getData(), true) ?: [];

$validPaths = array_merge(
    (array)$imagePaths,
    (array)$snapinPaths
);
$realValidPaths = [];
foreach ((array)$validPaths as $validPath) {
    foreach ((array)glob($validPath) as $expandedPath) {
        $realPath = realpath($expandedPath);
        if (!is_string($realPath) || !is_dir($realPath)) {
            continue;
        }
        $realValidPaths[] = rtrim($realPath, DS) . DS;
    }
}

$allowed = false;
$comparePath = rtrim($path, DS) . DS;
foreach (array_unique($realValidPaths) as $realValidPath) {
    if (strpos($comparePath, $realValidPath) === 0) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    $error = _('Path not allowed');
    $title = _('Access Denied');
    http_response_code(403);
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
    http_response_code(204);
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
http_response_code(200);
echo json_encode(
    [
        'free' => (int)$match[2],
        'used' => (int)$match[1]
    ]
);
exit;
