<?php
/**
 * replicateItems() skips an item whose transfer is still running.
 *
 * startTasking() stores each lftp process at
 * $this->procRef[$itemType][$name][$index]. The "Replication already
 * running" guard in replicateItems() tested $this->procRef[$itemType][$index]
 * instead -- a key nothing ever writes -- so the guard never fired. Every
 * cycle then compared the files while lftp was still copying them, found
 * the half-copied remote file the wrong size, deleted it, killed the running
 * lftp and started a new one. A transfer longer than one replicator cycle
 * (a large image over a throttled link) never finished. Forum 18301.
 *
 * The lookup now lives in _transferRunning(), which this runs from the
 * shipped file against a procRef written at startTasking()'s path.
 *
 * Usage: php tests/replication-running-guard.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$src = __DIR__ . '/../packages/web/src/Service/FOGService.php';
$code = file_get_contents($src);
if (false === $code) {
    fwrite(STDERR, "cannot read $src\n");
    exit(1);
}

$start = strpos($code, '    private function _transferRunning(');
if (false === $start) {
    fwrite(STDERR, "_transferRunning() not found in $src\n");
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
    fwrite(STDERR, "could not brace-match _transferRunning()\n");
    exit(1);
}
$method = str_replace('private function', 'public function', substr($code, $start, $end - $start + 1));

$harness = "namespace FOGTest;\n"
    . "class Svc {\n"
    . "    public \$procRef = [];\n"
    . "    public \$running = [];\n"
    // A "process" is a string tag; isRunning() looks it up by tag.
    . "    public function isRunning(\$p) { return !empty(\$this->running[\$p]); }\n"
    . $method
    . "\n}\n";
eval($harness);

$fail = 0;
$check = function ($label, $got, $want) use (&$fail) {
    if ($got === $want) {
        echo "  ok    $label\n";
        return;
    }
    echo "  FAIL  $label: got " . var_export($got, true) . ', want ' . var_export($want, true) . "\n";
    $fail++;
};

$svc = new \FOGTest\Svc();
// Written exactly where startTasking() writes it.
$svc->procRef['image']['win11'][0] = 'lftp-a';
$svc->running['lftp-a'] = true;

$check('a running transfer is seen', $svc->_transferRunning('image', 'win11', 0), 'lftp-a');
$check('another index of the same item is not', $svc->_transferRunning('image', 'win11', 1), null);
$check('another item is not', $svc->_transferRunning('image', 'other', 0), null);
$check('an item type with no transfers is not', $svc->_transferRunning('snapin', 'win11', 0), null);

$svc->running['lftp-a'] = false;
$check('a finished transfer is not', $svc->_transferRunning('image', 'win11', 0), null);

exit($fail ? 1 : 0);
