<?php
/**
 * FOG's service-log lines should render in the viewer's display zone, not
 * whatever zone the daemon happened to write them in.
 *
 * Service_Log_message() (packages/service/lib/service_lib.php) stamps every
 * service-log line via FOGCore::formatTime('now', 'm-d-y g:i:s a') with no
 * signed-in user to convert for, so it always lands in the storage zone --
 * UTC once the storage boundary is crossed. The log viewer (status/
 * logtoview.php) is read by a signed-in, authenticated request, so it is the
 * one place that CAN know the viewer's own display zone -- and re-labeling
 * the stamp there, rather than at write time, is what lets the exact same
 * on-disk bytes read correctly for two viewers in two different zones.
 *
 * Apache/nginx/syslog/etc. lines never match this stamp shape and so must be
 * left untouched -- their software chose whatever zone it carries, which
 * this code cannot know and must not guess at.
 *
 * This is a static check (logtoview.php boots the whole app on require, so
 * it cannot be included directly from a standalone test) plus a live replay
 * of the exact regex/format pair the source uses, against the exact string
 * Service_Log_message() produces, to catch a parsing regression the static
 * check alone would miss.
 *
 * Usage: php tests/logviewer-service-log-timezone.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$repo = dirname(__DIR__);
$src = (string)file_get_contents($repo . '/packages/web/status/logtoview.php');

// [passed, what it means]
$results = [];

$results[] = [
    (bool)preg_match('#function\s+convertServiceLogTimestamps\s*\(#', $src),
    'logtoview.php declares convertServiceLogTimestamps()',
];
$results[] = [
    (bool)preg_match('#\$output\s*=\s*convertServiceLogTimestamps\(\$output\)#', $src),
    'vals() runs the raw file text through convertServiceLogTimestamps() '
        . 'before returning it',
];
$results[] = [
    (bool)preg_match('#FOGCore::storageTimeZone\(\)#', $src),
    'the stamp is parsed in the zone it was actually written in, not '
        . 'assumed to be UTC outright -- storageTimeZone() still answers a '
        . 'pre-boundary install correctly',
];
$results[] = [
    (bool)preg_match('#FOGCore::displayTimeZone\(\)#', $src),
    'the stamp is shown in the requesting viewer\'s own display zone, the '
        . 'same preference every other date in the UI already uses',
];

// The exact regex and DateTime format the source uses, copied rather than
// required (see docblock) so this exercises the real parsing logic, not a
// paraphrase of it.
$pattern = '/^\[(\d{2}-\d{2}-\d{2} \d{1,2}:\d{2}:\d{2} [ap]m)\]/m';
$format = 'm-d-y g:i:s a';

// The exact shape Service_Log_message() writes: "[m-d-y g:i:s a] name msg".
$line = '[08-31-26 3:45:12 pm] Scheduler Task queued';

if (!preg_match($pattern, $line, $m)) {
    $results[] = [false, 'the regex matches a real Service_Log_message() line'];
} else {
    $utc = new DateTimeZone('UTC');
    $ny = new DateTimeZone('America/New_York');

    $stamp = DateTime::createFromFormat($format, $m[1], $utc);
    $results[] = [
        false !== $stamp,
        'the stamp round-trips through DateTime::createFromFormat() -- the '
            . 'dash-separated m-d-y shape is NOT the same as ISO Y-m-d, and '
            . "PHP's loose string parsing (niceDate()/toDisplay()) reads "
            . 'dashed dates as European d-m-y, which would silently corrupt '
            . 'this exact format',
    ];

    if (false !== $stamp) {
        $stamp->setTimezone($ny);
        // 2026-08-31 is EDT (UTC-4): 3:45:12 pm UTC -> 11:45:12 am EDT.
        $results[] = [
            '11:45:12 am' === $stamp->format('g:i:s a'),
            'converting a known UTC stamp into America/New_York lands on '
                . 'the correct wall-clock time (got '
                . $stamp->format('g:i:s a') . ')',
        ];
        $results[] = [
            '08-31-26' === $stamp->format('m-d-y'),
            'the date itself is unaffected when the zone shift does not '
                . 'cross midnight',
        ];
    }

    // A stamp already in the viewer's own zone must come back unchanged.
    $same = DateTime::createFromFormat($format, $m[1], $utc);
    if (false !== $same) {
        $same->setTimezone($utc);
        $results[] = [
            $m[1] === $same->format($format),
            'a viewer whose display zone is UTC (the common, unconfigured '
                . 'case) sees the exact same stamp -- no accidental drift '
                . 'when there is nothing to convert',
        ];
    }
}

// A line from a different log entirely must never match and never be
// touched -- this code has no idea what zone Apache or syslog wrote in.
$apacheLine = '[Mon Aug 31 15:45:12.123456 2026] [core:error] some message';
$results[] = [
    !preg_match($pattern, $apacheLine),
    'an Apache/nginx-style stamp does not match the service-log pattern '
        . 'and so is left alone',
];

$fails = array_filter($results, function ($r) {
    return !$r[0];
});

foreach ($results as list($passed, $desc)) {
    printf("%s %s\n", $passed ? 'PASS:' : 'FAIL:', $desc);
}

if ($fails) {
    exit(1);
}

echo "PASS: service-log timestamps convert into the viewer's display zone, "
    . "other logs are left alone\n";
exit(0);
