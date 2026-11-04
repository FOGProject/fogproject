#!/bin/bash
#
# _stripFogExportsFrom() must remove FOG's own exports and nothing else.
#
#   tests/nfs-exports-dropin.test.sh
#
# Forum 18303. configureNFS() used to move /etc/exports aside and write only
# FOG's two lines, so every update dropped the admin's own exports. FOG now
# writes /etc/exports.d/fog.exports and never rewrites /etc/exports. An install
# from before that still has FOG's lines in /etc/exports; _stripFogExportsFrom()
# takes exactly those out. It is EXECUTED here against scratch files. Make it
# strip by prefix and the look-alike case goes red.
#
# No root, no NFS, no FOG install.
#
# Usage: bash tests/nfs-exports-dropin.test.sh
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

# Lifted rather than sourced, as in nfs-preserved-exports-warning.test.sh.
lift() {
    awk -v fn="$1" '
        $0 ~ "^" fn "\\(\\) \\{" { grab = 1 }
        grab { print }
        grab && /^\}/ { exit }
    ' "${2:-$functions}"
}

helper=$(lift _stripFogExportsFrom)
if [[ -z $helper ]]; then
    echo "FAIL: _stripFogExportsFrom() not found in $functions" >&2
    exit 1
fi
eval "$helper"

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
error_log="$work/error.log"
timestamp="20261104.000000"

fog_ro='/images *(ro,sync,no_wdelay,subtree_check,insecure_locks,all_squash,anonuid=1002,anongid=1002,fsid=0)'
fog_rw='/images/dev *(rw,async,no_wdelay,subtree_check,all_squash,anonuid=1002,anongid=1002,fsid=1)'
admin='/srv/kubuntu *(ro,sync,no_subtree_check,insecure,fsid=2)'

# (a) FOG's two lines go; the admin's line, a comment and a blank line stay.
f="$work/a"
printf '# my exports\n%s\n\n%s\n%s\n' "$fog_ro" "$admin" "$fog_rw" >"$f"
printf '# my exports\n\n%s\n' "$admin" >"$work/a.want"
chmod 640 "$f"
_stripFogExportsFrom "$f" /images
cmp -s "$f" "$work/a.want"
check "(a) only FOG's two entries are removed" $?
[[ -f $f.fog-migrate.$timestamp ]]
check "(a) a .fog-migrate.<timestamp> backup is written" $?
# restorePreFogConfig() (uninstall.sh) takes the oldest <file>.[0-9]*. The
# migration backup must not match, or uninstall restores a file that still
# holds FOG's lines. Run the same glob here.
bak=("$f".[0-9]*)
[[ ! -e ${bak[0]} ]]
check "(a) the migration backup is invisible to the uninstall glob" $?
[[ $(stat -c %a "$f") == 640 ]]
check "(a) the file mode survives" $?
grep -q '^/images' "$f.fog-migrate.$timestamp" && grep -q kubuntu "$f.fog-migrate.$timestamp"
check "(a) the backup holds the original" $?

# (i) The real restorePreFogConfig(): it restores the oldest <file>.<timestamp>
# and never the migration backup, even when that is the only backup present.
restore=$(lift restorePreFogConfig "$root/lib/common/uninstall.sh")
if [[ -z $restore ]]; then
    check "(i) restorePreFogConfig() found in uninstall.sh" 1
else
    eval "$restore"
    f="$work/i"
    printf 'pre-fog\n' >"$f.20250101.000000"
    printf 'fog-era\n' >"$f.fog-migrate.20240101.000000"
    printf 'current\n' >"$f"
    restorePreFogConfig "$f" >/dev/null
    [[ $(cat "$f") == pre-fog ]]
    check "(i) uninstall restores the pre-FOG backup, not the migration backup" $?
    f="$work/i2"
    printf 'fog-era\n' >"$f.fog-migrate.20240101.000000"
    printf 'current\n' >"$f"
    restorePreFogConfig "$f" >/dev/null
    [[ $(cat "$f") == current ]]
    check "(i) a migration backup alone is never restored" $?
fi

# (b) A trailing slash plus double quotes, and a backslash continuation.
f="$work/b"
printf '"/images/" *(ro,fsid=0)\n/images/dev \\\n    *(rw,fsid=1)\n%s\n' "$admin" >"$f"
printf '%s\n' "$admin" >"$work/b.want"
_stripFogExportsFrom "$f" /images
cmp -s "$f" "$work/b.want"
check "(b) quoted, trailing-slash and continued entries are removed" $?

# (c) A path that only starts with the share path is the admin's.
f="$work/c"
printf '%s\n/images2 *(ro)\n/images/devices *(ro)\n%s\n' "$fog_ro" "$fog_rw" >"$f"
printf '/images2 *(ro)\n/images/devices *(ro)\n' >"$work/c.want"
_stripFogExportsFrom "$f" /images
cmp -s "$f" "$work/c.want"
check "(c) /images2 and /images/devices are kept" $?

# (d) No FOG entry: the file is not touched and no backup appears.
f="$work/d"
printf '# nothing of FOG here\n%s\n' "$admin" >"$f"
before=$(cksum <"$f")
inode_before=$(stat -c %i "$f")
_stripFogExportsFrom "$f" /images
[[ $(cksum <"$f") == "$before" && $(stat -c %i "$f") == "$inode_before" ]]
check "(d) a file with no FOG entry is unchanged" $?
leftovers=("$f".*)
[[ ! -e ${leftovers[0]} ]]
check "(d) no backup or temp file is written for an unchanged file" $?

# (e) A file that ends on a backslash continuation keeps its pending text.
# The rewrite adds a trailing newline; that is accepted.
f="$work/e"
printf '%s\n%s\n/srv/last \\' "$fog_ro" "$admin" >"$f"
printf '%s\n/srv/last \\\n' "$admin" >"$work/e.want"
_stripFogExportsFrom "$f" /images
cmp -s "$f" "$work/e.want"
check "(e) a trailing continuation line is not lost" $?

# (f) Doubled trailing slash on the share path still matches.
f="$work/f"
printf '%s\n%s\n%s\n' "$fog_ro" "$admin" "$fog_rw" >"$f"
printf '%s\n' "$admin" >"$work/f.want"
_stripFogExportsFrom "$f" /images//
cmp -s "$f" "$work/f.want"
check "(f) a doubled trailing slash on the share path is normalized" $?

# (g) An empty share path leaves the file alone.
f="$work/g"
printf '%s\n%s\n' "$fog_ro" "$admin" >"$f"
before=$(cksum <"$f")
_stripFogExportsFrom "$f" ""
[[ $(cksum <"$f") == "$before" ]]
check "(g) an empty share path does not touch the file" $?
leftovers=("$f".*)
[[ ! -e ${leftovers[0]} ]]
check "(g) an empty share path writes no backup or temp file" $?

# (h) A failed backup leaves the file unchanged and no temp file behind.
f="$work/h"
printf '%s\n%s\n' "$fog_ro" "$admin" >"$f"
before=$(cksum <"$f")
ln -s "$work/no-such-dir/x" "$f.fog-migrate.$timestamp"   # cp through it fails
_stripFogExportsFrom "$f" /images
rc=$?
[[ $rc -ne 0 && $(cksum <"$f") == "$before" ]]
check "(h) a failed backup returns non-zero and leaves the file alone" $?
leftovers=("$f".fogtmp.*)
[[ ! -e ${leftovers[0]} ]]
check "(h) a failed backup leaves no temp file" $?

# A missing file is not an error.
_stripFogExportsFrom "$work/missing" /images
check "a missing exports file returns success" $?

# The call site: configureNFS() must write the drop-in, never the admin's file.
body=$(lift configureNFS)
grep -q '_stripFogExportsFrom "\$nfsconfig"' <<<"$body"
check "configureNFS() migrates FOG's lines out of \$nfsconfig" $?
grep -q 'mv -f "\$newexports" "\$nfsfogexports"' <<<"$body"
check "configureNFS() writes FOG's exports to \$nfsfogexports" $?
# The strip must come after the temp drop-in is written, so a failed mkdir or
# write leaves FOG's lines in $nfsconfig.
wline=$(grep -n '> "\$newexports"' <<<"$body" | head -1 | cut -d: -f1)
sline=$(grep -n '_stripFogExportsFrom "\$nfsconfig"' <<<"$body" | head -1 | cut -d: -f1)
[[ -n $wline && -n $sline && $wline -lt $sline ]]
check "configureNFS() strips \$nfsconfig only after the temp drop-in is written" $?
! grep -q '> "\$nfsconfig"' <<<"$body"
check "configureNFS() does not rewrite \$nfsconfig" $?
grep -q '_stripFogExportsFrom "\$nfsconfig".*|| stripfailed=1' <<<"$body"
check "configureNFS() checks the result of the strip" $?

printf '%d passed, %d failed\n' "$pass" "$fail"
[[ $fail -eq 0 ]]
