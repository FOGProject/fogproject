<?php
/** Transactional directory operation state and existing Target confirmations. */
namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\Host;
use FOG\Net\FOGLdap;

class DirectoryRejoin extends FOGBase
{
    const LIMIT = 16384;
    const TERMINAL = ['verified', 'failed', 'unknown'];

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
        unset($value['clone_status'], $value['origin_host_id'], $value['clone_origin']);
        $keys = ['version','enabled','allow_create','name','domain','confirmed_by','confirmed_at',
            'target','source','config_revision','placement_hold','host_id',
            'fingerprint','dc','mode','phase','attempt_id','reason','native_status','secure_channel','boot_changed','boot','updated_at',
            'protocol','operation','expected_current','expected_pending','observation','observed_at','check_dc',
            'last_deploy_task_id','trigger_task_id','retry_authorized','workgroup'];
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
        foreach (['enabled','allow_create','placement_hold','boot_changed'] as $key) {
            if (isset($value[$key]) && !is_bool($value[$key])) {
                throw new \RuntimeException('rejoin_state_invalid', 409);
            }
        }
        foreach (['name','domain','config_revision','fingerprint','dc','mode','attempt_id','reason','phase','confirmed_by','confirmed_at','secure_channel','updated_at'] as $key) {
            if (isset($value[$key]) && (!is_string($value[$key]) || strlen($value[$key]) > 1024)) {
                throw new \RuntimeException('rejoin_state_invalid', 409);
            }
        }
        foreach (['source','target'] as $key) {
            if (isset($value[$key]) && (!is_array($value[$key])
                || !self::guid($value[$key]['guid'] ?? null) || !is_string($value[$key]['sid'] ?? null))) {
                throw new \RuntimeException('rejoin_state_invalid', 409);
            }
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

    public static function settings(Host $host)
    {
        $q = self::$DB->link()->prepare('SELECT hdRejoinConfig, hdRejoinState FROM hostDirectory WHERE hdHostID = ?');
        $q->execute([(int)$host->get('id')]);
        $row = $q->fetch(\PDO::FETCH_ASSOC) ?: [];
        $q->closeCursor();
        return ['config' => self::decode($row['hdRejoinConfig'] ?? null),
            'state' => self::decode($row['hdRejoinState'] ?? null)];
    }

    /** Short DB-only transaction; LDAP, credentials and OS calls stay outside. */
    public static function change(Host $host, callable $fn)
    {
        $directoryScope = DirectoryNameFence::authorityScope($host);
        try {
        $db = self::$DB->link();
        $engines = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('hosts','hostDirectory')")->fetchAll(\PDO::FETCH_COLUMN);
        if (count($engines) !== 2 || array_filter($engines, static function ($engine) {
            return 0 !== strcasecmp($engine, 'InnoDB');
        })) {
            throw new \RuntimeException('rejoin_transactional_tables_required', 409);
        }
        DirectoryNameFence::begin($db);
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
            DirectoryNameFence::commit($db);
            return $result;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                DirectoryNameFence::rollback($db);
            }
            throw $e;
        }
        } finally {
            $directoryScope->close();
        }
    }

    public static function guid($value)
    {
        return is_string($value) && 1 === preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $value);
    }

    public static function connection(Host $host, $wantedDomain = null)
    {
        $c = DirectoryPlacement::connection($host);
        if (!preg_match('#^(?:ldaps|ldap)://[A-Za-z0-9.-]+(?::[0-9]+)?/?$#D', $c['uri'])) {
            throw new \RuntimeException('rejoin_directory_unconfigured', 409);
        }
        DirectoryNameFence::assertNoFenceAtDispatch();
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

    public static function lookup(FOGLdap $ldap, $base, $account)
    {
        $answer = $ldap->computerRecord($base, $account);
        if (!in_array($answer['status'], ['found', 'absent'], true)) {
            throw new \RuntimeException('rejoin_lookup_failed', 409);
        }
        return $answer;
    }

    public static function configure(Host $host, array $input, $actor)
    {
        foreach (['rejoin_enabled', 'rejoin_source_account', 'rejoin_source_guid', 'rejoin_source_sid'] as $key) {
            if (array_key_exists($key, $input)) {
                throw new \RuntimeException('legacy_directory_configuration_unsupported', 409);
            }
        }
        return self::configureTarget($host, $input, $actor);
    }

    public static function validName($name)
    {
        if (!is_string($name) || !preg_match('/^(?=.{1,15}$)[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/iD', $name)
            || ctype_digit($name)) {
            throw new \RuntimeException('rejoin_name_invalid', 409);
        }
    }

    /** Called before any poll side effect, even when facts have not changed. */
    public static function beforePoll(Host $host, array $body)
    {
        if (DirectoryOperation::requested($body) && !MachineIdentity::requested($body)) {
            throw new \RuntimeException('directory_target_feature_required', 409);
        }
        if (!MachineIdentity::requested($body)) {
            // Operation authority is current DB state, not a cached fact object.
            $saved = self::settings($host);
            $config = $saved['config'];
            $state = $saved['state'];
            $required = !empty($config['enabled']) || 2 === ($state['protocol'] ?? 0) || !empty($state['attempt_id']);
            if (!$required && '' !== (string)$host->get('agentFingerprint')) {
                foreach ((array)\FOG\Router\Route::getList('agentenrollment', ['fingerprint' => (string)$host->get('agentFingerprint')]) as $enrollment) {
                    $fields = (array)$enrollment;
                    $identity = json_decode((string)($fields['identity'] ?? ''), true) ?: [];
                    if (!empty($identity['_mj1']['required'])) {
                        $required = true;
                        break;
                    }
                }
            }
            if ($required) {
                throw new \RuntimeException('directory_target_feature_required', 409);
            }
            return;
        }
        $identity = MachineIdentity::check($host, $body);
        if ('matched' !== $identity['identity']['status']) {
            throw new \RuntimeException('identity_not_matched', 409);
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


    /** Read LDAP outside transactions and build only the single current plan. */
    public static function plan(Host $host, array $body = [])
    {
        $saved = self::settings($host);
        if (DirectoryOperation::legacyPending($saved['state'])) {
            return self::publicState($saved['state']);
        }
        return 2 === ($saved['state']['protocol'] ?? 1) ? DirectoryOperation::plan($host, $body) : null;
    }


    public static function sameObject(array $a, array $b)
    {
        return !empty($a['guid']) && !empty($a['sid'])
            && 0 === strcasecmp($a['guid'], $b['guid'] ?? '') && $a['sid'] === ($b['sid'] ?? '');
    }

    public static function start(Host $host, array $body)
    {
        if (2 !== (self::settings($host)['state']['protocol'] ?? 1)) {
            throw new \RuntimeException('legacy_directory_operation_requires_review', 409);
        }
        return DirectoryOperation::start($host, $body);
    }


    public static function report(Host $host, array $body)
    {
        if (2 !== (self::settings($host)['state']['protocol'] ?? 1)) {
            throw new \RuntimeException('legacy_directory_operation_requires_review', 409);
        }
        return DirectoryOperation::report($host, $body);
    }

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

    public static function publicState(array $s)
    {
        if (empty($s['attempt_id'])) {
            return null;
        }
        if (2 === ($s['protocol'] ?? 1)) {
            $s['task_id'] = (int)($s['trigger_task_id'] ?? 0);
            return ['version' => 2] + array_intersect_key($s, array_flip(['attempt_id','host_id','config_revision','phase',
                'name','domain','mode','dc','target','reason','operation','expected_current','expected_pending','retry_authorized','task_id']));
        }
        return ['phase' => 'blocked', 'reason' => 'legacy_directory_operation_requires_review'];
    }

    public static function blocked(Host $host, $reason)
    {
        self::change($host, function (&$c, &$s) use ($reason) {
            if (empty($s['attempt_id']) || 'verified' === ($s['phase'] ?? '')) {
                $s['reason'] = substr((string)$reason, 0, 100);
            }
            return null;
        });
    }

    public static function allowsCreate(array $config)
    {
        return !array_key_exists('allow_create', $config) || true === $config['allow_create'];
    }

    /** Retry permission never discards evidence of a possibly executed operation. */
    public static function canConfirmRetry(array $state)
    {
        return 'verified' === ($state['phase'] ?? '')
            || ('' === ($state['phase'] ?? '') && empty($state['attempt_id']));
    }

    public static function targetCandidate(Host $host)
    {
        try {
            return DirectoryOperation::candidate($host) + ['name' => (string)$host->get('name'), 'domain' => (string)$host->get('ADDomain')];
        } catch (\Throwable $e) {
            return ['status' => 'unavailable'];
        }
    }

    private static function configureTarget(Host $host, array $input, $actor)
    {
        $saved = self::settings($host);
        if (DirectoryOperation::legacyPending($saved['state'])) {
            throw new \RuntimeException('legacy_directory_operation_requires_review', 409);
        }
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
        return self::change($host, function (&$c, &$s) use ($saved, $config, $input, $host) {
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
            DirectoryPlacementIntent::cancelPrepared(
                DirectoryNameFence::pdo(),
                DirectoryPlacementIntent::editableOwner(DirectoryNameFence::pdo(), (int)$host->get('id'))
            );
            $c = $config;
        });
    }
}
