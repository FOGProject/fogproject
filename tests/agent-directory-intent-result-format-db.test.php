<?php
/**
 * What a placement request stores about its result: a JSON record that fits
 * the 512-character column whatever the directory answered, the digest of the
 * full answer, and the outcome of the conditional update of the host's stored
 * DN. A success is SUCCEEDED even when that update finds newer facts, and an
 * UNKNOWN row never grants a new dispatch, whatever its text says.
 *
 * The objects in the payloads are identity tokens. This test never connects
 * to a directory.
 *
 * Needs FOG_TEST_DSN (and FOG_TEST_USER / FOG_TEST_PASS); skips without it.
 *
 * Usage: FOG_TEST_DSN='mysql:host=127.0.0.1;port=3306' php tests/agent-directory-intent-result-format-db.test.php
 */
if (!getenv('FOG_TEST_DSN')) {
    echo "SKIP directory intent result format DB: FOG_TEST_DSN not set\n";
    exit(0);
}

$directoryDbName = 'agent-directory-intent-result-format-db';
require __DIR__ . '/lib/directory-db.php';
$pdo = $GLOBALS['pdo']; // left by directory-db.php
directoryTestBindPdodb($pdo);
require __DIR__ . '/lib/directory-intent-fixture.php';
$t = new FogChecks();

use FOG\Agent\DirectoryPlacementIntent as Intent;

/** Prepares, dispatches and finishes one request. Optionally changes the host's facts in between. */
$run = static function (array $fixture, $success, $raw, $newerFacts = false) use ($pdo) {
    $ticket = Intent::prepare($fixture['payload']);
    Intent::dispatch($ticket);
    if ($newerFacts) {
        $pdo->prepare('UPDATE hostDirectory SET hdComputerDN = ?, hdObservedAt = ? WHERE hdHostID = ?')
            ->execute(['CN=' . $fixture['host_name'] . ',OU=Newer,DC=corp,DC=example', gmdate('Y-m-d H:i:s', time() - 30), $fixture['host_id']]);
    }
    Intent::finish($ticket, $success, $raw);
    return [$ticket, intentFixtureRow($pdo, $fixture['target']['guid']),
        intentFixtureAll($pdo, 'SELECT hdComputerDN FROM hostDirectory WHERE hdHostID = ?', [$fixture['host_id']])[0]['hdComputerDN']];
};

$long = intentFixtureCreate($pdo);
$rawLong = "ldap\n\t\x01é🌱|" . str_repeat("漢字𐍈/\\\r\n", 300);
list(, $row, $dn) = $run($long, true, $rawLong);
$result = json_decode((string)$row['dpiResult'], true);
$t->check(
    'a long UTF-8 answer is stored as valid JSON within the column bound',
    is_array($result) && strlen((string)$row['dpiResult']) <= 512 && 1 === ($result['version'] ?? null)
        && true === ($result['ldap_success'] ?? null) && true === ($result['truncated'] ?? null)
);
$t->check(
    'the digest covers the whole answer and the preview is valid UTF-8',
    hash('sha256', $rawLong) === ($result['ldap_result_sha256'] ?? null)
        && strlen($result['ldap_result']) < strlen($rawLong) && 1 === preg_match('//u', $result['ldap_result'])
        && "ldap\n\t\x01é🌱|" === substr($result['ldap_result'], 0, strlen("ldap\n\t\x01é🌱|"))
);
$t->check(
    'the projection is applied and the stored DN is the new one',
    'SUCCEEDED' === $row['dpiStatus'] && 'applied' === ($result['facts_projection'] ?? null)
        && ($long['payload']['rdn'] . ',' . $long['payload']['destination']['dn']) === $dn
);

$miss = intentFixtureCreate($pdo);
$rawMiss = "ldap_success\tcontrol=\x02";
list(, $row, $dn) = $run($miss, true, $rawMiss, true);
$result = json_decode((string)$row['dpiResult'], true);
$t->check(
    'newer facts are reported as a projection miss while the status stays SUCCEEDED',
    'SUCCEEDED' === $row['dpiStatus'] && 'cas_miss' === ($result['facts_projection'] ?? null)
        && $rawMiss === ($result['ldap_result'] ?? null) && false === ($result['truncated'] ?? null)
        && $dn !== $miss['payload']['rdn'] . ',' . $miss['payload']['destination']['dn']
        && 'CN=' . $miss['host_name'] . ',OU=Newer,DC=corp,DC=example' === $dn
);

// The text says success, the boolean says failure. The boolean decides.
$unknown = intentFixtureCreate($pdo);
list($ticket, $row) = $run($unknown, false, 'ldap_success');
$result = json_decode((string)$row['dpiResult'], true);
$t->check(
    'a false result is UNKNOWN whatever its text says',
    'UNKNOWN' === $row['dpiStatus'] && false === ($result['ldap_success'] ?? null)
        && 'ldap_success' === ($result['ldap_result'] ?? null) && 'not_attempted' === ($result['facts_projection'] ?? null)
);
$t->check(
    'the UNKNOWN row grants no new dispatch and stays unchanged',
    'directory_placement_dispatch_changed' === intentFixtureRefusal(static function () use ($ticket) {
        Intent::dispatch($ticket);
    }) && $row === intentFixtureRow($pdo, $unknown['target']['guid'])
);

// An OU name with a 4-byte character: the table is utf8mb3, so the stored payload must be ASCII-escaped.
$emoji = intentFixtureCreate($pdo);
$emoji['payload']['destination']['dn'] = 'OU=Lab 🌱,DC=corp,DC=example';
$prepared = Intent::prepare($emoji['payload']);
$row = intentFixtureRow($pdo, $emoji['target']['guid']);
$t->check(
    'a destination with a 4-byte character is stored and read back unchanged',
    'PREPARED' === $row['dpiStatus'] && 1 === preg_match('/^[\x00-\x7f]*$/', (string)$row['dpiPayload'])
        && 'OU=Lab 🌱,DC=corp,DC=example' === json_decode((string)$row['dpiPayload'], true)['destination']['dn']
        && 1 === $prepared['generation']
);

$t->finish();
