<?php
/**
 * The schema must SAY which columns are optional, and agree with the models.
 *
 * GH-1245, third instalment. A column declared NOT NULL with no DEFAULT is
 * only mandatory if something enforces it, and under a non-strict sql_mode
 * the server does not -- it downgrades the error to a warning and substitutes
 * an implicit zero value. For the nine years PDODB cleared sql_mode, the
 * declaration was a comment. Removing the clear turned every one of those
 * columns into a real constraint at once, which is how saving FOG settings
 * started failing with 1364.
 *
 * Schema step 348 resolved it in the direction the models already pointed:
 * every column a model does NOT list in $databaseFieldsRequired carries a
 * DEFAULT, and every column it DOES list stays bare so that omitting it is a
 * real error. This test is what keeps those two statements the same
 * statement. Without it the schema fixes today's state and nothing stops the
 * next column arriving with the ambiguity built back in.
 *
 * ONE direction is checked: a column the models treat as optional must carry
 * a DEFAULT. That is the 1364 that started this.
 *
 * The reverse -- a required column that carries a DEFAULT -- is deliberately
 * NOT a failure, because the two senses of "required" are not the same rule.
 * $databaseFieldsRequired is what isValid() refuses to save without; a bare
 * NOT NULL is what the SERVER refuses to store. A column can honestly be both
 * ORM-required and storage-defaulted, and four already are:
 * auditChange.acSubjectID and auditLog.alSubjectID default to 0 because 0 is
 * a real "no subject" (ADR 0021), multicastSessions.msSenderPID defaults to 0
 * meaning "not running", and nfsFailures.nfDateTime defaults to the current
 * timestamp. In each the default is unreachable through the ORM, which
 * refuses the write first, so it is defense in depth rather than a hole.
 * An earlier revision of this test failed on all four, which is how the
 * distinction got noticed.
 *
 * Reads commons/schema-expected.php, which is generated FROM a migrated
 * database, so it is the shipped description of what an install looks like --
 * not a restatement of the step that produced it.
 *
 * Foreign keys are treated as required whether or not a model declares them:
 * an INSERT that forgets which host a row belongs to should fail. FOG's
 * convention is that such a column is an integer whose name ends in ID, and
 * primary keys are excluded by the AUTO_INCREMENT check.
 *
 * Usage: php tests/optional-columns-carry-defaults.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__) . '/packages/web';
$manifest = require $root . '/commons/schema-expected.php';
$failures = [];
$checks = 0;

if (empty($manifest['tables'])) {
    fwrite(STDERR, "FAIL: manifest carries no tables\n");
    exit(1);
}

/*
 * Model intent: table => [column => required?]. The chain matters -- Task
 * extends TaskType, not FOGController -- so a model that declares neither
 * $databaseFields nor $databaseFieldsRequired inherits its parent's.
 */
$raw = [];
/*
 * Two roots since core moved to PSR-4: core models live under src/, and
 * lib/ still holds the plugin tree (and the two unconverted router files).
 * Both carry models, so both have to be walked.
 */
$roots = [];
foreach (['/src', '/lib'] as $sub) {
    if (is_dir($root . $sub)) {
        $roots[] = $root . $sub;
    }
}
$files = new \AppendIterator();
foreach ($roots as $dir) {
    $files->append(
        new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir))
    );
}
foreach ($files as $file) {
    if (!$file->isFile() || 'php' !== $file->getExtension()) {
        continue;
    }
    $src = (string) file_get_contents($file->getPathname());
    if (!preg_match('/class\s+\w+\s+extends\s+\w+/', $src)) {
        continue;
    }
    foreach (
        preg_split('/(?=\bclass\s+\w+\s+extends\s+)/', $src) as $chunk
    ) {
        if (!preg_match('/^class\s+(\w+)\s+extends\s+(\w+)/', $chunk, $m)) {
            continue;
        }
        preg_match("/databaseTable\s*=\s*'([^']+)'/", $chunk, $t);
        preg_match('/databaseFields\s*=\s*\[(.*?)\n    \];/s', $chunk, $f);
        preg_match(
            '/databaseFieldsRequired\s*=\s*\[(.*?)\n    \];/s',
            $chunk,
            $r
        );
        $raw[$m[1]] = [
            'parent' => $m[2],
            'table' => $t[1] ?? null,
            'fieldsSrc' => $f[1] ?? '',
            'req' => isset($r[1])
                ? (preg_match_all("/'([^']+)'/", $r[1], $rq) ? $rq[1] : [])
                : null,
        ];
    }
}

/**
 * Walks up the extends chain for a property the class itself did not set.
 *
 * @param array  $raw  every parsed class
 * @param string $cls  the class to start from
 * @param string $key  which property
 *
 * @return mixed
 */
function ocInherit($raw, $cls, $key)
{
    $seen = [];
    while (isset($raw[$cls]) && !isset($seen[$cls])) {
        $seen[$cls] = true;
        if (null !== $raw[$cls][$key] && '' !== $raw[$cls][$key]) {
            return $raw[$cls][$key];
        }
        $cls = $raw[$cls]['parent'];
    }
    return $key === 'req' ? [] : '';
}

/**
 * True when the class is a FOGController, however far down the chain.
 *
 * @param array  $raw every parsed class
 * @param string $cls the class to test
 *
 * @return bool
 */
function ocIsController($raw, $cls)
{
    $seen = [];
    while (isset($raw[$cls]) && !isset($seen[$cls])) {
        $seen[$cls] = true;
        if ('FOGController' === $raw[$cls]['parent']) {
            return true;
        }
        $cls = $raw[$cls]['parent'];
    }
    return false;
}

$intent = [];
foreach ($raw as $cls => $def) {
    if (!ocIsController($raw, $cls) || empty($def['table'])) {
        continue;
    }
    $fieldsSrc = $def['fieldsSrc'] ?: ocInherit($raw, $cls, 'fieldsSrc');
    $req = $def['req'] ?? ocInherit($raw, $cls, 'req');
    preg_match_all("/'([^']+)'\s*=>\s*'([^']+)'/", (string) $fieldsSrc, $pairs, PREG_SET_ORDER);
    $map = [];
    foreach ($pairs as $pair) {
        $map[strtolower($pair[2])] = in_array($pair[1], (array) $req, true);
    }
    $intent[strtolower($def['table'])] = $map;
}

$checks++;
if (count($intent) < 40) {
    $failures[] = 'only ' . count($intent) . ' models were parsed, so this '
        . 'would pass vacuously -- the scan did not reach the tree';
}

$optionalBare = [];
foreach ($manifest['tables'] as $table => $def) {
    $create = (string) ($def['create'] ?? '');
    foreach ((array) ($def['columns'] ?? []) as $column => $type) {
        $type = (string) $type;
        // Primary keys are the server's to fill.
        if (preg_match(
            '/`' . preg_quote($column, '/') . '`[^,]*AUTO_INCREMENT/i',
            $create
        )) {
            continue;
        }
        if (!preg_match('/\bNOT\s+NULL\b/i', $type)) {
            continue;
        }
        $hasDefault = (bool) preg_match('/\bDEFAULT\b/i', $type);
        $isFk = (bool) preg_match('/ID$/', $column)
            && (bool) preg_match('/^(tiny|small|medium|big)?int\b/i', $type);
        $declared = $intent[strtolower($table)][strtolower($column)] ?? null;
        $required = $isFk || (true === $declared);

        $checks++;
        if (!$required && !$hasDefault) {
            $optionalBare[] = "$table.$column ($type)";
        }
    }
}

if (count($optionalBare)) {
    $failures[] = count($optionalBare) . ' column(s) the models treat as '
        . 'optional are NOT NULL with no DEFAULT, so an INSERT that omits '
        . 'them is error 1364 on a strict server: '
        . implode(', ', array_slice($optionalBare, 0, 8))
        . (count($optionalBare) > 8 ? ', ...' : '');
}
if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " problem(s)):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks checks passed\n";
exit(0);
