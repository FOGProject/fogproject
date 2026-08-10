<?php
/**
 * Issues web and code-signing certificates to registered storage nodes.
 *
 * PHP version 5
 *
 * @category NodeCert
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Issues web and code-signing certificates to registered storage nodes.
 *
 * A storage node used to generate its own independent, self-signed
 * "FOG Server CA" during installation, so a fleet of five nodes had six
 * unrelated CAs and no browser or client could be made to trust all of them
 * without being told about each one. This lets the master issue them instead,
 * from the Web CA everything already chains to.
 *
 * Why this lives under service/ and not maintenance/: the maintenance
 * directory is restricted by the vhost to localhost and the server's own
 * addresses, and installfog.sh removes it once an install completes. A request
 * from another machine cannot reach it, which is exactly what this is.
 *
 * How a node proves it is one. It already holds the fogstorage database
 * password -- that is how it reaches the master's database at all -- and the
 * master holds the same value in FOG_STORAGENODE_MYSQLPASS. The node signs its
 * request with it, so nothing new has to be distributed to make this work, and
 * the secret itself never crosses the wire. TLS is not load-bearing here: the
 * node bootstraps before it has a certificate anything would trust, so it
 * connects with verification off and the signature is what authenticates.
 *
 * What it will issue. The names come from THIS server's record of the node,
 * never from the request. A node cannot ask for a certificate covering the
 * master, or another node, or a name it does not own, because it is not asked
 * what names it wants -- the CSR supplies a public key and nothing else. The
 * CA's own name constraints then bound the result a second time.
 *
 * @category NodeCert
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';

header('Content-Type: application/json');

/**
 * Emit a JSON result and stop.
 *
 * @param int    $code    HTTP status code.
 * @param array  $payload Body to encode.
 *
 * @return void
 */
function nodecertRespond($code, $payload)
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

$remoteIP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$remoteIP = filter_var($remoteIP, FILTER_VALIDATE_IP) ? $remoteIP : '';

$type = filter_input(INPUT_POST, 'type');
$csrB64 = filter_input(INPUT_POST, 'csr');
$mac = filter_input(INPUT_POST, 'hmac');

if (!in_array($type, ['web', 'signing'], true)) {
    nodecertRespond(400, ['error' => 'unknown certificate type']);
}
if (!$csrB64 || !$mac || !$remoteIP) {
    nodecertRespond(400, ['error' => 'incomplete request']);
}

// The shared secret. Absent means this master has no storage node password
// configured, in which case there is nothing a node could have signed with and
// no request can be genuine.
$secret = FOGCore::getSetting('FOG_STORAGENODE_MYSQLPASS');
if (!$secret) {
    nodecertRespond(503, ['error' => 'node certificate issuance is not configured']);
}

// hash_equals, not ==. A timing-variable comparison on a MAC is the standard
// way this kind of endpoint is broken open one byte at a time.
$expect = hash_hmac('sha256', $type . "\n" . $csrB64, $secret);
if (!hash_equals($expect, (string) $mac)) {
    error_log(
        sprintf(
            'FOG nodecert: request from %s rejected, bad signature',
            $remoteIP
        )
    );
    nodecertRespond(403, ['error' => 'authentication failed']);
}

// Authenticated, but not yet authorised: holding the storage password proves
// membership of this FOG installation, not which node is calling. The source
// address decides that, and it must match a node this master already knows --
// which is why a node registers before it asks for a certificate.
//
// Route::getIds is the same lookup FOGBase::certDecrypt() uses to find the
// communication key, rather than a second way of asking the same question.
$nodeIds = Route::getIds('storagenode', ['ip' => $remoteIP], 'id');
$node = count($nodeIds) > 0 ? new StorageNode(array_shift($nodeIds)) : null;
if (!$node || !$node->isValid()) {
    nodecertRespond(
        403,
        [
            'error' => sprintf(
                'no storage node is registered at %s -- register the node first',
                $remoteIP
            )
        ]
    );
}

$csr = base64_decode($csrB64, true);
if ($csr === false || strpos($csr, '-----BEGIN CERTIFICATE REQUEST-----') === false) {
    nodecertRespond(400, ['error' => 'malformed certificate request']);
}
// Parsed before it is written anywhere, so a request that is not a CSR at all
// never reaches the signing helper.
if (openssl_csr_get_public_key($csr) === false) {
    nodecertRespond(400, ['error' => 'certificate request does not carry a usable public key']);
}

// The names, built here from what this master knows. Deliberately NOT read
// from the CSR: openssl x509 -req ignores a request's extensions unless told
// to copy them, and this endpoint never tells it to, so the only names that
// can appear are the ones written below.
$names = [];
$names[] = (filter_var($remoteIP, FILTER_VALIDATE_IP) ? 'IP:' : 'DNS:') . $remoteIP;
$recorded = trim((string) $node->get('ip'));
if ($recorded && $recorded !== $remoteIP) {
    $names[] = (filter_var($recorded, FILTER_VALIDATE_IP) ? 'IP:' : 'DNS:') . $recorded;
}
// A DNS name is required -- see the note in fog-sign-node-cert about OpenSSL
// falling back to the subject CN when a certificate carries none. A node
// registered only by IP has no hostname on record, so derive one by reverse
// lookup and fall back to the master's own domain if that fails.
$haveDns = false;
foreach ($names as $entry) {
    if (strpos($entry, 'DNS:') === 0) {
        $haveDns = true;
        break;
    }
}
if (!$haveDns) {
    $ptr = gethostbyaddr($remoteIP);
    if ($ptr && $ptr !== $remoteIP && filter_var($ptr, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $names[] = 'DNS:' . $ptr;
    } else {
        nodecertRespond(
            409,
            [
                'error' => 'this node has no resolvable hostname; give it one in '
                    . 'DNS, or set its Storage Node name to a hostname, then retry'
            ]
        );
    }
}

$staging = FOG_BASE_DIR . DS . 'nodecert-staging';
if (!is_dir($staging) || !is_writable($staging)) {
    nodecertRespond(503, ['error' => 'node certificate issuance is not configured']);
}

// The id is the only caller-influenced value the helper sees, so it is
// generated here rather than accepted, and in the exact shape the helper
// validates.
$reqid = bin2hex(openssl_random_pseudo_bytes(16));
$csrfile = $staging . DS . $reqid . '.csr';
$sanfile = $staging . DS . $reqid . '.san';
$outfile = $staging . DS . $reqid . '.pem';
$chainfile = $staging . DS . $reqid . '.chain';

file_put_contents($csrfile, $csr);
file_put_contents($sanfile, implode("\n", $names) . "\n");

// Both arguments are quoted even though both were generated here rather than
// received: $type is one of two literals and $reqid is hex from
// openssl_random_pseudo_bytes. Quoting them anyway costs nothing and means a
// later change that lets either become caller-influenced does not silently
// become a shell injection. The helper re-validates both regardless.
$cmd = 'sudo -n '
    . escapeshellarg(rtrim(FOG_BASE_DIR, DS) . '/bin/fog-sign-node-cert')
    . ' ' . escapeshellarg($type)
    . ' ' . escapeshellarg($reqid) . ' 2>&1';
$output = shell_exec($cmd);

$leaf = file_exists($outfile) ? file_get_contents($outfile) : '';
$chain = file_exists($chainfile) ? file_get_contents($chainfile) : '';
foreach ([$csrfile, $sanfile, $outfile, $chainfile] as $tmp) {
    if (file_exists($tmp)) {
        unlink($tmp);
    }
}

if (!$leaf || !$chain) {
    error_log(
        sprintf(
            'FOG nodecert: request from %s failed: %s',
            $remoteIP,
            trim((string) $output)
        )
    );
    nodecertRespond(500, ['error' => trim((string) $output) ?: 'signing failed']);
}

// Every issuance is recorded. A CA that signs without leaving a trail cannot
// answer the one question that matters after an incident: what did it sign.
// error_log rather than FOGBase::log, which is protected and takes a log
// level, a browser flag and an object this script does not have.
error_log(
    sprintf(
        'FOG nodecert: issued a %s certificate to storage node %s for %s',
        $type,
        $remoteIP,
        implode(', ', $names)
    )
);

$root = BASEPATH . 'management' . DS . 'other' . DS . 'ca.cert.pem';
nodecertRespond(
    200,
    [
        'leaf' => $leaf,
        'chain' => $chain,
        'root' => file_exists($root) ? file_get_contents($root) : '',
        'names' => $names,
    ]
);
