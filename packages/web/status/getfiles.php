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
$decodedPath = urldecode($_REQUEST['path']);
$paths = explode(':', $decodedPath);
foreach ((array)$paths as &$decodedPath) {
    if (!(is_dir($decodedPath)
        && file_exists($decodedPath)
        && is_readable($decodedPath))
    ) {
        $files[] = json_encode(_('Path is unavailable'));
        continue;
    }
    $replaced_dir_sep = preg_replace(
        '#[\\/]#',
        DIRECTORY_SEPARATOR,
        $decodedPath
    );
    $glob_str = sprintf(
        '%s%s*',
        $replaced_dir_sep,
        DIRECTORY_SEPARATOR
    );
    $files = FOGCore::fastmerge(
        (array) $files,
        (array) glob($glob_str)
    );
}
echo json_encode($files);
exit;
