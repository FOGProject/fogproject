#!/bin/bash
#
# Pins sudo as a package the installer adds on every run, not only on a fresh
# install.
#
#   tests/sudo-is-a-baseline-package.test.sh
#
# Three helpers install /etc/sudoers.d drop-ins, and the web tier reaches them
# through sudo. An upgrade reuses the FOG_packages its .fogsettings recorded,
# so the distro lists in lib/*/config.sh never reach it. A server first
# installed before sudo was needed had no /etc/sudoers.d, and every drop-in
# write failed: "/etc/sudoers.d/fog-pki.tmp: No such file or directory".
#
# Static: reads lib/common/functions.sh. No install, no network, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FUNCS="$(cd "$HERE/.." && pwd)/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

# Prints the unconditional, top-level lines of function $1: indented by exactly
# four spaces, so a line inside an if/case arm does not count.
body() {
    awk -v fn="$1" '$0 ~ "^" fn "\\(\\) \\{" { on = 1; next } on && /^}/ { exit } on && /^    [^ ]/' "$FUNCS"
}

FAIL=0
for fn in installPackages listPackages; do
    if body "$fn" | grep -Eq '^    FOG_packages="\$\{FOG_packages\}( [-a-z0-9]+)* sudo( |")'; then
        echo "  ok    $fn adds sudo unconditionally"
    else
        echo "  FAIL  $fn does not add sudo on every run"
        FAIL=1
    fi
done
exit $FAIL
