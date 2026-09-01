#!/bin/bash
#
# Two writes of the same preference must not be in flight at once.
#
# fogPrefStore() is fire-and-forget, and one user gesture routinely produces
# several saves of the SAME key -- a grid writes its whole state on every
# column-sizing pass and every redraw, and DataTables fires those itself while
# a column is being resized. Sent concurrently they are answered in whatever
# order the server finishes them, and the row keeps whichever ANSWERS last
# rather than whichever was SENT last.
#
# Measured on the 1.6 lab, one double-click-to-fit on the host list:
#
#     send 1012   send 1012   send 1522
#       ok 1012     ok 1522     ok 1012
#
# The page showed the fitted layout, the server kept the old one, and the next
# load undid the fit. Nothing errored -- the write succeeded, twice, with the
# wrong value -- so it reads as the saved layout simply being ignored.
#
# This EXECUTES fogPrefStore against a stub $.ajax rather than grepping for the
# queue, because the failure is an ordering property: a queue that is present
# but releases on `success` instead of `complete`, or that replaces pending
# callbacks instead of accumulating them, greps identically to a correct one.
#
# Usage: bash tests/userpref-writes-are-serialized.test.sh
# Exit status 0 = pass, 1 = fail.

set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
JS="$ROOT/packages/web/management/js/fog/fog.common.js"

fail=0
ok()   { echo "ok  $1"; }
bad()  { echo "FAIL: $1"; fail=1; }

# Needs node. Skips rather than failing when it is absent, the same as
# apidocs-request-snippets.test.sh.
command -v node >/dev/null 2>&1 || {
    echo "SKIP: node is not installed"
    exit 0
}

[ -f "$JS" ] || { bad "fog.common.js not found at $JS"; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# Lift fogPrefStore and the two maps it closes over. Everything else in
# fog.common.js needs a browser, so the function is extracted rather than the
# file being loaded.
node - "$JS" "$WORK/prefstore.js" <<'NODEEOF'
const fs = require('fs');
const src = fs.readFileSync(process.argv[2], 'utf8');
const start = src.indexOf('var fogPrefInFlight = {},');
const sig = 'function fogPrefStore(key, value, cb) {';
if (start < 0 || src.indexOf(sig) < 0) {
  console.error('EXTRACT-FAIL: fogPrefStore and its queue maps were not found');
  process.exit(2);
}
// To the end of the function: its closing brace is the first one in column 0
// after the signature.
const from = src.indexOf(sig);
const end = src.indexOf('\n}', from) + 2;
fs.writeFileSync(process.argv[3], src.slice(start, end));
NODEEOF
[ $? -eq 0 ] || { bad "could not extract fogPrefStore from fog.common.js"; exit 1; }

node - "$WORK/prefstore.js" <<'NODEEOF'
const fs = require('fs');

// A stub jQuery whose ajax NEVER answers by itself: the test decides when each
// request completes, which is the only way to exercise an ordering bug.
const inflight = [];
const sent = [];
global.$ = {
  ajax(o) {
    sent.push(JSON.parse(o.data).value);
    inflight.push(o);
  }
};
global.fogApiBase = () => '/fog/';
const finish = (i, ok) => {
  const o = inflight[i];
  if (ok) { o.success(); } else { o.error({status: 500}); }
  o.complete();
};

eval(fs.readFileSync(process.argv[2], 'utf8'));

let fail = 0;
const check = (cond, msg) => { if (cond) { console.log('ok  ' + msg); }
  else { console.log('FAIL: ' + msg); fail = 1; } };

// 1. A second write while the first is in flight is HELD, not sent.
fogPrefStore('k', 'A');
fogPrefStore('k', 'B');
check(sent.length === 1 && sent[0] === 'A',
  'a second write to a key in flight is queued, not sent concurrently');

// 2. Only the NEWEST held value is sent when the first completes. This is the
//    actual defect: 'B' being sent after 'C' is how a stale value wins.
fogPrefStore('k', 'C');
finish(0, true);
check(sent.length === 2 && sent[1] === 'C',
  'the newest queued value is the one sent, superseded values are dropped');

// 3. A different key is never made to wait behind another.
fogPrefStore('other', 'Z');
check(sent.indexOf('Z') !== -1, 'a different key is not blocked by an in-flight write');

// 4. A FAILED write releases the key. Releasing on success alone wedges the
//    preference for the rest of the page's life after one 500.
sent.length = 0;
inflight.length = 0;
fogPrefStore('e', '1');
finish(0, false);
fogPrefStore('e', '2');
check(sent.length === 2 && sent[1] === '2',
  'a failed write releases the key rather than wedging it');

// 5. Every caller that passed a callback hears the outcome of the write that
//    actually landed -- a queued callback dropped with its value leaves its
//    caller waiting forever.
sent.length = 0;
inflight.length = 0;
const heard = [];
fogPrefStore('c', '1', () => heard.push('first'));
fogPrefStore('c', '2', () => heard.push('second'));
fogPrefStore('c', '3', () => heard.push('third'));
finish(0, true);   // '1' lands, its own callback fires
finish(1, true);   // '3' lands, carrying the callbacks of '2' and '3'
check(heard.length === 3, 'no queued callback is dropped (heard: ' + heard.join(',') + ')');

process.exit(fail);
NODEEOF
[ $? -eq 0 ] || fail=1

if [ "$fail" -ne 0 ]; then
    echo "FAIL: preference writes are not serialized per key"
    exit 1
fi
echo "PASS: at most one write per preference key is in flight, newest value wins"
exit 0
