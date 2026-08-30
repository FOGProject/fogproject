<?php
/**
 * A stored timestamp is in the INSTALL's zone. A displayed one is in the
 * VIEWER's. Confusing the two is silent and it corrupts data.
 *
 * The two were the same function until there was a display zone at all, so
 * every write site in this codebase was written against formatTime() and was
 * harmless right up until it was not. Two things went wrong the moment a
 * display zone existed:
 *
 *  1. formatTime() converts to the VIEWER's zone. Used to produce a value
 *     that is then STORED -- which is what FOGController::save() does for
 *     every model's createdTime -- it makes a shared row's timestamp depend
 *     on who happened to be signed in when it was written.
 *  2. displayTimeZone() cached its answer on first call, and LoadGlobals
 *     formats a date BEFORE setEnv() loads FOG_TZ_INFO. So the cache was
 *     primed with storageTimeZone()'s own UTC fallback and the whole request
 *     ran in UTC whatever the setting said. On the lab server that put 676
 *     audit rows five hours ahead of the history rows beside them, with
 *     nothing logged and nothing on screen to say so.
 *
 * Usage: php tests/stored-times-use-the-storage-zone.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$fails = [];

// --- 1. no write site formats 'now' through the display side -------------

// This exact spelling is always a value on its way into a datetime column;
// nothing puts 'Y-m-d H:i:s' on a page. The Ymd_His ones are filenames and
// are deliberately left alone -- a backup named in the viewer's zone is not
// a stored timestamp.
$hits = [];
$scanned = 0;
$rii = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($web, \FilesystemIterator::SKIP_DOTS)
);
foreach ($rii as $file) {
    $path = $file->getPathname();
    if ('php' !== strtolower($file->getExtension())
        || false !== strpos($path, '/vendor/')
    ) {
        continue;
    }
    $scanned++;
    $src = file_get_contents($path);
    if (false !== strpos($src, "formatTime('now', 'Y-m-d H:i:s')")) {
        $hits[] = str_replace($root . '/', '', $path);
    }
}
if ($hits) {
    $fails[] = 'these format the current time through the DISPLAY zone and'
        . ' then store it -- use storageNow(): ' . implode(', ', $hits);
}
// Below this and the scan is not scanning anything, so a clean result above
// would mean nothing at all.
if ($scanned < 400) {
    $fails[] = "the scan reached only $scanned php files; the check above"
        . ' proves nothing';
}

$base = file_get_contents($web . '/src/Base/FOGBase.php');
$controller = file_get_contents($web . '/src/Base/FOGController.php');

/**
 * One named method's body.
 *
 * @param string $src  the file
 * @param string $name the method
 *
 * @return string
 */
function sz_body($src, $name)
{
    if (!preg_match(
        '/function ' . preg_quote($name, '/') . '\(.*?\n    \}/s',
        $src,
        $m
    )) {
        return '';
    }

    return $m[0];
}

// --- 2. the storage clock really is the storage clock --------------------

$now = sz_body($base, 'storageNow');
if ('' === $now) {
    $fails[] = 'FOGBase::storageNow() not found';
} else {
    if (false === strpos($now, "niceDate('now')")) {
        $fails[] = 'storageNow() no longer reads the clock through'
            . ' niceDate(), which is the only thing that uses the STORAGE'
            . ' zone';
    }
    if (false !== strpos($now, 'toDisplay')
        || false !== strpos($now, 'displayTimeZone')
    ) {
        $fails[] = 'storageNow() reaches the display zone, which is exactly'
            . ' what it exists to avoid';
    }
}

// --- 3. the display zone is not cached before it is knowable -------------

$display = sz_body($base, 'displayTimeZone');
if ('' === $display) {
    $fails[] = 'FOGBase::displayTimeZone() not found';
} else {
    if (!preg_match(
        '/if \(empty\(self::\$TimeZone\).*?\) \{\s*\n\s*return \$storage;/s',
        $display
    )) {
        $fails[] = 'displayTimeZone() no longer returns WITHOUT caching while'
            . ' FOG_TZ_INFO is still unloaded -- caching there pins the whole'
            . ' request to the UTC fallback';
    }
    // The guard is only worth anything if it comes before the assignment.
    $guard = strpos($display, 'empty(self::$TimeZone)');
    $cache = strpos($display, 'self::$_displayTimeZone = $storage');
    if (false === $guard || false === $cache || $guard > $cache) {
        $fails[] = 'displayTimeZone() caches before it checks whether'
            . ' FOG_TZ_INFO has been loaded';
    }
}

// --- 4. the auto-filled createdTime is a stored value --------------------

$save = sz_body($controller, 'save');
if ('' === $save) {
    $fails[] = 'FOGController::save() not found';
} elseif (!preg_match(
    "/case 'createdTime':.*?self::storageNow\(\)/s",
    $save
)) {
    $fails[] = "FOGController::save()'s createdTime auto-fill no longer uses"
        . ' storageNow(), so every model\'s created date follows whoever'
        . ' happened to write the row';
}

// --- report ---------------------------------------------------------------

if ($fails) {
    fwrite(STDERR, "FAIL stored-times-use-the-storage-zone\n");
    foreach ($fails as $fail) {
        fwrite(STDERR, '  - ' . $fail . "\n");
    }
    exit(1);
}

echo "PASS stored-times-use-the-storage-zone\n";
exit(0);
