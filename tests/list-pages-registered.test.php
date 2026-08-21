<?php
/**
 * A node whose JS registers a DataTables list must be a search page.
 *
 * FOGPage::index() has two branches. For a node in FOGBase::$searchPages it
 * renders the grid and serves the AJAX payload the list JS asks for; for
 * any other node it falls through to a stub that prints
 * "Index page of: <PageClass>" and nothing else.
 *
 * Missing from that list, a page therefore looks half-built rather than
 * broken: the menu entry works, the page loads, the title is right, and the
 * grid is simply absent. No error, no log line, no failing request -- it is
 * the wrong half of an if, and nothing else in the tree says so.
 *
 * That is not hypothetical. Sites moved out of the site plugin into core,
 * and the plugin had been adding itself to $searchPages from its menu hook
 * (`$arguments['searchPages'][] = $this->node`). Core pages are listed
 * statically instead, the static list was not updated, and Site Management
 * shipped rendering "Index page of: SiteManagement".
 *
 * The tell is the grid registration itself. Two exist -- $.registerListPage
 * and $.registerTable -- and a fog.<node>.list.js calling either is asking
 * for a grid the server can only supply from the list branch. Two nodes ship
 * a list JS that calls NEITHER (apidocs, service): those are page scripts
 * that happen to follow the naming convention, and they are correctly absent
 * from $searchPages. So the rule keys on the call, not on the filename.
 *
 * DB-free: reads the JS files and the static property.
 *
 * Usage: php tests/list-pages-registered.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';
$jsRoot = $webroot . '/management/js/fog';
$baseFile = $webroot . '/lib/fog/fogbase.class.php';

foreach ([$jsRoot, $baseFile] as $needed) {
    if (!file_exists($needed)) {
        fwrite(STDERR, "FAIL: cannot read $needed\n");
        exit(1);
    }
}

// Read $searchPages out of the source rather than booting FOG: the property
// is protected, and a harness that instantiated FOGBase would need a
// database it has no reason to want.
$src = file_get_contents($baseFile);
if (!preg_match(
    '/protected static \$searchPages = \[(.*?)\];/s',
    $src,
    $m
)) {
    fwrite(STDERR, "FAIL: could not locate \$searchPages in fogbase\n");
    exit(1);
}
preg_match_all("/'([a-z]+)'/", $m[1], $found);
$searchPages = $found[1];

$failures = [];
$checks = 0;

if (count($searchPages) < 10) {
    fwrite(
        STDERR,
        'FAIL: parsed only ' . count($searchPages) . " search pages; the "
        . "property's shape must have changed\n"
    );
    exit(1);
}

/**
 * Does the page class owning this node declare its own index()?
 *
 * Read from source for the same reason $searchPages is: the harness must run
 * with no database and no generated config.class.php.
 *
 * @param string $webroot path to packages/web
 * @param string $node    the page node
 *
 * @return bool
 */
function self_serves_index($webroot, $node)
{
    foreach (glob($webroot . '/lib/pages/*.page.php') as $page) {
        $src = file_get_contents($page);
        if (!preg_match('/public \$node\s*=\s*\'' . $node . '\';/', $src)) {
            continue;
        }
        return (bool) preg_match('/function index\s*\(/', $src);
    }

    return false;
}

foreach (glob($jsRoot . '/*', GLOB_ONLYDIR) as $dir) {
    $node = basename($dir);
    $listJs = $dir . '/fog.' . $node . '.list.js';
    if (!is_readable($listJs)) {
        continue;
    }
    $js = file_get_contents($listJs);
    // registerReloadToggle does not count and does not collide: it contains
    // neither of these substrings.
    if (false === strpos($js, 'registerListPage')
        && false === strpos($js, 'registerTable')
    ) {
        // A list JS that registers no grid needs no list branch.
        continue;
    }
    $checks++;
    // A page that overrides index() cannot reach the base stub, so it is not
    // at risk of the failure this test exists to catch, and $searchPages is
    // not free to join: the property is ALSO the universal-search list, and
    // Route::unisearch() calls getClass() on every entry. A node whose page
    // serves its own grid has no model of that name, so listing it there
    // would trade a rendering bug for a search one.
    //
    // The live example is 'activity': ActivityManagement::index() renders the
    // card itself and ActivityManagement::getList() serves the rows, because
    // the source filter picks which class to list -- which is the whole point
    // of that page (ADR 0023) and is not something the base branch's single
    // $childClass can express.
    if (!in_array($node, $searchPages, true)
        && !self_serves_index($webroot, $node)
    ) {
        $failures[] = "$node registers a list page but is not in "
            . '$searchPages, so its page renders "Index page of: ..." '
            . 'instead of the grid';
    }
}

if (0 === $checks) {
    fwrite(STDERR, "FAIL: found no list pages to check; layout changed?\n");
    exit(1);
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks list pages registered\n";
exit(0);
