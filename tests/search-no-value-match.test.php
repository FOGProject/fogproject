<?php
/**
 * Universal search may match a setting's value, but must not confirm a
 * credential one.
 *
 * Searching settings by VALUE is the point of searching settings: "bzImage"
 * finds FOG_TFTP_PXE_KERNEL, and no key-only search ever can. So the value
 * clause stays.
 *
 * globalSettings is also where FOG keeps its passwords, and
 * Route::maskSensitiveSetting() strips their value from API reads. A hit on
 * the value would answer the question that masking refuses -- and answer it
 * repeatedly, a few characters at a time -- even though the value itself
 * never appears in the response. So unisearch() drops a credential row that
 * matched ONLY on its value, using Route::isSensitiveSetting() itself.
 *
 * The invariant is the PAIR, which is why this test is conditional rather
 * than a ban on either half:
 *
 *   matching settingValue  =>  calling isSensitiveSetting()
 *
 * Removing the drop while keeping the match is the regression that matters,
 * and it is an easy one to make -- the drop lives thirty lines below the
 * clause it guards, in the result loop, because SQL cannot report which OR
 * arm matched. Removing both is merely a feature regression, and the second
 * check names it so nobody re-adds the clause without the guard.
 *
 * Scoped to unisearch(), not the whole file: settingValue is a real column
 * and legitimately appears in reads elsewhere.
 *
 * DB-free: reads the source.
 *
 * Usage: php tests/search-no-value-match.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$routeFile = dirname(__DIR__)
    . '/packages/web/lib/router/route.class.php';

if (!is_readable($routeFile)) {
    fwrite(STDERR, "FAIL: cannot read $routeFile\n");
    exit(1);
}

$src = file_get_contents($routeFile);

// The body of unisearch(), from its signature to the next method.
$start = strpos($src, 'public static function unisearch(');
if (false === $start) {
    fwrite(STDERR, "FAIL: could not locate unisearch() in route\n");
    exit(1);
}
$end = strpos($src, 'public static function search(', $start);
if (false === $end) {
    fwrite(STDERR, "FAIL: could not find the end of unisearch()\n");
    exit(1);
}
$body = substr($src, $start, $end - $start);

// Comments explain both halves, so they must not count as either half.
$code = preg_replace('#//[^\n]*#', '', $body);

$failures = [];
$checks = 0;

$matchesValue = (bool)preg_match('/settingValue/i', $code);
$guards = (bool)preg_match('/isSensitiveSetting\s*\(/', $code);

$checks++;
if ($matchesValue && !$guards) {
    $failures[] = 'unisearch() matches settingValue without calling '
        . 'isSensitiveSetting(). Matching the value of a credential setting '
        . 'confirms a substring of a value the API deliberately masks, so '
        . 'the value clause may only ship alongside the drop that follows it '
        . 'in the result loop.';
}

$checks++;
if (!$matchesValue) {
    $failures[] = 'unisearch() no longer matches settingValue, so a setting '
        . 'can only be found by its key -- "bzImage" stops finding '
        . 'FOG_TFTP_PXE_KERNEL. If that is deliberate, delete this check and '
        . 'say why; do not just let it fail.';
}

/*
 * The grid path needs the same treatment and cannot borrow the same code:
 * unisearch() has the term and the row in one loop, while a DataTables list
 * builds its payload in FOGManagerController::complex() and only gets it
 * back afterwards. So Route::_applySettingValueScope() does it on the
 * returned rows, and these pin the three properties that make it correct
 * rather than merely present.
 */
$scope = null;
$scopeStart = strpos($src, 'private static function _applySettingValueScope(');
if (false === $scopeStart) {
    $checks++;
    $failures[] = 'Route::_applySettingValueScope() is gone, so a DataTables '
        . 'search on /fog/setting/list can match a credential value again';
} else {
    $scopeEnd = strpos($src, "\n    private static function ", $scopeStart + 10);
    $scope = false === $scopeEnd
        ? substr($src, $scopeStart)
        : substr($src, $scopeStart, $scopeEnd - $scopeStart);

    $checks++;
    if (false === strpos($scope, 'isSensitiveSetting(')) {
        $failures[] = '_applySettingValueScope() no longer calls '
            . 'isSensitiveSetting(), so it is deciding what counts as a '
            . 'credential from some second copy of the rule';
    }

    // No search term at all must be a no-op: a plain listing has to keep
    // returning every setting, values masked, as it always has.
    //
    // Matched loosely on purpose. The guard has since grown a second arm
    // for ?filter=value= (see setting-value-filter-oracle.test.php), which
    // is a NEW reason to act, not a lost reason to bail -- pinning the
    // condition as one literal line failed on the extra clause while the
    // property it guards was intact.
    $checks++;
    if (!preg_match('/if \(!count\(\$terms\)[^)]*\) \{/', $scope)) {
        $failures[] = '_applySettingValueScope() lost its early return for a '
            . 'request with no search terms, so a plain settings list would '
            . 'start hiding credential rows entirely';
    }

    // The count is the leak. Rewriting recordsFiltered alone leaves
    // recordsTotal answering the question the dropped row was dropped for.
    $checks++;
    if (false === strpos($scope, "recordsTotal")
        || false === strpos($scope, "recordsFiltered")
    ) {
        $failures[] = '_applySettingValueScope() does not rewrite BOTH '
            . 'recordsFiltered and recordsTotal; a stale total reports the '
            . 'dropped row and defeats the drop';
    }
}

$checks++;
$listem = strpos($src, 'public static function listem(');
$listemEnd = false === $listem
    ? false
    : strpos($src, "\n    public static function ", $listem + 10);
$listemBody = false === $listem
    ? ''
    : substr($src, $listem, ($listemEnd ?: strlen($src)) - $listem);
if (false === strpos($listemBody, '_applySettingValueScope(')) {
    $failures[] = 'listem() no longer calls _applySettingValueScope(), so the '
        . 'helper exists but nothing runs it';
}

if (count($failures)) {
    fwrite(STDERR, 'FAIL (' . count($failures) . " of $checks):\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok  $checks setting value-match checks\n";
exit(0);
