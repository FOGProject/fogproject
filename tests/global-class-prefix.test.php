<?php
/**
 * Guards the Phase 0.1 invariant: no bare references to PHP's built-in
 * classes remain in tracked PHP.
 *
 * Inside `namespace FOG;` an unqualified `catch (Exception $e)` means
 * FOG\Exception, which does not exist, so the catch stops matching -- silently,
 * at runtime, on the error path only. Phase 3 is safe from that only for as
 * long as every such reference stays qualified, so this test exists to fail
 * when a new bare one is written.
 *
 * Wraps bin/prefix-global-classes.php --check, which does the actual work.
 *
 * Usage: php tests/global-class-prefix.test.php
 * Exit status 0 = pass, 1 = fail.
 */

// Zero, and it stays zero. The previous commit set this to 729 and passed,
// which is how we know the assertion has teeth rather than being green
// because the checker is broken.
const EXPECT_SITES = 0;

$tool = dirname(__DIR__) . '/bin/prefix-global-classes.php';

if (!is_readable($tool)) {
    fwrite(STDERR, "FAIL: cannot read $tool\n");
    exit(1);
}

exec('php ' . escapeshellarg($tool) . ' --check 2>&1', $out, $rc);

$summary = array_pop($out);
while ($summary !== null && trim($summary) === '') {
    $summary = array_pop($out);
}

if (!preg_match('/^unprefixed: (\d+) sites in (\d+) files/', (string)$summary, $m)) {
    fwrite(STDERR, "FAIL: could not read the checker's summary line\n");
    fwrite(STDERR, "  got: " . var_export($summary, true) . "\n");
    exit(1);
}

$sites = (int)$m[1];

if ($sites !== EXPECT_SITES) {
    fwrite(
        STDERR,
        sprintf(
            "FAIL: %d bare built-in class references, expected %d\n",
            $sites,
            EXPECT_SITES
        )
    );
    // Show a sample rather than all of them.
    foreach (array_slice($out, 0, 20) as $line) {
        fwrite(STDERR, "  $line\n");
    }
    if (count($out) > 20) {
        fwrite(STDERR, '  ... and ' . (count($out) - 20) . " more\n");
    }
    exit(1);
}

printf("global-class-prefix: %d sites (expected %d)\n", $sites, EXPECT_SITES);
echo "PASS\n";
exit(0);
