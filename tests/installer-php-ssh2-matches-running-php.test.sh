#!/bin/bash
#
# The installer must install the ssh2 build for the PHP that php-fpm runs.
#
#   tests/installer-php-ssh2-matches-running-php.test.sh
#
# Forum 18289. On Debian-family systems php-ssh2 is a metapackage that names
# one php<ver>-ssh2 build. ondrej's PPA, which Linux Mint installs get, maps
# php-fpm to php8.4-fpm but php-ssh2 to php8.5-ssh2. The extension landed on
# PHP 8.5, FPM ran 8.4, and a snapin upload died with:
#
#   Call to undefined function FOG\Net\ssh2_connect()
#
# matchPhpSsh2ToRunningPhp() is EXECUTED here with a stand-in package
# installer, not read. The call site is checked textually, because a correct
# helper that nothing calls leaves the bug in place.
#
# No root, no network, no apt, no FOG install.
#
# Usage: bash tests/installer-php-ssh2-matches-running-php.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
functions="$root/lib/common/functions.sh"

pass=0
fail=0

check() {
    if [[ $2 -eq 0 ]]; then
        pass=$((pass + 1))
    else
        fail=$((fail + 1))
        printf '  FAIL  %s\n' "$1"
    fi
}

if [[ ! -r $functions ]]; then
    echo "FAIL: cannot read $functions" >&2
    exit 1
fi

# Lifted rather than sourced, as in installer-epel-already-provided.test.sh.
lift() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "$functions"
}

snippet=$(lift matchPhpSsh2ToRunningPhp)
if ! grep -q '^matchPhpSsh2ToRunningPhp() {' <<< "$snippet"; then
    echo "FAIL: could not find matchPhpSsh2ToRunningPhp() in lib/common/functions.sh." >&2
    echo "  If it moved or was renamed, point this test at it -- do not" >&2
    echo "  delete the assertions." >&2
    exit 1
fi
eval "$snippet"

dots() { :; }
installed=""
fakeinstall() { installed="$*"; return 0; }
packageinstaller=fakeinstall
error_log=/dev/null

# ---------------------------------------------------------------------------
# Debian family: the metapackage is swapped for the versioned build.
FOG_os_id=2
WEB_php_version=8.4
FOG_packages="php-fpm php-ssh2 sudo "
installed=""
matchPhpSsh2ToRunningPhp >/dev/null
check "installs php8.4-ssh2 for PHP 8.4 (got '$installed')" \
    "$([[ $installed == php8.4-ssh2 ]]; echo $?)"
check "FOG_packages names php8.4-ssh2 for the confirm step" \
    "$([[ " $FOG_packages " == *" php8.4-ssh2 "* ]]; echo $?)"
check "FOG_packages no longer names the php-ssh2 metapackage" \
    "$([[ " $FOG_packages " != *" php-ssh2 "* ]]; echo $?)"
check "the other packages are kept" \
    "$([[ " $FOG_packages " == *" php-fpm "* && " $FOG_packages " == *" sudo "* ]]; echo $?)"

# The metapackage at either end of the list is swapped too.
FOG_packages="php-ssh2"
matchPhpSsh2ToRunningPhp >/dev/null
check "a lone php-ssh2 is swapped (got '$FOG_packages')" \
    "$([[ " $FOG_packages " == *" php8.4-ssh2 "* && " $FOG_packages " != *" php-ssh2 "* ]]; echo $?)"

# ---------------------------------------------------------------------------
# RHEL family: php-pecl-ssh2 is not versioned, so nothing happens.
FOG_os_id=1
FOG_packages="php-fpm php-pecl-ssh2 "
installed=""
matchPhpSsh2ToRunningPhp >/dev/null
check "does nothing off the Debian family" \
    "$([[ -z $installed && $FOG_packages == "php-fpm php-pecl-ssh2 " ]]; echo $?)"

# No measured PHP version: nothing to match against.
FOG_os_id=2
WEB_php_version=""
FOG_packages="php-ssh2 "
installed=""
matchPhpSsh2ToRunningPhp >/dev/null
check "does nothing without a measured PHP version" \
    "$([[ -z $installed && $FOG_packages == "php-ssh2 " ]]; echo $?)"

# ---------------------------------------------------------------------------
# Wiring: installPackages calls it after WEB_php_version is measured.
body=$(lift installPackages)
after=$(awk '/export WEB_php_version=/ { seen = 1 } seen' <<< "$body")
check "installPackages calls matchPhpSsh2ToRunningPhp after measuring PHP" \
    "$(grep -q '^ *matchPhpSsh2ToRunningPhp$' <<< "$after"; echo $?)"

echo "ok $pass"
[[ $fail -eq 0 ]] || { echo "FAIL ($fail of $((pass + fail)))" >&2; exit 1; }
exit 0
