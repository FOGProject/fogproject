<?php
/**
 * Reads or sets FOG_NODE_API_KEY on the install this runs against.
 *
 * The key FOG signs its own server-to-server requests with. A storage node
 * points DATABASE_HOST at the master and so reads the master's row, which
 * is why there is normally nothing to set and no UI for it -- the FOG
 * Configuration page hides the row deliberately, because printing a shared
 * secret into a form field on every page load is not worth it for a value
 * that generates itself.
 *
 * A peer that is a full FOG server is the exception. It has its own
 * database, mints its own unrelated key, and cannot verify anything the
 * master signs. The master signs to that peer with the peer's ngmKey --
 * "Node API Signing Key" on the master's storage node edit page -- so the
 * peer needs the same value in its own FOG_NODE_API_KEY. This is the way
 * to put it there.
 *
 * Two reasons it cannot be done with FOG's own setSetting():
 *
 *   - setSetting() is an UPDATE through Setting/ServiceManager and does
 *     nothing at all when the row is absent, which is exactly the state a
 *     pure receiver is in. It never signs anything, so nodeApiKey() has
 *     never run there and no row exists.
 *   - validNodeSignature() deliberately never mints, so the peer cannot
 *     heal itself on the first signed request either. That is the correct
 *     behaviour -- a verifier that invents keys verifies nothing -- and it
 *     is what makes this a manual step.
 *
 * Deliberately does NOT boot FOG. It reads the DB constants straight out of
 * config.class.php, the same way bin/schema-manifest.php does. Booting
 * would pull in the autoloader, the session layer and LoadGlobals to write
 * one row, and every one of those is a way for this to fail on a machine
 * that is by definition not fully working yet.
 *
 * Usage:
 *   php bin/fog-node-key.php [--web /var/www/html/fog] [--show]
 *   php bin/fog-node-key.php [--web /var/www/html/fog] --set <key>
 *
 * Running daemons cache settings for FOG_SETTING_CACHE_TTL (300s default),
 * so a change can take up to five minutes to reach one that is already up.
 *
 * PHP version 7.4+
 *
 * @category Utility
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Loads the DB constants out of a FOG install's config.class.php.
 *
 * Same reader as bin/schema-manifest.php, and for the same reason: the
 * constants are the only thing needed and parsing them is cheaper and far
 * more robust than booting the application to reach them.
 *
 * @param string $root The web root of the FOG install.
 *
 * @return PDO
 */
function fogNodeKeyConnect($root)
{
    $config = rtrim($root, '/') . '/lib/fog/config.class.php';
    if (!file_exists($config)) {
        fwrite(STDERR, "No config.class.php under $root\n");
        fwrite(STDERR, "Pass the web root with --web /path/to/fog\n");
        exit(1);
    }
    $src = file_get_contents($config);
    $vals = [];
    foreach (['HOST', 'NAME', 'USERNAME', 'PASSWORD'] as $key) {
        if (preg_match(
            "/define\(\s*'DATABASE_$key'\s*,\s*'(.*?)'\s*\)/s",
            $src,
            $m
        )) {
            $vals[$key] = $m[1];
        }
    }
    if (!isset($vals['NAME'])) {
        fwrite(STDERR, "Could not read DATABASE_* from $config\n");
        exit(1);
    }
    return new \PDO(
        sprintf(
            'mysql:host=%s;dbname=%s',
            $vals['HOST'] ?: 'localhost',
            $vals['NAME']
        ),
        $vals['USERNAME'],
        $vals['PASSWORD'],
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
}

$roots = [
    '/var/www/html/fog',
    '/var/www/fog',
];
$root = '';
$set = null;
$show = false;

$argvv = array_slice($argv, 1);
for ($i = 0; $i < count($argvv); $i++) {
    switch ($argvv[$i]) {
    case '--web':
        $root = isset($argvv[$i + 1]) ? $argvv[++$i] : '';
        break;
    case '--set':
        $set = isset($argvv[$i + 1]) ? $argvv[++$i] : '';
        break;
    case '--show':
        $show = true;
        break;
    case '-h':
    case '--help':
        fwrite(
            STDOUT,
            "Usage:\n"
            . "  php bin/fog-node-key.php [--web <root>] [--show]\n"
            . "  php bin/fog-node-key.php [--web <root>] --set <key>\n\n"
            . "Reads or sets FOG_NODE_API_KEY. Needed only on a peer that\n"
            . "runs its own FOG database; set it to the value held in the\n"
            . "master's storage node record for this peer.\n"
        );
        exit(0);
    default:
        fwrite(STDERR, "Unknown argument: {$argvv[$i]}\n");
        exit(1);
    }
}

// Validated here rather than next to the write, so a bad invocation is
// rejected on any machine -- before locating a web root, before opening a
// connection, and without needing either to be working.
if (null !== $set) {
    $set = trim((string)$set);
    if ($set === '') {
        fwrite(STDERR, "--set needs a value\n");
        exit(1);
    }
    // The master generates these as 32 random bytes hex encoded. Not
    // enforced, because an administrator is entitled to choose their own
    // and a length rule here would be a second opinion about a value the
    // other end already accepted -- but short enough to be a typo is worth
    // saying out loud.
    if (strlen($set) < 32) {
        fwrite(
            STDERR,
            'Warning: that key is ' . strlen($set) . " characters. FOG\n"
            . "generates 64. Setting it anyway.\n"
        );
    }
}

if ($root === '') {
    foreach ($roots as $candidate) {
        if (file_exists($candidate . '/lib/fog/config.class.php')) {
            $root = $candidate;
            break;
        }
    }
}
if ($root === '') {
    fwrite(
        STDERR,
        "Could not find a FOG web root. Pass one with --web /path/to/fog\n"
    );
    exit(1);
}

$db = fogNodeKeyConnect($root);

if (null === $set) {
    $stmt = $db->prepare(
        'SELECT `settingValue` FROM `globalSettings` WHERE `settingKey` = ?'
    );
    $stmt->execute(['FOG_NODE_API_KEY']);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    $value = $row ? trim((string)$row['settingValue']) : '';
    if ($value === '') {
        fwrite(STDOUT, "FOG_NODE_API_KEY: (not set)\n");
        fwrite(
            STDOUT,
            "This install has never signed a request, or is a peer that\n"
            . "only receives them. If it is a peer, set this to the value\n"
            . "in the master's storage node record for it.\n"
        );
        exit(0);
    }
    // Printed in full on purpose: the only reason to run --show is to
    // compare or copy the value, and a truncated secret cannot be either.
    // This needs shell access to the server already.
    fwrite(STDOUT, "FOG_NODE_API_KEY: $value\n");
    exit(0);
}

// INSERT ... ON DUPLICATE KEY UPDATE rather than an UPDATE, because the row
// is normally absent on the machine that needs this run -- see the header.
// The UNIQUE KEY on settingKey is what makes the upsert well defined.
$stmt = $db->prepare(
    'INSERT INTO `globalSettings` '
    . '(`settingKey`, `settingDesc`, `settingValue`, `settingCategory`) '
    . 'VALUES (?, ?, ?, ?) '
    . 'ON DUPLICATE KEY UPDATE `settingValue` = VALUES(`settingValue`)'
);
$stmt->execute(
    [
        'FOG_NODE_API_KEY',
        'Shared secret FOG signs its own server-to-server requests with. '
        . 'On a peer running its own database this must match the Node API '
        . 'Signing Key held in the master storage node record for this peer.',
        $set,
        'FOG Storage Nodes',
    ]
);

$stmt = $db->prepare(
    'SELECT `settingValue` FROM `globalSettings` WHERE `settingKey` = ?'
);
$stmt->execute(['FOG_NODE_API_KEY']);
$row = $stmt->fetch(\PDO::FETCH_ASSOC);
// Read back rather than trusting the write: this is the whole point of the
// utility, and a silent no-op here would look identical to success and then
// fail later as an unexplained 401 on the node.
if (!$row || trim((string)$row['settingValue']) !== $set) {
    fwrite(STDERR, "The key did not land. Nothing has been changed.\n");
    exit(1);
}
fwrite(STDOUT, "FOG_NODE_API_KEY set on $root\n");
fwrite(
    STDOUT,
    "Running daemons cache settings for up to FOG_SETTING_CACHE_TTL\n"
    . "(300s by default), so give them that long to pick it up.\n"
);
exit(0);
