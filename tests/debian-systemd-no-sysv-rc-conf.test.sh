#!/bin/bash
#
# A Debian host that boots with systemd must not be asked for sysv-rc-conf.
#
#   tests/debian-systemd-no-sysv-rc-conf.test.sh
#
# On Debian 12 (bookworm) sysv-rc-conf depends on sysvinit-core, which
# conflicts with systemd-sysv. On a standard Debian 12 server the package can
# never be installed: the batch install fails, the per-package retry fails,
# and the installer stops at "Checking package: sysv-rc-conf ... Failed!".
# #530 had already dropped sysv-rc-conf from the Debian list for this reason;
# the 1.6 line brought it back for every Debian below 13 (#797 only gated 13+).
#
# sysv-rc-conf is only ever called on the non-systemd path, so the rule is:
# a systemd host never gets it, whatever the release number.
#
# lib/ubuntu/config.sh is SOURCED here with stand-in values. On the Debian
# branch it only assigns variables, so this needs no root, no apt, no network.
#
# Usage: bash tests/debian-systemd-no-sysv-rc-conf.test.sh
# Exit status 0 = pass, 1 = fail.

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
config="$root/lib/ubuntu/config.sh"

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

if [[ ! -r $config ]]; then
    echo "FAIL: cannot read $config" >&2
    exit 1
fi

# packages <distro> <version> <systemctl> [cached FOG_packages]
# Prints the FOG_packages the config computes, in a clean subshell.
packages() {
    (
        unset FOG_packages sysvrcconf
        linuxReleaseName_lower=$1
        OSVersion=$2
        systemctl=$3
        [[ -n $4 ]] && FOG_packages=$4
        packageQuery=false
        error_log=/dev/null
        . "$config" >/dev/null 2>&1
        printf '%s' " $FOG_packages "
    )
}

# ---------------------------------------------------------------------------
# Fresh install.
list=$(packages debian 12 yes)
check "Debian 12 with systemd: no sysv-rc-conf" \
    "$([[ $list != *" sysv-rc-conf "* ]]; echo $?)"
check "Debian 12 with systemd: the rest of the list is kept" \
    "$([[ $list == *" tftpd-hpa "* && $list == *" sudo "* ]]; echo $?)"

list=$(packages debian 11 yes)
check "Debian 11 with systemd: no sysv-rc-conf" \
    "$([[ $list != *" sysv-rc-conf "* ]]; echo $?)"

list=$(packages debian 13 yes)
check "Debian 13: no sysv-rc-conf (#797)" \
    "$([[ $list != *" sysv-rc-conf "* ]]; echo $?)"

# A Debian 12 box booted with sysvinit still calls sysv-rc-conf, so it keeps it.
list=$(packages debian 12 "")
check "Debian 12 without systemd: sysv-rc-conf is kept" \
    "$([[ $list == *" sysv-rc-conf "* ]]; echo $?)"

# ---------------------------------------------------------------------------
# Rerun or upgrade: a FOG_packages recorded in .fogsettings is reused, so a
# server whose first run failed here must not carry the name forward.
list=$(packages debian 12 yes "sudo sysv-rc-conf tar")
check "Debian 12 with systemd: stripped from a cached list (got '$list')" \
    "$([[ $list != *"sysv-rc-conf"* && $list == *" sudo "* && $list == *" tar "* ]]; echo $?)"

echo "ok $pass"
[[ $fail -eq 0 ]] || { echo "FAIL ($fail of $((pass + fail)))" >&2; exit 1; }
exit 0
