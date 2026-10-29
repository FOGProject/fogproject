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

# A release-candidate tag of the NEXT line. It starts with a digit, so the
# '[0-9]*' filter alone lets it through, and the dev arm then reported 1.6.<count>
# on the 1.5 line. A pre-release tag carries a '-'; a release tag never does.
git -C "$fx" checkout -q -b rc-1.6.0 master
commit '2020-03-01T00:00:00Z' 'rc'
git -C "$fx" tag 1.6.0-RC-1
git -C "$fx" checkout -q dev-branch

count=$(git -C "$fx" rev-list master..dev-branch --count)
want="1.5.10.$count"

for arm in dev-branch stable; do
    got=$(cd "$fx" && sh .githooks/lib/fog-version.sh "$arm" head 2>/dev/null | sed -n '1p')
    [ "$got" = "$want" ] || bad "$arm computed '$got', expected '$want'. A non-release or pre-release tag newer than the last release must not become the base version."
done

# A checkout whose HEAD reaches no release tag, while one exists elsewhere --
# what CI tests: the pull request's merge ref, with the dev arm run against it.
# That must still compute, from the newest release tag in the repository.
fcount=$(git -C "$fx" rev-list master..feature-x --count)
got=$(cd "$fx" && git checkout -q feature-x && sh .githooks/lib/fog-version.sh dev-branch head 2>/dev/null | sed -n '1p')
git -C "$fx" checkout -q dev-branch
[ "$got" = "1.5.10.$fcount" ] || bad "dev arm on a HEAD that reaches no release tag computed '$got', expected '1.5.10.$fcount'."

# A three-part release tag -- 1.6.0, the first release of a line. The 1.5 tags
# carry the build count as a fourth field (1.5.10.2518), so stripping the last
# field gives the base. Stripping it from 1.6.0 gave 1.6 and the dev arm
# reported 1.6.<count>. The installer reads any X.Y.Z version as a pinned FOS
# release tag (downloadfiles() in lib/common/functions.sh), so 1.6.2 asked
# FOGProject/fos for a release that does not exist.
git -C "$fx" checkout -q -b line16 master
commit '2020-04-01T00:00:00Z' 'release 1.6.0'
git -C "$fx" tag -a 1.6.0 -m '1.6.0'
commit '2020-04-02T00:00:00Z' 'patch'
lcount=$(git -C "$fx" rev-list master..line16 --count)
for arm in dev-branch stable; do
    got=$(cd "$fx" && sh .githooks/lib/fog-version.sh "$arm" head 2>/dev/null | sed -n '1p')
    if [ "$arm" = stable ]; then
        # The stable arm counts master..dev-branch, not master..HEAD.
        want16="1.6.0.$(git -C "$fx" rev-list master..dev-branch --count)"
    else
        want16="1.6.0.$lcount"
    fi
    [ "$got" = "$want16" ] || bad "$arm after a three-part release tag computed '$got', expected '$want16'."
done
git -C "$fx" checkout -q dev-branch

# A checkout with NO tags -- CI clones without them. The arms that do not use a
# release tag must still compute. Looking the tag up at the top of the script
# made this exit 129 before any arm ran.
bare="$tmp/notags"
git init -q -b master "$bare"
git -C "$bare" config user.email t@example.invalid
git -C "$bare" config user.name t
git -C "$bare" config commit.gpgsign false
mkdir -p "$bare/.githooks/lib" "$bare/packages/web/src/Base" "$bare/packages/web/lib/fog"
cp "$script" "$bare/.githooks/lib/fog-version.sh"
for f in packages/web/src/Base/System.php packages/web/lib/fog/system.class.php; do
    printf "<?php\ndefine('FOG_VERSION', '1.6.0-beta');\n" > "$bare/$f"
done
git -C "$bare" add -A
GIT_COMMITTER_DATE='2020-01-01T00:00:00Z' GIT_AUTHOR_DATE='2020-01-01T00:00:00Z' \
    git -C "$bare" commit -q -m seed
(cd "$bare" && sh .githooks/lib/fog-version.sh working-1.6 head >/dev/null 2>&1) \
    || bad "working-1.6 arm failed in a checkout with no tags."

exit $rc
