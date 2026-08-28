#!/bin/sh
# The generated config.class.php can never be committed.
#
# It holds DATABASE_PASSWORD, both FTP passwords and FOG_SCHEMA_INSTALL_TOKEN,
# and this is a public repository. Nothing but .gitignore stands between those
# and a commit, and until this file there was no test on it at all.
#
# WHY IT EXISTS NOW. Config moved from lib/fog/ to commons/ -- lib/fog held
# nothing else once core became PSR-4 under src/. The move was chosen over
# src/Base/Config.php for exactly one reason: .gitignore's rule is the GLOB
# `*config.class.php`, which matches the file at any path, so moving it within
# the tree needs no ignore change and cannot silently stop matching. A
# src/Base/Config.php would have needed a new hand-written path entry.
#
# That argument is only worth anything if the glob stays a glob. Narrow it to
# `/packages/web/lib/fog/config.class.php` -- which looks like a tightening --
# and the protection is gone with nothing to say so.
#
# The installer's write path is READ OUT OF functions.sh rather than named
# here. A test that hardcodes the destination passes happily after someone
# changes where the installer writes, which is the failure it exists to catch.
#
# Usage: sh tests/generated-config-is-untracked.test.sh
set -u
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$root" || exit 1

failures=0
checks=0
check() {
    checks=$((checks + 1))
    if [ "$2" -ne 0 ]; then
        echo "  FAIL  $1"
        failures=$((failures + 1))
    fi
}

# Where configureHttpd() actually redirects the generated heredoc.
dest=$(grep -oE '^\}" > "\$\{webdirdest\}[^"]*config\.class\.php"' \
    lib/common/functions.sh | sed -e 's/^.*\${webdirdest}//' -e 's/"$//')
if [ -z "$dest" ]; then
    echo "FAIL: could not read the config write path out of lib/common/functions.sh."
    echo "      The generation site moved or changed shape -- this test cannot"
    echo "      confirm what it is meant to confirm, so it fails rather than"
    echo "      passing by measuring nothing."
    exit 1
fi
echo "  installer writes: \${webdirdest}${dest}"

# The path the installer writes, plus the legacy one an upgraded server still
# carries and copybacktrunk.sh may still deploy to.
for rel in "packages/web/${dest#/}" packages/web/lib/fog/config.class.php; do
    git check-ignore -q "$rel"
    check "$rel is gitignored" $?
done

# A glob, not a path. This is the property the location argument rests on.
git check-ignore -q packages/web/some/other/place/config.class.php
check ".gitignore matches config.class.php at an arbitrary path (still a glob)" $?

# And nothing of the sort is tracked today.
tracked=$(git ls-files | grep -c 'config\.class\.php$')
check "no config.class.php is tracked (found $tracked)" \
    "$([ "$tracked" -eq 0 ]; echo $?)"

# The constants themselves, in case one is ever pasted into a tracked file.
#
# DERIVED from the installer's own heredoc, for the same reason the destination
# above is: a hardcoded list passes happily after someone adds a fifth secret,
# which is the failure this exists to catch. It was already wrong when written
# -- the list named three and functions.sh writes four, so a tracked file
# defining TFTP_FTP_PASSWORD would have gone unnoticed.
secrets=$(grep -oE "define\('[A-Z_]*(PASSWORD|TOKEN|SECRET)[A-Z_]*'" \
    lib/common/functions.sh \
    | sed -e "s/^define('//" -e "s/'$//" | sort -u | paste -sd '|' -)
check "the installer's secret list was found (got '$secrets')" \
    "$([ -n "$secrets" ]; echo $?)"

leaked=$(git grep -lE "define\('($secrets)'" \
    -- ':!lib/common/functions.sh' ':!tests/' 2>/dev/null | head -5)
check "no tracked file defines the generated secrets${leaked:+ ($leaked)}" \
    "$([ -z "$leaked" ]; echo $?)"

if [ "$failures" -ne 0 ]; then
    echo "FAIL ($failures of $checks)"
    exit 1
fi
echo "ok  $checks checks passed"
exit 0
