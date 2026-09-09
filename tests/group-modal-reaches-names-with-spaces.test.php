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
 * Both are pinned as the DEFINITION that decides the behavior -- the option
 * value select2 is constructed with, and the selector the submit reads --
 * not as a mention of either name.
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
    'and a comma still is, so a pasted list still splits',
    in_array(',', $separators, true)
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

$t->finish();
