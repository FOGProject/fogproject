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
FOGCore::checkAuthAndCSRF();
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
$imagePaths = Route::getIds('storagenode', [], 'path');
$snapinPaths = Route::getIds('storagenode', [], 'snapinpath');
// The same list StorageNode::_getData() asks with. It has to be, or a path it
// requests is dropped here with no error -- which is why it is no longer a
// second copy.
$validPaths = FOGLogPaths::directories();
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
