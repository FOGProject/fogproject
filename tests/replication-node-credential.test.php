<?php
/**
 * The replicator hands lftp the storage node's real FTP password.
 *
 * FOGService::replicateItems() gets its candidate nodes from
 * Route::getList() and then, inside the loop, re-fetches each one with
 * Route::getItem() so it can read `online` -- which getter() computes and a
 * list row does not carry. Those two wrappers do NOT agree about secrets,
 * and that is the whole of this test:
 *
 *   getList()  -> listem() builds the rows; the EMITTER strips secrets on
 *                 the way out to HTTP, so an internal caller sees them.
 *   getItem()  -> indiv() -> getter(), which strips tier-2 fields at the
 *                 source (route.class.php, $sensitiveAlwaysFields:
 *                 storagenode pass and key). The object handed back has no
 *                 password on it at all.
 *
 * So `$StorageNode->pass` after the re-fetch was the empty string, lftp was
 * given no password, and every transfer was refused at login. Because the
 * replicator reports a refused login as "check the password stored for this
 * node", the symptom pointed at the admin's configuration -- the stored
 * password was fine and the daemon simply never read it.
 *
 * The router asymmetry itself has since been closed: getter() no longer
 * strips, so getItem() and getList() both hand internal callers the whole
 * object and only the API emitter removes secrets (see
 * tests/api-nested-secret-strip.test.php). This stays as the regression gate
 * for the DAEMON's half -- it fakes the old asymmetry deliberately, so it
 * still fails if the credential read moves back onto the re-fetched object,
 * whatever the router happens to be doing that week.
 *
 * This runs the REAL method body, lifted from the shipped file, against
 * fakes that reproduce exactly that asymmetry, and asserts on the credential
 * the method actually reaches FOGFTP with. It is not a source-shape check:
 * move the read back onto the re-fetched object and this fails.
 *
 * Usage: php tests/replication-node-credential.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$src = __DIR__ . '/../packages/web/src/Service/FOGService.php';
$code = file_get_contents($src);
if (false === $code) {
    fwrite(STDERR, "cannot read $src\n");
    exit(1);
}

$start = strpos($code, 'protected function replicateItems(');
if (false === $start) {
    fwrite(STDERR, "replicateItems() not found in $src\n");
    exit(1);
}
$open = strpos($code, '{', $start);
$depth = 0;
$end = null;
for ($i = $open, $n = strlen($code); $i < $n; $i++) {
    if ('{' === $code[$i]) {
        $depth++;
    } elseif ('}' === $code[$i]) {
        $depth--;
        if (0 === $depth) {
            $end = $i;
            break;
        }
    }
}
if (null === $end) {
    fwrite(STDERR, "could not brace-match replicateItems()\n");
    exit(1);
}
$method = str_replace(
    'protected function',
    'public function',
    substr($code, $start, $end - $start + 1)
);

// The method checks the local file exists before it ever connects, so give
// it a real one: /<snapinpath>/<file>.
$dir = sys_get_temp_dir() . '/fog-repl-cred-' . getmypid();
@mkdir($dir, 0700, true);
file_put_contents("$dir/test", "payload\n");

$harness = <<<'HARNESS'
namespace FOGTest;

class Connected extends \Exception
{
    public $username;
    public $password;
    public $host;
    public $port;
}

/**
 * Stops the real method at the FTP login, carrying what it was handed.
 *
 * With $stop off it records the port and lets the method run on to the lftp
 * command, so the port gate can see every place the port has to reach.
 */
class FakeFtp
{
    public static $stop = true;
    public static $lastPort = null;
    public $username = 'UNSET';
    public $password = 'UNSET';
    public $host = 'UNSET';

    public function connect($host = '', $port = 0)
    {
        $e = new Connected();
        $e->username = $this->username;
        $e->password = $this->password;
        $e->host = $this->host;
        $e->port = $port;
        self::$lastPort = $port;
        if (self::$stop) {
            throw $e;
        }
        return $this;
    }
}

/**
 * Answers file_exists() for the ftp:// URL the method builds, and records
 * the URL instead of dialing it. This is the namespaced function the eval'd
 * method body resolves, ahead of the global one.
 */
function file_exists($path)
{
    // Only the ftp:// URL is faked; the method also checks a real local file.
    if (0 !== strpos($path, 'ftp://')) {
        return \file_exists($path);
    }
    Svc::$urls[] = $path;
    return false;
}

/**
 * Every host answers; only here so the method gets past its http probe.
 */
class FakeUrlRequests
{
    public function isAvailable($urls, $timeout = 1, $port = -1, $proto = '')
    {
        return [false];
    }
}

/**
 * The per-node FTP port resolver the method calls on the router row. Mirrors
 * StorageNode::resolveFtpPort() for the cases this test uses; the real
 * resolver is tested in tests/storagenode-port-resolution.test.php.
 */
class StorageNode
{
    public static $globalFtp = 0;

    public static function resolveFtpPort($nodePort)
    {
        $nodePort = (int)$nodePort;
        if ($nodePort >= 1 && $nodePort <= 65535) {
            return $nodePort;
        }
        return self::$globalFtp ?: 21;
    }
}

class Node
{
    private $f;
    public function __construct(array $f) { $this->f = $f; }
    public function __get($k) { return $this->f[$k] ?? null; }
    public function __isset($k) { return isset($this->f[$k]); }
}

/**
 * Reproduces the wrapper asymmetry: lists carry the credential, single
 * fetches do not.
 */
class Route
{
    public static $listRow = [];
    public static $itemRow = [];
    public static $self = [];

    public static function getList($class, $find = false, $op = 'AND', $ord = 'name')
    {
        // Our own node is in the list too -- the loop skips it by id, and
        // the count check needs both to clear its threshold of two.
        return [new Node(self::$self), new Node(self::$listRow)];
    }

    public static function getItem($class, $id)
    {
        if ((int)$id === (int)(self::$self['id'] ?? 0)) {
            return new Node(self::$self);
        }
        return new Node(self::$itemRow);
    }
}

class Svc
{
    public static $FOGFTP;
    public static $FOGURLRequests;
    public $procRef = [];

    public static function outall($s) { self::$out[] = $s; }
    public static function shortName($o) { return 'Snapin'; }
    public static function byteconvert($v) { return 0; }
    public static function globrecursive($p, $f = 0) { return []; }
    // Merges for real: the method needs the local file to stay in the list.
    public static function fastmerge() { return array_merge(...func_get_args()); }
    // The lftp argv startTasking() was handed, and the ftp:// URLs probed.
    public static $cmds = [];
    public static $urls = [];
    public function startTasking($cmd, $logname, $randind, $itemType, $name)
    {
        self::$cmds[] = $cmd;
        $this->procRef[$itemType][$name][$randind] = 'started';
    }
    public function killTasking($randind, $itemType, $name) {}
    protected static function _lftpQuote($v) { return '"' . $v . '"'; }
    // The real lookup is tested in tests/replication-running-guard. Here the
    // test chooses the answer: null (nothing running) for the credential
    // run, a handle for the run that proves the call site skips.
    public static $running = null;
    // $log is the transfer log PATH the method derives its log name from;
    // $out collects what outall() prints.
    public static $log = '/tmp/repl.log';
    public static $out = [];
    private function _transferRunning($t, $n, $i) { return self::$running; }
    public function getPID($p) { return 4242; }
HARNESS;

$harness .= "\n" . $method . "\n}\n";

eval($harness);

/**
 * The item being replicated.
 */
class ReplItem
{
    /**
     * Reads a field the way a FOGController does.
     *
     * @param string $f Field name.
     *
     * @return mixed
     */
    public function get($f)
    {
        if ('storagegroups' === $f) {
            return [1, 2];
        }
        if ('file' === $f) {
            return 'test';
        }
        return 'test';
    }
}

$failures = [];

\FOGTest\Svc::$FOGFTP = new \FOGTest\FakeFtp();
// We are node 1 in group 1; the target is node 3, group 2's master.
\FOGTest\Route::$self = [
    'id' => 1,
    'name' => 'DefaultMember',
    'isMaster' => true,
    'online' => true,
    'snapinpath' => $dir,
    'ftppath' => $dir,
    'bandwidth' => 0,
];
// What listem() produces: credential present, `online` absent.
\FOGTest\Route::$listRow = [
    'id' => 3,
    'name' => 'debian',
    'ip' => '10.0.0.3',
    'user' => 'fogproject',
    'pass' => 'the-real-password',
    'key' => 'the-real-key',
    'storagegroupID' => 2,
    'snapinpath' => $dir,
    'ftppath' => $dir,
    'bandwidth' => 0,
];
// What getter() produces: `online` present, credential stripped.
\FOGTest\Route::$itemRow = [
    'id' => 3,
    'name' => 'debian',
    'ip' => '10.0.0.3',
    'user' => 'fogproject',
    'storagegroupID' => 2,
    'online' => true,
    'snapinpath' => $dir,
    'ftppath' => $dir,
    'bandwidth' => 0,
];

$svc = new \FOGTest\Svc();
$reached = false;
try {
    $svc->replicateItems(1, 1, new ReplItem(), true);
} catch (\FOGTest\Connected $e) {
    $reached = true;
    if ('the-real-password' !== $e->password) {
        $failures[] = sprintf(
            "  the FTP password handed to lftp is not the node's\n"
            . "    want: 'the-real-password'\n"
            . "    got : %s%s",
            var_export($e->password, true),
            ('' === $e->password || null === $e->password)
                ? "\n    (empty -- read off the getItem() object, which "
                    . "getter() strips)"
                : ''
        );
    }
    if ('fogproject' !== $e->username) {
        $failures[] = sprintf(
            "  the FTP username is wrong\n    want: 'fogproject'\n    got : %s",
            var_export($e->username, true)
        );
    }
    if ('10.0.0.3' !== $e->host) {
        $failures[] = sprintf(
            "  the FTP host is wrong\n    want: '10.0.0.3'\n    got : %s",
            var_export($e->host, true)
        );
    }
} catch (\Throwable $t) {
    $failures[] = '  replicateItems() raised before reaching the FTP login: '
        . get_class($t) . ': ' . $t->getMessage();
}

if (!$reached && !$failures) {
    $failures[] = '  replicateItems() never reached the FTP login, so this '
        . 'test asserted nothing';
}

// A transfer still running for the item: replicateItems() must skip it
// before the FTP login. The old guard tested a key startTasking() never
// writes, so it went on, deleted the half-copied file and restarted lftp
// every cycle (forum 18301).
\FOGTest\Svc::$running = 'lftp-handle';
\FOGTest\Svc::$out = [];
try {
    $svc->replicateItems(1, 1, new ReplItem(), true);
    $skipped = (bool)preg_grep('/Replication already running with PID: 4242/', \FOGTest\Svc::$out);
    if (!$skipped) {
        $failures[] = '  a running transfer was not reported as already running';
    }
} catch (\FOGTest\Connected $e) {
    $failures[] = '  replicateItems() reached the FTP login while a transfer '
        . 'for the item was still running';
} catch (\Throwable $t) {
    $failures[] = '  replicateItems() raised on the running-transfer run: '
        . get_class($t) . ': ' . $t->getMessage();
}

// Per-node FTP port (forum 18303). Run the method past the FTP login to the
// lftp command, and check the node's port reaches all three places that dial
// the node: the FOGFTP connect, the ftp:// URL the existence and hash checks
// open, and lftp's -p. Row ftpport 0 must fall back to the global setting.
\FOGTest\Svc::$running = null;
\FOGTest\FakeFtp::$stop = false;
\FOGTest\Svc::$FOGURLRequests = new \FOGTest\FakeUrlRequests();
$portCases = [
    'node port 2121 beats global 990' => [2121, 990, 2121],
    'node port 0 uses global 990' => [0, 990, 990],
    'node port 0, no global uses 21' => [0, 0, 21],
];
foreach ($portCases as $label => list($nodePort, $global, $want)) {
    \FOGTest\Route::$itemRow['ftpport'] = $nodePort;
    \FOGTest\Route::$listRow['ftpport'] = $nodePort;
    \FOGTest\StorageNode::$globalFtp = $global;
    \FOGTest\Svc::$cmds = [];
    \FOGTest\Svc::$urls = [];
    \FOGTest\FakeFtp::$lastPort = null;
    try {
        $svc->replicateItems(1, 1, new ReplItem(), true);
    } catch (\Throwable $t) {
        $failures[] = "  [$label] replicateItems() raised: "
            . get_class($t) . ': ' . $t->getMessage();
        continue;
    }
    if (\FOGTest\FakeFtp::$lastPort !== $want) {
        $failures[] = "  [$label] FOGFTP::connect() got port "
            . var_export(\FOGTest\FakeFtp::$lastPort, true) . ", want $want";
    }
    $cmd = \FOGTest\Svc::$cmds[0] ?? [];
    $at = array_search('-p', $cmd, true);
    if (false === $at || ($cmd[$at + 1] ?? null) !== (string)$want) {
        $failures[] = "  [$label] the lftp command has no '-p $want'";
    }
    $url = \FOGTest\Svc::$urls[0] ?? '';
    if (0 !== strpos($url, "ftp://fogproject:the-real-password@10.0.0.3:$want/")
        && !preg_match('#^ftp://[^@]*@10\.0\.0\.3:' . $want . '/#', $url)
    ) {
        $failures[] = "  [$label] the ftp:// URL has no :$want port: "
            . var_export($url, true);
    }
}

@unlink("$dir/test");
@rmdir($dir);

if ($failures) {
    fwrite(
        STDERR,
        "FAIL: replication node credential\n" . implode("\n", $failures) . "\n"
    );
    exit(1);
}

echo "PASS: replication node credential\n";
exit(0);
