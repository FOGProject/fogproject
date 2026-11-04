#!/bin/bash
#
# _warnSshPasswordAuth stays quiet when FOG's SSH key is authorized and usable.
#
#   tests/ssh-password-warning-key.test.sh
#
# Since GH-2058 FOGSSH logs in with FOG's own key first. A box whose sshd
# refuses password login is fine when the storage user has that key and sshd
# reads it. _authorizeFogSshKey sets fogSshKeyAuthorized=yes on success; the
# master (_setupFogSshKey) and a node (_fetchFogSshKey) both go through it. A
# node has no /etc/fog/ssh/fog_ssh_key.pub, so the flag is the only shared
# signal. The warning must also run AFTER the key step.
#
# sshd is a stub on PATH. The home is scratch. No install, no network, no root.
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

mkdir -p "$WORK/sshbin" "$WORK/tools" "$WORK/empty" "$WORK/home"
for t in awk cat grep sed tr head rm cut mkdir chmod touch chown; do
    ln -s "$(command -v $t)" "$WORK/tools/$t"
done

# sshd: prints the three settings from the environment.
cat > "$WORK/sshbin/sshd" <<'STUB'
#!/bin/bash
printf 'port 22\npasswordauthentication %s\npubkeyauthentication %s\nauthorizedkeysfile %s\n' \
    "$FAKE_PA" "$FAKE_PK" "$FAKE_AKF"
STUB
chmod +x "$WORK/sshbin/sshd"

error_log="$WORK/error.log"
: > "$error_log"
timestamp=0
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
PATH="$WORK/sshbin:$WORK/tools"
# The storage user's home is the scratch home; no real passwd entry is read.
getent() { echo "fogproject:x:1000:1000::$WORK/home:/bin/sh"; }

SVC_user=fogproject ipaddress=10.0.45.2
sshdSearchDirs="$WORK/empty"
DEF_AKF=".ssh/authorized_keys .ssh/authorized_keys2"

run() { # $1 pa, $2 pk, $3 akf; uses the current fogSshKeyAuthorized
    out=$(FAKE_PA="$1" FAKE_PK="$2" FAKE_AKF="$3" _warnSshPasswordAuth 2>&1)
    rc=$?
}
quiet() { [[ $rc -eq 0 && -z $out ]] && ok "$1" || bad "$1 (rc=$rc out='$out')"; }
warns() { # $1 label, $2 extra text that must appear
    [[ $rc -eq 0 && $out == *WARNING* && $out == *"Match User fogproject"* && $out == *"$2"* ]] \
        && ok "$1" || bad "$1 (rc=$rc out='$out')"
}

echo "warning:"
fogSshKeyAuthorized=yes
run no yes "$DEF_AKF"
quiet "password no, key authorized, default authorizedkeysfile: silent"

run no yes "%h/.ssh/authorized_keys"
quiet "password no, key authorized, %h/.ssh/authorized_keys: silent"

unset fogSshKeyAuthorized
run no yes "$DEF_AKF"
warns "password no, no flag: warns with the error log" "$error_log"

fogSshKeyAuthorized=yes
run no no "$DEF_AKF"
warns "password no, flag, pubkeyauthentication no: warns" "public key login is off"

# None of these makes sshd read ~/.ssh/authorized_keys; the last three only look like it.
for akf in "/etc/ssh/keys/%u" ".ssh/authorized_keys2" \
           "/var/lib/keys/.ssh/authorized_keys" "/etc/ssh/.ssh/authorized_keys_%u"; do
    run no yes "$akf"
    warns "password no, flag, authorizedkeysfile '$akf': warns" "does not include ~/.ssh/authorized_keys"
done

run yes yes "$DEF_AKF"
quiet "password yes: silent"

echo "_authorizeFogSshKey sets the flag:"
echo "ssh-rsa AAAAFAKEKEY fog@test" > "$WORK/fog.pub"
unset fogSshKeyAuthorized
_authorizeFogSshKey "$WORK/fog.pub" >/dev/null 2>&1
[[ ${fogSshKeyAuthorized:-} == yes ]] && ok "success sets the flag" || bad "success sets the flag"

unset fogSshKeyAuthorized
_authorizeFogSshKey "$WORK/missing.pub" >/dev/null 2>&1
[[ -z ${fogSshKeyAuthorized:-} ]] && ok "unreadable pubfile leaves the flag unset" || bad "unreadable pubfile leaves the flag unset"

echo "call order in installfog.sh:"
# Per case arm (a line of the form "[X])" opens one): a warning after every
# key call, and none before the first key call.
awk '
    /^[[:space:]]*#/ {next}
    /^[[:space:]]*\[[A-Za-z]+\)[[:space:]]*$/ { if (pend) bad = 1; pend = 0 }
    /^[[:space:]]*;;[[:space:]]*$/ { if (pend) bad = 1; pend = 0 }
    /^[[:space:]]*(_setupFogSshKey|_fetchFogSshKey)[[:space:]]*$/ { pend = 1; keys++ }
    /^[[:space:]]*_warnSshPasswordAuth[[:space:]]*$/ { if (!keys) bad = 1; pend = 0 }
    END { exit (bad || pend || !keys) }
' "$INSTALLER" \
    && ok "a warning follows the key step in each arm, none before it" \
    || bad "a warning follows the key step in each arm, none before it"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
