#!/bin/bash
#
# Guards the installer's detection of a web certificate it does not own.
#
#   tests/external-cert-detection.test.sh
#
# acmeLeaf tells createSSLCA() to leave the web leaf alone. It has always had to
# be typed into .fogsettings by hand, and the cost of forgetting is not
# cosmetic: the leaf gets regenerated from the ORIGINAL CSR while the private
# key on disk is the ACME key, producing a cert/key mismatch that stops the web
# server. An unattended `-y` upgrade did that silently.
#
# So detection has to be right in BOTH directions, and both are pinned here.
# Missing a real external certificate breaks a working server. Falsely claiming
# one stops FOG managing a leaf it does issue, which quietly ends that server's
# renewals. That is why the presence of acme.sh or certbot is deliberately NOT
# proof -- plenty of servers run either for an unrelated domain.
#
# Needs openssl. Runs entirely on generated fixtures -- no install, no network,
# no root.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl is not installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
check() { [[ $1 == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# --- fixture: a FOG CA, a leaf it signed, and an unrelated self-signed leaf ---
mkdir -p "$WORK/ssl" "$WORK/foreign"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=FOG Server CA" \
    -keyout "$WORK/ssl/ca.key" -out "$WORK/ssl/ca.pem" >/dev/null 2>&1
openssl req -newkey rsa:2048 -nodes -subj "/CN=fog.example.org" \
    -keyout "$WORK/ssl/leaf.key" -out "$WORK/ssl/leaf.csr" >/dev/null 2>&1
openssl x509 -req -in "$WORK/ssl/leaf.csr" -CA "$WORK/ssl/ca.pem" -CAkey "$WORK/ssl/ca.key" \
    -days 1 -out "$WORK/ssl/leaf.pem" >/dev/null 2>&1
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=fog.example.org" \
    -keyout "$WORK/foreign/priv.key" -out "$WORK/foreign/fullchain.pem" >/dev/null 2>&1

reset_env() {
    etcconf=""; sslpath="$WORK/ssl"; rootCAPem="$WORK/ssl/ca.pem"; sslcachain=""
    sslpubcert="$WORK/ssl/leaf.pem"; sslprivkey="$WORK/ssl/leaf.key"
    acmeLeaf=""; publicWebCert=""
    error_log="$WORK/error.log"
}

echo "== _detectExternalCertManagement: says yes only on real evidence =="

# A. The genuine FOG install. Detection MUST stay quiet -- a false positive here
#    stops FOG re-issuing its own leaf and silently ends its renewals.
reset_env
if _detectExternalCertManagement >/dev/null; then
    bad "A: fired on a FOG-issued leaf at FOG's own path"
else
    ok "A: stays quiet on a genuine FOG-issued leaf"
fi

# B. FOG pointed straight at an ACME client's tree.
reset_env
sslpubcert="/etc/letsencrypt/live/fog.example.org/fullchain.pem"
out=$(_detectExternalCertManagement) && ok "B: fires when \$sslpubcert is under /etc/letsencrypt" \
    || bad "B: missed a path inside an ACME tree"
[[ $out == *"ACME client's tree"* ]] && ok "B2: names the ACME tree as the reason" \
    || bad "B2: reason did not name the ACME tree (got '$out')"

# C. The novhost=yes shape -- FOG never wrote the vhost, $sslpubcert still names
#    FOG's own unused leaf, and the web server serves somebody else's file.
reset_env
etcconf="$WORK/hand.conf"
printf '<VirtualHost *:443>\n    SSLCertificateFile %s\n    SSLCertificateKeyFile %s\n</VirtualHost>\n' \
    "$WORK/foreign/fullchain.pem" "$WORK/foreign/priv.key" > "$etcconf"
out=$(_detectExternalCertManagement) && ok "C: fires when the vhost serves a cert outside \$sslpath" \
    || bad "C: missed a vhost-served cert outside \$sslpath"
[[ $out == *"outside FOG's"* ]] && ok "C2: reason names the path and \$sslpath" \
    || bad "C2: reason did not name the path (got '$out')"

# D. A foreign leaf dropped AT FOG's own path. _createCommLeaf documents that as
#    supported, so it is a real configuration -- decided by verification, not by
#    matching "FOG" in the issuer name, since a CA can be renamed.
reset_env
cp "$WORK/foreign/fullchain.pem" "$WORK/ssl/leaf-foreign.pem"
sslpubcert="$WORK/ssl/leaf-foreign.pem"
out=$(_detectExternalCertManagement) && ok "D: fires on a leaf that does not chain to FOG's CA" \
    || bad "D: missed a foreign leaf sitting at FOG's own path"

# E. Already declared -- the caller skips detection entirely, so an admin who
#    set acmeLeaf by hand is never second-guessed.
if awk '/Detect-then-DECLARE/,/_warnExternalCertTooling/' "$FUNCS" | grep -q 'acmeLeaf != yes'; then
    ok "E: detection is skipped when acmeLeaf is already declared"
else
    bad "E: detection does not check acmeLeaf first"
fi

echo "== the paths are captured, and captured from the vhost =="

# F/G. Both directive spellings, so an nginx server is read as well as apache.
reset_env
etcconf="$WORK/nginx.conf"
printf 'server {\n    ssl_certificate %s;\n    ssl_certificate_key %s;\n}\n' \
    "$WORK/foreign/fullchain.pem" "$WORK/foreign/priv.key" > "$etcconf"
check "$(_vhostCertPath)" "$WORK/foreign/fullchain.pem" "F: _vhostCertPath reads nginx ssl_certificate"
check "$(_vhostKeyPath)"  "$WORK/foreign/priv.key"      "G: _vhostKeyPath reads nginx ssl_certificate_key"

# H. The cert directive must not swallow the key directive, in either spelling.
reset_env
etcconf="$WORK/apache2.conf"
printf '    SSLCertificateKeyFile %s\n    SSLCertificateFile %s\n' \
    "$WORK/foreign/priv.key" "$WORK/foreign/fullchain.pem" > "$etcconf"
check "$(_vhostCertPath)" "$WORK/foreign/fullchain.pem" "H: KeyFile listed first is not read as the cert"

# I. Persisted, or FOG re-emits its own paths on the next run and points the web
#    server at a certificate whose key is not the one on disk.
for k in webCertFile webKeyFile; do
    if awk '/managedKeys=/,/^    \)/' "$FUNCS" | grep -qE "^\s*$k\s*$"; then
        ok "I: $k is in managedKeys"
    else
        bad "I: $k is not persisted"
    fi
done

# J. No prompt on the detect path. Under -y there is nobody to answer, and that
#    is precisely the run that used to do the damage, so the safe behaviour must
#    be the default rather than an answer to a question.
if awk '/Detect-then-DECLARE/,/_warnExternalCertTooling/' "$FUNCS" | grep -qE '(^|[^_[:alnum:]])read($|[[:space:]])'; then
    bad "J: the detect path prompts, so an unattended run cannot benefit"
else
    ok "J: detection never prompts"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
