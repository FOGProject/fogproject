#!/bin/bash
#
# Guards the stamp that decides whether iPXE gets rebuilt with this server's CA.
#
#   tests/ipxe-build-stamp.test.sh
#
# --rebuild-ipxe-with-my-ca compiles the FOG CA into the iPXE binaries with
# CERT=/TRUST=, which is the only thing that lets a PXE client fetch boot.php
# over TLS from a private CA. The build is 10-25 minutes, so a stamp in the
# TFTP root records what was built and lets later runs skip it.
#
# The stamp used to record only the iPXE release tag and the CA bytes -- an
# intention, not a fact. downloadipxe() unpacks the published tarball into the
# same staging tree the build writes to, on every run, so:
#
#   run 1  unpack -> build -> stamp -> TFTP root gets the CA-embedded binaries
#   run 2  unpack (published binaries land on top of the build)
#          -> stamp still matches on tag and CA -> rebuild skipped
#          -> TFTP root gets the PUBLISHED binaries, which trust nothing
#
# Nothing in the install output mentioned the skip. The failure surfaced only at
# a booting client, as iPXE "Permission denied" from x509.c -- EACCES_USELESS,
# "found no usable certificates" -- because the binary had no trusted root to
# build a path to. First install fine, every install after it broken.
#
# So the stamp now also carries the sha256 of snponly.efi as it sits in the
# staging tree, and downloadipxe() skips the unpack precisely when that says a
# local build is already staged. Both halves are pinned below, including the
# mutation that the old stamp could not see.
#
# Needs sha256sum. No install, no network, no root.
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
# Stand in for the download. Leaves a marker so a test can tell whether the
# published tarball would have been unpacked over the staging tree.
fetchipxeasset() { : > "$WORK/fetched"; return 0; }

tftpdirsrc="$WORK/src"
tftpdirdst="$WORK/dst"
mkdir -p "$tftpdirsrc" "$tftpdirdst"

ipxeVer="v2.0.0-fog.8"
sslcachain=""
sslcapem="$WORK/ca.pem"
echo "-----BEGIN CERTIFICATE----- fixture -----END CERTIFICATE-----" > "$sslcapem"

stamp="$tftpdirdst/.fog-ipxe-build"

# The two states snponly.efi can be in. They only have to differ.
published() { echo "published-binary-trusts-nothing" > "$tftpdirsrc/snponly.efi"; }
locallybuilt() { echo "built-with-CERT-and-TRUST"    > "$tftpdirsrc/snponly.efi"; }

# What a completed build writes: the stamp, taken while the built binary is the
# one in the staging tree. Mirrors configureTFTPandPXE's ipxeBuildStampPending.
writestamp() { _resolveIpxeTrust; _ipxeBuildStampValue > "$stamp"; }

needs() { _needsLocalIpxeBuild && echo yes || echo no; }

echo "ipxe build stamp:"

# --- the build only ever happens when it was asked for ----------------------
rebuildIpxeWithMyCA="no"
published
rm -f "$stamp"
is "$(needs)" "no" "no rebuild when --rebuild-ipxe-with-my-ca is off"

rebuildIpxeWithMyCA="yes"
is "$(needs)" "yes" "rebuild when asked for and nothing has been built"

# --- a build that is genuinely still staged is not repeated ------------------
locallybuilt
writestamp
is "$(needs)" "no" "no rebuild when the built binary is still staged"

# --- THE REGRESSION ---------------------------------------------------------
# The tarball unpack replaces the built binary. Tag and CA are untouched, so
# the old two-field stamp still matched here and the rebuild was skipped.
published
is "$(needs)" "yes" "rebuild after the published tarball overwrites the build"

# --- and the other two inputs still force a rebuild on their own -------------
locallybuilt
writestamp
ipxeVer="v2.0.1-fog.1"
is "$(needs)" "yes" "rebuild when the iPXE release moves"
ipxeVer="v2.0.0-fog.8"

echo "-----BEGIN CERTIFICATE----- rotated -----END CERTIFICATE-----" > "$sslcapem"
is "$(needs)" "yes" "rebuild when the CA bytes change"
echo "-----BEGIN CERTIFICATE----- fixture -----END CERTIFICATE-----" > "$sslcapem"

# --- downloadipxe must not unpack over a staged build ------------------------
locallybuilt
writestamp
rm -f "$WORK/fetched"
downloadipxe >/dev/null 2>&1
is "$([[ -e $WORK/fetched ]] && echo fetched || echo kept)" "kept" \
    "download skipped while a local build is staged"
is "$(cat "$tftpdirsrc/snponly.efi")" "built-with-CERT-and-TRUST" \
    "the staged build survives the download step"

# ...but it must still unpack in every other state, or an upgrade never lands.
ipxeVer="v2.0.1-fog.1"
rm -f "$WORK/fetched"
downloadipxe >/dev/null 2>&1
is "$([[ -e $WORK/fetched ]] && echo fetched || echo kept)" "fetched" \
    "download still runs when the release moves"
ipxeVer="v2.0.0-fog.8"

rebuildIpxeWithMyCA="no"
rm -f "$WORK/fetched"
downloadipxe >/dev/null 2>&1
is "$([[ -e $WORK/fetched ]] && echo fetched || echo kept)" "fetched" \
    "download always runs on a server that does not build"

echo
echo "  passed: $PASS  failed: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
