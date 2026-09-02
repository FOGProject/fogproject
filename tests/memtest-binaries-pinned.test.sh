#!/bin/bash
#
# The Memtest86+ binaries FOG ships are the upstream release, byte for byte.
#
# packages/web/service/ipxe/mt86plus_x86_64 and mt86plus_i586 are the two
# x86 files from mt86plus_8.10.binaries.zip as published at memtest.org
# (sha256 7e6c5162cb84ab959aeb9d13c9cfd6976b0dec3b34936b73820b20c55eb26c29,
# matching the site's sha256sum.txt), renamed without the version so the
# FOG_MEMTEST_KERNEL default does not have to move on every upstream
# release. A blob in git has no diff a reviewer can read, so this is what
# stands in for one: the sums below were taken from that zip's contents,
# and a file that no longer matches -- a stray re-sign, a partial copy, a
# "helpful" rebuild -- fails here rather than on a client's screen.
#
# Also refuses a signed copy: installfog.sh countersigns the DEPLOYED files
# for Secure Boot, and the sync scripts exclude them for that reason. One
# pulled back into the tree would carry a server's key as though it were
# upstream, and it is 1520 bytes longer, so the sum catches it.
#
# Usage: bash tests/memtest-binaries-pinned.test.sh
# Exit status 0 = pass, 1 = fail.

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
dir="$here/../packages/web/service/ipxe"

declare -A want=(
    [mt86plus_x86_64]=11808288ea1ee332f7d89e683cef10fbcdf2d6585bb972688ed9fe0e3d30fc55
    [mt86plus_i586]=99807cbc4d8a017279932e4bf9b46b7fd3b6c2ea2c1284c34a042f01591fbbad
)

fail=0
for name in "${!want[@]}"; do
    f="$dir/$name"
    if [[ ! -f $f ]]; then
        echo "FAIL: $name is missing from packages/web/service/ipxe"
        fail=1
        continue
    fi
    got=$(sha256sum "$f" | awk '{print $1}')
    if [[ $got != "${want[$name]}" ]]; then
        echo "FAIL: $name sha256 $got, expected ${want[$name]} (Memtest86+ 8.10 upstream)"
        fail=1
        continue
    fi
    # The file must still be what iPXE expects on BOTH firmwares: a bzImage
    # (the "HdrS" Linux boot-protocol magic at 0x202) that is also a PE
    # ("MZ" at 0). Either missing and one platform stops booting it.
    if [[ $(dd if="$f" bs=1 skip=514 count=4 2>/dev/null) != HdrS ]]; then
        echo "FAIL: $name has no Linux boot-protocol header; BIOS clients cannot kernel it"
        fail=1
    fi
    if [[ $(dd if="$f" bs=1 count=2 2>/dev/null) != MZ ]]; then
        echo "FAIL: $name has no PE header; UEFI clients cannot chain it"
        fail=1
    fi
    [[ $fail -eq 0 ]] && echo "ok    $name matches upstream and carries both boot headers"
done

[[ $fail -eq 0 ]] || exit 1
echo "PASS"
