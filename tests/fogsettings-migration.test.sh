#!/bin/bash
#
# The one-run .fogsettings migration, end to end.
#
#   tests/fogsettings-migration.test.sh
#
# GH-1120 renamed all 79 managed keys to CATEGORY_lower_snake_case. That
# migration is TWO halves in two files, and each is useless -- or worse --
# without the other:
#
#   1. the rename-seed block in bin/installfog.sh copies every old value onto
#      its new key, guarded on the new key so it fires exactly once;
#   2. deprecatedKeys in writeUpdateFile() strips the old lines, and carries no
#      value whatsoever.
#
# Keep 2 and drop 1 and every setting on every server is wiped, silently, on the
# next upgrade -- and under -y there is nobody to notice. Nothing else in the
# suite exercises the pair together, and no unit test can: writeUpdateFile()
# only runs at the very end of an install.
#
# The migration is EXTRACTED FROM lib/common/functions.sh AND EVALUATED here
# rather than copied. A hand-copied replay is how a test passes while the behaviour is
# wrong, which is the failure mode install-settings-resolution.test.sh already
# documents for the httpsRedirect migration.
#
# No network, no root, no install. Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
error_log="$WORK/error.log"
: > "$error_log"
version="1.6.0-test"
fogprogramdir="$WORK/opt/fog"
mkdir -p "$fogprogramdir"

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# The pre-GH-1120 file: every one of the 79 managed keys, plus the things an
# upgrade must not eat -- two hand-set keys (which survive ONLY because the
# merge preserves lines it does not manage), an admin's own comment, and a key
# no version of FOG ever wrote.
cat > "$fogprogramdir/.fogsettings" <<'OLDEOF'
## Start of FOG Settings
## Created by the FOG Installer
## Version: 1.5.10.1
## Install time: Mon Jan  1 00:00:00 2024
ipaddress='10.0.0.5'
ipaddresses='10.0.0.5 10.0.0.6'
copybackold='1'
interface='eth0'
submask='255.255.255.0'
hostname='fog.example.org'
routeraddress='10.0.0.1'
plainrouter='10.0.0.1'
dnsaddress='10.0.0.2'
username='fogproject'
password='svcsecret'
osid='2'
osname='Debian'
dodhcp='Y'
bldhcp='1'
dhcpd='isc-dhcp-server'
dhcpengine='isc'
blexports='1'
installtype='N'
snmysqlexternal='0'
snmysqluser='fogmaster'
snmysqlpass='dbsecret'
snmysqlhost='localhost'
mysqldbname='fog'
installlang='1'
storageLocation='/images'
fogupdateloaded=1
docroot='/var/www/'
webroot='/fog/'
caCreated='yes'
httpproto='https'
startrange='10.0.0.100'
endrange='10.0.0.200'
packages='apache2 php'
noTftpBuild=''
tftpAdvOpts='--secure'
httpsRedirect='yes'
publicWebCert='no'
rebuildIpxeWithMyCA='no'
sslpath='/opt/fog/snapins/ssl'
backupPath='/home/backups'
php_ver='8.2'
sslprivkey='/opt/fog/pki/web/leaf/.webLeaf.key'
sslcakey='/opt/fog/pki/web/ca/.fogWebCA.key'
sslcapem='/opt/fog/pki/web/ca/.fogWebCA.pem'
sslcachain='/opt/fog/pki/web/ca/.fogWebCAchain.pem'
externalca='no'
extcacert=''
extcakey=''
extcaroot=''
sslcsr='/opt/fog/snapins/ssl/fog.csr'
sslpubcert='/opt/fog/pki/web/leaf/.webLeaf.pem'
sendreports='Y'
webserver='apache2'
webExtCACert=''
webExtCAKey=''
webExtCARoot=''
fogprogramdir='/opt/fog'
secureBootKey='/opt/fog/pki/secureboot/.fogSB.key'
secureBootCert='/opt/fog/pki/secureboot/.fogSB.pem'
secureboot='1'
catrust='1'
secureBootMokCert='/opt/fog/pki/secureboot/.fogSBCA.pem'
fwconfigure='configure'
fog_git_path='/root/fogproject'
fog_update_channel='stable'
extraServerNames='fog-alt.example.org'
acmeLeaf='no'
webCertFile=''
webKeyFile=''
bootdelay='3'
kernelBackupGenerations='4'
netbootproto='http'
netbootProtoForced='no'
rootCAPem='/opt/fog/pki/root/ca/.fogCA.pem'
rootCAKey='/opt/fog/pki/root/ca/.fogCA.key'
internalDomains='example.org'
internalSubnets='10.0.0.0/8'
sbNameConstraints='yes'
## --- my own notes, do not delete ---
inetConnectTimeout='30'
storageLocationCapture='/images/dev'
somethingFOGNeverWrote='keepme'
## End of FOG Settings
OLDEOF

echo "== the seed block carries every value =="

# Extract the real migration and run it, so this cannot drift from the code.
#
# It used to be an inline block in bin/installfog.sh and is now
# migrateDeprecatedKeys() in lib/common/functions.sh. It had to move: three
# entry points source .fogsettings and then read a renamed key -- installfog.sh,
# updatefog.sh and restorekernel.sh -- and a block living inside one of them
# could not be called by the other two, which is why those two had no migration
# at all. All three source functions.sh first.
#
# Evaluating the DEFINITION and then calling it keeps every eval site below
# unchanged: the string still both defines and runs the migration.
#
# The ordering property this move exists for is pinned separately, in
# tests/fogsettings-key-migration.test.sh. This file is about the VALUES.
seedblock=$(sed -n '/^migrateDeprecatedKeys() {/,/^}$/p' "$FUNCS")
if [[ -z $seedblock ]]; then
    bad "could not extract migrateDeprecatedKeys() from lib/common/functions.sh"
    printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
    exit 1
fi
seedblock="${seedblock}
migrateDeprecatedKeys"
ok "migrateDeprecatedKeys() is present in lib/common/functions.sh"

# Sourcing the old file is what an upgrade does: .fogsettings is SHELL.
resolvedfogprogramdir="$fogprogramdir"
# shellcheck source=/dev/null
. "$fogprogramdir/.fogsettings"
# GH-850, and not test scaffolding: the file RECORDS fogprogramdir but must not
# CONTROL it, because .fogsettings lives at $fogprogramdir/.fogsettings and so
# cannot be what locates itself. bin/installfog.sh re-asserts it here for the
# same reason. Without this line the stale '/opt/fog' in the file below
# relocates the install mid-run -- which this test did on its first outing,
# writing a real /opt/fog/.fogsettings on the host and asserting against that.
fogprogramdir="$resolvedfogprogramdir"
eval "$seedblock"

is "${NET_hostname}"                  "fog.example.org"  "NET_hostname carried from hostname"
is "${NET_fog_server_ip}"             "10.0.0.5"         "NET_fog_server_ip carried from ipaddress"
is "${DB_password}"                   "dbsecret"         "DB_password carried from snmysqlpass"
is "${SVC_password}"                  "svcsecret"        "SVC_password carried from password (the OTHER secret)"
is "${STORAGE_image_share_path}"      "/images"          "STORAGE_image_share_path carried from storageLocation"
is "${WEB_root}"                      "/fog/"            "WEB_root carried from webroot"
is "${BOOT_external_tftp_server}"     ""                 "BOOT_external_tftp_server keeps noTftpBuild's polarity"
is "${PKI_san_dns_names}"             "fog-alt.example.org" "PKI_san_dns_names carried from extraServerNames"
is "${PKI_sb_codesign_key}"           "/opt/fog/pki/secureboot/.fogSB.key" "PKI_sb_codesign_key carried from secureBootKey"
# Deliberately NOT the '/opt/fog' the old file records: FOG_program_dir is a
# RECORD of wherever the install actually is, so it must follow the live
# variable and never the stale line it sits next to (GH-850).
is "${FOG_program_dir}"               "$fogprogramdir"   "FOG_program_dir records the LIVE path, not the stale line"

# The merges: two old keys, one answer.
# Whichever of the two the seed reads, it copies the value verbatim -- the seed
# block is a copy, never a translation. Normalizing the ENCODING to yes/no is a
# separate step (_normalizeBooleanSettings), asserted on the written file below.
is "${DHCP_enabled}"  "1"        "DHCP_enabled takes bldhcp's value, not dodhcp's"
is "${DHCP_router}"   "10.0.0.1" "DHCP_router takes the clean value"
is "${PKI_web_ca_cert}"   "/opt/fog/pki/web/ca/.fogWebCA.pem"  "PKI_web_ca_cert comes from FOG's own canonical path"
is "${PKI_web_vhost_cert}" "/opt/fog/pki/web/leaf/.webLeaf.pem" "PKI_web_vhost_cert comes from sslpubcert when no external leaf was recorded"

# A router the admin declined was stored as a literal config comment, which is
# why plainrouter existed at all. One key cannot hold both, so the comment must
# not survive into it.
( hostname=""; DHCP_router=""; plainrouter=""; routeraddress="#   No router address added"
  eval "$seedblock" >/dev/null 2>&1
  [[ -z ${DHCP_router} ]] ) \
    && ok "a declined router does not carry the config-comment sentinel into DHCP_router" \
    || bad "DHCP_router picked up the '#   No router address added' sentinel"

echo "== writeUpdateFile rewrites the file canonically =="

writeUpdateFile
NEW="$fogprogramdir/.fogsettings"

# Every old spelling is gone. .fogsettings is SOURCED, so a stale line is not
# cosmetic -- it is a live shell variable that later code may still read.
stale=""
for k in ipaddress ipaddresses copybackold interface submask hostname \
         routeraddress plainrouter dnsaddress username password osid osname \
         dodhcp bldhcp dhcpd dhcpengine blexports installtype snmysqlexternal \
         snmysqluser snmysqlpass snmysqlhost mysqldbname installlang \
         storageLocation fogupdateloaded docroot webroot caCreated httpproto \
         startrange endrange packages noTftpBuild tftpAdvOpts httpsRedirect \
         publicWebCert rebuildIpxeWithMyCA sslpath backupPath php_ver \
         sslprivkey sslcakey sslcapem sslcachain externalca extcacert extcakey \
         extcaroot sslcsr sslpubcert sendreports webserver webExtCACert \
         webExtCAKey webExtCARoot fogprogramdir secureBootKey secureBootCert \
         secureboot catrust secureBootMokCert fwconfigure fog_git_path \
         fog_update_channel extraServerNames acmeLeaf webCertFile webKeyFile \
         bootdelay kernelBackupGenerations netbootproto netbootProtoForced \
         rootCAPem rootCAKey internalDomains internalSubnets sbNameConstraints; do
    grep -qE "^${k}=" "$NEW" && stale="$stale $k"
done
is "$stale" "" "every pre-GH-1120 line was stripped"

# ...and every new key is present exactly once.
dupes=""
missing=""
while read -r k; do
    [[ -z $k ]] && continue
    n=$(grep -cE "^${k}=" "$NEW")
    [[ $n -eq 0 ]] && missing="$missing $k"
    [[ $n -gt 1 ]] && dupes="$dupes $k"
done < <(sed -n '/local -a managedKeys=(/,/^    )/p' "$FUNCS" \
    | sed -e 's/#.*$//' -e 's/local -a managedKeys=(//' -e 's/)//' \
    | tr -s ' \n' '\n\n' | grep -vE '^$')
is "$missing" "" "every managed key was written"
is "$dupes"   "" "no managed key was written twice"

# The values survived the round trip.
grep -qx "NET_hostname='fog.example.org'" "$NEW" \
    && ok "a carried value round-trips into the new file" \
    || bad "NET_hostname did not round-trip"
grep -qx "FOG_installed=1" "$NEW" \
    && ok "FOG_installed stays unquoted and numeric, as the format has always been" \
    || bad "FOG_installed was quoted"

# Booleans land in ONE encoding, whatever the old file used. The fixture above
# deliberately supplies all three: installlang='1', sendreports='Y',
# httpsRedirect='yes'. Asserted on the written file rather than on the seed
# block, because the seed only copies -- normalization is what converts.
for pair in "FOG_install_lang=yes:installlang='1'" \
            "FOG_send_reports=yes:sendreports='Y'" \
            "DB_external=no:snmysqlexternal='0'" \
            "PKI_sb_enabled=yes:secureboot='1'" \
            "DHCP_enabled=yes:bldhcp='1'" \
            "WEB_https_redirect=yes:httpsRedirect='yes'" \
            "PKI_web_cert_publicly_trusted=no:publicWebCert='no'" \
            "BOOT_url_proto_forced=no:netbootProtoForced='no'"; do
    want="${pair%%:*}"; from="${pair#*:}"
    grep -qx "${want%%=*}='${want#*=}'" "$NEW" \
        && ok "${want} (from ${from})" \
        || bad "${want} not written (from ${from}); got: $(grep -E "^${want%%=*}=" "$NEW" || echo MISSING)"
done

# And nothing else slipped through in an old encoding. Reads the real key list
# out of functions.sh so a key added to it later is covered without editing this.
badbool=""
while read -r k; do
    [[ -z $k ]] && continue
    line=$(grep -E "^${k}=" "$NEW") || continue
    case "$line" in
        "$k='yes'"|"$k='no'"|"$k=''") ;;
        *) badbool="$badbool [$line]" ;;
    esac
done < <(sed -n '/^_booleanSettingKeys()/,/^}/p' "$FUNCS" \
    | sed -e 's/^ *echo //' -e 's/\\$//' -e '/^_booleanSettingKeys/d' -e '/^}/d' \
    | tr -s ' \n' '\n\n' | grep -vE '^$')
is "$badbool" "" "every boolean key was written as yes/no (or empty)"

# What an upgrade must not eat. Hand-set keys work ONLY because the merge keeps
# lines it does not manage; a plain fresh write would silently drop all of this.
for k in inetConnectTimeout storageLocationCapture somethingFOGNeverWrote; do
    grep -qE "^${k}=" "$NEW" && ok "hand-set/unknown ${k} survived" \
        || bad "hand-set/unknown ${k} was eaten by the rewrite"
done
grep -qF "my own notes, do not delete" "$NEW" \
    && ok "the admin's own comment survived" \
    || bad "the admin's comment was discarded"

# The derived block is marked, and the marker sits above the canonical paths --
# not orphaned at the top or stranded after them.
if grep -qF "## Derived -- do not edit" "$NEW"; then
    ok "the '## Derived -- do not edit' marker is emitted"
    mark=$(grep -n "## Derived -- do not edit" "$NEW" | head -1 | cut -d: -f1)
    root=$(grep -n "^PKI_root_ca_cert=" "$NEW" | head -1 | cut -d: -f1)
    host=$(grep -n "^NET_hostname=" "$NEW" | head -1 | cut -d: -f1)
    [[ -n $mark && -n $root && $mark -lt $root ]] \
        && ok "...above the canonical certificate paths" \
        || bad "the Derived marker is not above PKI_root_ca_cert"
    [[ -n $mark && -n $host && $host -lt $mark ]] \
        && ok "...and below the ordinary settings" \
        || bad "the Derived marker is not below NET_hostname"
else
    bad "no '## Derived -- do not edit' marker was emitted"
fi
grep -qE "^## (FOG|NET|DHCP|DB|WEB|BOOT|STORAGE|SVC|PKI) --" "$NEW" \
    && ok "the file is emitted in commented category blocks" \
    || bad "no category block headers were emitted"

echo "== the migration is one-shot =="

# The seed block must not fire again. An admin who changes a value must not have
# the next upgrade put the old one back from a stale line -- which is the exact
# failure the httpsRedirect migration was shaped to avoid.
# shellcheck source=/dev/null
. "$NEW"
NET_hostname="changed.example.org"
eval "$seedblock"
is "${NET_hostname}" "changed.example.org" "a value changed after migrating is not overwritten by a re-run"

# And a second write is stable: same keys, same values, no growth. Restore the
# value the one-shot check above changed, or the diff reports that instead.
NET_hostname="fog.example.org"
writeUpdateFile
cp "$NEW" "$WORK/first"
writeUpdateFile
if diff -q <(grep -vE '^## (Version|Install time):' "$WORK/first") \
           <(grep -vE '^## (Version|Install time):' "$NEW") >/dev/null; then
    ok "a second writeUpdateFile run is a no-op"
else
    bad "the second write changed the file"
    diff <(grep -vE '^## (Version|Install time):' "$WORK/first") \
         <(grep -vE '^## (Version|Install time):' "$NEW") | head -20
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
