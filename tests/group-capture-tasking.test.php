<?php
/**
 * A capture through Group::createImagePackage() must write a task row.
 *
 * Inside the method's imaging branch there were only multicast and deploy
 * arms. A capture matched neither, fell out the bottom without inserting
 * anything, and the caller reported success over an empty tasks table
 * (#1677). On this branch that is reachable from POST /group/{id}/task,
 * which accepts any task type.
 *
 * Drives the real method against a fake database: every SELECT it makes is
 * answered from a fixture keyed on the table name, and the INSERT into
 * `tasks` is what the assertions read. The storage node's online probe is
 * stubbed so no socket is opened.
 *
 * Usage: php tests/group-capture-tasking.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/scope-harness.php';

$root = dirname(__DIR__);
scopeHarnessBoot($root . '/packages/web');

/**
 * Stands in for PDODB. Answers query() from $responder.
 */
class CaptureFakeDb
{
    public $error = false;
    public $log = array();
    public $responder;
    private $_r = array();

    public function query($sql, $a = array(), $p = array())
    {
        $this->log[] = $sql;
        $this->_r = (array)call_user_func($this->responder, $sql, $p);
        return $this;
    }
    public function fetch($m = null, $t = '', $p = array())
    {
        return $this;
    }
    public function get($f = '')
    {
        // PDODB::get(), verbatim in shape: a key present on the result
        // itself (a single loaded row) answers with that value; otherwise
        // the key is collected across the rows.
        if (!$f) {
            return $this->_r;
        }
        $out = array();
        foreach ((array)$f as $key) {
            $key = trim($key);
            if (array_key_exists($key, $this->_r)) {
                return $this->_r[$key];
            }
            foreach ($this->_r as $row) {
                if (is_array($row) && array_key_exists($key, $row)) {
                    $out[] = $row[$key];
                }
            }
        }
        return count($out) ? $out : $this->_r;
    }
    public function insertId()
    {
        return 1;
    }
    public function affectedRows()
    {
        return 1;
    }
    public function sqlerror()
    {
        return '';
    }
    public function escape($v)
    {
        return "'" . str_replace("'", "''", (string)$v) . "'";
    }
    public function dbName()
    {
        return 'fogtest';
    }
    public function close()
    {
        return $this;
    }
}

/**
 * Stands in for FOGURLRequests: the master node lookup asks whether the node
 * is reachable, and the answer here is always yes.
 */
class AlwaysOnline
{
    public function isAvailable($ip, $timeout = 1, $port = -1)
    {
        return array(true);
    }
}

$db = new CaptureFakeDb();
$set = function ($class, $prop, $value) {
    $p = new \ReflectionProperty($class, $prop);
    $p->setAccessible(true);
    $p->setValue(null, $value);
};
$set('FOGBase', 'DB', $db);
$set('DatabaseManager', 'DB', $db);
$set('FOGBase', 'FOGURLRequests', new AlwaysOnline());
// HookManager saves a hookevent row for any event it has not seen, and that
// save writes history stamped with the acting user. An anonymous one is
// enough; the fixture answers the INSERT with nothing.
$set('FOGBase', 'FOGUser', FOGCore::getClass('User'));

/**
 * One database row for $class: every column the model declares, '' unless
 * $values names it.
 */
function row($class, array $values)
{
    $vars = Route::getClass($class, '', true);
    $row = array();
    foreach ($vars['databaseFields'] as $column) {
        $row[$column] = '';
    }
    return array_merge($row, $values);
}

$hostRows = array();
foreach (array(1, 2) as $hid) {
    $hostRows[] = row('Host', array('hostID' => $hid, 'hostName' => "host$hid",
        'hostImage' => 7, 'hostPending' => 0));
}
$fixture = array(
    'hosts' => $hostRows,
    'images' => array(row('Image', array('imageID' => 7, 'imageName' => 'win',
        'imagePath' => 'win', 'imageEnabled' => 1, 'imageTypeID' => 1,
        'imagePartitionTypeID' => 1, 'imageOSID' => 9, 'imageFormat' => 0,
        'imageProtect' => 0,
        'imageTypeName' => 'Single Disk', 'imageTypeValue' => 1,
        'imagePartitionTypeName' => 'Everything', 'imagePartitionTypeValue' => 1))),
    'imageGroupAssoc' => array(array('igaID' => 1, 'igaImageID' => 7,
        'igaStorageGroupID' => 3, 'igaPrimary' => 1)),
    'nfsGroups' => array(row('StorageGroup', array('ngID' => 3, 'ngName' => 'default'))),
    'nfsGroupMembers' => array(row('StorageNode', array('ngmID' => 5,
        'ngmMemberName' => 'master', 'ngmGroupID' => 3, 'ngmIsMasterNode' => 1,
        'ngmIsEnabled' => 1, 'ngmHostname' => '10.0.0.1', 'ngmInterface' => 'eth0',
        'ngmRootPath' => '/images', 'ngmFTPPath' => '/images', 'ngmUser' => 'fog',
        'ngmPass' => 'x', 'ngmMaxClients' => 10))),
    'taskTypes' => array(
        row('TaskType', array('ttID' => 1, 'ttName' => 'Deploy', 'ttIcon' => 'd.png',
            'ttType' => 'down', 'ttIsAccess' => 'both')),
        row('TaskType', array('ttID' => 2, 'ttName' => 'Capture', 'ttIcon' => 'c.png',
            'ttType' => 'up', 'ttIsAccess' => 'host')),
    ),
);
$inserts = array();
$db->responder = function ($sql, $params) use ($fixture, &$inserts) {
    if (preg_match('/^\s*INSERT INTO `(\w+)`/i', $sql, $m)) {
        $inserts[] = array('table' => $m[1], 'params' => $params);
        return array();
    }
    if (false !== stripos($sql, 'information_schema')) {
        return array();
    }
    if (!preg_match('/FROM `(\w+)`/i', $sql, $m)) {
        return array();
    }
    $table = $m[1];
    $rows = isset($fixture[$table]) ? $fixture[$table] : array();
    if ('tasks' === $table) {
        $rows = array();      // nothing queued or in progress
    }
    // Narrow by an `xID` IN (...) list in the WHERE. The list is bound --
    // IN (:id_0,:id_1) -- so each entry is resolved through the parameters.
    if (preg_match('/`(\w+ID)` IN \(([^)]*)\)/', $sql, $in)) {
        $ids = array_map(function ($v) use ($params) {
            $v = trim($v, "' ");
            if (':' === substr($v, 0, 1)) {
                $bare = substr($v, 1);
                $v = isset($params[$v]) ? $params[$v] : (isset($params[$bare]) ? $params[$bare] : $v);
            }
            return (string)$v;
        }, explode(',', $in[2]));
        $rows = array_values(array_filter($rows, function ($r) use ($in, $ids) {
            return in_array((string)(isset($r[$in[1]]) ? $r[$in[1]] : ''), $ids, true);
        }));
    }
    if (preg_match('/SELECT\s+COUNT/i', $sql)) {
        $n = count($rows);
        return array(array('total' => $n));
    }
    // A single-entity load reads fetch()->get() as ONE row.
    if (preg_match('/WHERE `(\w+)`=:id\b/', $sql, $m)) {
        $id = isset($params[':id']) ? $params[':id'] : (isset($params['id']) ? $params['id'] : null);
        foreach ($rows as $row) {
            if ((string)(isset($row[$m[1]]) ? $row[$m[1]] : '') === (string)$id) {
                return $row;
            }
        }
        return array();
    }
    // A LEFT JOINed table's row merged in, as a real join would.
    if (preg_match_all('/JOIN `(\w+)`/', $sql, $j)) {
        foreach ($j[1] as $joined) {
            foreach ($rows as &$r) {
                $r = array_merge(isset($fixture[$joined][0]) ? $fixture[$joined][0] : array(), $r);
            }
            unset($r);
        }
    }
    return $rows;
};

$fails = array();
$checks = 0;
function check($what, $ok, &$fails, &$checks)
{
    $checks++;
    if (!$ok) {
        $fails[] = $what;
    }
}

/**
 * Task rows (as the INSERT's bound values) written by one call, keyed by
 * the model's field name: hostID_0, typeID_0, hostID_1 ...
 */
function taskRows(array $inserts)
{
    $rows = array();
    foreach ($inserts as $ins) {
        if ('tasks' !== $ins['table']) {
            continue;
        }
        $byRow = array();
        foreach ($ins['params'] as $key => $val) {
            if (preg_match('/^:?(\w+?)_(\d+)$/', $key, $m)) {
                $byRow[(int)$m[2]][$m[1]] = $val;
            }
        }
        foreach ($byRow as $row) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Task a set of hosts through Group::createImagePackage(), the way
 * POST /group/{id}/task does. Returns [task rows written, message or ''].
 */
function taskSelection(array $hosts, $taskTypeID)
{
    global $inserts;
    $inserts = array();
    $error = '';
    $Group = FOGCore::getClass('Group')
        ->set('name', 'selection')
        ->set('hosts', $hosts);
    try {
        $Group->createImagePackage($taskTypeID, 'Capture Task', false, false, false, true, 'tester');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    return array(taskRows($inserts), $error);
}

$capture = new TaskType(2);
check('fixture: tasktype 2 is a capture', $capture->isValid() && $capture->isCapture() && !$capture->isDeploy(), $fails, $checks);

// 1. One host, capture: the bug. A row must land in `tasks`, for that host,
//    with the capture type, pointed at the master node.
list($rows, $error) = taskSelection(array(1), 2);
check('one-host capture throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('one-host capture inserts exactly one task row (got ' . count($rows) . ')', 1 === count($rows), $fails, $checks);
$row = isset($rows[0]) ? $rows[0] : array();
check('the row is for host 1', 1 === (int)(isset($row['hostID']) ? $row['hostID'] : 0), $fails, $checks);
check('the row is a capture (typeID 2)', 2 === (int)(isset($row['typeID']) ? $row['typeID'] : 0), $fails, $checks);
check('the row is on the master node (5)', 5 === (int)(isset($row['storagenodeID']) ? $row['storagenodeID'] : 0), $fails, $checks);
check('the row carries the host\'s image (7)', 7 === (int)(isset($row['imageID']) ? $row['imageID'] : 0), $fails, $checks);

// 2. Two hosts, capture: refused, nothing written.
list($rows, $error) = taskSelection(array(1, 2), 2);
check('two-host capture is refused (got: ' . $error . ')', false !== strpos($error, 'one host at a time'), $fails, $checks);
check('two-host capture writes no task row', 0 === count($rows), $fails, $checks);

// 3. Two hosts, deploy: unchanged -- one row per host.
list($rows, $error) = taskSelection(array(1, 2), 1);
check('two-host deploy throws nothing (got: ' . $error . ')', '' === $error, $fails, $checks);
check('two-host deploy inserts two rows (got ' . count($rows) . ')', 2 === count($rows), $fails, $checks);

if (count($fails)) {
    foreach ($fails as $f) {
        echo "FAIL: $f\n";
    }
    echo count($fails) . " of $checks checks failed\n";
    exit(1);
}
echo "PASS: $checks checks\n";
exit(0);
