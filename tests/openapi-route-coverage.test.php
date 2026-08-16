<?php
/**
 * Every route the router serves must appear in the OpenAPI document.
 *
 * The document is generated per request, which reads as "it keeps itself
 * current" and is only half true. Class lists, model fields and permissions
 * are read live; the shape of every operation is hand-written, so a route
 * that is not the generic CRUD shape is served and undescribed unless
 * someone edits openapi.class.php in the same breath. Nothing caught that,
 * and two upload routes sat undescribed from May to August as a result.
 *
 * The cost lands on a caller, not on us: a client generated from the
 * document simply has no method for the missing endpoint, and the
 * reasonable conclusion is that the server cannot do it.
 *
 * Checks both directions. A route served and not described is the case
 * above. A route described and not served is a client generated against a
 * method that 404s, which is the worse of the two.
 *
 * Source-level on purpose. Calling OpenAPI::document() would need a
 * database, a loaded config and a settings row, which is the opposite of
 * what these tests are (docs/adr/0008-secure-boot-enrolment-task-type.md:103).
 * The names being compared are literals in both files, so reading them is
 * enough, and the parser self-check below fails if that stops being true.
 *
 * Usage: php tests/openapi-route-coverage.test.php
 * Exit status 0 = pass, 1 = fail.
 */

/**
 * Routes deliberately left out of the document, and why.
 *
 * The reason is the point of this list. It turns "undocumented" from
 * something nobody noticed into something somebody signed, in a diff a
 * reviewer sees. A entry added without a real reason should not survive
 * review, and a list that grows past a couple of entries is telling you
 * the document has stopped being trustworthy rather than that the list
 * needs to be longer.
 *
 * Stale entries fail too -- a route that no longer exists, or that has
 * since been documented, has to be removed rather than left to rot.
 */
const ALLOWED = [
    'openapiSwaggerAlias' =>
        'The /swagger.json alias of /system/openapi. Same handler, same '
        . 'document, and the canonical path says so in its own description. '
        . 'A second path would duplicate the operation rather than describe '
        . 'anything new -- the same reason /list and /all, /create and /new '
        . 'are collapsed to one documented spelling each.',
];

$root = dirname(__DIR__);
$routeFile = $root . '/packages/web/lib/router/route.class.php';
$specFile = $root . '/packages/web/lib/fog/openapi.class.php';

foreach ([$routeFile, $specFile] as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, "FAIL: cannot read $file\n");
        exit(1);
    }
}

$routeSrc = (string)file_get_contents($routeFile);
$specSrc = (string)file_get_contents($specFile);

// Only defineRoutes(), so a handler array written anywhere else in the
// class cannot be mistaken for a route registration.
$start = strpos($routeSrc, 'protected static function defineRoutes()');
if (false === $start) {
    fwrite(STDERR, "FAIL: could not find Route::defineRoutes()\n");
    exit(1);
}
$end = strpos($routeSrc, "\n    }", $start);
$body = substr($routeSrc, $start, false === $end ? null : $end - $start);

// Each registration is ->verb('path', [__CLASS__, 'handler'], 'name').
preg_match_all(
    '/\[__CLASS__,\s*\'\w+\'\]\s*,\s*\'(\w+)\'/',
    $body,
    $m
);
$served = array_values(array_unique($m[1]));

// The parser is the weak point in a source-level test: a route registered
// in some other shape would be silently skipped, and the test would pass
// by not looking. Every registration carries exactly one handler array, so
// the two counts have to agree.
$handlers = substr_count($body, '[__CLASS__,');
if ($handlers !== count($m[1])) {
    fwrite(STDERR, "FAIL: parser missed a route registration\n");
    fwrite(STDERR, "  handler arrays: $handlers, names read: " . count($m[1]) . "\n");
    fwrite(STDERR, "  A route is probably registered in a shape this test does\n");
    fwrite(STDERR, "  not recognise. Fix the pattern here, not the router.\n");
    exit(1);
}
if (count($served) < 1) {
    fwrite(STDERR, "FAIL: read no routes at all from defineRoutes()\n");
    exit(1);
}

// _op()'s second argument is the router's name for the operation. The
// first is either a $class variable or a literal '' for the fixed paths.
preg_match_all(
    '/self::_op\(\s*(?:\$class|\'[^\']*\')\s*,\s*\'(\w+)\'/',
    $specSrc,
    $m
);
$described = array_values(array_unique($m[1]));
if (count($described) < 1) {
    fwrite(STDERR, "FAIL: read no operations at all from the generator\n");
    exit(1);
}

$undocumented = array_diff($served, $described, array_keys(ALLOWED));
$phantom = array_diff($described, $served);
$staleAllows = array_diff(array_keys(ALLOWED), $served);
$pointlessAllows = array_intersect(array_keys(ALLOWED), $described);

$failed = false;

if (count($undocumented) > 0) {
    $failed = true;
    fwrite(STDERR, "FAIL: served but not described in the document:\n");
    foreach ($undocumented as $name) {
        fwrite(STDERR, "  $name\n");
    }
    fwrite(STDERR, "  Describe it in OpenAPI::_classPaths() or _fixedPaths(),\n");
    fwrite(STDERR, "  or add it to ALLOWED here with the reason it stays out.\n");
}

if (count($phantom) > 0) {
    $failed = true;
    fwrite(STDERR, "FAIL: described but not served by the router:\n");
    foreach ($phantom as $name) {
        fwrite(STDERR, "  $name\n");
    }
    fwrite(STDERR, "  A client generated from this document would call an\n");
    fwrite(STDERR, "  endpoint that does not exist.\n");
}

if (count($staleAllows) > 0) {
    $failed = true;
    fwrite(STDERR, "FAIL: ALLOWED names no route serves any more:\n");
    foreach ($staleAllows as $name) {
        fwrite(STDERR, "  $name\n");
    }
}

if (count($pointlessAllows) > 0) {
    $failed = true;
    fwrite(STDERR, "FAIL: ALLOWED names that are now described anyway:\n");
    foreach ($pointlessAllows as $name) {
        fwrite(STDERR, "  $name\n");
    }
    fwrite(STDERR, "  Remove them from ALLOWED.\n");
}

if ($failed) {
    exit(1);
}

printf(
    "ok: %d routes served, %d described, %d deliberately not (%s)\n",
    count($served),
    count($described),
    count(ALLOWED),
    implode(', ', array_keys(ALLOWED))
);
exit(0);
