<?php
/**
 * Guards against a raw $_REQUEST read anywhere in the web tree.
 *
 * PHP builds the combined request array from GET, POST and (depending on
 * request_order / variables_order) the COOKIE before Initiator::sanitizeItems()
 * runs, so it bypasses the boot-time trim and null-byte strip. It also lets a
 * cookie answer a question that must be answered by the request itself.
 * FOGBase::requestParam() and FOGBase::hasRequestParam() read POST then GET
 * from the sanitized superglobals and are the one way to read a parameter.
 *
 * Also unit-checks the helpers against the real FOGBase: POST wins over GET,
 * GET is the fallback, the default applies, and a cookie-only key is not
 * returned.
 *
 * Usage: php tests/no-raw-request-superglobal.test.php [path/to/repo]
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGBase;

$root = $argv[1] ?? dirname(__DIR__);
$web = $root . '/packages/web';
$failures = [];

// The needle is split so this file never matches a scan for it.
$needle = '$_' . 'REQUEST';

$it = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web, \FilesystemIterator::SKIP_DOTS)
);
$scanned = 0;
foreach ($it as $file) {
    $path = $file->getPathname();
    // vendor/ is third-party; lib/plugins/ holds installed plugin artifacts,
    // not repo source (ADR 0009).
    if (!$file->isFile()
        || 'php' !== $file->getExtension()
        || false !== strpos($path, '/vendor/')
        || false !== strpos($path, '/packages/web/lib/plugins/')
    ) {
        continue;
    }
    $scanned++;
    // Code only: a comment that names the superglobal is not a read.
    foreach (token_get_all((string)file_get_contents($path)) as $tok) {
        if (is_array($tok) && T_VARIABLE === $tok[0] && $needle === $tok[1]) {
            $failures[] = sprintf(
                '%s:%d uses %s -- read it with FOGBase::requestParam() or'
                . ' FOGBase::hasRequestParam() (POST, then GET); a credential'
                . ' uses FOGBase::rawRequestParam()',
                substr($path, strlen($root) + 1),
                $tok[2],
                $needle
            );
        }
    }
}
if ($scanned < 400) {
    $failures[] = "only $scanned files scanned under packages/web; the scan"
        . ' is not reaching the tree';
}

// Unit check of the helpers, on the real class.
require_once __DIR__ . '/lib/filter-input-fixture.php';
require_once $root . '/packages/web/src/Base/FOGBase.php';

$_POST = ['both' => 'from-post', 'postonly' => 'p', 'arr' => ['a']];
$_GET = ['both' => 'from-get', 'getonly' => 'g'];
$_COOKIE = ['cookieonly' => 'c', 'both' => 'from-cookie'];

$checks = [
    'POST wins over GET'
        => FOGBase::requestParam('both') === 'from-post',
    'POST-only key is read'
        => FOGBase::requestParam('postonly') === 'p',
    'GET is the fallback'
        => FOGBase::requestParam('getonly') === 'g',
    'default applies when absent'
        => FOGBase::requestParam('missing', 'd') === 'd',
    'default is null when none given'
        => FOGBase::requestParam('missing') === null,
    'a cookie-only key is NOT returned'
        => FOGBase::requestParam('cookieonly', 'none') === 'none',
    'an array value comes back as sent'
        => FOGBase::requestParam('arr') === ['a'],
    'hasRequestParam sees POST'
        => true === FOGBase::hasRequestParam('postonly'),
    'hasRequestParam sees GET'
        => true === FOGBase::hasRequestParam('getonly'),
    'hasRequestParam ignores a cookie-only key'
        => false === FOGBase::hasRequestParam('cookieonly'),
    'hasRequestParam is false when absent'
        => false === FOGBase::hasRequestParam('missing'),
];

// rawRequestParam(): only null is absent. '' is a value, and false (an array
// value, as filter_input() reports it) is present but unusable -- default,
// with no fall-through to GET.
$raw = function (array $post, array $get, $default = 'd') {
    $GLOBALS['filter_input_fixture'] = [INPUT_POST => $post, INPUT_GET => $get];
    return FOGBase::rawRequestParam('u', $default);
};
$checks['raw: POST value is returned untrimmed']
    = $raw(['u' => ' bob '], []) === ' bob ';
$checks['raw: GET is the fallback when POST is absent']
    = $raw([], ['u' => 'bob']) === 'bob';
$checks['raw: an empty POST value does not fall through to GET']
    = $raw(['u' => ''], ['u' => 'bob']) === '';
$checks['raw: an array POST value gives the default, not GET']
    = $raw(['u' => false], ['u' => 'bob']) === 'd';
$checks['raw: absent in both gives the default']
    = $raw([], []) === 'd';

foreach ($checks as $name => $ok) {
    if (!$ok) {
        $failures[] = "helper: $name";
    }
}

if ($failures) {
    fwrite(STDERR, "FAIL\n  " . implode("\n  ", $failures) . "\n");
    exit(1);
}
echo "ok: no raw request superglobal under packages/web ($scanned files);"
    . ' requestParam()/hasRequestParam() behave' . "\n";
exit(0);
