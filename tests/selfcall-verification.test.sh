#!/bin/bash
#
# Guards how the installer addresses and verifies its own web tier.
#
#   tests/selfcall-verification.test.sh
#
# Three calls in this installer are made BY the FOG server TO the FOG server:
# backupDB()'s dump fetch, checkWebTier()'s liveness probe, and updateDB()'s
# schema POST. They used to pass -k. GH-1169 made them verify, which is right --
# the schema POST carries X-Fog-Install-Token, and -k handed that to whatever
# answered -- but it verified a chain fetched from an address rather than a name.
#
# That combination is unsatisfiable on any server whose certificate came from a
# public CA, because no public CA will issue for an IP address. Measured on a
# live Let's Encrypt install: five DNS SANs, no IP SAN, so dialling the address
# failed the hostname check even before the anchor was considered. The install
# aborted at checkWebTier and took updateDB and _publishLocalBootFiles with it.
#
# So the name comes from the certificate that is actually being SERVED, and the
# anchor is left alone when that certificate was issued outside FOG. Both are
# pinned here, along with the split that keeps GH-1169's fix intact: the
# liveness probe may retry insecurely to prove the site is up, and the
# token-carrying schema POST may never do that.
#
# Needs openssl. Runs entirely on generated fixtures -- no install, no network,
# no root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

command -v openssl >/dev/null 2>&1 || {
    echo "SKIP: openssl is not installed"
    exit 0
}

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
check() { [[ $1 == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# --- fixtures ----------------------------------------------------------------
# Two self-signed leaves with different CNs, so "read the served one" and "read
# FOG's own one" are distinguishable answers rather than the same string twice.
mkleaf() {
    openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
        -subj "/CN=$1/O=FOG Project" \
        -keyout "$WORK/$2.key" -out "$WORK/$2.pem" >/dev/null 2>&1
}
mkleaf "served.example.org" served
mkleaf "fogown.example.org" fogown

reset_env() {
    etcconf=""; sslPubCert=""; sslfullchain=""
    hostname=""; ipaddress=""
    acmeLeaf=""; publicWebCert=""; httpProto="https"
    selfCacertOpts=(); trustAnchorPem=""
    # Not decoration: _resolveSelfCacert redirects to $error_log, and an unset
    # one is an ambiguous redirect that fails the whole command, so the
    # function returns empty opts and every anchoring assertion passes for the
    # wrong reason.
    error_log="$WORK/error.log"
}

echo "== _servedCertName: which certificate is asked =="

# A. The vhost wins over FOG's own copy. This is the case that matters: on an
#    externally-managed install $sslPubCert still names FOG's leaf while the web
#    server serves somebody else's.
reset_env
etcconf="$WORK/apache.conf"
printf '<VirtualHost *:443>\n    SSLCertificateFile %s\n    SSLCertificateKeyFile %s\n</VirtualHost>\n' \
    "$WORK/served.pem" "$WORK/served.key" > "$etcconf"
sslPubCert="$WORK/fogown.pem"
check "$(_servedCertName)" "served.example.org" "A: reads the CN of the cert the vhost names, not FOG's own"

# B. nginx spells it differently and must parse the same way.
reset_env
etcconf="$WORK/nginx.conf"
printf 'server {\n    ssl_certificate %s;\n    ssl_certificate_key %s;\n}\n' \
    "$WORK/served.pem" "$WORK/served.key" > "$etcconf"
sslPubCert="$WORK/fogown.pem"
check "$(_servedCertName)" "served.example.org" "B: parses nginx ssl_certificate"

# C. ssl_certificate_key must NOT be mistaken for the leaf. It is a key file, so
#    openssl x509 yields nothing and the answer would silently fall through to
#    the next candidate -- passing for the wrong reason unless pinned.
reset_env
etcconf="$WORK/keyonly.conf"
printf 'server {\n    ssl_certificate_key %s;\n}\n' "$WORK/served.key" > "$etcconf"
sslPubCert="$WORK/fogown.pem"
check "$(_servedCertName)" "fogown.example.org" "C: ssl_certificate_key is not read as the leaf"

# D. No vhost to consult -- fall back to FOG's own leaf.
reset_env
sslPubCert="$WORK/fogown.pem"
check "$(_servedCertName)" "fogown.example.org" "D: falls back to \$sslPubCert"

# E. No readable certificate anywhere -- fall back to \$hostname, which is what
#    _createWebLeaf would have set that CN from anyway.
reset_env
sslPubCert="$WORK/does-not-exist.pem"
hostname="fog.example.org"
check "$(_servedCertName)" "fog.example.org" "E: falls back to \$hostname"

# F. Nothing at all -- the address, last.
reset_env
ipaddress="10.0.0.5"
check "$(_servedCertName)" "10.0.0.5" "F: falls back to \$ipaddress last"

echo "== _resolveSelfCacert: when FOG's root is the wrong anchor =="

printf 'not-a-real-anchor\n' > "$WORK/anchor.pem"
_resolveTrustAnchor() { trustAnchorPem="$WORK/anchor.pem"; return 0; }

# G. Plain HTTP verifies against the system store, unchanged.
reset_env; httpProto="http"
_resolveSelfCacert
check "${#selfCacertOpts[@]}" "0" "G: no --cacert on a plain HTTP install"

# H/I. The regression this exists for. An ACME leaf chains to a PUBLIC root the
#      system store already holds, and --cacert REPLACES that store -- so naming
#      FOG's root removes the only trust that would have worked.
reset_env; acmeLeaf="yes"
_resolveSelfCacert
check "${#selfCacertOpts[@]}" "0" "H: no --cacert when acmeLeaf=yes"

reset_env; publicWebCert="yes"
_resolveSelfCacert
check "${#selfCacertOpts[@]}" "0" "I: no --cacert when publicWebCert=yes"

# J. The ordinary FOG-issued install still pins to FOG's own anchor.
reset_env
_resolveSelfCacert
check "${selfCacertOpts[0]}" "--cacert" "J: still anchors on a FOG-issued install"

echo "== the liveness/secret split (source-level) =="

# K. GH-1169's actual fix. The schema POST carries an install token that grants
#    a schema deploy on a server with no users yet; it must never fall back to
#    skipping verification, however convenient that would be.
schemaLine=$(grep -n 'X-Fog-Install-Token' "$FUNCS" | grep 'curl' | head -1)
if [[ -n $schemaLine && $schemaLine != *" -k "* && $schemaLine != *"-sk"* ]]; then
    ok "K: the token-carrying schema POST does not pass -k"
else
    bad "K: the schema POST appears to skip verification"
fi

# L. The probe's insecure retry is a diagnostic and must stay inside the TLS
#    branch -- a blanket retry would re-hide the empty-body case this check was
#    written for (forums topic 18204).
if awk '/^checkWebTier\(\) \{/,/^\}/' "$FUNCS" | grep -q 'advice == tls' \
   && awk '/advice == tls/,/^    fi/' "$FUNCS" | grep -q 'curl -sS -k'; then
    ok "L: checkWebTier's insecure retry is guarded to the TLS branch"
else
    bad "L: could not confirm the retry is TLS-only"
fi

# M. Every self-call addresses the name, not the address.
if ! awk '/^checkWebTier\(\) \{/,/^\}/' "$FUNCS" | grep -q 'probeUrl=.*\${ipaddress}'; then
    ok "M: checkWebTier dials the resolved name, not \$ipaddress"
else
    bad "M: checkWebTier still dials \$ipaddress"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
