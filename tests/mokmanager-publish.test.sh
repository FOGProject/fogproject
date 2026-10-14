#!/bin/bash
#
# Guards that service/secureboot/ gets MokManager on a FRESH install.
#
#   tests/mokmanager-publish.test.sh
#
# The "Enroll Secure Boot Key" PXE item chains mmx64.efi from the web kit.
# The copy used to live in _publishSecureBootKit, which runs from
# configureHttpd -- BEFORE downloadipxesecureboot and configureTFTPandPXE stage
# the Secure Boot set into $tftpdirdst. An upgrade copied the previous install's
# file; a fresh install copied nothing, the request fell through to the API,
# and iPXE reported "Permission denied" (HTTP 401). Forum 18277.
#
# What this pins:
#   1. installfog.sh calls _publishMokManager AFTER configureTFTPandPXE.
#   2. _publishSecureBootKit no longer copies MokManager (so nothing can put
#      the copy back on the wrong side of the TFTP staging).
#   3. _publishMokManager copies both architectures into a published kit.
#   4. It writes nothing when the kit was not published (no MOK.der).
#
# Needs bash only. No network, no root, no install.
#
# Exit status 0 = pass, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"
INSTALLER="$REPO/bin/installfog.sh"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

error_log="$WORK/error.log"
: > "$error_log"
# shellcheck source=/dev/null
. "$FUNCS" >/dev/null 2>&1
apacheuser="$(id -un)"

# 1. Order in the full-install path: the call must come after the TFTP staging.
tftpline=$(grep -n '^ *configureTFTPandPXE$' "$INSTALLER" | tail -n 1 | cut -d: -f1)
mmline=$(grep -n '^ *_publishMokManager$' "$INSTALLER" | head -n 1 | cut -d: -f1)
if [[ -n $tftpline && -n $mmline && $mmline -gt $tftpline ]]; then
    ok "installfog.sh calls _publishMokManager after configureTFTPandPXE"
else
    bad "installfog.sh calls _publishMokManager after configureTFTPandPXE (tftp=$tftpline mm=$mmline)"
fi

# 2. The early function must not copy MokManager any more.
if declare -f _publishSecureBootKit | grep -q 'mmx64\.efi\|mmaa64\.efi'; then
    bad "_publishSecureBootKit does not copy MokManager"
else
    ok "_publishSecureBootKit does not copy MokManager"
fi

# 3. A published kit receives both architectures from the TFTP tree.
webdirdest="$WORK/web"
tftpdirdst="$WORK/tftp"
mkdir -p "$webdirdest/service/secureboot" "$tftpdirdst/secureboot/arm64-efi"
echo mok > "$webdirdest/service/secureboot/MOK.der"
echo x64 > "$tftpdirdst/secureboot/mmx64.efi"
echo aa64 > "$tftpdirdst/secureboot/arm64-efi/mmaa64.efi"
_publishMokManager
if [[ $(cat "$webdirdest/service/secureboot/mmx64.efi" 2>/dev/null) == x64 ]]; then
    ok "mmx64.efi is published into the kit"
else
    bad "mmx64.efi is published into the kit"
fi
if [[ $(cat "$webdirdest/service/secureboot/arm64-efi/mmaa64.efi" 2>/dev/null) == aa64 ]]; then
    ok "arm64-efi/mmaa64.efi is published into the kit"
else
    bad "arm64-efi/mmaa64.efi is published into the kit"
fi

# 4. No MOK.der means enrollment was declined: write nothing.
webdirdest="$WORK/web-nokit"
mkdir -p "$webdirdest/service"
_publishMokManager
if [[ -e $webdirdest/service/secureboot ]]; then
    bad "no kit directory is created when MOK.der was not published"
else
    ok "no kit directory is created when MOK.der was not published"
fi

echo
echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
