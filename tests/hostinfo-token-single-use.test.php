<?php
/**
 * service/hostinfo.php accepts a host token once, and only while it is young.
 *
 * The stored key is the sha256 of the token that status/hostgetkey.php
 * issued. A matching token is CONSUMED at once, before the reply is built:
 * the key becomes a random value, the lock and the lock time are cleared, in
 * one UPDATE that must change exactly one row. A reply that then throws
 * still leaves no usable token. A wrong token, an expired token, or a token
 * with no lock changes nothing.
 *
 * The script runs top to bottom with no function to call, so this test runs
 * the script's own token block, cut out by its anchors, against stubs. The
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

namespace HostinfoTokenTest;

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
define('HostinfoTokenTest\TTL', (int)($ttlMatch[1] ?? 0));

/**
 * Stand-in host: a field map loaded before the request, never refreshed.
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
        return $this->fields[$key] ?? '';
    }
    /**
     * Sets a field in memory only.
     *
     * @param string $key the field
     * @param mixed  $val the value
     *
     * @return StubHost
     */
    public function set($key, $val)
    {
        $this->fields[$key] = $val;
        return $this;
    }
    /**
     * Writes the token fields back to the row, the way the real save()
     * writes every field of the loaded object.
     *
     * @return StubHost
     */
    public function save()
    {
        $r = &FOGCore::$DB->row;
        $r['key'] = $this->fields['token'];
        $r['lock'] = (int)(bool)$this->fields['tokenlock'];
        $r['time'] = (int)$this->fields['tokenlocktime'];
        return $this;
    }
}

/**
 * Stand-in task: a field map, as the real one holds its host.
 */
class StubTask
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
        return $this->fields[$key] ?? null;
    }
    /**
     * Sets a field.
     *
     * @param string $key the field
     * @param mixed  $val the value
     *
     * @return StubTask
     */
    public function set($key, $val)
    {
        $this->fields[$key] = $val;
        return $this;
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
    public $row = [];
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
     * Applies the consume UPDATE the way the server would.
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
        // Each condition applies only when the SQL carries it.
        if ($params[':id'] === $r['id']
            && (false === strpos($sql, '`hostInfoKey`=:hash')
                || $params[':hash'] === $r['key'])
            && (false === strpos($sql, '`hostInfoLock`=1')
                || 1 === $r['lock'])
        ) {
            $r['key'] = $params[':new'];
            $r['lock'] = 0;
            $r['time'] = 0;
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
     * The host as loaded.
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
     * Mints a recognizable random stand-in.
     *
     * @return string
     */
    public static function createSecToken()
    {
        return 'random-reset-value';
    }
}

/**
 * The token the caller posts.
 *
 * @var string
 */
$posted = '';

/**
 * Stand-in for filter_input: the posted token.
 *
 * @param int    $type the input type
 * @param string $name the variable
 *
 * @return string|null
 */
function filter_input($type, $name)
{
    global $posted;
    return INPUT_POST === $type ? $posted : null;
}

$t = new \FogChecks();

$source = (string)file_get_contents(
    dirname(__DIR__) . '/packages/web/service/hostinfo.php'
);
$start = strpos($source, "    \$hosttoken = filter_input(INPUT_POST, 'hosttoken');");
$end = strpos($source, '    $TaskType = $Task->getTaskType();');
$t->check(
    'the token block is found by its anchors',
    false !== $start && false !== $end && $start < $end
);
$block = substr($source, (int)$start, (int)$end - (int)$start);

/**
 * Runs the script's token block for the stub host.
 *
 * @param string $block  the block's source
 * @param string $token  the token the caller posts
 * @param array  $row    the hosts row as stored
 * @param array  $loaded the host fields as loaded, when they differ
 *
 * @return string the message the script would echo, '' when it goes on
 */
function run($block, $token, array $row, array $loaded = [])
{
    global $posted;
    $posted = $token;
    FOGCore::$DB = new StubDb();
    FOGCore::$DB->row = $row;
    FOGCore::$Host = new StubHost(
        $loaded + [
            'id' => $row['id'],
            'token' => $row['key'],
            'tokenlock' => $row['lock'],
            'tokenlocktime' => $row['time'],
        ]
    );
    // The script runs at file scope, where $DB is the global PDODB.
    $GLOBALS['DB'] = FOGCore::$DB;
    // The task was loaded before the consume, with its own Host built from
    // the old row.
    $Task = new StubTask(['host' => new StubHost(FOGCore::$Host->fields)]);
    $GLOBALS['lastTask'] = $Task;
    try {
        eval("namespace HostinfoTokenTest;\n" . $block);
    } catch (\Exception $e) {
        return $e->getMessage();
    }
    return '';
}

$issued = ['id' => 7, 'lock' => 1, 'time' => time() - 5,
    'key' => hash('sha256', 'good-token')];

$reply = run($block, 'good-token', $issued);
$row = FOGCore::$DB->row;
$t->check('a matching young token goes on to the reply', '' === $reply);
$t->check(
    'the token is consumed before the reply is built',
    'random-reset-value' === $row['key']
        && 0 === $row['lock']
        && 0 === $row['time']
);

$reply = run($block, 'good-token', $row);
$t->check(
    'the same token a second time is refused',
    false !== strpos($reply, 'Invalid token passed for host')
        || false !== strpos($reply, 'Have not locked')
);

$reply = run($block, 'wrong-token', $issued);
$t->check(
    'a wrong token is refused',
    false !== strpos($reply, 'Invalid token passed for host')
);
$t->check(
    'a wrong token changes nothing: the lock holds and the key stays',
    $issued === FOGCore::$DB->row
);

$reply = run($block, hash('sha256', 'good-token'), $issued);
$t->check(
    'the stored hash itself is not accepted as the token',
    false !== strpos($reply, 'Invalid token passed for host')
);

$t->check('the lifetime constant was read from FOGCore', TTL > 0);
$old = ['time' => time() - (TTL + 1)] + $issued;
$reply = run($block, 'good-token', $old);
$t->check(
    'an expired token is refused with the same message',
    false !== strpos($reply, 'Invalid token passed for host')
);
$t->check('an expired token changes nothing', $old === FOGCore::$DB->row);

$reply = run($block, 'good-token', ['time' => time() - (TTL - 1)] + $issued);
$t->check(
    'a token one second inside the lifetime is accepted',
    '' === $reply
);

$reply = run($block, 'good-token', ['lock' => 0] + $issued);
$t->check(
    'a token with no lock is refused',
    false !== strpos($reply, 'Have not locked')
);

// Two requests with the same token: the host object is loaded before either
// consumes, so only the UPDATE's affected-row count can tell them apart.
$reply = run(
    $block,
    'good-token',
    ['lock' => 0, 'key' => 'random-reset-value'] + $issued,
    ['token' => $issued['key'], 'tokenlock' => 1, 'tokenlocktime' => $issued['time']]
);
$t->check(
    'a request that loaded the host before another consumed gets no reply',
    false !== strpos($reply, 'Invalid token passed for host')
);

// A listener saves the loaded host after the consume. hostinfo.php fires
// BOOT_ITEM_NEW_SETTINGS (SubnetGroup saves there) and HOST_INFO_EXPOSE. The
// row must keep the consumed values, so a replay of the same token inside the
// lifetime is still refused.
$reply = run($block, 'good-token', $issued);
FOGCore::$Host->save();
$t->check(
    'a save of the loaded host after the consume keeps the consumed row',
    '' === $reply
        && 'random-reset-value' === FOGCore::$DB->row['key']
        && 0 === FOGCore::$DB->row['lock']
        && 0 === FOGCore::$DB->row['time']
);
$saved = FOGCore::$DB->row;
$reply = run($block, 'good-token', $saved);
$t->check(
    'a replay of the token after that save is refused',
    '' !== $reply
);

run($block, 'good-token', $issued);
$sql = implode(' ', FOGCore::$DB->sql);
$t->check(
    'the consume UPDATE is conditional on the host, the hash and the lock',
    false !== strpos($sql, '`hostID`=:id')
        && false !== strpos($sql, '`hostInfoKey`=:hash')
        && false !== strpos($sql, '`hostInfoLock`=1')
        && false !== strpos($sql, '`hostInfoLockTime`=0')
);

// A hook reaches the host through $Host->get('task')->getHost(), and through
// a clone of the task. Both must see the consumed host, not the stale one the
// task was loaded with.
run($block, 'good-token', $issued);
$taskHost = $GLOBALS['lastTask']->get('host');
$t->check(
    'the task carries the same host object, with the consumed values',
    $taskHost === FOGCore::$Host
        && 'random-reset-value' === $taskHost->get('token')
        && !$taskHost->get('tokenlock')
        && 0 === $taskHost->get('tokenlocktime')
);
$setsAt = strpos($source, "->set('tokenlocktime', 0);");
$t->check(
    'the host and task are cloned only after the in-memory sets',
    false !== $setsAt
        && false !== strpos($source, '$dryHost = clone FOGCore::$Host;')
        && $setsAt < strpos($source, '$dryHost = clone FOGCore::$Host;')
        && $setsAt < strpos($source, "clone \$Task")
);

$t->check(
    'the consume comes before the reply is built',
    strpos($source, '`hostInfoLockTime`=0') < strpos($source, '$repFields = [')
);
// The consume block ends at $end. Nothing after it may write the lock, by
// set(), an array key or SQL: a late unlock or re-lock would undo the consume.
$after = substr($source, (int)$end);
$t->check(
    'nothing after the consume block writes tokenlock, tokenlocktime or hostInfoLock',
    1 !== preg_match('/tokenlock|hostInfoLock/i', $after)
);

$t->finish();
