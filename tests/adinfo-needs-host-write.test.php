<?php
/**
 * Pins the permission gate on FOGPage::adInfo() (GHSA-q38j-qx5x-w25v).
 *
 * adInfo() answers the global default domain-join user and password. The
 * page gate resolves it to host.view, and the read-only Legacy Restricted
 * role holds host.view. So the method itself has to ask for a permission
 * that can write a host's AD fields -- host.create or host.edit -- and it
 * has to ask before it reads FOG_AD_DEFAULT_PASSWORD.
 *
 * Source-anchored, for the reason set out in
 * tests/mass-edit-endpoint-is-gated.test.php: reaching adInfo() needs a
 * configured FOG, a session and a CSRF token. What is left is exactly what
 * source text can answer -- is the check there, and is it before the read.
 *
 * Usage: php tests/adinfo-needs-host-write.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$path = $root . '/packages/web/src/Base/FOGPage.php';
$source = @file_get_contents($path);
if (false === $source) {
    fwrite(STDERR, "FAIL: cannot read $path\n");
    exit(1);
}
$source = (string)$source;

$failures = [];
$check = static function ($what, $ok) use (&$failures) {
    if (!$ok) {
        $failures[] = $what;
    }
};

// The body of adInfo() alone, so a check in a neighboring method cannot
// satisfy the assertions. Brace-counted from the signature.
$start = strpos($source, 'public function adInfo()');
$body = null;
if (false !== $start) {
    $open = strpos($source, '{', $start);
    $depth = 0;
    $len = strlen($source);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $source[$i]) {
            $depth++;
        } elseif ('}' === $source[$i]) {
            $depth--;
            if (0 === $depth) {
                $body = substr($source, $open, $i - $open + 1);
                break;
            }
        }
    }
}
$check('adInfo() exists in FOGPage.php', null !== $body);
$body = (string)$body;

$create = strpos($body, "Authorization::can('host.create')");
$edit = strpos($body, "Authorization::can('host.edit')");
$denied = strpos($body, 'HTTP_FORBIDDEN');
$read = strpos($body, 'self::getSetting($names)');

$check('adInfo() asks for host.create', false !== $create);
$check('adInfo() asks for host.edit', false !== $edit);
$check('adInfo() answers 403 on a miss', false !== $denied);
$check('adInfo() still reads the AD defaults', false !== $read);
$check(
    'the permission check runs before the AD password is read',
    false !== $create && false !== $edit && false !== $denied
    && false !== $read
    && $create < $read && $edit < $read && $denied < $read
);
$check(
    'the check does not fall back to host.view',
    false === strpos($body, "can('host.view')")
);

if ($failures) {
    fwrite(STDERR, "FAIL: adinfo-needs-host-write\n  " . implode("\n  ", $failures) . "\n");
    exit(1);
}
echo "PASS: adinfo-needs-host-write\n";
exit(0);
