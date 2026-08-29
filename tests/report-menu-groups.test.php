<?php
/**
 * The report menu says which of its entries are reports and which are lists.
 *
 * ADR 0030 opened on the complaint that FOG's reports are not reports. Six
 * of them now are -- aggregations over a window -- and the other seven are
 * row dumps, several of which are the right answer to "give me the rows"
 * and are staying. Presenting both kinds as one flat alphabetical list is
 * what makes the good ones invisible and the plain ones look unfinished.
 *
 * So the menu carries two section labels, and the things that can go wrong
 * with that are all silent:
 *
 *   - a report added to lib/reports and not to AGGREGATIONS lands under
 *     Lists, which is the safe default but the wrong answer for a report
 *     that charts something;
 *   - AGGREGATIONS naming a report that no longer exists leaves an entry
 *     that never renders, and nothing says so;
 *   - a section label that survives its own contents renders as a heading
 *     over an empty gap. That is not cosmetic: the report menu resolves a
 *     permission PER REPORT, so a role holding only host.view keeps two
 *     entries and loses five, and an orphaned "Reports" heading reads as a
 *     rendering fault rather than as a permission working;
 *   - a label rendered as a link goes nowhere when clicked.
 *
 * Usage: php tests/report-menu-groups.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('report-menu-groups');

use FOG\ReportManagement;

$t = new FogChecks();
$root = dirname(__DIR__);
$web = $root . '/packages/web';

/*
 * 1. Every entry is placed, and placed once.
 */
$onDisk = [];
foreach (glob($web . '/lib/reports/*.report.php') as $file) {
    $onDisk[] = strtolower(
        str_replace('_', ' ', basename($file, '.report.php'))
    );
}
sort($onDisk);
$t->check('the scan finds the shipped reports', count($onDisk) >= 10);

$groups = ReportManagement::groupedReports();
$t->check(
    'the menu is split into exactly the two kinds',
    ['reports', 'lists'] === array_keys($groups)
);

$placed = array_map(
    'strtolower',
    array_merge($groups['reports'], $groups['lists'])
);
sort($placed);
$t->check(
    'every report on disk appears in exactly one group',
    $onDisk === $placed
);

/*
 * 2. AGGREGATIONS names things that exist. An entry for a deleted report
 *    is dead config that nothing reports, which is how a list like this
 *    rots.
 */
foreach (ReportManagement::AGGREGATIONS as $name) {
    $t->check(
        "AGGREGATIONS names '$name', and that report exists",
        in_array($name, $onDisk, true)
    );
}
$t->check(
    'the aggregations are the ones that landed under Reports',
    array_map('strtolower', $groups['reports'])
        === array_values(
            array_intersect($onDisk, ReportManagement::AGGREGATIONS)
        )
);
$t->check(
    'and nothing else did',
    [] === array_intersect(
        array_map('strtolower', $groups['lists']),
        ReportManagement::AGGREGATIONS
    )
);

/*
 * 3. The retired report is gone from both, and the reports that replaced
 *    the flat dumps are under the right heading.
 */
$t->check(
    'Inventory Report was retired into Hardware Report',
    !in_array('inventory report', $onDisk, true)
);
foreach (['imaging report', 'fleet report', 'hardware report',
    'storage report', 'snapin report', 'audit report', 'run history'] as $r
) {
    $t->check(
        "'$r' is under Reports",
        in_array($r, array_map('strtolower', $groups['reports']), true)
    );
}
// History Report ends in `_report` and is a row dump with a redirect on it,
// which is exactly why the grouping is a named list and not a filename
// convention.
$t->check(
    "'history report' is a list despite its name",
    in_array('history report', array_map('strtolower', $groups['lists']), true)
);

/*
 * 4. The renderer. A label is a '#' key -- a sub no report can have,
 *    because `f` is base64 and every other sub is alphanumeric.
 */
$page = (string)file_get_contents($web . '/src/Base/FOGPage.php');
$t->check(
    'a section label is rendered as a heading, not as a link',
    1 === preg_match(
        '/if \(0 === strpos\(\(string\) \$subItem, \x27#\x27\)\) \{\s*'
        . 'echo \x27<li class="nav-header">\x27/',
        $page
    )
);
$t->check(
    'labels are skipped by the permission filter rather than resolved',
    1 === preg_match(
        '/if \(0 === strpos\(\(string\) \$subKey, \x27#\x27\)\) \{\s*continue;/',
        $page
    )
);

/*
 * 5. The prune, DRIVEN rather than read. Asserting that the source still
 *    contains the loop passes with the whole thing disabled, which is what
 *    the first cut of this check did.
 */
$prune = function (array $menu) {
    return \FOG\Base\FOGPage::pruneEmptyMenuSections($menu);
};
$t->check(
    'a section with a report under it survives',
    ['#reports' => 'Reports', 'file&f=aW1n' => 'Imaging']
        === $prune(['#reports' => 'Reports', 'file&f=aW1n' => 'Imaging'])
);
$t->check(
    'a section whose reports were all filtered out is dropped',
    ['upload' => 'Import']
        === $prune(['#reports' => 'Reports', 'upload' => 'Import'])
);
$t->check(
    'a trailing non-report does not keep an empty section alive',
    ['#lists' => 'Lists', 'file&f=aG9zdA==' => 'Hosts', 'upload' => 'Import']
        === $prune([
            '#reports' => 'Reports',
            '#lists' => 'Lists',
            'file&f=aG9zdA==' => 'Hosts',
            'upload' => 'Import'
        ])
);
$t->check(
    'a section does not borrow the next section\'s contents',
    ['#lists' => 'Lists', 'file&f=aG9zdA==' => 'Hosts']
        === $prune([
            '#reports' => 'Reports',
            '#lists' => 'Lists',
            'file&f=aG9zdA==' => 'Hosts'
        ])
);
$t->check(
    'a menu with no sections at all is returned untouched',
    ['list' => 'List All', 'add' => 'Add'] === $prune(
        ['list' => 'List All', 'add' => 'Add']
    )
);
$t->check(
    'and both sections survive when both have reports',
    5 === count($prune([
        '#reports' => 'Reports',
        'file&f=aW1n' => 'Imaging',
        '#lists' => 'Lists',
        'file&f=aG9zdA==' => 'Hosts',
        'upload' => 'Import'
    ]))
);
$t->check(
    'the caller actually runs it',
    false !== strpos($page, 'self::pruneEmptyMenuSections($menu)')
);
$t->check(
    'the section names are translatable',
    false !== strpos($page, "_('Lists')")
    && false !== strpos($page, "self::\$foglang['Reports']")
);

$t->finish();
