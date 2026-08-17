<?php
/**
 * Every package in composer.lock must actually be committed under vendor/.
 *
 * Phase 0 chose committed vendor/ over `composer install` at install time
 * (ADR 0013), which makes the shipped tarball the dependency tree -- there is
 * no step on the customer's server that could fetch a missing package. So a
 * lock entry with no files beside it is not a stale checkout, it is a broken
 * release, and it fails at the first `use` on a live server rather than here.
 *
 * The advisory-policy check is the other half. firebase/php-jwt cannot be
 * installed by Composer 2.10+ without config.policy.advisories.ignore-id
 * naming PKSA-y2cr-5h3j-g3ys: every release that still supports PHP 7.4 is
 * covered by CVE-2025-45769, and the fix landed in 7.0.0, which requires PHP
 * 8.0. Dropping that line does not break anything visible here -- it breaks
 * `composer install` for the next person who regenerates vendor/, with an
 * error that reads like the package is unavailable. The reasoning for
 * accepting the advisory is in docs/refactor-phase2-plan.md; this only pins
 * that the acceptance stays declared.
 *
 * Usage: php tests/vendor-committed.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$web = 'packages/web';
$fails = [];

$lock = json_decode((string) @file_get_contents("$web/composer.lock"), true);
$json = json_decode((string) @file_get_contents("$web/composer.json"), true);
if (!is_array($lock) || !is_array($json)) {
    fwrite(STDERR, "FAIL: cannot read $web/composer.json or composer.lock\n");
    exit(1);
}

// 1. Every locked package is present on disk, with the files it declares.
foreach ($lock['packages'] ?? [] as $pkg) {
    $dir = "$web/vendor/{$pkg['name']}";
    if (!is_dir($dir)) {
        $fails[] = "{$pkg['name']} {$pkg['version']} is in composer.lock but"
            . " $dir is not committed";
        continue;
    }
    foreach ($pkg['autoload']['psr-4'] ?? [] as $ns => $paths) {
        foreach ((array) $paths as $path) {
            if (!is_dir(rtrim("$dir/$path", '/'))) {
                $fails[] = "{$pkg['name']} maps $ns to $path, which is missing"
                    . ' from the committed tree';
            }
        }
    }
}

// 2. The lock is in step with composer.json's requirements.
$locked = [];
foreach ($lock['packages'] ?? [] as $pkg) {
    $locked[$pkg['name']] = true;
}
foreach ($json['require'] ?? [] as $name => $constraint) {
    if ('php' === $name || 0 === strpos($name, 'ext-')) {
        continue;
    }
    if (!isset($locked[$name])) {
        $fails[] = "composer.json requires $name but composer.lock does not"
            . ' lock it -- run composer update and commit the result';
    }
}

// 3. The advisory acceptance stays declared while php-jwt is a dependency.
if (isset($json['require']['firebase/php-jwt'])) {
    $ignored = $json['config']['policy']['advisories']['ignore-id'] ?? [];
    if (!in_array('PKSA-y2cr-5h3j-g3ys', (array) $ignored, true)) {
        $fails[] = 'firebase/php-jwt is required but'
            . ' config.policy.advisories.ignore-id no longer names'
            . ' PKSA-y2cr-5h3j-g3ys -- composer install will refuse the only'
            . ' releases that run on PHP 7.4';
    }
}

// 4. The library does what it was chosen for: verify a signature over a JWKS
//    key, and reject a tampered one. Cheap, no network, no database.
//    Skipped when anything above failed -- with files missing this would be a
//    fatal on a class that is not there, burying the checks that explain why.
if (0 === count($fails)
    && isset($json['require']['firebase/php-jwt'])
    && extension_loaded('openssl')
) {
    require "$web/vendor/autoload.php";
    $b64u = function ($b) {
        return rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    };
    $res = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($res, $priv);
    $det = openssl_pkey_get_details($res);
    $jwks = ['keys' => [[
        'kty' => 'RSA', 'kid' => 'k1', 'use' => 'sig', 'alg' => 'RS256',
        'n' => $b64u($det['rsa']['n']), 'e' => $b64u($det['rsa']['e']),
    ]]];
    $token = \Firebase\JWT\JWT::encode(
        ['iss' => 'https://idp.test', 'exp' => time() + 300],
        $priv,
        'RS256',
        'k1'
    );
    $keys = \Firebase\JWT\JWK::parseKeySet($jwks);
    try {
        $claims = \Firebase\JWT\JWT::decode($token, $keys);
        if ('https://idp.test' !== ($claims->iss ?? null)) {
            $fails[] = 'php-jwt decoded a token but lost the iss claim';
        }
    } catch (\Throwable $e) {
        $fails[] = 'php-jwt could not verify a token signed with a key from a'
            . ' JWKS: ' . get_class($e) . ' ' . $e->getMessage();
    }
    try {
        \Firebase\JWT\JWT::decode(substr($token, 0, -3) . 'AAA', $keys);
        $fails[] = 'php-jwt accepted a token whose signature was altered';
    } catch (\Throwable $e) {
        // expected
    }
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: every locked package is committed and loadable\n";
exit(0);
