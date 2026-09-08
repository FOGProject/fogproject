#!/bin/bash
#
# Guards the served TLS chain when the leaf was issued OUTSIDE FOG.
#
#   tests/web-chain-external-leaf.test.sh
#
# _writeWebChainFiles() assembles what the web server sends. It used to take
# the intermediates from ${PKI_web_trust_chain} unconditionally, with no test
# that those certificates had anything to do with the leaf in
# ${PKI_web_vhost_cert}. On a server whose leaf comes from an ACME client that
# is two wrong things at once:
#
#   - it SENDS a certificate that cannot be in the path -- "CN=FOG Web CA"
#     alongside a Let's Encrypt leaf
#   - it OMITS the one intermediate that is required
#
# Observed live: a fog server presenting
#
#     0 s:CN=fog.example.com   i:C=US, O=Let's Encrypt, CN=YE2
#     1 s:CN=FOG Web CA        i:CN=FOG Server CA
#
# with the LE intermediate nowhere on the wire. Every client that trusts the
# public root still cannot build a path, so the installer's own self-calls fail
# -- backupDB and the strict updateDB schema deploy both exit curl 60, "unable
# to get local issuer certificate" -- while a browser with the intermediate
# already cached from another site shows a perfectly good padlock. That
# combination is what makes it hard to place.
#
# The fix is one mechanism rather than two: the intermediates are no longer
# "everything in the chain file that is not self-signed", they are the
# certificates that CRYPTOGRAPHICALLY link the leaf upwards, walked one issuer
# at a time out of a candidate pool. FOG's own chain file is in that pool, so a
# FOG-issued leaf is unaffected; an externally managed leaf additionally
# contributes the intermediates its ACME client left on disk.
#
# Name matching alone is not enough and is pinned below: a certificate whose
# subject equals the leaf's issuer but whose key did not sign the leaf must be
# rejected, or a stale CA of the same name silently poisons the chain.
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

# --- fixture helpers ---------------------------------------------------------
# A self-signed CA at $1.key/$1.pem with subject $2.
mkroot() {
    openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 30 \
        -subj "/CN=$2" -keyout "$1.key" -out "$1.pem" >/dev/null 2>&1
}
# A CA at $1.key/$1.pem with subject $2, signed by $3.key/$3.pem.
mkint() {
    openssl req -new -nodes -newkey rsa:2048 -sha256 -subj "/CN=$2" \
        -keyout "$1.key" -out "$1.csr" >/dev/null 2>&1
    printf 'basicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n' \
        > "$1.ext"
    openssl x509 -req -in "$1.csr" -CA "$3.pem" -CAkey "$3.key" -CAcreateserial \
        -sha256 -days 30 -extfile "$1.ext" -out "$1.pem" >/dev/null 2>&1
}
# An end-entity certificate at $1.key/$1.pem with CN $2, signed by $3.key/$3.pem.
mkleaf() {
    openssl req -new -nodes -newkey rsa:2048 -sha256 -subj "/CN=$2" \
        -keyout "$1.key" -out "$1.csr" >/dev/null 2>&1
    openssl x509 -req -in "$1.csr" -CA "$3.pem" -CAkey "$3.key" -CAcreateserial \
        -sha256 -days 30 -out "$1.pem" >/dev/null 2>&1
}
# Subjects of every certificate in $1, one per line. -nameopt RFC2253 pins the
# formatting: openssl's own default is not stable across versions (3.0.13 on
# the CI runner prints "CN = x" with spaces, 3.5.7 prints "CN=x" without), and
# a test that compares against the default passes only on the machine it was
# written on. See tests/web-chain-feedback.test.sh for the run that caught it.
subjects() {
    [[ -s $1 ]] || return 0
    local d; d=$(mktemp -d); local f
    awk -v d="$d" '/-----BEGIN CERTIFICATE-----/{n++} n{print > (d "/c" n ".pem")}' "$1"
    for f in "$d"/c*.pem; do
        [[ -f $f ]] || continue
        openssl x509 -in "$f" -noout -subject -nameopt RFC2253 2>/dev/null | sed 's/^subject=//'
    done
    rm -rf "$d"
}
ncerts() { grep -c 'BEGIN CERTIFICATE' "$1" 2>/dev/null || echo 0; }
has() { subjects "$1" | grep -qx "$2"; }

# --- the two PKIs ------------------------------------------------------------
fogprogramdir="$WORK/opt/fog"
PKI_root_dir="$WORK/etc/fog/pki"
PKI_client_cert_dir="$WORK/opt/fog/snapins/ssl"
webdirdest="$WORK/var/www/fog"
webdir="$PKI_root_dir/web"
leafdir="$webdir/leaf"
cadir="$webdir/ca"
mkdir -p "$leafdir" "$cadir" "$PKI_client_cert_dir" "$webdirdest/management/other/ssl"

# FOG's own: root -> Web CA -> leaf.
mkroot "$cadir/root" "FOG Server CA" || { echo "ERROR: fixture root CA failed" >&2; exit 1; }
mkint "$cadir/.fogWebCA" "FOG Web CA" "$cadir/root"
mkleaf "$leafdir/fogleaf" "fogserver" "$cadir/.fogWebCA"
cp "$leafdir/fogleaf.pem" "$leafdir/.webLeaf.pem"
cp "$leafdir/fogleaf.key" "$leafdir/.webLeaf.key"
PKI_root_ca_cert="$cadir/root.pem"
PKI_web_ca_cert="$cadir/.fogWebCA.pem"
PKI_web_trust_chain="$cadir/.fogWebCAchain.pem"
cat "$cadir/.fogWebCA.pem" "$cadir/root.pem" > "$PKI_web_trust_chain"

# A public PKI standing in for Let's Encrypt: root -> YE2 -> leaf, in an ACME
# client's tree, which is the shape certbot leaves behind.
acme="$WORK/etc/letsencrypt/live/fog"
mkdir -p "$acme"
mkroot "$WORK/pubroot" "Test Root X1"
mkint "$WORK/ye2" "YE2" "$WORK/pubroot"
mkleaf "$acme/cert" "fogserver" "$WORK/ye2"
cp "$WORK/ye2.pem" "$acme/chain.pem"
cat "$acme/cert.pem" "$acme/chain.pem" > "$acme/fullchain.pem"

fullchain="$leafdir/.webFullChain.pem"
chainonly="$leafdir/.webChain.pem"

# A leaf on its own, away from any sibling chain file, for the cases that test
# discovery from somewhere other than the leaf's own directory.
bare="$WORK/etc/pki/tls/certs"
mkdir -p "$bare"
cp "$acme/cert.pem" "$bare/letsencryptfog.pem"
cp "$acme/cert.key" "$bare/letsencryptfog.key"

reset() { rm -f "$fullchain" "$chainonly"; etcconf=""; sslchainonly=""; sslfullchain=""; }

echo "web chain, externally managed leaf:"

# --- 1. the reported bug -----------------------------------------------------
reset
PKI_web_vhost_cert="$acme/cert.pem"
PKI_web_vhost_key="$acme/cert.key"
_writeWebChainFiles
is "$(ncerts "$fullchain")" "2" "an ACME leaf assembles leaf + its own intermediate"
is "$(has "$fullchain" "CN=YE2"; echo $?)" "0" "the intermediate that signed the leaf is served"
is "$(has "$fullchain" "CN=FOG Web CA"; echo $?)" "1" "FOG's Web CA is NOT served next to a foreign leaf"
is "$(subjects "$fullchain" | head -1)" "CN=fogserver" "the leaf is first in the bundle"

# --- 2. the leaf file is itself a fullchain (source a) -----------------------
reset
PKI_web_vhost_cert="$acme/fullchain.pem"
PKI_web_vhost_key="$acme/cert.key"
_writeWebChainFiles
is "$(ncerts "$fullchain")" "2" "a leaf pointed at fullchain.pem yields leaf + intermediate once"
is "$(has "$fullchain" "CN=YE2"; echo $?)" "0" "the intermediate inside the leaf file is used"

# --- 3. the live vhost names the chain (source b) ----------------------------
reset
PKI_web_vhost_cert="$bare/letsencryptfog.pem"
PKI_web_vhost_key="$bare/letsencryptfog.key"
etcconf="$WORK/fog.conf"
cat > "$etcconf" <<EOF
    SSLCertificateFile $bare/letsencryptfog.pem
    SSLCertificateChainFile $acme/chain.pem
EOF
_writeWebChainFiles
is "$(has "$fullchain" "CN=YE2"; echo $?)" "0" "SSLCertificateChainFile in the live vhost is honored"
is "$(has "$fullchain" "CN=FOG Web CA"; echo $?)" "1" "and FOG's Web CA still is not served"

# --- 4. a vhost chain path inside FOG's own tree is not evidence -------------
# The derived bundle is reachable as an input -- that is what GH-1120 was --
# so a scraped path under FOG's pki/ tree must be ignored rather than fed back.
reset
PKI_web_vhost_cert="$bare/letsencryptfog.pem"
PKI_web_vhost_key="$bare/letsencryptfog.key"
etcconf="$WORK/fog-fogchain.conf"
cat > "$etcconf" <<EOF
    SSLCertificateFile $bare/letsencryptfog.pem
    SSLCertificateChainFile $PKI_web_trust_chain
EOF
_writeWebChainFiles
is "$(has "$fullchain" "CN=FOG Web CA"; echo $?)" "1" \
    "a scraped chain path inside FOG's pki tree is ignored"

# --- 5. nothing discoverable: leaf-only, never the wrong CA ------------------
reset
PKI_web_vhost_cert="$bare/letsencryptfog.pem"
PKI_web_vhost_key="$bare/letsencryptfog.key"
etcconf=""
# NOT $(_writeWebChainFiles): a command substitution runs it in a subshell, so
# $sslchainonly below would read the value this shell already had and the
# assertion would pass without the function having set anything.
_writeWebChainFiles > "$WORK/nochain.out" 2>&1
out="$(cat "$WORK/nochain.out")"
is "$(ncerts "$chainonly")" "0" "no discoverable intermediate leaves the chain file empty"
is "$sslchainonly" "" "and \$sslchainonly empty, so the caller serves the leaf alone"
is "$(printf '%s' "$out" | grep -qi 'intermediate'; echo $?)" "0" \
    "the admin is told an intermediate could not be found"

# --- 6. a FOG-issued leaf is unaffected --------------------------------------
reset
PKI_web_vhost_cert="$leafdir/.webLeaf.pem"
PKI_web_vhost_key="$leafdir/.webLeaf.key"
_writeWebChainFiles
is "$(ncerts "$fullchain")" "2" "a FOG-issued leaf still assembles leaf + Web CA"
is "$(has "$fullchain" "CN=FOG Web CA"; echo $?)" "0" "with FOG's Web CA, which did sign it"
is "$(has "$fullchain" "CN=FOG Server CA"; echo $?)" "1" "and without the root, which must not be sent"

# --- 7. the same name is not the same CA ------------------------------------
# Name matching alone would accept this. A CA with the leaf's issuer DN but a
# different key did not sign the leaf, and serving it produces a chain that
# fails to verify exactly like serving nothing -- while looking correct to
# anyone reading subject lines.
reset
mkroot "$WORK/otherroot" "Test Root X1"
mkint "$WORK/ye2-impostor" "YE2" "$WORK/otherroot"
imp="$WORK/impostor"
mkdir -p "$imp"
cp "$acme/cert.pem" "$imp/cert.pem"
cp "$acme/cert.key" "$imp/cert.key"
cp "$WORK/ye2-impostor.pem" "$imp/chain.pem"
PKI_web_vhost_cert="$imp/cert.pem"
PKI_web_vhost_key="$imp/cert.key"
_writeWebChainFiles
is "$(ncerts "$chainonly")" "0" "an intermediate with the right name but the wrong key is rejected"

# --- 8. the documented drop point, without a pair beside it -----------------
# /etc/fog/customizations/pki/web-leaf-chain.pem is what readme.txt tells
# administrators to use. _adoptCustomChain() reads it only when a matching
# web-leaf.pem/.key pair was dropped there too, which an admin whose leaf lives
# in an ACME tree has no way to do -- so the file they were told to supply was
# read by nothing.
reset
PKI_custom_dir="$WORK/etc/fog/customizations/pki"
mkdir -p "$PKI_custom_dir"
cp "$acme/chain.pem" "$PKI_custom_dir/web-leaf-chain.pem"
PKI_web_vhost_cert="$bare/letsencryptfog.pem"
PKI_web_vhost_key="$bare/letsencryptfog.key"
etcconf=""
_writeWebChainFiles
is "$(has "$fullchain" "CN=YE2"; echo $?)" "0"     "web-leaf-chain.pem is honored with no web-leaf pair beside it"
PKI_custom_dir=""


echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
