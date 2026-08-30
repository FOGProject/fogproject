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

# GH-1120 replaced the $acmeLeaf key with a derived test: a canonical vhost
# cert path that resolves OUTSIDE the web PKI zone dir means the leaf is managed
# elsewhere. _externallyManagedLeaf reads $fogprogramdir through _pkiZoneDir, so
# the sandbox needs both the dir and a leaf inside it to exercise either answer.
fogprogramdir="$WORK/fogprog"
mkdir -p "$fogprogramdir/pki/web/leaf"
cp "$WORK/fogown.pem" "$fogprogramdir/pki/web/leaf/.webLeaf.pem"

reset_env() {
    etcconf=""; PKI_web_vhost_cert=""; sslfullchain=""
    NET_hostname=""; NET_fog_server_ip=""
    PKI_web_cert_publicly_trusted=""; WEB_url_proto="https"
    selfCacertOpts=(); trustAnchorPem=""
    # Not decoration: _resolveSelfCacert redirects to $error_log, and an unset
    # one is an ambiguous redirect that fails the whole command, so the
    # function returns empty opts and every anchoring assertion passes for the
    # wrong reason.
    error_log="$WORK/error.log"
}

echo "== _servedCertName: which certificate is asked =="

# A. The vhost wins over FOG's own copy. This is the case that matters: on an
#    externally-managed install ${PKI_web_vhost_cert} still names FOG's leaf while the web
#    server serves somebody else's.
reset_env
etcconf="$WORK/apache.conf"
printf '<VirtualHost *:443>\n    SSLCertificateFile %s\n    SSLCertificateKeyFile %s\n</VirtualHost>\n' \
    "$WORK/served.pem" "$WORK/served.key" > "$etcconf"
PKI_web_vhost_cert="$WORK/fogown.pem"
check "$(_servedCertName)" "served.example.org" "A: reads the CN of the cert the vhost names, not FOG's own"

# B. nginx spells it differently and must parse the same way.
reset_env
etcconf="$WORK/nginx.conf"
printf 'server {\n    ssl_certificate %s;\n    ssl_certificate_key %s;\n}\n' \
    "$WORK/served.pem" "$WORK/served.key" > "$etcconf"
PKI_web_vhost_cert="$WORK/fogown.pem"
check "$(_servedCertName)" "served.example.org" "B: parses nginx ssl_certificate"

# C. ssl_certificate_key must NOT be mistaken for the leaf. It is a key file, so
#    openssl x509 yields nothing and the answer would silently fall through to
#    the next candidate -- passing for the wrong reason unless pinned.
reset_env
etcconf="$WORK/keyonly.conf"
printf 'server {\n    ssl_certificate_key %s;\n}\n' "$WORK/served.key" > "$etcconf"
PKI_web_vhost_cert="$WORK/fogown.pem"
check "$(_servedCertName)" "fogown.example.org" "C: ssl_certificate_key is not read as the leaf"

# C2. The vhost names a file that EXISTS but is not a certificate. The first
#     existing candidate wins outright, so the answer falls through to
#     ${NET_hostname} rather than to FOG's own leaf -- deliberately. Reporting a CN
#     read off a certificate the browser is NOT being served is worse than
#     reporting none: every URL printed from it looks authoritative and warns
#     anyway. Pinned because the loop this replaced did the opposite silently.
reset_env
etcconf="$WORK/junkleaf.conf"
printf 'not a certificate\n' > "$WORK/junk.pem"
printf 'server {\n    ssl_certificate %s;\n}\n' "$WORK/junk.pem" > "$etcconf"
PKI_web_vhost_cert="$WORK/fogown.pem"
NET_hostname="fog.example.org"
check "$(_servedCertName)" "fog.example.org" "C2: an unparseable served leaf does not fall back to FOG's own"

# D. No vhost to consult -- fall back to FOG's own leaf.
reset_env
PKI_web_vhost_cert="$WORK/fogown.pem"
check "$(_servedCertName)" "fogown.example.org" "D: falls back to \${PKI_web_vhost_cert}"

# E. No readable certificate anywhere -- fall back to \${NET_hostname}, which is what
#    _createWebLeaf would have set that CN from anyway.
reset_env
PKI_web_vhost_cert="$WORK/does-not-exist.pem"
NET_hostname="fog.example.org"
check "$(_servedCertName)" "fog.example.org" "E: falls back to \${NET_hostname}"

# F. Nothing at all -- the address, last.
reset_env
NET_fog_server_ip="10.0.0.5"
check "$(_servedCertName)" "10.0.0.5" "F: falls back to \${NET_fog_server_ip} last"

echo "== _resolveSelfCacert: when FOG's root is the wrong anchor =="

printf 'not-a-real-anchor\n' > "$WORK/anchor.pem"
_resolveTrustAnchor() { trustAnchorPem="$WORK/anchor.pem"; return 0; }

# G. Plain HTTP verifies against the system store, unchanged.
reset_env; WEB_url_proto="http"
_resolveSelfCacert
check "${#selfCacertOpts[@]}" "0" "G: no --cacert on a plain HTTP install"

# H/I. The regression this exists for. An ACME leaf chains to a PUBLIC root the
#      system store already holds, and --cacert REPLACES that store -- so naming
#      FOG's root removes the only trust that would have worked.
reset_env; PKI_web_vhost_cert="$WORK/served.pem"
_resolveSelfCacert
check "${#selfCacertOpts[@]}" "0" "H: no --cacert when the leaf is externally managed"

reset_env; PKI_web_cert_publicly_trusted="yes"
_resolveSelfCacert
check "${#selfCacertOpts[@]}" "0" "I: no --cacert when PKI_web_cert_publicly_trusted=yes"

# J. The ordinary FOG-issued install still pins to FOG's own anchor -- named
#    explicitly inside the zone dir, so this exercises the predicate's other
#    answer rather than only its "path unset" fallback.
reset_env; PKI_web_vhost_cert="$fogprogramdir/pki/web/leaf/.webLeaf.pem"
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
if ! awk '/^checkWebTier\(\) \{/,/^\}/' "$FUNCS" | grep -q 'probeUrl=.*\${NET_fog_server_ip}'; then
    ok "M: checkWebTier dials the resolved name, not \${NET_fog_server_ip}"
else
    bad "M: checkWebTier still dials \${NET_fog_server_ip}"
fi

# N. No self-addressed call may be sent to a proxy.
#
# A FOG server behind corporate egress filtering has http_proxy/https_proxy in
# root's environment, and curl honors them for every host that is not in
# no_proxy -- including the server's own name and its own LAN address. The
# request then goes to the proxy, which either cannot route back or refuses to
# CONNECT. Observed on backupDB() as
# `curl: (56) CONNECT tunnel failed, response 502`, i.e. the backup step failing
# for a reason with nothing to do with the database, and reported with a blank
# explanation because plain -s also suppresses curl's error text.
#
# Matched on the URL each call dials rather than on a list of function names, so
# a self-call added later is covered without editing this.
# Any curl invocation naming one of the addresses this server dials itself (or
# its master) by. The URL can sit a long way down the line, after a dozen -d
# flags, so this matches the whole line rather than a URL-shaped prefix.
selfcalls=$(grep -nE '\bcurl\b' "$FUNCS" \
    | grep -E '\$\{NET_fog_server_ip\}|\$\{DB_host\}|\$\{selfName\}|selfCacertOpts')
n=$(printf '%s\n' "$selfcalls" | grep -cvE '^$')
[[ $n -ge 5 ]] && ok "N: found $n self-addressed curl calls to check" \
    || bad "N: only found $n self-addressed curl calls; the match is probably broken"
proxied=""
while IFS= read -r line; do
    [[ -z $line ]] && continue
    [[ $line == *"--noproxy"* ]] || proxied="$proxied ${line%%:*}"
done <<< "$selfcalls"
check "$proxied" "" "N: every self-addressed curl passes --noproxy"

# O. And the dump fetch reports WHY it failed.
#    The block builds a $dbwhy string for five distinct failure modes; plain -s
#    silences curl's message, so the "curl exited N" branch had nothing to put
#    after the colon.
if awk '/dbhttpcode=\$\(curl/,/dbcurlstat=\$\?/' "$FUNCS" | grep -q 'curl -sS'; then
    ok "O: the dump fetch uses -sS, so its failure reason is not blank"
else
    bad "O: the dump fetch still uses plain -s -- \$dbcurlerr will be empty"
fi

# P. jq only runs when curl actually produced a body.
#    Unconditional, the redirection `< \$dbraw` failed on any curl error and bash
#    printed "No such file or directory" into the middle of the progress line,
#    ahead of the guards that would have explained the real fault.
if awk '/dbcurlstat=\$\?/,/dbjqstat=1/' "$FUNCS" | grep -q 'if \[\[ -f \$dbraw \]\]'; then
    ok "P: jq is guarded on curl having written the response body"
else
    bad "P: jq runs unconditionally, so a curl failure leaks a bash error"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
