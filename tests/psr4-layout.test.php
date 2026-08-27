<?php
/**
 * Every FOG class has exactly one PSR-4 home, and the taxonomy stays total.
 *
 * Wires bin/psr4-scan.php --check into the suite, the same arrangement
 * tests/namespaced-tree.test.php uses for bin/namespace-fog-classes.php: the
 * tool owns the rules, this makes a regression fail a commit.
 *
 * It is NOT a migration gate that goes away once the move lands. The scan
 * reads lib/ and src/ together and reports a class already at its target as
 * done, so this keeps asserting the same four invariants afterwards -- and
 * the one that matters most only bites afterwards, when someone adds a class:
 *
 *   1. One type per file. PSR-4 can address only the first; a second resolves
 *      as a side effect of loading the first, which is how mysqldump.class.php
 *      used to declare thirteen.
 *   2. Every class has a chosen bucket. A new class matching no derivation
 *      rule and named in no table FAILS rather than defaulting somewhere, so
 *      the taxonomy cannot silently acquire a junk drawer.
 *   3. No two files declare one class name.
 *   4. No two class names fold to one basename. The reverse bridge keys its
 *      lowercase map on exactly that, and Move 1's multi-root map resolves a
 *      tie by root order -- readdir order in a different hat, which is the
 *      failure tests/autoload-core-wins.test.php exists for.
 *
 * Each of the five refusal arms was proven by mutation before this was
 * committed, and the first one earned its keep immediately: renaming Timer to
 * Ping left --check GREEN, because $declared is keyed by class name and the
 * second file simply overwrote the first. The only tell was the reported
 * total dropping from 202 to 201. That check now runs where the map is built
 * rather than where targets are compared.
 *
 * DB-free: reads the tree.
 *
 * Usage: php tests/psr4-layout.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$tool = $root . '/bin/psr4-scan.php';

if (!is_readable($tool)) {
    fwrite(STDERR, "FAIL: missing $tool\n");
    exit(1);
}

$cmd = sprintf(
    '%s %s --check 2>&1',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($tool)
);
exec($cmd, $output, $status);

if (0 !== $status) {
    fwrite(STDERR, "FAIL: the PSR-4 layout has a problem.\n");
    foreach ($output as $line) {
        fwrite(STDERR, "  $line\n");
    }
    fwrite(
        STDERR,
        "\nSee docs/composer-psr4-plan.md. A new class needs a home: either it\n"
        . "extends something RULES already places, or it goes in TABLE in\n"
        . "bin/psr4-scan.php with the reason it belongs there.\n"
    );
    exit(1);
}

/*
 * Below this and the scan is not scanning. A tool that silently found no
 * files would exit 0 and report "ok: 0 class(es)", which reads as a pass.
 */
$line = trim(implode(' ', $output));
if (!preg_match('/ok: (\d+) class\(es\)/', $line, $m) || (int) $m[1] < 200) {
    fwrite(STDERR, "FAIL: expected the whole tree, got: $line\n");
    exit(1);
}

echo $line . "\n";
exit(0);
