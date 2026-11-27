<?php
/**
 * status/hostgetkey.php returns the token it just minted.
 *
 * When a host has no token yet, the script minted one, saved it, and then
 * echoed FOGCore::$Host->get('token'). The host object was loaded before the
 * save, so that read returned the old empty value and FOS got a blank reply.
 * FOS then could not open service/hostinfo.php. The first request must echo
 * the new token, and it must be the same value that was saved.
 *
 * The script runs top to bottom with no function to call, so this test runs
 * the script's own try block, cut out by its anchors, against stubs.
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
 * Stand-in for FOGCore.
 */
class FOGCore
{
    /**
     * The host.
     *
     * @var StubHost
     */
    public static $Host;
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
     * Mints a fixed token.
     *
     * @return string
     */
    public static function createSecToken()
    {
        return 'freshly-minted-token';
    }
}

/**
 * Stand-in for HostManager: records what it was asked to save.
 */
class HostManager
{
    /**
     * Every update call's third argument.
     *
     * @var array
     */
    public static $saved = [];
    /**
     * Records the update.
     *
     * @param array  $where the where
     * @param string $glue  the glue
     * @param array  $set   the new values
     *
     * @return void
     */
    public function update($where, $glue, $set)
    {
        self::$saved[] = $set;
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
 * Runs the script's block for one host and returns what it echoes.
 *
 * @param string $block  the block's source
 * @param array  $fields the host's fields as loaded
 *
 * @return string the message the script would echo
 */
function run($block, array $fields)
{
    FOGCore::$Host = new StubHost($fields);
    HostManager::$saved = [];
    try {
        eval("namespace HostgetkeyTest;\n" . $block);
    } catch (\Exception $e) {
        return $e->getMessage();
    }
    return '';
}

$reply = run($block, ['id' => 7, 'token' => '', 'tokenlock' => false]);
$t->check(
    'a host with no token is sent the token just minted',
    'freshly-minted-token' === $reply
);
$t->check(
    'the token sent is the token saved, with the lock set',
    1 === count(HostManager::$saved)
        && 'freshly-minted-token' === HostManager::$saved[0]['token']
        && true === HostManager::$saved[0]['tokenlock']
);

$reply = run($block, ['id' => 7, 'token' => 'existing', 'tokenlock' => false]);
$t->check(
    'a host with an unlocked token is sent that token and it is locked',
    'existing' === $reply
        && [['tokenlock' => true]] === HostManager::$saved
);

$reply = run($block, ['id' => 7, 'token' => 'existing', 'tokenlock' => true]);
$t->check(
    'a host whose token is locked is refused',
    false !== strpos($reply, 'in use') && [] === HostManager::$saved
);

$t->finish();
