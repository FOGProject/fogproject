<?php
/**
 * Every daemon entry point is a stub, and the class it names can actually run.
 *
 * The ten files under packages/service/<Name>/<Name> are named for their
 * systemd unit, so they carry no extension -- which puts them outside every
 * `*.php` filter in this tree. PHPStan's `paths` skips them, and until GH-1498
 * so did bin/import-core-classes.php and
 * tests/no-bare-core-references.test.php. That is how the PSR-4 sweep left all
 * ten with a bare `FOGCore::niceDate()` and every daemon died one loop
 * iteration in while systemd reported the unit active.
 *
 * The fix was to stop keeping logic there at all: the service loop moved to
 * FOGService::run(), which lives under packages/web/src/ where the analyzer,
 * the sweeper and copybacktrunk.sh all reach it. What is left in each entry
 * point is the part that genuinely must be an executable file outside the
 * webroot, because ExecStart points at it and eight of the ten units run as
 * root while the webroot is writable by the web user.
 *
 * So this file pins the ARRANGEMENT, which is the thing that stops the bug
 * coming back:
 *
 *   1. each entry point hands off to \FOG\Base\FOGCore::getClass('X')->run()
 *      -- fully qualified, because an import is a thing to forget and the
 *      sweeper cannot see this file to add one;
 *   2. class X exists under src/Service/ and has a run(), its own or
 *      inherited;
 *   3. the entry point holds no loop and no second statement of substance.
 *      This is the anti-regrowth assertion: logic that reappears here is
 *      logic nothing analyzes.
 *
 * Usage: php tests/daemon-entry-points-are-stubs.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$serviceDir = $repo . '/packages/service';
$classDir = $repo . '/packages/web/src/Service';

/**
 * Results are accumulated rather than counted through `global $pass/$fail`,
 * which the other suites use. Two reasons: a global mutated inside a helper
 * is invisible to static analysis, so PHPStan reads the final `$fail > 0` as
 * a comparison between 0 and 0 that is always false -- i.e. it can prove the
 * failure branch is dead, which for a test file is precisely the wrong thing
 * to be true. Keeping the tallies local means the analyzer can see them, and
 * the check at the foot is a real one.
 */
$oks = [];
$fails = [];

/**
 * Does $class, or anything it extends inside src/Service/, declare run()?
 *
 * Walks the chain rather than assuming a depth: ImageReplicator extends
 * FOGReplicator extends FOGService, and ImageSize goes through
 * FOGItemScanner. Hard-coding "own file or FOGService.php" would pass today
 * and quietly stop meaning anything the moment somebody puts run() on an
 * intermediate.
 */
function declaresRun(string $classDir, string $class, array $seen = []): bool
{
    if (isset($seen[$class]) || !is_file($classDir . '/' . $class . '.php')) {
        return false;
    }
    $seen[$class] = true;
    $src = (string)file_get_contents($classDir . '/' . $class . '.php');
    if (preg_match('/\bfunction\s+run\s*\(/', $src)) {
        return true;
    }
    if (preg_match('/\bclass\s+\w+\s+extends\s+(\w+)/', $src, $m)) {
        return declaresRun($classDir, $m[1], $seen);
    }
    return false;
}

$entries = glob($serviceDir . '/FOG*/FOG*');
sort($entries);

if (count($entries) < 10) {
    fwrite(
        STDERR,
        'FAIL: found ' . count($entries) . " daemon entry points, expected at"
        . " least 10. The scan is looking in the wrong place, so this test"
        . " would pass by measuring nothing.\n"
    );
    exit(1);
}

echo "1. each entry point hands off to a runnable service class\n";
foreach ($entries as $path) {
    $rel = str_replace($repo . '/', '', $path);
    $src = (string)file_get_contents($path);

    if (!preg_match(
        '/\\\\FOG\\\\Base\\\\FOGCore::getClass\(\s*\'(\w+)\'\s*\)->run\(\)/',
        $src,
        $m
    )) {
        $fails[] = ("$rel does not hand off via \\FOG\\Base\\FOGCore::getClass('X')->run()");
        continue;
    }
    $class = $m[1];

    if (!is_file($classDir . '/' . $class . '.php')) {
        $fails[] = "$rel names $class, which src/Service/ does not declare";
        continue;
    }
    if (!declaresRun($classDir, $class)) {
        $fails[] = "$rel names $class, which has no run() in its class chain";
        continue;
    }
    $oks[] = "$rel -> $class::run()";
}

echo "\n2. no entry point has grown logic back\n";
foreach ($entries as $path) {
    $rel = str_replace($repo . '/', '', $path);
    $src = (string)file_get_contents($path);

    // Tokenized: `while` inside the file docblock prose is not a loop, and
    // these files carry a lot of prose.
    $loops = 0;
    foreach (token_get_all($src) as $t) {
        if (is_array($t)
            && in_array($t[0], [T_WHILE, T_FOR, T_FOREACH, T_DO], true)
        ) {
            $loops++;
        }
    }
    if ($loops > 0) {
        $fails[] = "$rel contains $loops loop(s) -- the service loop belongs in src/Service/";
        continue;
    }

    // Exactly one ->run(). More than one means something is being sequenced
    // here rather than inside the class.
    $runs = preg_match_all('/->run\(\)/', $src);
    if (1 !== $runs) {
        $fails[] = "$rel calls ->run() $runs times, expected exactly 1";
        continue;
    }
    $oks[] = "$rel is still a stub (no loops, one hand-off)";
}

foreach ($oks as $m) {
    echo "  ok    $m\n";
}
foreach ($fails as $m) {
    echo "  FAIL  $m\n";
}
echo "\n";
if ([] !== $fails) {
    echo 'FAIL (' . count($fails) . ' of '
        . (count($oks) + count($fails)) . " assertions)\n";
    exit(1);
}
echo 'PASS (' . count($oks) . " assertions)\n";
