#!/usr/bin/env php
<?php
/**
 * Generates and diffs the expected-structure manifest used by
 * SchemaReconciler.
 *
 * PHP version 5
 *
 * @category SchemaManifest
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 *
 * The manifest is DATA, not something to hand-edit. It describes the
 * database structure a release expects, and SchemaReconciler closes any
 * gap between it and a live database.
 *
 * Two commands:
 *
 *   generate <fog-web-root> [output]
 *       Reads the CREATE TABLE names out of commons/schema.php, intersects
 *       them with what the live database actually has (which drops the
 *       scratch tables like globalSettings_new and anything since dropped,
 *       and ignores plugin tables), and writes the manifest. Any `renames`
 *       block in an existing manifest is carried through untouched --
 *       renames cannot be derived from an end-state and are the one part
 *       maintained by hand.
 *
 *   diff <old-manifest> <new-manifest>
 *       Reports every table and column present in the first manifest but
 *       missing from the second. Point it at a 1.5 manifest and a 1.6 one
 *       to answer "has 1.6 forgotten a structural change 1.5 made?" -- the
 *       failure this whole mechanism exists to catch. It also lists
 *       columns that moved in both directions on one table, which are the
 *       candidates for a `renames` entry.
 */

/**
 * Loads the DB constants out of a FOG install's config.class.php.
 *
 * @param string $root The web root of the FOG install.
 *
 * @return PDO
 */
function connect($root)
{
    $config = $root . '/lib/fog/config.class.php';
    if (!file_exists($config)) {
        fwrite(STDERR, "No config.class.php under $root\n");
        exit(1);
    }
    $src = file_get_contents($config);
    $vals = [];
    foreach (['HOST', 'NAME', 'USERNAME', 'PASSWORD'] as $key) {
        if (preg_match(
            "/define\(\s*'DATABASE_$key'\s*,\s*'(.*?)'\s*\)/s",
            $src,
            $m
        )) {
            $vals[$key] = $m[1];
        }
    }
    if (!isset($vals['NAME'])) {
        fwrite(STDERR, "Could not read DATABASE_* from $config\n");
        exit(1);
    }
    return new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s',
            $vals['HOST'] ?: 'localhost',
            $vals['NAME']
        ),
        $vals['USERNAME'],
        $vals['PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

/**
 * The table names commons/schema.php declares a CREATE TABLE for.
 *
 * @param string $root The web root of the FOG install.
 *
 * @return array
 */
function declaredTables($root)
{
    $src = file_get_contents($root . '/commons/schema.php');
    // Join the PHP string concatenation the schema file is written in, so
    // a CREATE TABLE split across source lines still matches.
    $src = preg_replace('/"\s*\.\s*"/', '', $src);
    $src = preg_replace("/'\s*\.\s*'/", '', $src);
    preg_match_all(
        '/CREATE TABLE\s*(?:IF NOT EXISTS\s*)?`(\w+)`/i',
        $src,
        $m
    );
    return array_values(array_unique($m[1]));
}

/**
 * Renders a value as PHP source.
 *
 * @param mixed $value  The value to render.
 * @param int   $indent Current indent depth.
 *
 * @return string
 */
function render($value, $indent = 1)
{
    $pad = str_repeat('    ', $indent);
    if (!is_array($value)) {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }
    $out = "[\n";
    foreach ($value as $k => $v) {
        $out .= $pad . '    '
            . (is_int($k) ? '' : "'" . str_replace("'", "\\'", $k) . "' => ")
            . render($v, $indent + 1)
            . ",\n";
    }
    return $out . $pad . ']';
}

$cmd = $argv[1] ?? '';

if ($cmd === 'generate') {
    $root = rtrim($argv[2] ?? '', '/');
    $out = $argv[3] ?? ($root . '/commons/schema-expected.php');
    if (!$root) {
        fwrite(STDERR, "usage: schema-manifest.php generate <fog-web-root> [output]\n");
        exit(1);
    }
    $pdo = connect($root);
    $declared = array_map('strtolower', declaredTables($root));

    $live = $pdo->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES'
        . ' WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'
    )->fetchAll(PDO::FETCH_COLUMN);

    $tables = [];
    foreach ($live as $table) {
        if (!in_array(strtolower($table), $declared)) {
            continue; // plugin table, or not part of core schema
        }
        $raw = $pdo->query(
            sprintf('SHOW CREATE TABLE `%s`', $table)
        )->fetch(PDO::FETCH_NUM)[1];
        $create = $raw;
        // Reconciler only ever runs this when the table is absent, but
        // IF NOT EXISTS makes it harmless if the snapshot was stale.
        $create = preg_replace(
            '/^CREATE TABLE /i',
            'CREATE TABLE IF NOT EXISTS ',
            $create
        );
        $create = preg_replace('/\s+/', ' ', $create);
        // Strip the live auto-increment counter. It is install-specific
        // state, not structure, and has no business in a checked-in file.
        $create = preg_replace('/ AUTO_INCREMENT=\d+/i', '', $create);

        // Column definitions come from SHOW CREATE TABLE, not from
        // information_schema. COLUMN_DEFAULT is not portable: MariaDB 10.2+
        // returns string defaults WITH their quotes while older MySQL
        // returns them without, so rebuilding a definition from its parts
        // either double-quotes the default (turning a default of 0 into the
        // literal string '0') or leaves it unquoted. SHOW CREATE TABLE is
        // already exact, correctly quoted DDL for the running server.
        $cols = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if (!preg_match('/^`(\w+)`\s+(.+?),?$/', $line, $m)) {
                continue;
            }
            // Skip the key/constraint clauses, which also start indented.
            if (preg_match('/^(PRIMARY|UNIQUE|KEY|CONSTRAINT|FULLTEXT|INDEX)\b/i', $line)) {
                continue;
            }
            // auto_increment is deliberately dropped: it is only valid on a
            // key column, and ADD COLUMN on an existing table has no key
            // yet. The CREATE path carries it; the ADD path never needs it.
            $def = preg_replace('/\s*AUTO_INCREMENT\b/i', '', $m[2]);
            $cols[$m[1]] = trim($def);
        }
        $tables[$table] = ['create' => $create, 'columns' => $cols];
    }

    // Preserve the hand-maintained renames block across regeneration.
    $renames = [];
    if (file_exists($out)) {
        $existing = include $out;
        if (is_array($existing) && !empty($existing['renames'])) {
            $renames = $existing['renames'];
        }
    }

    $php = "<?php\n"
        . "/**\n"
        . " * Expected database structure for this release.\n"
        . " *\n"
        . " * GENERATED FILE -- do not hand-edit the `tables` block. Regenerate\n"
        . " * with:  php bin/schema-manifest.php generate <fog-web-root>\n"
        . " *\n"
        . " * The `renames` block IS maintained by hand and is preserved across\n"
        . " * regeneration. A manifest describes an END state, so a renamed\n"
        . " * column is indistinguishable from a new one; without an entry here\n"
        . " * SchemaReconciler would add the target column empty and strand the\n"
        . " * data in the old one.\n"
        . " *\n"
        . " * Consumed by SchemaReconciler::reconcile().\n"
        . " *\n"
        . " * PHP version 5\n"
        . " *\n"
        . " * @category SchemaExpected\n"
        . " * @package  FOGProject\n"
        . " * @author   Tom Elliott <tommygunsster\@gmail.com>\n"
        . " * @license  http://opensource.org/licenses/gpl-3.0 GPLv3\n"
        . " * @link     https://fogproject.org\n"
        . " */\n"
        . "return [\n"
        . "    'renames' => " . render($renames) . ",\n"
        . "    'tables' => " . render($tables) . ",\n"
        . "];\n";
    file_put_contents($out, $php);
    printf(
        "Wrote %s: %d tables, %d renames preserved\n",
        $out,
        count($tables),
        count($renames)
    );
    exit(0);
}

if ($cmd === 'diff') {
    $a = include ($argv[2] ?? '');
    $b = include ($argv[3] ?? '');
    if (!is_array($a) || !is_array($b)) {
        fwrite(STDERR, "usage: schema-manifest.php diff <old> <new>\n");
        exit(1);
    }
    $lower = function ($m) {
        $o = [];
        foreach ($m['tables'] as $t => $d) {
            $o[strtolower($t)] = array_map(
                'strtolower',
                array_keys($d['columns'] ?? [])
            );
        }
        return $o;
    };
    $A = $lower($a);
    $B = $lower($b);
    $found = 0;
    foreach ($A as $table => $cols) {
        if (!isset($B[$table])) {
            printf("MISSING TABLE   %s\n", $table);
            $found++;
            continue;
        }
        $gone = array_diff($cols, $B[$table]);
        $added = array_diff($B[$table], $cols);
        foreach ($gone as $c) {
            printf("MISSING COLUMN  %s.%s\n", $table, $c);
            $found++;
        }
        // Columns lost on one side and gained on the other are what a
        // rename looks like from the outside. Reported, never guessed at.
        if (count($gone) && count($added)) {
            printf(
                "  ^ possible rename on %s: [%s] -> [%s]\n",
                $table,
                implode(',', $gone),
                implode(',', $added)
            );
        }
    }
    printf("\n%d difference(s).\n", $found);
    exit($found ? 1 : 0);
}

fwrite(
    STDERR,
    "usage:\n"
    . "  schema-manifest.php generate <fog-web-root> [output]\n"
    . "  schema-manifest.php diff <old-manifest> <new-manifest>\n"
);
exit(1);
