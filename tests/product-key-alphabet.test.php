<?php
/**
 * A real Windows product key has to be accepted, N and all.
 *
 * `FOGBase::productKeyIsValid()` is the single gate every product-key entry
 * path passes through -- the host and group forms, the Windows Key page, the
 * REST route, and iPXE key registration -- and `productKeyResolve()` throws
 * when it says no. It used to check the 24-character Base24 alphabet, which
 * is the set used to DECODE a pre-Windows-8 key into binary, not the set a
 * key is TYPED with. The difference is one letter, N, and Windows 8 and later
 * put N in the key itself, so every modern Enterprise or KMS key carrying one
 * was refused at entry (forums topic 18252, against 1.6.0-beta.5384).
 *
 * The keys below are Microsoft's own published KMS client setup keys. They
 * are the corpus precisely because they are real, public, and outside this
 * project's control: a charset that rejects one of them is wrong however
 * defensible it looks.
 *
 * The JS half is checked as source. `$.productKeyMask()` in fog.common.js
 * carries its own copy of the class so the browser can mask a key without a
 * round trip, and a copy that disagrees shows a stored, valid key as an
 * unrecognized blob -- fully bulleted, with no first and last group. There is
 * no behavior to assert without a browser, so what is asserted is that the
 * two spellings are character-for-character the same.
 *
 * Usage: php tests/product-key-alphabet.test.php
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

$webroot = dirname(__DIR__) . '/packages/web';

require_once $webroot . '/src/Base/FOGBase.php';

$base = 'FOG\Base\FOGBase';

$checks = 0;
$failures = [];
$check = static function ($what, $ok) use (&$checks, &$failures) {
    $checks++;
    if (!$ok) {
        $failures[] = $what;
    }
};

// Microsoft's KMS client setup keys. Every one of these is a key an admin can
// legitimately paste into FOG, and the first four carry an N.
$valid = [
    'Windows 10/11 Pro' => 'W269N-WFGWX-YVC9B-4J6C9-T83GX',
    'Windows 10/11 Enterprise' => 'NPPR9-FWDCX-D2C8J-H872K-2YT43',
    'Windows 10/11 Education' => 'NW6C2-QMPVW-D7KKK-3GKT6-VCFB2',
    'Windows 10/11 Home' => 'TX9XD-98N7V-6WMQ6-BX7FG-H8Q99',
    'Windows 7 Professional' => 'FJ82H-XT6CR-J8D7P-XQJJ2-GPDD4',
];
foreach ($valid as $what => $key) {
    $check(
        $what . " key is accepted ($key)",
        true === $base::productKeyIsValid($key)
    );
    // Entry normalization is part of the same contract: the forms hand the
    // value over grouped, unhyphenated or lowercased depending on how it was
    // pasted, and all three have to reach the same verdict.
    $check(
        $what . ' key is accepted unhyphenated and lowercased',
        true === $base::productKeyIsValid(strtolower(str_replace('-', '', $key)))
    );
    // The masked display keeps the outer groups only for a key the validator
    // recognizes, so a charset regression shows up here as a fully bulleted
    // field rather than an error.
    $check(
        $what . ' key masks to its first and last group',
        $base::productKeyMask($key)
        === substr($key, 0, 5) . '-•••••-•••••-•••••-' . substr($key, -5)
    );
}

// The excluded characters stay excluded. These are the ones Microsoft leaves
// out because they misread as one another, and accepting them would make a
// typo look like a key.
foreach (['A', 'E', 'I', 'O', 'U', 'L', 'S', 'Z', '0', '1'] as $char) {
    $check(
        "a key containing $char is rejected",
        false === $base::productKeyIsValid(str_repeat($char, 25))
    );
    // One bad character in an otherwise real key is the realistic case.
    $check(
        "a real key with one $char substituted is rejected",
        false === $base::productKeyIsValid(
            $char . 'PPR9-FWDCX-D2C8J-H872K-2YT43'
        )
    );
}

// Length is still exact, and an empty value is not a key.
$check(
    'a 24-character key is rejected',
    false === $base::productKeyIsValid('W269N-WFGWX-YVC9B-4J6C9-T83G')
);
$check(
    'a 26-character key is rejected',
    false === $base::productKeyIsValid('W269N-WFGWX-YVC9B-4J6C9-T83GXX')
);
$check('an empty value is not a key', false === $base::productKeyIsValid(''));

// The browser's copy of the class has to be the same class.
$php = (string)@file_get_contents($webroot . '/src/Base/FOGBase.php');
$js = (string)@file_get_contents(
    $webroot . '/management/js/fog/fog.common.js'
);
$phpClass = preg_match('~\^\[([A-Z0-9]+)\]\{25\}\$~', $php, $m) ? $m[1] : '';
$jsClass = preg_match('~\^\[([A-Z0-9]+)\]\{25\}\$~', $js, $m) ? $m[1] : '';
$check('FOGBase spells out a 25-character product-key class', $phpClass !== '');
$check('fog.common.js spells out a 25-character product-key class', $jsClass !== '');
$check(
    "the browser's product-key charset matches the server's"
    . " (php=$phpClass js=$jsClass)",
    $phpClass !== '' && $phpClass === $jsClass
);

if (count($failures)) {
    fwrite(STDERR, "FAIL: the product-key alphabet is wrong:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    fwrite(
        STDERR,
        sprintf("%d of %d checks failed\n", count($failures), $checks)
    );
    exit(1);
}

printf("PASS  product-key alphabet accepts real keys: %d checks\n", $checks);
exit(0);
