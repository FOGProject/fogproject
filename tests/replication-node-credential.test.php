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
}

/**
 * Stops the real method at the FTP login, carrying what it was handed.
 */
class FakeFtp
{
    public $username = 'UNSET';
    public $password = 'UNSET';
    public $host = 'UNSET';

    public function connect()
    {
        $e = new Connected();
        $e->username = $this->username;
        $e->password = $this->password;
        $e->host = $this->host;
        throw $e;
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
    public $procRef = [];

    public static function outall($s) {}
    public static function shortName($o) { return 'Snapin'; }
    public static function byteconvert($v) { return 0; }
    public static function globrecursive($p, $f = 0) { return []; }
    public static function fastmerge() { return []; }
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

@unlink("$dir/test");
@rmdir($dir);

if ($failures) {
    fwrite(
        STDERR,
        "FAIL: replication node credential\n" . implode("\n", $failures) . "\n"
    );
    exit(1);
}

echo "PASS: replication node credential (3 checks)\n";
exit(0);
