<?php
/**
 * Auto-merge is armed at OPEN, and only where it is safe to arm it.
 *
 * The `working-1.6 pull request gate` ruleset sets the merge method to the
 * merge queue, which makes merging two steps: green checks are only the
 * precondition, and something still has to ASK before anything is enqueued.
 * A pull request with all nine checks passing sits open forever if nobody
 * asks, and that looks exactly like being stuck.
 *
 * .github/workflows/automerge.yml arms GitHub's own auto-merge when the pull
 * request opens, so the enqueue happens without anyone coming back to press
 * a button.
 *
 * That means a non-draft pull request MERGES ITSELF once green, so every
 * guard below is load-bearing. Each assertion is one way this could quietly
 * become wrong:
 *
 *   - the wrong merge method: the ruleset allows merge commits ONLY, and
 *     asking for squash or rebase fails rather than falling back;
 *   - a missing draft guard: the documented opt-out stops working and there
 *     is no way to open a pull request without it merging itself;
 *   - a missing ready_for_review trigger: taking a pull request OUT of draft
 *     arms nothing, so the opt-out becomes a one-way door and the button is
 *     back;
 *   - a missing same-repo guard: a fork's token is read-only so this cannot
 *     work anyway, and a fork's merge is a maintainer's decision;
 *   - a widened base: dev-branch and stable must not start merging
 *     themselves as a side effect of this file.
 *
 * Static: this reads the workflow, so it needs no runner and no network.
 *
 * Usage: php tests/automerge-arms-safely.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$file = $repo . '/.github/workflows/automerge.yml';
$src = is_file($file) ? (string)file_get_contents($file) : '';

// Strip comments before asserting on any of this. The prose above the job
// explains every guard and so NAMES each of them -- a file-wide search would
// match the explanation with the guard itself deleted. That trap has already
// bitten two tests in this tree.
$code = (string)preg_replace('~^\s*\#.*$~m', '', $src);

$results = [];

$results[] = [
    '' !== $src,
    'the workflow exists at .github/workflows/automerge.yml',
];

// The ruleset allows merge commits only.
$results[] = [
    false !== strpos($code, '--merge')
        && false === strpos($code, '--squash')
        && false === strpos($code, '--rebase'),
    'it asks for --merge, the only method the ruleset allows',
];
$results[] = [
    false !== strpos($code, '--auto'),
    'and arms auto-merge rather than merging on the spot',
];

// The draft opt-out, and the trigger that makes lifting it work.
$results[] = [
    (bool)preg_match('#!\s*github\.event\.pull_request\.draft#', $code),
    'a draft is left alone, which is the documented way to hold one back',
];
$results[] = [
    (bool)preg_match('#types:\s*\[[^\]]*ready_for_review#', $code),
    'and ready_for_review is a trigger, so lifting the draft arms it '
        . 'instead of being a one-way door',
];

// Scope. Same-repo, working-1.6 only, long-lived heads excluded.
$results[] = [
    false !== strpos(
        $code,
        'github.event.pull_request.head.repo.full_name == github.repository'
    ),
    'only same-repo pull requests are armed',
];
$results[] = [
    (bool)preg_match(
        "#base\.ref\s*==\s*'working-1\.6'#",
        $code
    ),
    'only pull requests based on working-1.6, whose ruleset created the '
        . 'queue this works around',
];
$results[] = [
    (bool)preg_match('#contains\(fromJson\(.+stable.+dev-branch#', $code),
    'and a long-lived head branch is excluded, so a release sync-back is '
        . 'never armed',
];

// Arming must fail LOUDLY. continue-on-error hid a real failure on this
// job's first ever run -- the step reported "success" while the command had
// exited 1 -- and the job is not a required context, so a red mark on it
// cannot block anything.
$results[] = [
    false === strpos($code, 'continue-on-error'),
    'a failure to arm is visible rather than reported as success',
];

// contents: write is required by enablePullRequestAutoMerge; anything less
// answers "Resource not accessible by integration". Pinned so it is not
// "tidied" back to read by someone applying least privilege from first
// principles, which is exactly the mistake that produced the failing run.
$results[] = [
    (bool)preg_match('#permissions:\s*\n\s*contents:\s*write#', $code),
    'it holds the contents: write that enablePullRequestAutoMerge needs',
];
$results[] = [
    (bool)preg_match('#pull-requests:\s*write#', $code)
        && false === strpos($code, 'write-all'),
    'and pull-requests: write, without reaching for write-all',
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
