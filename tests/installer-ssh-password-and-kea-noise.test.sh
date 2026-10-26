#!/bin/bash
#
# Two installer findings from the RC-16 test run (GH-1945).
#
#   tests/installer-ssh-password-and-kea-noise.test.sh
#
# 1. With Kea chosen the installer made sure the OTHER engine was stopped and
#    disabled, and asked systemctl is-enabled about the ISC unit. That unit is
#    normally not installed, and is-enabled prints "Failed to get unit file
#    state for isc-dhcp-server.service" for a missing unit even with --quiet.
#    It landed on the console next to an OK. Only the query's stderr is dropped.
#
# 2. Image captures are moved into place over SSH, as the storage user, with a
#    password. When sshd has PasswordAuthentication no for that user the copy
#    succeeds and the move fails ("Unable to connect to ssh during move
#    upload"). _warnSshPasswordAuth() asks `sshd -T -C user=...` and prints a
#    WARNING. It changes no config and never fails the install.
#
# systemctl and sshd are stubs on PATH. No install, no network, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INSTALLER="$REPO/bin/installfog.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

mkdir -p "$WORK/bin" "$WORK/sshbin" "$WORK/empty" "$WORK/tools"
# The only real tools on PATH. A merged-/usr box has the real sshd in /usr/bin,
# so a PATH of /usr/bin would find it and the "no sshd" case would prove nothing.
for t in awk cat grep sed tr head rm; do ln -s "$(command -v $t)" "$WORK/tools/$t"; done
TOOLS="$WORK/tools"

# systemctl: logs every call. The ISC unit is not installed, so is-enabled on it
# prints systemd's own message to stderr and fails, as the real one does.
cat > "$WORK/bin/systemctl" <<'STUB'
#!/bin/bash
echo "$*" >> "$SYSTEMCTL_LOG"
if [[ $1 == is-enabled && $* == *isc-dhcp-server* ]]; then
    echo "Failed to get unit file state for isc-dhcp-server.service: No such file or directory" >&2
    exit 1
fi
[[ $1 == is-active ]] && exit 1
exit 0
STUB
chmod +x "$WORK/bin/systemctl"

# sshd: logs its arguments, answers with $FAKE_SSHD_PA, or errors on "error".
cat > "$WORK/sshbin/sshd" <<'STUB'
#!/bin/bash
echo "$*" >> "$SSHD_LOG"
[[ $FAKE_SSHD_PA == error ]] && { echo "sshd: no hostkeys available" >&2; exit 1; }
printf 'port 22\npasswordauthentication %s\npubkeyauthentication yes\n' "$FAKE_SSHD_PA"
STUB
chmod +x "$WORK/sshbin/sshd"
mkdir -p "$WORK/sbin"
cp "$WORK/sshbin/sshd" "$WORK/sbin/sshd"

error_log="$WORK/error.log"
: > "$error_log"
timestamp=0
SYSTEMCTL_LOG="$WORK/systemctl.log"
SSHD_LOG="$WORK/sshd.log"
export SYSTEMCTL_LOG SSHD_LOG
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
# Neither the real systemctl nor the real sshd may be found.
PATH="$WORK/bin:$TOOLS"

# ---- 1. Kea: no unit-state noise for the ISC unit ---------------------------
echo "Kea, ISC unit not installed:"
dots() { :; }
diffconfig() { :; }
errorStat() { :; }
configureKeaDHCP() { return 0; }
: > "$SYSTEMCTL_LOG"
DHCP_enabled=yes DHCP_engine=kea DHCP_service_name=kea-dhcp4-server
iscservice=isc-dhcp-server keaservice=kea-dhcp4-server
systemctl=yes
NET_fog_server_ip=10.0.45.2 NET_subnet_mask=255.255.255.0 NET_interface=eth0
DHCP_range_start=10.0.45.10 DHCP_range_end=10.0.45.254
DHCP_router=10.0.45.1 DHCP_dns_server_ip=10.0.45.1
err=$(configureDHCP 2>&1 >/dev/null)
is "$err" "" "no systemctl error text reaches the console"
grep -q '^is-enabled --quiet isc-dhcp-server' "$SYSTEMCTL_LOG" \
    && ok "the other engine is still queried" || bad "the other engine is still queried"
grep -q '^disable isc-dhcp-server' "$SYSTEMCTL_LOG" \
    && bad "an absent unit is not disabled" || ok "an absent unit is not disabled"
grep -q '^enable kea-dhcp4-server\|^is-enabled --quiet kea-dhcp4-server' "$SYSTEMCTL_LOG" \
    && ok "the chosen Kea unit is still handled" || bad "the chosen Kea unit is still handled"

# ---- 2. sshd password authentication ---------------------------------------
echo "sshd -T:"
declare -F _warnSshPasswordAuth >/dev/null && ok "_warnSshPasswordAuth exists" || bad "_warnSshPasswordAuth exists"
SVC_user=fogproject ipaddress=10.0.45.2
sshdSearchDirs="$WORK/empty"
run() { # $1 = sshd answer; prints the function's stdout, sets rc
    : > "$SSHD_LOG"
    out=$(FAKE_SSHD_PA="$1" _warnSshPasswordAuth 2>&1)
    rc=$?
}

PATH="$WORK/sshbin:$WORK/bin:$TOOLS"
run no
is "$rc" 0 "passwordauthentication no: returns 0"
[[ $out == *WARNING* && $out == *fogproject* && $out == *"Match User fogproject"* && $out == *"move upload"* ]] \
    && ok "passwordauthentication no: warns, names user, cause and fix" \
    || bad "passwordauthentication no: warns, names user, cause and fix"
is "$(cat "$SSHD_LOG")" "-T -C user=fogproject,host=localhost,addr=10.0.45.2" "sshd is asked about the service user and server address"

run yes
is "$rc" 0 "passwordauthentication yes: returns 0"
is "$out" "" "passwordauthentication yes: silent"

run error
is "$rc" 0 "sshd -T errors: returns 0"
is "$out" "" "sshd -T errors: silent"

PATH="$WORK/bin:$TOOLS"
run no
is "$rc" 0 "no sshd: returns 0"
is "$out" "" "no sshd: silent"

# sshd only in a sbin directory that is not on PATH.
sshdSearchDirs="$WORK/sbin"
run no
[[ $out == *WARNING* ]] && ok "sshd found outside PATH" || bad "sshd found outside PATH"

# set -u must not trip on the optional inputs.
unset SVC_user ipaddress
( set -u; PATH="$WORK/sshbin:$TOOLS"; FAKE_SSHD_PA=no _warnSshPasswordAuth >/dev/null 2>&1 ) \
    && ok "survives set -u with no SVC_user or ipaddress" || bad "survives set -u with no SVC_user or ipaddress"

# The installer actually calls it, after the account exists.
awk '/^[[:space:]]*configureUsers$/ {u=NR} /^[[:space:]]*_warnSshPasswordAuth$/ {w=NR} END {exit !(u && w > u)}' "$INSTALLER" \
    && ok "installfog.sh calls it after configureUsers" || bad "installfog.sh calls it after configureUsers"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
