<?php
/**
 * A PHP boolean must never reach a column as ''.
 *
 * Forum topic 18227: creating a snapin on 1.6.0-beta.4020 answered
 * "SQLSTATE[01000]: Warning: 1265 Data truncated for column 'sShutdown' at
 * row 1". The snapin pages have always written that column as
 *
 *     ->set('shutdown', $action == 'shutdown')
 *
 * -- an ordinary comparison, so the value is a real PHP boolean. Every value
 * FOG binds goes out as PDO::PARAM_STR, and (string)false is ''. Into
 * `snapins`.`sShutdown`, an enum('0','1'), that is error 1265 on any server
 * with STRICT_TRANS_TABLES.
 *
 * It is GH-1245 arriving by a different door. save()'s emptyValueFor() only
 * recognizes null and '' as empty, so a boolean walks past it untouched; and
 * the two builders in FOGManagerController called trim() first, which casts
 * before it trims, so false was already '' by the time it reached the binder.
 * The manager path is the one that ends every imaging task -- TaskQueue does
 * ->set('tokenlock', false) and hands it to HostManager::update(), against
 * `hosts`.`hostInfoLock`, a tinyint(1).
 *
 * What this pins is the MECHANISM, not today's call sites. Listing the
 * ->set() calls that currently pass a boolean would go green the moment
 * someone wrote another one, which is precisely how this arrived.
 *
 *   1. PDODB::_bind() normalizes a boolean, as a STRING, before binding.
 *   2. It does not reach for PDO::PARAM_BOOL/PARAM_INT to do it: bound as an
 *      integer, 0 against an ENUM is an *index*, and index 0 is the error
 *      value -- the same trap Schema::defaultLiteral() exists for.
 *   3. Neither builder in FOGManagerController flattens a value with a bare
 *      trim() on its way into a bind array.
 *   4. FOGController::save() still guards its own trim with is_string(), so
 *      the boolean survives as far as the binder.
 *
 * DB-free: this reads the source, like insertbatch-required-columns. The
 * behavior is proved against a live strict server, through the real ORM,
 * by background_scripts/prove_boolean_column_writes.php.
 *
 * Usage: php tests/booleans-store-as-zero-or-one.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

/**
 * Records one assertion.
 *
 * @param string $what      what is being asserted
 * @param bool   $ok        whether it holds
 * @param array  $failures  collected failures
 * @param int    $checks    running count
 *
 * @return void
 */
function bcheck($what, $ok, &$failures, &$checks)
{
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
}

/**
 * Source with comments removed, so a commented-out line cannot satisfy a
 * check and a sentence in a docblock cannot fail one.
 *
 * @param string $src the file's source
 *
 * @return string
 */
function bStripComments($src)
{
    $clean = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)
            && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)
        ) {
            continue;
        }
        $clean .= is_array($token) ? $token[1] : $token;
    }
    return $clean;
}

$pdoSrc = bStripComments(
    (string) file_get_contents("$root/packages/web/src/Db/PDODB.php")
);
$manSrc = bStripComments(
    (string) file_get_contents(
        "$root/packages/web/src/Base/FOGManagerController.php"
    )
);
$ctlSrc = bStripComments(
    (string) file_get_contents(
        "$root/packages/web/src/Base/FOGController.php"
    )
);

// --- 1. the binder normalizes, and does it before bindValue() -------------
$bindBody = '';
if (preg_match(
    '#private static function _bind\([^)]*\)\s*\{(.*?)\n    \}#s',
    $pdoSrc,
    $m
)) {
    $bindBody = $m[1];
}
bcheck('PDODB::_bind() was found', $bindBody !== '', $failures, $checks);
bcheck(
    'PDODB::_bind() tests the value with is_bool()',
    (bool) preg_match('#is_bool\(\$value\)#', $bindBody),
    $failures,
    $checks
);
bcheck(
    "PDODB::_bind() maps a boolean onto the strings '1' and '0'",
    (bool) preg_match(
        "#\\\$value\s*=\s*\\\$value\s*\?\s*'1'\s*:\s*'0'#",
        $bindBody
    ),
    $failures,
    $checks
);
$boolPos = strpos($bindBody, 'is_bool($value)');
$callPos = strpos($bindBody, 'bindValue(');
bcheck(
    'PDODB::_bind() normalizes BEFORE it binds',
    $boolPos !== false && $callPos !== false && $boolPos < $callPos,
    $failures,
    $checks
);

// --- 2. not as an integer -------------------------------------------------
// An ENUM column takes an integer as a MEMBER INDEX, and index 0 is the
// error value, so PARAM_BOOL/PARAM_INT would store the very thing this is
// meant to stop -- silently, because that write succeeds.
foreach (['PARAM_BOOL', 'PARAM_INT'] as $wrong) {
    bcheck(
        "PDODB::_bind() does not reach for PDO::$wrong",
        strpos($bindBody, $wrong) === false,
        $failures,
        $checks
    );
}

// --- 3. neither builder flattens a value with a bare trim() ---------------
// $field/$key are column NAMES and are still trimmed directly; only the
// values going into a bind array are at issue here.
foreach (['$val', '$value'] as $var) {
    bcheck(
        "FOGManagerController does not assign trim($var) directly",
        !preg_match(
            '#' . preg_quote($var, '#') . '\s*=\s*trim\(#',
            $manSrc
        ),
        $failures,
        $checks
    );
}

// --- 4. and the helper it uses instead keeps both non-strings intact ------
$helper = '';
if (preg_match(
    '#private static function _trimValue\(\$value\)\s*\{.*?\n    \}#s',
    $manSrc,
    $m
)) {
    $helper = $m[0];
}
bcheck(
    'FOGManagerController::_trimValue() was found',
    $helper !== '',
    $failures,
    $checks
);
if ($helper !== '') {
    // Run the shipped method rather than restating its rule.
    eval('class BtHelper { ' . str_replace('private static', 'public static', $helper) . ' }');
    $cases = [
        'null stays null' => [null, null],
        'false stays false' => [false, false],
        'true stays true' => [true, true],
        'a string is still trimmed' => ['  x  ', 'x'],
        'an int stays an int' => [0, 0],
        // A nested IN () list arrives here as an array, and trim() on one is
        // a TypeError -- fatal, not a warning, on PHP 8.
        'an array is handed back untouched' => [['a'], ['a']],
    ];
    foreach ($cases as $what => $case) {
        list($in, $want) = $case;
        $got = BtHelper::_trimValue($in);
        bcheck(
            "_trimValue(): $what",
            $got === $want,
            $failures,
            $checks
        );
    }
}

// --- 5. save() must not flatten it either --------------------------------
bcheck(
    'FOGController::save() guards its trim with is_string()',
    (bool) preg_match(
        '#is_string\(\$val\)\s*\)\s*\{\s*\$val\s*=\s*trim\(\$val\);#',
        $ctlSrc
    ),
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
