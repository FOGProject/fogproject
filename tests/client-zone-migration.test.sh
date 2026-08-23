#!/bin/bash
#
# Guards the client-communication keypair's move into its own PKI zone.
#
#   tests/client-zone-migration.test.sh
#
# The keypair used to live directly in ${PKI_client_cert_dir}, i.e.
# $snapindir/ssl. So "change the snapin SSL certificates" and "replace the one
# keypair every registered fog-client pins" were the same operation on the same
# directory -- and the second is invisible until hosts stop checking in, per
# host, with nothing naming the file. The real files now live in
# pki/client/leaf and a symlink per file stays behind at the canonical names,
# because FOGBase::_decryptCheck() builds `<sslpath>/.srvprivate.key` with the
# filename hardcoded, taking the directory from the storage-node DB record
# rather than from .fogsettings.
#
# Four things have to hold, and three of them fail silently:
#
#   1. The key's CONTENTS never change. Every registered client encrypts to its
#      public half, so a migration that regenerates rather than moves locks out
#      the entire fleet at once.
#   2. _separateCommKey must not treat FOG's own compat link as an admin's
#      symlink to their web key. It dereferences and copies such links, so
#      without a zone test it copies the keypair back into $snapindir/ssl on
#      every single run -- silently undoing the move.
#   3. pki/client/leaf must be TRAVERSABLE by the web user. Every other zone's
#      leaf/ is 0700 root:root; copying that here makes certDecrypt() fail as
#      `Private key not readable`, per client.
#   4. The snapin material next to it must be untouched, or the move
#      accomplished nothing.
#
# Drives the helpers directly rather than calling createSSLCA(), as
# tests/pki-idempotence.test.sh and tests/client-repin-warning.test.sh do, and
# in the same order createSSLCA calls them.
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
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

error_log="$WORK/error.log"
: > "$error_log"

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
dots() { :; }
errorStat() { :; }

apacheuser="$(id -un)"

# A fresh tree per scenario, so one case cannot leak into the next.
newtree() {
    fogprogramdir="$WORK/$1/opt/fog"
    snapindir="$fogprogramdir/snapins"
    PKI_client_cert_dir="$snapindir/ssl"
    PKI_client_encrypt_key=""
    PKI_client_encrypt_cert=""
    mkdir -p "${PKI_client_cert_dir}/CA"
    ZONE="$fogprogramdir/pki/client/leaf"
}
# Stand-ins for the snapin SSL material and shared config that must NOT move.
seedNeighbours() {
    echo "shared-req-config"   > "${PKI_client_cert_dir}/req.cnf"
    echo "shared-ca-config"    > "${PKI_client_cert_dir}/ca.cnf"
    echo "client-csr"          > "${PKI_client_cert_dir}/fog.csr"
    echo "dhparams"            > "${PKI_client_cert_dir}/dhparam.pem"
    echo "legacy-ca"           > "${PKI_client_cert_dir}/CA/.fogCA.pem"
    echo "an-admins-snapin-cert" > "${PKI_client_cert_dir}/snapin-upload.crt"
}
neighboursIntact() {
    local f bad=""
    for f in req.cnf ca.cnf fog.csr dhparam.pem CA/.fogCA.pem snapin-upload.crt; do
        [[ -f "${PKI_client_cert_dir}/${f}" && ! -L "${PKI_client_cert_dir}/${f}" ]] \
            || bad="$bad $f"
    done
    echo "$bad"
}
genkey() { openssl genrsa -out "$1" 2048 >>$error_log 2>&1; }

echo "client zone migration:"

# --- A. a fresh install ------------------------------------------------------
newtree fresh
seedNeighbours
_resolveClientLeafPaths
is "${PKI_client_encrypt_key}"  "$ZONE/.srvprivate.key" "fresh: the key record names the client zone"
is "${PKI_client_encrypt_cert}" "$ZONE/.srvpublic.crt"  "fresh: the cert record names the client zone"
genkey "${PKI_client_encrypt_key}"
echo "cert-bytes" > "${PKI_client_encrypt_cert}"
_linkClientLeafCompat
[[ -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "fresh: the canonical key name is a symlink" \
    || bad "fresh: the canonical key name is not a symlink"
is "$(readlink -f "${PKI_client_cert_dir}/.srvprivate.key")" \
   "$(readlink -f "$ZONE/.srvprivate.key")" \
   "fresh: the canonical key name resolves into the zone"
is "$(readlink -f "${PKI_client_cert_dir}/.srvpublic.crt")" \
   "$(readlink -f "$ZONE/.srvpublic.crt")" \
   "fresh: the canonical cert name resolves into the zone"
# $snapindir/ssl itself stays a real directory. A directory symlink would put
# snapin uploads straight back beside the keypair.
[[ -d ${PKI_client_cert_dir} && ! -L ${PKI_client_cert_dir} ]] \
    && ok "fresh: \$snapindir/ssl is still a real directory, not a symlink" \
    || bad "fresh: \$snapindir/ssl became a symlink"

# --- B. permissions: the trap that fails per-client, silently ----------------
mode=$(stat -c '%a' "$ZONE")
is "$mode" "710" "the leaf dir is 0710 (traversable by the web user)"
[[ $mode != 700 ]] \
    && ok "...and NOT 0700, which is what would break certDecrypt()" \
    || bad "the leaf dir is 0700 -- the web user cannot traverse it"
is "$(stat -c '%U:%G' "$ZONE")" "root:${apacheuser}" "the leaf dir is root:\${apacheuser}"
# 0710 over 0750 on purpose: the group digit is 1, so the web user can traverse
# INTO the directory but cannot list what is in it.
is "$(stat -c '%a' "$ZONE" | cut -c2)" "1" "group bit is execute-only: traverse, no listing"

# --- C. an upgrade from the old layout --------------------------------------
# The keypair sitting as REAL files in $snapindir/ssl, which is every existing
# server. The bytes must survive: a regenerated key locks out the whole fleet.
newtree upgrade
seedNeighbours
genkey "${PKI_client_cert_dir}/.srvprivate.key"
echo "the-deployed-cert" > "${PKI_client_cert_dir}/.srvpublic.crt"
keysum=$(sha256sum < "${PKI_client_cert_dir}/.srvprivate.key")
_separateCommKey
_resolveClientLeafPaths
_linkClientLeafCompat
[[ -f "$ZONE/.srvprivate.key" && ! -L "$ZONE/.srvprivate.key" ]] \
    && ok "upgrade: the real key is now in the zone" \
    || bad "upgrade: the real key did not move into the zone"
is "$(sha256sum < "$ZONE/.srvprivate.key")" "$keysum" \
   "upgrade: the key bytes are IDENTICAL (a re-key would orphan every client)"
is "$(cat "$ZONE/.srvpublic.crt")" "the-deployed-cert" \
   "upgrade: the deployed certificate moved with it"
[[ -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "upgrade: a compat symlink was left at the canonical name" \
    || bad "upgrade: no compat symlink at the canonical name"
is "$(readlink -f "${PKI_client_cert_dir}/.srvprivate.key")" \
   "$(readlink -f "$ZONE/.srvprivate.key")" \
   "upgrade: the canonical name still resolves to the key"
is "$(neighboursIntact)" "" "upgrade: every neighbouring file is untouched"

# --- D. THE TRAP: _separateCommKey must not eat its own compat link ---------
# _separateCommKey dereferences a symlink at the canonical name and copies the
# target over it. FOG's own compat link IS such a symlink, so without a zone
# test it writes a real duplicate of the client PRIVATE KEY back into
# $snapindir/ssl -- the directory the snapin replicator walks -- and announces a
# separation that did not happen.
#
# Asserted immediately after _separateCommKey and BEFORE the relink. That
# ordering is the whole point: _linkClientLeafCompat puts the symlink back later
# in the same run, so a check at the end of a pass sees nothing wrong and passes
# with the guard removed -- which is exactly what an earlier version of this
# test did. The duplicate is transient only if the run reaches the relink, and
# errorStat exits in between.
_separateCommKey
[[ -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "_separateCommKey leaves FOG's own compat link alone" \
    || bad "_separateCommKey dereferenced its own link -- a real private key is back in the snapin dir"
[[ ! -f "${PKI_client_cert_dir}/.srvprivate.key" || -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "...so no duplicate private key sits beside the snapin material" \
    || bad "a real duplicate of the client private key is in the snapin dir"
_resolveClientLeafPaths
_linkClientLeafCompat
[[ -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "second pass: the canonical name is still a symlink" \
    || bad "second pass: the canonical name is not a symlink"
is "$(readlink -f "${PKI_client_cert_dir}/.srvprivate.key")" \
   "$(readlink -f "$ZONE/.srvprivate.key")" \
   "second pass: it still resolves into the zone"
is "$(sha256sum < "$ZONE/.srvprivate.key")" "$keysum" \
   "second pass: the key bytes are unchanged"
# A third, for the same reason: the loop this guards against is per-run.
_separateCommKey; _resolveClientLeafPaths; _linkClientLeafCompat
[[ -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "third pass: still stable" || bad "third pass: the layout drifted"

# --- E. the case _separateCommKey actually exists for ------------------------
# An admin who relocated the web key has the canonical name pointing at THEIR
# file, which under the historic layout was the web key and the comm key at
# once. That must still be separated -- an ACME renewal writing their file must
# not change what certDecrypt() reads -- and then migrated like any real file.
newtree adminlink
seedNeighbours
mkdir -p "$WORK/adminlink/etc/letsencrypt"
genkey "$WORK/adminlink/etc/letsencrypt/privkey.pem"
acmesum=$(sha256sum < "$WORK/adminlink/etc/letsencrypt/privkey.pem")
ln -s "$WORK/adminlink/etc/letsencrypt/privkey.pem" "${PKI_client_cert_dir}/.srvprivate.key"
_separateCommKey
[[ -f "${PKI_client_cert_dir}/.srvprivate.key" && ! -L "${PKI_client_cert_dir}/.srvprivate.key" ]] \
    && ok "admin link: the comm key was separated into a real file" \
    || bad "admin link: the symlink to the admin's web key was not separated"
_resolveClientLeafPaths
_linkClientLeafCompat
is "$(sha256sum < "$ZONE/.srvprivate.key")" "$acmesum" \
   "admin link: the separated material kept the admin's key bytes"
# And the admin's own file is left where it is -- FOG copies, never moves it.
[[ -f "$WORK/adminlink/etc/letsencrypt/privkey.pem" ]] \
    && ok "admin link: the admin's own key file is untouched" \
    || bad "admin link: FOG moved or removed the admin's key"

# --- F. the retired pki/root/leaf links -------------------------------------
# They pointed at the keypair while it lived in $snapindir/ssl. With the keypair
# in its own zone they would be a second set of links to the first.
newtree rootleaf
seedNeighbours
genkey "${PKI_client_cert_dir}/.srvprivate.key"
echo c > "${PKI_client_cert_dir}/.srvpublic.crt"
mkdir -p "$fogprogramdir/pki/root/leaf"
ln -s "${PKI_client_cert_dir}/.srvprivate.key" "$fogprogramdir/pki/root/leaf/.srvprivate.key"
ln -s "${PKI_client_cert_dir}/.srvpublic.crt" "$fogprogramdir/pki/root/leaf/.srvpublic.crt"
_separateCommKey; _resolveClientLeafPaths
_retireRootLeafLinks
[[ ! -e "$fogprogramdir/pki/root/leaf" ]] \
    && ok "root zone: the stale discoverability links and dir are gone" \
    || bad "root zone: pki/root/leaf survived"
# A real file there is somebody else's, and must survive.
newtree rootleafreal
mkdir -p "$fogprogramdir/pki/root/leaf"
echo "not-a-link" > "$fogprogramdir/pki/root/leaf/.srvprivate.key"
_retireRootLeafLinks
is "$(cat "$fogprogramdir/pki/root/leaf/.srvprivate.key" 2>/dev/null)" "not-a-link" \
   "root zone: a REAL file there is never unlinked"

# --- G. the zone token exists at all ----------------------------------------
is "$(_pkiZoneDir client)" "$fogprogramdir/pki/client" "_pkiZoneDir knows the client zone"

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
