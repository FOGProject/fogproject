#!/bin/bash
#
# Guards the vhost's primary name and its alias list.
#
#   tests/vhost-servername.test.sh
#
# _createWebLeaf() has issued CN=${NET_hostname} with every address as an IP SAN for a
# while, so the certificate has been name-first. The vhost was not: ServerName
# was ${NET_fog_server_ip} and the name was demoted to an alias. Harmless while nothing
# verified -- and not harmless once the installer's own calls to itself started
# verifying, because no public CA will issue for an address.
#
# Two properties are pinned, and the second is the one most likely to be
# "tidied" away by someone reading the first:
#
#   1. the primary name is the certificate's name, falling back to the address
#      only when no name is available (a DNS-less lab install);
#   2. EVERY address stays as an alias, including on an ACME install where it can
#      never verify over HTTPS. A client configured with the address still talks
#      to FOG over HTTP, and the alias is the general failsafe for anything
#      addressing this server by number.
#
# Also pins that ServerName gets exactly one name. GH-650: a second address on
# the same NIC emitted a bare "10.0.0.2" line of its own and apache refused to
# start, failing the install at "Starting and checking status of web services".
#
# Needs openssl. Runs entirely on generated fixtures -- no install, no network,
# no root.
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
has()   { [[ " $1 " == *" $2 "* ]] && ok "$3" || bad "$3 (not in '$1')"; }
hasnt() { [[ " $1 " != *" $2 "* ]] && ok "$3" || bad "$3 (unexpectedly in '$1')"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=fog.example.org" \
    -keyout "$WORK/leaf.key" -out "$WORK/leaf.pem" >/dev/null 2>&1

reset_env() {
    etcconf=""; PKI_web_vhost_cert=""; sslfullchain=""
    NET_hostname=""; NET_fog_server_ip=""; PKI_san_ip_addresses=""; PKI_san_dns_names=""
    vhostname=""; vhostaliases=""
    error_log="$WORK/error.log"
}

echo "== name first, addresses as aliases =="

# A/B/C. The ordinary multi-homed server with a certificate.
reset_env
PKI_web_vhost_cert="$WORK/leaf.pem"
NET_hostname="fog.example.org"
NET_fog_server_ip="10.0.0.1"; PKI_san_ip_addresses="10.0.0.1 10.0.0.2"
_resolveVhostNames
check "$vhostname" "fog.example.org" "A: ServerName is the certificate's name, not the address"
has "$vhostaliases" "10.0.0.1" "B: the FIRST address is still an alias"
has "$vhostaliases" "10.0.0.2" "C: the second address is an alias too"

# D. ServerName must not repeat in the aliases -- apache warns on a duplicate,
#    and nginx treats a repeated server_name as a conflict.
hasnt "$vhostaliases" "fog.example.org" "D: the primary name is not repeated as an alias"

# E. ServerName takes exactly one word. GH-650 is what happens otherwise.
check "$(printf '%s' "$vhostname" | wc -w | tr -d ' ')" "1" "E: ServerName is a single name"

# F. Admin extras ride along.
reset_env
PKI_web_vhost_cert="$WORK/leaf.pem"
PKI_san_ip_addresses="10.0.0.1"; PKI_san_dns_names="fog.dmz.example.org images.example.org"
_resolveVhostNames
has "$vhostaliases" "fog.dmz.example.org"  "F: --extra-server-name reaches the aliases"
has "$vhostaliases" "images.example.org"   "F2: every extra name, not just the first"

echo "== the DNS-less fallback =="

# G/H. No certificate and no hostname: the address becomes the primary name, and
#      must NOT then also appear as an alias of itself.
reset_env
NET_fog_server_ip="10.0.0.5"; PKI_san_ip_addresses="10.0.0.5"
_resolveVhostNames
check "$vhostname" "10.0.0.5" "G: falls back to the address when no name exists"
hasnt "$vhostaliases" "10.0.0.5" "H: the fallback address is not aliased to itself"

# I. hostname with no readable certificate still beats the address.
reset_env
NET_hostname="fog.example.org"; NET_fog_server_ip="10.0.0.5"; PKI_san_ip_addresses="10.0.0.5"
_resolveVhostNames
check "$vhostname" "fog.example.org" "I: \${NET_hostname} is preferred over the address"
has "$vhostaliases" "10.0.0.5" "I2: and the address is aliased"

echo "== emission (source-level) =="

# J. An empty ServerAlias is an apache config error, and the alias list IS empty
#    on a single-homed server with no name but its address (case G/H).
if grep -q '\[\[ -n \$vhostaliases \]\] && echo "    ServerAlias\${vhostaliases}"' "$FUNCS"; then
    ok "J: ServerAlias is only emitted when there is an alias to emit"
else
    bad "J: ServerAlias may be emitted empty"
fi

# K. nginx's server_name must lead with the primary name too, or the two web
#    servers disagree about the server's identity again.
if [[ $(grep -c 'server_name \${vhostname}\${vhostaliases};' "$FUNCS") -eq 3 ]]; then
    ok "K: all three nginx server_name lines lead with the primary name"
else
    bad "K: an nginx server_name line still leads with the address"
fi

# L. Both web servers derive from ONE helper. They used to compute this
#    separately, which is how they drifted apart in the first place.
if [[ $(grep -c '_resolveVhostNames$' "$FUNCS") -eq 1 ]]; then
    ok "L: the name split is computed once, for both web servers"
else
    bad "L: more than one call site computes the name split"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
