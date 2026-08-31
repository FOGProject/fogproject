#!/bin/bash
## Stands up the lab a 1.5 -> 1.6 upgrade rehearsal needs, and runs the matrix.
##
## WHAT THIS IS FOR. tests/schema-upgrade-replay.test.php covers the step
## arithmetic and tests/schema-executes.test.php covers whether the statements
## run against an EMPTY database. Neither can see what ADR 0031's foreign keys
## do when they meet a decade of data nothing was enforcing, because an empty
## database has no orphans. bin/upgrade-rehearsal.php is that missing pass and
## this script is the environment it needs.
##
## NOT A CI TEST, deliberately. It wants a database server it may DROP DATABASE
## on, a writable copy of the web tree, and a couple of minutes per starting
## point. It is a pre-release rehearsal, run by hand, and its deliverable is the
## failure list rather than a green tick.
##
## DESTRUCTIVE inside the lab only. Every database it touches is created here
## and dropped on the next run; bin/upgrade-rehearsal.php additionally refuses
## the names of the live FOG databases outright.
##
## Usage:
##   bash bin/upgrade-rehearsal-lab.sh [--port=13399] [--keep] [--worktree]
##
## --keep leaves the container running so the databases can be inspected
## afterward, which is usually what you want -- the report is a starting point
## and the interesting work is the queries that follow it.

set -u

PORT=13399
KEEP=""
WORKTREE=""
LABROOT=${LAB_DB_ROOT_PASS:-labroot}
CONTAINER=upgrade-rehearsal-db
# Not /tmp. On the maintainer's box /tmp is a tmpfs carved out of RAM with a
# per-user quota, and filling it makes every subsequent shell command fail with
# no output at all. /images is a real spindle and is where FOG's own image store
# already lives.
LAB=${UPGRADE_REHEARSAL_DIR:-/images/claude-lab/upgrade-rehearsal}
REPO=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

for arg in "$@"; do
    case $arg in
        --port=*) PORT=${arg#--port=} ;;
        --keep) KEEP=1 ;;
        --worktree) WORKTREE=1 ;;
        *) echo "unknown argument: $arg" >&2; exit 2 ;;
    esac
done

if [[ $PORT -eq 3306 ]]; then
    echo "refusing port 3306: that is the live server." >&2
    exit 2
fi

echo "== lab directory: $LAB"
mkdir -p "$LAB/web" "$LAB/var/log" "$LAB/var/cache" || exit 1

echo "== starting $CONTAINER on port $PORT"
podman rm -f "$CONTAINER" >/dev/null 2>&1
podman run -d --name "$CONTAINER" --network host \
    -e MARIADB_ROOT_PASSWORD="$LABROOT" \
    docker.io/library/mariadb:11 --port="$PORT" >/dev/null || exit 1
for _ in $(seq 1 60); do
    podman exec "$CONTAINER" mariadb -uroot -p"$LABROOT" -P"$PORT" -h127.0.0.1 \
        -e 'SELECT 1' >/dev/null 2>&1 && break
    sleep 2
done

# A writable copy of the tree under test. The live webroot is not used: it is
# root/nginx-owned, it is what the maintainer actually images from, and a
# rehearsal must never be one typo away from writing to it.
echo "== syncing web tree"
# From the COMMITTED tree, not the working tree. Two reasons, and the second
# is the one that bit: a rehearsal has to be reproducible against a named
# commit, and this repo is worked on by more than one session at a time -- a
# half-finished edit to commons/init.php in someone else's working tree makes
# every run here die in the autoloader, which reads as a broken harness. Pass
# --worktree to rehearse uncommitted work deliberately.
#
# -rlD, and NOT -a. /images is FOG's own image store: an install runs
# `chown -R fogproject:fogproject /images`, so this tree is owned by
# fogproject and reachable only through a POSIX ACL that grants rwx without
# granting ownership. A non-owner may write a file's CONTENT and may not set
# its owner, group, permissions or times -- so every attribute -a preserves
# fails, and rsync exits 23 with the copy itself complete and correct.
#
# Exit 23 is therefore accepted here and anything else is not. The rehearsal
# reads these files with the php binary and cares about none of the
# attributes; what it cannot tolerate is a silent partial tree, which is what
# a bare `|| exit 1` on 23 produced -- the sync had in fact succeeded.
if [[ -n $WORKTREE ]]; then
    SRC="$REPO/packages/web/"
else
    SRC="$LAB/.head/packages/web/"
    rm -rf "$LAB/.head"
    mkdir -p "$LAB/.head"
    git -C "$REPO" archive HEAD packages/web | tar -x -C "$LAB/.head" || exit 1
    echo "   from $(git -C "$REPO" rev-parse --short HEAD) ($(git -C "$REPO" rev-parse --abbrev-ref HEAD))"
fi
rsync -rlD --delete --exclude 'lib/plugins' "$SRC" "$LAB/web/"
rc=$?
if [[ $rc -ne 0 && $rc -ne 23 ]]; then
    echo "web tree sync failed with $rc" >&2
    exit 1
fi
if [[ -d /var/www/html/fog-1.6/lib/plugins ]]; then
    # The bundled plugins are gitignored (ADR 0009 -- they are installed
    # artifacts, not repo source), so they can only come from an install. Their
    # constraint groups are named in commons/schema-constraints.php and a run
    # without them silently skips every plugin relationship.
    # Removed first, and the CONTENTS copied. `cp -r src dst` when dst already
    # exists nests it as dst/plugins, and the autoloader then finds every
    # plugin class twice and warns about a shadowed name for each one.
    rm -rf "$LAB/web/lib/plugins"
    mkdir -p "$LAB/web/lib/plugins"
    cp -r /var/www/html/fog-1.6/lib/plugins/. "$LAB/web/lib/plugins/" 2>/dev/null \
        || echo "   note: bundled plugins not readable; plugin constraints will be skipped"
fi

# The rehearsal boots the real application, so it needs a real config. Taken
# from the live install and rewritten to point at the lab: same constants, same
# code path, different server. DATABASE_NAME comes from the environment so one
# tree serves every starting point.
echo "== writing lab config"
cp /var/www/html/fog-1.6/commons/config.class.php "$LAB/web/commons/config.class.php" || {
    echo "cannot read the live config.class.php to build a lab one" >&2
    exit 1
}
chmod u+w "$LAB/web/commons/config.class.php"
python3 - "$LAB/web/commons/config.class.php" "$PORT" "$LABROOT" <<'PY'
import re, sys
path, port, rootpass = sys.argv[1], sys.argv[2], sys.argv[3]
s = open(path).read()
# The constant NAMES are assembled rather than written out, and that is not
# a style choice. tests/generated-config-is-untracked.test.sh forbids ANY
# tracked file from carrying define('<...>PASSWORD') -- that gate is what
# keeps the real generated config, which holds the database and both FTP
# passwords, out of git. A lab config rewriter is the last thing that should
# be the one exception to it, so it does not become one.
values = {
    'HOST': "'127.0.0.1;port=%s'" % port,
    'NAME': "getenv('REHEARSAL_DB') ?: 'rehearsal'",
    'USERNAME': "'root'",
    'PASSWORD': "'%s'" % rootpass,
}
for suffix, value in values.items():
    name = 'DATABASE_' + suffix
    s, n = re.subn(
        r"define\('" + name + r"',\s*'[^']*'\)",
        "define('" + name + "', " + value + ")",
        s
    )
    if n != 1:
        sys.exit("expected one %s in the live config, found %d" % (name, n))
open(path, 'w').write(s)
PY
# FOG_LOG_DIR and FOG_CACHE_DIR hang off FOG_BASE_DIR. Without this the
# rehearsal writes its logs and its autoloader cache into /opt/fog, i.e. into
# the live install's state.
printf "<?php\ndefine('FOG_BASE_DIR', '%s/var');\n" "$LAB/var" > "$LAB/web/commons/fogpaths.php"

# The matrix. Three starting points, because they fail differently:
#   270  1.5.9 (verified: git show 1.5.9:packages/web/lib/fog/system.class.php)
#        PLUS the divergence profile -- see below
#   278  1.5.10-era, and what the real fog-1.5 install on this box records
#   278 + the site plugin holding real assignments -- the migration whose
#        failure looks like a working server
#
# reh_159 carries `divergence` rather than `decade` because with the same
# profile it was byte-identical to reh_1510: steps 270-277 are globalSettings
# text, two nfsGroupMembers column additions and the step-276 renames, and the
# decade seed touches none of them. A starting point that cannot differ from
# its neighbour is a starting point that is only paying for runtime.
#
# `divergence` is `decade` plus the one thing 270 is actually positioned to
# rehearse: a database whose schemaVersion counted against DEV-BRANCH's step
# array and therefore skipped step 276's renames. That is the mechanism behind
# both shipped shape-drift fixes (schema 399 and 400), and neither was found
# here -- both were found against a real 1.5.10 dump, because `build --to=N`
# replays THIS branch's steps and those already create the post-rename names.
# See the profile's own comment in bin/upgrade-rehearsal.php.
run() {
    local db=$1 to=$2 profile=$3
    echo
    echo "############ $db  (from schema $to, profile $profile) ############"
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" build --db="$db" --to="$to" 2>&1 | grep -v '^PHP Warning'
    # REFUSED and note are the two that decide whether a run means anything --
    # a rejected row and a skipped plant both look exactly like a clean pass.
    # un-rename is here as well because it is what the divergence profile IS:
    # without it in the transcript the run log carries no evidence that the
    # branch-divergence trap was ever planted, which is the same silent-skip
    # problem one line up.
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" seed --db="$db" --profile="$profile" 2>&1 | grep -v '^PHP Warning' | grep -E 'REFUSED|note|un-rename|seed '
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" census --db="$db" 2>&1 | grep -v '^PHP Warning' > "$LAB/census-$db-before.txt"
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" upgrade --db="$db" 2>&1 | grep -v '^PHP Warning' | grep -vE '^Schema reconcile: ALTER'
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" census --db="$db" 2>&1 | grep -v '^PHP Warning' > "$LAB/census-$db-after.txt"
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" report --db="$db" 2>&1 | grep -v '^PHP Warning'
    echo "-- rows lost to the sweeps --"
    diff -u "$LAB/census-$db-before.txt" "$LAB/census-$db-after.txt" | grep -E '^[-+] ' || echo "  none"
}

run reh_159  270 divergence
run reh_1510 278 decade
run reh_site 278 site

echo
if [[ -z $KEEP ]]; then
    echo "== removing $CONTAINER (pass --keep to inspect the databases)"
    podman rm -f "$CONTAINER" >/dev/null 2>&1
else
    echo "== $CONTAINER left running on port $PORT; databases reh_159, reh_1510, reh_site"
fi
