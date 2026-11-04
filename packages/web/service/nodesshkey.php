<?php
/**
 * Hands the FOG SSH public key to registered storage nodes.
 *
 * PHP version 7.4+
 *
 * @category NodeSshKey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGCore;
use FOG\Items\StorageNode;
use FOG\Net\FOGSSH;
use FOG\Router\Route;

/**
 * Hands the FOG SSH public key to registered storage nodes.
 *
 * GH-2058 / forum 18303: the master logs in to a storage node over SSH, and
 * an sshd set to key-only logins forced an admin to keep a password exception
 * for the service account. The master now offers its own key first (see
 * FOGSSH::login()), so each node needs the public half in the service
 * account's authorized_keys. The node installer fetches it from here.
 *
 * Why this lives under service/ and not maintenance/: the same reason as
 * nodecert.php. The maintenance directory is restricted to localhost and the
 * server's own addresses, and a node is neither.
 *
 * Why the request is authenticated although a public key is not secret. The
 * node signs a nonce with the fogstorage database password, and this script
 * checks it before it answers. Without that check the endpoint would return
 * a value anyone could ask for, and an HMAC over a known plaintext would give
 * any caller an offline guessing oracle for the database password. Requiring
 * a valid request MAC means a caller learns nothing about that password.
 *
 * Who may ask. Holding the storage password proves membership of this FOG
 * installation, not which node is calling, so the request must also come
 * from the address of a registered storage node, as in nodecert.php. An
 * unregistered caller gets a 403 even with a valid signature.
 *
 * Why the response is signed over the client nonce. The node fetches with TLS
 * verification off, because it bootstraps before it has a certificate
 * anything would trust (see nodecert.php). So the signature is what stops a
 * substituted key: a man in the middle cannot sign a different key without
 * the password. The nonce is inside the signed bytes, so an old response
 * recorded earlier cannot be replayed against a new request.
 *
 * The signed forms, for the node side to match exactly:
 *   request  hmac = HMAC-SHA256("sshkey\n" . nonce)
 *   response hmac = HMAC-SHA256(nonce . "\n" . key)
 * Both use FOG_STORAGENODE_MYSQLPASS as the secret.
 *
 * @category NodeSshKey
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/*
 * A machine entry point: the caller is a storage node, which cannot present
 * a user credential. Declared per file rather than inferred from the absence
 * of one -- see Authorization::_hasNoPrincipal() for what it licenses.
 */
define('FOG_MACHINE_REQUEST', true);

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
function nodesshkeyRespond($code, $payload)
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

$remoteIP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$remoteIP = filter_var($remoteIP, FILTER_VALIDATE_IP) ? $remoteIP : '';

$nonce = (string) filter_input(INPUT_POST, 'nonce');
$mac = filter_input(INPUT_POST, 'hmac');

// The nonce is fixed to 32 hex characters so it is safe to sign and echo
// as-is; anything else is refused before any secret is touched. The caller's
// address is needed for the registration check below.
if (!preg_match('/^[0-9a-f]{32}$/', $nonce) || !$mac || !$remoteIP) {
    nodesshkeyRespond(400, ['error' => 'incomplete request']);
}

// The shared secret. Absent means this master has no storage node password
// configured, so no request can be genuine.
$secret = FOGCore::getSetting('FOG_STORAGENODE_MYSQLPASS');
if (!$secret) {
    nodesshkeyRespond(503, ['error' => 'node key distribution is not configured']);
}

// hash_equals, not ==, so the comparison does not leak how many leading
// bytes of the MAC were right.
$expect = hash_hmac('sha256', "sshkey\n" . $nonce, $secret);
if (!hash_equals($expect, (string) $mac)) {
    error_log(
        sprintf(
            'FOG nodesshkey: request from %s rejected, bad signature',
            $remoteIP
        )
    );
    nodesshkeyRespond(403, ['error' => 'authentication failed']);
}

// Authenticated, but not yet authorized: the storage password proves
// membership of this FOG installation, not which node is calling. The source
// address must match a node this master already knows, the same rule as
// nodecert.php -- which is why a node registers before it asks for the key.
$nodeIds = Route::getIds('storagenode', ['ip' => $remoteIP], 'id');
$node = count($nodeIds) > 0 ? new StorageNode(array_shift($nodeIds)) : null;
if (!$node || !$node->isValid()) {
    nodesshkeyRespond(
        403,
        [
            'error' => sprintf(
                'no storage node is registered at %s -- register the node first',
                $remoteIP
            )
        ]
    );
}

$pubfile = FOGSSH::keyPath() . '.pub';
$key = is_readable($pubfile) ? trim((string) file_get_contents($pubfile)) : '';
if ($key === '') {
    nodesshkeyRespond(
        404,
        ['error' => 'this server has no FOG SSH key; re-run the installer on it']
    );
}

nodesshkeyRespond(
    200,
    [
        'key' => $key,
        'hmac' => hash_hmac('sha256', $nonce . "\n" . $key, $secret),
    ]
);
