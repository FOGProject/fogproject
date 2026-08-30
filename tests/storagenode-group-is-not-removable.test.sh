#!/bin/bash
#
# A storage node's group cannot be removed, only repointed.
#
#   tests/storagenode-group-is-not-removable.test.sh
#
# nfsGroupMembers.ngmGroupID is a COLUMN on the node, not a link row. Every
# other association tab in FOG is a genuine many-to-many where removing means
# deleting a row; this one has nothing to delete, only somewhere else to point.
# Removing used to write ngmGroupID = 0, which left the node in a group that
# has never existed: invisible in every group, still in the storage node list,
# and unreachable by getOptimalStorageNode() and getMasterStorageNode(), which
# both start from a group. So the node could never be selected for any work
# again, and nothing said so. One such row was found on a live server.
#
# Schema step 388 declares the column ON DELETE RESTRICT and
# StorageGroup::removeNode() throws, so the write is refused twice over. This
# gate is about the third layer: the UI must stop OFFERING the operation.
#
# THE HALF THAT IS EASY TO MISS, and the reason this test exists rather than a
# comment: there are TWO removal paths on an association tab, and hiding the
# button closes only one. An already-associated checkbox posts
# confirmdel/remitems on its own when unticked, through $.checkItemUpdate. A
# tab that hides the button and leaves the checkbox live still offers the
# operation, and looks correct in a screenshot.
#
# Both halves are RUN here rather than grepped -- renderAssocTab() against a
# stub page, and the real default checkboxRender lifted out of fog.common.js
# and called. A grep for "allowRemove" passes just as happily when the flag is
# read and then ignored.
#
# Needs node for the JavaScript half, which skips on its own when node is
# absent, the same way apidocs-request-snippets.test.sh does. The PHP half
# always runs.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
WEB="$REPO/packages/web"
RENDER="$WEB/src/Base/FOGPageRender.php"
PAGE="$WEB/src/Pages/StorageGroupManagement.php"
COMMON="$WEB/management/js/fog/fog.common.js"
EDIT="$WEB/management/js/fog/storagegroup/fog.storagegroup.edit.js"

for f in "$RENDER" "$PAGE" "$COMMON" "$EDIT"; do
    [[ -f $f ]] || { echo "ERROR: $f not found" >&2; exit 1; }
done

PASS=0
FAIL=0
ok()  { echo "PASS: $1"; PASS=$((PASS + 1)); }
bad() { echo "FAIL: $1"; FAIL=$((FAIL + 1)); }

# ---------------------------------------------------------------------------
# 1. renderAssocTab(), executed. The trait is used by a stub page that supplies
#    the seven things the method reaches for, so what is asserted is the markup
#    the method really emits rather than the shape of its source.
# ---------------------------------------------------------------------------
php_out=$(WEB="$WEB" php -d error_reporting=E_ALL <<'PHPEOF'
<?php
$web = getenv('WEB');
if (!function_exists('_')) {
    function _($s) { return $s; }
}
// The trait's own file, loaded alone. Its `use` imports resolve lazily, and
// nothing this test calls reaches any of them.
require $web . '/src/Base/FOGPageRender.php';

class StubObj
{
    public function get($k) { return 42; }
}
class StubPage
{
    use FOG\Base\FOGPageRender;

    public $obj;
    public $headerData = [];
    public $attributes = [];

    public function __construct() { $this->obj = new StubObj(); }

    public static function makeButton($id, $label, $class, $props = '')
    {
        return '<button id="' . $id . '">' . $label . '</button>';
    }
    public static function makeTabUpdateURL($slug, $id) { return 'URL'; }
    public static function renderAssocCreate($a, $b, $c, $d, $e = '') { return ''; }
    public function assocDelModal($item = '') { return '<div id="' . $item . 'DelModal"></div>'; }
    public function render($cols, $id, $buttons) { echo '<table id="' . $id . '">' . $buttons . '</table>'; }

    // renderAssocTab is protected; this test is the caller.
    public function callIt($allowRemove)
    {
        ob_start();
        $this->renderAssocTab(
            'storagegroup-storagenode',
            'Title',
            'Header',
            'storagenode',
            'btn btn-primary float-end',
            '',
            '',
            '',
            $allowRemove
        );
        return ob_get_clean();
    }
}

$page = new StubPage();
$with = $page->callIt(true);
$without = $page->callIt(false);

$checks = [
    // The default must be unchanged: every other tab in FOG relies on it.
    'default still renders the Remove button'
        => false !== strpos($with, 'storagegroup-storagenode-remove'),
    'default still renders the confirm modal'
        => false !== strpos($with, 'storagenodeDelModal'),
    // The point of the flag.
    'allowRemove=false renders NO Remove button'
        => false === strpos($without, 'storagegroup-storagenode-remove'),
    'allowRemove=false renders NO confirm modal'
        => false === strpos($without, 'storagenodeDelModal'),
    // ...without gutting the tab: adding is how a node is moved.
    'allowRemove=false still renders the Add button'
        => false !== strpos($without, 'storagegroup-storagenode-send'),
    'allowRemove=false still renders the table'
        => false !== strpos($without, 'storagegroup-storagenode-table'),
];
foreach ($checks as $what => $result) {
    echo ($result ? 'PASS' : 'FAIL'), ': ', $what, "\n";
}
PHPEOF
)
php_status=$?
echo "$php_out"
if [[ $php_status -ne 0 ]]; then
    bad "the renderAssocTab harness ran cleanly"
fi
while IFS= read -r line; do
    case "$line" in
        PASS:*) PASS=$((PASS + 1)) ;;
        FAIL:*) FAIL=$((FAIL + 1)) ;;
    esac
done <<< "$php_out"

# ---------------------------------------------------------------------------
# 2. The storage group page must pass the flag. This is the USE, and on its own
#    it would be a weak assertion -- section 1 is what proves the flag does
#    anything. Anchored on the whole call so a reordered argument list fails
#    here rather than silently passing false to $noun.
# ---------------------------------------------------------------------------
if perl -0777 -ne 'exit(/renderAssocTab\(\s*'"'"'storagegroup-storagenode'"'"'.*?\n\s*false\s*\n\s*\);/s ? 0 : 1)' "$PAGE"; then
    ok "storagegroupmanagement passes allowRemove=false as the last argument"
else
    bad "storagegroupmanagement passes allowRemove=false as the last argument"
fi

# ---------------------------------------------------------------------------
# 3. The JavaScript half, executed. The default checkboxRender is lifted out of
#    fog.common.js verbatim and called, because the failure being guarded is a
#    behavior of that function and not the presence of a word in the file.
# ---------------------------------------------------------------------------
if ! command -v node >/dev/null 2>&1; then
    echo "SKIP: node is not installed; the JavaScript half did not run"
else
    node_out=$(COMMON="$COMMON" EDIT="$EDIT" node <<'NODEEOF'
const fs = require('fs');
const src = fs.readFileSync(process.env.COMMON, 'utf8');

// Lift the default checkboxRender out of $.registerAssociationTab. Running the
// whole factory would need jQuery, DataTables and Common; this is the function
// under test and it is self-contained.
const m = src.match(
  /checkboxRender = opts\.checkboxRender \|\| (function \(row\) \{[\s\S]*?\n    \}),\n/
) || src.match(
  /checkboxRender = opts\.checkboxRender \|\| (function\(row\) \{[\s\S]*?\n    \}),\n/
);
if (!m) {
  console.log('FAIL: the default checkboxRender could not be located in fog.common.js');
  process.exit(0);
}

function build(allowRemove) {
  const slug = 'storagegroup-storagenode';
  // eslint-disable-next-line no-new-func
  return new Function('allowRemove', 'slug', 'return (' + m[1] + ');')(allowRemove, slug);
}

const assoc = {id: 7, association: 'associated'};
const notAssoc = {id: 8, association: 'dissociated'};

const openTicked   = build(true)(assoc);
const openUnticked = build(true)(notAssoc);
const lockTicked   = build(false)(assoc);
const lockUnticked = build(false)(notAssoc);

const checks = [
  // Unchanged for every other tab.
  ['a normal tab leaves an associated checkbox live, so it can still be unticked',
    !/disabled/.test(openTicked)],
  ['a normal tab leaves an unassociated checkbox live',
    !/disabled/.test(openUnticked)],
  ['a normal tab still marks an associated row checked',
    /checked/.test(openTicked)],
  // The fix.
  ['allowRemove=false disables an ASSOCIATED checkbox, closing the untick path',
    /disabled/.test(lockTicked)],
  ['allowRemove=false leaves an UNASSOCIATED checkbox live, so a node can still be moved in',
    !/disabled/.test(lockUnticked)],
  // The plumbing $.checkItemUpdate needs must survive in both.
  ['the locked cell still carries input.associated with the row id',
    /class="associated"/.test(lockTicked) && /value="7"/.test(lockTicked)],
];
for (const [what, result] of checks) {
  console.log((result ? 'PASS' : 'FAIL') + ': ' + what);
}

// The page must actually ask for it.
const edit = fs.readFileSync(process.env.EDIT, 'utf8');
const asked = /slug:\s*'storagegroup-storagenode'[\s\S]{0,400}?allowRemove:\s*false/.test(edit);
console.log((asked ? 'PASS' : 'FAIL')
  + ': fog.storagegroup.edit.js passes allowRemove:false on the storagenode tab');
NODEEOF
)
    echo "$node_out"
    while IFS= read -r line; do
        case "$line" in
            PASS:*) PASS=$((PASS + 1)) ;;
            FAIL:*) FAIL=$((FAIL + 1)) ;;
        esac
    done <<< "$node_out"
fi

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
