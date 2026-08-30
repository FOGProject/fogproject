<?php
/**
 * A regen push must SKIP the suite on that run, not race it.
 *
 * .github/workflows/tests.yml runs `regen` first. When it pushes a commit to
 * the pull request's head branch, that push raises `synchronize` and starts a
 * fresh run against the corrected tree -- so the current run's suite is
 * testing a tree that is already gone.
 *
 * The guard that stops it is `needs.regen.outputs.pushed != 'true'` on both
 * dependent jobs. It was described in the comment above `suite:` for weeks
 * and never written into the expression, which cost a full suite on
 * essentially every push: the suite started, regen's push superseded the run
 * mid-flight, and everything ran again on the bot's commit. The comment puts
 * the suite at 418 of a run's 447 runner-seconds.
 *
 * Both ends of the contract already existed -- fog-workflows'
 * fogproject-pr-regen.yml declares the `pushed` output and its description
 * says "The caller uses it to SKIP the test suite on this run". Only the
 * caller was missing.
 *
 * WHY THIS READS `if:` LINES RATHER THAN GREPPING THE FILE
 *
 * The comment above the guard QUOTES the guard, so a file-wide search for
 * `pushed != 'true'` passes on the documentation with the expression deleted
 * -- a gate that reads its own docs. Only lines that are actually an `if:`
 * key are considered here.
 *
 * WHAT MUST NOT BREAK
 *
 * `!cancelled()` has to stay. A `needs:` imposes an implicit success(), so
 * without it a regen that FAILED or was SKIPPED -- fork pull request, wrong
 * base, denylisted head, or ANY merge_group event, where regen's condition
 * reads a null github.event.pull_request -- would take the suite with it.
 * None of the required contexts would ever report and the pull request would
 * be unmergeable. On a merge_group that is precisely the hang that stranded
 * GH-1514 in the queue at AWAITING_CHECKS.
 *
 * The empty case is the one that carries that: regen skipped leaves `pushed`
 * unset, and '' != 'true' is TRUE, so the suite runs. The guard may only ever
 * suppress the suite when a push actually happened.
 *
 * Usage: php tests/regen-push-skips-the-suite.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$file = $repo . '/.github/workflows/tests.yml';
$src = is_file($file) ? (string)file_get_contents($file) : '';

// Only real `if:` keys -- never a comment that happens to quote one.
$conditions = [];
foreach (preg_split('#\r?\n#', $src) as $line) {
    if (preg_match('#^\s+if:\s*(.+?)\s*$#', $line, $m)) {
        $conditions[] = $m[1];
    }
}

// The two guarded jobs, by the condition they must carry. regen's own `if:`
// is a multi-line block scalar (`if: >-`) and so is not one of these.
$guarded = array_values(
    array_filter(
        $conditions,
        function ($c) {
            return false !== strpos($c, 'cancelled()');
        }
    )
);

$results = [];

$results[] = [
    '' !== $src,
    'the workflow is readable',
];

$results[] = [
    2 === count($guarded),
    'exactly two jobs hang off regen (suite and phpstan), found '
        . count($guarded),
];

// THE FIX. Both must actually carry the guard, in the expression rather than
// in the prose above it.
$withGuard = array_values(
    array_filter(
        $guarded,
        function ($c) {
            return (bool)preg_match(
                "#needs\.regen\.outputs\.pushed\s*!=\s*'true'#",
                $c
            );
        }
    )
);
$results[] = [
    count($guarded) > 0 && count($withGuard) === count($guarded),
    "both carry needs.regen.outputs.pushed != 'true', so a regen push "
        . 'skips this run instead of racing it',
];

// And must keep !cancelled(), or a skipped regen -- every merge_group event
// included -- takes the suite with it and nothing ever reports.
$withCancelGuard = array_values(
    array_filter(
        $guarded,
        function ($c) {
            return false !== strpos($c, '!cancelled()');
        }
    )
);
$results[] = [
    count($guarded) > 0 && count($withCancelGuard) === count($guarded),
    'both keep !cancelled(), so a skipped or failed regen cannot strand '
        . 'the required contexts',
];

// The guard must be a NEGATIVE test against 'true'. Written as
// `== 'false'` it would suppress the suite whenever regen is skipped --
// exactly backwards, and unmergeable on forks and in the merge queue.
$results[] = [
    0 === count(
        array_filter(
            $guarded,
            function ($c) {
                return (bool)preg_match(
                    "#needs\.regen\.outputs\.pushed\s*==#",
                    $c
                );
            }
        )
    ),
    "the guard tests != 'true' rather than == anything, so an unset output "
        . '(regen skipped) still runs the suite',
];

// Both jobs must still depend on regen at all; the guard reads its output.
$results[] = [
    2 === preg_match_all('#^\s+needs:\s*regen\s*$#m', $src),
    'both jobs still declare needs: regen, which is what makes the output '
        . 'readable',
];

$failed = 0;
foreach ($results as [$passed, $why]) {
    echo $passed ? "  ok    $why\n" : "  FAIL  $why\n";
    $failed += $passed ? 0 : 1;
}
echo "\n";
if ($failed > 0) {
    echo 'FAIL (' . $failed . ' of ' . count($results) . " assertions)\n";
    exit(1);
}
echo 'PASS (' . count($results) . " assertions)\n";
