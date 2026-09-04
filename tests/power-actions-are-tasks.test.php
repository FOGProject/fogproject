<?php
/**
 * Shut down, restart and wake belong on the task surface.
 *
 * Each acts on the machines selected at the moment you press it and leaves
 * nothing standing behind it, which is what a task is. Two of the three had
 * nowhere to be asked for except a single host's Power Management tab -- so
 * there was no way to shut down a selection at all -- and the third, Wake-Up,
 * was a task type filed under Advanced beside Memtest and the disk wipes.
 *
 * WHAT THIS PROTECTS, and both halves fail silently:
 *
 * 1. THE PERMISSION IS THE NAME. Authorization::_subToAction() reads the
 *    prefix off the sub, so `taskPowerMulti` is host.task and `powerMulti`
 *    -- the obvious name -- would be host.EDIT on the POST. That is executed
 *    in tests/host-list-queue-task.test.php, beside the same assertion for
 *    deployMulti, because it is the same convention and they should fail
 *    together.
 *
 * 2. THE PANE HAS TO BE THERE, AND IN THE RIGHT PLACE. Checked by RENDERING
 *    the accordion, not by reading the source: the ordering is a property of
 *    the emitted markup and a rearrangement that still contains every string
 *    would pass a grep.
 *
 * 3. THE HOST'S POWER MANAGEMENT TAB DOES NOT OFFER AN IMMEDIATE ACTION.
 *    Not a tidiness rule -- a permission one. `hostPowermanagementPost` is
 *    reached as sub=powermanagement, which _subToAction() resolves to
 *    host.EDIT on a POST, so anything reachable through it is gated as "may
 *    change this host's settings". A schedule is that. An immediate shut
 *    down is not, and it had two ways in: `pmaddod` behind "Create New
 *    Immediate", and a `pmupdate` branch no page had posted to in years
 *    that could still flip a saved schedule to on-demand. Both are checked
 *    for here, and so is the literal 0 the insert now pins.
 *
 * The Wake-Up half -- moved into the pane rather than duplicated -- is
 * checked on the source instead, and deliberately. Rendering it needs the
 * task types themselves, and Route::getList() reaches far enough into the
 * database that seeding it costs more than the check is worth; the fake DB
 * answers the schema probe and never issues the SELECT. The two assertions
 * below are anchored on the whole branch rather than on the constant, so a
 * `continue` deleted or a condition inverted is a visible failure.
 *
 * Usage: php tests/power-actions-are-tasks.test.php
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

use FOG\Items\Host;
use FOG\Pages\HostManagement;

require_once __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('power-actions-are-tasks');
FogTestHarness::fakeDb();

$t = new FogChecks();
$root = dirname(__DIR__);
$web = $root . '/packages/web';

// -------------------------------------------------------------------------
// 1. The pane, rendered.
// -------------------------------------------------------------------------
$page = new HostManagement();
$render = new \ReflectionMethod(get_class($page), 'taskTypeAccordion');
$render->setAccessible(true);

$anchor = static function ($TaskType) {
    return '<a class="taskitem" data-type="' . $TaskType->id . '"></a>';
};
$powerAnchor = static function ($action, $label, $icon) {
    return '<a class="powertaskitem" data-power="' . $action . '">'
        . $label . '</a>';
};

$with = $render->invoke(
    $page,
    ['host', 'both'],
    $anchor,
    'probeAccordion',
    '',
    '',
    $powerAnchor
);
$without = $render->invoke(
    $page,
    ['host', 'both'],
    $anchor,
    'probeAccordion',
    '',
    ''
);

$t->check(
    'the accordion offers shut down and restart',
    false !== strpos($with, 'data-power="shutdown"')
    && false !== strpos($with, 'data-power="reboot"')
);

// BETWEEN, not merely present. Imaging first, the one-click power actions
// next, the things that need thinking about last.
$basic = strpos($with, 'probeAccordionBasic');
$powerPane = strpos($with, 'probeAccordionPower');
$advanced = strpos($with, 'probeAccordionAdvanced');
$t->check(
    'Power sits between Basic and Advanced in the emitted markup',
    false !== $basic
    && false !== $powerPane
    && false !== $advanced
    && $basic < $powerPane
    && $powerPane < $advanced
);

// Basic stays open, Power and Advanced closed -- exactly one `show`, or the
// accordion opens with two panes expanded and the whole point of collapsing
// them is gone.
$t->check(
    'exactly one pane is expanded on load',
    1 === substr_count($with, 'class="collapse show"')
);

// A surface that cannot carry out a power action must not offer one. The
// group task tab is the case in point: it passes no power anchor.
$t->check(
    'no power anchor means no Power pane at all',
    false === strpos($without, 'probeAccordionPower')
    && false === strpos($without, 'data-power=')
);
$t->check(
    'and the other two panes are unchanged by its absence',
    false !== strpos($without, 'probeAccordionBasic')
    && false !== strpos($without, 'probeAccordionAdvanced')
);

// -------------------------------------------------------------------------
// 2. Wake-Up moves into the pane rather than being offered twice.
// -------------------------------------------------------------------------
$src = file_get_contents($web . '/src/Base/FOGPageRender.php');
$body = '';
if (preg_match(
    '#protected function taskTypeAccordion\(.*?\n    \}\n#s',
    $src,
    $m
)) {
    $body = preg_replace('#^\s*//.*$#m', '', $m[0]);
} else {
    $t->check('taskTypeAccordion() is readable', false);
}

if ('' !== $body) {
    // The whole branch, not the constant: `TaskType::WAKE_UP` appearing
    // somewhere in the method proves nothing about what happens to the row.
    $t->check(
        'Wake-Up is routed into the power pane when there is one',
        (bool)preg_match(
            '#if \(count\(\$power\) > 0\s*&&\s*TaskType::WAKE_UP == '
            . '\$TaskType->id\s*\)\s*\{\s*'
            . '\$power\[\$anchor\(\$TaskType\)\] = \$TaskType->description;'
            . '\s*continue;\s*\}#s',
            $body
        )
    );
    // The `continue` is the difference between moved and duplicated, and a
    // duplicate is worse than the wrong pane: the same task offered twice in
    // one accordion.
    $t->check(
        'and it does NOT also fall through into Basic or Advanced',
        (bool)preg_match(
            '#continue;\s*\}\s*\$data\[\$anchor\(\$TaskType\)\]#s',
            $body
        )
    );
    // Guarded on the pane existing. Without this, a surface with no power
    // anchor loses Wake-Up entirely rather than keeping it under Advanced.
    $t->check(
        'the move is conditional on the pane being built',
        false !== strpos($body, 'if (count($power) > 0')
    );
}

// -------------------------------------------------------------------------
// 3. The browser posts to the gated endpoint, on both surfaces.
// -------------------------------------------------------------------------
foreach (
    [
        'the host list' => '/management/js/fog/host/fog.host.list.js',
        'the host edit page' => '/management/js/fog/host/fog.host.edit.js'
    ] as $where => $file
) {
    $js = preg_replace(
        '#^\s*//.*$#m',
        '',
        file_get_contents($web . $file)
    );
    $t->check(
        "$where posts a power action to sub=taskPowerMulti",
        (bool)preg_match(
            '#\.powertaskitem.*?sub=taskPowerMulti#s',
            $js
        )
    );
}

// The selection is read AT CLICK TIME. The grid is live behind the modal, so
// what was ticked when it opened is not necessarily what is ticked now, and
// the ids are what the server acts on. Anchored inside the handler.
$listJs = preg_replace(
    '#^\s*//.*$#m',
    '',
    file_get_contents($web . '/management/js/fog/host/fog.host.list.js')
);
if (preg_match(
    "#\\\$\('\.powertaskitem'\)\.on\('click'.*?\n    \}\);#s",
    $listJs,
    $m
)) {
    $t->check(
        'the list reads the selection inside the power click handler',
        false !== strpos($m[0], '$.getSelectedIds(table)')
    );
} else {
    $t->check('the power click handler is readable', false);
}

// -------------------------------------------------------------------------
// 4. The host's Power Management tab is schedules only.
// -------------------------------------------------------------------------
$host = new HostManagement();
$obj = new Host();
$obj->set('id', 5);
$objProp = new \ReflectionProperty(get_class($host), 'obj');
$objProp->setAccessible(true);
$objProp->setValue($host, $obj);

ob_start();
$host->hostPowermanagement();
$tab = (string)ob_get_clean();

// The control. newPMDisplay(true) is the form that carries the on-demand
// marker, so its absence below is a fact about the page rather than about
// the string never having existed.
$pmDisplay = new \ReflectionMethod(get_class($host), 'newPMDisplay');
$pmDisplay->setAccessible(true);
$t->check(
    'the immediate form is what carries the pmaddod marker',
    false !== strpos((string)$pmDisplay->invoke($host, true), 'pmaddod')
);
$t->check(
    'the tab renders the schedule form',
    false !== strpos($tab, 'name="pmadd"')
);
$t->check(
    'and no form on it posts an immediate action',
    false === strpos($tab, 'pmaddod')
);
$t->check(
    'there is no immediate control to press',
    false === strpos($tab, 'ondemandModal')
    && false === strpos($tab, 'ondemandBtn')
);

// The server half. The render above covers what the page offers; these
// cover what the endpoint would accept from a hand-made POST, which is the
// part that actually matters for a permission.
$hostSrc = (string)file_get_contents($web . '/src/Pages/HostManagement.php');
$postAt = strpos($hostSrc, 'public function hostPowermanagementPost()');
$method = '';
if (false !== $postAt) {
    $open = strpos($hostSrc, '{', $postAt);
    $depth = 0;
    for ($i = $open; $i < strlen($hostSrc); $i++) {
        if ('{' === $hostSrc[$i]) {
            $depth++;
        } elseif ('}' === $hostSrc[$i]) {
            $depth--;
            if (0 === $depth) {
                $method = substr($hostSrc, $open, $i - $open + 1);
                break;
            }
        }
    }
}
$t->check('the endpoint body is readable', '' !== $method);
$t->check(
    'the endpoint has no on-demand branch left',
    '' !== $method
    && false === strpos($method, 'pmaddod')
    && false === strpos($method, 'pmupdate')
);
$t->check(
    'and it writes a literal 0, not whatever the request asked for',
    '' !== $method
    && (bool)preg_match("#->set\('onDemand', 0\)#", $method)
);
// Both sides of the boundary in one place, so the pair reads as the rule it
// is rather than as two unrelated facts: the edit-gated sub takes
// schedules, the task-gated one takes immediate actions.
$t->check(
    'the immediate endpoint is still the task-gated one',
    'task' === FogTestHarness::callStatic(
        'Authorization',
        '_subToAction',
        ['taskPowerMulti', true]
    )
    && 'edit' === FogTestHarness::callStatic(
        'Authorization',
        '_subToAction',
        ['powermanagement', true]
    )
);

$t->finish();
