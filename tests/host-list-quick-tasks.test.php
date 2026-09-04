<?php
/**
 * One-click tasks on the host list: the parts that fail silently.
 *
 * The list gained three buttons in the grid's own button bar -- Deploy,
 * Capture and Multi-Cast -- that create the tasking on a single click,
 * skipping the Queue Task modal's type picker and options form. The buttons
 * themselves are drawn by the browser; what the server owns is
 * HostManagement::_quickTaskItems(), which says WHICH of the three this
 * install offers, what each is called, and whether the reader may task at
 * all. Three things about that are worth a test, and each of them is
 * invisible when it breaks.
 *
 * 1. THE PERMISSION. These buttons create taskings, so they are gated on
 *    `host.task` -- the same permission ?node=host&sub=deployMulti resolves
 *    to. Losing the gate does not break anything visibly: a reader without
 *    it simply gets three buttons whose every click is refused, which reads
 *    as a broken page rather than as a permission problem.
 *
 * 2. THE VALUES ARE READ THROUGH get(), NOT AS PROPERTIES. getClass()
 *    returns a FOGController, and FOGController declares no __get, so
 *    `$TaskType->name` is null and `(int)$TaskType->id` is 0 -- with no
 *    warning that survives the page's output buffer. The first cut of this
 *    emitted three spans reading data-type="0" data-name="", the browser
 *    drew three nameless buttons, and nothing anywhere said why. The
 *    accordion above it in the same file DOES read properties, because its
 *    objects come from Route::getList(); the two shapes look identical at
 *    the call site.
 *
 * 3. WHICH BUTTONS A SELECTION SIZE CAN USE IS THE ttIsAccess VALUE, and
 *    the whole feature is specified in those terms: one host selected offers
 *    Deploy and Capture, two or more offers Deploy and Multi-Cast. That is
 *    written down nowhere -- not in the script, not in the page. It falls
 *    out of 'both', 'host' and 'group' sitting on those three rows, run
 *    through the same assertSelectionTaskable() the create is refused by.
 *    Asserted as the two sets, so that a change to the rule is a change to
 *    the sentence a reader can check against the screenshot.
 *
 *    What this does NOT pin is the seed. The access values come from the
 *    fixture below, not from taskTypes, so someone editing the seeded
 *    ttIsAccess would change the buttons and leave this green. That is
 *    deliberate -- the effective value on an upgraded server is the product
 *    of several schema steps, and asserting one step's literal would pin the
 *    wrong thing -- but it is the gap, and it is why the three ids ARE
 *    pinned in section 2: the set of types is the half this file owns.
 *
 * Usage: php tests/host-list-quick-tasks.test.php
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

use FOG\Base\FOGCore;
use FOG\Items\TaskType;
use FOG\Items\User;
use FOG\Pages\HostManagement;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('host-list-quick-tasks');

$db = FogTestHarness::fakeDb();

// The three rows the method asks for, keyed by id. Only the four fields it
// reads, plus the seed's real ttIsAccess values -- which are the thing under
// test in section 3, so they are spelled out rather than taken from the
// running install.
/**
 * One taskTypes row. Every column, not just the four the method reads --
 * FOGController::setQuery() walks $databaseFields and warns on each one it
 * cannot find, which buries the test's own output.
 *
 * @param int    $id     ttID
 * @param string $name   ttName
 * @param string $icon   ttIcon
 * @param string $access ttIsAccess
 *
 * @return array
 */
$row = static function ($id, $name, $icon, $access) {
    return [
        'ttID' => $id,
        'ttName' => $name,
        'ttDescription' => $name,
        'ttIcon' => $icon,
        'ttKernel' => '',
        'ttKernelArgs' => '',
        'ttType' => '',
        'ttIsAdvanced' => 0,
        'ttIsAccess' => $access,
        'ttInitrd' => ''
    ];
};
$rows = [
    TaskType::DEPLOY => $row(TaskType::DEPLOY, 'Deploy', 'download', 'both'),
    TaskType::CAPTURE => $row(TaskType::CAPTURE, 'Capture', 'upload', 'host'),
    TaskType::MULTICAST => $row(
        TaskType::MULTICAST,
        'Multi-Cast',
        'share-alt',
        'group'
    )
];
// A single flat row, not a list of rows: FOGController::load() reads
// fetch()->get() as ONE record, and handing it a nested array leaves the
// object invalid with nothing said.
$db->responder = static function ($sql, $params) use ($db, $rows) {
    $db->error = false;
    if (false === strpos($sql, 'taskTypes')) {
        return null;
    }
    foreach ($params as $value) {
        if (isset($rows[(int)$value])) {
            return $rows[(int)$value];
        }
    }

    return [];
};

$t = new FogChecks();

$items = new \ReflectionMethod(HostManagement::class, '_quickTaskItems');
$items->setAccessible(true);
$page = new HostManagement();

/**
 * Runs _quickTaskItems() as a user holding the given permissions.
 *
 * @param array $perms the effective permission list for the acting user
 *
 * @return string the emitted markup
 */
$emit = static function (array $perms) use ($page, $items) {
    $user = (new User())->set('id', 1)->set('name', 'fog');
    foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
        FogTestHarness::setStatic($cls, 'FOGUser', $user);
    }
    FogTestHarness::setStatic('Authorization', '_permCache', [1 => $perms]);

    return (string)$items->invoke($page);
};

// -------------------------------------------------------------------------
// 1. The permission gate.
// -------------------------------------------------------------------------
$t->check(
    'a reader without host.task is offered no quick tasks at all',
    '' === $emit(['host.view', 'host.edit'])
);
$t->check(
    'host.task alone is enough',
    false !== strpos($emit(['host.task']), 'quicktaskitem')
);
$t->check(
    'and so is a wildcard',
    false !== strpos($emit(['*']), 'quicktaskitem')
);

// -------------------------------------------------------------------------
// 2. What the markup actually carries.
// -------------------------------------------------------------------------
$markup = $emit(['*']);

preg_match_all(
    '/<span class="quicktaskitem" data-type="(\d+)" data-access="([^"]*)" '
    . 'data-icon="([^"]*)" data-name="([^"]*)"><\/span>/',
    $markup,
    $m,
    PREG_SET_ORDER
);
$emitted = [];
foreach ($m as $span) {
    $emitted[(int)$span[1]] = [
        'access' => $span[2],
        'icon' => $span[3],
        'name' => $span[4]
    ];
}

$t->check(
    'exactly the three quick types are offered',
    [TaskType::DEPLOY, TaskType::CAPTURE, TaskType::MULTICAST]
    === array_keys($emitted)
);
// The get()-versus-property bug, stated as what a reader would see: an id
// of zero and a nameless button. Every field has to arrive, or the browser
// draws a control nobody can identify.
$t->check(
    'every span carries a real id, name, icon and access',
    3 === count($emitted)
    && count(
        array_filter(
            $emitted,
            static function ($row) {
                return '' !== $row['access']
                    && '' !== $row['icon']
                    && '' !== $row['name'];
            }
        )
    ) === 3
);
$t->check(
    'the names are the task types own, not placeholders',
    'Deploy' === ($emitted[TaskType::DEPLOY]['name'] ?? '')
    && 'Capture' === ($emitted[TaskType::CAPTURE]['name'] ?? '')
    && 'Multi-Cast' === ($emitted[TaskType::MULTICAST]['name'] ?? '')
);

// -------------------------------------------------------------------------
// 3. The rule the whole feature is specified in: which buttons light up.
// -------------------------------------------------------------------------
$assert = new \ReflectionMethod(HostManagement::class, 'assertSelectionTaskable');
$assert->setAccessible(true);

/**
 * A stand-in task type carrying one emitted row's access value.
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
 * The names a selection of this size can task, taken from what was emitted
 * and filtered by the server's own refusal.
 *
 * @param int $count how many rows are ticked
 *
 * @return array the usable task type names, in emission order
 */
$usable = static function ($count) use ($emitted, $assert, $page, $taskType) {
    $names = [];
    foreach ($emitted as $row) {
        try {
            $assert->invoke($page, $taskType($row['access'], $row['name']), $count);
            $names[] = $row['name'];
        } catch (\Exception $e) {
            continue;
        }
    }

    return $names;
};

$t->check(
    'one host selected offers Deploy and Capture',
    ['Deploy', 'Capture'] === $usable(1)
);
$t->check(
    'two hosts selected offers Deploy and Multi-Cast',
    ['Deploy', 'Multi-Cast'] === $usable(2)
);
$t->check(
    'a large selection is still Deploy and Multi-Cast',
    ['Deploy', 'Multi-Cast'] === $usable(250)
);

$t->finish();
