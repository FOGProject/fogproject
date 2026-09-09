<?php
/**
 * Every select2 built inside a Bootstrap modal must set dropdownParent.
 *
 * select2 appends its dropdown to <body> at z-index 1051. A Bootstrap 5
 * modal is 1055 and is its own stacking context, so a dropdown left at the
 * default renders BEHIND the dialog: populated, correct, and invisible.
 * Nothing errors and nothing is logged -- from the outside it looks exactly
 * like a search that matched nothing, which is how it was reported both
 * times.
 *
 * Raising the z-index is not the alternative. The bootstrap-5 theme does
 * ship `.select2-container--bootstrap-5 .select2-dropdown{z-index:1056}`,
 * but none of these controls pass `theme`, so their container is
 * select2-container--default and that rule never matches -- and the modal is
 * a stacking context either way. `dropdownParent` is the fix, and
 * fog.common.js's `.fog-select2` loop has always used it.
 *
 * The two that did not:
 *
 *  - fog.host.list.js loadGroupSelect() -- the Edit groups modal on the host
 *    list, built by hand rather than through .fog-select2. Reported
 *    2026-09-09 with a screenshot of "som" typed and no list;
 *    elementFromPoint() over the first result returned the modal's own help
 *    paragraph.
 *  - fog.image.multicast.js -- #image DOES carry fog-select2, so the shared
 *    loop anchors it correctly at page load, and then the success handler
 *    re-initialises it WITHOUT the option, which re-parents the dropdown to
 *    <body>. Measured in the same harness: before the re-init the topmost
 *    element over the open dropdown is select2's own search field, after it
 *    the modal's help paragraph. So the first session creates fine and every
 *    one after it in that modal has an invisible image list.
 *
 * This file is the general gate: it finds every .select2({...}) construction
 * in the FOG scripts, decides whether its target is inside a modal, and
 * requires dropdownParent on the ones that are. A new modal select added
 * without it fails here rather than in somebody's browser.
 *
 * Usage: php tests/select2-in-a-modal-anchors-its-dropdown.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('select2-in-a-modal');

$t = new FogChecks();

$jsDir = dirname(__DIR__) . '/packages/web/management/js/fog';

/**
 * Every .select2({ ... }) construction in a file, as [line, options text].
 *
 * Only the OBJECT form is collected. `.select2()` with no argument and the
 * method forms -- .select2('destroy'), .select2('val', x) -- construct
 * nothing that could carry the option, and a bare .select2() cannot be
 * inside a modal without also being one of the cases below, which the
 * inventory names explicitly.
 *
 * @param string $src file contents
 *
 * @return array
 */
$constructions = static function ($src) {
    $out = [];
    $offset = 0;
    while (false !== ($pos = strpos($src, '.select2({', $offset))) {
        $offset = $pos + 10;
        // Walk the braces so a nested object (ajax:{...}) does not end the
        // options early -- a substring to the first '}' would miss any
        // option written after the ajax block, which is where
        // dropdownParent would most naturally be added.
        $depth = 1;
        $i = $pos + 9;
        $len = strlen($src);
        while (++$i < $len && $depth > 0) {
            if ('{' === $src[$i]) {
                $depth++;
            } elseif ('}' === $src[$i]) {
                $depth--;
            }
        }
        $out[] = [
            substr_count(substr($src, 0, $pos), "\n") + 1,
            substr($src, $pos, $i - $pos)
        ];
    }

    return $out;
};

// The inventory of select2 constructions whose target is inside a modal,
// with the file that renders that markup. Hand-maintained BECAUSE it is the
// thing under test: the check below is "is this list still complete", and a
// list derived from the same source it audits would agree with itself.
//
// A construction not named here is asserted to be outside a modal, so
// forgetting to add one shows up as a failure on the file, not as silence.
$inModals = [
    'host/fog.host.list.js' => 1,      // #groupSelect, addToGroupModal
    'image/fog.image.multicast.js' => 1 // #image, createModal
];
$files = [];
$it = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($jsDir, \FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $f) {
    if ('js' === strtolower($f->getExtension())) {
        $files[] = $f->getPathname();
    }
}
sort($files);
$t->check('the FOG script directory was walked', count($files) > 0);

$anchoredInModal = [];
foreach ($files as $path) {
    $rel = ltrim(str_replace($jsDir, '', $path), '/');
    $src = (string)file_get_contents($path);
    // Comments stripped: a gate must not be satisfiable by the prose that
    // explains it, and both fixes are documented in comments right beside
    // the option they add.
    $code = (string)preg_replace('#//[^\n]*#', '', $src);
    foreach ($constructions($code) as $c) {
        list($line, $opts) = $c;
        $hasParent = false !== strpos($opts, 'dropdownParent');
        if (isset($inModals[$rel])) {
            if ($hasParent) {
                $anchoredInModal[$rel] = ($anchoredInModal[$rel] ?? 0) + 1;
            }
            $t->check(
                "$rel:$line builds a select2 in a modal and anchors it",
                $hasParent
            );
        }
    }
}

// The shared loop, checked on its own rather than as "every select2 in
// fog.common.js": that file also builds the sidebar's universal search,
// which is not in a modal and correctly carries no dropdownParent. What
// matters is that the .fog-select2 loop -- the path every generated select
// takes, including the ones inside modals -- still chooses its parent.
$common = (string)preg_replace(
    '#//[^\n]*#',
    '',
    (string)file_get_contents($jsDir . '/fog.common.js')
);
$t->check(
    'the .fog-select2 loop still anchors each select to its closest modal',
    1 === preg_match(
        // dropdownParent must be given the MODAL the element is in, not
        // merely be present in a block that also mentions .modal somewhere:
        // the first version of this check passed a mutation that kept
        // `$modal = $sel.closest('.modal')` and then handed
        // dropdownParent $(document.body) regardless.
        '#\$\(\s*[\'"]\.fog-select2[\'"]\s*\)\s*\.each\('
        . '.*?\$modal\s*=\s*\$sel\.closest\(\s*[\'"]\.modal[\'"]\s*\)'
        . '.*?dropdownParent:\s*\$modal\b#s',
        $common
    )
);

// Both halves of the inventory actually matched something. Without this a
// rename of either file would empty the loop above and every check would
// pass by not running.
foreach ($inModals as $rel => $expected) {
    $t->check(
        "$rel still carries $expected anchored modal select2",
        ($anchoredInModal[$rel] ?? 0) === $expected
    );
}

$t->finish();
