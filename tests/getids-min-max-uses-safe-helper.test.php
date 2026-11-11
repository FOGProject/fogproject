<?php
/**
 * min() and max() may not be applied directly to Route::getIds().
 *
 * Route::getIds() returns an empty array when the table has no rows. On PHP 8,
 * min([]) and max([]) throw ValueError, and the `@` operator does not suppress
 * an exception, so `@min(Route::getIds('storagegroup', false))` turned the
 * image, snapin and storage node add and save paths into a blank 500 on an
 * install with no storage group (GH-2068). On PHP 7.4 the same call gave a
 * warning and `false`.
 *
 * FOGBase::minId() and FOGBase::maxId() exist for exactly this: they return 0
 * for an empty collection and min()/max() otherwise. This gate stops a direct
 * call growing back.
 *
 * Scope is deliberately narrow: only a min( or max( whose first argument is
 * Route::getIds( is refused. A min()/max() over some other list is not this
 * gate's business; several of those already guard against the empty case.
 *
 * The match spans whitespace, newlines included, so a call wrapped across
 * lines is caught as well as a one-line one.
 *
 * Usage: php tests/getids-min-max-uses-safe-helper.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

/*
 * Below this and the scan is not scanning anything -- a broken file filter
 * would otherwise report a clean pass.
 */
const MIN_FILES = 100;

/*
 * `@` is optional, the word boundary keeps minId(/maxId( and any longer name
 * out, and \s spans newlines.
 */
const PATTERN = '/@?\b(?:min|max)\s*\(\s*Route::getIds\s*\(/';

$fail = false;

// The matcher checks itself first, so a pattern that silently matches nothing
// cannot pass the tree scan below.
$selfTests = [
    ["\$sgID = @min(Route::getIds('storagegroup', false));", true],
    ["\$id = min(\n    Route::getIds('storagegroup', false)\n);", true],
    ["\$sgID = self::minId(Route::getIds('storagegroup', false));", false],
];
foreach ($selfTests as list($code, $want)) {
    $got = 1 === preg_match(PATTERN, $code);
    if ($got !== $want) {
        fwrite(
            STDERR,
            'FAIL: matcher ' . ($want ? 'missed' : 'wrongly matched') . ': '
            . json_encode($code) . "\n"
        );
        $fail = true;
    }
}

$files = array_filter(
    explode("\n", (string) shell_exec('git ls-files "packages/web/src/*.php"')),
    function ($f) {
        return '' !== $f && is_readable($f);
    }
);

if (count($files) < MIN_FILES) {
    fwrite(
        STDERR,
        'FAIL: only ' . count($files) . ' file(s) found under packages/web/src, '
        . 'expected at least ' . MIN_FILES . ". The scan is not scanning.\n"
    );
    $fail = true;
}

$hits = [];
foreach ($files as $file) {
    $src = file_get_contents($file);
    if (!preg_match_all(PATTERN, $src, $m, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($m[0] as list($text, $offset)) {
        $line = substr_count($src, "\n", 0, $offset) + 1;
        $hits[] = $file . ':' . $line . ': '
            . preg_replace('/\s+/', ' ', $text);
    }
}

if ($hits !== []) {
    fwrite(
        STDERR,
        'FAIL: ' . count($hits) . ' min()/max() call(s) applied directly to '
        . "Route::getIds(); use self::minId() or self::maxId():\n"
    );
    foreach ($hits as $hit) {
        fwrite(STDERR, "  $hit\n");
    }
    $fail = true;
}

if ($fail) {
    exit(1);
}

printf(
    "ok: no min()/max() applied directly to Route::getIds() in %d file(s)\n",
    count($files)
);
exit(0);
