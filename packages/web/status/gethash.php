<?php
/**
 * Get's hash of file passed.
 *
 * PHP version 5
 *
 * @category Gethash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Get's hash of file passed.
 *
 * PHP version 5
 *
 * @category Gethash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
ignore_user_abort(true);
set_time_limit(0);
$file = filter_input(
    INPUT_POST,
    'file'
);
$file = base64_decode((string)$file, true);
if (!$file) {
    return '';
}
$file = realpath($file);
if (!is_string($file) || !is_file($file) || !is_readable($file)) {
    return '';
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
$compareFile = rtrim($file, DS) . DS;
foreach (array_unique($realValidPaths) as $realValidPath) {
    if (strpos($compareFile, $realValidPath) === 0) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    return '';
}

echo FOGCore::getHash($file);
exit;

