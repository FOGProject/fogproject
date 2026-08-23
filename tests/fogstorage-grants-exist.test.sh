#!/bin/bash
#
# Guards the fogstorage GRANT list against naming a table that does not exist.
#
#   tests/fogstorage-grants-exist.test.sh
#
# grantFogStorageAccess() writes a .sql file of per-table GRANTs and feeds it to
# mysql in ONE batch. MySQL refuses a grant on a missing table
# (ERROR 1146 ... Table 'fog.x' doesn't exist) and the client stops at that
# statement, so a single stale name aborts the whole batch: the grants after it
# never apply, errorStat sees a non-zero status, and the install stops with
# "Granting access to fogstorage database user ... Failed!".
#
# That shipped. ADR 0022 decision 3 retired `imagingLog` -- taskLog carries the
# image name now, so the two logs no longer recorded different things -- and
# Schema::dropTable('imagingLog') drops it, but the grant list still named it.
# Every FRESH install of working-1.6 failed at that step, which is late enough
# to look like a database problem rather than a stale list.
#
# The two halves live in different languages and different directories, so
# nothing connected them. This does: every table named in the grant block must
# be created by the schema and must not be dropped by it.
#
# Pure source analysis: no database, no install, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
SCHEMA="$REPO/packages/web/commons/schema.php"

[[ -f $FUNCS ]]  || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
[[ -f $SCHEMA ]] || { echo "ERROR: $SCHEMA not found" >&2; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

echo "fogstorage grants:"

# The per-table grants, read out of the real heredoc. `${DB_name}.*` is the
# database-wide SELECT and names no table, so it is excluded.
granted="$(grep -oE "GRANT [A-Z,]+ ON \\\$\{DB_name\}\.[A-Za-z_][A-Za-z0-9_]*" "$FUNCS" \
    | sed -E 's/.*\}\.//' | sort -u)"
count=$(printf '%s\n' "$granted" | grep -cvE '^$')

# A floor, so a change to the heredoc that stops the extraction matching cannot
# turn this into a test that passes by finding nothing.
[[ $count -ge 8 ]] && ok "found $count per-table grants to check" \
    || bad "only found $count per-table grants; extraction is probably broken"

# Tables the schema creates. Both spellings appear in this file: the CREATE TABLE
# strings and Schema::createTable() calls.
# Both `CREATE TABLE  \`x\`` (with the stray double space this file has) and
# `CREATE TABLE IF NOT EXISTS \`x\`` occur, alongside Schema::createTable().
# Missing a spelling here makes a live table look absent, which is a false
# failure rather than a missed regression -- but it is still wrong, and it did
# happen while writing this.
created="$( { grep -oE 'CREATE TABLE +(IF +NOT +EXISTS +)?`[A-Za-z0-9_]+`' "$SCHEMA" \
                 | sed -E 's/.*`(.*)`/\1/'
             grep -oE "Schema::createTable\('[A-Za-z0-9_]+'" "$SCHEMA" | sed -E "s/.*'(.*)'/\1/"
           } | sort -u)"
dropped="$(grep -oE "Schema::dropTable\('[A-Za-z0-9_]+'" "$SCHEMA" | sed -E "s/.*'(.*)'/\1/" | sort -u)"

[[ -n $created ]] || { bad "no CREATE TABLE names found in schema.php"; }

missing=""
retired=""
while read -r t; do
    [[ -z $t ]] && continue
    printf '%s\n' "$dropped" | grep -qxF "$t" && { retired="$retired $t"; continue; }
    printf '%s\n' "$created" | grep -qxF "$t" || missing="$missing $t"
done <<< "$granted"

is "$retired" "" "no grant names a table the schema DROPS"
is "$missing" "" "every granted table is created by the schema"

# And the specific regression, by name, so a failure says what happened rather
# than only that a set was non-empty.
printf '%s\n' "$granted" | grep -qxF imagingLog \
    && bad "imagingLog is still granted -- ADR 0022 dropped that table" \
    || ok "imagingLog is no longer granted (ADR 0022 dropped it)"
printf '%s\n' "$dropped" | grep -qxF imagingLog \
    && ok "...and the schema does still drop it, so this test is testing something" \
    || bad "schema.php no longer drops imagingLog -- re-check this test's premise"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
