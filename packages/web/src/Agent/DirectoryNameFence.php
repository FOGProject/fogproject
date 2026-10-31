<?php
/** Short same-connection ordering for FOG writers; never held across LDAP/native. */
namespace FOG\Agent;

use FOG\Base\FOGBase;
use FOG\Items\Host;

class DirectoryNameFence extends FOGBase
{
    const WAIT_SECONDS = 3;
    private static $frames = [];
    private static $next = 0;
    private static $owner;
    private static $poisoned = false;
    private static $parents = [];
    private static $dependencySets = [];

    private static function refuse($reason)
    {
        throw new DirectoryFenceRefusal($reason);
    }

    private static function scope(array $data)
    {
        $id = ++self::$next;
        self::$frames[$id] = $data + ['preflight' => false];
        return new DirectoryFenceScope($id);
    }

    public static function writerScope($kind, $table, array $fields, array $args)
    {
        return self::scope(['kind' => $kind, 'table' => $table, 'fields' => $fields, 'args' => $args]);
    }

    public static function routeScope($method, array $args)
    {
        return self::scope(['kind' => 'route', 'method' => $method, 'args' => $args]);
    }

    /** Last-resort scope for regular writers using PDODB directly. */
    public static function sqlScope()
    {
        return self::scope(['kind' => 'sql']);
    }

    public static function assertDispatchBoundary()
    {
        if (count(self::$frames) !== 1 || 'intent' !== self::$frames[array_key_first(self::$frames)]['kind']) {
            self::refuse('directory_dispatch_inside_writer');
        }
    }

    public static function assertNoFenceAtDispatch()
    {
        if (self::$frames || self::$owner || self::$poisoned) {
            self::refuse('directory_dispatch_fence_not_released');
        }
    }

    private static function queueCancellation(array $rows)
    {
        foreach ($rows as $row) {
            self::$owner['cancellations'][bin2hex($row['dpiGUID'])] = $row;
        }
    }

    private static function beginCancellationSQL()
    {
        if (empty(self::$owner['cancellations'])) {
            return;
        }
        $id = array_key_last(self::$frames);
        if ('sql' !== self::$frames[$id]['kind']) {
            self::refuse('directory_cancel_sql_scope_required');
        }
        $db = self::pdo();
        if ($db->inTransaction()) {
            self::refuse('directory_cancel_transaction_order');
        }
        self::begin($db);
        self::$frames[$id]['cancel_transaction'] = true;
        DirectoryPlacementIntent::cancelPrepared($db, array_values(self::$owner['cancellations']));
        self::$owner['cancellations'] = [];
    }

    /** Only a PREPARED cancellation introduces a one-statement transaction.
     * Existing bulk/chunk SQL contracts are otherwise unchanged.
     */
    public static function finishSQL($id, $success)
    {
        if (empty(self::$frames[$id]['cancel_transaction'])) {
            return null;
        }
        $db = self::pdo();
        $insertID = $db->lastInsertId();
        if ($success) {
            self::commit($db);
        } elseif ($db->inTransaction()) {
            self::rollback($db);
        }
        self::$frames[$id]['cancel_transaction'] = false;
        return $success ? (int)$insertID : null;
    }

    public static function begin(\PDO $db)
    {
        if ($db !== self::pdo() || true !== $db->beginTransaction()) {
            self::refuse('directory_fence_transaction_begin_failed');
        }
    }

    public static function commit(\PDO $db)
    {
        if ($db !== self::pdo() || true !== $db->commit()) {
            self::refuse('directory_fence_transaction_commit_unknown');
        }
    }

    public static function rollback(\PDO $db)
    {
        if ($db !== self::pdo() || true !== $db->rollBack()) {
            self::$poisoned = true;
            self::refuse('directory_fence_transaction_rollback_unknown');
        }
    }

    public static function inDecommission()
    {
        return (bool)self::$parents;
    }

    /** Preflight Host/HD association work before the first related save. */
    public static function preflightModels($table, array $fields, array $entities, $overrides = null)
    {
        if (!in_array($table, ['hosts', 'hostDirectory'], true)) {
            return;
        }
        self::acquire();
        $critical = 'hosts' === $table
            ? ['hostID', 'hostName', 'hostADDomain', 'hostADOU', 'hostUseAD', 'hostAgentFingerprint']
            : ['hdID', 'hdHostID'];
        foreach ($entities as $entity) {
            $values = [];
            foreach ($fields as $friendly => $column) {
                if (!in_array($column, $critical, true)) {
                    continue;
                }
                $value = is_object($overrides) && property_exists($overrides, $friendly)
                    ? $overrides->$friendly : $entity->get($friendly);
                $values[$column] = is_string($value) ? trim($value) : (is_bool($value) ? ($value ? '1' : '0') : $value);
            }
            self::guardInsertValues($table, $values, $values);
        }
    }

    public static function authorityScope(Host $host)
    {
        $scope = self::scope(['kind' => 'authority', 'host' => (int)$host->get('id')]);
        try {
            self::acquire();
            return $scope;
        } catch (\Throwable $e) {
            $scope->close();
            throw $e;
        }
    }

    /** Dedicated intent transitions use this same short boundary. */
    public static function run(callable $fn)
    {
        $scope = self::scope(['kind' => 'intent']);
        try {
            self::acquire();
            return $fn(self::pdo());
        } finally {
            $scope->close();
        }
    }

    public static function pdo()
    {
        if (!self::$owner || self::$poisoned || self::$owner['pid'] !== getmypid()
            || !method_exists(self::$DB, 'peekLink') || self::$DB->peekLink() !== self::$owner['pdo']) {
            self::refuse('directory_fence_connection_changed');
        }
        return self::$owner['pdo'];
    }

    public static function rows(\PDO $db, $sql, array $params = [])
    {
        if (method_exists(self::$DB, 'closeCursorForFence')) {
            self::$DB->closeCursorForFence($db);
        }
        $q = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $type = \PDO::PARAM_STR;
            if (is_array($value)) {
                list($value, $type) = $value;
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (null === $value) {
                $type = \PDO::PARAM_NULL;
            }
            $q->bindValue(is_int($key) ? $key + 1 : ':' . ltrim($key, ':'), $value, $type);
        }
        if (true !== $q->execute()) {
            self::refuse('directory_fence_query_failed');
        }
        try {
            return $q->fetchAll(\PDO::FETCH_ASSOC);
        } finally {
            $q->closeCursor();
        }
    }

    private static function session(\PDO $db)
    {
        $rows = self::rows($db, 'SELECT DATABASE() AS db, CASE WHEN @@lower_case_table_names=0 THEN DATABASE() ELSE LOWER(DATABASE()) END AS namespace_db, CONNECTION_ID() AS id, @@autocommit AS autocommit');
        if (count($rows) !== 1 || !is_string($rows[0]['db']) || '' === $rows[0]['db']
            || !is_string($rows[0]['namespace_db']) || '' === $rows[0]['namespace_db']) {
            self::refuse('directory_fence_database_invalid');
        }
        return $rows[0];
    }

    private static function acquire()
    {
        if (self::$poisoned) {
            self::refuse('directory_fence_poisoned');
        }
        if (self::$owner) {
            $db = self::pdo();
            $s = self::session($db);
            $held = self::rows($db, 'SELECT IS_USED_LOCK(?) AS owner', [self::$owner['key']]);
            if ($s['namespace_db'] !== self::$owner['db'] || (int)$s['id'] !== self::$owner['connection']
                || (int)($held[0]['owner'] ?? 0) !== self::$owner['connection']) {
                self::$poisoned = true;
                self::refuse('directory_fence_ownership_lost');
            }
            return;
        }
        $db = self::$DB->link();
        if (!$db instanceof \PDO || !method_exists(self::$DB, 'peekLink') || self::$DB->peekLink() !== $db) {
            self::refuse('directory_fence_captured_pdo_required');
        }
        if ((int)$db->getAttribute(\PDO::ATTR_ERRMODE) !== \PDO::ERRMODE_EXCEPTION) {
            self::refuse('directory_fence_exception_mode_required');
        }
        $s = self::session($db);
        if ($db->inTransaction() || 1 !== (int)$s['autocommit']) {
            self::refuse('directory_fence_lock_order');
        }
        $key = 'fog.mj1.name.v1:' . substr(hash('sha256', $s['namespace_db']), 0, 40);
        $held = self::rows($db, 'SELECT IS_USED_LOCK(?) AS owner', [$key]);
        if ((int)($held[0]['owner'] ?? 0) === (int)$s['id']) {
            self::refuse('directory_fence_stale_ownership');
        }
        $got = self::rows($db, 'SELECT GET_LOCK(?, ?) AS acquired', [$key, self::WAIT_SECONDS]);
        $value = $got[0]['acquired'] ?? null;
        if (1 !== $value && '1' !== $value) {
            self::refuse(0 === $value || '0' === $value ? 'directory_fence_timeout' : 'directory_fence_unavailable');
        }
        self::$owner = ['pdo' => $db, 'pid' => getmypid(), 'db' => $s['namespace_db'],
            'connection' => (int)$s['id'], 'key' => $key, 'root' => array_key_first(self::$frames)];
        DirectoryPlacementIntent::validateStorage($db);
    }

    public static function closeScope($id)
    {
        if (!isset(self::$frames[$id])) {
            self::refuse('directory_fence_scope_missing');
        }
        if (array_key_last(self::$frames) !== $id) {
            self::$poisoned = true;
            self::refuse('directory_fence_scope_order');
        }
        unset(self::$frames[$id]);
        if (!self::$owner || self::$owner['root'] !== $id) {
            return;
        }
        $owner = self::$owner;
        try {
            $db = self::pdo();
            if ($db->inTransaction()) {
                // An unknown callback's transaction is not ours to commit/roll back.
                // Do not release the ordering lock in front of uncommitted writes.
                self::refuse('directory_fence_transaction_leaked');
            }
            $released = self::rows($db, 'SELECT RELEASE_LOCK(?) AS released', [$owner['key']]);
            $value = $released[0]['released'] ?? null;
            if (1 !== $value && '1' !== $value) {
                self::refuse('directory_fence_release_unknown');
            }
            self::$owner = null;
            self::$parents = [];
            self::$dependencySets = [];
        } catch (\Throwable $e) {
            self::$poisoned = true;
            throw $e;
        }
    }

    private static function nativeRows()
    {
        $rows = self::rows(self::pdo(), 'SELECT hdHostID,hdRejoinConfig,hdRejoinState FROM hostDirectory');
        $out = [];
        foreach ($rows as $row) {
            $state = DirectoryRejoin::decode($row['hdRejoinState']);
            if (DirectoryOperation::legacyPending($state)) {
                self::refuse('directory_fence_uncertain_authority');
            }
            if (!$state || 'verified' === ($state['phase'] ?? '') || 'ready' === ($state['phase'] ?? '')) {
                continue;
            }
            if (empty($state['attempt_id']) && empty($state['phase'])) {
                continue;
            }
            if (!in_array($state['phase'] ?? '', ['started', 'awaiting_reboot', 'unknown', 'failed'], true)
                || (int)$row['hdHostID'] !== ($state['host_id'] ?? null)) {
                self::refuse('directory_fence_uncertain_authority');
            }
            $out[] = ['host' => (int)$row['hdHostID'], 'state' => $state];
        }
        return $out;
    }

    private static function footprint(array $s)
    {
        $names = [(string)($s['name'] ?? '')];
        if ('rename' === ($s['operation'] ?? '')) {
            $names[] = (string)($s['expected_current'] ?? '');
        }
        if (!empty($s['target']['account'])) {
            $names[] = substr($s['target']['account'], -1) === '$'
                ? substr($s['target']['account'], 0, -1) : $s['target']['account'];
        }
        if (in_array('', $names, true)) {
            self::refuse('directory_fence_footprint_invalid');
        }
        return array_map('strtolower', $names);
    }

    public static function assertOwnerIdle($id, $cancelPrepared = false)
    {
        foreach (self::nativeRows() as $row) {
            if ($row['host'] === (int)$id) {
                self::refuse('directory_host_operation_protected');
            }
        }
        if ($cancelPrepared) {
            self::queueCancellation(DirectoryPlacementIntent::editableOwner(self::pdo(), (int)$id));
        } else {
            DirectoryPlacementIntent::assertOwnerIdle(self::pdo(), (int)$id);
        }
    }

    public static function assertPlacement($host, array $target)
    {
        self::acquire();
        $wanted = strtolower(rtrim($target['account'], '$'));
        foreach (self::nativeRows() as $row) {
            $s = $row['state'];
            if ($row['host'] === (int)$host || in_array($wanted, self::footprint($s), true)
                || (!empty($s['target']['guid']) && !strcasecmp($s['target']['guid'], $target['guid']))
                || (!empty($s['target']['sid']) && $s['target']['sid'] === $target['sid'])) {
                self::refuse('directory_placement_native_in_progress');
            }
        }
    }

    private static function assertNameFree($name, $own)
    {
        foreach (self::nativeRows() as $row) {
            if ($row['host'] !== (int)$own && in_array(strtolower($name), self::footprint($row['state']), true)) {
                self::refuse('directory_name_operation_protected');
            }
        }
        self::queueCancellation(DirectoryPlacementIntent::editableName(self::pdo(), $name, (int)$own));
    }

    public static function assertClaim(Host $host, array $config, array $state, array $principal)
    {
        self::acquire();
        foreach (self::$frames as $frame) {
            if (in_array($frame['kind'], ['save', 'destroy', 'update', 'batch', 'route'], true)) {
                self::refuse('directory_claim_inside_generic_writer');
            }
        }
        $own = (int)$host->get('id');
        foreach (self::nativeRows() as $other) {
            if ($other['host'] === $own) {
                continue;
            }
            $sameGuid = !empty($state['target']['guid']) && !empty($other['state']['target']['guid'])
                && !strcasecmp($state['target']['guid'], $other['state']['target']['guid']);
            $sameSid = !empty($state['target']['sid']) && !empty($other['state']['target']['sid'])
                && $state['target']['sid'] === $other['state']['target']['sid'];
            if (array_intersect(self::footprint($state), self::footprint($other['state'])) || $sameGuid || $sameSid) {
                self::refuse('directory_target_operation_in_progress');
            }
        }
        if ('rename' === $state['operation']) {
            $rows = self::rows(self::pdo(), 'SELECT hostID FROM hosts WHERE LOWER(hostName)=LOWER(?) AND hostID<>?',
                [$state['expected_current'], $own]);
            if ($rows) {
                self::refuse('rename_name_reassigned');
            }
        }
        DirectoryPlacementIntent::assertClaim(self::pdo(), $own, $state);
    }

    private static function authority(array $row)
    {
        $c = DirectoryRejoin::decode($row['hdRejoinConfig'] ?? null);
        $s = DirectoryRejoin::decode($row['hdRejoinState'] ?? null);
        return count($c) > 1 || count($s) > 1;
    }

    private static function paramsFor($sql, array $params)
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $sql, $m);
        return array_intersect_key($params, array_flip($m[1]));
    }

    private static function table($sql)
    {
        if (preg_match('/^\s*(?:INSERT(?:\s+IGNORE)?\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([A-Za-z_][A-Za-z0-9_]*)`?\b/i', $sql, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function primary($table)
    {
        $rows = self::rows(self::pdo(), 'SHOW KEYS FROM `' . $table . '` WHERE Key_name=\'PRIMARY\'');
        if (count($rows) !== 1) {
            self::refuse('directory_fence_primary_key_required');
        }
        return $rows[0]['Column_name'];
    }

    /** Extract only the trusted framework's generated WHERE/tail, not arbitrary SQL. */
    private static function selection($sql, $table, array $params)
    {
        list($prefix, $where, $tail) = self::queryParts($sql);
        $query = 'SELECT * FROM `' . $table . '`' . ('' === $where ? '' : ' WHERE ' . $where) . $tail;
        return [self::rows(self::pdo(), $query, self::paramsFor($query, $params)), $where, $tail];
    }

    private static function queryParts($sql)
    {
        $prefix = $sql;
        $where = '';
        $tail = '';
        if (preg_match('/\s+(ORDER\s+BY|LIMIT)\s/is', $sql, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1];
            $tail = substr($sql, $at);
            $prefix = substr($sql, 0, $at);
        }
        if (preg_match('/\s+WHERE\s+(.*)$/is', $prefix, $m, PREG_OFFSET_CAPTURE)) {
            $where = $m[1][0];
            $prefix = substr($prefix, 0, $m[0][1]);
        }
        return [$prefix, $where, $tail];
    }

    private static function values($sql, array $params)
    {
        $out = [];
        if (preg_match('/\bSET\s+(.*?)(?:\s+WHERE\s|$)/is', $sql, $m)) {
            preg_match_all('/(?:`[^`]+`\.)?`([^`]+)`\s*=\s*:([A-Za-z0-9_]+)/', $m[1], $v, PREG_SET_ORDER);
            foreach ($v as $pair) {
                $value = $params[$pair[2]] ?? null;
                $out[$pair[1]] = is_array($value) ? $value[0] : $value;
            }
        }
        return $out;
    }

    private static function setColumns($sql)
    {
        if (!preg_match('/\bSET\s+(.*?)(?:\s+WHERE\s|\s+ORDER\s+BY\s|\s+LIMIT\s|$)/is', $sql, $m)) {
            return [];
        }
        preg_match_all('/(?:`[^`]+`\.)?`?([A-Za-z_][A-Za-z0-9_]*)`?\s*=/', $m[1], $columns);
        return array_values(array_unique(array_map('strtolower', $columns[1])));
    }

    private static function canonicalValues($table, array $values)
    {
        $names = 'hosts' === $table
            ? ['hostID', 'hostName', 'hostADDomain', 'hostADOU', 'hostUseAD', 'hostAgentFingerprint']
            : ['hdID', 'hdHostID', 'hdRejoinConfig', 'hdRejoinState'];
        $out = [];
        foreach ($values as $key => $value) {
            foreach ($names as $name) {
                if (!strcasecmp($key, $name)) {
                    $key = $name;
                    break;
                }
            }
            $out[$key] = is_bool($value) ? ($value ? '1' : '0') : $value;
        }
        return $out;
    }

    private static function guardRows($table, array $rows, array $values, $delete)
    {
        if ('hosts' === $table && $delete && $rows) {
            self::guardCascades($rows);
        }
        foreach ($rows as $row) {
            if ('hosts' === $table) {
                $id = (int)$row['hostID'];
                $critical = ['hostID', 'hostName', 'hostADDomain', 'hostADOU', 'hostUseAD', 'hostAgentFingerprint'];
                $changed = $delete;
                foreach ($critical as $key) {
                    if (array_key_exists($key, $values)
                        && ((null === $row[$key] || null === $values[$key])
                            ? $row[$key] !== $values[$key] : (string)$row[$key] !== (string)$values[$key])) {
                        $changed = true;
                    }
                }
                if ($changed) {
                    self::assertOwnerIdle($id, true);
                }
                if (array_key_exists('hostID', $values) && (int)$values['hostID'] !== $id) {
                    foreach (self::rows(self::pdo(), 'SELECT hdRejoinConfig,hdRejoinState FROM hostDirectory WHERE hdHostID=?', [$id]) as $directory) {
                        if (self::authority($directory)) {
                            self::refuse('directory_authority_parent_identity_protected');
                        }
                    }
                }
                if (isset($values['hostName'])) {
                    self::assertNameFree($values['hostName'], $id);
                }
            } elseif ('hostDirectory' === $table) {
                $owner = (int)$row['hdHostID'];
                if ($delete && self::$parents && !isset(self::$parents[$owner])) {
                    self::refuse('directory_dependency_outside_decommission');
                }
                $authorityChange = $delete || (array_key_exists('hdHostID', $values) && (int)$values['hdHostID'] !== $owner)
                    || (array_key_exists('hdID', $values) && (int)$values['hdID'] !== (int)$row['hdID'])
                    || array_key_exists('hdRejoinConfig', $values) || array_key_exists('hdRejoinState', $values);
                if ($authorityChange && !($delete && isset(self::$parents[$owner]))) {
                    if (self::authority($row)) {
                        self::refuse('directory_authority_history_protected');
                    }
                    self::assertOwnerIdle($owner, true);
                }
                if (array_key_exists('hdHostID', $values) && (int)$values['hdHostID'] !== $owner) {
                    self::assertOwnerIdle((int)$values['hdHostID'], true);
                }
            }
        }
        if ('hostDirectory' === $table && (array_key_exists('hdRejoinConfig', $values) || array_key_exists('hdRejoinState', $values))) {
            self::refuse('directory_authority_dedicated_writer_required');
        }
    }

    /** Called only for framework SQL. SQL and bindings remain by-reference together. */
    public static function beforeSQL($pdo, &$sql, array &$params)
    {
        $table = self::table($sql);
        if (null === $table) {
            return;
        }
        foreach (['hosts', 'hostDirectory', 'directoryPlacementIntent'] as $canonical) {
            if ($canonical !== $table && !strcasecmp($canonical, $table)) {
                $mode = self::rows($pdo, 'SELECT @@lower_case_table_names AS mode');
                if ((int)$mode[0]['mode'] !== 0) {
                    $table = $canonical;
                }
            }
        }
        if (!in_array($table, ['hosts', 'hostDirectory', 'directoryPlacementIntent'], true)) {
            if (self::$parents) {
                self::guardDependencySQL($table, $sql, $params);
            }
            return;
        }
        $values = self::canonicalValues($table, self::values($sql, self::normalizedParams($params)));
        $setColumns = self::setColumns($sql);
        if ('hosts' === $table && preg_match('/^\s*UPDATE/i', $sql)
            && $setColumns && !array_intersect($setColumns, ['hostid', 'hostname', 'hostaddomain', 'hostadou', 'hostusead', 'hostagentfingerprint'])
            && !self::hasSensitiveUpdateFrame($table) && !self::$parents) {
            return; // Ordinary non-authority manager facts remain outside this lock.
        }
        $params = self::normalizedParams($params);
        if (!self::$frames) {
            self::refuse('directory_fence_writer_scope_required');
        }
        self::acquire();
        if ($pdo !== self::pdo()) {
            self::refuse('directory_fence_sql_connection_changed');
        }
        if ('directoryPlacementIntent' === $table) {
            self::refuse('directory_intent_dedicated_writer_required');
        }
        if (preg_match('/^\s*INSERT/i', $sql)) {
            self::guardInsert($table, $sql, $params);
            self::beginCancellationSQL();
            return;
        }
        list($rows, $where, $tail) = self::selection($sql, $table, $params);
        $values = self::canonicalValues($table, self::values($sql, $params));
        $delete = (bool)preg_match('/^\s*DELETE/i', $sql);
        if (!$delete && (!$setColumns || array_diff($setColumns, array_map('strtolower', array_keys($values))))) {
            self::refuse('directory_fence_update_shape_unknown');
        }
        $rows = self::preflightUpdateFrames($table, $rows);
        // Preflight may consider later payloads, but only reservations touched
        // by this actual statement may be cancelled in its transaction.
        self::$owner['cancellations'] = [];
        if ($delete && 'hosts' === $table && self::$parents) {
            $rows = array_values(array_filter($rows, static function ($row) {
                return isset(self::$parents[(int)$row['hostID']]);
            }));
        }
        self::guardRows($table, $rows, $values, $delete);
        $pk = self::primary($table);
        $keys = [];
        foreach ($rows as $i => $row) {
            $key = '__mj1_fence_pk_' . $i;
            if (array_key_exists($key, $params)) {
                self::refuse('directory_fence_parameter_collision');
            }
            $params[$key] = $row[$pk];
            $keys[] = ':' . $key;
        }
        $condition = $keys ? '`' . $table . '`.`' . $pk . '` IN (' . implode(',', $keys) . ')' : '0=1';
        list($prefix) = self::queryParts($sql);
        $sql = $prefix . ' WHERE ' . ('' === $where ? '' : '(' . $where . ') AND ') . $condition . $tail;
        self::beginCancellationSQL();
    }

    private static function preflightUpdateFrames($table, array $rows)
    {
        foreach (self::$frames as $id => $frame) {
            if ('update' !== $frame['kind'] || $frame['table'] !== $table) {
                continue;
            }
            $pk = 'hosts' === $table ? 'hostID' : 'hdID';
            if ($frame['preflight']) {
                $rows = array_values(array_filter($rows, static function ($row) use ($pk, $frame) {
                    return isset($frame['fixed_ids'][(string)$row[$pk]]);
                }));
                continue;
            }
            $data = $frame['args'][2] ?? [];
            $payloads = isset($data[0]) && is_array($data[0]) ? $data : [$data];
            foreach ($payloads as $payload) {
                $values = [];
                foreach ($payload as $key => $value) {
                    if (!isset($frame['fields'][$key])) {
                        self::refuse('directory_fence_update_field_invalid');
                    }
                    $values[$frame['fields'][$key]] = is_string($value) ? trim($value) : (is_bool($value) ? ($value ? '1' : '0') : $value);
                }
                self::guardRows($table, $rows, $values, false);
            }
            self::$frames[$id]['preflight'] = true;
            self::$frames[$id]['fixed_ids'] = array_fill_keys(array_map('strval', array_column($rows, $pk)), true);
        }
        return $rows;
    }

    private static function normalizedParams(array $params)
    {
        $out = [];
        foreach ($params as $key => $value) {
            $key = is_string($key) ? ltrim($key, ':') : $key;
            if (array_key_exists($key, $out)) {
                self::refuse('directory_fence_parameter_collision');
            }
            $out[$key] = $value;
        }
        return $out;
    }

    private static function hasSensitiveUpdateFrame($table)
    {
        foreach (self::$frames as $frame) {
            if ('update' !== $frame['kind'] || $frame['table'] !== $table) {
                continue;
            }
            $data = $frame['args'][2] ?? [];
            foreach (isset($data[0]) && is_array($data[0]) ? $data : [$data] as $payload) {
                foreach (array_keys($payload) as $key) {
                    $column = $frame['fields'][$key] ?? '';
                    if (in_array($column, ['hostID', 'hostName', 'hostADDomain', 'hostADOU', 'hostUseAD', 'hostAgentFingerprint'], true)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private static function guardInsert($table, $sql, array $params)
    {
        self::preflightBatchFrames($table);
        self::$owner['cancellations'] = [];
        if (!preg_match('/INSERT(?:\s+IGNORE)?\s+INTO\s+`?[A-Za-z0-9_]+`?\s*\(([^)]+)\)\s*VALUES\s*(.*?)(?:\s+ON\s+DUPLICATE|$)/is', $sql, $m)) {
            self::refuse('directory_fence_insert_shape_unknown');
        }
        preg_match_all('/`([^`]+)`/', $m[1], $columns);
        preg_match_all('/\(([^()]*)\)/', $m[2], $tuples);
        if (!$columns[1] || !$tuples[1]) {
            self::refuse('directory_fence_insert_shape_unknown');
        }
        foreach ($tuples[1] as $tuple) {
            $parts = explode(',', $tuple);
            if (count($parts) !== count($columns[1])) {
                self::refuse('directory_fence_insert_arity');
            }
            $values = [];
            foreach ($parts as $i => $part) {
                if (!preg_match('/^\s*:([A-Za-z0-9_]+)\s*$/', $part, $p)) {
                    self::refuse('directory_fence_insert_value_unknown');
                }
                $value = $params[$p[1]] ?? null;
                $values[$columns[1][$i]] = is_array($value) ? $value[0] : $value;
            }
            $updated = [];
            if (preg_match('/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.*)$/is', $sql, $u)) {
                preg_match_all('/`([^`]+)`\s*=\s*VALUES\s*\(/i', $u[1], $keys);
                $updated = array_intersect_key($values, array_flip($keys[1]));
            }
            self::guardInsertValues($table, $values, $updated);
        }
    }

    private static function preflightBatchFrames($table)
    {
        foreach (self::$frames as $id => $frame) {
            if ('batch' !== $frame['kind'] || $frame['preflight']
                || (($frame['args'][2] ?? '') ?: $frame['table']) !== $table) {
                continue;
            }
            $fields = $frame['args'][0] ?? [];
            foreach ($frame['args'][1] ?? [] as $tuple) {
                if (count($fields) !== count($tuple)) {
                    self::refuse('directory_fence_batch_arity');
                }
                $values = [];
                foreach ($fields as $i => $field) {
                    if (!isset($frame['fields'][$field])) {
                        self::refuse('directory_fence_batch_field_invalid');
                    }
                    $value = $tuple[$i];
                    $values[$frame['fields'][$field]] = is_string($value) ? trim($value) : (is_bool($value) ? ($value ? '1' : '0') : $value);
                }
                self::guardInsertValues($table, $values, $values);
            }
            self::$frames[$id]['preflight'] = true;
        }
    }

    private static function guardInsertValues($table, array $values, array $updated)
    {
        $values = self::canonicalValues($table, $values);
        $updated = self::canonicalValues($table, $updated);
        $keys = 'hosts' === $table ? ['hostID', 'hostName'] : ['hdID', 'hdHostID'];
        $rows = [];
        foreach ($keys as $key) {
            if (!isset($values[$key]) || '' === (string)$values[$key] || ('hostName' !== $key && 0 === (int)$values[$key])) {
                continue;
            }
            foreach (self::rows(self::pdo(), 'SELECT * FROM `' . $table . '` WHERE `' . $key . '`=?', [$values[$key]]) as $row) {
                $rows[(string)($row['hostID'] ?? $row['hdID'])] = $row;
            }
        }
        self::guardRows($table, array_values($rows), $updated, false);
        if ('hostDirectory' === $table && !$rows
            && (array_key_exists('hdRejoinConfig', $values) || array_key_exists('hdRejoinState', $values))) {
            self::refuse('directory_authority_dedicated_writer_required');
        }
        if ('hosts' === $table && isset($values['hostName'])) {
            self::assertNameFree($values['hostName'], (int)($values['hostID'] ?? 0));
        }
    }

    public static function preflightDecommission($classname, array $ids)
    {
        if ('host' !== strtolower($classname)) {
            return;
        }
        self::acquire();
        foreach ($ids as $id) {
            if (self::$parents && !isset(self::$parents[(int)$id])) {
                self::refuse('directory_decommission_parent_scope_changed');
            }
            self::assertOwnerIdle((int)$id, true);
        }
        $hosts = [];
        foreach ($ids as $id) {
            $hosts = array_merge($hosts, self::rows(self::pdo(), 'SELECT * FROM hosts WHERE hostID=?', [$id]));
        }
        self::guardCascades($hosts);
        foreach ($ids as $id) {
            self::$parents[(int)$id] = true;
        }
    }

    /** SQL-engine CASCADE has no PHP child DELETE to intercept. Inspect its
     * actual FK closure before hooks and again before the parent SQL. A shared
     * wake relay may disappear with an inactive endpoint as before, but not
     * while its other endpoint owns an unresolved directory operation.
     */
    private static function guardCascades(array $hosts)
    {
        $parents = array_fill_keys(array_map('intval', array_column($hosts, 'hostID')), true);
        $queue = [];
        foreach ($hosts as $host) {
            $queue[] = ['hosts', $host, 0];
        }
        $seen = [];
        while ($queue) {
            list($table, $row, $depth) = array_shift($queue);
            $key = hash('sha256', serialize([$table, $row]));
            if (isset($seen[$key])) {
                continue;
            }
            if ($depth > 12) {
                self::refuse('directory_cascade_depth_unknown');
            }
            $seen[$key] = true;
            $edges = self::rows(self::pdo(), 'SELECT k.TABLE_NAME AS child,k.COLUMN_NAME AS column_name,k.REFERENCED_COLUMN_NAME AS parent_column,k.CONSTRAINT_NAME AS constraint_name FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME=? AND r.DELETE_RULE=\'CASCADE\' ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION', [$table]);
            $groups = [];
            foreach ($edges as $edge) {
                $groups[$edge['child'] . ':' . $edge['constraint_name']][] = $edge;
            }
            foreach ($groups as $group) {
                $child = $group[0]['child'];
                $where = [];
                $params = [];
                foreach ($group as $edge) {
                    foreach ([$child, $edge['column_name'], $edge['parent_column']] as $identifier) {
                        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier)) {
                            self::refuse('directory_cascade_mapping_unknown');
                        }
                    }
                    if (!array_key_exists($edge['parent_column'], $row)) {
                        self::refuse('directory_cascade_parent_unknown');
                    }
                    $where[] = '`' . $edge['column_name'] . '`=?';
                    $params[] = $row[$edge['parent_column']];
                }
                foreach (self::rows(self::pdo(), 'SELECT * FROM `' . $child . '` WHERE ' . implode(' AND ', $where), $params) as $dependent) {
                    $owners = self::owners($child, $dependent);
                    if (!$owners) {
                        self::refuse('directory_cascade_owner_unknown');
                    }
                    foreach ($owners as $owner) {
                        if (!isset($parents[$owner])) {
                            if ('hostDirectory' === $child && self::authority($dependent)) {
                                self::refuse('directory_cascade_outside_authority');
                            }
                            self::assertOwnerIdle($owner);
                        }
                    }
                    $queue[] = [$child, $dependent, $depth + 1];
                }
            }
        }
    }

    public static function preflightDependencies(array $operations)
    {
        if (!self::$parents) {
            return;
        }
        foreach ($operations as $operation) {
            $table = $operation['table'];
            if (!is_string($table) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
                self::refuse('directory_dependency_owner_unknown');
            }
            $sql = $operation['sql'];
            $params = self::normalizedParams($operation['params']);
            self::guardDependencySelection($table, $sql, $params);
        }
    }

    private static function owners($table, array $row, array $visited = [])
    {
        if ('hosts' === $table) {
            return [(int)$row['hostID']];
        }
        if (isset($visited[$table]) || count($visited) > 6) {
            return [];
        }
        $visited[$table] = true;
        // The normative core map also describes pre-FK relationships. Enforce
        // the actual row values; do not assume a declared CASCADE is installed.
        $edges = require dirname(__DIR__, 2) . '/commons/schema-constraints.php';
        $actual = self::rows(self::pdo(), 'SELECT COLUMN_NAME AS column_name,REFERENCED_TABLE_NAME AS parent_table,REFERENCED_COLUMN_NAME AS parent_column FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL', [$table]);
        foreach ($actual as $edge) {
            $edges[] = ['child' => $table, 'column' => $edge['column_name'], 'parent' => $edge['parent_table'], 'pcolumn' => $edge['parent_column']];
        }
        $selected = array_values(array_filter($edges, static function ($edge) use ($table) {
            return $edge['child'] === $table;
        }));
        $direct = array_values(array_filter($selected, static function ($edge) {
            return $edge['parent'] === 'hosts';
        }));
        $owners = [];
        foreach ($direct ?: $selected as $edge) {
            foreach ([$edge['column'], $edge['parent'], $edge['pcolumn']] as $identifier) {
                if (!is_string($identifier) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
                    self::refuse('directory_dependency_mapping_invalid');
                }
            }
            $value = $row[$edge['column']] ?? null;
            if (null === $value || '' === (string)$value || 0 === (int)$value) {
                continue;
            }
            $parents = self::rows(self::pdo(), 'SELECT * FROM `' . $edge['parent'] . '` WHERE `' . $edge['pcolumn'] . '`=?', [$value]);
            if (count($parents) !== 1) {
                if ($direct) {
                    self::refuse('directory_dependency_owner_missing');
                }
                continue;
            }
            $owners = array_merge($owners, self::owners($edge['parent'], $parents[0], $visited));
        }
        return array_values(array_unique($owners));
    }

    private static function guardDependencySelection($table, $sql, array $params)
    {
        self::acquire();
        list($rows, $where, $tail) = self::selection($sql, $table, $params);
        $pk = self::primary($table);
        $key = hash('sha256', serialize([$table, $where, $tail, self::paramsFor($where, $params)]));
        $ids = [];
        foreach ($rows as $row) {
            $owners = self::owners($table, $row);
            if (!$owners) {
                self::refuse('directory_dependency_owner_unknown');
            }
            foreach ($owners as $owner) {
                if (!isset(self::$parents[$owner])) {
                    self::refuse('directory_dependency_outside_decommission');
                }
            }
            if (!isset(self::$dependencySets[$key]) || isset(self::$dependencySets[$key][(string)$row[$pk]])) {
                $ids[(string)$row[$pk]] = true;
            }
        }
        self::$dependencySets[$key] = $ids;
        return [$rows, $where, $tail, $ids, $pk];
    }

    private static function guardDependencySQL($table, &$sql, array &$params)
    {
        if (!preg_match('/^\s*DELETE/i', $sql)) {
            return;
        }
        $params = self::normalizedParams($params);
        list($rows, $where, $tail, $ids, $pk) = self::guardDependencySelection($table, $sql, $params);
        $keys = [];
        foreach (array_keys($ids) as $i => $id) {
            $name = '__mj1_dependency_' . $i;
            if (array_key_exists($name, $params)) {
                self::refuse('directory_fence_parameter_collision');
            }
            $params[$name] = $id;
            $keys[] = ':' . $name;
        }
        $condition = $keys ? '`' . $table . '`.`' . $pk . '` IN (' . implode(',', $keys) . ')' : '0=1';
        list($prefix) = self::queryParts($sql);
        $sql = $prefix . ' WHERE ' . ('' === $where ? '' : '(' . $where . ') AND ') . $condition . $tail;
    }
}
