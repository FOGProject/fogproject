<?php
/**
 * Get's files stored as requested
 *
 * PHP version 7.4+
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
 * PHP version 7.4+
 *
 * @category Getfiles
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
/*
 * Two callers, two credentials.
 *
 * A browser reaches this with a session, and nothing about that changes.
 * FOG's own components reach it with no session at all -- StorageNode's
 * image/snapin listings are built by CLI daemons and by API requests
 * authenticated with a token -- and until GH-1312 there was no credential
 * they could present. They got a 401, _getData() read that as an empty
 * response, and every such caller was silently handed an empty file list.
 *
 * The signature is checked first because it is the cheaper answer and
 * because is_authorized() is what would otherwise redirect a machine caller
 * to a sign-in page. It authenticates only -- it says the caller belongs to
 * this installation. What it is allowed to read is still decided below, by
 * the same $validPaths list that has always bounded this endpoint.
 *
 * No CSRF check on the signed path: CSRF defends a browser being made to
 * act with its ambient cookie, and there is no cookie here. The signature
 * has to be computed, which is the thing a cross-site request cannot do.
 */
if (!FOGCore::validNodeSignature()) {
    FOGCore::checkAuthAndCSRF();
}
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
