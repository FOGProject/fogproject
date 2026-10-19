<?php
/**
 * Run the real uptime formatter with deterministic /proc data and translations.
 * Load it in a test namespace so stubs do not shadow production classes during
 * whole-suite static analysis.
 * Usage: php tests/uptime-unit-plurals.test.php
 */
namespace FOGTest\UptimeUnits {
    class FOGBase
    {
    }

    function exec($command, &$output = null, &$resultCode = null)
    {
        if ('cat /proc/uptime' !== $command) {
            throw new \RuntimeException('Unexpected command: ' . $command);
        }
        $output = [number_format($GLOBALS['uptimeSeconds'], 2, '.', '') . ' 0'];
        $resultCode = 0;
        return true;
    }

    function ngettext($singular, $plural, $count)
    {
        $translations = $GLOBALS['uptimeTranslations'];
        return $translations[$count === 1 ? $singular : $plural];
    }

    function _($message)
    {
        return $message;
    }
}

namespace {
    $coreSourcePath = dirname(__DIR__) . '/packages/web/src/Base/FOGCore.php';
    $coreSource = file_get_contents($coreSourcePath);
    if (false === $coreSource) {
        fwrite(STDERR, "cannot read $coreSourcePath\n");
        exit(1);
    }

    $productionNamespace = 'namespace FOG\\Base;';
    if (1 !== substr_count($coreSource, $productionNamespace)) {
        fwrite(
            STDERR,
            "expected exactly one FOG\\Base namespace declaration in "
            . "$coreSourcePath\n"
        );
        exit(1);
    }
    $coreSource = str_replace(
        $productionNamespace,
        'namespace FOGTest\\UptimeUnits;',
        $coreSource
    );
    eval(substr($coreSource, 5));

    $locales = [
        'English' => [
            'day' => 'day', 'days' => 'days',
            'hr' => 'hr', 'hrs' => 'hrs',
            'min' => 'min', 'mins' => 'mins',
        ],
        'Japanese' => [
            'day' => '日', 'days' => '日',
            'hr' => '時間', 'hrs' => '時間',
            'min' => '分', 'mins' => '分',
        ],
    ];
    $cases = [
        [0, 'Up: 0 days 0 hrs 0 mins', 'Up: 0 日 0 時間 0 分'],
        [90060, 'Up: 1 day 1 hr 1 min', 'Up: 1 日 1 時間 1 分'],
        [180120, 'Up: 2 days 2 hrs 2 mins', 'Up: 2 日 2 時間 2 分'],
        [176400, 'Up: 2 days 1 hr 0 mins', 'Up: 2 日 1 時間 0 分'],
        [7260, 'Up: 0 days 2 hrs 1 min', 'Up: 0 日 2 時間 1 分'],
        [86399.99, 'Up: 0 days 23 hrs 59 mins', 'Up: 0 日 23 時間 59 分'],
        [86400, 'Up: 1 day 0 hrs 0 mins', 'Up: 1 日 0 時間 0 分'],
    ];
    $failures = [];

    foreach ($locales as $locale => $translations) {
        $GLOBALS['uptimeTranslations'] = $translations;
        foreach ($cases as [$seconds, $english, $japanese]) {
            $GLOBALS['uptimeSeconds'] = $seconds;
            $expected = 'English' === $locale ? $english : $japanese;
            $actual = \FOGTest\UptimeUnits\FOGCore::systemUptime()['uptime'];
            if ($expected !== $actual) {
                $failures[] = sprintf(
                    '%s uptime %s: expected <%s>, got <%s>',
                    $locale,
                    $seconds,
                    $expected,
                    $actual
                );
            }
        }
    }

    if ($failures) {
        fwrite(STDERR, "FAIL: uptime unit pluralization\n - " . implode("\n - ", $failures) . "\n");
        exit(1);
    }

    echo "ok: uptime unit pluralization in English and Japanese\n";
}
