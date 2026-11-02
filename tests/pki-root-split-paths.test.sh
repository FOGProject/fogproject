#!/bin/bash
#
# Pins the web-certificate checks against the REAL PKI layout (GH-2026).
#
#   tests/pki-root-split-paths.test.sh
#
# GH-1121 moved FOG's PKI tree to ${PKI_root_dir} (/etc/fog/pki) and left
# $fogprogramdir/pki (/opt/fog/pki) as a symlink to it. So one tree has two
# names. Every other test here points ${PKI_root_dir} at $fogprogramdir/pki,
# which makes the two names the same string, and that hid the bug:
#
#   - _detectExternalCertManagement() signal 2 knew only the /opt name, so a
#     vhost naming the /etc path was "managed outside FOG" on every run.
#   - On nginx, whose vhost serves .webFullChain.pem, createSSLCA() then made
#     .webLeaf.pem a symlink to that bundle, and nothing undid it.
#   - _resolveWebLeafPaths() compared paths as strings, so a .fogsettings
#     that spells them /opt/fog/pki/... never matched its repair arms.
#
# This fixture builds the split layout, an Apache vhost, an nginx vhost, and a
# server the old code already hit.
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
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
dots() { :; }
errorStat() { :; }

# --- fixture: the split layout a real install has ----------------------------
# /etc/fog/pki holds the tree; /opt/fog/pki is a symlink to it. The symlink
# also tells _pkiRootDir() the migration already ran.
fogprogramdir="$WORK/opt/fog"
PKI_root_dir="$WORK/etc/fog/pki"
PKI_client_cert_dir="$fogprogramdir/snapins/ssl"
PKI_custom_dir="$WORK/etc/fog/customizations/pki"
webdirdest="$WORK/var/www/fog"
leafdir="$PKI_root_dir/web/leaf"
cadir="$PKI_root_dir/web/ca"
mkdir -p "$leafdir" "$cadir" "$PKI_client_cert_dir" "$webdirdest/management/other/ssl"
ln -s "$PKI_root_dir" "$fogprogramdir/pki"

openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 30 \
    -subj "/CN=FOG Server CA" -keyout "$cadir/root.key" -out "$cadir/root.pem" \
    >/dev/null 2>&1 || { echo "ERROR: fixture root CA failed" >&2; exit 1; }
openssl req -new -nodes -newkey rsa:2048 -sha256 -subj "/CN=FOG Web CA" \
    -keyout "$cadir/.fogWebCA.key" -out "$cadir/int.csr" >/dev/null 2>&1
printf 'basicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n' \
    > "$cadir/int.ext"
openssl x509 -req -in "$cadir/int.csr" -CA "$cadir/root.pem" -CAkey "$cadir/root.key" \
    -CAcreateserial -sha256 -days 30 -extfile "$cadir/int.ext" \
    -out "$cadir/.fogWebCA.pem" >/dev/null 2>&1
openssl req -new -nodes -newkey rsa:2048 -sha256 -subj "/CN=fogserver" \
    -keyout "$leafdir/.webLeaf.key" -out "$leafdir/leaf.csr" >/dev/null 2>&1
openssl x509 -req -in "$leafdir/leaf.csr" -CA "$cadir/.fogWebCA.pem" \
    -CAkey "$cadir/.fogWebCA.key" -CAcreateserial -sha256 -days 30 \
    -out "$leafdir/.webLeaf.pem" >/dev/null 2>&1
cp "$leafdir/.webLeaf.pem" "$WORK/leaf.orig.pem"

PKI_root_ca_cert="$cadir/root.pem"
PKI_web_ca_cert="$cadir/.fogWebCA.pem"
PKI_web_trust_chain="$cadir/.fogWebCAchain.pem"
cat "$cadir/.fogWebCA.pem" "$cadir/root.pem" > "$PKI_web_trust_chain"
PKI_web_vhost_cert="$leafdir/.webLeaf.pem"
PKI_web_vhost_key="$leafdir/.webLeaf.key"
_writeWebChainFiles
fullchain="$leafdir/.webFullChain.pem"
[[ -s $fullchain ]] || { echo "ERROR: fixture fullchain failed" >&2; exit 1; }

etcconf="$WORK/fog.conf"
fp() { openssl x509 -in "$1" -noout -fingerprint -sha256 2>/dev/null; }

echo "pki root split paths:"

# --- signal 2 knows both names for FOG's tree --------------------------------
printf '<VirtualHost *:443>\n    SSLCertificateFile %s\n    SSLCertificateKeyFile %s\n</VirtualHost>\n' \
    "$leafdir/.webLeaf.pem" "$leafdir/.webLeaf.key" > "$etcconf"
_detectExternalCertManagement >/dev/null 2>&1
is "$?" "1" "Apache: a vhost serving FOG's leaf from /etc/fog/pki is not external"

printf 'server {\n    ssl_certificate %s;\n    ssl_certificate_key %s;\n}\n' \
    "$fullchain" "$leafdir/.webLeaf.key" > "$etcconf"
_detectExternalCertManagement >/dev/null 2>&1
is "$?" "1" "nginx: a vhost serving FOG's bundle from /etc/fog/pki is not external"

printf 'server {\n    ssl_certificate %s;\n    ssl_certificate_key %s;\n}\n' \
    "$fogprogramdir/pki/web/leaf/.webFullChain.pem" "$fogprogramdir/pki/web/leaf/.webLeaf.key" > "$etcconf"
_detectExternalCertManagement >/dev/null 2>&1
is "$?" "1" "a vhost still naming the /opt/fog/pki symlink is not external"

# A sibling of the tree is not the tree. The trailing slash is what holds this.
mkdir -p "$WORK/etc/fog/pki-old"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=fogserver" \
    -keyout "$WORK/etc/fog/pki-old/foreign.key" -out "$WORK/etc/fog/pki-old/foreign.pem" \
    >/dev/null 2>&1
printf 'server {\n    ssl_certificate %s;\n}\n' "$WORK/etc/fog/pki-old/foreign.pem" > "$etcconf"
_detectExternalCertManagement >/dev/null 2>&1
is "$?" "0" "a certificate in a sibling of /etc/fog/pki is still external"

# --- _resolveWebLeafPaths accepts the /opt spelling -------------------------
PKI_web_vhost_cert="$fogprogramdir/pki/web/leaf/.webFullChain.pem"
_resolveWebLeafPaths
is "$PKI_web_vhost_cert" "$leafdir/.webLeaf.pem" \
    "a setting naming the bundle by its /opt spelling is repointed at the leaf"

PKI_web_vhost_key="$fogprogramdir/pki/web/.webLeaf.key"
_resolveWebLeafPaths
is "$PKI_web_vhost_key" "$leafdir/.webLeaf.key" \
    "a setting naming the flat key by its /opt spelling is repointed into leaf/"

# --- a server the old code already hit (nginx shape) -------------------------
# What `_linkCanonical .webFullChain.pem .webLeaf.pem` left behind.
rm -f "$leafdir/.webLeaf.pem"
ln -s "$fullchain" "$leafdir/.webLeaf.pem"
PKI_web_vhost_cert="$leafdir/.webLeaf.pem"
PKI_web_vhost_key="$leafdir/.webLeaf.key"
_resolveWebLeafPaths
if [[ ! -L $leafdir/.webLeaf.pem && -f $leafdir/.webLeaf.pem ]]; then
    ok "hit server: .webLeaf.pem is a real file again"
else
    bad "hit server: .webLeaf.pem is still a link ($(readlink "$leafdir/.webLeaf.pem"))"
fi
is "$(grep -c 'BEGIN CERTIFICATE' "$leafdir/.webLeaf.pem" 2>/dev/null)" "1" \
    "hit server: the repaired leaf holds one certificate"
is "$(fp "$leafdir/.webLeaf.pem")" "$(fp "$WORK/leaf.orig.pem")" \
    "hit server: the repaired leaf is the original leaf"
_externallyManagedLeaf && r=external || r=fog
is "$r" "fog" "hit server: the leaf is FOG-managed after repair"
_writeWebChainFiles
is "$(grep -c 'BEGIN CERTIFICATE' "$fullchain" 2>/dev/null)" "2" \
    "hit server: the next chain build is leaf + intermediate"

# A second run changes nothing.
before="$(fp "$leafdir/.webLeaf.pem")"
_resolveWebLeafPaths
is "$(fp "$leafdir/.webLeaf.pem")" "$before" "repair is idempotent"

# --- a bundle whose first certificate does not match the key is refused -----
# A re-issue that minted a new key and then failed to sign leaves the OLD leaf
# first in the bundle. Extracting it would pair a leaf with the wrong key.
cp "$leafdir/.webLeaf.key" "$WORK/key.orig"
openssl genrsa -out "$leafdir/.webLeaf.key" 2048 >/dev/null 2>&1
rm -f "$leafdir/.webLeaf.pem"
ln -s "$fullchain" "$leafdir/.webLeaf.pem"
_resolveWebLeafPaths
is "$(readlink "$leafdir/.webLeaf.pem")" "$fullchain" \
    "a leaf that does not match the key is not extracted; the link stays"
[[ -e $leafdir/.webLeaf.pem.new ]] && bad "a refused extract left .webLeaf.pem.new behind" \
    || ok "a refused extract leaves no .new file"
cp "$WORK/key.orig" "$leafdir/.webLeaf.key"

# --- an admin's own symlinks are left alone ---------------------------------
# Both names linked to one outside file. That file is the admin's; copying it
# into the zone would make FOG take it over and re-issue it.
mkdir -p "$WORK/acme"
cp "$fullchain" "$WORK/acme/both.pem"
mv "$fullchain" "$WORK/fullchain.real"
ln -s "$WORK/acme/both.pem" "$fullchain"
rm -f "$leafdir/.webLeaf.pem"
ln -s "$WORK/acme/both.pem" "$leafdir/.webLeaf.pem"
_resolveWebLeafPaths
is "$(readlink "$leafdir/.webLeaf.pem")" "$WORK/acme/both.pem" \
    "both names linked to an admin's file: .webLeaf.pem is not touched"
rm -f "$fullchain"
mv "$WORK/fullchain.real" "$fullchain"

cp "$WORK/leaf.orig.pem" "$WORK/acme/fullchain.pem"
rm -f "$leafdir/.webLeaf.pem"
ln -s "$WORK/acme/fullchain.pem" "$leafdir/.webLeaf.pem"
_resolveWebLeafPaths
is "$(readlink "$leafdir/.webLeaf.pem")" "$WORK/acme/fullchain.pem" \
    "a .webLeaf.pem linked to an admin's certificate is not touched"

echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
