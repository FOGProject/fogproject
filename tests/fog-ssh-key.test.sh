#!/bin/bash
#
# GH-2058: _authorizeFogSshKey puts the master's public key in the service
# user's authorized_keys, and must be safe to run on every install.
#
# Checks the modes sshd's StrictModes insists on, that a second run adds
# nothing, and that a key already in the file is left alone. _setupFogSshKey is
# not run here: it chowns to root, which needs root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
command -v ssh-keygen >/dev/null 2>&1 || { echo "SKIP: no ssh-keygen"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
error_log="$WORK/error.log"
# A missing function returns 127, which would make a "returns non-zero" case
# pass for the wrong reason, so fail loudly first.
# declare -F returns non-zero when any one of the names is undefined.
declare -F _authorizeFogSshKey _fogHmacHex _fogSshReplyValid >/dev/null 2>&1 \
    || { echo "ERROR: _authorizeFogSshKey, _fogHmacHex or _fogSshReplyValid is not defined" >&2; exit 1; }
# chown to oneself works unprivileged.
SVC_user="$(id -un)"

ssh-keygen -q -t rsa -b 2048 -N '' -C test-fog -f "$WORK/fog_key" >/dev/null 2>&1
ssh-keygen -q -t rsa -b 2048 -N '' -C test-other -f "$WORK/other_key" >/dev/null 2>&1
line="$(head -n1 "$WORK/fog_key.pub")"
other="$(head -n1 "$WORK/other_key.pub")"

echo "== fresh home =="
home="$WORK/home1"
mkdir -p "$home"
_authorizeFogSshKey "$WORK/fog_key.pub" "$home"
[[ $(stat -c %a "$home/.ssh") == 700 ]] && ok "A: .ssh is 0700" \
    || bad "A: .ssh is $(stat -c %a "$home/.ssh")"
[[ $(stat -c %a "$home/.ssh/authorized_keys") == 600 ]] && ok "A2: authorized_keys is 0600" \
    || bad "A2: authorized_keys is $(stat -c %a "$home/.ssh/authorized_keys")"
grep -qxF "$line" "$home/.ssh/authorized_keys" && ok "A3: the key line is present" \
    || bad "A3: the key line is missing"

echo "== second run =="
_authorizeFogSshKey "$WORK/fog_key.pub" "$home"
[[ $(grep -cxF "$line" "$home/.ssh/authorized_keys") -eq 1 ]] && ok "B: exactly one copy" \
    || bad "B: $(grep -cxF "$line" "$home/.ssh/authorized_keys") copies"

echo "== existing unrelated key =="
home="$WORK/home2"
mkdir -p "$home/.ssh"
echo "$other" > "$home/.ssh/authorized_keys"
_authorizeFogSshKey "$WORK/fog_key.pub" "$home"
grep -qxF "$other" "$home/.ssh/authorized_keys" && ok "C: the unrelated key is kept" \
    || bad "C: the unrelated key was lost"
grep -qxF "$line" "$home/.ssh/authorized_keys" && ok "C2: the new key is added" \
    || bad "C2: the new key is missing"

echo "== unreadable pub file =="
_authorizeFogSshKey "$WORK/missing.pub" "$WORK/home3" && bad "D: returned 0 for a missing file" \
    || ok "D: returns non-zero for a missing file"

echo "== bash and PHP sign the same bytes =="
if command -v php >/dev/null 2>&1 && command -v openssl >/dev/null 2>&1; then
    # Every character a shell string could mangle, plus a space.
    secret='p&ss"w>rd$x`y z'
    nonce="$(openssl rand -hex 16)"
    key="$line"
    phpmac() { php -r 'echo hash_hmac("sha256", $argv[1], $argv[2]);' -- "$1" "$2"; }
    reqdata="sshkey"$'\n'"${nonce}"
    respdata="${nonce}"$'\n'"${key}"
    [[ $(_fogHmacHex "$secret" "$reqdata") == "$(phpmac "$reqdata" "$secret")" ]] \
        && ok "E: request mac matches hash_hmac" || bad "E: request mac differs from hash_hmac"
    [[ $(_fogHmacHex "$secret" "$respdata") == "$(phpmac "$respdata" "$secret")" ]] \
        && ok "E2: response mac matches hash_hmac" || bad "E2: response mac differs from hash_hmac"

    pmac="$(phpmac "$respdata" "$secret")"
    _fogSshReplyValid "$secret" "$nonce" "$key" "$pmac" \
        && ok "F: the PHP-made mac is accepted" || bad "F: the PHP-made mac was refused"
    _fogSshReplyValid "$secret" "$nonce" "${key/ssh-rsa/ssh-rsb}" "$pmac" \
        && bad "F2: a changed key was accepted" || ok "F2: a changed key is refused"
    badnonce="${nonce:0:31}$([[ ${nonce:31:1} == 0 ]] && echo 1 || echo 0)"
    _fogSshReplyValid "$secret" "$badnonce" "$key" "$pmac" \
        && bad "F3: a changed nonce was accepted" || ok "F3: a changed nonce is refused"
    _fogSshReplyValid "wrong$secret" "$nonce" "$key" "$pmac" \
        && bad "F4: a wrong secret was accepted" || ok "F4: a wrong secret is refused"
else
    echo "  skip  no php or openssl"
fi

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
