<?php
/**
 * Queue Task on the host list: the three things that are not obvious.
 *
 * The list gained a Queue Task button that tasks whatever rows are ticked.
 * Most of it is markup and a fetch, and neither is worth a test. Three
 * pieces are, because each is load-bearing and each fails silently when
 * wrong.
 *
 * 1. THE PERMISSION IS IMPLICIT. Nothing registers `host.task` for the new
 *    subs. They are gated because Authorization::_subToAction() reads the
 *    `deploy` prefix off the sub name and answers `task` -- so the whole
 *    gate is a naming convention, and renaming the method to something
 *    tidier (`queueTask`, `taskSelected`) would silently reclassify it as
 *    `host.view` for the GET and `host.edit` for the POST. Both would be
 *    granted to people who must not be able to task a fleet. Executed
 *    through the same resolvePagePermission() the dispatcher calls.
 *
 * 2. THE SELECTION SIZE DECIDES WHICH TASK TYPES APPLY, and the rule comes
 *    from data rather than from a list of ids: `ttIsAccess` is 'group' for
 *    Multi-Cast (one session serves many hosts, so it means nothing for
 *    one), 'host' for Capture (an image comes off exactly one machine), and
 *    'both' for everything else. The script hides the entries that do not
 *    apply; assertSelectionTaskable() is what makes a request that skipped
 *    the UI get the same answer. Driven directly, since the browser half
 *    cannot be.
 *
 * 3. THE SELECTION IS CARRIED BY AN UNSAVED GROUP. That is not a shortcut
 *    around the host path -- it is the only path that gets Multi-Cast right,
 *    because one session has to cover the whole selection. It only works
 *    because Group::loadHosts() short circuits when there is no id; without
 *    that, set('hosts', ...) triggers a load whose filters are the
 *    empty-value shape that means "no filter" elsewhere in this codebase.
 *    Asserted as "no query was issued AND the ids survive", because either
 *    half alone would pass on a broken implementation.
 *
 * Usage: php tests/host-list-queue-task.test.php
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

use FOG\Auth\Authorization;
use FOG\Base\FOGCore;
use FOG\Items\Group;
use FOG\Pages\HostManagement;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('host-list-queue-task');
// resolvePagePermission() runs the real chain, which fires a hook, which
// asks the router for the known events. It needs a database to answer to.
FogTestHarness::fakeDb();

$t = new FogChecks();

// -------------------------------------------------------------------------
// 1. The permission the naming convention buys.
// -------------------------------------------------------------------------
$t->check(
    'GET ?sub=deployMulti resolves to host.task',
    'host.task' === Authorization::resolvePagePermission(
        'host',
        'deployMulti',
        false
    )
);
$t->check(
    'POST ?sub=deployMulti resolves to host.task',
    'host.task' === Authorization::resolvePagePermission(
        'host',
        'deployMulti',
        true
    )
);
// The control, and the reason this test exists: a name without the prefix
// lands somewhere far weaker. If someone renames the subs, this is what
// tells them what they just gave away.
$t->check(
    'a name without the deploy prefix would be host.view / host.edit',
    'host.view' === Authorization::resolvePagePermission(
        'host',
        'queueTask',
        false
    )
    && 'host.edit' === Authorization::resolvePagePermission(
        'host',
        'queueTask',
        true
    )
);

// The immediate power actions ride the same convention, and had to be named
// for it. `powerMulti` -- the obvious name, and the one this shipped as
// first -- resolves to host.EDIT on the POST, so anyone who could rename a
// host could shut down the fleet. `taskPowerMulti` carries the `task` prefix
// and lands on host.task with no change to Authorization at all.
$t->check(
    'POST ?sub=taskPowerMulti resolves to host.task',
    'host.task' === Authorization::resolvePagePermission(
        'host',
        'taskPowerMulti',
        true
    )
);
$t->check(
    'the name WITHOUT the task prefix would have been host.edit',
    'host.edit' === Authorization::resolvePagePermission(
        'host',
        'powerMulti',
        true
    )
);
// The endpoint has to exist under that exact name, or the gate is being
// asserted about a sub nothing answers. Both halves: FOGPageManager::render()
// resolves the bare name BEFORE appending the Post suffix, so an endpoint
// implemented only as <sub>Post is never reached and the request is answered
// by the host list -- 200, valid JSON, and a button that silently does
// nothing. See FOGPagePost::methodNotAllowed().
// Reflection rather than method_exists(): with a literal class name and a
// literal method name phpstan folds the call to a constant true, so the
// assertion would stop being one.
$endpoint = new \ReflectionClass(HostManagement::class);
$t->check(
    'both halves of the power endpoint exist under the gated name',
    $endpoint->hasMethod('taskPowerMulti')
    && $endpoint->hasMethod('taskPowerMultiPost')
);

// -------------------------------------------------------------------------
// 2. Which task types a selection of a given size can run.
// -------------------------------------------------------------------------
$page = new HostManagement();
$assert = new \ReflectionMethod(HostManagement::class, 'assertSelectionTaskable');
$assert->setAccessible(true);

/**
 * A stand-in task type. Only the two fields the rule reads.
 *
 * @param string $access ttIsAccess: both, host or group
 * @param string $name   the task type's name
 *
 * @return object
 */
$taskType = static function ($access, $name) {
    return new class($access, $name) {
        /** @var array */
        private $_d;

        /**
         * @param string $access ttIsAccess value
         * @param string $name   task type name
         */
        public function __construct($access, $name)
        {
            $this->_d = ['access' => $access, 'name' => $name];
        }

        /**
         * @param string $key the field to read
         *
         * @return string
         */
        public function get($key)
        {
            return $this->_d[$key] ?? '';
        }
    };
};

/**
 * Runs the rule and reports the refusal, or '' when it allowed the size.
 *
 * @param string $access ttIsAccess value
 * @param int    $count  how many hosts are selected
 *
 * @return string the exception message, or ''
 */
$refusal = static function ($access, $count) use ($page, $assert, $taskType) {
    try {
        $assert->invoke($page, $taskType($access, 'Test Type'), $count);
    } catch (\Exception $e) {
        return $e->getMessage();
    }

    return '';
};

$t->check(
    "a 'both' type runs on one host",
    '' === $refusal('both', 1)
);
$t->check(
    "a 'both' type runs on many hosts",
    '' === $refusal('both', 25)
);
$t->check(
    "Multi-Cast ('group') is refused for a single host",
    '' !== $refusal('group', 1)
);
$t->check(
    "Multi-Cast ('group') is allowed from two hosts up",
    '' === $refusal('group', 2)
);
$t->check(
    "Capture ('host') is allowed on exactly one host",
    '' === $refusal('host', 1)
);
$t->check(
    "Capture ('host') is refused for a selection",
    '' !== $refusal('host', 2)
);
// The message names the type, because a toast that just says "not allowed"
// against a menu of ten entries tells nobody which one was the problem.
$t->check(
    'the refusal names the task type',
    false !== strpos($refusal('group', 1), 'Test Type')
);

// -------------------------------------------------------------------------
// 3. The unsaved Group that carries the selection.
// -------------------------------------------------------------------------
$db = FogTestHarness::fakeDb();
$mark = count($db->log);

$Selection = new Group();
$Selection->set('name', '3 selected hosts');
$Selection->set('hosts', [4, 9, 17]);
$hosts = $Selection->get('hosts');

// Only the membership table matters. A settings read or the storage-epoch
// lookup can fire from anywhere in the boot chain and says nothing about
// whether the guard held; a `groupMembers` read is the guard failing.
$issued = array_filter(
    array_slice($db->log, $mark),
    static function ($sql) {
        return false !== strpos($sql, 'groupMembers');
    }
);

$t->check(
    'the ids set on an unsaved group are the ids it reports',
    [4, 9, 17] === $hosts
);
$t->check(
    'and it never went to groupMembers to find that out',
    count($issued) === 0
);

// The other half of the same guard: a group that DOES have an id must still
// load its members, or every real group tasking silently tasks nothing.
$db = FogTestHarness::fakeDb();
$db->responder = static function ($sql) use ($db) {
    $db->error = false;
    if (false !== strpos($sql, 'groupMembers')) {
        return [['gmHostID' => 7], ['gmHostID' => 8]];
    }

    return null;
};
$Real = new Group();
$Real->set('id', 3);
$t->check(
    'a saved group still loads its members',
    [7, 8] === array_map('intval', (array)$Real->get('hosts'))
);

$t->finish();
