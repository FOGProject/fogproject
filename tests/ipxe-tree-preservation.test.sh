#!/bin/bash
#
# Guards the TFTP tree: custom binaries, the stock copy, and the rebuild stamp.
#
#   tests/ipxe-tree-preservation.test.sh
#
# Three failures this pins, none of which is visible on the server when it
# happens:
#
#   1. The copy loop was `find -type f -exec cp -Rfv {} $tftpdirdst/{}`, which
#      overwrites unconditionally. A name FOG does not ship survives -- nothing
#      deletes it -- but any of the ~55 it DOES ship was destroyed on every
#      run, which is precisely the set an admin is most likely to have
#      replaced: snponly.efi, ipxe.efi, undionly.kkpxe.
#   2. A local rebuild wrote over the downloaded binaries in the same staging
#      tree, so afterward no pristine copy existed anywhere.
#   3. buildipxe.sh is a cold 8-make rebuild every invocation -- 10-25 minutes
#      on every install AND update -- with nothing recording what was already
#      built, so it ran again to reproduce identical bytes.
#
# The CA half of the stamp matters as much as the version half: TRUST=/CERT=
# bakes the certificate into the binary, so --recreate-ca or an external-CA
# switch must force a rebuild at an UNCHANGED iPXE tag, or iPXE goes on
# trusting a CA that no longer signs anything.
#
# Needs sha256sum; openssl only for the CA-change case, which skips without it.
# No network, no root, no install.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
FUNCS="$REPO/lib/common/functions.sh"

[[ -f $FUNCS ]] || { echo "ERROR: $FUNCS not found" >&2; exit 1; }
command -v sha256sum >/dev/null 2>&1 || { echo "SKIP: sha256sum is not installed"; exit 0; }

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
dots() { :; }
errorStat() { :; }

tftpdirsrc="$WORK/src"
tftpdirdst="$WORK/dst"
mkdir -p "$tftpdirsrc/i386-efi" "$tftpdirsrc/secureboot" "$tftpdirdst"

echo "ipxe tree preservation:"

# --- no-clobber --------------------------------------------------------------
echo "fog-snponly-v1" > "$tftpdirsrc/snponly.efi"
echo "fog-ipxe-v1"    > "$tftpdirsrc/i386-efi/ipxe.efi"
echo "upstream-shim"  > "$tftpdirsrc/secureboot/snponly.efi"

_copyIpxeTree
is "$ipxeSkipped" "" "first run has nothing to preserve yet"
is "$(cat "$tftpdirdst/snponly.efi")" "fog-snponly-v1" "first run installs FOG's binaries"

# The admin replaces one binary and FOG ships a newer version of both.
echo "ADMIN CUSTOM BUILD" > "$tftpdirdst/snponly.efi"
echo "fog-snponly-v2" > "$tftpdirsrc/snponly.efi"
echo "fog-ipxe-v2"    > "$tftpdirsrc/i386-efi/ipxe.efi"

_copyIpxeTree
is "$ipxeSkipped" "snponly.efi" "the admin's file is named as preserved"
is "$(cat "$tftpdirdst/snponly.efi")" "ADMIN CUSTOM BUILD" \
   "the admin's binary survives an upgrade"
is "$(cat "$tftpdirdst/i386-efi/ipxe.efi")" "fog-ipxe-v2" \
   "a FOG-owned binary still updates"

# The original checksum is carried forward rather than the admin's, so the file
# stays protected. Recording theirs would make it match next run and be
# silently overwritten then instead -- one upgrade later, which is worse than
# never protecting it.
echo "fog-snponly-v3" > "$tftpdirsrc/snponly.efi"
_copyIpxeTree
is "$(cat "$tftpdirdst/snponly.efi")" "ADMIN CUSTOM BUILD" \
   "still preserved on the run after that"

# A file the admin adds under a name FOG does not ship is untouched, which was
# already true and must stay true.
echo "MY OWN THING" > "$tftpdirdst/custom.ipxe"
_copyIpxeTree
is "$(cat "$tftpdirdst/custom.ipxe")" "MY OWN THING" \
   "a name FOG does not ship is left alone"

# The manifest must never manage itself.
if [[ -f $tftpdirdst/.fog-ipxe-manifest ]] && \
   ! grep -q 'fog-ipxe-manifest' "$tftpdirdst/.fog-ipxe-manifest"; then
    ok "the manifest does not list itself"
else
    bad "the manifest is missing or lists its own bookkeeping"
fi

# --- stock/ ------------------------------------------------------------------
_preserveStockIpxe
if [[ -f $tftpdirsrc/stock/snponly.efi && -f $tftpdirsrc/stock/i386-efi/ipxe.efi ]]; then
    ok "stock/ keeps a copy of the published binaries"
else
    bad "stock/ did not capture the published binaries"
fi
# Load-bearing, not tidiness: _signLocalIpxe prunes exactly
# "$tftproot/secureboot" and nothing deeper, so a copy of it under stock/ would
# fall outside the prune and FOG would add its own signature to Microsoft's and
# iPXE's signed shim and loader.
if [[ -e $tftpdirsrc/stock/secureboot ]]; then
    bad "stock/ contains secureboot/ -- those binaries would get re-signed"
else
    ok "stock/ excludes secureboot/, which must never be re-signed"
fi
# Re-running must not nest stock/ inside itself.
_preserveStockIpxe
if [[ -e $tftpdirsrc/stock/stock ]]; then
    bad "stock/ nested inside itself on a second run"
else
    ok "stock/ is rebuilt rather than nested on a re-run"
fi

# --- the rebuild stamp -------------------------------------------------------
needs() {
    BOOT_rebuild_ipxe_with_my_ca="$1"; ipxeVer="$2"; PKI_web_trust_chain="$3"; PKI_web_ca_cert="$3"
    if _needsLocalIpxeBuild; then
        printf '%s' "$(_ipxeBuildStampValue)" > "${tftpdirdst}/.fog-ipxe-build"
        echo "rebuild"
    else
        echo "skip"
    fi
}

if command -v openssl >/dev/null 2>&1; then
    for ca in ca1 ca2; do
        openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 5 \
            -subj "/CN=${ca}" -keyout "$WORK/${ca}.key" -out "$WORK/${ca}.pem" \
            >/dev/null 2>&1
    done
    is "$(needs no  v2.0.0 "$WORK/ca1.pem")" "skip"    "no rebuild when not asked for"
    is "$(needs yes v2.0.0 "$WORK/ca1.pem")" "rebuild" "first rebuild happens"
    is "$(needs yes v2.0.0 "$WORK/ca1.pem")" "skip"    "unchanged tag and CA does NOT rebuild"
    is "$(needs yes v2.1.0 "$WORK/ca1.pem")" "rebuild" "a newer iPXE tag rebuilds"
    is "$(needs yes v2.1.0 "$WORK/ca1.pem")" "skip"    "and settles again"
    is "$(needs yes v2.1.0 "$WORK/ca2.pem")" "rebuild" "a CHANGED CA rebuilds at the same tag"
    is "$(needs yes v2.1.0 "$WORK/ca2.pem")" "skip"    "and settles again"
else
    echo "  SKIP: openssl absent, rebuild-stamp CA cases not run"
fi

printf '\n%d passed, %d failed\n' "$PASS" "$FAIL"
[[ $FAIL -eq 0 ]] || { echo "  (see $error_log)"; exit 1; }
exit 0
