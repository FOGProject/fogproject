<?php
/**
 * A group task must not bind NULL into `tasks`.`taskPassreset`.
 *
 * Forum topic 18232. Creating a multicast deployment for a GROUP fails
 * before PXE with "Failed to create tasking" and:
 *
 *   SQLSTATE[23000]: Integrity constraint violation: 1048 Column
 *   'taskPassreset' cannot be null
 *
 * Three things have to line up, and only the last of them is new:
 *
 *  - `account` is posted only by a password reset task's form, and
 *    filter_input() answers NULL -- not '' -- for a POST key that is not
 *    there. So every other task type carried a NULL in $passreset.
 *  - Group::createImagePackage() batch-inserts a FIXED column list which
 *    always names passreset, so that NULL is always bound. The single-host
 *    path is unaffected because Host::createImagePackage() only sets the
 *    field when it holds something, which is why the report is about groups.
 *  - GH-1245 removed PDODB's `SET SESSION sql_mode=''`. For nine years the
 *    server silently coerced the NULL to '' on the way into a varchar(250)
 *    NOT NULL column; without the clear it refuses the statement instead.
 *
 * That last point is the whole reason this reproduces on dev-branch and not
 * on the 1.5.10 release, which is exactly what the reporter observed. It is
 * a NULL that was always wrong and was always being covered up.
 *
 * WHAT THIS PINS, and what it deliberately does not. The fault is a single
 * expression inside FOGPage::_tasking(), which is private, several hundred
 * lines long, and cannot be reached without a live database, a valid image,
 * a storage group and an optimal node -- none of which the fault has
 * anything to do with. So the expression is pinned as SOURCE, whole, rather
 * than by grepping for a function name that would still match a rewritten
 * line. The two facts that make it matter -- the column cannot hold NULL,
 * and the batch always names it -- are asserted separately, so a future
 * change to either fails here and this test gets revisited rather than
 * silently guarding nothing.
 *
 * Usage: php tests/group-tasking-passreset-never-null.test.php
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

$web = dirname(__DIR__) . '/packages/web';
$failures = [];
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $label what is being asserted
 * @param bool   $cond  the assertion
 *
 * @return void
 */
function check($label, $cond)
{
    global $failures, $checks;
    ++$checks;
    if (!$cond) {
        $failures[] = $label;
    }
}

// -------------------------------------------------------------------------
// 1. The fix itself.
// -------------------------------------------------------------------------
$page = (string)file_get_contents($web . '/lib/fog/fogpage.class.php');

check(
    'the tasking form reads `account` as a string, never as NULL',
    (bool)preg_match(
        '/\$passreset\s*=\s*trim\(\s*\(string\)\s*'
        . 'filter_input\(\s*INPUT_POST\s*,\s*\'account\'\s*\)\s*\)\s*;/',
        $page
    )
);
// The bare form is the bug. Named separately so a partial revert -- a cast
// with no trim, a trim with no cast -- is reported as what it is rather
// than as the check above simply not matching.
check(
    'and the uncast form is gone',
    !preg_match(
        '/\$passreset\s*=\s*filter_input\(\s*INPUT_POST\s*,\s*\'account\'\s*\)\s*;/',
        $page
    )
);

// -------------------------------------------------------------------------
// 2. Why it matters: the batch always binds the column.
//
//    If a later change stops naming passreset in these lists the NULL can no
//    longer reach the server and this test is guarding nothing -- so the
//    premise is asserted rather than assumed.
// -------------------------------------------------------------------------
$group = (string)file_get_contents($web . '/lib/fog/group.class.php');

preg_match_all(
    '/\$batchFields\s*=\s*array\((.*?)\);/s',
    $group,
    $lists
);
$binding = 0;
foreach ((array)$lists[1] as $list) {
    if (false !== strpos($list, "'passreset'")) {
        ++$binding;
    }
}
check(
    'group tasking still batch-inserts passreset unconditionally',
    $binding > 0
);

// -------------------------------------------------------------------------
// 3. And why NULL is refused: the column cannot hold one.
// -------------------------------------------------------------------------
$schema = (string)file_get_contents($web . '/commons/schema.php');
check(
    'tasks.taskPassreset is declared NOT NULL',
    (bool)preg_match(
        '/`taskPassreset`\s*"\s*\.\s*"\s*varchar\(250\)\s*NOT\s+NULL/i',
        $schema
    )
);

// -------------------------------------------------------------------------
if (count($failures) > 0) {
    fwrite(STDERR, sprintf("FAIL (%d of %d):\n", count($failures), $checks));
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
printf("ok  %d checks passed\n", $checks);
exit(0);
