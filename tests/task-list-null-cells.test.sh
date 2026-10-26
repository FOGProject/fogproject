#!/bin/bash
#
# The task grids print nothing, not "null", for a name a LEFT JOIN missed.
#
#   tests/task-list-null-cells.test.sh
#
# GH-1953 made FOGManagerController::dataOutput() keep a NULL column as null.
# It used to turn one into "". The task page's grids read names through LEFT
# JOINs (taskTypes, snapins, hosts), so a deleted type, snapin or host now
# arrives as null. Four render functions in fog.task.list.js glued those
# values straight into a string, and null + '' is the word "null". Under the
# old "" they printed nothing.
#
# The render functions are lifted out of the file verbatim and called with a
# null row, so this checks what the functions DO and not what they contain.
# $.escapeHtml is lifted from fog.common.js for the same reason.
#
# Needs node. Skips rather than failing when it is absent, the same as the
# other node-driven tests here.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
WEB="$REPO/packages/web"
COMMON="$WEB/management/js/fog/fog.common.js"
TASKS="$WEB/management/js/fog/task/fog.task.list.js"

for f in "$COMMON" "$TASKS"; do
    [[ -f $f ]] || { echo "ERROR: $f not found" >&2; exit 1; }
done

command -v node >/dev/null 2>&1 || {
    echo "SKIP: node is not installed"
    exit 0
}

PASS=0
FAIL=0

node_out=$(COMMON="$COMMON" TASKS="$TASKS" node <<'NODEEOF'
const fs = require('fs');
const common = fs.readFileSync(process.env.COMMON, 'utf8');
const tasks = fs.readFileSync(process.env.TASKS, 'utf8');

// $.escapeHtml, verbatim.
const esc = common.match(/\$\.escapeHtml = (function\(str\) \{[\s\S]*?\n\})/);
if (!esc) {
  console.log('FAIL: $.escapeHtml could not be located in fog.common.js');
  process.exit(0);
}
const $ = {};
// eslint-disable-next-line no-new-func
$.escapeHtml = new Function('return (' + esc[1] + ');')();

// The render function whose body contains `anchor`, cut out by brace
// matching from its `render: function` to the closing brace.
function lift(anchor) {
  const at = tasks.indexOf(anchor);
  if (at < 0) {
    return null;
  }
  const start = tasks.lastIndexOf('render: function', at);
  const open = tasks.indexOf('{', start);
  let depth = 0;
  let i = open;
  for (; i < tasks.length; i++) {
    if (tasks[i] === '{') depth++;
    if (tasks[i] === '}' && --depth === 0) break;
  }
  const src = tasks.slice(start + 'render: '.length, i + 1);
  // eslint-disable-next-line no-new-func
  return new Function('$', 'return (' + src + ');')($);
}

const cases = [
  // Anchors that exist before and after the fix, so the unfixed file fails
  // on its output rather than on a missing function.
  ['active tasks: task type', 'row.tasktypeicon',
    [null, 'display', {tasktypename: null, tasktypeicon: null}]],
  ['active snapin tasks: snapin', 'node=snapin&sub=edit&id=',
    [null, 'display', {snapinid: 5}]],
  ['active snapin tasks: host', "node=host&sub=edit&id='\n",
    [null, 'display', {hostid: null}]],
  ['recent tasks: redeploy label', 'data-task-name',
    [null, 'display', {hostid: 3, typeID: 1, tasktypename: null, hostname: 'lab'}]],
];
for (const [what, anchor, args] of cases) {
  const fn = lift(anchor);
  if (!fn) {
    console.log('FAIL: ' + what + ' -- render function not found');
    continue;
  }
  const out = String(fn.apply(null, args));
  console.log((/null/.test(out) ? 'FAIL' : 'PASS')
    + ': ' + what + ' prints no "null" for a missed join -> ' + out.replace(/\s+/g, ' '));
}
NODEEOF
)
echo "$node_out"
while IFS= read -r line; do
    case "$line" in
        PASS:*) PASS=$((PASS + 1)) ;;
        FAIL:*) FAIL=$((FAIL + 1)) ;;
    esac
done <<< "$node_out"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
