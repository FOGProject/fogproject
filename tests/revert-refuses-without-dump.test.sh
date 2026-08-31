#!/bin/bash
#
# Guards the preconditions in bin/revertfog.sh.
#
#   tests/revert-refuses-without-dump.test.sh
#
# revertfog.sh drops the database and moves the web tree aside. The single
# property that has to hold is that it cannot do either of those and only THEN
# discover there is nothing to restore -- backupDB() has silently skipped in
# the field twice (GH-314's status-of-the-wrong-command, GH-1146's symlinked
# webroot), so "no dump" is a state that really happens and the wrong behavior
# there is an unbootable server rather than an inconvenience.
#
# Three cases, run against a fake install under a temporary directory with
# stub mysql/mysqldump/systemctl on PATH, so nothing real is touched:
#
#   A  no dump at all      -> exits non-zero having invoked NOTHING
#   B  only a 1.6-era dump -> refuses, naming the marker that gave it away
#   C  a 1.5-era dump      -> --dry-run prints the whole plan and changes nothing
#
# A and B run WITHOUT --dry-run and with --yes, on purpose. A dry run would
# prove only that --dry-run works; what is being pinned is that the guards stop
# a REAL run before it reaches anything destructive.
#
# The stub log is the strong assertion in A and B: a script that invoked no
# external command cannot have stopped a daemon, dropped a table or moved a
# directory, whatever the host's init system is. $systemctl is forced to "yes"
# so stopInitScript() takes the systemd arm and the stub is what it calls --
# config.sh guards that variable with [[ -z ]], so this is the same knob an
# admin has, not a test-only hook.
#
# Root: the script refuses to run as anyone else, so this uses `unshare -r`,
# which maps the caller to uid 0 in a private user namespace. If unprivileged
# user namespaces are unavailable the test SKIPS rather than failing.
#
# No install, no network, no database, no real root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
SCRIPT="$REPO/bin/revertfog.sh"

[[ -f $SCRIPT ]] || { echo "ERROR: $SCRIPT not found" >&2; exit 1; }

if ! unshare -r true >/dev/null 2>&1; then
    echo "  SKIP  unprivileged user namespaces unavailable; cannot run revertfog.sh as root"
    exit 0
fi

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()    { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }
has()   { [[ "$1" == *"$2"* ]] && ok "$3" || bad "$3 (not found)"; }
hasnt() { [[ "$1" != *"$2"* ]] && ok "$3" || bad "$3 (unexpectedly present)"; }
exists()  { [[ -e $1 ]] && ok "$2" || bad "$2 ($1 is gone)"; }
absent()  { local m; m=$(compgen -G "$1" 2>/dev/null); [[ -z $m ]] && ok "$2" || bad "$2 ($m was created)"; }

STUB="$WORK/stub"
LOG="$WORK/calls.log"
PROG="$WORK/fog"
DOCROOT="$WORK/www"
WEBTREE="$DOCROOT/fog"
BACKUPS="$WORK/backups"
DUMPS="$BACKUPS/fogDBbackups"
mkdir -p "$STUB" "$PROG" "$WEBTREE/src/Base" "$DUMPS"

# Every external command revertfog.sh can reach on the destructive path. They
# all succeed, so anything that gets past a guard runs to completion rather
# than being saved by a failure -- a stub that errored would let a broken
# guard pass for the wrong reason.
for c in mysql mysqldump systemctl chown; do
    cat > "$STUB/$c" <<EOF
#!/bin/bash
echo "$c \$*" >> "\$STUBLOG"
exit 0
EOF
    chmod +x "$STUB/$c"
done

# A minimal .fogsettings in 1.6 spellings, which is what a server that has just
# upgraded actually carries.
cat > "$PROG/.fogsettings" <<EOF
## Start of FOG Settings
## Version: 1.6.0-beta
FOG_install_type='N'
FOG_os_id='2'
FOG_os_name='Debian'
FOG_program_dir='$PROG'
DB_name='fog'
DB_host='localhost'
DB_user='fogmaster'
DB_password='secret'
DB_external='no'
DB_backup_path='$BACKUPS/'
WEB_docroot='$DOCROOT/'
WEB_root='/fog/'
WEB_server_engine='apache2'
SVC_user='fogproject'
## End of FOG Settings
EOF

# The live (1.6) web tree. SENTINEL is the file that proves the tree was not
# moved aside; src/Base/System.php is what makes it recognizably 1.6.
echo "live tree, must survive a refusal" > "$WEBTREE/SENTINEL"
echo "<?php" > "$WEBTREE/src/Base/System.php"

# A genuine 1.5-era web backup, so that step 4 is REACHABLE. Without one the
# script skips the web tree for its own reasons and the sentinel would survive
# a broken guard by accident.
WEBBAK="$BACKUPS/fog_web_1.6.0-beta.BACKUP"
mkdir -p "$WEBBAK/lib/fog"
echo "<?php" > "$WEBBAK/lib/fog/system.class.php"
echo "restored 1.5 tree" > "$WEBBAK/MARKER"

# Likewise a stored kernel generation, so step 6 is reachable and the plan has
# to name it rather than skipping it for a reason unrelated to the guards.
mkdir -p "$PROG/customizations/kernel-backups/gen-1"
echo "not a real kernel" > "$PROG/customizations/kernel-backups/gen-1/bzImage"

# ---------------------------------------------------------------------------
# Dump fixtures, in mysqldump-php's actual output shape -- that is what
# maintenance/backup_db.php runs on 1.5 and 1.6 alike, so the header, the
# SHOW CREATE TABLE bodies and the closing SQL_NOTES line are all real.
# ---------------------------------------------------------------------------
mk15() {
    cat > "$1" <<'EOF'
-- mysqldump-php https://github.com/ifsnop/mysqldump-php
--
-- Host: localhost	Database: fog
-- ------------------------------------------------------
-- Server version 	10.11.6-MariaDB

/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

--
-- Table structure for table `schemaVersion`
--

CREATE TABLE `schemaVersion` (
  `vID` int(11) NOT NULL AUTO_INCREMENT,
  `vValue` varchar(255) NOT NULL,
  PRIMARY KEY (`vID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `schemaVersion` VALUES (1,'287');

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `groupID` int(11) NOT NULL AUTO_INCREMENT,
  `groupName` varchar(50) NOT NULL,
  `groupKernel` varchar(255) NOT NULL,
  PRIMARY KEY (`groupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Table structure for table `site`
--
-- The 1.5 site PLUGIN's table. Singular, and it must not be mistaken for
-- core's 1.6 `sites`.

CREATE TABLE `site` (
  `sID` int(11) NOT NULL AUTO_INCREMENT,
  `sName` varchar(255) NOT NULL,
  PRIMARY KEY (`sID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `globalSettings` VALUES (1,'FOG_SITES_NOTE','mentions sites and groupInit in prose',' ','General');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
EOF
}

mk16() {
    cat > "$1" <<'EOF'
-- mysqldump-php https://github.com/ifsnop/mysqldump-php
--
-- Host: localhost	Database: fog
-- ------------------------------------------------------
-- Server version 	10.11.6-MariaDB

/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

CREATE TABLE `schemaVersion` (
  `vID` int(11) NOT NULL AUTO_INCREMENT,
  `vValue` varchar(255) NOT NULL,
  PRIMARY KEY (`vID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `schemaVersion` VALUES (1,'398');

CREATE TABLE `groups` (
  `groupID` int(11) NOT NULL AUTO_INCREMENT,
  `groupName` varchar(50) NOT NULL,
  `groupInit` longtext NOT NULL,
  PRIMARY KEY (`groupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

CREATE TABLE `sites` (
  `siteID` int(11) NOT NULL AUTO_INCREMENT,
  `siteName` varchar(255) NOT NULL,
  PRIMARY KEY (`siteID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
EOF
}

# Runs revertfog.sh as uid 0 inside a user namespace, against the fake install.
# $out holds combined output, $rc the real exit status.
runrevert() {
    : > "$LOG"
    out=$(cd "$REPO/bin" && unshare -r env \
        PATH="$STUB:$PATH" \
        STUBLOG="$LOG" \
        fogprogramdir="$PROG" \
        systemctl=yes \
        bash ./revertfog.sh "$@" 2>&1)
    rc=$?
    calls=$(cat "$LOG")
}

# ---------------------------------------------------------------------------
# A. No dump present. A real run, with --yes, and nothing may happen.
# ---------------------------------------------------------------------------
echo "  A. no dump present"
runrevert --yes
[[ $rc -ne 0 ]] && ok "A1. exits non-zero" || bad "A1. exits non-zero (got $rc)"
has "$out" "no pre-upgrade database dump on this server" "A2. says what is missing"
is  "$calls" "" "A3. invoked no external command at all -- nothing was stopped or dropped"
exists "$WEBTREE/SENTINEL" "A4. the live web tree is untouched"
absent "$DUMPS/fog_sql_pre-revert_*" "A5. no pre-revert database dump was written"
absent "$PROG/.fogsettings.pre-revert_*" "A6. .fogsettings was not touched"

# ---------------------------------------------------------------------------
# B. Only a 1.6-era dump. Present, readable, newest -- and still refused.
# ---------------------------------------------------------------------------
echo "  B. only a 1.6-era dump present"
mk16 "$DUMPS/fog_sql_1.6.0-beta_20260830_020000.sql"
runrevert --yes
[[ $rc -ne 0 ]] && ok "B1. exits non-zero" || bad "B1. exits non-zero (got $rc)"
has "$out" "no stored dump is a 1.5-era dump" "B2. refuses the whole set"
has "$out" 'the 1.6-only table `sites`' "B3. names the marker that gave it away"
is  "$calls" "" "B4. invoked no external command at all"
exists "$WEBTREE/SENTINEL" "B5. the live web tree is untouched"
absent "$DUMPS/fog_sql_pre-revert_*" "B6. no pre-revert database dump was written"

# ---------------------------------------------------------------------------
# C. A 1.5-era dump exists, but is OLDER than the 1.6 one. --dry-run must
#    report the full plan, pick the 1.5 dump, and change nothing.
#
#    The ordering is the point: an upgrade that failed and was then re-run
#    leaves a 1.6 dump sitting on top of the 1.5 one, so "newest" and "newest
#    USABLE" are different files and only the second is correct.
# ---------------------------------------------------------------------------
echo "  C. a 1.5-era dump, older than the 1.6 one, --dry-run"
FIFTEEN="$DUMPS/fog_sql_1.6.0-beta_20260829_010000.sql"
mk15 "$FIFTEEN"
touch -d '2026-08-29 01:00:00' "$FIFTEEN"
runrevert --dry-run
is "$rc" "0" "C1. exits zero"
has "$out" "$(basename "$FIFTEEN")" "C2. plans the 1.5 dump, not the newer 1.6 one"
hasnt "$out" "fog_sql_1.6.0-beta_20260830_020000.sql" "C3. the 1.6 dump is not chosen"
has "$out" "DRY RUN -- every check runs, nothing is changed" "C4. says it is a dry run"
has "$out" "1. dump the CURRENT (1.6) database" "C5. plan step 1: safety dump"
has "$out" "2. stop the FOG daemons"            "C6. plan step 2: stop services"
has "$out" "3. drop every table"                "C7. plan step 3: drop and restore"
has "$out" "and restore the 1.5 tree from"      "C8. plan step 4: web tree"
has "$out" "5. add the pre-1.6 key spellings"   "C9. plan step 5: .fogsettings"
has "$out" "6. restore FOS kernel generation"   "C10. plan step 6: kernels via restorekernel.sh"
has "$out" "7. start the FOG daemons again"     "C11. plan step 7: start services"
has "$out" "It will NOT down-migrate any schema step" "C12. says down-migration is not offered"
# And nothing happened.
exists "$WEBTREE/SENTINEL" "C13. the live web tree is untouched"
absent "$DUMPS/fog_sql_pre-revert_*" "C14. no pre-revert database dump was written"
absent "$PROG/.fogsettings.pre-revert_*" "C15. .fogsettings was not copied aside"
hasnt "$calls" "DROP TABLE" "C16. no DROP TABLE was issued"
hasnt "$calls" "systemctl" "C17. no service was stopped or started"
hasnt "$calls" "mysqldump" "C18. mysqldump was not run"
# The only thing a dry run may do is the read-only credential probe, which has
# to happen for the plan to be honest about whether the revert can work.
has "$calls" "--execute=quit" "C19. the read-only credential probe still ran"

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
