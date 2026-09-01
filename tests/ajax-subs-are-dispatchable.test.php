<?php
/**
 * Every sub the web UI fetches must resolve to a method on its page class.
 *
 * THE BUG THIS EXISTS FOR. FOGPageManager::render() picks the method BEFORE
 * it considers an Ajax or Post suffix:
 *
 *     if (... || !method_exists($class, $method) || empty($method)) {
 *         $method = 'index';
 *         ... addJavascript("js/fog/{$node}/fog.{$node}.list.js");
 *     }
 *     if (self::$ajax && method_exists($class, $method.'Ajax')) { ... }
 *     if (self::$post && method_exists($class, $method.'Post')) { ... }
 *
 * `$method` there is the raw sub. So an endpoint implemented ONLY as
 * <sub>Post is never reached: the name does not resolve, $method becomes
 * 'index', and the request is answered by the node's own list. HTTP 200,
 * valid JSON, nothing anywhere saying the endpoint does not exist.
 *
 * That is how the host list's mass edit shipped completely unreachable.
 * `massEditFormPost()` and `massEditPost()` had no bare counterparts, so a
 * POST to sub=masseditform came back as the 86-row host list envelope --
 * confirmed on a live 1.6 server, byte-identical across two runs. The
 * browser read data.msg as undefined, rendered an empty modal, and showed
 * no error because the response was a perfectly good 200. The apply half
 * was worse: Update reported nothing wrong and wrote nothing at all.
 *
 * WHAT IS CHECKED. Every `sub=` the management JS fetches, paired with the
 * node in the same URL (or the file's own directory where the URL uses
 * Common.node), against the page class owning that node. The predicate is
 * method_exists() -- the same call the dispatcher makes, on the same class,
 * not a grep for the name.
 *
 * The one legitimate fallback is allowed by name below and no other.
 *
 * It also checks the other end: a bare method that exists ONLY to be found
 * by the dispatcher must have a suffixed sibling to hand off to, or the
 * endpoint answers 405 to everything.
 *
 * Usage: php tests/ajax-subs-are-dispatchable.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('ajax-subs-dispatchable');

$t = new FogChecks();
$root = dirname(__DIR__) . '/packages/web';

/**
 * Subs that are MEANT to fall through to index(), with the reason.
 *
 * `list` is every grid's data URL and there is no list() anywhere -- it is
 * a PHP reserved word until 7.0 and the pages never grew one. The fallback
 * is what serves those grids, and the caller wants exactly the index
 * payload, which is the difference between this and the mass edit case.
 *
 * Adding a name here is a claim that the node's INDEX is the right answer
 * for that sub. It is not a way to silence a missing endpoint.
 */
$allowedFallback = [
    'list' => 'every grid\'s data URL; index() is the intended handler'
];

/**
 * Nodes whose page class is not fixed, so a name lookup cannot answer.
 *
 * `report`: loadPageClasses() picks the class from the `f` query parameter
 * for this node, so ReportManagement is one of many and a sub may live on
 * any individual report.
 *
 * `home`: two things answer it. DashboardPage declares the node; ProcessLogin
 * never sets one, and the login POST does not reach dispatch at all --
 * FOGPageManager::render() returns immediately for a caller who is not
 * logged in, which is every caller of ?node=home&sub=login.
 */
$unfixedNode = [
    'report' => 'class chosen by the f parameter',
    'home' => 'login is answered before dispatch'
];

// node => page class, read from the class's own $node property.
$byNode = [];
foreach (glob($root . '/src/Pages/*.php') as $f) {
    $src = (string)file_get_contents($f);
    if (preg_match('/public \$node = \'([a-z0-9_]+)\'/', $src, $m)) {
        $byNode[$m[1]] = 'FOG\\Pages\\' . basename($f, '.php');
    }
}
$t->check('the node to page-class map was built', count($byNode) > 5);

// Every (node, sub) the product actually asks for. BOTH halves: the
// browser's own fetches, and the action URLs PHP renders onto forms --
// sub=massedit is the second kind, so a JS-only scan would have watched
// the form endpoint break and missed the apply endpoint beside it.
$pairs = [];
foreach ([['/management/js', 'js'], ['/src', 'php']] as $spec) {
    list($dir, $ext) = $spec;
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root . $dir)
    );
    foreach ($it as $file) {
        if ($file->getExtension() !== $ext) {
            continue;
        }
        $path = $file->getPathname();
        $found = preg_match_all(
            // node=<name> or Common.node, then sub=<name> in the same URL.
            '/(?:node=([A-Za-z0-9_]+)|Common\.node)[^\'"]*?(?:&amp;|&)sub=([A-Za-z0-9_]+)/',
            (string)file_get_contents($path),
            $ms,
            PREG_SET_ORDER
        );
        if (!$found) {
            continue;
        }
        foreach ($ms as $m) {
            $node = '' !== $m[1] ? $m[1] : basename(dirname($path));
            $pairs[$node . '|' . $m[2]] = [$node, $m[2], basename($path)];
        }
    }
}
$t->check('the endpoint table was collected', count($pairs) > 30);
$t->check(
    'the host mass edit endpoints are in it, or this test proves nothing',
    isset($pairs['host|masseditform']) && isset($pairs['host|massedit'])
);

foreach ($pairs as $pair) {
    list($node, $sub, $where) = $pair;
    if (isset($allowedFallback[strtolower($sub)])) {
        continue;
    }
    if (isset($unfixedNode[$node])) {
        continue;
    }
    if (!isset($byNode[$node])) {
        // Not every node is a page class. 'client' and 'schema' are
        // special-cased in FOGPageManager::render() before dispatch, and a
        // docblock's illustrative ?node=X is not an endpoint at all. A real
        // page's subs are covered by the pairs that DO resolve to a class.
        continue;
    }
    // method_exists is the dispatcher's own predicate, run on the real
    // class -- not a search for the name in a file.
    $t->check(
        "node=$node sub=$sub resolves to a method ($where)",
        method_exists($byNode[$node], $sub)
    );
}

/**
 * The body of a method, read from the file it is declared in.
 *
 * @param string $class  class name
 * @param string $method method name
 *
 * @return string
 */
function methodBody($class, $method)
{
    $r = new \ReflectionMethod($class, $method);
    $lines = (array)file($r->getFileName());

    return implode(
        '',
        array_slice(
            $lines,
            $r->getStartLine() - 1,
            $r->getEndLine() - $r->getStartLine() + 1
        )
    );
}

// The other end: an anchor with nothing behind it answers 405 to every
// verb, which is a worse endpoint than the one it replaced.
$anchors = 0;
foreach ($byNode as $node => $class) {
    foreach (get_class_methods($class) as $method) {
        if (false === strpos(methodBody($class, $method), 'methodNotAllowed(')) {
            continue;
        }
        $anchors++;
        $t->check(
            "$class::$method() anchors a suffixed handler",
            method_exists($class, $method . 'Post')
            || method_exists($class, $method . 'Ajax')
        );
    }
}
$t->check('at least one dispatch anchor was examined', $anchors > 0);

// And the anchor answers the right code, since 200 with the wrong body is
// the failure this whole file is about.
$t->check(
    'methodNotAllowed sends 405',
    false !== strpos(
        methodBody('FOG\\Base\\FOGPage', 'methodNotAllowed'),
        'HTTP_METHOD_NOT_ALLOWED'
    )
);

$t->finish();
