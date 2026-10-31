<?php
/** Task/name-driven Windows directory operations with one-shot claims. */
namespace FOG\Agent;

use FOG\Items\Host;
use FOG\Items\TaskType;

class DirectoryOperation extends \FOG\Base\FOGBase
{
    const FEATURE = 'directory-task-operations-v3';
    const OBSERVATION_MAX_AGE = 600;

    public static function requested(array $body)
    {
        return in_array(self::FEATURE, (array)($body['features'] ?? []), true);
    }

    /** A per-request view of the existing local native marker, never authority. */
    public static function localAttempt(array $body)
    {
        if (!array_key_exists('directory_attempt', $body)) {
            return ['phase' => 'none']; // Internal policy callers; the route requires it.
        }
        $a = $body['directory_attempt'];
        if (!is_array($a) || array_diff(array_keys($a), ['phase','attempt_id','host_id','fingerprint'])
            || !in_array($a['phase'] ?? null, ['none','unreadable','reserved','may_have_run','unknown','failed','awaiting_reboot','verified'], true)) {
            throw new \RuntimeException('directory_local_attempt_invalid', 400);
        }
        if (!in_array($a['phase'], ['none','unreadable'], true)
            && (!is_int($a['host_id'] ?? null) || $a['host_id'] < 1
                || !is_string($a['attempt_id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $a['attempt_id'])
                || !is_string($a['fingerprint'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $a['fingerprint']))) {
            throw new \RuntimeException('directory_local_attempt_invalid', 400);
        }
        return $a;
    }

    private static function localMatches(array $a, array $s)
    {
        return !empty($s['attempt_id']) && ($a['attempt_id'] ?? '') === $s['attempt_id']
            && ($a['host_id'] ?? 0) === ($s['host_id'] ?? -1)
            && ($a['fingerprint'] ?? '') === ($s['fingerprint'] ?? '');
    }

    public static function legacyPending(array $s)
    {
        return !empty($s['attempt_id']) && 2 !== ($s['protocol'] ?? 1)
            && 'verified' !== ($s['phase'] ?? '');
    }

    public static function revision(Host $host, array $c, $dc)
    {
        return substr(hash('sha256', json_encode([(int)$host->get('id'),
            (string)$host->get('name'), strtolower(trim((string)$host->get('ADDomain'))),
            (string)$host->get('agentFingerprint'), $c, $dc])), 0, 32);
    }

    /** Freshness is independent of SC and membership: Rename and Join differ. */
    public static function observationFresh(Host $host, array $c, array $s, $now = null)
    {
        $o = $s['observation'] ?? null;
        $at = $s['observed_at'] ?? null;
        $now = null === $now ? time() : $now;
        return is_array($o) && is_int($at) && $at >= 0
            && $at <= $now && $now - $at <= self::OBSERVATION_MAX_AGE
            && is_string($o['revision'] ?? null)
            && hash_equals(self::revision($host, $c, $s['check_dc'] ?? ''), $o['revision']);
    }

    /** OU placement needs current, healthy membership, not merely a past result. */
    public static function placementHealthy(Host $host, array $c, array $s)
    {
        $o = $s['observation'] ?? [];
        $dc = $s['check_dc'] ?? '';
        return self::observationFresh($host, $c, $s)
            && 'true' === ($o['secure_channel'] ?? '')
            && true === ($o['dc_reachable'] ?? false)
            && in_array($o['role'] ?? '', ['workstation','member_server'], true)
            && 0 === strcasecmp($o['current_name'] ?? '', (string)$host->get('name'))
            && 0 === strcasecmp($o['pending_name'] ?? '', (string)$host->get('name'))
            && self::sameDomain($o, (string)$host->get('ADDomain'))
            && is_string($o['dc'] ?? null) && '' !== $o['dc']
            && ('' === $dc || 0 === strcasecmp($o['dc'], $dc));
    }

    public static function check(Host $host)
    {
        if (!$host->get('useAD') || !State::factsEnabled()) {
            return null;
        }
        $saved = DirectoryRejoin::settings($host);
        $s = $saved['state'];
        if (2 !== ($s['protocol'] ?? 1) || self::legacyPending($s)) {
            return null;
        }
        $dc = $s['check_dc'] ?? '';
        return ['name' => (string)$host->get('name'), 'domain' => trim((string)$host->get('ADDomain')),
            'dc' => $dc, 'revision' => self::revision($host, $saved['config'], $dc)];
    }

    /** Before desired state and OU side effects; observations are never credentials. */
    public static function observe(Host $host, array $body)
    {
        $local = self::localAttempt($body);
        DirectoryRejoin::change($host, function (&$c, &$s) use ($host, $body, $local) {
            if (self::legacyPending($s)) {
                $s['placement_hold'] = true;
                return;
            }
            $contextChanged = strcasecmp($c['name'] ?? '', (string)$host->get('name'))
                || strcasecmp($c['domain'] ?? '', trim((string)$host->get('ADDomain')));
            if (2 !== ($c['protocol'] ?? 1) || ($contextChanged && (empty($s['attempt_id']) || 'verified' === ($s['phase'] ?? '')))) {
                $next = ['version' => 1, 'protocol' => 2, 'allow_create' => DirectoryRejoin::allowsCreate($c),
                    'name' => (string)$host->get('name'), 'domain' => strtolower(trim((string)$host->get('ADDomain'))),
                    'config_revision' => bin2hex(random_bytes(16))];
                if (!strcasecmp($c['name'] ?? '', $next['name']) && !strcasecmp($c['domain'] ?? '', $next['domain']) && !empty($c['target'])) {
                    $next['target'] = $c['target'];
                }
                $c = $next;
            }
            $s['protocol'] = 2;
            if ('verified' === ($s['phase'] ?? '')) {
                unset($s['source']);
            }
            $fresh = $body['directory'] ?? [];
            $workgroup = !empty($s['workgroup']);
            $isWorkgroup = false === ($fresh['joined'] ?? null) && 'workgroup' === ($fresh['kind'] ?? '');
            if (!empty($fresh)) {
                $s['workgroup'] = $isWorkgroup;
                $workgroup = $isWorkgroup;
            }
            $o = $body['directory_observation'] ?? null;
            if (null === $o) {
                // Even a recently accepted True is not this poll's observation.
                // Preserve its value/time and the operation history, but stop OU writes.
                $s['placement_hold'] = true;
                return;
            }
            if (!is_array($o)) {
                throw new \RuntimeException('directory_observation_invalid', 400);
            }
            if (array_diff(array_keys($o), ['revision','current_name','pending_name','domain','netbios','role','secure_channel','dc','dc_reachable','boot'])
                || (isset($o['boot']) && is_array($o['boot']) && array_diff(array_keys($o['boot']), ['marker','started']))) {
                throw new \RuntimeException('directory_observation_invalid', 400);
            }
            $dc = $s['check_dc'] ?? '';
            if (($o['revision'] ?? '') !== self::revision($host, $c, $dc)) {
                $s['placement_hold'] = true;
                return;
            }
            foreach (['current_name','pending_name','domain','netbios','role','secure_channel','dc'] as $key) {
                if (!is_string($o[$key] ?? null) || strlen($o[$key]) > 255) {
                    throw new \RuntimeException('directory_observation_invalid', 400);
                }
            }
            if (!in_array($o['secure_channel'], ['true','false','unknown'], true)
                || !is_bool($o['dc_reachable'] ?? null) || !is_array($o['boot'] ?? null)
                || !is_string($o['boot']['marker'] ?? null) || strlen($o['boot']['marker']) > 128
                || !is_string($o['boot']['started'] ?? null) || strlen($o['boot']['started']) > 64) {
                throw new \RuntimeException('directory_observation_invalid', 400);
            }
            $s['observation'] = $o;
            $s['observed_at'] = time();
            $active = !empty($s['attempt_id']) && 'verified' !== ($s['phase'] ?? '');
            $o = $s['observation'] ?? [];
            $healthy = self::placementHealthy($host, $c, $s);
            $task = DirectoryTask::read($host, $s, $o);
            $pending = !in_array($task['status'], ['none','consumed','baseline'], true);
            $localPending = !in_array($local['phase'], ['none','verified'], true);
            $s['placement_hold'] = $active || $localPending || $pending || (!$workgroup && !$healthy);
        });
    }

    public static function sameDomain(array $o, $want)
    {
        return '' !== $want && (0 === strcasecmp($o['domain'] ?? '', $want)
            || ('' !== ($o['netbios'] ?? '') && 0 === strcasecmp($o['netbios'], $want)));
    }

    /** Pure selector shared by DB and contract tests. Never treats errors as absence. */
    public static function select(array $o, $name, $deploy, $reassigned, array $target, $allowCreate, $confirmed, $authorized)
    {
        if (!in_array($o['role'] ?? '', ['workstation','member_server'], true)
            || empty($o['dc_reachable']) || 'unknown' === ($o['secure_channel'] ?? 'unknown')) {
            throw new \RuntimeException('directory_observation_unknown', 409);
        }
        $current = $o['current_name'] ?? '';
        $pending = $o['pending_name'] ?? '';
        if ('' === $current || '' === $pending || (strcasecmp($pending, $current) && strcasecmp($pending, $name))) {
            throw new \RuntimeException('directory_pending_name_conflict', 409);
        }
        $rename = 0 !== strcasecmp($current, $name);
        if (!$deploy && !$authorized && !$rename && 'true' === $o['secure_channel']) {
            return null;
        }
        if (!$deploy && !$rename && !$authorized) {
            throw new \RuntimeException('directory_recovery_confirmation_required', 409);
        }
        $status = $target['status'] ?? 'error';
        if (!$deploy && $rename && !$reassigned && 'absent' === $status) {
            // A known false SC observation is diagnostic, not permission to
            // replace the account. The existing object is checked separately.
            return ['operation' => 'rename', 'mode' => 'rename', 'target' => null];
        }
        if (!$deploy && $rename && !$reassigned && 'unconfigured' === $status) {
            throw new \RuntimeException('rename_identity_unverified', 409);
        }
        if ('found' === $status) {
            $record = $target['record'] ?? [];
            if (!DirectoryRejoin::guid($record['guid'] ?? null) || !is_string($record['sid'] ?? null)
                || '' === $record['sid'] || strcasecmp($record['account'] ?? '', $name.'$')) {
                throw new \RuntimeException('rejoin_target_invalid', 409);
            }
            if (!$confirmed) {
                throw new \RuntimeException('rejoin_target_confirmation_required', 409);
            }
            return ['operation' => 'rebind', 'mode' => 'existing', 'target' => $target['record']];
        }
        if ('absent' === $status && $allowCreate) {
            return ['operation' => 'rebind', 'mode' => 'create', 'target' => null];
        }
        throw new \RuntimeException('absent' === $status ? 'rejoin_create_not_permitted' : 'rejoin_lookup_failed', 409);
    }

    public static function candidate(Host $host, $account = null)
    {
        $config = DirectoryPlacement::connection($host);
        if (empty($config['uri'])) {
            return ['status' => 'unconfigured'];
        }
        $domain = trim((string)$host->get('ADDomain'));
        if (false === strpos($domain, '.')) {
            $saved = DirectoryRejoin::settings($host);
            $o = $saved['state']['observation'] ?? [];
            if (self::sameDomain($o, $domain) && false !== strpos($o['domain'] ?? '', '.')) {
                $domain = $o['domain'];
            }
        }
        list($ldap, $base, $dc) = DirectoryRejoin::connection($host, $domain);
        try {
            return DirectoryRejoin::lookup($ldap, $base, null === $account ? (string)$host->get('name') : $account) + ['dc' => $dc];
        } finally {
            $ldap->close();
        }
    }

    /** Per-operation identity of the existing account being renamed. */
    private static function renameObject(Host $host, $account, array $expected = [])
    {
        $answer = self::candidate($host, $account);
        $record = $answer['record'] ?? [];
        if ('found' !== $answer['status'] || !DirectoryRejoin::guid($record['guid'] ?? null)
            || !is_string($record['sid'] ?? null) || '' === $record['sid']
            || strcasecmp($record['account'] ?? '', $account.'$')
            || ($expected && !DirectoryRejoin::sameObject($expected, $record))) {
            throw new \RuntimeException('rename_identity_unverified', 409);
        }
        return $record;
    }

    public static function deploy(Host $host, array $s, array $o)
    {
        $task = DirectoryTask::read($host, $s, $o);
        return 'eligible' === $task['status'] ? $task['task_id'] : 0;
    }

    private static function taskBlocked(array $task)
    {
        $reason = 'eligible' === $task['status'] ? 'changed' : $task['status'];
        return ['phase' => 'blocked', 'reason' => 'directory_task_'.$reason];
    }

    public static function plan(Host $host, array $body = [])
    {
        $saved = DirectoryRejoin::settings($host);
        $c = $saved['config'];
        $s = $saved['state'];
        $local = self::localAttempt($body);
        if (self::legacyPending($s)) {
            return DirectoryRejoin::publicState($s);
        }
        if (!$host->get('useAD') || !State::factsEnabled()) {
            return !empty($s['attempt_id']) && 'verified' !== ($s['phase'] ?? '')
                ? ('ready' === $s['phase'] ? ['phase' => 'blocked', 'reason' => 'rejoin_disabled'] : DirectoryRejoin::publicState($s)) : null;
        }
        if (!in_array($local['phase'], ['none','verified'], true)) {
            $matching = self::localMatches($local, $s);
            if ('awaiting_reboot' === $local['phase'] && $matching && 'verified' === ($s['phase'] ?? '')) {
                return DirectoryRejoin::publicState($s); // Lost terminal ACK, not a new operation.
            }
            // A reserved summary is only a candidate for the Agent's stronger
            // version/snapshot check under its marker file lock. Never override
            // local native uncertainty with a Server retry authorization.
            $explicitRetry = !empty($c['retry_authorized']) && 'reserved' === $local['phase']
                && ($local['host_id'] ?? 0) === (int)$host->get('id')
                && ($local['fingerprint'] ?? '') === (string)$host->get('agentFingerprint')
                && in_array($s['phase'] ?? '', ['', 'ready', 'verified'], true);
            if (!$explicitRetry && (!$matching || in_array($s['phase'] ?? '', ['', 'ready','verified'], true))) {
                return ['phase' => 'blocked', 'reason' => 'directory_local_operation_requires_review'];
            }
        }
        if (!empty($s['attempt_id']) && 'verified' !== ($s['phase'] ?? '')) {
            if ('ready' === ($s['phase'] ?? '') && !empty($s['trigger_task_id'])) {
                $task = DirectoryTask::read($host, $s, $s['observation'] ?? []);
                if ('eligible' !== $task['status'] || $task['task_id'] !== $s['trigger_task_id']) {
                    return self::taskBlocked($task);
                }
            }
            return DirectoryRejoin::publicState($s);
        }
        $o = $s['observation'] ?? [];
        if (!self::observationFresh($host, $c, $s)) {
            return ['phase' => 'blocked', 'reason' => 'directory_observation_required'];
        }
        $workgroup = !empty($s['workgroup']) && '' === ($o['domain'] ?? '');
        if (!$workgroup && (!in_array($o['role'] ?? '', ['workstation','member_server'], true)
            || empty($o['dc_reachable']) || 'unknown' === ($o['secure_channel'] ?? 'unknown'))) {
            return ['phase' => 'blocked', 'reason' => 'directory_observation_unknown'];
        }
        if (!$workgroup && !self::sameDomain($o, trim((string)$host->get('ADDomain')))) {
            throw new \RuntimeException('directory_domain_mismatch', 409);
        }
        $name = (string)$host->get('name');
        if (!$host->isHostnameSafe()) {
            throw new \RuntimeException('rejoin_name_invalid', 409);
        }
        $taskState = DirectoryTask::read($host, $s, $o);
        if (!in_array($taskState['status'], ['none','consumed','baseline','eligible'], true)) {
            return self::taskBlocked($taskState);
        }
        if (!array_key_exists('last_deploy_task_id', $s) && 'eligible' !== $taskState['status']) {
            return DirectoryRejoin::change($host, function (&$config, &$state) use ($saved, $taskState) {
                if ($config !== $saved['config'] || $state !== $saved['state']) {
                    throw new \RuntimeException('directory_baseline_changed', 409);
                }
                $state['last_deploy_task_id'] = $taskState['task_id'];
                return null;
            });
        }
        $task = 'eligible' === $taskState['status'] ? $taskState['task_id'] : 0;
        if ($workgroup && !$task) {
            return null;
        }
        if ($workgroup && (!in_array($o['role'] ?? '', ['workstation','member_server','standalone_server'], true)
            || empty($o['dc_reachable']) || empty($o['current_name']) || empty($o['pending_name'])
            || (strcasecmp($o['pending_name'], $o['current_name']) && strcasecmp($o['pending_name'], $name)))) {
            throw new \RuntimeException('directory_join_observation_unknown', 409);
        }
        $same = !strcasecmp($o['current_name'], $name) && !strcasecmp($o['pending_name'], $name);
        if (!$task && empty($c['retry_authorized']) && $same && 'true' === $o['secure_channel']) {
            return null;
        }
        $q = self::$DB->link()->prepare('SELECT hostID FROM hosts WHERE LOWER(hostName)=LOWER(?) AND hostID<>? LIMIT 1');
        $q->execute([$o['current_name'], (int)$host->get('id')]);
        $reassigned = (bool)$q->fetchColumn();
        $q->closeCursor();
        $target = $workgroup ? ['status' => 'unconfigured'] : self::candidate($host);
        if (!empty($target['dc']) && 0 !== strcasecmp($target['dc'], $o['dc'])) {
            DirectoryRejoin::change($host, function (&$config, &$state) use ($target) {
                $state['check_dc'] = $target['dc'];
            });
            return ['phase' => 'blocked', 'reason' => 'directory_observation_required'];
        }
        $confirmed = !empty($c['target']) && 'found' === $target['status']
            && !strcasecmp($c['name'] ?? '', $name) && !strcasecmp($c['domain'] ?? '', (string)$host->get('ADDomain'))
            && DirectoryRejoin::sameObject($c['target'], $target['record']);
        $choice = $workgroup ? ['operation' => 'join', 'mode' => 'join', 'target' => null] : self::select(
            $o,
            $name,
            $task > 0,
            $reassigned,
            $target,
            DirectoryRejoin::allowsCreate($c),
            $confirmed,
            $task > 0 || !empty($c['retry_authorized'])
        );
        if (null === $choice) {
            return null;
        }
        if ('rename' === $choice['operation']) {
            $expected = !strcasecmp($s['name'] ?? '', $o['current_name']) && !empty($s['target'])
                ? $s['target'] : [];
            $choice['target'] = self::renameObject($host, $o['current_name'], $expected);
        }
        if ('rebind' === $choice['operation']) {
            DirectoryRejoin::validName($name);
        }
        $p = $choice + ['version' => 1, 'protocol' => 2, 'host_id' => (int)$host->get('id'),
            'attempt_id' => bin2hex(random_bytes(16)), 'config_revision' => $o['revision'],
            'fingerprint' => (string)$host->get('agentFingerprint'), 'name' => $name,
            'domain' => strtolower($workgroup ? trim((string)$host->get('ADDomain')) : $o['domain']), 'dc' => $o['dc'], 'phase' => 'ready',
            'expected_current' => $o['current_name'], 'expected_pending' => $o['pending_name'],
            'retry_authorized' => !empty($c['retry_authorized']),
            'placement_hold' => true,
            'trigger_task_id' => $task, 'last_deploy_task_id' => (int)($s['last_deploy_task_id'] ?? 0),
            'observation' => $o, 'observed_at' => $s['observed_at'], 'check_dc' => $s['check_dc'] ?? ''];
        return DirectoryRejoin::change($host, function (&$config, &$state, $principal) use ($saved, $p) {
            if ($config !== $saved['config'] || $state !== $saved['state'] || $principal['hostAgentFingerprint'] !== $p['fingerprint']) {
                throw new \RuntimeException('rejoin_plan_changed', 409);
            }
            $state = $p;
            return DirectoryRejoin::publicState($p);
        });
    }

    public static function start(Host $host, array $body)
    {
        $saved = DirectoryRejoin::settings($host);
        $s = $saved['state'];
        if (!$host->get('useAD') || !State::factsEnabled()) {
            throw new \RuntimeException('rejoin_disabled', 409);
        }
        self::claimable($host, $saved, $body);
        if ('rebind' === $s['operation']) {
            $t = self::candidate($host);
            if ($s['mode'] === 'existing' ? ('found' !== $t['status'] || !DirectoryRejoin::sameObject($s['target'], $t['record']))
                : ('absent' !== $t['status'] || !DirectoryRejoin::allowsCreate($saved['config']))) {
                throw new \RuntimeException('rejoin_target_changed', 409);
            }
        } elseif ('rename' === $s['operation']) {
            $t = self::candidate($host);
            if ('absent' !== $t['status']) {
                throw new \RuntimeException('rename_target_changed', 409);
            }
            if (empty($s['target'])) {
                throw new \RuntimeException('rename_identity_unverified', 409);
            }
            self::renameObject($host, $s['expected_current'], $s['target']);
            $q = self::$DB->link()->prepare('SELECT COUNT(*) FROM hosts WHERE LOWER(hostName)=LOWER(?) AND hostID<>?');
            $q->execute([$s['expected_current'], (int)$host->get('id')]);
            $assigned = (int)$q->fetchColumn();
            $q->closeCursor();
            if ($assigned) {
                throw new \RuntimeException('rename_name_reassigned', 409);
            }
        }
        $credential = DirectoryJoin::rejoinCredential($host);
        if ('' === $credential['username'] || '' === $credential['password']) {
            throw new \RuntimeException('rejoin_credential_missing', 409);
        }
        self::claim($host, $saved, $body);
        return ['status' => 'ok', 'directory' => $credential + ['rejoin' => DirectoryRejoin::publicState($s)]];
    }

    /** The DB-only authority boundary; LDAP and credentials stay outside the lock. */
    public static function claim(Host $host, array $saved, array $body)
    {
        DirectoryRejoin::change($host, function (&$c, &$state, $principal) use ($saved, $body, $host) {
            if ($c !== $saved['config'] || $state !== $saved['state'] || !$principal['hostUseAD']
                || $principal['hostAgentFingerprint'] !== $state['fingerprint']
                || strcasecmp($principal['hostName'], $state['name'])
                || ('join' === $state['operation']
                    ? strcasecmp($state['domain'], trim((string)$principal['hostADDomain']))
                    : !self::sameDomain($state['observation'], $principal['hostADDomain']))) {
                throw new \RuntimeException('rejoin_plan_changed', 409);
            }
            DirectoryNameFence::assertClaim($host, $c, $state, $principal);
            self::claimable($host, ['config' => $c, 'state' => $state], $body);
            $task = DirectoryTask::read($host, $state, $state['observation'], true);
            if (!empty($state['trigger_task_id'])) {
                if ('eligible' !== $task['status'] || $task['task_id'] !== $state['trigger_task_id']) {
                    throw new \RuntimeException('directory_task_claim_changed', 409);
                }
            } elseif (!in_array($task['status'], ['none','consumed'], true)) {
                throw new \RuntimeException('directory_task_requires_plan', 409);
            }
            if (!empty($state['trigger_task_id'])) {
                if ($state['trigger_task_id'] <= ($state['last_deploy_task_id'] ?? 0)) {
                    throw new \RuntimeException('deploy_already_consumed', 409);
                }
                $state['last_deploy_task_id'] = $state['trigger_task_id'];
            }
            $c['retry_authorized'] = false;
            $state['phase'] = 'started';
            $state['reason'] = 'mutation_may_have_run';
        });
    }

    public static function claimable(Host $host, array $saved, array $body)
    {
        $s = $saved['state'];
        if ('ready' !== ($s['phase'] ?? '') || 2 !== ($s['protocol'] ?? 1)
            || ($body['attempt_id'] ?? '') !== ($s['attempt_id'] ?? null)
            || ($body['config_revision'] ?? '') !== ($s['config_revision'] ?? null)
            || ($body['operation'] ?? '') !== ($s['operation'] ?? null)
            || ($body['task_id'] ?? null) !== (int)($s['trigger_task_id'] ?? 0)
            || ($body['target_guid'] ?? null) !== ($s['target']['guid'] ?? null)
            || (string)$host->get('agentFingerprint') !== ($s['fingerprint'] ?? null)
            || strcasecmp((string)$host->get('name'), $s['name'] ?? '')
            || !self::observationFresh($host, $saved['config'], $s)
            || self::revision($host, $saved['config'], $s['check_dc'] ?? '') !== ($s['config_revision'] ?? null)) {
            throw new \RuntimeException('rejoin_start_refused', 409);
        }
    }

    public static function report(Host $host, array $body)
    {
        $saved = DirectoryRejoin::settings($host);
        $s = $saved['state'];
        if (($body['attempt_id'] ?? '') !== ($s['attempt_id'] ?? null)
            || ($body['config_revision'] ?? '') !== ($s['config_revision'] ?? null)
            || (int)($body['id'] ?? 0) !== (int)$host->get('id')
            || ($body['task_id'] ?? null) !== (int)($s['trigger_task_id'] ?? 0)
            || (string)$host->get('agentFingerprint') !== ($s['fingerprint'] ?? null)) {
            throw new \RuntimeException('rejoin_result_mismatch', 409);
        }
        $phase = $body['status'] ?? '';
        if (!in_array($phase, ['awaiting_reboot','verified','failed','unknown'], true)) {
            throw new \RuntimeException('rejoin_result_invalid', 400);
        }
        $target = null;
        if (in_array($phase, ['awaiting_reboot','verified'], true)) {
            if (($body['native_status'] ?? null) !== 0) {
                throw new \RuntimeException('rejoin_native_success_required', 409);
            }
            if ('rebind' === $s['operation']) {
                $t = self::candidate($host);
                if ('found' !== $t['status'] || (!empty($s['target']) && !DirectoryRejoin::sameObject($s['target'], $t['record']))) {
                    throw new \RuntimeException('rejoin_target_changed', 409);
                }
                $target = $t['record'];
            } elseif ('rename' === $s['operation']) {
                $t = self::candidate($host);
                if ('found' !== $t['status'] || empty($s['target'])
                    || !DirectoryRejoin::sameObject($s['target'], $t['record'])) {
                    throw new \RuntimeException('rename_identity_unverified', 409);
                }
                $target = $t['record'];
            } elseif ('join' === $s['operation']) {
                $t = self::candidate($host);
                if ('found' === $t['status']) {
                    $target = $t['record'];
                } elseif ('unconfigured' !== $t['status']) {
                    throw new \RuntimeException('rename_target_unverified', 409);
                }
            }
            if ('verified' === $phase && (($body['secure_channel'] ?? '') !== 'healthy'
                || true !== ($body['boot_changed'] ?? false) || strcasecmp($body['current_name'] ?? '', $s['name'])
                || (strcasecmp($body['domain'] ?? '', $s['domain'])
                    && strcasecmp($body['netbios'] ?? '', $s['domain'])))) {
                throw new \RuntimeException('rejoin_verification_incomplete', 409);
            }
        }
        DirectoryRejoin::change($host, function (&$c, &$state, $principal) use ($saved, $phase, $body, $target) {
            if ($state !== $saved['state'] || $c !== $saved['config'] || $principal['hostAgentFingerprint'] !== $state['fingerprint']) {
                throw new \RuntimeException('rejoin_result_changed', 409);
            }
            DirectoryRejoin::transition($state['phase'], $phase);
            $state['phase'] = $phase;
            $state['reason'] = $phase;
            $state['native_status'] = $body['native_status'] ?? null;
            $state['secure_channel'] = $body['secure_channel'] ?? 'unknown';
            $state['updated_at'] = gmdate('c');
            if ($target) {
                $state['target'] = $target;
            }
            if ('verified' === $phase) {
                $state['placement_hold'] = false;
                if ($target) {
                    $c['target'] = $target;
                    $c['name'] = $state['name'];
                    $c['domain'] = strtolower(trim($principal['hostADDomain']));
                }
            }
        });
        return '';
    }
}
