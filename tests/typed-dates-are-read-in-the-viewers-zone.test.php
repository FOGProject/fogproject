<?php
/**
 * A datetime somebody TYPED is in their zone, not the install's.
 *
 * niceDate() reads a value in the STORAGE zone. That is right for a value
 * that came out of a column and wrong for one that came off a form: the
 * viewer typed it while looking at a page rendered in THEIR zone, so reading
 * it as storage silently moves it by the offset between the two.
 *
 * It bites only the users who have set a display preference, which is the
 * worst possible distribution for a bug -- most people never see it, the
 * ones who do get a task scheduled hours from when they asked, and there is
 * nothing on screen or in a log to say why.
 *
 * The companion gate is stored-times-use-the-storage-zone.test.php, which
 * covers the other direction: a value on its way IN must not be formatted
 * through the viewer's zone.
 *
 * Usage: php tests/typed-dates-are-read-in-the-viewers-zone.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

$fails = [];

/**
 * One named method's body.
 *
 * @param string $src  the file
 * @param string $name the method
 *
 * @return string
 */
function vz_body($src, $name)
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

// --- the helper itself ---------------------------------------------------

$base = file_get_contents($web . '/src/Base/FOGBase.php');
$viewer = vz_body($base, 'viewerDate');
if ('' === $viewer) {
    $fails[] = 'FOGBase::viewerDate() not found';
} elseif (false === strpos($viewer, 'self::displayToStorage(')) {
    $fails[] = 'viewerDate() no longer converts from the display zone, which'
        . ' makes it an alias for niceDate() and the distinction disappears';
}

// --- every form-supplied datetime goes through it ------------------------

// Each is a value a person typed into the web UI. The pairing is what the
// gate is really about: the SOURCE is request input, and the READER must be
// viewerDate(). Anchored on the whole statement, so a changed reader shows
// up as a failure rather than as a grep that still matches somewhere else.
$typed = [
    'src/Base/FOGPagePost.php' => [
        'the scheduled start time of a task' =>
            '$scheduleDeployTime = self::viewerDate($scheduleSingleTime);',
    ],
    'src/Audit/ReportWindow.php' => [
        'the end of a report window' => ': self::viewerDate($given[\'end\']);',
        'the start of a report window' =>
            ': self::viewerDate($given[\'start\']);',
    ],
    'lib/pages/hostmanagement.page.php' => [
        'the Secure Boot enrollment date' =>
            '$sbEnrolled = self::viewerDate($sbEnrolled)',
    ],
];
foreach ($typed as $file => $cases) {
    $src = file_get_contents($web . '/' . $file);
    foreach ($cases as $what => $needle) {
        if (false === strpos($src, $needle)) {
            $fails[] = "$what ($file) is no longer read with viewerDate(), so"
                . ' a viewer with a display zone gets a value they did not'
                . ' type';
        }
    }
}

// --- and the machine-supplied one deliberately does NOT ------------------

// The fog-client posts this. There is no viewer in a client check-in, so
// converting it from a display zone would be inventing an offset out of
// whoever happened to be signed in somewhere else.
$track = file_get_contents($web . '/src/Client/UserTrack.php');
if (false !== strpos($track, 'viewerDate(')) {
    $fails[] = 'UserTrack reads the CLIENT-supplied date through'
        . ' viewerDate() -- a client check-in has no viewer, and the zone it'
        . ' arrives in is a separate open question';
}

// --- report ---------------------------------------------------------------

if ($fails) {
    fwrite(STDERR, "FAIL typed-dates-are-read-in-the-viewers-zone\n");
    foreach ($fails as $fail) {
        fwrite(STDERR, '  - ' . $fail . "\n");
    }
    exit(1);
}

echo "PASS typed-dates-are-read-in-the-viewers-zone\n";
exit(0);
