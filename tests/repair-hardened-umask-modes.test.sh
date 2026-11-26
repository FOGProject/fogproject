#!/bin/bash
#
# Pins _repairHardenedUmaskModes: an upgrade over an install made under
# umask 027 opens the listed paths to the modes a default install has, and
# leaves every path that is closed on purpose closed.
#
#   tests/repair-hardened-umask-modes.test.sh
#
# Forum 18335/18336. installfog.sh now sets umask 022, but a umask applies
# only when a path is created. mkdir -p and `>` keep an existing path's mode,
# so /etc/fog and the rest stayed 0750/0640 after an upgrade. The modes built
# below are the ones a Debian 13 install under umask 027 left.
#
# No root. Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0 FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# The function names /home/${SVC_user}; SVC_user below resolves that through
# /home/.. into $WORK, so the shipped text runs unchanged.
[[ -d /home ]] || { echo "ERROR: no /home on this system" >&2; exit 1; }

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

PKI_root_dir="$WORK/etc/fog/pki"
error_log="$WORK/error.log"
fogprogramdir="$WORK/opt/fog"
snapindir="$fogprogramdir/snapins"
servicelogs="$fogprogramdir/log"
webdirdest="$WORK/var/www/html/fog/"
# The RHEL-family layout, where these are two files.
WEB_docroot="$WORK/var/www/html/"
webredirect="${webdirdest}index.php"
SVC_user="..$WORK/home/fogproject"
home="$WORK/home/fogproject"

# path mode: create it with that mode. A trailing / makes a directory.
mk() {
    if [[ $1 == */ ]]; then
        mkdir -p "$1"
    else
        mkdir -p "$(dirname "$1")"
        : > "$1"
    fi
    chmod "$2" "$1"
}
# The 027 install, as measured.
mk "$WORK/etc/fog/" 750
mk "$WORK/etc/fog/pki/" 755
mk "$WORK/etc/fog/pki/agent/" 750
mk "$WORK/etc/fog/pki/agent/ca/" 700
mk "$WORK/etc/fog/pki/agent/ca/int.csr" 640
mk "$WORK/etc/fog/pki/agent/ca/.fogAgentCA.key" 600
mk "$WORK/etc/fog/pki/client/" 750
mk "$WORK/etc/fog/pki/client/leaf/" 710
mk "$WORK/etc/fog/pki/client/leaf/fog.csr" 640
mk "$WORK/etc/fog/pki/client/leaf/.srvprivate.key" 640
mk "$WORK/etc/fog/pki/root/" 750
mk "$WORK/etc/fog/pki/root/ca/.fogCA.key" 400
mk "$WORK/etc/fog/pki/web/" 750
mk "$WORK/etc/fog/pki/web/ca/.trustAnchor.pem" 640
mk "$WORK/etc/fog/pki/web/ca/.fogWebCA.srl" 640
mk "$WORK/etc/fog/pki/web/leaf/.webLeaf.sans" 640
mk "$WORK/etc/fog/pki/web/leaf/.webLeaf.key" 600
mk "$WORK/etc/fog/ssh/" 750
mk "$WORK/etc/fog/ssh/fog_ssh_key" 640
mk "$snapindir/ssl/CA/root.cnf" 640
chmod 750 "$snapindir/ssl" "$snapindir/ssl/CA"
mk "$fogprogramdir/customizations/kernel-backups/gen-1/bzImage" 640
mk "$fogprogramdir/customizations/ipxe-bg/bg.png" 640
chmod 750 "$fogprogramdir/customizations" "$fogprogramdir/customizations/kernel-backups" \
    "$fogprogramdir/customizations/ipxe-bg"
mk "$fogprogramdir/pkiadmin-staging/" 750
mk "$servicelogs/plugins/" 750
mk "$servicelogs/faults/" 750
# A cache file root wrote, and one the web user wrote. Only root's goes.
mk "$fogprogramdir/cache/settings.cache.json" 600
mk "$fogprogramdir/cache/web.cache.json" 600
chmod 1777 "$fogprogramdir/cache"
(( EUID == 0 )) && chown nobody "$fogprogramdir/cache/web.cache.json"
mk "${webdirdest}management/other/agent-ca-bundle.pem" 640
mk "${webdirdest}commons/config.class.php" 640
mk "$webredirect" 640
mk "$home/.bashrc" 640
mk "$home/.config/autostart/warnfogaccount.desktop" 640
mk "${WEB_docroot}index.php" 640
chmod 750 "$home" "$home/.config" "$home/.config/autostart"

_repairHardenedUmaskModes

is() {
    local mode
    mode=$(stat -c %a "$1" 2>/dev/null) || { bad "${1#$WORK/} missing"; return; }
    [[ $mode == "$2" ]] && ok "${1#$WORK/} is $2 ($3)" || bad "${1#$WORK/} is $mode, want $2 ($3)"
}

# Opened to the default-install mode.
is "$WORK/etc/fog" 755 opened
is "$WORK/etc/fog/pki/agent" 755 opened
is "$WORK/etc/fog/pki/client" 755 opened
is "$WORK/etc/fog/pki/root" 755 opened
is "$WORK/etc/fog/pki/web" 755 opened
is "$WORK/etc/fog/pki/agent/ca/int.csr" 644 opened
is "$WORK/etc/fog/pki/client/leaf/fog.csr" 644 opened
is "$WORK/etc/fog/pki/web/ca/.trustAnchor.pem" 644 opened
is "$WORK/etc/fog/pki/web/ca/.fogWebCA.srl" 644 opened
is "$WORK/etc/fog/pki/web/leaf/.webLeaf.sans" 644 opened
is "$snapindir/ssl" 755 opened
is "$snapindir/ssl/CA" 755 opened
is "$snapindir/ssl/CA/root.cnf" 644 opened
is "$fogprogramdir/customizations" 755 opened
is "$fogprogramdir/customizations/kernel-backups" 755 opened
is "$fogprogramdir/customizations/kernel-backups/gen-1/bzImage" 644 opened
is "$fogprogramdir/customizations/ipxe-bg/bg.png" 644 opened
is "$servicelogs/plugins" 755 opened
is "${webdirdest}management/other/agent-ca-bundle.pem" 644 opened
is "$webredirect" 644 opened
is "$home/.bashrc" 644 opened
is "$home" 755 opened
is "${WEB_docroot}index.php" 644 opened
is "$home/.config" 755 opened
is "$home/.config/autostart/warnfogaccount.desktop" 644 opened

# Closed on purpose, and left closed.
is "$WORK/etc/fog/pki/agent/ca" 700 "kept closed"
is "$WORK/etc/fog/pki/agent/ca/.fogAgentCA.key" 600 "kept closed"
is "$WORK/etc/fog/pki/client/leaf" 710 "kept closed"
is "$WORK/etc/fog/pki/client/leaf/.srvprivate.key" 640 "kept closed"
is "$WORK/etc/fog/pki/root/ca/.fogCA.key" 400 "kept closed"
is "$WORK/etc/fog/pki/web/leaf/.webLeaf.key" 600 "kept closed"
is "$WORK/etc/fog/ssh" 750 "kept closed"
is "$WORK/etc/fog/ssh/fog_ssh_key" 640 "kept closed"
is "$fogprogramdir/pkiadmin-staging" 750 "kept closed"
is "$servicelogs/faults" 750 "kept closed"
is "${webdirdest}commons/config.class.php" 640 "kept closed"
is "$fogprogramdir/cache/web.cache.json" 600 "a non-root cache file is kept"
# As a non-root runner, the "root" file is this user's, so it stays.
if (( EUID == 0 )); then
    [[ ! -e $fogprogramdir/cache/settings.cache.json ]] \
        && ok "a root-owned cache file is removed" \
        || bad "a root-owned cache file was kept"
fi

# _openWebDownloads, which configureHttpd runs on every install: the
# downloaded boot files and client installers, cached 0640 by a 027 install.
mk "${webdirdest}service/ipxe/bzImage" 640
mk "${webdirdest}client/FOGService.msi" 640
_openWebDownloads
is "${webdirdest}service/ipxe/bzImage" 644 opened
is "${webdirdest}client/FOGService.msi" 644 opened

# Symlinks planted by an account that can write the parent. The repair runs
# as root, and chmod follows a link it is given, so each would open the link's
# target. This test runs as a non-root user, so every parent here is
# non-root-owned, which is the case _rootSafePath must refuse.
ATK="$WORK/attack"
mkdir -p "$ATK"
mk "$ATK/shadow" 600
mk "$ATK/keydir/" 700
fogprogramdir="$ATK/opt/fog"
snapindir="$fogprogramdir/snapins"
servicelogs="$fogprogramdir/log"
webdirdest="$ATK/var/www/html/fog/"
webredirect="$ATK/var/www/html/index.php"
SVC_user="..$ATK/home/fogproject"
PKI_root_dir="$ATK/etc/fog/pki"
mkdir -p "${webdirdest}management/other" "${webdirdest}service/ipxe" "$snapindir" \
    "$ATK/home/fogproject/.config/autostart"
ln -s "$ATK/shadow" "${webdirdest}management/other/agent-ca-bundle.pem"
ln -s "$ATK/shadow" "$ATK/home/fogproject/.bashrc"
ln -s "$ATK/shadow" "${webdirdest}service/ipxe/bzImage"
ln -s "$ATK/keydir" "$snapindir/ssl"
ln -s "$ATK/keydir" "$fogprogramdir/customizations"
ln -s "$ATK/keydir" "${webdirdest}client"
# As root, mkdir made these parents root-owned, which the guard trusts. Hand
# them to an unprivileged owner, as www-data and fogproject own them on a server.
(( EUID == 0 )) && chown -R nobody "$ATK"

_repairHardenedUmaskModes
_openWebDownloads

is "$ATK/shadow" 600 "link target kept closed"
is "$ATK/keydir" 700 "link target kept closed"

# This block stands in for a link swapped in after _rootSafePath passes. The
# stub guard passes every path. The chmod must not follow a link to a file.
# chmod -R -P must not follow a link to a tree. At a single-directory site, a
# link to a directory opens that directory's listing, by design.
SWP="$WORK/swap"
mkdir -p "$SWP"
mk "$SWP/shadow" 600
mk "$SWP/keytree/key" 600
chmod 700 "$SWP/keytree"
fogprogramdir="$SWP/opt/fog"
snapindir="$fogprogramdir/snapins"
servicelogs="$fogprogramdir/log"
webdirdest="$SWP/var/www/html/fog/"
webredirect="$SWP/var/www/html/index.php"
SVC_user="..$SWP/home/fogproject"
PKI_root_dir="$SWP/etc/fog/pki"
mkdir -p "${webdirdest}management/other" "${webdirdest}service" "$fogprogramdir" \
    "$SWP/home/fogproject/.config"
ln -s "$SWP/shadow" "${webdirdest}management/other/agent-ca-bundle.pem"
ln -s "$SWP/shadow" "$SWP/home/fogproject/.config/autostart"
ln -s "$SWP/keytree" "${webdirdest}service/ipxe"
ln -s "$SWP/keytree" "$fogprogramdir/customizations"
_rootSafePath() { [[ $1 == /* && -e $1 ]]; }
_repairHardenedUmaskModes
_openWebDownloads
. "$FUNCS" >/dev/null 2>&1

# Only where chmod has -P. Elsewhere _chmodNoFollow falls back, by design.
# The test's own probe, so a broken _chmodHasNoFollow cannot skip these.
if chmod -R -P --version >/dev/null 2>&1; then
    _chmodHasNoFollow && ok "_chmodHasNoFollow finds -P" \
        || bad "_chmodHasNoFollow misses -P, which this chmod has"
    is "$SWP/shadow" 600 "swapped-in link target kept closed"
    is "$SWP/keytree" 700 "swapped-in link target kept closed"
    is "$SWP/keytree/key" 600 "swapped-in link target kept closed"
else
    echo "  skip  this chmod has no -P, so a swapped-in link is followed"
fi

# The guard trusts a link that root placed in a root-only directory. Without
# that, an admin's /var/www -> /srv/www would undo the whole repair. On a
# merged-/usr system, /bin/sh is a link inside the linked /bin. So this also
# checks a trusted link as a parent.
if [[ -L /bin && $(stat -c %u /) == 0 ]]; then
    _rootSafePath /bin/sh && ok "_rootSafePath trusts /bin/sh, links root placed" \
        || bad "_rootSafePath refused /bin/sh, links root placed"
else
    echo "  skip  no root-placed /bin link on this system"
fi

# Order: the repair must run before the steps that mkdir -p or `>` into these
# paths, or they keep the old mode. In installfog.sh it follows configureUsers
# and precedes every configureHttpd, configureMinHttpd and installFOGServices.
INSTALLER="$REPO/bin/installfog.sh"
repair=$(grep -n '^ *_repairHardenedUmaskModes$' "$INSTALLER" | head -1 | cut -d: -f1)
users=$(grep -n '^ *configureUsers$' "$INSTALLER" | tail -1 | cut -d: -f1)
first=$(grep -nE '^ *(configureHttpd|configureMinHttpd|installFOGServices)$' "$INSTALLER" \
    | head -1 | cut -d: -f1)
if [[ -n $repair && -n $users && -n $first ]] && (( users < repair && repair < first )); then
    ok "installfog.sh calls the repair after configureUsers, before the web and service steps"
else
    bad "installfog.sh call order: configureUsers=$users repair=$repair first step=$first"
fi

echo
echo "$PASS passed, $FAIL failed"
(( FAIL == 0 ))
