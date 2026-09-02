#!/bin/bash
#
#  FOG is a computer imaging solution.
#  Copyright (C) 2007  Chuck Syperski & Jian Zhang
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#    any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# The way back from an update that stayed on this release line (GH-1659).
#
# An update records two things it can be undone from and, until this script,
# surfaced neither on its own: FOG_last_good_commit in .fogsettings names the
# commit the last COMPLETED install ran from, and backupDB() writes a dump of
# the database immediately before the schema is migrated. offerRevert()
# prints both -- but only from installfog.sh's EXIT trap, so only on a run
# that failed, and never again. An update that succeeded and then turned out
# to have broken something had no command to run at all.
#
# This script is that command, in two halves that are deliberately separate:
#
#   --checkout           moves the working copy back to FOG_last_good_commit
#                        (git checkout --detach, as offerRevert suggests)
#   --restore-db <dump>  puts a pre-migration dump back
#
# Run with neither and it only REPORTS: where the checkout is against where it
# was, what schema the database is on against what the code expects, and
# which of the dumps on disk could be restored. Nothing is changed.
#
# Why it is not bin/revertfog.sh: that script is the 1.6 -> 1.5 crossing. It
# restores the pre-upgrade 1.5 web tree as well as the database, and its
# _dumpNotFifteenReason() proof refuses any dump that reads as 1.6 -- which is
# every dump this script exists to restore. The proof here is a different
# question: not "is this dump 1.5" but "does this dump match the schema of
# the code that is checked out". A dump's schemaVersion row and the checked-
# out System.php's FOG_SCHEMA are both readable without touching the
# database, so a mismatch is refused before anything is stopped or dropped.
#
# See docs/development/reverting-an-upgrade.md.
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "revertupdate.sh must be run as root user"
    exit 1
fi

usage() {
    echo -e "Usage: $0 [-h?] [--checkout] [--restore-db <dump>] [--yes]"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --checkout\tMove the working copy back to the commit the"
    echo -e "\t                \tlast completed install ran from"
    echo -e "\t      --restore-db\tRestore a pre-migration database dump. Refused"
    echo -e "\t                  \tunless its schema matches the checked-out code"
    echo -e "\t-y    --yes\t\tSkip the confirmation prompt"
    echo
    echo -e "With no action, reports what could be reverted and changes nothing."
    exit 0
}

shortopts="h?y"
longopts="help,checkout,restore-db:,yes"
optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

doCheckout=0
dumpfile=""
autoYes=""
while :; do
    case $1 in
        -h | -\? | --help)
            usage
            ;;
        --checkout)
            doCheckout=1
            shift
            ;;
        --restore-db)
            dumpfile="$2"
            shift 2
            ;;
        -y | --yes)
            autoYes=1
            shift
            ;;
        --)
            shift
            break
            ;;
        *)
            echo "Error: unhandled option '$1'."
            exit 10
            ;;
    esac
done

[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
error_log="${workingdir}/error_logs/fog_revertupdate_error.log"
: > "$error_log"

# exitFail so a failing errorStat inside the sourced functions returns control
# here instead of killing the script mid-restore. Same reason restorekernel.sh
# and revertfog.sh set it.
exitFail=1
. ../lib/common/functions.sh

# Same resolution order as restorekernel.sh and revertfog.sh: the
# /etc/fog/fog.conf pointer, then .fogsettings for what the last install
# recorded, then config.sh for what it derives.
[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"

if [[ ! -r "$fogprogramdir/.fogsettings" ]]; then
    echo " * No existing FOG install found at $fogprogramdir (.fogsettings missing)."
    echo " * revertupdate.sh undoes an UPDATE -- there is no install here to go back from."
    exit 1
fi
. "$fogprogramdir/.fogsettings"
migrateDeprecatedKeys
# Saved BEFORE config.sh, which overwrites FOG_git_path with the tree this
# script runs from. That is right for the installer -- "the git path is
# wherever you ran me" -- and wrong here: the recorded value is the tree the
# last completed install ran from, which is the one FOG_last_good_commit is
# a commit OF. An admin running this from a second clone still gets the
# checkout the record names, and the fallback is config.sh's answer.
settingsGitPath="${FOG_git_path:-}"
linuxReleaseName_lower="${FOG_os_name,,}"
. ../lib/common/config.sh
[[ -n ${FOG_os_id} ]] && doOSSpecificIncludes >/dev/null

gitpath="${settingsGitPath:-${FOG_git_path:-$(dirname "$bindir")}}"
if [[ ! -d ${gitpath}/.git ]]; then
    echo " * ${gitpath} is not a git checkout, so there is no commit to go back to."
    echo " * FOG_git_path in ${fogprogramdir}/.fogsettings names the tree the installer ran from."
    exit 1
fi

# Read the same way offerRevert() does: off disk, stripped of quoting.
recorded=$(sed -n 's/^FOG_last_good_commit=//p' "${fogprogramdir}/.fogsettings" 2>/dev/null | tr -d "\"' " | tail -n1)
head=$(git -C "$gitpath" rev-parse HEAD 2>/dev/null)

# FOG_SCHEMA of the code at a given commit, read out of git rather than off
# disk so it can be asked of a commit that is NOT checked out -- which is how
# the report says "this dump would be restorable after --checkout" without
# moving anything first. Empty for a commit with no 1.6 System.php, which is
# how a 1.5 commit is told apart: that crossing belongs to revertfog.sh.
schemaOfCommit() {
    git -C "$gitpath" show "$1:packages/web/src/Base/System.php" 2>/dev/null \
        | awk -F'[(),]' '/define\(.FOG_SCHEMA.,/ {gsub(/[[:space:]]/, "", $3); print $3; exit}'
}
# The schemaVersion row a dump would put back. mysqldump-php and the mysql
# client both write it as INSERT INTO `schemaVersion` VALUES (1,'N').
schemaOfDump() {
    sed -n "s/^INSERT INTO \`schemaVersion\` VALUES (1,'\([0-9]*\)').*/\1/p" "$1" 2>/dev/null | head -n1
}
short() { echo "${1:0:12}"; }

recordedSchema=""
[[ -n $recorded ]] && recordedSchema=$(schemaOfCommit "$recorded")
headSchema=$(schemaOfCommit "$head")

[[ -n ${DB_host} ]] && host="--host=${DB_host}"
sqloptionsuser="${host} -s --user=${DB_user}"
liveSchema=""
if [[ ${DB_external} != yes ]]; then
    liveSchema=$(schemaVersionInDB 2>/dev/null)
fi

dumpdir="${DB_backup_path%/}/fogDBbackups"

# ---------------------------------------------------------------------------
# Report. This runs on every invocation, because a checkout or a restore that
# does not first say what it found is a checkout or a restore nobody can
# check afterwards.
# ---------------------------------------------------------------------------
echo " * Checkout: ${gitpath}"
if [[ -z $recorded ]]; then
    echo " |   last completed install: not recorded (FOG_last_good_commit is empty)"
elif [[ $head == "$recorded" ]]; then
    echo " |   at $(short "$head"), which is where the last completed install ran from"
else
    echo " |   now at:                $(short "$head")  (code expects schema ${headSchema:-unknown})"
    echo " |   last completed install: $(short "$recorded")  (code expects schema ${recordedSchema:-unknown})"
fi
echo " * Database:"
if [[ ${DB_external} == yes ]]; then
    echo " |   external -- not read, and not restored by this script"
elif [[ -n $liveSchema ]]; then
    echo " |   schema ${liveSchema}"
else
    echo " |   could not read the schema version (is the database up?)"
fi
echo " * Dumps under ${dumpdir}:"
dumps=()
while IFS= read -r f; do
    [[ -n $f ]] && dumps+=("$f")
done < <(ls -t "${dumpdir}"/fog_sql_*.sql 2>/dev/null)
if [[ ${#dumps[@]} -eq 0 ]]; then
    echo " |   none"
fi
for f in "${dumps[@]}"; do
    s=$(schemaOfDump "$f")
    note=""
    if [[ -z $s ]]; then
        note="no schemaVersion row -- not restorable"
    elif [[ -n $headSchema && $s == "$headSchema" ]]; then
        note="matches the checked-out code -- restorable now"
    elif [[ -n $recordedSchema && $s == "$recordedSchema" ]]; then
        note="matches the last completed install -- restorable after --checkout"
    else
        note="matches neither"
    fi
    echo " |   $(basename "$f")  schema ${s:-?}  ($note)"
done

if [[ $doCheckout -eq 0 && -z $dumpfile ]]; then
    echo
    echo " * Nothing was changed. To go back:"
    if [[ -n $recorded && $head != "$recorded" ]]; then
        echo " |     $0 --checkout"
    fi
    echo " |     $0 --restore-db <dump>"
    echo " |     cd ${gitpath}/bin && ./installfog.sh"
    echo " | The installer run is what puts the checked-out code back in service;"
    echo " | neither step above touches the deployed web tree."
    exit 0
fi

# ---------------------------------------------------------------------------
# --checkout
# ---------------------------------------------------------------------------
if [[ $doCheckout -eq 1 ]]; then
    echo
    if [[ -z $recorded ]]; then
        echo " * No commit is recorded to go back to. Nothing done."
        exit 1
    fi
    if [[ $head == "$recorded" ]]; then
        echo " * The checkout is already at $(short "$recorded"). Nothing done."
        exit 0
    fi
    if [[ -z $recordedSchema ]]; then
        echo " * $(short "$recorded") is not a 1.6 commit. Going back across the 1.5"
        echo " * boundary restores a web tree as well, which is bin/revertfog.sh's job."
        exit 1
    fi
    echo " * About to move ${gitpath} to $(short "$recorded") (detached HEAD)."
    echo " * Local changes that conflict will make git refuse; nothing is discarded."
    if [[ -z $autoYes ]]; then
        echo -n " * Continue? (y/N) "
        read -r reply
        case $reply in
            [Yy]|[Yy][Ee][Ss]) ;;
            *) echo " * Aborted."; exit 0 ;;
        esac
    fi
    # --detach, not reset --hard, for the reason offerRevert() gives: the
    # checkout may be on a different branch than the recorded commit belongs
    # to, and moving a branch ref at a foreign commit leaves a diverged branch
    # behind. Detaching moves only HEAD.
    dots "Checking out $(short "$recorded")"
    if git -C "$gitpath" checkout --detach "$recorded" >>$error_log 2>&1; then
        errorStat 0
        head="$recorded"
        headSchema="$recordedSchema"
    else
        echo "Failed"
        echo " * git refused. See $error_log -- usually local changes in the way."
        exit 1
    fi
fi

# ---------------------------------------------------------------------------
# --restore-db
# ---------------------------------------------------------------------------
if [[ -n $dumpfile ]]; then
    echo
    if [[ ${DB_external} == yes ]]; then
        echo " * DB_external=yes: this server's database is not managed here, so this"
        echo " * script will not restore into it. Restore the dump on that server."
        exit 1
    fi
    [[ $dumpfile != /* ]] && dumpfile="${workingdir}/${dumpfile}"
    if [[ ! -s $dumpfile ]]; then
        echo " * ${dumpfile} does not exist or is empty. Nothing done."
        exit 1
    fi
    dumpschema=$(schemaOfDump "$dumpfile")
    if [[ -z $dumpschema ]]; then
        echo " * ${dumpfile} carries no schemaVersion row, so there is no telling what"
        echo " * code it belongs to. Nothing done."
        exit 1
    fi
    # THE PROOF. The dump has to match the code that will run against it,
    # which is whatever is checked out now -- after --checkout, that is the
    # recorded commit. Old code at a newer schema, or new code at an older
    # one, is the state this script exists to get people OUT of, so it will
    # not create it.
    if [[ -z $headSchema ]]; then
        echo " * The checked-out commit has no 1.6 System.php. If you are going back to"
        echo " * 1.5, bin/revertfog.sh restores the database AND the web tree; this"
        echo " * script does neither for that crossing. Nothing done."
        exit 1
    fi
    if [[ $dumpschema != "$headSchema" ]]; then
        echo " * REFUSED: $(basename "$dumpfile") is schema ${dumpschema}, and the checked-out"
        echo " * code at $(short "$head") expects schema ${headSchema}. Restoring it would leave the"
        echo " * database and the code disagreeing, which is what you are trying to undo."
        if [[ -n $recordedSchema && $dumpschema == "$recordedSchema" && $head != "$recorded" ]]; then
            echo " * It does match the last completed install: run --checkout first, or"
            echo " * pass both flags together."
        fi
        echo " * Nothing done."
        exit 1
    fi
    if [[ -n $liveSchema && $liveSchema == "$dumpschema" ]]; then
        echo " * Note: the database is already on schema ${liveSchema}. Restoring replaces"
        echo " * its contents with the dump's all the same."
    fi

    # Credentials, proven before anything is stopped -- same shape as
    # revertfog.sh, and for the same reason: a revert that cannot reach the
    # database can only get halfway.
    if ! mysql $sqloptionsuser --password="${DB_password}" --execute="quit" >/dev/null 2>&1; then
        if detectMysqlSslOption $sqloptionsuser --password="${DB_password}"; then
            sqloptionsuser="${sqloptionsuser} ${mysqlsslopt}"
        else
            echo " * Cannot connect to the database as '${DB_user}' with the credentials in"
            echo " * ${fogprogramdir}/.fogsettings. Fix that first. Nothing done."
            exit 1
        fi
    fi

    predump="${dumpdir}/fog_sql_prerevert_$(date +"%Y%m%d_%H%M%S").sql"
    echo " * About to:"
    echo " |   dump the current database to ${predump}"
    echo " |   stop the FOG daemons ($serviceList)"
    echo " |   drop every table in \`${DB_name}\` and restore $(basename "$dumpfile") (schema ${dumpschema})"
    echo " | Everything recorded since that dump was taken is lost. Then re-run"
    echo " | installfog.sh to put the code back in service."
    if [[ -z $autoYes ]]; then
        echo -n " * Continue? (y/N) "
        read -r reply
        case $reply in
            [Yy]|[Yy][Ee][Ss]) ;;
            *) echo " * Aborted."; exit 0 ;;
        esac
    fi

    dots "Dumping the current database first"
    mkdir -p "$dumpdir" >>$error_log 2>&1
    # Not $sqloptionsuser: it carries -s, which mysqldump rejects. Found by
    # revertfog.sh's rehearsal, not by reading.
    if command -v mysqldump >/dev/null 2>&1 \
        && mysqldump ${host} --user="${DB_user}" ${mysqlsslopt:-} \
            --password="${DB_password}" "${DB_name}" > "$predump" 2>>$error_log; then
        chmod 600 "$predump" >>$error_log 2>&1
        errorStat 0
    else
        echo "Failed"
        echo " * Could not dump the current database. See $error_log."
        echo " * Stopping here: nothing has been touched."
        rm -f "$predump"
        exit 1
    fi

    stopInitScript

    dots "Dropping the current tables"
    # By name rather than DROP DATABASE, so the database object and the FOG
    # user's grants survive; FOREIGN_KEY_CHECKS off because 1.6 declares
    # constraints (ADR 0031) and SHOW TABLES is not dependency order.
    tables=$(mysql $sqloptionsuser --password="${DB_password}" -N -B --execute="SHOW TABLES" "${DB_name}" 2>>$error_log)
    if [[ -n $tables ]]; then
        droplist=$(printf '%s\n' "$tables" | sed 's/^/`/; s/$/`/' | paste -sd, -)
        if ! mysql $sqloptionsuser --password="${DB_password}" \
            --execute="SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS ${droplist}; SET FOREIGN_KEY_CHECKS=1;" \
            "${DB_name}" >>$error_log 2>&1; then
            echo "Failed"
            echo " * Could not drop the existing tables. See $error_log."
            echo " * The database is half-dropped at best. Restore from ${predump}"
            echo " * or from ${dumpfile} by hand before doing anything else."
            exit 1
        fi
    fi
    errorStat 0

    dots "Restoring $(basename "$dumpfile")"
    if mysql $sqloptionsuser --password="${DB_password}" "${DB_name}" < "$dumpfile" >>$error_log 2>&1; then
        errorStat 0
    else
        echo "Failed"
        echo " * The restore did not complete. See $error_log."
        echo " * \`${DB_name}\` now holds a PARTIAL restore. The dump is still at"
        echo " * ${dumpfile} and the pre-revert state is at ${predump}."
        exit 1
    fi
    echo " * Database restored to schema ${dumpschema}. The daemons are stopped."
fi

echo
echo " * Now re-run the installer from the checked-out code:"
echo " |     cd ${gitpath}/bin && ./installfog.sh"
echo " | That redeploys the web tree to match, and starts the daemons again."
exit 0
