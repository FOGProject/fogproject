<?php
/**
 * A page that reads $this->obj must be in FOGPage::$PagesWithObjects.
 *
 * FOGPage's constructor only populates $this->obj -- the model instance the
 * edit and delete tabs are rendered from -- for a node it finds in
 * $PagesWithObjects. A page missing from that list gets null, and the first
 * `$this->obj->get(...)` is a fatal on null, which FOG serves as a bodyless
 * 500: no page, no message, nothing in the UI to read.
 *
 * That shipped. Sites moved out of the site plugin into core; the plugin had
 * been injecting itself into the list from a hook, core pages are listed
 * statically, and the static list was not updated. `?node=site&sub=edit`
 * fataled in renderEditTabs() for every site, while the list page it was
 * reached from worked fine.
 *
 * The rule keys on assignment, not on the read. Three pages -- the dashboard
 * and the server-info page -- read $this->obj too, but assign it themselves
 * first (a StorageNode chosen from a request parameter, not the page's own
 * node), so they neither need nor want the constructor's help. A page that
 * only ever READS it is the one depending on the list.
 *
 * Sibling of tests/list-pages-registered.test.php: same shape of bug, the
 * other half of the page wiring. Missing from $searchPages a page renders a
 * stub instead of its grid; missing from $PagesWithObjects it 500s on edit.
 *
 * DB-free: reads the page sources and the property.
 *
 * Usage: php tests/page-objects-registered.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';
$pageDir = $webroot . '/lib/pages';
$pageBase = $webroot . '/lib/fog/fogpage.class.php';

foreach ([$pageDir, $pageBase] as $needed) {
    if (!file_exists($needed)) {
        fwrite(STDERR, "FAIL: cannot read $needed\n");
        exit(1);
    }
}

// Read the property out of the source rather than booting FOG -- the
// harness has no reason to want a database.
$src = file_get_contents($pageBase);
if (!preg_match(
    '/public \$PagesWithObjects = \[(.*?)\];/s',
    $src,
    $m
)) {
    fwrite(STDERR, "FAIL: could not locate \$PagesWithObjects in fogpage\n");
    exit(1);
}
preg_match_all("/'([a-z]+)'/", $m[1], $found);
$pagesWithObjects = $found[1];

if (count($pagesWithObjects) < 10) {
    fwrite(
        STDERR,
        'FAIL: parsed only ' . count($pagesWithObjects) . " entries; the "
        . "property's shape must have changed\n"
    );
    exit(1);
}

$failures = [];
$checks = 0;

foreach (glob($pageDir . '/*.page.php') as $file) {
    $pageSrc = file_get_contents($file);
    if (false === strpos($pageSrc, '$this->obj->')) {
        continue;
    }
    // Assigns its own -- see the docblock. Not the constructor's business.
    if (preg_match('/\$this->obj\s*=/', $pageSrc)) {
        continue;
    }
    if (!preg_match("/public \\\$node\s*=\s*'([a-z]+)'/", $pageSrc, $n)) {
        $failures[] = basename($file)
            . ' reads $this->obj but declares no $node';
        $checks++;
        continue;
    }
    $checks++;
    if (!in_array($n[1], $pagesWithObjects, true)) {
        $failures[] = basename($file) . " (node '{$n[1]}') reads \$this->obj "
            . 'but is not in $PagesWithObjects, so sub=edit and sub=delete '
            . 'fatal on null and serve a bodyless 500';
    }
}

if (0 === $checks) {
    fwrite(STDERR, "FAIL: found no pages to check; layout changed?\n");
    exit(1);
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks object pages registered\n";
exit(0);
