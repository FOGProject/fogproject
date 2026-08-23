<?php
/**
 * The Create New User form must not ask an API-only account for a password.
 *
 * Two defects, found from one screenshot of ?node=user&sub=add, both of
 * which reach the user only as text and neither of which fails anything.
 *
 * WHAT IS PINNED, and the failure each one catches:
 *
 *  1. validateForm() no longer halves the maximum length. The line read
 *     `parseInt(maxLength) / 2`, which was wrong in both directions: with
 *     no maxlength attribute the default "-1" became -0.5, so the password
 *     field announced "must be between 4 and -0.5 characters"; with a real
 *     one it understated the true limit by half, so the username field
 *     claimed 25 when makeInput() had been handed 50. Nothing enforces a
 *     maximum here, so this was only ever a message -- which is exactly why
 *     it survived: there is no failing submit to notice.
 *  2. With no maximum declared, the message says "at least N" rather than
 *     naming a range whose top half does not exist.
 *  3. addPost() generates a password when the account is API-only and none
 *     was posted, and generates a RANDOM one. This is the load-bearing
 *     check. User::set() bcrypts whatever it is handed, so storing '' does
 *     not produce an unusable hash -- it produces a valid hash OF the empty
 *     string, and password_verify('', $hash) returns true. isAPIOnly()
 *     refuses the sign-in, so nothing visibly breaks; the account is simply
 *     one unticked box away from a blank-password login, for ever.
 *  4. The password is read with an explicit (string) cast. The toggle
 *     DISABLES the field, a disabled input is not submitted at all, and
 *     filter_input() then answers null -- straight into trim(), which is a
 *     PHP 8.1 deprecation that a distro php.ini hides.
 *  5. The client toggle does all three of required/disabled/hide. Dropping
 *     `required` alone leaves the form refusing to submit against fields
 *     nobody can see; dropping `disabled` alone posts '' and defeats 3;
 *     dropping the hide leaves the question the user actually asked.
 *  6. FOG_BCACHE_VER moved, or the browser keeps the old fog.common.js and
 *     the old fog.user.add.js and every one of these fixes is invisible.
 *
 * Usage: php tests/apionly-password-and-validation.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('apionly-password-and-validation');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$commonSrc = file_get_contents($web . '/management/js/fog/fog.common.js');
$addJsSrc = file_get_contents($web . '/management/js/fog/user/fog.user.add.js');
$pageSrc = file_get_contents($web . '/lib/pages/usermanagement.page.php');
$sysSrc = file_get_contents($web . '/lib/fog/system.class.php');

// Comments carry the words this file is looking for, so every source check
// below runs against a comment-stripped copy. Without this the fix and a
// comment describing the fix are indistinguishable -- a gate that passes on
// its own documentation, which this repo has shipped before.
$strip = function ($src) {
    $src = preg_replace('#/\*.*?\*/#s', '', $src);
    return preg_replace('#(^|\s)//[^\n]*#', '$1', $src);
};
$common = $strip($commonSrc);
$addJs = $strip($addJsSrc);
$page = $strip($pageSrc);

// ---------------------------------------------------------------------------
// 1/2. The length message.
// ---------------------------------------------------------------------------
$t->check(
    'validateForm() no longer halves maxlength',
    false === strpos($common, 'parseInt(maxLength) / 2')
);
$t->check(
    'maxLength is parsed as-is',
    (bool)preg_match('/maxLength = parseInt\(maxLength\);/', $common)
);
$t->check(
    'no maximum declared means the message says "at least"',
    (bool)preg_match(
        '/if \(maxLength < 0\) \{\s*'
        . "invalidReason = 'Field must be at least ' \\+ minLength/s",
        $common
    )
);
$t->check(
    'the range wording survives for fields that DO declare a maximum',
    false !== strpos(
        $common,
        "'Field must be between ' + minLength + ' and ' + maxLength"
    )
);
$t->check(
    'the equal-length wording is untouched',
    false !== strpos($common, "'Field must be ' + minLength + ' characters'")
);
// ---------------------------------------------------------------------------
// 3/4. The server side.
// ---------------------------------------------------------------------------
$t->check(
    'addPost() generates a password only when the account is API-only',
    (bool)preg_match(
        "/if \(\\\$apionly && '' === \\\$password\) \{/",
        $page
    )
);
$t->check(
    'and generates it from the CSPRNG, not from a constant',
    (bool)preg_match(
        "/if \(\\\$apionly && '' === \\\$password\) \{\s*"
        . "\\\$password = bin2hex\(random_bytes\(\d+\)\);/s",
        $page
    )
);
$t->check(
    'it is at least 128 bits of randomness',
    (bool)preg_match('/bin2hex\(random_bytes\((\d+)\)\)/', $page, $m)
    && (int)$m[1] >= 16
);
$t->check(
    'the empty string is never what gets stored',
    !preg_match(
        "/\\\$apionly[^\n]*\n\s*\\\$password = '';/",
        $page
    )
);
$t->check(
    'the posted password is cast before trim()',
    (bool)preg_match(
        "/\\\$password = trim\(\s*\(string\)filter_input\(INPUT_POST, "
        . "'password'\)\s*\);/s",
        $page
    )
);

// ---------------------------------------------------------------------------
// 5. The client toggle.
// ---------------------------------------------------------------------------
$t->check(
    'the toggle listens for a change on the API-only box',
    (bool)preg_match(
        "/\\\$\(document\)\.on\('change', '#apionly'/",
        $addJs
    )
);
$t->check(
    'it targets both password fields',
    false !== strpos($addJs, "$('#password, #password_name')")
);
$t->check(
    'it clears required, so validateForm() stops blocking on hidden fields',
    (bool)preg_match("/\.prop\('required', !apiOnly\)/", $addJs)
);
$t->check(
    'it disables them, so nothing is posted and the server generates one',
    (bool)preg_match("/\.prop\('disabled', apiOnly\)/", $addJs)
);
$t->check(
    'it hides the row',
    (bool)preg_match("/row\.toggleClass\('d-none', apiOnly\)/", $addJs)
);
$t->check(
    'it clears any value left behind',
    (bool)preg_match("/field\.val\(''\)/", $addJs)
);
$t->check(
    'it clears a stale invalid marker rather than hiding an error',
    false !== strpos($addJs, "field.removeClass('is-invalid')")
    && false !== strpos($addJs, "span.invalid-feedback")
);
$t->check(
    'it runs once on load, so a re-rendered ticked box starts consistent',
    (bool)preg_match("/\}\)\.trigger\('change'\);/", $addJs)
);
$t->check(
    'the note explaining where the credential comes from exists',
    false !== strpos($pageSrc, "id=\"apionly-password-note\"")
    && false !== strpos($addJs, "#apionly-password-note")
);
$t->check(
    'the note ships hidden',
    (bool)preg_match(
        '/class="form-text d-none" \'\s*\. \'id="apionly-password-note"/',
        $pageSrc
    )
    || (bool)preg_match(
        '/form-text d-none[^\n]*apionly-password-note/',
        $pageSrc
    )
);

// ---------------------------------------------------------------------------
// 6. Both labels say which credential they govern, and the cache moved.
// ---------------------------------------------------------------------------
$t->check(
    'the API Enable label names the credentials it actually gates',
    2 === preg_match_all(
        '/legacy fog-user-token header and HTTP Basic/',
        $pageSrc
    )
);
$t->check(
    'FOG_BCACHE_VER is at least 306',
    (bool)preg_match("/define\('FOG_BCACHE_VER', (\d+)\)/", $sysSrc, $m)
    && (int)$m[1] >= 306
);

$t->finish();
