#!/bin/bash
#
# Guards bin/revertupdate.sh (GH-1659).
#
#   tests/revertupdate.test.sh
#
# The script has two halves that must stay separate and one proof that must
# stand between them and the database: a dump is restored only when its
# schemaVersion row equals the FOG_SCHEMA of the code that is checked out.
# Old code at a newer schema, or new code at an older one, is the state the
# script exists to get an administrator OUT of, so the cases below pin that it
# will not create it -- and that a refusal invokes nothing destructive at all.
#
# Fake install under a temporary directory: a real git repository standing in
# for FOG_git_path with two commits carrying different FOG_SCHEMA values, two
# dumps carrying the matching schemaVersion rows, and stub mysql / mysqldump /
# systemctl on PATH that log their arguments. The mysql stub answers the one
# read the script makes (the live schemaVersion) from $FAKE_LIVE_SCHEMA.
#
#   A  no action           -> reports, names which dump fits which commit,
#                             runs nothing destructive
#   B  --restore-db, dump behind the checkout  -> REFUSED, nothing invoked
#   C  --checkout          -> HEAD moves to the recorded commit, nothing else
#   D  --restore-db, dump ahead of the checkout -> REFUSED, nothing invoked
#   E  --restore-db, dump matching the checkout -> safety dump, stop, drop,
#                             restore, in that order
#   F  --checkout at the recorded commit -> nothing to do, exit 0
#
# Root: the script refuses to run as anyone else, so this uses `unshare -r`.
# If unprivileged user namespaces are unavailable the test SKIPS.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
SCRIPT="$REPO/bin/revertupdate.sh"

[[ -f $SCRIPT ]] || { echo "ERROR: $SCRIPT not found" >&2; exit 1; }

if ! unshare -r true >/dev/null 2>&1; then
    echo "  SKIP  unprivileged user namespaces unavailable; cannot run revertupdate.sh as root"
    exit 0
fi
command -v git >/dev/null 2>&1 || { echo "  SKIP  git not installed"; exit 0; }

PASS=0; FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
check() { [[ $1 == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }
has()   { [[ "$1" == *"$2"* ]] && ok "$3" || bad "$3 (missing: $2)"; }
hasnt() { [[ "$1" != *"$2"* ]] && ok "$3" || bad "$3 (unexpectedly present: $2)"; }

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
STUB="$WORK/stub"
LOG="$WORK/calls.log"
PROG="$WORK/fog"
GITREPO="$WORK/checkout"
BACKUPS="$WORK/backups"
DUMPS="$BACKUPS/fogDBbackups"
mkdir -p "$STUB" "$PROG" "$DUMPS" "$GITREPO/packages/web/src/Base"

# The mysql stub answers the schema read and logs everything else. It must
# SUCCEED on the destructive calls, so that anything past a guard runs to
# completion rather than being saved by a failing stub.
cat > "$STUB/mysql" <<'EOF'
#!/bin/bash
echo "mysql $*" >> "$STUBLOG"
case "$*" in
    *"SELECT vValue"*) echo "$FAKE_LIVE_SCHEMA" ;;
    *"SHOW TABLES"*)   printf 'hosts\nimages\n' ;;
esac
exit 0
EOF
for c in mysqldump systemctl; do
    cat > "$STUB/$c" <<EOF
#!/bin/bash
echo "$c \$*" >> "\$STUBLOG"
exit 0
EOF
done
chmod +x "$STUB"/*

# A checkout with two commits: OLD expects schema 410, NEW expects 415.
export GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@x GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@x
git -C "$GITREPO" init -q -b working-1.6
printf '<?php\n        define('"'"'FOG_SCHEMA'"'"', 410);\n' > "$GITREPO/packages/web/src/Base/System.php"
git -C "$GITREPO" add -A && git -C "$GITREPO" commit -q -m old
OLD=$(git -C "$GITREPO" rev-parse HEAD)
printf '<?php\n        define('"'"'FOG_SCHEMA'"'"', 415);\n' > "$GITREPO/packages/web/src/Base/System.php"
git -C "$GITREPO" commit -q -am new
NEW=$(git -C "$GITREPO" rev-parse HEAD)

cat > "$PROG/.fogsettings" <<EOF
## Start of FOG Settings
## Version: 1.6.0
FOG_install_type='N'
FOG_os_id='2'
FOG_os_name='Debian'
FOG_program_dir='$PROG'
FOG_git_path='$GITREPO'
FOG_last_good_commit='$OLD'
DB_name='fog'
DB_host='localhost'
DB_user='fogmaster'
DB_password='secret'
DB_external='no'
DB_backup_path='$BACKUPS/'
WEB_docroot='$WORK/www/'
WEB_root='/fog/'
WEB_server_engine='apache2'
SVC_user='fogproject'
## End of FOG Settings
EOF

mkdump() { # <file> <schema>
    cat > "$1" <<EOF
-- mysqldump-php shaped
CREATE TABLE \`schemaVersion\` (
  \`vID\` int(11) NOT NULL,
  \`vValue\` varchar(255) NOT NULL
);
INSERT INTO \`schemaVersion\` VALUES (1,'$2');
SET SQL_NOTES=@OLD_SQL_NOTES;
EOF
}
mkdump "$DUMPS/fog_sql_4600_20260901_120000.sql" 410
sleep 1
mkdump "$DUMPS/fog_sql_4650_20260902_120000.sql" 415

run() {
    : > "$LOG"
    out=$(cd "$REPO/bin" && unshare -r env \
        PATH="$STUB:$PATH" \
        STUBLOG="$LOG" \
        FAKE_LIVE_SCHEMA="${LIVE:-415}" \
        GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory GIT_CONFIG_VALUE_0='*' \
        fogprogramdir="$PROG" \
        systemctl=yes \
        bash ./revertupdate.sh "$@" 2>&1)
    rc=$?
    calls=$(cat "$LOG")
}

echo "== A: no action reports, and changes nothing =="
run
check "$rc" "0" "exits 0"
has "$out" "last completed install: ${OLD:0:12}" "names the commit the last completed install ran from"
has "$out" "now at:                ${NEW:0:12}" "and where the checkout is now"
has "$out" "fog_sql_4600_20260901_120000.sql  schema 410  (matches the last completed install -- restorable after --checkout)" \
    "says the older dump fits the recorded commit, after a checkout"
has "$out" "fog_sql_4650_20260902_120000.sql  schema 415  (matches the checked-out code -- restorable now)" \
    "and that the newer dump fits the code checked out now"
has "$out" "Nothing was changed" "says nothing was changed"
hasnt "$calls" "mysqldump" "took no safety dump"
hasnt "$calls" "systemctl" "stopped nothing"
hasnt "$calls" "DROP TABLE" "dropped nothing"
check "$(git -C "$GITREPO" rev-parse HEAD)" "$NEW" "and moved the checkout nowhere"

echo "== B: a dump behind the checked-out code is refused before anything runs =="
run --restore-db "$DUMPS/fog_sql_4600_20260901_120000.sql" --yes
check "$rc" "1" "exits non-zero"
has "$out" "REFUSED" "says so"
has "$out" "schema 410, and the checked-out" "names the dump's schema"
has "$out" "expects schema 415" "and the code's"
has "$out" "run --checkout first" "and points at the checkout that would make it fit"
hasnt "$calls" "mysqldump" "no safety dump"
hasnt "$calls" "systemctl" "nothing stopped"
hasnt "$calls" "DROP TABLE" "nothing dropped"
[[ $calls == *"--execute=quit"* ]] && bad "connected to the database before the proof" \
    || ok "the proof runs before the database is even connected to"

echo "== C: --checkout moves HEAD to the recorded commit and nothing else =="
run --checkout --yes
check "$rc" "0" "exits 0"
check "$(git -C "$GITREPO" rev-parse HEAD)" "$OLD" "HEAD is the recorded commit"
check "$(git -C "$GITREPO" rev-parse --abbrev-ref HEAD)" "HEAD" "detached, not a moved branch"
check "$(git -C "$GITREPO" rev-parse working-1.6)" "$NEW" "the branch ref is where it was"
hasnt "$calls" "mysqldump" "no safety dump"
hasnt "$calls" "DROP TABLE" "nothing dropped"
has "$out" "installfog.sh" "and says the installer run is what puts the code in service"

echo "== D: a dump AHEAD of the checked-out code is refused too =="
run --restore-db "$DUMPS/fog_sql_4650_20260902_120000.sql" --yes
check "$rc" "1" "exits non-zero"
has "$out" "REFUSED" "says so"
hasnt "$out" "run --checkout first" "and does not suggest a checkout that would not help"
hasnt "$calls" "DROP TABLE" "nothing dropped"

echo "== E: the matching dump restores, in order, after a safety dump =="
run --restore-db "$DUMPS/fog_sql_4600_20260901_120000.sql" --yes
check "$rc" "0" "exits 0"
seq=$(printf '%s\n' "$calls" | grep -oE '^(mysqldump|systemctl|mysql .*(DROP TABLE|SHOW TABLES)|mysql .* fog$)' | sed 's/ .*DROP TABLE.*/ DROP/; s/ .*SHOW TABLES.*/ SHOW/; s/^mysql .*fog$/mysql RESTORE/' | tr '\n' ' ')
has "$seq" "mysqldump " "took the safety dump"
case "$seq" in
    "mysqldump systemctl "*"mysql SHOW mysql DROP mysql RESTORE ") ok "in the order dump, stop, list, drop, restore" ;;
    *) bad "order was: $seq" ;;
esac
has "$out" "Database restored to schema 410" "says what schema the database is on now"
ls "$DUMPS"/fog_sql_prerevert_*.sql >/dev/null 2>&1 && ok "the safety dump is named beside the others" \
    || bad "no fog_sql_prerevert_*.sql was written"

echo "== F: --checkout at the recorded commit has nothing to do =="
run --checkout --yes
check "$rc" "0" "exits 0"
has "$out" "already at" "and says so"
hasnt "$calls" "DROP TABLE" "nothing dropped"

echo
printf '%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
