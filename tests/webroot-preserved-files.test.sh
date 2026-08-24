#!/bin/bash
#
# What survives configureHttpd()'s wipe of the web root.
#
#   tests/webroot-preserved-files.test.sh
#
# configureHttpd() does `rm -rf $webdirdest` and rebuilds the tree from
# $webdirsrc, so an upgrade genuinely gets the new release and nothing else --
# except that management/other/ is where an administrator's own files live, and
# those have to come back. They are restored from the pre-wipe backup.
#
# The question that loop has to answer for each file is "is this ours or
# theirs", and it used to answer it from a hardcoded list: gpl-3.0.txt and
# index.php. That is a second description of what the release ships, kept by
# hand, and it drifted the moment FOG dropped a file it had been shipping
# there. management/other/_variables.scss -- a Font Awesome 4 icon list, dead
# since the FA7 migration -- was not on the list, so every install classified
# it as the administrator's and copied it back out of the backup. It could
# never leave an upgraded server.
#
# The question is now asked of the source tree, which cannot drift from itself.
# ca.* stays named because it is a different kind of FOG-owned file: not
# shipped in the tree at all, but minted into that directory by
# _installCATrustAnchor(). Restoring the old one over a freshly generated CA
# hands the server a stale trust anchor -- the certificate fog-client pins and
# iPXE is built against -- so that exclusion is load-bearing and is pinned
# here rather than left to be tidied away as redundant.
#
# The loop is EXECUTED against a fixture, not read. A textual check passes on a
# loop that names the right variables and gets the logic backwards, and the
# logic is the whole of this change.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/webroot-preserved-files.test.sh
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
# The loop itself, lifted out of configureHttpd().
# ---------------------------------------------------------------------------
snippet=$(awk '
    /^    local retired_web_other=/ { grab = 1 }
    grab { print }
    grab && /^    done$/ { exit }
' "$functions")

if [[ -z $snippet ]]; then
    echo "FAIL: could not find the management/other restore loop in" >&2
    echo "  lib/common/functions.sh. If it moved or was rewritten, point this" >&2
    echo "  test at it -- do not delete the assertion." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Run it against a fixture standing in for one upgrade.
# ---------------------------------------------------------------------------
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

DB_backup_path="$work/backup"
version="1.2.3"
webdirsrc="$work/src"
webdirdest="$work/dest"
error_log="$work/error.log"
backup="$DB_backup_path/fog_web_${version}.BACKUP/management/other"

mkdir -p "$backup" "$webdirsrc/management/other" "$webdirdest/management/other"

# What the NEW release ships there.
: > "$webdirsrc/management/other/index.php"
: > "$webdirsrc/management/other/gpl-3.0.txt"
cp "$webdirsrc/management/other/index.php" "$webdirdest/management/other/index.php"
cp "$webdirsrc/management/other/gpl-3.0.txt" "$webdirdest/management/other/gpl-3.0.txt"

# What the pre-wipe backup holds.
echo old > "$backup/index.php"
echo old > "$backup/gpl-3.0.txt"
# A file FOG used to ship there and has since dropped -- the actual bug.
echo dead > "$backup/_variables.scss"
# The administrator's own.
echo mine > "$backup/company-logo.png"
# Minted by the PKI step, never shipped in the tree.
echo stale > "$backup/ca.cert.pem"
echo stale > "$backup/ca.cert.der"

# Wrapped in a function, which is where it really runs (configureHttpd) and
# what makes its `local` declarations legal.
eval "fog_restore_web_other() {
$snippet
}"
fog_restore_web_other

out="$webdirdest/management/other"

check "a file the release still ships is not overwritten from the backup" \
    "$([[ ! -s $out/index.php && ! -s $out/gpl-3.0.txt ]]; echo $?)"

check "a file the release has DROPPED is not resurrected from the backup" \
    "$([[ ! -e $out/_variables.scss ]]; echo $?)"

check "the administrator's own file is restored" \
    "$([[ -e $out/company-logo.png ]]; echo $?)"

check "a minted ca.* is never restored over the newly generated one" \
    "$([[ ! -e $out/ca.cert.pem && ! -e $out/ca.cert.der ]]; echo $?)"

# ---------------------------------------------------------------------------
# And that the decision is not made from a hand-kept list again.
#
# Comments stripped: the prose above the loop names both files while
# explaining why they are no longer named, and a gate its own documentation
# satisfies is not a gate.
# ---------------------------------------------------------------------------
findline=$(printf '%s\n' "$snippet" | sed 's/#.*//' | grep 'for i in')

check "the restore loop names no individual shipped file" \
    "$(! printf '%s\n' "$findline" | grep -qE 'gpl-3\.0\.txt|index\.php'; echo $?)"

check "the restore loop still excludes the minted ca.*" \
    "$(printf '%s\n' "$findline" | grep -q "not -name 'ca\.\*'"; echo $?)"

check "the decision is asked of the source tree" \
    "$(printf '%s\n' "$snippet" | sed 's/#.*//' | grep -q 'webdirsrc.*management/other'; echo $?)"

total=$((pass + fail))
if [[ $fail -gt 0 ]]; then
    printf 'FAIL: %d of %d checks failed\n' "$fail" "$total" >&2
    exit 1
fi
printf 'ok  %d checks passed\n' "$total"
exit 0
