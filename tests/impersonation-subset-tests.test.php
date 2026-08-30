<?php
/**
 * Who may impersonate whom: TWO independent tests, both of which must pass.
 *
 * They are separate on purpose and the separation is the thing most likely
 * to be lost by somebody implementing "subset" from a one-line description.
 *
 *   1. PERMISSION SUBSET. The target's permissions must nest inside the
 *      impersonator's, computed against Authorization::registry() rather
 *      than guessed.
 *   2. SITE SUBSET. The target's sites must nest inside the impersonator's.
 *      Site scope is NOT a permission node -- SiteScope answers it out of
 *      four membership tables -- so test 1 cannot see it at all, and a Site
 *      A administrator would otherwise reach a Site B user whose permission
 *      nodes happened to nest.
 *
 * The site half has traps that plain set arithmetic on userSiteIDs() walks
 * straight into:
 *
 *   - A '*' HOLDER, OR A CATCH-ALL MEMBER, IS NEVER SITE-BOUNDED at all, so
 *     they are a site superset of everybody however few sites they are in.
 *     A catch-all administrator's id list is one site; an ordinary user's
 *     may be two; the arithmetic alone therefore refuses the administrator.
 *   - NO SITE IN USE means scoping is off and everyone sees everything.
 *     Both lists are empty and nest trivially: right answer, wrong reason,
 *     so it is stated rather than left to fall out.
 *   - CATCH-ALL MEMBERSHIP IS THE UNIVERSAL SITE SET, not one more id.
 *
 * And note the naming trap this file exists next to: isUnscoped() means
 * SEES EVERYTHING, while a user in no site at all sees NOTHING. Two
 * opposite conditions, one English word.
 *
 * MUTATION-VERIFIED, and one result is worth recording because it was NOT
 * what writing the code predicted. Five edits were made and watched:
 *
 *   removed the impersonator-side unscoped short circuit  -> checks 3, 9 red
 *   removed the '*' short circuit in permissionRefusal()  -> checks 3, 4 red
 *   removed the impersonate.start grant check             -> check 11 red
 *   inverted the site subset direction                    -> checks 1, 6 red
 *   inverted the permission subset direction              -> five checks red
 *
 * A sixth -- deleting the catch-all branch on the TARGET side -- changed
 * nothing, and that is a fact about the branch rather than a gap here. A
 * catch-all target's id list contains the catch-all's id, the impersonator
 * does not have it, and array_diff refuses on the arithmetic alone. Check 8
 * below therefore stops asserting a refusal that would happen anyway and
 * asserts the thing the branch actually protects: that the refusal survives
 * a userSiteIDs() which reports a catch-all member as reaching nothing in
 * particular -- the shape any "all sites is not really a list" optimization
 * would take, and the shape under which the arithmetic silently flips to
 * allow.
 *
 * Usage: php tests/impersonation-subset-tests.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;
use FOG\Auth\Identity;
use FOG\Auth\SiteScope;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('impersonation-subset');

$fails = [];
$checks = 0;

function check($label, $cond, array &$fails, &$checks)
{
    $checks++;
    if (!$cond) {
        $fails[] = $label;
    }
}

/*
 * The fixture, as role and site membership rather than as answers.
 *
 * Driving Authorization::getPermissions() and SiteScope::userSiteIDs()
 * through their real SQL -- rather than stubbing the two methods -- is what
 * makes this a test of the subset logic instead of a test of itself. Both
 * own their queries deliberately (see their docblocks), so the shapes below
 * are the shapes production runs.
 */
/**
 * One scenario user's rows, in the column the real query selects.
 *
 * The scenario state is passed in rather than read from the enclosing scope
 * so that each helper states the shape it expects. These three ARE the
 * fixture: get one of them wrong and the subset tests below are measuring
 * the fixture rather than the code.
 *
 * @param array<int, array<int, int|string>> $state the scenario map
 * @param int                                $uid   the user asked about
 * @param string                             $col   the selected column
 *
 * @return array<int, array<string, int|string>>
 */
function scenarioRows(array $state, $uid, $col)
{
    $rows = [];
    foreach ((array)($state[$uid] ?? []) as $v) {
        $rows[] = [$col => $v];
    }

    return $rows;
}

/**
 * A COUNT(*) answer for a yes/no scenario flag.
 *
 * @param bool $flag the scenario's answer
 *
 * @return int
 */
function scenarioFlag($flag)
{
    return $flag ? 1 : 0;
}

/**
 * SiteScope::isUnscoped()'s COUNT for one user.
 *
 * The override wins where a scenario sets it, because it is the only way to
 * make a user a catch-all member WITHOUT putting the catch-all id in their
 * site list -- which is what check 8 needs to reach the target-side branch
 * at all.
 *
 * @param array<int, bool>               $override forced answers by user
 * @param array<int, array<int, int>>    $sites    site ids by user
 * @param int                            $catchAll the catch-all site id
 * @param int                            $uid      the user asked about
 *
 * @return int
 */
function scenarioUnscoped(array $override, array $sites, $catchAll, $uid)
{
    if (isset($override[$uid])) {
        return $override[$uid] ? 1 : 0;
    }
    $mine = (array)($sites[$uid] ?? []);

    return ($catchAll > 0 && in_array($catchAll, $mine, true)) ? 1 : 0;
}

$PERMS = [];
$SITES = [];
$CATCHALL = 0;
$SITES_IN_USE = true;
$UNSCOPED_OVERRIDE = [];

$db = FogTestHarness::fakeDb();
$db->responder = function ($sql, $params) use (
    &$PERMS,
    &$SITES,
    &$CATCHALL,
    &$SITES_IN_USE,
    &$UNSCOPED_OVERRIDE
) {
    // Authorization::getPermissions(): direct roles UNION group roles.
    if (false !== strpos($sql, 'FROM `roleUserAssoc`')
        && false !== strpos($sql, '`rpName`')
    ) {
        return scenarioRows($PERMS, (int)($params['userid'] ?? 0), 'rpName');
    }
    // SiteScope::sitesInUse() -- sites other than the catch-all.
    if (false !== strpos($sql, '`siteCatchAll` IS NULL')) {
        return ['cnt' => scenarioFlag($SITES_IN_USE)];
    }
    // SiteScope::isUnscoped() -- is any reachable site a catch-all.
    //
    // $UNSCOPED_OVERRIDE lets a scenario say "this user is a catch-all
    // member" WITHOUT putting the catch-all's id in their site list. Check
    // 8 needs exactly that: it is the only way to reach the branch whose
    // answer the id arithmetic does not already give.
    if (false !== strpos($sql, '`siteCatchAll` IS NOT NULL')) {
        return [
            'cnt' => scenarioUnscoped(
                $UNSCOPED_OVERRIDE,
                $SITES,
                $CATCHALL,
                (int)($params['uid'] ?? 0)
            )
        ];
    }
    // SiteScope::userSiteIDs() -- the four-arm reachability UNION.
    if (false !== strpos($sql, 'FROM `siteUserMembers`')) {
        return scenarioRows($SITES, (int)($params['uid'] ?? 0), 'siteID');
    }
    return null;
};

/**
 * Clear every per-request memo the two resolvers keep.
 *
 * Both cache by user id for the life of a request, which is correct in
 * production and would make every scenario after the first read the one
 * before it here.
 *
 * @return void
 */
function forgetScenario()
{
    Authorization::resetCache();
    SiteScope::forgetCaches();
    $p = new \ReflectionProperty(SiteScope::class, '_sitesInUse');
    $p->setAccessible(true);
    $p->setValue(null, null);
}

/*
 * 1. The ordinary allow: a helpdesk role whose grants are a strict subset,
 *    in a site the administrator also reaches.
 */
forgetScenario();
$PERMS = [
    1 => ['host.view', 'host.edit', 'user.view', 'impersonate.start'],
    7 => ['host.view']
];
$SITES = [1 => [10, 11], 7 => [10]];
$SITES_IN_USE = true;
$CATCHALL = 99;

check(
    'a strict subset in a shared site is refused: '
    . Identity::refusalReason(1, 7),
    '' === Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 2. The permission test bites: the target holds something the
 *    administrator does not.
 */
forgetScenario();
$PERMS = [
    1 => ['host.view', 'impersonate.start'],
    7 => ['host.view', 'snapin.delete']
];
check(
    'a target holding a permission the impersonator lacks is allowed',
    '' !== Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 3. A holder of '*' may impersonate anybody, INCLUDING somebody carrying a
 *    permission string no registry declares -- a grant left behind by an
 *    uninstalled plugin. can() answers those TRUE for a '*' holder (it
 *    leaves an unregistered node alone and then matches the star), so
 *    expanding '*' to the registry and comparing sets would make the
 *    administrator look narrower than they are and refuse.
 */
forgetScenario();
$PERMS = [
    1 => ['*'],
    7 => ['host.view', 'retiredplugin.view']
];
$SITES = [1 => [10], 7 => [10, 11]];
check(
    "a '*' holder is refused over a stale plugin grant: "
    . Identity::refusalReason(1, 7),
    '' === Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 4. ADMIN IMPERSONATES ADMIN IS ALLOWED, decided rather than emergent.
 *    '*' is a superset of everything so it passes on the arithmetic; the
 *    decision is that refusing it would buy nothing, because neither party
 *    gains access they did not have.
 */
forgetScenario();
$PERMS = [1 => ['*'], 7 => ['*']];
$SITES = [1 => [], 7 => []];
check(
    'an administrator may not impersonate another administrator: '
    . Identity::refusalReason(1, 7),
    '' === Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 5. The reverse is refused: a scoped administrator may not become a full
 *    one. This is the check that would silently invert if the two argument
 *    positions were ever swapped.
 */
forgetScenario();
$PERMS = [1 => ['host.view', 'impersonate.start'], 7 => ['*']];
check(
    'a scoped administrator may impersonate a full one',
    '' !== Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 6. A TARGET IN NO SITE AT ALL IS ALLOWED. The empty set nests inside
 *    anything, and here that is not a loophole: a user in no site reaches
 *    no scoped object, so becoming them cannot widen the administrator's
 *    reach in any direction. It is also the likeliest real ticket -- a new
 *    account that sees nothing and cannot say why.
 */
forgetScenario();
$PERMS = [1 => ['host.view', 'impersonate.start'], 7 => ['host.view']];
$SITES = [1 => [10], 7 => []];
check(
    'a target in no site at all is refused: ' . Identity::refusalReason(1, 7),
    '' === Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 7. The site test bites on its own, with permissions identical. Test 1
 *    cannot see this, which is the entire reason there are two tests.
 */
forgetScenario();
$PERMS = [1 => ['host.view', 'impersonate.start'], 7 => ['host.view']];
$SITES = [1 => [10], 7 => [11]];
check(
    'a Site A administrator may reach a Site B user whose permissions nest',
    '' !== Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 8. THE CATCH-ALL TARGET, asserted where it is actually decided.
 *
 *    The target sees the whole install; the administrator is in two
 *    ordinary sites. The fixture reports the target as reaching NO named
 *    site while still answering isUnscoped() -- so the id arithmetic would
 *    say the empty set nests inside {10,11} and allow it, and only the
 *    catch-all branch can refuse.
 *
 *    That is deliberately not how userSiteIDs() behaves today, and the
 *    check is written this way because of it: with today's behavior the
 *    branch cannot change any answer, so a fixture using it would pass with
 *    the branch deleted and prove nothing. "All sites" is not naturally an
 *    id list, so this is the shape the read would take the first time
 *    somebody optimizes it -- and it is the shape in which the arithmetic
 *    silently stops refusing.
 */
forgetScenario();
$PERMS = [1 => ['host.view', 'impersonate.start'], 7 => ['host.view']];
$CATCHALL = 99;
$SITES = [1 => [10, 11], 7 => []];
$UNSCOPED_OVERRIDE = [7 => true];
check(
    'a catch-all target is reachable by a two-site administrator',
    '' !== Identity::refusalReason(1, 7),
    $fails,
    $checks
);
$UNSCOPED_OVERRIDE = [];

/*
 * 9. The same shape the other way round: a catch-all ADMINISTRATOR is a
 *    site superset of everybody, so the ordinary-site target is allowed.
 */
forgetScenario();
$SITES = [1 => [99], 7 => [10, 11]];
check(
    'a catch-all administrator is refused an ordinary-site target: '
    . Identity::refusalReason(1, 7),
    '' === Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 10. NO SITE IN USE: scoping is switched off, so site membership decides
 *     nothing. Right answer, and stated rather than reached by accident.
 */
forgetScenario();
$SITES_IN_USE = false;
$CATCHALL = 0;
$SITES = [1 => [10], 7 => [11]];
check(
    'site membership still refuses on an install using no sites: '
    . Identity::refusalReason(1, 7),
    '' === Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 11. The capability is a grant of its own. Holding every permission the
 *     target holds is necessary and not sufficient: without
 *     impersonate.start nobody may impersonate anybody, which is what makes
 *     this deny-by-default on upgrade rather than a power every user
 *     administrator silently acquires.
 */
forgetScenario();
$SITES_IN_USE = true;
$PERMS = [1 => ['host.view', 'user.edit'], 7 => ['host.view']];
$SITES = [1 => [10], 7 => [10]];
check(
    'impersonation is permitted without the impersonate.start grant',
    '' !== Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 * 12. Self-impersonation is refused. Not a security property -- it opens a
 *     span that means nothing and puts a bracket in the audit trail around
 *     ordinary work.
 */
forgetScenario();
$PERMS = [1 => ['*']];
$SITES = [1 => [10]];
check(
    'a user may impersonate themselves',
    '' !== Identity::refusalReason(1, 1),
    $fails,
    $checks
);

/*
 * 13. The wildcard expansion is against the registry, not string-matched.
 *     `host.*` has to cover every action `host` declares, or an
 *     administrator holding the wildcard would be refused a target holding
 *     one plain action of it.
 */
forgetScenario();
$PERMS = [
    1 => ['host.*', 'impersonate.start'],
    7 => ['host.view', 'host.edit', 'host.delete']
];
$SITES = [1 => [10], 7 => [10]];
check(
    'a host.* holder is refused a target holding plain host actions: '
    . Identity::refusalReason(1, 7),
    '' === Identity::refusalReason(1, 7),
    $fails,
    $checks
);

/*
 *     ...and it must not leak across nodes: host.* says nothing about
 *     snapins.
 */
forgetScenario();
$PERMS = [
    1 => ['host.*', 'impersonate.start'],
    7 => ['host.view', 'snapin.view']
];
check(
    'host.* was treated as covering another node entirely',
    '' !== Identity::refusalReason(1, 7),
    $fails,
    $checks
);

if (count($fails)) {
    fwrite(STDERR, "FAIL (" . count($fails) . " of $checks checks)\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
