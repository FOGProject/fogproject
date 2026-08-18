#!/bin/bash
#
# Guards the served TLS chain against feeding on its own output.
#
#   tests/web-chain-feedback.test.sh
#
# _writeWebChainFiles() assembles what the web server sends: the leaf from
# $sslpubcert, then the intermediates out of $sslcachain, into
# pki/web/leaf/.webFullChain.pem. The vhost is pointed at that bundle.
#
# Which meant the bundle was reachable as an INPUT. createSSLCA() adopts
# whatever certificate path the live vhost names, via
# _detectExternalCertManagement() signal 2 -- "the vhost serves a leaf from
# outside FOG's own paths, so the admin must manage it". That test only knew
# about $sslpath, and the zoned PKI had long since moved FOG's own web leaf to
# $fogprogramdir/pki/web/leaf. So on an ordinary FOG-issued HTTPS server the
# signal fired on FOG's own file, recorded acmeLeaf=yes, and set
# $sslpubcert=.webFullChain.pem. From then on
#
#     cat "$sslpubcert" "$chainonly" > fullchain.new
#
# appended one more copy of the intermediate on every install. Observed live at
# fourteen certificates: leaf + 13 identical "CN=FOG Web CA".
#
# Browsers tolerate that. iPXE does not -- it validates pairwise from the
# trusted root upwards (crypto/x509.c), so copy 2 is checked against copy 1 as
# its issuer, fails x509_check_issuer, and every HTTPS netboot dies at
# boot.php. Nothing server-side says why.
#
# Three fixes, all pinned here: the detector knows the PKI tree is FOG's, the
# assembler reads only the leaf out of $sslpubcert, and _resolveWebLeafPaths()
# repoints an $sslpubcert that names the derived bundle -- while leaving an
# admin's own fullchain.pem alone, because that is a supported value.
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

# --- fixture: root -> intermediate -> leaf, the shape FOG issues -------------
fogprogramdir="$WORK/opt/fog"
sslpath="$WORK/opt/fog/snapins/ssl"
webdirdest="$WORK/var/www/fog"
leafdir="$fogprogramdir/pki/web/leaf"
cadir="$fogprogramdir/pki/web/ca"
mkdir -p "$leafdir" "$cadir" "$sslpath" "$webdirdest/management/other/ssl"

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

# root+intermediate, the shape createWebIntermediateCA writes.
sslcachain="$cadir/.fogWebCAchain.pem"
cat "$cadir/.fogWebCA.pem" "$cadir/root.pem" > "$sslcachain"

sslprivkey="$leafdir/.webLeaf.key"
fullchain="$leafdir/.webFullChain.pem"

ncerts() { grep -c 'BEGIN CERTIFICATE' "$1" 2>/dev/null; }

echo "web chain feedback:"

# --- the assembler, run over and over ----------------------------------------
sslpubcert="$leafdir/.webLeaf.pem"
_writeWebChainFiles
is "$(ncerts "$fullchain")" "2" "a normal run assembles leaf + intermediate"

# THE REGRESSION. $sslpubcert names the bundle this function writes, which is
# what createSSLCA() adopted from the vhost on every FOG-issued HTTPS server.
sslpubcert="$fullchain"
for _ in 1 2 3 4 5; do _writeWebChainFiles; done
is "$(ncerts "$fullchain")" "2" "five runs against its own output stay at leaf + intermediate"
is "$(openssl x509 -in "$fullchain" -noout -subject 2>/dev/null)" \
   "subject=CN=fogserver" "the leaf is still first in the bundle"

# An already-grown bundle collapses rather than needing a migration.
{ openssl x509 -in "$leafdir/.webLeaf.pem"
  for _ in 1 2 3 4 5 6 7 8 9 10 11 12 13; do openssl x509 -in "$cadir/.fogWebCA.pem"; done
} > "$fullchain" 2>/dev/null
is "$(ncerts "$fullchain")" "14" "fixture reproduces the live 14-certificate bundle"
_writeWebChainFiles
is "$(ncerts "$fullchain")" "2" "the next run collapses a grown bundle back"

# --- the cause: FOG's own PKI tree is not evidence of an external cert -------
etcconf="$WORK/fog.conf"
rootCAPem="$cadir/root.pem"
sslpubcert="$leafdir/.webLeaf.pem"

echo "    ssl_certificate ${fullchain};" > "$etcconf"
_detectExternalCertManagement >/dev/null 2>&1
is "$?" "1" "a vhost serving FOG's own pki/ leaf is not external"

echo "    ssl_certificate /etc/letsencrypt/live/fog/fullchain.pem;" > "$etcconf"
_detectExternalCertManagement >/dev/null 2>&1
is "$?" "0" "a vhost serving someone else's leaf still is external"

# --- and the input stops naming the output ----------------------------------
sslpubcert="$fullchain"
_resolveWebLeafPaths
is "$sslpubcert" "$leafdir/.webLeaf.pem" "an \$sslpubcert naming the derived bundle is repointed"

sslpubcert="/etc/letsencrypt/live/fog/fullchain.pem"
_resolveWebLeafPaths
is "$sslpubcert" "/etc/letsencrypt/live/fog/fullchain.pem" \
    "an ACME client's own fullchain is left alone"

echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
