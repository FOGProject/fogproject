<?php
/** Child worker for real-PDO intent fault tests; scratch DB only. */

class U2P2IntentAckLossPdo extends \PDO
{
    public $ackLossArmed = false;
    public $parentCommitCount = 0;

    public function commit(): bool
    {
        $committed = parent::commit();
        $this->parentCommitCount++;
        if ($this->ackLossArmed) {
            $this->ackLossArmed = false;
            throw new \RuntimeException('fixture_simulated_commit_ack_loss_after_parent_commit');
        }
        return $committed;
    }
}

class U2P2IntentCommitFalsePdo extends \PDO
{
    public $returnFalseArmed = false;
    public $parentCommitCount = 0;
    public $parentCommitSucceeded = false;

    public function commit(): bool
    {
        $committed = parent::commit();
        $this->parentCommitCount++;
        $this->parentCommitSucceeded = $committed;
        if ($this->returnFalseArmed) {
            $this->returnFalseArmed = false;
            return false;
        }
        return $committed;
    }
}

class U2P2IntentPreCommitThrowPdo extends \PDO
{
    public $throwBeforeParentArmed = false;
    public $commitAttempts = 0;
    public $parentCommitCount = 0;

    public function commit(): bool
    {
        $this->commitAttempts++;
        if ($this->throwBeforeParentArmed) {
            $this->throwBeforeParentArmed = false;
            throw new \RuntimeException('fixture_commit_exception_before_parent_commit');
        }
        $committed = parent::commit();
        $this->parentCommitCount++;
        return $committed;
    }
}

class U2P2IntentReleaseAckLossStatement extends \PDOStatement
{
    public static $armed = false;
    public static $releaseExecuted = false;

    protected function __construct()
    {
    }

    public function execute($params = null): bool
    {
        $ok = parent::execute($params);
        if ($ok && self::$armed
            && preg_match('/^\\s*SELECT\\s+RELEASE_LOCK\\s*\\(/i', $this->queryString)) {
            self::$armed = false;
            self::$releaseExecuted = true;
            throw new \RuntimeException('fixture_release_ack_loss_after_parent_release');
        }
        return $ok;
    }
}

function u2p2IntentFaultPdo($mode = '')
{
    $dsn = (string)getenv('MJ1_DB_DSN');
    if ('1' !== getenv('MJ1_DB_ALLOW_RESET') || 0 !== strpos($dsn, 'mysql:')
        || '' === $dsn) {
        throw new \RuntimeException('intent worker requires explicit MySQL scratch opt-in');
    }
    $pdoClass = in_array($mode, ['prepare-commit-ack-loss', 'dispatch-commit-ack-loss',
        'finish-success-commit-ack-loss'], true)
        ? U2P2IntentAckLossPdo::class
        : ('dispatch-commit-false' === $mode ? U2P2IntentCommitFalsePdo::class
            : ('finish-before-parent-commit-throw' === $mode
                ? U2P2IntentPreCommitThrowPdo::class : \PDO::class));
    $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
    if ('dispatch-release-ack-loss' === $mode) {
        $options[\PDO::ATTR_STATEMENT_CLASS] = [U2P2IntentReleaseAckLossStatement::class, []];
    }
    $pdo = new $pdoClass(
        $dsn,
        getenv('MJ1_DB_USER') ?: 'root',
        getenv('MJ1_DB_PASSWORD') ?: '',
        $options
    );
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ('fog_mj1' !== $database && 0 !== strpos($database, 'fog_mj1_')) {
        throw new \RuntimeException('intent worker database is outside the scratch prefix');
    }
    if (!defined('DATABASE_NAME')) {
        define('DATABASE_NAME', $database);
    } elseif (DATABASE_NAME !== $database) {
        throw new \RuntimeException('DATABASE_NAME does not match validated scratch database');
    }
    $link = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
    $link->setAccessible(true);
    $link->setValue(null, $pdo);
    $db = new FOG\Db\PDODB();
    FogTestHarness::setStatic('FOGBase', 'DB', $db);
    FogTestHarness::setStatic('DatabaseManager', 'DB', $db);
    FOG\Agent\DirectoryPlacementIntent::validateStorage($pdo);
    return $pdo;
}

function u2p2IntentFaultStatus(PDO $pdo, array $ticket)
{
    $q = $pdo->prepare('SELECT dpiStatus FROM directoryPlacementIntent WHERE dpiGUID=?');
    $q->bindValue(1, $ticket['guid'], \PDO::PARAM_LOB);
    $q->execute();
    return $q->fetchColumn();
}

function u2p2IntentFaultStatusForGuid(PDO $pdo, $guid)
{
    $q = $pdo->prepare('SELECT dpiStatus FROM directoryPlacementIntent WHERE dpiGUID=?');
    $q->bindValue(1, hex2bin(str_replace('-', '', strtolower((string)$guid))), \PDO::PARAM_LOB);
    $q->execute();
    return $q->fetchColumn();
}

$mode = $argv[1] ?? '';
$barrierDir = $argv[2] ?? '';
$input = isset($argv[3]) ? json_decode($argv[3], true) : null;
if (is_array($input) && isset($input['ticket']['guid_hex'])
    && is_string($input['ticket']['guid_hex'])
    && preg_match('/^[a-f0-9]{32}$/D', $input['ticket']['guid_hex'])) {
    $input['ticket']['guid'] = hex2bin($input['ticket']['guid_hex']);
    unset($input['ticket']['guid_hex']);
}
$ticket = is_array($input) ? ($input['ticket'] ?? null) : null;
$payload = is_array($input) ? ($input['payload'] ?? null) : null;
$expectedRoot = rtrim(realpath(sys_get_temp_dir()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$resolvedDir = realpath($barrierDir);
if (!in_array($mode, ['prepare-commit-ack-loss', 'fresh-worker-prepare',
        'dispatch-commit-ack-loss', 'dispatch-commit-false', 'dispatch-release-ack-loss',
        'finish-success-commit-ack-loss', 'finish-before-parent-commit-throw',
        'dispatch-consume-hold', 'dispatch-consume-kill-before-finish',
        'dispatch-consume-replace-link-before-finish',
        'fresh-worker-dispatch'], true)
    || false === $resolvedDir || 0 !== strpos($resolvedDir, $expectedRoot)
    || 0 !== strpos(basename($resolvedDir), 'u2-p2-intent-fault-')
    || (in_array($mode, ['prepare-commit-ack-loss', 'fresh-worker-prepare'], true)
        ? (!is_array($payload) || !isset($payload['target']['guid']))
        : (!is_array($ticket) || !isset($ticket['guid'], $ticket['sid'], $ticket['generation'], $ticket['hash'], $ticket['payload'])
            || !is_string($ticket['guid']) || 16 !== strlen($ticket['guid'])))) {
    fwrite(STDERR, "invalid scratch worker arguments\n");
    exit(2);
}

try {
    require_once dirname(__DIR__) . '/lib/fog-test-harness.php';
    FogTestHarness::boot('u2-p2-intent-fault-worker');
    $pdo = u2p2IntentFaultPdo($mode);
    $ldap = new FOG\Net\FOGLdap(); // Opaque binding object only; no connect/send.

    if ('prepare-commit-ack-loss' === $mode) {
        $pdo->ackLossArmed = true;
        $ticketReturned = false;
        $reason = '';
        try {
            $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($payload);
            $ticketReturned = is_array($ticket);
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
        }
        echo json_encode([
            'simulated_commit_ack_loss' => 'fixture_simulated_commit_ack_loss_after_parent_commit' === $reason,
            'reason' => $reason,
            'parent_commit_count' => $pdo->parentCommitCount,
            'ticket_returned' => $ticketReturned,
            'status_after_exception' => u2p2IntentFaultStatusForGuid($pdo, $payload['target']['guid'])
        ]), "\n";
        exit(0);
    }

    if ('fresh-worker-prepare' === $mode) {
        $ticketReturned = false;
        $reason = '';
        try {
            $ticket = FOG\Agent\DirectoryPlacementIntent::prepare($payload);
            $ticketReturned = is_array($ticket);
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
        }
        echo json_encode([
            'ticket_returned' => $ticketReturned,
            'reason' => $reason,
            'status' => u2p2IntentFaultStatusForGuid($pdo, $payload['target']['guid'])
        ]), "\n";
        exit(0);
    }

    if ('dispatch-commit-ack-loss' === $mode) {
        $pdo->ackLossArmed = true;
        $tokenReturned = false;
        $reason = '';
        try {
            $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
            $tokenReturned = is_object($token);
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
        }
        echo json_encode([
            'simulated_commit_ack_loss' => 'fixture_simulated_commit_ack_loss_after_parent_commit' === $reason,
            'reason' => $reason,
            'parent_commit_count' => $pdo->parentCommitCount,
            'token_returned' => $tokenReturned,
            'status_after_exception' => u2p2IntentFaultStatus($pdo, $ticket)
        ]), "\n";
        exit(0);
    }

    if ('dispatch-commit-false' === $mode) {
        $pdo->returnFalseArmed = true;
        $tokenReturned = false;
        $reason = '';
        try {
            $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
            $tokenReturned = is_object($token);
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
        }
        echo json_encode([
            'injected_false_after_parent_commit' => !$pdo->returnFalseArmed
                && 1 === $pdo->parentCommitCount && true === $pdo->parentCommitSucceeded,
            'reason' => $reason,
            'parent_commit_count' => $pdo->parentCommitCount,
            'parent_commit_succeeded' => $pdo->parentCommitSucceeded,
            'token_returned' => $tokenReturned,
            'status' => u2p2IntentFaultStatus($pdo, $ticket)
        ]), "\n";
        exit(0);
    }

    if ('dispatch-release-ack-loss' === $mode) {
        U2P2IntentReleaseAckLossStatement::$armed = true;
        $tokenReturned = false;
        $reason = '';
        try {
            $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
            $tokenReturned = is_object($token);
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
        }
        $sameProcessReason = '';
        try {
            FOG\Agent\DirectoryNameFence::run(static function () {
                return true;
            });
        } catch (\Throwable $e) {
            $sameProcessReason = $e->getMessage();
        }
        $namespace = (string)$pdo->query(
            'SELECT CASE WHEN @@lower_case_table_names=0 THEN DATABASE() ELSE LOWER(DATABASE()) END'
        )->fetchColumn();
        $key = 'fog.mj1.name.v1:' . substr(hash('sha256', $namespace), 0, 40);
        $q = $pdo->prepare('SELECT IS_USED_LOCK(?)');
        $q->execute([$key]);
        $lockOwner = $q->fetchColumn();
        echo json_encode([
            'release_execute_fault_injected' => U2P2IntentReleaseAckLossStatement::$releaseExecuted,
            'dispatch_reason' => $reason,
            'token_returned' => $tokenReturned,
            'same_process_reason' => $sameProcessReason,
            'lock_owner' => $lockOwner,
            'status' => u2p2IntentFaultStatus($pdo, $ticket)
        ]), "\n";
        exit(0);
    }

    if ('finish-success-commit-ack-loss' === $mode) {
        $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $entry = FOG\Net\FOGLdap::guidDN($ticket['payload']['target']['guid']);
        $parent = FOG\Net\FOGLdap::guidDN($ticket['payload']['destination']['guid']);
        $rdn = FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $ldap, $entry, $parent);

        // Inject only the result receipt. No LDAP connection/request is made.
        $pdo->ackLossArmed = true;
        $finishReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'injected-success-result');
        } catch (\Throwable $e) {
            $finishReason = $e->getMessage();
        }

        $changedReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::finish($token, false, 'different-result');
        } catch (\Throwable $e) {
            $changedReason = $e->getMessage();
        }

        $sameReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'injected-success-result');
        } catch (\Throwable $e) {
            $sameReason = $e->getMessage();
        }

        $duplicateConsumeReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $ldap, $entry, $parent);
        } catch (\Throwable $e) {
            $duplicateConsumeReason = $e->getMessage();
        }

        echo json_encode([
            'injected_result' => 'injected-success-result',
            'finish_reason' => $finishReason,
            'parent_commit_count' => $pdo->parentCommitCount,
            'durable_status' => u2p2IntentFaultStatus($pdo, $ticket),
            'durable_result' => (function () use ($pdo, $ticket) {
                $q = $pdo->prepare('SELECT dpiResult FROM directoryPlacementIntent WHERE dpiGUID=?');
                $q->bindValue(1, $ticket['guid'], \PDO::PARAM_LOB);
                $q->execute();
                return $q->fetchColumn();
            })(),
            'changed_outcome_reason' => $changedReason,
            'same_outcome_retry_reason' => $sameReason,
            'duplicate_consume_reason' => $duplicateConsumeReason,
            'permit_consumed_once' => '' !== $rdn,
            'ldap_connected' => false,
            'ldap_request_sent' => false
        ]), "\n";
        exit(0);
    }

    if ('finish-before-parent-commit-throw' === $mode) {
        $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $entry = FOG\Net\FOGLdap::guidDN($ticket['payload']['target']['guid']);
        $parent = FOG\Net\FOGLdap::guidDN($ticket['payload']['destination']['guid']);
        $rdn = FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $ldap, $entry, $parent);

        // The first result COMMIT throws before PDO::commit(), so finish()
        // should roll back its own still-open transaction. Retry the exact
        // same injected result on this healthy connection; no LDAP I/O occurs.
        $pdo->throwBeforeParentArmed = true;
        $firstReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'injected-success-result');
        } catch (\Throwable $e) {
            $firstReason = $e->getMessage();
        }
        $statusAfterRollback = u2p2IntentFaultStatus($pdo, $ticket);
        $retryReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'injected-success-result');
        } catch (\Throwable $e) {
            $retryReason = $e->getMessage();
        }
        $duplicateConsumeReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $ldap, $entry, $parent);
        } catch (\Throwable $e) {
            $duplicateConsumeReason = $e->getMessage();
        }

        $q = $pdo->prepare('SELECT dpiResult FROM directoryPlacementIntent WHERE dpiGUID=?');
        $q->bindValue(1, $ticket['guid'], \PDO::PARAM_LOB);
        $q->execute();
        echo json_encode([
            'first_finish_reason' => $firstReason,
            'status_after_rollback' => $statusAfterRollback,
            'transaction_open_after_rollback' => $pdo->inTransaction(),
            'retry_reason' => $retryReason,
            'commit_attempts' => $pdo->commitAttempts,
            'parent_commit_count' => $pdo->parentCommitCount,
            'final_status' => u2p2IntentFaultStatus($pdo, $ticket),
            'final_result' => $q->fetchColumn(),
            'duplicate_consume_reason' => $duplicateConsumeReason,
            'permit_consumed_once' => '' !== $rdn,
            'ldap_connected' => false,
            'ldap_request_sent' => false
        ]), "\n";
        exit(0);
    }

    if ('dispatch-consume-hold' === $mode) {
        $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $entry = FOG\Net\FOGLdap::guidDN($ticket['payload']['target']['guid']);
        $parent = FOG\Net\FOGLdap::guidDN($ticket['payload']['destination']['guid']);
        FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $ldap, $entry, $parent);
        $connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        file_put_contents($resolvedDir . DIRECTORY_SEPARATOR . 'consumed.json', json_encode([
            'consumed' => true,
            'status' => u2p2IntentFaultStatus($pdo, $ticket),
            'ldap_connected' => false,
            'connection_id' => $connectionID
        ]), LOCK_EX);
        // Parent terminates this process. No finish(), LDAP connection, or
        // LDAP request occurs; PDO session closure is caused by process death.
        while (true) {
            usleep(100000);
        }
    }

    if ('dispatch-consume-kill-before-finish' === $mode) {
        $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $entry = FOG\Net\FOGLdap::guidDN($ticket['payload']['target']['guid']);
        $parent = FOG\Net\FOGLdap::guidDN($ticket['payload']['destination']['guid']);
        FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $ldap, $entry, $parent);
        $connectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        $pdodbLink = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
        $pdodbLink->setAccessible(true);
        $originalLinkSame = $pdodbLink->getValue() === $pdo;
        file_put_contents($resolvedDir . DIRECTORY_SEPARATOR . 'consumed-awaiting-kill.json', json_encode([
            'consumed' => true,
            'status' => u2p2IntentFaultStatus($pdo, $ticket),
            'connection_id' => $connectionID,
            'ldap_connected' => false,
            'ldap_request_sent' => false
        ]), LOCK_EX);

        $resume = $resolvedDir . DIRECTORY_SEPARATOR . 'finish-after-kill';
        $deadline = microtime(true) + 20.0;
        while (!is_file($resume)) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('parent did not release finish-after-kill barrier');
            }
            usleep(10000);
        }

        $finishThrew = false;
        $finishReason = '';
        try {
            // This result is injected to model a possible completed LDAP side
            // effect. No LDAP connection or request exists in this fixture.
            FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'injected-ldap-success');
        } catch (\Throwable $e) {
            $finishThrew = true;
            $finishReason = $e->getMessage();
        }
        $retryThrew = false;
        $retryReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'injected-ldap-success');
        } catch (\Throwable $e) {
            $retryThrew = true;
            $retryReason = $e->getMessage();
        }
        $changedOutcomeReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::finish($token, false, 'different-after-kill');
        } catch (\Throwable $e) {
            $changedOutcomeReason = $e->getMessage();
        }
        $connectionProbeThrew = false;
        $connectionProbeID = null;
        try {
            $connectionProbeID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        } catch (\Throwable $e) {
            $connectionProbeThrew = true;
        }
        $linkStillOriginal = $pdodbLink->getValue() === $pdo;
        echo json_encode([
            'finish_threw' => $finishThrew,
            'finish_reason' => $finishReason,
            'same_worker_retry_threw' => $retryThrew,
            'same_worker_retry_reason' => $retryReason,
            'changed_outcome_reason' => $changedOutcomeReason,
            'connection_probe_threw' => $connectionProbeThrew,
            'connection_probe_id' => $connectionProbeID,
            'original_pdodb_link_same_before_kill' => $originalLinkSame,
            'original_pdodb_link_same_after_retry' => $linkStillOriginal,
            'injected_result' => 'injected-ldap-success',
            'connection_id' => $connectionID,
            'ldap_connected' => false,
            'ldap_request_sent' => false
        ]), "\n";
        exit(0);
    }

    if ('dispatch-consume-replace-link-before-finish' === $mode) {
        $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $entry = FOG\Net\FOGLdap::guidDN($ticket['payload']['target']['guid']);
        $parent = FOG\Net\FOGLdap::guidDN($ticket['payload']['destination']['guid']);
        $rdn = FOG\Agent\DirectoryPlacementIntent::consumePermit($token, $ldap, $entry, $parent);
        $originalConnectionID = (int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();

        // Fixture-only substitution: the production code must refuse to use
        // this replacement PDO to complete a receipt bound to the original.
        $replacement = new \PDO(
            (string)getenv('MJ1_DB_DSN'),
            getenv('MJ1_DB_USER') ?: 'root',
            getenv('MJ1_DB_PASSWORD') ?: '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $replacementDatabase = (string)$replacement->query('SELECT DATABASE()')->fetchColumn();
        if ($replacementDatabase !== DATABASE_NAME
            || ('fog_mj1' !== $replacementDatabase && 0 !== strpos($replacementDatabase, 'fog_mj1_'))) {
            throw new \RuntimeException('replacement PDO selected a different or non-scratch database');
        }
        $replacementConnectionID = (int)$replacement->query('SELECT CONNECTION_ID()')->fetchColumn();
        $pdodbLink = new \ReflectionProperty(FOG\Db\PDODB::class, '_link');
        $pdodbLink->setAccessible(true);
        $pdodbLink->setValue(null, $replacement);

        $finishReason = '';
        try {
            FOG\Agent\DirectoryPlacementIntent::finish($token, true, 'injected-success-result');
        } catch (\Throwable $e) {
            $finishReason = $e->getMessage();
        }
        echo json_encode([
            'fixture_replacement_installed' => $pdodbLink->getValue() === $replacement,
            'original_connection_id' => $originalConnectionID,
            'replacement_connection_id' => $replacementConnectionID,
            'selected_database' => $replacementDatabase,
            'permit_consumed_before_replacement' => '' !== $rdn,
            'finish_reason' => $finishReason,
            'original_status' => u2p2IntentFaultStatus($pdo, $ticket),
            'original_result' => (function () use ($pdo, $ticket) {
                $q = $pdo->prepare('SELECT dpiResult FROM directoryPlacementIntent WHERE dpiGUID=?');
                $q->bindValue(1, $ticket['guid'], \PDO::PARAM_LOB);
                $q->execute();
                return $q->fetchColumn();
            })(),
            'ldap_connected' => false,
            'ldap_request_sent' => false
        ]), "\n";
        exit(0);
    }

    $tokenReturned = false;
    $reason = '';
    try {
        $token = FOG\Agent\DirectoryPlacementIntent::dispatch($ticket, $ldap);
        $tokenReturned = is_object($token);
    } catch (\Throwable $e) {
        $reason = $e->getMessage();
    }
    echo json_encode([
        'token_returned' => $tokenReturned,
        'reason' => $reason,
        'status' => u2p2IntentFaultStatus($pdo, $ticket)
    ]), "\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, json_encode(['class' => get_class($e), 'reason' => $e->getMessage()]) . "\n");
    exit(1);
}
