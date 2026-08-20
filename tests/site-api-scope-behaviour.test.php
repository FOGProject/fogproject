<?php
/**
 * What the API object boundary DOES, not how it is built.
 *
 * The sibling site-api-scope.test.php is a source test: it asserts that each
 * read route consults the boundary and that nobody has rewritten a `null ===`
 * check into a falsy one. That is worth having and it is not this. It passes
 * unchanged if the boundary is compiled into the query, applied to the rows,
 * or handed to a plugin -- so it cannot tell a working boundary from a
 * rewritten one, which is exactly the question when the implementation moves.
 *
 * So this file asserts OUTCOMES: for each of the three states the boundary can
 * be in, which objects come back. Every assertion here is about ids in a
 * result set, never about the SQL that produced them, so it holds across any
 * implementation that gets the answer right.
 *
 * THE THREE STATES, and all three have to survive any change:
 *
 *   1. No plugin, or no acting user -> no boundary, read unrestricted.
 *      Not an edge case: the service daemons and the status endpoints reach
 *      Route::ids()/names() logged out, through getIds()/getNames(). A
 *      boundary that always applies scopes those too, and the symptom is
 *      imaging breaking, not a 403.
 *
 *   2. A scoped user with objects in scope -> exactly those objects.
 *
 *   3. A scoped user with NOTHING in scope -> NOTHING, not everything.
 *      This is the one that matters. `null` means "no boundary" and `array()`
 *      means "you may see nothing"; both are falsy, so any `if (!$ids)` test
 *      collapses deny-all into full disclosure -- for precisely the users the
 *      boundary exists to restrict, with no error and no log line.
 *
 * TWO ARMS.
 *
 * The row-filtered routes -- listem() and search() -- are assertable with no
 * database at all, because the boundary is applied to objects the manager
 * already built: a fake PDO hands back three rows and the assertion is which
 * of them survive. That arm always runs.
 *
 * names() and ids() are not. They push the boundary into the WHERE clause, so
 * "which rows come back" is a question only a real database can answer, and
 * asserting on the generated SQL instead would be asserting the mechanism --
 * the thing this file exists not to do. That arm therefore runs against a real
 * 1.5 schema when one is reachable and SKIPS when it is not, rather than
 * quietly degrading into a string match.
 *
 * The database arm creates its own rows, marked with this process' pid, and
 * asserts only about those. It never asserts a table is empty: "empty of my
 * rows" is not "empty", and a server with real data would fail an assertion
 * that confused the two. The single exception is deny-all, where a zero TOTAL
 * is the assertion.
 *
 * Usage: php tests/site-api-scope-behaviour.test.php
 * Exit status 0 = pass (or skip), 1 = fail.
 */

$web = dirname(__DIR__) . '/packages/web';

// --------------------------------------------------------------- subprocess
//
// _requireObjectScope() answers a denial with sendResponse(), which ends in
// exit -- there is no result to inspect and no exception to catch, so the
// only way to observe it is from outside the process. Re-exec of this same
// file with an argument, and the marker below is what "it did not deny"
// looks like.
if (isset($argv[1]) && '--object-scope' === $argv[1]) {
    require __DIR__ . '/lib/scope-harness.php';
    scopeHarnessBoot($web, $argv[2]);
    // The fragment states need a real database: the gate answers a fragment
    // by asking whether one row satisfies it, which is a question only a
    // database has. The rows are the PARENT's fixture -- they exist for as
    // long as the parent does -- and their ids arrive on the command line so
    // the child does not create a second, unrelated set.
    if (0 === strpos($argv[2], 'frag')) {
        scopeHarnessDbReason();
        scopeHarnessSetDb(new PDODB());
        $inScope = array_map('intval', array_slice($argv, 4));
        ScopeProbe::$whereAnswer = 'frag-deny' === $argv[2]
            ? '1=0'
            : function ($idExpr) use ($inScope) {
                return $idExpr . ' IN (' . implode(',', $inScope) . ')';
            };
    }
    $m = new \ReflectionMethod('Route', '_requireObjectScope');
    $m->setAccessible(true);
    $m->invoke(null, 'group', (int)$argv[3]);
    echo "ALLOWED\n";
    exit(0);
}

require __DIR__ . '/lib/scope-harness.php';

$failures = [];
$checks = 0;

function check($label, $cond, array &$failures, &$checks)
{
    $checks++;
    if (!$cond) {
        $failures[] = $label;
    }
}

// ------------------------------------------------------- arm 1: no database
//
// Three rows in, and the question is which come out. `group` rather than
// `host` for the second class because a Host builds a MACAddress on load,
// which reaches for globals a fake database has no way to supply; both are
// scoped classes and the boundary code does not branch on which.
scopeHarnessBoot($web, 'none');

$states = [
    ['none', 'unbounded', [1, 2, 3]],
    [[2, 3], 'scoped', [2, 3]],
    [[], 'deny-all', []],
];

foreach (['host', 'group'] as $class) {
    foreach ($states as [$answer, $label, $expect]) {
        ScopeProbe::$answer = $answer;
        foreach (['listem', 'search'] as $route) {
            Route::$data = null;
            if ('listem' === $route) {
                Route::listem($class);
            } else {
                Route::search($class, 'h');
            }
            $got = array_map(
                'intval',
                array_column((array)(Route::$data[$class . 's'] ?? []), 'id')
            );
            sort($got);
            check(
                "$route($class) $label returns [" . implode(',', $expect) . ']',
                $got === $expect,
                $failures,
                $checks
            );
            // The count is part of the answer, not decoration: a caller that
            // pages on it is told how many objects exist, so a count computed
            // over the unscoped set discloses the size of what it hid.
            check(
                "$route($class) $label reports count " . count($expect),
                (int)(Route::$data['count'] ?? -1) === count($expect),
                $failures,
                $checks
            );
        }
    }
}

// ---------------------------------------- arm 2: per-object route, out of process
$objectScope = function ($state, $id, array $extra = []) {
    $out = [];
    exec(
        sprintf(
            '%s %s --object-scope %s %d %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
            escapeshellarg($state),
            $id,
            implode(' ', array_map('intval', $extra))
        ),
        $out
    );
    return in_array('ALLOWED', array_map('trim', $out), true);
};
foreach ([['none', 1, true], ['scoped', 1, false], ['scoped', 3, true], ['deny', 1, false]] as [$state, $id, $allow]) {
    check(
        "_requireObjectScope(group, $id) under '$state' " . ($allow ? 'allows' : 'denies'),
        $objectScope($state, $id) === $allow,
        $failures,
        $checks
    );
}

// ------------------------------------------------------- arm 3: real database
//
// Skipped rather than faked when there is no 1.5 schema to talk to. The
// credentials are read from a live install's generated config at run time and
// never written down here.
$dbSkip = scopeHarnessDbReason();
if (null !== $dbSkip) {
    echo "SKIP (database arm): $dbSkip\n";
} else {
    // Arms 1 and 2 ran against the fake; from here the boundary has to be
    // answered by a real database, because "which rows come back" is the
    // whole assertion and only a database can answer it.
    $real = new PDODB();
    scopeHarnessSetDb($real);
    $ids = scopeHarnessFixture($real);
    $mine = function ($values) use ($ids) {
        $out = array_values(
            array_intersect(array_map('intval', (array)$values), $ids)
        );
        sort($out);
        return $out;
    };
    $cases = [
        ['none', 'unbounded', $ids],
        [[$ids[1], $ids[2]], 'scoped', [$ids[1], $ids[2]]],
        [[], 'deny-all', []],
    ];
    foreach ($cases as [$answer, $label, $expect]) {
        ScopeProbe::$answer = $answer;

        Route::$data = null;
        Route::names('group', []);
        $names = (array)Route::$data;
        check(
            "names(group) $label returns my " . count($expect) . ' group(s)',
            $mine(array_column($names, 'id')) === $expect,
            $failures,
            $checks
        );

        Route::$data = null;
        Route::ids('group', [], 'id');
        $idlist = (array)Route::$data;
        check(
            "ids(group) $label returns my " . count($expect) . ' group(s)',
            $mine($idlist) === $expect,
            $failures,
            $checks
        );

        // Deny-all is the only state where a TOTAL is assertable, and it is
        // the assertion the whole tri-state exists for: not "my rows are
        // gone" but "no rows at all", which is what separates a boundary
        // that compiled to nothing from one that compiled to everything.
        if ('deny-all' === $label) {
            check(
                'names(group) deny-all returns NO rows at all',
                0 === count($names),
                $failures,
                $checks
            );
            check(
                'ids(group) deny-all returns NO rows at all',
                0 === count($idlist),
                $failures,
                $checks
            );
        }
    }

    /*
     * The SQL-fragment path, which is what a site-scoped server actually
     * runs -- the id list is now the fallback for plugins that predate the
     * fragment event.
     *
     * Only reachable with a real database, and not because of a harness
     * limitation: the whole point of a fragment is that the DATABASE applies
     * the boundary, so there is nothing to observe without one. The answers
     * below are built from the caller's own $idExpr rather than a hardcoded
     * column, so what is under test is the seam and not a string.
     */
    ScopeProbe::$answer = 'none';
    $fragCases = array(
        array('none', 'unbounded', $ids),
        array(
            function ($idExpr) use ($ids) {
                return $idExpr . ' IN (' . $ids[1] . ',' . $ids[2] . ')';
            },
            'scoped',
            array($ids[1], $ids[2])
        ),
        array('1=0', 'deny-all', array()),
    );
    foreach ($fragCases as [$answer, $label, $expect]) {
        ScopeProbe::$whereAnswer = $answer;

        Route::$data = null;
        Route::names('group', []);
        $names = (array)Route::$data;
        check(
            "fragment: names(group) $label returns my " . count($expect),
            $mine(array_column($names, 'id')) === $expect,
            $failures,
            $checks
        );

        Route::$data = null;
        Route::ids('group', [], 'id');
        $idlist = (array)Route::$data;
        check(
            "fragment: ids(group) $label returns my " . count($expect),
            $mine($idlist) === $expect,
            $failures,
            $checks
        );

        Route::$data = null;
        Route::listem('group');
        check(
            "fragment: listem(group) $label returns my " . count($expect),
            $mine(array_column((array)(Route::$data['groups'] ?? []), 'id')) === $expect,
            $failures,
            $checks
        );

        Route::$data = null;
        Route::search('group', 'zz-scopetest-');
        check(
            "fragment: search(group) $label returns my " . count($expect),
            $mine(array_column((array)(Route::$data['groups'] ?? []), 'id')) === $expect,
            $failures,
            $checks
        );

        if ('deny-all' === $label) {
            check(
                'fragment: names(group) deny-all returns NO rows at all',
                0 === count($names),
                $failures,
                $checks
            );
            check(
                'fragment: listem(group) deny-all returns NO rows at all',
                0 === (int)(Route::$data['count'] ?? -1)
                || 0 === count((array)(Route::$data['groups'] ?? [])),
                $failures,
                $checks
            );
        }
    }

    /*
     * The fragment survives a caller's own filter.
     *
     * Every case above hands names()/ids() an empty filter, so the boundary
     * is the only term in the WHERE and a fragment that got dropped whenever
     * a clause already existed would pass all of them. That is not a
     * hypothetical: the append helper has two arms, and only the empty one
     * was being driven. A caller asking for all three of my groups, narrowed
     * by a fragment naming two, has to come back with two.
     */
    ScopeProbe::$answer = 'none';
    ScopeProbe::$whereAnswer = function ($idExpr) use ($ids) {
        return $idExpr . ' IN (' . $ids[1] . ',' . $ids[2] . ')';
    };
    Route::$data = null;
    Route::names('group', ['id' => $ids]);
    check(
        'fragment: names(group) narrows a filter the caller supplied',
        $mine(array_column((array)Route::$data, 'id')) === [$ids[1], $ids[2]],
        $failures,
        $checks
    );
    Route::$data = null;
    Route::ids('group', ['id' => $ids], 'id');
    check(
        'fragment: ids(group) narrows a filter the caller supplied',
        $mine((array)Route::$data) === [$ids[1], $ids[2]],
        $failures,
        $checks
    );
    ScopeProbe::$whereAnswer = '1=0';
    Route::$data = null;
    Route::names('group', ['id' => $ids]);
    check(
        'fragment: deny-all beats a filter the caller supplied',
        0 === count((array)Route::$data),
        $failures,
        $checks
    );
    // And the manager path too: listem() passes the caller's own $find
    // alongside the fragment, which is the second arm of the same join.
    ScopeProbe::$whereAnswer = function ($idExpr) use ($ids) {
        return $idExpr . ' IN (' . $ids[1] . ',' . $ids[2] . ')';
    };
    Route::$data = null;
    Route::listem('group', 'name', false, ['id' => $ids]);
    check(
        'fragment: listem(group) narrows a filter the caller supplied',
        $mine(array_column((array)(Route::$data['groups'] ?? []), 'id'))
        === [$ids[1], $ids[2]],
        $failures,
        $checks
    );

    /*
     * Exactly ONE boundary is applied, never both.
     *
     * Two narrowings ANDed together is not a safer boundary, it is a
     * different one nobody stated -- and the failure is invisible, because
     * over-restriction looks like the feature working. The fragment says
     * "these two"; the id list says "that other one"; if both applied the
     * answer would be nothing at all.
     */
    ScopeProbe::$whereAnswer = function ($idExpr) use ($ids) {
        return $idExpr . ' IN (' . $ids[1] . ',' . $ids[2] . ')';
    };
    ScopeProbe::$answer = array($ids[0]);
    foreach (array('names', 'ids', 'listem', 'search') as $route) {
        Route::$data = null;
        switch ($route) {
            case 'names':
                Route::names('group', []);
                $got = $mine(array_column((array)Route::$data, 'id'));
                break;
            case 'ids':
                Route::ids('group', [], 'id');
                $got = $mine((array)Route::$data);
                break;
            case 'listem':
                Route::listem('group');
                $got = $mine(array_column((array)(Route::$data['groups'] ?? []), 'id'));
                break;
            default:
                Route::search('group', 'zz-scopetest-');
                $got = $mine(array_column((array)(Route::$data['groups'] ?? []), 'id'));
        }
        check(
            "$route(group) applies the fragment ALONE when both events answer",
            $got === array($ids[1], $ids[2]),
            $failures,
            $checks
        );
    }

    /*
     * And the id list is still reachable. Without this the fall-through could
     * be broken in the other direction -- fragment-only -- and every check
     * above would still pass while every plugin written before the fragment
     * event silently stopped bounding anything.
     */
    ScopeProbe::$whereAnswer = 'none';
    ScopeProbe::$answer = array($ids[0]);
    Route::$data = null;
    Route::names('group', []);
    check(
        'the id list still bounds a read when no fragment answers',
        $mine(array_column((array)Route::$data, 'id')) === array($ids[0]),
        $failures,
        $checks
    );
    // An empty fragment is silence, not deny-all: it must fall through to
    // the id list rather than either denying or reading unbounded.
    ScopeProbe::$whereAnswer = '   ';
    Route::$data = null;
    Route::names('group', []);
    check(
        'an empty fragment falls through to the id list',
        $mine(array_column((array)Route::$data, 'id')) === array($ids[0]),
        $failures,
        $checks
    );
    ScopeProbe::$whereAnswer = 'none';
    ScopeProbe::$answer = 'none';

    /*
     * The two boundaries must agree about WHICH hosts are in a site.
     *
     * They did not. A membership lookup through SiteHostAssociation's manager
     * drags in Host's joins, including the primary-MAC filter, so it dropped
     * every host with no primary MAC -- 95 of 1000 in the lab -- while the
     * SQL fragment, which joins nothing, returned all of them. Not a
     * disclosure: it under-returned, so a site-restricted user could not see
     * hosts in their own site. But it meant which hosts you could see
     * depended on which of the two answered, which is the one thing a
     * boundary must not do.
     *
     * Asserted against the database rather than against the other function,
     * so it cannot pass by both being wrong in the same way.
     */
    [$maclessSite, $maclessHosts] = scopeHarnessMaclessFixture($real);
    $got = Site::hostIDsForSites([$maclessSite]);
    sort($got);
    check(
        'hostIDsForSites() returns hosts that have no primary MAC',
        array_map('intval', (array)$got) === $maclessHosts,
        $failures,
        $checks
    );
    // And the fragment agrees, which is the property that actually matters.
    $frag = Site::scopedObjectWhere(
        'host',
        '`hosts`.`hostID`',
        0
    );
    check(
        'scopedObjectWhere() declines for a user with no restriction row',
        null === $frag,
        $failures,
        $checks
    );

    // The per-object gate under a fragment. Same expression the lists narrow
    // with, so a 403 and an absent row are the same decision.
    check(
        'fragment: the gate allows an object inside the boundary',
        true === $objectScope('frag-scoped', $ids[1], [$ids[1], $ids[2]]),
        $failures,
        $checks
    );
    check(
        'fragment: the gate denies an object outside the boundary',
        false === $objectScope('frag-scoped', $ids[0], [$ids[1], $ids[2]]),
        $failures,
        $checks
    );
    check(
        'fragment: the gate denies everything under a deny-all fragment',
        false === $objectScope('frag-deny', $ids[1], [$ids[1], $ids[2]]),
        $failures,
        $checks
    );
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
echo "ok  $checks checks passed\n";
