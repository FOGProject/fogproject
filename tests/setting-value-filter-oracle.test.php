<?php
/**
 * Filtering settings by value must not confirm a credential.
 *
 * maskSensitiveSetting() strips `value` from FOG_TFTP_FTP_PASSWORD and its
 * kin, and _applySettingValueScope() already drops a sensitive row that a
 * DataTables SEARCH matched only on that stripped value. The equality form
 * of the same question arrived by a different door and was not covered:
 *
 *   GET /setting?filter=value=<guess>        -> recordsFiltered 1 or 0
 *   GET /setting/names?filter=value=<guess>  -> the row, or []
 *   GET /setting/ids?filter=value=<guess>    -> the id, or []
 *
 * The response carries no value in any of the three, and it does not need
 * to: the row's PRESENCE is the answer, and an attacker holding setting.view
 * can ask it as many times as they like. /names and /ids are the worse two,
 * because they never return a value at all, so masking is not even in play.
 *
 * The fix is per ROW, not per FIELD, matching the reasoning already written
 * on unfilterableFields(): a setting's value is ordinary configuration for
 * all but a handful of keys, and blocking the field outright would take
 * "which setting holds bzImage" away to protect four passwords. ids() is the
 * one exception and is refused instead -- it projects $getField alone, so on
 * the default /setting/ids the setting NAME is not in the result and there is
 * no row to ask isSensitiveSetting() about.
 *
 * Executable, not just a source gate: the shipped filtering logic is lifted
 * out of route.class.php and run against synthetic rows, so a change that
 * keeps the shape and breaks the behaviour still fails.
 *
 * DB-free: reads the source, executes the lifted method.
 *
 * Usage: php tests/setting-value-filter-oracle.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$webroot = dirname(__DIR__) . '/packages/web';
$routeFile = $webroot . '/src/Router/Route.php';

if (!is_readable($routeFile)) {
    fwrite(STDERR, "FAIL: cannot read $routeFile\n");
    exit(1);
}
$route = file_get_contents($routeFile);

$failures = [];
$checks = 0;

/**
 * Returns the source of a named method, signature to the following one.
 */
$bodyOf = function ($src, $needle) {
    $start = strpos($src, $needle);
    if (false === $start) {
        return null;
    }
    $next = preg_match(
        '/\n    (?:public|private|protected)[ a-z]* function /',
        $src,
        $m,
        PREG_OFFSET_CAPTURE,
        $start + strlen($needle)
    );
    return $next
        ? substr($src, $start, $m[0][1] - $start)
        : substr($src, $start);
};

// ---------------------------------------------------------------------
// 1. listem() must hand the parsed filter to the scope function. Without
//    this the function cannot see a ?filter= term at all and the whole
//    fix is inert while still looking present.
// ---------------------------------------------------------------------
$checks++;
$listem = $bodyOf($route, 'public static function listem(');
if (null === $listem) {
    $failures[] = 'listem() not found';
} elseif (!preg_match(
    '/_applySettingValueScope\(\s*\$classname,\s*[^;]*?\$whereItems/s',
    $listem
)) {
    $failures[] = 'listem() does not pass $whereItems to '
        . '_applySettingValueScope(); a ?filter= term is invisible to it';
}

// ---------------------------------------------------------------------
// 2. Lift _applySettingValueScope() and RUN it.
// ---------------------------------------------------------------------
$scope = $bodyOf($route, 'private static function _applySettingValueScope(');
if (null === $scope) {
    $failures[] = '_applySettingValueScope() not found';
} else {
    // Stub only what the method reaches out to: the predicate and the
    // payload. The filtering itself is the shipped code.
    eval(
        'class ScopeProbe {'
        . ' public static $data = [];'
        . ' public static function isSensitiveSetting($k) {'
        . '     return 1 === preg_match('
        . '         "#(PASSWORD|PASSWD|PWD|SECRET|TOKEN)#i", (string)$k'
        . '     );'
        . ' }'
        . ' public static function run($c, $v, $w = []) {'
        . '     return self::_applySettingValueScope($c, $v, $w);'
        . ' }'
        . str_replace('self::', 'static::', $scope)
        . '}'
    );

    $rows = [
        ['id' => 5, 'name' => 'FOG_TFTP_PXE_KERNEL', 'value' => 'bzImage'],
        ['id' => 3, 'name' => 'FOG_TFTP_FTP_PASSWORD'],
    ];
    $envelope = function ($rows) {
        return [
            'data' => $rows,
            'recordsTotal' => count($rows),
            'recordsFiltered' => count($rows),
        ];
    };

    // 2a. A value FILTER drops the credential row and keeps the ordinary one.
    $checks++;
    ScopeProbe::$data = $envelope($rows);
    ScopeProbe::run('setting', [], ['value' => 'whatever']);
    $names = array_column(ScopeProbe::$data['data'], 'name');
    if (in_array('FOG_TFTP_FTP_PASSWORD', $names, true)) {
        $failures[] = 'filter=value: the credential row survived -- its '
            . 'presence confirms the guess';
    }
    if (!in_array('FOG_TFTP_PXE_KERNEL', $names, true)) {
        $failures[] = 'filter=value: dropped an ordinary setting; filtering '
            . 'settings by value must keep working';
    }

    // 2b. A value filter ANDed with a name filter still drops it. Filter
    //     terms are conjunctive, so matching the name does not make the
    //     value any less load bearing -- this is where the search arm's
    //     "matched on a visible field" rescue must NOT apply.
    $checks++;
    ScopeProbe::$data = $envelope($rows);
    ScopeProbe::run(
        'setting',
        [],
        ['value' => 'whatever', 'name' => 'FOG_TFTP_FTP_PASSWORD']
    );
    if (in_array(
        'FOG_TFTP_FTP_PASSWORD',
        array_column(ScopeProbe::$data['data'], 'name'),
        true
    )) {
        $failures[] = 'filter=value&name=: the credential row survived on a '
            . 'name match; filter terms are ANDed, not ORed';
    }

    // 2c. recordsTotal is rewritten too. Leaving the SQL count behind
    //     answers the question the dropped row was dropped for.
    $checks++;
    ScopeProbe::$data = $envelope($rows);
    ScopeProbe::run('setting', [], ['value' => 'whatever']);
    if (ScopeProbe::$data['recordsTotal'] !== 1
        || ScopeProbe::$data['recordsFiltered'] !== 1
    ) {
        $failures[] = 'filter=value: counts not rewritten ('
            . ScopeProbe::$data['recordsTotal'] . '/'
            . ScopeProbe::$data['recordsFiltered'] . '), the row count leaks '
            . 'the hit';
    }

    // 2d. No value filter and no search term: a plain listing is untouched.
    $checks++;
    ScopeProbe::$data = $envelope($rows);
    ScopeProbe::run('setting', [], ['name' => 'FOG_TFTP_FTP_PASSWORD']);
    if (count(ScopeProbe::$data['data']) !== 2) {
        $failures[] = 'a listing with no value filter lost rows; every '
            . 'setting must still be listable with its value masked';
    }

    // 2e. The search arm still rescues a sensitive row matched on its name.
    //     Searching "PASSWORD" should find FOG_TFTP_FTP_PASSWORD -- the key
    //     was never the secret -- so 2b must not have broken it.
    $checks++;
    ScopeProbe::$data = $envelope($rows);
    ScopeProbe::run('setting', ['search' => ['value' => 'PASSWORD']], []);
    if (!in_array(
        'FOG_TFTP_FTP_PASSWORD',
        array_column(ScopeProbe::$data['data'], 'name'),
        true
    )) {
        $failures[] = 'search on the KEY no longer finds the credential row; '
            . 'the name was never the secret';
    }

    // 2g. A value filter BEATS the search arm's visible-field rescue.
    //     This is the case the explicit skip exists for: searching
    //     "PASSWORD" would normally keep FOG_TFTP_FTP_PASSWORD (2e), but
    //     if the same request ALSO filters value=<guess> then the row is
    //     only present because the guess was right, and saying so is the
    //     leak. Without this case the skip is over-determined -- with no
    //     search term the visible-field loop drops the row anyway -- and a
    //     regression here would go unnoticed.
    $checks++;
    ScopeProbe::$data = $envelope($rows);
    ScopeProbe::run(
        'setting',
        ['search' => ['value' => 'PASSWORD']],
        ['value' => 'whatever']
    );
    if (in_array(
        'FOG_TFTP_FTP_PASSWORD',
        array_column(ScopeProbe::$data['data'], 'name'),
        true
    )) {
        $failures[] = 'search=PASSWORD + filter=value: the credential row '
            . 'was rescued by the name match, but the value filter is what '
            . 'put it there';
    }

    // 2f. Another class is not touched.
    $checks++;
    ScopeProbe::$data = $envelope($rows);
    ScopeProbe::run('host', [], ['value' => 'whatever']);
    if (count(ScopeProbe::$data['data']) !== 2) {
        $failures[] = 'a non-setting class was filtered';
    }
}

// ---------------------------------------------------------------------
// 3. names() drops the row itself -- it returns id and name only, so
//    masking never applies and the presence of the row is the answer.
// ---------------------------------------------------------------------
$checks++;
$names = $bodyOf($route, 'public static function names(');
if (null === $names) {
    $failures[] = 'names() not found';
} else {
    if (!preg_match('/isSensitiveSetting/', $names)) {
        $failures[] = 'names() does not consult isSensitiveSetting(); '
            . '/setting/names?filter=value=<guess> is an oracle';
    }
    if (!preg_match("/array_key_exists\(\s*'value'/", $names)) {
        $failures[] = 'names() does not gate on a value filter; it must '
            . 'drop credential rows only when value was filtered on';
    }
}

// ---------------------------------------------------------------------
// 4. ids() refuses instead -- it projects $getField alone, so the setting
//    name is not in the result to test.
// ---------------------------------------------------------------------
$checks++;
$ids = $bodyOf($route, 'public static function ids(');
if (null === $ids) {
    $failures[] = 'ids() not found';
} else {
    if (!preg_match(
        "/'setting' === \\\$classname\s*\n\s*&& is_array\(\\\$whereItems\)"
        . "\s*\n\s*&& array_key_exists\(\s*'value'/",
        $ids
    )) {
        $failures[] = 'ids() does not refuse a setting filter on value; '
            . '/setting/ids?filter=value=<guess> is an oracle';
    }
    // sendResponse() exits, and a daemon that exits is a restart loop.
    if (!preg_match("/'cli' === PHP_SAPI/", $ids)) {
        $failures[] = 'ids() refusal is not SAPI-gated';
    }
}

if (count($failures) > 0) {
    fwrite(STDERR, "FAIL (" . count($failures) . " of $checks checks)\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}
fwrite(STDOUT, "PASS: $checks checks\n");
exit(0);
