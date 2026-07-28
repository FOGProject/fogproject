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

verbegin=""
channel="$current_channel"
trunkversion="$current_version"

case "$branchon" in
    dev)
        tagversion=$(git describe --tags "$gitcom")
        baseversion=${tagversion%.*}
        trunkversion="${baseversion}.${gitcount}"
        channel="Patches"
        ;;
    stable)
        tagversion=$(git describe --tags "$gitcom")
        baseversion=${tagversion%.*}
        gitcount=$(git rev-list master..dev-branch --count) # Get the gitcount from dev-branch instead
        trunkversion="${baseversion}.${gitcount}"
        channel="Patches"
        ;;
    working)
        verbegin="${branchend}.0-beta"
        trunkversion="${verbegin}.${gitcount}"
        channel="Beta"
        ;;
    rc)
        channel="Release Candidate"
        version_prefix="${branchend}.0-RC"
        n=$(printf '%s\n' "$current_version" | sed -n "s/^${version_prefix}-\([0-9][0-9]*\)\$/\1/p")
        if [ -n "$n" ]; then
            last_rc_version=$n
            next_rc_version=$((last_rc_version + 1))
            trunkversion="${version_prefix}-${next_rc_version}"
        else
            trunkversion="${version_prefix}-1"
        fi
        ;;
    feature)
        verbegin="${branchend}.0-feature"
        trunkversion="${verbegin}.${gitcount}"
        channel="Feature"
        ;;
esac

sed -i "s/define('FOG_VERSION',.*);/define('FOG_VERSION', '$trunkversion');/g" "$system_file"
sed -i "s/define('FOG_CHANNEL',.*);/define('FOG_CHANNEL', '$channel');/g" "$system_file"
