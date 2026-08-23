#!/bin/bash
#
# Guards the loudness of a client-communication certificate change.
#
#   tests/client-repin-warning.test.sh
#
# -K/--recreate-keys and -C/--recreate-CA regenerate ${PKI_client_cert_dir}/.srvprivate.key.
# That is the key FOGBase::certDecrypt() opens on every fog-client handshake, so
# replacing it invalidates every registered client at once -- and it used to say
# nothing at all. The symptom arrives days later as hosts failing to authorize,
# with nothing on the server connecting it to the install run.
#
# Two things are pinned here, because the warning is worthless without both:
#
#   1. Regenerating the key re-issues the comm leaf. _createCommLeaf() keeps
#      whatever is already at .srvpublic.crt, so a -K run used to leave the OLD
#      certificate in place over the NEW key -- a pairing under which nothing
#      can authenticate and no re-pin helps.
#   2. _warnClientRepin() prints, in terms an admin can act on, that every
#      registered fog-client has to be reinstalled or re-pinned -- and stays
#      silent on an ordinary upgrade, or the warning stops meaning anything.
#
# Warn and proceed, never refuse: -K is a legitimate thing to ask for. So the
# absence of an exit is asserted too.
#
# Drives the comm-key section of createSSLCA() directly rather than calling it,
# for the same reason tests/pki-idempotence.test.sh does: that function also
# mints the vhost, writes under /etc and restarts services.
#
# Needs openssl. No network, no root, no install.
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

# errorStat exits the whole script on a non-zero status, which inside a test run
# reads as a pass (no failures printed) rather than as a failure.
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
PKI_allowed_domain_names="test.local"
PKI_san_dns_names=""
recreateKeys=no
recreateCA=no
mkdir -p "${PKI_client_cert_dir}/CA" "$webdirdest/management/other/ssl" "${DB_backup_path}"

# The comm-key half of createSSLCA(), in its own order, plus the publish step
# that hands the certificate to clients. Everything below the comm leaf (web
# zone, Secure Boot, vhost) is irrelevant to this question and left out.
comm_pass() {
    _resolveSslPath
    _resolveRootCA >/dev/null 2>&1

    local sanentries="IP.1 = ${NET_fog_server_ip}"
    _relocatePkiConf
    cat > "$(_pkiConfDir)/ca.cnf" << CNF
[v3_ca]
subjectAltName = @alt_names
[alt_names]
$sanentries
DNS.1 = ${NET_hostname}
CNF
    cat > "$(_pkiConfDir)/req.cnf" << CNF
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
CNF

    _separateCommKey
    _resolveClientLeafPaths
    if [[ $recreateKeys == yes || $recreateCA == yes || ! -e ${PKI_client_encrypt_key} || ! -e $(_pkiZoneDir client)/leaf/fog.csr ]]; then
        if [[ ! -e ${PKI_client_encrypt_key} || $recreateKeys == yes || $recreateCA == yes ]]; then
            openssl genrsa -out "${PKI_client_encrypt_key}" 4096 >>$error_log 2>&1
            if [[ $recreateKeys == yes || $recreateCA == yes ]]; then
                _discardOrphanedCommLeaf
            fi
        fi
        openssl req -new -sha512 -key "${PKI_client_encrypt_key}" -out "$(_pkiZoneDir client)/leaf/fog.csr" \
            -config "$(_pkiConfDir)/req.cnf" >>$error_log 2>&1
    fi
    _createCommLeaf >/dev/null 2>&1
    _warnClientRepin
    _linkClientLeafCompat
    # The publish at the end of createSSLCA(), which is what makes the file this
    # run compared against the "deployed copy" for the next one.
    mkdir -p "$webdirdest/management/other/ssl" >>$error_log 2>&1
    cp -f "${PKI_client_encrypt_cert}" "$webdirdest/management/other/ssl/srvpublic.crt" >>$error_log 2>&1
    return 0
}

fp() { openssl x509 -noout -fingerprint -sha256 -in "$1" 2>/dev/null | sed 's/^.*=//'; }

echo "client re-pin warning:"

# --- 1. first install: nothing was ever published, so nothing to re-pin -------
out=$(comm_pass)
if [[ ! -f ${PKI_client_cert_dir}/.srvpublic.crt ]]; then
    bad "the first pass did not produce a comm leaf at all -- check $error_log"
    printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
    exit 1
fi
ok "the first pass produced a comm leaf"
if [[ $out == *"MUST BE REINSTALLED OR RE-PINNED"* ]]; then
    bad "a first install warned about re-pinning clients that cannot exist yet"
else
    ok "a first install is silent"
fi

deployed_fp=$(fp "$webdirdest/management/other/ssl/srvpublic.crt")
key_before=$(openssl rsa -noout -modulus -in "${PKI_client_cert_dir}/.srvprivate.key" 2>/dev/null | openssl md5)

# --- 2. an ordinary upgrade must stay silent ---------------------------------
# configureHttpd rm -rf's $webdirdest before createSSLCA and backs it up first,
# so reproduce that: the deployed copy is reachable only from the backup, which
# is the path _warnClientRepin has to fall back to.
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl"
cp -f "$webdirdest/management/other/ssl/srvpublic.crt" \
      "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl/srvpublic.crt"
rm -rf "$webdirdest"

out=$(comm_pass)
if [[ $out == *"MUST BE REINSTALLED OR RE-PINNED"* ]]; then
    bad "an ordinary upgrade warned about re-pinning"
else
    ok "an ordinary upgrade is silent"
fi
if [[ "$(fp "$webdirdest/management/other/ssl/srvpublic.crt")" == "$deployed_fp" ]]; then
    ok "an ordinary upgrade republishes the same certificate"
else
    bad "an ordinary upgrade changed the published comm certificate"
fi

# --- 3. -K against that existing install -------------------------------------
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl"
cp -f "$webdirdest/management/other/ssl/srvpublic.crt" \
      "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl/srvpublic.crt"
rm -rf "$webdirdest"

recreateKeys=yes
out=$(comm_pass)
status=$?
recreateKeys=no

if [[ $status -eq 0 ]]; then
    ok "-K warns and PROCEEDS -- it does not refuse"
else
    bad "-K aborted (exit $status) instead of warning and proceeding"
fi

key_after=$(openssl rsa -noout -modulus -in "${PKI_client_cert_dir}/.srvprivate.key" 2>/dev/null | openssl md5)
if [[ $key_after != "$key_before" ]]; then
    ok "-K regenerated the client communication key"
else
    bad "-K did not regenerate the client communication key -- the fixture is wrong"
fi

# The bug underneath the missing warning: the leaf has to follow the key, or the
# published certificate's public half pairs with a key this server threw away.
certmod=$(openssl x509 -noout -modulus -in "${PKI_client_cert_dir}/.srvpublic.crt" 2>/dev/null | openssl md5)
if [[ -n $certmod && $certmod == "$key_after" ]]; then
    ok "-K re-issued the comm leaf so it pairs with the new key"
else
    bad "-K left a comm leaf that does NOT pair with the new key -- no client can authenticate"
fi

if [[ $out == *"MUST BE REINSTALLED OR RE-PINNED"* ]]; then
    ok "-K says every registered fog-client must be reinstalled or re-pinned"
else
    bad "-K changed the comm certificate silently"
    printf '%s\n' "$out" | sed 's/^/        /'
fi
if [[ $out == *"client communication certificate has CHANGED"* ]]; then
    ok "-K names what changed"
else
    bad "-K did not name the client communication certificate"
fi

if [[ "$(fp "$webdirdest/management/other/ssl/srvpublic.crt")" != "$deployed_fp" ]]; then
    ok "-K published a different certificate (so the warning was true)"
else
    bad "-K warned but republished the same certificate"
fi

# --- 4. and it settles: the run after -K is quiet again ----------------------
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl"
cp -f "$webdirdest/management/other/ssl/srvpublic.crt" \
      "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl/srvpublic.crt"
rm -rf "$webdirdest"

out=$(comm_pass)
if [[ $out == *"MUST BE REINSTALLED OR RE-PINNED"* ]]; then
    bad "the run after -K warned again -- the warning does not clear"
else
    ok "the run after -K is silent again"
fi

# --- 5. a MISSING key is damage, not intent: keep the certificate ------------
# The genrsa branch also fires on `! -e .srvprivate.key`, with no flag passed at
# all -- a bad restore or a lost disk. There the surviving certificate is the
# admin's way back (put the old key next to it and the server is whole), so it
# must NOT be deleted. _createCommLeaf()'s own mismatch warning covers this.
mkdir -p "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl"
cp -f "$webdirdest/management/other/ssl/srvpublic.crt" \
      "${DB_backup_path}/fog_web_${version}.BACKUP/management/other/ssl/srvpublic.crt"
rm -rf "$webdirdest"

kept_fp=$(fp "${PKI_client_cert_dir}/.srvpublic.crt")
rm -f "${PKI_client_cert_dir}/.srvprivate.key"
out=$(comm_pass)

if [[ -f ${PKI_client_cert_dir}/.srvpublic.crt && "$(fp "${PKI_client_cert_dir}/.srvpublic.crt")" == "$kept_fp" ]]; then
    ok "a missing key does NOT delete the surviving certificate"
else
    bad "a missing key deleted the certificate -- the admin's route back (restore the old key) is gone"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || {
    echo "  (openssl errors, if any, are in $error_log)"
    exit 1
}
exit 0
