<?php
/**
 * The rows of the Software tab grid, for a host and for a group.
 *
 * SoftwareGrid is pure: plain arrays in, rows out. So this drives the real
 * class with the shapes Resolver::resolveSoftwareDetail() and the pages hand
 * it, and needs no database. What it pins is the part an admin acts on: the
 * order of the rows, which rows are inherited, and WHO set the state shown
 * (this host, a group, or the package).
 *
 * Usage: php tests/software-grid-rows.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('software-grid-rows');
FogTestHarness::fakeDb();

use FOG\Assign\SoftwareGrid;

$t = new FogChecks();

/**
 * One software entry, as SoftwareGrid::entries() would build it.
 *
 * @param int    $id      the id
 * @param string $name    the name
 * @param string $package the package
 * @param int    $enabled 1 or 0
 * @param string $state   the entry's own state
 *
 * @return array
 */
function entry($id, $name, $package, $enabled = 1, $state = 'present')
{
    return [
        'id' => $id,
        'name' => $name,
        'backend' => 'choco',
        'package' => $package,
        'enabled' => $enabled,
        'state' => $state
    ];
}

/**
 * A Resolver::resolveSoftwareDetail() row.
 *
 * @param int         $id        the software id
 * @param string|null $state     the override that applies, or null
 * @param int         $groupID   0 for the host
 * @param string      $effective the state that applies
 *
 * @return array
 */
function res($id, $state, $groupID, $effective)
{
    return [
        'id' => $id,
        'state' => $state,
        'groupID' => $groupID,
        'effective' => $effective
    ];
}

/**
 * The rows keyed by software id.
 *
 * @param array $rows the grid rows
 *
 * @return array
 */
function byId(array $rows)
{
    $out = [];
    foreach ($rows as $row) {
        $out[$row['id']] = $row;
    }
    return $out;
}

$software = [
    1 => entry(1, '7zip', '7zip'),
    2 => entry(2, 'vlc', 'vlc'),
    3 => entry(3, 'firefox', 'firefox', 1, 'absent'),
    4 => entry(4, 'old-vlc', 'vlc'),
    5 => entry(5, 'retired', 'retired', 0),
    6 => entry(6, 'zoom', 'zoom')
];
$groupNames = [10 => 'Lab PCs'];

// Host 42: links 2 then 1 (run order), 4 (duplicate package of 2, so it is
// superseded), 5 (disabled). Group 10 grants 3 (Absent) and 2 (Absent), which
// the host's own link on 2 overrides.
$links = [
    ['id' => 2, 'state' => 'absent'],
    ['id' => 1, 'state' => null],
    ['id' => 4, 'state' => ''],
    ['id' => 5, 'state' => 'present'],
    ['id' => 99, 'state' => null]
];
$resolved = [
    res(2, 'absent', 0, 'absent'),
    res(1, null, 0, 'present'),
    res(5, 'present', 0, 'present'),
    res(3, 'absent', 10, 'absent'),
    res(6, null, 10, 'present')
];
$statuses = [
    1 => ['status' => 'installed', 'installedVersion' => '26.2', 'checked' => '2026-11-20 10:00:00'],
    2 => ['status' => 'removed', 'installedVersion' => '', 'checked' => '2026-11-21 10:00:00']
];

$rows = SoftwareGrid::hostRows($links, $software, $resolved, $groupNames, $statuses);
$ids = array_column($rows, 'id');
$by = byId($rows);

$t->check(
    'linked rows keep link order, then inherited rows in resolver order',
    [2, 1, 4, 5, 3, 6] === $ids
);
$t->check(
    'a link with no software entry is skipped',
    !isset($by[99])
);
$t->check(
    'linked rows are linked, inherited rows are not',
    $by[2]['linked'] && $by[1]['linked'] && !$by[3]['linked'] && !$by[6]['linked']
);
$t->check(
    'a link carries its own state; an inherited row has none',
    'absent' === $by[2]['state'] && '' === $by[1]['state'] && '' === $by[3]['state']
);
$t->check(
    'the entry state is present or absent',
    'present' === $by[1]['entryState'] && 'absent' === $by[3]['entryState']
);

// Source mapping.
$t->check(
    'a state the host link set is source host',
    'host' === $by[2]['source'] && '' === $by[2]['sourceName']
);
$t->check(
    'a state a group set is source group, with the group name',
    'group' === $by[3]['source'] && 'Lab PCs' === $by[3]['sourceName']
);
$t->check(
    'no state set by any link is source package',
    'package' === $by[1]['source'] && 'package' === $by[6]['source']
);
$t->check(
    'a package default granted by a group names that group (viaName)',
    'Lab PCs' === $by[6]['viaName']
);
$t->check(
    'viaName is empty when the host links it, or a link set the state',
    '' === $by[1]['viaName'] && '' === $by[2]['viaName'] && '' === $by[3]['viaName']
);
$t->check(
    'an unknown group leaves viaName empty',
    '' === byId(
        SoftwareGrid::hostRows(
            [],
            $software,
            [res(6, null, 77, 'present')],
            $groupNames,
            []
        )
    )[6]['viaName']
);
$t->check(
    'an unknown group id leaves the source name empty',
    '' === byId(
        SoftwareGrid::hostRows(
            [],
            $software,
            [res(3, 'absent', 77, 'absent')],
            $groupNames,
            []
        )
    )[3]['sourceName']
);
$t->check(
    'effective comes from the resolver',
    'absent' === $by[2]['effective'] && 'present' === $by[1]['effective']
    && 'absent' === $by[3]['effective']
);

// Notes.
$t->check(
    'a disabled entry has no effective state and the note disabled',
    null === $by[5]['effective'] && 'disabled' === ($by[5]['note'] ?? null)
);
$t->check(
    'an enabled entry the resolver dropped is superseded',
    null === $by[4]['effective'] && 'superseded' === ($by[4]['note'] ?? null)
);
$t->check(
    'a normal row has no note',
    !isset($by[1]['note']) && !isset($by[3]['note'])
);

// Status join.
$t->check(
    'status joins on the software id',
    'installed' === $by[1]['status'] && '26.2' === $by[1]['installedVersion']
    && '2026-11-20 10:00:00' === $by[1]['checked']
);
$t->check(
    'a row with no status has empty fields',
    '' === $by[3]['status'] && '' === $by[3]['installedVersion']
    && '' === $by[3]['checked']
);
$t->check(
    'a removed status is carried as removed',
    'removed' === $by[2]['status']
);

// available.
$avail = SoftwareGrid::available($software, array_column($rows, 'id'));
$t->check(
    'available is empty when every entry is linked or inherited',
    [] === $avail
);
$avail = SoftwareGrid::available($software, array_column($links, 'id'));
$t->check(
    'available excludes the linked ids and sorts by name',
    [
        ['id' => 3, 'name' => 'firefox'],
        ['id' => 6, 'name' => 'zoom']
    ] === $avail
);

// links(): Route link objects to plain links, order kept.
$objs = [
    (object)['softwareID' => '7', 'state' => 'absent'],
    (object)['softwareID' => 3, 'state' => null]
];
$t->check(
    'links() keeps order and casts the id',
    [['id' => 7, 'state' => 'absent'], ['id' => 3, 'state' => null]]
    === SoftwareGrid::links($objs)
);

// Group variant.
$glinks = [
    ['id' => 3, 'state' => 'absent'],
    ['id' => 1, 'state' => ''],
    ['id' => 5, 'state' => null]
];
$grows = SoftwareGrid::groupRows($glinks, $software);
$gby = byId($grows);
$t->check(
    'group rows keep link order',
    [3, 1, 5] === array_column($grows, 'id')
);
$t->check(
    'group rows are all linked and carry their state',
    $gby[3]['linked'] && $gby[1]['linked'] && 'absent' === $gby[3]['state']
    && '' === $gby[1]['state']
);
$t->check(
    'group rows carry no effective, source or status',
    !array_key_exists('effective', $gby[3])
    && !array_key_exists('source', $gby[3])
    && !array_key_exists('status', $gby[3])
);
$t->check(
    'a disabled entry on a group has the note disabled',
    'disabled' === ($gby[5]['note'] ?? null)
);

$t->finish();
