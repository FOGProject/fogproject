#!/bin/bash
#
# The upgrade rehearsal, reduced to one starting point, as a regression gate.
#
#   tests/upgrade-rehearsal-ci.test.sh
#
# WHAT THIS IS, AND WHAT IT IS NOT. bin/upgrade-rehearsal-lab.sh runs the full
# matrix by hand and its deliverable is a failure LIST -- three starting points,
# a container it may DROP DATABASE on, and a report to read. That is a
# pre-release exercise and it stays one.
#
# This is the other half of the trade. A fixture nobody runs decays into a
# fixture that does not work, and the rehearsal is exactly the kind of harness
# that rots quietly: it boots the whole application, so any change to the
# autoloader, the config contract or the schema runner breaks it, and nothing
# would say so until someone reached for it before a release and found it dead.
#
# So this runs ONE starting point (278 / decade), and asserts the KNOWN result
# rather than a clean one. Two open findings are expected here and are recorded
# in the baseline as expected:
#
#   fk_hostMAC_hmHostID            errno 150, structural (the seed's bigint)
#   fk_nfsGroupMembers_ngmGroupID  1452, one orphan (RESTRICT, not swept)
#
# Asserting zero would be a lie about the state of the code, and asserting
# nothing would be a gate nobody can fail. Asserting the baseline catches the
# three things that matter: a fix that regresses, a seed that goes vacuous, and
# a NEW failure appearing.
#
# THE BASELINE LIVES IN THE REPO, not in the workflow. tests/fixtures/
# upgrade-rehearsal-baseline.txt is the expected `report` output, so a change
# that legitimately moves the numbers -- adding a constraint, fixing one of the
# two above -- updates it in the SAME commit and the diff is visible in review.
# A number hardcoded in a CI workflow goes stale in another repository, where
# nobody making the change is looking.
#
# NO LIVE INSTALL NEEDED. `makeconfig` writes the lab config, so this runs on a
# clean checkout. commons/config.class.php is generated and gitignored, which is
# why the lab script's copy-the-live-one approach could never work in CI.
#
# SKIPS, LOUDLY, when there is no database. Exit 0 with a SKIP line, matching
# the FOG_TEST_DSN convention the other DB-backed tests use -- so the local
# tests/*.test.sh sweep stays green on a machine with no server.
#
# Environment:
#   REHEARSAL_CI_HOST   e.g. 127.0.0.1;port=3306   (required; else SKIP)
#   REHEARSAL_CI_USER   default root
#   REHEARSAL_CI_PASS   default empty
#   REHEARSAL_CI_DB     default reh_ci
#
# Exit status 0 = pass or skip, 1 = fail.

set -u

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
BASELINE="$HERE/fixtures/upgrade-rehearsal-baseline.txt"

HOST="${REHEARSAL_CI_HOST:-}"
if [[ -z $HOST ]]; then
    echo "upgrade rehearsal: SKIP (REHEARSAL_CI_HOST not set; no database)"
    exit 0
fi
USER_="${REHEARSAL_CI_USER:-root}"
PASS="${REHEARSAL_CI_PASS:-}"
DB="${REHEARSAL_CI_DB:-reh_ci}"

# The live-database guard in upgrade-rehearsal.php refuses fog/fog-1.5/fog-1.6
# outright, but this wrapper picks a default, so it must not be able to pick a
# dangerous one even if someone overrides it.
case $DB in
    fog|fog-1.5|fog-1.6)
        echo "upgrade rehearsal: FAIL -- refusing live database name '$DB'"
        exit 1
        ;;
esac

[[ -f $BASELINE ]] || { echo "upgrade rehearsal: FAIL -- no baseline at $BASELINE"; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# A COPY, never packages/web itself: makeconfig writes commons/config.class.php,
# and that path is gitignored precisely because a real one holds passwords.
# Writing into the checkout would leave a stray config behind for whatever runs
# next, which on a developer's machine is their own tree.
cp -r "$REPO/packages/web" "$WORK/web" || { echo "upgrade rehearsal: FAIL -- could not copy the web tree"; exit 1; }
mkdir -p "$WORK/var/log" "$WORK/var/cache"
printf "<?php\ndefine('FOG_BASE_DIR', '%s/var');\n" "$WORK" > "$WORK/web/commons/fogpaths.php"

R() { php "$REPO/bin/upgrade-rehearsal.php" "$WORK/web" "$@" 2>&1 | grep -v '^PHP Warning'; }

echo "upgrade rehearsal: one starting point (schema 278, profile decade)"

R makeconfig --host="$HOST" --user="$USER_" --pass="$PASS" >/dev/null || {
    echo "  FAIL  makeconfig did not write a lab config"; exit 1; }

if ! R build --db="$DB" --to=278 | grep -q 'landed on schema 278'; then
    echo "  FAIL  build did not land on schema 278"; exit 1
fi

seedout="$(R seed --db="$DB" --profile=decade)"
# A REFUSED row is the vacuous case: the starting schema already enforces what
# the upgrade is supposed to introduce, so every later assertion passes for the
# wrong reason. This is the single most important check in the file.
if grep -q 'REFUSED' <<<"$seedout"; then
    echo "  FAIL  the seed was refused a row, so this run proves nothing:"
    grep 'REFUSED' <<<"$seedout" | sed 's/^/        /'
    exit 1
fi
echo "  ok    every seeded row landed (no REFUSED)"

R upgrade --db="$DB" >/dev/null

# The header line carries the database name and the current FOG_SCHEMA, so
# baselining it would redden every pull request that adds a schema step --
# a change this gate has no opinion about. The version an upgrade lands on
# is already covered by tests/schema-upgrade-replay.test.php, and the build
# step above asserts the STARTING point. What is left is the part this
# fixture exists for: which constraints landed, which did not, and what
# survived that no constraint can express.
got="$(R report --db="$DB" | grep -v '^report ')"
if diff -u "$BASELINE" <(echo "$got") > "$WORK/diff.txt"; then
    echo "  ok    the report matches tests/fixtures/upgrade-rehearsal-baseline.txt"
    echo
    echo "  1 starting point, 0 differences from the baseline"
    exit 0
fi

echo "  FAIL  the report differs from the baseline."
echo "        If this change legitimately moves the numbers, update"
echo "        tests/fixtures/upgrade-rehearsal-baseline.txt in the SAME commit."
echo
sed 's/^/        /' "$WORK/diff.txt"
exit 1
