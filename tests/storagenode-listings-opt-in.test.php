<?php
/**
 * Serializing a storage node must not fan out to that node.
 *
 * `images` and `snapinfiles` are not columns. Each is an outbound HTTP GET
 * to status/getfiles.php on the node itself, so Route::getter() building
 * them meant two round trips to a possibly-dead machine every time anything
 * turned a StorageNode into a payload -- paid by every caller, including the
 * many that never look at the answer. FOGMulticastManager re-reads its
 * master nodes every MULTICASTSLEEPTIME (10 seconds by default), and the
 * storage group grid serializes a master node per row.
 *
 * `logfiles` was already commented out for exactly this cost. That is the
 * tell: the answer was never "delete the field", it was "stop making every
 * caller pay for it". So they are now behind ?expand=, and the commented
 * line is left as it was.
 *
 * THE SUBTLE HALF. getter() now asks wantsExpand(), so parseExpand() has to
 * have run before getter() is called. indiv() used to call it after, which
 * was harmless while nothing in getter() consulted it and is a silent
 * regression now: the fields would be absent even when the caller asked for
 * them, with no error anywhere. That ordering is pinned here, because it is
 * the kind of thing a later tidy-up reverses without noticing.
 *
 * Usage: php tests/storagenode-listings-opt-in.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$file = 'packages/web/lib/router/route.class.php';
$src = (string)file_get_contents($file);

/*
 * 1. The expand parser and its predicate, run for real. queryParam() is the
 *    only thing they reach for that a test cannot have.
 */
$methods = [];
foreach (['parseExpand', 'wantsExpand'] as $name) {
    if (!preg_match(
        '#\n    public static function '
        . preg_quote($name, '#')
        . '\(.*?\n    \}#s',
        $src,
        $m
    )) {
        $fails[] = "Route::$name() is gone; the opt-in has no gate";
        continue;
    }
    $methods[$name] = $m[0];
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

eval(
    'class ExpandProbe {'
    . ' public static $expand = []; public static $expandAll = false;'
    . ' public static $expandDepth = 0; public static $raw = null;'
    . ' public static function queryParam($k) { return self::$raw; }'
    . implode("\n", $methods)
    . ' }'
);

$cases = [
    // raw ?expand= value      images  snapinfiles
    [null,                     false,  false],
    ['',                       false,  false],
    ['images',                 true,   false],
    ['snapinfiles',            false,  true],
    ['images,snapinfiles',     true,   true],
    ['all',                    true,   true],
    ['IMAGES',                 true,   false],
    [' images ',               true,   false],
    ['snapins',                false,  false],
];
foreach ($cases as list($raw, $wantImages, $wantSnapins)) {
    ExpandProbe::$raw = $raw;
    ExpandProbe::parseExpand();
    $label = $raw === null ? '(absent)' : "'$raw'";
    if (ExpandProbe::wantsExpand('images') !== $wantImages) {
        $fails[] = "expand=$label gives the wrong answer for images";
    }
    if (ExpandProbe::wantsExpand('snapinfiles') !== $wantSnapins) {
        $fails[] = "expand=$label gives the wrong answer for snapinfiles";
    }
}

/*
 * 2. getter() actually gates on it.
 *
 * Pinned by shape: what must not come back is an unconditional
 * $class->get('images'), which is what shipped.
 */
if (!preg_match(
    '#public static function getter\(\$classname, \$class\).*?\n    \}#s',
    $src,
    $g
)) {
    $fails[] = 'Route::getter() is gone';
    $case = '';
} elseif (!preg_match(
    '#\n                case \'storagenode\':.*?\n                    break;#s',
    $g[0],
    $m
)) {
    $fails[] = "Route::getter()'s storagenode case is gone";
    $case = '';
} else {
    $case = $m[0];
}
if ('' !== $case) {
    foreach (['images', 'snapinfiles'] as $field) {
        if (!preg_match(
            '#if \(self::wantsExpand\(\'' . $field . '\'\)\) \{\s*\n\s*'
            . '\$extra\[\'' . $field . '\'\] = \$class->get\(\''
            . $field . '\'\);#',
            $case
        )) {
            $fails[] = "storagenode's $field is not gated on"
                . " wantsExpand('$field'); serializing a node fans out to"
                . ' the node again';
        }
    }
    // Left exactly as it was found, per the repo's own rule about
    // commented-out code. It is also the evidence for why the other two are
    // opt-in rather than deleted.
    if (false === strpos($case, "//'logfiles' => \$class->get('logfiles'),")) {
        $fails[] = "the commented-out logfiles line has been removed from"
            . ' the storagenode case';
    }
}

/*
 * 3. parseExpand() must precede getter() inside indiv().
 */
if (!preg_match(
    '#public static function indiv\(\$class, \$id\).*?\n    \}#s',
    $src,
    $m
)) {
    $fails[] = 'Route::indiv() is gone';
} else {
    $body = $m[0];
    $parse = strpos($body, 'self::parseExpand();');
    $get = strpos($body, 'self::getter(');
    if ($parse === false) {
        $fails[] = 'indiv() no longer calls parseExpand(); ?expand= would be'
            . ' ignored entirely';
    } elseif ($get === false) {
        $fails[] = 'indiv() no longer calls getter()';
    } elseif ($parse > $get) {
        $fails[] = 'indiv() calls parseExpand() after getter(); getter()'
            . ' reads the expansion state, so storagenode images and'
            . ' snapinfiles would be absent even when asked for -- silently,'
            . ' with no error anywhere';
    }
}

/*
 * 4. The document has to say so. A client generated from a spec that still
 *    promises these fields unconditionally looks for something that is not
 *    in the payload.
 */
$openapi = (string)file_get_contents(
    'packages/web/lib/fog/openapi.class.php'
);
if (!preg_match('#case \'storagenode\':.*?expand=all#s', $openapi)) {
    $fails[] = 'OpenAPI no longer records that storagenode images and'
        . ' snapinfiles are expand-only';
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: storage node file listings are opt-in\n";
exit(0);
