#!/bin/bash
#
# Guards the architecture -> DHCP boot file mapping, and that ISC and Kea agree.
#
#   tests/dhcp-bootfile-mapping.test.sh
#
# FOG hands each client architecture a different PXE boot file, and 64-bit UEFI
# and arm64 now get the signed shim chain by default -- secureboot/*-shim*.efi --
# rather than the unsigned binaries. That is deliberate and not a Secure Boot
# opt-in: shim is an ordinary UEFI application carrying a Microsoft signature, so
# it boots the same whether the firmware is enforcing or not. The signed chain is
# a superset, so every 64-bit UEFI client gets it. See _uefiBootFile().
#
# Two things are easy to break here and neither shows up until a client fails to
# boot, long after the install that caused it:
#
#   1. The fallback. downloadipxesecureboot() is deliberately non-fatal, so an
#      install whose fetch failed has no secureboot/ tree at all. If the config
#      still named the shim, TFTP would answer "file not found" and EVERY UEFI
#      client on the network would stop booting -- over a feature the site may
#      not even use. _sbChainStaged() is what prevents that, and it reads the
#      STAGING tree because configureDHCP runs before the copy loop fills the
#      TFTP root.
#
#   2. The ISC/Kea agreement. The two generators are separate bodies of code --
#      a heredoc piped through sed for Kea, a run of echo lines for ISC -- and
#      the only thing keeping them in step was a comment asking future editors
#      to keep them in sync. A site switching DHCP engines would otherwise get
#      silently different boot files.
#
# Also pinned: what must NOT move. There is no Microsoft-signed 32-bit shim and
# no signed 32-bit iPXE, so i386-efi clients cannot use this chain at all; BIOS
# has no Secure Boot to speak of; and Apple BSDP serves Intel Macs over a
# protocol none of this enters.
#
# No install, no network, no root.
#
# Exit status 0 = pass or skip, 1 = fail.

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

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1

BOOT_dhcp_delay_seconds=0

# Two staging trees: one with the signed chain present, one without. Only the
# x86-64 shim is created -- that is exactly the file _sbChainStaged() looks for,
# so this also pins that it is not testing for something else.
mkdir -p "$WORK/staged/secureboot"
: > "$WORK/staged/secureboot/snponly-shimx64.efi"
mkdir -p "$WORK/bare"

# Replay the ISC class block from configureDHCP() rather than copying the
# expected filenames into this test. Sourcing functions.sh does not run it -- it
# is inline in a case branch -- so the lines are extracted and evaluated with
# $dhcptouse pointed at a file. Reading the real lines is the point: a test that
# restated them would pass while the generator drifted.
isc_classes() {
    local out="$WORK/dhcpd.conf"
    : > "$out"
    local block
    block=$(sed -n '/echo "class \\"Legacy\\" {"/,/^            diffconfig/p' "$FUNCS" \
        | sed '/^            diffconfig/d')
    [[ -n $block ]] || { echo "ERROR: could not extract the ISC class block" >&2; exit 1; }
    local dhcptouse="$out"
    eval "$block"
    cat "$out"
}

# The filename ISC gives one class, by class name.
isc_file_for() {
    isc_classes | awk -v want="class \"$1\" {" '
        $0 == want { found = 1; next }
        found && /filename/ { gsub(/^ *filename "|"; *$/, ""); print; exit }
    '
}

# The boot-file-name Kea gives one client-class, by class name.
kea_file_for() {
    _keaBaseClasses | awk -v want="\"name\": \"$1\"" '
        index($0, want) { found = 1; next }
        found && /boot-file-name/ {
            sub(/^.*"boot-file-name": "/, ""); sub(/".*$/, ""); print; exit
        }
    '
}

echo "With the signed chain staged"
tftpdirsrc="$WORK/staged"

is "$(_sbChainStaged && echo yes || echo no)" "yes" "_sbChainStaged sees the staged shim"
is "$(_uefiBootFile x64)"   "secureboot/snponly-shimx64.efi"            "x86-64 UEFI gets the shim"
is "$(_uefiBootFile arm64)" "secureboot/arm64-efi/snponly-shimaa64.efi" "arm64 UEFI gets the arm64 shim"

# Arch 7, 8 and 9 all mean "64-bit UEFI" to some firmware vendor, so all three
# have to land on the same file or the disagreement becomes a boot failure.
for c in UEFI-64-1 UEFI-64-2 UEFI-64-3; do
    is "$(isc_file_for $c)" "secureboot/snponly-shimx64.efi" "ISC $c -> shim"
done
is "$(isc_file_for UEFI-ARM64)" "secureboot/arm64-efi/snponly-shimaa64.efi" "ISC UEFI-ARM64 -> arm64 shim"
# Arch 00007 with a UNDI suffix -- a 64-bit UEFI client like any other, and it
# used to be matched separately and left behind by changes like this one.
is "$(isc_file_for SURFACE-PRO-4)" "secureboot/snponly-shimx64.efi" "ISC SURFACE-PRO-4 -> shim"

echo
echo "With the signed chain missing (a failed or skipped download)"
tftpdirsrc="$WORK/bare"

is "$(_sbChainStaged && echo yes || echo no)" "no" "_sbChainStaged reports it missing"
is "$(_uefiBootFile x64)"   "snponly.efi"           "x86-64 UEFI falls back to the unsigned binary"
is "$(_uefiBootFile arm64)" "arm64-efi/snponly.efi" "arm64 UEFI falls back to the unsigned binary"

for c in UEFI-64-1 UEFI-64-2 UEFI-64-3; do
    is "$(isc_file_for $c)" "snponly.efi" "ISC $c falls back"
done
is "$(isc_file_for UEFI-ARM64)"    "arm64-efi/snponly.efi" "ISC UEFI-ARM64 falls back"
is "$(isc_file_for SURFACE-PRO-4)" "snponly.efi"           "ISC SURFACE-PRO-4 falls back"

echo
echo "What must not move"
for tree in staged bare; do
    tftpdirsrc="$WORK/$tree"
    is "$(isc_file_for UEFI-32-1)" "i386-efi/snponly.efi" "ISC UEFI-32-1 stays unsigned ($tree)"
    is "$(isc_file_for UEFI-32-2)" "i386-efi/snponly.efi" "ISC UEFI-32-2 stays unsigned ($tree)"
    is "$(kea_file_for FOG-UEFI-32-1)" "i386-efi/snponly.efi" "Kea FOG-UEFI-32-1 stays unsigned ($tree)"
    is "$(kea_file_for FOG-Apple-Intel-Netboot)" "" "Kea Apple BSDP is not in the base classes ($tree)"
    is "$(_biosBootFile)" "undionly.kkpxe" "BIOS is untouched ($tree)"
done

# --boot-delay drives a second BIOS binary through _biosBootFile, and both
# indirections now run through the same sed pipeline in _keaBaseClasses. Pin
# that adding the UEFI one did not break the BIOS one.
tftpdirsrc="$WORK/staged"
BOOT_dhcp_delay_seconds=10
is "$(kea_file_for FOG-Legacy-BIOS)" "10secdelay/undionly.kkpxe" "Kea BIOS still follows --boot-delay"
is "$(kea_file_for FOG-UEFI-64-1)" "secureboot/snponly-shimx64.efi" "Kea 64-bit UEFI unaffected by --boot-delay"
BOOT_dhcp_delay_seconds=0

echo
echo "ISC and Kea agree"
# The invariant _keaBaseClasses' own comment asks for, checked rather than asked.
for tree in staged bare; do
    tftpdirsrc="$WORK/$tree"
    while read -r isc kea; do
        is "$(kea_file_for "$kea")" "$(isc_file_for "$isc")" "$isc == $kea ($tree)"
    done <<'PAIRS'
Legacy FOG-Legacy-BIOS
UEFI-32-2 FOG-UEFI-32-2
UEFI-32-1 FOG-UEFI-32-1
UEFI-64-1 FOG-UEFI-64-1
UEFI-64-2 FOG-UEFI-64-2
UEFI-64-3 FOG-UEFI-64-3
UEFI-ARM64 FOG-UEFI-ARM64
SURFACE-PRO-4 FOG-Surface-Pro-4
PAIRS
done

echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
