#!/bin/bash
#
# Guards the udpcast tarball the installer builds (IPv6 multicast).
#
#   tests/installer-udpcast-ipv6.test.sh
#
# configureUDPCast() unpacks $udpcastsrc, runs ./configure and builds. The
# tarball is the 20250223 source plus the IPv6 port, with a regenerated
# configure. This test does what the installer does and checks three things:
#
#   * configure detects getifaddrs and if_nametoindex. Without them the IPv6
#     code is compiled out and udp-sender silently stays IPv4 only.
#   * udp-sender builds.
#   * The built binary carries the IPv6 interface error string, which proves
#     the IPv6 code is in it.
#
# Needs tar, make and a C compiler. No network, no root, no install.
#
# Exit status 0 = pass or skip, 1 = fail.

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
CONFIG="$REPO/lib/common/config.sh"

[[ -f $CONFIG ]] || { echo "ERROR: $CONFIG not found" >&2; exit 1; }
for tool in tar make; do
    command -v $tool >/dev/null 2>&1 || { echo "SKIP: $tool is not installed"; exit 0; }
done
command -v cc >/dev/null 2>&1 || command -v gcc >/dev/null 2>&1 || { echo "SKIP: no C compiler is installed"; exit 0; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0
ok()  { PASS=$((PASS + 1)); printf '  ok    %s\n' "$1"; }
bad() { FAIL=$((FAIL + 1)); printf '  FAIL  %s\n' "$1"; }

# Read the real paths from config.sh, so the test cannot drift from the
# installer. config.sh gives a relative path, resolved from tests/ the same
# way the installer resolves it from its own directory.
udpcastsrc=""
udpcastout=""
# shellcheck source=/dev/null
. "$CONFIG" >/dev/null 2>&1
SRC="$(cd "$REPO/bin" && cd "$(dirname "$udpcastsrc")" && pwd)/$(basename "$udpcastsrc")"

[[ -f $SRC ]] && ok "udpcastsrc exists ($(basename "$SRC"))" || { bad "udpcastsrc missing: $SRC"; echo "FAIL: $FAIL"; exit 1; }
case "$(basename "$SRC")" in
    *-ipv6.tar.gz) ok "installer points at the ipv6 tarball" ;;
    *) bad "installer does not point at the ipv6 tarball: $SRC" ;;
esac

cd "$WORK" || exit 1
if tar xzf "$SRC" 2>"$WORK/tar.err" && cd "$udpcastout"; then
    ok "tarball unpacks to $udpcastout"
else
    bad "tarball did not unpack to $udpcastout"
    echo "FAIL: $FAIL"
    exit 1
fi

if ./configure >"$WORK/configure.log" 2>&1; then
    ok "./configure succeeds"
else
    bad "./configure failed (see $WORK/configure.log)"
    tail -20 "$WORK/configure.log"
    echo "FAIL: $FAIL"
    exit 1
fi

for sym in HAVE_GETIFADDRS HAVE_IF_NAMETOINDEX; do
    if grep -Eq "^#define $sym 1" config.h; then
        ok "config.h defines $sym"
    else
        bad "config.h does not define $sym"
    fi
done

if make udp-sender >"$WORK/make.log" 2>&1 && [[ -x udp-sender ]]; then
    ok "make udp-sender builds"
else
    bad "make udp-sender failed"
    tail -20 "$WORK/make.log"
    echo "FAIL: $FAIL"
    exit 1
fi

n=$(grep -ac 'No suitable IPv6 network interface found' udp-sender)
[[ $n -eq 1 ]] && ok "udp-sender carries the IPv6 interface error string" \
    || bad "IPv6 interface error string count is $n, expected 1"

echo "$PASS passed, $FAIL failed"
[[ $FAIL -eq 0 ]]
