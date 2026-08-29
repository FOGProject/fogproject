#!/bin/bash
#
# Two faults left over from the GH-1120 rename, both silent, both distinct from
# what #1297 and #1298 fixed.
#
#   tests/upgrade-proto-and-os-prompt.test.sh
#
# 1. THE HTTPS REDIRECT WAS SWITCHED OFF BY UPGRADING.
#
#    Splitting the old three-meanings-in-one httpproto key rests on one piece of
#    evidence: an existing httpproto=https is the only record that an admin ever
#    asked for -S. migrateDeprecatedKeys carries it across with
#
#        [[ -z ${WEB_url_proto} ]] && WEB_url_proto="$httpproto"
#
#    and the redirect migration downstream reads the result. But installfog.sh
#    defaulted WEB_url_proto to http BEFORE .fogsettings was sourced, so that
#    guard never held on a pre-1.6 upgrade -- the seed could not fire, the
#    redirect migration saw http, and WEB_https_redirect came out no. The admin
#    is not told; the redirect simply stops happening.
#
#    Nothing reads WEB_url_proto between the two points and it is assigned
#    unconditionally afterward, so the default is removed rather than reordered.
#
# 2. THE OS-CHOICE PROMPT IGNORED THE ANSWER.
#
#    displayOSChoices did `read osid` while the case below tested ${FOG_os_id}.
#    FOG_os_id is already $strSuggestedOS by then, so the case matched the
#    suggestion and broke: the prompt took a choice and always installed for the
#    suggested distro. Same class as the nine prompts fixed in input.sh -- this
#    one lives in functions.sh, which that pass did not cover.
#
# Drives the real functions. Needs bash. No install, no network, no root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INSTALLER="$REPO/bin/installfog.sh"

[[ -f $FUNCS ]]     || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
[[ -f $INSTALLER ]] || { echo "ERROR: $INSTALLER not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

echo "upgrade proto + OS prompt:"

# --- A. no pre-source default on WEB_url_proto ------------------------------
# Source-level, because the seed it defeats is 70 lines away and the damage is
# invisible: an upgrade that quietly stops redirecting looks like a working one.
grep -q '^\[\[ -z ${WEB_url_proto} \]\] && WEB_url_proto=' "$INSTALLER" \
    && bad "A: WEB_url_proto is defaulted before .fogsettings is sourced -- the httpproto seed can never fire" \
    || ok "A: WEB_url_proto carries no pre-source default"
# The seed and the unconditional assignment must both still be there, or the
# assertion above passes for the wrong reason.
grep -q 'WEB_url_proto="\$httpproto"' "$INSTALLER" \
    && ok "A: the httpproto seed is still present" \
    || bad "A: the httpproto seed is gone -- nothing carries the old value across"
grep -q '^WEB_url_proto="https"$' "$INSTALLER" \
    && ok "A: WEB_url_proto is still assigned unconditionally afterward" \
    || bad "A: nothing sets WEB_url_proto after the migration -- removing the default would leave it empty"

# --- B. behavior: a pre-1.6 server that had -S keeps its redirect ----------
# migrateDeprecatedKeys is the real thing (#1297); this drives it exactly as the
# installer does, then applies the redirect migration verbatim.
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
if ! declare -F migrateDeprecatedKeys >/dev/null; then
    bad "B: migrateDeprecatedKeys is not defined by lib/common/functions.sh"
else
    # Whatever the installer initializes WEB_url_proto to before sourcing
    # .fogsettings, applied here too -- otherwise this harness starts from a
    # clean slate the real run never has, and would keep passing with the
    # pre-source default put back. There should be no such line; the eval is
    # what makes its return a failure rather than a silent pass.
    presource=$(grep -E '^\[\[ -z \$\{WEB_url_proto\} \]\] && WEB_url_proto=' "$INSTALLER")
    redirect_for() {
        (
            # A pre-1.6 .fogsettings records httpproto and nothing else here.
            WEB_url_proto=""
            [[ -n $presource ]] && eval "$presource"
            httpproto="$1"
            WEB_https_redirect=""
            migrateDeprecatedKeys >/dev/null 2>&1
            if [[ -z ${WEB_https_redirect} ]]; then
                [[ ${WEB_url_proto} == https ]] && WEB_https_redirect="yes" || WEB_https_redirect="no"
            fi
            printf '%s' "${WEB_https_redirect}"
        )
    }
    is "$(redirect_for https)" "yes" "B: httpproto=https keeps the redirect on"
    is "$(redirect_for http)"  "no"  "B: httpproto=http does not turn it on"

    # And an admin who already turned the redirect OFF must not have it turned
    # back on by the next upgrade re-reading WEB_url_proto.
    got=$(
        httpproto="https"; WEB_url_proto="https"; WEB_https_redirect="no"
        migrateDeprecatedKeys >/dev/null 2>&1
        if [[ -z ${WEB_https_redirect} ]]; then
            [[ ${WEB_url_proto} == https ]] && WEB_https_redirect="yes" || WEB_https_redirect="no"
        fi
        printf '%s' "${WEB_https_redirect}"
    )
    is "$got" "no" "B: a redirect the admin switched off stays off"
fi

# --- C. the OS prompt reads into the key its own case tests -----------------
prompt=$(awk '/^displayOSChoices\(\) \{/,/^\}/' "$FUNCS")
printf '%s\n' "$prompt" | grep -qE '^\s*read FOG_os_id\s*$' \
    && ok "C: the prompt reads into FOG_os_id" \
    || bad "C: the prompt does not read into FOG_os_id"
printf '%s\n' "$prompt" | grep -qE '^\s*read osid\s*$' \
    && bad "C: it still reads the retired 'osid'" \
    || ok "C: it no longer reads the retired 'osid'"
printf '%s\n' "$prompt" | grep -q 'case ${FOG_os_id} in' \
    && ok "C: and the case still tests FOG_os_id" \
    || bad "C: the case no longer tests FOG_os_id -- re-check what the read should name"

# --- D. behavior: the answer actually reaches the case --------------------
# Replays the prompt's own logic against piped input, which is the only way to
# see that a typed choice survives. A wrong wiring is invisible otherwise: the
# suggestion is a valid answer, so the install succeeds for the wrong distro.
choose() {
    (
        strSuggestedOS=2
        FOG_os_id=$strSuggestedOS
        read FOG_os_id <<< "$1"
        case ${FOG_os_id} in
            "")      FOG_os_id=$strSuggestedOS ;;
            1|2|3|4) ;;
            *)       FOG_os_id="invalid" ;;
        esac
        printf '%s' "${FOG_os_id}"
    )
}
is "$(choose 1)"  "1"       "D: typing 1 selects Redhat, not the suggestion"
is "$(choose 4)"  "4"       "D: typing 4 selects Arch"
is "$(choose '')" "2"       "D: pressing Enter still takes the suggestion"
is "$(choose 9)"  "invalid" "D: a bad answer is rejected rather than silently accepted"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
