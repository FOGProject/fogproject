#!/bin/bash
#
# Guards the interactive installer's prompts against reading into a dead
# variable.
#
#   tests/interactive-prompt-wiring.test.sh
#
# `.fogsettings` is SOURCED, so a key name IS a shell variable name. That is
# what made the GH-1120 rename a ~2500-site variable sweep, and it is why the
# prompts in lib/common/input.sh are written as
#
#     while [[ -z ${SOME_KEY} ]]; do
#         read SOME_KEY          # <-- the prompt fills the key directly
#         case ${SOME_KEY} in ...
#     done
#
# The rename moved every `while`/`case`/test onto the new spelling and left nine
# `read` statements on the OLD one. The answer went to a variable nothing reads,
# the loop re-tested the still-empty new key, and the `""` branch of the case
# supplied a default -- so the prompt appeared, accepted input, and silently
# discarded it:
#
#   read installtype   / case ${FOG_install_type}      storage node unselectable
#   read interface     / while [[ -z ${NET_interface} ]]   re-prompts forever
#   read dodhcp        / case ${DHCP_enabled}          DHCP always off
#   read routeraddress / case ${DHCP_router}           router IP ignored
#   read dnsaddress    / case ${DHCP_dns_server_ip}    DNS ignored
#   read installlang   / case ${FOG_install_lang}      language packs never install
#   read snmysqlhost   / while [[ -z ${DB_host} ]]     INFINITE LOOP
#   read snmysqlpass   / while [[ -z ${DB_password} ]] INFINITE LOOP
#   read hostname      / case $blHost (newinput.sh)    hostname change ignored
#
# Every prompt sits behind `[[ -z $autoaccept ]]`, so `-y` runs were unaffected
# and the whole existing suite stayed green -- nothing covered interactive
# wiring at all. The two storage-node loops have no autoaccept guard, so a
# storage-node install hung with no way out.
#
# The invariant: a `read` target is either a key the installer manages, or a
# local scratch variable. It is NEVER a retired spelling -- all nine bugs above
# read into a key that is in deprecatedKeys, so that one test catches the whole
# class, including a recurrence under some future rename.
#
# Pure source analysis: no install, no network, no root, no openssl.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INPUTS=("$REPO/lib/common/input.sh" "$REPO/lib/common/newinput.sh")

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
for f in "${INPUTS[@]}"; do
    [[ -f $f ]] || { echo "ERROR: $f not found" >&2; exit 1; }
done

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# Both key arrays come out of the real source with COMMENTS STRIPPED. The arrays
# carry a lot of prose that names keys, so a grep over the raw block matches a
# key that is merely mentioned -- the same trap install-settings-resolution
# documents, and the same helper.
arrayKeys() {
    sed -n "/local -a $1=(/,/^    )/p" "$FUNCS" \
        | sed -e 's/#.*$//' -e "s/local -a $1=(//" -e 's/)//' \
        | tr -s ' \n' '\n\n' | grep -vE '^$'
}
managed="$(arrayKeys managedKeys)"
deprecated="$(arrayKeys deprecatedKeys)"
inlist() { printf '%s\n' "$2" | grep -qxF "$1"; }

[[ -n $managed ]]    || { echo "ERROR: managedKeys came back empty" >&2; exit 1; }
[[ -n $deprecated ]] || { echo "ERROR: deprecatedKeys came back empty" >&2; exit 1; }

# Local scratch variables the prompts legitimately read into: a yes/no answer
# that is then translated into a key by the case below it. Deliberately a short
# explicit allowlist rather than a pattern -- "it looks local" is how
# `read interface` passed review.
scratch="
blInt
blRouter
blDNS
blHost
blReports
blConstrain
blExtCA
answer
inextcacert
inextcakey
inextcaroot
"

# Every `read` in both files, one record per statement: "file:line target".
readTargets() {
    for f in "$@"; do
        grep -nE '^[[:space:]]*read[[:space:]]+(-r[[:space:]]+)?[A-Za-z_][A-Za-z0-9_]*[[:space:]]*$' "$f" \
            | while IFS= read -r hit; do
                ln="${hit%%:*}"
                var="$(printf '%s' "${hit#*:}" | sed -E 's/^[[:space:]]*read[[:space:]]+(-r[[:space:]]+)?//' | tr -d '[:space:]')"
                printf '%s:%s %s\n' "$(basename "$f")" "$ln" "$var"
            done
    done
}

allReads="$(readTargets "${INPUTS[@]}")"
count=$(printf '%s\n' "$allReads" | grep -cvE '^$')

# A sanity floor. If the extraction silently stops matching -- a reformat, a
# `read -p`, a here-string -- every assertion below passes vacuously and this
# test becomes decoration.
[[ $count -ge 15 ]] && ok "found $count read statements to check" \
    || bad "only found $count read statements; extraction is probably broken"

# --- A. the actual bug: never read into a retired key ------------------------
retired=""
while read -r where var; do
    [[ -n $var ]] || continue
    inlist "$var" "$deprecated" && retired="$retired $where($var)"
done <<< "$allReads"
is "$retired" "" "no prompt reads into a deprecatedKeys spelling"

# --- B. and every target is accounted for ------------------------------------
# Catches a NEW old-style name that was never a .fogsettings key at all, which
# test A cannot see.
unknown=""
while read -r where var; do
    [[ -n $var ]] || continue
    inlist "$var" "$managed" && continue
    inlist "$var" "$scratch" && continue
    unknown="$unknown $where($var)"
done <<< "$allReads"
is "$unknown" "" "every read target is a managed key or an allowlisted scratch var"

# --- C. the nine specific sites, by key --------------------------------------
# Named individually so a regression names the prompt that broke rather than
# just a count. Each is the canonical key its own loop tests.
for key in FOG_install_type NET_interface DHCP_enabled DHCP_router \
           DHCP_dns_server_ip FOG_install_lang DB_host DB_password; do
    printf '%s\n' "$allReads" | grep -qE "[[:space:]]${key}\$" \
        && ok "input.sh reads into ${key}" \
        || bad "nothing reads into ${key} -- its prompt cannot fill it"
done
# NET_hostname is the one prompt that deliberately does NOT read straight into
# its key, so it is asserted differently from the eight above.
#
# What an admin types now passes through _usableHostname() before it is
# accepted. That is not tidying: this prompt never validated anything from
# either end, and the value reaches an OpenSSL config directly -- an empty or
# "(none)" hostname produces `subjectAltName = DNS:`, which openssl refuses,
# aborting the installer with the web server stopped and not yet restarted.
#
# So assert the validation is in the path, and that the bypass has not come
# back. That the prompt can still FILL the key is checked structurally by test D
# below, which is what the direct-read assertion was standing in for.
grep -qE '^[[:space:]]*NET_hostname=\$\(_usableHostname "\$answer"\)' "$REPO/lib/common/newinput.sh" \
    && ok "newinput.sh validates a typed hostname through _usableHostname" \
    || bad "newinput.sh accepts a typed hostname without validating it"
grep -qE '^[[:space:]]*read[[:space:]]+(-r[[:space:]]+)?NET_hostname[[:space:]]*$' "$REPO/lib/common/newinput.sh" \
    && bad "newinput.sh reads straight into NET_hostname, bypassing validation" \
    || ok "newinput.sh never reads straight into NET_hostname"

# --- D. no `while [[ -z ${KEY} ]]` loop can spin without a way to fill KEY ---
# The two storage-node loops hung precisely because the only read inside them
# targeted a dead name and the loop condition could never become false. A loop
# over a managed key must contain either a read into it or an assignment to it.
spinners=""
for f in "${INPUTS[@]}"; do
    base="$(basename "$f")"
    while IFS= read -r hit; do
        ln="${hit%%:*}"
        key="$(printf '%s' "${hit#*:}" | sed -E 's/.*-z[[:space:]]+\$\{?([A-Za-z_][A-Za-z0-9_]*)\}?.*/\1/')"
        inlist "$key" "$managed" || continue
        # Body runs to the matching `done` at the same indentation.
        indent="$(printf '%s' "${hit#*:}" | sed -E 's/[^[:space:]].*//')"
        end=$(awk -v s="$ln" -v ind="^${indent}done" 'NR>s && $0 ~ ind {print NR; exit}' "$f")
        [[ -n $end ]] || { spinners="$spinners $base:$ln($key:no-done)"; continue; }
        body="$(sed -n "$((ln+1)),$((end-1))p" "$f")"
        printf '%s\n' "$body" | grep -qE "^[[:space:]]*read[[:space:]]+(-r[[:space:]]+)?${key}[[:space:]]*\$" && continue
        printf '%s\n' "$body" | grep -qE "^[[:space:]]*${key}=" && continue
        spinners="$spinners $base:$ln($key)"
    done < <(grep -nE '^[[:space:]]*while[[:space:]]+\[\[[[:space:]]+-z[[:space:]]+\$\{?[A-Za-z_][A-Za-z0-9_]*\}?[[:space:]]+\]\]' "$f")
done
is "$spinners" "" "every 'while [[ -z KEY ]]' loop can actually fill KEY"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
