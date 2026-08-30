<?php
/**
 * The impersonation picker's markup and its JavaScript agree.
 *
 * FOG HAS NO NATIVELY-SUBMITTING FORMS. fog.common.js disableFormDefaults()
 * binds preventDefault to every <form> on the page, on every load and every
 * AJAX navigation, and fog.common.js says so itself where the CSV export
 * works around it: "disableFormDefaults() preventDefaults every form on the
 * page". Every control that posts does so through $.apiCall/processForm, or
 * through a submit handler of its own that navigates by hand -- which is what
 * the report date-range form does.
 *
 * The first cut of the impersonation picker shipped a plain <form> with a
 * type="submit" button. The button was inert: no request, no error, no
 * console message, nothing in any log. Identical on screen to the menu-guard
 * redirect that preceded it, and to the "click Show and nothing happens" bug
 * the report window carries a docblock about. Three different causes, one
 * indistinguishable symptom, which is why this is pinned rather than
 * remembered.
 *
 * So:
 *
 *   (a) the picker emits NO <form>, so there is nothing for
 *       disableFormDefaults() to disable and no submit to be swallowed;
 *   (b) the ids the JS binds are the ids the PHP emits. A rename on either
 *       side is exactly how this breaks again, and it breaks silently;
 *   (c) the JS binds them DELEGATED, from document. The picker is fetched
 *       into the modal after the JS has run, and #ajaxPageWrapper is
 *       replaced on every in-app navigation, so a direct bind would be lost;
 *   (d) the modal is the ONLY surface. index() redirects home instead of
 *       rendering the picker a second time. Two renderers for one control is
 *       two places to keep the candidate filtering, the refusal copy and the
 *       ids in step -- and the ids above are exactly what the JS binds, so
 *       the second copy is a second chance for (b) to go wrong.
 *
 * MUTATION-VERIFIED:
 *
 *   wrap the picker's controls in a <form>       -> (a) red
 *   rename #impersonate-send in the PHP only     -> (b) red
 *   change $(document).on to $('#...').on in JS  -> (c) red
 *   make index() render the picker again         -> (d) red
 *
 * Usage: php tests/impersonation-picker-is-wired.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$fails = [];
$checks = 0;

/**
 * Record one assertion.
 *
 * @param string   $label  what was being asserted
 * @param bool     $cond   whether it held
 * @param string[] $fails  collected failures
 * @param int      $checks assertions run
 *
 * @return void
 */
function check($label, $cond, array &$fails, &$checks)
{
    $checks++;
    if (!$cond) {
        $fails[] = $label;
    }
}

/**
 * PHP source with its comments removed.
 *
 * Every scan below is a claim about CODE, and the comments here talk about
 * exactly the constructs being looked for -- the docblock explaining why
 * there must be no <form> contains the word "<form>". A raw grep fails on
 * its own documentation, which is the same trap as a gate PASSING on its
 * own documentation, arriving from the other side. Both are the reason to
 * tokenize rather than grep.
 *
 * @param string $src PHP source
 *
 * @return string
 */
function stripPhpComments($src)
{
    $out = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)
            && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
        ) {
            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
}

$web = dirname(__DIR__) . '/packages/web';
$php = stripPhpComments(
    (string)file_get_contents(
        $web . '/lib/pages/impersonatemanagement.page.php'
    )
);
$js = (string)file_get_contents(
    $web . '/management/js/fog/fog.impersonate.js'
);
$shell = (string)file_get_contents($web . '/management/other/index.php');
$assets = (string)file_get_contents($web . '/src/Base/Page.php');

check(
    'the page class was not found, so nothing below was checked',
    '' !== $php,
    $fails,
    $checks
);
check(
    'fog.impersonate.js was not found, so nothing below was checked',
    '' !== $js,
    $fails,
    $checks
);

/*
 * (a) NO FORM. Not "a form wired correctly" -- none at all. A form here can
 *     only be a native submit that never fires, because everything the
 *     picker does is one XHR.
 */
check(
    'the picker emits a <form>, whose submit disableFormDefaults() eats',
    false === stripos($php, '<form'),
    $fails,
    $checks
);
check(
    'the picker emits a submit button, which posts nothing in FOG',
    false === stripos($php, 'type="submit"'),
    $fails,
    $checks
);

/*
 * (b) THE IDS MATCH ON BOTH SIDES.
 */
foreach (['impersonate-send', 'impersonate-target'] as $id) {
    check(
        "the PHP does not emit #$id, which the JS binds",
        false !== strpos($php, 'id="' . $id . '"'),
        $fails,
        $checks
    );
    check(
        "the JS does not reference #$id, which the PHP emits",
        false !== strpos($js, "#$id"),
        $fails,
        $checks
    );
}
/*
 *     ...including the two the SHELL owns: the modal the trigger targets,
 *     and the body the JS fills. The trigger is plain Bootstrap markup, so
 *     a mismatch there fails by opening nothing at all.
 */
foreach (['impersonate-modal', 'impersonate-modal-body'] as $id) {
    check(
        "the page shell does not emit #$id",
        false !== strpos($shell, 'id="' . $id . '"'),
        $fails,
        $checks
    );
    check(
        "the JS does not reference #$id",
        false !== strpos($js, "#$id"),
        $fails,
        $checks
    );
}
check(
    'the dropdown trigger does not target the modal',
    false !== strpos($shell, 'data-bs-target="#impersonate-modal"'),
    $fails,
    $checks
);

/*
 *     And the sub the JS fetches is a method the page actually declares.
 *     FOGPageManager only appends 'Ajax' when the BASE method exists, so
 *     both have to be there or the modal body arrives as a whole page.
 */
check(
    'the JS fetches a sub the page does not implement',
    false !== strpos($js, 'sub=startModal'),
    $fails,
    $checks
);
foreach (['startModal', 'startModalAjax'] as $method) {
    check(
        "the page does not declare $method()",
        false !== strpos($php, "function $method("),
        $fails,
        $checks
    );
}

/*
 * (c) DELEGATED BINDING, and the file is actually served.
 */
/*
 *     EVERY handler, not just one. A first cut asserted only that
 *     '$(document).on(' appeared somewhere, and the mutation that turned the
 *     click handler into a direct bind stayed GREEN -- the modal's own
 *     delegated handler satisfied it. An assertion that some other line
 *     happens to satisfy is not an assertion about the line that matters.
 */
preg_match_all(
    '#\$\((?!document\))([A-Za-z_]+)\)\s*\.on\(#',
    $js,
    $direct
);
check(
    'the JS binds directly on ' . implode(', ', $direct[1])
    . ' -- the picker is fetched after this file runs, and'
    . ' #ajaxPageWrapper is replaced on every in-app navigation, so a'
    . ' direct bind is lost',
    0 === count($direct[0]),
    $fails,
    $checks
);
check(
    'the JS has fewer than two delegated handlers, so one of the modal'
    . ' fetch and the send click is bound some other way',
    2 <= substr_count($js, '$(document).on('),
    $fails,
    $checks
);
check(
    'fog.impersonate.js is not in the authenticated asset list, so it never'
    . ' loads and every control above is inert',
    false !== strpos($assets, "'js/fog/fog.impersonate.js'"),
    $fails,
    $checks
);

/*
 * (d) ONE SURFACE. Matched as the FIRST statement in index(), because
 *     unconditional is the contract: home is where both answers lead, so a
 *     canStart() branch could only pick which message to flash on the way,
 *     and "you may not impersonate" is the wrong thing to tell somebody who
 *     very possibly may. A redirect further down, after a render, would
 *     satisfy a looser search while emitting the whole second surface first.
 */
check(
    'ImpersonateManagement::index() does not redirect as its first act, so'
    . ' the picker has a second surface -- a second copy of the candidate'
    . ' filtering and the ids the JS binds, free to drift from this one',
    (bool)preg_match(
        '#function\s+index\s*\([^)]*\)\s*\{\s*self::redirect\(#',
        $php
    ),
    $fails,
    $checks
);

if (count($fails)) {
    fwrite(STDERR, 'FAIL (' . count($fails) . " of $checks checks)\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

fwrite(STDOUT, "PASS ($checks checks)\n");
exit(0);
