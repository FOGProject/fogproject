<?php
/**
 * A node with a page class must survive the known-node guard.
 *
 * FOGPage::buildMainMenuItems() redirects any $node absent from its known
 * list back to the dashboard. It runs from Page::render() -- AFTER
 * FOGPageManager::render() has dispatched and the page has echoed itself
 * into the output buffer -- so a missing node does not 404 and does not
 * deny. It renders, is discarded, and the browser lands on the dashboard.
 *
 * On screen that is indistinguishable from a link that does nothing, and
 * there is no log line, no status code and no message. That is how
 * `impersonate` shipped: the page class registered, the permission check
 * passed, index() built its card, and the guard threw the whole thing away
 * because the node has no sidebar entry.
 *
 * Two properties, and the split matters:
 *
 *   (a) EVERY node declared by a *.page.php is reachable. This is the
 *       general invariant and it fails for whichever node is next.
 *   (b) The guard's list is DERIVED from Authorization::exemptNodes()
 *       rather than keeping its own copy of the same names. Asserted by
 *       calling FOGPage::knownNodes() -- the function the guard itself
 *       calls -- so removing the merge makes this red. A test that merely
 *       re-computed the union would pass on the broken code, because it
 *       would be checking its own arithmetic rather than the guard's.
 *
 * MUTATION-VERIFIED:
 *
 *   drop Authorization::exemptNodes() from knownNodes() -> (b) and the
 *       impersonate/logout/hwinfo arms of (a) red
 *   remove 'impersonate' from Authorization::EXEMPT_NODES -> (a) red for
 *       impersonate
 *   restore the old hardcoded literal list in place of knownNodes() -> (b)
 *       red
 *
 * Usage: php tests/menu-guard-reaches-every-page-node.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Auth\Authorization;
use FOG\Base\FOGPage;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('menu-guard');
FogTestHarness::fakeDb();

$fails = [];
$checks = 0;

/**
 * Record one assertion.
 *
 * By reference rather than through `global`, matching the sibling test
 * files: a global write is invisible to static analysis, so the final
 * `count($fails)` reads as always-zero and the whole failure path is
 * reported as dead code.
 *
 * @param string   $label  what was being asserted
 * @param bool     $cond   whether it held
 * @param string[] $fails  collected failures
 * @param int      $checks assertions run
 *
 * @return void
 */
function check($label, $cond, array &$fails, &$checks)
{
    $checks++;
    if (!$cond) {
        $fails[] = $label;
    }
}

$web = dirname(__DIR__) . '/packages/web';

/*
 * The sidebar's own nodes, read out of the $menu literal in
 * buildMainMenuItems(). Parsed rather than called: building the real menu
 * needs a signed-in user, the hook manager and a database, and none of that
 * changes which keys the literal declares.
 *
 * Bounded to the function body so the parse cannot drift into an unrelated
 * array further down the file.
 */
$src = (string)file_get_contents($web . '/src/Base/FOGPage.php');
$start = strpos($src, 'public static function buildMainMenuItems');
$end = strpos($src, 'public static function knownNodes');
check(
    'buildMainMenuItems and knownNodes are both present in FOGPage',
    false !== $start && false !== $end && $end > $start,
    $fails,
    $checks
);
$body = (false !== $start && false !== $end)
    ? substr($src, $start, $end - $start)
    : '';
preg_match_all("#^\s{12}'([a-z0-9_]+)' => \[#m", $body, $m);
$menuNodes = $m[1];
/*
 * Plus the ones inserted conditionally rather than declared in the literal
 * -- `plugin` is added by arrayInsertAfter() only when FOG_PLUGINSYS_ENABLED
 * is on. It is a sidebar node whenever the feature exists, so the guard is
 * doing the right thing by redirecting it when the feature is off; what it
 * must not do is redirect it when the feature is on.
 */
preg_match_all(
    "#arrayInsertAfter\(\s*'[a-z0-9_]+',\s*\\\$menu,\s*'([a-z0-9_]+)'#",
    $body,
    $ins
);
$menuNodes = array_merge($menuNodes, $ins[1]);
check(
    'the sidebar menu literal parsed to a plausible node list',
    count($menuNodes) > 10,
    $fails,
    $checks
);

/*
 * (a) EVERY page class's node reaches dispatch.
 *
 * A page class is the definition of "this node exists": FOGPageManager
 * registers one only when its declared $node equals the requested one, so a
 * node no page class declares is genuinely unreachable and SHOULD redirect.
 */
$known = FOGPage::knownNodes($menuNodes);
$pageNodes = [];
foreach ((array)glob($web . '/lib/pages/*.page.php') as $file) {
    $text = (string)file_get_contents($file);
    if (!preg_match("#public\s+\\\$node\s*=\s*'([a-z0-9_]+)'#", $text, $n)) {
        continue;
    }
    $pageNodes[$n[1]] = basename($file);
}
check(
    'the page classes parsed to a plausible node list',
    count($pageNodes) > 10,
    $fails,
    $checks
);
foreach ($pageNodes as $node => $file) {
    check(
        "$file declares node '$node', which the menu guard redirects away",
        in_array($node, $known, true),
        $fails,
        $checks
    );
}

/*
 * (b) The guard DERIVES its escapes from the exempt-node list.
 *
 * Every exempt node is by definition a real node that carries its own gate
 * instead of a permission, which is precisely the set most likely to have no
 * sidebar entry. Before this was derived, five of them were written out by
 * hand in the guard as well -- and the sixth and seventh, `login` and
 * `impersonate`, were not.
 *
 * Asserted through knownNodes() with an EMPTY menu, so a name that only
 * survives because it also has a sidebar entry cannot mask a missing merge.
 */
$escapes = FOGPage::knownNodes();
foreach (Authorization::exemptNodes() as $exempt) {
    check(
        "exempt node '$exempt' is not reachable when it has no menu entry",
        in_array($exempt, $escapes, true),
        $fails,
        $checks
    );
}

/*
 * And the guard still redirects something that is genuinely not a node --
 * otherwise the fix would be "let everything through", which loses the
 * stale-bookmark behavior the guard exists for.
 */
check(
    'an unknown node is treated as known',
    !in_array('nosuchnode', FOGPage::knownNodes($menuNodes), true),
    $fails,
    $checks
);

if (count($fails)) {
    fwrite(STDERR, 'FAIL (' . count($fails) . " of $checks checks)\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
