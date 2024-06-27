<?php
/**
 * Get's files stored as requested
 *
 * PHP version 5
 *
 * @category Getfiles
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Get's files stored as requested
 *
 * PHP version 5
 *
 * @category Getfiles
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
$path = filter_input(INPUT_GET, 'path');
if (!is_string($path)) {
    echo json_encode(
        _('Invalid')
    );
    exit;
}
$decodePath = urldecode(
    Initiator::sanitizeItems(
        $path
    )
);
Route::ids('storagenode', [], 'path');
$imagePaths = json_decode(Route::getData(), true);
Route::ids('storagenode', [], 'snapinpath');
$snapinPaths = json_decode(Route::getData(), true);
$validPaths = [
    '/var/log/apache2',
    '/var/log/fog',
    '/var/log/httpd',
    '/var/log/nginx',
    '/var/log/php*'
];
$validPaths = array_merge(
    $imagePaths,
    $snapinPaths,
    $validPaths
);
$paths = explode(':', $decodePath);
$realpaths = [];
foreach ((array)$paths as $decodedPath) {
    $pathTest = preg_grep('#' . $decodedPath . '#', $validPaths);
    if (count($pathTest ?: []) < 1) {
        continue;
    }
    foreach ($pathTest as $path) {
        $realpaths = FOGCore::fastmerge(
            (array)$realpaths,
            glob($path)
        );
    }
}
$files = [];
foreach ($realpaths as $path) {
    if (!(is_dir($path)
        && file_exists($path)
        && is_readable($path))
    ) {
        continue;
    }
    $replaced_dir_sep = str_replace(
        ['\\', '/'],
        [DS, DS],
        $path
    );
    $glob_str = sprintf(
        '%s%s*',
        $replaced_dir_sep,
        DS
    );
    $files = FOGCore::fastmerge(
        (array)$files,
        (array)glob($glob_str)
    );
}
echo json_encode(
    Initiator::sanitizeItems(
        $files
    )
);
exit;
