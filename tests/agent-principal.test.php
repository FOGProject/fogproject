<?php
/**
 * What gets past the fog-agent certificate gate, pinned.
 *
 * Agent\Principal::verify() is the whole of "which host is calling" for
 * every /agent/v1/ route after enrollment. The web server has already
 * verified the chain by the time PHP runs, and this test is about the
 * cases where trusting that verdict alone would be wrong:
 *
 *   - the server said SUCCESS but the certificate chains to some OTHER CA
 *     the vhost happens to trust (an Apache install verifies clients
 *     against SSLCACertificateFile, which is also the server's own chain
 *     on an external-CA install) -- refused, because Principal re-verifies
 *     against the agent bundle and nothing else;
 *   - the certificate was issued by the agent CA but not for client auth
 *     -- refused on purpose;
 *   - the server said anything but SUCCESS -- refused before any
 *     cryptography;
 *   - nginx's URL-escaped form and Apache's plain PEM -- both accepted,
 *     and they yield the same fingerprint.
 *
 * The fingerprint asserted is computed here from the key file with
 * openssl itself, so this also pins the binding: sha256 over the PEM
 * SubjectPublicKeyInfo, which is what enrollment stored on the host.
 *
 * DB-free. Mints a throwaway root -> agent CA -> leaf chain with the
 * openssl CLI under a temp dir and removes it after.
 *
 * Usage: php tests/agent-principal.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Agent\Principal;

$root = dirname(__DIR__);
require_once $root . '/packages/web/src/Agent/Principal.php';

$failures = 0;
$checks = 0;
$check = function ($name, $expected, $actual) use (&$failures, &$checks) {
    $checks++;
    if ($expected === $actual) {
        return;
    }
    $failures++;
    fwrite(STDERR, sprintf("  FAIL %s\n    expected %s\n    got      %s\n", $name, var_export($expected, true), var_export($actual, true)));
};

$dir = sys_get_temp_dir() . '/fog-agent-principal-' . getmypid();
mkdir($dir, 0700);
$run = function ($cmd) use ($dir) {
    $out = [];
    $rc = 0;
    exec(sprintf('cd %s && %s 2>&1', escapeshellarg($dir), $cmd), $out, $rc);
    if (0 !== $rc) {
        fwrite(STDERR, "openssl failed: $cmd\n" . implode("\n", $out) . "\n");
        exit(1);
    }
};
file_put_contents($dir . '/ext.cnf', implode("\n", [
    '[ca]',
    'basicConstraints = critical, CA:TRUE, pathlen:0',
    'keyUsage = critical, keyCertSign, cRLSign',
    'subjectKeyIdentifier = hash',
    'authorityKeyIdentifier = keyid:always',
    '[client]',
    'basicConstraints = CA:FALSE',
    'keyUsage = digitalSignature',
    'extendedKeyUsage = clientAuth',
    '[server]',
    'basicConstraints = CA:FALSE',
    'keyUsage = digitalSignature',
    'extendedKeyUsage = serverAuth',
    '',
]));
// Two roots: ours, and one the vhost might also trust.
foreach (['root', 'rogue'] as $name) {
    $run("openssl ecparam -name prime256v1 -genkey -noout -out $name.key");
    $run("openssl req -x509 -new -key $name.key -sha256 -days 2 -subj '/CN=$name' -out $name.pem");
}
// Our agent CA under our root; a rogue "agent CA" under the rogue root.
foreach (['root' => 'agentca', 'rogue' => 'rogueca'] as $issuer => $name) {
    $run("openssl ecparam -name prime256v1 -genkey -noout -out $name.key");
    $run("openssl req -new -key $name.key -subj '/CN=$name' -out $name.csr");
    $run("openssl x509 -req -in $name.csr -CA $issuer.pem -CAkey $issuer.key -CAcreateserial -days 2 -sha256 -extfile ext.cnf -extensions ca -out $name.pem");
}
// Leaves: a proper agent, one from the rogue CA, one from our CA but for a server.
foreach (['good' => ['agentca', 'client'], 'rogue' => ['rogueca', 'client'], 'server' => ['agentca', 'server']] as $name => list($ca, $ext)) {
    $run("openssl ecparam -name prime256v1 -genkey -noout -out $name.key");
    $run("openssl req -new -key $name.key -subj '/CN=fog-agent host 1' -out $name.csr");
    $run("openssl x509 -req -in $name.csr -CA $ca.pem -CAkey $ca.key -CAcreateserial -days 2 -sha256 -extfile ext.cnf -extensions $ext -out $name.pem");
}
$run('cat agentca.pem root.pem > bundle.pem');
$run('openssl pkey -in good.key -pubout -out good.pub');

$bundle = $dir . '/bundle.pem';
$good = file_get_contents($dir . '/good.pem');
$expectedFp = hash('sha256', file_get_contents($dir . '/good.pub'));

$verified = Principal::verify(['SSL_CLIENT_VERIFY' => 'SUCCESS', 'SSL_CLIENT_CERT' => $good], $bundle);
$check('good chain, plain PEM (Apache): fingerprint is sha256 of the SPKI PEM', $expectedFp, $verified['fingerprint'] ?? null);
$check('good chain: not_after is a future unix time', true, ($verified['not_after'] ?? 0) > time());

$escaped = Principal::verify(['SSL_CLIENT_VERIFY' => 'SUCCESS', 'SSL_CLIENT_CERT' => rawurlencode($good)], $bundle);
$check('good chain, URL-escaped PEM (nginx): same fingerprint', $expectedFp, $escaped['fingerprint'] ?? null);

$check('server said NONE: refused before cryptography', null, Principal::verify(['SSL_CLIENT_VERIFY' => 'NONE', 'SSL_CLIENT_CERT' => $good], $bundle));
$check('server said nothing at all: refused', null, Principal::verify([], $bundle));
$check('SUCCESS with no certificate: refused', null, Principal::verify(['SSL_CLIENT_VERIFY' => 'SUCCESS'], $bundle));

$rogue = file_get_contents($dir . '/rogue.pem');
$check('SUCCESS but issued under a CA that is not ours: refused', null, Principal::verify(['SSL_CLIENT_VERIFY' => 'SUCCESS', 'SSL_CLIENT_CERT' => $rogue], $bundle));

$server = file_get_contents($dir . '/server.pem');
$check('SUCCESS, our CA, but a serverAuth certificate: refused', null, Principal::verify(['SSL_CLIENT_VERIFY' => 'SUCCESS', 'SSL_CLIENT_CERT' => $server], $bundle));

$check('garbage where the PEM should be: refused', null, Principal::verify(['SSL_CLIENT_VERIFY' => 'SUCCESS', 'SSL_CLIENT_CERT' => 'not a certificate'], $bundle));
$check('bundle missing on this server (a storage node): refused', null, Principal::verify(['SSL_CLIENT_VERIFY' => 'SUCCESS', 'SSL_CLIENT_CERT' => $good], $dir . '/absent.pem'));

foreach (glob($dir . '/*') as $f) {
    unlink($f);
}
rmdir($dir);

if ($failures > 0) {
    fwrite(STDERR, "FAIL: agent-principal ($failures of $checks checks)\n");
    exit(1);
}
echo "PASS agent-principal ($checks checks)\n";
