#!/bin/bash
#
# errorStat() reports one outcome per step.
#
#   tests/errorstat-reporting.test.sh
#
# GH-1597. errorStat() printed "Failed!" and then "OK" for the same step. When
# $exitFail is set the inner branch does not exit, execution falls out of the
# `if`, and the function's last line -- `[[ -z $skipOk ]] && echo "OK"` -- runs
# anyway:
#
#    * Fetching FOG (working-1.6)...........Failed!
#   OK
#
# exitFail is set by all four scripts that source functions.sh
# (installfog.sh, updatefog.sh, restorekernel.sh, revertfog.sh), so every
# failed step in any of them reported both outcomes.
#
# The function is EXECUTED here, not read. A textual check for a `return` would
# pass on a return placed in the wrong branch -- which is the only way to get
# this wrong -- so each case runs errorStat and asserts on what it printed.
#
# The exit-on-failure path (exitFail unset) is exercised in a subshell, because
# passing means the process ends.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/errorstat-reporting.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
functions="$root/lib/common/functions.sh"

pass=0
fail=0

check() {
    if [[ $2 -eq 0 ]]; then
        pass=$((pass + 1))
    else
        fail=$((fail + 1))
        printf '  FAIL  %s\n' "$1"
    fi
}

if [[ ! -r $functions ]]; then
    echo "FAIL: cannot read $functions" >&2
    exit 1
fi

# Lifted rather than sourced: functions.sh is 7000 lines that assume an
# installer environment, and sourcing it here would run far more than the one
# function under test.
lift() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "$functions"
}

snippet=$(lift errorStat)
if ! grep -q '^errorStat() {' <<< "$snippet"; then
    echo "FAIL: could not find errorStat() in lib/common/functions.sh." >&2
    echo "  If it moved or was renamed, point this test at it -- do not" >&2
    echo "  delete the assertions." >&2
    exit 1
fi
eval "$snippet"

# ---------------------------------------------------------------------------
# The regression: a failed step under $exitFail reports Failed! and nothing
# else.
# ---------------------------------------------------------------------------
out=$( exitFail=1; errorStat 1 2>&1 )

check "a failed step under exitFail says Failed!" \
    "$(grep -q 'Failed!' <<< "$out"; echo $?)"
check "and does NOT also say OK" \
    "$(! grep -qx 'OK' <<< "$out"; echo $?)"
check "exactly one line of output" \
    "$([[ $(wc -l <<< "$out") -eq 1 ]]; echo $?)"

# skipOk must not resurrect the OK either -- it suppresses OK, it does not
# change what a failure prints.
out=$( exitFail=1; errorStat 1 "skipOk" 2>&1 )
check "a failed step with skipOk still says Failed! only" \
    "$(grep -q 'Failed!' <<< "$out" && ! grep -qx 'OK' <<< "$out"; echo $?)"

# ---------------------------------------------------------------------------
# The ordinary paths are unchanged. These are what a return in the wrong place
# would break.
# ---------------------------------------------------------------------------
out=$( exitFail=1; errorStat 0 2>&1 )
check "a successful step still says OK" \
    "$(grep -qx 'OK' <<< "$out"; echo $?)"
check "and does not say Failed!" \
    "$(! grep -q 'Failed!' <<< "$out"; echo $?)"

out=$( exitFail=1; errorStat 0 "skipOk" 2>&1 )
check "a successful step with skipOk prints nothing" \
    "$([[ -z $out ]]; echo $?)"

# ---------------------------------------------------------------------------
# Without exitFail the function still ENDS THE PROCESS on failure. This is the
# installer's own behavior and the reason the return above is confined to the
# exitFail branch.
# ---------------------------------------------------------------------------
sub=$(mktemp)
trap 'rm -f "$sub"' EXIT
{
    echo "$snippet"
    echo 'error_log=/dev/null'
    echo 'unset exitFail'
    echo 'errorStat 3 >/dev/null 2>&1'
    echo 'echo REACHED'
} > "$sub"
subout=$(bash "$sub" 2>&1)
substat=$?

check "without exitFail a failure still exits the process" \
    "$(! grep -q 'REACHED' <<< "$subout"; echo $?)"
check "and exits with the status it was given" \
    "$([[ $substat -eq 3 ]]; echo $?)"

# ---------------------------------------------------------------------------
# The return value. Nothing consumes it today -- all 148 call sites are bare
# statements -- but it used to be whatever `[[ -z $skipOk ]] && echo OK`
# happened to leave, which was 1 for a SUCCEEDING step whenever skipOk was
# passed. Pinned so the first caller that does read it is not misled.
# ---------------------------------------------------------------------------
( exitFail=1; errorStat 0 "skipOk" >/dev/null 2>&1 )
check "a successful step returns 0 even with skipOk" \
    "$([[ $? -eq 0 ]]; echo $?)"

( exitFail=1; errorStat 4 >/dev/null 2>&1 )
check "a failed step under exitFail returns the status" \
    "$([[ $? -eq 4 ]]; echo $?)"

printf '\n%s: %d passed, %d failed\n' "$(basename "$0")" "$pass" "$fail"
[[ $fail -eq 0 ]]
