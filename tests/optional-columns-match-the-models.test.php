<?php
/**
 * Schema step 286 must give a default to exactly the columns FOG calls
 * optional -- no more, no less.
 *
 * GH-1245, third instalment. A column declared NOT NULL with no DEFAULT is
 * only mandatory if something enforces it, and for nine years nothing did:
 * PDODB cleared sql_mode on every connection, so the server downgraded the
 * error to a warning and substituted an implicit zero. Removing the clear
 * turned 329 such declarations into real constraints at once, which is how
 * saving FOG settings started failing with error 1364.
 *
 * Step 286 resolves that by giving the OPTIONAL ones a default. Which ones
 * are optional is not a judgement call -- FOG already states it, in each
 * model's $databaseFieldsRequired -- so the risk is not that the step is
 * wrong today but that the two statements drift apart later, silently and in
 * either direction:
 *
 *   - a column added to $databaseFieldsRequired while it still carries a
 *     default from the map. The model refuses the save, the database does
 *     not, and every write path that bypasses isValid() -- insertBatch(),
 *     the API, a service -- can still make the row the model forbids.
 *   - a foreign key finding its way into the map. An INSERT that forgets the
 *     row it hangs off then succeeds and makes a silent orphan pointing at
 *     id 0 or ''. taskLog.taskID is the near miss: it is a mediumtext on
 *     this branch, so a type-based rule does not see it as a key at all.
 *
 * This branch has no commons/schema-expected.php and no SchemaReconciler, so
 * 1.6's manifest-driven equivalent cannot port. The rule is checked against
 * the models directly instead, which needs no database.
 *
 * Plugin tables are absent from the map on purpose and that is asserted too:
 * a plugin's table is built by Schema::createTable() at install time with no
 * defaults at all, and install() calls uninstall() -- which DROPS it. An
 * ALTER here would be erased by the next plugin install.
 *
 * The live half was proven separately, against a real 1.5 server:
 * scripts/background_scripts/prove_schema_286.php runs the step through
 * FOG's own boot and checks what the server then accepts.
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
$web = $root . '/packages/web';
$failures = array();
$checks = 0;

/**
 * Records a check.
 *
 * @param bool   $ok      whether it passed
 * @param string $message what failed, stated as the defect
 *
 * @return void
 */
function ocCheck($ok, $message)
{
    global $checks, $failures;
    $checks++;
    if (!$ok) {
        $failures[] = $message;
    }
}

// ---------------------------------------------------------------
// 1. The step is present and the schema version reaches it.
// ---------------------------------------------------------------
$schemaSrc = file_get_contents($web . '/commons/schema.php');
$systemSrc = file_get_contents($web . '/lib/fog/system.class.php');

ocCheck(
    false !== strpos($schemaSrc, "\n// 286\n"),
    'schema step 286 is gone. Optional columns lose their defaults again and '
    . 'error 1364 comes back on any INSERT that omits one.'
);

preg_match("/define\('FOG_SCHEMA',\s*(\d+)\)/", $systemSrc, $ver);
ocCheck(
    isset($ver[1]) && (int) $ver[1] >= 286,
    'FOG_SCHEMA is below 286, so the schema updater never runs the step. '
    . 'A step nothing reaches is not a migration.'
);

// ---------------------------------------------------------------
// 2. Read the map out of the step.
// ---------------------------------------------------------------
$optional = array();
$block = substr($schemaSrc, (int) strpos($schemaSrc, "\n// 286\n"));
if (preg_match('/\$optional = array\((.*?)\n        \);/s', $block, $m)) {
    $table = '';
    foreach (explode("\n", $m[1]) as $line) {
        if (preg_match("/^\s*'([^']+)' => array\(/", $line, $t)) {
            $table = $t[1];
            $optional[$table] = array();
            continue;
        }
        if ('' === $table) {
            continue;
        }
        preg_match_all("/'([^']+)'/", $line, $c);
        foreach ($c[1] as $col) {
            $optional[$table][] = $col;
        }
    }
}
$total = 0;
foreach ($optional as $cols) {
    $total += count($cols);
}
ocCheck(
    count($optional) > 20 && $total > 150,
    'the $optional map could not be read out of step 286, so every check '
    . 'below passes vacuously.'
);

// ---------------------------------------------------------------
// 3. Read every model's table, fields and required fields.
// ---------------------------------------------------------------
$classes = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($web));
foreach ($it as $file) {
    if (!preg_match('/\.class\.php$/', $file->getFilename())) {
        continue;
    }
    $src = file_get_contents($file->getPathname());
    if (!preg_match('/^\s*class\s+(\w+)\s+extends\s+(\w+)/mi', $src, $m)) {
        continue;
    }
    $fields = array();
    if (preg_match(
        '/\$databaseFields\s*=\s*(?:array\s*\(|\[)(.*?)(?:\)|\])\s*;/s',
        $src,
        $b
    )) {
        preg_match_all(
            '/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/',
            $b[1],
            $p,
            PREG_SET_ORDER
        );
        foreach ($p as $pair) {
            $fields[$pair[1]] = $pair[2];
        }
    }
    $req = array();
    if (preg_match(
        '/\$databaseFieldsRequired\s*=\s*(?:array\s*\(|\[)(.*?)(?:\)|\])\s*;/s',
        $src,
        $b
    )) {
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $b[1], $p);
        $req = $p[1];
    }
    $tbl = '';
    if (preg_match('/\$databaseTable\s*=\s*[\'"]([^\'"]+)/', $src, $t)) {
        $tbl = $t[1];
    }
    $classes[$m[1]] = array(
        'parent' => $m[2],
        'table' => $tbl,
        'fields' => $fields,
        'req' => $req,
        'plugin' => false !== strpos($file->getPathname(), '/lib/plugins/'),
    );
}

/**
 * Collects a property up the inheritance chain, parent first.
 *
 * A model states only what it adds; Host does not restate FOGController's.
 *
 * @param string $name    class to start from
 * @param string $key     'fields' or 'req'
 * @param array  $classes every class read above
 * @param array  $seen    guards a cycle
 *
 * @return array
 */
function ocResolve($name, $key, $classes, $seen = array())
{
    if (!isset($classes[$name]) || isset($seen[$name])) {
        return array();
    }
    $seen[$name] = true;

    return array_merge(
        ocResolve($classes[$name]['parent'], $key, $classes, $seen),
        $classes[$name][$key]
    );
}

$required = array();
$pluginOwned = array();
foreach ($classes as $name => $class) {
    $table = $class['table'];
    if (!$table) {
        $walk = $name;
        while (isset($classes[$walk]) && !$classes[$walk]['table']) {
            $walk = $classes[$walk]['parent'];
        }
        $table = isset($classes[$walk]) ? $classes[$walk]['table'] : '';
    }
    if (!$table) {
        continue;
    }
    $key = strtolower($table);
    // Plugin-owned only if NO core class claims the same table: the
    // taskstateedit and tasktypeedit plugins declare models on the CORE
    // taskStates and taskTypes tables, and those are schema.php's.
    if ($class['plugin']) {
        if (!isset($pluginOwned[$key])) {
            $pluginOwned[$key] = true;
        }
    } else {
        $pluginOwned[$key] = false;
    }
    $fields = ocResolve($name, 'fields', $classes);
    foreach (ocResolve($name, 'req', $classes) as $friendly) {
        if (isset($fields[$friendly])) {
            $required[$key][strtolower($fields[$friendly])] = true;
        }
    }
}

ocCheck(
    count($classes) > 50 && count($required) > 20,
    'the model scan found almost nothing, so the two checks below pass '
    . 'vacuously.'
);

// ---------------------------------------------------------------
// 4. Nothing in the map is a column a model calls required.
// ---------------------------------------------------------------
foreach ($optional as $table => $cols) {
    $key = strtolower($table);
    foreach ($cols as $col) {
        ocCheck(
            !isset($required[$key][strtolower($col)]),
            sprintf(
                '%s.%s carries a DEFAULT from step 286 while a model lists '
                . 'it in $databaseFieldsRequired. The model refuses the '
                . 'save and the database accepts it, so every write path '
                . 'that skips isValid() can still create the row the model '
                . 'forbids. Drop it from the map, or from the model.',
                $table,
                $col
            )
        );
    }
}

// ---------------------------------------------------------------
// 5. No foreign key is in the map, and no plugin table.
// ---------------------------------------------------------------
foreach ($optional as $table => $cols) {
    ocCheck(
        empty($pluginOwned[strtolower($table)]),
        sprintf(
            '%s is a plugin-owned table and step 286 alters it. The next '
            . 'plugin install DROPS and rebuilds that table without '
            . 'defaults, so the alter is erased and the entry is false '
            . 'comfort. Fix Schema::createTable()\'s callers instead.',
            $table
        )
    );
    foreach ($cols as $col) {
        ocCheck(
            !preg_match('/ID$/', $col),
            sprintf(
                '%s.%s ends in ID and step 286 gives it a default, so an '
                . 'INSERT that forgets the row it hangs off now succeeds '
                . 'and makes a silent orphan. Not gated on an integer '
                . 'type on purpose: taskLog.taskID is a mediumtext.',
                $table,
                $col
            )
        );
    }
}

// ---------------------------------------------------------------
// 6. The columns that must never be defaulted, named outright.
// ---------------------------------------------------------------
$canaries = array(
    'users' => array('uName', 'uPass'),
    'hosts' => array('hostName'),
    'nfsGroupMembers' => array('ngmPass', 'ngmRootPath', 'ngmUser'),
    'taskLog' => array('taskID', 'taskStateID'),
    'tasks' => array('taskHostID', 'taskTypeID'),
);
foreach ($canaries as $table => $cols) {
    foreach ($cols as $col) {
        ocCheck(
            !isset($optional[$table])
            || !in_array($col, $optional[$table], true),
            sprintf(
                '%s.%s appears in step 286. A default here makes a row that '
                . 'is meaningless -- a nameless user, a storage node with '
                . 'no root path, a log line belonging to no task -- '
                . 'insertable without complaint.',
                $table,
                $col
            )
        );
    }
}

if (count($failures) > 0) {
    echo 'FAIL optional-columns-match-the-models ('
        . count($failures) . " problem(s))\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "PASS optional-columns-match-the-models ($checks checks)\n";
exit(0);
