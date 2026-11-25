#!/bin/bash
#
# Guards the installer's IPv6 support (FOGProject/fogproject#2152, slice D).
#
#   tests/installer-ipv6.test.sh
#
# Sites reach FOG by DNS name (A + AAAA). For that to work, three things hold.
# The server listens on IPv6. Its web certificate carries its IPv6 addresses.
# The Web CA that signs the certificate permits them. DHCPv6 is opt-in.
#
# What is easy to break, and fails far from the cause:
#
#   * The Web CA's name constraints. They are fixed when the CA is issued, and
#     only ULA, loopback and link-local ranges were ever permitted for IPv6. A
#     leaf with the server's global (2001:...) address failed verification
#     against its own CA. An install with a Web CA kept that CA forever.
#     With --reissue-web-ca the installer re-issues the CA, with the same key,
#     when it lacks the server's IPv6 addresses. It does this only for a CA that
#     this server's own root issued. Without the flag, and for any other CA,
#     the leaf gets no IPv6 SANs the CA cannot cover.
#   * "No IPv6 on the box -> no change at all". Every addition below is gated
#     on an IPv6 address being present, and this suite pins the IPv4 side.
#   * The bits that are emitted inside big functions (the nginx listen lines,
#     the vsftpd config) are extracted from functions.sh and evaluated, so the
#     test reads the real lines and cannot drift from them.
#
# Needs openssl. No network, no root, no install. The kea-dhcp6 -t check
# needs the daemon, so it is run by hand and not here.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INSTALLER="$REPO/bin/installfog.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
[[ -f $INSTALLER ]] || { echo "ERROR: $INSTALLER not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl is not installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()    { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }
has()   { [[ $1 == *"$2"* ]] && ok "$3" || bad "$3 (not found: '$2')"; }
hasnt() { [[ $1 != *"$2"* ]] && ok "$3" || bad "$3 (unexpectedly present: '$2')"; }

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# errorStat exits the whole script on a non-zero status, which in a test run
# would look like a pass rather than a failure.
dots() { :; }
errorStat() { :; }
setSELinuxContext() { :; }
diffconfig() { :; }
exitFail=1

# A canned `ip -6 -o addr show dev eth0 scope global`. One line per address, in
# the shape the real command prints. Covers every case _globalAddresses6 has a
# rule for: a stable global, a ULA, a second global, and one each of the
# tentative, deprecated and temporary kinds it must skip.
IP6_FIXTURE='2: eth0    inet6 2001:db8:0:1::5/64 scope global dynamic noprefixroute \       valid_lft 86400sec preferred_lft 14400sec
2: eth0    inet6 fd00:f06:0:1::1/64 scope global \       valid_lft forever preferred_lft forever
2: eth0    inet6 2001:db8:0:1::99/64 scope global tentative \       valid_lft forever preferred_lft forever
2: eth0    inet6 2001:db8:0:1::98/64 scope global deprecated dynamic \       valid_lft 10sec preferred_lft 0sec
2: eth0    inet6 2001:db8:0:1::97/64 scope global temporary dynamic \       valid_lft 600sec preferred_lft 300sec
2: eth0    inet6 fe80::1/64 scope link \       valid_lft forever preferred_lft forever'
ip() { printf '%s\n' "$IP6_FIXTURE"; }

echo "== validip6 and _urlHost =="

for v in "fd00::1" "::1" "2001:db8::5" "2001:0db8:0000:0000:0000:0000:0000:0005" "fe80::" "1:2:3:4:5:6:7:8"; do
    is "$(validip6 "$v")" "0" "validip6 accepts $v"
done
for v in "" "10.0.0.5" "fd00:::1" "1::2::3" "12345::1" "1:2:3:4:5:6:7" ":1:2:3:4:5:6:7" "fd00::g" "[fd00::1]" "fog.example.org"; do
    is "$(validip6 "$v")" "1" "validip6 rejects '$v'"
done

is "$(_urlHost fd00::1)" "[fd00::1]" "_urlHost brackets an IPv6 literal"
is "$(_urlHost 10.0.0.1)" "10.0.0.1" "_urlHost leaves an IPv4 address alone"
is "$(_urlHost fog.example.org)" "fog.example.org" "_urlHost leaves a name alone"

echo "== IPv6 detection: _globalAddresses6 and normalizeIpAddress =="

is "$(_globalAddresses6 eth0 | tr '\n' ' ')" "2001:db8:0:1::5 fd00:f06:0:1::1 " \
    "stable global and ULA kept; tentative, deprecated, temporary and fe80 skipped"
is "$(_globalAddresses6 "")" "" "no interface -> nothing"

NET_interface=eth0
NET_fog_server_ip="10.0.0.5"
PKI_san_ip_addresses="10.0.0.5"
normalizeIpAddress
is "$PKI_san_ip_addresses" "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1" "IPv6 addresses ride along in the SAN list"
is "$NET_fog_server_ip" "10.0.0.5" "the primary stays the IPv4 address"
normalizeIpAddress
is "$PKI_san_ip_addresses" "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1" "a second pass adds nothing (idempotent)"

# An IPv6 entry already recorded in .fogsettings stays: an admin can add one by
# hand, or hold it on another NIC. It merges with the detected ones, once each.
PKI_san_ip_addresses="10.0.0.5 2001:db8:dead::1 2001:db8:0:1::5"
normalizeIpAddress
is "$PKI_san_ip_addresses" "10.0.0.5 2001:db8:dead::1 2001:db8:0:1::5 fd00:f06:0:1::1" \
    "a recorded IPv6 entry is kept, and merged with the detected ones without duplicates"

# No IPv6 on the box: no change at all.
ip() { :; }
NET_fog_server_ip="10.0.0.5"
PKI_san_ip_addresses="10.0.0.5 10.0.0.6"
normalizeIpAddress
is "$PKI_san_ip_addresses" "10.0.0.5 10.0.0.6" "no IPv6 on the box leaves the list untouched"
is "$(_serverIp6List)" "" "...and there is no IPv6 list"
ip() { printf '%s\n' "$IP6_FIXTURE"; }

echo "== _nameConstraints: the server's own IPv6 addresses =="

NET_hostname="fogserver.test.local"
PKI_allowed_domain_names="test.local"
PKI_san_dns_names=""
PKI_internal_subnets=""
PKI_san_ip_addresses="10.0.0.5"
v4only=$(_nameConstraints)
hasnt "$v4only" "ffff:ffff" "IPv4-only server: no IPv6 subtree at all"
has "$v4only" "permitted;IP:10.0.0.5/255.255.255.255" "IPv4-only server: own address still /32"

PKI_san_ip_addresses="10.0.0.5 2001:db8:0:1::5 2001:db8:0:1::6 fd00:f06:0:1::1"
withv6=$(_nameConstraints)
has "$withv6" "permitted;IP:2001:db8:0:1::/ffff:ffff:ffff:ffff::" \
    "the server's own global address is permitted as its /64"
has "$withv6" "permitted;IP:fd00:f06:0:1::/ffff:ffff:ffff:ffff::" \
    "the server's own ULA address is permitted as its /64 too"
is "$(grep -o 'IP:2001:db8:0:1::/' <<< "$withv6" | wc -l)" "1" \
    "two addresses in one /64 give one subtree"
hasnt "$withv6" "IP:2001:db8:0:1::5/" "no /128 subtree for the address (an address change would re-issue the CA)"
has "$withv6" "permitted;IP:fc00::/fe00::" "the ULA range is still permitted"

echo "== createSSLCA: IPv6 addresses become IP.n SAN lines =="

# The real SAN-building lines, out of createSSLCA, evaluated here.
san_block=$(sed -n '/^    sanentries=""$/,/^    done < <(_defaultServerNames)$/p' "$FUNCS")
[[ -n $san_block ]] || { echo "ERROR: could not extract the SAN block from createSSLCA" >&2; exit 1; }
NET_fog_server_ip="10.0.0.5"
PKI_san_ip_addresses="10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
eval "$san_block"
has "$sanentries" "IP.1 = 10.0.0.5" "SAN: the IPv4 address is IP.1"
has "$sanentries" "IP.2 = 2001:db8:0:1::5" "SAN: the global address is a valid IP.2 line"
has "$sanentries" "IP.3 = fd00:f06:0:1::1" "SAN: the ULA address is a valid IP.3 line"
# The proof that matters: OpenSSL accepts those lines and puts the address in.
cat > "$WORK/san.cnf" <<EOF
[req]
distinguished_name = dn
prompt = no
[dn]
CN = test
[v3]
subjectAltName = @alt_names
[alt_names]
$sanentries
$dnsSanEntries
EOF
openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -days 1 \
    -config "$WORK/san.cnf" -extensions v3 -keyout "$WORK/san.key" -out "$WORK/san.pem" >/dev/null 2>&1
santext=$(openssl x509 -in "$WORK/san.pem" -noout -text 2>/dev/null)
has "$santext" "IP Address:2001:DB8:0:1:0:0:0:5" "OpenSSL carries the global address as an iPAddress SAN"

# DNS names: the netboot / FOG_WEB_HOST name is not added here, it is READ from
# the certificate (_resolveNetbootHost), so it is covered by whatever
# _defaultServerNames lists. Pin that the host name is a DNS SAN.
has "$dnsSanEntries" "= fogserver.test.local" "SAN: the host name is a DNS entry (what netboot resolves against)"

echo "== _ipv6Canonical =="
is "$(_ipv6Canonical 2001:db8::5)" "2001:DB8:0:0:0:0:0:5" "compressed address expands the way OpenSSL prints it"
is "$(_ipv6Canonical ::1)" "0:0:0:0:0:0:0:1" "::1"
is "$(_ipv6Canonical fd00:f06:0:1::)" "FD00:F06:0:1:0:0:0:0" "trailing ::"
is "$(_ipv6Canonical 1:2:3:4:5:6:7:8)" "1:2:3:4:5:6:7:8" "an address with no :: is unchanged"

echo "== Web CA: re-issued when it lacks the IPv6 addresses =="

fogprogramdir="$WORK/opt/fog"
PKI_root_dir="$fogprogramdir/pki"
snapindir="$WORK/opt/fog/snapins"
PKI_client_cert_dir="$snapindir/ssl"
webdirdest="$WORK/var/www/fog"
DB_backup_path="$WORK/backups"
version="1.6.0-test"
apacheuser="$(id -un)"
PKI_sb_enabled=1
recreateKeys=no
recreateCA=no
externalca=no
# installfog.sh sets this once per run. The archived CA certificate uses it.
timestamp="1700000000"
mkdir -p "${PKI_client_cert_dir}/CA" "$webdirdest" "${DB_backup_path}"

# One pass of the Web zone, in createSSLCA()'s own order: names, CA, leaf.
web_pass() {
    NET_fog_server_ip="10.0.0.5"
    PKI_san_ip_addresses="$1"
    certip="${NET_fog_server_ip}"
    _resolveSslPath
    _resolveRootCA
    _relocatePkiConf
    eval "$san_block"
    cat > "$(_pkiConfDir)/ca.cnf" <<EOF
[v3_ca]
subjectAltName = @alt_names
[alt_names]
$sanentries
$dnsSanEntries
EOF
    cat > "$(_pkiConfDir)/req.cnf" <<EOF
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no
[req_distinguished_name]
CN = $certip
O = FOG Project
OU = FOG Client Communication
[v3_req]
subjectAltName = @alt_names
[alt_names]
$sanentries
$dnsSanEntries
EOF
    # Not in $( ): the function sets PKI_web_ca_* for the steps below.
    createWebIntermediateCA > "$WORK/ca-pass.out" 2>&1
    CA_PASS_OUT=$(cat "$WORK/ca-pass.out")
    # createSSLCA runs this once the signing CA is known, before the leaf.
    WEB_PASS_OUT=$(_dropUncoveredIp6Sans 2>&1)
    _resolveWebLeafPaths
    _createWebLeaf >/dev/null 2>&1
}
fp() { openssl x509 -in "$1" -noout -fingerprint -sha256 2>/dev/null; }

webca="$fogprogramdir/pki/web/ca/.fogWebCA.pem"
webleaf="$fogprogramdir/pki/web/leaf/.webLeaf.pem"
rootca="$fogprogramdir/pki/root/ca/.fogCA.pem"

web_pass "10.0.0.5"
ca_v4=$(fp "$webca")
leaf_v4=$(fp "$webleaf")
[[ -n $ca_v4 && -n $leaf_v4 ]] || {
    echo "ERROR: the IPv4-only pass produced no Web CA or leaf" >&2
    tail -n 15 "$error_log" >&2
    exit 1
}

# No IPv6 on the box: a second pass changes nothing.
web_pass "10.0.0.5"
is "$(fp "$webca")" "$ca_v4" "no IPv6: the Web CA is left as it was"
is "$(fp "$webleaf")" "$leaf_v4" "no IPv6: the leaf is left as it was"

# The box gains a global and a ULA address. The CA lacks both. Without the
# flag the CA is left alone, and the leaf leaves the new addresses off.
webkey="$fogprogramdir/pki/web/ca/.fogWebCA.key"
pubof() { openssl pkey -in "$1" -pubout 2>/dev/null | openssl sha256 2>/dev/null; }
key_v4=$(pubof "$webkey")
reissueWebCA=0
web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
is "$(fp "$webca")" "$ca_v4" "no --reissue-web-ca: the Web CA is left as it was"
is "$(ls -A "$fogprogramdir/pki/web/ca/" | grep -c '^\.fogWebCA\.pem\.[0-9]')" "0" "no --reissue-web-ca: no Web CA archive appears"
leaftext=$(openssl x509 -in "$webleaf" -noout -text 2>/dev/null)
hasnt "$leaftext" "IP Address:2001" "no --reissue-web-ca: the leaf leaves out the uncovered IPv6 SAN"
has "$leaftext" "IP Address:10.0.0.5" "no --reissue-web-ca: the IPv4 SAN stays"
has "$WEB_PASS_OUT" "--reissue-web-ca" "no --reissue-web-ca: the output names the flag"
has "$WEB_PASS_OUT" "Boot by DNS name works" "no --reissue-web-ca: the output says boot by name works"
hasnt "$CA_PASS_OUT" "Re-issuing" "no --reissue-web-ca: no re-issue message"
if openssl verify -trusted "$rootca" -untrusted "$webca" "$webleaf" >/dev/null 2>&1; then
    ok "no --reissue-web-ca: the leaf verifies"
else
    bad "no --reissue-web-ca: the leaf does not verify"
fi
# A second pass with the same addresses changes nothing. The leaf stays the
# same, so the iPXE build stamp stays the same and no rebuild starts.
ipxetrust="$webca"
ipxeVer="test"
leaf_default=$(fp "$webleaf")
stamp_default=$(_ipxeBuildStampValue)
web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
is "$(fp "$webleaf")" "$leaf_default" "no --reissue-web-ca: a second pass leaves the leaf as it was"
is "$(_ipxeBuildStampValue)" "$stamp_default" "no --reissue-web-ca: a second pass leaves the iPXE build stamp as it was"
# A root that cannot issue a CA: the flag would do nothing, so the output
# must not suggest it. The subshell keeps the setting out of later passes.
noissuer_out=$(
    # _resolveRootCA runs this check on every pass, so stub its verdict.
    _rootCACanIssue() { rootCAIssuer=0; }
    web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
    echo "$WEB_PASS_OUT"
)
has "$noissuer_out" "left off the web certificate" "root that cannot issue: the uncovered address is still left off"
hasnt "$noissuer_out" "--reissue-web-ca" "root that cannot issue: the output does not suggest the flag"
# The flag re-issues the CA.
reissueWebCA=1
web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
ca_v6=$(fp "$webca")
is "$(pubof "$webkey")" "$key_v4" "the re-issue keeps the Web CA key"
is "$(ls -A "$fogprogramdir/pki/web/ca/" | grep -c '^\.fogWebCA\.key\.')" "0" "...and no key was set aside"
leaf_v6=$(fp "$webleaf")
[[ $ca_v6 != "$ca_v4" ]] && ok "the Web CA was re-issued to cover the IPv6 addresses" \
    || bad "the Web CA was NOT re-issued"
[[ $leaf_v6 != "$leaf_v4" ]] && ok "the leaf was re-issued under the new CA" \
    || bad "the leaf was NOT re-issued"
archived=$(ls -A "$fogprogramdir/pki/web/ca/" 2>/dev/null | grep -c '^\.fogWebCA\.pem\.[0-9]')
is "$archived" "1" "the old Web CA certificate is kept beside the new one"
[[ -f "$webca.${timestamp}" ]] && ok "the archive uses the run's timestamp" \
    || bad "the archive does not use the run's timestamp"
has "$CA_PASS_OUT" "Re-issuing it with the same key." "re-issue message: says the key is kept"
has "$CA_PASS_OUT" "Rebuild any USB or ESP boot media" "re-issue message: names the boot media to rebuild"
has "$CA_PASS_OUT" "storage node that builds its own iPXE" "re-issue message: names the storage nodes"
cat "$webca" "$rootca" > "$WORK/chain.pem"
leaftext=$(openssl x509 -in "$webleaf" -noout -text 2>/dev/null)
has "$leaftext" "IP Address:2001:DB8:0:1:0:0:0:5" "the leaf carries the global address"
if openssl verify -trusted "$rootca" -untrusted "$webca" "$webleaf" >/dev/null 2>&1; then
    ok "the leaf with a GLOBAL IPv6 SAN verifies against its own Web CA and the root"
else
    bad "the leaf with a global IPv6 SAN does NOT verify (permitted subtree violation?)"
fi
is "$(openssl verify -trusted "$rootca" "$webca" 2>&1 | tail -1)" "$webca: OK" \
    "the new Web CA still chains to the SAME root (the root was not touched)"
is "$(openssl x509 -in "$webca" -noout -pubkey | openssl sha256)" "$key_v4" \
    "the new Web CA certificate carries the same public key"

# Third pass: the CA already covers the addresses, so nothing moves.
web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
is "$(fp "$webca")" "$ca_v6" "a CA that already covers IPv6 is left alone"
is "$(fp "$webleaf")" "$leaf_v6" "...and so is the leaf"

# The address changes inside the same /64 (SLAAC, privacy): no re-issue.
web_pass "10.0.0.5 2001:db8:0:1::99 fd00:f06:0:1::1"
is "$(fp "$webca")" "$ca_v6" "a new address in the same /64 does not re-issue the CA"
web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"

# An address in a new /64 is a new gap: re-issued once more.
web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1 2001:db8:0:2::7"
[[ $(fp "$webca") != "$ca_v6" ]] && ok "an address in a new /64 triggers one more re-issue" \
    || bad "an address in a new /64 did not trigger a re-issue"

echo "== Web CA that cannot be re-issued: the leaf leaves out what it cannot cover =="

# The root key goes offline, then the box gains an address in a new /64. The
# CA lacks it and cannot be re-issued. The leaf must drop that SAN, or it fails
# verification for IPv4 clients as well. The addresses the CA covers stay.
ca_before=$(fp "$webca")
rootkey="$fogprogramdir/pki/root/ca/.fogCA.key"
mv "$rootkey" "$WORK/rootkey.away"
# Without the flag the output must not name it: the CA cannot be re-issued.
reissueWebCA=0
web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1 2001:db8:0:2::7 2001:db8:0:3::9"
hasnt "$WEB_PASS_OUT" "--reissue-web-ca" "offline root, no flag: the output does not name the flag"
# With the flag the offline message shows.
reissueWebCA=1
web_pass "10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1 2001:db8:0:2::7 2001:db8:0:3::9"
mv "$WORK/rootkey.away" "$rootkey"
# Nothing in one run clears this, and it would hide the hint in later passes.
rootCAKeyOffline=0
has "$CA_PASS_OUT" "Root CA private key is not on this server" "offline root with the flag: the offline message shows"
is "$(fp "$webca")" "$ca_before" "offline root: the Web CA is left as it was"
leaftext=$(openssl x509 -in "$webleaf" -noout -text 2>/dev/null)
hasnt "$leaftext" "IP Address:2001:DB8:0:3:0:0:0:9" "offline root: the uncovered address is left off"
has "$leaftext" "IP Address:2001:DB8:0:1:0:0:0:5" "offline root: a covered global address stays"
has "$leaftext" "IP Address:FD00:F06:0:1:0:0:0:1" "offline root: a covered ULA address stays"
has "$leaftext" "IP Address:10.0.0.5" "offline root: the IPv4 SAN stays"
has "$WEB_PASS_OUT" "left off the web certificate: 2001:db8:0:3::9" "offline root: one message names the address"
if openssl verify -trusted "$rootca" -untrusted "$webca" "$webleaf" >/dev/null 2>&1; then
    ok "offline root: the leaf verifies"
else
    bad "offline root: the leaf does not verify"
fi

echo "== Web CA: a failed re-issue keeps the old certificate =="

# The issue step fails. The old certificate must go back in place. Otherwise
# the next run takes the create path and mints a new CA key.
ca_before=$(fp "$webca")
(
    # As openssl x509 does on a failed sign: an empty output file, exit 1.
    _issueIntermediateCA() { : > "$2/$4"; return 1; }
    NET_fog_server_ip="10.0.0.5"
    PKI_san_ip_addresses="10.0.0.5 2001:db8:0:4::1"
    timestamp="1700000099"
    # The offline-root pass above set this, and nothing in one run clears it.
    rootCAKeyOffline=0
    reissueWebCA=1
    createWebIntermediateCA > "$WORK/reissue.out" 2>&1
)
has "$(cat "$WORK/reissue.out")" "Re-issuing it with the same key." "failed re-issue: the re-issue branch ran"
is "$(fp "$webca")" "$ca_before" "failed re-issue: the old Web CA certificate is back in place"

echo "== Web CA: an admin-supplied CA is never re-issued =="

# A CA issued by ANOTHER root, parked at the canonical Web CA paths -- the shape
# --web-ca-cert/--web-ca-key/--web-ca-root leave behind. It has no IPv6
# constraints, so it would be re-issued if the guard were missing.
cat > "$WORK/ext.cnf" <<EOF
[req]
distinguished_name = dn
prompt = no
[dn]
CN = Other Root
[v3_root]
basicConstraints = critical,CA:TRUE
keyUsage = critical,keyCertSign,cRLSign
[v3_int]
basicConstraints = critical,CA:TRUE,pathlen:0
keyUsage = critical,keyCertSign,cRLSign
nameConstraints = critical,permitted;DNS:test.local,permitted;DNS:fogserver,permitted;DNS:fog-server,permitted;IP:10.0.0.5/255.255.255.255
EOF
openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -days 30 \
    -config "$WORK/ext.cnf" -extensions v3_root -keyout "$WORK/ext-root.key" -out "$WORK/ext-root.pem" >/dev/null 2>&1
openssl req -new -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes \
    -config "$WORK/ext.cnf" -subj "/CN=Other Web CA" -keyout "$WORK/ext-int.key" -out "$WORK/ext-int.csr" >/dev/null 2>&1
openssl x509 -req -in "$WORK/ext-int.csr" -CA "$WORK/ext-root.pem" -CAkey "$WORK/ext-root.key" \
    -CAcreateserial -days 30 -extfile "$WORK/ext.cnf" -extensions v3_int -out "$WORK/ext-int.pem" >/dev/null 2>&1
cp "$WORK/ext-int.pem" "$webca"
cp "$WORK/ext-int.key" "$fogprogramdir/pki/web/ca/.fogWebCA.key"
ext_fp=$(fp "$webca")
archived_before=$(ls -A "$fogprogramdir/pki/web/ca/" | grep -c '^\.fogWebCA\.pem\.[0-9]')
web_pass "10.0.0.5 2001:db8:0:1::5"
is "$(fp "$webca")" "$ext_fp" "a Web CA another root issued is left exactly as it was"
is "$(ls -A "$fogprogramdir/pki/web/ca/" | grep -c '^\.fogWebCA\.pem\.[0-9]')" "$archived_before" \
    "...and nothing was archived for it"
leaftext=$(openssl x509 -in "$webleaf" -noout -text 2>/dev/null)
hasnt "$leaftext" "IP Address:2001" "external CA: the leaf carries no IPv6 SAN"
has "$WEB_PASS_OUT" "IPv6 addresses were left off the web certificate" "external CA: one message says why"
has "$CA_PASS_OUT" "It is admin-supplied, or it does not verify. The installer did not re-issue it." "external CA with --reissue-web-ca: the output says it was not re-issued"
# Without the flag, the output must not suggest the flag. The flag does nothing for this CA.
reissueWebCA=0
web_pass "10.0.0.5 2001:db8:0:1::5"
reissueWebCA=1
is "$(fp "$webca")" "$ext_fp" "external CA without the flag: left exactly as it was"
has "$WEB_PASS_OUT" "IPv6 addresses were left off the web certificate" "external CA without the flag: one message says why"
hasnt "$WEB_PASS_OUT" "--reissue-web-ca" "external CA: the output does not suggest the flag"
if openssl verify -trusted "$WORK/ext-root.pem" -untrusted "$webca" "$webleaf" >/dev/null 2>&1; then
    ok "external CA: the leaf verifies against the admin's chain"
else
    bad "external CA: the leaf does not verify against the admin's chain"
fi

echo "== Web CA: a fresh install with IPv6 needs no flag =="

# No CA exists yet, so the installer creates one. It covers IPv6 from the start.
(
    reissueWebCA=0
    fogprogramdir="$WORK/fresh/opt/fog"
    PKI_root_dir="$fogprogramdir/pki"
    snapindir="$fogprogramdir/snapins"
    PKI_client_cert_dir="$snapindir/ssl"
    unset PKI_root_ca_cert PKI_root_ca_key PKI_web_ca_cert PKI_web_ca_key PKI_web_trust_chain PKI_web_vhost_cert PKI_web_vhost_key
    mkdir -p "${PKI_client_cert_dir}/CA"
    # The offline-root pass above set this, and nothing in one run clears it.
    rootCAKeyOffline=0
    web_pass "10.0.0.5 2001:db8:0:1::5"
) >/dev/null 2>&1
freshleaf="$WORK/fresh/opt/fog/pki/web/leaf/.webLeaf.pem"
freshca="$WORK/fresh/opt/fog/pki/web/ca/.fogWebCA.pem"
freshroot="$WORK/fresh/opt/fog/snapins/ssl/CA/.fogCA.pem"
[[ -s $freshca && -s $freshleaf ]] || bad "fresh install: no Web CA or leaf was created"
has "$(openssl x509 -in "$freshleaf" -noout -text 2>/dev/null)" "IP Address:2001:DB8:0:1:0:0:0:5" "fresh install: the leaf carries the IPv6 SAN without the flag"
_caCoversIp6 "$freshca" 2001:db8:0:1::5 && ok "fresh install: the Web CA permits the IPv6 address" \
    || bad "fresh install: the Web CA does not permit the IPv6 address"
if openssl verify -trusted "$freshroot" -untrusted "$freshca" "$freshleaf" >/dev/null 2>&1; then
    ok "fresh install: the leaf verifies"
else
    bad "fresh install: the leaf does not verify"
fi
reissueWebCA=0

echo "== nginx: IPv6 listeners =="

etcconf="$WORK/nginx.conf"
nginxhttp2listen=" http2"
NET_fog_server_ip="10.0.0.5"

PKI_san_ip_addresses="10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
: > "$etcconf"
eval "$(grep -E 'echo "    listen 80;"|listen \[::\]:80;' "$FUNCS")"
out=$(cat "$etcconf")
has "$out" "listen 80;" "port 80 still listens on IPv4"
has "$out" "listen [::]:80;" "port 80 also listens on IPv6"

PKI_san_ip_addresses="10.0.0.5"
: > "$etcconf"
eval "$(grep -E 'echo "    listen 80;"|listen \[::\]:80;' "$FUNCS")"
out=$(cat "$etcconf")
has "$out" "listen 80;" "no IPv6: port 80 listener is unchanged"
hasnt "$out" "[::]" "no IPv6: no IPv6 listener on port 80"

for n in 1 2; do
    block=$(grep -E 'NET_fog_server_ip[}]:443 ssl|listen \[::\]:443 ssl' "$FUNCS" | sed -n "$((2 * n - 1)),$((2 * n))p")
    [[ -n $block ]] || { bad "443 listener block $n could not be extracted"; continue; }
    PKI_san_ip_addresses="10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
    : > "$etcconf"
    eval "$block"
    out=$(cat "$etcconf")
    has "$out" "listen 10.0.0.5:443 ssl http2;" "443 block $n: the IPv4 listener is unchanged"
    has "$out" "listen [::]:443 ssl http2;" "443 block $n: one IPv6 wildcard listener, same options"
    hasnt "$out" "listen [2001" "443 block $n: no per-address listener (a renumbered address must not stop nginx)"
    PKI_san_ip_addresses="10.0.0.5"
    : > "$etcconf"
    eval "$block"
    is "$(cat "$etcconf")" "    listen 10.0.0.5:443 ssl http2;" "443 block $n, no IPv6: exactly the one IPv4 line"
done

# Apache needs no change. Its vhosts are *:80 and *:443, which cover both
# families. The one Listen directive FOG writes names no address.

echo "== vsftpd: dual stack only with IPv6 and bindv6only=0 =="

ftppasvmin=40000
ftppasvmax=40100
seccompsand=""
ftp_block=$(sed -n '/^    ftplisten="listen=YES"$/,/> "\$ftpconfig"$/p' "$FUNCS")
[[ -n $ftp_block ]] || { echo "ERROR: could not extract the vsftpd config block" >&2; exit 1; }
ftpconfig="$WORK/vsftpd.conf"
BINDV6ONLY=0
sysctl() { echo "$BINDV6ONLY"; }
PKI_san_ip_addresses="10.0.0.5 2001:db8:0:1::5"
eval "$ftp_block"
out=$(cat "$ftpconfig")
has "$out" "listen=NO" "IPv6 and bindv6only=0: the IPv4-only listen is switched off"
has "$out" "listen_ipv6=YES" "IPv6 and bindv6only=0: vsftpd listens dual stack"
is "$ftpv6note" "" "IPv6 and bindv6only=0: nothing to explain"
BINDV6ONLY=1
eval "$ftp_block"
out=$(cat "$ftpconfig")
has "$out" "listen=YES" "IPv6 and bindv6only=1: listen=YES stays"
hasnt "$out" "listen_ipv6" "IPv6 and bindv6only=1: no IPv6 socket that would refuse IPv4 clients"
has "$ftpv6note" "net.ipv6.bindv6only" "IPv6 and bindv6only=1: one line says why"
BINDV6ONLY=0
PKI_san_ip_addresses="10.0.0.5"
eval "$ftp_block"
out=$(cat "$ftpconfig")
has "$out" "listen=YES" "no IPv6: listen=YES as before"
hasnt "$out" "listen_ipv6" "no IPv6: no listen_ipv6 line"
is "$ftpv6note" "" "no IPv6: nothing to explain"
unset -f sysctl

echo "== default.ipxe: an IPv6 literal is bracketed =="

tftpdirdst="$WORK/tftp"
mkdir -p "$tftpdirdst"
WEB_root="/fog/"
BOOT_url_proto="http"
sBOOT_url_proto="http"
netboothost=""
NET_hostname=""
PKI_san_ip_addresses=""
NET_fog_server_ip="fd00:f06:0:1::1"
configureDefaultiPXEfile >/dev/null 2>&1
has "$(cat "$tftpdirdst/default.ipxe" 2>/dev/null)" "chain http://[fd00:f06:0:1::1]/fog/service/ipxe/boot.php" \
    "an IPv6 literal host is bracketed in the chain line"
NET_fog_server_ip="10.0.0.1"
configureDefaultiPXEfile >/dev/null 2>&1
has "$(cat "$tftpdirdst/default.ipxe" 2>/dev/null)" "chain http://10.0.0.1/fog/service/ipxe/boot.php" \
    "an IPv4 host is not bracketed"

echo "== Kea DHCPv6 (--dhcp6-range) =="

NET_interface=eth0
NET_hostname="fogserver.test.local"
PKI_san_ip_addresses="10.0.0.5 2001:db8:0:1::5 fd00:f06:0:1::1"
DHCP6_range_start="fd00:f06:0:1::100"
DHCP6_range_end="fd00:f06:0:1::1ff"
tftpdirsrc="$WORK/tftpsrc"
mkdir -p "$tftpdirsrc"
_writeKea6Config "$WORK/k6.conf" "fd00:f06:0:1::1"
k6=$(cat "$WORK/k6.conf")
has "$k6" '"interfaces": [ "eth0" ]' "DHCPv6 listens on the deployment interface"
has "$k6" '"subnet": "fd00:f06:0:1::/64"' "the subnet is the /64 of the server's address"
has "$k6" '"pool": "fd00:f06:0:1::100-fd00:f06:0:1::1ff"' "the pool is the one the admin gave"
has "$k6" '"name": "ipxe"' "an iPXE class exists"
has "$k6" '"data": "tftp://[fd00:f06:0:1::1]/default.ipxe"' "iPXE is sent to default.ipxe"
has "$k6" "$(_uefiBootFile x64)\" }" "the x64 UEFI class names the boot file the IPv4 config uses"
has "$k6" "tftp://[fd00:f06:0:1::1]/$(_uefiBootFile x64)" "...as a tftp URL on the server's address"
has "$k6" "tftp://[fd00:f06:0:1::1]/$(_uefiBootFile arm64)" "the arm64 class names the arm64 boot file"
has "$k6" "0x0007" "x64 UEFI is matched on option 61 = 0x0007"
has "$k6" "0x000b" "arm64 UEFI is matched on option 61 = 0x000b"
hasnt "$k6" "dns-servers" "no DNS servers are sent"
BOOT_uefi_bootfile=ipxe
_writeKea6Config "$WORK/k6b.conf" "fd00:f06:0:1::1"
has "$(cat "$WORK/k6b.conf")" "tftp://[fd00:f06:0:1::1]/ipxe.efi" "--uefi-bootfile ipxe carries into the DHCPv6 boot file"
BOOT_uefi_bootfile=""

# configureDHCP6 end to end, with service handling switched off.
kea6config="$WORK/etc-kea/kea-dhcp6.conf"
timestamp="t1"
systemctl=no
FOG_os_id=9
kea6service="kea-dhcp6"

DHCP6_range_start=""; DHCP6_range_end=""
out=$(configureDHCP6 2>&1)
[[ ! -e $kea6config ]] && ok "no --dhcp6-range: no DHCPv6 config is written" || bad "a DHCPv6 config was written without --dhcp6-range"
is "$out" "" "no --dhcp6-range: configureDHCP6 does nothing and says nothing"

DHCP6_range_start="fd00:f06:0:1::100"; DHCP6_range_end="fd00:f06:0:1::1ff"
configureDHCP6 >/dev/null 2>&1
[[ -s $kea6config ]] && ok "--dhcp6-range writes the DHCPv6 config" || bad "configureDHCP6 wrote no config"
has "$(cat "$kea6config" 2>/dev/null)" 'tftp://[fd00:f06:0:1::1]/default.ipxe' \
    "...naming the server's address in the pool's /64, not the other one"

rm -f "$kea6config"
DHCP6_range_start="fd00:dead:0:9::100"; DHCP6_range_end="fd00:dead:0:9::1ff"
out=$(configureDHCP6 2>&1)
has "$out" "no IPv6 address in the /64" "a pool outside every server /64 is refused with a reason"
[[ ! -e $kea6config ]] && ok "...and no config is written for it" || bad "a config was written for a pool the server cannot serve"

# The generated file is plain JSON. This is a syntax check only. The
# kea-dhcp6 -t check needs the daemon and is run by hand, not here.
if command -v python3 >/dev/null 2>&1; then
    DHCP6_range_start="fd00:f06:0:1::100"; DHCP6_range_end="fd00:f06:0:1::1ff"
    if python3 -I -c 'import json,sys; json.load(open(sys.argv[1]))' "$WORK/k6.conf" 2>/dev/null; then
        ok "the DHCPv6 config is valid JSON"
    else
        bad "the DHCPv6 config is not valid JSON"
    fi
fi

echo "== --dhcp6-range: option parsing =="

# getopt itself decides whether the long option takes a value. Run it with the
# installer's own option string.
longopts=$(sed -n 's/^longopts="\(.*\)"$/\1/p' "$INSTALLER")
[[ -n $longopts ]] || { echo "ERROR: could not read the installer's long options" >&2; exit 1; }
parsed=$(getopt -o "" -l "$longopts" -n test -- --dhcp6-range fd00::100,fd00::1ff 2>&1)
is "$parsed" " --dhcp6-range 'fd00::100,fd00::1ff' --" "getopt accepts --dhcp6-range and keeps its value"
arm=$(sed -n '/^        --dhcp6-range)$/,/^            ;;$/p' "$INSTALLER")
[[ -n $arm ]] || { echo "ERROR: could not extract the --dhcp6-range arm" >&2; exit 1; }
usage() { :; }
parse6() {
    ( sDHCP6_range_start=""; sDHCP6_range_end=""
      eval "case \$1 in
$arm
esac"
      echo "start=${sDHCP6_range_start} end=${sDHCP6_range_end}" ) 2>&1
}
is "$(parse6 --dhcp6-range fd00::100,fd00::1ff)" "start=fd00::100 end=fd00::1ff" "START,END is split into the two ends"
is "$(parse6 --dhcp6-range fd00::100)" "--dhcp6-range requires START,END IPv6 addresses after, like fd00::100,fd00::1ff" "a single address is refused"
is "$(parse6 --dhcp6-range 10.0.0.1,10.0.0.9)" "--dhcp6-range requires START,END IPv6 addresses after, like fd00::100,fd00::1ff" "IPv4 addresses are refused"
is "$(parse6 --dhcp6-range fd00::100,nonsense)" "--dhcp6-range requires START,END IPv6 addresses after, like fd00::100,fd00::1ff" "a bad end is refused"
apply=$(grep -E '^\[\[ -n \$\{sDHCP6_range_(start|end)\} \]\]' "$INSTALLER")
sDHCP6_range_start="fd00::100"; sDHCP6_range_end="fd00::1ff"; DHCP6_range_start="old"; DHCP6_range_end="old"
eval "$apply"
is "$DHCP6_range_start,$DHCP6_range_end" "fd00::100,fd00::1ff" "the flag beats a recorded pool"
sDHCP6_range_start=""; sDHCP6_range_end=""; DHCP6_range_start="a"; DHCP6_range_end="b"
eval "$apply"
is "$DHCP6_range_start,$DHCP6_range_end" "a,b" "without the flag a recorded pool is kept"

# Persistence: written to .fogsettings with the other DHCP keys.
persist=$(sed -n '/DHCP_range_start DHCP_range_end$/,+6p' "$FUNCS")
has "$persist" "DHCP6_range_start DHCP6_range_end" "the DHCPv6 keys are persisted across upgrades"
hasnt "$persist" "DHCP6_dns" "no DNS key is persisted"

echo "== --reissue-web-ca: option parsing =="
parsed=$(getopt -o "" -l "$longopts" -n test -- --reissue-web-ca 2>&1)
is "$parsed" " --reissue-web-ca --" "getopt accepts --reissue-web-ca with no value"
arm=$(sed -n '/^        --reissue-web-ca)$/,/^            ;;$/p' "$INSTALLER")
[[ -n $arm ]] || { echo "ERROR: could not extract the --reissue-web-ca arm" >&2; exit 1; }
parseReissue() {
    ( reissueWebCA=""
      eval "case \$1 in
$arm
esac"
      echo "reissueWebCA=${reissueWebCA}" ) 2>&1
}
is "$(parseReissue --reissue-web-ca)" "reissueWebCA=1" "--reissue-web-ca sets reissueWebCA"
is "$(parseReissue --other)" "reissueWebCA=" "without the option the variable stays unset"

echo "== packages =="
FOG_packages="isc-dhcp-server"
DHCP6_range_start=""
kea6package="kea-dhcp6-server"
resolveDHCP6
is "$FOG_packages" "isc-dhcp-server" "no --dhcp6-range: no package is added"
DHCP6_range_start="fd00::100"
resolveDHCP6
has "$FOG_packages" "kea-dhcp6-server" "--dhcp6-range adds the DHCPv6 package"

echo "== firewall: DHCPv6 opens only with --dhcp6-range =="

DHCP_enabled=no
BOOT_external_tftp_server=no
DHCP6_range_start=""
is "$(_firewallPortList | grep -c '^547/udp')" "0" "no --dhcp6-range: 547/udp is not opened"
DHCP6_range_start="fd00::100"
is "$(_firewallPortList | grep '^547/udp' | cut -d' ' -f1)" "547/udp" "--dhcp6-range: 547/udp is in the port list (ufw and the summary)"
fwcalls="$WORK/fwcalls"
: > "$fwcalls"
firewall-cmd() { echo "firewall-cmd $*" >> "$fwcalls"; return 0; }
_configureFirewalld >/dev/null 2>&1
has "$(cat "$fwcalls")" "--add-service=dhcpv6" "--dhcp6-range: firewalld gets the dhcpv6 service"
: > "$fwcalls"
DHCP6_range_start=""
_configureFirewalld >/dev/null 2>&1
hasnt "$(cat "$fwcalls")" "dhcpv6" "no --dhcp6-range: firewalld gets no dhcpv6 service"
unset -f firewall-cmd
DHCP6_range_start="fd00::100"
ufwcalls="$WORK/ufwcalls"
: > "$ufwcalls"
ufw() { echo "ufw $*" >> "$ufwcalls"; return 0; }
_configureUfw >/dev/null 2>&1
has "$(cat "$ufwcalls")" "ufw allow 547/udp" "--dhcp6-range: ufw allows 547/udp"
unset -f ufw
hint=$(_reportIptables 2>&1)
has "$hint" "ip6tables -I INPUT -p udp --dport 547 -j ACCEPT" "--dhcp6-range: the iptables hint names 547/udp, with ip6tables"
has "$hint" "iptables -I INPUT -p tcp --dport 80 -j ACCEPT" "the other hint lines still use iptables"

echo
echo "installer-ipv6: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]] && echo PASS || { echo FAIL; exit 1; }
