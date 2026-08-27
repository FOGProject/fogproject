<?php
/**
 * FOG's own components must be able to authenticate without a session.
 *
 * StorageNode's image and snapin listings are built by asking the node for
 * them over HTTP -- status/getfiles.php, one request per listing. That
 * endpoint required a signed-in session, and the only credential
 * FOGURLRequests had to offer was the caller's own session cookie. Every
 * caller that has no session -- every CLI daemon, and any API request
 * authenticated by token rather than by cookie -- therefore got a 401, which
 * StorageNode::_getData() turned into an empty list. Not an error: wrong
 * data, silently, for the multicast manager and for every token client
 * reading GET /storagenode/{id}.
 *
 * The credential those callers can hold is a shared secret in globalSettings
 * -- master and node read the same database, so there is nothing to
 * distribute -- used to sign the request rather than to be presented. It is
 * purpose scoped: service/nodecert.php signs with FOG_STORAGENODE_MYSQLPASS,
 * and that password is direct database access, so leaking it in transport
 * costs the whole schema. Leaking this one costs the ability to list
 * directories on a node.
 *
 * WHAT IS ACTUALLY EXERCISED HERE. The three signing/verifying methods are
 * lifted out of FOGBase and run for real against a stubbed key, so the sign
 * -> verify round trip and every tamper case are executed, not described.
 * Only the parts that need a database (nodeApiKey's INSERT) are pinned by
 * source shape, and the shapes chosen are the ones whose loss reintroduces a
 * specific defect.
 *
 * Usage: php tests/node-signature-auth.test.php
 * Exit status 0 = pass, 1 = fail.
 */

$root = dirname(__DIR__);
chdir($root);

$fails = [];
$baseFile = 'packages/web/src/Base/FOGBase.php';
$base = (string)file_get_contents($baseFile);

/*
 * 1. Build a probe out of the shipped methods.
 *
 * getSetting() and nodeApiKey() are the only things they reach for that a
 * test cannot have, so those two are stubbed and everything else -- the
 * payload layout, the window arithmetic, the header names, hash_equals --
 * is the real code.
 */
$consts = [];
foreach (['NODE_API_KEY_SETTING', 'NODE_SIGNATURE_WINDOW'] as $name) {
    if (!preg_match(
        '#\n    const ' . $name . ' = (.+?);\n#',
        $base,
        $m
    )) {
        $fails[] = "FOGBase::$name is gone";
        continue;
    }
    $consts[$name] = trim($m[1]);
}

$methods = [];
foreach (
    [
        '_nodeSignaturePayload',
        'nodeSigningKeyFor',
        '_nodeVerificationKeys',
        'nodeSignatureHeaders',
        'validNodeSignature'
    ] as $name
) {
    if (!preg_match(
        '#\n    (?:public|private) static function '
        . preg_quote($name, '#')
        . '\(.*?\n    \}#s',
        $base,
        $m
    )) {
        $fails[] = "FOGBase::$name() is gone; nothing else can authenticate"
            . ' a session-less FOG component';
        continue;
    }
    $methods[$name] = $m[0];
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

/*
 * nodeSigningKeyFor() and _nodeVerificationKeys() read nfsGroupMembers, so
 * the probe needs a $DB. A fake one rather than a stub of the methods:
 * what is worth testing is the row handling itself -- an unmatched host
 * used to come back as the string 'Array' and sign every request to an
 * unknown peer with a constant no one could verify.
 *
 * The fake answers on the shape of the SQL, which is all the two callers
 * differ by: one names ngmHostname, the other does not.
 */
class NodeSigFakeResult
{
    private $_rows;

    public function __construct($rows)
    {
        $this->_rows = $rows;
    }

    public function fetch($mode = null, $how = null)
    {
        return $this;
    }

    public function get($field = null)
    {
        return $this->_rows;
    }
}

class NodeSigFakeDb
{
    /** Rows as ['ngmHostname' => ..., 'ngmKey' => ...]. */
    public static $nodes = [];

    public function escape($value)
    {
        return "'" . str_replace("'", "''", (string)$value) . "'";
    }

    public function query($sql)
    {
        $rows = [];
        foreach (self::$nodes as $node) {
            if (trim((string)$node['ngmKey']) === '') {
                continue;
            }
            if (false !== strpos($sql, 'ngmHostname')) {
                $quoted = "'" . $node['ngmHostname'] . "'";
                if (false === strpos($sql, $quoted)) {
                    continue;
                }
            }
            $rows[] = ['ngmKey' => $node['ngmKey']];
        }
        return new NodeSigFakeResult($rows);
    }
}

eval(
    'class NodeSigProbe {'
    . ' const NODE_API_KEY_SETTING = ' . $consts['NODE_API_KEY_SETTING'] . ';'
    . ' const NODE_SIGNATURE_WINDOW = '
    . $consts['NODE_SIGNATURE_WINDOW'] . ';'
    . ' public static $key = \'\';'
    . ' public static $DB;'
    . ' public static function nodeApiKey() { return self::$key; }'
    . ' public static function getSetting($k) { return self::$key; }'
    . implode("\n", $methods)
    . ' }'
);
NodeSigProbe::$DB = new NodeSigFakeDb();

/**
 * Presents a set of headers as an inbound request.
 *
 * @param string $method  The request method.
 * @param string $uri     REQUEST_URI, path and query.
 * @param array  $headers Header lines as nodeSignatureHeaders() returns.
 *
 * @return void
 */
function nodeSigPresent($method, $uri, array $headers)
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    unset(
        $_SERVER['HTTP_X_FOG_NODE_TIMESTAMP'],
        $_SERVER['HTTP_X_FOG_NODE_SIGNATURE']
    );
    foreach ($headers as $line) {
        list($name, $value) = explode(': ', $line, 2);
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $_SERVER[$key] = $value;
    }
}

$key = str_repeat('a1', 32);
NodeSigProbe::$key = $key;

$url = 'https://10.0.0.9/fog/status/getfiles.php?path=%2Fimages';
$uri = '/fog/status/getfiles.php?path=%2Fimages';
$headers = NodeSigProbe::nodeSignatureHeaders($url, 'GET');

if (count($headers) !== 2) {
    $fails[] = 'nodeSignatureHeaders() no longer returns both a timestamp'
        . ' and a signature header';
}

/*
 * The channel is headers, not a query parameter, for the reason
 * installTokenHeader() already documents: a header cannot be set by a
 * cross-site form or an <img>, and it never lands in browser history, a
 * bookmark, a Referer or an access log. A long-lived shared secret in a URL
 * would be in all of them.
 */
foreach ($headers as $line) {
    if (0 !== strpos($line, 'X-Fog-Node-')) {
        $fails[] = 'nodeSignatureHeaders() emits something other than an'
            . ' X-Fog-Node-* header: ' . $line;
    }
}

// The happy path.
nodeSigPresent('GET', $uri, $headers);
if (!NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a request signed by this installation does not verify;'
        . ' every session-less caller is back to a 401 and an empty list';
}

// A signature lifted onto a different path. This is the whole reason the
// path is in the signed material: a captured listing request must not become
// a read of somewhere else.
nodeSigPresent('GET', '/fog/status/getfiles.php?path=%2Fetc', $headers);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a signature verifies against a path it was not issued for';
}

// ...or onto a different method. A GET must not become a POST.
nodeSigPresent('POST', $uri, $headers);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a signature issued for GET verifies as a POST';
}

// The timestamp is signed, so it cannot be edited to widen its own window.
$moved = [
    'X-Fog-Node-Timestamp: ' . (string)(time() - 10),
    $headers[1]
];
nodeSigPresent('GET', $uri, $moved);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'the presented timestamp is not covered by the signature; a'
        . ' captured request could be re-dated indefinitely';
}

// Outside the window, correctly signed. This is the property
// service/nodecert.php does not have -- its HMAC covers the payload only, so
// a captured request replays forever.
$stale = (string)(time() - (NodeSigProbe::NODE_SIGNATURE_WINDOW + 60));
$staleSig = hash_hmac(
    'sha256',
    $stale . "\n" . 'GET' . "\n" . $uri,
    $key
);
nodeSigPresent(
    'GET',
    $uri,
    [
        'X-Fog-Node-Timestamp: ' . $stale,
        'X-Fog-Node-Signature: ' . $staleSig
    ]
);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a correctly signed request from outside the replay window'
        . ' is accepted; replay is unbounded again';
}

// Clock skew in the other direction has to be refused too, or the window is
// only half a window.
$future = (string)(time() + (NodeSigProbe::NODE_SIGNATURE_WINDOW + 60));
$futureSig = hash_hmac(
    'sha256',
    $future . "\n" . 'GET' . "\n" . $uri,
    $key
);
nodeSigPresent(
    'GET',
    $uri,
    [
        'X-Fog-Node-Timestamp: ' . $future,
        'X-Fog-Node-Signature: ' . $futureSig
    ]
);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a request dated beyond the window into the future is'
        . ' accepted';
}

/*
 * Per-peer keys.
 *
 * A true storage node points DATABASE_HOST at the master, so both ends read
 * the same globalSettings row and there is nothing to distribute. A peer
 * that is a full FOG server has its own database, mints its own key, and
 * can never verify anything the master signs -- validNodeSignature() must
 * not mint, so it cannot heal itself either. nfsGroupMembers.ngmKey is the
 * per-peer secret the administrator sets on both ends, the same way ngmUser
 * and ngmPass have always had to match the account on the node.
 */
$peerKey = str_repeat('b2', 32);
NodeSigFakeDb::$nodes = [
    ['ngmHostname' => '10.0.0.9', 'ngmKey' => $peerKey],
    ['ngmHostname' => '10.0.0.10', 'ngmKey' => ''],
];

if (NodeSigProbe::nodeSigningKeyFor('10.0.0.9') !== $peerKey) {
    $fails[] = 'nodeSigningKeyFor() does not return a peer key that is set';
}

// The node exists but has no key of its own -- the shared-database case,
// which must keep signing with the installation-wide key.
if (NodeSigProbe::nodeSigningKeyFor('10.0.0.10') !== '') {
    $fails[] = 'nodeSigningKeyFor() returned something for a node with an'
        . ' empty ngmKey; a shared-database node must fall back';
}

// No such node. This is the one that bit: the single-row fetch handed back
// the empty result set, which casts to the string 'Array', so every request
// to an unknown host was signed with a constant nobody holds -- and it read
// as a working signature right up to the point of verification.
$unknown = NodeSigProbe::nodeSigningKeyFor('203.0.113.199');
if ($unknown !== '') {
    $fails[] = 'nodeSigningKeyFor() returned ' . var_export($unknown, true)
        . ' for a host matching no node; expected an empty string';
}

// The signer prefers the peer key, and the result differs from what the
// shared key would have produced.
$peerHeaders = NodeSigProbe::nodeSignatureHeaders($url, 'GET');
if ($peerHeaders === $headers) {
    $fails[] = 'nodeSignatureHeaders() ignored the peer key and signed with'
        . ' the installation-wide one';
}

// This server holds that peer key in its own table, so it verifies -- the
// shared-database topology, where the master signs with the target node's
// ngmKey and the node reads the very same row.
nodeSigPresent('GET', $uri, $peerHeaders);
if (!NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a signature made with a peer key present in nfsGroupMembers'
        . ' was refused';
}

// The global key still verifies alongside it. Both are legitimate and the
// receiver cannot tell from the request which was used.
NodeSigFakeDb::$nodes = [];
$globalHeaders = NodeSigProbe::nodeSignatureHeaders($url, 'GET');
NodeSigFakeDb::$nodes = [
    ['ngmHostname' => '10.0.0.9', 'ngmKey' => $peerKey],
];
nodeSigPresent('GET', $uri, $globalHeaders);
if (!NodeSigProbe::validNodeSignature()) {
    $fails[] = 'the installation-wide key stopped verifying once a peer key'
        . ' existed; adding peers must not break the common case';
}

// A key this server does not hold is still refused. Widening the candidate
// set must not turn into accepting anything.
$stranger = str_repeat('c3', 32);
$sig = hash_hmac('sha256', time() . "\nGET\n" . $uri, $stranger);
nodeSigPresent(
    'GET',
    $uri,
    [
        'X-Fog-Node-Timestamp: ' . time(),
        'X-Fog-Node-Signature: ' . $sig
    ]
);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a signature made with a key this server does not hold was'
        . ' accepted';
}

NodeSigFakeDb::$nodes = [];

/*
 * A peer must have SOME way to be given the key.
 *
 * FOG_NODE_API_KEY is hidden from the FOG Configuration page on purpose --
 * printing a shared secret into a form field on every page load is not
 * worth it for a value that generates itself -- and setSetting() is an
 * UPDATE that does nothing when the row is absent, which is exactly the
 * state a pure receiver is in: it never signs, so nodeApiKey() has never
 * run there. Without bin/fog-node-key.php the per-peer key can be set on
 * the master and nowhere else, which makes the whole feature inoperable in
 * the one topology it exists for.
 */
if (!is_readable('bin/fog-node-key.php')) {
    $fails[] = 'bin/fog-node-key.php is gone; a peer FOG server would have'
        . ' no way to be given its FOG_NODE_API_KEY';
} else {
    $cli = (string)file_get_contents('bin/fog-node-key.php');
    // An upsert, not an UPDATE. The row is normally absent on the machine
    // that needs this run, and an UPDATE there is a silent no-op.
    if (false === strpos($cli, 'ON DUPLICATE KEY UPDATE')) {
        $fails[] = 'bin/fog-node-key.php no longer upserts; on a peer the'
            . ' row does not exist yet and an UPDATE would do nothing';
    }
    // It must read back what it wrote: a silent no-op here looks exactly
    // like success and then fails later as an unexplained 401 on the node.
    if (false === strpos($cli, 'The key did not land')) {
        $fails[] = 'bin/fog-node-key.php no longer verifies its own write';
    }
}

// ...and the UI has to say where to run it, or the value is a dead end.
$nodePage = (string)file_get_contents(
    'packages/web/lib/pages/storagenodemanagement.page.php'
);
if (false === strpos($nodePage, 'fog-node-key.php')) {
    $fails[] = 'the storage node page no longer tells the administrator how'
        . ' to set the matching key on the peer';
}

// A different installation's key.
NodeSigProbe::$key = str_repeat('b2', 32);
nodeSigPresent('GET', $uri, $headers);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a signature verifies under a key it was not made with';
}

/*
 * No key at all must be a refusal, not an opening. An install that has not
 * yet generated one, or whose row was deleted to rotate it, must not accept
 * whatever turns up -- and hash_hmac() with an empty key is a perfectly
 * ordinary MAC that an attacker can compute.
 */
NodeSigProbe::$key = '';
$emptyKeySig = hash_hmac(
    'sha256',
    (string)time() . "\n" . 'GET' . "\n" . $uri,
    ''
);
nodeSigPresent(
    'GET',
    $uri,
    [
        'X-Fog-Node-Timestamp: ' . (string)time(),
        'X-Fog-Node-Signature: ' . $emptyKeySig
    ]
);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'an install with no node key accepts a signature computed'
        . ' with the empty key';
}
NodeSigProbe::$key = $key;

// Malformed and absent input.
nodeSigPresent('GET', $uri, []);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'an unsigned request verifies';
}
/*
 * A non-numeric timestamp. Over-determined on purpose and worth saying so:
 * removing the ctype_digit() guard alone does not make this pass, because
 * (int)'not-a-number' is 0 and the window check then refuses it. The guard
 * is input hygiene rather than the control; the window and the signature
 * are the controls, and both are pinned above on their own.
 */
nodeSigPresent(
    'GET',
    $uri,
    [
        'X-Fog-Node-Timestamp: not-a-number',
        'X-Fog-Node-Signature: ' . str_repeat('0', 64)
    ]
);
if (NodeSigProbe::validNodeSignature()) {
    $fails[] = 'a non-numeric timestamp is not rejected';
}

/*
 * 2. Verification must never mint a key.
 *
 * nodeApiKey() creates one when it is missing, which is right for the
 * sending side and wrong here: an endpoint that generates a secret because
 * an unauthenticated caller asked it to is writing attacker-triggered rows,
 * and on a fresh install it would create the key the very request being
 * checked could not have known.
 */
if (isset($methods['validNodeSignature'])) {
    if (false !== strpos($methods['validNodeSignature'], 'nodeApiKey(')) {
        $fails[] = 'validNodeSignature() calls nodeApiKey(); verification'
            . ' must read the key, never create it';
    }
    if (false === strpos($methods['validNodeSignature'], 'hash_equals(')) {
        $fails[] = 'validNodeSignature() no longer compares with'
            . ' hash_equals(); a timing-variable compare leaks the expected'
            . ' signature a byte at a time';
    }
}

/*
 * 3. The key itself.
 *
 * random_bytes() rather than anything seedable, and INSERT IGNORE rather
 * than setSetting() -- setSetting() is an UPDATE through SettingManager and
 * does nothing at all when the row is absent, which is precisely the case
 * being healed. The UNIQUE KEY on settingKey then makes the INSERT the
 * arbiter of a race, so two processes reaching this at once agree instead of
 * one signing with a key the other has replaced.
 */
if (!preg_match(
    '#public static function nodeApiKey\(\).*?\n    \}#s',
    $base,
    $mk
)) {
    $fails[] = 'FOGBase::nodeApiKey() is gone';
} else {
    if (false === strpos($mk[0], 'random_bytes(')) {
        $fails[] = 'nodeApiKey() no longer uses random_bytes(); the node key'
            . ' has to be unguessable';
    }
    if (false === strpos($mk[0], 'INSERT IGNORE')) {
        $fails[] = 'nodeApiKey() no longer creates the row with INSERT'
            . ' IGNORE; setSetting() cannot create it, and a plain INSERT'
            . ' makes concurrent generation a lost update';
    }
}

/*
 * 4. The receiving endpoint.
 *
 * A browser still needs a session and a CSRF token. What must not come back
 * is checkAuthAndCSRF() being the only gate (session-less callers get wrong
 * data again) or the signature being the only gate (the endpoint stops
 * caring about sessions entirely).
 */
$getfiles = (string)file_get_contents('packages/web/status/getfiles.php');
if (!preg_match(
    '#if \(!FOGCore::validNodeSignature\(\)\) \{\s*\n\s*'
    . 'FOGCore::checkAuthAndCSRF\(\);\s*\n\s*\}#',
    $getfiles
)) {
    $fails[] = 'status/getfiles.php no longer falls back to'
        . ' checkAuthAndCSRF() when the request is unsigned, or no longer'
        . ' accepts a signature at all';
}

/*
 * 5. The sending side signs FOG's own hosts and nobody else's.
 *
 * Same reasoning as the session cookie and the CSRF token beside it: this is
 * a credential, and a credential handed to api.github.com is a credential
 * given away. isFogHost() is the one answer all three key off.
 */
$requests = (string)file_get_contents(
    'packages/web/src/Net/FOGURLRequests.php'
);
if (!preg_match(
    '#if \(\$isFogHost\) \{.*?nodeSignatureHeaders\(#s',
    $requests
)) {
    $fails[] = 'FOGURLRequests no longer signs for FOG hosts, or signs'
        . ' without gating on $isFogHost -- the node key would go to every'
        . ' third-party host this class fetches from';
}
if (false === strpos($requests, 'CURLOPT_CUSTOMREQUEST')) {
    $fails[] = 'FOGURLRequests signs without consulting'
        . ' CURLOPT_CUSTOMREQUEST/CURLOPT_NOBODY; the method in the'
        . ' signature would not be the method sent';
}

/*
 * 6. The key must not be readable back out.
 *
 * SENSITIVE_SETTING_PATTERN matches PASSWORD/PASSWD/PWD/SECRET/TOKEN and not
 * KEY -- widening it to KEY would mask FOG_KEYMAP and FOG_KEY_SEQUENCE,
 * which are not secrets -- so this one has to be named explicitly, and the
 * configuration page has to drop the row rather than render it.
 */
$route = (string)file_get_contents(
    'packages/web/src/Router/Route.php'
);
if (!preg_match(
    '#\$sensitiveSettings = \[.*?\'FOG_NODE_API_KEY\'.*?\];#s',
    $route
)) {
    $fails[] = 'FOG_NODE_API_KEY is not in Route::$sensitiveSettings; the'
        . ' shared node secret would be readable over REST';
}
$configPage = (string)file_get_contents(
    'packages/web/lib/pages/fogconfigurationpage.page.php'
);
if (false === strpos($configPage, 'NODE_API_KEY_SETTING')) {
    $fails[] = 'the FOG Configuration page no longer drops the node key'
        . ' row; the secret would be printed into a form field';
}

if (count($fails) > 0) {
    fwrite(STDERR, 'FAIL: ' . count($fails) . " problem(s):\n");
    foreach ($fails as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "ok: FOG components authenticate to each other without a session\n";
exit(0);
