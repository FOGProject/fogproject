#!/bin/bash
#
# Replays an upgrade from FOG 1.5 on the filesystem: /etc/fog is the 1.5
# symlink to $servicedst/etc, and the installer's own functions run in install
# order -- the PKI migration (reached through _pkiZoneDir, as configureHttpd
# reaches it), then linkOptFogDir.
#
#   tests/upgrade-from-15-etc-fog.test.sh
#
# The upgrade rehearsal (upgrade-rehearsal-ci.test.sh) replays the SCHEMA only.
# Nothing replayed the filesystem, so this ordering bug shipped: the migration
# wrote /etc/fog/pki through the 1.5 link into $servicedst/etc/pki, and
# linkOptFogDir then replaced the link with an empty /etc/fog. The root CA key,
# both intermediates and the web leaf sat in /opt/fog/service/etc/pki where
# nothing looked. Reported from a live server: Apache failed on its next start
# with "SSLCertificateFile: file '/opt/fog/pki/web/leaf/.webLeaf.pem' does not
# exist or is empty".
#
# Also covers the server that was already hit: /etc/fog real, the tree orphaned
# in $servicedst/etc/pki, and a later run's fresh partial tree in /etc/fog/pki.
#
# /etc, /opt and /var/log are tmpfs mounts in a private user and mount
# namespace, so linkOptFogDir runs exactly as shipped, hardcoded paths and all.
# Needs unshare(1) with unprivileged user namespaces, or root (which CI is).
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

if [[ ${FOG_ETCFOG_TEST_INNER:-0} -ne 1 ]]; then
    if [[ $EUID -ne 0 ]]; then
        command -v unshare >/dev/null 2>&1 \
            || { echo "SKIP: not root and unshare(1) is unavailable"; exit 0; }
        unshare -rm true >/dev/null 2>&1 \
            || { echo "SKIP: unprivileged user namespaces are unavailable"; exit 0; }
        exec unshare -rm env FOG_ETCFOG_TEST_INNER=1 bash "${BASH_SOURCE[0]}" "$@"
    fi
    export FOG_ETCFOG_TEST_INNER=1
    command -v unshare >/dev/null 2>&1 && exec unshare -m bash "${BASH_SOURCE[0]}" "$@"
fi

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# Sourced before the mounts: nothing after this point needs the host's /etc.
error_log=/dev/null
. "$FUNCS" >/dev/null 2>&1
dots() { :; }
errorStat() { :; }

for d in /etc /opt /var/log; do
    mount -t tmpfs none "$d" >/dev/null 2>&1 \
        || { echo "SKIP: could not place a tmpfs over $d"; exit 0; }
done

fogprogramdir=/opt/fog
servicedst=/opt/fog/service
servicelogs=/opt/fog/log
PKI_root_dir=/etc/fog/pki
WEB_server_engine=apache2

reset() {
    rm -rf /etc/fog /opt/fog /var/log/fog
    mkdir -p "$servicedst/etc" "$servicelogs"
    echo "<?php // service config" > "$servicedst/etc/config.php"
}

echo "== A: an upgrade from 1.5, replayed in install order =="
reset
ln -s "$servicedst/etc" /etc/fog
mkdir -p /opt/fog/pki/root/ca /opt/fog/pki/web/leaf
echo "root-ca-key" > /opt/fog/pki/root/ca/.fogCA.key
echo "web-leaf" > /opt/fog/pki/web/leaf/.webLeaf.pem
_pkiZoneDir root >/dev/null 2>&1
linkOptFogDir >/dev/null 2>&1

is "$(cat /etc/fog/pki/root/ca/.fogCA.key 2>/dev/null)" "root-ca-key" \
    "A: the root CA key is at /etc/fog/pki after linkOptFogDir"
is "$(cat /opt/fog/pki/web/leaf/.webLeaf.pem 2>/dev/null)" "web-leaf" \
    "A: the web leaf still reads at the path the vhost names"
[[ -d /etc/fog && ! -L /etc/fog ]] && ok "A: /etc/fog is a real directory" \
    || bad "A: /etc/fog is a real directory"
[[ ! -e $servicedst/etc/pki ]] && ok "A: nothing is left behind in \$servicedst/etc" \
    || bad "A: a tree was left behind in \$servicedst/etc"
is "$(cat /etc/fog/config.php 2>/dev/null)" "<?php // service config" \
    "A: /etc/fog/config.php still reaches the service config"

echo "== B: a server already hit by the bug heals on its next run =="
reset
mkdir -p /etc/fog/pki/root/ca /etc/fog/pki/secureboot/ca
ln -s /opt/fog/snapins/ssl/CA/.fogCA.pem /etc/fog/pki/root/ca/.fogCA.pem
ln -s /etc/fog/pki /opt/fog/pki
orphan="$servicedst/etc/pki"
mkdir -p "$orphan/root/ca" "$orphan/web/leaf" "$orphan/secureboot/ca"
ln -s /opt/fog/snapins/ssl/CA/.fogCA.pem "$orphan/root/ca/.fogCA.pem"
echo "root-ca-key" > "$orphan/root/ca/.fogCA.key"
echo "web-leaf" > "$orphan/web/leaf/.webLeaf.pem"
echo "sb-ca" > "$orphan/secureboot/ca/.fogSBCA.pem"
mkdir -p "$servicedst/etc/customizations"
echo "custom" > "$servicedst/etc/customizations/boot.ipxe"
_pkiZoneDir root >/dev/null 2>&1

is "$(cat /etc/fog/pki/root/ca/.fogCA.key 2>/dev/null)" "root-ca-key" "B: the root CA key is restored"
is "$(cat /etc/fog/pki/web/leaf/.webLeaf.pem 2>/dev/null)" "web-leaf" "B: the web leaf is restored"
is "$(cat /etc/fog/pki/secureboot/ca/.fogSBCA.pem 2>/dev/null)" "sb-ca" \
    "B: the Secure Boot CA is restored into the directory the later run created"
is "$(cat /etc/fog/customizations/boot.ipxe 2>/dev/null)" "custom" "B: customizations are restored too"
[[ ! -e $orphan ]] && ok "B: the orphan is removed once every file is copied" \
    || bad "B: the orphan was left behind although every file was copied"

echo "== C: a file a later run replaced is kept, and so is the old tree =="
reset
mkdir -p /etc/fog/pki/secureboot/ca
echo "new-sb-ca" > /etc/fog/pki/secureboot/ca/.fogSBCA.pem
mkdir -p "$orphan/root/ca" "$orphan/secureboot/ca"
echo "root-ca-key" > "$orphan/root/ca/.fogCA.key"
echo "old-sb-ca" > "$orphan/secureboot/ca/.fogSBCA.pem"
_pkiZoneDir root >/dev/null 2>&1

is "$(cat /etc/fog/pki/secureboot/ca/.fogSBCA.pem 2>/dev/null)" "new-sb-ca" \
    "C: the live file is never overwritten"
is "$(cat /etc/fog/pki/root/ca/.fogCA.key 2>/dev/null)" "root-ca-key" "C: missing files are still restored"
kept=$(find "$servicedst/etc" -maxdepth 1 -name 'pki.recovered-*' | wc -l)
is "$kept" "1" "C: the differing old tree is renamed aside, not deleted"

echo "== D: an /etc/fog link the admin pointed elsewhere is not touched =="
reset
mkdir -p /opt/admin-etc
ln -s /opt/admin-etc /etc/fog
_adoptServiceEtcDir /etc/fog/pki 2>/dev/null
is "$(readlink /etc/fog)" "/opt/admin-etc" "D: the admin's link is left as it was"

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
