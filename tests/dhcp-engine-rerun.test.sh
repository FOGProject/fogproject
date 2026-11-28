#!/bin/bash
#
# Guards the Kea engine choice across installer re-runs (GH-1747).
#
#   tests/dhcp-engine-rerun.test.sh
#
# resolveDHCPEngine() swaps the ISC package for the Kea package in
# ${FOG_packages}, and the installer saves that list. It does not save
# $dhcpname or $dhcpconfig, so the next run re-seeds both from the distro
# config: the ISC package name and the ISC config path. The guard looked only
# for the ISC name in the saved list. Every re-run of a Kea install therefore
# returned early and kept the ISC path. On Ubuntu that wrote the Kea JSON to
# /etc/dhcp3/dhcpd.conf, AppArmor refused kea-dhcp4 the read, and the install
# stopped before TFTP.
#
# The first run always worked, which is why nobody saw it. So this replays two
# runs through the real function, per distro, with each distro's own defaults
# read from lib/<distro>/config.sh.
#
# No install, no network, no root.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }
is()  { [[ "$1" == "$2" ]] && ok "$3" || bad "$3 (expected '$2', got '$1')"; }

# The re-run below rests on which keys the installer saves. Pin that here, so a
# change to the saved list fails this test instead of leaving it replaying a
# re-run the installer no longer does.
saved=$(awk '/^writeUpdateFile\(\) *\{/,/^\}/' "$FUNCS" | sed 's/#.*//')
[[ -n $saved ]] || bad "writeUpdateFile() found in functions.sh"
for key in FOG_packages DHCP_engine DHCP_proxy; do
    grep -qw -- "$key" <<<"$saved" && ok "$key is saved" || bad "$key is saved"
done
for key in dhcpconfig dhcpname; do
    grep -qw -- "$key" <<<"$saved" && bad "$key is not saved" || ok "$key is not saved"
done

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

# Defined after sourcing on purpose: the real ones ask the package manager.
INSTALLED=""
pkgIsInstalled() { [[ " $INSTALLED " == *" $1 "* ]]; }
pkgIsAvailable() { return 0; }

# What doOSSpecificIncludes gives each run: the distro's own [[ -z ]] defaults.
seed() {
    local cfg="$REPO/lib/$1/config.sh" v line
    for v in dhcpconfig dhcpname keapackage keaservice dnsmasqpackage dnsmasqservice dnsmasqconfig; do
        line=$(grep -m1 -E "^[[:space:]]*\[\[ -z \\\$$v \]\] && $v=\"[^\"]*\"[[:space:]]*$" "$cfg")
        [[ -n $line ]] || { bad "$1: default for $v in lib/$1/config.sh"; return 1; }
        eval "$line"
    done
}
reset() { unset dhcpconfig dhcpname keapackage keaservice keaconfig DHCP_service_name dhcpconfigother dnsmasqpackage dnsmasqservice dnsmasqconfig; }

DHCP_enabled=yes
for distro in ubuntu redhat arch alpine; do
    [[ -f $REPO/lib/$distro/config.sh ]] || continue
    echo "$distro:"

    reset; unset DHCP_engine; INSTALLED=""
    seed "$distro" || continue
    iscconfig=$dhcpconfig
    iscpkg=$dhcpname
    keapkg=$keapackage
    FOG_packages="bc $iscpkg lftp"
    resolveDHCPEngine
    is "$DHCP_engine" kea "first run picks Kea"
    is "$dhcpconfig" /etc/kea/kea-dhcp4.conf "first run writes the Kea config path"

    # Re-run: the saved keys come back, everything else is re-seeded.
    savedpackages=$FOG_packages
    savedengine=$DHCP_engine
    reset; INSTALLED="$keapkg"
    FOG_packages=$savedpackages
    DHCP_engine=$savedengine
    seed "$distro"
    resolveDHCPEngine
    is "$DHCP_engine" kea "re-run stays on Kea"
    is "$dhcpconfig" /etc/kea/kea-dhcp4.conf "re-run writes the Kea config path"
    is "$dhcpname" "$keapkg" "re-run names the Kea package"
    is "$FOG_packages" "$savedpackages" "re-run leaves the package list alone"

    # dnsmasq is opt-in: an explicit --dhcp-engine dnsmasq (it reaches the
    # function as DHCP_engine) swaps the DHCP package, and the saved list then
    # names dnsmasq while $dhcpname comes back as the distro's ISC name.
    reset; INSTALLED=""
    FOG_packages="bc $iscpkg lftp"
    # Debian-family gets the binary-only package: "dnsmasq" auto-starts a DNS
    # service that fails against systemd-resolved on port 53.
    dmpkg=dnsmasq
    [[ $distro == ubuntu ]] && dmpkg=dnsmasq-base
    DHCP_engine=dnsmasq
    seed "$distro"
    resolveDHCPEngine
    is "$DHCP_engine" dnsmasq "dnsmasq run 1 keeps the explicit engine"
    is "$dhcpconfig" /etc/fog/dnsmasq.conf "dnsmasq run 1 writes FOG's own config path"
    is "$dhcpname" "$dmpkg" "dnsmasq run 1 names the dnsmasq package"
    is "$DHCP_service_name" fog-dnsmasq "dnsmasq run 1 names FOG's own service, not the distro's"
    is "$FOG_packages" "bc $dmpkg lftp" "dnsmasq run 1 swaps the DHCP package"
    savedpackages=$FOG_packages
    savedengine=$DHCP_engine
    reset; INSTALLED="$dmpkg"
    FOG_packages=$savedpackages
    DHCP_engine=$savedengine
    seed "$distro"
    resolveDHCPEngine
    is "$DHCP_engine" dnsmasq "dnsmasq re-run stays on dnsmasq"
    is "$dhcpconfig" /etc/fog/dnsmasq.conf "dnsmasq re-run writes FOG's own config path"
    is "$dhcpname" "$dmpkg" "dnsmasq re-run names the dnsmasq package"
    is "$DHCP_service_name" fog-dnsmasq "dnsmasq re-run names FOG's own service"
    is "$FOG_packages" "$savedpackages" "dnsmasq re-run leaves the package list alone"
    # Back to Kea, then to ISC, from the saved dnsmasq list (an explicit switch).
    reset; FOG_packages=$savedpackages; DHCP_engine=kea
    seed "$distro"
    resolveDHCPEngine
    is "$DHCP_engine" kea "explicit kea after dnsmasq picks Kea"
    is "$FOG_packages" "bc $keapkg lftp" "explicit kea after dnsmasq swaps the package back"
    reset; FOG_packages=$savedpackages; DHCP_engine=isc
    seed "$distro"
    if [[ $iscpkg == "$keapkg" ]]; then
        # No ISC package in the distro config (Alpine): isc is a usage error.
        usage() { :; }
        out=$(resolveDHCPEngine 2>&1); rc=$?
        is "$rc" "3" "explicit isc without an ISC package exits 3"
        is "$out" "--dhcp-engine isc is not available on this OS: it has no ISC DHCP package" "and says why in one line"
        unset -f usage
    else
        resolveDHCPEngine
        is "$DHCP_engine" isc "explicit isc after dnsmasq picks ISC"
        is "$FOG_packages" "bc $iscpkg lftp" "explicit isc after dnsmasq swaps the package back"
    fi

    # An existing ISC install must not move on a re-run. Alpine has no ISC
    # package: its $dhcpname is already the Kea package.
    [[ $iscpkg == "$keapkg" ]] && continue
    reset; INSTALLED="$iscpkg"
    FOG_packages="bc $iscpkg lftp"
    DHCP_engine=isc
    seed "$distro"
    resolveDHCPEngine
    is "$DHCP_engine" isc "ISC re-run stays on ISC"
    is "$dhcpconfig" "$iscconfig" "ISC re-run keeps the ISC config path"
    is "$FOG_packages" "bc $iscpkg lftp" "ISC re-run keeps the ISC package"
done

echo
echo "dhcp-engine-rerun: $PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
