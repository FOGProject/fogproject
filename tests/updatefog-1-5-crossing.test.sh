#!/bin/bash
#
# bin/updatefog.sh carries a 1.5 server across to 1.6, without reading itself
# out from under its own feet.
#
#   tests/updatefog-1-5-crossing.test.sh
#
# THE ASSERTION THIS FILE EXISTS FOR
#
# The script replaces itself. Checking out 1.6 rewrites bin/updatefog.sh while
# bash is still reading it, and bash reads a script incrementally, seeking by
# byte offset -- so a file that changes length mid-run makes it resume in the
# middle of a different line. The failure is silent, arbitrary, and happens
# AFTER the checkout has already succeeded, which is the worst possible moment:
# the server's code has moved and the process driving the upgrade is now
# executing fragments.
#
# The guard is to copy itself somewhere git will not touch and re-exec from
# there before going anywhere near git. That is cheap, and it is the kind of
# thing that gets "simplified" out by someone who has not hit it -- so it is
# asserted here by actually rewriting the original mid-run and checking the
# process was unaffected, not by grepping for the function.
#
# Everything else here is a refusal. This script runs as root on somebody's
# working imaging server and moves it to a different major version; nearly
# every interesting case is one where it should decline.
#
# No root, no network, no FOG install: the root check is neutered in a copy,
# git and the installer are stubs on PATH.
#
# Usage: bash tests/updatefog-1-5-crossing.test.sh
# Exit status 0 = pass, 1 = fail.

REPO="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$REPO/bin/updatefog.sh"

PASS=0
FAIL=0
ok()  { echo "PASS: $1"; PASS=$((PASS + 1)); }
bad() { echo "FAIL: $1"; FAIL=$((FAIL + 1)); }
check() { if [[ $2 -eq 0 ]]; then ok "$1"; else bad "$1"; fi; }

if [[ ! -r $SRC ]]; then
    echo "FAIL: cannot read $SRC" >&2
    exit 1
fi
if ! command -v git >/dev/null 2>&1; then
    echo "SKIP: git is not installed" >&2
    exit 0
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# --- unmodified, before the root check ------------------------------------
bash "$SRC" --help >/dev/null 2>&1
check "--help exits 0 without root" "$?"
check "--help names the rc channel, which is the one most people will use" \
    "$(bash "$SRC" --help 2>&1 | grep -q 'rc '; echo $?)"
check "--help warns that 1.5 -> 1.6 is a major upgrade" \
    "$(bash "$SRC" --help 2>&1 | grep -qi 'MAJOR upgrade'; echo $?)"
check "--help points at revertfog.sh as the way back" \
    "$(bash "$SRC" --help 2>&1 | grep -q 'revertfog.sh'; echo $?)"

bash "$SRC" >/dev/null 2>&1
check "a non-root run exits 1" "$([[ $? -eq 1 ]]; echo $?)"
bash "$SRC" --nonsense >/dev/null 2>&1
check "a bad argument exits 3, and does so before asking for root" \
    "$([[ $? -eq 3 ]]; echo $?)"

# --- a copy with the root gate removed ------------------------------------
sut="$work/updatefog.sh"
sed 's/^if \[\[ ! \$EUID -eq 0 \]\]; then$/if false; then/' "$SRC" > "$sut"
if ! grep -q 'if false; then' "$sut"; then
    echo "FAIL: could not neuter the root check -- it changed shape." >&2
    exit 1
fi
chmod +x "$sut"

stubs="$work/stubs"
mkdir -p "$stubs"
calls="$work/calls.log"

cat > "$stubs/git" <<'EOF'
#!/bin/bash
echo "git $*" >> "$CALLS"
case "$2$1" in
    *rev-parse*)
        case "$*" in
            *--abbrev-ref*) echo "stable" ;;
            *) echo "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" ;;
        esac
        ;;
    *ls-remote*) : ;;
esac
[[ $* == *ls-remote* ]] && exit 0
exit 0
EOF
chmod +x "$stubs/git"

# A fake 1.5 install: a checkout with a bin/, and a .fogsettings to find.
mkinstall() {
    local dir="$1"
    mkdir -p "$dir/.git" "$dir/bin" "$work/opt"
    cat > "$dir/bin/installfog.sh" <<'EOF'
#!/bin/bash
echo "installfog $*" >> "$CALLS"
exit ${FAKE_INSTALL_STATUS:-0}
EOF
    chmod +x "$dir/bin/installfog.sh"
    : > "$work/opt/.fogsettings"
}

run() {
    ( export PATH="$stubs:$PATH" CALLS="$calls" fogprogramdir="$work/opt"
      cd "$work" && bash "$sut" "$@" )
}

# ---------------------------------------------------------------------------
# THE SELF-REWRITE GUARD, exercised for real.
#
# A stub git whose `checkout` truncates the ORIGINAL script to nothing --
# which is what a real checkout does to it in miniature. Without the re-exec
# the running process is reading that file and cannot survive it.
# ---------------------------------------------------------------------------
victimdir="$work/victim"
mkinstall "$victimdir"
cp "$sut" "$victimdir/bin/updatefog.sh"

cat > "$stubs/git" <<'EOF'
#!/bin/bash
echo "git $*" >> "$CALLS"
for a in "$@"; do
    if [[ $a == checkout ]]; then
        # What a real branch change does to this file, at its most brutal.
        : > "$VICTIM/bin/updatefog.sh"
    fi
done
case "$*" in
    *--abbrev-ref*) echo "stable" ;;
    *rev-parse*)    echo "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" ;;
esac
exit 0
EOF
chmod +x "$stubs/git"

: > "$calls"
out=$( export PATH="$stubs:$PATH" CALLS="$calls" VICTIM="$victimdir" \
              fogprogramdir="$work/opt" FAKE_INSTALL_STATUS=0
       cd "$victimdir/bin" && bash ./updatefog.sh --channel beta --yes 2>&1 )
st=$?

check "it survives its own file being emptied mid-run" \
    "$([[ $st -eq 0 ]]; echo $?)"
check "and still reached the installer afterwards" \
    "$(grep -q '^installfog' "$calls"; echo $?)"
check "and reported success rather than a fragment of itself" \
    "$(grep -q 'completed successfully' <<< "$out"; echo $?)"
check "the original really was emptied, so the test proved something" \
    "$([[ ! -s $victimdir/bin/updatefog.sh ]]; echo $?)"
check "no temporary copy is left behind" \
    "$([[ $(ls /tmp/fog-updatefog.* 2>/dev/null | wc -l) -eq 0 ]]; echo $?)"

# Restore the ordinary stub for the rest.
cat > "$stubs/git" <<'EOF'
#!/bin/bash
echo "git $*" >> "$CALLS"
case "$*" in
    *--abbrev-ref*) echo "stable" ;;
    *rev-parse*)    echo "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" ;;
esac
exit 0
EOF
chmod +x "$stubs/git"

# ---------------------------------------------------------------------------
# Refusals.
# ---------------------------------------------------------------------------
nogit="$work/nogit"
mkdir -p "$nogit/bin"
: > "$work/opt/.fogsettings"
out=$(run --git-path "$nogit" --yes 2>&1)
check "a tarball install (no .git) is refused, exit 1" \
    "$([[ $? -eq 1 ]]; echo $?)"
check "and is sent to the bootstrap installer, which can upgrade it in place" \
    "$(grep -q 'bootstrap.sh' <<< "$out"; echo $?)"

rm -f "$work/opt/.fogsettings"
d="$work/noinstall"; mkinstall "$d"; rm -f "$work/opt/.fogsettings"
out=$(run --git-path "$d" --yes 2>&1)
check "no .fogsettings means no install to update, exit 1" \
    "$([[ $? -eq 1 ]]; echo $?)"
: > "$work/opt/.fogsettings"

d="$work/badchan"; mkinstall "$d"
out=$(run --git-path "$d" --channel nonsense --yes 2>&1)
check "an unknown channel exits 3" "$([[ $? -eq 3 ]]; echo $?)"
check "and names the retired spellings, which still work" \
    "$(grep -qi 'staging' <<< "$out"; echo $?)"

d="$work/rc"; mkinstall "$d"
out=$(run --git-path "$d" --channel rc --yes 2>&1)
check "rc with nothing published says so, rather than 'unknown channel'" \
    "$(grep -qi 'No release candidate is currently published' <<< "$out"; echo $?)"

# ---------------------------------------------------------------------------
# The crossing is announced, and the vocabulary matches 1.6's.
# ---------------------------------------------------------------------------
d="$work/crossing"; mkinstall "$d"
: > "$calls"
out=$(run --git-path "$d" --channel beta --yes 2>&1)
check "moving from stable to working-1.6 is announced as a MAJOR upgrade" \
    "$(grep -qi 'MAJOR upgrade' <<< "$out"; echo $?)"
check "it names revertfog.sh as the only supported way back" \
    "$(grep -q 'revertfog.sh' <<< "$out"; echo $?)"
check "it checks out working-1.6" \
    "$(grep -q 'checkout working-1.6' "$calls"; echo $?)"

for pair in "beta:working-1.6" "dev:working-1.6" "patches:dev-branch" "staging:dev-branch" "stable:stable"; do
    ch="${pair%%:*}"; want="${pair#*:}"
    d="$work/v-$ch"; mkinstall "$d"
    : > "$calls"
    run --git-path "$d" --channel "$ch" --yes >/dev/null 2>&1
    check "--channel ${ch} checks out ${want}, matching 1.6's vocabulary" \
        "$(grep -q "checkout ${want}\$" "$calls"; echo $?)"
done

# ---------------------------------------------------------------------------
# Interactive by default; -Y only on request. This is the whole reason the
# retired fogupdater.sh was not simply fixed in place.
# ---------------------------------------------------------------------------
d="$work/yes"; mkinstall "$d"
: > "$calls"
run --git-path "$d" --channel beta --yes >/dev/null 2>&1
check "--yes runs the installer with -Y" \
    "$(grep -q '^installfog.*-Y' "$calls"; echo $?)"

# Asserted in whichever environment this happens to run in. Both branches check
# the same requirement -- never unattended unless asked -- because whether
# /dev/tty can be opened is a property of the test runner, not of the script,
# and a check that only worked on a headless runner would go quietly vacuous on
# a developer's terminal.
d="$work/interactive"; mkinstall "$d"
: > "$calls"
out=$(run --git-path "$d" --channel beta < /dev/null 2>&1)
st=$?

# The invariant, either way: no -Y. That is the whole difference from the
# retired fogupdater.sh, which ended in `./installfog.sh -y`.
check "without --yes the installer is never given -Y" \
    "$(! grep -q '^installfog.*-Y' "$calls"; echo $?)"

if [[ $st -eq 7 ]]; then
    ok "with no terminal available it refuses (exit 7) rather than going unattended"
    # The refusal now happens BEFORE the checkout, so there is nothing to put
    # back and the message must not imply otherwise. It used to sit beside the
    # installer invocation, which left a headless run with a 1.5 server and a
    # 1.6 source tree.
    check "and says nothing has been changed" \
        "$(grep -qi 'Nothing has been changed' <<< "$out"; echo $?)"
    check "and the checkout was never moved" \
        "$(! grep -q 'checkout' "$calls"; echo $?)"
    check "and does not start the installer at all" \
        "$(! grep -q '^installfog' "$calls"; echo $?)"
else
    # A terminal was available, so the run reached the confirmation prompt --
    # and its stdin is /dev/null, so `read` got EOF. An unanswerable prompt
    # must CANCEL. Treating EOF as consent would mean a cron entry that lost
    # its terminal silently upgrading a server across a major version.
    ok "with a terminal available it reaches the confirmation prompt"
    check "an unanswered confirmation cancels instead of proceeding" \
        "$(grep -qi 'Canceled' <<< "$out"; echo $?)"
    check "and the installer is never started" \
        "$(! grep -q '^installfog' "$calls"; echo $?)"
    check "and the checkout is not moved either" \
        "$(! grep -q 'checkout' "$calls"; echo $?)"
    # The refusal still has to EXIST, or a headless run would silently go
    # unattended. Checked in the source, since it cannot be reached here.
    check "the no-terminal refusal is still present in the script" \
        "$(sed 's/#.*//' "$SRC" | grep -q 'exec < /dev/tty'; echo $?)"
fi

# ---------------------------------------------------------------------------
# It reverts nothing, and says so.
# ---------------------------------------------------------------------------
d="$work/failing"; mkinstall "$d"
: > "$calls"
out=$( export PATH="$stubs:$PATH" CALLS="$calls" fogprogramdir="$work/opt" FAKE_INSTALL_STATUS=9
       cd "$work" && bash "$sut" --git-path "$d" --channel beta --yes 2>&1 )
check "a failed install propagates the installer's exit status" \
    "$([[ $? -eq 9 ]]; echo $?)"
check "it does not reset the checkout itself" \
    "$(! grep -q 'reset --hard aaaa' "$calls"; echo $?)"
check "it names the commit to reset to instead" \
    "$(grep -q 'reset --hard aaaaaaaa' <<< "$out"; echo $?)"
check "and mentions revertfog.sh, because the database may already have moved" \
    "$(grep -q 'revertfog.sh' <<< "$out"; echo $?)"

# ---------------------------------------------------------------------------
# rcBranch, against a REAL remote.
#
# Everything above runs with a git stub whose ls-remote exits 0 saying nothing,
# so none of it can see what this catches: a bare `rc-*` pattern matches the
# TAIL of a ref at slash boundaries, so ls-remote also returns
# refs/heads/feat/rc-anything. That would send a 1.5 server across to a feature
# branch on --channel rc -- the very channel this script recommends for the
# crossing -- while reporting it as the current release candidate.
# ---------------------------------------------------------------------------
rcfn=$(awk '/^rcBranch\(\) \{/ { grab = 1 } grab { print } grab && /^\}/ { exit }' "$SRC")
if ! grep -q '^rcBranch() {' <<< "$rcfn"; then
    echo "FAIL: could not find rcBranch() in bin/updatefog.sh." >&2
    echo "  If it moved or was renamed, point this test at it -- do not" >&2
    echo "  delete the assertions." >&2
    exit 1
fi
eval "$rcfn"

rcremote="$work/rc.git"
rcseed="$work/rcseed"
git init -q --bare "$rcremote"
git init -q "$rcseed"
git -C "$rcseed" config user.email t@example.invalid
git -C "$rcseed" config user.name t
echo x > "$rcseed/f"
git -C "$rcseed" add f && git -C "$rcseed" commit -qm seed
rcpublish() {
    git -C "$rcseed" branch -f "$1" >/dev/null 2>&1
    git -C "$rcseed" push -q "$rcremote" "$1" >/dev/null 2>&1
}
repo="$rcremote"

rcpublish feat/rc-update-channel
check "a feat/rc-* branch is not offered as a release candidate" \
    "$([[ -z $(rcBranch) ]]; echo $?)"
check "and rcBranch reports nothing published" \
    "$(! rcBranch >/dev/null 2>&1; echo $?)"

rcpublish rc-1.6.2
rcpublish rc-1.6.10
check "rc-1.6.10 beats rc-1.6.2 and the decoy (version order, not lexical)" \
    "$([[ $(rcBranch) == rc-1.6.10 ]]; echo $?)"

# ---------------------------------------------------------------------------
# It stays standalone. The moment it sources the installer library it stops
# being portable to a branch that does not have the same one.
# ---------------------------------------------------------------------------
body=$(sed 's/#.*//' "$SRC")
check "it sources nothing" \
    "$(! grep -qE '^[[:space:]]*(\.|source)[[:space:]]+\.\./lib' <<< "$body"; echo $?)"

echo
if [[ $FAIL -eq 0 ]]; then
    echo "PASS ($PASS assertions)"
    exit 0
fi
echo "FAIL ($FAIL of $((PASS + FAIL)) assertions)"
exit 1
