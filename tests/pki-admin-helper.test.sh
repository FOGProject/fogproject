#!/bin/bash
#
# Guards fog-pki-admin, the root helper the Certificates page drives over sudo.
#
#   tests/pki-admin-helper.test.sh
#
# This script is a privilege boundary: the web user can start it, and on the
# other side of it are a CA, the host trust store, and a file that root SOURCES
# AS SHELL on the next installer run. Everything below is a refusal that has to
# hold, or a value that has to survive intact.
#
# The web leaf half is newer and the same shape. A leaf private key MAY come
# through this channel and a CA key may not, so the CA:TRUE refusal below is
# what makes that sentence a checked property rather than a promise -- and every
# refusal is checked against "is the vhost still pointing where it was", because
# a helper that refused after repointing it would pass a weaker test while
# costing an administrator the console they were using.
#
# The .fogsettings half is the one worth stating plainly. Writing an
# unvalidated value into a file root later sources is a root shell with extra
# steps, so set-preference matches its value against that KEY'S OWN literal
# pattern -- ^(yes|no)$ for the three switches, ^(http|https)$ for the netboot
# transport -- and its key against a four-entry allowlist. Both refusals are exercised here, and both
# are checked by "did the file change", not merely by "did the command exit
# non-zero" -- a helper that rejected the argument after writing would pass the
# weaker test.
#
# Runs the REAL script with its REAL hardcoded config path, as a REAL uid 0, by
# putting a tmpfs over /opt inside an unprivileged user namespace. No fixture
# copy of the helper, no sed-rewritten path, nothing that can drift from what
# ships.
#
# Needs openssl and either unshare(1) with unprivileged user namespaces, or to
# be run as root already (which CI is).
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
HELPER="$REPO/packages/pki/fog-pki-admin"

[[ -f $HELPER ]] || { echo "ERROR: $HELPER not found" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "SKIP: openssl is not installed"; exit 0; }

# Re-exec inside a user namespace unless we are already root. The namespace
# gives uid 0 and a private mount table, so the helper's own `[[ $EUID -eq 0 ]]`
# and its hardcoded CONF="/opt/fog/.fog-pki-admin" are both exercised as
# shipped.
if [[ ${FOG_PKI_TEST_INNER:-0} -ne 1 ]]; then
    if [[ $EUID -ne 0 ]]; then
        command -v unshare >/dev/null 2>&1 \
            || { echo "SKIP: not root and unshare(1) is unavailable"; exit 0; }
        unshare -rm true >/dev/null 2>&1 \
            || { echo "SKIP: unprivileged user namespaces are unavailable"; exit 0; }
        exec unshare -rm env FOG_PKI_TEST_INNER=1 bash "${BASH_SOURCE[0]}" "$@"
    fi
    export FOG_PKI_TEST_INNER=1
    # Already root: still take a private mount namespace so the tmpfs below
    # cannot be seen by, or outlive, this test.
    if command -v unshare >/dev/null 2>&1; then
        exec unshare -m bash "${BASH_SOURCE[0]}" "$@"
    fi
fi

mount -t tmpfs none /opt >/dev/null 2>&1 \
    || { echo "SKIP: could not place a tmpfs over /opt"; exit 0; }

FOGDIR=/opt/fog
CONF="$FOGDIR/.fog-pki-admin"
SETTINGS="$FOGDIR/.fogsettings"
STAGING="$FOGDIR/pkiadmin-staging"
ZONE="$FOGDIR/pki/web"
# A SIBLING of the pki tree, never a directory inside it -- that is the whole
# mechanism _externallyManagedLeaf() keys off, per ADR 0040.
CUSTOM="$FOGDIR/customizations/pki"
mkdir -p "$FOGDIR" "$STAGING" "$ZONE/ca" "$ZONE/leaf" "$FOGDIR/pki/root/ca" \
    "$FOGDIR/pki/client/leaf" "$CUSTOM"

WORK="$(mktemp -d)"
PASS=0; FAIL=0
ok()    { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad()   { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
check() { [[ $1 == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }
# openssl 3.x prints "CN = FOG Server CA" where older releases print "CN=FOG
# Server CA". Neither spelling is the thing under test, so normalize both away.
subjectOf() { openssl x509 -in "$1" -noout -subject 2>/dev/null | sed 's/ *= */=/g'; }

# --- fixtures ---------------------------------------------------------------
# A FOG root and its web intermediate; a separate corporate root with its own
# intermediate; a plain server certificate; an already-expired root.
mkroot() { # name subject days -> $WORK/<name>.{key,pem}
    openssl req -x509 -newkey rsa:2048 -nodes -days "$3" -subj "/CN=$2" \
        -addext "basicConstraints=critical,CA:TRUE" \
        -keyout "$WORK/$1.key" -out "$WORK/$1.pem" >/dev/null 2>&1
}
mkint() { # name subject signer -> $WORK/<name>.pem
    openssl req -newkey rsa:2048 -nodes -subj "/CN=$2" \
        -keyout "$WORK/$1.key" -out "$WORK/$1.csr" >/dev/null 2>&1
    printf 'basicConstraints=critical,CA:TRUE,pathlen:0\n' > "$WORK/$1.ext"
    openssl x509 -req -in "$WORK/$1.csr" -CA "$WORK/$3.pem" -CAkey "$WORK/$3.key" \
        -CAcreateserial -days 3650 -extfile "$WORK/$1.ext" \
        -out "$WORK/$1.pem" >/dev/null 2>&1
}
mkleaf() { # name subject signer -> $WORK/<name>.pem
    openssl req -newkey rsa:2048 -nodes -subj "/CN=$2" \
        -keyout "$WORK/$1.key" -out "$WORK/$1.csr" >/dev/null 2>&1
    printf 'basicConstraints=critical,CA:FALSE\n' > "$WORK/$1.ext"
    openssl x509 -req -in "$WORK/$1.csr" -CA "$WORK/$3.pem" -CAkey "$WORK/$3.key" \
        -CAcreateserial -days 3650 -extfile "$WORK/$1.ext" \
        -out "$WORK/$1.pem" >/dev/null 2>&1
}

mkroot fogroot "FOG Server CA" 3650
mkint  webca   "FOG Web CA"    fogroot
mkleaf vhost   "fog.example.org" webca
mkroot corp    "Corp Root CA"  3650
# Self-signed AND CA:FALSE. The server certificate below is signed by corp, so
# it is filtered by self-signedness before basicConstraints is ever consulted --
# which left the CA:TRUE check untested until this fixture existed.
openssl req -x509 -newkey rsa:2048 -nodes -days 3650 -subj "/CN=selfsigned.example.org" \
    -addext "basicConstraints=critical,CA:FALSE" \
    -keyout "$WORK/selfleaf.key" -out "$WORK/selfleaf.pem" >/dev/null 2>&1
mkint  corpint "Corp Issuing CA" corp
mkleaf plain   "www.example.org" corp
# Issued by the corporate INTERMEDIATE, so adopting it needs both an
# intermediate in the chain file and the corporate root anchored. A leaf signed
# straight off a root would pass the chain checks with the chain file empty and
# leave the interesting case untested.
mkleaf corpleaf "corpleaf.example.org" corpint

# An expired root has to be MINTED expired: openssl req -days cannot go
# negative, so back-date with -not_before/-not_after via a CA-signed self
# issuance is fussier than simply using faketime. Use a 1-day root and
# -checkend far enough out that the helper's own -checkend 0 still passes,
# then build the expired case with openssl x509 -req against itself.
openssl req -newkey rsa:2048 -nodes -subj "/CN=Expired Root CA" \
    -keyout "$WORK/expired.key" -out "$WORK/expired.csr" >/dev/null 2>&1
printf 'basicConstraints=critical,CA:TRUE\n' > "$WORK/expired.ext"
openssl x509 -req -in "$WORK/expired.csr" -signkey "$WORK/expired.key" \
    -extfile "$WORK/expired.ext" -not_before 20200101000000Z \
    -not_after 20200102000000Z -out "$WORK/expired.pem" >/dev/null 2>&1
if ! openssl x509 -in "$WORK/expired.pem" -noout >/dev/null 2>&1; then
    # -not_before/-not_after landed in OpenSSL 3.4; older releases need -days
    # counted from now, which cannot express the past. Skip just that case.
    rm -f "$WORK/expired.pem"
fi

install -m 0644 "$WORK/fogroot.pem" "$FOGDIR/pki/root/ca/.fogCA.pem"
install -m 0400 "$WORK/fogroot.key" "$FOGDIR/pki/root/ca/.fogCA.key"
install -m 0644 "$WORK/webca.pem"   "$ZONE/ca/.fogWebCA.pem"
cat "$WORK/webca.pem" "$WORK/fogroot.pem" > "$ZONE/ca/.fogWebCAchain.pem"
install -m 0644 "$WORK/vhost.pem"   "$ZONE/leaf/.webLeaf.pem"
install -m 0644 "$WORK/fogroot.pem" "$FOGDIR/pki/client/leaf/.srvpublic.crt"

cat > "$CONF" <<EOF
PKI_ROOT_CERT=$FOGDIR/pki/root/ca/.fogCA.pem
PKI_ROOT_KEY=$FOGDIR/pki/root/ca/.fogCA.key
PKI_WEB_CA_CERT=$ZONE/ca/.fogWebCA.pem
PKI_WEB_CHAIN=$ZONE/ca/.fogWebCAchain.pem
PKI_WEB_ANCHOR=$ZONE/ca/.trustAnchor.pem
PKI_WEB_VHOST_CERT=$ZONE/leaf/.webLeaf.pem
PKI_WEB_ZONE_DIR=$ZONE
PKI_EXTERNAL_ROOT=$ZONE/ca/.externalRoot.pem
PKI_CLIENT_CERT=$FOGDIR/pki/client/leaf/.srvpublic.crt
PKI_SB_CA_CERT=$FOGDIR/pki/secureboot/ca/.fogSBCA.pem
PKI_WEB_CA_KEY=$ZONE/ca/.fogWebCA.key
PKI_WEB_VHOST_KEY=$ZONE/leaf/.webLeaf.key
PKI_SB_CA_KEY=$FOGDIR/pki/secureboot/ca/.fogSBCA.key
PKI_CLIENT_KEY=$FOGDIR/pki/client/leaf/.srvprivate.key
PKI_SETTINGS=$SETTINGS
PKI_STAGING=$STAGING
PKI_CUSTOM_DIR=$CUSTOM
PKI_WEB_EXTERNAL_CHAIN=$ZONE/leaf/.externalChain.pem
WEB_ENGINE=
EOF
chmod 0600 "$CONF"

writeSettingsFixture() {
    cat > "$SETTINGS" <<'EOF'
## Start of FOG Settings
## Version: 1.6.0

## PKI -- certificate authorities and trust
PKI_sb_enabled='yes'
PKI_web_cert_publicly_trusted='no'
PKI_web_external_root_cert=''
## Present because adopt-custom-leaf rewrites it, and writeSetting refuses a
## key the file does not already carry -- without this line the chain
## assertions would pass for the wrong reason.
PKI_web_trust_chain='/opt/fog/pki/web/ca/.fogWebCAchain.pem'

## WEB
WEB_https_redirect='no'

## BOOT
BOOT_rebuild_ipxe_with_my_ca='no'
BOOT_url_proto='http'
BOOT_url_proto_forced='no'

## The two cleartext secrets, and a path key. Present in the fixture ON
## PURPOSE: without them the allowlist below would be tested by a file that
## does not carry the key, so writeSetting's "must already exist" guard would
## refuse them whatever the allowlist said, and deleting the allowlist would
## not turn a single assertion red.
SVC_password='correct-horse'
DB_password='battery-staple'
FOG_program_dir='/opt/fog'
## End of FOG Settings

## Carried over from the previous file.
inetConnectTimeout=20
EOF
    chmod 0600 "$SETTINGS"
}
writeSettingsFixture

stage() { # <file> -> echoes the request id it was staged under
    local id
    id=$(openssl rand -hex 16)
    cp "$1" "$STAGING/$id.pem"
    printf '%s' "$id"
}
settingOf() { # read a key back the way the installer does: by sourcing
    ( set +u; . "$SETTINGS" >/dev/null 2>&1; printf '%s' "${!1}" )
}

echo "== status: reports the chain as public metadata =="
out=$("$HELPER" status 2>&1); st=$?
check "$st" "0" "status exits 0"
if command -v php >/dev/null 2>&1; then
    php -r '$j=json_decode(file_get_contents("php://stdin"),true); exit($j===null?1:0);' <<< "$out"
    check "$?" "0" "status emits valid JSON"
    subj=$(php -r '$j=json_decode(file_get_contents("php://stdin"),true);
        foreach($j["certificates"] as $c){ if($c["slot"]==="root"){ echo $c["subject"]; } }' <<< "$out")
    check "${subj// = /=}" "CN=FOG Server CA" "status names the subject of the root"
    php -r '$j=json_decode(file_get_contents("php://stdin"),true);
        exit($j["root_key_on_server"]===true?0:1);' <<< "$out"
    check "$?" "0" "status sees the root key on disk"
    php -r '$j=json_decode(file_get_contents("php://stdin"),true);
        exit($j["externally_managed_leaf"]===false?0:1);' <<< "$out"
    check "$?" "0" "a leaf inside the web zone is not externally managed"
    php -r '$j=json_decode(file_get_contents("php://stdin"),true);
        exit($j["preferences"]["WEB_https_redirect"]==="no"?0:1);' <<< "$out"
    check "$?" "0" "status reads the preferences out of .fogsettings"
else
    echo "  SKIP  JSON assertions (php is not installed)"
fi
# status DOES name the private key paths, deliberately: the page tests
# readability itself, with the web server's own credentials, because that is
# the only test that answers the question it asks. What must never appear is a
# key's CONTENTS -- this helper runs as root and can read every one of them.
case "$out" in
    *BEGIN*PRIVATE*) bad "status leaked private key material" ;;
    *)               ok "status carries no private key material" ;;
esac
if command -v php >/dev/null 2>&1; then
    php -r '$j=json_decode(file_get_contents("php://stdin"),true);
        $k=[]; foreach($j["private_keys"] as $p){ $k[$p["label"]]=$p["expect_readable"]; }
        exit(($k["CA private key"]===false
              && $k["Client communication private key"]===true) ? 0 : 1);' <<< "$out"
    check "$?" "0" "status marks the client key as legitimately web-readable and the CA key as not"
    php -r '$j=json_decode(file_get_contents("php://stdin"),true);
        foreach($j["private_keys"] as $p){ if($p["label"]==="CA private key"){ echo $p["path"]; } }' <<< "$out"         | grep -qxF "$FOGDIR/pki/root/ca/.fogCA.key"
    check "$?" "0" "status names the real key path, not a retired layout"
fi

echo
echo "== export: a slot name, never a path =="
id=$(stage "$WORK/fogroot.pem"); rm -f "$STAGING/$id.pem"
"$HELPER" export root "$id" >/dev/null 2>&1
check "$?" "0" "export root succeeds"
check "$(subjectOf "$STAGING/$id.pem")" \
      "subject=CN=FOG Server CA" "export root writes the root certificate"
rm -f "$STAGING/$id.pem"

# A real, readable, perfectly valid certificate that is simply not one of the
# slots. Handing it a private key instead proved nothing: that is refused for
# containing no certificate, so the allowlist could be deleted with every
# assertion still green.
"$HELPER" export "$WORK/corp.pem" "$id" >/dev/null 2>&1
check "$?" "1" "export refuses a path in place of a slot name"
"$HELPER" export "$FOGDIR/pki/root/ca/.fogCA.key" "$id" >/dev/null 2>&1
check "$?" "1" "export refuses a path to a private key"
# Aimed at a directory the helper CAN write. Pointed at /etc it was refused by
# the filesystem rather than by the pattern, so deleting the pattern turned
# nothing red.
"$HELPER" export root "../canary" >/dev/null 2>&1
check "$?" "1" "export refuses a traversing request id"
[[ -e /opt/fog/canary.pem ]] && bad "the traversing export escaped the staging directory" \
    || ok "nothing was written outside the staging directory"
"$HELPER" export privatekey "$id" >/dev/null 2>&1
check "$?" "1" "export refuses an unknown slot"
[[ -e "$STAGING/$id.pem" ]] && bad "a refused export still wrote to staging" \
    || ok "a refused export writes nothing"

echo
echo "== import-root: only a self-signed CA, and only the self-signed part =="
id=$(stage "$WORK/corp.pem")
out=$("$HELPER" import-root "$id" 2>&1); st=$?
check "$st" "0" "a corporate root imports"
# Rebuilding the anchor FILE is mandatory; pushing it into the host trust store
# is best-effort and reported, matching _installCATrustAnchor(). The test
# namespace cannot write the real store, so the token is what is pinned here --
# not which of the three it is.
case "$out" in
    "OK 1 trust:ok"|"OK 1 trust:failed"|"OK 1 trust:unavailable")
        ok "import-root reports how many roots it kept and what the host store did" ;;
    *)  bad "import-root's success line is '$out'" ;;
esac
check "$(subjectOf "$ZONE/ca/.externalRoot.pem")" \
      "subject=CN=Corp Root CA" "the imported root lands at the canonical path"
check "$(settingOf PKI_web_external_root_cert)" "$ZONE/ca/.externalRoot.pem" \
      "the canonical path is recorded in .fogsettings, not the upload's own path"
check "$(grep -c 'BEGIN CERTIFICATE' "$ZONE/ca/.trustAnchor.pem" 2>/dev/null)" "2" \
      "the trust anchor carries FOG's root and the imported one"
rm -f "$STAGING/$id.pem"

# A bundle: the anchor must gain the root and NOT the intermediate. Anchoring
# an intermediate would trust it as a root, which widens what this box accepts.
cat "$WORK/corpint.pem" "$WORK/corp.pem" > "$WORK/bundle.pem"
id=$(stage "$WORK/bundle.pem")
"$HELPER" import-root "$id" >/dev/null 2>&1
check "$?" "0" "a root+intermediate bundle imports"
check "$(grep -c 'BEGIN CERTIFICATE' "$ZONE/ca/.externalRoot.pem" 2>/dev/null)" "1" \
      "only the self-signed certificate is kept out of a bundle"
rm -f "$STAGING/$id.pem"

for case_ in "corpint:an intermediate" "plain:a server certificate" \
             "selfleaf:a self-signed certificate that is not a CA"; do
    f="${case_%%:*}"; label="${case_#*:}"
    before=$(sha256sum "$ZONE/ca/.externalRoot.pem" | cut -d' ' -f1)
    id=$(stage "$WORK/$f.pem")
    "$HELPER" import-root "$id" >/dev/null 2>&1
    check "$?" "1" "import-root refuses $label"
    check "$(sha256sum "$ZONE/ca/.externalRoot.pem" | cut -d' ' -f1)" "$before" \
          "a refused import ($label) left the previous root in place"
    rm -f "$STAGING/$id.pem"
done

if [[ -f $WORK/expired.pem ]]; then
    id=$(stage "$WORK/expired.pem")
    "$HELPER" import-root "$id" >/dev/null 2>&1
    check "$?" "1" "import-root refuses an expired root"
    rm -f "$STAGING/$id.pem"
else
    echo "  SKIP  expired-root case (this openssl cannot mint one)"
fi

# The request id is what keeps import-root reading inside the staging
# directory. A perfectly valid root placed just outside it must still be
# unreachable.
cp "$WORK/corp.pem" "$FOGDIR/outside.pem"
before=$(sha256sum "$ZONE/ca/.externalRoot.pem" | cut -d' ' -f1)
"$HELPER" import-root "../outside" >/dev/null 2>&1
check "$?" "1" "import-root refuses a traversing request id"
check "$(sha256sum "$ZONE/ca/.externalRoot.pem" | cut -d' ' -f1)" "$before" \
      "and imported nothing from outside the staging directory"

printf 'not a certificate at all\n' > "$WORK/junk.pem"
id=$(stage "$WORK/junk.pem")
"$HELPER" import-root "$id" >/dev/null 2>&1
check "$?" "1" "import-root refuses a file that is not PEM"
rm -f "$STAGING/$id.pem"

# A private key wearing a certificate's clothes: PEM, parses as something,
# and must never be stored or echoed back.
id=$(openssl rand -hex 16)
cat "$WORK/corp.key" > "$STAGING/$id.pem"
out=$("$HELPER" import-root "$id" 2>&1); st=$?
check "$st" "1" "import-root refuses a private key"
case "$out" in
    *PRIVATE*) bad "the refusal echoed the key back" ;;
    *)         ok "the refusal does not echo the key back" ;;
esac
rm -f "$STAGING/$id.pem"

echo
echo "== clear-root: the import is undoable from the same screen =="
"$HELPER" clear-root >/dev/null 2>&1
check "$?" "0" "clear-root succeeds"
[[ -e "$ZONE/ca/.externalRoot.pem" ]] && bad "clear-root left the file behind" \
    || ok "clear-root removes the imported root"
check "$(settingOf PKI_web_external_root_cert)" "" "clear-root clears the setting"
check "$(grep -c 'BEGIN CERTIFICATE' "$ZONE/ca/.trustAnchor.pem" 2>/dev/null)" "1" \
      "the trust anchor drops back to FOG's root alone"

echo
echo "== set-preference: .fogsettings is sourced as shell by root =="
writeSettingsFixture
before=$(sha256sum "$SETTINGS" | cut -d' ' -f1)

"$HELPER" set-preference WEB_https_redirect yes >/dev/null 2>&1
check "$?" "0" "an allowlisted key with an allowed value is accepted"
check "$(settingOf WEB_https_redirect)" "yes" "the value is what the file sources to"
check "$(grep -c '^WEB_https_redirect=' "$SETTINGS")" "1" "the key is rewritten, not duplicated"
check "$(settingOf inetConnectTimeout)" "20" "an unmanaged carried-over line survives"
check "$(grep -c '^## End of FOG Settings' "$SETTINGS")" "1" "the file structure is intact"

# Every value here is "yes", which the value pattern accepts. Using a value
# the pattern rejects (a password, a path) would have let the KEY allowlist be
# deleted with every assertion still green -- the refusal would have come from
# the other gate.
for key in SVC_password DB_password FOG_program_dir BOOT_url_proto_forced PKI_root_ca_cert; do
    before=$(sha256sum "$SETTINGS" | cut -d' ' -f1)
    "$HELPER" set-preference "$key" yes >/dev/null 2>&1
    check "$?" "1" "set-preference refuses the key $key"
    check "$(sha256sum "$SETTINGS" | cut -d' ' -f1)" "$before" \
          "refusing $key changed nothing in the file"
done

# The injection cases. Each of these, written verbatim into a file root later
# sources, executes as root.
CANARY=/opt/fog/canary
for payload in "no'; touch ${CANARY}; #" \
               "\$(touch ${CANARY})" \
               "\`touch ${CANARY}\`" \
               "yes"$'\n'"touch ${CANARY}" \
               "YES" "1" "true" ""; do
    before=$(sha256sum "$SETTINGS" | cut -d' ' -f1)
    "$HELPER" set-preference WEB_https_redirect "$payload" >/dev/null 2>&1
    st=$?
    label=$(printf '%s' "$payload" | tr '\n' ' ')
    check "$st" "1" "set-preference refuses the value '${label:-<empty>}'"
    check "$(sha256sum "$SETTINGS" | cut -d' ' -f1)" "$before" \
          "refusing '${label:-<empty>}' changed nothing in the file"
done
# Source the file the way installfog.sh does and see whether anything ran.
( set +u; . "$SETTINGS" >/dev/null 2>&1 )
[[ -e $CANARY ]] && bad "sourcing .fogsettings executed an injected payload" \
    || ok "sourcing .fogsettings after every refusal executes nothing"

echo
echo "== the value domain is PER KEY, and each one is still a fixed set =="
# BOOT_url_proto's domain is http|https, so the single ^(yes|no)$ became a
# per-key lookup. That is not a relaxation -- every key still names a literal
# set -- but a per-key lookup is exactly the shape that can quietly acquire a
# permissive default, so both directions are pinned.
"$HELPER" set-preference BOOT_url_proto https >/dev/null 2>&1
check "$?" "0" "set-preference accepts https for BOOT_url_proto"
check "$(settingOf BOOT_url_proto)" "https" "and the value lands in the file"
"$HELPER" set-preference BOOT_url_proto http >/dev/null 2>&1
check "$?" "0" "and accepts http"
check "$(settingOf BOOT_url_proto)" "http" "and back again"

# The domains must not leak into one another. A switch that accepted a
# transport, or a transport that accepted yes, would mean the per-key lookup
# had collapsed back to one permissive pattern.
before=$(sha256sum "$SETTINGS" | cut -d' ' -f1)
"$HELPER" set-preference BOOT_url_proto yes >/dev/null 2>&1
check "$?" "1" "BOOT_url_proto refuses yes -- its domain is not the switches'"
"$HELPER" set-preference WEB_https_redirect https >/dev/null 2>&1
check "$?" "1" "and WEB_https_redirect refuses https -- nor is the reverse true"
check "$(sha256sum "$SETTINGS" | cut -d' ' -f1)" "$before" \
    "neither refusal changed the file"

# The same injection shapes as above, against the key whose domain is new.
# .fogsettings is still sourced as shell by root, so a widened domain that
# stopped matching a literal pattern would be a root shell with extra steps.
CANARY2=/opt/fog/canary2
for payload in "http'; touch ${CANARY2}; #" \
               "\$(touch ${CANARY2})" \
               "https"$'\n'"touch ${CANARY2}" \
               "HTTPS" "httpss" "ftp" ""; do
    before=$(sha256sum "$SETTINGS" | cut -d' ' -f1)
    "$HELPER" set-preference BOOT_url_proto "$payload" >/dev/null 2>&1
    st=$?
    label=$(printf '%s' "$payload" | tr '\n' ' ')
    check "$st" "1" "BOOT_url_proto refuses the value '${label:-<empty>}'"
    check "$(sha256sum "$SETTINGS" | cut -d' ' -f1)" "$before" \
        "refusing '${label:-<empty>}' changed nothing in the file"
done
( set +u; . "$SETTINGS" >/dev/null 2>&1 )
[[ -e $CANARY2 ]] && bad "sourcing .fogsettings executed an injected transport" \
    || ok "sourcing .fogsettings after every transport refusal executes nothing"

# BOOT_url_proto_forced is still not settable. Writing BOOT_url_proto already
# forces the transport, so a second settable flag would be another way to say
# the same thing -- and the one that carries none of the steering keys with it.
"$HELPER" set-preference BOOT_url_proto_forced yes >/dev/null 2>&1
check "$?" "1" "BOOT_url_proto_forced is still not a settable preference"

# A key the allowlist permits but the file does not carry. Appending it would
# land it after "## End of FOG Settings", in the region the installer's merge
# treats as the admin's own lines.
grep -v '^BOOT_rebuild_ipxe_with_my_ca=' "$SETTINGS" > "$SETTINGS.x" && mv "$SETTINGS.x" "$SETTINGS"
"$HELPER" set-preference BOOT_rebuild_ipxe_with_my_ca yes >/dev/null 2>&1
check "$?" "1" "set-preference refuses a key the file does not already carry"
check "$(grep -c '^BOOT_rebuild_ipxe_with_my_ca=' "$SETTINGS")" "0" "and appends nothing"

echo
echo "== bringing your own web leaf: what has to be refused =="
# The whole point of this block is that every refusal leaves the server
# SERVING WHAT IT WAS SERVING. A helper that refused after repointing the vhost
# would pass an exit-code assertion and cost an administrator their console, so
# the vhost link is re-checked after each one.
vhostUnchanged() { # <label>
    if [[ -L $ZONE/leaf/.webLeaf.pem ]]; then
        bad "$1 (the vhost was repointed anyway)"
    else
        ok "$1"
    fi
}

"$HELPER" adopt-custom-leaf >/dev/null 2>&1
check "$?" "1" "adopt refuses when the customizations directory is empty"

install -m 0644 "$WORK/corpleaf.pem" "$CUSTOM/web-leaf.pem"
out=$("$HELPER" adopt-custom-leaf 2>&1)
check "$?" "1" "adopt refuses a certificate with no key"
case "$out" in
    *"both files are required"*) ok "and says both files are required" ;;
    *)                           bad "the refusal blamed something else: $out" ;;
esac

# A real key, but not this certificate's. The pair test is the one thing
# standing between an upload and a web server that will not start.
install -m 0600 "$WORK/corpint.key" "$CUSTOM/web-leaf.key"
out=$("$HELPER" adopt-custom-leaf 2>&1)
check "$?" "1" "adopt refuses a certificate and key that are not a pair"
vhostUnchanged "and leaves the vhost alone"

# The genuine pair, but this server has never been told to trust Corp Root.
install -m 0600 "$WORK/corpleaf.key" "$CUSTOM/web-leaf.key"
install -m 0644 "$WORK/corpint.pem"  "$CUSTOM/web-leaf-chain.pem"
out=$("$HELPER" adopt-custom-leaf 2>&1)
check "$?" "1" "adopt refuses a leaf with no trust path"
case "$out" in
    *"Corp Issuing CA"*) ok "and names the issuer it could not reach" ;;
    *)                   bad "the refusal did not name the issuer: $out" ;;
esac
vhostUnchanged "and leaves the vhost alone"

echo
echo "== the CA:TRUE refusal, which is what 'no CA key here' actually means =="
install -m 0644 "$WORK/corpint.pem" "$CUSTOM/web-leaf.pem"
install -m 0600 "$WORK/corpint.key" "$CUSTOM/web-leaf.key"
rm -f "$CUSTOM/web-leaf-chain.pem"
out=$("$HELPER" adopt-custom-leaf 2>&1)
check "$?" "1" "adopt refuses a CA certificate offered as the web leaf"
case "$out" in
    *"CA:TRUE"*) ok "and says which property refused it" ;;
    *)           bad "the refusal did not name basicConstraints: $out" ;;
esac
vhostUnchanged "and leaves the vhost alone"

echo
echo "== adopting a leaf, once its root is trusted =="
id=$(stage "$WORK/corp.pem")
"$HELPER" import-root "$id" >/dev/null 2>&1
check "$?" "0" "the corporate root imports"

install -m 0644 "$WORK/corpleaf.pem" "$CUSTOM/web-leaf.pem"
install -m 0600 "$WORK/corpleaf.key" "$CUSTOM/web-leaf.key"
install -m 0644 "$WORK/corpint.pem"  "$CUSTOM/web-leaf-chain.pem"
out=$("$HELPER" adopt-custom-leaf 2>&1)
check "$?" "0" "adopt succeeds once a path builds"
check "$(readlink "$ZONE/leaf/.webLeaf.pem")" "$CUSTOM/web-leaf.pem" \
    "the canonical certificate path points AT the admin's file"
check "$(readlink "$ZONE/leaf/.webLeaf.key")" "$CUSTOM/web-leaf.key" \
    "and so does the key path"
# A symlink, not a copy, because createSSLCA() does the same thing with the
# same material on its next run. If the helper copied instead, the installer
# would silently undo the page.
check "$(settingOf PKI_web_trust_chain)" "$ZONE/leaf/.externalChain.pem" \
    "the chain is recorded at a canonical path, not where the admin's file was"
check "$(grep -c 'BEGIN CERTIFICATE' "$ZONE/leaf/.externalChain.pem")" "1" \
    "the chain file holds the intermediate"

echo
echo "== a root in the supplied material is reported, never anchored =="
# The load-bearing one. rebuildAnchor() and _resolveTrustAnchor() both take
# every self-signed certificate out of the chain file, so a root left in there
# would be trusted as a side effect of supplying a chain -- and the deliberate
# import on this page would be theatre.
cat "$WORK/corpleaf.pem" "$WORK/corpint.pem" "$WORK/corp.pem" > "$CUSTOM/web-leaf.pem"
rm -f "$CUSTOM/web-leaf-chain.pem"
"$HELPER" adopt-custom-leaf >/dev/null 2>&1
check "$?" "0" "a fullchain with the root appended is adopted"
check "$(grep -c 'BEGIN CERTIFICATE' "$ZONE/leaf/.externalChain.pem")" "1" \
    "and the chain file holds ONLY the intermediate"
rm -rf "$WORK/cc"; mkdir -p "$WORK/cc"
awk -v d="$WORK/cc" '/BEGIN CERTIFICATE/{n++} n{print > (d "/c" n ".pem")}' \
    "$ZONE/leaf/.externalChain.pem"
check "$(subjectOf "$WORK/cc/c1.pem")" "subject=CN=Corp Issuing CA" \
    "the certificate in the chain is the intermediate, not the root"

echo
echo "== a fullchain has to lead with its leaf =="
# openssl x509 -in reads only the first certificate, and _writeWebChainFiles()
# relies on that to assemble what the web server serves. A file whose first
# certificate is not the leaf would be adopted into a vhost that cannot start.
cat "$WORK/corpint.pem" "$WORK/corpleaf.pem" > "$CUSTOM/web-leaf.pem"
out=$("$HELPER" adopt-custom-leaf 2>&1)
check "$?" "1" "adopt refuses a fullchain whose first certificate is not the leaf"
case "$out" in
    *"put the leaf first"*) ok "and says how to fix it" ;;
    *)                      bad "the refusal did not say how to fix it: $out" ;;
esac

echo
echo "== import-leaf: the upload channel =="
rm -f "$CUSTOM/"web-leaf*
id=$(openssl rand -hex 16)
cat "$WORK/corpleaf.pem" "$WORK/corpint.pem" > "$STAGING/$id.pem"
cp "$WORK/corpleaf.key" "$STAGING/$id.key"
out=$("$HELPER" import-leaf "$id" 2>&1)
check "$?" "0" "a PEM certificate and key upload, and are adopted"
check "$(subjectOf "$CUSTOM/web-leaf.pem")" "subject=CN=corpleaf.example.org" \
    "the material lands in the customizations tree, where the installer will find it again"
check "$(stat -c '%a' "$CUSTOM/web-leaf.key" 2>/dev/null)" "600" \
    "and the private key is not group-readable"

id=$(openssl rand -hex 16)
cp "$WORK/corpleaf.pem" "$STAGING/$id.pem"
"$HELPER" import-leaf "$id" >/dev/null 2>&1
check "$?" "1" "import-leaf refuses a certificate with no key"

id=$(openssl rand -hex 16)
"$HELPER" import-leaf "$id" >/dev/null 2>&1
check "$?" "1" "import-leaf refuses a request id with nothing staged under it"
"$HELPER" import-leaf "../../etc/shadow" >/dev/null 2>&1
check "$?" "1" "and refuses a request id that is not 32 hex characters"

echo
echo "== import-leaf: PKCS#12, and the passphrase that must not be an argument =="
# The passphrase travels as a FILE under the same request id. This helper is
# started from a command line the web tier builds, and an argument is readable
# in /proc by every local user for as long as the call lasts -- so the file is
# not a convenience, it is the reason this is safe to offer at all.
check "$(grep -c 'passin "\$passin"' "$HELPER")" "3" \
    "the PKCS#12 reads take their passphrase from a file: URI"
check "$(grep -c 'file:\${stagedPass}' "$HELPER")" "2" \
    "and the file is the staged one, never a value on the command line"

id=$(openssl rand -hex 16)
openssl pkcs12 -export -out "$STAGING/$id.p12" -inkey "$WORK/corpleaf.key" \
    -in "$WORK/corpleaf.pem" -certfile "$WORK/corpint.pem" \
    -passout pass:s3cret >/dev/null 2>&1
if [[ -s $STAGING/$id.p12 ]]; then
    rm -f "$CUSTOM/"web-leaf*
    printf 's3cret' > "$STAGING/$id.pass"
    out=$("$HELPER" import-leaf "$id" 2>&1)
    check "$?" "0" "a PKCS#12 container unpacks and is adopted"
    check "$(subjectOf "$CUSTOM/web-leaf.pem")" "subject=CN=corpleaf.example.org" \
        "with the same leaf the PEM route produced"
    check "$(openssl pkey -in "$CUSTOM/web-leaf.key" -noout >/dev/null 2>&1; echo $?)" "0" \
        "and a key the web server can read without a passphrase"

    # The wrong passphrase must fail as a passphrase problem, not as a
    # mangled-certificate one: an administrator retyping a password needs to be
    # told that is what went wrong.
    id=$(openssl rand -hex 16)
    openssl pkcs12 -export -out "$STAGING/$id.p12" -inkey "$WORK/corpleaf.key" \
        -in "$WORK/corpleaf.pem" -passout pass:s3cret >/dev/null 2>&1
    printf 'wrong' > "$STAGING/$id.pass"
    out=$("$HELPER" import-leaf "$id" 2>&1)
    check "$?" "1" "a wrong passphrase is refused"
    case "$out" in
        *passphrase*) ok "and the message says it was the passphrase" ;;
        *)            bad "the refusal blamed something else: $out" ;;
    esac
else
    echo "  SKIP  this openssl would not write a PKCS#12 fixture"
fi

echo
echo "== existing files in the customizations tree are not destroyed =="
# This directory exists for the administrator's own files. A page that
# overwrote one without trace would be spending something it does not own.
rm -f "$CUSTOM/"web-leaf*
printf 'admin-put-this-here\n' > "$CUSTOM/web-leaf.pem"
id=$(openssl rand -hex 16)
cp "$WORK/corpleaf.pem" "$STAGING/$id.pem"
cp "$WORK/corpleaf.key" "$STAGING/$id.key"
"$HELPER" import-leaf "$id" >/dev/null 2>&1
check "$?" "0" "an upload over an existing file succeeds"
check "$(cat "$CUSTOM/web-leaf.pem.replaced" 2>/dev/null)" "admin-put-this-here" \
    "and what was there is kept beside it"

echo
echo "== status reports the tree, without changing anything =="
rm -f "$CUSTOM/"web-leaf*
install -m 0644 "$WORK/corpleaf.pem" "$CUSTOM/web-leaf.pem"
install -m 0600 "$WORK/corpleaf.key" "$CUSTOM/web-leaf.key"
st=$("$HELPER" status 2>/dev/null)
case "$st" in
    *'"pair_ok": true'*) ok "status sees a usable pair" ;;
    *)                   bad "status did not report the pair" ;;
esac
case "$st" in
    *'"subject": "CN=corpleaf.example.org"'*) ok "and names it" ;;
    *)                                        bad "status did not name the subject" ;;
esac
install -m 0600 "$WORK/corpint.key" "$CUSTOM/web-leaf.key"
st=$("$HELPER" status 2>/dev/null)
case "$st" in
    *'"pair_ok": false'*) ok "and says so when the key does not match" ;;
    *)                    bad "status called a mismatched pair usable" ;;
esac

echo
echo "== the helper refuses to run without its config =="
mv "$CONF" "$CONF.away"
out=$("$HELPER" status 2>&1)
check "$?" "1" "no config means no verbs"
# On the MESSAGE, not just the exit status. Without the config the staging
# check below it also fails, so a bare exit-code assertion stays green even if
# the config requirement is deleted -- and the admin would then be told the
# staging directory is misconfigured for a server that simply has not been
# installed.
case "$out" in
    *"re-run the FOG installer"*) ok "and says which of the two it is" ;;
    *)                            bad "the refusal blamed something else: $out" ;;
esac
mv "$CONF.away" "$CONF"

# The root check cannot be turned red from here: this test IS root, which is
# the only way to exercise the rest of the script at all. It is one line and
# the sudoers rule is what actually places the boundary; noted rather than
# faked with an assertion that proves nothing.

echo
printf '%d passed, %d failed\n' "$PASS" "$FAIL"
rm -rf "$WORK"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
