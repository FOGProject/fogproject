<?php
/**
 * What FOG has decided about one file in the FOS boot directory.
 *
 * PHP version 7.4+
 *
 * @category BootFile
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Items;

use FOG\Base\FOGController;

/**
 * What FOG has decided about one file in the FOS boot directory.
 *
 * The filesystem remains the inventory -- whether a file exists, how big it
 * is and when it changed are read live on every listing, so a kernel copied
 * in by hand appears and one deleted by hand disappears, with nothing to
 * reconcile. A row here records only what the directory cannot say: the role
 * read out of the bytes, the version banner, the FOS release, and whether an
 * admin has pinned the file against pruning.
 *
 * `size` and `mtime` are the cache key rather than the inventory. A file
 * whose stat has moved is re-read; one whose stat matches is trusted.
 *
 * @category BootFile
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class BootFile extends FOGController
{
    /**
     * The table name.
     *
     * @var string
     */
    protected $databaseTable = 'bootFile';
    /**
     * The table fields.
     *
     * @var array
     */
    protected $databaseFields = [
        'id' => 'bfID',
        'name' => 'bfName',
        'size' => 'bfSize',
        'mtime' => 'bfMtime',
        'checksum' => 'bfChecksum',
        'role' => 'bfRole',
        'kernelVersion' => 'bfKernelVersion',
        'releaseTag' => 'bfReleaseTag',
        'inspected' => 'bfInspected',
        'pinned' => 'bfPinned'
    ];
    /**
     * The required fields.
     *
     * @var array
     */
    protected $databaseFieldsRequired = [
        'name'
    ];
}
