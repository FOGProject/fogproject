#!/bin/bash
#
# Guards the management-portal URLs the installer prints when it finishes.
#
#   tests/management-url-report.test.sh
#
# This used to be a single line naming ${NET_fog_server_ip}. On an HTTPS install
# that is the one address guaranteed to make the browser complain -- the
# certificate is issued for a NAME, so reaching the portal by address is a name
# mismatch every time -- so an admin's first contact with their new server was a
# security warning, with nothing on screen naming the URL that would have
# worked.
#
# _managementUrls() now prints the certificate's name first and the address as a
# fallback. Three things about that are easy to regress and none of them would
# fail an install, so nothing would catch them:
#
#   1. The name has to come from the CERTIFICATE (_servedCertName), not from
#      ${NET_hostname}. Those differ on an externally-issued or publicly-trusted
#      leaf, which carries only the names its issuer was asked for -- and
#      --public-web-cert is a supported path. Printing a name the certificate
#      does not carry sends the admin to a URL that warns exactly as loudly as
#      the address, while implying it will not.
#
#   2. _servedCertName's own last resort IS the address. So on a server with no
#      usable name it hands back the very thing the fallback line prints, and an
#      unguarded implementation emits the same URL twice under two different
#      captions -- one of which claims it is a certificate name.
#
#   3. The address must still be printed. DNS may not have caught up when the
#      install finishes, and on a server with no name it is the only thing that
#      works at all.
#
# No install, no network, no root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }
has() { grep -qF -- "$2" <<<"$1" && ok "$3" || bad "$3 (output did not contain '$2')"; }
hasnt() { grep -qF -- "$2" <<<"$1" && bad "$3 (output unexpectedly contained '$2')" || ok "$3"; }

# Read the real function rather than reimplementing it -- a test that restated
# the format would pass while the installer drifted.
eval "$(sed -n '/^_managementUrls() {/,/^}/p' "$FUNCS")"
declare -F _managementUrls >/dev/null || { echo "ERROR: could not extract _managementUrls" >&2; exit 1; }

# validip echoes 0 for a valid IPv4 literal, 1 otherwise -- same contract as the
# installer's own, which lives in lib/common/config.sh and needs no sourcing for
# this.
validip() { [[ $1 =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]] && echo 0 || echo 1; }

WEB_root="/fog/"
NET_fog_server_ip="10.0.0.10"
CERT_CN=""
_servedCertName() { echo "$CERT_CN"; }

emit() { WEB_url_proto="$1"; CERT_CN="$2"; _managementUrls; }

echo "HTTPS with a name in the certificate"
out=$(emit https "fogserver.example.com")
has "$out" "https://fogserver.example.com/fog/management" "prints the certificate name URL"
has "$out" "https://10.0.0.10/fog/management"             "prints the address URL too"
# Order is the point: the name is the recommendation, the address is the
# fallback. A reader takes the first URL offered.
nameline=$(grep -n "fogserver.example.com/fog/management" <<<"$out" | head -1 | cut -d: -f1)
ipline=$(grep -n "10.0.0.10/fog/management" <<<"$out" | head -1 | cut -d: -f1)
[[ -n $nameline && -n $ipline && $nameline -lt $ipline ]] \
    && ok "the name comes before the address" \
    || bad "the name comes before the address (name@${nameline:-?}, ip@${ipline:-?})"
has "$out" "certificate names fogserver.example.com instead" "says why the address warns"

echo
echo "HTTPS with no usable name (_servedCertName falls back to the address)"
out=$(emit https "10.0.0.10")
is "$(grep -c "10.0.0.10/fog/management" <<<"$out")" "1" "the address URL is printed exactly once"
hasnt "$out" "certificate is" "does not caption the address as a certificate name"
has "$out" "--hostname" "points at --hostname as the fix"

echo
echo "HTTPS with an empty name (defensive -- _servedCertName should not, but)"
out=$(emit https "")
is "$(grep -c "10.0.0.10/fog/management" <<<"$out")" "1" "still exactly one address URL"
hasnt "$out" "https:///" "no empty-host URL is emitted"

echo
echo "HTTP"
out=$(emit http "fogserver.example.com")
has "$out" "http://fogserver.example.com/fog/management" "prints the name URL over http"
hasnt "$out" "https://" "does not switch scheme"
# The certificate wording is HTTPS-only: on a plain-HTTP install there may be no
# served certificate at all, so claiming one would be an invention.
hasnt "$out" "certificate" "no certificate wording on an http install"

out=$(emit http "10.0.0.10")
is "$(grep -c "10.0.0.10/fog/management" <<<"$out")" "1" "http, no name: one address URL"
hasnt "$out" "certificate" "http, no name: no certificate wording"

echo
echo "A non-default webroot is honoured"
WEB_root="/"
out=$(emit https "fog.example.com")
has "$out" "https://fog.example.com/management" "webroot / gives a single slash"
hasnt "$out" "//management" "no doubled slash"
WEB_root="/fog/"

echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
