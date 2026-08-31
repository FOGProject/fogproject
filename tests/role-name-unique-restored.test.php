<?php
/**
 * Schema step 401 restores `roles`.`rName`'s UNIQUE index without deleting.
 *
 * The manifest declares the index; a server whose `roles` table came from the
 * 1.5 accesscontrol plugin does not have it, because that plugin never
 * declared one and native RBAC adopted the table rather than rebuilding it.
 * SchemaReconciler::shapeDrift() reports the gap; nothing repaired it.
 *
 * What is pinned here is the SAFETY of the repair, not merely that it runs.
 * Six tables reference `roles`.`rID` with ON DELETE CASCADE -- rolePermissions,
 * roleUserAssoc, roleUserGroupAssoc, siteRoleGrants, and the plugins'
 * ldapGroupRoleAssoc and oidcGroupRoleAssoc -- so deleting a duplicate role
 * would silently strip that role's permissions, assignments, site grants and
 * directory mappings. That is an access-control change, and this step must
 * never make one. Hence: rename, never delete, and the first holder of a name
 * keeps it so that a by-name lookup still resolves to the row it does today.
 *
 * Usage: php tests/role-name-unique-restored.test.php
 * Exit status 0 = pass, 1 = fail.
 *
 * PHP version 7.4+
 *
 * @category Tests
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once __DIR__ . '/lib/fog-schema-collector.php';
require_once __DIR__ . '/lib/fog-test-harness.php';

$root = dirname(__DIR__);
$schemaFile = $root . '/packages/web/commons/schema.php';

$fogSchema = 0;
if (preg_match(
    "/define\('FOG_SCHEMA',\s*(\d+)\)/",
    (string)file_get_contents($root . '/packages/web/src/Base/System.php'),
    $m
)) {
    $fogSchema = (int)$m[1];
}

$steps = fogCollectSchemaSteps($schemaFile, 'fogtest', $fogSchema);
$t = new FogChecks();

// The step this test is about, pinned by number.
define('ROLE_INDEX_STEP', 401);

/**
 * A `self::$DB` standing in for a server holding a `roles` table.
 */
class RoleIndexDB extends SchemaStubDB
{
    /** @var bool whether the server has a `roles` table */
    public $hasRoles = true;

    /** @var bool whether `rName` already carries a UNIQUE index */
    public $hasIndex = false;

    /** @var array rows as ['id' => int, 'name' => string] */
    public $roles = [];

    /** @var array every statement issued, in order */
    public $log = [];

    /** @var mixed what the next get() returns */
    private $_answer = null;

    public function query($query = null, ...$rest)
    {
        $sql = (string)$query;
        $this->log[] = $sql;
        $params = [];
        foreach ($rest as $arg) {
            if (is_array($arg) && count($arg)) {
                $params = $arg;
            }
        }

        if (false !== strpos($sql, 'information_schema`.`TABLES')) {
            $this->_answer = ['n' => $this->hasRoles ? 1 : 0];
            return $this;
        }
        if (false !== strpos($sql, 'STATISTICS')) {
            $this->_answer = $this->hasIndex
                ? [['i' => 'rName']]
                : [['i' => 'PRIMARY']];
            return $this;
        }
        if (0 === strpos(ltrim($sql), 'UPDATE `roles`')) {
            // Apply it, so the collision re-check further down sees the
            // renamed data rather than the original. A fake that ignored the
            // write would let the "still colliding" branch pass for the
            // wrong reason.
            foreach ($this->roles as $i => $row) {
                if ((int)$row['id'] === (int)($params[':id'] ?? 0)) {
                    $this->roles[$i]['name'] = (string)($params[':n'] ?? '');
                }
            }
            $this->_answer = null;
            return $this;
        }
        // Order matters: BOTH statements end in `HAVING COUNT(*) > 1) `d``,
        // so the row-returning one must be recognized first or the step is
        // handed a count where it expects rows and silently renames nothing.
        if (false !== strpos($sql, 'FROM `roles` `r`')) {
            // Every row but the lowest id of each repeated name. Compared
            // case-insensitively, because rName is utf8mb3_general_ci and
            // that is what the real index enforces.
            $out = [];
            foreach ($this->_dupeNames() as $key => $ids) {
                sort($ids);
                array_shift($ids);
                foreach ($ids as $id) {
                    $out[] = ['id' => $id, 'name' => $this->_nameOf($id)];
                }
            }
            usort(
                $out,
                static function ($a, $b) {
                    return $a['id'] <=> $b['id'];
                }
            );
            $this->_answer = $out;
            return $this;
        }
        if (false !== strpos($sql, 'SELECT COUNT(*) AS `n` FROM (SELECT')) {
            // The post-rename collision count.
            $this->_answer = ['n' => count($this->_dupeNames())];
            return $this;
        }
        $this->_answer = null;
        return $this;
    }

    /** @return array lowercased name => list of ids, repeats only */
    private function _dupeNames()
    {
        $by = [];
        foreach ($this->roles as $row) {
            $by[strtolower((string)$row['name'])][] = (int)$row['id'];
        }
        return array_filter(
            $by,
            static function ($ids) {
                return count($ids) > 1;
            }
        );
    }

    /** @param int $id role id @return string */
    private function _nameOf($id)
    {
        foreach ($this->roles as $row) {
            if ((int)$row['id'] === (int)$id) {
                return (string)$row['name'];
            }
        }
        return '';
    }

    public function fetch($what = null, ...$rest)
    {
        return $this;
    }

    public function get($key = null)
    {
        return $this->_answer;
    }
}

/**
 * Runs step 401 against a fake server.
 *
 * @param array $roles   rows as ['id' => int, 'name' => string]
 * @param bool  $hasIdx  whether the UNIQUE index already exists
 * @param bool  $hasTbl  whether the `roles` table exists at all
 *
 * @return RoleIndexDB the server, after the step
 */
function runRoleStep(array $roles, $hasIdx = false, $hasTbl = true)
{
    global $steps, $fogSchema;
    $db = new RoleIndexDB();
    $db->roles = $roles;
    $db->hasIndex = $hasIdx;
    $db->hasRoles = $hasTbl;
    $prev = SchemaCollector::$DB;
    SchemaCollector::$DB = $db;
    // Indexed by the step's own number rather than by $fogSchema, which is
    // only the same thing until the next step lands.
    $step = $steps[ROLE_INDEX_STEP - 1];
    foreach ((array)$step as $update) {
        if (is_callable($update)) {
            $update();
        }
    }
    SchemaCollector::$DB = $prev;
    return $db;
}

/** @param array $log statements @return bool whether the index was added */
function addedIndex(array $log)
{
    foreach ($log as $sql) {
        if (false !== stripos($sql, 'ADD UNIQUE KEY `rName`')) {
            return true;
        }
    }
    return false;
}

/** @param array $log statements @return bool whether anything was deleted */
function deletedAnything(array $log)
{
    foreach ($log as $sql) {
        if (preg_match('/^\s*(DELETE|TRUNCATE|DROP)\b/i', $sql)) {
            return true;
        }
    }
    return false;
}

// --- the clean case --------------------------------------------------------
$db = runRoleStep([
    ['id' => 1, 'name' => 'Administrators'],
    ['id' => 2, 'name' => 'Operators'],
]);
$t->check('the index is added when no name repeats', addedIndex($db->log));
$t->check(
    'nothing is renamed when no name repeats',
    'Administrators' === $db->roles[0]['name']
        && 'Operators' === $db->roles[1]['name']
);

// --- already correct -------------------------------------------------------
$db = runRoleStep(
    [['id' => 1, 'name' => 'Administrators']],
    true
);
$t->check(
    'a server that already has the index is left alone',
    !addedIndex($db->log)
);

// --- no roles table --------------------------------------------------------
$db = runRoleStep([], false, false);
$t->check(
    'a server with no roles table is left alone',
    !addedIndex($db->log)
);

// --- duplicates: renamed, never deleted ------------------------------------
$db = runRoleStep([
    ['id' => 3, 'name' => 'Techs'],
    ['id' => 7, 'name' => 'Techs'],
    ['id' => 9, 'name' => 'Techs'],
]);
$t->check('the index is added once duplicates are resolved', addedIndex($db->log));
$t->check(
    'NOTHING is deleted -- six tables CASCADE off roles.rID',
    !deletedAnything($db->log)
);
$t->check(
    'every role still exists',
    count($db->roles) === 3
);
$t->check(
    'the FIRST holder keeps the name, so a by-name lookup is unchanged',
    'Techs' === $db->roles[0]['name']
);
$t->check(
    'each later duplicate gets a distinct name',
    $db->roles[1]['name'] === 'Techs (duplicate 7)'
        && $db->roles[2]['name'] === 'Techs (duplicate 9)'
);

// Case-variant duplicates ('Techs' vs 'techs') are NOT checked here, on
// purpose. rName is utf8mb3_general_ci, so the UNIQUE index treats them as the
// same value and the step's duplicate search has to agree -- but this stub
// computes duplicates in PHP, so it would be testing its own strtolower()
// rather than the SQL. Proved instead against a real server, where the
// collation is applied by the thing that enforces it:
// tests/role-name-unique-on-a-real-server.test.php.

// --- a name too long to carry a suffix -------------------------------------
$long = str_repeat('r', 255);
$db = runRoleStep([
    ['id' => 1, 'name' => $long],
    ['id' => 2, 'name' => $long],
]);
$t->check(
    'a 255-char name is truncated to make room for the suffix',
    strlen($db->roles[1]['name']) <= 255
        && $db->roles[1]['name'] !== $long
);
$t->check('the index is still added after truncation', addedIndex($db->log));

// --- it never aborts the upgrade -------------------------------------------
// Step 332's precedent: a schema step that cannot finish its job logs and
// returns true rather than stranding the admin on ?node=schema over data that
// is entirely intact. Here the rename lands on a name already in use, so a
// collision survives and the index must be SKIPPED rather than attempted.
$db = runRoleStep([
    ['id' => 1, 'name' => 'Techs'],
    ['id' => 2, 'name' => 'Techs'],
    ['id' => 3, 'name' => 'Techs (duplicate 2)'],
]);
$t->check(
    'the index is skipped when a collision survives the rename',
    !addedIndex($db->log)
);
$t->check(
    'and still nothing is deleted',
    !deletedAnything($db->log) && count($db->roles) === 3
);

$t->finish();
