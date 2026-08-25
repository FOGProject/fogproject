<?php
/**
 * FOGSSH::delete() always terminates, and says whether it worked.
 *
 * The old shape ended with a bare `$this->delete($path)` after emptying the
 * directory -- an unconditional self-call with identical arguments and no test
 * for whether anything had changed. For a path that exists but CANNOT be
 * removed, every pass repeated itself exactly: sftp_rmdir failed, sftp_unlink
 * failed, scanFilesystem() returned [] (it lists files, and a plain file is not
 * a directory), and delete() called itself again. No termination condition at
 * all -- it ran until PHP exhausted memory_limit.
 *
 * Observed in the field as:
 *
 *   PHP Fatal error: Allowed memory size of 268435456 bytes exhausted
 *   in lib/fog/fogssh.class.php on line 336
 *
 * once per FOS retry. A memory-exhaustion fatal is not catchable, so
 * TaskQueue::checkout()'s catch never ran, the response body was empty, and FOS
 * -- which waits for '##' -- printed "Error returned:" with nothing after it
 * until it gave up. The capture had already been renamed into place; the task
 * never reached Complete. Reached because `<image>.movetmp` keeps the ownership
 * of whatever it was renamed from, and a root-owned 0755 directory cannot be
 * emptied by the storage node's SSH user.
 *
 * The runaway is what this file pins, so the stub COUNTS the passes and throws
 * once they exceed a small bound. A regression therefore fails this test in
 * milliseconds instead of reproducing the out-of-memory kill inside CI.
 *
 * No ssh2 extension needed: FOGSSH has no constructor, sftp_rmdir/sftp_unlink
 * reach the real class only through __call, and exists()/scanFilesystem() are
 * ordinary methods -- so a subclass can stand in for the filesystem entirely.
 *
 * Usage: php tests/fogssh-delete-terminates.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('fogssh-delete');

$t = new FogChecks();

/**
 * A fake remote filesystem with per-path removal permission.
 */
class FakeSshFs extends \FOG\FOGSSH
{
    /** @var array path => 'file'|'dir' */
    public $tree = [];

    /** @var array paths whose removal always fails, whatever they are */
    public $refuse = [];

    /** @var int how many times delete() has looked at anything */
    public $passes = 0;

    /** @var int the bound; past this the stub assumes a runaway */
    public $bound = 40;

    public function exists($path)
    {
        // delete() calls this first on every pass, so it is the natural place
        // to notice that we are going around in circles.
        if (++$this->passes > $this->bound) {
            throw new \RuntimeException(
                'runaway: delete() made more than ' . $this->bound . ' passes'
            );
        }

        return isset($this->tree[$path]);
    }

    public function scanFilesystem($remote_file)
    {
        if ('dir' !== ($this->tree[$remote_file] ?? '')) {
            return [];
        }
        // Files only, matching the real method -- which is why a tree with
        // nested directories cannot be emptied by delete() at all.
        $prefix = rtrim($remote_file, '/') . '/';
        $found = [];
        foreach ($this->tree as $path => $type) {
            if ($path === $remote_file || 'file' !== $type) {
                continue;
            }
            if (0 === strpos($path, $prefix)) {
                $found[] = $path;
            }
        }

        return $found;
    }

    public function sftp_rmdir($path)
    {
        if (in_array($path, $this->refuse, true)
            || 'dir' !== ($this->tree[$path] ?? '')
            || count($this->scanFilesystem($path))
        ) {
            return false;
        }
        unset($this->tree[$path]);

        return true;
    }

    public function sftp_unlink($path)
    {
        if (in_array($path, $this->refuse, true)
            || 'file' !== ($this->tree[$path] ?? '')
        ) {
            return false;
        }
        unset($this->tree[$path]);

        return true;
    }
}

/**
 * Runs one case, turning a runaway into a failure rather than a hang.
 *
 * @param object $fs   the fake filesystem
 * @param string $path what to delete
 *
 * @return array [bool|null $answer, string $error]
 */
function tryDelete($fs, $path)
{
    try {
        return [$fs->delete($path), ''];
    } catch (\RuntimeException $e) {
        return [null, $e->getMessage()];
    }
}

/*
 * 1. THE REPORTED CASE. A root-owned directory the SSH user cannot empty:
 *    neither the directory nor anything in it can be removed.
 */
$fs = new FakeSshFs();
$fs->tree = [
    '/images/Base-Dev.movetmp' => 'dir',
    '/images/Base-Dev.movetmp/d1.partitions' => 'file',
    '/images/Base-Dev.movetmp/d1.original.fstypes' => 'file',
];
$fs->refuse = array_keys($fs->tree);

list($answer, $error) = tryDelete($fs, '/images/Base-Dev.movetmp');
$t->check(
    'an unremovable directory terminates instead of recursing',
    '' === $error
);
$t->check(
    'and it answers false rather than claiming success',
    false === $answer
);

/*
 * 2. A single unremovable FILE. This is the tighter version of the same
 *    runaway: scanFilesystem() returns [] for a file, so the old code had
 *    nothing at all to make progress with and simply called itself.
 */
$fs = new FakeSshFs();
$fs->tree = ['/images/locked' => 'file'];
$fs->refuse = ['/images/locked'];

list($answer, $error) = tryDelete($fs, '/images/locked');
$t->check(
    'an unremovable file terminates',
    '' === $error
);
$t->check(
    'an unremovable file answers false',
    false === $answer
);

/*
 * 3. The case that has to keep working: a populated directory the user CAN
 *    empty. rmdir fails first (not empty), the contents go, and the retry
 *    succeeds.
 */
$fs = new FakeSshFs();
$fs->tree = [
    '/images/Base-Dev.movetmp' => 'dir',
    '/images/Base-Dev.movetmp/d1.partitions' => 'file',
    '/images/Base-Dev.movetmp/d1.img' => 'file',
];

list($answer, $error) = tryDelete($fs, '/images/Base-Dev.movetmp');
$t->check(
    'a removable directory still terminates',
    '' === $error
);
$t->check(
    'a removable directory is removed and answers true',
    true === $answer
);
$t->check(
    'and nothing is left behind',
    [] === $fs->tree
);

/*
 * 4. One unremovable file inside an otherwise removable directory. The
 *    directory must not be reported as deleted, and the pass count must stay
 *    bounded even though part of the work succeeded.
 */
$fs = new FakeSshFs();
$fs->tree = [
    '/images/part' => 'dir',
    '/images/part/ok.img' => 'file',
    '/images/part/locked.img' => 'file',
];
$fs->refuse = ['/images/part/locked.img'];

list($answer, $error) = tryDelete($fs, '/images/part');
$t->check(
    'a partially removable directory terminates',
    '' === $error
);
$t->check(
    'a partially removable directory answers false',
    false === $answer
);
$t->check(
    'the file that could be removed still was',
    !isset($fs->tree['/images/part/ok.img'])
);

/*
 * 5. Nothing to do. A path that is not there is not a failure -- the caller
 *    wanted it gone and it is gone.
 */
$fs = new FakeSshFs();
list($answer, $error) = tryDelete($fs, '/images/never-existed');
$t->check(
    'a missing path answers true without touching anything',
    '' === $error && true === $answer
);

/*
 * 6. The return type itself. filedeleter.class.php tests
 *    `!self::$FOGSSH->delete($deleteFile)` to decide whether to report a failed
 *    removal; while delete() returned $this that branch could never run, so
 *    every failure was reported as a success. Pinned here because it is a
 *    contract between two files.
 */
$fs = new FakeSshFs();
$fs->tree = ['/images/locked' => 'file'];
$fs->refuse = ['/images/locked'];
list($answer, $error) = tryDelete($fs, '/images/locked');
$t->check(
    'delete() returns a bool, not a fluent $this',
    '' === $error && is_bool($answer)
);

$t->finish();
