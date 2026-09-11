<?php
/**
 * A credential must reach passwordValidate() as the operator typed it.
 *
 * Registration::_fullReg() used to read username and password out of the array
 * FOGBase::stripAndDecode() had rewritten. That helper closes with
 * Initiator::e() -- htmlspecialchars(ENT_QUOTES) -- which is right for a value
 * about to be rendered into a page and wrong for one about to be compared
 * against a password hash. A password containing & < > " or ' arrived at
 * password_verify() as its entity form and could never match, so the account
 * could not register-with-deploy while signing in perfectly from the web UI
 * and answering '#!ok' at service/checkcredentials.php -- which is exactly the
 * contradiction reported in forums topic 18228.
 *
 * MOST OF THIS IS EXECUTED, NOT SCANNED. decodeCredential() is called for real
 * with the values FOS actually sends, so a gutted implementation goes red.
 * Only the two read SITES have to be pinned by source: whether _fullReg() reads
 * the mangled array or the raw request is a fact about one line inside a method
 * that needs a whole HTTP request to reach, and the end-to-end proof of that
 * lives in scripts/background_scripts/prove_registration_deploy_login_18228.sh.
 *
 * No database.
 *
 * Usage: php tests/credentials-are-not-html-escaped.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
$web = $root . '/packages/web';

require $web . '/commons/init.php';
new Initiator();

$fails = array();

/**
 * Records a failed assertion.
 *
 * @param array  $fails collected failures
 * @param string $why   what did not hold
 *
 * @return void
 */
function credFail(array &$fails, $why)
{
    $fails[] = $why;
}

/**
 * Reads a file with its comments stripped.
 *
 * A gate that matches inside a comment passes on code that no longer does the
 * thing -- including on the comment this very fix added, which names every
 * symbol below.
 *
 * @param string $path the file to read
 *
 * @return string the file's code, comments removed
 */
function credCode($path)
{
    $out = '';
    foreach (token_get_all(file_get_contents($path)) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }
            $out .= $token[1];
            continue;
        }
        $out .= $token;
    }

    return $out;
}

$regPath = $web . '/lib/reg-task/registration.class.php';
$ccPath = $web . '/service/checkcredentials.php';
$reg = credCode($regPath);
$cc = credCode($ccPath);

/*
 * Vacuity guard. Every source assertion below is "this string is present" or
 * "this string is absent"; if a file were emptied, moved or failed to read,
 * the absent-checks would all pass and this suite would report green on a
 * deleted feature.
 */
if (strlen($reg) < 8000) {
    credFail($fails, 'registration.class.php read as only ' . strlen($reg) . ' bytes of code');
}
if (strlen($cc) < 1500) {
    credFail($fails, 'checkcredentials.php read as only ' . strlen($cc) . ' bytes of code');
}

// ---- 1. decodeCredential() itself, executed --------------------------------

$cases = array(
    'plain alphanumeric' => 'Passw0rd',
    'an ampersand' => 'f&g-Pass1',
    'an apostrophe' => "it's-fog1",
    'a double quote' => 'say"fog"1',
    'a less-than' => 'a<bpass1',
    'a greater-than' => 'a>bpass1',
    // Not an HTML special, so it always survived. Kept because the forum
    // thread reported a French keyboard and this is what rules that out as
    // the cause.
    'an accented character' => 'motdepasse' . chr(0xC3) . chr(0xA9),
);
foreach ($cases as $label => $plain) {
    $seen = FOGBase::decodeCredential(base64_encode($plain));
    if ($seen !== $plain) {
        credFail(
            $fails,
            sprintf(
                'decodeCredential() mangled a password containing %s: sent %s, got %s',
                $label,
                var_export($plain, true),
                var_export($seen, true)
            )
        );
    }
}

/*
 * A '+' is in the base64 alphabet and a bare '+' in a urlencoded body decodes
 * back to a space, so the decoder has to restore it -- as stripAndDecode()
 * always has. Without this, swapping the non-strict decode for a strict one
 * turns a mangled login into a REFUSED one, which is a worse regression than
 * the bug being fixed. 'fg>aaP1' encodes to 'Zmc+YWFQMQ=='.
 */
if (FOGBase::decodeCredential('Zmc+YWFQMQ==') !== 'fg>aaP1') {
    credFail($fails, "decodeCredential() failed on base64 containing a '+'");
}
if (FOGBase::decodeCredential('Zmc YWFQMQ==') !== 'fg>aaP1') {
    credFail(
        $fails,
        "decodeCredential() did not restore a '+' that arrived as a space"
    );
}

/*
 * STRICT decoding. base64_decode() without $strict silently drops every
 * character outside the alphabet and always "succeeds", so a corrupted field
 * became a plausible wrong credential rather than a refused one.
 */
foreach (array('not!valid!base64!', '@@@@', "Zm9n\x00YQ==") as $junk) {
    if (false !== FOGBase::decodeCredential($junk)) {
        credFail(
            $fails,
            'decodeCredential() accepted ' . var_export($junk, true)
                . ' instead of refusing it -- the decode is not strict'
        );
    }
}

// ---- 2. the helper it must NOT be, still escaping ---------------------------

/*
 * The fix must not be "make stripAndDecode() stop escaping". That helper feeds
 * values that ARE rendered into pages, so weakening it to fix a login would
 * trade an authentication bug for an injection one. Asserted positively: the
 * escaping is still there, and decodeCredential() is a genuinely different
 * path rather than an alias for it.
 */
$item = array('v' => base64_encode('a<b&c'));
$stripped = FOGBase::stripAndDecode($item);
if (false === strpos($stripped['v'], '&lt;')) {
    credFail(
        $fails,
        'stripAndDecode() no longer HTML-escapes -- the credential fix must not '
        . 'have been made by weakening the shared sanitiser'
    );
}

// ---- 3. the read sites ------------------------------------------------------

/*
 * The whole read is anchored, not just the function name. A grep for
 * "decodeCredential" alone passes on a line whose result is thrown away, and
 * a grep for the closure alone passes if someone re-reads $_REQUEST after it.
 */
$readSite = "\$readCred = function (\$name) {\n"
    . "                return filter_input(INPUT_POST, \$name)\n"
    . "                    ?? filter_input(INPUT_GET, \$name)\n"
    . "                    ?? '';\n"
    . "            };\n"
    . "            \$username = self::decodeCredential(\$readCred('username'));\n"
    . "            \$password = self::decodeCredential(\$readCred('password'));";
if (false === strpos($reg, $readSite)) {
    credFail(
        $fails,
        '_fullReg() no longer reads its credentials through decodeCredential() '
        . 'on the raw request'
    );
}

/*
 * filter_input() reads PHP's ORIGINAL request data, so it is immune to
 * stripAndDecode() rewriting the superglobal in place. Reading the superglobal
 * would reintroduce the bug the moment someone moved the stripAndDecode()
 * call, which is precisely how the first cut of this fix broke working-1.6.
 */
foreach (array('$_REQUEST[\'username\']', '$_REQUEST[\'password\']',
               '$_POST[\'username\']', '$_POST[\'password\']') as $bad) {
    if (false !== strpos($reg, $bad)) {
        credFail(
            $fails,
            'registration reads ' . $bad . ' -- credentials must come from '
            . 'filter_input(), not the superglobal stripAndDecode() rewrites'
        );
    }
}

/*
 * service/checkcredentials.php validates the SAME credential for the SAME
 * caller. The two disagreeing is what made this bug so hard to believe: that
 * endpoint answered '#!ok' for a password registration then rejected. They
 * share the decoder so they cannot drift apart again.
 */
if (false === strpos($cc, "FOGCore::decodeCredential(\$readParam('username'))")
    || false === strpos($cc, "FOGCore::decodeCredential(\$readParam('password'))")
) {
    credFail(
        $fails,
        'checkcredentials.php no longer decodes through the shared '
        . 'decodeCredential(), so it can drift from registration again'
    );
}
if (false !== strpos($cc, 'base64_decode(')) {
    credFail(
        $fails,
        'checkcredentials.php decodes base64 itself again instead of sharing '
        . 'decodeCredential()'
    );
}

// ---- report -----------------------------------------------------------------

if ($fails) {
    fwrite(STDERR, "FAIL\n");
    foreach ($fails as $why) {
        fwrite(STDERR, '  - ' . $why . "\n");
    }
    exit(1);
}
fwrite(STDERR, "PASS credentials-are-not-html-escaped\n");
exit(0);
