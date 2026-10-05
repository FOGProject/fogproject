<?php
/**
 * FOGSSH::size() totals a remote image over SFTP, and TaskQueue records it at
 * capture for every image format.
 *
 * #1814: a fresh capture showed "On Server Size 0.00 iB". The capture move set
 * srvsize only for format 1 (partimage), and measured it with a local
 * getFilesize() on a path that lives on the storage node. Every other format
 * stayed 0 until the next FOGImageSize pass, up to an hour later.
 *
 * No ssh2 extension needed: exists() and scanFilesystem() are ordinary
 * methods, and a method a subclass defines wins over FOGSSH::__call, so
 * sftp_stat can be stubbed too.
 *
 * Usage: php tests/fogssh-size.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('fogssh-size');

$t = new FogChecks();

/**
 * A fake remote filesystem: path => size, or 'dir'.
 */
class FakeSizeFs extends \FOG\Net\FOGSSH
{
    /** @var array path => int size | 'dir' */
    public $tree = [];

    public function exists($path)
    {
        return isset($this->tree[$path]);
    }

    public function scanFilesystem($remote_file)
    {
        if ('dir' !== ($this->tree[$remote_file] ?? '')) {
            return [];
        }
        $prefix = rtrim($remote_file, '/') . '/';
        $found = [];
        foreach ($this->tree as $path => $type) {
            if ('dir' !== $type && 0 === strpos($path, $prefix)) {
                $found[] = $path;
            }
        }

        return $found;
    }

    public function sftp_stat($path)
    {
        $v = $this->tree[$path] ?? null;

        return is_int($v) ? ['size' => $v] : false;
    }
}

/*
 * 1. An image directory: the sum of its files.
 */
$fs = new FakeSizeFs();
$fs->tree = [
    '/images/win11' => 'dir',
    '/images/win11/d1.mbr' => 512,
    '/images/win11/d1.partitions' => 1024,
    '/images/win11/d1p1.img' => 104857600,
    '/images/win11/d1p3.img' => 27826392000,
];
$t->check(
    'an image directory totals its files',
    512 + 1024 + 104857600 + 27826392000 === $fs->size('/images/win11')
);

/*
 * 2. A single file answers its own size.
 */
$fs = new FakeSizeFs();
$fs->tree = ['/images/legacy.img' => 4096];
$t->check(
    'a plain file answers its own size',
    4096 === $fs->size('/images/legacy.img')
);

/*
 * 3. A missing path is 0, not false and not an error.
 */
$fs = new FakeSizeFs();
$t->check(
    'a missing path answers 0',
    0 === $fs->size('/images/never-existed')
);

/*
 * 4. THE REPORTED CASE, at the call site. The capture move must record
 *    srvsize for every format, not only inside the format-1 branch.
 */
$src = file_get_contents(
    dirname(__DIR__) . '/packages/web/src/TaskHandling/TaskQueue.php'
);
$t->check(
    'TaskQueue records srvsize from the node, outside the format-1 branch',
    1 === preg_match(
        "/->set\('srvsize', self::\\\$FOGSSH->size\(\\\$dest\)\);\s*"
        . "self::\\\$FOGSSH->disconnect\(\);/",
        $src
    )
);

$t->finish();
