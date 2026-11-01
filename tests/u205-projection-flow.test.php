<?php
/**
 * U2-05 projection/recovery schedule using a scratch PDO and fictional LDAP.
 * The LDAP stub is a call-boundary fixture only; it makes no network request.
 */
class U205ProjectionLdapStub
{
    public static $connects = 0;
    public static $lookups = [];
    public static $containers = [];
    public static $moves = [];
    public static $context = [];
    public static $records = [];
    public static $recordQueues = [];
    public static $containerRecord = null;
    public static $onContainer = null;
    private $connected = false;

    public static function reset()
    {
        self::$connects = 0;
        self::$lookups = [];
        self::$containers = [];
        self::$moves = [];
        self::$records = [];
        self::$recordQueues = [];
        self::$containerRecord = null;
        self::$onContainer = null;
    }

    public function connect($uri, $bindDn, $password, $caFile = '')
    {
        self::$connects++;
        $this->connected = true;
        return true;
    }

    public function close()
    {
        $this->connected = false;
    }

    public function placementContext($searchBase)
    {
        if (!$this->connected || !self::samePlacementNC((string)$searchBase, self::$context)) {
            return ['status' => 'error', 'reason' => 'fixture_search_scope_invalid'];
        }
        self::$containers[] = ['kind' => 'context', 'search_base' => (string)$searchBase];
        return self::$context + ['status' => 'found'];
    }

    public function computerRecord($baseDn, $account)
    {
        $key = strtolower(rtrim((string)$account, '$'));
        self::$lookups[] = ['base_dn' => (string)$baseDn, 'account' => (string)$account];
        if (!$this->connected || !self::sameNamingContext((string)$baseDn, self::$context['base_dn'] ?? '')) {
            return ['status' => 'error', 'reason' => 'fixture_search_base_invalid'];
        }
        $record = !empty(self::$recordQueues[$key])
            ? array_shift(self::$recordQueues[$key])
            : (self::$records[$key] ?? ['status' => 'error', 'reason' => 'fixture_record_unspecified']);
        if ('found' === ($record['status'] ?? null)
            && !self::sameNamingContext((string)($record['record']['dn'] ?? ''), (string)$baseDn)) {
            return ['status' => 'error', 'reason' => 'fixture_search_subtree_mismatch'];
        }
        return $record;
    }

    public function containerIdentity($dn)
    {
        self::$containers[] = ['kind' => 'container', 'dn' => (string)$dn];
        if (is_callable(self::$onContainer)) {
            $callback = self::$onContainer;
            self::$onContainer = null;
            $callback();
        }
        $record = self::$containerRecord;
        if (is_array($record) && ($dn === $record['dn'] || !strcasecmp((string)$dn, self::guidDN($record['guid'])))) {
            return ['status' => 'found', 'guid' => $record['guid'], 'dn' => $record['dn']];
        }
        return ['status' => 'error', 'reason' => 'fixture_container_unspecified'];
    }

    public function moveTo($entry, $parent, $permit = null)
    {
        $rdn = FOG\Agent\DirectoryPlacementIntent::consumePermit($permit, $this, $entry, $parent);
        self::$moves[] = ['entry' => $entry, 'parent' => $parent, 'rdn' => $rdn];
        return true;
    }

    public function moveOutcome()
    {
        return ['kind' => 'before_call_not_sent'];
    }

    public function error()
    {
        return 'u205_fictional_ldap_stub';
    }

    public static function guidDN($guid)
    {
        return '<GUID=' . $guid . '>';
    }

    public static function parentDn($dn)
    {
        $parts = explode(',', (string)$dn);
        array_shift($parts);
        return implode(',', $parts);
    }

    public static function rdn($dn)
    {
        return explode(',', (string)$dn, 2)[0];
    }

    private static function sameNamingContext($dn, $nc)
    {
        $dn = strtolower(trim((string)$dn));
        $nc = strtolower(trim((string)$nc));
        return '' !== $nc && ($dn === $nc || (strlen($dn) > strlen($nc)
            && substr($dn, -strlen($nc) - 1) === ',' . $nc));
    }

    public static function samePlacementNC($dn, array $context)
    {
        $root = (string)($context['base_dn'] ?? '');
        if (!self::sameNamingContext($dn, $root)) {
            return false;
        }
        foreach (($context['naming_contexts'] ?? []) as $nc) {
            if (is_string($nc) && strlen($nc) > strlen($root)
                && self::sameNamingContext($dn, $nc)) {
                return false;
            }
        }
        return true;
    }
}
class_alias(U205ProjectionLdapStub::class, 'FOG\\Net\\FOGLdap', false);

$dsn = (string)getenv('MJ1_DB_DSN');
if ('' === $dsn) {
    echo "SKIP U2-05 projection flow: MJ1_DB_DSN not set\n";
    exit(0);
}
if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
    throw new \RuntimeException('U2-05 projection flow requires explicit scratch opt-in and MySQL DSN');
}

require_once __DIR__ . '/lib/fog-test-harness.php';
FogTestHarness::boot('u205-projection-flow');
require_once __DIR__ . '/lib/sc-hold-pdo.php';

$pdo = new \PDO($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
    throw new \RuntimeException('U2-05 projection flow requires a fog_mj1-prefixed scratch DB');
}
if (!defined('DATABASE_NAME')) {
    define('DATABASE_NAME', $database);
} elseif (DATABASE_NAME !== $database) {
    throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
}

$link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
$link->setAccessible(true);
$link->setValue(null, $pdo);
$db = new ScHoldPdoDb($pdo);
FogTestHarness::setStatic('FOGBase', 'DB', $db);
FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

$test = new FogChecks();
$rootDN = 'DC=u205,DC=invalid';
$managedDN = 'OU=Managed,' . $rootDN;
U205ProjectionLdapStub::$context = [
    'domain' => 'u205.invalid', 'base_dn' => $rootDN, 'dc' => 'dc1.u205.invalid',
    'naming_contexts' => [$rootDN, 'CN=Configuration,' . $rootDN, 'CN=Schema,CN=Configuration,' . $rootDN]
];
$settingsCache = [];
$setSettings = static function () use (&$settingsCache, $rootDN) {
    $settingsCache = [
        'FOG_DIRECTORY_PLACEMENT_ENABLED' => ['value' => '1', 'ts' => time()],
        'FOG_DIRECTORY_LDAP_URI' => ['value' => 'ldaps://dc1.u205.invalid', 'ts' => time()],
        'FOG_DIRECTORY_BIND_DN' => ['value' => 'cn=fixture,' . strtolower($rootDN), 'ts' => time()],
        'FOG_DIRECTORY_BIND_PASSWORD' => ['value' => 'fictional-password', 'ts' => time()],
        'FOG_DIRECTORY_CA_CERT' => ['value' => '', 'ts' => time()],
        'FOG_DIRECTORY_BASE_DN' => ['value' => $rootDN, 'ts' => time()]
    ];
    FogTestHarness::setStatic('FOGBase', '_settingsCache', $settingsCache);
    FogTestHarness::setStatic('FOGBase', '_settingsFileChecked', true);
};
$setSettings();
$guid = static function () {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4'
        . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$guidBytes = static function ($value) {
    return hex2bin(str_replace('-', '', strtolower((string)$value)));
};
$fixtures = [];
$createFixture = static function ($label) use ($pdo, $guid, $rootDN, $managedDN, &$fixtures) {
    do {
        $hostID = random_int(1500000000, 1800000000);
        $name = 'U205' . strtoupper(bin2hex(random_bytes(5)));
        $check = $pdo->prepare('SELECT COUNT(*) FROM hosts WHERE hostID=? OR hostName=?');
        $check->execute([$hostID, $name]);
        $hostExists = (int)$check->fetchColumn() > 0;
        $check = $pdo->prepare('SELECT COUNT(*) FROM hostDirectory WHERE hdHostID=?');
        $check->execute([$hostID]);
        $directoryExists = (int)$check->fetchColumn() > 0;
        $check = $pdo->prepare('SELECT COUNT(*) FROM directoryPlacementIntent WHERE dpiHostID=?');
        $check->execute([$hostID]);
        $intentExists = (int)$check->fetchColumn() > 0;
        $check = $pdo->prepare('SELECT COUNT(*) FROM auditLog WHERE alSubjectType=\'host\' AND alSubjectID=?');
        $check->execute([$hostID]);
        $auditExists = (int)$check->fetchColumn() > 0;
        $check = $pdo->prepare('SELECT COUNT(*) FROM history WHERE hSubjectType=\'host\' AND hSubjectID=?');
        $check->execute([$hostID]);
        $historyExists = (int)$check->fetchColumn() > 0;
    } while ($hostExists || $directoryExists || $intentExists || $auditExists || $historyExists);
    $oldDN = 'CN=' . $name . ',OU=Legacy,' . $rootDN;
    $observedAt = gmdate('Y-m-d H:i:s', time() - 120);
    $fingerprint = hash('sha256', random_bytes(32));
    $host = $pdo->prepare('INSERT INTO hosts (hostID,hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint) VALUES (?,?,1,?,?,?)');
    $host->execute([$hostID, $name, 'U205', $managedDN, $fingerprint]);
    $fixtures[] = ['host_id' => $hostID, 'name' => $name, 'host_inserted' => true, 'directory_inserted' => false];
    $fixtureIndex = array_key_last($fixtures);
    $directory = $pdo->prepare(
        'INSERT INTO hostDirectory (hdHostID,hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdPlacementAt,hdPlacementError,hdRejoinConfig,hdRejoinState) '
        . 'VALUES (?,1,\'domain\',?,?,?,?,NULL,\'\',NULL,NULL)'
    );
    $directory->execute([$hostID, 'U205', $oldDN, $name . '$', $observedAt]);
    $hdID = (int)$pdo->lastInsertId();
    $fixtures[$fixtureIndex]['directory_inserted'] = true;

    $target = ['guid' => $guid(), 'sid' => 'S-1-5-21-205-' . $hostID,
        'account' => $name . '$', 'dn' => $oldDN];
    $destination = ['guid' => $guid(), 'dn' => $managedDN];
    $hostModel = (new FOG\Items\Host())->set('id', $hostID)->set('name', $name)
        ->set('ADDomain', 'U205')->set('ADOU', $managedDN)->set('useAD', 1)
        ->set('agentFingerprint', $fingerprint);
    $directoryModel = (new FOG\Items\HostDirectory())->set('id', $hdID)->set('hostID', $hostID)
        ->set('joined', 1)->set('kind', 'domain')->set('domain', 'U205')->set('computerDN', $oldDN)
        ->set('machineAccount', $name . '$')->set('observedAt', $observedAt)
        ->set('placementAt', null)->set('placementError', '')->set('rejoinConfig', null)->set('rejoinState', null);
    $snapshotMethod = new \ReflectionMethod(FOG\Agent\DirectoryPlacementIntent::class, 'snapshot');
    $snapshotMethod->setAccessible(true);
    $snapshot = $snapshotMethod->invoke(null, $pdo, $hostID);
    $payload = ['version' => 1, 'host_id' => $hostID, 'snapshot' => $snapshot,
        'target' => $target, 'destination' => $destination,
        'dc' => 'dc1.u205.invalid', 'nc' => $rootDN, 'rdn' => 'CN=' . $name];
    U205ProjectionLdapStub::reset();
    U205ProjectionLdapStub::$containerRecord = $destination;
    U205ProjectionLdapStub::$records[strtolower($name)] = ['status' => 'found', 'record' => $target];
    return ['label' => $label, 'host_id' => $hostID, 'hd_id' => $hdID, 'name' => $name,
        'fingerprint' => $fingerprint, 'old_dn' => $oldDN, 'target' => $target,
        'destination' => $destination, 'payload' => $payload,
        'host' => $hostModel, 'directory' => $directoryModel];
};
$readIntent = static function (array $fixture) use ($pdo, $guidBytes) {
    $q = $pdo->prepare('SELECT dpiGUID,dpiSID,dpiHostID,dpiGeneration,dpiStatus,dpiPayload,dpiHash,dpiResult FROM directoryPlacementIntent WHERE dpiGUID=?');
    $q->bindValue(1, $guidBytes($fixture['target']['guid']), \PDO::PARAM_LOB);
    $q->execute();
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};
$readFacts = static function (array $fixture) use ($pdo) {
    $q = $pdo->prepare('SELECT hdComputerDN,hdObservedAt,hdPlacementAt,hdPlacementError FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$fixture['host_id']]);
    return $q->fetch(\PDO::FETCH_ASSOC) ?: null;
};
$freshModels = static function (array $fixture) use ($pdo) {
    $q = $pdo->prepare('SELECT hostName,hostUseAD,hostADDomain,hostADOU,hostAgentFingerprint FROM hosts WHERE hostID=?');
    $q->execute([$fixture['host_id']]);
    $h = $q->fetch(\PDO::FETCH_ASSOC);
    $q = $pdo->prepare('SELECT hdJoined,hdKind,hdDomain,hdComputerDN,hdMachineAccount,hdObservedAt,hdPlacementAt,hdPlacementError,hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=?');
    $q->execute([$fixture['host_id']]);
    $d = $q->fetch(\PDO::FETCH_ASSOC);
    if (!is_array($h) || !is_array($d)) {
        throw new \RuntimeException('fixture models could not be reloaded');
    }
    $host = (new FOG\Items\Host())->set('id', $fixture['host_id'])->set('name', $h['hostName'])
        ->set('useAD', $h['hostUseAD'])->set('ADDomain', $h['hostADDomain'])->set('ADOU', $h['hostADOU'])
        ->set('agentFingerprint', $h['hostAgentFingerprint']);
    $directory = (new FOG\Items\HostDirectory())->set('id', $fixture['hd_id'])->set('hostID', $fixture['host_id'])
        ->set('joined', $d['hdJoined'])->set('kind', $d['hdKind'])->set('domain', $d['hdDomain'])
        ->set('computerDN', $d['hdComputerDN'])->set('machineAccount', $d['hdMachineAccount'])
        ->set('observedAt', $d['hdObservedAt'])->set('placementAt', $d['hdPlacementAt'])
        ->set('placementError', $d['hdPlacementError'])->set('rejoinConfig', $d['hdRejoinConfig'])
        ->set('rejoinState', $d['hdRejoinState']);
    return [$host, $directory];
};
$ticketForWorker = static function (array $ticket) {
    if (!isset($ticket['guid']) || 16 !== strlen($ticket['guid'])) {
        throw new \RuntimeException('intent ticket GUID is not 16 bytes');
    }
    $ticket['guid_hex'] = bin2hex($ticket['guid']);
    unset($ticket['guid']);
    return $ticket;
};
$waitForFile = static function ($path, $process, $seconds) {
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
            return null;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return null;
};

$barrierDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    . 'u205-projection-' . bin2hex(random_bytes(12));
if (!mkdir($barrierDir, 0700)) {
    throw new \RuntimeException('cannot create private U2-05 barrier directory');
}
$resolvedBarrierDir = realpath($barrierDir);
$resolvedTemp = rtrim((string)realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (false === $resolvedBarrierDir || 0 !== strpos($resolvedBarrierDir, $resolvedTemp)
    || 0 !== strpos(basename($resolvedBarrierDir), 'u205-projection-')) {
    throw new \RuntimeException('U2-05 barrier directory escaped private temp root');
}
$worker = __DIR__ . '/fixtures/u205-projection-worker.php';
$process = null;
$pipes = [];

try {
    // PREPARED ticket is handed to a child worker. It dispatches, consumes a
    // fake LDAP permit, and pauses after the success response but before finish.
    $fixture = $createFixture('projection-cas-miss');
    $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($fixture['payload']);
    $workerInput = json_encode(['ticket' => $ticketForWorker($ticket)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pipes = [];
    // Match check-php.sh's explicit suppression of E_DEPRECATED while keeping
    // every other PHP warning/error visible on the captured stderr pipe.
    $process = proc_open([PHP_BINARY, '-d', 'error_reporting=E_ALL & ~E_DEPRECATED', $worker, $resolvedBarrierDir, $workerInput],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new \RuntimeException('cannot start U2-05 barrier worker');
    }
    fclose($pipes[0]);
    $pipes = [1 => $pipes[1], 2 => $pipes[2]];
    $ldapReturned = $waitForFile($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'ldap-returned.json', $process, 15.0);
    if (!is_array($ldapReturned)) {
        throw new \RuntimeException('fake LDAP success barrier was not reached');
    }

    // This is a normal HostDirectory model save through the real PDODB and
    // common fence, completed after dispatch/LDAP response and before finish.
    $concurrentDN = 'CN=' . $fixture['name'] . ',OU=ConcurrentFacts,' . $rootDN;
    $concurrentObserved = gmdate('Y-m-d H:i:s');
    $savedFacts = (new FOG\Items\HostDirectory($fixture['hd_id']))
        ->set('computerDN', $concurrentDN)->set('observedAt', $concurrentObserved)->save();
    $factsAfterWriter = $readFacts($fixture);
    file_put_contents($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'facts-saved', 'go', LOCK_EX);
    $ackLoss = $waitForFile($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'finish-ack-loss.json', $process, 15.0);
    if (!is_array($ackLoss)) {
        throw new \RuntimeException('finish acknowledgement-loss barrier was not reached');
    }
    $intentAfterFinish = $readIntent($fixture);
    $projection = is_array($intentAfterFinish) ? json_decode((string)$intentAfterFinish['dpiResult'], true) : null;
    $test->check('post-dispatch ordinary facts save wins finish facts CAS and records bounded projection diagnostic',
        is_array($ldapReturned) && 1 === ($ldapReturned['move_calls'] ?? null)
        && true === ($ldapReturned['permit_consumed'] ?? false) && 'fake' === ($ldapReturned['ldap_transport'] ?? null)
        && $savedFacts instanceof FOG\Items\HostDirectory
        && is_array($factsAfterWriter) && $concurrentDN === $factsAfterWriter['hdComputerDN']
        && $concurrentObserved === $factsAfterWriter['hdObservedAt'] && null === $factsAfterWriter['hdPlacementAt']
        && is_array($ackLoss) && 'u205_commit_ack_lost_after_commit' === ($ackLoss['reason'] ?? null)
        && ($ackLoss['row']['dpiResult'] ?? null) === ($intentAfterFinish['dpiResult'] ?? null)
        && is_array($intentAfterFinish) && 1 === (int)$intentAfterFinish['dpiGeneration']
        && 'SUCCEEDED' === $intentAfterFinish['dpiStatus'] && is_array($projection)
        && 1 === ($projection['version'] ?? null) && true === ($projection['ldap_success'] ?? null)
        && 'ldap_success' === ($projection['ldap_result'] ?? null)
        && hash_equals(hash('sha256', 'ldap_success'), (string)($projection['ldap_result_sha256'] ?? ''))
        && 'cas_miss' === ($projection['facts_projection'] ?? null)
        && false === ($projection['truncated'] ?? null)
        && strlen((string)$intentAfterFinish['dpiResult']) <= 512);

    // After this same host's finish CAS miss, process the next current
    // protocol-2 observation through the real method with a valid local
    // marker. Its body is simulated; LDAP remains the fictional stub below.
    $protocolHost = $fixture['host'];
    $checkDC = 'dc1.u205.invalid';
    $config = ['version' => 1, 'protocol' => 2, 'allow_create' => true,
        'name' => $fixture['name'], 'domain' => 'U205',
        'config_revision' => bin2hex(random_bytes(16)), 'target' => $fixture['target']];
    $attemptID = bin2hex(random_bytes(16));
    $fingerprint = $fixture['fingerprint'];
    $state = ['version' => 1, 'protocol' => 2, 'operation' => 'rebind', 'mode' => 'existing',
        'target' => $fixture['target'], 'host_id' => $fixture['host_id'],
        'name' => $fixture['name'], 'domain' => 'U205', 'dc' => $checkDC, 'check_dc' => $checkDC,
        'phase' => 'verified', 'attempt_id' => $attemptID, 'fingerprint' => $fingerprint,
        'expected_current' => $fixture['name'], 'expected_pending' => $fixture['name'],
        'observed_at' => time() - 30, 'placement_hold' => true,
        'reason' => 'directory_recovery_confirmation_required', 'secure_channel' => 'broken', 'native_status' => 0];
    $state['config_revision'] = FOG\Agent\DirectoryOperation::revision($protocolHost, $config, $checkDC);
    $pdo->prepare('UPDATE hostDirectory SET hdRejoinConfig=?,hdRejoinState=? WHERE hdHostID=?')
        ->execute([json_encode($config, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $fixture['host_id']]);
    $observation = ['revision' => $state['config_revision'], 'dc' => $checkDC, 'dc_reachable' => true,
        'current_name' => $fixture['name'], 'pending_name' => $fixture['name'],
        'domain' => 'U205', 'netbios' => 'U205', 'role' => 'workstation', 'secure_channel' => 'true',
        'boot' => ['marker' => 'u205-simulated-boot', 'started' => gmdate(DATE_ATOM)]];
    $observeBody = ['directory' => ['joined' => true, 'kind' => 'domain'],
        'directory_observation' => $observation,
        'directory_attempt' => ['phase' => 'verified', 'host_id' => $fixture['host_id'],
            'attempt_id' => $attemptID, 'fingerprint' => $fingerprint]];
    $validatedLocalAttempt = FOG\Agent\DirectoryOperation::localAttempt($observeBody);
    FOG\Agent\DirectoryOperation::observe($protocolHost, $observeBody);
    $savedProtocolState = FOG\Agent\DirectoryRejoin::settings($protocolHost);
    U205ProjectionLdapStub::reset();
    U205ProjectionLdapStub::$context = [
        'domain' => 'u205.invalid', 'base_dn' => $rootDN, 'dc' => $checkDC,
        'naming_contexts' => [$rootDN, 'CN=Configuration,' . $rootDN, 'CN=Schema,CN=Configuration,' . $rootDN]
    ];
    U205ProjectionLdapStub::$containerRecord = $fixture['destination'];
    $actualDN = 'CN=' . $fixture['name'] . ',' . $managedDN;
    $protocolRecord = $fixture['target'];
    $protocolRecord['dn'] = $actualDN;
    U205ProjectionLdapStub::$records[strtolower($fixture['name'])] = [
        'status' => 'found', 'record' => $protocolRecord
    ];
    list($observedHost, $observedDirectory) = $freshModels($fixture);
    FOG\Agent\DirectoryPlacement::ensure($observedHost, $observedDirectory);
    $protocolFactsAfterLearn = $readFacts($fixture);
    $protocolIntent = $readIntent($fixture);
    $protocolCounts = [U205ProjectionLdapStub::$connects, count(U205ProjectionLdapStub::$lookups), count(U205ProjectionLdapStub::$moves)];
    list($observedAgainHost, $observedAgainDirectory) = $freshModels($fixture);
    FOG\Agent\DirectoryPlacement::ensure($observedAgainHost, $observedAgainDirectory);
    $test->check('after the same host finish CAS miss, protocol-2 observe with simulated body and valid local marker permits fresh already-correct read/cooldown without Move',
        2 === ($savedProtocolState['state']['protocol'] ?? null)
        && false === ($savedProtocolState['state']['placement_hold'] ?? true)
        && $validatedLocalAttempt === $observeBody['directory_attempt']
        && FOG\Agent\DirectoryOperation::observationFresh(
            $protocolHost, $savedProtocolState['config'], $savedProtocolState['state'])
        && FOG\Agent\DirectoryOperation::placementHealthy(
            $protocolHost, $savedProtocolState['config'], $savedProtocolState['state'])
        && $observation === ($savedProtocolState['state']['observation'] ?? null)
        && is_array($protocolFactsAfterLearn) && $actualDN === $protocolFactsAfterLearn['hdComputerDN']
        && !empty($protocolFactsAfterLearn['hdPlacementAt']) && '' === $protocolFactsAfterLearn['hdPlacementError']
        && is_array($protocolIntent) && 1 === (int)$protocolIntent['dpiGeneration']
        && 'SUCCEEDED' === $protocolIntent['dpiStatus']
        && $intentAfterFinish['dpiResult'] === $protocolIntent['dpiResult']
        && 1 === $protocolCounts[0] && 1 === $protocolCounts[1] && 0 === $protocolCounts[2]
        && $protocolCounts === [U205ProjectionLdapStub::$connects, count(U205ProjectionLdapStub::$lookups), count(U205ProjectionLdapStub::$moves)]);

    // A real new facts drift clears the learned cooldown. The fake LDAP now
    // reports that same object outside the destination, so normal ensure must
    // prepare/dispatch generation 2 and perform exactly one fake GUID move.
    $driftDN = $fixture['old_dn'];
    $driftObserved = gmdate('Y-m-d H:i:s', time() + 1);
    $driftSaved = (new FOG\Items\HostDirectory($fixture['hd_id']))
        ->set('computerDN', $driftDN)->set('observedAt', $driftObserved)
        ->set('placementAt', null)->set('placementError', '')->save();
    U205ProjectionLdapStub::reset();
    U205ProjectionLdapStub::$context = [
        'domain' => 'u205.invalid', 'base_dn' => $rootDN, 'dc' => 'dc1.u205.invalid',
        'naming_contexts' => [$rootDN, 'CN=Configuration,' . $rootDN, 'CN=Schema,CN=Configuration,' . $rootDN]
    ];
    U205ProjectionLdapStub::$containerRecord = $fixture['destination'];
    $driftRecord = $fixture['target'];
    $driftRecord['dn'] = $driftDN;
    U205ProjectionLdapStub::$records[strtolower($fixture['name'])] = ['status' => 'found', 'record' => $driftRecord];
    list($driftHost, $driftDirectory) = $freshModels($fixture);
    FOG\Agent\DirectoryPlacement::ensure($driftHost, $driftDirectory);
    $intentGeneration2 = $readIntent($fixture);
    $factsGeneration2 = $readFacts($fixture);
    $test->check('fresh actual drift advances terminal slot to generation 2 and performs one normal GUID move',
        $driftSaved instanceof FOG\Items\HostDirectory
        && is_array($intentGeneration2) && 2 === (int)$intentGeneration2['dpiGeneration']
        && 'SUCCEEDED' === $intentGeneration2['dpiStatus']
        && 'applied' === (json_decode((string)$intentGeneration2['dpiResult'], true)['facts_projection'] ?? null)
        && count(U205ProjectionLdapStub::$moves) === 1
        && $actualDN === ($factsGeneration2['hdComputerDN'] ?? null)
        && !empty($factsGeneration2['hdPlacementAt']));
    $beforeOldCallback = [$intentGeneration2, $factsGeneration2];

    // Resume the old process-local receipt on its original healthy PDO and
    // SQL connection. The old generation must not touch gen-2 state.
    file_put_contents($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'retry-finish', 'go', LOCK_EX);
    $workerResult = $waitForFile($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'worker-result.json', $process, 15.0);
    $workerDeadline = microtime(true) + 5.0;
    do {
        $workerStatus = proc_get_status($process);
        if (!$workerStatus['running']) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $workerDeadline);
    if (isset($workerStatus['running']) && $workerStatus['running']) {
        proc_terminate($process, 9);
        $workerStatus['exitcode'] = -1;
    }
    $workerStdout = stream_get_contents($pipes[1]);
    $workerStderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $workerCloseCode = proc_close($process);
    $workerExitCode = isset($workerStatus['exitcode']) && $workerStatus['exitcode'] >= 0
        ? $workerStatus['exitcode'] : $workerCloseCode;
    $process = null;
    $pipes = [];
    $intentAfterOldCallback = $readIntent($fixture);
    $factsAfterOldCallback = $readFacts($fixture);
    $test->check('old successful callback receipt on original healthy PDO cannot alter new generation or projected facts',
        is_array($workerResult) && 'directory_placement_result_generation_changed' === ($workerResult['retry_reason'] ?? null)
        && true === ($workerResult['original_pdo_connection_preserved'] ?? false)
        && 0 === $workerExitCode && false === (bool)$workerStderr
        && false !== strpos((string)$workerStdout, 'PASS U2-05 projection worker')
        && $intentGeneration2 === $intentAfterOldCallback
        && $factsGeneration2 === $factsAfterOldCallback
        && $beforeOldCallback === [$intentAfterOldCallback, $factsAfterOldCallback]);

    // Changed Host and configuration snapshots refuse before intent success;
    // this specifically exercises request binding rather than a SUCCEEDED slot.
    foreach (['host' => 'name', 'config' => 'ADOU'] as $changeCase => $field) {
        $changed = $createFixture('changed-' . $changeCase);
        if ('host' === $changeCase) {
            $changed['host']->set('name', $changed['name'] . 'EDIT');
        } else {
            $changedOU = 'OU=Changed,' . $rootDN;
            $changed['host']->set('ADOU', $changedOU);
            U205ProjectionLdapStub::$containerRecord = ['guid' => $guid(), 'dn' => $changedOU];
        }
        U205ProjectionLdapStub::$records[strtolower($changed['name'])] = ['status' => 'found', 'record' => $changed['target']];
        FOG\Agent\DirectoryPlacement::ensure($changed['host'], $changed['directory']);
        $row = $readFacts($changed);
        $slot = $readIntent($changed);
        $test->check('changed ' . $changeCase . ' snapshot refuses before success or Move',
            'directory_placement_request_changed' === ($row['hdPlacementError'] ?? null)
            && null === $slot && 0 === count(U205ProjectionLdapStub::$moves));
    }

    // Target identity changes on the authoritative post-PREPARED lookup.
    // The unsent generation must be canceled and no fake Move called.
    $targetChange = $createFixture('changed-target');
    U205ProjectionLdapStub::$records[strtolower($targetChange['name'])] = [
        'status' => 'found', 'record' => $targetChange['target']
    ];
    $replacement = $targetChange['target'];
    $replacement['guid'] = $guid();
    $replacement['sid'] = 'S-1-5-21-205-' . random_int(2000000000, 3000000000);
    U205ProjectionLdapStub::$recordQueues[strtolower($targetChange['name'])] = [
        ['status' => 'found', 'record' => $targetChange['target']],
        ['status' => 'found', 'record' => $replacement]
    ];
    FOG\Agent\DirectoryPlacement::ensure($targetChange['host'], $targetChange['directory']);
    $targetIntent = $readIntent($targetChange);
    $targetFacts = $readFacts($targetChange);
    $test->check('changed Target identity cancels unsent generation before success or Move',
        'CANCELLED' === ($targetIntent['dpiStatus'] ?? null)
        && 1 === (int)($targetIntent['dpiGeneration'] ?? 0)
        && 'directory_placement_identity_changed_before_dispatch' === ($targetFacts['hdPlacementError'] ?? null)
        && 0 === count(U205ProjectionLdapStub::$moves));
} finally {
    if (is_resource($process)) {
        if (!is_file($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'facts-saved')) {
            @file_put_contents($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'facts-saved', 'abort', LOCK_EX);
        }
        if (!is_file($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'retry-finish')) {
            @file_put_contents($resolvedBarrierDir . DIRECTORY_SEPARATOR . 'retry-finish', 'abort', LOCK_EX);
        }
        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process, 9);
        }
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($process);
    }
    foreach ($fixtures as $fixture) {
        $pdo->prepare('DELETE FROM directoryPlacementIntent WHERE dpiHostID=?')->execute([$fixture['host_id']]);
        $pdo->prepare('DELETE FROM auditLog WHERE alSubjectType=\'host\' AND alSubjectID=?')->execute([$fixture['host_id']]);
        $pdo->prepare('DELETE FROM history WHERE hSubjectType=\'host\' AND hSubjectID=?')->execute([$fixture['host_id']]);
        $pdo->prepare('DELETE FROM hostDirectory WHERE hdHostID=?')->execute([$fixture['host_id']]);
        $pdo->prepare('DELETE FROM hosts WHERE hostID=?')->execute([$fixture['host_id']]);
    }
    foreach (glob($resolvedBarrierDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($resolvedBarrierDir);
}

$test->finish();
