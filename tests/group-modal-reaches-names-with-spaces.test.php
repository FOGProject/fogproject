<?php
/**
 * Edit groups (host list): a group whose name contains a space must be
 * reachable, and a term you are still typing must not be submitted.
 *
 * Both defects live in loadGroupSelect()/submitMembership() in
 * fog.host.list.js, both were reported together on 2026-09-09, and both are
 * silent -- the modal answers 200 and the grid redraws either way. What you
 * get is the wrong group.
 *
 * 1. SPACE WAS A TOKEN SEPARATOR. `tokenSeparators: [',', ' ']` tells
 *    select2 to commit the search term as a tag the moment you press space.
 *    So "Something DarksideMilk" became the tag "Something" at the first
 *    space; the ajax search never ran on the whole name, the real group
 *    could not be offered, and Add sent "Something" in groups_new -- which
 *    the server CREATES, because an unmatched name is how you make a new
 *    group here. Two of the three groups on the reporting server had spaces
 *    in their names, so the feature was unusable for most of them.
 *
 *    Measured in a browser against the shipped select2 and the real group
 *    names: with ' ' a separator, typing "Something " left one tag,
 *    "Something", and an empty dropdown; without it, "Something Dark" stayed
 *    in the search box and "Something DarksideMilk" was offered and
 *    clickable.
 *
 *    Comma stays. Pasting a comma-separated list of names is the reason
 *    tokenSeparators is set at all, and a comma cannot appear in a name that
 *    was typed into this same control.
 *
 * 2. THE SUBMIT READ EVERY OPTION, NOT THE SELECTED ONES. select2's
 *    createTag() puts the term you are typing into the <select> as an
 *    UNSELECTED <option> so it can be offered as "(new)". submitMembership()
 *    read `.find('option')`, so that fragment went to the server as well:
 *    typing "Something Dark", then clicking "Something DarksideMilk" in the
 *    list, joined the right group AND created a junk one called "Something
 *    Dark". Verified in the same harness -- after the pick the select held
 *    <option value="Something Dark"> unselected and <option value="1">
 *    selected.
 *
 * 3. THE DROPDOWN RENDERED BEHIND THE MODAL. Reported straight after the
 *    first two, with a screenshot of "som" typed and no list. select2
 *    appends its dropdown to <body> at z-index 1051; a Bootstrap 5 modal is
 *    1055. So the list was built, populated and correct -- and painted
 *    underneath the dialog. Measured: with "som" typed,
 *    .select2-results__option held "Something DarksideMilk", and
 *    elementFromPoint() at the middle of the first row returned
 *    P.form-text -- the modal's own help paragraph. Nothing errors, and it
 *    is indistinguishable from a search that matched nothing.
 *
 *    `dropdownParent: groupModal` puts it inside the dialog, which is what
 *    the .fog-select2 loop in fog.common.js already does for every other
 *    select in a modal. Raising the z-index would not do: this control is
 *    built without `theme`, so it is select2-container--default and the
 *    bootstrap-5 theme's 1056 rule never matches it, and a modal is a
 *    stacking context regardless.
 *
 * 4. TAB THREW THE TERM AWAY. Requested 2026-09-09 alongside a semicolon
 *    separator. Enter already committed the highlighted option -- measured
 *    on the shipped build: type "Lab Room One", press Enter, and the select
 *    holds that exact string, spaces and all -- but Tab did not. select2
 *    binds its own keydown on the search field, closes the dropdown on Tab
 *    and clears the box, so the name simply vanished.
 *
 *    The handler is CAPTURE phase because select2's is bound first and a
 *    bubbling one only runs after the dropdown has already closed, and it
 *    re-dispatches Enter rather than selecting the option itself: select2
 *    hangs no data on the results <li> ($(li).data('data') is undefined),
 *    so its own Enter path is the available API and is the code Enter is
 *    already exercised on. Measured after: typing "Lab Room Two" and
 *    pressing Tab leaves that one chip with focus still in the box; and
 *    arrowing down to "Something DarksideMilk" and pressing Tab yields
 *    <option value="1">, the group's ID, not its name.
 *
 * All four are pinned as the DEFINITION that decides the behavior -- the
 * option values select2 is constructed with, the selector the submit reads,
 * and the listener that intercepts the key -- not as a mention of any of
 * their names.
 *
 * Usage: php tests/group-modal-reaches-names-with-spaces.test.php
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

FogTestHarness::boot('group-modal-names-with-spaces');

$t = new FogChecks();

$path = dirname(__DIR__)
    . '/packages/web/management/js/fog/host/fog.host.list.js';
$src = (string)file_get_contents($path);
$t->check('fog.host.list.js was read', '' !== $src);

// Comments stripped before matching: every claim below is about the code
// select2 and jQuery actually run, and this file explains both fixes in
// prose right beside them. Without this a gate can be satisfied by its own
// documentation.
$code = (string)preg_replace('#//[^\n]*#', '', $src);

// -- 1. the separator list -------------------------------------------------
if (!preg_match('#tokenSeparators:\s*\[([^\]]*)\]#', $code, $m)) {
    $t->check('loadGroupSelect() sets tokenSeparators', false);
    $t->finish();
}
// Each element read as a QUOTED STRING, not by splitting on commas: the list
// configures a comma, so a split would consume the very entry it is meant to
// read -- and trimming quotes off " ' ' " leaves nothing at all, which is how
// the first version of this gate passed a mutation that restored the space.
preg_match_all('#\'((?:[^\'\\\\]|\\\\.)*)\'#', $m[1], $mm);
$separators = $mm[1];
$t->check(
    'the separator list parsed into at least one entry',
    count($separators) > 0
);

$t->check(
    'a space is NOT a token separator, so a name can contain one',
    !in_array(' ', $separators, true)
);
$t->check(
    'a comma still is, so a pasted list still splits',
    in_array(',', $separators, true)
);
$t->check(
    'and so is a semicolon',
    in_array(';', $separators, true)
);

// -- 2. what the submit reads ---------------------------------------------
// Anchored on the assignment, not on the string 'option:selected' anywhere
// in the file: the selector only matters in the statement that builds the
// list of ids the POST carries.
$t->check(
    'submitMembership() reads only the SELECTED options',
    1 === preg_match(
        '#var\s+items\s*=\s*groupModalSelect\s*\.\s*'
        . 'find\(\s*[\'"]option:selected[\'"]\s*\)#',
        $code
    )
);
$t->check(
    'and never every option, which would submit the term being typed',
    0 === preg_match(
        '#groupModalSelect\s*\.\s*find\(\s*[\'"]option[\'"]\s*\)#',
        $code
    )
);

// -- 3. where the dropdown is parented ------------------------------------
// Anchored to the select2 CONSTRUCTION, not to the string anywhere in the
// file: dropdownParent only does anything as an option on this call.
$t->check(
    'the dropdown is parented to the modal, not to <body>',
    1 === preg_match(
        '#groupModalSelect\s*\.\s*select2\(\s*\{.*?'
        . 'dropdownParent:\s*groupModal\b#s',
        substr($code, 0, (int)strpos($code, 'submitMembership'))
    )
);


// -- 4. Tab commits, the way Enter does ------------------------------------
// Three separate things decide this and all three are pinned: the listener
// must be on the select2 container, in the CAPTURE phase (bubbling runs too
// late -- select2 has closed the dropdown by then), and it must hand select2
// the key it acts on. A check for the string 'Tab' alone would pass a
// handler bound the wrong way round, which is the version that does nothing.
$tab = (string)strstr($code, "next('.select2-container')");
$t->check(
    'the Tab handler is bound to the select2 container',
    '' !== $tab
);
$t->check(
    'in the capture phase, ahead of select2 own keydown',
    1 === preg_match(
        '#addEventListener\(\s*[\'"]keydown[\'"]\s*,.*?,\s*true\s*\)#s',
        (string)substr($tab, 0, 1200)
    )
);
$t->check(
    'it acts on Tab and leaves Shift+Tab alone',
    1 === preg_match(
        '#e\.key\s*!==\s*[\'"]Tab[\'"]\s*\|\|\s*e\.shiftKey#',
        $tab
    )
);
$t->check(
    'only when the dropdown has something highlighted',
    1 === preg_match(
        '#!groupModal\.find\(\s*[\'"]\.select2-results__option--highlighted[\'"]\s*\)\.length#',
        $tab
    )
);
$t->check(
    'and it commits by re-dispatching the key select2 already handles',
    1 === preg_match('#which:\s*13#', $tab)
    && 1 === preg_match('#preventDefault\(\)#', $tab)
);

$t->finish();
