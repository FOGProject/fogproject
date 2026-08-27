<?php
/**
 * Holds the line on Phase 3's one genuinely dangerous bug class: a class
 * name that is *produced* and then used as data.
 *
 * PHP resolves a class *reference* whether it is namespaced or not, and the
 * compatibility aliases Phase 3 adds mean `new Host` keeps working after
 * Host becomes FOG\Items\Host. So every consumer of a class name is safe. What is
 * not safe is the other direction: get_class(), __CLASS__ and ::class report
 * the DECLARED name, never the alias, so the moment a class moves into a
 * namespace every one of them starts returning 'FOG\Items\Host'.
 *
 * FOG uses those strings as data in at least five places -- a database
 * column name, a switch case, an HTML name= attribute, a Route::$validClasses
 * lookup, and the text written to the history table. In all five the prefix
 * is silently wrong: no error, no warning, just a query against a column that
 * does not exist or a switch that falls through to its default. Two of those
 * defaults are load-bearing. EventManager::register() throws on its default
 * and the throw is caught and merely logged, so no hook and no event would
 * register at all; Authorization::assertAdminRemainsAfterDelete() returns on
 * its default, so the last-administrator lockout guard would stop running.
 *
 * FOGBase::shortName() is the fix, and it is a no-op on a tree with no
 * namespaces -- which is exactly why the derivation sites can be corrected
 * and shipped ahead of the namespacing itself. This test is what stops them
 * regressing in between, and what stops a newly written site from skipping
 * the helper.
 *
 * The rule: any get_class() or __CLASS__ outside vendor/ and tests/ must
 * either pass through shortName(), or carry a `class-name consumer` comment
 * within the three lines above it saying why the raw name is fine.
 *
 * Two things deliberately out of scope. `[__CLASS__, 'method']` callables
 * (about fifty of them, from Route's AltoRouter wiring) are consumers by
 * construction. `self::class` is likewise only ever used here as a callable
 * or a getClass() argument; if that changes, widen NEEDLES.
 *
 * Usage: php tests/class-name-derivation.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGBase;

$root = dirname(__DIR__);
chdir($root);

/** Tokens that produce a class name as a string. */
const NEEDLES = ['get_class(', '__CLASS__'];

/** Comment marker that opts a site out, with a reason. */
const CONSUMER = 'class-name consumer';

/** How many lines above a site the marker may appear. */
const MARKER_WINDOW = 3;

/*
 * Below this and the test is not testing anything. The commit that
 * introduced shortName() left 35 call sites; if a refactor drops most of
 * them the scan would pass vacuously, so require the bulk to still be there.
 */
const MIN_CONVERTED = 25;

$files = array_filter(
    explode("\n", (string) shell_exec('git ls-files "*.php"')),
    function ($f) {
        if ('' === $f || !is_readable($f)) {
            return false;
        }
        return 0 !== strpos($f, 'packages/web/vendor/')
            && 0 !== strpos($f, 'tests/');
    }
);

if (count($files) < 100) {
    fwrite(STDERR, "FAIL: only " . count($files) . " files scanned; "
        . "expected the whole tree. Is this a git checkout?\n");
    exit(1);
}

$unguarded = [];
$converted = 0;
$consumers = 0;

foreach ($files as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $n => $line) {
        $trimmed = ltrim($line);
        // A comment mentioning get_class() is documentation, not a call.
        if ('' === $trimmed
            || 0 === strpos($trimmed, '//')
            || 0 === strpos($trimmed, '*')
            || 0 === strpos($trimmed, '/*')
        ) {
            continue;
        }
        $hit = false;
        foreach (NEEDLES as $needle) {
            if (false !== strpos($line, $needle)) {
                $hit = true;
            }
        }
        if (!$hit) {
            continue;
        }
        // [__CLASS__, 'method'] is a callable, not a produced string.
        if (false !== strpos($line, '__CLASS__,')
            && false === strpos($line, 'get_class(')
        ) {
            continue;
        }
        // Already wrapped -- e.g. self::shortName(__CLASS__). Counted below.
        if (false !== strpos($line, 'shortName(')) {
            continue;
        }
        // shortName() is itself the one legitimate raw caller.
        if ('packages/web/src/Base/FOGBase.php' === $file
            && false !== strpos($line, 'is_object($class) ? get_class($class)')
        ) {
            continue;
        }
        $marked = false;
        for ($back = 1; $back <= MARKER_WINDOW; $back++) {
            if (isset($lines[$n - $back])
                && false !== strpos($lines[$n - $back], CONSUMER)
            ) {
                $marked = true;
                break;
            }
        }
        if ($marked) {
            $consumers++;
            continue;
        }
        $unguarded[] = sprintf('%s:%d  %s', $file, $n + 1, $trimmed);
    }
    /*
     * Counted in its own pass, not alongside the needles above: a converted
     * site no longer contains the string `get_class(` at all, which is the
     * whole point of the conversion. The declaration itself is excluded so
     * that deleting every call site cannot still satisfy the floor.
     */
    foreach ($lines as $line) {
        if (false === strpos($line, 'shortName(')) {
            continue;
        }
        if (false !== strpos($line, 'function shortName(')) {
            continue;
        }
        $converted++;
    }
}

$fail = false;

if (count($unguarded) > 0) {
    fwrite(
        STDERR,
        "FAIL: " . count($unguarded) . " class-name derivation(s) neither "
        . "go through FOGBase::shortName() nor carry a '" . CONSUMER
        . "' comment:\n"
    );
    foreach ($unguarded as $u) {
        fwrite(STDERR, "  $u\n");
    }
    fwrite(
        STDERR,
        "\nIf the string is used as data (a column name, a switch case, an\n"
        . "attribute, log text), wrap it: self::shortName(\$this).\n"
        . "If it is handed back to PHP as a class reference, say so with a\n"
        . "'" . CONSUMER . "' comment above it.\n"
    );
    $fail = true;
}

if ($converted < MIN_CONVERTED) {
    fwrite(
        STDERR,
        "FAIL: only $converted shortName() derivation site(s) found, expected "
        . "at least " . MIN_CONVERTED . ". The scan would pass vacuously.\n"
    );
    $fail = true;
}

// The helper's own behaviour, on the two shapes every call site uses.
require_once $root . '/packages/web/src/Base/FOGBase.php';

$cases = [
    ['FOG\\Items\\Host', 'Host', 'namespaced name'],
    ['Host', 'Host', 'global name (today\'s tree)'],
    ['FOG\\Deep\\Ns\\Host', 'Host', 'nested namespace'],
    ['\\Host', 'Host', 'leading separator'],
    ['', '', 'empty string'],
];
foreach ($cases as $case) {
    list($in, $want, $label) = $case;
    $got = FOGBase::shortName($in);
    if ($got !== $want) {
        fwrite(
            STDERR,
            "FAIL: shortName($label): expected '$want', got '$got'\n"
        );
        $fail = true;
    }
}

$obj = new \stdClass();
if ('stdClass' !== FOGBase::shortName($obj)) {
    fwrite(STDERR, "FAIL: shortName() does not accept an object\n");
    $fail = true;
}

if ($fail) {
    exit(1);
}

printf(
    "ok: %d derivation site(s) via shortName(), %d marked consumer(s), "
    . "%d file(s) scanned, 6 behaviour case(s)\n",
    $converted,
    $consumers,
    count($files)
);
exit(0);
