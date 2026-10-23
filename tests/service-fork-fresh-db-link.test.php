<?php
/**
 * A daemon child must never use the supervisor's database connection.
 *
 * Service_persist() forks a child, and forks a replacement each time the
 * child exits (#917). The supervisor connects to MySQL while it boots, before
 * the first fork. Each child used to inherit that one socket. A child that
 * died in the middle of a result left unread packets on it, and the next
 * child read them as replies to its own queries: "Packets out of order",
 * prepare() returned false, and every setting read came back empty (#1930).
 * A child that exited cleanly sent COM_QUIT on it instead.
 *
 * So the supervisor closes its connection before every fork, and each child
 * opens its own. This runs the REAL Service_persist() against stub DB
 * classes that record who called them, and checks both halves for two
 * consecutive children. No root and no database.
 *
 * Usage: php tests/service-fork-fresh-db-link.test.php [repo-root]
 * Exit status 0 = pass, 1 = fail.
 */

if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
    fwrite(STDERR, "SKIP: pcntl/posix unavailable\n");
    exit(0);
}

$root = $argv[1] ?? dirname(__DIR__);
$lib = $root . '/packages/service/lib/service_lib.php';
$tmp = sys_get_temp_dir() . '/fog-service-fork-' . getmypid();
@mkdir($tmp . '/commons', 0700, true);
$events = $tmp . '/events';
touch($tmp . '/servicemaster.log');

// The stub boot: just enough of FOGCore for service_lib.php, plus DB
// classes that record each call with the caller's pid.
file_put_contents($tmp . '/commons/base.inc.php', '<?php
namespace FOG\Base {
    class FOGCore {
        public static function getSetting($key) {
            return [
                "SERVICE_LOG_PATH" => ' . var_export($tmp, true) . ',
                "SERVICEMASTERLOGFILENAME" => "servicemaster.log",
                "SERVICESLEEPTIME" => 1,
            ][$key] ?? "";
        }
        public static function formatTime($t, $f = false) { return $t; }
    }
}
namespace FOG\Db {
    function record($what) {
        file_put_contents(' . var_export($events, true) . ',
            $what . " " . getmypid() . "\n", FILE_APPEND);
    }
    class PDODB {
        public static function disconnect() { record("disconnect"); }
    }
    class DatabaseManager {
        public static function getLink() { record("connect"); }
    }
}
');

// The daemon: two children. The first exits, so the supervisor forks a
// replacement; the second stops the supervisor.
$daemon = $tmp . '/daemon.php';
file_put_contents($daemon, '<?php
define("WEBROOT", ' . var_export($tmp, true) . ');
define("DS", "/");
define("FOG_LOG_DIR", ' . var_export($tmp, true) . ');
require ' . var_export($lib, true) . ';
Service_persist("ForkTest");
\FOG\Db\record("child");
if (substr_count(file_get_contents(' . var_export($events, true) . '), "child") > 1) {
    posix_kill(posix_getppid(), SIGTERM);
}
exit(0);
');

$out = [];
exec(
    'timeout 30 ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($daemon)
    . ' 2>&1',
    $out
);

$failures = [];
$lines = is_file($events)
    ? file($events, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];
$log = array_map(function ($l) {
    return explode(' ', $l);
}, $lines);
$children = array_values(array_filter($log, function ($e) {
    return 'child' === $e[0];
}));
if (count($children) < 2) {
    $failures[] = 'expected two children, saw ' . count($children)
        . '; daemon output: ' . implode(' | ', $out);
}
foreach ($children as $n => $child) {
    $pid = $child[1];
    $at = array_search($child, $log, true);
    $before = array_slice($log, 0, $at);
    // The supervisor's own disconnect must come before this child exists.
    $parentClosed = array_filter($before, function ($e) use ($pid) {
        return 'disconnect' === $e[0] && $e[1] !== $pid;
    });
    if (count($parentClosed) < $n + 1) {
        $failures[] = 'child ' . ($n + 1) . " ($pid) was forked while the"
            . ' supervisor still held its database connection';
    }
    if (!in_array(['connect', $pid], $before, true)) {
        $failures[] = 'child ' . ($n + 1) . " ($pid) did not open its own"
            . ' database connection';
    }
}

array_map('unlink', glob($tmp . '/commons/*') ?: []);
array_map('unlink', glob($tmp . '/*.*') ?: []);
@unlink($events);
@rmdir($tmp . '/commons');
@rmdir($tmp);

if ($failures) {
    foreach ($failures as $f) {
        fwrite(STDERR, "FAIL: $f\n");
    }
    exit(1);
}
echo "OK: each daemon child opens its own database connection\n";
exit(0);
