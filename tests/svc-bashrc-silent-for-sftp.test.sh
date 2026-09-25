#!/bin/bash
#
# GH-1781: the service user's .bashrc must print nothing in a non-interactive
# shell.
#
# The storage node user is the SFTP login that moves a capture out of
# /images/dev. OpenSSH starts an external sftp-server through that user's
# shell, and bash reads .bashrc for it. Any output there lands in the SFTP
# stream, the client fails with "Received message too long 1500476704", and
# the capture stays in dev. The warning is still wanted for a person who logs
# in, so the interactive case is checked as well.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
error_log="$WORK/error.log"

# The text the installer passes, from the installer itself, so a change to its
# first words cannot leave the repair sed matching nothing.
SVC_user=fogproject
eval "$(grep -m1 '^[[:space:]]*textmessage="You seem' "$FUNCS")"
[[ -n $textmessage ]] || { echo "ERROR: textmessage not found in $FUNCS" >&2; exit 1; }

# What a non-interactive shell (sshd running sftp-server) and an interactive
# login print when they read the file.
quiet() { bash --norc --noprofile -c ". '$1'" 2>&1; }
loud()  { bash --norc --noprofile -i -c ". '$1'" 2>/dev/null; }

echo "== fresh install =="
rc="$WORK/fresh.bashrc"
: > "$rc"
_svcUserBashrc "$rc" "$textmessage"
[[ -z $(quiet "$rc") ]] && ok "A: prints nothing in a non-interactive shell" \
    || bad "A: printed in a non-interactive shell: $(quiet "$rc" | head -1)"
loud "$rc" | grep -q "system account" && ok "A2: still warns an interactive login" \
    || bad "A2: no warning for an interactive login"

echo "== upgrade: the line an older installer wrote =="
rc="$WORK/old.bashrc"
printf 'echo -e "%s"\n#exit 1\n' "$textmessage" > "$rc"
[[ -n $(quiet "$rc") ]] || bad "B0: fixture does not reproduce the defect"
_svcUserBashrc "$rc" "$textmessage"
[[ -z $(quiet "$rc") ]] && ok "B: the old unguarded line is repaired in place" \
    || bad "B: the old line still prints: $(quiet "$rc" | head -1)"
[[ $(grep -c "system account" "$rc") -eq 1 ]] && ok "B2: the warning is not added twice" \
    || bad "B2: $(grep -c "system account" "$rc") warning lines"

echo "== a second run changes nothing =="
cp "$rc" "$WORK/before"
_svcUserBashrc "$rc" "$textmessage"
cmp -s "$rc" "$WORK/before" && ok "C: idempotent" || bad "C: a second run changed the file"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
