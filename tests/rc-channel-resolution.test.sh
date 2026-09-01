#!/bin/bash
#
# The rc channel resolves to the CURRENT release candidate.
#
#   tests/rc-channel-resolution.test.sh
#
# rc is the only channel that is a query rather than a constant. stable,
# patches and beta each name a branch that always exists; rc-* is a family
# whose members come and go, so channelToBranch has to ask the remote which one
# is current.
#
# Two things that follow from that, and that this pins:
#
#   * The answer is the highest VERSION, not the newest commit. A lexical sort
#     puts rc-1.6.2 above rc-1.6.10, which is backwards for every series that
#     runs past nine; and a date sort would follow a late fix pushed to a
#     superseded RC. It also has to be answerable from `git ls-remote`, which
#     reports no dates at all -- bin/bootstrap.sh resolves this before it has a
#     clone to run for-each-ref against.
#
#   * "No release candidate published" is a real, ordinary state -- it is true
#     of origin right now -- and it is NOT the same failure as a misspelled
#     channel. channelToBranch returns 2 for it and 1 for an unknown name, so
#     the caller can say something true instead of blaming the admin.
#
# Runs against a local throwaway repository used as a remote, so it needs git
# but no network, no root, and no FOG install.
#
# Usage: bash tests/rc-channel-resolution.test.sh
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

lift() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "$functions"
}

snippet="$(lift normalizeChannel)
$(lift channelToBranch)
$(lift rcBranch)
$(lift branchToChannel)"

for fn in normalizeChannel channelToBranch rcBranch branchToChannel; do
    if ! grep -q "^${fn}() {" <<< "$snippet"; then
        echo "FAIL: could not find ${fn}() in lib/common/functions.sh." >&2
        echo "  If it moved or was renamed, point this test at it -- do not" >&2
        echo "  delete the assertions." >&2
        exit 1
    fi
done
eval "$snippet"

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# A bare repository standing in for origin, and a working clone of it standing
# in for the FOG checkout.
#
# BOTH are needed, because rcBranch asks the CHECKOUT'S OWN origin first and
# only falls back to the built-in constant when there is no checkout to ask
# (which is bin/bootstrap.sh's case, before it has cloned anything). Setting
# FOG_git_remote alone is not enough: with FOG_git_path unset, `git -C ""`
# resolves against the current directory -- which, when this suite runs from
# the FOG repo, is a real checkout whose origin is the real fogproject, so
# every lookup below would silently query the network and find no rc branch.
FOG_git_remote="$work/remote.git"
seed="$work/seed"
git init -q --bare "$FOG_git_remote"
git init -q "$seed"
git -C "$seed" config user.email t@example.invalid
git -C "$seed" config user.name t
echo x > "$seed/f"
git -C "$seed" add f && git -C "$seed" commit -qm seed
git -C "$seed" remote add origin "$FOG_git_remote"

# The checkout rcBranch will interrogate. Its origin is the fixture, so nothing
# here touches the network.
FOG_git_path="$work/checkout"
git clone -q "$FOG_git_remote" "$FOG_git_path" 2>/dev/null

publish() {
    git -C "$seed" branch -f "$1" >/dev/null 2>&1
    git -C "$seed" push -q origin "$1" >/dev/null 2>&1
}

# ---------------------------------------------------------------------------
# Nothing published: a known channel with nothing to point at.
# ---------------------------------------------------------------------------
publish stable
publish working-1.6
# A branch whose LAST PATH SEGMENT starts with rc-, which is not a release
# candidate. ls-remote matches a pattern against the tail of a ref at slash
# boundaries, so a bare `rc-*` pattern matches this and rcBranch used to offer
# it as the current RC -- confirmed against origin, where it returned
# feat/rc-update-channel. Published FIRST so it is the only candidate here and
# stays in the way for every assertion below.
publish feat/rc-update-channel

out=$(channelToBranch rc); st=$?
check "rc with no release candidate returns 2, not 1" \
    "$([[ $st -eq 2 ]]; echo $?)"
check "a feat/rc-* branch is not mistaken for a release candidate" \
    "$([[ -z $(rcBranch) ]]; echo $?)"
check "and prints nothing to stand in for a branch name" \
    "$([[ -z $out ]]; echo $?)"

# The distinction is only useful if a genuine typo still says something else.
channelToBranch nonsense >/dev/null 2>&1
check "an unknown channel still returns 1" \
    "$([[ $? -eq 1 ]]; echo $?)"

# The three constant channels must be unaffected by any of this.
check "stable still resolves without asking the remote" \
    "$([[ $(channelToBranch stable) == stable ]]; echo $?)"
check "patches still resolves" \
    "$([[ $(channelToBranch patches) == dev-branch ]]; echo $?)"
check "beta still resolves" \
    "$([[ $(channelToBranch beta) == working-1.6 ]]; echo $?)"

# ---------------------------------------------------------------------------
# One published.
# ---------------------------------------------------------------------------
publish rc-1.6.0
check "a single rc-* branch resolves to itself" \
    "$([[ $(channelToBranch rc) == rc-1.6.0 ]]; echo $?)"

# ---------------------------------------------------------------------------
# Several published. This is the assertion the whole design turns on.
# ---------------------------------------------------------------------------
publish rc-1.6.1
publish rc-1.6.2
check "the newest of several is chosen" \
    "$([[ $(channelToBranch rc) == rc-1.6.2 ]]; echo $?)"

# Pushed LAST but lowest -- a date or push-order answer picks this one, a
# version sort does not.
publish rc-1.6.10
check "rc-1.6.10 beats rc-1.6.2 (version order, not lexical)" \
    "$([[ $(channelToBranch rc) == rc-1.6.10 ]]; echo $?)"

check "and it does not outrank a real one once one exists" \
    "$([[ $(channelToBranch rc) == rc-1.6.10 ]]; echo $?)"

publish rc-1.5.99
check "a stale lower series pushed later does not win" \
    "$([[ $(channelToBranch rc) == rc-1.6.10 ]]; echo $?)"

# Nothing about the query may leak into the other channels.
check "beta is unaffected by published release candidates" \
    "$([[ $(channelToBranch beta) == working-1.6 ]]; echo $?)"

# ---------------------------------------------------------------------------
# The checkout's own origin wins over the built-in constant.
#
# A server installed from a fork or an internal mirror must not be told about a
# release candidate its own origin does not carry: gitUpdateToBranch would then
# `git checkout` a branch that is not there and fail. An air-gapped server must
# not reach for github.com at all.
# ---------------------------------------------------------------------------
fork="$work/fork.git"
git init -q --bare "$fork"
git -C "$seed" remote add fork "$fork" 2>/dev/null
git -C "$seed" push -q fork stable >/dev/null 2>&1
git -C "$seed" push -q fork rc-1.5.99 >/dev/null 2>&1

forkco="$work/forkco"
git clone -q "$fork" "$forkco" 2>/dev/null
check "a checkout cloned from a fork resolves against THAT fork"     "$([[ $(FOG_git_path="$forkco" channelToBranch rc) == rc-1.5.99 ]]; echo $?)"

check "and the built-in constant does not override it"     "$([[ $(FOG_git_path="$forkco" FOG_git_remote="$FOG_git_remote" channelToBranch rc) == rc-1.5.99 ]]; echo $?)"

# No checkout at all -- bootstrap.sh's case -- falls back to the constant.
check "with no checkout to ask, the configured remote is used"     "$([[ $(FOG_git_path="$work/nothing-here" channelToBranch rc) == rc-1.6.10 ]]; echo $?)"

# ---------------------------------------------------------------------------
# The inverse, and the vocabulary.
# ---------------------------------------------------------------------------
check "an rc-* branch maps back to the rc channel" \
    "$([[ $(branchToChannel rc-1.6.10) == rc ]]; echo $?)"
check "rc is its own canonical spelling" \
    "$([[ $(normalizeChannel rc) == rc ]]; echo $?)"
check "a feature branch still has no channel" \
    "$(! branchToChannel feature-x >/dev/null 2>&1; echo $?)"

# ---------------------------------------------------------------------------
# The callers say the right thing about each failure.
# ---------------------------------------------------------------------------
updater=$(sed 's/#.*//' "$root/bin/updatefog.sh")

check "updatefog.sh handles the 'nothing published' case separately" \
    "$(grep -q '2)' <<< "$updater"; echo $?)"

check "it says no release candidate is published, not 'unknown channel'" \
    "$(grep -qi 'No release candidate is currently published' <<< "$updater"; echo $?)"

check "updatefog.sh offers rc in its help" \
    "$(grep -q 'stable|patches|beta|rc' <<< "$updater"; echo $?)"

check "installfog.sh --channel accepts rc" \
    "$(sed 's/#.*//' "$root/bin/installfog.sh" | grep -qi 'stable, patches, beta, rc'; echo $?)"

printf '\n%s: %d passed, %d failed\n' "$(basename "$0")" "$pass" "$fail"
[[ $fail -eq 0 ]]
