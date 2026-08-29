#!/bin/bash
#
# Guards the installer's scratch-directory helper.
#
#   tests/installer-tmpdir.test.sh
#
# Five call sites used to write this inline as
#
#     [[ ! -d ../tmp/ ]] && mkdir -p ../tmp/ >/dev/null 2>&1
#     cd ../tmp/
#
# Three faults, which compound. "../tmp/" resolves against whatever the ambient
# cwd happens to be. The mkdir error goes to /dev/null, destroying the one
# message that would explain a failure. And the cd is unguarded, so execution
# CONTINUES in the wrong directory -- and because the copy step that followed was
# also relative, a failed cd downloaded 60-80MB of kernels into bin/ and then
# copied them from there. The install "succeeded", left untracked binaries in the
# source tree, and printed only
#
#     lib/common/functions.sh: line 9059: cd: ../tmp/: No such file or directory
#
# which is not enough to act on. Reported twice; the second time it did not clear
# on a re-run, which is the signature of the path existing as a non-directory --
# mkdir -p refuses that forever, so every retry fails identically.
#
# What this pins:
#
#   1. the returned path is ABSOLUTE, so no caller depends on cwd
#   2. it is created when missing, and a second call is a no-op
#   3. a path that exists as a FILE fails with a message naming that specific
#      cause, rather than the generic one that sends people hunting permissions
#   4. failure is reported by exit status, so callers can stop instead of
#      carrying on into the wrong directory
#   5. no call site still resolves ../tmp relative to the cwd
#
# Needs bash only. Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# Only the helper, not the whole installer: sourcing functions.sh runs nothing,
# but it does pull in every other definition, and this test has no business
# depending on those.
eval "$(awk '/^_installerTmpDir\(\) \{/,/^\}/' "$FUNCS")"
if ! declare -F _installerTmpDir >/dev/null; then
    echo "ERROR: could not extract _installerTmpDir from $FUNCS" >&2
    exit 1
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
error_log="$WORK/error.log"
: > "$error_log"

echo "installer scratch directory:"

# installfog.sh captures workingdir with pwd from bin/ before anything can move,
# so the helper anchors on it rather than on the cwd of whoever calls it.
workingdir="$WORK/bin"
mkdir -p "$workingdir"

# --- created when missing, and absolute -------------------------------------
out="$(_installerTmpDir)"
rc=$?
is "$rc" "0" "succeeds when the directory does not exist yet"
if [[ $out == /* ]]; then
    ok "returns an ABSOLUTE path, so no caller depends on its cwd"
else
    bad "returned a relative path: $out"
fi
[[ -d $out ]] && ok "the directory it names actually exists" \
    || bad "returned $out but it is not a directory"

# THE point of anchoring on workingdir: the answer must not move when the caller
# does. The old code returned a different directory per cwd, silently.
elsewhere="$WORK/elsewhere/deeper"
mkdir -p "$elsewhere"
out2="$(cd "$elsewhere" && _installerTmpDir)"
is "$out2" "$out" "the same path from a completely different cwd"

# --- idempotent -------------------------------------------------------------
out3="$(_installerTmpDir)"
is "$?" "0" "a second call succeeds"
is "$out3" "$out" "and returns the same path"

# --- the case that does NOT clear on a re-run -------------------------------
#
# mkdir -p refuses a path that exists as a file, every time, so a re-run cannot
# help. The message has to name this cause specifically: the generic "could not
# create" sends an admin looking for a permissions problem they do not have.
rm -rf "$out"
: > "$WORK/tmp"
err="$(_installerTmpDir 2>&1 >/dev/null)"
rc=$?
is "$rc" "1" "fails when the path exists as a file"
if [[ $err == *"not a directory"* ]]; then
    ok "and says so, rather than reporting a generic create failure"
else
    bad "message does not identify the cause: $err"
fi
if [[ $err == *"Remove or rename"* ]]; then
    ok "and says what to do about it"
else
    bad "message gives no remedy: $err"
fi
rm -f "$WORK/tmp"

# --- failure is detectable by callers ---------------------------------------
#
# This is what the old code lacked. The cd was unguarded, so a failure became a
# successful-looking install that had written to the wrong place.
: > "$WORK/tmp"
if _installerTmpDir >/dev/null 2>&1; then
    bad "returned success while the scratch path was unusable"
else
    ok "non-zero exit lets callers stop instead of continuing"
fi
rm -f "$WORK/tmp"

# --- no call site resolves ../tmp against the cwd any more ------------------
#
# Grep rather than behavior, because the failure being guarded is a call site
# quietly reintroducing the relative form. Comments are excluded, and the
# helper's own definition is the one legitimate use.
stray="$(grep -nE '(^|[^#])\.\./tmp' "$FUNCS" \
    | grep -vE '^\s*[0-9]+:\s*#' \
    | grep -v 'local d="\${workingdir%/}/\.\./tmp"' \
    | grep -vE ':\s*#')"
if [[ -z $stray ]]; then
    ok "no call site resolves ../tmp relative to the cwd"
else
    bad "a relative ../tmp came back:"
    printf '          %s\n' "$stray"
fi

echo
if [[ $FAIL -eq 0 ]]; then
    echo "$PASS passed, 0 failed"
    exit 0
fi
echo "$PASS passed, $FAIL failed"
exit 1
