#!/usr/bin/env bash
#
# The pre-1.6 lower-case transport/PKI setting names are gone, replaced by
# camelCase keys. Nothing is aliased: _migrateLegacySettingNames() copies each
# old value onto its new key once, and the old name is in deprecatedKeys so the
# next writeUpdateFile() removes the line.
#
# That is two lists that have to agree, and a call site whose POSITION is the
# whole correctness argument. This pins all three:
#
#   1. Every pair the migration handles is in deprecatedKeys, and every
#      lower-case name in deprecatedKeys is handled by the migration. Drift
#      either way is silent and destructive: a name in the migration but not
#      deprecated leaves the file carrying both spellings forever; a name
#      deprecated but not migrated DISCARDS the admin's value on the next write.
#   2. The copy is one-way and one-shot -- a value already under the new name
#      (a flag on this run, or an earlier migration) always wins.
#   3. The call sits between sourcing .fogsettings and doOSSpecificIncludes.
#      config.sh defaults secureBoot and caTrust to 1 when unset, so a call
#      after that point would silently overwrite --no-secure-boot /
#      --no-ca-trust with the default the admin opted out of.
#
# Needs bash only. No network, no root, no install.

set -u
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INSTALL="$REPO/bin/installfog.sh"

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

error_log=/dev/null
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

echo "settings name migration:"

# --- 1. the two lists agree ---------------------------------------------------

# The pairs the migration actually handles, read out of the function body so
# this cannot pass by being kept in step with a copy of itself.
mapfile -t PAIRS < <(
    sed -n '/^_migrateLegacySettingNames()/,/^}/p' "$FUNCS" \
        | grep -oE '\b[a-z]+:[a-zA-Z]+\b'
)
DEPRECATED="$(sed -n '/local -a deprecatedKeys=(/,/^    )/p' "$FUNCS" | grep -v '^[[:space:]]*#')"
MANAGED="$(sed -n '/local -a managedKeys=(/,/^    )/p' "$FUNCS" | grep -v '^[[:space:]]*#')"

if [[ ${#PAIRS[@]} -gt 0 ]]; then
    ok "the migration declares ${#PAIRS[@]} legacy pair(s)"
else
    bad "no legacy pairs found -- did _migrateLegacySettingNames change shape?"
fi

missing_dep=0
missing_man=0
for pair in "${PAIRS[@]}"; do
    old="${pair%%:*}"
    new="${pair##*:}"
    grep -qE "(^|[[:space:]])${old}([[:space:]]|\$)" <<<"$DEPRECATED" \
        || { bad "'$old' is migrated but NOT in deprecatedKeys -- the file would keep both spellings"; missing_dep=1; }
    grep -qE "(^|[[:space:]])${new}([[:space:]]|\$)" <<<"$MANAGED" \
        || { bad "'$new' is the migration target but NOT in managedKeys -- it would never be written"; missing_man=1; }
done
[[ $missing_dep -eq 0 ]] && ok "every migrated old name is in deprecatedKeys"
[[ $missing_man -eq 0 ]] && ok "every migration target is in managedKeys"

# ...and the other direction. A lower-case name retired without a migration
# takes the admin's value with it.
orphan=0
while read -r key; do
    [[ -z $key ]] && continue
    # Only the lower-case transport/PKI spellings are migration candidates; the
    # genuinely dead 1.5 keys (pkiMode, php_verAdds, ...) have no replacement.
    case $key in
        storageftpuser|storageftppass|bootfilename|notpxedefaultfile|php_verAdds|pkiMode|fogClientCACN) continue ;;
    esac
    matched=0
    for pair in "${PAIRS[@]}"; do
        [[ ${pair%%:*} == "$key" ]] && matched=1 && break
    done
    [[ $matched -eq 1 ]] || { bad "'$key' is deprecated but has no migration -- its value is discarded"; orphan=1; }
done < <(tr -s '[:space:]' '\n' <<<"$DEPRECATED" \
            | grep -vE '^$|^local$|^-a$|^deprecatedKeys=\($|^\)$')
[[ $orphan -eq 0 ]] && ok "every deprecated legacy name has a migration"

# --- 2. the copy is one-way and one-shot --------------------------------------

reset_all() {
    local pair
    for pair in "${PAIRS[@]}"; do
        unset "${pair%%:*}" "${pair##*:}" 2>/dev/null
    done
}

# An untouched pre-1.6 file: the old name carries the value across.
reset_all
httpproto="https"; secureboot="0"; sslpath="/opt/fog/snapins/ssl"; catrust="0"
_migrateLegacySettingNames
is "${httpProto:-}" "https"                "httpproto=https is carried onto httpProto"
is "${secureBoot:-}" "0"                   "a --no-secure-boot opt-out survives as secureBoot=0"
is "${caTrust:-}" "0"                      "a --no-ca-trust opt-out survives as caTrust=0"
is "${sslPath:-}" "/opt/fog/snapins/ssl"   "sslpath is carried onto sslPath"

# An already-migrated file has only the new name. Nothing to do, nothing undone.
reset_all
httpProto="http"
_migrateLegacySettingNames
is "$httpProto" "http" "an already-migrated value is left alone"

# Both present -- which happens for exactly one run, between the migration and
# the write that drops the old line. The new name wins.
reset_all
httpproto="http"; httpProto="https"
_migrateLegacySettingNames
is "$httpProto" "https" "the new name wins when both are set"

# A flag on this run has already written the new name before we get here.
reset_all
secureboot="1"; secureBoot="0"
_migrateLegacySettingNames
is "$secureBoot" "0" "--no-secure-boot on this run beats a persisted secureboot=1"

# Empty is not a value. An old key present but blank must not blank the new one.
reset_all
httpproto=""; httpProto="https"
_migrateLegacySettingNames
is "$httpProto" "https" "an empty old key does not blank the new one"

# Idempotent: running it twice changes nothing.
reset_all
httpproto="https"
_migrateLegacySettingNames
first="$httpProto"
_migrateLegacySettingNames
is "$httpProto" "$first" "a second run is a no-op"

# --- 3. the call site is in the right place -----------------------------------

# Line order in installfog.sh, not just presence. This is the assertion that
# would have caught defaulting secureBoot before the admin's opt-out was read.
src_line() { grep -nE "$1" "$INSTALL" | head -1 | cut -d: -f1; }
l_source=$(src_line '^\s*\.\s+"\$fogpriorconfig"')
l_migrate=$(src_line '^\s*_migrateLegacySettingNames\s*$')
l_includes=$(src_line '^\s*doOSSpecificIncludes\s*$')

if [[ -n $l_source && -n $l_migrate && -n $l_includes ]]; then
    ok "the source, the migration and doOSSpecificIncludes are all present"
    [[ $l_source -lt $l_migrate ]] \
        && ok "the migration runs AFTER .fogsettings is sourced" \
        || bad "the migration runs before the source -- there is nothing to migrate yet"
    [[ $l_migrate -lt $l_includes ]] \
        && ok "the migration runs BEFORE doOSSpecificIncludes" \
        || bad "the migration runs after config.sh defaults -- secureBoot/caTrust opt-outs are lost"
else
    bad "could not locate all three call sites in installfog.sh"
fi

# installfog.sh must NOT pre-seed a renamed key ahead of the source.
if awk -v n="$l_source" 'NR<n' "$INSTALL" \
    | grep -qE '^\[\[ -z \$(httpProto|externalCA|secureBoot|caTrust|sslPath|netbootProto) \]\]'; then
    bad "a renamed key is defaulted before the source -- the persisted value would lose"
else
    ok "no renamed key is defaulted ahead of the source"
fi

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
