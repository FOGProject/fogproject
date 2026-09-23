#!/bin/bash
#
# Pins which warning _writeWebChainFiles() prints when the leaf's issuer is
# present but openssl refuses it.
#
#   tests/web-chain-issuer-rejected.test.sh
#
# _walkChainFromLeaf() accepts an issuer only if `openssl verify
# -partial_chain` passes. It used to discard that verdict, so a Web CA that
# was found but rejected -- in the field, a leaf carrying an address outside
# the CA's name constraints ("permitted subtree violation") -- was reported
# as _warnNoWebIntermediate: "nothing on this server chains to that issuer",
# plus advice about ACME chain files. For FOG's own Web CA that is the wrong
# cause and the wrong remedy.
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
has()    { [[ "$1" == *"$2"* ]] && ok "$3" || bad "$3 (missing '$2')"; }
hasnt()  { [[ "$1" != *"$2"* ]] && ok "$3" || bad "$3 (unexpected '$2')"; }

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
dots() { :; }
errorStat() { :; }

fogprogramdir="$WORK/opt/fog"
PKI_root_dir="$fogprogramdir/pki"
PKI_client_cert_dir="$WORK/opt/fog/snapins/ssl"
leafdir="$fogprogramdir/pki/web/leaf"
cadir="$fogprogramdir/pki/web/ca"
mkdir -p "$leafdir" "$cadir" "${PKI_client_cert_dir}"

# --- fixture: root -> name-constrained Web CA -> leaf ------------------------
openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 30 \
    -subj "/CN=FOG Server CA" -keyout "$cadir/root.key" -out "$cadir/root.pem" \
    >/dev/null 2>&1 || { echo "ERROR: fixture root CA failed" >&2; exit 1; }
openssl req -new -nodes -newkey rsa:2048 -sha256 \
    -subj "/CN=FOG Web CA/O=FOG Project/OU=FOG Web UI" \
    -keyout "$cadir/.fogWebCA.key" -out "$cadir/int.csr" >/dev/null 2>&1
# The shape _nameConstraints() emits: the server's names plus RFC1918.
printf '%s\n' 'basicConstraints=critical,CA:TRUE' \
    'keyUsage=critical,keyCertSign,cRLSign' \
    'nameConstraints=critical,permitted;DNS:fogserver,permitted;IP:10.0.0.0/255.0.0.0,permitted;IP:127.0.0.0/255.0.0.0' \
    > "$cadir/int.ext"
openssl x509 -req -in "$cadir/int.csr" -CA "$cadir/root.pem" -CAkey "$cadir/root.key" \
    -CAcreateserial -sha256 -days 30 -extfile "$cadir/int.ext" \
    -out "$cadir/.fogWebCA.pem" >/dev/null 2>&1

PKI_web_trust_chain="$cadir/.fogWebCAchain.pem"
cat "$cadir/.fogWebCA.pem" "$cadir/root.pem" > "${PKI_web_trust_chain}"
PKI_root_ca_cert="$cadir/root.pem"
PKI_web_vhost_key="$leafdir/.webLeaf.key"
PKI_web_vhost_cert="$leafdir/.webLeaf.pem"

# Leaf signed by that CA, carrying $1 as its IP SAN.
mkleaf() {
    printf 'subjectAltName=DNS:fogserver,IP:%s\n' "$1" > "$leafdir/leaf.ext"
    openssl req -new -nodes -newkey rsa:2048 -sha256 -subj "/CN=fogserver" \
        -keyout "$leafdir/.webLeaf.key" -out "$leafdir/leaf.csr" >/dev/null 2>&1
    openssl x509 -req -in "$leafdir/leaf.csr" -CA "$cadir/.fogWebCA.pem" \
        -CAkey "$cadir/.fogWebCA.key" -CAcreateserial -sha256 -days 30 \
        -extfile "$leafdir/leaf.ext" -out "$leafdir/.webLeaf.pem" >/dev/null 2>&1
}

echo "web chain issuer rejected:"

# --- the regression: an address outside the constraints ----------------------
mkleaf 203.0.113.5
out=$(_writeWebChainFiles 2>&1)
has   "$out" "issuer was found but does not verify" "a rejected issuer says it was found"
has   "$out" "permitted subtree violation"          "it quotes openssl's reason"
has   "$out" "rm -rf $fogprogramdir/pki/web"        "it names the Web zone to remove"
hasnt "$out" "no intermediate certificate was found" "it does not claim nothing chains"
hasnt "$out" "letsencrypt"                          "it does not give ACME advice"

# --- the control: an address inside them still chains, silently --------------
mkleaf 10.1.2.3
out=$(_writeWebChainFiles 2>&1)
[[ -z $out ]] && ok "a leaf inside the constraints warns about nothing" \
    || bad "a leaf inside the constraints warns about nothing (got: $out)"
[[ -s $leafdir/.webFullChain.pem ]] && ok "and the full chain is assembled" \
    || bad "and the full chain is assembled"

# --- the genuinely-missing case keeps its own warning -------------------------
: > "${PKI_web_trust_chain}"
out=$(_writeWebChainFiles 2>&1)
has   "$out" "no intermediate certificate was found" "an absent issuer keeps the old warning"
hasnt "$out" "does not verify"                       "and is not reported as rejected"

echo
echo "  $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
