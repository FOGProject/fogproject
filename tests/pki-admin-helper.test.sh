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
# The .fogsettings half is the one worth stating plainly. Writing an
# unvalidated value into a file root later sources is a root shell with extra
# steps, so set-preference matches its value against ^(yes|no)$ and its key
# against a three-entry allowlist. Both refusals are exercised here, and both
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
mkdir -p "$FOGDIR" "$STAGING" "$ZONE/ca" "$ZONE/leaf" "$FOGDIR/pki/root/ca" "$FOGDIR/pki/client/leaf"

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

## WEB
WEB_https_redirect='no'

## BOOT
BOOT_rebuild_ipxe_with_my_ca='no'
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

# A key the allowlist permits but the file does not carry. Appending it would
# land it after "## End of FOG Settings", in the region the installer's merge
# treats as the admin's own lines.
grep -v '^BOOT_rebuild_ipxe_with_my_ca=' "$SETTINGS" > "$SETTINGS.x" && mv "$SETTINGS.x" "$SETTINGS"
"$HELPER" set-preference BOOT_rebuild_ipxe_with_my_ca yes >/dev/null 2>&1
check "$?" "1" "set-preference refuses a key the file does not already carry"
check "$(grep -c '^BOOT_rebuild_ipxe_with_my_ca=' "$SETTINGS")" "0" "and appends nothing"

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
