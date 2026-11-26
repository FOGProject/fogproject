#!/bin/bash
#
# Pins that the installed daemon tree is readable by $apacheuser, whatever
# modes the checkout carries.
#
#   tests/service-tree-readable-by-web-user.test.sh
#
# Forum 18335. FOGPluginRunner, FOGRetentionRunner and FOGAgentReleaseSync run
# as the web user (ADR 0010). installFOGServices copies packages/service with
# cp -R, which keeps the source modes. A checkout made under umask 027 is
# 0750/0640, so the installed tree was root-only. The root daemons started;
# those three restarted forever on "Could not open input file".
#
# Runs installFOGServices and configureFOGService for real, against a source
# tree built 0750/0640 under umask 027. No root, no network. It checks the
# "other" bits, which is what a non-owner, non-group account gets.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

PASS=0 FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
dots() { :; }
errorStat() { :; }
setSELinuxContext() { :; }
startInitScript() { :; }

umask 027
error_log="$WORK/error.log"
apacheuser=nobody
servicesrc="$WORK/src/service"
fogprogramdir="$WORK/opt/fog"
servicedst="$fogprogramdir/service"
servicelogs="$WORK/log/fog"
webdirdest=/var/www/fog

# The checkout, as a hardened umask leaves it.
mkdir -p "$servicesrc/FOGPluginRunner" "$servicesrc/lib"
echo '<?php' > "$servicesrc/FOGPluginRunner/FOGPluginRunner"
echo '<?php' > "$servicesrc/lib/service_lib.php"
chmod 0750 "$servicesrc/FOGPluginRunner" "$servicesrc/lib"
chmod 0640 "$servicesrc/FOGPluginRunner/FOGPluginRunner" "$servicesrc/lib/service_lib.php"

# A private file already in etc/, as 1.5 left pki/ there. It must stay private.
mkdir -p "$servicedst/etc/pki"
echo key > "$servicedst/etc/pki/private.key"
chmod 0700 "$servicedst/etc/pki"
chmod 0600 "$servicedst/etc/pki/private.key"

installFOGServices >/dev/null 2>&1
configureFOGService >/dev/null 2>&1

# "Other" may do $2 (r, x, or rx) on $1.
other_can() {
    local mode
    mode=$(stat -c %A "$1" 2>/dev/null) || { bad "$1 missing"; return; }
    case $2 in
        x)  [[ ${mode:9:1} == x ]] ;;
        r)  [[ ${mode:7:1} == r ]] ;;
        rx) [[ ${mode:7:1} == r && ${mode:9:1} == x ]] ;;
    esac && ok "other has $2 on ${1#$WORK/} ($mode)" || bad "other lacks $2 on ${1#$WORK/} ($mode)"
}

other_can "$WORK/opt/fog" x
other_can "$servicedst" rx
other_can "$servicedst/FOGPluginRunner" rx
other_can "$servicedst/FOGPluginRunner/FOGPluginRunner" r
other_can "$servicedst/lib" rx
other_can "$servicedst/lib/service_lib.php" r
other_can "$servicedst/etc" x
other_can "$servicedst/etc/config.php" r
other_can "$servicelogs" x
# A host directory above a custom log path is not FOG's to open up.
mode=$(stat -c %A "$WORK/log")
[[ ${mode:9:1} == - ]] && ok "host parent of servicelogs left alone ($mode)" \
    || bad "host parent of servicelogs was opened ($mode)"

mode=$(stat -c %a "$servicedst/etc/pki/private.key")
[[ $mode == 600 ]] && ok "etc/pki/private.key stays 600" || bad "etc/pki/private.key became $mode"
mode=$(stat -c %a "$servicedst/etc/pki")
[[ $mode == 700 ]] && ok "etc/pki stays 700" || bad "etc/pki became $mode"

echo
echo "$PASS passed, $FAIL failed"
(( FAIL == 0 ))
