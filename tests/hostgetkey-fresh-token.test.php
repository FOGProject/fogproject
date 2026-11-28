<?php
/**
 * status/hostgetkey.php issues a single-use, short-lived, hashed host token.
 *
 * The script used to lock the token when it handed it out and re-serve the
 * stored plaintext. A host that died mid-task left the lock set, so every
 * later call answered "Host token is currently in use". Now every call mints
 * a new token and stores only its sha256, in one conditional UPDATE: it
 * changes the row when there is no lock or the lock is HOST_TOKEN_TTL seconds
 * old or older, and the plaintext is answered only when a row changed. Two
 * callers at once cannot both win.
 *
 * The script runs top to bottom with no function to call, so this test runs
 * the script's own try block, cut out by its anchors, against stubs. The
 * stub database models the one host row and applies the UPDATE's condition
 * from the bound parameters; the SQL text is checked separately.
 *
 * Requires no database.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace HostgetkeyTest;

require_once __DIR__ . '/lib/fog-test-harness.php';

// The lifetime comes from the real constant, so the boundary cases below
// follow it if it changes.
preg_match(
    '/const HOST_TOKEN_TTL = (\d+);/',
    (string)file_get_contents(
        dirname(__DIR__) . '/packages/web/src/Base/FOGCore.php'
    ),
    $ttlMatch
);
define('HostgetkeyTest\TTL', (int)($ttlMatch[1] ?? 0));

/**
 * Stand-in host: a field map that is NOT refreshed by an update.
 */
class StubHost
{
    /**
     * The fields as loaded.
     *
     * @var array
     */
    public $fields;
    /**
     * Holds the fields.
     *
     * @param array $fields the fields
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }
    /**
     * Reads a field.
     *
     * @param string $key the field
     *
     * @return mixed
     */
    public function get($key)
    {
        if ('task' === $key) {
            return new StubTask();
        }
        return $this->fields[$key] ?? '';
    }
    /**
     * Whether the host is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return true;
    }
    /**
     * Records a save: a save of the loaded host would write its old token
     * fields back over the claim.
     *
     * @return StubHost
     */
    public function save()
    {
        ++FOGCore::$saves;
        return $this;
    }
}

/**
 * Stand-in task: always valid.
 */
class StubTask
{
    /**
     * Whether the task is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return true;
    }
}

/**
 * Stand-in database: one hosts row, and the affected-row count.
 */
class StubDb
{
    /**
     * The row: id, lock, time, key.
     *
     * @var array
     */
    public $row = ['id' => 7, 'lock' => 0, 'time' => 0, 'key' => ''];
    /**
     * Every SQL text received.
     *
     * @var array
     */
    public $sql = [];
    /**
     * Rows the last statement changed.
     *
     * @var int
     */
    public $affected = 0;
    /**
     * Applies the conditional UPDATE the way the server would.
     *
     * @param string $sql    the statement
     * @param array  $data   unused
     * @param array  $params the bound values
     *
     * @return StubDb
     */
    public function query($sql, $data = [], $params = [])
    {
        $this->sql[] = $sql;
        $this->affected = 0;
        $r = &$this->row;
        if ($params[':id'] === $r['id']
            && (
                0 === $r['lock']
                // The expiry arm applies only when the SQL carries it.
                || (
                    false !== strpos($sql, 'OR `hostInfoLockTime`<=:expired')
                    && $r['time'] <= $params[':expired']
                )
            )
        ) {
            $r['key'] = $params[':hash'];
            $r['lock'] = 1;
            $r['time'] = $params[':now'];
            $this->affected = 1;
        }
        return $this;
    }
    /**
     * Rows the last statement changed.
     *
     * @return int
     */
    public function affectedRows()
    {
        return $this->affected;
    }
}

/**
 * Stand-in for FOGCore.
 */
class FOGCore
{
    const HOST_TOKEN_TTL = TTL;
    /**
     * The host.
     *
     * @var StubHost
     */
    public static $Host;
    /**
     * The database.
     *
     * @var StubDb
     */
    public static $DB;
    /**
     * How many tokens were minted.
     *
     * @var int
     */
    public static $minted = 0;
    /**
     * How many times the loaded host was saved.
     *
     * @var int
     */
    public static $saves = 0;
    /**
     * Loads nothing: the host is already set.
     *
     * @return void
     */
    public static function getHostItem()
    {
    }
    /**
     * Allows every caller.
     *
     * @return bool
     */
    public static function hostKeySourceAllowed()
    {
        return true;
    }
    /**
     * Mints a different token each call.
     *
     * @return string
     */
    public static function createSecToken()
    {
        return 'minted-token-' . (++self::$minted);
    }
}

/**
 * Stand-in for filter_input: the CLI has no REMOTE_ADDR.
 *
 * @return string
 */
function filter_input()
{
    return '192.0.2.10';
}

$t = new \FogChecks();

$source = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/status/hostgetkey.php'
);
$start = strpos($source, "    FOGCore::getHostItem(false, true);");
$end = strpos($source, '} catch (\\Exception $e) {');
$t->check(
    'the token block is found by its anchors',
    false !== $start && false !== $end && $start < $end
);
$block = substr($source, (int)$start, (int)$end - (int)$start);

/**
 * Runs the script's block for the stub host and returns what it echoes.
 *
 * @param string $block the block's source
 *
 * @return string the message the script would echo
 */
function run($block)
{
    // The host object is loaded before the update and never refreshed. Its
    // lock is always 0 here: only the row decides.
    FOGCore::$Host = new StubHost(['id' => 7, 'tokenlock' => false]);
    // The script runs at file scope, where $DB is the global PDODB.
    $GLOBALS['DB'] = FOGCore::$DB;
    try {
        eval("namespace HostgetkeyTest;\n" . $block);
    } catch (\Exception $e) {
        return $e->getMessage();
    }
    return '';
}

FOGCore::$DB = new StubDb();
$reply = run($block);
$row = FOGCore::$DB->row;
$t->check(
    'a host with no lock is sent the token just minted',
    'minted-token-1' === $reply
);
$t->check(
    'the stored key is the sha256 of the token, not the token',
    hash('sha256', 'minted-token-1') === $row['key']
        && 'minted-token-1' !== $row['key']
);
$t->check(
    'the lock is set and the issue time is now',
    1 === $row['lock'] && abs(time() - $row['time']) < 5
);

$reply = run($block);
$t->check(
    'a second call within the lifetime is refused',
    false !== strpos($reply, 'in use')
        && hash('sha256', 'minted-token-1') === FOGCore::$DB->row['key']
);

$t->check(
    'a token minted for a refused call is never stored',
    hash('sha256', 'minted-token-2') !== FOGCore::$DB->row['key']
);

$t->check('the lifetime constant was read from FOGCore', TTL > 0);
FOGCore::$DB->row['time'] = time() - (TTL - 1);
$reply = run($block);
$t->check(
    'a lock one second inside the lifetime is still refused',
    false !== strpos($reply, 'in use')
);

$oldHash = FOGCore::$DB->row['key'];
FOGCore::$DB->row['time'] = time() - (TTL + 1);
$reply = run($block);
$t->check(
    'a lock one second past the lifetime is taken over with a NEW token',
    0 === strpos($reply, 'minted-token-')
        && 'minted-token-1' !== $reply
        && hash('sha256', $reply) === FOGCore::$DB->row['key']
);
$t->check(
    'the old token no longer matches the stored key',
    $oldHash !== FOGCore::$DB->row['key']
        && hash('sha256', 'minted-token-1') !== FOGCore::$DB->row['key']
);

FOGCore::$DB->row['time'] = time() - TTL;
$reply = run($block);
$t->check(
    'a lock exactly as old as the lifetime is abandoned',
    0 === strpos($reply, 'minted-token-')
);

// One winner: the host object is stale (lock 0) for both callers, so only
// the conditional UPDATE can tell them apart.
FOGCore::$DB = new StubDb();
$first = run($block);
$second = run($block);
$t->check(
    'two callers on a stale host object: exactly one gets a token',
    0 === strpos($first, 'minted-token-')
        && false !== strpos($second, 'in use')
        && hash('sha256', $first) === FOGCore::$DB->row['key']
);

$t->check(
    'nothing saved the loaded host after the claim',
    0 === FOGCore::$saves
);

$sql = implode(' ', FOGCore::$DB->sql);
$t->check(
    'the UPDATE is conditional on no lock or an expired lock, by host id',
    false !== strpos($sql, '`hostID`=:id')
        && false !== strpos($sql, '`hostInfoLock`=0')
        && false !== strpos($sql, 'OR `hostInfoLockTime`<=:expired')
);

$t->finish();
