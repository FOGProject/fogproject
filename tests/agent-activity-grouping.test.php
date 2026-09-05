<?php
/**
 * Agent Activity: the grouped grid, and the four bugs that were silent.
 *
 * This page shows one RowGroup per host -- the host's name, its event count
 * and an expand control in the header, that host's newest event as the row
 * beneath it, and the rest of its events added to the same grid when the
 * header is clicked. Every part of that arrangement replaces something that
 * was broken in a way nothing reported.
 *
 * 1. NO row.child(). The first version opened a nested DataTable per host
 *    through `row.child()`. A DataTables row has ONE child slot and
 *    registerTable() turns Responsive on for every grid, so Responsive owned
 *    it: the nested table was never constructed and clicking expand rendered
 *    Responsive's hidden-column list instead. Measured on a live install at
 *    1920px with zero columns hidden, so it was not a narrow-viewport
 *    artifact -- and nothing threw, which is how it survived a follow-up fix
 *    aimed at the pager of a table that did not exist. Any return to
 *    row.child() on this page returns to that.
 *
 * 2. THE ANCHOR ROW IS NEVER FILTERED. Collapse is an ext.search filter, and
 *    RowGroup builds headers from the rows that SURVIVE it -- a group with
 *    none left renders no header at all. Verified against the vendored
 *    RowGroup, not assumed: with one of two groups fully filtered, only the
 *    other group's header was emitted. So the filter has to let the anchor
 *    through unconditionally; a filter that reads only the expanded flag
 *    makes every collapsed host vanish from the page, which looks like an
 *    empty install.
 *
 * 3. EVERY ROW OF A HOST SORTS ALIKE. RowGroup starts a new group each time
 *    its dataSrc changes down the ORDERED rows, so a group is only whole if
 *    the table is ordered by it. Ordering by time alone let one host's older
 *    events fall past the next host's newest, and its header was drawn
 *    twice. The fix is a hidden column that sorts on a per-GROUP key, and
 *    the trap inside the fix is that eventRow() is handed a row this file
 *    already built: recomputing the key from `seed.lastTime` reads undefined
 *    (a seed row carries createdTime), every event sorts under
 *    "undefined|<id>", and the group splits exactly as before. So the key is
 *    COPIED, and that is what this pins.
 *
 * 4. TRUNCATION READS recordsFiltered. Route::listem()'s recordsTotal is
 *    every row in auditLog -- 1435 on the lab install -- not the host's. A
 *    cap test against it called a 134-event host truncated at 500 and put
 *    "showing the newest 500" on a header that was showing all of them.
 *
 * And the toolbar half, which is not specific to this page: a grid that
 * passes `select: false` must not be given Select All and Deselect All, and
 * a page that declares itself unselectable must not be given "Delete
 * selected". That used to be decided by a hardcoded list of node names,
 * which this page was not added to -- so it shipped a red Delete selected
 * over a table with no delete route anywhere in FOG (ADR 0021 Decision 8).
 *
 * Usage: php tests/agent-activity-grouping.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGPage;
use FOG\Pages\ActivityManagement;
use FOG\Pages\AgentActivityManagement;
use FOG\Pages\AuditManagement;
use FOG\Pages\PluginManagement;
use FOG\Pages\TaskManagement;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('agent-activity-grouping');

$t = new FogChecks();

/**
 * A file's CODE, with its comment lines removed.
 *
 * Every check below asks whether the source still does something, and the
 * source explains at length why it does it -- including by quoting the
 * broken shape it replaced. Scanning the raw file makes those explanations
 * fail the build: the first run of this test went red on `row.child()` and
 * on the old node list, both of which appear only inside the comments that
 * say never to go back to them. A gate that cannot survive being described
 * is a gate that pressures the next person to delete the description.
 *
 * Whole comment lines only, which is all this codebase writes -- so no
 * string containing // is at risk, and every check here matches on a line
 * of code.
 *
 * @param string $path file to read
 *
 * @return string
 */
$code = static function ($path) {
    $out = [];
    foreach (preg_split('/\R/', (string)file_get_contents($path)) as $line) {
        $trim = ltrim($line);
        if ('' !== $trim
            && (0 === strpos($trim, '//')
                || 0 === strpos($trim, '/*')
                || 0 === strpos($trim, '*'))
        ) {
            continue;
        }
        $out[] = $line;
    }
    return implode("\n", $out);
};

$web = dirname(__DIR__) . '/packages/web';
$js = $code(
    $web . '/management/js/fog/agentactivity/fog.agentactivity.list.js'
);
$common = $code($web . '/management/js/fog/fog.common.js');
$page = $code(
    (new \ReflectionClass(AgentActivityManagement::class))->getFileName()
);
$base = $code(
    (new \ReflectionClass(FOGPage::class))->getFileName()
);

// 1. No child rows on this page, at all. Responsive owns the one slot.
$t->check(
    'the grid uses no row.child() -- Responsive owns that slot',
    false === strpos($js, 'row.child')
        && false === strpos($js, '.child(')
);

// The replacement it must be using instead: rows added to the grid itself.
$t->check(
    'expanded events are added to the grid (rows.add)',
    false !== strpos($js, 'table.rows.add(')
);

// 2. The collapse filter passes the anchor unconditionally. Pinned on the
// shape of the expression, because a filter that happens to return true for
// an anchor today by testing something else would not survive a rename.
$t->check(
    'the collapse filter lets the anchor row through',
    1 === preg_match(
        '/return\s+row\.anchor\s*===\s*true\s*\|\|\s*expanded\[row\.hostName\]/',
        $js
    )
);
$t->check(
    'the seed row is marked as the anchor',
    1 === preg_match('/anchor:\s*true/', $js)
        && 1 === preg_match('/anchor:\s*false/', $js)
);

// 3. eventRow() COPIES the group key rather than recomputing it. Recomputing
// from a seed row is the exact bug: seed.lastTime is undefined.
$t->check(
    'eventRow copies groupSort from the seed, never recomputes it',
    1 === preg_match('/groupSort:\s*seed\.groupSort/', $js)
        && false === strpos($js, 'String(seed.lastTime)')
);

// The table has to be ORDERED by the group, or contiguity is luck.
$t->check(
    'the grid orders by the hidden group column first',
    1 === preg_match('/order:\s*\[\s*\[\s*4,\s*\'desc\'\s*\]/', $js)
);
$t->check(
    'the hidden group column sorts on groupSort',
    1 === preg_match(
        '/if\s*\(t\s*===\s*\'sort\'\s*\|\|\s*t\s*===\s*\'type\'\)\s*\{\s*return\s+row\.groupSort;/',
        $js
    )
);
// A fifth <th> has to exist for a fifth column to be addressable, and it
// must be out of the Column Visibility picker.
$t->check(
    'the page emits the hidden Host column as noVis',
    false !== strpos($page, "['class' => 'noVis']")
        && 1 === preg_match("/_\('Outcome'\),\s*_\('Host'\)/", $page)
);

// 4. The cap is judged against this host's count, not the whole audit log.
$t->check(
    'truncation reads recordsFiltered, not recordsTotal',
    false !== strpos($js, 'json.recordsFiltered')
        && false === strpos($js, 'json.recordsTotal')
);

// The anchor needs the newest row IN FULL or the collapsed view has empty
// cells under populated headings.
foreach (['lastText', 'lastOutcome'] as $field) {
    $t->check(
        sprintf('getList() returns %s for the anchor row', $field),
        false !== strpos($page, "'" . $field . "' =>")
            && false !== strpos($js, $field)
    );
}

// ---- the toolbar half ----

// The PHP seam: a property, not a list of node names.
$t->check(
    'FOGPage declares $selectable and defaults it true',
    1 === preg_match('/public\s+\$selectable\s*=\s*true;/', $base)
);
$t->check(
    'the delete actionbox is gated on $this->selectable',
    false !== strpos($base, 'if ($this->selectable) {')
);
$t->check(
    'the hardcoded read-only node list is gone',
    false === strpos($base, "['plugin', 'task', 'activity', 'audit']")
);

// Every page that was in that list, plus the one it missed.
foreach (
    [
        'agentactivity' => AgentActivityManagement::class,
        'audit' => AuditManagement::class,
        'activity' => ActivityManagement::class,
        'plugin' => PluginManagement::class,
        'task' => TaskManagement::class
    ] as $node => $class
) {
    $src = $code((new \ReflectionClass($class))->getFileName());
    $t->check(
        sprintf('%s declares itself unselectable', $node),
        1 === preg_match('/public\s+\$selectable\s*=\s*false;/', $src)
    );
}

// The JS seam: the buttons that select are dropped when nothing selects.
$t->check(
    'registerTable drops selectAll/selectNone on select:false',
    1 === preg_match(
        '/if\s*\(opts\.select\s*===\s*false\)\s*\{\s*defaults\.buttons\s*=\s*defaults\.buttons\.filter/',
        $common
    )
    && false !== strpos($common, "b.extend !== 'selectAll'")
    && false !== strpos($common, "b.extend !== 'selectNone'")
);

// The page's own grid has to actually make that statement, or the seam is
// wired to nothing here.
$t->check(
    'the agent activity grid passes select: false',
    1 === preg_match('/select:\s*false/', $js)
);

$t->finish();
