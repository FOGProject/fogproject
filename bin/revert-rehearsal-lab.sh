#!/bin/bash
## Runs bin/revertfog.sh for real, against the lab that upgrade-rehearsal-lab.sh
## built, and asserts the server came back.
##
## WHY THIS EXISTS. tests/revert-refuses-without-dump.test.sh gates the parts of
## revertfog.sh that can be reasoned about without a server -- the refusals, the
## check ORDER, the 1.5-era sniff. None of that is the same question as whether
## the script actually puts a database back. A revert path nobody has run is a
## comfort, not a plan, and the one that matters is not the clean case: it is a
## revert after an upgrade that died PARTWAY, with the web tree already replaced.
##
## ROOT. revertfog.sh refuses to run as anyone else, correctly -- it moves web
## trees and chowns them. The rehearsal gets there with `unshare -r`, an
## UNPRIVILEGED user namespace in which the caller's uid maps to 0. No real
## privilege is acquired and nothing outside the caller's own reach becomes
## writable; the one visible consequence is that the closing
## `chown SVC_user:apacheuser` fails, which the script already logs and carries
## on from. Running the rehearsal under real sudo would put a script that
## stops daemons and moves directories one shim away from the live install,
## for no gain.
##
## WHAT IS REAL AND WHAT IS STUBBED. Everything runs for real except one thing:
## `systemctl`, which is shimmed to a recorder. stopInitScript() calls it by
## bare name against the SYSTEM's service list, so an unshimmed run would stop
## the FOG daemons on the machine doing the rehearsal -- a live-state change
## outside the lab, and nothing to do with whether the revert works. The shim
## logs every invocation so the run can still assert WHICH services were asked
## for, which is the part under test.
##
## The lab database is on a non-default port and revertfog.sh builds
## `mysql --host=${DB_host}` with no port option -- correctly, FOG has never had
## a port key. MYSQL_TCP_PORT is exported instead: the client's own documented
## default-port variable, honored by both mysql and mysqldump, so not one line
## of the script's connection handling is bypassed or shimmed.
##
## If it were ever NOT honored the run fails safe rather than reaching the
## machine's real MariaDB: the lab root password does not authenticate there,
## so revertfog.sh stops at its pre-flight connection check -- which it makes
## before stopping a daemon or touching a table, deliberately.
##
## DESTRUCTIVE inside the lab only: the lab database, the lab web tree, and a
## $fogprogramdir created here. Nothing under /opt/fog or /var/www is read or
## written -- $fogprogramdir is exported so revertfog.sh's own resolution order
## (env, then /etc/fog/fog.conf, then /opt/fog) stops at the first.
##
## Usage:
##   bash bin/revert-rehearsal-lab.sh [--port=13399] [--case=clean|partial|nodump|all]
##
## THE FIXTURE IS BUILT HERE, not borrowed. It is a database replayed to schema
## 263 -- the last position working-1.6 and dev-branch share -- then seeded, then
## dumped. Two reasons it is not the real 1.5 clone the upgrade rehearsal uses:
##
##   A dump is only a 1.5-era dump if 1.6 code has never touched the database.
##   SchemaReconciler's first pass CREATES every table in the manifest, and it
##   does that without advancing schemaVersion -- so one boot of a 1.6 web tree
##   against a 1.5 database leaves it carrying `userAuths` while still reporting
##   a 1.5 version. The lab's fog-1.5 clone has exactly that history, and
##   revertfog.sh refuses its dump CORRECTLY. That is the script working.
##
##   Replaying to 278 would not help: working-1.6 creates `userAuths` below 278,
##   so any harness fixture built past the 264 divergence is 1.6-shaped by
##   construction. 263 is the last step number that means the same thing on both
##   branches, which is what makes it the honest starting point.
##
## Prerequisite: bin/upgrade-rehearsal-lab.sh has run at least once, leaving the
## container up and a web tree at $LAB/web.

set -u

PORT=13399
CASES=all
LABROOT=${LAB_DB_ROOT_PASS:-labroot}
LAB=${UPGRADE_REHEARSAL_DIR:-/images/claude-lab/upgrade-rehearsal}
REPO=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
DBHOST=127.0.0.1
DB=reh_revert
FIXTURE_SCHEMA=263

for arg in "$@"; do
    case $arg in
        --port=*) PORT=${arg#--port=} ;;
        --case=*) CASES=${arg#--case=} ;;
        *) echo "unknown argument: $arg" >&2; exit 2 ;;
    esac
done

SANDBOX=$LAB/revert
PROG=$SANDBOX/fogprog
SHIM=$SANDBOX/shim
WEBDEST=$SANDBOX/webroot/fog
LOG=$SANDBOX/run.log

fails=0
pass() { printf '  ok    %s\n' "$1"; }
bad()  { printf '  FAIL  %s\n' "$1"; fails=$((fails + 1)); }
check() { if [[ $2 -eq 0 ]]; then pass "$1"; else bad "$1"; fi; }

sql() {
    mariadb --protocol=TCP -h 127.0.0.1 -P "$PORT" -uroot -p"$LABROOT" \
        -N -B --execute="$1" "${2:-}" 2>/dev/null
}

# ---------------------------------------------------------------------------
# The sandbox. Rebuilt every run: a revert MOVES the web tree aside rather than
# deleting it, so a second run over the leftovers of the first would be testing
# the leftovers.
# ---------------------------------------------------------------------------
buildSandbox() {
    rm -rf "$SANDBOX"
    mkdir -p "$PROG" "$SHIM" "$WEBDEST" "$SANDBOX/backups/fogDBbackups"

    # A 1.6 web tree at the destination: PSR-4 under src/, no lib/fog/. This is
    # what a partway-failed upgrade leaves behind and what _isFifteenWebTree()
    # must NOT accept as a restore source.
    mkdir -p "$WEBDEST/src/Base" "$WEBDEST/commons"
    echo '<?php // 1.6' > "$WEBDEST/src/Base/FOGBase.php"
    echo '<?php // 1.6' > "$WEBDEST/commons/init.php"

    # The 1.5 backup: classes under lib/fog/, no src/.
    local backup="$SANDBOX/backups/fog_web_1.5.10.2189.BACKUP"
    rm -rf "$backup"
    mkdir -p "$backup/lib/fog" "$backup/commons"
    # system.class.php specifically: that is the file _isFifteenWebTree() keys
    # on, and it is a real 1.5 path (/var/www/html/fog-1.5/lib/fog/).
    echo '<?php // 1.5' > "$backup/lib/fog/system.class.php"
    echo '<?php // 1.5' > "$backup/commons/init.php"

    cat > "$PROG/.fogsettings" <<EOF
## Lab .fogsettings, written by bin/revert-rehearsal-lab.sh. Not a real install.
DB_name='$DB'
DB_host='$DBHOST'
DB_user='root'
DB_password='$LABROOT'
DB_backup_path='$SANDBOX/backups'
WEB_docroot='$SANDBOX/webroot/'
WEB_root='/fog/'
FOG_os_name='Fedora'
FOG_os_id='2'
SVC_user='$(id -un)'
EOF
    chmod 600 "$PROG/.fogsettings"

    # The recorder. stopInitScript() only issues `stop` for a unit whose
    # `is-active` returns 0, so the shim answers 0 -- otherwise the whole stop
    # path is skipped and an assertion that it ran would be vacuous. Every
    # invocation is logged, which is what lets the run check WHICH units were
    # asked for rather than merely that something was.
    cat > "$SHIM/systemctl" <<'EOF'
#!/bin/bash
echo "systemctl $*" >> "$SYSTEMCTL_SHIM_LOG"
exit 0
EOF
    chmod +x "$SHIM/systemctl"
    : > "$SANDBOX/systemctl.log"
}

# ---------------------------------------------------------------------------
# The 1.5-era fixture and its pre-upgrade dump. See the header for why 263.
# ---------------------------------------------------------------------------
buildFixture() {
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" build \
        --db=$DB --to=$FIXTURE_SCHEMA >>"$SANDBOX/fixture.log" 2>&1
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" seed \
        --db=$DB --profile=decade >>"$SANDBOX/fixture.log" 2>&1
    # The 1.5 accesscontrol plugin's own tables. A harness fixture replays
    # CORE schema steps and so has no plugin table at all, which would leave
    # the marker list untested against exactly the case that broke it: 1.6
    # pulling a plugin's table into core under the same name. `roles` here is
    # the 1.5 plugin's shape, taken from
    # lib/plugins/accesscontrol/class/accesscontrol.class.php.
    mysql --host=$DBHOST -uroot -p"$LABROOT" "$DB" -e "
        CREATE TABLE IF NOT EXISTS \`roles\` (
            \`rID\` int(11) NOT NULL AUTO_INCREMENT,
            \`rName\` varchar(255) NOT NULL,
            \`rDesc\` longtext NOT NULL,
            \`rCreatedBy\` varchar(40) NOT NULL DEFAULT '',
            \`rCreatedTime\` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (\`rID\`), UNIQUE KEY \`rName\` (\`rName\`)
        ) ENGINE=InnoDB;
        INSERT IGNORE INTO \`roles\` (\`rName\`,\`rDesc\`)
            VALUES ('Lab Operator','from the 1.5 accesscontrol plugin');
    " >>"$SANDBOX/fixture.log" 2>&1

    # Named the way backupDB() names it -- after the version being INSTALLED,
    # not the one being dumped. revertfog.sh must not be reading the filename
    # to decide what era a dump is, and giving it a 1.6 name is what proves it.
    mysqldump --host=$DBHOST -uroot -p"$LABROOT" "$DB" \
        > "$SANDBOX/backups/fogDBbackups/fog_sql_1.6.0_$(date +%Y%m%d_%H%M%S).sql" \
        2>>"$SANDBOX/fixture.log"
}

runRevert() {
    ( cd "$REPO/bin" \
      && SYSTEMCTL_SHIM_LOG="$SANDBOX/systemctl.log" \
         MYSQL_TCP_PORT="$PORT" \
         PATH="$SHIM:$PATH" \
         fogprogramdir="$PROG" \
         unshare -r bash ./revertfog.sh "$@" ) >"$LOG" 2>&1
    return $?
}

schemaNow() { sql 'SELECT `vValue` FROM `schemaVersion` LIMIT 1' "$DB"; }
hostsNow()  { sql 'SELECT COUNT(*) FROM `hosts`' "$DB"; }

export MYSQL_TCP_PORT=$PORT

# Proves the variable is being honored BEFORE anything destructive runs. Without
# this the whole rehearsal could silently be pointed at the wrong server.
reached=$(mysql --host=$DBHOST -uroot -p"$LABROOT" -N -B -e 'SELECT @@port' 2>/dev/null)
if [[ ${reached:-0} -ne $PORT ]]; then
    echo "the mysql client reached port '${reached:-none}', not $PORT --" >&2
    echo "MYSQL_TCP_PORT is not being honored; refusing to run." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Case 1: the clean revert.
# ---------------------------------------------------------------------------
if [[ $CASES == all || $CASES == clean ]]; then
    echo "== case: clean revert =="
    buildSandbox
    buildFixture
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" upgrade --db=$DB \
        >>"$SANDBOX/upgrade.log" 2>&1
    before_schema=$(schemaNow); before_hosts=$(hostsNow)
    echo "  before: schema $before_schema, $before_hosts hosts"
    runRevert --yes; rc=$?
    check "revertfog.sh exits 0" $rc
    after_schema=$(schemaNow); after_hosts=$(hostsNow)
    echo "  after:  schema $after_schema, $after_hosts hosts"
    [[ $after_schema -lt $before_schema ]]; check "schema went backward ($before_schema -> $after_schema)" $?
    [[ $after_schema -eq $FIXTURE_SCHEMA ]]
    check "schema is back at the 1.5 starting point ($FIXTURE_SCHEMA)" $?
    [[ $after_hosts -eq $before_hosts ]]; check "no hosts lost ($after_hosts)" $?
    # The database is a 1.5 database again, not merely a smaller number.
    sql 'SHOW TABLES LIKE "userAuths"' "$DB" | grep -q . ; [[ $? -ne 0 ]]
    check "the 1.6-only table userAuths is gone" $?
    n=$(sql "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$DB'")
    [[ ${n:-1} -eq 0 ]]; check "no foreign keys remain (1.5 declares none): $n" $?
    grep -q "^systemctl stop FOGScheduler" "$SANDBOX/systemctl.log"
    check "the FOG daemons were stopped (FOGScheduler among them)" $?
    # The order is the property, not the fact. A revert that dropped tables
    # while the scheduler was still writing to them would be restoring under a
    # live writer.
    stopline=$(grep -n "^systemctl stop " "$SANDBOX/systemctl.log" | head -1 | cut -d: -f1)
    [[ -n $stopline ]] && grep -q "Dropping the current tables" "$LOG"
    check "the daemons were stopped before the tables were dropped" $?
    [[ -f $WEBDEST/lib/fog/system.class.php && ! -f $WEBDEST/src/Base/FOGBase.php ]]
    check "the 1.5 web tree replaced the 1.6 one" $?
    grep -q "FOG 1.5 compatibility keys" "$PROG/.fogsettings"
    check ".fogsettings carries the pre-1.6 key spellings again" $?
    ls "$PROG"/.fogsettings.pre-revert_* >/dev/null 2>&1
    check "the pre-revert .fogsettings was kept" $?
    ls "$SANDBOX/backups/fogDBbackups/" | grep -qi revert
    check "a safety dump of the 1.6 database was taken first" $?
    # The dump this run accepted carried a 1.5 accesscontrol `roles` table and
    # was written by mysqldump rather than by FOG's own mysqldump-php. Both
    # used to be refused; see the comments on _16_TABLES and the footer test.
    grep -q 'CREATE TABLE `roles`' "$SANDBOX"/backups/fogDBbackups/fog_sql_1.6.0_*.sql
    check "the accepted dump really did contain a 1.5 plugin \`roles\` table" $?
    grep -q 'Dump completed' "$SANDBOX"/backups/fogDBbackups/fog_sql_1.6.0_*.sql
    check "the accepted dump really was written by mysqldump, not mysqldump-php" $?
fi

# ---------------------------------------------------------------------------
# Case 2: the real one -- an upgrade that died PARTWAY.
# ---------------------------------------------------------------------------
# Not "1.5 restored again": the point is that a database stopped in the middle
# of the 1.6 steps is still recognized as needing the 1.5 dump, and that the
# 1.6 web tree already sitting at the destination is not mistaken for a restore
# source. Schema 300 is inside 1.6's range and past the 264 divergence marker.
if [[ $CASES == all || $CASES == partial ]]; then
    echo
    echo "== case: revert after an upgrade that failed partway =="
    buildSandbox
    buildFixture
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" upgrade --db=$DB --to=300 \
        >>"$SANDBOX/partial.log" 2>&1
    mid=$(schemaNow)
    echo "  upgrade stopped at schema $mid"
    [[ ${mid:-0} -gt $FIXTURE_SCHEMA && ${mid:-0} -lt 398 ]]
    check "the database really is mid-upgrade ($mid)" $?
    runRevert --yes; rc=$?
    check "revertfog.sh exits 0 from a mid-upgrade database" $rc
    after_schema=$(schemaNow)
    [[ $after_schema -eq $FIXTURE_SCHEMA ]]
    check "schema is back at $FIXTURE_SCHEMA (was $mid)" $?
    [[ -f $WEBDEST/lib/fog/system.class.php ]]
    check "the 1.5 web tree was restored over the replaced one" $?
fi

# ---------------------------------------------------------------------------
# Case 3: no usable dump. Refuse loudly, change nothing.
# ---------------------------------------------------------------------------
if [[ $CASES == all || $CASES == nodump ]]; then
    echo
    echo "== case: no usable dump =="
    buildSandbox
    buildFixture
    php "$REPO/bin/upgrade-rehearsal.php" "$LAB/web" upgrade --db=$DB \
        >>"$SANDBOX/upgrade.log" 2>&1
    rm -f "$SANDBOX/backups/fogDBbackups/"*.sql
    before_schema=$(schemaNow); before_hosts=$(hostsNow)
    runRevert --yes; rc=$?
    [[ $rc -ne 0 ]]; check "revertfog.sh exits non-zero with no dump" $?
    grep -qi "dump" "$LOG"; check "it says why" $?
    [[ $(schemaNow) == "$before_schema" ]]; check "the schema is untouched ($before_schema)" $?
    [[ $(hostsNow) == "$before_hosts" ]]; check "the data is untouched ($before_hosts hosts)" $?
    [[ ! -s $SANDBOX/systemctl.log ]]; check "no daemon was stopped" $?
    [[ -f $WEBDEST/src/Base/FOGBase.php ]]; check "the web tree is untouched" $?
fi

echo
if [[ $fails -eq 0 ]]; then
    echo "ok  revert rehearsal passed"
else
    echo "FAIL  $fails assertion(s) failed -- see $LOG"
fi
exit $((fails > 0))
