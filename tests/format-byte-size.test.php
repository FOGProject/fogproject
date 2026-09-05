<?php
/**
 * FOGBase::formatByteSize picks a BINARY unit, so it must pick it with a
 * binary logarithm.
 *
 * The original chose the unit with floor((strlen($size) - 1) / 3) -- one
 * step per three decimal digits, which is a step of 1000 -- and then
 * divided by a power of 1024. The two scales disagree for every value
 * between 10^(3n) and 1024^n, and everything in that band rendered as a
 * fraction of the unit above. Found on the Inventory tab on 2026-09-04:
 * a host with 968 MB of RAM showed "0.95 GiB".
 *
 * The helper is used for image, snapin, disk and storage sizes as well, so
 * the cases below cover the boundaries rather than just the one that was
 * reported.
 *
 * Usage: php tests/format-byte-size.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('format-byte-size');

/**
 * formatByteSize is protected; this is the smallest legal way to call it.
 */
class FormatByteSizeProbe extends \FOG\Base\FOGBase
{
    /**
     * @param int|float $size bytes
     *
     * @return string
     */
    public static function fmt($size)
    {
        return self::formatByteSize($size);
    }
}

$checks = [
    // The reported defect, both hosts that showed it up.
    [968 * 1048576, '968.00 MiB', 'an agent host reporting 968 MB of RAM'],
    [1000 * 1048576, '1000.00 MiB', 'a 1000 MB VM, just under a GiB'],
    [1000000000, '953.67 MiB', 'a 1 GB image, decimal vendor gigabytes'],

    // Boundaries: the unit must change exactly at the binary step.
    [1073741823, '1024.00 MiB', 'one byte below a GiB stays in MiB'],
    [1073741824, '1.00 GiB', 'exactly a GiB steps up'],
    [1048575, '1024.00 KiB', 'one byte below a MiB stays in KiB'],
    [1048576, '1.00 MiB', 'exactly a MiB steps up'],

    // Values that were already right must not move.
    [8172 * 1048576, '7.98 GiB', 'a Windows host with 8172 MB, correct before and after'],
    [500 * 1048576, '500.00 MiB', 'a 500 MB value, outside the bad band'],
    [1099511627776, '1.00 TiB', 'exactly a TiB'],

    // Past the end of the units array. Unclamped, log(1024^9, 1024) is 9
    // and the array has nine entries, so this is an undefined index -- the
    // clamp is what keeps the largest unit and lets the number grow.
    [pow(1024, 9), '1024.00 YiB', 'beyond YiB keeps the largest unit'],

    // Degenerate input must not warn or divide by a negative power.
    [0, '0.00 iB', 'zero'],
    ['', '0.00 iB', 'an empty string, as an unset column gives'],
];

$failed = 0;
foreach ($checks as list($in, $want, $why)) {
    $got = FormatByteSizeProbe::fmt($in);
    if ($got === $want) {
        printf("  ok    %s (%s)\n", $why, $got);
        continue;
    }
    ++$failed;
    printf("  FAIL  %s: got '%s', want '%s'\n", $why, $got, $want);
}

printf("\n%d passed, %d failed\n", count($checks) - $failed, $failed);
exit($failed ? 1 : 0);
