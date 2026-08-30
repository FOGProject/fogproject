<?php
/**
 * Exactly one thing decides whether a node exists.
 *
 * A node exists if and only if some *.page.php declares it, which is what
 * FOGPageManager::loadPageClasses() computes and what
 * FOGPageManager::render() then acts on. Any SECOND list of "the nodes that
 * exist" is a hand-maintained copy of a derived fact, and it drifts.
 *
 * It drifted. FOGPage::buildMainMenuItems() kept its own list -- the sidebar
 * keys plus a hardcoded ['home','logout','hwinfo','client','schema','ipxe']
 * -- and redirected anything absent from it. Five of those six were also
 * Authorization::EXEMPT_NODES entries, written out a second time by hand;
 * the sixth and seventh exempt nodes, `login` and `impersonate`, were not.
 *
 * The failure that produces is silent in a way a wrong permission is not.
 * The guard runs from Page::render(), AFTER dispatch has accepted the node
 * and the page has echoed itself into the output buffer, so the page is
 * built and then thrown away: no status code, no message, nothing in any
 * log, and on screen indistinguishable from a link that does nothing.
 * `impersonate` shipped that way.
 *
 * So this pins the ownership rather than the list:
 *
 *   (a) FOGPageManager::render() HAS the unknown-node arm. If this is ever
 *       removed, an unknown node reaches getFOGPageClass() on a missing key.
 *   (b) buildMainMenuItems() does NOT redirect. It runs after dispatch, so
 *       any redirect it makes is necessarily throwing away a page that
 *       dispatch already accepted -- there is no case where it is right.
 *   (c) Nothing anywhere in FOGPage.php -- the sidebar, menu and chrome
 *       layer -- turns a node away. Broader than (b) because the next copy
 *       of this mistake will be in a different function.
 *
 * (c) is the one that catches the NEXT instance rather than this one. (a)
 * and (b) describe today's two functions; (c) describes the mistake.
 *
 * A first cut of (c) scanned buildMainMenuItems() for EXEMPT_NODES names
 * appearing as literals. It reported two that were fine -- 'client' is a
 * genuine sidebar entry, 'schema' is an early return -- because "this name
 * is mentioned" is not the same claim as "this name gates reachability".
 *
 * MUTATION-VERIFIED:
 *
 *   add a redirect() back into buildMainMenuItems()      -> (b) red
 *   restore the hardcoded escape list in the menu builder -> (b) and (c) red
 *   delete the unknown-node arm from FOGPageManager       -> (a) red
 *
 * See ADR 0034.
 *
 * Usage: php tests/one-authority-for-which-nodes-exist.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('node-authority');

$fails = [];
$checks = 0;

/**
 * Record one assertion.
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

/**
 * The source of one function, from its signature to the next one.
 *
 * Bounded so a match cannot drift into an unrelated method further down a
 * 1700-line file and report a property of the wrong code.
 *
 * @param string $src  the whole file
 * @param string $sig  the function signature to find
 *
 * @return string '' when the signature is absent
 */
function functionBody($src, $sig)
{
    $start = strpos($src, $sig);
    if (false === $start) {
        return '';
    }
    $next = strpos($src, ' function ', $start + strlen($sig));

    return false === $next
        ? substr($src, $start)
        : substr($src, $start, $next - $start);
}

/**
 * PHP source with its comments removed.
 *
 * A gate that greps raw source passes -- or, as here, FAILS -- on its own
 * documentation: the comment explaining why buildMainMenuItems() must not
 * call redirect() contains the string "redirect(". Tokenizing is the only
 * way to ask about code rather than about prose.
 *
 * @param string $src PHP source, with or without an opening tag
 *
 * @return string
 */
function stripComments($src)
{
    $out = '';
    foreach (token_get_all("<?php " . $src) as $token) {
        if (is_array($token)
            && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
}

$web = dirname(__DIR__) . '/packages/web';
$manager = (string)file_get_contents($web . '/src/Base/FOGPageManager.php');
$page = (string)file_get_contents($web . '/src/Base/FOGPage.php');

/*
 * (a) DISPATCH OWNS THE UNKNOWN-NODE CASE.
 *
 * The whole condition, not a grep for the method name: a test that matched
 * only "array_key_exists" would stay green if the arm were rewritten to
 * check something else, or wrapped in a condition that is never true.
 */
$render = functionBody($manager, 'public function render()');
check(
    'FOGPageManager::render() was not found, so nothing below was checked',
    '' !== $render,
    $fails,
    $checks
);
check(
    'dispatch no longer tests whether the node registered a page class',
    false !== strpos(
        $render,
        'if (!array_key_exists($this->classValue, $this->_nodes)) {'
    ),
    $fails,
    $checks
);
check(
    'dispatch recognizes an unknown node but does not turn it away',
    false !== strpos($render, "self::redirect('../management/index.php');"),
    $fails,
    $checks
);

/*
 * (b) THE MENU BUILDER DOES NOT REDIRECT.
 *
 * It runs from Page::render(), after dispatch has accepted the node and the
 * page has already rendered into the output buffer. Anything it turns away
 * is therefore a page that was going to work.
 */
$menu = functionBody($page, 'public static function buildMainMenuItems');
check(
    'buildMainMenuItems() was not found, so nothing below was checked',
    '' !== $menu,
    $fails,
    $checks
);
check(
    'buildMainMenuItems() redirects, which can only discard a good page',
    false === strpos(stripComments($menu), 'redirect('),
    $fails,
    $checks
);

/*
 * (c) AND NOTHING IN THE MENU/RENDER LAYER TURNS A NODE AWAY AT ALL.
 *
 * Broader than (b) on purpose: (b) names one function, and the next copy of
 * this mistake will be in a different one. FOGPage.php is where the sidebar,
 * the menu structure and the page chrome live, and none of that is entitled
 * to decide that a node does not exist -- dispatch decided that already, and
 * anything here runs after it.
 *
 * The three legitimate '../management/index.php' redirects in the codebase
 * are management/index.php's logout branch, the schema updater bouncing a
 * database that is already current, and the dispatch arm itself. None is in
 * this file, and none is a menu decision.
 */
check(
    'FOGPage.php turns a node away, which is dispatch\'s decision to make',
    false === strpos(
        stripComments($page),
        "redirect('../management/index.php')"
    ),
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
