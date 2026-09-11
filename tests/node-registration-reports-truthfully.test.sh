#!/bin/bash
#
# Guards GH-575: a node<->master maintenance POST that did not land must not
# report success, and must not print what came back.
#
#   tests/node-registration-reports-truthfully.test.sh
#
# registerStorageNode() and updateStorageNodeCredentials() both POST to THIS
# node's own web tier at maintenance/create_update_node.php. Three things
# answer in its place, and none of them is a connection failure -- curl exits 0
# for all three, so nothing downstream noticed:
#
#   * an inline filtering proxy (the reporter's iboss appliance returned
#     ERR_CONNECT_FAIL as an HTML block page, HTTP 200),
#   * the node's own web tier bouncing to ?node=schema with a 308 when it
#     cannot read the master's database,
#   * a 200 carrying markup from anything else in front of the server.
#
# updateStorageNodeCredentials() additionally had no -o at all, so the block
# page was written straight into the installer's dotted progress line -- which
# is the console output the issue was filed with.
#
# The registration arm DID carry a status check before this, and it could not
# work: it also carried -L, and curl reports %{http_code} for the LAST transfer
# it made. Following the 308 to the schema page turned the exact failure the
# check was written for into a green 200. That is why section 2 asserts on the
# absence of -L rather than on the presence of a case block -- a gate that
# passes on code carrying -L is a gate that would have passed on the bug.
#
# Section 3 is behavioral: it runs the real function bodies out of
# functions.sh against a throwaway HTTP server that plays each interception in
# turn, and asserts on what the installer printed. Source assertions alone
# cannot tell "checks the status" from "checks the status wrongly".
#
# No root, no network beyond loopback, no FOG install.
#
# Exit status 0 = pass, 1 = fail.

cd "$(dirname "$0")/.." || exit 1
FUNCS="lib/common/functions.sh"
[[ -r $FUNCS ]] || { echo "cannot read $FUNCS -- run this from the repository"; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# body <function-name> -- one shell function's text, comments stripped. This
# file's own prose names -L and <!doctype html>; a gate its documentation
# satisfies is not a gate.
body() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { inside = 1 }
        inside { print }
        inside && /^}/ { exit }
    ' "$FUNCS" | grep -vE '^[[:space:]]*#'
}

echo "1. neither call lets a response body reach stdout"

# The unredirected curl is the whole of the original report. Both calls must
# capture what comes back -- into a variable here, since both now inspect it.
for fn in registerStorageNode updateStorageNodeCredentials; do
    stray=0
    while IFS= read -r line; do
        [[ $line == *create_update_node.php* ]] || continue
        [[ $line == *curl* ]] || continue
        # Captured by $( ), or discarded with -o. Anything else prints.
        [[ $line == *'=$(curl'* || $line == *'-o /dev/null'* ]] && continue
        stray=1
        printf '        uncaptured: %s\n' "$(printf '%s' "$line" | sed 's/^ *//' | cut -c1-90)"
    done < <(body "$fn")
    if [[ $stray -eq 0 ]]; then
        ok "$fn captures the response instead of printing it"
    else
        bad "$fn writes the endpoint's reply to the installer's own output"
    fi
done

echo
echo "2. the registration POST does not follow redirects"

# See the header. -L and %{http_code} together cannot see a 308.
if body registerStorageNode | grep -E 'curl.*create_update_node' \
        | grep -qE -- '(^|[[:space:]])-[a-zA-Z]*L[a-zA-Z]*([[:space:]]|$)'; then
    bad "the registration POST still passes -L, so %{http_code} reports the redirect TARGET's status"
else
    ok "the registration POST reports its own status, not a followed redirect's"
fi

echo
echo "3. behavioral -- each interception is reported as a failure"

# Sections 1 and 2 are source assertions and always run; only this one needs a
# stand-in server. Skipping rather than failing follows secureboot-authvars.
if ! command -v python3 >/dev/null 2>&1 || ! command -v curl >/dev/null 2>&1; then
    echo "  SKIP  needs python3 and curl to stand up a local endpoint"
    printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
    [[ $FAIL -eq 0 ]]
    exit
fi

tmp=$(mktemp -d) || exit 1
trap 'kill %1 2>/dev/null; rm -rf "$tmp"' EXIT

# One server, three behaviors picked by the mode file it re-reads per request.
cat > "$tmp/srv.py" <<'PY'
import os, sys
from http.server import BaseHTTPRequestHandler, HTTPServer
MODE = sys.argv[2]
class H(BaseHTTPRequestHandler):
    def do_POST(self):
        mode = open(MODE).read().strip()
        if mode == 'ok':
            self.send_response(200); self.send_header('Content-Length', '0'); self.end_headers()
        elif mode == 'redirect':
            # The schema page itself answers 200. That is the whole point: with
            # -L, curl reports THAT 200 and the 308 becomes invisible.
            if self.path.endswith('node=schema'):
                b = b'<html><body>schema update</body></html>'
                self.send_response(200); self.send_header('Content-Length', str(len(b))); self.end_headers()
                self.wfile.write(b)
                return
            self.send_response(308); self.send_header('Location', '/management/index.php?node=schema')
            self.send_header('Content-Length', '0'); self.end_headers()
        else:  # proxy block page: HTTP 200 carrying markup
            b = b'<!doctype html>\n<html><head><meta http-equiv="refresh" content="0;url=https://proxy.example/bp.html"/></head></html>'
            self.send_response(200); self.send_header('Content-Length', str(len(b))); self.end_headers()
            self.wfile.write(b)
    do_GET = do_POST
    def log_message(self, *a): pass
HTTPServer(('127.0.0.1', int(sys.argv[1])), H).serve_forever()
PY

port=$(( 20000 + RANDOM % 20000 ))
echo ok > "$tmp/mode"
python3 "$tmp/srv.py" "$port" "$tmp/mode" &
for _ in $(seq 1 40); do
    curl -s -o /dev/null -m 1 -X POST "http://127.0.0.1:$port/" && break
    sleep 0.1
done

# The functions under test, lifted verbatim out of functions.sh. dots() comes
# with them so the output shape is the installer's own.
{
    body dots
    body _reportNodePostFailure
    body registerStorageNode
    body updateStorageNodeCredentials
} > "$tmp/under-test.sh"

# Everything registerStorageNode reaches for besides the POST. The existence
# probe is answered by the same server, whose reply is never the literal
# "exists", so the registration branch is always the one taken. It is wget on
# this branch rather than curl, hence the timeout below -- an unanswered probe
# would otherwise stall the whole section rather than fail it.
cat > "$tmp/harness.sh" <<HARNESS
httpproto=http
ipaddress=127.0.0.1:$port
webroot=/
storageLocation=/images
snapindir=/opt/fog/snapins
sslpath=/opt/fog/snapins/ssl
interface=eth0
username=fogproject
password=hunter2
maxClients=10
. "$tmp/under-test.sh"
HARNESS

run_mode() {
    echo "$1" > "$tmp/mode"
    timeout 30 bash -c ". '$tmp/harness.sh'; $2" 2>&1
}

for mode in redirect proxy; do
    case $mode in
        redirect) label="a 308 to the schema page" ;;
        proxy)    label="a 200 carrying a proxy block page" ;;
    esac

    # Asserting on the registration line specifically: the existence probe
    # above it prints its own "Done", so a bare "no Done anywhere" test would
    # be red no matter what the registration did.
    out=$(run_mode "$mode" registerStorageNode)
    regline=$(printf '%s\n' "$out" | grep 'Node being registered')
    if [[ $regline == *Failed* && $regline != *Done* ]]; then
        ok "registerStorageNode reports Failed on $label"
    else
        bad "registerStorageNode did not report a failure on $label -- got: $(printf '%s' "$out" | tr '\n' '|' | cut -c1-140)"
    fi

    out=$(run_mode "$mode" updateStorageNodeCredentials)
    if [[ $out == *Failed* && $out != *Done* ]]; then
        ok "updateStorageNodeCredentials reports Failed on $label"
    else
        bad "updateStorageNodeCredentials did not report a failure on $label -- got: $(printf '%s' "$out" | tr '\n' '|' | cut -c1-140)"
    fi

    if [[ $out != *'<!doctype'* && $out != *'<html'* ]]; then
        ok "updateStorageNodeCredentials keeps the reply out of the installer's output ($mode)"
    else
        bad "updateStorageNodeCredentials printed the endpoint's reply ($mode)"
    fi
done

# And the good path still says Done, or the two assertions above are satisfied
# by a function that can only ever fail.
out=$(run_mode ok updateStorageNodeCredentials)
if [[ $out == *Done* && $out != *Failed* ]]; then
    ok "updateStorageNodeCredentials reports Done when the POST lands"
else
    bad "updateStorageNodeCredentials does not report success on a clean 200 -- got: $(printf '%s' "$out" | tr '\n' '|' | cut -c1-140)"
fi

out=$(run_mode ok registerStorageNode)
regline=$(printf '%s\n' "$out" | grep 'Node being registered')
if [[ $regline == *Done* && $regline != *Failed* ]]; then
    ok "registerStorageNode reports Done when the POST lands"
else
    bad "registerStorageNode does not report success on a clean 200 -- got: $(printf '%s' "$out" | tr '\n' '|' | cut -c1-140)"
fi

echo
printf '%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]]
