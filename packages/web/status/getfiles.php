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
Route::ids('storagenode',[], 'path');
$imagePaths = json_decode(Route::getData(), true);
Route::ids('storagenode',[], 'snapinpath');
$snapinPaths = json_decode(Route::getData(), true);
$validPaths = [
    '/var/log/nginx',
    '/var/log/httpd',
    '/var/log/apache2',
    '/var/log/php-fpm',
    '/var/log/php5-fpm',
    '/var/log/php5.6-fpm',
    '/var/log/php7-fpm',
    '/var/log/php7.0-fpm',
    '/var/log/php7.1-fpm',
    '/var/log/php7.2-fpm',
    '/var/log/php7.3-fpm'
];
$validPaths = array_merge(
    $imagePaths,
    $snapinPaths,
    $validPaths
);
$paths = explode(':', $decodePath);
foreach ((array)$paths as &$decodedPath) {
    $pathTest = preg_grep('#' . $decodedPath . '#', $validPaths);
    if (count($pathTest ?: []) < 1) {
        continue;
    }
    if (!(is_dir($decodedPath)
        && file_exists($decodedPath)
        && is_readable($decodedPath))
    ) {
        continue;
    }
    $replaced_dir_sep = str_replace(
        ['\\', '/'],
        [DS, DS],
        $decodedPath
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
