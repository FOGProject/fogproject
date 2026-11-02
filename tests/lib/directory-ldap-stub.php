<?php
/**
 * A fictional in-process stand-in for FOG\Net\FOGLdap, for the placement DB
 * tests. It answers from tables the test fills, counts connects and Moves,
 * and can fail a Move. No LDAP transport is used.
 *
 * A test includes this file BEFORE anything autoloads the real class, then
 * calls class_alias(DirectoryLdapStub::class, 'FOG\\Net\\FOGLdap', false).
 */
class DirectoryLdapStub
{
    public static $connects = 0;
    public static $lookups = [];
    public static $containers = [];
    public static $moves = [];
    public static $context = [];
    public static $records = [];
    public static $recordQueues = [];
    public static $containerQueue = [];
    public static $containerRecord = null;
    public static $onContainer = null;
    public static $onMove = null;
    public static $moveResult = true;
    private $connected = false;

    /** Forgets everything a previous case set, except the naming contexts. */
    public static function reset()
    {
        self::$connects = 0;
        self::$lookups = [];
        self::$containers = [];
        self::$moves = [];
        self::$records = [];
        self::$recordQueues = [];
        self::$containerQueue = [];
        self::$containerRecord = null;
        self::$onContainer = null;
        self::$onMove = null;
        self::$moveResult = true;
    }

    public function connect($uri, $bindDn, $password, $caFile = '')
    {
        self::$connects++;
        $this->connected = true;
        return true;
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
        return !empty(self::$recordQueues[$key])
            ? array_shift(self::$recordQueues[$key])
            : (self::$records[$key] ?? ['status' => 'error', 'reason' => 'fixture_record_unspecified']);
    }

    public function containerIdentity($dn)
    {
        self::$containers[] = ['kind' => 'container', 'dn' => (string)$dn];
        if (is_callable(self::$onContainer)) {
            $callback = self::$onContainer;
            self::$onContainer = null;
            $callback();
        }
        if (self::$containerQueue) {
            return array_shift(self::$containerQueue);
        }
        $record = self::$containerRecord;
        if (is_array($record) && ($dn === $record['dn'] || !strcasecmp((string)$dn, self::guidDN($record['guid'])))) {
            return ['status' => 'found', 'guid' => $record['guid'], 'dn' => $record['dn']];
        }
        return ['status' => 'error', 'reason' => 'fixture_container_unspecified'];
    }

    public function moveTo($dn, $parentDn, $rdn = '')
    {
        self::$moves[] = ['entry' => $dn, 'parent' => $parentDn, 'rdn' => $rdn];
        if (is_callable(self::$onMove)) {
            call_user_func(self::$onMove);
        }
        return self::$moveResult;
    }

    public function moveOutcome()
    {
        return ['kind' => self::$moveResult ? 'ldap_success' : 'request_result_unknown', 'code' => null];
    }

    public function error()
    {
        return 'fictional_ldap_stub';
    }

    public function close()
    {
        $this->connected = false;
    }

    public static function guidDN($guid)
    {
        return '<GUID=' . strtolower(str_replace('-', '', (string)$guid)) . '>';
    }

    public static function rdn($dn)
    {
        return explode(',', (string)$dn, 2)[0];
    }

    public static function parentDn($dn)
    {
        return explode(',', (string)$dn, 2)[1] ?? '';
    }

    public static function sameNamingContext($dn, $nc)
    {
        $dn = trim((string)$dn);
        $nc = trim((string)$nc);
        return '' !== $nc && (0 === strcasecmp($dn, $nc)
            || (strlen($dn) > strlen($nc) && 0 === strcasecmp(substr($dn, -strlen($nc) - 1), ',' . $nc)));
    }

    public static function samePlacementNC($dn, array $context)
    {
        $root = (string)($context['base_dn'] ?? '');
        if ('' === $root || !self::sameNamingContext($dn, $root)) {
            return false;
        }
        $best = '';
        foreach ($context['naming_contexts'] ?? [] as $nc) {
            if (is_string($nc) && strlen($nc) > strlen($best) && self::sameNamingContext($dn, $nc)) {
                $best = $nc;
            }
        }
        return '' !== $best && 0 === strcasecmp($best, $root);
    }
}
