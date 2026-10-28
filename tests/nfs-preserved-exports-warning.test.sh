#!/bin/bash
#
# The preserved-exports warning must be about the image staging export only.
#
#   tests/nfs-preserved-exports-warning.test.sh
#
# GH-1988. When the installer keeps an existing /etc/exports, configureNFS()
# warns that captures will fail to rename if <share>/dev still uses
# no_root_squash. The check was `grep -q no_root_squash` over the whole file,
# so an unrelated export -- an ISO share, say -- raised the warning on a
# server whose /images/dev was already all_squash, and the warning told the
# admin to change an export that was already right.
#
# nfsDevExportHasNoRootSquash() is EXECUTED here against sample exports files.
# Put the whole-file grep back in the helper and the first assertion goes red.
# The call site is checked textually too: a correct helper that configureNFS()
# does not call leaves the bug in place.
#
# No root, no NFS, no FOG install.
#
# Usage: bash tests/nfs-preserved-exports-warning.test.sh
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

# Lifted rather than sourced, as in installer-epel-already-provided.test.sh.
lift() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "$functions"
}

helper=$(lift nfsDevExportHasNoRootSquash)
if [[ -z $helper ]]; then
    echo "FAIL: nfsDevExportHasNoRootSquash() not found in $functions" >&2
    exit 1
fi
eval "$helper"

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# warns <name> <expected 0|1> <exports content>
#   0 = the helper must report no_root_squash on /images/dev
#   1 = it must not
warns() {
    printf '%s\n' "$3" >"$work/exports"
    nfsDevExportHasNoRootSquash "$work/exports" /images
    [[ $? -eq $2 ]]
    check "$1" $?
}

safe_dev='/images *(ro,sync,no_wdelay,subtree_check,insecure_locks,all_squash,anonuid=1002,anongid=1002,fsid=0)
/images/dev *(rw,async,no_wdelay,subtree_check,all_squash,anonuid=1002,anongid=1002,fsid=1)'

warns "an unrelated export with no_root_squash is not a warning" 1 \
    "$safe_dev
/srv/iso *(ro,no_root_squash)"

warns "the read-only /images export with no_root_squash is not a warning" 1 \
    "/images *(ro,sync,no_root_squash,fsid=0)
/images/dev *(rw,async,all_squash,anonuid=1002,anongid=1002,fsid=1)"

warns "/images/dev with no_root_squash is a warning" 0 \
    "/images *(ro,sync,all_squash,fsid=0)
/images/dev *(rw,async,no_wdelay,no_root_squash,insecure,fsid=1)"

warns "/images/dev with a trailing slash is still /images/dev" 0 \
    "/images/dev/ *(rw,no_root_squash)"

warns "a quoted /images/dev path is still /images/dev" 0 \
    '"/images/dev" *(rw,no_root_squash)'

warns "a continued /images/dev line is read as one export" 0 \
    "/images/dev \\
    *(rw,no_root_squash)"

warns "a look-alike path is not /images/dev" 1 \
    "$safe_dev
/images/dev2 *(rw,no_root_squash)"

warns "a commented-out /images/dev line is ignored" 1 \
    "$safe_dev
# /images/dev *(rw,no_root_squash)"

warns "a trailing comment on /images/dev is ignored" 1 \
    "/images/dev *(rw,all_squash,anonuid=1002,anongid=1002) # was no_root_squash"

rm -f "$work/exports"
nfsDevExportHasNoRootSquash "$work/exports" /images
[[ $? -ne 0 ]]
check "a missing exports file is not a warning" $?

# The call site: configureNFS() must ask the helper, not grep the file.
body=$(lift configureNFS)
grep -q 'nfsDevExportHasNoRootSquash "\$nfsconfig"' <<<"$body"
check "configureNFS() calls nfsDevExportHasNoRootSquash" $?
! grep -q 'grep -q "no_root_squash"' <<<"$body"
check "configureNFS() no longer greps the whole exports file" $?

printf '%d passed, %d failed\n' "$pass" "$fail"
[[ $fail -eq 0 ]]
