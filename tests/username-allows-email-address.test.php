<?php
/**
 * A username may be an email address, and an account can be saved under it.
 *
 * Reported on the forum (topic 18239): an OIDC provider sends the email
 * address as the username claim. Just-in-time provisioning creates the
 * account with that name, because it writes the row directly. Every later
 * save of the account then failed, because the user form refused "@". An
 * admin could not pre-create the account either, for the same reason.
 *
 * WHAT IS PINNED, and the failure each one catches:
 *
 *  1. The form rule accepts an email address, and still refuses what it
 *     refused before: a leading or trailing separator, a doubled separator,
 *     a name too short, a character outside the set.
 *  2. The login rule (User::PATTERN) accepts the same names. Without it an
 *     admin can create "bob@example.com" and nobody can sign in as it.
 *  3. The form rule exists once. It was four literal copies -- two in the
 *     rendered beRegexTo attributes, two in the POST handlers -- so a fix to
 *     one copy left the browser and the server disagreeing.
 *  4. userGeneralPost() excludes the account's own row from the duplicate
 *     check. The form lowercases the name, and users.uName compares
 *     case-insensitively, so an IdP name such as "Rahman@Example.com" found
 *     ITSELF and every save answered "A user already exists with this name".
 *     This one is a source check: the collation lives in MySQL, which no
 *     test here has.
 *
 * Usage: php tests/username-allows-email-address.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('username-allows-email-address');

$t = new FogChecks();

$pageFile = dirname(__DIR__) . '/packages/web/src/Pages/UserManagement.php';
$pageSrc = file_get_contents($pageFile);
$strip = function ($src) {
    $src = preg_replace('#/\*.*?\*/#s', '', $src);
    return preg_replace('#(^|\s)//[^\n]*#', '$1', $src);
};
$code = $strip($pageSrc);

$accept = [
    'rahman@example.com',
    'first.last@uni.edu.tr',
    'fog',
    'john doe',
    'a_b-c',
];
$reject = [
    '@example.com',
    'rahman@',
    'ab',
    'a..b',
    'bad!name',
];

// ---------------------------------------------------------------------------
// 1. The form rule.
// ---------------------------------------------------------------------------
$formRule = defined('\FOG\Pages\UserManagement::USERNAME_REGEX')
    ? '/' . \FOG\Pages\UserManagement::USERNAME_REGEX . '/'
    : null;
$t->check('UserManagement::USERNAME_REGEX is defined', null !== $formRule);
foreach ($accept as $name) {
    $t->check(
        "the form rule accepts '$name'",
        null !== $formRule && 1 === preg_match($formRule, $name)
    );
}
foreach ($reject as $name) {
    $t->check(
        "the form rule still refuses '$name'",
        null !== $formRule && 0 === preg_match($formRule, $name)
    );
}

// ---------------------------------------------------------------------------
// 2. The login rule.
// ---------------------------------------------------------------------------
foreach (['rahman@example.com', 'Rahman@Example.com'] as $name) {
    $t->check(
        "User::PATTERN accepts '$name'",
        1 === preg_match(\FOG\Items\User::PATTERN, $name)
    );
}
foreach (['@example.com', 'bad!name'] as $name) {
    $t->check(
        "User::PATTERN still refuses '$name'",
        0 === preg_match(\FOG\Items\User::PATTERN, $name)
    );
}

// ---------------------------------------------------------------------------
// 3. One copy of the form rule.
// ---------------------------------------------------------------------------
$t->check(
    'the username regex is written out once, in the constant',
    1 === substr_count($code, '[A-Za-z\d][\w\s\-\.')
);
$t->check(
    'both forms and both POST handlers read USERNAME_REGEX',
    4 === substr_count($code, 'self::USERNAME_REGEX')
);

// ---------------------------------------------------------------------------
// 4. The duplicate check on edit skips the account being edited.
// ---------------------------------------------------------------------------
$body = '';
if (preg_match(
    '/function userGeneralPost\(\).*?\n    }\n/s',
    $code,
    $m
)) {
    $body = $m[0];
}
$t->check('UserManagement::userGeneralPost() is found', '' !== $body);
$t->check(
    "userGeneralPost() passes the account's own id to exists()",
    (bool)preg_match(
        '/->exists\(\s*\$user\s*,\s*\$this->obj->get\(\'id\'\)\s*\)/',
        $body
    )
);

$t->finish();
