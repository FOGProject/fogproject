<?php
/**
 * Isolated child for the U2-05 result-projection barrier. It uses a real PDO
 * and a one-shot fake LDAP permit boundary; it never opens an LDAP connection.
 */

class U205ProjectionAckPdo extends \PDO
{
    public $loseNextCommitAck = false;

    public function commit(): bool
    {
        $committed = parent::commit();
        if ($this->loseNextCommitAck) {
            $this->loseNextCommitAck = false;
            throw new \RuntimeException('u205_commit_ack_lost_after_commit');
        }
        return $committed;
    }
}

class U205ProjectionLdapStub
{
    public static $moves = 0;

    public function moveTo($entry, $parent, $permit = null)
    {
        FOG\Agent\DirectoryPlacementIntent::consumePermit($permit, $this, $entry, $parent);
        self::$moves++;
        return true;
    }

    public static function guidDN($guid)
    {
        return '<GUID=' . $guid . '>';
    }
}

class_alias(U205ProjectionLdapStub::class, 'FOG\\Net\\FOGLdap', false);

$barrierDir = isset($argv[1]) ? realpath($argv[1]) : false;
$input = isset($argv[2]) ? json_decode($argv[2], true) : null;
$tmpRoot = rtrim((string)realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (false === $barrierDir || 0 !== strpos($barrierDir, $tmpRoot)
    || 0 !== strpos(basename($barrierDir), 'u205-projection-')
    || !is_array($input) || !isset($input['ticket']['guid_hex'])
    || !preg_match('/^[a-f0-9]{32}$/D', (string)$input['ticket']['guid_hex'])) {
    fwrite(STDERR, "invalid U2-05 scratch worker arguments\n");
    exit(2);
}
$input['ticket']['guid'] = hex2bin($input['ticket']['guid_hex']);
unset($input['ticket']['guid_hex']);
$ticket = $input['ticket'];
$write = static function ($name, array $value) use ($barrierDir) {
    $path = $barrierDir . DIRECTORY_SEPARATOR . $name;
    $tmp = $path . '.tmp';
    if (false === file_put_contents($tmp, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX)
        || !rename($tmp, $path)) {
        throw new \RuntimeException('cannot write U2-05 barrier signal');
    }
};
$waitFor = static function ($name) use ($barrierDir) {
    $path = $barrierDir . DIRECTORY_SEPARATOR . $name;
    $deadline = microtime(true) + 25.0;
    do {
        if (is_file($path)) {
            return true;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return false;
};

try {
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:') || '' === $dsn) {
        throw new \RuntimeException('U2-05 worker requires explicit MySQL scratch opt-in');
    }
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u205-projection-worker');
    $pdo = new U205ProjectionAckPdo(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new \RuntimeException('U2-05 worker database is outside scratch prefix');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new \RuntimeException('U2-05 worker database does not match DATABASE_NAME');
    }
    $link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

    $ldap = new U205ProjectionLdapStub();
    $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
    $originalConnectionId = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $entry = U205ProjectionLdapStub::guidDN($ticket['payload']['target']['guid']);
    $parent = U205ProjectionLdapStub::guidDN($ticket['payload']['destination']['guid']);
    $ldap->moveTo($entry, $parent, $token);
    $write('ldap-returned.json', ['move_calls' => U205ProjectionLdapStub::$moves,
        'permit_consumed' => true, 'ldap_transport' => 'fake']);
    if (!$waitFor('facts-saved')) {
        throw new \RuntimeException('facts-save barrier timed out');
    }

    // The parent has completed an ordinary, fenced HostDirectory facts save
    // after the fake LDAP success and before this result transaction begins.
    $pdo->loseNextCommitAck = true;
    $finishReason = '';
    try {
        FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'ldap_success');
    } catch (\Throwable $e) {
        $finishReason = $e->getMessage();
    }

    $stored = $pdo->prepare('SELECT dpiGeneration,dpiStatus,dpiResult FROM directoryPlacementIntent WHERE dpiGUID=?');
    $stored->bindValue(1, $ticket['guid'], \PDO::PARAM_LOB);
    $stored->execute();
    $row = $stored->fetch(\PDO::FETCH_ASSOC) ?: [];
    $connectionAfterAckLoss = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $write('finish-ack-loss.json', ['reason' => $finishReason, 'row' => $row,
        'connection_id' => $connectionAfterAckLoss]);
    if (!$waitFor('retry-finish')) {
        throw new \RuntimeException('old-receipt retry barrier timed out');
    }

    // Retry the retained receipt on the same original, still-healthy PDO and
    // SQL connection. H02 binds both identities to this process-local result.
    $retryReason = '';
    try {
        FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'ldap_success');
    } catch (\Throwable $e) {
        $retryReason = $e->getMessage();
    }
    $connectionAfterRetry = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $after = $pdo->prepare('SELECT dpiGeneration,dpiStatus,dpiResult FROM directoryPlacementIntent WHERE dpiGUID=?');
    $after->bindValue(1, $ticket['guid'], \PDO::PARAM_LOB);
    $after->execute();
    $finalRow = $after->fetch(\PDO::FETCH_ASSOC) ?: [];
    $hostFacts = $pdo->prepare('SELECT hdComputerDN,hdObservedAt,hdPlacementAt FROM hostDirectory WHERE hdHostID=?');
    $hostFacts->execute([(int)$ticket['payload']['host_id']]);
    $facts = $hostFacts->fetch(\PDO::FETCH_ASSOC) ?: [];
    $write('worker-result.json', ['finish_reason' => $finishReason,
        'retry_reason' => $retryReason, 'row' => $finalRow, 'facts' => $facts,
        'original_pdo_connection_preserved' => $originalConnectionId === $connectionAfterAckLoss
            && $originalConnectionId === $connectionAfterRetry]);
    echo "PASS U2-05 projection worker\n";
} catch (\Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
}
