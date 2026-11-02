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

    public static function guidBytes($guid)
    {
        return self::real('guidBytes', [$guid]);
    }

    public static function sameNamingContext($dn, $nc)
    {
        return self::real('sameNamingContext', [$dn, $nc]);
    }

    public static function samePlacementNC($dn, array $context)
    {
        return self::real('samePlacementNC', [$dn, $context]);
    }

    /**
     * Calls the product's own static helper. The real class cannot load under
     * its own name here, because the stub holds that name, so its source is
     * loaded once under another name.
     *
     * @param string $method the static method
     * @param array  $args   its arguments
     *
     * @return mixed
     */
    private static function real($method, array $args)
    {
        if (!class_exists('FOG\\Net\\DirectoryLdapReal', false)) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/packages/web/src/Net/FOGLdap.php');
            $source = preg_replace('/^class FOGLdap\b/m', 'class DirectoryLdapReal', (string)$source, 1);
            eval('?>' . $source);
        }
        return call_user_func_array(['FOG\\Net\\DirectoryLdapReal', $method], $args);
    }
}
