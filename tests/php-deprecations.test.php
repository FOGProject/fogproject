<?php
/**
 * Deprecated PHP syntax that still parses, so nothing else catches it.
 *
 * Currently one rule: `"${var}"` string interpolation, deprecated in PHP 8.2
 * and slated for removal. It emits a runtime E_DEPRECATED per evaluation --
 * not per file, per *execution* -- so a deprecated interpolation inside a
 * router's route table or a boot-menu builder logs on every request. On an
 * install with display_errors on, it also lands in the output stream, which
 * is how a deprecation notice turns a JSON response into an unparseable one.
 *
 * `php -l` will not report it, php-cs-fixer's @PSR2 ruleset has no rule for
 * it, and it will keep parsing right up until the release that removes it.
 *
 * WHY THE TOKENIZER AND NOT A REGEX. FOG's tree is full of iPXE script text
 * held in SINGLE-quoted PHP strings:
 *
 *     'chain -ar ${boot-url}/service/ipxe/grub.efi'
 *     'set arch ${buildarch}'
 *
 * That is iPXE's own variable syntax. It does not interpolate, it must reach
 * the client exactly as written, and there are far more of those than there
 * ever were real deprecations. A regex for '${' corrupts every one of them.
 * The tokenizer only emits T_DOLLAR_OPEN_CURLY_BRACES inside a string that
 * genuinely interpolates, so it tells the two apart and a text search cannot.
 *
 * Usage: php tests/php-deprecations.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$files = array_filter(
    explode("\n", (string) shell_exec('git ls-files "*.php"')),
    function ($f) {
        return '' !== $f
            && is_readable($f)
            && 0 !== strpos($f, 'packages/web/vendor/');
    }
);

if (count($files) < 100) {
    fwrite(
        STDERR,
        'FAIL: only ' . count($files) . " files scanned; expected the whole "
        . "tree. Is this a git checkout?\n"
    );
    exit(1);
}

$hits = [];
foreach ($files as $file) {
    foreach (token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token) && T_DOLLAR_OPEN_CURLY_BRACES === $token[0]) {
            $hits[] = $file . ':' . $token[2];
        }
    }
}

if (count($hits) > 0) {
    fwrite(
        STDERR,
        'FAIL: ' . count($hits) . ' use(s) of "${var}" interpolation, '
        . "deprecated in PHP 8.2:\n"
    );
    foreach ($hits as $hit) {
        fwrite(STDERR, "  $hit\n");
    }
    fwrite(STDERR, "\nWrite \"{\$var}\" instead. Same value, no notice.\n");
    exit(1);
}

printf("ok: %d file(s), no deprecated \"\${var}\" interpolation\n", count($files));
exit(0);
