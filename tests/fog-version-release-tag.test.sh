#!/bin/bash
#
# Pins that FOG_VERSION takes its base from a RELEASE tag, never from the
# newest tag of any name.
#
#   tests/fog-version-release-tag.test.sh
#
# .githooks/lib/fog-version.sh finds the newest tagged commit and strips the
# last dot-field from its tag to get the base (1.5.10.2253 -> 1.5.10). On
# 2026-09-06 the tag archive/feature-fog2-gui was pushed and became the newest
# tag. The dev arm then computed archive/feature-fog2-gui.2479, the slash broke
# the sed in apply-fog-version.sh, and fog-workflows' daily sweep failed on
# dev-branch every day after.
#
# Builds a throwaway repo with one release tag and a NEWER non-release tag, and
# checks that the dev and stable arms still report the release base.
#
# Exit 0 = pass, 1 = fail.

set -u

repo=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
script="$repo/.githooks/lib/fog-version.sh"

rc=0
bad() {
    printf 'FAIL: %s\n' "$1" >&2
    rc=1
}

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
fx="$tmp/fixture"

# Commit dates are set explicitly: rev-list orders tags by commit date, and
# commits made in the same second would leave "newest" undefined.
commit() {
    GIT_COMMITTER_DATE="$1" GIT_AUTHOR_DATE="$1" \
        git -C "$fx" commit -q --allow-empty -m "$2"
}

git init -q -b master "$fx"
git -C "$fx" config user.email t@example.invalid
git -C "$fx" config user.name t
git -C "$fx" config commit.gpgsign false
git -C "$fx" config tag.gpgsign false

# The script reads the committed version from whichever System file its branch
# uses, so the fixture carries both.
mkdir -p "$fx/.githooks/lib" "$fx/packages/web/src/Base" "$fx/packages/web/lib/fog"
cp "$script" "$fx/.githooks/lib/fog-version.sh"
for f in packages/web/src/Base/System.php packages/web/lib/fog/system.class.php; do
    printf "<?php\ndefine('FOG_VERSION', '1.5.10.1');\n" > "$fx/$f"
done
git -C "$fx" add -A
commit '2020-01-01T00:00:00Z' 'master'

git -C "$fx" checkout -q -b dev-branch
commit '2020-01-02T00:00:00Z' 'release'
git -C "$fx" tag 1.5.10.1
commit '2020-01-03T00:00:00Z' 'patch'

# An archived feature branch, tagged the way archive/feature-fog2-gui is:
# annotated, and on a commit newer than the release.
git -C "$fx" checkout -q -b feature-x master
commit '2020-02-01T00:00:00Z' 'feature work'
git -C "$fx" tag -a archive/feature-x -m 'archive'
git -C "$fx" checkout -q dev-branch

count=$(git -C "$fx" rev-list master..dev-branch --count)
want="1.5.10.$count"

for arm in dev-branch stable; do
    got=$(cd "$fx" && sh .githooks/lib/fog-version.sh "$arm" head 2>/dev/null | sed -n '1p')
    [ "$got" = "$want" ] || bad "$arm computed '$got', expected '$want'. A non-release tag newer than the last release must not become the base version."
done

exit $rc
