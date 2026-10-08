#!/bin/bash
#
# Guards: the client communication certificate must be issued by the CURRENT
# root, over the SAME key.
#
#   tests/comm-leaf-follows-root.test.sh
#
# _createCommLeaf kept .srvpublic.crt whenever it existed. Replace the root
# under an existing install -- restoring an older server's CA onto a fresh 1.6
# build so registered fog-clients keep trusting it -- and the leaf stayed signed
# by the root it replaced. Every fog-client checks it against the root it
# pinned and refused it ("Trust chain did not complete to the known authority
# anchor. Thumbprints did not match."), so the whole fleet stopped checking in.
#
# Drives _createCommLeaf and _warnClientRepin directly against a scratch tree.
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
PKI_root_dir="$WORK/etc/fog/pki"
PKI_client_cert_dir="$WORK/opt/fog/snapins/ssl"
webdirdest="$WORK/var/www/fog"
DB_backup_path="$WORK/backup"
version="test"
mkdir -p "${PKI_client_cert_dir}/CA" "$(_pkiConfDir)" "$(_pkiZoneDir client)/leaf" \
    "$webdirdest/management/other/ssl"
PKI_root_ca_cert="${PKI_client_cert_dir}/CA/.fogCA.pem"
PKI_root_ca_key="${PKI_client_cert_dir}/CA/.fogCA.key"
PKI_client_encrypt_cert="$(_pkiZoneDir client)/leaf/.srvpublic.crt"
PKI_client_encrypt_key="$(_pkiZoneDir client)/leaf/.srvprivate.key"
printf '[req]\nprompt = no\ndistinguished_name = dn\n[dn]\nCN = 192.0.2.10\n' > "$(_pkiConfDir)/req.cnf"
printf '[v3_ca]\nsubjectAltName = IP:192.0.2.10\n' > "$(_pkiConfDir)/ca.cnf"

mint_root() {
    openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 30 \
        -keyout "${PKI_root_ca_key}" -out "${PKI_root_ca_cert}" \
        -subj "/CN=$1" \
        -addext "basicConstraints=critical,CA:TRUE" \
        -addext "keyUsage=critical,keyCertSign,cRLSign" >>"$error_log" 2>&1
}

pubkey() { openssl x509 -in "$1" -noout -pubkey 2>/dev/null; }

issued_by_root() {
    openssl verify -trusted "${PKI_root_ca_cert}" "${PKI_client_encrypt_cert}" >/dev/null 2>&1
}

echo "client communication certificate follows the root:"

# A fresh install: root A, the comm key, its CSR, and the leaf A signs.
mint_root "FOG Server CA"
openssl genrsa -out "${PKI_client_encrypt_key}" 2048 >>"$error_log" 2>&1
openssl req -new -key "${PKI_client_encrypt_key}" -out "$(_pkiZoneDir client)/leaf/fog.csr" \
    -config "$(_pkiConfDir)/req.cnf" >>"$error_log" 2>&1
_createCommLeaf >/dev/null
if issued_by_root; then
    ok "a fresh comm leaf verifies against the root"
else
    bad "a fresh comm leaf does not verify against the root -- check $error_log"
fi
original="$(pubkey "${PKI_client_encrypt_cert}")"
cp "${PKI_client_encrypt_cert}" "$webdirdest/management/other/ssl/srvpublic.crt"

# The reported case: an older server's root is restored over it, and the CSR
# beside the key came from some other key.
mint_root "FOG Server CA"
openssl genrsa -out "$WORK/other.key" 2048 >>"$error_log" 2>&1
openssl req -new -key "$WORK/other.key" -out "$(_pkiZoneDir client)/leaf/fog.csr" \
    -config "$(_pkiConfDir)/req.cnf" >>"$error_log" 2>&1
_createCommLeaf >/dev/null
if issued_by_root; then
    ok "after the root is replaced, the comm leaf verifies against the new root"
else
    bad "after the root is replaced, the comm leaf still chains to the OLD root"
fi
if [[ -n $original && "$(pubkey "${PKI_client_encrypt_cert}")" == "$original" ]]; then
    ok "the re-issued comm leaf carries the same public key"
else
    bad "the re-issued comm leaf carries a different public key"
fi
if ls "${PKI_client_encrypt_cert}".* >/dev/null 2>&1; then
    ok "the replaced comm leaf is kept beside the new one"
else
    bad "the replaced comm leaf was not kept"
fi
if [[ -z "$(_warnClientRepin)" ]]; then
    ok "re-issuing over the same key prints no re-pin warning"
else
    bad "re-issuing over the same key printed the re-pin warning"
fi

# A real key change must still warn.
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=x" \
    -keyout "$WORK/x.key" -out "$webdirdest/management/other/ssl/srvpublic.crt" >>"$error_log" 2>&1
if _warnClientRepin | grep -q "MUST BE REINSTALLED"; then
    ok "a changed key still prints the re-pin warning"
else
    bad "a changed key no longer prints the re-pin warning"
fi

# A leaf the admin keeps outside the client zone is theirs: never re-issued.
mkdir -p "$WORK/admin"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=admin" \
    -keyout "$WORK/admin/comm.key" -out "$WORK/admin/comm.crt" >>"$error_log" 2>&1
saved_cert="${PKI_client_encrypt_cert}"; saved_key="${PKI_client_encrypt_key}"
rm -f "${PKI_client_encrypt_cert}" "${PKI_client_encrypt_key}"
ln -s "$WORK/admin/comm.crt" "${PKI_client_encrypt_cert}"
ln -s "$WORK/admin/comm.key" "${PKI_client_encrypt_key}"
before="$(openssl dgst -sha256 "$WORK/admin/comm.crt")"
_createCommLeaf >/dev/null
if [[ "$(openssl dgst -sha256 "$WORK/admin/comm.crt")" == "$before" && -L ${saved_cert} ]]; then
    ok "a comm leaf kept outside the client zone is left alone"
else
    bad "a comm leaf kept outside the client zone was re-issued"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
