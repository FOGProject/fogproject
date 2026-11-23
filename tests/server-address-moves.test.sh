#!/bin/bash
#
# Guards forum 18327: a server whose address or NIC changed since the last
# install follows it, on the box and in Storage Management.
#
#   tests/server-address-moves.test.sh
#
# An upgrade does not run input.sh, so the address and NIC came from
# .fogsettings and nothing checked them against the box. checkServerAddress()
# is that check. This runs the real function bodies out of functions.sh
# against a stubbed `ip` that serves a fixture address table, and asserts on
# the variables the rest of the installer reads.
#
# Section 2 runs updateStorageNodeAddress() against a throwaway HTTP server,
# like tests/node-registration-reports-truthfully.test.sh does for the other
# node POSTs. Section 3 runs moveTftpHostSetting() against a stubbed mysql.
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

# body <function-name> -- one shell function's text, comments stripped.
body() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { inside = 1 }
        inside { print }
        inside && /^}/ { exit }
    ' "$FUNCS" | grep -vE '^[[:space:]]*#'
}

tmp=$(mktemp -d) || exit 1
trap 'kill %1 2>/dev/null; rm -rf "$tmp"' EXIT

{
    body dots
    body validip
    body mask2network
    body askDhcpRouter
    body askDhcpDns
    body _offerDhcpRefresh
    body _dhcpFollowSubnet
    body _reportNodePostFailure
    body _interfaceHolding
    body _globalAddresses
    body checkServerAddress
    body updateStorageNodeAddress
    body moveTftpHostSetting
} > "$tmp/under-test.sh"

# `ip` answers from $FIX, which holds `ip -4 -o addr show` lines. Only the
# three call shapes the functions under test use are served.
cat > "$tmp/stubs.sh" <<'STUBS'
ip() {
    local args="$*"
    case $args in
        "-4 link show dev "*)
            awk -v d="${args##* dev }" '$2 == d { found = 1 } END { exit !found }' "$FIX"
            ;;
        "-4 route show default dev "*)
            echo "default via $GW proto static metric 100"
            ;;
        *" dev "*)
            awk -v d="${args##* dev }" '$2 == d' "$FIX"
            ;;
        *)
            cat "$FIX"
            ;;
    esac
}
getAllNetworkInterfaces() { echo -n "$FIRSTIF"; }
getCidr() { echo 24; }
cidr2mask() { echo "${NEWMASK:-255.255.255.0}"; }
STUBS

# addr <ifname> <cidr> [scope] -- one fixture line in `ip -o` shape.
addr() {
    printf '2: %s    inet %s brd 0.0.0.0 scope %s %s\\       valid_lft forever preferred_lft forever\n' \
        "$1" "$2" "${3:-global}" "$1"
}

# run_check <stdin> <assignments> -- runs checkServerAddress in a subshell and
# prints the variables it can change, one per line, then the exit status.
run_check() {
    printf '%s\n' "$1" | bash -c "
        FIX='$tmp/fix'
        $2
        . '$tmp/stubs.sh'
        . '$tmp/under-test.sh'
        checkServerAddress >'$tmp/out' 2>&1
        rc=\$?
        echo \"ip=\$NET_fog_server_ip\"
        echo \"if=\$NET_interface\"
        echo \"was=\$serverIpWas\"
        echo \"ifwas=\$serverIfWas\"
        echo \"san=\$PKI_san_ip_addresses\"
        echo \"start=\$DHCP_range_start\"
        echo \"end=\$DHCP_range_end\"
        echo \"router=\$DHCP_router\"
        echo \"dns=\$DHCP_dns_server_ip\"
        echo \"rc=\$rc\"
    " 2>&1
}

# expect <label> <output> <line>... -- every line must appear in the output.
expect() {
    local label=$1 out=$2 want missing=""
    shift 2
    for want in "$@"; do
        printf '%s\n' "$out" | grep -qxF -- "$want" || missing="$missing [$want]"
    done
    if [[ -z $missing ]]; then
        ok "$label"
    else
        bad "$label -- missing$missing; got: $(printf '%s' "$out" | tr '\n' '|')"
    fi
}

echo "1. checkServerAddress"

addr eth0 10.0.0.5/24 > "$tmp/fix"
out=$(run_check "" "NET_fog_server_ip=10.0.0.5; NET_interface=eth0; PKI_san_ip_addresses=10.0.0.5")
expect "the recorded address on the recorded NIC changes nothing" "$out" \
    "ip=10.0.0.5" "if=eth0" "was=" "rc=0"

addr ens18 10.0.0.5/24 > "$tmp/fix"
out=$(run_check "" "NET_fog_server_ip=10.0.0.5; NET_interface=eth0; PKI_san_ip_addresses=10.0.0.5")
expect "a renamed NIC is followed, and the address stays" "$out" \
    "ip=10.0.0.5" "if=ens18" "was=10.0.0.5" "ifwas=eth0" "san=10.0.0.5"

# The link-local address is listed first and with global scope, which is the
# GH-1747 shape: only the 169.254 filter keeps it from becoming the primary.
{ addr eth0 169.254.7.7/16; addr eth0 10.0.0.9/24; addr eth0 10.0.0.10/24; } > "$tmp/fix"
out=$(run_check "" "autoaccept=yes; NET_fog_server_ip=10.0.0.5; NET_interface=eth0; PKI_san_ip_addresses=10.0.0.5")
expect "with -y, a gone address moves to the NIC's primary usable one" "$out" \
    "ip=10.0.0.9" "if=eth0" "was=10.0.0.5" "san=10.0.0.9 10.0.0.10"

out=$(run_check "n" "NET_fog_server_ip=10.0.0.5; NET_interface=eth0; PKI_san_ip_addresses=10.0.0.5")
expect "answering no keeps the recorded address" "$out" \
    "ip=10.0.0.5" "if=eth0" "was=" "san=10.0.0.5"

out=$(run_check "y" "NET_fog_server_ip=10.0.0.5; NET_interface=eth0; PKI_san_ip_addresses=10.0.0.5")
expect "answering yes moves to the new address" "$out" \
    "ip=10.0.0.9" "was=10.0.0.5"

addr ens18 10.0.0.9/24 > "$tmp/fix"
out=$(run_check "" "autoaccept=yes; FIRSTIF='ens18 ens19'; NET_fog_server_ip=10.0.0.5; NET_interface=eth0; PKI_san_ip_addresses=10.0.0.5")
expect "with the NIC gone too, the fresh-install pick supplies the address" "$out" \
    "ip=10.0.0.9" "if=ens18" "was=10.0.0.5" "ifwas=eth0"

{ addr eth0 10.0.0.5/24; addr eth1 10.0.1.77/24; } > "$tmp/fix"
out=$(run_check "" "sNET_fog_server_ip=10.0.1.77; NET_fog_server_ip=10.0.0.5; NET_interface=eth0; PKI_san_ip_addresses=10.0.0.5")
expect "--server-ip wins over an address that is still on the box" "$out" \
    "ip=10.0.1.77" "if=eth1" "was=10.0.0.5" "san=10.0.1.77"

# Saved DHCP settings for 10.0.0.0/24, as an upgrade reads them.
DHCP="DHCP_enabled=yes; NET_subnet_mask=255.255.255.0; DHCP_range_start=10.0.0.10; DHCP_range_end=10.0.0.254; DHCP_router=10.0.0.1; DHCP_dns_server_ip=8.8.8.8; GW=10.0.1.1"

addr eth0 10.0.0.9/24 > "$tmp/fix"
out=$(run_check "" "autoaccept=yes; $DHCP; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "a move inside the subnet keeps the saved DHCP settings" "$out" \
    "ip=10.0.0.9" "start=10.0.0.10" "end=10.0.0.254" "router=10.0.0.1" "dns=8.8.8.8"

addr eth0 10.0.1.9/24 > "$tmp/fix"
out=$(run_check "" "autoaccept=yes; $DHCP; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "-y alone keeps the saved DHCP settings on a new subnet" "$out" \
    "ip=10.0.1.9" "start=10.0.0.10" "end=10.0.0.254" "router=10.0.0.1" "dns=8.8.8.8"
if grep -qF "DHCP is NOT changed" "$tmp/out" && grep -qF -- "--update-dhcp-subnet" "$tmp/out"; then
    ok "-y alone says DHCP is not changed and names the flag that changes it"
else
    bad "-y alone kept DHCP without saying so -- got: $(tr '\n' '|' < "$tmp/out")"
fi

out=$(run_check "" "autoaccept=yes; sDHCP_update_subnet=1; $DHCP; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "-y with --update-dhcp-subnet clears the pool and takes the new gateway" "$out" \
    "ip=10.0.1.9" "start=" "end=" "router=10.0.1.1" "dns=8.8.8.8"

out=$(run_check $'y\ny\n\ny\n' "sDHCP_update_subnet=1; $DHCP; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "--update-dhcp-subnet without -y skips the yes/no question" "$out" \
    "ip=10.0.1.9" "start=" "router=10.0.1.1" "dns=8.8.8.8"

out=$(run_check "" "autoaccept=yes; sDHCP_update_subnet=1; $DHCP; DHCP_router='#   No router address added'; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "a router the administrator declined stays declined" "$out" \
    "start=" "router=#   No router address added"

# No default route on the new NIC: under -y the router question used to loop
# on "Invalid router IP Address!" forever. timeout turns a hang into a FAIL.
# The resolv.conf the fallback reads has only an IPv6 nameserver, so the DNS
# question has nothing usable either.
echo "nameserver fe80::1" > "$tmp/resolv.conf"
out=$(timeout 10 bash -c "$(declare -f run_check); tmp='$tmp'; run_check '' \"autoaccept=yes; sDHCP_update_subnet=1; $DHCP; GW=; DHCP_dns_server_ip=; resolvconf='$tmp/resolv.conf'; NET_fog_server_ip=10.0.0.5; NET_interface=eth0\"")
expect "under -y, nothing usable means no router and no DNS, not a hang" "$out" \
    "ip=10.0.1.9" "router=#   No router address added" "dns=#   No dns added"

# --update-dhcp-subnet on the run AFTER a move that kept DHCP: the address is
# no longer moved, so only _dhcpFollowSubnet can see the stale pool.
run_follow() {
    printf '' | bash -c "
        FIX='$tmp/fix'
        $1
        . '$tmp/stubs.sh'
        . '$tmp/under-test.sh'
        checkServerAddress >'$tmp/out' 2>&1
        _dhcpFollowSubnet >>'$tmp/out' 2>&1
        echo \"start=\$DHCP_range_start\"
        echo \"router=\$DHCP_router\"
    " 2>&1
}
out=$(run_follow "autoaccept=yes; sDHCP_update_subnet=1; $DHCP; NET_fog_server_ip=10.0.1.9; NET_interface=eth0")
expect "a later run with --update-dhcp-subnet fixes a pool left on the old subnet" "$out" \
    "start=" "router=10.0.1.1"
out=$(run_follow "autoaccept=yes; $DHCP; NET_fog_server_ip=10.0.1.9; NET_interface=eth0")
expect "a later run without the flag leaves DHCP alone" "$out" \
    "start=10.0.0.10" "router=10.0.0.1"
out=$(run_follow "autoaccept=yes; sDHCP_update_subnet=1; $DHCP; DHCP_range_start=10.0.1.10; DHCP_router=10.0.1.1; NET_fog_server_ip=10.0.1.9; NET_interface=eth0")
expect "a later run with the flag leaves a pool already on this subnet alone" "$out" \
    "start=10.0.1.10" "router=10.0.1.1"

out=$(run_check $'y\nn' "$DHCP; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "answering no to the DHCP question keeps the saved settings" "$out" \
    "ip=10.0.1.9" "start=10.0.0.10" "router=10.0.0.1" "dns=8.8.8.8"

# Yes to the address, yes to DHCP, then the router and DNS questions: yes and
# a typed router, yes and the suggested DNS.
out=$(run_check $'y\ny\ny\n10.0.1.254\ny\n' "$DHCP; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "answering yes asks the router and DNS questions again" "$out" \
    "ip=10.0.1.9" "start=" "router=10.0.1.254" "dns=8.8.8.8"

# Same network address, wider mask: only the mask comparison catches it.
addr eth0 10.0.0.9/16 > "$tmp/fix"
out=$(run_check "" "autoaccept=yes; sDHCP_update_subnet=1; $DHCP; NEWMASK=255.255.0.0; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "a changed mask counts as a new subnet" "$out" \
    "ip=10.0.0.9" "start=" "router=10.0.1.1"

addr eth0 10.0.1.9/24 > "$tmp/fix"
out=$(run_check "" "autoaccept=yes; sDHCP_update_subnet=1; $DHCP; DHCP_enabled=no; NET_fog_server_ip=10.0.0.5; NET_interface=eth0")
expect "a server that does not run DHCP is not asked" "$out" \
    "ip=10.0.1.9" "start=10.0.0.10" "router=10.0.0.1"

# The function exits rather than returns here, so check the shell's status.
printf '' | bash -c "FIX='$tmp/fix'; sNET_fog_server_ip=10.0.9.9; . '$tmp/stubs.sh'; . '$tmp/under-test.sh'; checkServerAddress" >/dev/null 2>&1
if [[ $? -eq 9 ]]; then
    ok "--server-ip that is not on the box exits 9"
else
    bad "--server-ip that is not on the box did not exit 9"
fi

echo
echo "2. updateStorageNodeAddress"

if ! command -v python3 >/dev/null 2>&1 || ! command -v curl >/dev/null 2>&1; then
    echo "  SKIP  needs python3 and curl to stand up a local endpoint"
else
    cat > "$tmp/srv.py" <<'PY'
import sys, urllib.parse
from http.server import BaseHTTPRequestHandler, HTTPServer
class H(BaseHTTPRequestHandler):
    def do_POST(self):
        n = int(self.headers.get('Content-Length', 0))
        with open(sys.argv[3], 'ab') as f:
            f.write(self.rfile.read(n) + b'\n')
        mode = open(sys.argv[2]).read().strip()
        if mode == 'conflict':
            b = b'refused: another storage node is already registered at 10.0.0.9'
            self.send_response(409)
        elif mode == 'forbidden':
            b = b'refused: 10.0.0.9 is not the address this request arrived on'
            self.send_response(403)
        elif mode == 'gate':
            b = b''
            self.send_response(403)
        elif mode == 'proxy':
            b = b'<!doctype html><html></html>'
            self.send_response(200)
        else:
            b = b''
            self.send_response(200)
        self.send_header('Content-Length', str(len(b))); self.end_headers()
        self.wfile.write(b)
    def log_message(self, *a): pass
HTTPServer(('127.0.0.1', int(sys.argv[1])), H).serve_forever()
PY
    port=$(( 20000 + RANDOM % 20000 ))
    echo ok > "$tmp/mode"
    : > "$tmp/posts"
    python3 "$tmp/srv.py" "$port" "$tmp/mode" "$tmp/posts" &
    for _ in $(seq 1 40); do
        curl -s -o /dev/null -m 1 -X POST "http://127.0.0.1:$port/" && break
        sleep 0.1
    done

    run_move() {
        echo "$1" > "$tmp/mode"
        : > "$tmp/posts"
        bash -c "
            WEB_url_proto=http
            NET_fog_server_ip=127.0.0.1:$port
            NET_interface=ens18
            WEB_root=/
            serverIpWas='$2'
            . '$tmp/under-test.sh'
            updateStorageNodeAddress
        " 2>&1
    }

    out=$(run_move ok "")
    if [[ -z $out && ! -s $tmp/posts ]]; then
        ok "no move recorded means no request"
    else
        bad "updateStorageNodeAddress posted without a move -- got: $out"
    fi

    out=$(run_move ok 10.0.0.5)
    oldip=$(printf '%s' 10.0.0.5 | base64)
    if [[ $out == *Done* && $out != *Failed* ]] && grep -q "nodeIp" "$tmp/posts" \
            && grep -qF "oldip=$oldip" "$tmp/posts"; then
        ok "a clean 200 reports Done, and the POST names the old address"
    else
        bad "the good path did not report Done or did not post the old address -- got: $(printf '%s' "$out" | tr '\n' '|')"
    fi

    out=$(run_move conflict 10.0.0.5)
    if [[ $out == *Failed* && $out == *'already registered at 10.0.0.9'* ]]; then
        ok "a 409 reports Failed and shows the endpoint's reason"
    else
        bad "a 409 was not reported with its reason -- got: $(printf '%s' "$out" | tr '\n' '|')"
    fi

    out=$(run_move forbidden 10.0.0.5)
    if [[ $out == *Failed* && $out == *'not the address this request arrived on'* ]]; then
        ok "a 403 with a reason reports Failed and shows the reason"
    else
        bad "a 403 was not reported with its reason -- got: $(printf '%s' "$out" | tr '\n' '|')"
    fi

    out=$(run_move gate 10.0.0.5)
    if [[ $out == *Failed* && $out == *'answered HTTP 403'* ]]; then
        ok "a bare 403 (the same-machine gate) is reported as a failed request"
    else
        bad "a bare 403 was not reported -- got: $(printf '%s' "$out" | tr '\n' '|')"
    fi

    # The next run sees no move and never posts again, so advice to re-run
    # would retry nothing.
    advice=""
    for mode in conflict forbidden gate proxy; do
        out=$(run_move "$mode" 10.0.0.5)
        [[ $out == *'re-run this installer'* || $out != *'does not retry'* ]] && advice="$advice $mode"
    done
    if [[ -z $advice ]]; then
        ok "every failure names the by-hand fix, never a re-run"
    else
        bad "these failures advise a re-run that cannot retry the move:$advice"
    fi

    out=$(run_move proxy 10.0.0.5)
    if [[ $out == *Failed* && $out != *'<!doctype'* ]]; then
        ok "a 200 carrying markup reports Failed, and the markup stays out"
    else
        bad "a 200 carrying markup was not reported as a failure -- got: $(printf '%s' "$out" | tr '\n' '|')"
    fi
fi

echo
echo "3. moveTftpHostSetting"

run_tftp() {
    : > "$tmp/sql"
    bash -c "
        mysql() { printf '%s\n' \"\$*\" >> '$tmp/sql'; }
        errorStat() { :; }
        error_log=/dev/null
        NET_fog_server_ip='$2'
        serverIpWas='$1'
        . '$tmp/under-test.sh'
        moveTftpHostSetting
    " >/dev/null 2>&1
}

run_tftp 10.0.0.5 10.0.0.9
if grep -qF "SET settingValue='10.0.0.9' WHERE settingKey='FOG_TFTP_HOST' AND settingValue='10.0.0.5'" "$tmp/sql"; then
    ok "FOG_TFTP_HOST moves only where it still holds the old address"
else
    bad "the FOG_TFTP_HOST statement is not conditioned on the old address -- got: $(cat "$tmp/sql")"
fi

run_tftp 10.0.0.5 10.0.0.5
if [[ ! -s $tmp/sql ]]; then
    ok "a NIC-only move does not touch FOG_TFTP_HOST"
else
    bad "a NIC-only move wrote FOG_TFTP_HOST"
fi

run_tftp "10.0.0.5' OR '1'='1" 10.0.0.9
if [[ ! -s $tmp/sql ]]; then
    ok "an old value that is not an address never reaches the statement"
else
    bad "a non-address old value reached the statement"
fi

echo
echo "4. installfog.sh calls them in the order they depend on"

# The calls only, in file order, comments stripped. The node record has to
# carry the new address before anything that finds the node by its address.
calls=$(grep -vE '^[[:space:]]*#' bin/installfog.sh \
    | grep -oE '^[[:space:]]*(normalizeIpAddress|checkServerAddress|_dhcpFollowSubnet|updateStorageNodeAddress|moveTftpHostSetting|registerStorageNode|updateStorageNodeCredentials|_installNodeWebCert)[[:space:]]*$' \
    | awk '{print $1}' | tr '\n' ' ')
for want in \
    "normalizeIpAddress checkServerAddress _dhcpFollowSubnet" \
    "updateStorageNodeAddress _installNodeWebCert" \
    "updateStorageNodeAddress registerStorageNode updateStorageNodeCredentials _installNodeWebCert" \
    "updateStorageNodeAddress moveTftpHostSetting updateStorageNodeCredentials"; do
    if [[ " $calls" == *" $want "* ]]; then
        ok "in order: $want"
    else
        bad "out of order or missing: $want -- calls are: $calls"
    fi
done

echo
printf '%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]]
