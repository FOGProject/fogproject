#!/bin/bash
#
# Kernel backup generations actually rotate.
#
#   tests/kernel-backup-rotation.test.sh
#
# GH-1579. backupPreservedCustomizations() prunes the oldest generation using
# BOOT_kernel_backups_kept and then shifts the rest up by one -- except the
# shift loop read `kernelBackupGenerations`, the retired pre-GH-1120 spelling.
# That name survives only as a migration source and in the deprecated-key strip
# list, so on a migrated install, and on every fresh one, it is unset. Bash
# evaluates an unset name as 0 in arithmetic, so `k` started at -1 and the loop
# body never ran: gen-1 was overwritten in place forever and gen-2 onward were
# never created.
#
# Nothing failed. --kernel-backup-count silently did nothing, and
# restorekernel.sh --generation N was unusable for every N above 1 -- which is
# every N worth asking for, since gen-1 is the snapshot you already have.
#
# The rotation is EXECUTED against a fixture, not read. A textual check for the
# right variable name passes on a loop that names it and gets the direction
# backwards, and the direction is the whole of the behavior: rotating the
# wrong way overwrites the newest snapshot with the oldest.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/kernel-backup-rotation.test.sh
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

# ---------------------------------------------------------------------------
# The prune-and-rotate block, lifted out of backupPreservedCustomizations().
# ---------------------------------------------------------------------------
snippet=$(awk '
    /BOOT_kernel_backups_kept\} -lt 1 \]\] &&/ { grab = 1 }
    grab { print }
    grab && /mkdir -p "\$\{kbdir\}\/gen-1"/ { exit }
' "$functions")

if [[ -z $snippet ]]; then
    echo "FAIL: could not find the kernel-backup rotation block in" >&2
    echo "  lib/common/functions.sh. If it moved or was rewritten, point this" >&2
    echo "  test at it -- do not delete the assertion." >&2
    exit 1
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

error_log="$work/error.log"

# The block opens `if [[ -d $ipxedir ]]` and the extraction stops before its
# `fi`, so the closer is supplied here. Everything else -- the default, the
# prune, the shift -- is the real code. It computes $kbdir from
# $customizationsDir itself, so the fixture drives it through that rather than
# passing a path in.
eval "rotate() {
    local warn=0
$snippet
    fi
}"

# Entering the block at all requires a live iPXE directory, and $webdirsrc is
# read for the shipped-file subtraction further down the real function.
ipxedir="$work/ipxe"
webdirsrc="$work/src"
mkdir -p "$ipxedir" "$webdirsrc/service/ipxe"

# One install: rotate, then leave a marker in the fresh gen-1 so a later run
# can say which snapshot ended up where.
install() {
    rotate
    echo "$1" > "${customizationsDir}/kernel-backups/gen-1/marker"
}

# ---------------------------------------------------------------------------
# Three installs in a row, with the default retention.
# ---------------------------------------------------------------------------
customizationsDir="$work/default"
kbdir="$customizationsDir/kernel-backups"
unset BOOT_kernel_backups_kept kernelBackupGenerations

for gen in first second third fourth; do
    install "$gen"
done

check "gen-2 is created (it never was: the loop did not run)" \
    "$([[ -d $kbdir/gen-2 ]]; echo $?)"

check "gen-3 is created" \
    "$([[ -d $kbdir/gen-3 ]]; echo $?)"

check "the default keeps three generations, not four" \
    "$([[ ! -d $kbdir/gen-4 ]]; echo $?)"

# Rotation direction: gen-1 is always the newest, and each older snapshot has
# moved DOWN the list by exactly one install.
check "gen-1 holds the newest snapshot" \
    "$([[ $(cat "$kbdir/gen-1/marker" 2>/dev/null) == fourth ]]; echo $?)"

check "gen-2 holds the one before it" \
    "$([[ $(cat "$kbdir/gen-2/marker" 2>/dev/null) == third ]]; echo $?)"

check "gen-3 holds the one before that" \
    "$([[ $(cat "$kbdir/gen-3/marker" 2>/dev/null) == second ]]; echo $?)"

# ---------------------------------------------------------------------------
# --kernel-backup-count actually changes retention. This is what the bug made
# a no-op, and a rotation that hardcoded 3 would still pass everything above.
# ---------------------------------------------------------------------------
customizationsDir="$work/kb-five"
kbdir="$customizationsDir/kernel-backups"
BOOT_kernel_backups_kept=5

for gen in 1 2 3 4 5 6; do
    install "$gen"
done

check "a retention of 5 keeps gen-5" \
    "$([[ -d $kbdir/gen-5 ]]; echo $?)"

check "a retention of 5 prunes gen-6" \
    "$([[ ! -d $kbdir/gen-6 ]]; echo $?)"

# ---------------------------------------------------------------------------
# LOWERING the retention prunes what is now above it.
#
# The prune used to remove only gen-N, so going from 5 to 3 left gen-4 and
# gen-5 on disk forever: above the rotation loop, so never shifted and never
# pruned again, frozen at whatever they last held -- while restorekernel.sh
# --list went on offering them as current snapshots.
# ---------------------------------------------------------------------------
BOOT_kernel_backups_kept=3
install lowered

check "lowering the retention prunes gen-4" \
    "$([[ ! -d $kbdir/gen-4 ]]; echo $?)"

check "and gen-5" \
    "$([[ ! -d $kbdir/gen-5 ]]; echo $?)"

check "while keeping what is still inside the new retention" \
    "$([[ -d $kbdir/gen-1 && -d $kbdir/gen-2 ]]; echo $?)"

check "and gen-1 still holds the newest snapshot" \
    "$([[ $(cat "$kbdir/gen-1/marker" 2>/dev/null) == lowered ]]; echo $?)"

# ---------------------------------------------------------------------------
# A retention of 1 must not fall into the same off-by-one from the other side:
# the loop is skipped legitimately here, and gen-1 is simply replaced.
# ---------------------------------------------------------------------------
customizationsDir="$work/kb-one"
kbdir="$customizationsDir/kernel-backups"
BOOT_kernel_backups_kept=1

for gen in old new; do
    install "$gen"
done

check "a retention of 1 keeps only gen-1" \
    "$([[ -d $kbdir/gen-1 && ! -d $kbdir/gen-2 ]]; echo $?)"

check "a retention of 1 still holds the newest snapshot" \
    "$([[ $(cat "$kbdir/gen-1/marker" 2>/dev/null) == new ]]; echo $?)"

# ---------------------------------------------------------------------------
# And that the retired name cannot come back. A tree where the rotation reads
# kernelBackupGenerations again passes every fixture above whenever that name
# happens to be set, which is exactly the legacy install the bug hid on.
# ---------------------------------------------------------------------------
# Comments stripped: the prose above the loop names the retired spelling
# in order to explain why it must not be read, and that explanation is
# worth keeping.
check "the rotation does not read the retired kernelBackupGenerations" \
    "$(! sed 's/#.*//' <<< "$snippet" | grep -q 'kernelBackupGenerations'; echo $?)"

printf '\n%s: %d passed, %d failed\n' "$(basename "$0")" "$pass" "$fail"
[[ $fail -eq 0 ]]
