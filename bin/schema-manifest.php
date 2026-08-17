#!/usr/bin/env php
<?php
/**
 * Generates and diffs the expected-structure manifest used by
 * SchemaReconciler.
 *
 * PHP version 7.4+
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
    return new \PDO(
        sprintf(
            'mysql:host=%s;dbname=%s',
            $vals['HOST'] ?: 'localhost',
            $vals['NAME']
        ),
        $vals['USERNAME'],
        $vals['PASSWORD'],
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
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
    )->fetchAll(\PDO::FETCH_COLUMN);

    $tables = [];
    foreach ($live as $table) {
        if (!in_array(strtolower($table), $declared)) {
            continue; // plugin table, or not part of core schema
        }
        $raw = $pdo->query(
            sprintf('SHOW CREATE TABLE `%s`', $table)
        )->fetch(\PDO::FETCH_NUM)[1];
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

        // Normalize the UCA-14.0.0 collations back to _general_ci.
        //
        // MariaDB 11.4 made utf8mb3_uca1400_ai_ci the DEFAULT collation for
        // the Unicode charsets, so a table created there without an explicit
        // COLLATE gets it and SHOW CREATE TABLE prints it back explicitly.
        // These strings are executed verbatim by SchemaReconciler::plan() on
        // any install missing the table, and no MariaDB below 11.4 -- nor any
        // version of MySQL, which has never had them -- can execute that.
        // Regenerating on an 11.4 box shipped `1273 Unknown collation` to
        // every older server, and because a failed reconcile also stops the
        // schema version being recorded, those servers then 308-redirected
        // every request to ?node=schema, taking the installer's pre-upgrade
        // database dump down with them.
        //
        // Normalized rather than stripped, deliberately. Stripping would let
        // each target server apply its own default, which on 11.4+ is the
        // uca1400 collation again -- reintroducing the bug for anyone
        // upgrading there, and leaving these tables on a different collation
        // from the fifty-four that already say _general_ci. Naming it keeps
        // one collation across the whole schema by construction.
        //
        // Only the collation is rewritten; CHARSET=utf8mb3 is left alone, as
        // utf8mb3_general_ci is the charset's own default collation and the
        // two agree.
        $create = preg_replace(
            '/\b(utf8(?:mb3|mb4)?)_uca\d+_[a-z0-9_]+\b/i',
            '$1_general_ci',
            $create
        );

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
        // The enforced floor, not the historic boilerplate. The 727-file
        // sweep off "PHP version 5" fixed the manifest but not the generator
        // that writes it, so the next regeneration silently reverted the file
        // and failed tests/php-version-docblocks.test.php -- a generated
        // artifact drifting back because only its output was corrected.
        . " * PHP version 7.4+\n"
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

    // Map the OLD side's column names through the NEW side's declared
    // renames before comparing. pAnon1 and pIcon are the same column with
    // two names; without this every declared rename reports as both a
    // missing column and an unexplained new one, and the check cries wolf
    // on exactly the differences that are already accounted for.
    foreach ((array)($b['renames'] ?? []) as $r) {
        $t = strtolower($r['table'] ?? '');
        $from = strtolower($r['from'] ?? '');
        $to = strtolower($r['to'] ?? '');
        if (!$t || !$from || !$to || !isset($A[$t])) {
            continue;
        }
        $i = array_search($from, $A[$t]);
        if (false !== $i) {
            $A[$t][$i] = $to;
        }
    }
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

if ($cmd === 'check') {
    // Static staleness check: does the manifest still describe what
    // commons/schema.php actually builds? Needs no database, so it can run
    // in a pre-commit hook or CI.
    //
    // The failure it exists to catch: someone adds a CREATE TABLE or an
    // ADD COLUMN to schema.php and does not regenerate the manifest, so
    // SchemaReconciler silently stops knowing about the new structure and
    // an upgrading 1.5 install never gets it.
    $web = rtrim($argv[2] ?? '', '/');
    $manFile = $argv[3] ?? ($web . '/commons/schema-expected.php');
    if (!$web || !file_exists($web . '/commons/schema.php')) {
        fwrite(STDERR, "usage: schema-manifest.php check <web-dir> [manifest]\n");
        exit(1);
    }
    $manifest = file_exists($manFile) ? include $manFile : null;
    if (!is_array($manifest) || empty($manifest['tables'])) {
        fwrite(STDERR, "check: no usable manifest at $manFile\n");
        exit(1);
    }

    // Extract the DDL with PHP's own lexer rather than by regex. schema.php
    // is ~4800 lines of concatenated string literals containing quotes and
    // backslashes; a regex that tries to find string boundaries in that will
    // eventually match a span running from the middle of one literal into
    // the next, and silently produce a statement that was never in the file.
    // token_get_all is the actual parser, so the literals come out exact.
    $tokens = token_get_all(file_get_contents($web . '/commons/schema.php'));
    $unquote = function ($lit) {
        $q = $lit[0];
        $body = substr($lit, 1, -1);
        return $q === "'"
            ? str_replace(["\\'", '\\\\'], ["'", '\\'], $body)
            : stripcslashes($body);
    };
    $ops = [];
    $pendingDrop = false;
    for ($i = 0, $n = count($tokens); $i < $n; $i++) {
        $tk = $tokens[$i];
        if (is_array($tk) && $tk[0] === T_STRING && $tk[1] === 'dropTable') {
            $pendingDrop = true;
            continue;
        }
        if (!is_array($tk) || $tk[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $val = $unquote($tk[1]);
        if ($pendingDrop) {
            $ops[] = ['drop' => $val];
            $pendingDrop = false;
            continue;
        }
        // Absorb `"a" . "b" . "c"` into one statement.
        $j = $i + 1;
        while ($j < $n) {
            $next = $tokens[$j];
            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                $j++;
                continue;
            }
            if ($next === '.'
                && isset($tokens[$j + 1])
            ) {
                $k = $j + 1;
                while ($k < $n
                    && is_array($tokens[$k])
                    && in_array($tokens[$k][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])
                ) {
                    $k++;
                }
                if (is_array($tokens[$k]) && $tokens[$k][0] === T_CONSTANT_ENCAPSED_STRING) {
                    $val .= $unquote($tokens[$k][1]);
                    $i = $k;
                    $j = $k + 1;
                    continue;
                }
            }
            break;
        }
        $ops[] = ['sql' => $val];
    }

    // Walk the DDL in order and simulate it, so tables that are created and
    // later dropped or renamed away (globalSettings_new, aloLog, ...) do not
    // read as missing from the manifest.
    $tables = [];
    foreach ($ops as $op) {
        if (isset($op['drop'])) {
            unset($tables[strtolower($op['drop'])]);
            continue;
        }
        $s = preg_replace('/\s+/', ' ', $op['sql']);
        if ($s === '') {
            continue;
        }
        if (preg_match('/^\s*CREATE TABLE\s*(?:IF NOT EXISTS\s*)?`(\w+)`/i', $s, $m)) {
            $t = strtolower($m[1]);
            $tables[$t] = $tables[$t] ?? [];
        } elseif (preg_match('/^\s*RENAME TABLE\s*`(\w+)`\s*TO\s*`(\w+)`/i', $s, $m)) {
            $from = strtolower($m[1]);
            $tables[strtolower($m[2])] = $tables[$from] ?? [];
            unset($tables[$from]);
        } elseif (preg_match('/^\s*DROP TABLE\s*(?:IF EXISTS\s*)?(.+)$/i', $s, $m)) {
            preg_match_all('/`(\w+)`/', $m[1], $dm);
            foreach ($dm[1] as $d) {
                unset($tables[strtolower($d)]);
            }
        } elseif (preg_match('/^\s*ALTER TABLE\s*`?(\w+)`?\s*(.*)$/i', $s, $m)) {
            $t = strtolower($m[1]);
            if (!isset($tables[$t])) {
                continue;
            }
            $rest = $m[2];
            // CHANGE first: it also matches nothing else, and its target is
            // the surviving name.
            if (preg_match_all(
                '/\bCHANGE\s+(?:COLUMN\s+)?`?(\w+)`?\s+`?(\w+)`?/i',
                $rest,
                $cm,
                PREG_SET_ORDER
            )) {
                foreach ($cm as $c) {
                    $tables[$t] = array_values(
                        array_diff($tables[$t], [strtolower($c[1])])
                    );
                    $tables[$t][] = strtolower($c[2]);
                }
            }
            if (preg_match_all('/\bADD\s+(?:COLUMN\s+)?`(\w+)`/i', $rest, $am)) {
                foreach ($am[1] as $c) {
                    $tables[$t][] = strtolower($c);
                }
            }
            if (preg_match_all('/\bDROP\s+(?:COLUMN\s+)?`?(\w+)`?/i', $rest, $dm)) {
                foreach ($dm[1] as $c) {
                    $tables[$t] = array_values(
                        array_diff($tables[$t], [strtolower($c)])
                    );
                }
            }
        }
    }

    $have = [];
    foreach ($manifest['tables'] as $t => $d) {
        $have[strtolower($t)] = array_map(
            'strtolower',
            array_keys($d['columns'] ?? [])
        );
    }
    $problems = 0;
    foreach ($tables as $t => $cols) {
        if (!isset($have[$t])) {
            printf("STALE MANIFEST  table `%s` is built by schema.php but absent\n", $t);
            $problems++;
            continue;
        }
        foreach (array_unique($cols) as $c) {
            if (!in_array($c, $have[$t])) {
                printf("STALE MANIFEST  column `%s`.`%s` is added by schema.php but absent\n", $t, $c);
                $problems++;
            }
        }
    }
    if ($problems) {
        printf(
            "\n%d problem(s). Regenerate:\n  php bin/schema-manifest.php generate <fog-web-root>\n",
            $problems
        );
        exit(1);
    }
    printf("manifest covers all %d tables schema.php builds.\n", count($tables));
    exit(0);
}

fwrite(
    STDERR,
    "usage:\n"
    . "  schema-manifest.php generate <fog-web-root> [output]\n"
    . "  schema-manifest.php diff   <old-manifest> <new-manifest>\n"
    . "  schema-manifest.php check  <web-dir> [manifest]\n"
);
exit(1);
