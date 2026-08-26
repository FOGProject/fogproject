#!/bin/bash
#
# Guards the installer's hostname handling.
#
#   tests/hostname-resolution.test.sh
#
# A FOG 1.6 server with no hostname set could not be installed or upgraded: the
# web server was left stopped and the failure said nothing about a hostname.
# The chain was
#
#   no system hostname
#     -> ${NET_hostname} empty (or the kernel's literal default "(none)",
#        which is NOT empty, so every -z test in the tree waved it through)
#     -> createSecureBootIntermediateCA() writes `subjectAltName = DNS:`
#     -> openssl x509 -req refuses it outright
#        ("X509V3_parse_list:invalid null value")
#     -> errorStat exits the installer -- from inside configureHttpd(), which
#        has already stopped the web server and has not yet reached the
#        createSSLCA() call that restarts it.
#
# Four properties are pinned here, and the third is the one most likely to be
# "tidied" back to how it was, because the broken form does not fail loudly:
#
#   1. _usableHostname() rejects everything that cannot serve as a certificate
#      name -- empty, "(none)", localhost, an IP literal, bad grammar.
#   2. _certLeafName() NEVER returns empty, and never returns an address: its
#      callers all need a DNS name specifically.
#   3. The generated ca.cnf/req.cnf carry no empty `DNS.1 = `. OpenSSL ACCEPTS
#      that one and emits a zero-length dNSName, so the certificate signs
#      cleanly and then fails every `openssl verify` against the DNS
#      nameConstraints both intermediates carry -- silently, at the clients.
#   4. The Secure Boot sign.cnf carries no bare `DNS:`, and the resulting
#      extension section actually parses. That is the reported crash.
#
# Assertions are on the GENERATED CONFIG TEXT, plus one real openssl signing
# round-trip for the case that used to abort. No install, no network, no root.
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
hasnt() { [[ $1 != *"$2"* ]] && ok "$3" || bad "$3 (unexpectedly found '$2')"; }

# The stubs below shadow hostname/hostnamectl on PATH. Applied by assignment and
# undone afterwards rather than inside a ( subshell ): a subshell gets its own
# copy of PASS/FAIL, so every assertion made in one is counted nowhere and a
# failure inside it cannot fail the run.
REALPATH="$PATH"
stubOn()  { PATH="$WORK/bin:$REALPATH"; }
stubOff() { PATH="$REALPATH"; }

error_log="$WORK/error.log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# This machine has a hostname of its own, and _certLeafName()/_detectedHostname()
# ask it directly. Shadow the three tools they use so the no-hostname case is
# reproducible anywhere -- the same PATH-stub approach the FOS harnesses use.
mkdir -p "$WORK/bin"
printf '#!/bin/sh\necho "(none)"\n'  > "$WORK/bin/hostname"
printf '#!/bin/sh\nexit 1\n'         > "$WORK/bin/hostnamectl"
chmod +x "$WORK/bin/hostname" "$WORK/bin/hostnamectl"

echo "hostname resolution:"

# --- 1. what can and cannot be a certificate name ----------------------------

for badname in "" "(none)" "localhost" "localhost.localdomain" "10.0.0.5" "-nope-" "two words"; do
    _usableHostname "$badname" >/dev/null \
        && bad "_usableHostname rejects '${badname:-<empty>}'" \
        || ok  "_usableHostname rejects '${badname:-<empty>}'"
done
for goodname in "fog.example.org" "fogsrv" "fog-01.corp.example.com"; do
    check "$(_usableHostname "$goodname")" "$goodname" "_usableHostname accepts '$goodname'"
done
# A trailing dot is a legal FQDN and an illegal certificate name, so it is
# stripped rather than rejected.
check "$(_usableHostname "fog.example.org.")" "fog.example.org" "_usableHostname strips the root dot"
# First usable wins, so a bad persisted value does not mask a good detected one.
check "$(_usableHostname "(none)" "localhost" "fog.example.org")" "fog.example.org" \
      "_usableHostname takes the first USABLE candidate, not the first"

# --- 2. _certLeafName() always yields a name ---------------------------------

stubOn
NET_hostname=""; NET_fog_server_ip="10.0.0.5"
# fogserver, not the address: an IP literal in a DNS SAN matches no DNS subtree
# in the Web CA's nameConstraints, which is the failure the nameConstraints note
# in createSecureBootIntermediateCA() describes.
check "$(_certLeafName)" "fogserver" "_certLeafName falls back to fogserver, never the address"
NET_hostname="(none)"
check "$(_certLeafName)" "fogserver" "_certLeafName rejects a persisted '(none)'"
NET_hostname="fog.example.org"
check "$(_certLeafName)" "fog.example.org" "_certLeafName prefers a usable NET_hostname"
stubOff

# --- 3. no empty DNS.1 in the SAN config -------------------------------------

# The DNS half of createSSLCA()'s alt_names block, replayed. It is inline in a
# very long function rather than in one of its own, so it is replayed here the
# same way install-settings-resolution.test.sh replays the migration block.
sanDnsBlock() {
    local extraname dnscount=0 dnsSanEntries=""
    while IFS= read -r extraname; do
        [[ -z $extraname ]] && continue
        dnscount=$((dnscount + 1))
        [[ -n $dnsSanEntries ]] && dnsSanEntries="${dnsSanEntries}"$'\n'
        dnsSanEntries="${dnsSanEntries}DNS.${dnscount} = ${extraname}"
    done < <(_defaultServerNames)
    printf '%s\n' "$dnsSanEntries"
}

NET_fog_server_ip="10.0.0.5"; PKI_san_ip_addresses="10.0.0.5"
PKI_san_dns_names=""; PKI_allowed_domain_names=""

NET_hostname=""
block=$(sanDnsBlock)
hasnt "$block" "DNS.1 = "$'\n' "no empty DNS.1 when the hostname is empty"
check "$(printf '%s' "$block" | head -1)" "DNS.1 = fogserver" "DNS.1 falls through to fogserver"

NET_hostname="fog.example.org"
block=$(sanDnsBlock)
check "$(printf '%s' "$block" | head -1)" "DNS.1 = fog.example.org" "DNS.1 is the hostname when there is one"
check "$(printf '%s' "$block" | grep -c 'fog\.example\.org$')" "1" "the hostname is not duplicated into a later DNS.n"

# Every emitted entry has a value. This is the assertion that catches a
# reintroduced `DNS.n = ${SOMETHING_EMPTY}` anywhere in the block.
NET_hostname=""
badentries=$(sanDnsBlock | grep -c '=[[:space:]]*$')
check "$badentries" "0" "no SAN entry is emitted without a value"

# --- 4. the Secure Boot signing config parses --------------------------------

writeSignCnf() {   # $1 = the name to put in the SAN
    cat > "$WORK/sign.cnf" << EOF
[ req ]
distinguished_name = req_dn
prompt             = no

[ req_dn ]
CN = FOG Project Secure Boot Signing
O  = FOG Project
OU = FOG Secure Boot

[ v3_sign ]
basicConstraints = critical,CA:FALSE
extendedKeyUsage = codeSigning
subjectKeyIdentifier = hash
subjectAltName   = DNS:$1
EOF
}

# A throwaway CA, because the failure is at SIGNING time, not at CSR time:
# [v3_sign] is not referenced from [req], so `openssl req` never parses it and
# reports nothing. Only `openssl x509 -req -extensions v3_sign` does.
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=Test SB CA" \
    -keyout "$WORK/ca.key" -out "$WORK/ca.pem" >/dev/null 2>&1

signWith() {   # $1 = the name; echoes openssl's exit status
    writeSignCnf "$1"
    openssl req -new -sha256 -nodes -newkey rsa:2048 -config "$WORK/sign.cnf" \
        -keyout "$WORK/sign.key" -out "$WORK/sign.csr" >/dev/null 2>&1
    openssl x509 -req -in "$WORK/sign.csr" -CA "$WORK/ca.pem" -CAkey "$WORK/ca.key" \
        -CAcreateserial -sha256 -days 5 -extensions v3_sign -extfile "$WORK/sign.cnf" \
        -out "$WORK/sign.pem" >/dev/null 2>&1
    echo $?
}

# The regression itself: prove the empty form really is fatal, so a future
# reader can see that the guard below is load-bearing and not decorative.
check "$(signWith "")" "1" "a bare 'DNS:' is rejected by openssl (the reported crash)"

stubOn
NET_hostname=""
sbSanName=$(_certLeafName)
check "$(signWith "$sbSanName")" "0" "the name _certLeafName gives signs cleanly"
writeSignCnf "$sbSanName"
# The value after DNS: must be non-empty. Testing for the absence of the
# substring "DNS:" cannot express that -- it is present in every correct line
# too -- so match the line shape instead.
sanline=$(grep 'subjectAltName' "$WORK/sign.cnf")
if [[ $sanline =~ DNS:[^[:space:]]+ ]]; then
    ok "sign.cnf never carries a bare DNS:"
else
    bad "sign.cnf never carries a bare DNS: (got '$sanline')"
fi
stubOff

# --- 5. the real source, not just the replay ---------------------------------
#
# Sections 3 and 4 replay blocks that live inline in very long functions, which
# means they prove the SHAPE is right without proving functions.sh still has it.
# These two assertions close that gap: they are what actually fails if somebody
# puts either raw interpolation back.
hasnt "$(grep -c 'DNS\.1 = \${NET_hostname}' "$FUNCS")" "1" \
      "functions.sh does not hardcode DNS.1 to a raw \${NET_hostname}"
hasnt "$(grep 'subjectAltName' "$FUNCS")" 'DNS:${NET_hostname' \
      "functions.sh does not build a subjectAltName from a raw \${NET_hostname}"
# ...and that the guaranteed-non-empty helper is what the three name sites use.
# Counting CALLS, not mentions: the definition and the comments explaining it
# also match a bare name grep, so that count would drift on any edit to either.
check "$(grep -c '\$(_certLeafName)' "$FUNCS")" "3" \
      "all three OpenSSL name sites go through _certLeafName"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
