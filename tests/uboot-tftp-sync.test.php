<?php
/**
 * UbootTftpSync: MAC-to-filename convention, the SSH connect/mkdir handshake,
 * the orphan-file safety pattern, and the call-site wiring.
 *
 * Two of this class's four pieces are pure and self-contained enough to
 * drive directly: _pxeFileNames() (the pxelinux.cfg/01-<mac> convention
 * itself -- get this wrong and every wget-less board silently gets no file
 * at all) and _connect() (the SSH handshake + mkdir-if-missing, reusing the
 * same FakeSshFs-subclassing technique tests/fogssh-delete-terminates.test.php
 * established for FOGSSH -- no ssh2 extension needed).
 *
 * The other two -- materializeMany()/removeMany()/reconcile()'s per-host
 * loop, and _syncOne()'s render -- go through `new Host($id)`, which in this
 * suite's DB-free harness means a real load() against FogFakeDb's synthesized
 * rows, and then UbootBootMenu::renderForHost(), which is the same heavy
 * render path tests/lib/bootmenu-harness.php exists to drive and is already
 * covered there (golden fixture + bootmenu-uboot-output.test.php). Rebuilding
 * that harness here would duplicate coverage that already exists elsewhere
 * for the render itself, so what is asserted instead is the orchestration
 * around it, the same way tests/tasklog-records-cancel.test.php pins
 * TaskManager::cancel()'s bulk-update ordering by reading the method body
 * rather than driving a bulk cancel through real Task objects.
 *
 * Usage: php tests/uboot-tftp-sync.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('uboot-tftp-sync');
$db = FogTestHarness::fakeDb();
// MACAddress::setMAC() logs a rejected mac through self::$FOGCore->debug();
// a real request populates that from LoadGlobals, which this DB-free
// bootstrap does not run.
FogTestHarness::setStatic('FOGBase', 'FOGCore', new \FOG\Base\FOGCore());

$t = new FogChecks();

$syncFile = dirname(__DIR__)
    . '/packages/web/src/Boot/UbootTftpSync.php';
$syncSource = file_get_contents($syncFile);

/*
 * _pxeFileNames(): the pxelinux.cfg/01-<mac> convention.
 *
 * A host with more than one NIC has to resolve to a filename per MAC, all
 * three input notations (colon, dash, bare hex) of the SAME mac have to
 * collapse to one filename, and anything MACAddress::isValid() rejects has
 * to be skipped rather than producing a broken filename.
 */
class MacListHost extends \FOG\Items\Host
{
    /** @var string[] */
    public $macs = [];

    public function __construct()
    {
        // Deliberately skips the parent constructor: the string-arg path
        // through FOGController::__construct() would try to set up
        // databaseFields bookkeeping. This class exists only to answer
        // getMyMacs() without a database, so a bare FOGBase-level
        // construction is all the private/reflected method under test needs.
    }

    public function getMyMacs($justme = true)
    {
        return $this->macs;
    }
}

$macHost = new MacListHost();
$macHost->macs = [
    'AA:BB:CC:DD:EE:FF',
    'aa-bb-cc-dd-ee-ff',
    '112233445566',
    'not-a-mac-address',
    'AA:BB:CC:DD:EE:FF',
];
$names = FogTestHarness::callStatic(
    'FOG\Boot\UbootTftpSync',
    '_pxeFileNames',
    [$macHost]
);
sort($names);

$t->check(
    'three distinct macs in five different spellings resolve to two names',
    2 === count($names)
);
$t->check(
    'the same mac in colon, dash, and repeated form collapses to one file',
    in_array('01-aa-bb-cc-dd-ee-ff', $names, true)
);
$t->check(
    'a second, distinct mac gets its own file',
    in_array('01-11-22-33-44-55-66', $names, true)
);
$t->check(
    'an invalid mac is skipped, not turned into a broken filename',
    !preg_grep('/not-a-mac/', $names)
);

$emptyHost = new MacListHost();
$emptyHost->macs = [];
$t->check(
    'a host with no macs resolves to no files',
    [] === FogTestHarness::callStatic(
        'FOG\Boot\UbootTftpSync',
        '_pxeFileNames',
        [$emptyHost]
    )
);

/*
 * NAME_PATTERN: the filter reconcile() trusts before it deletes anything.
 * Only ever touch a file this class's own convention produced.
 */
$t->check(
    'NAME_PATTERN matches this class\'s own filenames',
    (bool)preg_match(
        \FOG\Boot\UbootTftpSync::NAME_PATTERN,
        '01-aa-bb-cc-dd-ee-ff'
    )
);
foreach (
    [
        'uname is not a mac file' => 'uname',
        'an uppercase mac file is not matched (names are lowercased first)'
            => '01-AA-BB-CC-DD-EE-FF',
        'a kernel image is not matched' => 'vmlinuz',
        'a dotfile is not matched' => '.gitkeep',
        'a short mac is not matched' => '01-aa-bb-cc-dd-ee',
    ] as $label => $candidate
) {
    $t->check(
        $label,
        !preg_match(\FOG\Boot\UbootTftpSync::NAME_PATTERN, $candidate)
    );
}

/*
 * _connect(): the SSH handshake, the dir path built from
 * FOG_TFTP_ROOT_DIR, and mkdir-only-if-missing.
 *
 * No ssh2 extension needed -- same technique as
 * tests/fogssh-delete-terminates.test.php: FOGSSH has no constructor, and
 * every method this needs is declared directly (not reached through
 * FOGSSH::__call()), so a subclass stands in for the whole remote filesystem.
 */
class FakeTftpFs extends \FOG\Net\FOGSSH
{
    /**
     * Shadows the parent's __set()/__get()-backed $data array with real,
     * declared properties -- the assertions below read them straight back,
     * and phpstan-tests.neon (level 5) does not know the magic accessors'
     * shape without a @property annotation FOGSSH does not carry. UbootTftpSync
     * itself still writes through __set() when handed a real FOGSSH.
     *
     * @var string
     */
    public $host = '';

    /** @var string */
    public $username = '';

    /** @var string */
    public $password = '';

    /** @var array path => 'file'|'dir' */
    public $tree = [];

    /** @var array path => bytes, for 'file' entries */
    public $contents = [];

    /** @var bool what connect() succeeds or fails */
    public $connectSucceeds = true;

    /** @var int how many times connect() was called */
    public $connectCalls = 0;

    /** @var int how many times disconnect() was called */
    public $disconnectCalls = 0;

    /** @var string[] every path sftp_mkdir() was asked to create */
    public $mkdirCalls = [];

    /** @var array path => mode, every sftp_chmod() call */
    public $chmodCalls = [];

    /** @var string[] every remote path put() wrote, in order */
    public $putCalls = [];

    /**
     * Clears the put() log between staging calls. A method rather than a
     * direct `$fakeFs->putCalls = []` at the call site, because phpstan
     * narrows the property to array{} after that assignment and cannot see
     * through callStatic() to the writes that follow, so every comparison
     * after it reads as always-false.
     *
     * @return void
     */
    public function resetPuts()
    {
        $this->putCalls = [];
    }

    /**
     * ssh2_sftp_stat()'s shape for the one key the sync reads.
     *
     * @param string $path remote path
     *
     * @return array{size: int}|false
     */
    public function sftp_stat($path)
    {
        if (!isset($this->contents[$path])) {
            return false;
        }

        return ['size' => strlen($this->contents[$path])];
    }

    /**
     * Mirrors the real connect()'s actual contract (self|false), not its
     * @return object docblock -- the real method's own catch branch returns
     * false on failure, which is the falsy value UbootTftpSync::_connect()'s
     * `if (!self::$FOGSSH->connect())` depends on.
     *
     * @return self|false
     */
    public function connect(
        $host = '',
        $port = 0,
        $autologin = true,
        $connectmethod = 'ssh2_connect'
    ) {
        ++$this->connectCalls;
        if (!$this->connectSucceeds) {
            return false;
        }

        return $this;
    }

    public function disconnect()
    {
        ++$this->disconnectCalls;

        return true;
    }

    public function exists($path)
    {
        return isset($this->tree[$path]);
    }

    public function sftp_mkdir($path)
    {
        $this->mkdirCalls[] = $path;
        $this->tree[$path] = 'dir';

        return true;
    }

    public function put($localfile, $remotefile)
    {
        $this->putCalls[] = $remotefile;
        $this->tree[$remotefile] = 'file';
        $this->contents[$remotefile] = file_get_contents($localfile);
    }

    public function sftp_chmod($path, $mode)
    {
        $this->chmodCalls[$path] = $mode;

        return true;
    }

    public function unlinkFile($path)
    {
        unset($this->tree[$path], $this->contents[$path]);

        return true;
    }

    public function scanFilesystem($remote_file)
    {
        if ('dir' !== ($this->tree[$remote_file] ?? '')) {
            return [];
        }
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
}

// A real directory standing in for <webroot>/service/ipxe/: the path the
// pxelinux.cfg dir must NOT be built from (topic 18229), and the place
// _stageBootFiles() reads the kernel and init from.
$kernelDir = dirname(FOG_CACHE_DIR) . '/service/ipxe';
mkdir($kernelDir, 0755, true);
file_put_contents($kernelDir . '/arm_Image', str_repeat('K', 4096));
file_put_contents($kernelDir . '/arm_init.cpio.gz', str_repeat('I', 2048));

$tftpSettings = [
    'FOG_TFTP_HOST' => 'tftp.example.test',
    'FOG_TFTP_FTP_USERNAME' => 'fogtftp',
    'FOG_TFTP_FTP_PASSWORD' => 's3cret',
    'FOG_TFTP_ROOT_DIR' => '/srv/tftp/',
    'FOG_TFTP_PXE_KERNEL_DIR' => $kernelDir . '/',
];
$db->responder = function ($sql, $params) use ($tftpSettings) {
    if (false !== stripos($sql, 'FROM `globalSettings`')) {
        $rows = [];
        foreach ($tftpSettings as $k => $v) {
            $rows[] = ['settingKey' => $k, 'settingValue' => $v];
        }

        return $rows;
    }

    return null;
};

$fakeFs = new FakeTftpFs();
FogTestHarness::setStatic('FOGBase', 'FOGSSH', $fakeFs);

$dir = FogTestHarness::callStatic('FOG\Boot\UbootTftpSync', '_connect');

$t->check(
    'the pxelinux.cfg dir is built from FOG_TFTP_ROOT_DIR, not the kernel dir',
    '/srv/tftp/pxelinux.cfg' === $dir
);
$t->check(
    'the SSH credentials are read from the TFTP settings',
    'tftp.example.test' === $fakeFs->host
    && 'fogtftp' === $fakeFs->username
    && 's3cret' === $fakeFs->password
);
$t->check(
    'a missing pxelinux.cfg dir is created',
    [$dir] === $fakeFs->mkdirCalls
);

$mkdirCallsBefore = count($fakeFs->mkdirCalls);
FogTestHarness::callStatic('FOG\Boot\UbootTftpSync', '_connect');
$t->check(
    'an existing pxelinux.cfg dir is not recreated',
    $mkdirCallsBefore === count($fakeFs->mkdirCalls)
);

$fakeFs->connectSucceeds = false;
$threw = null;
try {
    FogTestHarness::callStatic('FOG\Boot\UbootTftpSync', '_connect');
} catch (\Throwable $e) {
    $threw = $e;
}
$t->check(
    'a failed SSH connection throws rather than silently continuing',
    null !== $threw
    && false !== stripos($threw->getMessage(), 'Unable to connect')
);
$fakeFs->connectSucceeds = true;

/*
 * _write(): the temp-file-and-upload primitive every write goes through.
 */
FogTestHarness::callStatic(
    'FOG\Boot\UbootTftpSync',
    '_write',
    ['/tftpboot/pxelinux.cfg/01-aa-bb-cc-dd-ee-ff', "DEFAULT localboot\n"]
);
$t->check(
    '_write() uploads the exact bytes it was given',
    "DEFAULT localboot\n"
    === ($fakeFs->contents['/tftpboot/pxelinux.cfg/01-aa-bb-cc-dd-ee-ff'] ?? null)
);
$t->check(
    '_write() chmods the file to 0644 -- an SFTP PUT\'s permissions follow '
    . 'the server umask, not FOG, and a TFTP daemon reading as a different '
    . 'user than the SFTP login needs read access explicitly granted',
    0644 === ($fakeFs->chmodCalls['/tftpboot/pxelinux.cfg/01-aa-bb-cc-dd-ee-ff'] ?? null)
);

/*
 * Orchestration properties that are cheap to pin by reading the source and
 * expensive to drive end-to-end (see the file header comment for why):
 * catching \Throwable rather than \Exception, the id-list hygiene every
 * batched entry point does before opening a connection, and the
 * isValid()-gated branch _syncOne() uses to choose write vs. delete.
 */

/*
 * Each method's body is extracted first (stopping at its own closing brace,
 * same technique tests/tasklog-records-cancel.test.php uses), rather than
 * matching ".*?catch" against the whole file: three methods in a row all
 * catching \Throwable meant an unbounded lazy match found the NEXT method's
 * catch clause just fine even after this one's was reverted to \Exception --
 * a mutation test caught that false pass before this file was trusted.
 */
function extractMethodBody($source, $signaturePattern)
{
    if (preg_match($signaturePattern . '.*?\n    \}\n#s', $source, $m)) {
        return $m[0];
    }

    return '';
}

foreach (
    [
        'materializeMany' => '#public static function materializeMany\(array \$hostIDs\)',
        'removeMany' => '#public static function removeMany\(array \$hostIDs\)',
        'reconcile' => '#public static function reconcile\(\)',
    ] as $method => $signaturePattern
) {
    $body = extractMethodBody($syncSource, $signaturePattern);
    $t->check("$method() is found", '' !== $body);
    $t->check(
        "$method() swallows Throwable, not just Exception",
        (bool)preg_match('#catch\s*\(\s*\\\\Throwable#', $body)
    );
}

foreach (['materializeMany', 'removeMany'] as $method) {
    $t->check(
        "$method() de-duplicates and drops empty ids before opening a connection",
        (bool)preg_match(
            '#' . $method . '\(array \$hostIDs\)\s*\{\s*'
            . '\$hostIDs = array_values\(array_unique\(array_filter\(\$hostIDs\)\)\);'
            . '\s*if \(!\$hostIDs\) \{\s*return;#s',
            $syncSource
        )
    );
}

/*
 * _stageBootFiles(): the kernel and init a TFTP-mode document names have
 * to exist in the TFTP root, beside pxelinux.cfg/, or `pxe boot` gets
 * `File not found` for a file FOG never copied. Size is the change
 * detector and a per-connection cache keeps reconcile() to one stat per
 * file, so each of those has to be shown to do its job on its own.
 */
$root = '/srv/tftp';
$stageArgs = [$root, ['arm_Image', 'arm_init.cpio.gz']];
$fakeFs->resetPuts();
FogTestHarness::setStatic('FOG\Boot\UbootTftpSync', '_staged', []);
FogTestHarness::callStatic('FOG\Boot\UbootTftpSync', '_stageBootFiles', $stageArgs);
$t->check(
    'a kernel and init missing from the TFTP root are uploaded beside pxelinux.cfg/',
    [$root . '/arm_Image', $root . '/arm_init.cpio.gz'] === $fakeFs->putCalls
    && str_repeat('K', 4096) === ($fakeFs->contents[$root . '/arm_Image'] ?? '')
    && str_repeat('I', 2048) === ($fakeFs->contents[$root . '/arm_init.cpio.gz'] ?? '')
);
$t->check(
    'staged boot files are made world-readable for a tftpd running as another user',
    0644 === ($fakeFs->chmodCalls[$root . '/arm_Image'] ?? null)
    && 0644 === ($fakeFs->chmodCalls[$root . '/arm_init.cpio.gz'] ?? null)
);

$fakeFs->resetPuts();
FogTestHarness::setStatic('FOG\Boot\UbootTftpSync', '_staged', []);
FogTestHarness::callStatic('FOG\Boot\UbootTftpSync', '_stageBootFiles', $stageArgs);
$t->check(
    'a boot file already present at the same size is not uploaded again (size compare, cache cleared)',
    [] === $fakeFs->putCalls
);

file_put_contents($kernelDir . '/arm_Image', str_repeat('K', 5000));
$fakeFs->resetPuts();
FogTestHarness::setStatic('FOG\Boot\UbootTftpSync', '_staged', []);
FogTestHarness::callStatic('FOG\Boot\UbootTftpSync', '_stageBootFiles', $stageArgs);
$t->check(
    'a boot file whose size changed locally is uploaded again, and only that one',
    [$root . '/arm_Image'] === $fakeFs->putCalls
    && 5000 === strlen($fakeFs->contents[$root . '/arm_Image'])
);

file_put_contents($kernelDir . '/arm_Image', str_repeat('K', 6000));
$fakeFs->resetPuts();
// Cache deliberately NOT cleared: the size differs, but this connection
// already confirmed the file, so a second host in the same reconcile pass
// must not stat or upload it again.
FogTestHarness::callStatic('FOG\Boot\UbootTftpSync', '_stageBootFiles', $stageArgs);
$t->check(
    'within one connection a file already confirmed is not re-checked',
    [] === $fakeFs->putCalls
);

FogTestHarness::setStatic('FOG\Boot\UbootTftpSync', '_staged', []);
$threw = '';
try {
    FogTestHarness::callStatic(
        'FOG\Boot\UbootTftpSync',
        '_stageBootFiles',
        [$root, ['arm_Image.custom']]
    );
} catch (\Exception $e) {
    $threw = $e->getMessage();
}
$t->check(
    'a named boot file absent from the local kernel dir throws, naming the file',
    false !== strpos($threw, 'arm_Image.custom')
    && false !== strpos($threw, $kernelDir)
);

$t->check(
    '_syncOne() renders in TFTP mode and stages the boot files before writing',
    (bool)preg_match(
        '#if \(\$Host->isValid\(\) && \$Host->get\(.task.\)->isValid\(\)\) \{'
        . '\s*\$built = UbootBootMenu::buildForHost\(\$Host, true\);'
        . '\s*self::_stageBootFiles\(dirname\(\$dir\), \$built\[.files.\]\);#s',
        $syncSource
    )
);
$t->check(
    'reconcile() renders in TFTP mode and stages the boot files before writing',
    (bool)preg_match(
        '#\$Host = new Host\(\$hostID\);.{0,300}?'
        . '\$built = UbootBootMenu::buildForHost\(\$Host, true\);'
        . '\s*self::_stageBootFiles\(dirname\(\$dir\), \$built\[.files.\]\);#s',
        $syncSource
    )
);

$t->check(
    '_syncOne() writes only when the host and its task are both valid',
    (bool)preg_match(
        '#if \(\$Host->isValid\(\) && \$Host->get\(.task.\)->isValid\(\)\) \{'
        . '\s*\$built = UbootBootMenu::buildForHost\(\$Host, true\);#s',
        $syncSource
    )
);
$t->check(
    'reconcile() derives the active-host set from queued + in-progress tasks',
    (bool)preg_match(
        '#Route::getIds\(\s*.task.,\s*\[\s*.stateID. => self::fastmerge\('
        . '\s*self::getQueuedStates\(\),\s*\(array\)\s*self::getProgressState\(\)#s',
        $syncSource
    )
);
$t->check(
    'reconcile() only deletes files matching this class\'s own naming pattern',
    (bool)preg_match(
        '#if \(!preg_match\(self::NAME_PATTERN, \$name\)\) \{\s*continue;#s',
        $syncSource
    )
);

/*
 * Call-site wiring: every task-lifecycle mutation this class exists to
 * track actually calls into it. Each pattern is anchored to the surrounding
 * statement it was inserted next to, not just "the string appears in the
 * file somewhere", so a refactor that moves the call away from the save it
 * is meant to follow still fails this.
 */
$callSites = [
    'packages/web/src/Items/Host.php' => [
        'createImagePackage() materializes the new task' =>
            '#\$this->set\(.task., \$Task\);'
            . '.{0,800}?UbootTftpSync::materialize\(\$this\);#s',
    ],
    'packages/web/src/Items/Group.php' => [
        'createImagePackage() materializes every tasked host in one batch' =>
            '#UbootTftpSync::materializeMany\(\s*Route::getIds\(\s*.task.#s',
    ],
    'packages/web/src/Items/Task.php' => [
        'cancel() removes the file once the cancel is saved' =>
            '#stateID., self::getCancelledState\(\)\)->save\(\)\)\s*\{'
            . '.{0,800}?UbootTftpSync::remove\(\$this->getHost\(\)\);#s',
    ],
    'packages/web/src/Managers/TaskManager.php' => [
        'cancel() removes the files for every task the bulk update touched' =>
            '#if \(\$updated\)\s*\{.{0,800}?'
            . 'UbootTftpSync::removeMany\(\$hostIDs\);#s',
    ],
    'packages/web/src/TaskHandling/TaskQueue.php' => [
        'checkout() removes the file once tasking completes' =>
            '#HOST_TASKING_COMPLETE.*?UbootTftpSync::remove\(self::\$Host\);#s',
    ],
    'packages/web/src/TaskHandling/TaskError.php' => [
        '_markFailed() removes the file once the task is marked failed' =>
            '#\$Task->set\(.stateID., \$failed\)->save\(\);\s*'
            . 'UbootTftpSync::remove\(\$Task->getHost\(\)\);#',
    ],
    'packages/web/src/Service/TaskScheduler.php' => [
        '_commonOutput() runs the reconcile sweep' =>
            '#UbootTftpSync::reconcile\(\);#',
    ],
];

foreach ($callSites as $relPath => $checks) {
    $source = file_get_contents(dirname(__DIR__) . '/' . $relPath);
    foreach ($checks as $label => $pattern) {
        $t->check($label, (bool)preg_match($pattern, $source));
    }
}

$t->finish();
