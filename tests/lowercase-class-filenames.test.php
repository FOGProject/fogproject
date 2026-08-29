<?php
/**
 * Every autoloadable class file ships with an all-lowercase basename.
 *
 * The installer force-lowercases class filenames on an --oldcopy upgrade
 * (`configureHttpd()` in lib/common/functions.sh), and it does so on the tree
 * restored from the backup -- before the new tree is copied over it. So a
 * shipped file whose basename contains an uppercase letter reappears beside
 * the lowercased copy the rename just produced, and the server carries two
 * files for one class.
 *
 * Nothing errors. Initiator keys its class map on the lowercased basename
 * stem and looks it up by the lowercased class name, so both files claim the
 * same key and which one wins is decided by directory read order -- per
 * install, and silently. The loser is a whole release behind. GH-1136, and
 * the same shape as the hook class-name collision in GH-1024.
 *
 * The convention is not new; 390-odd files already follow it. What was
 * missing is anything that says so, which is how FOGPagePost.class.php,
 * FOGPageRender.class.php and UserGroupManagement.page.php got in.
 *
 * Case is irrelevant to resolution either way -- the autoloader lowercases
 * both sides -- so this costs nothing to honor.
 *
 * Scope note: PSR-4 files under packages/web/src/ and packages/web/vendor/
 * are `Foo.php`, not `Foo.class.php`, so they match none of these suffixes
 * and are correctly untouched by both the installer loop and this test.
 *
 * DB-free: walks the tree.
 *
 * Usage: php tests/lowercase-class-filenames.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';

if (!is_dir($webroot)) {
    fwrite(STDERR, "FAIL: cannot read $webroot\n");
    exit(1);
}

// The five suffixes Initiator autoloads on. The installer's rename sweep
// must cover exactly these, and this list is the other half of that pair.
$suffixes = [
    '.class.php',
    '.event.php',
    '.hook.php',
    '.page.php',
    '.report.php',
];

$checks = 0;
$failures = [];

$iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($webroot, \FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $name = $file->getFilename();
    $matched = false;
    foreach ($suffixes as $suffix) {
        if (substr($name, -strlen($suffix)) === $suffix) {
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        continue;
    }
    $checks++;
    if ($name !== strtolower($name)) {
        $rel = str_replace(dirname(__DIR__) . '/', '', $file->getPathname());
        $failures[] = $rel . ' -- rename to ' . strtolower($name);
    }
}

if (0 === $checks) {
    fwrite(STDERR, "FAIL: found no class files to check; layout changed?\n");
    exit(1);
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        "An uppercase basename collides with its own lowercased copy after\n"
        . "an --oldcopy upgrade; the autoloader then picks between them by\n"
        . "directory read order. See GH-1136.\n"
    );
    exit(1);
}

echo "ok  $checks class filenames lowercase\n";
exit(0);
