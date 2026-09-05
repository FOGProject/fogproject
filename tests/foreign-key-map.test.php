<?php
/**
 * Every column that holds another table's id is CLASSIFIED, and the manifest
 * carries no constraint of its own.
 *
 * ADR 0031 puts referential integrity in the database, but the thing that
 * actually gets forgotten is not the constraint -- nobody forgets a
 * constraint they have decided to add. What gets forgotten is DECIDING. A
 * new table lands with a `somethingID` column, nothing enforces the
 * relationship, and it joins the class of orphan sources this work exists to
 * close, silently and for years.
 *
 * So this gates the MAP, not the constraint list. It is textual and runs in
 * the existing no-database matrix, which is the other half of the argument:
 * gating the constraints themselves would need a live server and could only
 * ever run somewhere CI does not go.
 *
 * WHAT COUNTS AS AN ID COLUMN. A name ending in `ID`, an integer type, and
 * not the table's own primary key. Across the whole 70-table manifest that
 * heuristic yields exactly one false positive -- multicastSessions.msSenderPID
 * is a process id -- so it is bounded by a single named exception rather than
 * by a list that could quietly absorb real ones.
 *
 * The escape from this test is to CLASSIFY the column, including as `audit`
 * (a record of something that happened, which must not constrain the thing it
 * recorded) or `poly` (target table named by a sibling column, so no
 * constraint is expressible). Both are answers. Absence is not.
 *
 * WHY THE MANIFEST MUST STAY CONSTRAINT-FREE. SchemaReconciler executes the
 * manifest's `create` strings in MANIFEST order, which is not dependency
 * order -- apiTokens precedes users, groupMembers precedes hosts. Measured
 * against an empty database: with constraints inlined, 34 of 70 tables fail
 * with errno 150; stripped and added afterward as ALTERs, none do.
 * bin/schema-manifest.php strips them for that reason, and this is what
 * notices if a regeneration ever stops.
 *
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

$root = dirname(__DIR__);
$manifest = require $root . '/packages/web/commons/schema-expected.php';
$map = require $root . '/packages/web/commons/schema-constraints.php';

/**
 * Integer columns the coverage rule below would otherwise demand a
 * classification for, and which are not row references.
 *
 * An allowlist here is a hole in the gate, so each entry needs a reason a
 * reader can check.
 */
$notAReference = [
    // A unix process id for the running multicast sender, not a row id.
    'multicastSessions.msSenderPID',
    // A flag for whether a snapin is a package, not a reference.
    'snapins.sPackType',
    // The user tier (0 mobile, 1 admin, 2 api). No table of user types
    // exists; Authorization reads the integer directly.
    'users.uType',
    // Whether the host has this module on or off -- a boolean, not a
    // reference to taskStates like the other *State columns. It was a
    // varchar(1) until schema step 409 made it the tinyint(1) it had always
    // held, which is what brought it into this check's sights.
    'moduleStatusByHost.msState',
];

$validActions = ['CASCADE', 'RESTRICT', 'SET NULL', 'none'];
$validClasses = ['junction', 'satellite', 'config', 'work', 'audit', 'poly'];

$failures = [];
$checked = 0;

// ---------------------------------------------------------------------------
// 1. The manifest declares no foreign key.
// ---------------------------------------------------------------------------
foreach ($manifest['tables'] as $table => $def) {
    $checked++;
    if (stripos((string)($def['create'] ?? ''), 'FOREIGN KEY') !== false) {
        $failures[] = "$table: `create` carries a FOREIGN KEY clause."
            . ' The manifest executes in manifest order, which is not'
            . ' dependency order -- regenerate with a bin/schema-manifest.php'
            . ' that strips them.';
    }
}

// ---------------------------------------------------------------------------
// 2. The map is internally coherent.
// ---------------------------------------------------------------------------
$byChildColumn = [];
$names = [];
foreach ($map as $i => $rel) {
    foreach (['child', 'column', 'parent', 'pcolumn', 'class', 'action'] as $k) {
        if (!array_key_exists($k, $rel)) {
            $failures[] = "entry $i is missing `$k`";
            continue 2;
        }
    }
    $checked++;
    $key = $rel['child'] . '.' . $rel['column'];
    if (isset($byChildColumn[$key])) {
        $failures[] = "$key is classified twice";
    }
    $byChildColumn[$key] = $rel;

    if (!in_array($rel['class'], $validClasses, true)) {
        $failures[] = "$key has unknown class `{$rel['class']}`";
    }
    if (!in_array($rel['action'], $validActions, true)) {
        $failures[] = "$key has unknown action `{$rel['action']}`";
    }
    // An audit row must never constrain the thing it recorded, and a
    // polymorphic column cannot express a constraint at all. Both are `none`
    // by definition, so a real action on one is a decision that contradicts
    // the class it was filed under -- see ADR 0031 decision 3.
    if (in_array($rel['class'], ['audit', 'poly'], true)
        && $rel['action'] !== 'none'
    ) {
        $failures[] = "$key is class `{$rel['class']}` but declares action"
            . " `{$rel['action']}`; both classes take no constraint";
    }
    if ($rel['action'] === 'none' && !empty($rel['enabled'])) {
        $failures[] = "$key declares no action but is enabled";
    }

    if ($rel['action'] === 'none') {
        continue;
    }

    $name = 'fk_' . $rel['child'] . '_' . $rel['column'];
    if (isset($names[strtolower($name)])) {
        $failures[] = "constraint name $name is not unique";
    }
    $names[strtolower($name)] = true;
    if (strlen($name) > 64) {
        $failures[] = "constraint name $name exceeds 64 characters";
    }
}

// ---------------------------------------------------------------------------
// 3. Every id column in the manifest is classified.
// ---------------------------------------------------------------------------
foreach ($manifest['tables'] as $table => $def) {
    $columns = $def['columns'] ?? [];
    $primary = (string)(array_key_first($columns) ?? '');
    foreach ($columns as $column => $type) {
        // Not just /ID$/. A state column is as much a reference as an id
        // column and reads the same taskStates rows, but it is spelled
        // `msState`, `stState`, `fdqState`. While this matched only /ID$/,
        // two live references to taskStates -- multicastSessions.msState and
        // fileDeleteQueue.fdqState -- were absent from
        // commons/schema-constraints.php and the gate could not say so,
        // because it could not see the columns at all.
        //
        // Widening the pattern is the fix rather than adding the two by
        // hand: a gate that misses a whole naming convention will miss the
        // next column in it too.
        if (!preg_match('/(ID|State)$/', $column) || $column === $primary) {
            continue;
        }
        if (!preg_match('/^(tiny|small|medium|big)?int/i', (string)$type)) {
            continue;
        }
        if (in_array("$table.$column", $notAReference, true)) {
            continue;
        }
        $checked++;
        if (!isset($byChildColumn["$table.$column"])) {
            $failures[] = "$table.$column holds an id and is not classified"
                . ' in commons/schema-constraints.php. Classify it -- `audit`'
                . ' and `poly` are answers, absence is not.';
        }
    }
}

// ---------------------------------------------------------------------------
// 4. An ENABLED constraint must be one the database can actually accept.
// ---------------------------------------------------------------------------
foreach ($map as $rel) {
    if (empty($rel['enabled'])) {
        continue;
    }
    $checked++;
    $child = $manifest['tables'][$rel['child']]['columns'] ?? null;
    $parent = $manifest['tables'][$rel['parent']]['columns'] ?? null;
    if (null === $child || null === $parent) {
        // A plugin table, which the core manifest does not describe. The
        // reconciler skips a relationship whose tables are absent, so this
        // is normal rather than a gap.
        continue;
    }
    if (!isset($child[$rel['column']]) || !isset($parent[$rel['pcolumn']])) {
        $failures[] = "{$rel['child']}.{$rel['column']} is enabled but the"
            . ' column or its parent column is not in the manifest';
        continue;
    }
    // InnoDB requires an exact type match and returns errno 150 otherwise --
    // which on a live upgrade is a constraint that silently never applies.
    $ct = strtolower(preg_replace('/\s.*$/', '', $child[$rel['column']]));
    $pt = strtolower(preg_replace('/\s.*$/', '', $parent[$rel['pcolumn']]));
    if ($ct !== $pt) {
        $failures[] = "{$rel['child']}.{$rel['column']} is enabled but its"
            . " type ($ct) differs from {$rel['parent']}.{$rel['pcolumn']}"
            . " ($pt); InnoDB refuses this with errno 150";
    }
    // SET NULL cannot apply to a NOT NULL column, and a sentinel means the
    // column still spells "no reference" as 0 rather than NULL.
    $nullable = stripos($child[$rel['column']], 'NOT NULL') === false;
    if ($rel['action'] === 'SET NULL' && !$nullable) {
        $failures[] = "{$rel['child']}.{$rel['column']} is enabled with"
            . ' SET NULL but the column is NOT NULL';
    }
    if (array_key_exists('sentinel', $rel) && !$nullable) {
        $failures[] = "{$rel['child']}.{$rel['column']} is enabled but still"
            . " carries a sentinel and is NOT NULL; a foreign key accepts"
            . ' NULL for "no reference" and nothing else';
    }
}

/*
 * WHICH GROUP IS TURNED ON, named rather than counted.
 *
 * ADR 0031 lands the 87 constraints group by group so each one is a
 * reviewable commit with its own lab run. The checks above ask whether an
 * enabled relationship is well-formed; none of them asks whether it was
 * MEANT to be on, and `enabled` is one word in a 103-line table -- the
 * easiest thing in this file to change by accident, and the change with the
 * largest blast radius, because the next upgrade run declares whatever it
 * finds here against every customer database.
 *
 * So the set is pinned. EDIT THIS LIST IN THE SAME COMMIT THAT ENABLES A
 * GROUP, and if that feels like friction, it is meant to: enabling a group
 * is the deliberate act the phasing exists to keep deliberate.
 *
 * Group 1 -- host-owned junctions and satellites, schema step 382.
 * Group 2 -- identity: users, roles, user groups and sites, schema step 383.
 * Group 3 -- storage: groups, nodes, image and snapin assoc, schema step 384.
 * Group 5 -- configuration references, schema step 388.
 * Group 6 -- tasks and active work, schema step 390.
 * Group 7 -- user preferences, schema step 392. The only group whose table
 *   was created by the release that constrains it, so the only one with no
 *   orphan sweep before it.
 * Groups 'location', 'ou', 'windowskey', 'ldap', 'oidc', 'capone' and
 *   'subnetgroup' -- the plugin tables, each applied by a step appended to
 *   that plugin's own schema() in the fog-plugins repo, not by a core step.
 */
$expected = [
    // Group 1
    'groupMembers.gmHostID',
    'groupMembers.gmGroupID',
    'hostMAC.hmHostID',
    'snapinAssoc.saHostID',
    'snapinAssoc.saSnapinID',
    'printerAssoc.paHostID',
    'printerAssoc.paPrinterID',
    'moduleStatusByHost.msHostID',
    'moduleStatusByHost.msModuleID',
    'inventory.iHostID',
    'hostScreenSettings.hssHostID',
    'hostAutoLogOut.haloHostID',
    'powerManagement.pmHostID',
    'greenFog.gfHostID',
    // Group 2
    'siteHostMembers.shmSiteID',
    'siteHostMembers.shmHostID',
    'siteGroupMembers.sgmSiteID',
    'siteGroupMembers.sgmGroupID',
    'siteUserMembers.sumSiteID',
    'siteUserMembers.sumUserID',
    'siteUserGroupMembers.sugmSiteID',
    'siteUserGroupMembers.sugmUserGroupID',
    'siteRoleGrants.srgSiteID',
    'siteRoleGrants.srgRoleID',
    'siteUserGroupGrants.suggSiteID',
    'siteUserGroupGrants.suggGroupID',
    'roleUserAssoc.ruaRoleID',
    'roleUserAssoc.ruaUserID',
    'roleUserGroupAssoc.rugRoleID',
    'roleUserGroupAssoc.rugGroupID',
    'rolePermissions.rpRoleID',
    'userGroupMembers.ugmGroupID',
    'userGroupMembers.ugmUserID',
    'apiTokens.atUserID',
    'userAuths.uaUserID',
    // Group 3
    //
    // nfsGroupMembers.ngmGroupID was here and is not any more: step 385
    // retires the CASCADE step 384 created for it. A storage node always
    // belongs to a group but is not owned by it, so the relationship is
    // config/RESTRICT and lands with group 5.
    'imageGroupAssoc.igaStorageGroupID',
    'imageGroupAssoc.igaImageID',
    'snapinGroupAssoc.sgaStorageGroupID',
    'snapinGroupAssoc.sgaSnapinID',
    // Group 5
    'hosts.hostImage',
    'hosts.hostArchID',
    'images.imageArchID',
    'images.imageOSID',
    'images.imageTypeID',
    'images.imagePartitionTypeID',
    'scheduledTasks.stTaskTypeID',
    'scheduledTasks.stImageID',
    'fileDeleteQueue.fdqStorageGroupID',
    'multicastSessions.msNFSGroupID',
    'multicastSessions.msSenderNode',
    'nfsGroupMembers.ngmGroupID',
    // Group 6
    'tasks.taskHostID',
    'tasks.taskImageID',
    'tasks.taskStateID',
    'tasks.taskTypeID',
    'tasks.taskNFSGroupID',
    'tasks.taskNFSMemberID',
    'tasks.taskLastMemberID',
    'snapinJobs.sjHostID',
    'snapinJobs.sjStateID',
    'snapinTasks.stJobID',
    'snapinTasks.stSnapinID',
    'snapinTasks.stState',
    'fileDeleteQueue.fdqState',
    'multicastSessions.msState',
    'multicastSessionsAssoc.msID',
    'multicastSessionsAssoc.tID',
    // Group 7
    'userPrefs.upUserID',
    // Group 8
    'savedFilters.sfUserID',
    'savedFilters.sfCreatorID',
    'savedFilterUserAssoc.sfuaFilterID',
    'savedFilterUserAssoc.sfuaUserID',
    'savedFilterGroupAssoc.sfgaFilterID',
    'savedFilterGroupAssoc.sfgaUserGroupID',
    'savedFilterRoleAssoc.sfraFilterID',
    'savedFilterRoleAssoc.sfraRoleID',
    // Group 9 -- ADR 0038 group grants. Both ends CASCADE on both tables: a
    // grant is meaningless once either the group or the granted object is
    // gone, and an orphan row would offer a grant against a reused id.
    'groupSnapinAssoc.gsaGroupID',
    'groupSnapinAssoc.gsaSnapinID',
    'groupPrinterAssoc.gpaGroupID',
    'groupPrinterAssoc.gpaPrinterID',
    // Group 13 -- fog-agent software, design 0003. Same CASCADE reasoning
    // as group 9 for the two assignment tables; a status row is a fact
    // about a host and an entry and goes with either.
    'softwareAssoc.swaHostID',
    'softwareAssoc.swaSoftwareID',
    'groupSoftwareAssoc.gswaGroupID',
    'groupSoftwareAssoc.gswaSoftwareID',
    'softwareStatus.sstHostID',
    'softwareStatus.sstSoftwareID',
    // Group 10 -- modules, the third declarative grant. Same CASCADE
    // reasoning as group 9.
    'groupModuleAssoc.gmaGroupID',
    'groupModuleAssoc.gmaModuleID',
    // Group 11 -- Power Management, the fourth grant. `satellite` rather
    // than junction: a schedule references only its group, there being no
    // second end to link to. CASCADE, with a sharper edge than group 9's:
    // an orphan schedule left against a reused group id would silently
    // start shutting down every host that inherited the number.
    'groupPowerManagement.gpmGroupID',
    'agentEnrollment.aeHostID',
    'hostSoftware.hsHostID',
        'hostUserSession.husHostID',
        'hostDirectory.hdHostID',
        'hostPrinter.hpHostID',
        'hostSpooler.hspHostID',
    'hostNetwork.hnHostID',
    'agentWake.awTargetID',
    'agentWake.awSenderID',
    'hostFactState.hfsHostID',
    // Plugin groups, named for the plugin rather than numbered. Each
    // lands in that plugin's own repo, in an appended step of its
    // manager's schema(); see fog-plugins tests/foreign-keys.test.php,
    // which pins the call and its order from the other side.
    // Group 'location'
    'locationAssoc.laLocationID',
    'locationAssoc.laHostID',
    'location.lStorageGroupID',
    'location.lStorageNodeID',
    // Group 'ou'
    'ouAssoc.oaOUID',
    'ouAssoc.oaHostID',
    // Group 'windowskey'
    'windowsKeysAssoc.wkaImageID',
    'windowsKeysAssoc.wkaKeyID',
    // Group 'ldap'
    'LDAPGroups.lgServerID',
    'ldapGroupRoleAssoc.lgraGroupID',
    'ldapGroupRoleAssoc.lgraRoleID',
    'ldapGroupUserGroupAssoc.lgugGroupID',
    'ldapGroupUserGroupAssoc.lgugUserGroupID',
    'ldapUserGrant.lugUserID',
    // Group 'oidc'
    'OIDCGroups.ogProviderID',
    'oidcIdentity.oiProviderID',
    'oidcIdentity.oiUserID',
    'oidcGroupRoleAssoc.ograGroupID',
    'oidcGroupRoleAssoc.ograRoleID',
    'oidcGroupUserGroupAssoc.ogugGroupID',
    'oidcGroupUserGroupAssoc.ogugUserGroupID',
    'oidcUserGrant.ougUserID',
    // Group 'capone'
    'capone.cImageID',
    'capone.cOSID',
    // Group 'subnetgroup'
    'subnetgroup.sgGroupID',
];
/*
 * CORE GROUPS ARE INTS, PLUGIN GROUPS ARE STRINGS.
 *
 * planConstraints() and planSweep() both select on `$rel['group'] === $group`.
 * A strict comparison is what lets one map serve both spaces: 5 === 'ldap' is
 * false, and so is 'ldap' === 5. Written loosely it would not be -- PHP 7's
 * `5 == 'ldap'` is true, which would have made a core step apply the LDAP
 * plugin's constraints. So the separation is real but it rests on the two
 * spaces staying typed, and nothing else in the codebase would notice a
 * plugin group written as a number or a core group written as '5'.
 *
 * Every enabled relationship must also HAVE a group. One without is
 * unreachable: no filtered step applies it, and only the trailing unfiltered
 * reconcile would ever create it -- silently, on some later upgrade,
 * unswept.
 */
$corePlugins = [
    'location',
    'ou',
    'windowskey',
    'ldap',
    'oidc',
    'capone',
    'subnetgroup',
];
foreach ($map as $rel) {
    if (empty($rel['enabled'])) {
        continue;
    }
    $checked++;
    $key = $rel['child'] . '.' . $rel['column'];
    if (!array_key_exists('group', $rel)) {
        $failures[] = "$key is enabled but carries no group; no schema step"
            . ' can apply it';
        continue;
    }
    $isPluginTable = !isset($manifest['tables'][$rel['child']]);
    if ($isPluginTable) {
        if (!is_string($rel['group'])) {
            $failures[] = "$key is a plugin table but its group is not a"
                . ' string; a core step would then match it';
        } elseif (!in_array($rel['group'], $corePlugins, true)) {
            $failures[] = "$key names group '{$rel['group']}', which is not"
                . ' one of the known plugins';
        }
        continue;
    }
    if (!is_int($rel['group'])) {
        $failures[] = "$key is a core table but its group is not an int;"
            . ' a plugin step would then match it';
    }
}

$actual = [];
foreach ($map as $rel) {
    if (!empty($rel['enabled'])) {
        $actual[] = $rel['child'] . '.' . $rel['column'];
    }
}
sort($expected);
sort($actual);
$checked++;
foreach (array_diff($actual, $expected) as $extra) {
    $failures[] = "$extra is enabled but is not in this test's expected set."
        . ' Either the flip was accidental, or the group landed without'
        . ' updating the list above';
}
foreach (array_diff($expected, $actual) as $missing) {
    $failures[] = "$missing is expected to be enabled and is not."
        . ' A constraint that ships disabled is one FOG is not enforcing';
}

// ---------------------------------------------------------------------------
// The prose that quotes the map must be recomputed from the map.
//
// ADR 0031's Status paragraph and the survey's Phase D both state a count of
// declared relationships. Those numbers are what a reviewer reads FIRST and
// they are the part with no mechanism behind them -- the map is executable
// and cannot drift, but a sentence about the map silently can. It did: the
// Status paragraph sat at "40 of 87 declared; the remaining 47 still ship
// disabled" through six more groups and all five plugins, understating the
// work by half and pointing a reader at 47 pending constraints that did not
// exist.
//
// Matching on the digits rather than on the whole sentence deliberately: the
// prose around them should stay free to be rewritten, and only the claim
// about quantity is being pinned.
$declared = count(array_filter($map, static function ($r) {
    return !empty($r['enabled']);
}));
$total = count($map);
$plugin = count(array_filter($map, static function ($r) {
    return !empty($r['enabled']) && !is_int($r['group'] ?? null);
}));

$prose = [
    'docs/adr/0031-referential-integrity-is-declared-in-the-database.md' => [
        '#\*\*(\d+) of the map\'s (\d+) relationships are declared\.\*\*#',
        [$declared, $total],
        'the declared/total count in the Status paragraph',
    ],
    'docs/development/foreign-keys.md' => [
        '#(\d+) of the map\'s (\d+) relationships live in them#',
        [$plugin, $total],
        'the plugin/total count in Phase D',
    ],
];

foreach ($prose as $relative => $spec) {
    list($pattern, $want, $what) = $spec;
    $checked++;
    $path = $root . '/' . $relative;
    $text = is_readable($path) ? file_get_contents($path) : '';
    if ('' === $text) {
        $failures[] = "$relative could not be read, so $what is unverified";
        continue;
    }
    if (!preg_match($pattern, $text, $m)) {
        $failures[] = "$relative no longer states $what in the form this test"
            . ' pins. Restate it, or move the pin -- an unpinned count is the'
            . ' one that goes stale';
        continue;
    }
    $got = [(int)$m[1], (int)$m[2]];
    if ($got !== $want) {
        $failures[] = sprintf(
            '%s states %d of %d for %s; the map says %d of %d',
            $relative,
            $got[0],
            $got[1],
            $what,
            $want[0],
            $want[1]
        );
    }
}

if (count($failures)) {
    echo "FAIL: " . count($failures) . " problem(s).\n\n";
    foreach ($failures as $f) {
        echo "  $f\n";
    }
    exit(1);
}

printf(
    "foreign-key-map: %d checks passed, %d relationships, %d enabled\n",
    $checked,
    count($map),
    count(array_filter($map, static function ($r) {
        return !empty($r['enabled']);
    }))
);
exit(0);
