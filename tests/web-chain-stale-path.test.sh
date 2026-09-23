#!/bin/bash
#
# Pins that a FOG-issued web leaf is served WITH its Web CA when the chain
# setting names FOG's own default by a different spelling.
#
#   tests/web-chain-stale-path.test.sh
#
# Field report: a server upgraded from 1.5 served .webLeaf.pem alone, and the
# installer's self-calls failed "unable to get local issuer certificate".
# .fogWebCA.pem was on disk and verified; .fogWebCAchain.pem was not. Two
# defects combined:
#
#  - createWebIntermediateCA() decided "admin override or FOG default?" by
#    comparing PKI_web_trust_chain as a STRING. A value persisted before the
#    pki tree moved to /etc/fog/pki reads /opt/fog/pki/..., now a symlink to
#    the same file, and the root path carries a double slash from
#    PKI_client_cert_dir's trailing slash. Both read as overrides, so the chain
#    file was never written.
#  - _webChainCandidates() read only that chain file for a FOG-issued leaf, so
#    with it missing the pool was empty although PKI_web_ca_cert -- the CA
#    that signed the leaf -- was right there.
#
# Needs openssl. Runs on generated fixtures -- no install, no network, no root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl is not installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
dots() { :; }
errorStat() { :; }

# The migrated layout: the real tree under etc/, the legacy opt/ path a symlink.
fogprogramdir="$WORK/opt/fog"
PKI_root_dir="$WORK/etc/fog/pki"
mkdir -p "$PKI_root_dir/web/ca" "$PKI_root_dir/web/leaf" "$fogprogramdir/snapins/ssl/CA"
ln -s "$PKI_root_dir" "$fogprogramdir/pki"
cadir="$PKI_root_dir/web/ca"
leafdir="$PKI_root_dir/web/leaf"

# --- fixture: 1.5-era root -> FOG Web CA -> leaf ------------------------------
root="$fogprogramdir/snapins/ssl/CA/.fogCA.pem"
openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 30 \
    -subj "/CN=FOG Server CA" -keyout "$fogprogramdir/snapins/ssl/CA/.fogCA.key" \
    -out "$root" >/dev/null 2>&1 || { echo "ERROR: fixture root CA failed" >&2; exit 1; }
openssl req -new -nodes -newkey rsa:2048 -sha256 \
    -subj "/CN=FOG Web CA/O=FOG Project/OU=FOG Web UI" \
    -keyout "$cadir/.fogWebCA.key" -out "$cadir/int.csr" >/dev/null 2>&1
printf '%s\n' 'basicConstraints=critical,CA:TRUE' 'keyUsage=critical,keyCertSign,cRLSign' \
    > "$cadir/int.ext"
openssl x509 -req -in "$cadir/int.csr" -CA "$root" \
    -CAkey "$fogprogramdir/snapins/ssl/CA/.fogCA.key" -CAcreateserial -sha256 -days 30 \
    -extfile "$cadir/int.ext" -out "$cadir/.fogWebCA.pem" >/dev/null 2>&1
openssl req -new -nodes -newkey rsa:2048 -sha256 -subj "/CN=fogserver" \
    -keyout "$leafdir/.webLeaf.key" -out "$leafdir/leaf.csr" >/dev/null 2>&1
openssl x509 -req -in "$leafdir/leaf.csr" -CA "$cadir/.fogWebCA.pem" \
    -CAkey "$cadir/.fogWebCA.key" -CAcreateserial -sha256 -days 30 \
    -out "$leafdir/.webLeaf.pem" >/dev/null 2>&1
webcafp=$(openssl x509 -in "$cadir/.fogWebCA.pem" -noout -fingerprint -sha256)

PKI_web_vhost_key="$leafdir/.webLeaf.key"
PKI_web_vhost_cert="$leafdir/.webLeaf.pem"

# Does the assembled full chain carry the Web CA after the leaf?
fullchainHasWebCA() {
    local f="$leafdir/.webFullChain.pem"
    [[ -s $f ]] || return 1
    awk '/BEGIN CERT/{n++} n==2' "$f" | openssl x509 -noout -fingerprint -sha256 2>/dev/null \
        | grep -qxF "$webcafp"
}

echo "web chain stale path:"

# --- defect 1: the legacy /opt path is FOG's default, not an override ---------
reset() {
    rm -f "$cadir/.fogWebCAchain.pem" "$leafdir/.webFullChain.pem" "$leafdir/.webChain.pem"
    PKI_root_ca_cert="$root"
    PKI_web_ca_cert=""
}
reset
PKI_web_trust_chain="$fogprogramdir/pki/web/ca/.fogWebCAchain.pem"
createWebIntermediateCA >/dev/null 2>&1
[[ ${PKI_web_trust_chain} == "$cadir/.fogWebCAchain.pem" ]] \
    && ok "a legacy /opt path is recognized as the FOG default" \
    || bad "a legacy /opt path is recognized as the FOG default (kept ${PKI_web_trust_chain})"
[[ -s $cadir/.fogWebCAchain.pem ]] && ok "and the chain file is written" \
    || bad "and the chain file is written"

# The root path as PKI_client_cert_dir's trailing slash spells it.
reset
PKI_root_ca_cert="$fogprogramdir/snapins/ssl//CA/.fogCA.pem"
PKI_web_trust_chain="$root"
createWebIntermediateCA >/dev/null 2>&1
[[ -s $cadir/.fogWebCAchain.pem ]] && ok "a double-slash root path is recognized too" \
    || bad "a double-slash root path is recognized too"

# A real override is still honored.
reset
mine="$WORK/admin-chain.pem"
cat "$cadir/.fogWebCA.pem" "$root" > "$mine"
PKI_web_trust_chain="$mine"
createWebIntermediateCA >/dev/null 2>&1
[[ ${PKI_web_trust_chain} == "$mine" && ! -e $cadir/.fogWebCAchain.pem ]] \
    && ok "an admin's own chain path is left alone" \
    || bad "an admin's own chain path is left alone (now ${PKI_web_trust_chain})"

# --- defect 2: a missing chain file still serves the Web CA --------------------
reset
PKI_web_ca_cert="$cadir/.fogWebCA.pem"
PKI_web_trust_chain="$WORK/nowhere/chain.pem"
out=$(_writeWebChainFiles 2>&1)
fullchainHasWebCA && ok "with no chain file, the full chain still carries the Web CA" \
    || bad "with no chain file, the full chain still carries the Web CA"
[[ $out != *"no intermediate certificate was found"* ]] && ok "and nothing warns" \
    || bad "and nothing warns (got: $out)"

# --- end to end: the field state converges -----------------------------------
reset
PKI_web_trust_chain="$fogprogramdir/pki/web/ca/.fogWebCAchain.pem"
createWebIntermediateCA >/dev/null 2>&1
_writeWebChainFiles >/dev/null 2>&1
fullchainHasWebCA && ok "the field state now serves leaf + Web CA" \
    || bad "the field state now serves leaf + Web CA"
openssl verify -trusted "$root" -untrusted "$leafdir/.webFullChain.pem" \
    "$leafdir/.webFullChain.pem" >/dev/null 2>&1 \
    && ok "and what is served verifies against the root" \
    || bad "and what is served verifies against the root"

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
