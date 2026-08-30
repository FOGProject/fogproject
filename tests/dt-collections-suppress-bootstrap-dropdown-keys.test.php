<?php
/**
 * DataTables button collections must not reach Bootstrap's dropdown keyboard
 * handler.
 *
 * Bootstrap 5.3 delegates one keydown handler off `document` to any
 * `.dropdown-menu`. A DataTables collection IS a `<ul class="dropdown-menu">`,
 * but it has no `[data-bs-toggle="dropdown"]`, so on Escape/ArrowUp/ArrowDown
 * the handler preventDefault()s and then throws looking for a toggle that was
 * never there. Arrow keys stop scrolling the open panel and every one of those
 * keys writes a console error.
 *
 * Four things are pinned, and each one alone leaves the bug reachable:
 *
 * - the listener exists at all;
 * - it is on `window`, in the CAPTURE phase. Bootstrap's EventHandler passes
 *   its delegation flag through as addEventListener's capture argument, so its
 *   handler is itself a capture listener on `document`, registered before this
 *   file loads. Both a bubble listener and a `document` capture listener
 *   therefore run second and are silent no-ops -- they stop the event, look
 *   correct, and Bootstrap has already thrown. This was measured, not
 *   reasoned: the first cut of the fix used `document` and changed nothing;
 * - it suppresses exactly Escape, ArrowUp and ArrowDown. Capture on `document`
 *   halts the event before `body`, where DataTables binds the collection's own
 *   Tab focus trap, so adding Tab here would break keyboard navigation
 *   outright;
 * - it is scoped to div.dt-button-collection, so a genuine Bootstrap dropdown
 *   -- which has a toggle and works -- keeps its arrow-key navigation.
 *
 * Usage: php tests/dt-collections-suppress-bootstrap-dropdown-keys.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$js = file_get_contents(
    $root . '/packages/web/management/js/fog/fog.common.js'
);

$fails = [];

// The whole registration, not the function name: a listener that has quietly
// become bubble-phase is the same bug with the right identifier on it.
if (!preg_match(
    '#window\.addEventListener\(\s*\'keydown\',\s*function\s*\(e\)\s*\{'
    . '.*?\}\s*,\s*true\s*\)#s',
    $js
)) {
    $fails[] = 'no capture-phase window keydown listener: on `document`, or '
        . 'without the trailing `true`, this runs after Bootstrap has already '
        . 'thrown -- it stops the event, reports nothing, and fixes nothing';
}

// The key list, in both directions.
if (!preg_match(
    "#SUPPRESSED = \\['Escape', 'ArrowUp', 'ArrowDown'\\]#",
    $js
)) {
    $fails[] = 'the suppressed key list is not exactly Escape, ArrowUp and '
        . 'ArrowDown -- the three Bootstrap acts on in a dropdown menu';
}
if (preg_match("#SUPPRESSED = \\[[^\\]]*'Tab'#", $js)) {
    $fails[] = "'Tab' is in the suppressed list, which halts the event before "
        . 'body and so kills the DataTables collection focus trap';
}

// And it may only fire inside a DataTables collection.
if (!preg_match(
    "#closest\\('div\\.dt-button-collection'\\)#",
    $js
)) {
    $fails[] = 'the suppression is not scoped to div.dt-button-collection, so '
        . 'it also disables arrow-key navigation in real Bootstrap dropdowns';
}

if ($fails) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    exit(1);
}
echo "PASS: DataTables collections keep Bootstrap's dropdown keys away from "
    . "document\n";
exit(0);
