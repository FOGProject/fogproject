#!/bin/bash
#
# Guards the single boolean encoding for .fogsettings: yes / no.
#
#   tests/boolean-encoding.test.sh
#
# Before GH-1120's follow-up there were three encodings, and the flag layer
# mixed them inside one variable: sDHCP_enabled was assigned "Y" and then 1 on
# the very next line, and sBOOT_external_tftp_server was assigned the string
# "true", which nothing ever tested for. Twelve boolean keys split three ways --
# yes/no, 1/0, Y/N -- so which literal a test had to use was a per-key fact, and
# getting it wrong fails silently in both directions:
#
#   [[ "N" == 0 ]]   is simply false
#   [[ "N" -eq 1 ]]  evaluates "N" as an ARITHMETIC expression -- an unset
#                    variable named N, hence 0 -- rather than erroring
#
# That pair is how DHCP_enabled="N" satisfied neither the enabled test nor the
# disabled one, which is what this whole change exists to make impossible.
#
# Runs the real _normalizeBool/_normalizeBooleanSettings out of functions.sh and
# greps the real source for stragglers. No install, no network, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

declare -F _normalizeBool >/dev/null \
    || { echo "ERROR: _normalizeBool is not defined" >&2; exit 1; }
declare -F _booleanSettingKeys >/dev/null \
    || { echo "ERROR: _booleanSettingKeys is not defined" >&2; exit 1; }
declare -F _normalizeBooleanSettings >/dev/null \
    || { echo "ERROR: _normalizeBooleanSettings is not defined" >&2; exit 1; }

boolKeys="$(_booleanSettingKeys)"

# --- A. every spelling a human or an old installer might have left behind ----
for v in yes y 1 true on enabled YES Y True ON Enabled; do
    is "$(_normalizeBool "$v")" "yes" "_normalizeBool '$v' -> yes"
done
for v in no n 0 false off disabled NO N False OFF Disabled; do
    is "$(_normalizeBool "$v")" "no" "_normalizeBool '$v' -> no"
done

# --- B. what it must NOT do --------------------------------------------------
# Empty stays empty: the prompt loops are `while [[ -z ${KEY} ]]`, so collapsing
# unset into "no" would stop every prompt firing and silently answer for the
# admin.
is "$(_normalizeBool "")" "" "empty stays empty (prompt loops depend on it)"
# An unrecognized value is left alone rather than guessed at. Turning a typo
# into "no" is how a deliberate setting disappears with nothing to show why.
is "$(_normalizeBool "maybe")" "maybe" "an unrecognized value is left untouched"
is "$(_normalizeBool "2")" "2" "an out-of-range number is left untouched"

# --- C. idempotence ----------------------------------------------------------
# This runs on EVERY install, not once behind a version marker, because
# .fogsettings is a file admins edit -- an old encoding can arrive at any time.
# So a second pass must be a no-op.
is "$(_normalizeBool "$(_normalizeBool 1)")" "yes" "normalizing twice is stable (yes)"
is "$(_normalizeBool "$(_normalizeBool N)")" "no" "normalizing twice is stable (no)"

# --- D. the whole-set sweep, on the real key list ----------------------------
# Seed every boolean key with a legacy encoding and check they all convert.
DHCP_enabled=1
FOG_install_lang=0
FOG_send_reports=Y
FOG_copy_back_old=1
DB_external=1
BOOT_external_tftp_server=true
BOOT_rebuild_ipxe_with_my_ca=yes
BOOT_url_proto_forced=1
PKI_sb_enabled=0
PKI_web_cert_publicly_trusted=no
STORAGE_rebuild_nfs_exports=1
WEB_https_redirect=yes
_normalizeBooleanSettings
stragglers=""
for k in $boolKeys; do
    case "${!k}" in
        yes|no) ;;
        *) stragglers="$stragglers $k=${!k}" ;;
    esac
done
is "$stragglers" "" "every boolean key normalizes to yes/no from a legacy value"
is "$DHCP_enabled" "yes" "DHCP_enabled 1 -> yes"
is "$FOG_install_lang" "no" "FOG_install_lang 0 -> no"
is "$FOG_send_reports" "yes" "FOG_send_reports Y -> yes"
is "$BOOT_external_tftp_server" "yes" "BOOT_external_tftp_server true -> yes"
is "$PKI_sb_enabled" "no" "PKI_sb_enabled 0 -> no"

# An unset key must stay unset through the sweep, for the same reason as B.
unset DHCP_enabled
_normalizeBooleanSettings
is "${DHCP_enabled-UNSET}" "UNSET" "the sweep leaves an unset key unset"

# --- E. the list itself ------------------------------------------------------
# FOG_installed is excluded on purpose: settingLine() writes it unquoted and
# numeric to keep the historical file format, bin/updatefog.sh reads it, and it
# records install state rather than a preference.
printf '%s\n' $boolKeys | grep -qxF FOG_installed \
    && bad "FOG_installed is in the boolean list -- it is a numeric state marker" \
    || ok "FOG_installed is excluded from the boolean list"
# These two are not booleans at all. Folding either to yes/no destroys an
# answer, so the guard is that they never join the list.
for k in SVC_firewall_control FOG_install_type; do
    printf '%s\n' $boolKeys | grep -qxF "$k" \
        && bad "$k is in the boolean list -- it is not a boolean" \
        || ok "$k is excluded from the boolean list"
done
# Every key in the list must actually be a managed .fogsettings key, or
# normalizing it accomplishes nothing.
arrayKeys() {
    sed -n "/local -a $1=(/,/^    )/p" "$FUNCS" \
        | sed -e 's/#.*$//' -e "s/local -a $1=(//" -e 's/)//' \
        | tr -s ' \n' '\n\n' | grep -vE '^$'
}
managed="$(arrayKeys managedKeys)"
unmanaged=""
for k in $boolKeys; do
    printf '%s\n' "$managed" | grep -qxF "$k" || unmanaged="$unmanaged $k"
done
is "$unmanaged" "" "every boolean key is a managed key"

# --- F. no old-encoding test survives in the shell sources -------------------
# The point of one encoding is that there is nowhere left for the other two to
# hide. Arithmetic comparisons are called out separately because they are the
# ones that fail silently rather than merely being false.
alt="$(printf '%s|' $boolKeys | sed 's/|$//')"
arith="$(grep -rnE "\\\$\{?($alt)\}?[[:space:]]*(-eq|-ne|-lt|-gt|-ge|-le)" \
    "$REPO/lib" "$REPO/bin" "$REPO/utils" 2>/dev/null)"
is "$arith" "" "no boolean key is tested with an arithmetic operator"
# The literal has to be a COMPLETE token: without the trailing boundary,
# [YyNn] happily matches the "y" of "yes" and every correct line is a hit.
lits="$(grep -rnE "\\\$\{?($alt)\}?[[:space:]]*(==|!=)[[:space:]]*[\"']?(0|1|[YyNn]|true|false)[\"']?[[:space:]]*(\]|&|\||;|\$)" \
    "$REPO/lib" "$REPO/bin" "$REPO/utils" 2>/dev/null)"
is "$lits" "" "no boolean key is compared against 0/1/Y/N/true/false"
assigns="$(grep -rnE "($alt)=[\"']?([01]|[YyNn]|true|false)[\"']?[[:space:]]*(;|\$)" \
    "$REPO/lib" "$REPO/bin" "$REPO/utils" 2>/dev/null)"
is "$assigns" "" "no boolean key is assigned 0/1/Y/N/true/false"
# Including the s-prefixed flag shadows, which were the worst offenders --
# sDHCP_enabled held "Y" and 1 on consecutive lines.
shadows="$(grep -rnE "s($alt)=[\"']?([01]|[YyNn]|true|false)[\"']?[[:space:]]*(;|\$)" \
    "$REPO/lib" "$REPO/bin" 2>/dev/null)"
is "$shadows" "" "no flag shadow is assigned 0/1/Y/N/true/false"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
