<?php
/**
 * Transactional directory operation state and existing Target confirmations.
 *
 * PHP version 7.4+
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Net\FOGLdap;

/**
 * Owns the rejoin config and state stored on hostDirectory. Every write goes
 * through change(), one short transaction holding the host and hostDirectory
 * row locks, so two requests for one host never interleave. The operation
 * logic is in DirectoryOperation.
 *
 * @category DirectoryMembership
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DirectoryRejoin extends FOGBase
{
    const LIMIT = 16384;

    /**
     * The phases of an operation that may have changed the computer or its
     * directory account. An operator must review one before the host is
     * deleted, and no Move may start while one exists.
     */
    const PROTECTED_PHASES = ['started', 'awaiting_reboot', 'unknown', 'failed'];

    /**
     * Decodes and validates a stored config or state value.
     *
     * @param mixed $raw the stored JSON; anything but a string is refused
     *
     * @return array empty for NULL or an empty string
     *
     * @throws \RuntimeException rejoin_state_invalid (409) for a value that
     *                           is not JSON, has unknown keys or wrong types
     */
    public static function decode($raw)
    {
        if (null === $raw || '' === $raw) {
            return [];
        }
        if (!is_string($raw) || strlen($raw) > self::LIMIT) {
            throw new \RuntimeException('rejoin_state_invalid', 409);
        }
        $value = json_decode($raw, true);
        if (!is_array($value) || 1 !== ($value['version'] ?? null)) {
            throw new \RuntimeException('rejoin_state_invalid', 409);
        }
        $keys = ['version','allow_create','name','domain','confirmed_by','confirmed_at',
            'target','config_revision','placement_hold','host_id',
            'fingerprint','dc','mode','phase','attempt_id','reason','native_status','secure_channel','boot_changed','boot','updated_at',
            'protocol','operation','expected_current','expected_pending','observation','observed_at','check_dc',
            'last_deploy_task_id','trigger_task_id','retry_authorized','workgroup',
            'cleared_attempt','cleared_phase','cleared_by','cleared_at'];
        if (array_diff(array_keys($value), $keys)) {
            throw new \RuntimeException('rejoin_state_invalid', 409);
        }
        if (isset($value['protocol']) && $value['protocol'] !== 2) {
            throw new \RuntimeException('rejoin_state_invalid', 409);
        }
        foreach (['observed_at','last_deploy_task_id','trigger_task_id'] as $key) {
            if (isset($value[$key]) && (!is_int($value[$key]) || $value[$key] < 0)) {
                throw new \RuntimeException('rejoin_state_invalid', 409);
            }
        }
        foreach (['operation','expected_current','expected_pending','check_dc'] as $key) {
            if (isset($value[$key]) && (!is_string($value[$key]) || strlen($value[$key]) > 255)) {
                throw new \RuntimeException('rejoin_state_invalid', 409);
            }
        }
        foreach (['retry_authorized','workgroup'] as $key) {
            if (isset($value[$key]) && !is_bool($value[$key])) {
                throw new \RuntimeException('rejoin_state_invalid', 409);
            }
        }
        if (isset($value['observation']) && !is_array($value['observation'])) {
            throw new \RuntimeException('rejoin_state_invalid', 409);
        }
        foreach (['allow_create','placement_hold','boot_changed'] as $key) {
            if (isset($value[$key]) && !is_bool($value[$key])) {
                throw new \RuntimeException('rejoin_state_invalid', 409);
            }
        }
        foreach (['name','domain','config_revision','fingerprint','dc','mode','attempt_id','reason','phase','confirmed_by','confirmed_at','secure_channel','updated_at','cleared_attempt','cleared_phase','cleared_by','cleared_at'] as $key) {
            if (isset($value[$key]) && (!is_string($value[$key]) || strlen($value[$key]) > 1024)) {
                throw new \RuntimeException('rejoin_state_invalid', 409);
            }
        }
        if (isset($value['target']) && (!is_array($value['target'])
            || !self::guid($value['target']['guid'] ?? null) || !is_string($value['target']['sid'] ?? null))) {
            throw new \RuntimeException('rejoin_state_invalid', 409);
        }
        return $value;
    }

    private static function encoded(array $value)
    {
        $raw = json_encode($value);
        if (false === $raw || strlen($raw) > self::LIMIT) {
            throw new \RuntimeException('rejoin_state_invalid', 409);
        }
        self::decode($raw);
        return $raw;
    }

    /**
     * The host's stored config and state, without locking.
     *
     * @param Host $host the host
     *
     * @return array with the keys config and state
     */
    public static function settings(Host $host)
    {
        $q = self::$DB->link()->prepare('SELECT hdRejoinConfig, hdRejoinState FROM hostDirectory WHERE hdHostID = ?');
        $q->execute([(int)$host->get('id')]);
        $row = $q->fetch(\PDO::FETCH_ASSOC) ?: [];
        $q->closeCursor();
        return ['config' => self::decode($row['hdRejoinConfig'] ?? null),
            'state' => self::decode($row['hdRejoinState'] ?? null)];
    }

    /**
     * Runs $fn on the locked config and state and stores what it leaves.
     * A short DB-only transaction: LDAP, credentials and OS calls stay
     * outside. A deadlock (1213) or a changed-since-read error (1020) answers
     * 409 so the agent retries.
     *
     * @param Host     $host the host
     * @param callable $fn   function (&$config, &$state, $principal): mixed
     *
     * @return mixed whatever $fn returns
     *
     * @throws \RuntimeException rejoin_nested_transaction, rejoin_host_missing,
     *                           rejoin_deadlock_retry or rejoin_plan_changed
     *                           (all 409), or whatever $fn throws
     */
    public static function change(Host $host, callable $fn)
    {
        $db = self::$DB->link();
        if ($db->inTransaction()) {
            throw new \RuntimeException('rejoin_nested_transaction', 409);
        }
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT hostID, hostName, hostADDomain, hostUseAD, hostAgentFingerprint FROM hosts WHERE hostID = ? FOR UPDATE');
            $q->execute([(int)$host->get('id')]);
            $principal = $q->fetch(\PDO::FETCH_ASSOC);
            $q->closeCursor();
            if (!$principal) {
                throw new \RuntimeException('rejoin_host_missing', 409);
            }
            $q = $db->prepare('INSERT IGNORE INTO hostDirectory (hdHostID) VALUES (?)');
            $q->execute([(int)$host->get('id')]);
            $q = $db->prepare('SELECT hdRejoinConfig, hdRejoinState FROM hostDirectory WHERE hdHostID = ? FOR UPDATE');
            $q->execute([(int)$host->get('id')]);
            $row = $q->fetch(\PDO::FETCH_ASSOC);
            $q->closeCursor();
            $config = self::decode($row['hdRejoinConfig']);
            $state = self::decode($row['hdRejoinState']);
            $result = $fn($config, $state, $principal);
            $q = $db->prepare('UPDATE hostDirectory SET hdRejoinConfig = ?, hdRejoinState = ? WHERE hdHostID = ?');
            $q->execute([self::encoded($config + ['version' => 1]), self::encoded($state + ['version' => 1]), (int)$host->get('id')]);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            // Not inTransaction(): PHPStan cannot see that beginTransaction()
            // changes the answer. rollBack() throws when the server already
            // ended the transaction, which is the same end state.
            try {
                $db->rollBack();
            } catch (\PDOException $ignored) {
                // No transaction left to roll back.
            }
            if ($e instanceof \PDOException) {
                $driverCode = (int)($e->errorInfo[1] ?? 0);
                // 1213: InnoDB picked this transaction as a deadlock victim
                // (an imaging task write can be the other side). Nothing was
                // written, so the agent is told to retry (409), not given a
                // server error.
                if (1213 === $driverCode) {
                    throw new \RuntimeException('rejoin_deadlock_retry', 409, $e);
                }
                // 1020: MariaDB with snapshot isolation (the 11.8 default)
                // refuses a locking read of a row another session changed
                // after this transaction's snapshot. That is the same loser
                // of a race that MySQL reports by re-reading the new state.
                if (1020 === $driverCode) {
                    throw new \RuntimeException('rejoin_plan_changed', 409, $e);
                }
            }
            throw $e;
        }
    }

    /**
     * Whether a stored state is an operation that may have changed the
     * computer or its directory account, or one nobody can read.
     *
     * @param mixed $raw the stored hdRejoinState
     *
     * @return bool
     */
    private static function protectedState($raw)
    {
        try {
            $state = self::decode($raw);
        } catch (\Throwable $e) {
            return true;
        }
        return in_array($state['phase'] ?? '', self::PROTECTED_PHASES, true);
    }

    /**
     * The hosts among $ids whose directory state must be cleared by an
     * operator before the host is deleted.
     *
     * @param int[] $ids host ids
     *
     * @return int[]
     */
    public static function protectedHosts(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $q = self::$DB->link()->prepare('SELECT hdHostID,hdRejoinState FROM hostDirectory WHERE hdHostID IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ') AND hdRejoinState IS NOT NULL');
        $q->execute($ids);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC);
        $q->closeCursor();
        $out = [];
        foreach ($rows as $row) {
            if (self::protectedState($row['hdRejoinState'])) {
                $out[] = (int)$row['hdHostID'];
            }
        }
        // A Move that may have run, or whose result is unknown, protects its
        // owner the same way.
        return array_values(array_unique(array_merge($out, DirectoryPlacementIntent::protectedOwners($ids))));
    }

    /**
     * Records an operator's decision that a stored operation needs no more
     * review. Nothing is undone. The attempt's evidence is kept in the
     * cleared_* keys and the deploy task counters never move backward.
     *
     * Works on an unreadable row, which no other writer can touch.
     *
     * @param Host   $host            the host
     * @param string $expectedAttempt the attempt the operator was shown
     * @param string $actor           the operator's name
     *
     * @return void
     */
    public static function clearReview(Host $host, $expectedAttempt, $actor)
    {
        $id = (int)$host->get('id');
        $db = self::$DB->link();
        if ($db->inTransaction()) {
            throw new \RuntimeException('rejoin_nested_transaction', 409);
        }
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT hostID FROM hosts WHERE hostID = ? FOR UPDATE');
            $q->execute([$id]);
            $found = $q->fetchColumn();
            $q->closeCursor();
            if (false === $found) {
                throw new \RuntimeException('rejoin_host_missing', 409);
            }
            $q = $db->prepare('SELECT hdRejoinConfig, hdRejoinState FROM hostDirectory WHERE hdHostID = ? FOR UPDATE');
            $q->execute([$id]);
            $row = $q->fetch(\PDO::FETCH_ASSOC);
            $q->closeCursor();
            if (!$row) {
                throw new \RuntimeException('rejoin_review_not_required', 409);
            }
            $state = [];
            $readable = true;
            try {
                $state = self::decode($row['hdRejoinState']);
            } catch (\Throwable $e) {
                $readable = false;
            }
            if ($readable) {
                if (!self::protectedState($row['hdRejoinState'])) {
                    throw new \RuntimeException('rejoin_review_not_required', 409);
                }
                if (($state['attempt_id'] ?? '') !== (string)$expectedAttempt) {
                    throw new \RuntimeException('rejoin_review_changed', 409);
                }
            }
            try {
                $config = self::decode($row['hdRejoinConfig']);
            } catch (\Throwable $e) {
                $config = null;
            }
            if ($config) {
                $config['retry_authorized'] = false;
                $config = self::encoded($config + ['version' => 1]);
            } else {
                $config = null;
            }
            $q = $db->prepare('SELECT MAX(taskID) FROM tasks WHERE taskHostID = ? AND taskTypeID IN ('
                . implode(',', array_map('intval', \FOG\Items\TaskType::DEPLOYTASKS)) . ')');
            $q->execute([$id]);
            $latest = (int)$q->fetchColumn();
            $q->closeCursor();
            $cleared = [
                'version' => 1,
                'protocol' => 2,
                'reason' => 'operator_cleared',
                'last_deploy_task_id' => max(
                    $latest,
                    (int)($state['last_deploy_task_id'] ?? 0),
                    (int)($state['trigger_task_id'] ?? 0)
                ),
                'cleared_attempt' => (string)($state['attempt_id'] ?? ''),
                'cleared_phase' => $readable ? (string)($state['phase'] ?? '') : 'unreadable',
                'cleared_by' => substr((string)$actor, 0, 100),
                'cleared_at' => gmdate('c')
            ];
            $q = $db->prepare('UPDATE hostDirectory SET hdRejoinConfig = ?, hdRejoinState = ? WHERE hdHostID = ?');
            $q->execute([$config, self::encoded($cleared), $id]);
            $db->commit();
        } catch (\Throwable $e) {
            try {
                $db->rollBack();
            } catch (\PDOException $ignored) {
                // No transaction left to roll back.
            }
            throw $e;
        }
        \FOG\Audit\Audit::record([
            'type' => 'host.directory.review_cleared',
            'subjectType' => 'host',
            'subjectID' => $id,
            'subjectLabel' => (string)$host->get('name'),
            'permission' => 'host.edit',
            'affectedCount' => 1,
            'renderable' => 1,
            'text' => substr('cleared the directory review of attempt ' . $cleared['cleared_attempt'], 0, \FOG\Audit\Audit::MAX_DETAIL)
        ]);
    }

    /**
     * Whether a value is a GUID in the 8-4-4-4-12 form.
     *
     * @param mixed $value the value to test
     *
     * @return bool
     */
    public static function guid($value)
    {
        return is_string($value) && 1 === preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $value);
    }

    /**
     * Opens the LDAP connection for the host's domain and checks that the
     * configured search base sits inside it. Never call inside a transaction.
     *
     * @param Host        $host         the host
     * @param string|null $wantedDomain the domain to verify; defaults to the host's
     *
     * @return array the FOGLdap connection, the base DN and the domain controller
     *
     * @throws \RuntimeException rejoin_directory_unconfigured,
     *                           rejoin_directory_unavailable,
     *                           rejoin_domain_unverified, rejoin_scope_invalid
     *                           or directory_ldap_inside_transaction (409)
     */
    public static function connection(Host $host, $wantedDomain = null)
    {
        $c = DirectoryPlacement::connection($host);
        if (!preg_match('#^(?:ldaps|ldap)://[A-Za-z0-9.-]+(?::[0-9]+)?/?$#D', $c['uri'])) {
            throw new \RuntimeException('rejoin_directory_unconfigured', 409);
        }
        if (self::$DB->link()->inTransaction()) {
            throw new \RuntimeException('directory_ldap_inside_transaction', 409);
        }
        $ldap = new FOGLdap();
        if (!$ldap->connect($c['uri'], $c['bindDN'], $c['password'], $c['caCert'])) {
            throw new \RuntimeException('rejoin_directory_unavailable', 409);
        }
        $ctx = $ldap->domainContext(strtolower(trim(null === $wantedDomain ? (string)$host->get('ADDomain') : (string)$wantedDomain)));
        if ('found' !== $ctx['status']) {
            $ldap->close();
            throw new \RuntimeException('rejoin_domain_unverified', 409);
        }
        $base = $c['baseDN'];
        if ('' === $base || (0 !== strcasecmp($base, $ctx['base_dn'])
            && !self::endsWithDN($base, $ctx['base_dn']))) {
            $ldap->close();
            throw new \RuntimeException('rejoin_scope_invalid', 409);
        }
        return [$ldap, $base, $ctx['dc']];
    }

    private static function endsWithDN($dn, $root)
    {
        return strlen($dn) > strlen($root)
            && 0 === strcasecmp(substr($dn, -strlen($root) - 1), ',' . $root);
    }

    /**
     * Looks up one computer account. An error is never read as absence.
     *
     * @param FOGLdap $ldap    the open connection
     * @param string  $base    the search base
     * @param string  $account the account name
     *
     * @return array status found or absent, and the record when found
     *
     * @throws \RuntimeException rejoin_lookup_failed (409) for any other status
     */
    public static function lookup(FOGLdap $ldap, $base, $account)
    {
        $answer = $ldap->computerRecord($base, $account);
        if (!in_array($answer['status'], ['found', 'absent'], true)) {
            throw new \RuntimeException('rejoin_lookup_failed', 409);
        }
        return $answer;
    }

    /**
     * Requires a name an AD computer account can have: letters, digits and
     * hyphens, 1 to 15 characters, not all digits, no leading or trailing hyphen.
     *
     * @param mixed $name the host name
     *
     * @return void
     *
     * @throws \RuntimeException rejoin_name_invalid (409)
     */
    public static function validName($name)
    {
        if (!is_string($name) || !preg_match('/^(?=.{1,15}$)[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/iD', $name)
            || ctype_digit($name)) {
            throw new \RuntimeException('rejoin_name_invalid', 409);
        }
    }

    /**
     * Called before any poll side effect, even when facts have not changed.
     * A poll without the directory features returns at once (it is never
     * refused, so tasks and check-in still work). A poll with them must pass
     * the identity check, then stores the observation.
     *
     * @param Host  $host the host
     * @param array $body the decoded poll body
     *
     * @return void
     *
     * @throws \RuntimeException directory_target_feature_required,
     *                           identity_not_matched or an observation error
     */
    public static function beforePoll(Host $host, array $body)
    {
        if (DirectoryOperation::requested($body) && !MachineIdentity::requested($body)) {
            throw new \RuntimeException('directory_target_feature_required', 409);
        }
        if (!MachineIdentity::requested($body)) {
            // A poll without the directory features is not refused: the
            // agent sends one whenever its feature probe or identity check
            // fails, and a 409 would stop that host's whole poll (tasks and
            // check-in too). Nothing is planned, claimed or written for it,
            // so a pending operation waits until the agent polls with the
            // features again.
            return;
        }
        $identity = MachineIdentity::check($host, $body);
        if ('matched' !== $identity['identity']['status']) {
            throw new \RuntimeException('identity_not_matched', 409);
        }
        // A host with directory membership off has no operation to observe.
        // Observing would create its hostDirectory row and lock it on every poll.
        if (!$host->get('useAD') || !State::factsEnabled()) {
            return;
        }
        if (DirectoryOperation::requested($body)) {
            if (!isset($body['directory_attempt']) || !is_array($body['directory_attempt'])) {
                throw new \RuntimeException('directory_local_attempt_required', 409);
            }
            DirectoryOperation::observe($host, $body);
            return;
        }
        throw new \RuntimeException('directory_agent_update_required', 409);
    }


    /**
     * The directory_rejoin block for a poll answer.
     *
     * @param Host  $host the host
     * @param array $body the decoded poll body
     *
     * @return array|null see DirectoryOperation::plan()
     */
    public static function plan(Host $host, array $body = [])
    {
        // No protocol-2 state yet means no poll has declared the feature.
        return 2 === (self::settings($host)['state']['protocol'] ?? 1) ? DirectoryOperation::plan($host, $body) : null;
    }


    /**
     * Whether two directory records are the same object: same GUID and SID.
     *
     * @param array $a a record
     * @param array $b a record
     *
     * @return bool
     */
    public static function sameObject(array $a, array $b)
    {
        return !empty($a['guid']) && !empty($a['sid'])
            && 0 === strcasecmp($a['guid'], $b['guid'] ?? '') && $a['sid'] === ($b['sid'] ?? '');
    }

    /**
     * The agent's claim of the planned operation.
     *
     * @param Host  $host the host
     * @param array $body the decoded start body
     *
     * @return array see DirectoryOperation::start()
     */
    public static function start(Host $host, array $body)
    {
        return DirectoryOperation::start($host, $body);
    }


    /**
     * The agent's result for the claimed operation.
     *
     * @param Host  $host the host
     * @param array $body the decoded result body
     *
     * @return string see DirectoryOperation::report()
     */
    public static function report(Host $host, array $body)
    {
        return DirectoryOperation::report($host, $body);
    }

    /**
     * Allows only the forward phase moves of a started attempt, so a late or
     * repeated result cannot rewrite a finished one.
     *
     * @param string $from the stored phase
     * @param string $to   the reported phase
     *
     * @return void
     *
     * @throws \RuntimeException rejoin_result_out_of_order (409)
     */
    public static function transition($from, $to)
    {
        if ($from === $to) {
            return;
        }
        $allowed = ['started' => ['awaiting_reboot', 'failed', 'unknown'],
            'awaiting_reboot' => ['verified', 'unknown'], 'unknown' => [], 'failed' => [], 'verified' => []];
        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new \RuntimeException('rejoin_result_out_of_order', 409);
        }
    }

    /**
     * The part of a stored state the agent may see, as the version 2 object.
     *
     * @param array $s the stored state
     *
     * @return array|null null when there is no attempt
     */
    public static function publicState(array $s)
    {
        if (empty($s['attempt_id'])) {
            return null;
        }
        $s['task_id'] = (int)($s['trigger_task_id'] ?? 0);
        return ['version' => 2] + array_intersect_key($s, array_flip(['attempt_id','host_id','config_revision','phase',
            'name','domain','mode','dc','target','reason','operation','expected_current','expected_pending','retry_authorized','task_id']));
    }

    /**
     * Notes why a poll was refused, for the operator. It never touches a
     * running attempt, and it skips the write when the reason is unchanged.
     *
     * @param Host   $host   the host
     * @param string $reason the refusal code
     *
     * @return void
     */
    public static function blocked(Host $host, $reason)
    {
        // A poll repeats the same refusal every few seconds. Skip the locked
        // write when the stored reason is already this one.
        $saved = self::settings($host)['state'];
        if ((empty($saved['attempt_id']) || 'verified' === ($saved['phase'] ?? ''))
            && ($saved['reason'] ?? null) === substr((string)$reason, 0, 100)) {
            return;
        }
        self::change($host, function (&$c, &$s) use ($reason) {
            if (empty($s['attempt_id']) || 'verified' === ($s['phase'] ?? '')) {
                $s['reason'] = substr((string)$reason, 0, 100);
            }
            return null;
        });
    }

    /**
     * Whether the config lets the server create a missing account. Allowed
     * unless the operator turned it off.
     *
     * @param array $config the stored config
     *
     * @return bool
     */
    public static function allowsCreate(array $config)
    {
        return !array_key_exists('allow_create', $config) || true === $config['allow_create'];
    }

    /**
     * Whether an operator may authorize a retry. Retry permission never
     * discards evidence of a possibly executed operation.
     *
     * @param array $state the stored state
     *
     * @return bool
     */
    public static function canConfirmRetry(array $state)
    {
        return 'verified' === ($state['phase'] ?? '')
            || ('' === ($state['phase'] ?? '') && empty($state['attempt_id']));
    }

    /**
     * The directory account the host would rebind to, for the operator to
     * confirm on the Active Directory tab.
     *
     * @param Host $host the host
     *
     * @return array the lookup answer with name and domain, or status unavailable
     */
    public static function targetCandidate(Host $host)
    {
        try {
            return DirectoryOperation::candidate($host) + ['name' => (string)$host->get('name'), 'domain' => (string)$host->get('ADDomain')];
        } catch (\Throwable $e) {
            return ['status' => 'unavailable'];
        }
    }

    /**
     * Stores the operator's settings from the host Active Directory tab:
     * whether creating an account is allowed, a confirmed target and a retry.
     *
     * @param Host   $host  the host
     * @param array  $input the posted rejoin_* fields
     * @param string $actor the operator's name
     *
     * @return void
     *
     * @throws \RuntimeException rejoin_config_changed or
     *                           rejoin_attempt_requires_review (409)
     */
    public static function configure(Host $host, array $input, $actor)
    {
        $saved = self::settings($host);
        $config = ['version' => 1, 'protocol' => 2,
            'allow_create' => array_key_exists('rejoin_allow_create', $input) ? (bool)$input['rejoin_allow_create'] : self::allowsCreate($saved['config']),
            'name' => (string)$host->get('name'), 'domain' => strtolower(trim((string)$host->get('ADDomain'))),
            'config_revision' => bin2hex(random_bytes(16)), 'confirmed_by' => substr((string)$actor, 0, 100), 'confirmed_at' => gmdate('c')];
        if (!strcasecmp($saved['config']['name'] ?? '', $config['name'])
            && !strcasecmp($saved['config']['domain'] ?? '', $config['domain']) && !empty($saved['config']['target'])) {
            $config['target'] = $saved['config']['target'];
        }
        if (!empty($input['rejoin_confirm_target'])) {
            $t = DirectoryOperation::candidate($host);
            if ('found' !== $t['status'] || !self::guid($input['rejoin_target_guid'] ?? '')
                || strcasecmp($input['rejoin_target_guid'], $t['record']['guid'])) {
                throw new \RuntimeException('rejoin_target_confirmation_required', 409);
            }
            $config['target'] = $t['record'];
        }
        self::change($host, function (&$c, &$s) use ($saved, $config, $input, $host) {
            if ($c !== $saved['config'] || $s !== $saved['state']) {
                throw new \RuntimeException('rejoin_config_changed', 409);
            }
            if (!empty($input['rejoin_retry'])) {
                if (!self::canConfirmRetry($s)) {
                    throw new \RuntimeException('rejoin_attempt_requires_review', 409);
                }
                $config['retry_authorized'] = true;
                // Keep the old verified attempt for a pending terminal ACK.
                // The new config revision requires a fresh observation before
                // planning, and only claim consumes this retry authorization.
            } elseif (!empty($s['attempt_id']) && 'verified' !== ($s['phase'] ?? '')) {
                throw new \RuntimeException('rejoin_attempt_requires_review', 409);
            }
            // A PREPARED Move was built from the old config. A Move that may
            // have run refuses the change instead.
            DirectoryPlacementIntent::cancelOwnerPrepared(self::$DB->link(), (int)$host->get('id'));
            $c = $config;
        });
    }
}
