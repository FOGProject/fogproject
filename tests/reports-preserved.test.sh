#!/bin/bash
#
# User-authored reports survive an install.
#
#   tests/reports-preserved.test.sh
#
# GH-1580. backupReports() copied $webdirdest/management/reports into ../rpttmp
# on both install paths, and restoreReports() was defined and never called from
# anywhere. configureHttpd()'s `rm -rf $webdirdest` then took the live copy, so
# an administrator's own reports were destroyed on every install and upgrade
# and left only in a temporary directory nothing ever read.
#
# Two assertions, and both are needed. The behavior test alone passes on a
# correct function that is still never called -- which is precisely the state
# this fixes -- so the call sites are pinned separately. The call-site test
# alone passes on a function that is called and then fails the install, which
# is what the unguarded `cp -a ../rpttmp/*` did on a fresh install where
# ../rpttmp is empty.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/reports-preserved.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
functions="$root/lib/common/functions.sh"
installer="$root/bin/installfog.sh"

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

for f in "$functions" "$installer"; do
    if [[ ! -r $f ]]; then
        echo "FAIL: cannot read $f" >&2
        exit 1
    fi
done

# ---------------------------------------------------------------------------
# restoreReports() is CALLED, on both install paths.
#
# configureMinHttpd() calls configureHttpd(), so the storage-node path wipes
# the web root exactly as the normal path does. It backs the reports up, so it
# has to put them back -- a restore on the normal path only would have left
# storage nodes with the original bug.
# ---------------------------------------------------------------------------
uncommented=$(sed 's/#.*//' "$installer")

check "restoreReports is called at all" \
    "$(grep -qE '^[[:space:]]*restoreReports[[:space:]]*$' <<< "$uncommented"; echo $?)"

check "it is called as many times as backupReports (both install paths)" \
    "$([[ $(grep -cE '^[[:space:]]*restoreReports[[:space:]]*$' <<< "$uncommented") \
        -eq $(grep -cE '^[[:space:]]*backupReports[[:space:]]*$' <<< "$uncommented") ]]; echo $?)"

# Ordering: restoring before the wipe would be pointless, so each restore must
# follow the configureHttpd/configureMinHttpd that rebuilt the tree.
firstrestore=$(grep -nE '^[[:space:]]*restoreReports[[:space:]]*$' <<< "$uncommented" | head -1 | cut -d: -f1)
firstwipe=$(grep -nE '^[[:space:]]*configure(Min)?Httpd[[:space:]]*$' <<< "$uncommented" | head -1 | cut -d: -f1)

check "the first restore comes after the first web-tree rebuild" \
    "$([[ -n $firstrestore && -n $firstwipe && $firstrestore -gt $firstwipe ]]; echo $?)"

# ---------------------------------------------------------------------------
# And that it does the right thing when called.
# ---------------------------------------------------------------------------
snippet=$(awk '
    /^restoreReports\(\) \{/ { grab = 1 }
    grab { print }
    grab && /^\}/ && !/^restoreReports/ { exit }
' "$functions")

if [[ -z $snippet ]]; then
    echo "FAIL: could not find restoreReports() in lib/common/functions.sh." >&2
    echo "  If it moved or was renamed, point this test at it -- do not" >&2
    echo "  delete the assertion." >&2
    exit 1
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

error_log="$work/error.log"
# The real ones print progress and, for errorStat, exit the installer on a
# non-zero status. Stubbed so a failure is observable here instead of killing
# the test run.
dots() { :; }
# Recorded to a FILE, not a variable: runcase() invokes the function in a
# subshell so the cd cannot leak between cases, and a variable set in there
# would never reach the assertions -- which would make every errorStat check
# below silently vacuous.
errorStat() { echo "$1" > "$statfile"; return 0; }

eval "$snippet"

# The function reads ../rpttmp relative to the caller's cwd, which in a real
# run is the repo's bin/. Each case gets its own directory pair.
runcase() {
    local case_dir="$work/$1"
    mkdir -p "$case_dir/bin"
    webdirdest="$case_dir/web/"
    statfile="$case_dir/errorstat"
    rm -f "$statfile"
    ( cd "$case_dir/bin" && restoreReports )
}

# Empty when errorStat was never reached, which is a pass: the function
# returned early because there was nothing to restore.
reportedstat() {
    cat "$work/$1/errorstat" 2>/dev/null
}

# --- an administrator's report is restored ---------------------------------
mkdir -p "$work/normal/web/management/reports" "$work/normal/rpttmp"
echo mine > "$work/normal/rpttmp/my-report.php"
runcase normal

check "an administrator's report is restored into the rebuilt tree" \
    "$([[ -e $work/normal/web/management/reports/my-report.php ]]; echo $?)"

check "its contents survive intact" \
    "$([[ $(cat "$work/normal/web/management/reports/my-report.php" 2>/dev/null) == mine ]]; echo $?)"

# --- dotfiles come along ---------------------------------------------------
mkdir -p "$work/dotfile/web/management/reports" "$work/dotfile/rpttmp"
echo hidden > "$work/dotfile/rpttmp/.htaccess"
runcase dotfile

check "a dotfile is restored too (cp -a of ./, not a bare glob)" \
    "$([[ -e $work/dotfile/web/management/reports/.htaccess ]]; echo $?)"

# --- an empty rpttmp is not a failure --------------------------------------
# The fresh-install case. `cp -a ../rpttmp/*` passed the unexpanded pattern to
# cp here, which fails -- and errorStat would have ended the install over a
# report that was never there.
mkdir -p "$work/empty/web/management/reports" "$work/empty/rpttmp"
runcase empty

check "an empty rpttmp does not report a failure" \
    "$([[ -z $(reportedstat empty) || $(reportedstat empty) -eq 0 ]]; echo $?)"

check "an empty rpttmp does not create anything" \
    "$([[ -z $(ls -A "$work/empty/web/management/reports") ]]; echo $?)"

# --- no rpttmp at all is not a failure -------------------------------------
mkdir -p "$work/none/web/management/reports"
runcase none

check "a missing rpttmp does not report a failure" \
    "$([[ -z $(reportedstat none) || $(reportedstat none) -eq 0 ]]; echo $?)"

# --- no reports directory in the rebuilt tree ------------------------------
mkdir -p "$work/notree/rpttmp"
echo mine > "$work/notree/rpttmp/my-report.php"
runcase notree

check "a rebuilt tree without management/reports does not report a failure" \
    "$([[ -z $(reportedstat notree) || $(reportedstat notree) -eq 0 ]]; echo $?)"

printf '\n%s: %d passed, %d failed\n' "$(basename "$0")" "$pass" "$fail"
[[ $fail -eq 0 ]]
