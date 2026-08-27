<?php
/**
 * An API call with no usable credential must answer 401, and must not
 * write an audit row for having presented nothing.
 *
 * Route's three auth arms all mean the same thing when they fail -- the
 * credential was missing or wrong -- and RFC 7235 spells that 401. 403
 * means "I know who you are and you may not", which nothing here can have
 * established, because all three run BEFORE any principal is bound.
 *
 * _testToken() was the one arm answering 403, and it runs first, so it
 * decided the response for every unauthenticated request: a call with no
 * headers at all came back 403 while a merely wrong Bearer token came back
 * 401. Anybody debugging a client from the status code was sent looking for
 * a permission rather than a credential.
 *
 * WHAT IS PINNED:
 *
 *  1. All three arms answer 401. Pinned as "no arm sends 403" rather than
 *     by naming them, so a fourth auth mechanism cannot reintroduce the
 *     inconsistency by being written to a different rule.
 *  2. The audit row is conditional on something having been presented.
 *     _testAuth() already had that rule and _testToken() did not, and
 *     because _testToken() runs first the ordinary no-credential request
 *     wrote a rejection row every time -- 73 api-token rows against 1
 *     user-token row on the lab server, which is this method recording
 *     nobody presenting anything. Rejections are worth reading because they
 *     are rare.
 *  3. The presented token is still never written into the row. That is
 *     #1261/#1262's mistake and it must not come back through an edit that
 *     is only meant to move a status code.
 *  4. No WWW-Authenticate header. RFC 7235 wants one on a 401; it is also
 *     what makes a browser throw a native basic-auth prompt, and the
 *     management UI reaches these routes over XHR. The omission is a
 *     decision, so it is pinned rather than left to be "fixed" later.
 *
 * Usage: php tests/api-unauthenticated-401.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('api-unauthenticated-401');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$routeSrc = file_get_contents($web . '/src/Router/Route.php');

$bodyOf = function ($src, $signature, $len = 6000) {
    $at = strpos($src, $signature);
    return false === $at ? '' : substr($src, $at, $len);
};

$tokenBody = $bodyOf($routeSrc, 'private static function _testToken()');
$bearerBody = $bodyOf($routeSrc, 'private static function _testBearer()');
$authBody = $bodyOf($routeSrc, 'private static function _testAuth()');

$t->check('the three auth arms were found', '' !== $tokenBody
    && '' !== $bearerBody && '' !== $authBody);

// 1. Every arm answers 401 and none answers 403.
foreach ([
    '_testToken' => $tokenBody,
    '_testBearer' => $bearerBody,
    '_testAuth' => $authBody
] as $name => $body) {
    $t->check(
        "$name() answers HTTP_UNAUTHORIZED",
        false !== strpos($body, 'HTTPResponseCodes::HTTP_UNAUTHORIZED')
    );
    // Comments stripped: the docblocks here discuss 403 on purpose, and a
    // gate that trips on prose about itself is a gate that gets deleted.
    $code = preg_replace('#//[^\n]*#', '', $body);
    $t->check(
        "$name() does not answer HTTP_FORBIDDEN",
        false === strpos($code, 'HTTPResponseCodes::HTTP_FORBIDDEN')
    );
}

// 2. Nothing presented is not an attempt, in BOTH token arms.
$t->check(
    '_testToken() only audits when a token was actually presented',
    (bool)preg_match(
        "/if \('' !== \\\$passtoken\) \{\s*Audit::record\(/s",
        $tokenBody
    )
);
$t->check(
    '_testAuth() keeps the same rule for the user token',
    (bool)preg_match(
        "/if \('' !== \\\$usertoken\) \{\s*Audit::record\(/s",
        $authBody
    )
);
// The early return is what makes the audit block reachable only on failure.
$t->check(
    '_testToken() returns on a match rather than inverting the compare',
    (bool)preg_match(
        "/if \(hash_equals\(\(string\)self::\\\$_token, \(string\)"
        . "\\\$passtoken\)\) \{\s*return;\s*\}/s",
        $tokenBody
    )
);

// 3. The credential is never the audit payload.
foreach (['_testToken' => $tokenBody, '_testBearer' => $bearerBody] as $name => $body) {
    $t->check(
        "$name() does not put the presented token in the audit row",
        false === strpos($body, "'text' => \$passtoken")
        && false === strpos($body, "'subjectLabel' => \$passtoken")
        && false === strpos($body, "'text' => \$token")
        && false === strpos($body, "'subjectLabel' => \$token")
    );
}

// 4. The WWW-Authenticate omission is deliberate.
$t->check(
    'no arm sends a WWW-Authenticate header',
    false === stripos(
        preg_replace('#//[^\n]*#', '', $tokenBody . $bearerBody . $authBody),
        'WWW-Authenticate'
    )
);

$t->finish();
