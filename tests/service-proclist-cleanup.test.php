<?php
/**
 * cleanupProcList() actually empties the process lists.
 *
 * The daemons record each spawned transfer in $this->procRef and its pipes
 * in $this->procPipes, and doHousekeeping() -- which runs every 100ms while
 * a service waits for its next cycle -- calls cleanupProcList() to reap the
 * finished ones.
 *
 * The trap it fell into is worth stating plainly, because the broken form
 * reads as correct:
 *
 *     foreach ((array)$this->procRef as $item => &$itemTypes) { ... }
 *
 * The cast produces a TEMPORARY array. `&$itemTypes` therefore binds into
 * that copy, not into the property, and every unset() made through it is
 * discarded when the loop ends. Nothing errors. The entry simply stays, and
 * because housekeeping runs ten times a second the daemon re-reports the
 * same finished transfer about ten times a second for as long as it lives
 * -- an unbounded log, and proc_close() called repeatedly on one handle.
 *
 * Observed on the 1.6 lab the moment Group -> Group replication started
 * working: 'Sync finished - Resource id #1348' filled the log at roughly
 * ten lines a second. The casts had been added the same day for PHP 8 null
 * safety, which is a real need -- so the fix keeps them and iterates over
 * array_keys() snapshots instead, writing through $this->procRef by path.
 *
 * This runs the REAL method, lifted from the shipped file.
 *
 * Usage: php tests/service-proclist-cleanup.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$src = __DIR__ . '/../packages/web/lib/service/fogservice.class.php';
$code = file_get_contents($src);
if (false === $code) {
    fwrite(STDERR, "cannot read $src\n");
    exit(1);
}

$start = strpos($code, '    public function cleanupProcList()');
if (false === $start) {
    fwrite(STDERR, "cleanupProcList() not found in $src\n");
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
    fwrite(STDERR, "could not brace-match cleanupProcList()\n");
    exit(1);
}
$method = substr($code, $start, $end - $start + 1);

$harness = "namespace FOGTest;\n"
    . "class Svc {\n"
    . "    public \$procRef = [];\n"
    . "    public \$procPipes = [];\n"
    . "    public \$running = [];\n"
    . "    public \$reported = 0;\n"
    // A "process" here is a string tag; running-ness is looked up by tag so
    // a test can have one finished and one still going.
    . "    public function isRunning(\$p) { return !empty(\$this->running[\$p]); }\n"
    . "    public static \$log = [];\n"
    . "    public static function outall(\$s) { self::\$log[] = \$s; }\n"
    . $method
    . "\n}\n";

eval($harness);

$failures = [];

// 1. One finished transfer: it must be gone after ONE pass, and reported
//    exactly once.
$svc = new \FOGTest\Svc();
$svc->procRef = ['group' => ['test' => [0 => 'PROC-A']]];
$svc->procPipes = ['group' => ['test' => [0 => []]]];
$svc->running = ['PROC-A' => false];
\FOGTest\Svc::$log = [];
$svc->cleanupProcList();

if (!empty($svc->procRef)) {
    $failures[] = "  a finished transfer was not removed from procRef\n    "
        . 'left behind: ' . json_encode($svc->procRef)
        . "\n    (the classic cause is foreach over a (array) cast, whose "
        . 'unsets go to a temporary)';
}
if (!empty($svc->procPipes)) {
    $failures[] = '  a finished transfer was not removed from procPipes: '
        . json_encode($svc->procPipes);
}
if (1 !== count(\FOGTest\Svc::$log)) {
    $failures[] = sprintf(
        "  one finished transfer should report once, got %d line(s)",
        count(\FOGTest\Svc::$log)
    );
}

// 2. The repeat-report symptom, stated directly: a second housekeeping pass
//    over the same state must say nothing, because there is nothing left.
\FOGTest\Svc::$log = [];
$svc->cleanupProcList();
if (0 !== count(\FOGTest\Svc::$log)) {
    $failures[] = sprintf(
        "  a second pass re-reported %d finished transfer(s); housekeeping "
        . 'runs every 100ms, so this is an unbounded log',
        count(\FOGTest\Svc::$log)
    );
}

// 3. A transfer still running must be left completely alone.
$svc2 = new \FOGTest\Svc();
$svc2->procRef = ['group' => ['test' => [0 => 'PROC-B']]];
$svc2->procPipes = ['group' => ['test' => [0 => []]]];
$svc2->running = ['PROC-B' => true];
\FOGTest\Svc::$log = [];
$svc2->cleanupProcList();
if (($svc2->procRef['group']['test'][0] ?? null) !== 'PROC-B') {
    $failures[] = '  a RUNNING transfer was reaped: '
        . json_encode($svc2->procRef);
}
if (0 !== count(\FOGTest\Svc::$log)) {
    $failures[] = '  a running transfer was reported as finished';
}

// 4. Mixed: reap the finished one, keep the running one, and keep the
//    parent keys that still hold something.
$svc3 = new \FOGTest\Svc();
$svc3->procRef = ['group' => ['test' => [0 => 'DONE', 1 => 'BUSY']]];
$svc3->procPipes = ['group' => ['test' => [0 => [], 1 => []]]];
$svc3->running = ['DONE' => false, 'BUSY' => true];
\FOGTest\Svc::$log = [];
$svc3->cleanupProcList();
$left = $svc3->procRef['group']['test'] ?? [];
if (array_values($left) !== ['BUSY']) {
    $failures[] = '  mixed state reaped wrongly, expected only BUSY left: '
        . json_encode($svc3->procRef);
}

if ($failures) {
    fwrite(
        STDERR,
        "FAIL: service proclist cleanup\n" . implode("\n", $failures) . "\n"
    );
    exit(1);
}

echo "PASS: service proclist cleanup (7 checks)\n";
exit(0);
