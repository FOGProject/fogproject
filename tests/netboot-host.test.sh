#!/bin/bash
#
# Guards the host that HTTPS netboot addresses this server by.
#
#   tests/netboot-host.test.sh
#
# iPXE validates TLS strictly and has no --insecure, so the name in the netboot
# URL has to be a name the served certificate actually carries. There was no
# test over this at all: install-settings-resolution.test.sh covers which
# PROTOCOL is chosen but never calls the function that writes the URL, so
# nothing could see the host.
#
# What went wrong without one. configureDefaultiPXEfile() used ${NET_fog_server_ip} for
# the whole prior life of the line; when a name was introduced it was
# ${NET_hostname:-${NET_fog_server_ip}}, guarded only by validip -- and validhostname()
# accepts a single label, so a short "fog" passed every check and produced
# https://fog/fog/service/ipxe/boot.php against a certificate issued to
# fog.arrowheaddental.com. iPXE stopped at the handshake.
#
# That failed only on the path the change was written for. _defaultServerNames()
# puts both the FQDN and the short ${NET_hostname%%.*} in the SAN list, so a short
# name is a real SAN on a FOG-issued certificate. But _createWebLeaf() returns
# early when acmeLeaf/publicWebCert is set, so a publicly-issued leaf carries
# only the names its issuer was asked for -- and publicWebCert is one of exactly
# two things that select HTTPS netboot.
#
# The properties pinned here, in the order they are easiest to break again:
#
#   1. the name comes from the CERTIFICATE, not from ${NET_hostname} (A2, I);
#   2. a commonName is honoured only when there is no subjectAltName at all,
#      which is iPXE's own rule -- see docs/adr/0016 (D, E). E is the one most
#      likely to be "simplified" by someone who reads D;
#   3. an install that cannot satisfy this FAILS rather than writing an
#      unbootable default.ipxe (K, L, P);
#   4. HTTP netboot is untouched and still uses the address (O).
#
# Needs openssl. Runs entirely on generated fixtures -- no install, no network,
# no root, no database.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl is not installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
check() { [[ $1 == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }
rc()    { [[ $1 -eq $2 ]] && ok "$3" || bad "$3 (expected rc $2, got $1)"; }
has()   { [[ $1 == *"$2"* ]] && ok "$3" || bad "$3 (not found in '$1')"; }
hasnt() { [[ $1 != *"$2"* ]] && ok "$3" || bad "$3 (unexpectedly present in '$1')"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# A leaf with an explicit SAN set, via a config file rather than -addext:
# -addext needs OpenSSL 1.1.1, and the point of this suite is to run wherever
# the installer does. An empty $3 means NO subjectAltName extension at all,
# which is a different case from an empty one and is what D exercises.
mkleaf() {
    local out=$1 cn=$2 sans=$3 cnf="$WORK/$1.cnf"
    {
        echo "[req]"
        echo "distinguished_name = dn"
        echo "prompt = no"
        echo "[dn]"
        echo "CN = $cn"
        if [[ -n $sans ]]; then
            echo "[v3]"
            echo "subjectAltName = $sans"
        fi
    } > "$cnf"
    local -a ext=()
    [[ -n $sans ]] && ext=(-extensions v3)
    openssl req -x509 -newkey rsa:2048 -nodes -days 1 -config "$cnf" "${ext[@]}" \
        -keyout "$WORK/$out.key" -out "$WORK/$out.pem" >/dev/null 2>&1
}

# The publicly-issued shape: exactly the name its issuer was asked for. No short
# name, no IP. This is the reported failure.
mkleaf public   "fog.example.org" "DNS:fog.example.org"
# The FOG-issued shape: _defaultServerNames() adds the short form and the IPs.
mkleaf fogpki   "fog.example.org" "DNS:fog.example.org,DNS:fog,IP:10.0.0.1"
# CN only, no SAN extension whatsoever.
mkleaf cnonly   "fog.example.org" ""
# A CN that is NOT among its own SANs. Once a SAN exists the CN must be ignored.
mkleaf cnliar   "fog"             "DNS:other.example.org"
mkleaf wildcard "*.example.org"   "DNS:*.example.org"

reset_env() {
    etcconf=""; PKI_web_vhost_cert=""; sslfullchain=""
    NET_hostname=""; NET_fog_server_ip=""; PKI_san_ip_addresses=""; PKI_san_dns_names=""
    netboothost=""; BOOT_url_proto=""; BOOT_url_proto_forced=""; sBOOT_url_proto=""
    PKI_web_cert_publicly_trusted="no"; BOOT_rebuild_ipxe_with_my_ca="no"
    WEB_root="/fog/"
    tftpdirdst="$WORK/tftp"
    error_log="$WORK/error.log"
    # Without this the fatal paths call `exit 1` and take the test script with
    # them. functions.sh honours it on every one of them.
    exitFail=1
}

echo "== _certServesName: does the certificate carry this name? =="

out=$(_certServesName "$WORK/public.pem" "fog.example.org"); status=$?
check "$out" "exact" "A: the FQDN in the SAN list matches"
rc $status 0 "A2: ...and returns success"

out=$(_certServesName "$WORK/public.pem" "fog"); status=$?
rc $status 1 "B: the SHORT name does NOT match a public leaf -- the reported bug"

out=$(_certServesName "$WORK/fogpki.pem" "fog"); status=$?
check "$out" "exact" "C: the short name DOES match a FOG-issued leaf (it is a SAN)"

out=$(_certServesName "$WORK/cnonly.pem" "fog.example.org"); status=$?
check "$out" "exact" "D: with no SAN at all, the commonName is honoured (ADR 0016)"

out=$(_certServesName "$WORK/cnliar.pem" "fog"); status=$?
rc $status 1 "E: once ANY SAN exists the commonName is ignored -- do not 'simplify' this"

out=$(_certServesName "$WORK/wildcard.pem" "fog.example.org"); status=$?
check "$out" "wildcard" "F: a wildcard SAN matches one label, and says so"

out=$(_certServesName "$WORK/wildcard.pem" "deep.sub.example.org"); status=$?
rc $status 1 "G: a wildcard does NOT match across a dot"

out=$(_certServesName "$WORK/nope.pem" "fog.example.org"); status=$?
rc $status 1 "H: an unreadable certificate matches nothing"

echo "== _resolveNetbootHost: one name for the whole boot =="

# I. The reported case: a public leaf for the FQDN, and ${NET_hostname} holding the
#    short name. The certificate wins.
reset_env
PKI_web_vhost_cert="$WORK/public.pem"
NET_hostname="fog"; NET_fog_server_ip="10.0.0.1"
_resolveNetbootHost >/dev/null 2>&1; status=$?
rc $status 0 "I: resolves against a public leaf"
check "$netboothost" "fog.example.org" "I2: the name is the CERTIFICATE's, not \${NET_hostname}"

# J. No certificate to read -- a storage node's configureMinHttpd path, or
#    --no-vhost. Fall back to the name, never to the address.
reset_env
NET_hostname="fog.example.org"; NET_fog_server_ip="10.0.0.1"
_resolveNetbootHost >/dev/null 2>&1; status=$?
rc $status 0 "J: no readable certificate is not fatal on its own"
check "$netboothost" "fog.example.org" "J2: ...it falls back to \${NET_hostname}"

# K. No certificate AND no name: the address is all there is, and an https URL
#    built from it cannot work whatever the certificate says.
reset_env
NET_fog_server_ip="10.0.0.5"
_resolveNetbootHost >/dev/null 2>&1; status=$?
rc $status 1 "K: an address-only server is fatal under HTTPS netboot"

# L. A certificate that does not serve the name resolved from it.
reset_env
PKI_web_vhost_cert="$WORK/cnliar.pem"
NET_hostname="fog"; NET_fog_server_ip="10.0.0.1"
_resolveNetbootHost >/dev/null 2>&1; status=$?
rc $status 1 "L: a name the certificate does not carry is fatal"

# M. Idempotent: the recorder calls this after configureDefaultiPXEfile already
#    has, and must get the same answer without re-announcing it.
reset_env
PKI_web_vhost_cert="$WORK/public.pem"
NET_hostname="fog"; NET_fog_server_ip="10.0.0.1"
_resolveNetbootHost >/dev/null 2>&1
second=$(_resolveNetbootHost 2>&1)
check "$netboothost" "fog.example.org" "M: a second call keeps the same name"
check "$second" "" "M2: ...and says nothing the second time"

echo "== configureDefaultiPXEfile: what actually reaches default.ipxe =="

# N. End to end. This is the artifact that broke; assert the file, not just the
#    resolver.
reset_env
mkdir -p "$tftpdirdst"
PKI_web_vhost_cert="$WORK/public.pem"
NET_hostname="fog"; NET_fog_server_ip="10.0.0.1"
sBOOT_url_proto="https"
configureDefaultiPXEfile >/dev/null 2>&1
written=$(cat "$tftpdirdst/default.ipxe" 2>/dev/null)
has   "$written" "https://fog.example.org/fog/service/ipxe/boot.php" \
    "N: default.ipxe chains to the certificate's name"
hasnt "$written" "https://fog/" \
    "N2: ...and not to the short hostname"

# O. HTTP netboot is deliberately untouched. It never cared about names, and
#    changing it would rewrite the boot URL of every ordinary install.
reset_env
mkdir -p "$tftpdirdst"
rm -f "$tftpdirdst/default.ipxe"
PKI_web_vhost_cert="$WORK/public.pem"
NET_hostname="fog"; NET_fog_server_ip="10.0.0.1"
sBOOT_url_proto="http"
configureDefaultiPXEfile >/dev/null 2>&1
written=$(cat "$tftpdirdst/default.ipxe" 2>/dev/null)
has "$written" "http://10.0.0.1/fog/service/ipxe/boot.php" \
    "O: HTTP netboot still uses the address"

# O2. The Secure Boot ledger's whole supply of data is these two lines. Nothing
#     else asks a machine what its firmware state is, so if they are dropped --
#     by a rebase, or by someone tidying the echo -- every host silently reads
#     "never reported" for ever, the enrollment task's target check goes back to
#     allowing everything, and there is no error anywhere to say so.
#
#     Asserted against the WRITTEN FILE and against the exact iPXE spelling,
#     not against the source line. A grep of functions.sh for "secureboot"
#     would pass on a commented-out line, and ${efi/SecureBoot} is
#     case-sensitive to iPXE -- ${efi/secureboot} resolves to nothing at all
#     and would fail open into "never reported" with the param still present.
has "$written" 'param secureboot ${efi/SecureBoot}' \
    "O2: default.ipxe reports the Secure Boot variable"
has "$written" 'param setupmode ${efi/SetupMode}' \
    "O2b: ...and Setup Mode, which is what says whether enrollment is unattended"

# O3. Order matters for one reason: the mac1..mac7 chain below short-circuits
#     to :bootme on the first absent interface, so anything emitted after it is
#     unreachable on a single-NIC machine -- which is most machines. These two
#     must sit above it, beside sysuuid.
sb_line=$(printf '%s\n' "$written" | grep -n 'param secureboot' | cut -d: -f1)
bootme_line=$(printf '%s\n' "$written" | grep -n 'goto bootme' | head -1 | cut -d: -f1)
if [[ -n $sb_line && -n $bootme_line && $sb_line -lt $bootme_line ]]; then
    ok "O3: the report is emitted above the short-circuiting NIC chain"
else
    bad "O3: secureboot param is at line ${sb_line:-none}, first 'goto bootme' at ${bootme_line:-none} -- a single-NIC machine would never send it"
fi

# P. The fatal case must not leave a file behind for TFTP to serve. An install
#    that aborts having already written an unbootable default.ipxe is the worst
#    of both outcomes.
reset_env
mkdir -p "$tftpdirdst"
rm -f "$tftpdirdst/default.ipxe"
NET_fog_server_ip="10.0.0.5"
sBOOT_url_proto="https"
configureDefaultiPXEfile >/dev/null 2>&1
if [[ ! -f $tftpdirdst/default.ipxe ]]; then
    ok "P: an aborted resolution writes no default.ipxe"
else
    bad "P: default.ipxe was written despite an unusable host ($(cat "$tftpdirdst/default.ipxe"))"
fi

echo "== the recorder keeps FOG_WEB_HOST in step =="

# Q. Hops 2..n come from the FOG_WEB_HOST DB row, which the installer never used
#    to write -- it is seeded from ${NET_fog_server_ip} on a fresh schema deploy and then
#    left alone, so a fresh public-cert install pointed them at https://<IP>/.
#    Source-level, because asserting the row needs a database.
if grep -q "recordNetbootWebHost" "$REPO/bin/installfog.sh"; then
    ok "Q: installfog.sh records FOG_WEB_HOST"
else
    bad "Q: nothing keeps FOG_WEB_HOST in step with the netboot name"
fi

# R. Gated on HTTPS netboot. Rewriting FOG_WEB_HOST on every plain-HTTP install
#    would stomp a name plenty of admins set deliberately.
if awk '/^recordNetbootWebHost\(\)/,/^}/' "$FUNCS" | grep -q 'BOOT_url_proto} == https'; then
    ok "R: the rewrite only happens under HTTPS netboot"
else
    bad "R: FOG_WEB_HOST would be rewritten on HTTP installs too"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
