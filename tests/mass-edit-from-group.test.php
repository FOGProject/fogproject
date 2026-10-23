<?php
/**
 * The group page's mass edit resolves the members on the server, in scope.
 *
 * GH-1938. The group page opens the host list's mass edit over the group's
 * members. Four things make that safe, and this pins each one:
 *
 *   - the members are split against the caller's host scope by a pure
 *     helper: in-scope ids are kept, the rest are COUNTED, never silently
 *     lost and never edited. Checked by calling the helper;
 *   - the group itself is scope-checked BEFORE it is loaded;
 *   - the selection reads the group first, and both handlers still put the
 *     resulting ids through requirePageObjectScopeMass(), so the existing
 *     airtight host gate is unchanged;
 *   - a group-launched form carries the group, not a snapshot of ids, so
 *     the apply reads the membership again;
 *   - the group page shows the button only under the permission the
 *     endpoint itself demands.
 *
 * The last four are source-anchored, for the reason given in
 * tests/mass-edit-endpoint-is-gated.test.php: reaching the handlers needs a
 * configured FOG, a session and a schema.
 *
 * Usage: php tests/mass-edit-from-group.test.php
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
$hostPath = $root . '/packages/web/src/Pages/HostManagement.php';
$groupPath = $root . '/packages/web/src/Pages/GroupManagement.php';
$hostSrc = (string)@file_get_contents($hostPath);
$groupSrc = (string)@file_get_contents($groupPath);
if ('' === $hostSrc || '' === $groupSrc) {
    fwrite(STDERR, "FAIL: cannot read the page sources\n");
    exit(1);
}

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

/**
 * The body of one method, so an assertion about one handler cannot be
 * satisfied by an identical line in a neighbor.
 */
$methodBody = static function ($src, $signature) {
    $start = strpos($src, $signature);
    if (false === $start) {
        return null;
    }
    $open = strpos($src, '{', $start);
    if (false === $open) {
        return null;
    }
    $depth = 0;
    $len = strlen($src);
    for ($i = $open; $i < $len; $i++) {
        if ('{' === $src[$i]) {
            $depth++;
        } elseif ('}' === $src[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($src, $open, $i - $open + 1);
            }
        }
    }
    return null;
};

// --- The scope split, executed ---------------------------------------------

// The helper is pure, so the method is lifted out of the source and run on
// its own -- loading HostManagement would need a configured server.
$split = $methodBody(
    $hostSrc,
    'private static function massEditScopeMembers(array $members, $scoped)'
);
$check('massEditScopeMembers() is findable', null !== $split);
if (null !== $split) {
    $fn = eval('return static function (array $members, $scoped) '
        . $split . ';');

    $r = $fn([3, 1, 2], null);
    $check(
        'an unbounded caller keeps every member and excludes none',
        [3, 1, 2] === $r['hosts'] && 0 === $r['excluded']
    );

    $r = $fn([1, 2, 3, 4], [2, 4, 9]);
    $check(
        'a bounded caller keeps only the members in scope',
        [2, 4] === $r['hosts']
    );
    $check(
        'the members left out are counted, not lost',
        2 === $r['excluded']
    );

    $r = $fn(['5', 5, 0, '', 6], ['5', '6']);
    $check(
        'ids are normalized: duplicates and zeroes do not count',
        [5, 6] === $r['hosts'] && 0 === $r['excluded']
    );

    $r = $fn([1, 2], []);
    $check(
        'a caller who reaches no host gets nothing, with all counted',
        [] === $r['hosts'] && 2 === $r['excluded']
    );
}

// --- The group is scope-checked before it is loaded ------------------------

$groupSel = (string)$methodBody(
    $hostSrc,
    'private function massEditGroupSelection()'
);
$scopeAt = strpos($groupSel, "requirePageObjectScope('group', \$groupID)");
$loadAt = strpos($groupSel, 'new Group($groupID)');
$check('the group is scope-checked', false !== $scopeAt);
$check(
    'the scope check runs before the group is loaded',
    false !== $scopeAt && false !== $loadAt && $scopeAt < $loadAt
);
$check(
    'the members are split against the host scope',
    false !== strpos($groupSel, "scopedObjectIDs('host')")
);

// --- The selection, and the unchanged host gate ----------------------------

$sel = (string)$methodBody($hostSrc, 'private function massEditSelection()');
$check(
    'the selection reads a posted group before posted ids',
    false !== strpos($sel, 'massEditGroupSelection()')
        && strpos($sel, 'massEditGroupSelection()')
            < strpos($sel, "'hosts'")
);
foreach (['massEditFormPost', 'massEditPost'] as $handler) {
    $body = (string)$methodBody($hostSrc, "public function $handler()");
    $selAt = strpos($body, 'massEditSelection()');
    $gateAt = strpos($body, "requirePageObjectScopeMass('host', \$hosts)");
    $check(
        "$handler() still bounds the resolved ids to the host scope",
        false !== $selAt && false !== $gateAt && $selAt < $gateAt
    );
}

// --- A group launch posts the group, not a snapshot -------------------------

$form = (string)$methodBody($hostSrc, 'public function massEditFormPost()');
$check(
    'a group-launched form carries the group id',
    false !== strpos($form, 'name="group"')
);
$check(
    'the form says how many members were left out',
    false !== strpos($form, "\$group['excluded']")
);

// --- The group page button --------------------------------------------------

$tab = (string)$methodBody($groupSrc, 'public function groupHosts()');
$check(
    'the group page gates the button on the endpoint\'s own permission',
    false !== strpos(
        $tab,
        "resolvePagePermission('host', 'massedit', true)"
    )
);
$check(
    'the group page reuses the host list\'s modal',
    false !== strpos($tab, 'HostManagement::massEditActions()')
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: mass edit from a group:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  mass edit from a group: %d checks\n", $checks);
exit(0);
