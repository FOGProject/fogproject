#!/bin/bash
#
# The installer names the way back, and does not take it.
#
#   tests/revert-offer.test.sh
#
# bin/updatefog.sh used to git-reset the checkout and re-run installfog.sh
# automatically when an update failed. That is gone: reverting means running
# the installer a second time on a box that has just failed running it once,
# which is the least predictable moment to do the most invasive thing, and it
# only ever protected people who updated THROUGH updatefog.sh -- not the
# `git pull && ./installfog.sh` majority.
#
# offerRevert() replaces it. It prints the commit worth going back to and stops
# there, from any installfog.sh run however it was started. The value of the
# thing is entirely in WHEN IT STAYS QUIET: an offer that fires on a fresh
# install, or on a re-run at the same commit, is pointing at git for a failure
# that has nothing to do with the code having changed, and would send an admin
# to reset a working tree over a bad password prompt.
#
# So most of what is asserted here is silence.
#
# No root, no network, no FOG install -- but it does create throwaway git
# repositories under a temp dir, so it needs git.
#
# Usage: bash tests/revert-offer.test.sh
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
if ! command -v git >/dev/null 2>&1; then
    echo "SKIP: git is not installed" >&2
    exit 0
fi

# ---------------------------------------------------------------------------
# The two functions, lifted out of functions.sh.
# ---------------------------------------------------------------------------
lift() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "$functions"
}

snippet="$(lift markInstallCommit)
$(lift offerRevert)"

if ! grep -q 'offerRevert()' <<< "$snippet" || ! grep -q 'markInstallCommit()' <<< "$snippet"; then
    echo "FAIL: could not find markInstallCommit/offerRevert in" >&2
    echo "  lib/common/functions.sh. If they moved or were renamed, point this" >&2
    echo "  test at them -- do not delete the assertions." >&2
    exit 1
fi
eval "$snippet"

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# A throwaway checkout with two commits, so there is a real "previous" one.
FOG_git_path="$work/repo"
mkdir -p "$FOG_git_path"
git -C "$FOG_git_path" init -q
git -C "$FOG_git_path" config user.email t@example.invalid
git -C "$FOG_git_path" config user.name t
echo one > "$FOG_git_path/f"
git -C "$FOG_git_path" add f && git -C "$FOG_git_path" commit -qm one
first=$(git -C "$FOG_git_path" rev-parse HEAD)
echo two > "$FOG_git_path/f"
git -C "$FOG_git_path" commit -qam two
second=$(git -C "$FOG_git_path" rev-parse HEAD)

# ---------------------------------------------------------------------------
# markInstallCommit records HEAD, and never fails a run that has no commit.
# ---------------------------------------------------------------------------
unset FOG_last_good_commit
markInstallCommit
check "markInstallCommit records the current HEAD" \
    "$([[ $FOG_last_good_commit == "$second" ]]; echo $?)"

notarepo="$work/tarball"
mkdir -p "$notarepo"
( FOG_git_path="$notarepo"; unset FOG_last_good_commit
  markInstallCommit; st=$?
  [[ $st -eq 0 && -z $FOG_last_good_commit ]] )
check "a tarball install records nothing and does not fail" "$?"

# ---------------------------------------------------------------------------
# The offer fires exactly once: failed run, recorded commit, HEAD moved.
# ---------------------------------------------------------------------------
FOG_last_good_commit="$first"
out=$(offerRevert 1)

check "a failed run with a moved HEAD prints an offer" \
    "$(grep -q 'reset --hard' <<< "$out"; echo $?)"

check "it names the RECORDED commit, not HEAD" \
    "$(grep -q "reset --hard ${first}" <<< "$out"; echo $?)"

check "it names the checkout to run git in" \
    "$(grep -q -- "-C ${FOG_git_path}" <<< "$out"; echo $?)"

check "it says nothing was reverted" \
    "$(grep -qi 'has been reverted for you' <<< "$out"; echo $?)"

# It must not DO anything. This is the whole difference from revertUpdate().
check "the working tree is untouched" \
    "$([[ $(git -C "$FOG_git_path" rev-parse HEAD) == "$second" ]]; echo $?)"

check "the file on disk is untouched" \
    "$([[ $(cat "$FOG_git_path/f") == two ]]; echo $?)"

# ---------------------------------------------------------------------------
# And the silence. Each of these is a case where naming git would be a wrong
# diagnosis.
# ---------------------------------------------------------------------------
FOG_last_good_commit="$first"
check "a SUCCESSFUL run offers nothing" \
    "$([[ -z $(offerRevert 0) ]]; echo $?)"

FOG_last_good_commit="$second"
check "a re-run at the same commit offers nothing" \
    "$([[ -z $(offerRevert 1) ]]; echo $?)"

unset FOG_last_good_commit
check "a first install, with nothing recorded, offers nothing" \
    "$([[ -z $(offerRevert 1) ]]; echo $?)"

FOG_last_good_commit="$first"
check "a checkout that is not a git tree offers nothing" \
    "$([[ -z $(FOG_git_path="$notarepo" offerRevert 1) ]]; echo $?)"

# ---------------------------------------------------------------------------
# Wiring. The functions being correct is worth nothing if the installer does
# not call them -- which is exactly how restoreReports() sat dead (GH-1580).
# ---------------------------------------------------------------------------
installer=$(sed 's/#.*//' "$root/bin/installfog.sh")

check "installfog.sh arms offerRevert on exit" \
    "$(grep -qE "trap .*offerRevert.* EXIT" <<< "$installer"; echo $?)"

check "installfog.sh calls markInstallCommit" \
    "$(grep -qE '^[[:space:]]*markInstallCommit[[:space:]]*$' <<< "$installer"; echo $?)"

# Both install paths, and each immediately before the writeUpdateFile that
# persists it -- recording it later than that would mark a run successful that
# had not written the file, and earlier would mark one that had not finished.
marks=$(grep -nE '^[[:space:]]*markInstallCommit[[:space:]]*$' <<< "$installer" | cut -d: -f1)
check "it is recorded on both install paths" \
    "$([[ $(wc -w <<< "$marks") -eq 2 ]]; echo $?)"

ok=0
for n in $marks; do
    next=$(sed -n "$((n + 1))p" <<< "$installer" | tr -d '[:space:]')
    [[ $next == writeUpdateFile ]] || ok=1
done
check "each record is immediately followed by writeUpdateFile" "$ok"

# ---------------------------------------------------------------------------
# The automatic path is really gone.
# ---------------------------------------------------------------------------
# Comments stripped: update.sh's header explains that revertUpdate() is gone
# and why, which is worth keeping and would otherwise match this grep.
check "revertUpdate() no longer exists" \
    "$(! cat "$root"/lib/common/*.sh "$root"/bin/*.sh | sed 's/#.*//' | grep -q 'revertUpdate'; echo $?)"

check "updatefog.sh no longer offers --no-revert" \
    "$(! grep -q -- '--no-revert' "$root/bin/updatefog.sh"; echo $?)"

# ---------------------------------------------------------------------------
# --channel moved to the installer, so updatefog.sh stops writing .fogsettings
# ahead of the checkout to make an override stick.
# ---------------------------------------------------------------------------
check "installfog.sh accepts --channel" \
    "$(grep -q 'channel:' <<< "$installer"; echo $?)"

check "installfog.sh validates it through normalizeChannel" \
    "$(grep -q 'normalizeChannel' <<< "$installer"; echo $?)"

check "updatefog.sh no longer calls writeUpdateFile itself" \
    "$(! sed 's/#.*//' "$root/bin/updatefog.sh" | grep -qE '^[[:space:]]*writeUpdateFile[[:space:]]*$'; echo $?)"

check "updatefog.sh forwards --channel to the installer" \
    "$(grep -q 'channelArgs' "$root/bin/updatefog.sh"; echo $?)"

# ---------------------------------------------------------------------------
# updatefog.sh replaces itself.
#
# gitUpdateToBranch() checks out a different branch, which rewrites
# bin/updatefog.sh underneath a bash process still reading it. Bash reads a
# script incrementally and seeks by byte offset, so a file that changes length
# mid-run resumes in the middle of a different line -- silently, arbitrarily,
# and only AFTER the checkout has succeeded. Every channel switch changes this
# file, so it is not hypothetical.
#
# Exercised for real: the original is emptied while it runs, and it has to
# finish anyway.
# ---------------------------------------------------------------------------
updater="$root/bin/updatefog.sh"
uwork="$work/selfrewrite"
mkdir -p "$uwork/bin"

# Built fresh each time, because running it is what destroys it.
#
# HONEST LIMIT OF THE BEHAVIORAL CHECK BELOW. Bash reads ahead in blocks, and
# this script is small enough that a given bash on a given filesystem may
# already hold the rest of it when the truncation lands -- so the run can
# survive WITHOUT the copy too, and this fixture was observed doing exactly
# that. It is a regression check (the guard must not break the ordinary path),
# not a proof that the guard is required.
#
# What pins the guard is the pair of structural assertions after it: that the
# re-exec exists, and that it happens before gitUpdateToBranch. Those do fail
# when the guard is removed, which was verified. The behavioral case is kept
# because the failure it describes is real -- it just cannot be provoked
# reliably on a file this size, and a check that only sometimes reproduces is
# worth having as long as nobody reads it as the proof.
buildFixture() {
    sed -e 's/^if \[\[ ! \$EUID -eq 0 \]\]; then$/if false; then/'         -e 's#^\. \.\./lib/common/functions\.sh$#: > "$VICTIM"#'         "$updater" > "$uwork/bin/updatefog.sh"
    chmod +x "$uwork/bin/updatefog.sh"
    grep -q 'if false; then' "$uwork/bin/updatefog.sh" &&
        grep -q ': > "$VICTIM"' "$uwork/bin/updatefog.sh"
}

if buildFixture; then
    out=$( cd "$uwork/bin" && VICTIM="$uwork/bin/updatefog.sh"            bash ./updatefog.sh --channel beta --yes 2>&1 )
    st=$?
    check "updatefog.sh keeps executing after its own file is emptied mid-run"         "$(grep -q 'No existing FOG install found' <<< "$out"; echo $?)"
    check "and exits deliberately rather than falling off the end of a lost file"         "$([[ $st -eq 1 ]]; echo $?)"
    check "the original really was emptied, so that proved something"         "$([[ ! -s $uwork/bin/updatefog.sh ]]; echo $?)"
    check "no temporary copy is left behind"         "$([[ $(ls /tmp/fog-updatefog.* 2>/dev/null | wc -l) -eq 0 ]]; echo $?)"
else
    check "could not build the self-rewrite fixture (a source line moved)" 1
fi

u=$(sed 's/#.*//' "$updater")

check "updatefog.sh re-execs from a copy before touching git" \
    "$(grep -q 'FOG_UPDATE_RELAUNCHED' <<< "$u"; echo $?)"

# The re-exec has to happen BEFORE the git work, or it protects nothing.
relaunchAt=$(grep -n 'exec bash' <<< "$u" | head -1 | cut -d: -f1)
gitAt=$(grep -n 'gitUpdateToBranch' <<< "$u" | head -1 | cut -d: -f1)
check "the re-exec comes before gitUpdateToBranch" \
    "$([[ -n $relaunchAt && -n $gitAt && $relaunchAt -lt $gitAt ]]; echo $?)"

# $error_log is read by gitUpdateToBranch and by the tee that shows the
# installer. Unset, `>>$error_log` redirects into a file named "".
check "updatefog.sh sets error_log" \
    "$(grep -q '^error_log=' <<< "$u"; echo $?)"
logAt=$(grep -n '^error_log=' <<< "$u" | head -1 | cut -d: -f1)
firstUse=$(grep -n '[$]error_log' <<< "$u" | head -1 | cut -d: -f1)
check "and sets it before its first use" \
    "$([[ -n $logAt && -n $firstUse && $logAt -lt $firstUse ]]; echo $?)"


printf '\n%s: %d passed, %d failed\n' "$(basename "$0")" "$pass" "$fail"
[[ $fail -eq 0 ]]
