<?php
/**
 * The Agent Activity surfaces: one filter, two places, its own gate.
 *
 * The agent's audit rows have always been in `auditLog` tagged with the
 * host id; nothing could read them by host. Two surfaces now do -- a tab on
 * the host page and a page under Logging -- and the checks here are about
 * the things that would quietly rot:
 *
 * - ONE filter. The tab and the page must select the same rows, or a host
 *   shows two different histories depending on where you look. Both go
 *   through AgentActivityManagement::TYPE_PREFIX rather than either of them
 *   spelling out the agent.* type names.
 * - NO hardcoded list of type names. A new fact kind is a registry entry
 *   and a block in the poll (the route rule); it must not also be a third
 *   place to edit before its rows become visible.
 * - The SCOPED endpoint stays scoped. An absent or malformed id must return
 *   nothing, never fall through to every host's rows.
 * - Its own permission node. ADR 0021 made audit.view narrow because the
 *   audit log discloses attempted usernames and refusals; aliasing this
 *   onto it would force anyone who may see what an agent did to also see
 *   every failed sign-in.
 * - rowGroup, grouped on hostName. This bullet used to say the opposite --
 *   that the grid must NOT group, because registerTable() auto-pages any
 *   table that does and a grouped grid loses the infinite scroll. That trade
 *   was real but it was the wrong side of it: the summary-plus-child-table
 *   arrangement it protected never worked in a browser at all, because
 *   Responsive owns the one row.child() slot every DataTables row has. The
 *   page now groups, is paged rather than infinitely scrolled, and expands a
 *   host by adding rows to the same grid. tests/agent-activity-grouping
 *   .test.php holds the detail; what is pinned here is that the child table
 *   does not come back.
 *
 * Usage: php tests/agent-activity-page.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-activity-page');
FogTestHarness::fakeDb();

$t = new FogChecks();

$pageFile = __DIR__ . '/../packages/web/src/Pages/AgentActivityManagement.php';
$hostFile = __DIR__ . '/../packages/web/src/Pages/HostManagement.php';
$jsFile = __DIR__
    . '/../packages/web/management/js/fog/agentactivity/fog.agentactivity.list.js';

$pageSrc = (string)file_get_contents($pageFile);
$hostSrc = (string)file_get_contents($hostFile);
$jsSrc = (string)file_get_contents($jsFile);

// --------------------------------------------------------------- wiring

$t->check(
    'the page class exists and declares its own node',
    class_exists('FOG\Pages\AgentActivityManagement')
    && 'agentactivity' === (new \FOG\Pages\AgentActivityManagement())->node
);

$registry = \FOG\Auth\Authorization::coreRegistry();
$t->check(
    'agentactivity is a permission node of its own, not an alias onto audit',
    isset($registry['agentactivity'])
    && in_array('view', (array)$registry['agentactivity'], true)
);

// Read-only by construction: auditlog has no create, update or delete route
// anywhere in FOG (ADR 0021 Decision 8), so granting anything else here
// would name an action nothing can perform.
$t->check(
    'agentactivity grants view and nothing else',
    isset($registry['agentactivity'])
    && ['view'] === array_values((array)$registry['agentactivity'])
);

$aliases = (new \ReflectionClass('FOG\Auth\Authorization'))
    ->getConstant('NODE_ALIASES');
$t->check(
    'agentactivity is not aliased onto another node\'s gate',
    !isset($aliases['agentactivity'])
);

$fogPageSrc = (string)file_get_contents(
    __DIR__ . '/../packages/web/src/Base/FOGPage.php'
);
$t->check(
    'the node has a sidebar entry',
    1 === preg_match(
        "#'agentactivity'\s*=>\s*\[\s*_\('Agent Activity'\)#",
        $fogPageSrc
    )
);
$t->check(
    'the sidebar entry sits under the Logging group',
    1 === preg_match(
        "#'logging'\s*=>\s*\[.*?'children'\s*=>\s*\[[^\]]*'agentactivity'#s",
        $fogPageSrc
    )
);

// The JS is loaded by convention, js/fog/<node>/fog.<node>.list.js
// (FOGPageManager::render()), so the filename IS the wiring.
$t->check(
    'the grid script is where the node convention will look for it',
    is_file($jsFile)
);

// ------------------------------------------------- one filter, two places

$t->check(
    'the page filters on the shared prefix constant',
    false !== strpos($pageSrc, 'self::TYPE_PREFIX')
);
$t->check(
    'the host tab filters on the SAME constant, not its own copy',
    false !== strpos($hostSrc, 'AgentActivityManagement::TYPE_PREFIX')
);

// The drift this prevents: eleven agent.* types exist today and a twelfth
// is one registry entry away. A surface that listed them would show a host
// an incomplete history and give no sign it was doing so.
$knownTypes = [
    'agent.enroll', 'agent.token', 'agent.result', 'agent.inventory',
    'agent.software', 'agent.printers', 'agent.directory',
    'agent.directory.move', 'agent.secureboot', 'agent.usersession',
    'agent.wake'
];
$listed = 0;
foreach ($knownTypes as $type) {
    if (false !== strpos($pageSrc, "'" . $type . "'")) {
        $listed++;
    }
}
$t->check(
    'the page names no individual agent.* type, so a new kind needs no edit',
    0 === $listed
);

// Both surfaces must ask auditLog for host-subject rows. A filter that
// dropped subjectType would collide with any other model numbered the same.
foreach ([['the page', $pageSrc], ['the host tab', $hostSrc]] as $pair) {
    list($who, $src) = $pair;
    $t->check(
        $who . ' scopes to host subjects',
        1 === preg_match("#'subjectType'\s*=>\s*'host'#", $src)
    );
}

// ------------------------------------------------ the scope cannot be lost

// An unscoped answer is the one thing the scoped endpoint must never give,
// and the guard is what stops a missing id becoming "every host".
$t->check(
    'the scoped endpoint refuses an id below 1 before it reads anything',
    1 === preg_match(
        '#\$hostID\s*=\s*\(int\)\s*Route::queryParam\(\'id\'\);\s*'
        . 'if\s*\(\$hostID\s*<\s*1\)#s',
        $pageSrc
    )
);
$t->check(
    'the id is cast to int, so no request string reaches the query',
    1 === preg_match("#\(int\)\s*Route::queryParam\('id'\)#", $pageSrc)
);
$t->check(
    'the host tab takes its id from the loaded host, never the query string',
    1 === preg_match(
        "#'subjectID'\s*=>\s*\(int\)\\\$this->obj->get\('id'\)#",
        $hostSrc
    )
);

// ------------------------------------------------------ the grid contract

$t->check(
    'the grid groups on hostName with rowGroup',
    false !== strpos($jsSrc, 'rowGroup:')
        && 1 === preg_match('~dataSrc:\s*\x27hostName\x27~', $jsSrc)
);

// headerData and attributes are positional; a mismatch silently shifts
// every column's attributes one to the left.
$page = new \FOG\Pages\AgentActivityManagement();
ob_start();
$page->index();
$html = (string)ob_get_clean();
$ref = new \ReflectionObject($page);
$headerProp = $ref->getProperty('headerData');
$headerProp->setAccessible(true);
$attrProp = $ref->getProperty('attributes');
$attrProp->setAccessible(true);
$headers = (array)$headerProp->getValue($page);
$attrs = (array)$attrProp->getValue($page);

$t->check(
    'the header and attribute arrays are the same length',
    count($headers) === count($attrs)
);

// The JS column list must match the rendered header count or DataTables
// throws and the grid never draws.
$cols = 0;
if (preg_match('#columns:\s*\[(.*?)\n    \],#s', $jsSrc, $m)) {
    $cols = preg_match_all('#^\s{6}[a-zA-Z{]#m', $m[1]);
}
$t->check(
    'the grid declares one column per header (' . count($headers) . ')',
    $cols === count($headers)
);

$t->check(
    'the page renders its table container',
    false !== strpos($html, 'agentactivity-table')
);

// The bug this pins: the child table was first written as a bare
// .DataTable(), which opted out of every convention registerTable() applies
// -- including its `dom`, which is where the pager lives, and its Scroller
// setup. An expanded host showed the first ten of its events with no way to
// reach the rest. Reported against a host with seventy-nine of them.
// Comment lines dropped first. The file explains at length why row.child()
// is not used here, and scanning the prose that says "never do this" for the
// thing not to do fails the build on the documentation.
$jsCode = implode(
    "\n",
    array_filter(
        preg_split('/\R/', $jsSrc),
        static function ($line) {
            $trim = ltrim($line);
            return '' === $trim
                || (0 !== strpos($trim, '//')
                    && 0 !== strpos($trim, '/*')
                    && 0 !== strpos($trim, '*'));
        }
    )
);
$t->check(
    'no child table is built for an expanded host, by any route',
    false === strpos($jsCode, 'agentactivity-child-')
        && 0 === preg_match('~\brow\.child\(~', $jsCode)
);

// registerTable() sizes a Scroller table on a setTimeout(0) after init. A
// grid built into a node still being attached -- a child row is exactly that
// -- reaches fogSizeScroller() before its wrapper is in the document, and an
// unguarded container() threw an uncaught TypeError out of that timeout.
$commonSrc = (string)file_get_contents(
    __DIR__ . '/../packages/web/management/js/fog/fog.common.js'
);
$t->check(
    'fogSizeScroller skips a table whose container is not in the document yet',
    1 === preg_match(
        '#var container = dt\.table\(\)\.container\(\);\s*'
        . 'if \(!container\) \{\s*return;#s',
        $commonSrc
    )
);

$t->finish();
