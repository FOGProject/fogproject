#!/usr/bin/env sh
#
# Recomputes FOG_VERSION/FOG_CHANNEL for the given (or current) branch and
# rewrites packages/web/lib/fog/system.class.php in place.
#
# Deliberately does NOT `git add`, `git commit`, or assume it's running
# mid-commit - it only touches the working tree. That's what lets it be
# shared between .githooks/pre-commit (which stages the result itself,
# mid-commit) and a CI job (which has no commit in progress and stages/
# commits/pushes on its own afterward).
#
# Usage: fog-version.sh [branch-name]
#   branch-name defaults to the currently checked out branch.

set -e

project_dir=$(git rev-parse --show-toplevel)
system_file="$project_dir/packages/web/lib/fog/system.class.php"

gitbranch="${1:-$(git branch --show-current)}"

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

drifted=false
[ "$trunkversion" != "$current_version" ] && drifted=true
# dev-branch and stable deliberately carry no FOG_CHANNEL line at all (a
# clean version string, no channel text) - current_channel is empty there
# by design, not by omission, so it's excluded from drift detection rather
# than treated as permanently "wrong".
if [ -n "$current_channel" ] && [ "$channel" != "$current_channel" ]; then
    drifted=true
fi

if [ "$drifted" = true ]; then
    # What's committed disagrees, so whatever calls this script is about
    # to add one more real commit to this branch to fix it. Recompute with
    # gitcount+1 - the count that will actually be true once that commit
    # exists - so the fix is correct the instant it lands instead of being
    # wrong by exactly the commit that made it. Without this, the very next
    # check finds "drift" again and fixes it again, forever.
    compute_version "$((gitcount + 1))"
fi

sed -i "s/define('FOG_VERSION',.*);/define('FOG_VERSION', '$trunkversion');/g" "$system_file"
sed -i "s/define('FOG_CHANNEL',.*);/define('FOG_CHANNEL', '$channel');/g" "$system_file"
