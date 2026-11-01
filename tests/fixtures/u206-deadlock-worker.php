<?php
/** Isolated real-PDO worker for U2-06; LDAP is represented by one inert permit use. */

class U206DeadlockStatement extends \PDOStatement
{
    private $barrierDirectory;
    private $intentGuidHex;
    private $boundValues = [];
    private static $intentSignalWritten = false;

    protected function __construct($barrierDirectory, $intentGuidHex)
    {
        $this->barrierDirectory = (string)$barrierDirectory;
        $this->intentGuidHex = (string)$intentGuidHex;
    }

    public function bindValue($param, $value, $type = \PDO::PARAM_STR): bool
    {
        $bound = parent::bindValue($param, $value, $type);
        if ($bound) {
            $this->boundValues[$param] = $value;
        }
        return $bound;
    }

    public function execute($params = null)
    {
        $executed = parent::execute($params);
        $sql = preg_replace('/\s+/', ' ', trim((string)$this->queryString));
        $isFinishLock = false !== stripos($sql,
            'SELECT * FROM directoryPlacementIntent WHERE dpiGUID=?')
            && false !== stripos($sql, ' FOR UPDATE');
        $boundGuid = is_array($params) && array_key_exists(0, $params)
            ? $params[0]
            : (is_array($params) && array_key_exists(1, $params)
                ? $params[1] : ($this->boundValues[1] ?? null));
        $guid = null === $boundGuid ? '' : bin2hex((string)$boundGuid);
        if ($executed && $isFinishLock && hash_equals($this->intentGuidHex, $guid)
            && !self::$intentSignalWritten) {
            self::$intentSignalWritten = true;
            u206WriteBarrier($this->barrierDirectory, 'intent-locked.json', [
                'exact_intent_locked' => true,
                'guid_hex' => $guid,
                'binding_source' => is_array($params) && array_key_exists(0, $params)
                    ? 'execute-params-0' : (is_array($params) && array_key_exists(1, $params)
                        ? 'execute-params-1' : 'bindValue-1')
            ]);
        }
        return $executed;
    }
}

/** A genuine PDO connection with no behavior overrides beyond its test statement class. */
class U206DeadlockPdo extends \PDO
{
}

class U206DeadlockLdapStub
{
    public $simulatedCount = 0;

    public function moveTo($entry, $parent, $permit = null)
    {
        FOG\Agent\DirectoryPlacementIntent::consumePermit($permit, $this, $entry, $parent);
        $this->simulatedCount++;
        return true;
    }

    public static function guidDN($guid)
    {
        return '<GUID=' . $guid . '>';
    }
}

class_alias(U206DeadlockLdapStub::class, 'FOG\\Net\\FOGLdap', false);

function u206WriteBarrier($directory, $name, array $data)
{
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (false === file_put_contents($tmp, $json, LOCK_EX) || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new \RuntimeException('U2-06 worker cannot atomically publish ' . $name);
    }
}

function u206WaitFor($directory, $name, $seconds)
{
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    $deadline = microtime(true) + $seconds;
    do {
        if (is_file($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    return null;
}

$barrierDirectory = isset($argv[1]) ? realpath($argv[1]) : false;
$input = isset($argv[2]) ? json_decode($argv[2], true) : null;
$tempRoot = rtrim((string)realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (false === $barrierDirectory || 0 !== strpos($barrierDirectory, $tempRoot)
    || 0 !== strpos(basename($barrierDirectory), 'u206-deadlock-')
    || !is_array($input) || !isset($input['ticket']['guid_hex'])
    || !preg_match('/^[a-f0-9]{32}$/D', (string)$input['ticket']['guid_hex'])) {
    fwrite(STDERR, "invalid U2-06 worker arguments\n");
    exit(2);
}
$ticket = $input['ticket'];
$ticket['guid'] = hex2bin($ticket['guid_hex']);
$guidHex = $ticket['guid_hex'];
unset($ticket['guid_hex']);

try {
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || '' === $dsn || 0 !== strpos($dsn, 'mysql:')) {
        throw new \RuntimeException('U2-06 worker requires explicit MySQL scratch opt-in');
    }
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u206-deadlock-worker');
    $pdo = new U206DeadlockPdo($dsn, getenv('MJ1_DB_USER') ?: 'root', getenv('MJ1_DB_PASSWORD') ?: '', [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STATEMENT_CLASS => [U206DeadlockStatement::class,
            [$barrierDirectory, $guidHex]]
    ]);
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new \RuntimeException('U2-06 worker database is outside the scratch prefix');
    }
    $serverVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    if (false === stripos($serverVersion, 'MariaDB')) {
        throw new \RuntimeException('U2-06 worker requires MariaDB');
    }
    if (!(int)$pdo->query('SELECT @@innodb_deadlock_detect')->fetchColumn()) {
        throw new \RuntimeException('U2-06 worker requires InnoDB deadlock detection enabled');
    }
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=10');
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new \RuntimeException('U2-06 worker DATABASE_NAME mismatch');
    }

    $link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);

    $ldap = new U206DeadlockLdapStub();
    $permit = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
    $entry = U206DeadlockLdapStub::guidDN($ticket['payload']['target']['guid']);
    $parent = U206DeadlockLdapStub::guidDN($ticket['payload']['destination']['guid']);
    $ldap->moveTo($entry, $parent, $permit);
    u206WriteBarrier($barrierDirectory, 'ldap-success.json', [
        'permit_consumed' => true,
        'simulated_count' => $ldap->simulatedCount
    ]);

    if (null === u206WaitFor($barrierDirectory, 'go-finish.json', 20.0)) {
        throw new \RuntimeException('U2-06 go-finish barrier timed out');
    }

    $finishCalls = 0;
    $finishError = null;
    $sqlstate = null;
    $driverCode = null;
    $finishCalls++;
    try {
        FOG\Agent\DirectoryPlacementIntent::finish($permit, true, 'ldap_success');
    } catch (\Throwable $e) {
        $finishError = get_class($e);
        if ($e instanceof \PDOException) {
            $info = is_array($e->errorInfo ?? null) ? $e->errorInfo : [];
            $sqlstate = (string)($info[0] ?? $e->getCode());
            $driverCode = isset($info[1]) ? (int)$info[1] : null;
        }
    }

    // No second finish call and no second permit/LDAP use: leave the durable
    // MAY_HAVE_RUN record as the only authority after a deadlock victim.
    u206WriteBarrier($barrierDirectory, 'worker-result.json', [
        'finish_calls' => $finishCalls,
        'finish_error' => $finishError,
        'sqlstate' => $sqlstate,
        'driver_code' => $driverCode,
        'permit_consumed' => true,
        'simulated_count' => $ldap->simulatedCount
    ]);
    echo "U2-06 worker recorded finish outcome\n";
} catch (\Throwable $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
}
