#!/bin/bash
#
# Guards the "long-lived certificate re-issued on every run" bug class.
#
#   tests/pki-idempotence.test.sh
#
# The web leaf's historic guard was `[[ ! -x ${PKI_web_vhost_cert} ]]`. Certificates are
# not executable, so that test was true of every certificate ever written and
# the leaf was re-signed on EVERY installer run. It was harmless only while one
# key did every job; the moment the signing key can be offline, or a client has
# pinned something, re-issuing on an upgrade is a broken server.
#
# That one is fixed -- the leaf now re-issues only when its SAN set or its
# signing CA actually changed, tracked by the .webLeaf.sans stamp. The class was
# never guarded, which is what this is for: it runs the PKI creation path twice
# against the same tree and asserts that nothing long-lived changed. A second
# run must be a no-op for anything a client, a firmware, or an offline key
# depends on.
#
# What "long-lived" means here, and why each one matters if it churns:
#
#   root CA            fog-client pins it as ca.cert.der; re-minting orphans
#                      every registered client at once.
#   comm key + leaf    every client encrypts to that public half.
#   web intermediate   re-minting it invalidates the leaf beneath it.
#   web leaf key       a new key needs a new certificate, so this drags the
#                      leaf with it.
#   Secure Boot CA     enrolled in firmware, per machine, by hand.
#   SB signing leaf    what signed the kernels already deployed.
#   PK / KEK           written into firmware in Setup Mode.
#   MOK                enrolled through MokManager behind physical presence.
#
# Deliberately drives the PKI helpers directly rather than calling
# createSSLCA(): that function also mints the vhost, writes under /etc and
# restarts services. The sequence below mirrors the order createSSLCA calls
# them in. If it drifts, this test still answers the question it exists to
# answer -- "does a second pass re-issue anything" -- because each helper owns
# its own guard.
#
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

# Stubs. errorStat exits the whole script on a non-zero status, which in a test
# run would look like a pass (no failures printed) rather than a failure.
dots() { :; }
errorStat() { :; }
setSELinuxContext() { :; }

# --- a self-contained install tree -------------------------------------------
fogprogramdir="$WORK/opt/fog"
snapindir="$WORK/opt/fog/snapins"
PKI_client_cert_dir="$snapindir/ssl"
webdirdest="$WORK/var/www/fog"
DB_backup_path="$WORK/backups"
version="1.6.0-test"
apacheuser="$(id -un)"
NET_hostname="fogserver.test.local"
NET_fog_server_ip="10.0.0.5"
PKI_san_ip_addresses="10.0.0.5"
certip="${NET_fog_server_ip}"
# Set so _collectPkiNames does not stop to prompt for them.
PKI_allowed_domain_names="test.local"
PKI_san_dns_names=""
PKI_sb_enabled=1
recreateKeys=no
recreateCA=no
externalca=no
mkdir -p "${PKI_client_cert_dir}/CA" "$webdirdest" "${DB_backup_path}"

# One pass of the PKI creation path, in createSSLCA()'s own order.
pki_pass() {
    _resolveSslPath
    _resolveRootCA

    local sanentries="IP.1 = ${NET_fog_server_ip}"
    _relocatePkiConf
    cat > "$(_pkiConfDir)/ca.cnf" << EOF
[v3_ca]
subjectAltName = @alt_names
[alt_names]
$sanentries
DNS.1 = ${NET_hostname}
EOF
    cat > "$(_pkiConfDir)/req.cnf" << EOF
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
DNS.1 = ${NET_hostname}
EOF

    _separateCommKey
    _resolveClientLeafPaths
    if [[ ! -e ${PKI_client_encrypt_key} || ! -e $(_pkiZoneDir client)/leaf/fog.csr ]]; then
        openssl genrsa -out "${PKI_client_encrypt_key}" 4096 >>$error_log 2>&1
        openssl req -new -sha512 -key "${PKI_client_encrypt_key}" -out "$(_pkiZoneDir client)/leaf/fog.csr" \
            -config "$(_pkiConfDir)/req.cnf" >>$error_log 2>&1
    fi
    _createCommLeaf >/dev/null 2>&1

    createWebIntermediateCA
    _resolveWebLeafPaths
    _createWebLeaf >/dev/null 2>&1
    _writeWebChainFiles

    createSecureBootIntermediateCA >/dev/null 2>&1
    _ensureSecureBootKeys >/dev/null 2>&1
    _ensureSecureBootPlatformKeys >/dev/null 2>&1
    return 0
}

# --- the artefacts a second run must not touch -------------------------------
artefacts() {
    printf '%s\n' \
        "${PKI_client_cert_dir}/CA/.fogCA.pem|root CA certificate" \
        "$fogprogramdir/pki/root/ca/.fogCA.key|root CA private key" \
        "${PKI_client_cert_dir}/.srvprivate.key|client communication key" \
        "${PKI_client_cert_dir}/.srvpublic.crt|client communication leaf" \
        "$fogprogramdir/pki/web/ca/.fogWebCA.pem|web intermediate CA" \
        "$fogprogramdir/pki/web/ca/.fogWebCA.key|web intermediate CA key" \
        "$fogprogramdir/pki/web/leaf/.webLeaf.key|web leaf private key" \
        "$fogprogramdir/pki/web/leaf/.webLeaf.pem|web leaf certificate" \
        "$fogprogramdir/pki/secureboot/ca/.fogSBCA.pem|Secure Boot CA" \
        "$fogprogramdir/pki/secureboot/ca/.fogSBCA.key|Secure Boot CA key" \
        "$fogprogramdir/pki/secureboot/leaf/sign.key|Secure Boot signing key" \
        "$fogprogramdir/pki/secureboot/leaf/sign.pem|Secure Boot signing certificate" \
        "$fogprogramdir/pki/secureboot/PK.key|Platform Key" \
        "$fogprogramdir/pki/secureboot/PK.pem|Platform Key certificate" \
        "$fogprogramdir/pki/secureboot/KEK.key|Key Exchange Key" \
        "$fogprogramdir/pki/secureboot/KEK.pem|Key Exchange Key certificate"
    # Not listed: the flat MOK.key/MOK.pem pair. It is the FALLBACK for a server
    # with no Secure Boot CA -- _ensureSecureBootKeys returns early here because
    # createSecureBootIntermediateCA has already pointed ${PKI_sb_codesign_key} at the
    # signing leaf -- so it is genuinely absent in this configuration rather
    # than missing. sign.key/sign.pem above are what this install enrolls.
}

sumof() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" 2>/dev/null | awk '{print $1}'
    else
        openssl dgst -sha256 "$1" 2>/dev/null | awk '{print $NF}'
    fi
}

echo "pki idempotence:"

pki_pass
first_run_log_size=$(wc -c < "$error_log")

# Snapshot everything that exists after the first pass. Anything absent is
# named rather than passed over: this driver is a stand-in for createSSLCA, so
# an artefact that stopped being produced would otherwise silently drop out of
# the comparison and the test would keep reporting a pass over fewer files.
declare -A BEFORE=()
present=0
absent=""
while IFS='|' read -r path label; do
    if [[ -f $path ]]; then
        BEFORE["$path"]=$(sumof "$path")
        present=$((present + 1))
    else
        absent="${absent}${absent:+, }${label}"
    fi
done < <(artefacts)
[[ -n $absent ]] && printf '  note  not produced by this driver: %s\n' "$absent"

if [[ $present -eq 0 ]]; then
    bad "the first pass created no PKI artefacts at all -- check $error_log"
    printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
    exit 1
fi
ok "first pass created $present PKI artefact(s)"

# The whole point: run it again against the same tree.
pki_pass

changed=0
while IFS='|' read -r path label; do
    [[ -n ${BEFORE[$path]:-} ]] || continue
    if [[ ! -f $path ]]; then
        bad "$label disappeared on the second run"
        changed=$((changed + 1))
        continue
    fi
    if [[ "$(sumof "$path")" != "${BEFORE[$path]}" ]]; then
        bad "$label was REGENERATED on the second run ($path)"
        changed=$((changed + 1))
    fi
done < <(artefacts)

[[ $changed -eq 0 ]] && ok "no long-lived artefact changed on the second run"

# The stamp is what makes the web leaf's re-issue decision, so prove it is
# doing the work rather than the leaf surviving by accident: change the name
# set, and the leaf MUST be re-issued.
stamp="$fogprogramdir/pki/web/leaf/.webLeaf.sans"
if [[ -f $stamp ]]; then
    ok "the web leaf SAN stamp exists"
    leafbefore=$(sumof "$fogprogramdir/pki/web/leaf/.webLeaf.pem")
    NET_hostname="renamed.test.local"
    pki_pass
    if [[ "$(sumof "$fogprogramdir/pki/web/leaf/.webLeaf.pem")" != "$leafbefore" ]]; then
        ok "renaming the server DOES re-issue the web leaf"
    else
        bad "renaming the server did not re-issue the web leaf -- the stamp is not tracking the name set"
    fi
    # ...and nothing above the leaf moved with it.
    for ca in \
        "${PKI_client_cert_dir}/CA/.fogCA.pem" \
        "$fogprogramdir/pki/web/ca/.fogWebCA.pem" \
        "${PKI_client_cert_dir}/.srvpublic.crt"; do
        [[ -n ${BEFORE[$ca]:-} ]] || continue
        if [[ "$(sumof "$ca")" != "${BEFORE[$ca]}" ]]; then
            bad "re-issuing the leaf also changed $ca"
        fi
    done
    ok "re-issuing the leaf left the CAs and the comm leaf alone"
else
    bad "the web leaf SAN stamp was never written ($stamp)"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || {
    echo "  (openssl errors, if any, are in $error_log -- first pass wrote ${first_run_log_size} bytes)"
    exit 1
}
exit 0
