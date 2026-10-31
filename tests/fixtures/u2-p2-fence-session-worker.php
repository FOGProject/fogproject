<?php
/** Isolated process fault worker for u2-p2-fence-session.test.php. */

class U2P2FenceSessionStatement extends PDOStatement
{
    public static $fault = '';
    public static $getLockExecutes = 0;
    public static $updateHostExecutes = 0;
    public static $releaseLockExecutes = 0;
    public static $actualGetLock = null;
    public static $actualRelease = null;
    public static $faultInjected = false;

    protected function __construct()
    {
    }

    public function execute($params = null): bool
    {
        if (preg_match('/\bGET_LOCK\s*\(/i', $this->queryString)) {
            self::$getLockExecutes++;
        }
        if (preg_match('/\bRELEASE_LOCK\s*\(/i', $this->queryString)) {
            self::$releaseLockExecutes++;
        }
        if (preg_match('/^\s*UPDATE\s+`?hosts`?\b/i', $this->queryString)) {
            self::$updateHostExecutes++;
        }
        return null === $params ? parent::execute() : parent::execute($params);
    }

    public function fetchAll($how = null, $class_name = null, $ctor_args = null, ...$args): array
    {
        // Match PDOStatement's named parameters on PHP 8 while remaining
        // compatible with PHP 7.4's variadic fetch-mode arguments. Preserve
        // the caller's original arity when delegating to the real statement.
        switch (func_num_args()) {
            case 0:
                $rows = parent::fetchAll();
                break;
            case 1:
                $rows = parent::fetchAll($how);
                break;
            case 2:
                $rows = parent::fetchAll($how, $class_name);
                break;
            default:
                $rows = parent::fetchAll($how, $class_name, $ctor_args, ...$args);
                break;
        }
        if (self::$faultInjected || !is_array($rows) || !$rows) {
            return $rows;
        }
        if (in_array(self::$fault, ['get-lock-null', 'get-lock-exception'], true)
            && preg_match('/\bGET_LOCK\s*\(/i', $this->queryString)
            && array_key_exists('acquired', $rows[0])) {
            self::$actualGetLock = $rows[0]['acquired'];
            self::$faultInjected = true;
            if ('get-lock-exception' === self::$fault) {
                throw new RuntimeException('fixture_get_lock_reply_exception');
            }
            return [['acquired' => null]];
        }
        if (in_array(self::$fault, ['release-zero', 'release-null', 'release-exception'], true)
            && preg_match('/\bRELEASE_LOCK\s*\(/i', $this->queryString)
            && array_key_exists('released', $rows[0])) {
            self::$actualRelease = $rows[0]['released'];
            self::$faultInjected = true;
            if ('release-exception' === self::$fault) {
                throw new RuntimeException('fixture_release_reply_exception');
            }
            return [['released' => 'release-zero' === self::$fault ? 0 : null]];
        }
        return $rows;
    }
}

function u2p2FenceSessionPdo()
{
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('' === $dsn || '1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')) {
        throw new RuntimeException('explicit scratch opt-in and mysql DSN are required');
    }
    $pdo = new PDO(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STATEMENT_CLASS => [U2P2FenceSessionStatement::class, []]
        ]
    );
    $identity = $pdo->query(
        'SELECT DATABASE() AS db, CASE WHEN @@lower_case_table_names=0 THEN DATABASE() ELSE LOWER(DATABASE()) END AS namespace_db, CONNECTION_ID() AS id'
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($identity)
        || ('fog_mj1' !== $identity['db'] && 0 !== strpos($identity['db'], 'fog_mj1_'))) {
        throw new RuntimeException('database name must use the fog_mj1 scratch prefix');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $identity['db']);
    } elseif (DATABASE_NAME !== $identity['db']) {
        throw new RuntimeException('DATABASE_NAME differs from the validated scratch DB');
    }
    return [$pdo, $identity];
}

function u2p2FenceSessionInstall(PDO $pdo)
{
    $property = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $property->setAccessible(true);
    $property->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    return $db;
}

function u2p2FenceSessionLockKey(PDO $pdo)
{
    $row = $pdo->query(
        'SELECT CASE WHEN @@lower_case_table_names=0 THEN DATABASE() ELSE LOWER(DATABASE()) END AS namespace_db'
    )->fetch(PDO::FETCH_ASSOC);
    return 'fog.mj1.name.v1:' . substr(hash('sha256', $row['namespace_db']), 0, 40);
}

function u2p2FenceSessionEmit(array $result, $exitCode = 0)
{
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
    exit($exitCode);
}

$mode = $argv[1] ?? '';
if (!in_array($mode, [
    'pdo-swap', 'stale-lock', 'autocommit-off', 'get-lock-null',
    'release-zero', 'release-null', 'release-exception', 'get-lock-exception', 'probe'
], true)) {
    fwrite(STDERR, "invalid fence-session worker mode\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-fence-session-worker');
    list($pdo, $identity) = u2p2FenceSessionPdo();
    $db = u2p2FenceSessionInstall($pdo);

    if ('pdo-swap' === $mode) {
        $hostID = (int)($argv[2] ?? 0);
        $replacementName = (string)($argv[3] ?? '');
        if ($hostID <= 0 || !preg_match('/^P2FS[A-F0-9]{12}$/D', $replacementName)) {
            throw new RuntimeException('invalid fixture arguments');
        }
        $secondPdo = new PDO(
            (string)getenv('MJ1_DB_DSN'),
            getenv('MJ1_DB_USER') ?: 'root',
            getenv('MJ1_DB_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_STATEMENT_CLASS => [U2P2FenceSessionStatement::class, []]
            ]
        );
        $before = $pdo->prepare('SELECT * FROM hosts WHERE hostID=?');
        $before->execute([$hostID]);
        $beforeRow = $before->fetch(PDO::FETCH_ASSOC);
        $oldName = is_array($beforeRow) && array_key_exists('hostName', $beforeRow)
            ? $beforeRow['hostName'] : '__missing__';
        $beforeHash = hash('sha256', serialize($beforeRow));
        $callback = false;
        $reason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () use ($secondPdo, $hostID, $replacementName, $db, &$callback) {
                $callback = true;
                $property = new ReflectionProperty(FOG\Db\PDODB::class, '_link');
                $property->setAccessible(true);
                $property->setValue(null, $secondPdo);
                $db->query(
                    'UPDATE hosts SET hostName=:new_name WHERE hostID=:host_id',
                    [],
                    ['new_name' => $replacementName, 'host_id' => $hostID]
                );
            });
        } catch (Throwable $e) {
            $reason = $e->getMessage();
        }
        $after = $secondPdo->prepare('SELECT * FROM hosts WHERE hostID=?');
        $after->execute([$hostID]);
        $afterRow = $after->fetch(PDO::FETCH_ASSOC);
        $newName = is_array($afterRow) && array_key_exists('hostName', $afterRow)
            ? $afterRow['hostName'] : '__missing__';
        $afterHash = hash('sha256', serialize($afterRow));
        $secondReason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () {
                throw new RuntimeException('poisoned fence callback must not run');
            });
        } catch (Throwable $e) {
            $secondReason = $e->getMessage();
        }
        u2p2FenceSessionEmit([
            'mode' => $mode,
            'connection_a' => $identity['id'],
            'connection_b' => $secondPdo->query('SELECT CONNECTION_ID()')->fetchColumn(),
            'callback_entered' => $callback,
            'reason' => $reason,
            'second_reason' => $secondReason,
            'old_name' => $oldName,
            'new_name' => $newName,
            'before_row_hash' => $beforeHash,
            'after_row_hash' => $afterHash,
            'replacement_name' => $replacementName,
            'update_executes' => U2P2FenceSessionStatement::$updateHostExecutes,
            'get_lock_executes' => U2P2FenceSessionStatement::$getLockExecutes
        ]);
    }

    if ('stale-lock' === $mode) {
        $key = u2p2FenceSessionLockKey($pdo);
        $q = $pdo->prepare('SELECT GET_LOCK(?,0) AS acquired');
        $q->execute([$key]);
        $acquired = $q->fetch(PDO::FETCH_ASSOC);
        $acquiredPresent = is_array($acquired) && array_key_exists('acquired', $acquired);
        $callback = false;
        $reason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () use (&$callback) {
                $callback = true;
            });
        } catch (Throwable $e) {
            $reason = $e->getMessage();
        }
        $owner = $pdo->prepare('SELECT IS_USED_LOCK(?) AS owner');
        $owner->execute([$key]);
        $ownerID = $owner->fetchColumn();
        u2p2FenceSessionEmit([
            'mode' => $mode,
            'connection_id' => $identity['id'],
            'acquired_present' => $acquiredPresent,
            'acquired' => $acquiredPresent ? $acquired['acquired'] : '__missing__',
            'reason' => $reason,
            'callback_entered' => $callback,
            'lock_owner_after_refusal' => $ownerID,
            'get_lock_executes' => U2P2FenceSessionStatement::$getLockExecutes
        ]);
    }

    if ('autocommit-off' === $mode) {
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
        $callback = false;
        $reason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () use (&$callback) {
                $callback = true;
            });
        } catch (Throwable $e) {
            $reason = $e->getMessage();
        }
        $session = $pdo->query('SELECT @@autocommit AS autocommit')->fetch(PDO::FETCH_ASSOC);
        u2p2FenceSessionEmit([
            'mode' => $mode,
            'autocommit' => (int)$session['autocommit'],
            'reason' => $reason,
            'callback_entered' => $callback,
            'get_lock_executes' => U2P2FenceSessionStatement::$getLockExecutes,
            'update_executes' => U2P2FenceSessionStatement::$updateHostExecutes
        ]);
    }

    if (in_array($mode, ['get-lock-null', 'get-lock-exception'], true)) {
        U2P2FenceSessionStatement::$fault = $mode;
        $callback = false;
        $firstReason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () use (&$callback) {
                $callback = true;
            });
        } catch (Throwable $e) {
            $firstReason = $e->getMessage();
        }
        $secondCallback = false;
        $secondReason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () use (&$secondCallback) {
                $secondCallback = true;
            });
        } catch (Throwable $e) {
            $secondReason = $e->getMessage();
        }
        $key = u2p2FenceSessionLockKey($pdo);
        $owner = $pdo->prepare('SELECT IS_USED_LOCK(?) AS owner');
        $owner->execute([$key]);
        u2p2FenceSessionEmit([
            'mode' => $mode,
            'connection_id' => $identity['id'],
            'actual_get_lock' => U2P2FenceSessionStatement::$actualGetLock,
            'fault_injected' => U2P2FenceSessionStatement::$faultInjected,
            'first_reason' => $firstReason,
            'second_reason' => $secondReason,
            'callback_entered' => $callback,
            'second_callback_entered' => $secondCallback,
            'lock_owner_after_refusal' => $owner->fetchColumn(),
            'get_lock_executes' => U2P2FenceSessionStatement::$getLockExecutes,
            'update_executes' => U2P2FenceSessionStatement::$updateHostExecutes
        ]);
    }

    if (in_array($mode, ['release-zero', 'release-null', 'release-exception'], true)) {
        $hostID = (int)($argv[2] ?? 0);
        $newName = (string)($argv[3] ?? '');
        if ($hostID <= 0 || !preg_match('/^P2FS[A-F0-9]{12}$/D', $newName)) {
            throw new RuntimeException('invalid fixture arguments');
        }
        U2P2FenceSessionStatement::$fault = $mode;
        $callback = false;
        $callbackQuerySucceeded = false;
        $firstReason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () use ($db, $hostID, $newName, &$callback, &$callbackQuerySucceeded) {
                $callback = true;
                $db->query(
                    'UPDATE `hosts` SET `hosts`.`hostName`=:new_name WHERE `hosts`.`hostID`=:host_id',
                    [],
                    ['new_name' => $newName, 'host_id' => $hostID]
                );
                $callbackQuerySucceeded = false === $db->error;
                return $callbackQuerySucceeded;
            });
        } catch (Throwable $e) {
            $firstReason = $e->getMessage();
        }
        $row = $pdo->prepare('SELECT hostName FROM hosts WHERE hostID=?');
        $row->execute([$hostID]);
        $storedName = $row->fetchColumn();
        $key = u2p2FenceSessionLockKey($pdo);
        $owner = $pdo->prepare('SELECT IS_USED_LOCK(?) AS owner');
        $owner->execute([$key]);
        $ownerAfterRelease = $owner->fetchColumn();

        $secondCallback = false;
        $secondReason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () use (&$secondCallback) {
                $secondCallback = true;
            });
        } catch (Throwable $e) {
            $secondReason = $e->getMessage();
        }
        u2p2FenceSessionEmit([
            'mode' => $mode,
            'connection_id' => $identity['id'],
            'callback_entered' => $callback,
            'callback_query_succeeded' => $callbackQuerySucceeded,
            'first_reason' => $firstReason,
            'stored_name' => $storedName,
            'expected_name' => $newName,
            'actual_release' => U2P2FenceSessionStatement::$actualRelease,
            'fault_injected' => U2P2FenceSessionStatement::$faultInjected,
            'lock_owner_after_release' => $ownerAfterRelease,
            'second_callback_entered' => $secondCallback,
            'second_reason' => $secondReason,
            'get_lock_executes' => U2P2FenceSessionStatement::$getLockExecutes,
            'release_lock_executes' => U2P2FenceSessionStatement::$releaseLockExecutes,
            'update_executes' => U2P2FenceSessionStatement::$updateHostExecutes
        ]);
    }

    if ('probe' === $mode) {
        $callback = false;
        FOG\Agent\DirectoryNameFence::run(static function () use (&$callback) {
            $callback = true;
        });
        u2p2FenceSessionEmit([
            'mode' => $mode,
            'callback_entered' => $callback,
            'connection_id' => $identity['id']
        ]);
    }
} catch (Throwable $e) {
    u2p2FenceSessionEmit([
        'mode' => $mode,
        'worker_error' => get_class($e),
        'reason' => $e->getMessage()
    ], 1);
}
