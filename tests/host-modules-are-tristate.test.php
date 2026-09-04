<?php
/**
 * A host can say a module is OFF, and saying it must stick.
 *
 * ADR 0038 gives a module three states where snapins and printers have two:
 *
 *   host row, msState = 1   the host says ON
 *   host row, msState = 0   the host says OFF, and beats every group grant
 *   no row at all           unstated -- a group grant turns it on, nothing
 *                           else does
 *
 * Before this, the Modules tab was a plain association grid: ticking wrote a
 * row, unticking DELETED it. Under the rule above that is not "off", it is
 * "unstated" -- so a host in a group that grants the module would have it
 * switched straight back on, which is the opposite of what the click meant.
 * A host could not express OFF at all.
 *
 * WHAT THIS DRIVES:
 *
 *   1. setModuleState() writes msState directly and is the tab's ONLY
 *      writer. Routing through addModule()/removeModule() cannot work:
 *      those go through the `modules` array and assocSetter, which can
 *      only insert a row or delete one and has no way to write a row that
 *      exists and means OFF.
 *   2. It upserts. Flipping ON to OFF is an update of an existing row, not
 *      an insert -- an insert would hit UNIQUE (msHostID, msModuleID).
 *   3. Clearing removes the row, because "unstated" IS the absent row.
 *   4. Only positive integer ids reach the database, and an empty set or an
 *      unsaved host runs nothing at all.
 *   5. An unknown state string is refused, not guessed at. A state this
 *      endpoint does not recognize must never silently become one it does,
 *      and the dangerous direction is any wrong answer becoming ON.
 *   6. save() protects existing OFF rows from assocSetter. It deletes
 *      ($cur - $items) where $cur is every row and $items is get('modules'),
 *      which loadModules() filters to state 1 -- so without the union an OFF
 *      row is on one side of that diff only, and every save touching modules
 *      would drop it.
 *   7. The grid ships the raw state, and the browser distinguishes NULL from
 *      0. Reading a missing row as "off" would show the host asserting
 *      something it has not said.
 *
 * Usage: php tests/host-modules-are-tristate.test.php
 * Exit status 0 = pass, 1 = fail.
 */

use FOG\Base\FOGCore;
use FOG\Items\Host;
use FOG\Items\User;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('host-modules-tristate');

$t = new FogChecks();
$db = FogTestHarness::fakeDb();
$root = dirname(__DIR__) . '/packages/web';

$admin = (new User())->set('id', 1)->set('name', 'fog');
foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
    FogTestHarness::setStatic($cls, 'FOGUser', $admin);
}
FogTestHarness::setStatic('Authorization', '_permCache', [1 => ['*']]);

/**
 * Runs setModuleState against a canned set of existing rows.
 *
 * @param FogFakeDb $db       the fake
 * @param array     $existing module ids the host already has a row for
 * @param array     $ids      the ids to write
 * @param int|null  $state    1, 0 or null
 * @param int       $hostID   the host
 *
 * @return array the statements issued
 */
function writeState($db, array $existing, $ids, $state, $hostID = 5)
{
    $db->log = [];
    $db->responder = function ($sql) use ($existing) {
        if (false !== strpos($sql, 'moduleStatusByHost')
            && 0 === stripos(ltrim($sql), 'SELECT')
        ) {
            $rows = [];
            foreach ($existing as $id) {
                $rows[] = ['msModuleID' => $id, 'msID' => $id];
            }
            return $rows;
        }
        return null;
    };
    (new Host())
        ->set('id', $hostID)
        ->setModuleState($ids, $state);
    $db->responder = null;

    return $db->log;
}

/**
 * Counts statements of one kind against moduleStatusByHost.
 *
 * Scoped to that table on purpose: a save() also writes a history row and
 * reads FOG_LOG_INFO dozens of times, and counting those would make this
 * assert something other than what it says.
 *
 * @param array  $log  statements
 * @param string $verb SQL leading keyword
 *
 * @return int
 */
function countVerb(array $log, $verb)
{
    $n = 0;
    foreach ($log as $sql) {
        $sql = (string)$sql;
        if (false === strpos($sql, 'moduleStatusByHost')) {
            continue;
        }
        if (0 === stripos(ltrim($sql), $verb)) {
            $n++;
        }
    }

    return $n;
}
/**
 * The statements that touched the module table.
 *
 * @param array $log statements
 *
 * @return array
 */
function moduleStatements(array $log)
{
    $out = [];
    foreach ($log as $sql) {
        if (false !== strpos((string)$sql, 'moduleStatusByHost')) {
            $out[] = (string)$sql;
        }
    }

    return $out;
}
/**
 * A method's body with its comments stripped.
 *
 * Needed because these assertions are about what the code DOES: a comment
 * saying "not addModule()" would otherwise fail a check for addModule().
 *
 * @param string $body source
 *
 * @return string
 */
function codeOnly($body)
{
    $body = preg_replace('#/\*.*?\*/#s', '', (string)$body);

    return (string)preg_replace('#//[^\n]*#', '', (string)$body);
}

// Invariant 2: the write is an UPSERT, so flipping an existing row and
// stating one for the first time are the same statement -- and neither
// needs a read first to tell which case it is.
$log = writeState($db, [7], [7], 0);
$t->check(
    'flipping an existing row to OFF is one write',
    1 === countVerb($log, 'INSERT')
);
$sql = implode(' ', moduleStatements($log));
$t->check(
    'and it is the upsert, which is what stops the unique key rejecting it',
    false !== stripos($sql, 'ON DUPLICATE KEY UPDATE')
);
$t->check(
    'the write carries msState',
    false !== stripos($sql, '`msState`=VALUES(`msState`)')
);
$t->check(
    'and it reads nothing first, because it does not need to',
    0 === countVerb($log, 'SELECT')
);

// A module with no row takes the identical path.
$log = writeState($db, [], [9], 1);
$t->check(
    'a module with no row is written the same way',
    1 === countVerb($log, 'INSERT')
);

// Two modules, one write each.
$log = writeState($db, [7], [7, 9], 1);
$t->check(
    'two modules are two writes',
    2 === countVerb($log, 'INSERT')
);

// Invariant 3.
$log = writeState($db, [7], [7], null);
$t->check(
    'clearing a state deletes the row, because unstated IS no row',
    countVerb($log, 'DELETE') === 1
);
$t->check(
    'clearing writes no state',
    0 === countVerb($log, 'UPDATE') && 0 === countVerb($log, 'INSERT')
);

// Invariant 4.
$t->check(
    'no usable id runs nothing',
    0 === count(moduleStatements(writeState($db, [7], ['nope', 0, -2], 1)))
);
$t->check(
    'an empty set runs nothing',
    0 === count(moduleStatements(writeState($db, [7], [], 1)))
);
$t->check(
    'an unsaved host runs nothing',
    0 === count(moduleStatements(writeState($db, [7], [9], 1, 0)))
);
$log = writeState($db, [], ['9; DROP TABLE `hosts`'], 1);
$t->check(
    'a non-numeric id cannot carry SQL through',
    false === strpos(implode(' ', $log), 'DROP TABLE')
);
// The delete path is where the id count is observable: deletemass binds one
// placeholder per id, so the statement says how many survived the cast.
// Counting writes cannot show this -- an invalid id fails validation and
// silently produces no statement at all, so a missing cast looks identical
// to a working one.
$log = moduleStatements(writeState($db, [7], ['nope', 7, -2, '7'], null));
$del = '';
foreach ($log as $sql) {
    if (0 === stripos(ltrim($sql), 'DELETE')) {
        $del = $sql;
    }
}
$t->check('the delete statement was found', '' !== $del);
$t->check(
    'exactly one id survives the cast and the dedupe',
    1 === substr_count($del, ':where_1_')
);

/**
 * The body of a method, read from the file it is declared in.
 *
 * @param string $class  class
 * @param string $method method
 *
 * @return string
 */
function bodyOf($class, $method)
{
    $r = new \ReflectionMethod($class, $method);
    $lines = (array)file($r->getFileName());

    return implode(
        '',
        array_slice(
            $lines,
            $r->getStartLine() - 1,
            $r->getEndLine() - $r->getStartLine() + 1
        )
    );
}

// Invariant 1: the tab has exactly one writer, and it is not assocSetter's.
$post = codeOnly(bodyOf('FOG\\Pages\\HostManagement', 'hostModulePost'));
$t->check(
    'the tab writes through setModuleState',
    2 < substr_count($post, 'setModuleState(')
);
$t->check(
    'the tab does not route modules through addModule/removeModule',
    false === strpos($post, "assocPost('addModule'")
    && false === strpos($post, 'addModule(')
    && false === strpos($post, 'removeModule(')
);
$t->check(
    'and still runs the CSRF gate assocPost used to run for it',
    false !== strpos($post, 'checkAuthAndCSRF()')
);
// The bulk buttons keep their existing wire shape, so no client change.
$t->check(
    'confirmadd still means ON',
    (bool)preg_match('/confirmadd.*?setModuleState\(.*?, 1\);/s', $post)
);
$t->check(
    'confirmdel still means unstated, not OFF',
    (bool)preg_match('/confirmdel.*?setModuleState\(.*?, null\);/s', $post)
);

// Invariant 5.
$t->check(
    'an unrecognized state throws rather than defaulting',
    false !== strpos($post, "throw new \\Exception(_('Unknown module state'))")
);
$states = [];
if (preg_match('/\$states = \[(.*?)\];/s', $post, $m)) {
    $states = $m[1];
}
$t->check(
    'the three states are named, and only three',
    false !== strpos($states, "'on' => 1")
    && false !== strpos($states, "'off' => 0")
    && false !== strpos($states, "'unset' => null")
    && 3 === substr_count($states, '=>')
);

// Invariant 6, as a position: the union has to run before the diff it
// protects against, not merely exist somewhere in save().
$save = codeOnly(bodyOf('FOG\\Items\\Host', 'save'));
$unionAt = strpos($save, "statedModuleIDs(\$this->get('id'), 0)");
$assocAt = strpos($save, "assocSetter('Module', 'module')");
$t->check('save() reads the OFF rows', false !== $unionAt);
$t->check('save() still runs the module assocSetter', false !== $assocAt);
$t->check(
    'the OFF rows are protected BEFORE the diff that would drop them',
    false !== $unionAt && false !== $assocAt && $unionAt < $assocAt
);
$t->check(
    'and it is gated on modules being dirty, so it costs an untouched save nothing',
    (bool)preg_match(
        '/isDirty\(\'modules\'\).*?statedModuleIDs/s',
        $save
    )
);

// statedModuleIDs itself, executed: the state filter is the whole point.
$db->log = [];
$db->responder = function () {
    return [];
};
FogTestHarness::callStatic('FOG\\Items\\Host', 'statedModuleIDs', [5, 0]);
$db->responder = null;
$t->check(
    'statedModuleIDs filters on the state it was asked for',
    false !== strpos(implode(' ', $db->log), 'msState')
);
$t->check(
    'an unsaved host reads nothing',
    [] === FogTestHarness::callStatic(
        'FOG\\Items\\Host',
        'statedModuleIDs',
        [0, 0]
    )
);

// Invariant 7, both halves.
$list = codeOnly(bodyOf('FOG\\Pages\\HostManagement', 'getModulesList'));
$t->check(
    'the grid ships the raw msState',
    (bool)preg_match("/'db' => 'msState',\s*'dt' => 'state'/", $list)
);
$t->check(
    'and keeps it out of the free-text search',
    (bool)preg_match("/'dt' => 'state',\s*'nosearch' => true/", $list)
);

$js = (string)file_get_contents(
    $root . '/management/js/fog/host/fog.host.edit.js'
);
$render = '';
if (preg_match('/function moduleStateSelect\(row\) \{(.*?)\n {4}\}/s', $js, $m)) {
    $render = $m[1];
}
$t->check('the module state cell renderer was found', '' !== $render);
$t->check(
    'the cell is a select, not a checkbox',
    false !== strpos($render, '<select')
    && false === strpos($render, 'type="checkbox"')
);
$t->check(
    'it offers exactly the three states',
    false !== strpos($render, "['on', 'On']")
    && false !== strpos($render, "['off', 'Off']")
    && false !== strpos($render, "['unset', 'Not set']")
);
$t->check(
    'a missing row reads as unset, and == null does not catch 0',
    false !== strpos($render, "row.state == null")
    && false !== strpos($render, "'unset'")
);
$t->check(
    'the module id is escaped into the markup',
    false !== strpos($render, '$.escapeHtml(String(row.id))')
);
$t->check(
    'the tab commits its own cell rather than leaving a dead checkbox',
    false !== strpos($js, 'confirmmodulestate')
    && false !== strpos($js, "off('change.moduleState')")
);
$t->check(
    'the column says State, not Associated',
    false !== strpos(
        bodyOf('FOG\\Pages\\HostManagement', 'hostModules'),
        "_('State')"
    )
);

$t->finish();
