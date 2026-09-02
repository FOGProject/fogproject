<?php
/**
 * Power Management is a GRANT, not a fan-out (ADR 0038).
 *
 * It was the last control on the group page that still copied. Saving a
 * schedule wrote one `powerManagement` row per host that happened to be a
 * member at that instant and recorded nothing about where the rows came from,
 * so a host added afterward got no schedule, a host removed kept one forever,
 * and nothing could replay it. The tab's own text said "to all hosts in this
 * group", which was true for one instant.
 *
 * tests/assign-resolver.test.php proves the RESOLUTION against a real
 * database -- ordering, deduplication, the on-demand exclusion. This file
 * pins the things a seeded database cannot see: that there is no on-demand
 * column to make a grant fire twice, that each consumer reads through the
 * resolver rather than around it, and that the write path creates one row
 * about the group instead of one row per member.
 *
 * EVERY FAILURE HERE IS SILENT. A regression does not raise: it writes rows
 * that look exactly like the ones an admin set by hand, on whichever hosts
 * were members at the time, and the only symptom is a machine that shuts
 * itself down months later with nothing to explain why.
 *
 * Usage: php tests/group-power-management-is-a-grant.test.php
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
$web = $root . '/packages/web';

$fails = [];
$checks = 0;
$check = static function ($what, $ok) use (&$checks, &$fails) {
    $checks++;
    if (!$ok) {
        $fails[] = $what;
    }
};

// Comments stripped before any behavioral grep: this file's own reasoning
// names every symbol it looks for, and so does the code's.
$strip = static function ($php) {
    $out = '';
    foreach (token_get_all($php) as $token) {
        if (is_array($token)
            && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }
    return $out;
};

// ---------------------------------------------------------------------
// 1. The table, and the column that must not exist.

$manifest = include $web . '/commons/schema-expected.php';
$table = $manifest['tables']['groupPowerManagement'] ?? null;
$check(
    'commons/schema-expected.php declares groupPowerManagement',
    is_array($table)
);
if (is_array($table)) {
    $columns = array_keys($table['columns'] ?? []);
    $check(
        'the grant table carries the five cron fields, the group and the action',
        [] === array_diff(
            [
                'gpmID',
                'gpmGroupID',
                'gpmMin',
                'gpmHour',
                'gpmDom',
                'gpmMonth',
                'gpmDow',
                'gpmAction'
            ],
            $columns
        )
    );
    // The absence IS the design. An on-demand action is a task against the
    // membership at the moment it is created; a GRANT of "shut down
    // immediately" would fire again for every host that joined afterward.
    $check(
        'the grant table has NO on-demand column',
        !in_array('gpmOndemand', $columns, true)
    );
    // Identity is cron plus action, and it is unique. insertBatch() upserts,
    // so without this saving the same schedule twice is a second row that
    // reboots the machine a second time.
    $check(
        'the grant table is unique on group + cron + action',
        false !== strpos(
            (string)($table['create'] ?? ''),
            'UNIQUE KEY `gpmCron` (`gpmGroupID`,`gpmMin`,`gpmHour`,'
            . '`gpmDom`,`gpmMonth`,`gpmDow`,`gpmAction`)'
        )
    );
}

// The foreign key. An orphan schedule against a reused group id would
// silently start shutting down every host that inherited the number.
$constraints = include $web . '/commons/schema-constraints.php';
$fk = null;
foreach ((array)$constraints as $row) {
    if (($row['child'] ?? '') === 'groupPowerManagement') {
        $fk = $row;
        break;
    }
}
$check(
    'the grant table declares a CASCADE foreign key to groups, enabled',
    is_array($fk)
    && 'gpmGroupID' === ($fk['column'] ?? '')
    && 'groups' === ($fk['parent'] ?? '')
    && 'CASCADE' === ($fk['action'] ?? '')
    && true === ($fk['enabled'] ?? false)
);

// ---------------------------------------------------------------------
// 2. The resolver is the only way in.

$resolver = $strip(file_get_contents($web . '/src/Assign/Resolver.php'));
$check(
    'Assign\\Resolver exposes resolvePowerManagement()',
    false !== strpos(
        $resolver,
        'public static function resolvePowerManagement(array $hostIDs)'
    )
);
$check(
    'the resolver reads groupPowerManagement directly, not through a manager',
    false !== strpos($resolver, 'FROM `groupPowerManagement` ')
);

// ---------------------------------------------------------------------
// 3. The client reads through the resolver, and drops wol.

$pm = $strip(file_get_contents($web . '/src/Client/PM.php'));
$check(
    'Client\\PM asks the resolver for the schedules',
    false !== strpos($pm, 'Resolver::resolvePowerManagement(')
);
// The old direct read. A host that kept it would see only its OWN rows and
// none of its groups' -- a grant that silently reaches nothing, which is
// indistinguishable from a group with no schedules set.
$check(
    'Client\\PM no longer lists powermanagement rows for its schedules',
    false === strpos($pm, "'onDemand' => [0, '']")
);
// Handing a running client `wol` schedules it to wake itself.
$check(
    'Client\\PM drops wol from what it hands the client',
    (bool)preg_match(
        "#'wol'\s*===\s*\\\$schedule\['action'\]#",
        $pm
    )
);

// ---------------------------------------------------------------------
// 4. The scheduler expands group wake grants -- and counts them.

$sched = $strip(file_get_contents($web . '/src/Service/TaskScheduler.php'));
$check(
    'TaskScheduler reads the group wake grants',
    false !== strpos($sched, 'wakeGrants()')
);
// ORDER IS LOAD-BEARING. The daemon throws ' * No tasks found!' when the
// count is zero, so a server whose only wake schedule is a group grant would
// bail before reaching the loop -- a schedule that never fires, with a log
// line saying everything is fine.
$readAt = strpos($sched, 'wakeGrants()');
$countAt = strpos($sched, '$taskCount <= 0');
$check(
    'the grants are read BEFORE the no-tasks-found check, and counted',
    false !== $readAt
    && false !== $countAt
    && $readAt < $countAt
    && false !== strpos($sched, '$staskcount + $ptaskcount + $gtaskcount')
);

$mgr = $strip(
    file_get_contents($web . '/src/Managers/GroupPowerManagementManager.php')
);
// Only wake. A group-granted shutdown or reboot is run by the FOG client on
// each member; sending it from here as well would do it twice.
$check(
    'wakeGrants() selects only the wol grants',
    (bool)preg_match(
        "#`gpmAction`\s*=\s*'wol'#",
        $mgr
    )
);

// ---------------------------------------------------------------------
// 5. The write path: one row about the group, not one per member.

$group = file_get_contents($web . '/src/Pages/GroupManagement.php');
$post = '';
if (preg_match(
    '#public function groupPowermanagementPost\(\).*?\n    \}\n#s',
    $group,
    $m
)) {
    $post = $strip('<?php ' . $m[0]);
} else {
    $fails[] = 'GroupManagement::groupPowermanagementPost() is gone';
    $checks++;
}

if ('' !== $post) {
    $check(
        'a scheduled save creates a GroupPowerManagement row',
        false !== strpos($post, "getClass('GroupPowerManagement')")
        && false !== strpos($post, "->set('groupID', \$groupID)")
    );
    // THE FAN-OUT MUST BE REACHABLE ONLY FROM THE ON-DEMAND BRANCH. The old
    // code built one PowerManagementManager row per member for BOTH cases,
    // and that is the exact defect: a schedule copied onto whoever was a
    // member at the time.
    //
    // The branch body is EXTRACTED, not merely located. An order-only test --
    // `if ($onDemand)` appears before the insertBatch -- passes just as
    // happily on `if (true)`, or on a branch that no longer closes before the
    // grant, which is the shape that reintroduces the fan-out. Counting
    // braces is what makes "inside" mean inside.
    $onDemandAt = strpos($post, 'if ($onDemand)');
    $branch = '';
    if (false !== $onDemandAt) {
        $open = strpos($post, '{', $onDemandAt);
        if (false !== $open) {
            $depth = 0;
            for ($i = $open, $n = strlen($post); $i < $n; $i++) {
                if ('{' === $post[$i]) {
                    $depth++;
                } elseif ('}' === $post[$i]) {
                    $depth--;
                    if (0 === $depth) {
                        $branch = substr($post, $open, $i - $open + 1);
                        break;
                    }
                }
            }
        }
    }
    $check(
        'the per-host fan-out is INSIDE the on-demand branch',
        '' !== $branch
        && false !== strpos($branch, "getClass('PowerManagementManager')")
    );
    $check(
        'the grant is created OUTSIDE the on-demand branch',
        '' !== $branch
        && false === strpos($branch, "getClass('GroupPowerManagement')")
        && false !== strpos($post, "getClass('GroupPowerManagement')")
    );
    // And the branch must not fall through: without the return, an immediate
    // action would fan out AND leave a standing grant behind it.
    //
    // Anchored on the END of the branch, not on `return;` appearing anywhere
    // inside it. The wake arm has an early return of its own, so a bare
    // strpos() is satisfied by that one and passes with the closing return
    // deleted -- which is precisely the fall-through this is here to catch.
    // Found by running that mutation.
    $check(
        'the on-demand branch ENDS in a return rather than falling through',
        '' !== $branch
        && (bool)preg_match('#return;\s*\}$#', $branch)
    );
    // Ids arrive from the browser and a grant id is not a secret, so the
    // revoke must be scoped to this group as well as to the ids -- otherwise
    // a crafted post revokes another group's schedule.
    $check(
        'revoking a grant is scoped to this group, not to the ids alone',
        (bool)preg_match(
            "#deletemass\(\s*'grouppowermanagement',\s*\[\s*'id'\s*=>\s*"
            . "\\\$grantIDs,\s*'groupID'\s*=>\s*\\\$groupID#s",
            $post
        )
    );
}

// The tab must not be listing the members' rows. `hosts` in the grid query
// would make it a summary of what the members hold, which is what the whole
// change is getting away from.
$list = '';
if (preg_match(
    '#public function getGrouppowermanagementList\(\).*?\n    \}\n#s',
    $group,
    $m
)) {
    $list = $strip('<?php ' . $m[0]);
}
$check(
    'the grid lists the GROUP\'S grants, scoped by groupID',
    (bool)preg_match(
        "#listem\(\s*'grouppowermanagement',\s*\[\s*'groupID'#s",
        $list
    )
);
// Not a new REST route. groupsnapinassociation and groupmoduleassociation
// are not in $validClasses either; that list gates the HTTP API surface, and
// a page driving its own grid does not need to widen it.
$route = file_get_contents($web . '/src/Router/Route.php');
if (preg_match(
    '#public static \$validClasses = \[(.*?)\];#s',
    $route,
    $m
)) {
    $check(
        'the grant did not become a public API route',
        false === strpos($m[1], "'grouppowermanagement'")
    );
} else {
    $fails[] = 'Route::$validClasses could not be read';
    $checks++;
}

if ($fails) {
    fwrite(STDERR, "FAIL group-power-management-is-a-grant:\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($fails), $checks)
    );
    exit(1);
}

printf("PASS  group power management is a grant: %d checks\n", $checks);
exit(0);
