#!/bin/bash
#
# A second install run keeps the web backup of the release it upgraded from.
#
#   tests/web-backup-keeps-other-release.test.sh
#
# configureHttpd() names the web backup fog_web_${version}.BACKUP, after the
# version being INSTALLED, and removes any existing one before it copies the
# current tree in. A 1.5 -> 1.6 upgrade that is run twice therefore replaced the
# 1.5 tree with the 1.6 one, and bin/revertfog.sh had no web tree left to
# restore (GH-2000). _setAsideOtherVersionWebBackup() renames a backup of a
# different release out of the way instead.
#
# The function is EXECUTED against fixtures, not read.
#
# No root, no network, no FOG install.
#
# Usage: bash tests/web-backup-keeps-other-release.test.sh
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

snippet=$(awk '
    /^_setAsideOtherVersionWebBackup\(\) \{/ { grab = 1 }
    grab { print }
    grab && /^\}$/ { exit }
' "$functions")
if [[ -z $snippet ]]; then
    echo "FAIL: could not find _setAsideOtherVersionWebBackup in lib/common/functions.sh" >&2
    exit 1
fi
eval "$snippet"

# The backup step must call it before it removes the old backup.
grep -Pzo '_setAsideOtherVersionWebBackup\n    if \[\[ -d \$\{DB_backup_path\}/fog_web_\$\{version\}\.BACKUP \]\]; then\n        rm -rf' "$functions" >/dev/null
check "configureHttpd() calls it before removing fog_web_\${version}.BACKUP" $?

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
error_log="$work/error.log"
version="1.6.0-RC-19"

# Write a backup holding release $2 in the layout $3 (15, 16, 16fallback).
mkbackup() {
    local dir="$DB_backup_path/$1"
    case $3 in
        15) mkdir -p "$dir/lib/fog"; printf "define('FOG_VERSION', '%s');\n" "$2" > "$dir/lib/fog/system.class.php" ;;
        16) mkdir -p "$dir/commons" "$dir/src/Base"
            printf "define('FOG_VERSION', '%s');\n" "$2" > "$dir/commons/version.php"
            printf "define('FOG_VERSION', 'fallback');\n" > "$dir/src/Base/System.php" ;;
        16fallback) mkdir -p "$dir/src/Base"; printf "define('FOG_VERSION', '%s');\n" "$2" > "$dir/src/Base/System.php" ;;
    esac
    touch "$dir/marker-$2"
}
reset() { rm -rf "$work/backup"; DB_backup_path="$work/backup"; mkdir -p "$DB_backup_path"; }

# --- the GH-2000 rerun: the backup holds 1.5 ---------------------------------
reset
mkbackup "fog_web_${version}.BACKUP" "1.5.10.2518" 15
_setAsideOtherVersionWebBackup
[[ ! -e $DB_backup_path/fog_web_${version}.BACKUP ]]
check "a 1.5 tree under the 1.6 name is moved off that name" $?
[[ -f $DB_backup_path/fog_web_1.5.10.2518.BACKUP/marker-1.5.10.2518 ]]
check "and kept, renamed after its own version" $?

# --- that name is already taken: keep both -----------------------------------
reset
mkbackup "fog_web_1.5.10.2518.BACKUP" "1.5.10.2518" 15
mkbackup "fog_web_${version}.BACKUP" "1.5.10.2518" 15
_setAsideOtherVersionWebBackup
n=$(find "$DB_backup_path" -maxdepth 1 -name 'fog_web_1.5.10.2518_*.BACKUP' | wc -l)
[[ $n -eq 1 && -d $DB_backup_path/fog_web_1.5.10.2518.BACKUP ]]
check "an existing backup of that release is not overwritten" $?

# --- an older 1.6 build, read from the generated version file ----------------
reset
mkbackup "fog_web_${version}.BACKUP" "1.6.0-RC-18" 16
_setAsideOtherVersionWebBackup
[[ -d $DB_backup_path/fog_web_1.6.0-RC-18.BACKUP && ! -e $DB_backup_path/fog_web_${version}.BACKUP ]]
check "a 1.6 tree's generated version.php wins over the fallback" $?

# --- the same release: left for the caller to replace, as before -------------
reset
mkbackup "fog_web_${version}.BACKUP" "$version" 16fallback
_setAsideOtherVersionWebBackup
[[ -f $DB_backup_path/fog_web_${version}.BACKUP/marker-$version ]]
check "a backup of the release being installed stays where it is" $?
[[ $(find "$DB_backup_path" -mindepth 1 -maxdepth 1 | wc -l) -eq 1 ]]
check "and nothing else is created" $?

# --- no backup, or one with no readable version ------------------------------
reset
_setAsideOtherVersionWebBackup
check "no backup: returns 0" $?
mkdir -p "$DB_backup_path/fog_web_${version}.BACKUP"
_setAsideOtherVersionWebBackup
[[ -d $DB_backup_path/fog_web_${version}.BACKUP ]]
check "an unreadable version leaves the backup to the old path" $?

echo "web backup keeps other release: $pass passed, $fail failed"
[[ $fail -eq 0 ]]
