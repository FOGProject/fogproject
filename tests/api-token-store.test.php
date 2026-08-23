<?php
/**
 * The API token store must stay a SEPARATE credential from users.uAPIToken.
 *
 * ADR 0027. The whole value of an APIToken comes from four properties that
 * uAPIToken deliberately does not have: it is hashed at rest, shown once,
 * individually revocable, and there can be many per user. Each of those is
 * one line away from being lost, and losing any of them is silent -- a token
 * that authenticates still authenticates whether or not its plaintext also
 * sits in a column, whether or not its per-token kill switch is consulted,
 * and whether or not Bearer still takes the old plaintext token as well.
 *
 * That silence is the reason this file exists. The probes that verified the
 * feature end to end (scripts/background_scripts/probe_api_token_store.sh and
 * probe_api_token_lifecycle.php) need a live database and a running server,
 * so they cannot run in CI. These checks pin the same contract with no
 * database, in the suite's usual style.
 *
 * WHAT IS PINNED, and the failure each one catches:
 *
 *  1. Bearer accepts APIToken and NOTHING else. The regression to fear is a
 *     well-meaning "also accept uAPIToken for compatibility" -- which sounds
 *     harmless and quietly restores a plaintext, UI-visible, non-revocable
 *     credential as a standalone Bearer secret. #1324 shipped exactly that
 *     shape; withdrawing it was the point of ADR 0027.
 *  2. The credential is not base64-decoded. The legacy pair is base64 and
 *     this is not, and mixing them is unfixable rather than merely wrong:
 *     hex is itself valid base64, so base64_decode($hex, true) SUCCEEDS with
 *     garbage. Encoded and raw are therefore indistinguishable and accepting
 *     both would need two lookups per request.
 *  3. Only the hash is stored. Pinned by asserting the column set and that
 *     generate() sets no field from the plaintext except through hashToken().
 *  4. The hash column is UNIQUE. Without it two rows can carry one hash and
 *     load('hash') silently picks one -- so revoking a token could leave a
 *     working duplicate.
 *  5. Tokens carry >= 256 bits of CSPRNG output. This is the invariant the
 *     decision NOT to salt rests on; shortening the token or making it
 *     user-choosable voids that reasoning, and this is where that gets
 *     noticed.
 *  6. Deleting a user deletes its tokens, AND resolve() fails closed on an
 *     ownerless token. Both, because the second is what makes a mistake in
 *     the first survivable.
 *  7. APIToken is not a REST class. A token-management API surface lets one
 *     leaked credential mint another, so revoking the leaked one fixes
 *     nothing.
 *  8. The token form posts do not silently clobber the legacy card. Two
 *     controls sharing one endpoint is the GH-987 defect class: the fields
 *     the OTHER form owns are absent from the request, get read as 0, and
 *     the user is told it saved.
 *
 * Usage: php tests/api-token-store.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('api-token-store');

$t = new FogChecks();

$web = dirname(__DIR__) . '/packages/web';
$modelSrc = file_get_contents($web . '/lib/fog/apitoken.class.php');
$routeSrc = file_get_contents($web . '/lib/router/route.class.php');
$schemaSrc = file_get_contents($web . '/commons/schema.php');
$pageSrc = file_get_contents($web . '/lib/pages/usermanagement.page.php');

// ---------------------------------------------------------------------------
// 1. Bearer accepts APIToken and nothing else.
// ---------------------------------------------------------------------------
$bearerBody = '';
if (preg_match(
    '/private static function _testBearer\(\)\s*\{(.*?)\n    \}/s',
    $routeSrc,
    $m
)) {
    $bearerBody = $m[1];
}

$t->check('_testBearer() exists', '' !== $bearerBody);
$t->check(
    '_testBearer() resolves through APIToken',
    (bool)preg_match('/APIToken::resolve\s*\(/', $bearerBody)
);
// The old shape looked the user up by uAPIToken. Any of these reappearing in
// this function means the withdrawn credential is back.
foreach (['uAPIToken', "'token'", 'getUserByToken', 'APIToken::class'] as $gone) {
    $t->check(
        "_testBearer() does not reach for $gone",
        false === strpos($bearerBody, $gone)
    );
}

// ---------------------------------------------------------------------------
// 2. The presented credential is passed through verbatim.
// ---------------------------------------------------------------------------
$credBody = '';
if (preg_match(
    '/private static function _bearerCredential\(\)\s*\{(.*?)\n    \}/s',
    $routeSrc,
    $m
)) {
    $credBody = $m[1];
}
$t->check('_bearerCredential() exists', '' !== $credBody);
$t->check(
    '_bearerCredential() does not base64-decode',
    false === strpos($credBody, 'base64_decode')
);

// Behavioural, not just textual: drive the real function through $_SERVER.
$cred = new \ReflectionMethod('FOG\\Route', '_bearerCredential');
$cred->setAccessible(true);

$sample = 'fog_' . str_repeat('ab', 64);
$cases = [
    // header value                        expected return
    ['Bearer ' . $sample,                  $sample],
    ['bearer ' . $sample,                  $sample],
    ['Bearer   ' . $sample . '  ',         $sample],
    // Presented but empty: an attempt, so '' rather than null.
    ['Bearer',                             ''],
    ['Bearer ',                            ''],
    // Not the Bearer scheme -> not our credential, fall through.
    ['Bearer' . $sample,                   null],
    ['Basic ' . base64_encode('a:b'),      null],
    ['',                                   null]
];
foreach ($cases as $i => $case) {
    list($header, $want) = $case;
    $_SERVER['HTTP_AUTHORIZATION'] = $header;
    unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    $got = $cred->invoke(null);
    $t->check(
        sprintf(
            '_bearerCredential(%s) === %s',
            var_export($header, true),
            var_export($want, true)
        ),
        $got === $want
    );
}
unset($_SERVER['HTTP_AUTHORIZATION']);

// A base64 of a plausible token must come back UNCHANGED. If anyone
// reintroduces decoding, this is the check that fails.
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode($sample);
$t->check(
    'a base64 value is returned as sent, not decoded',
    $cred->invoke(null) === base64_encode($sample)
);
unset($_SERVER['HTTP_AUTHORIZATION']);

// ---------------------------------------------------------------------------
// 3/5. Only the hash is stored; the token is CSPRNG and long enough.
// ---------------------------------------------------------------------------
$t->check(
    'hashToken() is sha256',
    (bool)preg_match("/hash\(\s*'sha256'/", $modelSrc)
);
$t->check(
    'generate() uses random_bytes',
    (bool)preg_match('/random_bytes\(\s*(\d+)\s*\)/', $modelSrc, $rb)
);
$bytes = isset($rb[1]) ? (int)$rb[1] : 0;
$t->check(
    "generate() draws >= 32 bytes of entropy (got $bytes)",
    $bytes >= 32
);
$t->check(
    'generate() stores the hash, never the token',
    (bool)preg_match("/->set\(\s*'hash',\s*self::hashToken\(\\\$token\)\s*\)/", $modelSrc)
    && !preg_match("/->set\(\s*'[a-z]+',\s*\\\$token\s*\)/i", $modelSrc)
);
$t->check(
    'generate() refuses to hand back a token whose row did not save',
    (bool)preg_match('/if\s*\(!\$row->save\(\)\)\s*\{\s*(\/\/[^\n]*\n\s*)*return false;/', $modelSrc)
);

// No column may hold the plaintext. `hash` is the only secret-bearing field.
$fields = [];
if (preg_match('/\$databaseFields = \[(.*?)\];/s', $modelSrc, $m)) {
    preg_match_all("/'([a-zA-Z]+)' =>/", $m[1], $f);
    $fields = $f[1];
}
$t->check('model declares a hash field', in_array('hash', $fields, true));
$t->check(
    'model declares no plaintext token field',
    !in_array('token', $fields, true) && !in_array('secret', $fields, true)
);
$t->check(
    'model declares a per-token enabled field',
    in_array('enabled', $fields, true)
);

// ---------------------------------------------------------------------------
// 4. The hash column is unique, so revocation cannot leave a twin.
// ---------------------------------------------------------------------------
$create = '';
if (preg_match('/CREATE TABLE IF NOT EXISTS `apiTokens`(.*?)ROW_FORMAT/s', $schemaSrc, $m)) {
    $create = $m[1];
}
$t->check('schema creates apiTokens', '' !== $create);
$t->check(
    'apiTokens.atHash is UNIQUE',
    (bool)preg_match('/UNIQUE KEY.*?atHash/s', $create)
);
$t->check(
    'apiTokens.atHash is wide enough for sha256 hex',
    (bool)preg_match('/atHash`?\s+CHAR\(64\)/i', $create)
);
$t->check(
    'apiTokens is indexed by owner',
    (bool)preg_match('/KEY.*?atUserID/s', $create)
);

// ---------------------------------------------------------------------------
// 6. Cascade on user delete, and fail closed if it is ever missed.
// ---------------------------------------------------------------------------
// Anchored on the $findWhere assignment rather than on `case 'user':`,
// which also matches an unrelated switch earlier in the file.
$userCase = '';
if (preg_match(
    '/case \'user\':\s*\n\s*\$findWhere = \[\'userID\' => \$itemIDs\];(.*?)break;/s',
    $routeSrc,
    $m
)) {
    $userCase = $m[1];
}
$t->check('the user delete cascade block was found', '' !== $userCase);
$t->check(
    'deleting a user cascades to its api tokens',
    (bool)preg_match('/[\'"]apitoken[\'"]\s*=>/i', $userCase)
);
$t->check(
    'resolve() refuses a token whose owner is gone',
    (bool)preg_match(
        '/\$user\s*=\s*self::getClass\(\s*\'User\'.*?if\s*\(!\$user->isValid\(\)\)\s*\{(.*?)return null;/s',
        $modelSrc
    )
);
$t->check(
    'resolve() consults the per-token enabled flag',
    (bool)preg_match("/\\\$row->get\('enabled'\)/", $modelSrc)
);

// ---------------------------------------------------------------------------
// 7. Not reachable as a REST resource.
// ---------------------------------------------------------------------------
$validClasses = [];
if (preg_match('/\$validClasses = \[(.*?)\];/s', $routeSrc, $m)) {
    preg_match_all("/'([a-z0-9_]+)'/i", $m[1], $c);
    $validClasses = array_map('strtolower', $c[1]);
}
$t->check('route class list was parsed', count($validClasses) > 10);
$t->check(
    'apitoken is not a REST class',
    !in_array('apitoken', $validClasses, true)
);

// ---------------------------------------------------------------------------
// 8. The token form does not clobber the legacy card (GH-987 shape).
// ---------------------------------------------------------------------------
$apiPost = '';
if (preg_match('/function userAPIPost\((.*?)\n    \}/s', $pageSrc, $m)) {
    $apiPost = $m[1];
}
$t->check('userAPIPost() exists', '' !== $apiPost);
$t->check(
    'userAPIPost() hands off to the token handler before touching uAPIToken',
    (bool)preg_match(
        '/_userBearerTokensPost\(.*?\)(.*?)return;/s',
        $apiPost
    )
);
// The READ of the legacy field, not the word -- it also appears in the
// comment that explains why the ordering matters.
$legacyPos = strpos($apiPost, "\$_POST['apienabled']");
$bearerPos = strpos($apiPost, '$this->_userBearerTokensPost(');
$t->check(
    'the token handler runs first, so absent legacy fields are never read as 0',
    false !== $bearerPos
    && (false === $legacyPos || $bearerPos < $legacyPos)
);
// Posted token ids must be constrained to the user whose page this is,
// otherwise one user's form deletes another's credentials. The manage loop
// iterates the OWNER'S tokens and matches posted ids against them, rather
// than loading whatever id was posted.
$t->check(
    'token management iterates the owning user\'s tokens',
    (bool)preg_match(
        '/getClass\(\'APITokenManager\'\)\s*\n?\s*->forUser\(\$uid\)/',
        $pageSrc
    )
);
$mgrSrc = file_get_contents($web . '/lib/fog/apitokenmanager.class.php');
$t->check(
    'forUser() filters on the owner column',
    (bool)preg_match('/WHERE `atUserID` = :uid/', $mgrSrc)
);

// ---------------------------------------------------------------------------
// 9. The two form-plumbing bugs a real deploy found, which no source read
// would have.
//
// Both were invisible to every other check in this file: the page rendered,
// the buttons drew, and clicking them did nothing whatsoever. Pinned because
// the failure mode is silence -- no error, no log line, no failed request.
// ---------------------------------------------------------------------------
$jsSrc = file_get_contents(
    $web . '/management/js/fog/user/fog.user.edit.js'
);

// (a) FOG has no generic binding that picks a tab form up. disableFormDefaults()
//     only suppresses the native submit; every card is wired by hand in its
//     page's JS, and an unwired one renders perfectly and does nothing.
$t->check(
    'the token card form is wired in JS',
    false !== strpos($jsSrc, '#user-apitoken-form')
);
$t->check(
    'the save button is wired',
    false !== strpos($jsSrc, '#apitoken-send')
);
$t->check(
    'the issue button is wired',
    false !== strpos($jsSrc, '#issuetoken')
);

// (b) processForm() posts `new FormData(form)`, and FormData omits submit
//     buttons unless the submitter is handed to it. So the save discriminator
//     has to be a real field, and creation cannot be a submit button at all.
$t->check(
    'the save discriminator is a posted field, not a button name',
    false !== strpos($pageSrc, "filter_input(INPUT_POST, 'tokenaction')")
    && false !== strpos($pageSrc, 'name="tokenaction" value="manage"')
);
$t->check(
    'the issue control is not a submit button',
    false !== strpos($pageSrc, 'type="button" id="issuetoken"')
);
$t->check(
    'issuing a token is its own CSRF-gated endpoint',
    (bool)preg_match(
        '/public function issueAPIToken\(\)\s*\{\s*(\/\/[^\n]*\n\s*)*self::checkAuthAndCSRF\(\);/',
        $pageSrc
    )
);

// The plaintext must reach the browser exactly once, in the reply to the
// click that created it. Anything that persists it across renders -- a
// session key, a data attribute -- defeats show-once.
$t->check(
    'the plaintext is not carried in the session',
    false === strpos($pageSrc, 'fog_new_api_token')
);

$t->finish();
