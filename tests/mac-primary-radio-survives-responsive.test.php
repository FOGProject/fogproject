<?php
/**
 * The host MAC tab shows the primary MAC as selected.
 *
 * Forums 18310: every host's MAC tab showed no primary MAC, and a new
 * choice looked lost after the redraw. The database was correct. The
 * primary radios shared name="primary". DataTables Responsive measures the
 * grid by inserting a copy of it into the page. The copy's checked radio
 * joined the same named group and unchecked the real one, then the copy
 * was removed. Observed in a browser on the lab: the real radio kept its
 * checked attribute but its checked property was false.
 *
 * So the radio must not carry a name, and the change handler must clear
 * the other radios itself.
 *
 * Pinned by source: the render and the handler are browser callbacks.
 *
 * Usage: php tests/mac-primary-radio-survives-responsive.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

$t = new FogChecks();
$src = (string)@file_get_contents(
    dirname(__DIR__) . '/packages/web/management/js/fog/host/fog.host.edit.js'
);

// The input tag the primary column renders, from its opening to the '/>'.
$from = strpos($src, 'belongsto="primaryMacs"');
$radioTag = false === $from ? '' : substr($src, $from, (int)strpos($src, "'/>'", $from) - $from);
$t->check(
    'the primary MAC radio is rendered',
    '' !== $radioTag
);
$t->check(
    'the primary MAC radio carries no name attribute',
    '' !== $radioTag && false === strpos($radioTag, 'name=')
);

// The change handler, from its declaration to the next handler.
$at = strpos($src, 'var onMacsRadioSelect = function');
$end = false === $at ? false : strpos($src, 'var onMacsCheckboxSelect', $at);
$handler = (false === $at || false === $end) ? '' : substr($src, $at, $end - $at);
$t->check(
    'the change handler clears the other primary radios',
    1 === preg_match(
        "/\\\$\\('#host-macaddresses-table input\\.primary'\\)\\.not\\(this\\)\\.prop\\('checked', false\\)/",
        $handler
    )
);

$t->finish();
