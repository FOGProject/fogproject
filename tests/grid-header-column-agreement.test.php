<?php
/**
 * The host grid's header row and its DataTables column list must agree.
 *
 * DataTables walks each <th>, looks up aoColumns[i] and raises error 18,
 * "Incorrect column count", for any header with no column behind it. That
 * error kills the WHOLE grid rather than one cell, and nothing on the page
 * says why -- so the two halves falling out of step is a silent, total
 * failure of the busiest list in FOG.
 *
 * They had already fallen out of step. HostManagement::index() emits the
 * "Ping Status" header only when FOG_HOST_LOOKUP is on, while
 * fog.host.list.js carried a fixed column array that always included
 * pingstatus. With that setting off -- an admin-facing toggle on the FOG
 * Configuration page -- the table had one fewer header than the JS had
 * columns and the host list did not draw at all.
 *
 * The fix is structural rather than a matching pair of edits: every header
 * carries its DataTables `data` key in a data-col attribute, and the JS
 * builds its column list by reading them. A conditional header then takes
 * its column with it, and adding a column server-side needs no JS change.
 *
 * So this asserts both halves of that:
 *
 *   1. Every <th> the host list emits carries a data-col, with
 *      FOG_HOST_LOOKUP both on and off -- an untagged header is the one
 *      thing that can still put the counts out.
 *   2. Gating pingstatus actually removes exactly that column, so the
 *      mechanism is doing the work rather than the counts agreeing by
 *      luck.
 *   3. fog.host.list.js does not hardcode a columns array, which is what
 *      stops the original bug being reintroduced by someone adding a
 *      column the obvious way.
 *
 * Usage: php tests/grid-header-column-agreement.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('grid-header-columns');

$db = FogTestHarness::fakeDb();
$db->pdo->rowCount = 1;
$db->pdo->countValue = 1;

// Unrestricted, so the header set is captured whole rather than the subset
// one permission set happens to reach.
$admin = FOGCore::getClass('User')->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

$t = new FogChecks();

/**
 * Builds the host list header row with FOG_HOST_LOOKUP in a given state.
 *
 * The page reads the cached FOGBase::$fogpingactive rather than the setting
 * on every render, so the static is what decides the header set.
 *
 * @param bool $pingActive whether FOG_HOST_LOOKUP is on
 *
 * @return array [th count, data-col keys in order]
 */
function hostHeaderRow($pingActive)
{
    FogTestHarness::setStatic('FOGBase', 'fogpingactive', $pingActive);
    $page = FOGCore::getClass('HostManagement');
    $row = $page->buildHeaderRow();
    preg_match_all('/<th\b[^>]*>/', $row, $ths);
    preg_match_all('/data-col="([^"]+)"/', $row, $cols);

    return [count($ths[0]), $cols[1]];
}

list($onCount, $onCols) = hostHeaderRow(true);
list($offCount, $offCols) = hostHeaderRow(false);

$t->check(
    'host list emits headers with FOG_HOST_LOOKUP on',
    $onCount > 0
);
$t->check(
    'every header carries a data-col with FOG_HOST_LOOKUP on ('
    . count($onCols) . ' of ' . $onCount . ')',
    $onCount === count($onCols)
);
$t->check(
    'every header carries a data-col with FOG_HOST_LOOKUP off ('
    . count($offCols) . ' of ' . $offCount . ')',
    $offCount === count($offCols)
);
$t->check(
    'pingstatus is present when FOG_HOST_LOOKUP is on',
    in_array('pingstatus', $onCols, true)
);
$t->check(
    'pingstatus is absent when FOG_HOST_LOOKUP is off',
    !in_array('pingstatus', $offCols, true)
);
$t->check(
    'gating pingstatus removes exactly that one column ('
    . implode(', ', $offCols) . ')',
    array_values(array_diff($onCols, $offCols)) === ['pingstatus']
);
$t->check(
    'no header key is emitted twice',
    count($onCols) === count(array_unique($onCols))
);

// The JS half. A hardcoded array here is what the fix removed, and
// reintroducing one is how this bug comes back -- it would look correct
// against whatever the header set happens to be on the author's server.
$js = file_get_contents(
    dirname(__DIR__)
    . '/packages/web/management/js/fog/host/fog.host.list.js'
);
$t->check(
    'fog.host.list.js reads the header row for its columns',
    false !== strpos($js, "data-col")
);
$t->check(
    'fog.host.list.js passes the derived list to DataTables',
    false !== strpos($js, 'columns: columns')
);
$t->check(
    'fog.host.list.js does not hardcode a columns array',
    0 === preg_match('/columns:\s*\[/', $js)
);
// columnDefs address columns by index, and those indexes move whenever a
// column is added or gated -- which was the other half of the same bug.
$t->check(
    'fog.host.list.js addresses columnDefs by looked-up index',
    false !== strpos($js, 'colIndex')
        && 0 === preg_match('/targets:\s*\d/', $js)
);

$t->finish();
