<?php
/**
 * StorageNode::resolveSshPort() / resolveFtpPort() pick the right port.
 *
 * A storage node may carry its own SSH and FTP port (forum 18303). 0 means
 * "use the global setting" (FOG_SSH_PORT / FOG_FTP_PORT), and an unset or
 * invalid global falls back to 22 / 21. Router rows (plain objects) call
 * these two static wrappers, so they carry the whole rule.
 *
 * This runs the REAL wrapper and resolver source, lifted from the shipped
 * file, against a stub getSetting(). It is not a source-shape check: change
 * a default, a setting name or a range bound and a case below fails.
 *
 * Usage: php tests/storagenode-port-resolution.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$src = __DIR__ . '/../packages/web/src/Items/StorageNode.php';
$code = file_get_contents($src);
if (false === $code) {
    fwrite(STDERR, "cannot read $src\n");
    exit(1);
}
$start = strpos($code, 'public static function resolveSshPort(');
$tail = strpos($code, 'private static function _resolvePort(');
if (false === $start || false === $tail) {
    fwrite(STDERR, "resolver methods not found in $src\n");
    exit(1);
}
$open = strpos($code, '{', $tail);
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
    fwrite(STDERR, "could not brace-match _resolvePort()\n");
    exit(1);
}
$methods = substr($code, $start, $end - $start + 1);

eval(
    'class PortProbe {'
    . ' public static $settings = [];'
    . ' public static function getSetting($k) {'
    . '  return self::$settings[$k] ?? null; }'
    . $methods
    . '}'
);

$failures = [];
$check = function ($label, $got, $want) use (&$failures) {
    if ($got !== $want) {
        $failures[] = sprintf(
            "  %s: want %s, got %s",
            $label,
            var_export($want, true),
            var_export($got, true)
        );
    }
};

// [node value, FOG_SSH_PORT, FOG_FTP_PORT] => ssh, ftp
$cases = [
    'node port wins' => [[2222, 2200, 2100], 2222, 2222],
    'string node port wins' => [['2222', 2200, 2100], 2222, 2222],
    'node 0 uses the global' => [[0, 2200, 2100], 2200, 2100],
    'node null uses the global' => [[null, 2200, 2100], 2200, 2100],
    'node 0, no global: defaults' => [[0, null, null], 22, 21],
    'node 0, global 0: defaults' => [[0, 0, 0], 22, 21],
    'node 70000 is invalid: global' => [[70000, 2200, 2100], 2200, 2100],
    'node 70000, no global: defaults' => [[70000, null, null], 22, 21],
    'node abc is invalid: global' => [['abc', 2200, 2100], 2200, 2100],
    'node -5 is invalid: defaults' => [[-5, null, null], 22, 21],
    'global 70000 is invalid: defaults' => [[0, 70000, 70000], 22, 21],
    'global abc is invalid: defaults' => [[0, 'abc', 'abc'], 22, 21],
    'node 65535 is the top of range' => [[65535, null, null], 65535, 65535],
    'node 1 is the bottom of range' => [[1, null, null], 1, 1],
];
foreach ($cases as $label => list($in, $ssh, $ftp)) {
    PortProbe::$settings = ['FOG_SSH_PORT' => $in[1], 'FOG_FTP_PORT' => $in[2]];
    $check("$label (ssh)", PortProbe::resolveSshPort($in[0]), $ssh);
    $check("$label (ftp)", PortProbe::resolveFtpPort($in[0]), $ftp);
}

if ($failures) {
    fwrite(STDERR, "FAIL: storagenode port resolution\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo 'PASS: storagenode port resolution (' . count($cases) * 2 . " checks)\n";
exit(0);
