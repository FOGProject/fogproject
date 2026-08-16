<?php
/**
 * The FOG\ namespacing stays complete and stays correct.
 *
 * Two questions, both of which bin/namespace-fog-classes.php already answers
 * -- this wires its --check mode into the suite so a new file cannot quietly
 * regress either one.
 *
 *   1. Every class file declares `namespace FOG;` and aliases its own name
 *      back into the global namespace. Miss the alias and every existing
 *      caller of that class -- core, bundled plugins, third-party plugins --
 *      stops resolving.
 *
 *   2. No namespaced file references one of the four deliberately-global
 *      classes unqualified. This is the one that unit tests cannot otherwise
 *      see: `Initiator::e($x)` inside `namespace FOG;` resolves to
 *      FOG\Initiator, which does not exist and for Initiator never can --
 *      commons/init.php is not a *.class.php file, so it is not in the
 *      autoloader's map and no bridge reaches it. The tree passed all 26
 *      other tests, and resolved all 226 classes under both spellings, while
 *      being completely unable to render a page.
 *
 * Usage: php tests/namespaced-tree.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$tool = $root . '/bin/namespace-fog-classes.php';

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
    fwrite(STDERR, "FAIL: the tree is not fully namespaced.\n");
    foreach ($output as $line) {
        fwrite(STDERR, "  $line\n");
    }
    fwrite(
        STDERR,
        "\nRun: php bin/namespace-fog-classes.php --fix\n"
    );
    exit(1);
}

echo trim(implode(' ', $output)) . "\n";
exit(0);
