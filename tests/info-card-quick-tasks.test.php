<?php
/**
 * One-click tasks on the host and group EDIT pages: the parts that fail
 * silently.
 *
 * The list grid has had Deploy/Capture/Multi-Cast buttons for a while
 * (tests/host-list-quick-tasks.test.php pins those). The same affordance now
 * sits in the info card at the top of the edit pages, so an admin already
 * looking at a host or a group does not have to go back to the grid and find
 * the row again. FOGPageRender::renderQuickTaskActions() builds them for both
 * pages. Four things about that are worth a test, and each is invisible when
 * it breaks.
 *
 * 1. THE PERMISSION. These create taskings, so they are gated on
 *    `{node}.task` -- the action ?node={node}&sub=deploy resolves to through
 *    Authorization::_subToAction(), which reads the 'deploy' prefix. The gate
 *    here and the gate the POST hits have to be the same string or a reader
 *    is shown buttons whose every click is refused, which reads as a broken
 *    page rather than as a permission problem. Pinned for BOTH nodes,
 *    because the string is built by concatenation and 'group' taking the
 *    'host' gate would be invisible on a server where the same people hold
 *    both.
 *
 * 2. THE CONFIRMATION HAS TO BE THERE, AND HAS TO NAME THE TARGET. This is
 *    the one that matters. Unlike the list, where you tick a row to get the
 *    buttons, you arrive on an edit page just by clicking a host name -- so
 *    one stray click would deploy over a running machine, or from a group
 *    over every machine in it. The script refuses to fire without
 *    data-confirm, but a data-confirm that came out EMPTY would sail through
 *    window.confirm('')... and an empty-but-present attribute is exactly what
 *    a get()-versus-property slip produces. So the attribute is pinned as
 *    non-empty AND as containing the caller's target text.
 *
 * 3. THE VALUES ARE READ THROUGH get(), NOT AS PROPERTIES. Same trap the
 *    list version documents: TaskType is a FOGController, which declares no
 *    __get, so `$TaskType->name` is null and `(int)$TaskType->id` is 0 with
 *    no warning that survives the page's output buffer. Here it would draw a
 *    nameless button pointed at type 0.
 *
 * 4. WHICH PAIR EACH PAGE OFFERS. Deploy and Capture for a host; Deploy and
 *    Multi-Cast for a group. Capturing is something you do to one machine
 *    and a group is by definition more than one, so a group offering Capture
 *    is a real defect -- and one the server would happily act on, since
 *    GroupManagement::deployPost() does not check ttIsAccess.
 *
 * Usage: php tests/info-card-quick-tasks.test.php
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

use FOG\Items\TaskType;
use FOG\Items\User;
use FOG\Pages\GroupManagement;
use FOG\Pages\HostManagement;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('info-card-quick-tasks');

$db = FogTestHarness::fakeDb();

/**
 * One taskTypes row. Every column, not just the three the builder reads --
 * FOGController::setQuery() walks $databaseFields and warns on each one it
 * cannot find, which buries the test's own output.
 *
 * @param int    $id   ttID
 * @param string $name ttName
 * @param string $icon ttIcon
 *
 * @return array
 */
$row = static function ($id, $name, $icon) {
    return [
        'ttID' => $id,
        'ttName' => $name,
        'ttDescription' => $name,
        'ttIcon' => $icon,
        'ttKernel' => '',
        'ttKernelArgs' => '',
        'ttType' => '',
        'ttIsAdvanced' => 0,
        'ttIsAccess' => 'both',
        'ttInitrd' => ''
    ];
};
$rows = [
    TaskType::DEPLOY => $row(TaskType::DEPLOY, 'Deploy', 'download'),
    TaskType::CAPTURE => $row(TaskType::CAPTURE, 'Capture', 'upload'),
    TaskType::MULTICAST => $row(TaskType::MULTICAST, 'Multi-Cast', 'share-alt')
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

/**
 * Runs the builder as a user holding the given permissions.
 *
 * @param array  $perms   the effective permission list for the acting user
 * @param string $node    'host' or 'group'
 * @param array  $typeIds the task types that page asks for
 * @param string $target  the confirmation's description of the target
 *
 * @return string the emitted markup
 */
$emit = static function (
    array $perms,
    $node,
    array $typeIds,
    $target = 'host "bench-01"'
) {
    $user = (new User())->set('id', 1)->set('name', 'fog');
    foreach (['FOGBase', 'Authorization', 'Route'] as $cls) {
        FogTestHarness::setStatic($cls, 'FOGUser', $user);
    }
    FogTestHarness::setStatic('Authorization', '_permCache', [1 => $perms]);

    return (string)HostManagement::renderQuickTaskActions(
        $node,
        42,
        $typeIds,
        $target
    );
};

$hostTypes = [TaskType::DEPLOY, TaskType::CAPTURE];
$groupTypes = [TaskType::DEPLOY, TaskType::MULTICAST];

// -------------------------------------------------------------------------
// 1. The permission gate, per node.
// -------------------------------------------------------------------------
$t->check(
    'a reader without host.task is offered no quick tasks on a host',
    '' === $emit(['host.view', 'host.edit'], 'host', $hostTypes)
);
$t->check(
    'host.task alone is enough',
    false !== strpos($emit(['host.task'], 'host', $hostTypes), 'fog-quicktask')
);
$t->check(
    'a reader without group.task is offered no quick tasks on a group',
    '' === $emit(['group.view', 'group.edit'], 'group', $groupTypes)
);
$t->check(
    'host.task does NOT unlock the group card',
    '' === $emit(['host.task'], 'group', $groupTypes)
);
$t->check(
    'group.task alone is enough',
    false !== strpos(
        $emit(['group.task'], 'group', $groupTypes),
        'fog-quicktask'
    )
);
$t->check(
    'and a wildcard unlocks both',
    false !== strpos($emit(['*'], 'host', $hostTypes), 'fog-quicktask')
    && false !== strpos($emit(['*'], 'group', $groupTypes), 'fog-quicktask')
);

// -------------------------------------------------------------------------
// 2. The confirmation. The reason a one-click deploy is safe to sit here.
// -------------------------------------------------------------------------
/**
 * Every button in a run of markup, as [type => [icon, name, confirm]].
 *
 * @param string $markup the emitted button group
 *
 * @return array
 */
$parse = static function ($markup) {
    preg_match_all(
        '/<button id="quicktask-[a-z]+-(\d+)"[^>]*?'
        . 'data-type="(\d+)"[^>]*?data-confirm="([^"]*)"[^>]*?>'
        . '<i class="fas fa-([^"]*)"><\/i> ([^<]*)<\/button>/',
        $markup,
        $m,
        PREG_SET_ORDER
    );
    $out = [];
    foreach ($m as $b) {
        // The id's trailing number and data-type are the same value written
        // twice; if they ever disagree the button and its id name different
        // taskings, so the parse insists on the pair rather than trusting one.
        if ((int)$b[1] !== (int)$b[2]) {
            continue;
        }
        $out[(int)$b[2]] = [
            'confirm' => html_entity_decode($b[3], ENT_QUOTES, 'UTF-8'),
            'icon' => $b[4],
            'name' => $b[5]
        ];
    }

    return $out;
};

/**
 * One field of one button, or '' if that button was never emitted.
 *
 * A reader rather than `$buttons[$type]['field'] ?? ''` at each call site:
 * a missing BUTTON and a missing field are the same failure to a check that
 * only wants to know whether the text is there.
 *
 * @param array  $buttons parsed buttons, keyed by task type id
 * @param int    $type    the task type wanted
 * @param string $field   'confirm', 'icon' or 'name'
 *
 * @return string
 */
$field = static function (array $buttons, $type, $field) {
    $button = $buttons[$type] ?? [];

    return (string)($button[$field] ?? '');
};

$markup = $emit(['*'], 'host', $hostTypes);
$hostButtons = $parse($markup);
$groupButtons = $parse(
    $emit(['*'], 'group', $groupTypes, 'all 12 hosts in group "Lab"')
);

$t->check(
    'every host button carries a non-empty confirmation',
    2 === count($hostButtons)
    && 2 === count(
        array_filter(
            $hostButtons,
            static function ($b) {
                return '' !== trim($b['confirm']);
            }
        )
    )
);
$t->check(
    'the confirmation names the task type and the host',
    false !== strpos($field($hostButtons, TaskType::DEPLOY, 'confirm'), 'Deploy')
    && false !== strpos(
        $field($hostButtons, TaskType::DEPLOY, 'confirm'),
        'host "bench-01"'
    )
);
$t->check(
    'a capture confirmation names Capture, not Deploy',
    false !== strpos(
        $field($hostButtons, TaskType::CAPTURE, 'confirm'),
        'Capture'
    )
);
$t->check(
    'the group confirmation carries the member count and the group name',
    false !== strpos(
        $field($groupButtons, TaskType::MULTICAST, 'confirm'),
        'all 12 hosts in group "Lab"'
    )
);

// -------------------------------------------------------------------------
// 3. What the markup actually carries.
// -------------------------------------------------------------------------
// The get()-versus-property bug, stated as what a reader would see: a
// nameless button pointed at type 0.
$t->check(
    'every button carries a real id, name and icon',
    2 === count(
        array_filter(
            $hostButtons,
            static function ($b) {
                return '' !== $b['icon'] && '' !== trim($b['name']);
            }
        )
    )
);
$t->check(
    'the names are the task types own, not placeholders',
    'Deploy' === trim($field($hostButtons, TaskType::DEPLOY, 'name'))
    && 'Capture' === trim($field($hostButtons, TaskType::CAPTURE, 'name'))
);
// Not taste: btn-outline-secondary keeps #6c757d as the TEXT color, which
// against the dark card (#212529) is 3.29:1 -- under the 4.5:1 AA floor for
// body-sized text. Filled puts white on #6c757d and holds 4.69:1 in both
// themes. Measured in a browser against the shipped stylesheets; pinned here
// as the class, because the class is the part a future edit would change.
$t->check(
    'the buttons are filled btn-secondary, not outline',
    false !== strpos($markup, 'class="btn btn-secondary fog-quicktask"')
    && false === strpos($markup, 'btn-outline-secondary')
);
$t->check(
    'a task type this server has deleted simply loses its button',
    [TaskType::DEPLOY] === array_keys(
        $parse($emit(['*'], 'host', [TaskType::DEPLOY, 9999]))
    )
);
$t->check(
    'and a page whose every type is gone emits nothing at all',
    '' === $emit(['*'], 'host', [9998, 9999])
);

// -------------------------------------------------------------------------
// 4. The pair each page offers.
// -------------------------------------------------------------------------
$t->check(
    'a host offers Deploy and Capture',
    [TaskType::DEPLOY, TaskType::CAPTURE] === array_keys($hostButtons)
);
$t->check(
    'a group offers Deploy and Multi-Cast, never Capture',
    [TaskType::DEPLOY, TaskType::MULTICAST] === array_keys($groupButtons)
);

// The pages themselves have to ask for those pairs; the builder takes
// whatever it is handed. Read from the source rather than rendered, because
// rendering edit() needs a loaded entity and every tab behind it.
foreach (
    [
        'host' => [HostManagement::class, 'TaskType::DEPLOY, TaskType::CAPTURE'],
        'group' => [
            GroupManagement::class,
            'TaskType::DEPLOY, TaskType::MULTICAST'
        ]
    ] as $node => $expect
) {
    $src = (string)file_get_contents(
        (new \ReflectionClass($expect[0]))->getFileName()
    );
    $t->check(
        sprintf('%s edit() asks for [%s]', $node, $expect[1]),
        false !== strpos($src, '[' . $expect[1] . ']')
    );
}

$t->finish();
