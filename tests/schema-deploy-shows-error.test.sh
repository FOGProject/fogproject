#!/bin/bash
#
# Guards: a failed schema deploy puts the reason in the installer error log.
#
#   tests/schema-deploy-shows-error.test.sh
#
# updateDB posted to the schema page with `curl -fsL`. On a failed deploy the
# page answers 500 and writes each failed step to fog_schema_update_error.log
# in the webroot. -f discarded the reply and -s hid curl's own error line, so
# the installer printed "Updating Database....Failed!", exited 22, and the
# error-log tail showed whatever ran before -- the php-fpm status, in the
# forum report that found this (topic 18259).
#
# Drives updateDB with curl replaced by a function that behaves like the real
# one against a failing schema page: with -f it prints nothing and exits 22,
# without it it writes the body to -o and the status to -w.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

error_log="$WORK/error.log"
: > "$error_log"

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

webdirdest="$WORK/web/"
mkdir -p "${webdirdest}commons"
: > "${webdirdest}commons/schema.php"
SCHEMA_ERR="${webdirdest}fog_schema_update_error.log"
# A line from an earlier run. It must not be reported as this run's error.
echo "OLD: an error from a previous run" > "$SCHEMA_ERR"

dots() { :; }
_servedCertName() { echo "fog.test.local"; }
_resolveSelfCacert() { selfCacertOpts=(); }
schemaIndexedStepsDone() { return 1; }
verifySchemaDeploy() { :; }
DB_external=yes
WEB_url_proto=https
WEB_root=/fog/
installToken=0123456789abcdef
STORAGE_image_share_path=/images/
dbupdate=yes

ERRORSTAT=""
errorStat() { ERRORSTAT=$1; }

# The schema page failing one step: the reply the real page sends, and the
# line it appends to its own error log.
curl() {
    local out="" fail=0 w="" a
    echo "Create Table: Can't create table \`fog\`.\`hostMAC\` (errno: 150 \"Foreign key constraint is incorrectly formed\")" >> "$SCHEMA_ERR"
    while [[ $# -gt 0 ]]; do
        a=$1
        case "$a" in
            -o) out=$2; shift ;;
            -w) w=$2; shift ;;
            -*f*) [[ $a != --* ]] && fail=1 ;;
        esac
        shift
    done
    if [[ $fail -eq 1 ]]; then
        return 22
    fi
    local body='{"error":"Unable to update schema","title":"Schema Update Fail"}'
    if [[ -n $out && $out != - ]]; then
        printf '%s' "$body" > "$out"
    else
        printf '%s' "$body"
    fi
    [[ -n $w ]] && printf '500'
    return 0
}

echo "schema deploy shows its error:"

updateDB >/dev/null 2>&1

if [[ $ERRORSTAT == 22 ]]; then
    ok "a failed deploy still ends with status 22"
else
    bad "a failed deploy ended with status '${ERRORSTAT}', not 22"
fi
if grep -q 'Unable to update schema' "$error_log"; then
    ok "the schema page's reply is in the error log"
else
    bad "the schema page's reply is not in the error log"
fi
if grep -q 'HTTP 500' "$error_log"; then
    ok "the HTTP status is in the error log"
else
    bad "the HTTP status is not in the error log"
fi
if tail -n 5 "$error_log" | grep -q 'Foreign key constraint is incorrectly formed'; then
    ok "the failed step is in the last five lines, which errorStat shows"
else
    bad "the failed step is not in the last five lines of the error log"
fi
if grep -q 'OLD: an error from a previous run' "$error_log"; then
    bad "an error from an earlier run was reported as this run's"
else
    ok "only this run's schema errors are copied"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
