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
 *
 *       A table 1.6 dropped ON PURPOSE is not a forgotten port, but the
 *       diff cannot tell the two apart -- both look like "present there,
 *       absent here". The NEW manifest declares those in a `retired`
 *       block and they are reported as accounted for rather than as a
 *       difference. Without it the warning fires on every schema commit
 *       forever, and a warning that always fires is one nobody reads.
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
    // Two spellings: this reads an ALREADY INSTALLED tree and a server
    // legitimately runs an older release than the script. Config is generated
    // into commons/ now, beside fogpaths.php; it used to go to lib/fog/.
    $config = null;
    foreach (['/commons/config.class.php', '/lib/fog/config.class.php'] as $rel) {
        if (file_exists(rtrim($root, '/') . $rel)) {
            $config = rtrim($root, '/') . $rel;
            break;
        }
    }
    if ($config === null) {
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

        // Normalize the UCA-14.0.0 collations back to _general_ci, here on
        // $raw so that BOTH things derived from it are covered -- the CREATE
        // below and the per-column definitions further down, which are parsed
        // out of $raw separately and reach the server by their own route
        // (Pass 3 of SchemaReconciler::plan() builds ALTER TABLE ... ADD
        // COLUMN from them). Normalizing only the CREATE would leave the
        // column path able to ship the same unusable name.
        //
        // MariaDB 11.4 made utf8mb3_uca1400_ai_ci the DEFAULT collation for
        // the Unicode charsets, so a table created there without an explicit
        // COLLATE gets it and SHOW CREATE TABLE prints it back explicitly.
        // These strings are executed verbatim on any install missing the
        // table or the column, and no MariaDB below 11.4 -- nor any version
        // of MySQL, which has never had them -- can execute that. Generating
        // on 11.4+ therefore shipped `1273 Unknown collation` to every older
        // server, and because a failed reconcile also stops the schema
        // version being recorded, those servers then 308-redirected every
        // request to ?node=schema, taking the installer's pre-upgrade
        // database dump down with them. GH-1147.
        //
        // Normalized rather than stripped, deliberately. Stripping would let
        // each target server apply its own default, which on 11.4+ is the
        // uca1400 collation again -- reintroducing the bug for anyone
        // upgrading there, and leaving these tables on a different collation
        // from the rest, which raises "Illegal mix of collations" on a
        // varchar join across the split. Naming it keeps one collation across
        // the whole schema by construction.
        //
        // Only the collation is rewritten; CHARSET=utf8mb3 is left alone, as
        // utf8mb3_general_ci is that charset's own default collation and the
        // two agree.
        //
        // Note this cannot be caught by the person introducing it: on the
        // generating server every one of these statements is valid. Only an
        // older server ever sees the failure, which is why
        // tests/schema-portable-collation.test.php checks the committed file
        // rather than trusting the generator to have been run correctly.
        $raw = preg_replace(
            '/\b(utf8(?:mb3|mb4)?)_uca\d+_[a-z0-9_]+\b/i',
            '$1_general_ci',
            $raw
        );

        $create = $raw;
        // Reconciler only ever runs this when the table is absent, but
        // IF NOT EXISTS makes it harmless if the snapshot was stale.
        $create = preg_replace(
            '/^CREATE TABLE /i',
            'CREATE TABLE IF NOT EXISTS ',
            $create
        );
        $create = preg_replace('/\s+/', ' ', $create);

        // Strip foreign keys out of the CREATE.
        //
        // Not cosmetic, and not a preference. SchemaReconciler executes
        // these strings in MANIFEST order, which is not dependency order --
        // apiTokens precedes users, groupMembers precedes hosts. Measured on
        // an empty database: with the constraints inlined, 34 of 70 tables
        // fail with errno 150; with them removed and added afterward as
        // ALTERs, none do. tests/schema-executes.test.php's second database
        // is exactly that path, so a regeneration that let them through
        // would turn it red -- see ADR 0031 decision 4.
        //
        // The constraints are not lost: commons/schema-constraints.php
        // declares them and SchemaReconciler::planConstraints() adds them in
        // its own pass, after every table exists.
        //
        // The KEY that InnoDB creates alongside a foreign key is left alone.
        // It is an ordinary index once the constraint is gone, and dropping
        // it would make the manifest describe a table with worse lookup
        // behavior than a fresh install has.
        $create = preg_replace(
            '/,\s*CONSTRAINT `[^`]+` FOREIGN KEY \([^)]*\)'
            . ' REFERENCES `[^`]+` \([^)]*\)'
            . '(?: ON DELETE (?:RESTRICT|CASCADE|SET NULL|NO ACTION))?'
            . '(?: ON UPDATE (?:RESTRICT|CASCADE|SET NULL|NO ACTION))?/i',
            '',
            $create
        );

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
        // Refuse to emit a table whose two blocks disagree.
        //
        // They are derived from one $raw and so cannot normally differ, but
        // they are derived by DIFFERENT means -- the create is $raw with its
        // whitespace collapsed, the columns are $raw parsed a line at a time
        // -- and only the columns parse can fail quietly. If a server ever
        // prints SHOW CREATE TABLE in a shape that regex does not match,
        // $cols comes out empty, every `columns` block ships empty, and
        // SchemaReconciler silently loses its ability to add a missing
        // column to ANY table: plan() pass 3 skips a table whose `columns`
        // is empty. That is the failure taskLog had alone in 407be1a53,
        // multiplied by the whole schema, and nothing downstream would say
        // so -- the manifest would still be valid PHP and the create
        // strings would still execute on a fresh install.
        //
        // Names only. The definitions legitimately differ: the create keeps
        // AUTO_INCREMENT and the column definitions drop it. Comparing the
        // two blocks properly is tests/schema-manifest-consistent.test.php's
        // job, and it works on the committed file, which is where the
        // hand-edits that actually drifted happen.
        if (!count($cols)) {
            fwrite(
                STDERR,
                "Refusing to write: parsed no columns for `$table`.\n"
                . "SHOW CREATE TABLE output was not in the expected shape.\n"
            );
            exit(1);
        }
        foreach (array_keys($cols) as $name) {
            if (false !== strpos($create, '`' . $name . '`')) {
                continue;
            }
            fwrite(
                STDERR,
                "Refusing to write: `$table`.`$name` was parsed as a column"
                . " but does not appear in the CREATE statement.\n"
            );
            exit(1);
        }
        $tables[$table] = ['create' => $create, 'columns' => $cols];
    }

    // Preserve the hand-maintained blocks across regeneration. Neither can
    // be derived from a live database: `renames` describes a transition the
    // end state has already erased, and `retired` describes a table that is
    // absent precisely because the decision was taken.
    $renames = [];
    $retired = [];
    if (file_exists($out)) {
        $existing = include $out;
        if (is_array($existing) && !empty($existing['renames'])) {
            $renames = $existing['renames'];
        }
        if (is_array($existing) && !empty($existing['retired'])) {
            $retired = $existing['retired'];
        }
    }

    $php = "<?php\n"
        . "/**\n"
        . " * Expected database structure for this release.\n"
        . " *\n"
        . " * GENERATED FILE -- do not hand-edit the `tables` block. Regenerate\n"
        . " * with:  php bin/schema-manifest.php generate <fog-web-root>\n"
        . " *\n"
        . " * The `renames` and `retired` blocks ARE maintained by hand and are\n"
        . " * preserved across regeneration.\n"
        . " *\n"
        . " * `renames`: a manifest describes an END state, so a renamed column\n"
        . " * is indistinguishable from a new one; without an entry here\n"
        . " * SchemaReconciler would add the target column empty and strand the\n"
        . " * data in the old one.\n"
        . " *\n"
        . " * `retired`: a table 1.6 dropped deliberately, so that the 1.5\n"
        . " * comparison in .githooks/pre-commit reports it as accounted for\n"
        . " * rather than as a port somebody forgot. Read by that check only --\n"
        . " * SchemaReconciler never touches it.\n"
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
        . " * @author   Tom Elliott <tommygunsster@gmail.com>\n"
        . " * @license  http://opensource.org/licenses/gpl-3.0 GPLv3\n"
        . " * @link     https://fogproject.org\n"
        . " */\n"
        . "return [\n"
        . "    'renames' => " . render($renames) . ",\n"
        . "    'retired' => " . render($retired) . ",\n"
        . "    'tables' => " . render($tables) . ",\n"
        . "];\n";
    file_put_contents($out, $php);
    printf(
        "Wrote %s: %d tables, %d renames preserved, %d retired preserved\n",
        $out,
        count($tables),
        count($renames),
        count($retired)
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
    // Tables and columns the NEW side declares it dropped on purpose. Keyed
    // lowercase so the lookup matches the comparison, which is
    // case-insensitive because MySQL's own table-name casing depends on the
    // server's filesystem.
    //
    // An entry with a `column` retires that one column and leaves the table
    // alone; without one it retires the whole table. Both exist because a
    // rebuild drops both kinds, and the alternative to declaring a dropped
    // column is a permanent difference in this output -- which trains
    // whoever reads it to skim past differences, and the next real one goes
    // with it.
    $retired = [];
    $retiredCols = [];
    foreach ((array)($b['retired'] ?? []) as $r) {
        $t = strtolower($r['table'] ?? '');
        if (!$t) {
            continue;
        }
        $c = strtolower((string)($r['column'] ?? ''));
        if ('' !== $c) {
            $retiredCols[$t . '.' . $c] = (string)($r['reason'] ?? '');
            continue;
        }
        $retired[$t] = (string)($r['reason'] ?? '');
    }

    $found = 0;
    foreach ($A as $table => $cols) {
        if (!isset($B[$table])) {
            if (isset($retired[$table])) {
                // Reported, not silenced. The point of the block is that the
                // difference is accounted for -- somebody reading the output
                // still gets told the table is gone and why.
                printf(
                    "RETIRED TABLE   %s -- %s\n",
                    $table,
                    $retired[$table] ?: 'no reason recorded'
                );
                continue;
            }
            printf("MISSING TABLE   %s\n", $table);
            $found++;
            continue;
        }
        $gone = array_diff($cols, $B[$table]);
        $added = array_diff($B[$table], $cols);
        foreach ($gone as $i => $c) {
            if (isset($retiredCols[$table . '.' . $c])) {
                // Reported, not silenced, exactly as for a retired table.
                printf(
                    "RETIRED COLUMN  %s.%s -- %s\n",
                    $table,
                    $c,
                    $retiredCols[$table . '.' . $c] ?: 'no reason recorded'
                );
                // Dropped from $gone as well, so the rename heuristic below
                // does not then read a deliberate drop plus an unrelated
                // addition as one renamed column.
                unset($gone[$i]);
                continue;
            }
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
