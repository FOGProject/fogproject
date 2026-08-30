<?php
/**
 * A plugin can name its own report.
 *
 * WHY THIS SEAM EXISTS. ReportManagement::reportTitles() is core's map and a
 * plugin cannot add a row to it, so every bundled plugin report is in the
 * state core's reports were before that map: `ou_report.report.php` renders a
 * heading saying "Export OUs" under a sidebar entry saying "Ou Report",
 * because ucwords() of a file name was all the menu had. The existing
 * SUB_MENULINK_DATA event is the seam for MOVING a menu entry, not for
 * naming one -- a listener using it has to rebuild the base64 key by hand.
 *
 * So reportTitles() fires REPORT_TITLE_DATA, and this is the contract that
 * event promises. Both halves fail silently if they break: a listener that
 * stops being called leaves the label as ucwords() of a file name, which is
 * a plausible-looking label rather than an error.
 *
 * THE MEMOIZATION IS PART OF THE CONTRACT, not an implementation detail.
 * titleFor() is called once per menu entry and again by each report for its
 * own heading, and the sidebar is built for every node on every page --
 * so an unmemoized event would fire fifteen times a request forever. A
 * listener that assumed it ran once per lookup would be wrong; one that
 * assumes it runs once per request is right, and that is asserted here.
 *
 * Registered BEFORE the first reportTitles() call, deliberately: a listener
 * added after the map is built does not appear, which is the one thing a
 * plugin author has to know.
 *
 * Usage: php tests/report-title-hook.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('report-title-hook');
FogTestHarness::fakeDb();

use FOG\Pages\ReportManagement;

$t = new FogChecks();

$fired = 0;
// The manager is a protected static on FOGBase, which is where every hook
// in FOG reaches it from; a test is outside the hierarchy, so it comes
// through the harness rather than by making the property public.
$hooks = FogTestHarness::getStatic('FOG\Base\FOGBase', 'HookManager');
$hooks->register(
    'REPORT_TITLE_DATA',
    function ($arguments) use (&$fired) {
        $fired++;
        // A report core knows nothing about, which is the plugin case.
        $arguments['titles']['ou report'] = 'Export OUs';
        // And an override of a core row, which the event deliberately
        // allows -- the same latitude every other *_DATA event gives.
        $arguments['titles']['snapin list'] = 'Snapins, Listed';
    }
);

/*
 * 1. The listener reaches the map, through the public entry points rather
 *    than by reading reportTitles() directly -- titleFor() is what the
 *    sidebar actually calls.
 */
$t->check(
    'a plugin can name a report core has never heard of',
    'Export OUs' === ReportManagement::titleFor('ou report')
);
$t->check(
    'and can override a core label',
    'Snapins, Listed' === ReportManagement::titleFor('snapin list')
);
$t->check(
    'a core label with no listener behind it is untouched',
    'Fleet Report' === ReportManagement::titleFor('fleet report')
);
$t->check(
    'a report nobody named still falls back to the old derivation',
    'Some Other Report' === ReportManagement::titleFor('some other report')
);

/*
 * 2. Once per request, however many labels are asked for. Without this the
 *    event fires for every menu entry on every page in FOG.
 */
$before = $fired;
for ($i = 0; $i < 10; $i++) {
    ReportManagement::titleFor('ou report');
    ReportManagement::reportTitles();
}
$t->check(
    'the event fired at all',
    $before > 0
);
$t->check(
    'and fired exactly once, not once per lookup',
    1 === $fired
);

/*
 * 3. The payload is passed BY REFERENCE. processEvent() merges an `event`
 *    key and calls the listener with the argument array; a listener that
 *    received a copy would appear to work -- no error, no warning -- and
 *    change nothing. That is the failure this whole file is about, so it
 *    is asserted on the value that came back rather than on the mechanism.
 */
$t->check(
    "the listener's write survived, so `titles` really is a reference",
    'Export OUs' === (ReportManagement::reportTitles()['ou report'] ?? null)
);

$t->finish();
