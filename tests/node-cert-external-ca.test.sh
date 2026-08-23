#!/bin/bash
#
# Guards storage-node certificate issuance, including from an imported Web CA.
#
#   tests/node-cert-external-ca.test.sh
#
# A storage node generates its OWN keypair and its OWN CSR (_requestNodeCert),
# POSTs it to the master's service/nodecert.php, and the master signs it with
# the Web CA through fog-sign-node-cert -- a root-only helper the web user can
# invoke but cannot hand paths to.
#
# That worked only while the Web CA was FOG's own. The helper appended
# PKI_ROOT_CERT to the chain it returned and verified the freshly-signed leaf
# against PKI_ROOT_CERT, and PKI_ROOT_CERT is the FOG root -- which, on a server
# whose admin supplied their own Web CA, never signed that intermediate. So:
#
#   * `openssl verify -CAfile <FOG root> -untrusted <imported intermediate>`
#     cannot build a chain, so EVERY node request was refused, and
#   * the refusal said "a requested name is probably outside the CA's name
#     constraints", sending the admin to look at name constraints for something
#     that was never a name problem.
#
# The fix is one idea: the web zone's anchor is not necessarily the FOG root.
# PKI_WEB_ANCHOR names pki/web/ca/.trustAnchor.pem, which _resolveTrustAnchor
# builds with the FOG root AND an imported root where there is one, deduped by
# fingerprint -- so one path covers both installs. PKI_WEB_CHAIN names the
# zone's own trust path (issuer + the root anchoring it), which is what a node
# should serve beneath its leaf.
#
# Runs fog-sign-node-cert directly against generated CAs. Needs openssl. No
# install, no network, no root, no sudo.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
HELPER="$REPO/packages/pki/fog-sign-node-cert"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $HELPER ]] || { echo "ERROR: $HELPER not found" >&2; exit 1; }
[[ -f $FUNCS ]]  || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl is not installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# --- fixtures ---------------------------------------------------------------
# Two independent worlds, each a root that signs an intermediate:
#   fog/  what FOG mints itself
#   ext/  an intermediate an enterprise PKI handed the admin, under ITS own
#         root, which FOG has never seen
mkca() {
    local d="$WORK/$1"
    mkdir -p "$d"
    openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 5 \
        -subj "/CN=$1 Root" -keyout "$d/root.key" -out "$d/root.pem" \
        -addext "basicConstraints=critical,CA:TRUE" >/dev/null 2>&1 || return 1
    openssl req -new -nodes -newkey rsa:2048 -sha256 -subj "/CN=$1 Web CA" \
        -keyout "$d/int.key" -out "$d/int.csr" >/dev/null 2>&1 || return 1
    printf '[v3_int]\nbasicConstraints=critical,CA:TRUE,pathlen:0\nkeyUsage=critical,keyCertSign,cRLSign\n' > "$d/int.cnf"
    openssl x509 -req -in "$d/int.csr" -CA "$d/root.pem" -CAkey "$d/root.key" \
        -CAcreateserial -sha256 -days 5 -extensions v3_int -extfile "$d/int.cnf" \
        -out "$d/int.pem" >/dev/null 2>&1 || return 1
    # The zone's trust path, exactly as _writeWebChainFiles builds it.
    cat "$d/int.pem" "$d/root.pem" > "$d/chain.pem"
    return 0
}
mkca fog || { echo "ERROR: fog CA fixture failed" >&2; exit 1; }
mkca ext || { echo "ERROR: ext CA fixture failed" >&2; exit 1; }

# A node's request: its own key, its own CSR. This is the node side of
# _requestNodeCert, and the point of the whole design -- the node's private key
# never leaves the node.
mkdir -p "$WORK/node"
openssl genrsa -out "$WORK/node/node.key" 2048 >/dev/null 2>&1
openssl req -new -sha256 -key "$WORK/node/node.key" -out "$WORK/node/node.csr" \
    -subj "/CN=node1.test.local" >/dev/null 2>&1

# The staging dir and the root-only conf the helper reads.
STAGE="$WORK/staging"
mkdir -p "$STAGE"
run_helper() {
    # The helper requires a 32-char lowercase hex id -- it is validated before
    # it ever reaches the filesystem, which is what stops a request id being a
    # path. Use a real one rather than working around the check.
    local zone="$1" conf="$WORK/fog-pki.conf"
    local reqid="deadbeefcafe4000900000000000000f"
    cp "$WORK/node/node.csr" "$STAGE/${reqid}.csr"
    # One entry per line, 'DNS:name' or 'IP:addr' -- the helper re-validates
    # each and refuses anything else, since a line with '[' or '=' would open a
    # new section in the extension file it builds.
    printf 'DNS:node1.test.local\n' > "$STAGE/${reqid}.san"
    rm -f "$STAGE/${reqid}.pem" "$STAGE/${reqid}.chain"
    sed "s|^CONF=.*|CONF=\"${conf}\"|" "$HELPER" > "$WORK/helper.sh"
    chmod +x "$WORK/helper.sh"
    HELPER_OUT="$("$WORK/helper.sh" "$zone" "$reqid" 2>&1)"
    HELPER_ST=$?
    LEAF="$STAGE/${reqid}.pem"
    CHAIN="$STAGE/${reqid}.chain"
    return $HELPER_ST
}
writeconf() { printf '%s\n' "$@" > "$WORK/fog-pki.conf"; }

echo "node cert issuance:"

# --- A. FOG's own Web CA: the case that already worked -----------------------
writeconf \
    "PKI_WEB_CA_CERT=$WORK/fog/int.pem" \
    "PKI_WEB_CA_KEY=$WORK/fog/int.key" \
    "PKI_ROOT_CERT=$WORK/fog/root.pem" \
    "PKI_WEB_ANCHOR=$WORK/fog/root.pem" \
    "PKI_WEB_CHAIN=$WORK/fog/chain.pem" \
    "PKI_STAGING=$STAGE"
run_helper web
is "$HELPER_ST" "0" "A: FOG's own Web CA issues to a node"
[[ -s $LEAF ]] && ok "A: a leaf was written" || bad "A: no leaf (out: $HELPER_OUT)"
openssl verify -CAfile "$WORK/fog/root.pem" -untrusted "$WORK/fog/int.pem" "$LEAF" >/dev/null 2>&1 \
    && ok "A: the leaf verifies under the FOG root" \
    || bad "A: the leaf does not verify"
# The leaf must belong to the key the NODE holds, which is what proves the
# master signed the node's own CSR rather than issuing a keypair for it.
lm=$(openssl x509 -noout -modulus -in "$LEAF" 2>/dev/null)
km=$(openssl rsa -noout -modulus -in "$WORK/node/node.key" 2>/dev/null)
is "$lm" "$km" "A: the leaf pairs with the node's own private key"

# --- B. THE REGRESSION: an imported Web CA ----------------------------------
# Everything is the admin's except PKI_ROOT_CERT, which stays FOG's root because
# that is what fog-client pins and an external WEB CA does not replace it. This
# is precisely the shape that used to fail.
writeconf \
    "PKI_WEB_CA_CERT=$WORK/ext/int.pem" \
    "PKI_WEB_CA_KEY=$WORK/ext/int.key" \
    "PKI_ROOT_CERT=$WORK/fog/root.pem" \
    "PKI_WEB_ANCHOR=$WORK/ext/root.pem" \
    "PKI_WEB_CHAIN=$WORK/ext/chain.pem" \
    "PKI_STAGING=$STAGE"
run_helper web
is "$HELPER_ST" "0" "B: an IMPORTED Web CA issues to a node"
[[ $HELPER_OUT != *"name constraints"* ]] \
    && ok "B: no bogus name-constraints refusal" \
    || bad "B: refused with the name-constraints message (out: $HELPER_OUT)"
[[ -s $LEAF ]] && ok "B: a leaf was written" || bad "B: no leaf (out: $HELPER_OUT)"
openssl verify -CAfile "$WORK/ext/root.pem" -untrusted "$WORK/ext/int.pem" "$LEAF" >/dev/null 2>&1 \
    && ok "B: the leaf verifies under the ADMIN's root" \
    || bad "B: the leaf does not verify under the admin's root"

# The chain handed to the node must be the admin's trust path, and must NOT
# carry the FOG root -- a node serving an unrelated root is what the old code
# produced.
is "$(grep -c 'BEGIN CERTIFICATE' "$CHAIN")" "2" "B: the chain is issuer + its own root"
extrootfp=$(openssl x509 -in "$WORK/ext/root.pem" -noout -fingerprint -sha256 2>/dev/null | sed 's/.*=//')
fogrootfp=$(openssl x509 -in "$WORK/fog/root.pem" -noout -fingerprint -sha256 2>/dev/null | sed 's/.*=//')
chainfps=$(awk 'BEGIN{n=0} /BEGIN CERT/{n++} {print > "'"$WORK"'/c" n ".pem"}' "$CHAIN"; \
           for f in "$WORK"/c*.pem; do openssl x509 -in "$f" -noout -fingerprint -sha256 2>/dev/null | sed 's/.*=//'; done)
[[ $chainfps == *"$extrootfp"* ]] \
    && ok "B: the admin's root IS in the chain" \
    || bad "B: the admin's root is missing from the chain"
[[ $chainfps != *"$fogrootfp"* ]] \
    && ok "B: the FOG root is NOT in the chain" \
    || bad "B: the FOG root was appended to a chain it does not anchor"
rm -f "$WORK"/c*.pem

# --- C. the fallback, for a conf written before these keys existed ----------
# An upgraded master whose .fog-pki predates PKI_WEB_ANCHOR/PKI_WEB_CHAIN must
# still issue on an ordinary FOG-CA install rather than failing closed.
writeconf \
    "PKI_WEB_CA_CERT=$WORK/fog/int.pem" \
    "PKI_WEB_CA_KEY=$WORK/fog/int.key" \
    "PKI_ROOT_CERT=$WORK/fog/root.pem" \
    "PKI_STAGING=$STAGE"
run_helper web
is "$HELPER_ST" "0" "C: an old conf with neither new key still issues"
is "$(grep -c 'BEGIN CERTIFICATE' "$CHAIN")" "2" "C: and still builds issuer + root"

# --- D. a genuinely absent CA is still refused -------------------------------
# The fallbacks must not turn "no CA here" into a silent success.
writeconf \
    "PKI_WEB_CA_CERT=$WORK/nope/int.pem" \
    "PKI_WEB_CA_KEY=$WORK/nope/int.key" \
    "PKI_ROOT_CERT=$WORK/fog/root.pem" \
    "PKI_STAGING=$STAGE"
run_helper web
[[ $HELPER_ST -ne 0 ]] && ok "D: a missing Web CA is refused" \
    || bad "D: a missing Web CA was accepted"

# --- E. the master's conf actually carries the new keys ----------------------
# The helper cannot use what _installNodeCertSigner does not write.
signer=$(awk '/^_installNodeCertSigner\(\) \{/,/^\}/' "$FUNCS")
for k in PKI_WEB_ANCHOR PKI_WEB_CHAIN; do
    printf '%s\n' "$signer" | grep -q "echo \"${k}=" \
        && ok "E: _installNodeCertSigner writes ${k}" \
        || bad "E: _installNodeCertSigner does not write ${k}"
done
# And the anchor it names is the trust-anchor bundle, not the FOG root -- naming
# PKI_root_ca_cert there would reintroduce the whole bug.
printf '%s\n' "$signer" | grep -q 'PKI_WEB_ANCHOR=.*trustAnchor' \
    && ok "E: PKI_WEB_ANCHOR names the web zone's trust anchor bundle" \
    || bad "E: PKI_WEB_ANCHOR does not name .trustAnchor.pem"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
