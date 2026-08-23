<?php
/**
 * A routed request keeps its query string.
 *
 * FOG serves everything that is not a real file through an internal rewrite
 * to api/index.php. Apache's rewrite carries QSA and always has. nginx's
 * try_files fallback named a plain URI, and a plain URI hands the router an
 * EMPTY query string -- so on nginx every routed endpoint that reads a query
 * parameter saw nothing, with no error anywhere.
 *
 * That is not an OIDC problem, it is where it was found: the plugin's
 * /ext/oidc/start?provider=3 arrived as provider=0 and answered "Unknown
 * identity provider" on a provider that was configured and enabled. The
 * API's own expand/start/length reads were already working around it inside
 * Route::queryParam().
 *
 * Two halves, and both are needed:
 *
 *   the installer appends $is_args$args, which fixes it at the source, but
 *   only for a server that re-runs the installer;
 *
 *   Route::queryParam() re-parses REQUEST_URI, which is what carries every
 *   server that does not.
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
$failures = [];
$checks = 0;

// ---------------------------------------------------------------
// 1. Every nginx try_files fallback carries the query string.
// ---------------------------------------------------------------
$functions = file_get_contents($root . '/lib/common/functions.sh');

// The three vhost writers (http, https, and the no-certificate variant) each
// emit their own copy, so counting matters as much as matching: a fourth
// added without the suffix is the same bug again.
preg_match_all(
    '#try_files \\\\\$uri \\\\\$uri/ \$\{WEB_root\}api/index\.php([^;"]*);#',
    $functions,
    $matches
);
$checks++;
if (count($matches[0]) < 3) {
    $failures[] = sprintf(
        'expected at least 3 nginx router fallbacks in functions.sh, found %d',
        count($matches[0])
    );
}
foreach ($matches[1] as $i => $suffix) {
    $checks++;
    if (strpos($suffix, '$is_args') === false
        || strpos($suffix, '$args') === false
    ) {
        $failures[] = sprintf(
            'nginx router fallback #%d does not append $is_args$args, so a '
            . 'routed request reaches the router with an empty query string',
            $i + 1
        );
    }
}

// Apache's side is QSA and must stay that way -- counted the same way, and
// for the same reason: there are three of these too.
preg_match_all(
    '#RewriteRule \^\$\{webrootre\}\(\.\*\)\$ \$\{WEB_root\}api/index\.php '
    . '\[([^\]]*)\]#',
    $functions,
    $apache
);
$checks++;
if (count($apache[0]) < 3) {
    $failures[] = sprintf(
        'expected at least 3 apache router rewrites in functions.sh, found %d',
        count($apache[0])
    );
}
foreach ($apache[1] as $i => $flags) {
    $checks++;
    if (strpos($flags, 'QSA') === false) {
        $failures[] = sprintf(
            'apache router rewrite #%d no longer carries QSA, so it drops '
            . 'the query string the way nginx used to',
            $i + 1
        );
    }
}

// ---------------------------------------------------------------
// 2. Route::queryParam() is public and still falls back.
// ---------------------------------------------------------------
$route = file_get_contents(
    $root . '/packages/web/lib/router/route.class.php'
);
$squashed = preg_replace('#\s+#', '', $route);

$signature = 'publicstaticfunctionqueryParam($key)';
$checks++;
$at = strpos($squashed, $signature);
if ($at === false) {
    $failures[] = 'Route::queryParam() is not public -- a plugin handler '
        . 'cannot reach it and will re-invent filter_input(INPUT_GET)';
    $body = '';
} else {
    // Scoped to the method: REQUEST_URI appears three times in this file,
    // so an unscoped search passes while queryParam's own copy is gone.
    $end = strpos($squashed, 'function', $at + strlen($signature));
    $body = substr($squashed, $at, $end === false ? null : $end - $at);
}

// The fallback is the whole point of the method; without it the method is
// just filter_input with extra steps.
$checks++;
if ($body !== '' && strpos($body, '$_SERVER[\'REQUEST_URI\']??\'\'') === false) {
    $failures[] = 'Route::queryParam() no longer re-parses REQUEST_URI';
}
$checks++;
if ($body !== '' && strpos($body, 'parse_str($qs,$parsed);') === false) {
    $failures[] = 'Route::queryParam() no longer parses the recovered query '
        . 'string';
}

if (count($failures) > 0) {
    echo "FAIL routed-query-string (" . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS routed-query-string ($checks checks)\n";
exit(0);
