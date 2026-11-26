#!/bin/bash
#
# Pins that $fogprogramdir/bin is traversable by $apacheuser after the two
# helpers the web tier gates on are installed, whatever the umask.
#
#   tests/fog-bin-traversable-by-web-user.test.sh
#
# Forum 18336. FOGConfigurationPage::_pkiRun() and
# FOGPage::secureBootStagingDir() test is_executable() on a helper in
# $fogprogramdir/bin as $apacheuser, before any sudo. Under umask 027,
# mkdir -p left that directory 0750 root:root. The test failed, so the
# Certificates page said the helper was not installed (the installer had
# printed Done), and kernels downloaded unsigned with no error.
#
# Runs _installPkiAdminHelper and _installSecureBootSigner under umask 027.
# No root: install(1) is stubbed to fail, and the chmod under test runs
# before it. It checks the "other" x bit, which is what a non-owner,
# non-group account gets.
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
setSELinuxContext() { :; }
_secureBootCertPem() { echo pem; }
# Root-only in real life; failing here ends each function right after the
# directory work, which is the part under test.
install() { return 1; }

umask 027
error_log="$WORK/error.log"
apacheuser=nobody
FOG_install_type=N
PKI_sb_codesign_key=/nonexistent.key
PKI_sb_codesign_cert=/nonexistent.pem

# Each helper alone, so neither passes on the other's chmod.
check() {
    local fn=$1 mode
    fogprogramdir="$WORK/$fn/opt/fog"
    mkdir -p "$fogprogramdir"
    "$fn" >/dev/null 2>&1
    mode=$(stat -c %A "$fogprogramdir/bin" 2>/dev/null) || { bad "$fn: bin missing"; return; }
    [[ ${mode:9:1} == x ]] && ok "$fn: other has x on bin ($mode)" \
        || bad "$fn: other lacks x on bin ($mode)"
    [[ ${mode:7:1} != r ]] && ok "$fn: other cannot list bin ($mode)" \
        || bad "$fn: other can list bin ($mode)"
}

check _installPkiAdminHelper
check _installSecureBootSigner

echo
echo "$PASS passed, $FAIL failed"
(( FAIL == 0 ))
