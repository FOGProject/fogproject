<?php
/**
 * Fixtures for the placement-intent DB tests.
 *
 * Included by a test that has already included tests/lib/directory-db.php and
 * bound the application's PDODB to its PDO. It builds a joined host with its
 * hostDirectory row and the request payload that DirectoryPlacementIntent
 * persists, and it reads rows back by their own SQL. It never starts an LDAP
 * connection: the objects named in a payload are identity tokens only.
 */

/** A random lower-case version-4 style GUID. */
function intentFixtureGuid()
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
}

/** The 16 raw bytes the table stores for a GUID. */
function intentFixtureBytes($guid)
{
    return hex2bin(str_replace('-', '', strtolower($guid)));
}

/**
 * The snapshot a request carries, read the way the product reads it.
 *
 * @param \PDO $pdo  the connection
 * @param int  $host the host id
 */
function intentFixtureSnapshot(\PDO $pdo, $host)
{
    $method = new \ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, '_snapshot');
    $method->setAccessible(true);
    return $method->invoke(null, $pdo, $host);
}

/**
 * Inserts a joined host and builds a request for it.
 *
 * @param \PDO  $pdo  the connection
 * @param array $with optional keys: state (hdRejoinState JSON), config
 *                    (hdRejoinConfig JSON), name (host and account name)
 *
 * @return array host_id, host_name, target, destination, payload, state
 */
function intentFixtureCreate(\PDO $pdo, array $with = [])
{
    static $next = 3000;
    $id = ++$next;
    $name = $with['name'] ?? ('IT' . $id);
    $domain = 'corp.example';
    $oldDn = 'CN=' . $name . ',OU=Legacy,DC=corp,DC=example';
    $fingerprint = hash('sha256', 'fp' . $id);
    $pdo->prepare('INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)')
        ->execute([$id, $name, $domain, 'OU=Managed,DC=corp,DC=example', $fingerprint]);
    $pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)')
        ->execute([$id, $domain, $oldDn, $name . '$', $with['config'] ?? null, $with['state'] ?? null]);
    $target = ['guid' => intentFixtureGuid(), 'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
        'account' => $name . '$', 'dn' => $oldDn];
    $destination = ['guid' => intentFixtureGuid(), 'dn' => 'OU=Managed,DC=corp,DC=example'];
    $payload = ['version' => 1, 'host_id' => $id, 'snapshot' => intentFixtureSnapshot($pdo, $id),
        'target' => $target, 'destination' => $destination, 'dc' => 'dc1.corp.example',
        'nc' => 'DC=corp,DC=example', 'rdn' => 'CN=' . $name];
    return ['host_id' => $id, 'host_name' => $name, 'target' => $target, 'destination' => $destination,
        'payload' => $payload, 'state' => ['operation' => 'join', 'name' => $name, 'target' => $target]];
}

/**
 * Inserts a joined host that holds a ready rebind plan, so that
 * DirectoryOperation::claim() can start it.
 *
 * @param \PDO        $pdo    the connection
 * @param string|null $target the target account name; the host's own name when null
 * @param string|null $name   the host's name
 *
 * @return array host (model), host_id, host_name, target, body (the start body)
 */
function intentFixtureReadyPlan(\PDO $pdo, $target = null, $name = null)
{
    static $next = 7000;
    $id = ++$next;
    $name = $name ?? ('RP' . $id);
    $targetName = rtrim((string)(null === $target ? $name : $target), '$');
    $domain = 'corp.example';
    $fingerprint = hash('sha256', 'rp' . $id);
    $targetRecord = ['guid' => intentFixtureGuid(), 'sid' => 'S-1-5-21-1048576-' . random_int(100000, 999999999),
        'account' => $targetName . '$', 'dn' => 'CN=' . $targetName . ',OU=Computers,DC=corp,DC=example'];
    $host = (new FOG\Items\Host())->set('id', $id)->set('name', $name)->set('ADDomain', $domain)
        ->set('ADOU', 'OU=Managed,DC=corp,DC=example')->set('useAD', 1)->set('agentFingerprint', $fingerprint);
    $config = ['version' => 1, 'protocol' => 2, 'allow_create' => false, 'name' => $name, 'domain' => $domain,
        'target' => $targetRecord, 'config_revision' => bin2hex(random_bytes(16)),
        'confirmed_by' => 'intent-fixture', 'confirmed_at' => gmdate('c')];
    $revision = FOG\Agent\DirectoryOperation::revision($host, $config, 'dc1.corp.example');
    $observation = ['revision' => $revision, 'current_name' => $name, 'pending_name' => $name, 'domain' => $domain,
        'netbios' => 'CORP', 'role' => 'workstation', 'secure_channel' => 'true', 'dc' => 'dc1.corp.example',
        'dc_reachable' => true, 'boot' => ['marker' => 'intent-fixture', 'started' => gmdate(DATE_ATOM)]];
    $state = ['version' => 1, 'protocol' => 2, 'operation' => 'rebind', 'mode' => 'existing', 'target' => $targetRecord,
        'host_id' => $id, 'name' => $name, 'domain' => $domain, 'dc' => 'dc1.corp.example',
        'check_dc' => 'dc1.corp.example', 'phase' => 'ready', 'attempt_id' => bin2hex(random_bytes(16)),
        'config_revision' => $revision, 'fingerprint' => $fingerprint, 'expected_current' => $name,
        'expected_pending' => $name, 'observed_at' => time(), 'observation' => $observation,
        'last_deploy_task_id' => 0, 'trigger_task_id' => 0, 'placement_hold' => false];
    $pdo->prepare('INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)')
        ->execute([$id, $name, $domain, 'OU=Managed,DC=corp,DC=example', $fingerprint]);
    $pdo->prepare('INSERT INTO hostDirectory (hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdRejoinConfig,hdRejoinState) VALUES (?,1,\'domain\',?,?,?,UTC_TIMESTAMP(),?,?)')
        ->execute([$id, $domain, $targetRecord['dn'], $targetRecord['account'], json_encode($config), json_encode($state)]);
    return ['host' => $host, 'host_id' => $id, 'host_name' => $name, 'target' => $targetRecord,
        'body' => ['attempt_id' => $state['attempt_id'], 'config_revision' => $revision, 'operation' => 'rebind',
            'task_id' => 0, 'target_guid' => $targetRecord['guid']]];
}

/** A request payload for a ready-plan fixture: a Move of its target into the Managed OU. */
function intentFixturePayloadFor(\PDO $pdo, array $plan)
{
    return ['version' => 1, 'host_id' => $plan['host_id'], 'snapshot' => intentFixtureSnapshot($pdo, $plan['host_id']),
        'target' => $plan['target'], 'destination' => ['guid' => intentFixtureGuid(), 'dn' => 'OU=Managed,DC=corp,DC=example'],
        'dc' => 'dc1.corp.example', 'nc' => 'DC=corp,DC=example', 'rdn' => 'CN=' . rtrim($plan['target']['account'], '$')];
}

/** The intent row for a GUID, or null. */
function intentFixtureRow(\PDO $pdo, $guid)
{
    $q = $pdo->prepare('SELECT * FROM directoryPlacementIntent WHERE dpiGUID=?');
    $q->bindValue(1, intentFixtureBytes($guid), \PDO::PARAM_LOB);
    $q->execute();
    $row = $q->fetch(\PDO::FETCH_ASSOC) ?: null;
    $q->closeCursor();
    return $row;
}

/** Just the status of the intent row for a GUID, or null. */
function intentFixtureStatus(\PDO $pdo, $guid)
{
    $row = intentFixtureRow($pdo, $guid);
    return null === $row ? null : $row['dpiStatus'];
}

/**
 * The message of the RuntimeException $operation throws, '' when it throws
 * none. Any other throwable is rethrown, so a test cannot pass on a crash.
 */
function intentFixtureRefusal(callable $operation)
{
    try {
        $operation();
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    }
    return '';
}

/** Runs a statement and returns every row, leaving no open cursor. */
function intentFixtureAll(\PDO $pdo, $sql, array $params = [])
{
    $q = $pdo->prepare($sql);
    $q->execute($params);
    $rows = $q->fetchAll(\PDO::FETCH_ASSOC);
    $q->closeCursor();
    return $rows;
}
