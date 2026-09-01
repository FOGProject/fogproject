<?php
/**
 * Pins both snapin-tasking paths to the resolver.
 *
 * ADR 0038 decision 4: the ordered snapin list is computed WHEN A TASK IS
 * CREATED and written onto the task. There are two places that create snapin
 * tasking -- Host::_createSnapinTasking() for one host, and
 * Group::_createSnapinTasking() for a whole membership -- and the defect this
 * guards against is one of them quietly going back to reading a host's own
 * associations. That is not a crash. It is a group grant that applies to
 * every host tasked one way and to none tasked the other, which looks like
 * flaky snapins and is diagnosed by nobody.
 *
 * WHAT THIS IS AND IS NOT. This asserts on SOURCE TEXT, which this suite
 * normally refuses to do, so the reason has to be better than convenience.
 * The resolver's BEHAVIOR -- the order, the dedupe, the throw -- is covered
 * behaviorally and at length by tests/assign-resolver.test.php against a real
 * database. What is left over is exactly the question source text can answer
 * and a unit test cannot reach without standing up a configured FOG: does
 * this call site still call it. Constructing a Host far enough to reach
 * _createSnapinTasking() needs a live config, a task, a snapin job and a
 * schema, at which point the test is an install rehearsal rather than a gate.
 *
 * So the assertions anchor WHOLE STATEMENTS rather than grepping for the
 * class name. A grep for "Resolver" passes against a resolver call sitting
 * dead above the old association read; anchoring the assignment and the
 * absence of the old read together does not.
 *
 * Usage: php tests/task-creation-resolves-assignments.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$root = dirname(__DIR__);
$src = $root . '/packages/web/src';

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

$read = static function ($path) {
    $body = @file_get_contents($path);
    if (false === $body) {
        fwrite(STDERR, "FAIL: cannot read $path\n");
        exit(1);
    }
    return (string)$body;
};

$group = $read($src . '/Items/Group.php');
$host = $read($src . '/Items/Host.php');

/**
 * Returns just the body of a method, so an assertion about "this method does
 * not read snapinAssoc" cannot be satisfied or broken by an unrelated method
 * in the same file that legitimately does.
 */
$method = static function ($source, $signature) {
    $start = strpos($source, $signature);
    if (false === $start) {
        return null;
    }
    $open = strpos($source, '{', $start);
    if (false === $open) {
        return null;
    }
    $depth = 0;
    $len = strlen($source);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $source[$i]) {
            $depth++;
        } elseif ('}' === $source[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($source, $open, $i - $open + 1);
            }
        }
    }
    return null;
};

$groupBody = $method($group, 'private function _createSnapinTasking(');
$hostBody = $method($host, 'private function _createSnapinTasking(');

$check(
    'Group::_createSnapinTasking() is still findable',
    null !== $groupBody
);
$check(
    'Host::_createSnapinTasking() is still findable',
    null !== $hostBody
);
if (null === $groupBody || null === $hostBody) {
    fwrite(STDERR, "FAIL: a tasking method moved or was renamed; this test is\n");
    fwrite(STDERR, "      pinning nothing until its signatures are updated.\n");
    exit(1);
}

// The group path. The whole assignment is anchored, not the class name: a
// resolver call whose result is thrown away would satisfy a looser check.
$check(
    'the group path resolves the whole membership in one call',
    false !== strpos(
        $groupBody,
        '$assocByHost = Resolver::resolveSnapins($hostIDs);'
    )
);
// ...and it must pass the SET. A resolver called per host inside the loop is
// GH-707 returning: a thousand round trips for a thousand-host group. That
// is why the signature takes an array, and why this is worth its own check.
$check(
    'the group path does not resolve one host at a time',
    false === strpos($groupBody, 'resolveSnapins([$hostID]')
);
$check(
    'the group path no longer reads snapinassociation itself',
    false === stripos($groupBody, 'snapinassociation')
);

// The host path. Resolved once into a local and reused, rather than read
// twice: the emptiness guard and the id list must agree, and they only
// certainly agree if they are the same value.
$check(
    'the host path resolves through the resolver',
    false !== strpos(
        $hostBody,
        '$resolved = Resolver::resolveSnapins([$hostID])[$hostID] ?? [];'
    )
);
$check(
    'the host path tasks what it resolved',
    false !== strpos($hostBody, '$snapin = $resolved;')
);
$check(
    'the host path no longer tasks the raw snapins property',
    false === strpos($hostBody, "\$snapin = \$this->get('snapins');")
);

// Both files must actually import it. A fully-qualified call would work and
// would also be the first sign somebody pasted the line in without meaning
// to, so the import is required rather than tolerated.
$check(
    'Group.php imports the resolver',
    false !== strpos($group, 'use FOG\Assign\Resolver;')
);
$check(
    'Host.php imports the resolver',
    false !== strpos($host, 'use FOG\Assign\Resolver;')
);

// The resolver has to exist under the name both files import, which is the
// one thing here that would otherwise be caught only at runtime, on a server,
// during a deploy.
$check(
    'the resolver is where those imports say it is',
    is_readable($src . '/Assign/Resolver.php')
        && false !== strpos(
            $read($src . '/Assign/Resolver.php'),
            'namespace FOG\Assign;'
        )
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: snapin tasking no longer resolves assignments:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  task creation resolves assignments: %d checks\n", $checks);
exit(0);
