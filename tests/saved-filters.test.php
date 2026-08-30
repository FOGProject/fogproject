<?php
/**
 * Saved grid filters: the four things that fail SILENTLY if they drift.
 *
 * None of these shows up as an error. A filter applied on page load looks
 * like a grid that lost rows; a filter listed three times looks like three
 * filters; a global filter saved without the permission looks like it worked;
 * a remembered search TERM looks like a short grid with no visible cause.
 *
 *  1. listFor() reaches every share path from ONE select, so a filter that a
 *     user can see by several routes at once is still one row. The moment it
 *     becomes a UNION of per-path selects the picker starts repeating itself.
 *  2. The provenance precedence is most-specific-first. The badge a user sees
 *     has to describe the most deliberate grant, or "shared with you by your
 *     manager" silently becomes "everyone".
 *  3. Nothing is ever applied on load. The whole design rests on it: the
 *     saved state of a grid carries no search, and a filter is applied only
 *     by a click that the user can see and undo with the chip's x.
 *  4. Saving a filter FOR EVERYONE takes savedfilter.create. It is an access
 *     control decision, not a column: a global filter appears in every user's
 *     picker.
 *
 * Usage: php tests/saved-filters.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$manager = file_get_contents($web . '/src/Managers/SavedFilterManager.php');
$route = file_get_contents($web . '/src/Router/Route.php');
$authz = file_get_contents($web . '/src/Auth/Authorization.php');
$js = file_get_contents($web . '/management/js/fog/fog.filters.js');
$common = file_get_contents($web . '/management/js/fog/fog.common.js');

$fails = [];

/**
 * One named method's body, so an assertion cannot pass on a comment or on an
 * unrelated method further down the file.
 *
 * @param string $src  the file
 * @param string $name the method
 *
 * @return string
 */
function sf_body($src, $name)
{
    if (!preg_match(
        '/function ' . preg_quote($name, '/') . '\(.*?\n    \}/s',
        $src,
        $m
    )) {
        return '';
    }

    return $m[0];
}

/**
 * The same body with its // comments removed.
 *
 * Without this the UNION check below fails on the comment EXPLAINING that
 * there is no union -- a gate passing (or failing) on its own documentation
 * rather than on the code it guards.
 *
 * @param string $body the method body
 *
 * @return string
 */
function sf_code($body)
{
    return preg_replace('#^\s*//.*$#m', '', $body);
}

// --- 1. one select, not a union ------------------------------------------

$listFor = sf_body($manager, 'listFor');
if ('' === $listFor) {
    $fails[] = 'SavedFilterManager::listFor() not found';
} else {
    if (false !== stripos(sf_code($listFor), 'union')) {
        $fails[] = 'listFor() uses UNION -- a filter reachable by several'
            . ' share paths will be listed once per path';
    }
    if (1 !== preg_match_all('/SELECT `sfID`|SELECT f\.`sfID`/', $listFor)) {
        $fails[] = 'listFor() no longer builds exactly one row-returning'
            . ' select over savedFilters';
    }
    // The three share paths, each an OR arm of that one select.
    foreach (
        [
            'savedFilterUserAssoc' => 'direct',
            'savedFilterGroupAssoc' => 'group',
            'savedFilterRoleAssoc' => 'role'
        ] as $table => $what
    ) {
        if (false === strpos($listFor, $table)) {
            $fails[] = "listFor() no longer reads the $what share table"
                . " ($table)";
        }
    }
}

// --- 2. precedence, most specific first ----------------------------------

if ('' !== $listFor) {
    if (!preg_match_all(
        "/\\\$source = '(mine|user|group|role|global)'/",
        $listFor,
        $m
    )) {
        $fails[] = 'listFor() no longer reports a share source';
    } elseif ($m[1] !== ['mine', 'user', 'group', 'role', 'global']) {
        $fails[] = 'listFor() reports sources in the order '
            . implode(' > ', $m[1])
            . ' -- it must be mine > user > group > role > global, most'
            . ' deliberate grant first';
    }
    // The order above is only meaningful if the tests are a fall-through
    // chain: separate ifs would let a later one overwrite an earlier one.
    if (!preg_match(
        "/if \(\\\$mine\) \{.*?\} elseif \(\\\$row\['viaUser'\]\).*?"
        . "\} elseif \(\\\$row\['viaGroup'\]\).*?"
        . "\} elseif \(\\\$row\['viaRole'\]\).*?\} else \{/s",
        $listFor
    )) {
        $fails[] = 'listFor() no longer picks the source with a single'
            . ' if/elseif fall-through, so a less specific grant can win';
    }
}

// --- 3. nothing is applied on load ---------------------------------------

// The definition itself is excluded; what is counted is call sites.
$calls = preg_match_all(
    '/(?<!function )applyFilter\(dt, filter\)/',
    $js,
    $m,
    PREG_OFFSET_CAPTURE
);
if (1 !== $calls) {
    $fails[] = 'fog.filters.js applies a filter from ' . $calls
        . ' places -- there must be exactly one, the Apply button';
} else {
    $before = substr($js, max(0, $m[0][0][1] - 200), 200);
    if (false === strpos($before, "addEventListener('click'")) {
        $fails[] = 'the one applyFilter() call is no longer inside a click'
            . ' handler, so a filter can be applied without the user asking';
    }
}
foreach (['DOMContentLoaded', '$(document).ready', 'doPageLoad'] as $onload) {
    if (false !== strpos($js, $onload)) {
        $fails[] = "fog.filters.js registers $onload -- this file must do"
            . ' nothing at all until the toolbar button is clicked';
    }
}

// --- 4. the affordance is a boolean, never a term ------------------------

$store = sf_body($common, 'fogAffordanceStore');
if ('' === $store) {
    $fails[] = 'fogAffordanceStore() not found';
} elseif (!preg_match("/fogPrefStore\(key, on \? '1' : ''\)/", $store)) {
    $fails[] = 'fogAffordanceStore() no longer writes a plain boolean -- the'
        . ' search row is remembered as SHOWN, never as what was typed in it';
}

// --- 5. a global filter takes savedfilter.create -------------------------

$gate = sf_body($route, '_mayManageGlobalFilters');
if ('' === $gate) {
    $fails[] = 'Route::_mayManageGlobalFilters() not found';
} elseif (false === strpos($gate, "Authorization::can('savedfilter.'")) {
    $fails[] = '_mayManageGlobalFilters() no longer consults Authorization';
}
$handler = sf_body($route, 'savedfilters');
if ('' === $handler) {
    $fails[] = 'Route::savedfilters() not found';
} else {
    // Anchored on the store() CALL, not on a count of mentions: the handler
    // names the permission three times (to advertise it, to enforce it, and
    // to pick the status code), so a count of two still passed with the one
    // that actually enforces it replaced by `true`.
    if (!preg_match(
        '/\$manager->store\(.*?self::_mayManageGlobalFilters\(\'create\'\)'
        . '\s*\);/s',
        $handler
    )) {
        $fails[] = 'Route::savedfilters() no longer passes the caller\'s'
            . ' global-filter permission to store(), so a global filter can'
            . ' be saved by anybody';
    }
    if (false === strpos(
        $handler,
        "'mayShareGlobally' => self::_mayManageGlobalFilters('create')"
    )) {
        $fails[] = 'Route::savedfilters() no longer tells the client whether'
            . ' it may offer the "everyone" option';
    }
    if (false === strpos($handler, 'HTTP_FORBIDDEN')) {
        $fails[] = 'Route::savedfilters() no longer answers 403 when a global'
            . ' filter is refused';
    }
}
$store = sf_body($manager, 'store');
if ('' === $store) {
    $fails[] = 'SavedFilterManager::store() not found';
} elseif (!preg_match('/\$mayManageGlobal\b/', $store)) {
    $fails[] = 'SavedFilterManager::store() no longer takes the caller\'s'
        . ' global-filter permission, so the route is the only guard';
}
if (false === strpos($authz, "'savedfilter' => [")) {
    $fails[] = 'Authorization::coreRegistry() no longer registers the'
        . ' savedfilter permission node, so nobody can be granted it';
}

// --- report ---------------------------------------------------------------

if ($fails) {
    fwrite(STDERR, "FAIL saved-filters\n");
    foreach ($fails as $fail) {
        fwrite(STDERR, '  - ' . $fail . "\n");
    }
    exit(1);
}

echo "PASS saved-filters\n";
exit(0);
