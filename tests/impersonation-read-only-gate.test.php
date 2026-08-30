<?php
/**
 * A span may read, and may write the impersonated user's own preferences.
 *
 * The gate is an ALLOWLIST rather than a list of forbidden operations, and
 * the choice is the thing this file defends. The obvious shape is to refuse
 * the four operations that turn a temporary view into permanent account
 * takeover -- password change, API token creation, role assignment, auth
 * source change. This repository already records why that shape rots: ADR
 * 0021's account of `storagenode.pass` ends "naming them per route is what
 * hid this". A refusal list must be re-audited whenever somebody adds a
 * route. An allowlist need not be.
 *
 * The allowlist also closes something no refusal list could see:
 * FOGController::save() auto-fills `createdBy` from self::$FOGUser, which is
 * the MASK, so an ordinary create performed mid-span would stamp the
 * target's name onto the row itself -- a second attribution forgery, in a
 * column no audit change repairs.
 *
 * Three properties are pinned:
 *
 *   (a) Reads pass, writes do not, on both surfaces.
 *   (b) The EXIT is reachable in every permission state. Impersonate a user
 *       holding no roles and a gated exit traps the administrator as them,
 *       with no way back short of clearing a cookie. This is asserted
 *       against Authorization::exemptNodes() -- the list
 *       resolvePagePermission() actually consults, so a plugin that
 *       reshaped it is covered too -- not against a copy of the const.
 *   (c) `schema` is refused even though it is an exempt node that resolves
 *       to NO permission at all. That is the case a gate keyed only on the
 *       permission string would wave straight through, and a schema deploy
 *       rewrites the whole database.
 *
 * MUTATION-VERIFIED:
 *
 *   removed the '.view' suffix test          -> every write check red
 *   added 'schema' to the allowed page nodes -> check (c) red
 *   dropped the METHOD from the API entries  -> the savedfilter write red
 *   removed 'impersonate' from EXEMPT_NODES  -> the exit checks red
 *
 * Usage: php tests/impersonation-read-only-gate.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;
use FOG\Auth\Identity;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('impersonation-gate');

$fails = [];
$checks = 0;

function check($label, $cond, array &$fails, &$checks)
{
    $checks++;
    if (!$cond) {
        $fails[] = $label;
    }
}

FogTestHarness::fakeDb();

/**
 * The gate's decision for a page request.
 *
 * @param string|null $perm the resolved permission
 * @param string      $node the page node
 *
 * @return bool
 */
function pagePermits($perm, $node)
{
    return Authorization::impersonationPermits($perm, 'page', $node);
}

/**
 * The gate's decision for an API request.
 *
 * @param string|null $perm   the resolved permission
 * @param string      $route  the matched route name
 * @param string      $method the HTTP method
 *
 * @return bool
 */
function apiPermits($perm, $route, $method)
{
    return Authorization::impersonationPermits($perm, 'api', $route, $method);
}

/*
 * (a) READS PASS.
 *
 * Every ordinary page is reached with a resolved `.view`, including the
 * GET of an edit form -- Authorization::_subToAction() answers 'view' for a
 * GET whatever the sub is called, so opening host edit is a read and only
 * submitting it is a write. That is exactly the behavior impersonation
 * wants: the administrator sees the form the target sees.
 */
foreach (['host.view', 'user.view', 'task.view', 'oidc.view'] as $perm) {
    check(
        "a read ($perm) is refused to a span",
        pagePermits($perm, 'host'),
        $fails,
        $checks
    );
}

/*
 *     WRITES DO NOT. One per action the registry declares, so a new action
 *     added to a node cannot quietly inherit "allowed".
 */
$writes = [
    'host.edit',
    'host.create',
    'host.delete',
    'host.task',
    'user.edit',
    'user.create',
    'role.edit',
    'apitoken.create',
    'settings.edit',
    'system.export',
    'plugin.install'
];
foreach ($writes as $perm) {
    check(
        "a write ($perm) is permitted to a span",
        !pagePermits($perm, 'host'),
        $fails,
        $checks
    );
}

/*
 *     ...and the four that turn a view into account takeover, named
 *     explicitly because they are the ones the brief called out. They are
 *     refused by the general rule rather than by being listed, which is the
 *     whole point -- but a reader deserves to see them tested by name.
 */
$takeover = [
    'user.edit' => 'password change / auth source change',
    'apitoken.create' => 'API token creation',
    'role.edit' => 'role assignment',
    'role.create' => 'role creation'
];
foreach ($takeover as $perm => $what) {
    check(
        "$what ($perm) is permitted to a span",
        !pagePermits($perm, 'user'),
        $fails,
        $checks
    );
}

/*
 * (c) THE NULL-PERMISSION TRAP. Every EXEMPT_NODES entry resolves to no
 *     permission at all, so can() waves it through and a gate keyed on the
 *     permission string alone cannot see it. `schema` is the one that
 *     matters: a schema deploy rewrites the whole database, and
 *     FOGBase::isSchemaAdmin() reads the CURRENT user -- which is the mask.
 */
check(
    'the schema updater is reachable behind a mask',
    !pagePermits(null, 'schema'),
    $fails,
    $checks
);
check(
    'the client endpoint node is reachable behind a mask',
    !pagePermits(null, 'client'),
    $fails,
    $checks
);
check(
    'the login node is reachable behind a mask',
    !pagePermits(null, 'login'),
    $fails,
    $checks
);

/*
 *     ...while the three that must work still do. `home` is where a refusal
 *     redirects TO, so refusing it would loop.
 */
foreach (['home', 'logout', 'impersonate'] as $node) {
    check(
        "the $node node is refused to a span",
        pagePermits(null, $node),
        $fails,
        $checks
    );
}

/*
 * (b) THE EXIT IS UNGATED, asserted against the real constant.
 *
 *     Two independent things have to hold, and the second is the one that
 *     bites: the gate must let `impersonate` through, AND
 *     resolvePagePermission() must answer null for it, because a target
 *     holding no roles satisfies no permission whatsoever. If `impersonate`
 *     ever became an ordinary registry node, the gate check above would
 *     still pass and the administrator would still be trapped.
 */
check(
    'impersonate is not an exempt node, so leaving is permission-checked',
    in_array('impersonate', Authorization::exemptNodes(), true),
    $fails,
    $checks
);
check(
    'ending impersonation resolves to a permission somebody could lack',
    null === Authorization::resolvePagePermission('impersonate', 'end'),
    $fails,
    $checks
);
check(
    'the logout node resolves to a permission somebody could lack',
    null === Authorization::resolvePagePermission('logout', ''),
    $fails,
    $checks
);

/*
 *     And the grant that gates STARTING is a real, declared action -- so it
 *     can be granted to a helpdesk role and withheld from another. A
 *     permission string absent from the registry is ungrantable by
 *     construction (assertCanGrant checks the same registry), which would
 *     silently make impersonation administrator-only forever.
 */
$registry = Authorization::registry();
check(
    'impersonate is not a registry node, so the grant is ungrantable',
    isset($registry['impersonate']),
    $fails,
    $checks
);
check(
    'impersonate.start is not a declared action',
    in_array('start', (array)($registry['impersonate'] ?? []), true),
    $fails,
    $checks
);
check(
    'impersonate declares an end action, which would make leaving grantable'
    . ' -- and therefore withholdable',
    !in_array('end', (array)($registry['impersonate'] ?? []), true),
    $fails,
    $checks
);

/*
 * THE API SURFACE. The routes a span may still write resolve to a NULL
 * permission (Authorization::API_ROUTE_PERMISSIONS), so nothing in the
 * permission string distinguishes "the user's own timezone preference"
 * from "deploy the schema". The route name and the METHOD do.
 */
check(
    'a generic list route is refused to a span',
    apiPermits('host.view', 'list', 'GET'),
    $fails,
    $checks
);
check(
    'a generic create route is permitted to a span',
    !apiPermits('host.create', 'create', 'POST'),
    $fails,
    $checks
);
check(
    'a generic update route is permitted to a span',
    !apiPermits('host.edit', 'update', 'PUT'),
    $fails,
    $checks
);
check(
    'a generic delete route is permitted to a span',
    !apiPermits('host.delete', 'delete', 'DELETE'),
    $fails,
    $checks
);

/*
 * The preference routes: readable AND writable, because the impersonated
 * user's own preferences are the one thing a span is for. Route::userpref()
 * takes its user id from the session, which is the mask, so a write here
 * lands on the target -- which is the point.
 */
foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
    check(
        "userpref $method is refused to a span",
        apiPermits(null, 'userpref', $method),
        $fails,
        $checks
    );
}

/*
 * Saved filters are readable and NOT writable, and the split is the reason
 * the allowlist carries methods at all. A saved filter can be shared with
 * named people or made global, which is an outward act rather than a view
 * preference -- so reading the picker works and saving one does not.
 */
check(
    'reading saved filters is refused to a span',
    apiPermits(null, 'savedfilters', 'GET'),
    $fails,
    $checks
);
check(
    'writing a saved filter is permitted to a span',
    !apiPermits(null, 'savedfilters', 'POST'),
    $fails,
    $checks
);
check(
    'deleting a saved filter is permitted to a span',
    !apiPermits(null, 'savedfilter', 'DELETE'),
    $fails,
    $checks
);

/*
 * An unlisted null-permission route is refused rather than waved through.
 * This is the allowlist's whole shape: something added tomorrow is denied
 * until somebody names it, instead of being permitted until somebody
 * remembers to forbid it.
 */
check(
    'an unlisted null-permission route defaults to permitted',
    !apiPermits(null, 'somethingaddedtomorrow', 'POST'),
    $fails,
    $checks
);
check(
    'an unlisted null-permission route is permitted even on GET',
    !apiPermits(null, 'somethingaddedtomorrow', 'GET'),
    $fails,
    $checks
);

/*
 * A permission ending in something that merely CONTAINS view must not pass.
 * The suffix test is on '.view' precisely so a node called `overview` or an
 * action called `preview` cannot slip through.
 */
check(
    'a permission merely containing view is treated as a read',
    !pagePermits('host.viewsomething', 'host'),
    $fails,
    $checks
);
check(
    'a node whose name ends in view is treated as a read',
    !pagePermits('overview.edit', 'overview'),
    $fails,
    $checks
);

/*
 * And the gate is inert when nobody is impersonating. Asserted through the
 * caller rather than the decision function, because that is where the
 * short circuit lives: impersonationPermits() answers the DECISION only.
 */
check(
    'a span is reported open when the session carries none',
    !Identity::isImpersonating(),
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
