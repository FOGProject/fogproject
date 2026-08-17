<?php
/**
 * The `PHP version` line in file docblocks must match the version FOG enforces.
 *
 * 304 files said "PHP version 5" -- inherited from the era when that was true
 * and never revisited, while Initiator::_verCheck() has required 7.4 for
 * years. Nothing breaks, which is exactly why it sat there: the only readers
 * are contributors, who were being told the wrong floor by every file they
 * opened.
 *
 * The floor is read out of _verCheck() rather than hardcoded here, so raising
 * it fails this test until the docblocks follow. That is the point -- the next
 * bump should not be able to leave 300 files lying again.
 *
 * vendor/ is exempt: third-party packages state their own requirements.
 *
 * Usage: php tests/php-version-docblocks.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

// The enforced floor, taken from the check that actually rejects a request.
$init = (string) @file_get_contents('packages/web/commons/init.php');
if (!preg_match("/version_compare\(phpversion\(\), '([0-9.]+)', '<'\)/", $init, $m)) {
    fwrite(STDERR, "FAIL: cannot find the version floor in Initiator::_verCheck()\n");
    exit(1);
}
$floor = $m[1];
$expected = "PHP version $floor+";

$files = explode("\n", trim((string) shell_exec('git ls-files "*.php"')));
$fails = [];
foreach ($files as $file) {
    if ('' === $file || 0 === strpos($file, 'packages/web/vendor/')) {
        continue;
    }
    $n = 0;
    foreach (explode("\n", (string) @file_get_contents($file)) as $i => $line) {
        // Only the docblock form: " * PHP version X". Prose that happens to
        // begin that way (mysqldump's "PHP version of ... cli") is not a
        // version claim and must not be rewritten into one.
        if (!preg_match('/^\s*\*\s*PHP [Vv]ersion\s+[0-9]/', $line)) {
            continue;
        }
        ++$n;
        if (trim(preg_replace('/^\s*\*\s*/', '', $line)) !== $expected) {
            $fails[] = "$file:" . ($i + 1) . ' says "'
                . trim(preg_replace('/^\s*\*\s*/', '', $line))
                . "\", but FOG enforces $floor -- expected \"$expected\"";
        }
    }
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " docblock(s) state the wrong PHP floor:\n");
    foreach (array_slice($fails, 0, 20) as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    if (count($fails) > 20) {
        fwrite(STDERR, '  ... and ' . (count($fails) - 20) . " more\n");
    }
    exit(1);
}

echo "ok: every PHP version docblock states the enforced floor ($floor)\n";
exit(0);
