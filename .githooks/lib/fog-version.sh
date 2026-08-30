#!/usr/bin/env sh
#
# Computes what FOG_VERSION/FOG_CHANNEL should be for the given (or
# current) branch and prints three lines: version, channel, and whether
# that differs from what's currently committed (true/false) - so a caller
# can skip writing/committing entirely when nothing needs to change,
# instead of always writing and checking `git diff` afterward.
#
# Deliberately does NOT touch packages/web/lib/fog/system.class.php or any
# other file - purely a function of git state, so it's safe to run ad hoc
# (locally or in CI) without leaving a dirty working tree behind. Pair with
# apply-fog-version.sh to actually write the result somewhere.
#
# Usage: fog-version.sh [branch-name] [mode]
#   branch-name defaults to the currently checked out branch.
#   mode  0     (default) report honestly; if drifted, print the version the
#               next commit should carry.
#         1     a commit is being written right now - always print the
#               version that commit should carry. Used by pre-commit.
#         head  print what the commit that ALREADY EXISTS should carry, with
#               no +1 - i.e. verify rather than write.
#
#   No local hook calls this any more: FOG_VERSION is written only on a base
#   branch, after a merge, by CI. See the header of .githooks/pre-commit.

set -e

project_dir=$(git rev-parse --show-toplevel)
system_file="$project_dir/packages/web/lib/fog/system.class.php"

gitbranch="${1:-$(git branch --show-current)}"
mode="${2:-0}"

gitcom=$(git rev-list --tags --no-walk --max-count=1)

git fetch origin master:master 2>/dev/null || true
gitcount=$(git rev-list master..HEAD --count)

branchon=$(echo "$gitbranch" | awk -F'-' '{print $1}')
branchend=$(echo "$gitbranch" | awk -F'-' '{print $2}')

current_version=$(grep "define('FOG_VERSION'" "$system_file" | sed "s/.*FOG_VERSION', '\([^']*\)');/\1/")
current_channel=$(grep "define('FOG_CHANNEL'" "$system_file" | sed "s/.*FOG_CHANNEL', '\([^']*\)');/\1/")

# Computes trunkversion/channel using the given commit count for the
# count-based branch types (dev/working/feature). rc ignores it (it
# increments off the currently committed suffix, not a commit count) and
# stable overrides it with its own dev-branch-relative count - both
# unchanged from the original formula.
compute_version() {
    count="$1"
    verbegin=""
    channel="$current_channel"
    trunkversion="$current_version"

    case "$branchon" in
        dev)
            tagversion=$(git describe --tags "$gitcom")
            baseversion=${tagversion%.*}
            trunkversion="${baseversion}.${count}"
            channel="Patches"
            ;;
        stable)
            tagversion=$(git describe --tags "$gitcom")
            baseversion=${tagversion%.*}
            count=$(git rev-list master..dev-branch --count) # Get the gitcount from dev-branch instead
            trunkversion="${baseversion}.${count}"
            channel="Patches"
            ;;
        working)
            verbegin="${branchend}.0-beta"
            trunkversion="${verbegin}.${count}"
            channel="Beta"
            ;;
        rc)
            channel="Release Candidate"
            version_prefix="${branchend}.0-RC"
            n=$(printf '%s\n' "$current_version" | sed -n "s/^${version_prefix}-\([0-9][0-9]*\)\$/\1/p")
            if [ -n "$n" ]; then
                trunkversion="${version_prefix}-$((n + 1))"
            else
                trunkversion="${version_prefix}-1"
            fi
            ;;
        feature)
            verbegin="${branchend}.0-feature"
            trunkversion="${verbegin}.${count}"
            channel="Feature"
            ;;
    esac
}

# First pass: what the version already correctly is, if nothing writes a
# new commit right now.
compute_version "$gitcount"

# rc is the one branch type with no count-verifiable answer: it increments
# off whatever suffix is already committed rather than off a commit count,
# so the pass above always returns "one more than what is there". For the
# modes that are about to write a commit that is exactly right. For head
# mode, which asks whether the committed value is already correct, it would
# be a permanent false positive - so there, the committed value stands.
if [ "$mode" = "head" ] && [ "$branchon" = "rc" ]; then
    trunkversion="$current_version"
fi

drifted=false
[ "$trunkversion" != "$current_version" ] && drifted=true
# dev-branch and stable deliberately carry no FOG_CHANNEL line at all (a
# clean version string, no channel text) - current_channel is empty there
# by design, not by omission, so it's excluded from drift detection rather
# than treated as permanently "wrong".
if { [ -n "$current_channel" ] && [ "$channel" != "$current_channel" ]; }; then
    drifted=true
fi
if [ "$mode" = "1" ]; then
    drifted=true
fi

# head mode stops here. Every other mode answers "what should the NEXT
# commit say"; head answers "what should the commit that already exists
# say", so adding 1 for a commit nobody is writing would defeat the whole
# point of it.
if [ "$mode" != "head" ] && [ "$drifted" = true ]; then
    # What's committed disagrees, so whatever calls this script is about
    # to add one more real commit to this branch to fix it. Recompute with
    # gitcount+1 - the count that will actually be true once that commit
    # exists - so the fix is correct the instant it lands instead of being
    # wrong by exactly the commit that made it. Without this, the very next
    # check finds "drift" again and fixes it again, forever.
    #
    # This is also why mode=1 lands one too high on an --amend: an amend
    # REPLACES HEAD rather than extending it, so the +1 counts a commit that
    # will never exist. That was the standing hazard of stamping a version
    # from a local hook, and is one of the reasons no local hook does it now.
    compute_version "$((gitcount + 1))"
fi

printf '%s\n' "$trunkversion"
printf '%s\n' "$channel"
printf '%s\n' "$drifted"
