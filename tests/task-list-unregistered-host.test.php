<?php
/**
 * A task for an unregistered host shows its MAC, and never its token hash.
 *
 * Forum 18086: a deploy to an unregistered host is a task with no host row
 * (taskHostID NULL). Its grids have no host name to print and no host to link
 * to, so the row carries the MAC the machine booted with. Two halves:
 *
 *   the grids  the render functions of fog.task.list.js print
 *              "<MAC> (unregistered)" with no link, and still link a
 *              registered host. Lifted out of the file and run under node,
 *              so this checks what they DO. Skips without node.
 *   the JSON   TaskManagement::_taskJoinColumns() has no column for the
 *              task's token hash. The hash is credential-equivalent, and the
 *              grid builds its JSON from this list.
 *   the log    the log pane selects logMAC and emits it as `mac`.
 *
 * Usage: php tests/task-list-unregistered-host.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('task-list-unregistered-host');
FogTestHarness::fakeDb();

$t = new FogChecks();
$web = dirname(__DIR__) . '/packages/web';

// --------------------------------------------------------------- the JSON

$cols = new \ReflectionMethod('FOG\Pages\TaskManagement', '_taskJoinColumns');
$cols->setAccessible(true);
$page = (new \ReflectionClass('FOG\Pages\TaskManagement'))
    ->newInstanceWithoutConstructor();
$list = $cols->invoke($page);
$db = array_column($list, 'db');
$dt = array_column($list, 'dt');
$t->check(
    'the task grid columns never include the token hash',
    !in_array('taskTokenHash', $db, true) && !in_array('tokenHash', $dt, true)
);
$t->check(
    'but the task MAC is there, as `mac`',
    in_array('taskMAC', $db, true) && in_array('mac', $dt, true)
);

// The label is built on the server, translated, and only without a host.
$label = new \ReflectionMethod('FOG\Pages\TaskManagement', '_unregisteredLabel');
$label->setAccessible(true);
$t->check(
    'the server label is "<MAC> (unregistered)" for a row with no host',
    'aa:bb (unregistered)' === $label->invoke(null, null, 'aa:bb')
);
$t->check(
    'and empty for a row with a host, or with no MAC',
    '' === $label->invoke(null, 3, 'aa:bb') && '' === $label->invoke(null, null, '')
);
$byDt = array_column($list, null, 'dt');
$t->check(
    'the task grids carry a hostLabel column built by that formatter',
    isset($byDt['hostLabel']['formatter'])
        && 'x (unregistered)' === $byDt['hostLabel']['formatter']('x', ['hostID' => null])
        && '' === $byDt['hostLabel']['formatter']('x', ['hostID' => 5])
);

// ----------------------------------------------------------- the log pane

$src = file_get_contents($web . '/src/Pages/TaskManagement.php');
$from = new \ReflectionMethod('FOG\Pages\TaskManagement', '_logQueryFrom');
$from->setAccessible(true);
$t->check(
    'the log pane selects the MAC the log row was written with',
    false !== strpos((string)$from->invoke(null), '`taskLog`.`logMAC` AS `logMAC`')
);
$t->check(
    'and emits it as `mac`',
    false !== strpos($src, "['db' => 'logMAC', 'dt' => 'mac']")
);
$t->check(
    'and emits the translated label as `hostLabel`',
    1 === preg_match("/'db' => 'logMAC',\s*'dt' => 'hostLabel'/", $src)
);

// ------------------------------------------------------------- the grids

if (!trim((string)shell_exec('command -v node'))) {
    echo "SKIP  node is not installed; JS render half not executed\n";
    $t->finish();
}
$script = <<<'JS'
const fs = require('fs');
const web = process.argv[2];
const common = fs.readFileSync(web + '/management/js/fog/fog.common.js', 'utf8');
const tasks = fs.readFileSync(web + '/management/js/fog/task/fog.task.list.js', 'utf8');
const esc = common.match(/\$\.escapeHtml = (function\(str\) \{[\s\S]*?\n\})/);
const $ = {};
$.escapeHtml = new Function('return (' + esc[1] + ');')();
// The render function whose body holds `anchor`, cut out by brace matching.
function lift(anchor, nth) {
  let at = -1;
  for (let i = 0; i <= nth; i++) {
    at = tasks.indexOf(anchor, at + 1);
  }
  const start = tasks.lastIndexOf('render: function', at);
  const open = tasks.indexOf('{', start);
  let depth = 0, i = open;
  for (; i < tasks.length; i++) {
    if (tasks[i] === '{') depth++;
    if (tasks[i] === '}' && --depth === 0) break;
  }
  return new Function('$', 'return (' + tasks.slice(start + 8, i + 1) + ');')($);
}
// The server sends the translated text; the JS must not build English.
const unreg = {hostid: null, mac: 'aa:bb:cc:dd:ee:ff', hostname: null,
  hostLabel: 'LABEL aa:bb:cc:dd:ee:ff'};
const reg = {hostid: 3, mac: '', hostname: 'lab', hostLabel: ''};
const out = {};
// Each host column's render function is found by the host link it builds.
const link = 'node=host&sub=edit&id=\' + row.hostid';
const panes = ['active', 'recent', 'logs'];
panes.forEach((name, n) => {
  const fn = lift(link, n);
  out[name] = [fn(null, 'display', unreg), fn('lab', 'display', reg)];
});
out.mac_escaped = lift(link, 0)(null, 'display',
  {hostid: null, hostLabel: '<b>x</b> (unregistered)'});
// The log detail modal: lifted by name and run against a capturing stub.
const at = tasks.indexOf('function showLogDetail(row)');
let depth = 0, end = tasks.indexOf('{', at);
for (let i = end; i < tasks.length; i++) {
  if (tasks[i] === '{') depth++;
  if (tasks[i] === '}' && --depth === 0) { end = i; break; }
}
let html = '';
const stub = function() {
  return {html: function(h) { html = h; }, modal: function() {}};
};
stub.escapeHtml = $.escapeHtml;
stub.each = function(a, f) { a.forEach((v, i) => f(i, v)); };
new Function('$', tasks.slice(at, end + 1) + '; return showLogDetail;')(stub)(
  {hostid: null, hostname: null, mac: 'aa:bb', hostLabel: '<i>x</i> (unregistered)'});
out.modal = html;
console.log(JSON.stringify(out));
JS;
$tmp = tempnam(sys_get_temp_dir(), 'tlu');
file_put_contents($tmp, $script);
$run = shell_exec(
    'node ' . escapeshellarg($tmp) . ' ' . escapeshellarg($web) . ' 2>&1'
);
unlink($tmp);
$got = json_decode((string)$run, true);
if (!is_array($got)) {
    $t->check('the render functions could be lifted: ' . trim((string)$run), false);
    $t->finish();
}
foreach (['active', 'recent', 'logs'] as $pane) {
    $t->check(
        "$pane: an unregistered host prints its MAC, with no link",
        'LABEL aa:bb:cc:dd:ee:ff' === ($got[$pane][0] ?? null)
    );
    $t->check(
        "$pane: a registered host still links by id",
        false !== strpos((string)($got[$pane][1] ?? ''), 'id=3')
            && false !== strpos((string)($got[$pane][1] ?? ''), '>lab</a>')
    );
}
$t->check(
    'the label is HTML-escaped in the grid',
    false === strpos((string)($got['mac_escaped'] ?? ''), '<b>')
        && false !== strpos((string)($got['mac_escaped'] ?? ''), '&lt;b&gt;')
);
$t->check(
    'the log detail modal shows the label, escaped',
    false !== strpos((string)($got['modal'] ?? ''), '&lt;i&gt;x&lt;/i&gt;')
        && false === strpos((string)($got['modal'] ?? ''), '<i>x')
);
$t->check(
    'the JS carries no English "(unregistered)" of its own',
    false === strpos(
        (string)file_get_contents(
            $web . '/management/js/fog/task/fog.task.list.js'
        ),
        "' (unregistered)'"
    )
);

$t->finish();
