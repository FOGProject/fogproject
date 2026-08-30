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
# fallback, and ${WEB_url_primary}=address swaps that order for an admin whose
# network reaches this server by address. Five things about that are easy to
# regress and none of them would fail an install, so nothing would catch them:
#
#   0. The explanation must follow the CERTIFICATE, not the ordering. A
#      FOG-issued leaf carries an IP SAN for every address in
#      ${PKI_san_ip_addresses}, so the address is not a name mismatch there and
#      the "only one that will not warn" wording is simply false -- it was
#      printed unconditionally until GH-1488. Equally, ${WEB_url_primary}=address
#      orders the URLs and does not make one work: on a names-only leaf the
#      address still warns and the text still has to say so.
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

# _certServesAddress is extracted for real rather than stubbed: the SAN
# extraction inside it is the half most likely to rot silently -- openssl's
# -text layout, and the difference between an "IP Address:" entry and a "DNS:"
# one that merely looks like an address -- and a stub would report it healthy
# forever. Fixtures for it are generated at the bottom of this file.
#
# Renamed on the way in. The ordering cases below stub _certServesAddress under
# its real name so they need no certificate each, and the stub is defined after
# this eval -- so extracting under the same name would silently hand the unit
# tests the stub, and every one of them would pass on whatever CERT_HAS_IP
# happened to hold. They did, before this line renamed it.
eval "$(sed -n '/^_certServesAddress() {/,/^}/p' "$FUNCS" \
    | sed '1s/^_certServesAddress()/_realCertServesAddress()/')"
declare -F _realCertServesAddress >/dev/null || { echo "ERROR: could not extract _certServesAddress" >&2; exit 1; }

# validip echoes 0 for a valid IPv4 literal, 1 otherwise -- same contract as the
# installer's own, which lives in lib/common/config.sh and needs no sourcing for
# this.
validip() { [[ $1 =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]] && echo 0 || echo 1; }

WEB_root="/fog/"
NET_fog_server_ip="10.0.0.10"
CERT_CN=""
CERT_HAS_IP=no
_servedCertName() { echo "$CERT_CN"; }
# A path, not a certificate: the ordering cases below drive the address
# question through CERT_HAS_IP instead, so they need no openssl fixture each.
# _certServesAddress itself is exercised against real certificates further down.
_servedCertPath() { echo "/nonexistent/served.pem"; }
_certServesAddress() { [[ $CERT_HAS_IP == yes ]]; }

# $3 = does the served certificate carry the address as an IP SAN (default no,
# i.e. the names-only leaf every pre-existing case below was written for).
# $4 = ${WEB_url_primary} (default name).
emit() {
    WEB_url_proto="$1"; CERT_CN="$2"
    CERT_HAS_IP="${3:-no}"; WEB_url_primary="${4:-name}"
    _managementUrls
}

# Line number of the first management URL for host $2 in output $1, or empty.
urlline() { grep -n -- "$2/fog/management" <<<"$1" | head -1 | cut -d: -f1; }

# Asserts $2 appears before $3 in $1.
before() {
    local a b
    a=$(urlline "$1" "$2"); b=$(urlline "$1" "$3")
    [[ -n $a && -n $b && $a -lt $b ]] \
        && ok "$4" || bad "$4 ($2@${a:-?}, $3@${b:-?})"
}

echo "HTTPS with a name in the certificate"
out=$(emit https "fogserver.example.com")
has "$out" "https://fogserver.example.com/fog/management" "prints the certificate name URL"
has "$out" "https://10.0.0.10/fog/management"             "prints the address URL too"
# Order is the point: the name is the recommendation, the address is the
# fallback. A reader takes the first URL offered.
before "$out" "fogserver.example.com" "10.0.0.10" "the name comes before the address"
has "$out" "certificate names fogserver.example.com instead" "says why the address warns"

echo
echo "HTTPS where the certificate covers the address too (a FOG-issued leaf)"
out=$(emit https "fogserver.example.com" yes)
has "$out" "https://fogserver.example.com/fog/management" "still prints the name URL"
has "$out" "https://10.0.0.10/fog/management"             "still prints the address URL"
before "$out" "fogserver.example.com" "10.0.0.10" "default order is unchanged by the IP SAN"
# The two sentences that are false on this leaf. Both are what comes back if the
# branch is dropped, and neither would fail an install.
hasnt "$out" "the only one that will not warn" "does not call the name the only URL that will not warn"
hasnt "$out" "certificate names fogserver.example.com instead" "does not call the address a name mismatch"
has "$out" "covers the address as well as the" "says the certificate covers both"

echo
echo "WEB_url_primary=address"
out=$(emit https "fogserver.example.com" yes address)
before "$out" "10.0.0.10" "fogserver.example.com" "puts the address first"
has "$out" "https://fogserver.example.com/fog/management" "still prints the name URL"
is "$(grep -c "10.0.0.10/fog/management" <<<"$out")" "1" "the address URL is printed once, not twice"
hasnt "$out" "Use the first one" "no name-first recommendation contradicting the order"

# Ordering is a preference; the mismatch is a fact. Choosing address-first on a
# names-only leaf must NOT silence the warning -- that would recommend a URL
# the browser rejects, which is the failure the name-first default exists to
# avoid in the first place.
out=$(emit https "fogserver.example.com" no address)
before "$out" "10.0.0.10" "fogserver.example.com" "still puts the address first"
has "$out" "name mismatch" "says the address is a name mismatch on a names-only leaf"
has "$out" "WEB_url_primary=address" "names the setting that moved it"

# An unrecognized value is the default, not a third behavior.
out=$(emit https "fogserver.example.com" yes "adress")
before "$out" "fogserver.example.com" "10.0.0.10" "a misspelled value falls back to name-first"

# With no usable name there is one URL, so the setting has nothing to reorder.
out=$(emit https "10.0.0.10" no address)
is "$(grep -c "10.0.0.10/fog/management" <<<"$out")" "1" "no name: still exactly one URL"

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
echo "A non-default webroot is honored"
WEB_root="/"
out=$(emit https "fog.example.com")
has "$out" "https://fog.example.com/management" "webroot / gives a single slash"
hasnt "$out" "//management" "no doubled slash"
WEB_root="/fog/"

echo
echo "_certServesAddress against real certificates"
if ! command -v openssl >/dev/null 2>&1; then
    echo "  skip  openssl not available"
else
    fixdir=$(mktemp -d)
    trap 'rm -rf "$fixdir"' EXIT
    # rsa:2048, not the installer's 4096: these are discarded at the end of this
    # block and the key size has no bearing on SAN parsing.
    mkfix() {
        if [[ -n $3 ]]; then
            openssl req -x509 -newkey rsa:2048 -nodes -keyout /dev/null \
                -out "$fixdir/$1.pem" -days 1 -subj "/CN=$2" \
                -addext "subjectAltName=$3" >/dev/null 2>&1
        else
            openssl req -x509 -newkey rsa:2048 -nodes -keyout /dev/null \
                -out "$fixdir/$1.pem" -days 1 -subj "/CN=$2" >/dev/null 2>&1
        fi
        [[ -s $fixdir/$1.pem ]] || { echo "ERROR: fixture $1 not generated" >&2; exit 1; }
    }
    mkfix both     fog.example.com "IP:10.0.0.10,DNS:fog.example.com,DNS:fog"
    mkfix nameonly fog.example.com "DNS:fog.example.com,DNS:fog"
    # 10.0.0.100 shares a prefix with 10.0.0.10: a substring or glob comparison
    # accepts it, a literal one does not.
    mkfix near     fog.example.com "IP:10.0.0.100,DNS:fog.example.com"
    # An address-shaped DNS SAN is not an iPAddress SAN, and no TLS client
    # accepts it as one -- so neither may this.
    mkfix dnsip    fog.example.com "DNS:fog.example.com,DNS:10.0.0.10"
    # Nor is a commonName, whatever it holds.
    mkfix cnonly   10.0.0.10       ""

    yn() { _realCertServesAddress "$1" 10.0.0.10 && echo yes || echo no; }
    is "$(yn "$fixdir/both.pem")"     yes "matches an IP SAN for the address"
    is "$(yn "$fixdir/nameonly.pem")" no  "no match on a names-only certificate"
    is "$(yn "$fixdir/near.pem")"     no  "no match on 10.0.0.100 (prefix, not equal)"
    is "$(yn "$fixdir/dnsip.pem")"    no  "no match on an address-shaped DNS SAN"
    is "$(yn "$fixdir/cnonly.pem")"   no  "no match on an address in the commonName"
    is "$(yn "$fixdir/absent.pem")"   no  "no match when the file does not exist"
    # A contract assertion, not a guard test: the implementation satisfies it
    # twice over (the early return, and the literal comparison no SAN can equal),
    # so no single-line mutation turns it red. It is here to pin the contract for
    # a future rewrite, and it earns its place only in combination -- loosening
    # the comparison to a prefix match AND dropping the early return does make it
    # fail, which is the pair that would otherwise match every SAN on an empty
    # address.
    is "$(_realCertServesAddress "$fixdir/both.pem" "" && echo yes || echo no)" no \
        "no match when no address is given"
fi

echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
