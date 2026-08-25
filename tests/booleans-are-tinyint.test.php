<?php
/**
 * Two-state columns are tinyint(1), and the migration that made them so does
 * not convert an ENUM by index.
 *
 * ADR 0028. FOG spelled its booleans enum('0','1') for years, which put a trap
 * in every one of them: an integer written to an ENUM is a member INDEX, so
 * `1` means the member '0' -- FALSE -- and `0` is the error value, refused
 * under STRICT_TRANS_TABLES. The tree only survived that because PDODB binds
 * everything as PDO::PARAM_STR.
 *
 * WHAT THIS PINS, and why each half matters:
 *
 *   1. The manifest carries no enum('0','1') column. That is the end state,
 *      and it is what a new column added as an enum would break.
 *
 *   2. Step 368 converts through VARCHAR before TINYINT. This is the
 *      load-bearing one. A direct `ALTER ... MODIFY TINYINT(1)` converts an
 *      ENUM BY INDEX, not by label -- '0' becomes 1 and '1' becomes 2, both
 *      truthy, silently, on every upgrading server. Anyone "simplifying"
 *      those three statements into one turns an upgrade into a data
 *      corruption with no error anywhere. Measured on MariaDB 11.8; the live
 *      proof is background_scripts/prove_boolean_column_writes.php's sibling
 *      run in the pull request.
 *
 *   3. The UPDATE between the two ALTERs survives. A row still holding the
 *      ENUM error value (GH-1245's legacy) reaches the varchar stage as '',
 *      which tinyint refuses -- so removing it turns the step into a hard
 *      failure on exactly the servers that most need it.
 *
 * It does NOT pin today's column list. A boolean added later is covered by
 * check 1 without anyone editing this file.
 *
 * DB-free: reads the manifest and the schema source.
 *
 * Usage: php tests/booleans-are-tinyint.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';
$failures = [];
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $what     what is being asserted
 * @param bool   $ok       whether it holds
 * @param array  $failures collected failures
 * @param int    $checks   running count
 *
 * @return void
 */
function btCheck($what, $ok, &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

// --- 1. no two-state ENUM survives in the manifest -------------------------
$manifest = include $web . '/commons/schema-expected.php';
$tables = isset($manifest['tables']) && is_array($manifest['tables'])
    ? $manifest['tables']
    : [];
btCheck('the manifest has tables', count($tables) > 0, $failures, $checks);

foreach ($tables as $table => $def) {
    if (!isset($def['columns']) || !is_array($def['columns'])) {
        continue;
    }
    foreach ($def['columns'] as $column => $type) {
        btCheck(
            sprintf(
                '%s.%s is %s -- a two-state column is tinyint(1) (ADR 0028); '
                . 'an integer written to enum(\'0\',\'1\') is a member index, '
                . 'so 1 stores FALSE',
                $table,
                $column,
                trim((string) $type)
            ),
            !preg_match("/^enum\('0','1'\)/i", trim((string) $type)),
            $failures,
            $checks
        );
    }
    if (isset($def['create'])) {
        btCheck(
            sprintf('%s\'s CREATE carries no enum(\'0\',\'1\') column', $table),
            !preg_match("/enum\('0','1'\)/i", (string) $def['create']),
            $failures,
            $checks
        );
    }
}

// --- 2 & 3. the conversion keeps its three statements ----------------------
// The SQL lives in Schema::enumToTinyint(), shared so that core step 368 and
// the three bundled plugins that convert their own columns cannot drift.
$schemaClass = (string) file_get_contents($web . '/lib/fog/schema.class.php');
$method = '';
if (preg_match(
    '#public static function enumToTinyint\(array \$map\)\s*\{(.*?)\n    \}#s',
    $schemaClass,
    $m
)) {
    $method = $m[1];
}
btCheck(
    'Schema::enumToTinyint() exists -- core and the bundled plugins share one '
    . 'implementation of the conversion',
    $method !== '',
    $failures,
    $checks
);

$varcharAt = strpos($method, 'VARCHAR(1)');
$updateAt = strpos($method, 'UPDATE');
$tinyintAt = strpos($method, 'TINYINT(1)');

btCheck(
    'enumToTinyint() converts through VARCHAR(1) first -- a direct ALTER to '
    . "TINYINT converts an ENUM by INDEX, turning every '0' into 1 and every "
    . "'1' into 2, silently, on every upgrading server",
    $varcharAt !== false,
    $failures,
    $checks
);
btCheck(
    'enumToTinyint() still repairs stragglers between the two ALTERs -- a row '
    . "holding the ENUM error value arrives as '' and tinyint refuses it",
    $updateAt !== false,
    $failures,
    $checks
);
btCheck(
    'enumToTinyint() lands on TINYINT(1)',
    $tinyintAt !== false,
    $failures,
    $checks
);
btCheck(
    'enumToTinyint() runs VARCHAR -> UPDATE -> TINYINT in that order',
    $varcharAt !== false && $updateAt !== false && $tinyintAt !== false
        && $varcharAt < $updateAt && $updateAt < $tinyintAt,
    $failures,
    $checks
);
btCheck(
    'enumToTinyint() checks the live column type before touching it, so a '
    . 're-run is a read and a column already changed is left alone',
    false !== strpos($method, "enum\\\\('0','1'\\\\)"),
    $failures,
    $checks
);
btCheck(
    'enumToTinyint() carries the column\'s nullability across rather than '
    . 'assuming NOT NULL -- LDAPServers.lsAllowAPI is nullable, and rewriting '
    . 'that would be a behaviour change smuggled in by a type change',
    false !== strpos($method, 'IS_NULLABLE'),
    $failures,
    $checks
);

// --- 4. step 368 is what runs it for the core tables -----------------------
$schema = (string) file_get_contents($web . '/commons/schema.php');
$step = '';
if (preg_match('#\n// 368\n\$this->schema\[\] = \[(.*?)\n\];#s', $schema, $m)) {
    $step = $m[1];
}
btCheck('schema step 368 was found', $step !== '', $failures, $checks);
btCheck(
    'schema step 368 converts the core columns through the shared helper',
    false !== strpos(preg_replace('#^\s*//.*$#m', '', $step), 'Schema::enumToTinyint'),
    $failures,
    $checks
);

printf("%d checks\n", $checks);
if (count($failures) > 0) {
    foreach ($failures as $f) {
        printf("  FAIL  %s\n", $f);
    }
    printf("%d failed\n", count($failures));
    exit(1);
}
printf("all passed\n");
exit(0);
