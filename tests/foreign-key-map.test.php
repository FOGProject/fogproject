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
 * Columns whose name ends in ID and which are not row references.
 *
 * One entry, and it should stay that way: an allowlist here is a hole in the
 * gate, so each addition needs a reason a reader can check.
 */
$notAReference = [
    // A unix process id for the running multicast sender, not a row id.
    'multicastSessions.msSenderPID',
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
        if (!preg_match('/ID$/', $column) || $column === $primary) {
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
