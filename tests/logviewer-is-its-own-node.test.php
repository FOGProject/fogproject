<?php
/**
 * The Log Viewer is a node of its own, under Logging, with its gate unmoved.
 *
 * It used to be a tab on the About page -- `?node=about&sub=logviewer` --
 * which put "what has this server been doing" in among kernels, PXE menus and
 * settings. It now sits in the sidebar beside Activity and the Audit Log.
 *
 * A node move is five separate edits in four files, and getting four of them
 * right produces a page that looks moved and does not work. Each assertion
 * below is one of those edits:
 *
 *   - the page class exists and answers for the node, or FOGPageManager finds
 *     nothing and the node 404s;
 *   - the class is reachable under its BARE name, or the autoloader logs
 *     "does not declare" and takes the same route (ADR 0013);
 *   - the permission alias is present and matches `about`'s, or the move
 *     silently changes who can read the logs;
 *   - the sidebar group lists it, or it is a node nobody can navigate to;
 *   - the About sub-menus no longer do, in BOTH copies -- FOGPage carries one
 *     and SubMenuData::subMenu() carries another, and they are kept in step
 *     deliberately;
 *   - the JS sits where Page.php will look for it. This is the one that fails
 *     silently: the page renders, the form is inert, and nothing logs.
 *
 * Static: this reads the source, so it needs no database and no web server.
 *
 * Usage: php tests/logviewer-is-its-own-node.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$web = $repo . '/packages/web/';

// [passed, what it means], classified in one pass at the end.
$results = [];

$page = $web . 'lib/pages/logviewermanagement.page.php';
$src = is_file($page) ? (string)file_get_contents($page) : '';

$results[] = [
    '' !== $src,
    'the page file exists at lib/pages/logviewermanagement.page.php',
];
$results[] = [
    (bool)preg_match('#class\s+LogViewerManagement\s+extends\s+FOGPage#', $src),
    'it declares LogViewerManagement, the class its filename promises',
];
$results[] = [
    (bool)preg_match('#public\s+\$node\s*=\s*\'logviewer\'#', $src),
    'and answers for node "logviewer"',
];
// The autoloader resolves a page by its bare global name. A namespaced page
// without this loads as nothing and the node 404s -- with only an error_log
// line to say why.
$results[] = [
    false !== strpos($src, "class_alias(__NAMESPACE__ . '\\\\LogViewerManagement'"),
    'and is aliased to its bare name for the autoloader (ADR 0013)',
];
$results[] = [
    (bool)preg_match('#public\s+function\s+index\(\.\.\.\$args\)#', $src),
    'and its entry point is index(...$args), matching FOGPage',
];

// THE GATE MUST NOT MOVE. Relocating a page in the sidebar is not an
// access-control decision, so logviewer maps onto whatever `about` maps onto.
$auth = (string)file_get_contents($web . 'src/Auth/Authorization.php');
preg_match('#\'about\'\s*=>\s*\'(\w+)\'#', $auth, $aboutNode);
preg_match('#\'logviewer\'\s*=>\s*\'(\w+)\'#', $auth, $lvNode);
$results[] = [
    !empty($lvNode[1]),
    'logviewer has a NODE_ALIASES entry, so it is not silently unguarded',
];
$results[] = [
    !empty($aboutNode[1]) && ($lvNode[1] ?? null) === $aboutNode[1],
    'and it resolves to the same node as about ('
        . ($aboutNode[1] ?? '?') . '), so nobody gained or lost access',
];

$fogpage = (string)file_get_contents($web . 'src/Base/FOGPage.php');

$results[] = [
    (bool)preg_match(
        "#'children'\s*=>\s*\[[^\]]*'logviewer'[^\]]*\]#",
        $fogpage
    ),
    'the Logging sidebar group lists it as a child',
];
$results[] = [
    (bool)preg_match("#'logviewer'\s*=>\s*\[\s*_\('Log Viewer'\)#", $fogpage),
    'and it has a top-level menu entry, which is what makes it groupable',
];

// Gone from the About tabs, in both copies of that list.
$results[] = [
    false === strpos($fogpage, "'logviewer' => self::\$foglang['LogViewer']"),
    'it is gone from the About sub-menu in FOGPage',
];
$submenu = (string)file_get_contents($web . 'lib/hooks/submenudata.hook.php');
$results[] = [
    false === strpos($submenu, "'logviewer' => self::\$foglang['LogViewer']"),
    'and from the second copy in SubMenuData::subMenu()',
];

// The old URL still resolves. It has been `?node=about&sub=logviewer` for
// years, so it is in bookmarks and in documentation -- same reasoning as
// History_Report, which ADR 0023 item 4 kept alive as a redirect.
$conf = (string)file_get_contents(
    $web . 'lib/pages/fogconfigurationpage.page.php'
);
$results[] = [
    (bool)preg_match(
        '#function\s+logviewer\(\)\s*\{\s*self::redirect\(\'\?node=logviewer\'\);#',
        $conf
    ),
    'the old ?node=about&sub=logviewer URL redirects rather than 404ing',
];

// Page.php builds the script path as js/fog/{node}/fog.{node}.js for a node
// with no sub. If the file is not there the page renders and the form does
// nothing, with no error anywhere -- so this is pinned by PATH, not by name.
$results[] = [
    is_file($web . 'management/js/fog/logviewer/fog.logviewer.js'),
    'the JS is at the path Page.php derives from the node name',
];
$results[] = [
    !is_file($web . 'management/js/fog/about/fog.about.logviewer.js'),
    'and is no longer at its old about/ path, where nothing would load it',
];

$failed = 0;
foreach ($results as [$passed, $why]) {
    echo $passed ? "  ok    $why\n" : "  FAIL  $why\n";
    $failed += $passed ? 0 : 1;
}
echo "\n";
if ($failed > 0) {
    echo 'FAIL (' . $failed . ' of ' . count($results) . " assertions)\n";
    exit(1);
}
echo 'PASS (' . count($results) . " assertions)\n";
