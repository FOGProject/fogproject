#!/bin/bash
#
# The service account's home directory is ensured on EVERY install.
#
#   tests/installer-svc-user-home.test.sh
#
# configureUsers() created /home/$SVC_user and chowned it inside the branch
# that runs only when the account did not already exist. So a home that is
# later removed, or left owned by root, was never repaired: `getent passwd`
# still answers, the branch prints "Skipped", and the install reports success
# having touched the home not at all.
#
# vsftpd answers "cannot change directory" when the account's home is missing,
# which breaks every snapin download and every transfer the server makes
# through its own FTP. The installer's output never mentioned the home, so a
# re-install was the obvious remedy and did nothing (forums topic 18253).
#
# What this pins:
#
#   1. a missing home is created, and owned by the service account
#   2. an EXISTING home still gets its ownership corrected -- the repair case,
#      which is the one the old code could not reach
#   3. a second call is a no-op and still succeeds (every upgrade re-runs it)
#   4. a path that exists as a FILE fails, with the cause named in the error
#      log, rather than being retried forever by a mkdir -p that refuses it
#   5. configureUsers() calls the helper outside the account-creation branch,
#      and does so BEFORE anything writes into the home
#
# Needs bash only; chown is stubbed, so this needs no root. Exit status 0 =
# pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# Only the helper. Sourcing functions.sh runs nothing, but it pulls in every
# other definition, and this test has no business depending on those.
eval "$(awk '/^_ensureSvcUserHome\(\) \{/,/^\}/' "$FUNCS")"
if ! declare -F _ensureSvcUserHome >/dev/null; then
    echo "ERROR: could not extract _ensureSvcUserHome from $FUNCS" >&2
    exit 1
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
error_log="$WORK/error.log"
: > "$error_log"

# The helper names /home/$SVC_user absolutely, so redirect that one path into
# the sandbox by making SVC_user a relative hop out of /home. Cleaner than
# rewriting the function: what is under test is the logic, and rewriting the
# path would mean the test no longer runs the shipped text.
SVC_user="../${WORK#/}/fogproject"
HOMEDIR="/home/${SVC_user}"
[[ -d /home ]] || { echo "ERROR: no /home on this system" >&2; exit 1; }

# chown needs root for a real uid, and the account does not exist here. Stub it
# and log the argv, so ownership is observable without privilege.
chown() { echo "chown $*" >> "$WORK/calls"; return 0; }

echo "service account home directory:"

# --- 1. missing home is created and chowned ---------------------------------
rm -rf "$HOMEDIR" "$WORK/calls"
_ensureSvcUserHome
is "$?" "0" "succeeds when the home does not exist"
[[ -d $HOMEDIR ]] && ok "creates the home directory" \
                  || bad "did not create $HOMEDIR"
if grep -q "chown ${SVC_user}:${SVC_user} ${HOMEDIR}" "$WORK/calls" 2>/dev/null; then
    ok "chowns it to the service account"
else
    bad "no chown to ${SVC_user}:${SVC_user} (calls: $(cat "$WORK/calls" 2>/dev/null | tr '\n' '|'))"
fi

# --- 2. the repair case: home exists, ownership still corrected -------------
# This is what the old code could not do. The directory is already there, so
# the create branch would never have run.
rm -f "$WORK/calls"
_ensureSvcUserHome
is "$?" "0" "succeeds when the home already exists"
if grep -q "chown ${SVC_user}:${SVC_user} ${HOMEDIR}" "$WORK/calls" 2>/dev/null; then
    ok "still corrects ownership on an EXISTING home"
else
    bad "an existing home was left with whatever ownership it had"
fi

# --- 3. idempotent ----------------------------------------------------------
_ensureSvcUserHome
is "$?" "0" "a third call is a no-op and still succeeds"
[[ -d $HOMEDIR ]] && ok "the home survives a repeat call" \
                  || bad "$HOMEDIR disappeared"

# --- 4. a file at that path is a named failure, not a retry forever ---------
rm -rf "$HOMEDIR"
: > "$HOMEDIR"
: > "$error_log"
_ensureSvcUserHome
rc=$?
[[ $rc -ne 0 ]] && ok "fails when the path exists as a file" \
                || bad "returned 0 for a file at $HOMEDIR"
if grep -q "is not a directory" "$error_log"; then
    ok "names that specific cause in the error log"
else
    bad "the error log does not say the path is not a directory"
fi
rm -f "$HOMEDIR"

# --- 5. configureUsers() calls it outside the creation branch ---------------
# Read the function's text rather than running it -- it calls useradd, chsh and
# a desktop-autostart writer, none of which belong in a unit test.
body="$(awk '/^configureUsers\(\) \{/,/^\}/' "$FUNCS")"
# Comments below name every symbol the ordering checks search for, so those run
# against a comment-stripped copy. Otherwise the fix and a comment describing
# the fix are the same text. The creation branch's new comment names
# _svcUserBashrc() for exactly that reason.
code="$(sed -e 's/[[:space:]]*#.*$//' <<< "$body")"
calls=$(grep -c '_ensureSvcUserHome' <<< "$code")
[[ $calls -ge 1 ]] && ok "configureUsers() calls _ensureSvcUserHome ($calls site(s))" \
                   || bad "configureUsers() never calls _ensureSvcUserHome"

# The call that matters is the one at function scope. Inside the else branch it
# is only ever reached on the run that creates the account, which is the bug.
if grep -qE '^    _ensureSvcUserHome$' <<< "$code"; then
    ok "one call is at function scope, so every install runs it"
else
    bad "no unindented _ensureSvcUserHome call -- every call is inside a branch"
fi

# It has to run before anything writes into the home, or the writers are still
# aiming at a path that may not exist.
ensure_line=$(grep -nE '^    _ensureSvcUserHome$' <<< "$code" | head -1 | cut -d: -f1)
bashrc_line=$(grep -n '_svcUserBashrc' <<< "$code" | head -1 | cut -d: -f1)
config_line=$(grep -n 'mkdir -p /home/\${SVC_user}/\.config' <<< "$code" | head -1 | cut -d: -f1)
if [[ -n $ensure_line && -n $bashrc_line && $ensure_line -lt $bashrc_line ]]; then
    ok "runs before _svcUserBashrc() writes .bashrc"
else
    bad "runs at line '$ensure_line', _svcUserBashrc at '$bashrc_line'"
fi
if [[ -n $ensure_line && -n $config_line && $ensure_line -lt $config_line ]]; then
    ok "runs before the autostart block writes .config"
else
    bad "runs at line '$ensure_line', the .config write at '$config_line'"
fi

# And the old inline mkdir must be gone, or the two can drift apart again.
if grep -qE '^\s*\[\[ \$retVal -eq 0 \]\] && mkdir -p /home/\$\{SVC_user\} ' <<< "$code"; then
    bad "the inline mkdir -p /home/\${SVC_user} is still in the creation branch"
else
    ok "the creation branch no longer mkdirs the home itself"
fi

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
