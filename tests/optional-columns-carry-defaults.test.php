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

/*
 * The placement-intent table is a deliberately raw-SQL store, not a
 * FOGController model, so the model scan above cannot infer its required
 * fields. Keep this one explicit storage contract tied to the product's
 * prepare() INSERT column list and the generated schema manifest. This is a
 * specialized durable-record contract; it does not exempt arbitrary
 * non-model tables or relax the schema's NOT NULL requirements.
 */
$intentRequiredColumns = [
    'dpiguid', 'dpisid', 'dpihostid', 'dpigeneration', 'dpistatus',
    'dpipayload', 'dpihash', 'dpiupdated',
];
$storageRequired = [
    'directoryplacementintent' => array_fill_keys($intentRequiredColumns, true),
];
$intentTable = $manifest['tables']['directoryPlacementIntent'] ?? null;
$intentExpectedColumns = [
    'dpiGUID', 'dpiSID', 'dpiHostID', 'dpiGeneration', 'dpiStatus',
    'dpiPayload', 'dpiHash', 'dpiResult', 'dpiUpdated',
];
$intentModelPath = $root . '/src/Agent/DirectoryPlacementIntent.php';
$intentModelSource = is_file($intentModelPath)
    ? (string)file_get_contents($intentModelPath)
    : '';
$insertColumns = [];
if (preg_match(
    '/\$db->prepare\(\s*\'INSERT INTO directoryPlacementIntent\s*\(([^)]*)\)\s*VALUES/s',
    $intentModelSource,
    $insertMatch
)) {
    $insertColumns = array_map('strtolower', array_map('trim', explode(',', $insertMatch[1])));
}
$checks++;
if (!is_array($intentTable)
    || array_keys((array)($intentTable['columns'] ?? [])) !== $intentExpectedColumns
    || $insertColumns !== $intentRequiredColumns) {
    $failures[] = 'directoryPlacementIntent storage contract must match the actual '
        . 'DirectoryPlacementIntent::prepare() INSERT and schema manifest columns';
} else {
    foreach ($intentRequiredColumns as $column) {
        $name = array_search($column, array_map('strtolower', array_keys($intentTable['columns'])), true);
        $definition = false === $name ? '' : (string)$intentTable['columns'][array_keys($intentTable['columns'])[$name]];
        if (!preg_match('/\bNOT\s+NULL\b/i', $definition)
            || preg_match('/\bDEFAULT\b/i', $definition)) {
            $failures[] = 'directoryPlacementIntent.' . $column
                . ' must remain storage-required (NOT NULL without DEFAULT)';
        }
    }
    if (preg_match('/\bNOT\s+NULL\b/i', (string)$intentTable['columns']['dpiResult'])) {
        $failures[] = 'directoryPlacementIntent.dpiResult must remain nullable';
    }
}

// When the PHP matrix provides its isolated audit DB, prove the storage
// contract with a real strict-mode omission. Use a temporary copy of the
// generated table so the probe cannot leave rows or schema changes behind.
if (getenv('MJ1_DB_DSN')) {
    if (getenv('MJ1_DB_ALLOW_RESET') !== '1') {
        $failures[] = 'directoryPlacementIntent strict-mode probe requires the explicit scratch DB opt-in';
    } else {
        try {
            $probeDb = new \PDO(
                getenv('MJ1_DB_DSN'),
                getenv('MJ1_DB_USER') ?: 'root',
                getenv('MJ1_DB_PASSWORD') ?: '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $probeName = (string)$probeDb->query('SELECT DATABASE()')->fetchColumn();
            if (!preg_match('/\Afog_mj1(?:_[A-Za-z0-9_]+)?\z/', $probeName)) {
                throw new \RuntimeException('strict-mode probe requires a fog_mj1 scratch database');
            }
            if (!is_array($intentTable)) {
                throw new \RuntimeException('directoryPlacementIntent manifest definition is missing');
            }
            $temporaryCreate = preg_replace(
                '/^CREATE TABLE IF NOT EXISTS `directoryPlacementIntent`/i',
                'CREATE TEMPORARY TABLE `oc_directoryPlacementIntent_probe`',
                (string)$intentTable['create'],
                1,
                $createCount
            );
            if (1 !== $createCount) {
                throw new \RuntimeException('could not derive strict-mode probe table from the schema manifest');
            }
            $oldSqlMode = (string)$probeDb->query('SELECT @@SESSION.sql_mode')->fetchColumn();
            $probeDb->exec($temporaryCreate);
            try {
                $probeColumns = $probeDb->query(
                    'SHOW COLUMNS FROM `oc_directoryPlacementIntent_probe`'
                )->fetchAll(\PDO::FETCH_ASSOC);
                if (array_column($probeColumns, 'Field') !== $intentExpectedColumns) {
                    $failures[] = 'strict-mode probe table columns differ from the schema manifest';
                }
                foreach ($intentRequiredColumns as $requiredColumn) {
                    $columnIndex = array_search(
                        $requiredColumn,
                        array_map('strtolower', array_column($probeColumns, 'Field')),
                        true
                    );
                    if (false === $columnIndex || 'NO' !== $probeColumns[$columnIndex]['Null']
                        || null !== $probeColumns[$columnIndex]['Default']) {
                        $failures[] = 'actual directoryPlacementIntent column must be NOT NULL without DEFAULT: '
                            . $requiredColumn;
                    }
                }
                $resultIndex = array_search('dpiResult', array_column($probeColumns, 'Field'), true);
                if (false === $resultIndex || 'YES' !== $probeColumns[$resultIndex]['Null']) {
                    $failures[] = 'actual directoryPlacementIntent.dpiResult column must remain nullable';
                }

                $probeDb->exec("SET SESSION sql_mode='STRICT_ALL_TABLES'");
                $omissionRejected = false;
                try {
                    $probe = $probeDb->prepare(
                        'INSERT INTO `oc_directoryPlacementIntent_probe` '
                        . '(dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash) '
                        . 'VALUES (?,?,?,?,?,?,?)'
                    );
                    $payload = '{}';
                    $probe->execute([
                        random_bytes(16), 'S-1-5-21-1', 1, 1, 'PREPARED',
                        $payload, hash('sha256', $payload),
                    ]);
                } catch (\PDOException $e) {
                    $omissionRejected = isset($e->errorInfo[1])
                        && (int)$e->errorInfo[1] === 1364;
                }
                if (!$omissionRejected) {
                    $failures[] = 'strict SQL mode accepted an INSERT omitting directoryPlacementIntent.dpiUpdated';
                }
            } finally {
                $probeDb->exec('SET SESSION sql_mode=' . $probeDb->quote($oldSqlMode));
                $probeDb->exec('DROP TEMPORARY TABLE IF EXISTS `oc_directoryPlacementIntent_probe`');
            }
            $checks++;
        } catch (\Throwable $e) {
            $failures[] = 'directoryPlacementIntent strict-mode omission probe failed: ' . $e->getMessage();
        }
    }
} else {
    echo "PENDING: directoryPlacementIntent strict-mode omission probe requires MJ1_DB_DSN\n";
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
        $explicitStorageRequired = $storageRequired[strtolower($table)][strtolower($column)] ?? false;
        $required = $isFk || (true === $declared) || (true === $explicitStorageRequired);

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
