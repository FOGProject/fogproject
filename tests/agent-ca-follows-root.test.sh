#!/bin/bash
#
# Guards: the FOG Agent CA must be issued by the CURRENT root.
#
#   tests/agent-ca-follows-root.test.sh
#
# createAgentIntermediateCA used to mint only when .fogAgentCA.pem was absent.
# Replace the root under an existing install -- restoring an older server's CA
# onto a fresh 1.6 build so registered fog-clients keep trusting it -- and the
# agent CA stayed signed by the root it replaced. fog-sign-node-cert then
# refused every agent leaf with "the issued certificate does not verify
# against .../.fogCA.pem", and every enrollment answered 503.
#
# Drives createAgentIntermediateCA directly against a scratch tree.
# Needs openssl. No network, no root, no install.
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
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

error_log="$WORK/error.log"
: > "$error_log"

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

dots() { :; }
errorStat() { :; }
setSELinuxContext() { :; }

fogprogramdir="$WORK/opt/fog"
PKI_root_dir="$fogprogramdir/pki"
PKI_client_cert_dir="$WORK/opt/fog/snapins/ssl"
mkdir -p "${PKI_client_cert_dir}/CA"
PKI_root_ca_cert="${PKI_client_cert_dir}/CA/.fogCA.pem"
PKI_root_ca_key="${PKI_client_cert_dir}/CA/.fogCA.key"

# A self-signed root with the extensions the installer gives its own.
mint_root() {
    openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 30 \
        -keyout "${PKI_root_ca_key}" -out "${PKI_root_ca_cert}" \
        -subj "/CN=$1/O=FOG Project/OU=FOG Root CA" \
        -addext "basicConstraints=critical,CA:TRUE" \
        -addext "keyUsage=critical,keyCertSign,cRLSign" >>"$error_log" 2>&1
}

sumof() { openssl dgst -sha256 "$1" 2>/dev/null | awk '{print $NF}'; }

issued_by_root() {
    openssl verify -trusted "${PKI_root_ca_cert}" "${PKI_agent_ca_cert}" >/dev/null 2>&1
}

echo "agent CA follows the root:"

mint_root "FOG Server CA one"
createAgentIntermediateCA >/dev/null
if issued_by_root; then
    ok "a fresh agent CA verifies against the root"
else
    bad "a fresh agent CA does not verify against the root -- check $error_log"
fi
first=$(sumof "${PKI_agent_ca_cert}")

createAgentIntermediateCA >/dev/null
if [[ "$(sumof "${PKI_agent_ca_cert}")" == "$first" ]]; then
    ok "a second run with the same root keeps the agent CA"
else
    bad "a second run with the same root re-minted the agent CA"
fi

# The reported case: the root is replaced under an existing agent CA.
mint_root "FOG Server CA two"
createAgentIntermediateCA >/dev/null
if issued_by_root; then
    ok "after the root is replaced, the agent CA verifies against the new root"
else
    bad "after the root is replaced, the agent CA still chains to the OLD root"
fi
if ls "${PKI_agent_ca_cert}".* >/dev/null 2>&1; then
    ok "the replaced agent CA is kept beside the new one"
else
    bad "the replaced agent CA was not kept"
fi
if grep -q "$(openssl x509 -in "${PKI_agent_ca_cert}" 2>/dev/null | sed -n 2p)" "${PKI_agent_ca_bundle}"; then
    ok "the agent CA bundle carries the new agent CA"
else
    bad "the agent CA bundle does not carry the new agent CA"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
