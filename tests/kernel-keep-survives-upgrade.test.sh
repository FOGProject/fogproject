#!/bin/bash
#
# A boot file marked to be kept survives an upgrade.
#
#   tests/kernel-keep-survives-upgrade.test.sh
#
# configureHttpd() deletes the whole web root and rebuilds it, so everything
# in service/ipxe is destroyed on every install. What comes back is whatever
# restorePreservedCustomizations() puts back -- and the per-release siblings
# (bzImage.<release>) are deliberately NOT part of a generation, because they
# are already copies of a kernel and snapshotting them would multiply the same
# bytes by the generation count.
#
# So "keep this kernel" applied to a sibling would have survived exactly until
# the next upgrade, with nothing reporting that it had gone. The copy in
# customizations/kernel-backups/keep/ is what makes the promise real, and the
# copy IS the record: the restore is a shell function running while the web
# root is being rebuilt, with no database in reach.
#
# The restore is EXECUTED against a fixture, not read. The interesting cases
# are the two exclusions -- a fresh file of the same name must win, and the six
# default names must never be restored -- and a textual check cannot tell a
# working exclusion from an inverted one.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/kernel-keep-survives-upgrade.test.sh
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
# The keep-restore block, lifted out of restorePreservedCustomizations().
# ---------------------------------------------------------------------------
snippet=$(awk '
    /if \[\[ -d "\$\{kbdir\}\/keep" \]\]; then/ { grab = 1 }
    grab { print }
    grab && /^    fi$/ { exit }
' "$functions")

if [[ -z $snippet ]]; then
    echo "FAIL: could not find the keep-restore block in" >&2
    echo "  lib/common/functions.sh. If it moved or was rewritten, point this" >&2
    echo "  test at it -- do not delete the assertion." >&2
    exit 1
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

error_log="$work/error.log"

eval "restoreKept() {
    local st=0
    local defaultnames=\" bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz \"
    local bn f
$snippet
    return \$st
}"

kbdir="$work/customizations/kernel-backups"
ipxedir="$work/ipxe"
mkdir -p "$kbdir/keep" "$ipxedir"

# What an admin marked: a per-release sibling, and a kernel of their own.
printf 'known-good' > "$kbdir/keep/bzImage.20260701-093344"
printf 'my-kernel' > "$kbdir/keep/bzImage_MyHardware"

# And a default name, which must NOT come back -- picking up the new kernel is
# the point of an update, and this is the same exclusion the generation
# restore makes.
printf 'old-default' > "$kbdir/keep/bzImage"

# The fresh tree as an install would leave it: the new default kernel, and
# nothing else.
printf 'brand-new' > "$ipxedir/bzImage"

restoreKept

check "a kept per-release sibling is restored" \
    "$([[ -f $ipxedir/bzImage.20260701-093344 ]]; echo $?)"

check "and holds the bytes that were kept" \
    "$([[ $(cat "$ipxedir/bzImage.20260701-093344") == known-good ]]; echo $?)"

check "a kept custom-named kernel is restored" \
    "$([[ $(cat "$ipxedir/bzImage_MyHardware" 2>/dev/null) == my-kernel ]]; echo $?)"

# The whole point of the exclusion: an update must not be undone.
check "a kept file under a DEFAULT name does not overwrite the new kernel" \
    "$([[ $(cat "$ipxedir/bzImage") == brand-new ]]; echo $?)"

# ---------------------------------------------------------------------------
# The default-name exclusion ON ITS OWN.
#
# The case above does not test it: bzImage exists in the fresh tree there, so
# the "leave an existing file alone" guard already prevents the copy and
# breaking the exclusion changes nothing. Verified by mutation -- disabling
# the exclusion left that assertion passing.
#
# The discriminating fixture is a default name the fresh tree does NOT have.
# The exclusion must still refuse it: an install that did not land bzImage has
# a bigger problem than a missing kernel, and quietly reinstating the previous
# one hides it while pretending the update worked.
# ---------------------------------------------------------------------------
kbdir="$work/exclusion/kernel-backups"
mkdir -p "$kbdir/keep"
rm -rf "$ipxedir"
mkdir -p "$ipxedir"
printf 'old-default' > "$kbdir/keep/bzImage"
printf 'old-init' > "$kbdir/keep/init.xz"
printf 'mine' > "$kbdir/keep/bzImage_MyHardware"
restoreKept

check "a kept DEFAULT name is not restored even when the tree lacks it"     "$([[ ! -e $ipxedir/bzImage ]]; echo $?)"

check "the same for a default init name"     "$([[ ! -e $ipxedir/init.xz ]]; echo $?)"

check "while a custom name in the same directory still is restored"     "$([[ $(cat "$ipxedir/bzImage_MyHardware" 2>/dev/null) == mine ]]; echo $?)"

# ---------------------------------------------------------------------------
# A fresh file of the same name always wins, default name or not. The restore
# fills gaps; it does not reinstate.
# ---------------------------------------------------------------------------
kbdir="$work/customizations/kernel-backups"
rm -rf "$ipxedir"
mkdir -p "$ipxedir"
printf 'brand-new' > "$ipxedir/bzImage"
printf 'fresh-sibling' > "$ipxedir/bzImage.20260701-093344"
restoreKept

check "an existing file is left alone rather than replaced" \
    "$([[ $(cat "$ipxedir/bzImage.20260701-093344") == fresh-sibling ]]; echo $?)"

# ---------------------------------------------------------------------------
# Nothing kept, nothing to do -- and no error.
# ---------------------------------------------------------------------------
kbdir="$work/empty/kernel-backups"
mkdir -p "$kbdir/keep"
rm -rf "$ipxedir"
mkdir -p "$ipxedir"
restoreKept
rc=$?

check "an empty keep directory restores nothing and succeeds" \
    "$([[ $rc -eq 0 && -z $(ls -A "$ipxedir") ]]; echo $?)"

# ---------------------------------------------------------------------------
# No keep directory at all: every server before this change, and every one
# where the admin has kept nothing.
# ---------------------------------------------------------------------------
kbdir="$work/nokeep/kernel-backups"
mkdir -p "$kbdir"
restoreKept
rc=$?

check "a missing keep directory is not an error" \
    "$([[ $rc -eq 0 ]]; echo $?)"

# ---------------------------------------------------------------------------
# The installer has to create the directory, and has to make it writable by
# the web user -- marking a file to be kept is a web action, and the copy is
# what records it.
# ---------------------------------------------------------------------------
check "the installer creates kernel-backups/keep" \
    "$(grep -q 'mkdir -p "\$optdir/kernel-backups/keep"' "$functions"; echo $?)"

check "and gives the web user write access to it" \
    "$(grep -q 'chown "\${SVC_user}:\${apacheuser}" "\$optdir/kernel-backups/keep"' "$functions"; echo $?)"

printf '\n%s: %d passed, %d failed\n' "$(basename "$0")" "$pass" "$fail"
[[ $fail -eq 0 ]]
