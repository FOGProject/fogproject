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
# Puts a server that has upgraded to 1.6 back onto its pre-upgrade 1.5
# database, web tree and FOS kernels.
#
# The material to go back with already exists -- backupDB() writes a dump, and
# configureHttpd() copies the old web tree aside -- and until now nothing
# assembled it. This is that assembly, and it is its own script for the same
# reason restorekernel.sh is: it has to be usable when the thing you must not
# re-run is the installer.
#
# WHY THERE IS NO DOWN-MIGRATION, AND WHY THERE NEVER WILL BE
# -----------------------------------------------------------
# The obvious-looking alternative -- walk commons/schema.php backwards from
# the version the database reached -- cannot work and must not be proposed.
#
#   * Steps are lossy by design. Step 331 onward moves the site plugin's data
#     into core's `sites` and then DROPS `site`, `siteHostAssoc`,
#     `siteUserAssoc`, `siteGroupAssoc` and `siteUserGroupAssoc`. Nothing in
#     the schema records what was in them, so "undo" has nothing to read.
#   * A step is a position, not an identity. `schemaVersion`.`vValue` is a
#     COUNT of applied elements of $this->schema, and working-1.6 and
#     dev-branch fill the same positions with different migrations from index
#     264 on (see SchemaReconciler's docstring). "Undo step 270" therefore
#     names two different migrations depending on which branch you ask.
#   * The dump covers everything a down-migration would, exactly, at a
#     fraction of the cost -- including the rows, which no schema walk
#     restores.
#
# So the pre-upgrade dump is the ONLY supported way back, and this script's
# job is to refuse loudly rather than half-do anything when that dump is not
# there. See docs/development/reverting-an-upgrade.md.
#
# ORDER, AND WHY IT IS THAT ORDER
# -------------------------------
# Every precondition is checked BEFORE the first destructive act. It must be
# impossible for this script to stop the FOG daemons, or drop a table, and
# only then find out there is nothing to restore -- backupDB() has silently
# skipped in the field (GH-314, GH-1146), so "no dump" is a real state and not
# a hypothetical one.
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "revertfog.sh must be run as root user"
    exit 1
fi

usage() {
    echo -e "Usage: $0 [-h?y] [--list] [--dump <path>] [--kernel-generation <N>] [--dry-run]"
    echo -e "\t-h -? --help\t\tDisplay this info"
    echo -e "\t      --list\t\tShow every stored database dump, newest first,"
    echo -e "\t            \t\twith the reason any of them is not usable, and exit"
    echo -e "\t      --dump\t\tRestore this dump instead of the newest usable one."
    echo -e "\t            \t\tIt is checked exactly the same way -- there is no"
    echo -e "\t            \t\toverride for a dump that is not 1.5-era"
    echo -e "\t      --kernel-generation\tWhich kernel-backups generation to put back."
    echo -e "\t                         \tDefaults to 1, the snapshot taken at the start"
    echo -e "\t                         \tof the LAST install -- so after two failed"
    echo -e "\t                         \tinstall runs the pre-upgrade set is 2"
    echo -e "\t      --dry-run\t\tRun every check and print every action, change nothing"
    echo -e "\t-y    --yes\t\tSkip the confirmation prompt"
    echo -e "\n\tWhy a down-migration of schema steps is not offered, and what an"
    echo -e "\tadmin has to have done BEFORE upgrading for this to work at all:"
    echo -e "\tdocs/development/reverting-an-upgrade.md"
    exit 0
}

shortopts="h?y"
longopts="help,list,dump:,kernel-generation:,dry-run,yes"
optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

doList=0
dumpArg=""
kernelGeneration=1
dryRun=0
autoYes=""
while :; do
    case $1 in
        -h | -\? | --help)
            usage
            ;;
        --list)
            doList=1
            shift
            ;;
        --dump)
            dumpArg="$2"
            shift 2
            ;;
        --kernel-generation)
            kernelGeneration="$2"
            shift 2
            ;;
        --dry-run)
            dryRun=1
            shift
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
error_log="${workingdir}/error_logs/fog_revert_error.log"
: > "$error_log"

# exitFail so a failing errorStat inside the sourced functions returns control
# here instead of killing the script mid-revert. Same reason restorekernel.sh
# and updatefog.sh set it: this script has to be able to report what it did
# and did not do, which a bare `exit` from a library function destroys.
exitFail=1
. ../lib/common/functions.sh

# Same resolution order as restorekernel.sh and updatefog.sh: find the install
# via the /etc/fog/fog.conf pointer (GH-850), read .fogsettings for what the
# previous install actually recorded, then config.sh for what it derives but
# does not record ($webdirdest is the one that matters here).
[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"

if [[ ! -r "$fogprogramdir/.fogsettings" ]]; then
    echo " * No existing FOG install found at $fogprogramdir (.fogsettings missing)."
    echo " * revertfog.sh reverts an EXISTING install -- there is nothing here to revert."
    exit 1
fi
. "$fogprogramdir/.fogsettings"
# Before the first read of a renamed key, for the reason spelled out in
# restorekernel.sh: without it a pre-GH-1120 .fogsettings leaves FOG_os_id
# empty, doOSSpecificIncludes is skipped, and $webdirdest below is empty --
# which here would mean restoring the web tree into a relative path under bin/.
migrateDeprecatedKeys
linuxReleaseName_lower="${FOG_os_name,,}"
. ../lib/common/config.sh
[[ -n ${FOG_os_id} ]] && doOSSpecificIncludes >/dev/null

# The version of the code in THIS checkout. Used only for the names of the
# pre-revert copies below -- deliberately NOT used to pick a dump. See the
# comment on _dumpNotFifteenReason().
if [[ -z $version ]]; then
    for versionfile in ../packages/web/commons/version.php ../packages/web/src/Base/System.php; do
        [[ -f $versionfile ]] || continue
        version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' "$versionfile" | tr -d '[[:space:]]')"
        [[ -n $version ]] && break
    done
fi
[[ -z $version ]] && version="unknown"

dbbackupdir="${DB_backup_path%/}/fogDBbackups"
timestamp=$(date +"%Y%m%d_%H%M%S")

# ---------------------------------------------------------------------------
# Is this dump a 1.5-era dump?
# ---------------------------------------------------------------------------
# Echoes the reason it is NOT, or nothing at all when it is.
#
# The test is STRUCTURAL, and deliberately neither of the two things that look
# like they should work:
#
#   The filename lies. backupDB() names the file fog_sql_${version}_<ts>.sql
#   where $version is read out of the CHECKOUT being installed
#   (bin/installfog.sh, the awk over commons/version.php) -- i.e. the NEW
#   version. The pre-upgrade dump taken on the way to 1.6 is therefore stamped
#   with a 1.6 version while containing a 1.5 database. The filename orders
#   candidates and nothing else.
#
#   The schema number does not separate them. `schemaVersion`.`vValue` is a
#   count of applied elements of $this->schema, and the two branches OVERLAP:
#   a fully patched 1.5.10 arrives carrying 287 (count($this->schema) on
#   dev-branch, which is what FOG_SCHEMA there equals), while a 1.6 upgrade
#   that died early can be sitting well below that. There is no "1.6 range" to
#   test against. The number is reported because it belongs in a bug report,
#   not because it decides anything.
#
# What DOES separate them is structure that only 1.6 ever creates. The two
# branches share every schema position up to 263 and diverge from 264 on, so
# the earliest thing a 1.6 upgrade can leave behind is step 264's
# `groups`.`groupInit` -- a 1.6 database that died before that is structurally
# a 1.5 database, and restoring it is exactly right. Anything further in
# leaves one of the markers below. Verified this session: none of these names
# appears anywhere in dev-branch's commons/schema.php, nor in any plugin it
# bundles. (Note `sites` is core's table and `site` is the 1.5 plugin's; the
# backticks in the patterns are what keeps those apart.)
# `roles` was here and had to come OUT. FOG 1.5's accesscontrol plugin owns a
# `roles` table -- lib/plugins/accesscontrol/class/accesscontrol.class.php
# declares it -- and 1.6 core adopted that table wholesale, same five
# columns, so neither the name nor the shape can tell the two apart. Any 1.5
# server running accesscontrol would have had its own valid pre-upgrade dump
# refused as "a 1.6 dump", at the one moment it is needed.
#
# Nothing is lost by dropping it: 1.6 creates `userAuths` below schema 278
# and `roles` above it, so userAuths always fires first. Verified against a
# real 1.5.10 database (2079 hosts) and a step-by-step replay, by
# bin/revert-rehearsal-lab.sh. roleUserGroupAssoc replaces it: 1.6 core only,
# no 1.5 plugin declares it.
#
# The wider rule for this list: a candidate must be absent from 1.5 CORE and
# from every 1.5 PLUGIN. The plugins are the half that is easy to miss --
# they carry tables schema.php never mentions, and 1.6 has been pulling that
# functionality into core, which is exactly what makes the names collide.
_16_TABLES="userAuths sites userPrefs savedFilters savedFilterUserAssoc roleUserGroupAssoc"
_16_COLUMNS="groupInit pIcon pDescription msShutdown"
_dumpNotFifteenReason() {
    local f="$1" t c
    [[ -s $f ]] || { echo "the file is empty"; return 0; }
    # A dump written by mysqldump-php (which is what maintenance/backup_db.php
    # runs, on 1.5 and 1.6 alike) always carries these. Their absence means
    # this is not a FOG database dump at all -- or, for the tail, that it is a
    # truncated one, which is the shape a half-written or half-copied file
    # takes and which would otherwise restore silently and partially.
    grep -q 'CREATE TABLE `schemaVersion`' "$f" \
        || { echo "no schemaVersion table -- this is not a FOG database dump"; return 0; }
    # The footer, and NOT by one dumper's spelling of it. mysqldump-php closes
    # with SQL_NOTES (Mysqldump.php, dumpSettings restore block); mariadb-dump
    # and mysql's own client close with NOTE_VERBOSITY and a "Dump completed"
    # comment instead. Both restore COLLATION_CONNECTION immediately before
    # whichever they use, so that is the line to anchor on.
    #
    # This matters because it is the dump an admin took BY HAND that has the
    # other footer -- and taking one by hand before upgrading is exactly what
    # the release notes tell them to do. A test that only knew FOG's own
    # dumper would refuse the very backup the documentation asked for, at the
    # one moment it is needed. Found by bin/revert-rehearsal-lab.sh.
    grep -qE 'SET (COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION|SQL_NOTES=@OLD_SQL_NOTES)' "$f" \
        || { echo "truncated -- the dump has no closing statement block"; return 0; }
    for t in $_16_TABLES; do
        grep -qE "^CREATE TABLE \`${t}\`" "$f" \
            && { echo "it contains the 1.6-only table \`${t}\` -- this is a 1.6 dump"; return 0; }
    done
    # Column definition lines inside SHOW CREATE TABLE output are indented and
    # start with the backticked column name, so this cannot be tripped by a
    # setting VALUE that happens to contain the word: INSERT lines begin in
    # column zero.
    for c in $_16_COLUMNS; do
        grep -qE "^[[:space:]]+\`${c}\`" "$f" \
            && { echo "it contains the 1.6-only column \`${c}\` -- this is a 1.6 dump"; return 0; }
    done
    # 1.5 declares no foreign keys at all; they arrived with ADR 0031 on 1.6.
    grep -qE "^[[:space:]]+CONSTRAINT \`.*\` FOREIGN KEY" "$f" \
        && { echo "it declares foreign keys -- 1.5 has none, this is a 1.6 dump"; return 0; }
    return 0
}

# The count of applied schema elements the dump carries, or nothing when it
# cannot be read. Informational -- see above.
_dumpSchemaCount() {
    sed -n 's/^INSERT INTO `schemaVersion` VALUES (\([^)]*\)).*/\1/p' "$1" \
        | head -n 1 | awk -F, '{print $2}' | tr -dc '0-9'
}

# Every stored dump, newest first. find/sort rather than `ls -t` so a
# DB_backup_path containing a space still works.
_candidateDumps() {
    [[ -d $dbbackupdir ]] || return 0
    find "$dbbackupdir" -maxdepth 1 -type f -name 'fog_sql_*.sql' -printf '%T@\t%p\n' 2>/dev/null \
        | sort -rn | cut -f2-
}

# ---------------------------------------------------------------------------
# Is this web-tree backup a 1.5 tree?
# ---------------------------------------------------------------------------
# Same problem as the dump and the same answer. configureHttpd() REMOVES an
# existing fog_web_<ver>.BACKUP before writing a new one, so a second
# installfog.sh run after a failed upgrade overwrites the 1.5 tree with the
# 1.6 one it just laid down -- and the directory name, which carries the
# version being installed, looks identical either way.
#
# 1.5 keeps its classes under lib/fog/ and has no src/; 1.6 is PSR-4 under
# src/ (ADR 0013) and has no lib/fog/. One file each way settles it.
_isFifteenWebTree() {
    [[ -f "$1/lib/fog/system.class.php" && ! -e "$1/src/Base/System.php" ]]
}
_candidateWebBackups() {
    [[ -d ${DB_backup_path%/} ]] || return 0
    find "${DB_backup_path%/}" -maxdepth 1 -type d -name 'fog_web_*.BACKUP' -printf '%T@\t%p\n' 2>/dev/null \
        | sort -rn | cut -f2-
}

# ---------------------------------------------------------------------------
# --list
# ---------------------------------------------------------------------------
if [[ $doList -eq 1 ]]; then
    echo " * Database dumps under $dbbackupdir, newest first:"
    listed=0
    while read -r d; do
        [[ -n $d ]] || continue
        listed=1
        why=$(_dumpNotFifteenReason "$d")
        count=$(_dumpSchemaCount "$d")
        if [[ -z $why ]]; then
            echo "   USABLE   $(basename "$d")  (schema count ${count:-unknown})"
        else
            echo "   rejected $(basename "$d")  -- $why"
        fi
    done < <(_candidateDumps)
    [[ $listed -eq 0 ]] && echo "   (none)"
    echo
    echo " * Web tree backups under ${DB_backup_path%/}:"
    listed=0
    while read -r w; do
        [[ -n $w ]] || continue
        listed=1
        if _isFifteenWebTree "$w"; then
            echo "   USABLE   $(basename "$w")"
        else
            echo "   rejected $(basename "$w")  -- not a 1.5 tree (no lib/fog/system.class.php, or it carries src/)"
        fi
    done < <(_candidateWebBackups)
    [[ $listed -eq 0 ]] && echo "   (none)"
    exit 0
fi

# ---------------------------------------------------------------------------
# PRECONDITIONS. Nothing below this block is allowed to change anything, and
# nothing above the confirmation prompt is allowed to either.
# ---------------------------------------------------------------------------
fail() {
    echo
    echo " * REFUSING TO REVERT: $1"
    shift
    while [[ $# -gt 0 ]]; do
        echo "   $1"
        shift
    done
    echo
    echo " * Nothing was stopped, dropped, moved or restored."
    exit 1
}

# An external database is somebody else's to manage, and backupDB() skips the
# dump entirely in that mode -- so there is nothing here to restore from and
# dropping the tables would be destroying data this server does not own.
[[ ${DB_external} == yes ]] && fail \
    "this install is in external database mode." \
    "backupDB() takes no dump in that mode, so there is no pre-upgrade copy" \
    "to put back. Restore the database from your own backup, then re-run" \
    "installfog.sh from your 1.5 checkout."

command -v mysql >/dev/null 2>&1 || fail \
    "the mysql client is not on PATH." \
    "Nothing can be restored without it."

# Pick the dump. --dump names one explicitly; otherwise the newest that passes.
# There is no --force: a dump that is not 1.5-era restored onto 1.5 code is a
# server that boots to a schema page it can never satisfy.
dumpfile=""
if [[ -n $dumpArg ]]; then
    [[ -f $dumpArg ]] || fail "--dump names a file that does not exist: $dumpArg"
    why=$(_dumpNotFifteenReason "$dumpArg")
    [[ -n $why ]] && fail "the dump you named is not a 1.5-era dump -- $why" \
        "$(basename "$dumpArg")" \
        "Run --list to see what else is stored."
    dumpfile="$dumpArg"
else
    rejects=""
    while read -r d; do
        [[ -n $d ]] || continue
        why=$(_dumpNotFifteenReason "$d")
        if [[ -z $why ]]; then
            dumpfile="$d"
            break
        fi
        rejects="${rejects}     $(basename "$d") -- ${why}"$'\n'
    done < <(_candidateDumps)
    if [[ -z $dumpfile ]]; then
        if [[ -z $rejects ]]; then
            fail "there is no pre-upgrade database dump on this server." \
                 "Looked in: $dbbackupdir" \
                 "" \
                 "backupDB() writes fog_sql_<version>_<timestamp>.sql there at the" \
                 "start of every install that finds tables to dump. If none is" \
                 "present the upgrade ran without one -- there is nothing to go" \
                 "back to and this script will not pretend otherwise." \
                 "" \
                 "Your database is UNTOUCHED. Restore from your own backup if you" \
                 "have one; otherwise stay on 1.6 and report what went wrong."
        else
            fail "no stored dump is a 1.5-era dump." \
                 "Looked in: $dbbackupdir" \
                 "" \
                 "$(printf '%s' "$rejects")" \
                 "Every candidate is a 1.6 dump or unreadable. Restoring one onto" \
                 "1.5 code would leave a database 1.5 cannot migrate."
        fi
    fi
fi
dumpcount=$(_dumpSchemaCount "$dumpfile")

# Only now the database, and deliberately in this order: with no usable dump
# the script has refused above without having touched mysql at all, which is
# the property that makes "REFUSE LOUDLY AND DO NOTHING" checkable rather than
# merely intended.
#
# Prove the recorded credentials still reach the database BEFORE anything is
# stopped. Deliberately not checkDatabaseConnection(): errorStat() prints "OK"
# after "Failed!" when $exitFail is set, so its return value cannot be used to
# branch on. detectMysqlSslOption() is reused as-is -- it is the GH-685
# fallback for a client that demands TLS from a server offering none.
[[ -n ${DB_host} ]] && host="--host=${DB_host}"
sqloptionsuser="${host} -s --user=${DB_user}"
if ! mysql $sqloptionsuser --password="${DB_password}" --execute="quit" >/dev/null 2>&1; then
    if detectMysqlSslOption $sqloptionsuser --password="${DB_password}"; then
        sqloptionsuser="${sqloptionsuser} ${mysqlsslopt}"
    else
        fail "cannot connect to the database as '${DB_user}' with the credentials in ${fogprogramdir}/.fogsettings." \
             "Fix that first -- a revert that cannot reach the database can only" \
             "get halfway."
    fi
fi

# From here on the remaining pieces are BEST EFFORT and are named in the plan
# before anything happens. The database dump is the one hard precondition,
# because it is the only piece that cannot be recreated: the web tree comes
# back from a 1.5 checkout by re-running its installer, and the kernels come
# back from the FOS release.
webbackup=""
while read -r w; do
    [[ -n $w ]] || continue
    if _isFifteenWebTree "$w"; then
        webbackup="$w"
        break
    fi
done < <(_candidateWebBackups)

kernelgen="${fogprogramdir}/customizations/kernel-backups/gen-${kernelGeneration}"
[[ -d $kernelgen ]] || kernelgen=""

# Collapse any trailing slashes to exactly one. lib/<distro>/config.sh can hand
# back a value ending "//" (its "does the docroot already contain fog" test is a
# substring match, so a docroot whose PATH happens to contain "fog" takes the
# branch that appends only a slash), and a single ${var%/} then still leaves one
# -- which would make the mv below move the document root instead of the FOG
# tree inside it.
while [[ $webdirdest == */ ]]; do webdirdest="${webdirdest%/}"; done
webdirdest="${webdirdest}/"
webaside="${DB_backup_path%/}/fog_web_pre-revert_${timestamp}"
predump="${dbbackupdir}/fog_sql_pre-revert_${version}_${timestamp}.sql"
canpredump=0
command -v mysqldump >/dev/null 2>&1 && canpredump=1

# ---------------------------------------------------------------------------
# THE PLAN. Printed in full, in order, before the admin is asked anything --
# including every step that is going to be SKIPPED and why.
# ---------------------------------------------------------------------------
echo
echo " * FOG revert to 1.5"
echo "   Install:        $fogprogramdir"
echo "   Database:       ${DB_name} on ${DB_host:-localhost} as ${DB_user}"
echo "   Web tree:       ${webdirdest}"
echo "   This checkout:  ${version}"
[[ $dryRun -eq 1 ]] && echo "   DRY RUN -- every check runs, nothing is changed"
echo
echo " * What this will do, in order:"
if [[ $canpredump -eq 1 ]]; then
    echo "   1. dump the CURRENT (1.6) database to"
    echo "        ${predump}"
else
    echo "   1. SKIP the safety dump of the current database -- mysqldump is not on"
    echo "      PATH. If this revert goes wrong the 1.6 database is not recoverable."
fi
echo "   2. stop the FOG daemons:"
echo "        $serviceList"
echo "   3. drop every table in \`${DB_name}\` and restore"
echo "        $(basename "$dumpfile")"
echo "        (schema count ${dumpcount:-unknown}, $(stat -c %s "$dumpfile" 2>/dev/null) bytes, $(date -r "$dumpfile" '+%F %T' 2>/dev/null))"
if [[ -n $webbackup ]]; then
    echo "   4. move ${webdirdest} aside to"
    echo "        ${webaside}"
    echo "      and restore the 1.5 tree from"
    echo "        ${webbackup}"
else
    echo "   4. SKIP the web tree -- no 1.5-era fog_web_*.BACKUP was found under"
    echo "      ${DB_backup_path%/}. configureHttpd() overwrites that directory on"
    echo "      every install, so a second install run replaces the 1.5 copy with"
    echo "      the 1.6 one. Re-run bin/installfog.sh from your 1.5 checkout after"
    echo "      this finishes."
fi
echo "   5. add the pre-1.6 key spellings back to ${fogprogramdir}/.fogsettings"
echo "      (a copy of the current file is kept alongside it)"
if [[ -n $kernelgen ]]; then
    echo "   6. restore FOS kernel generation ${kernelGeneration} via restorekernel.sh"
else
    echo "   6. SKIP the FOS kernels -- no generation ${kernelGeneration} under"
    echo "      ${fogprogramdir}/customizations/kernel-backups/"
fi
echo "   7. start the FOG daemons again"
echo
echo " * It will NOT down-migrate any schema step. Some are lossy -- the site"
echo "   plugin's tables are dropped once their rows are in core -- and the dump"
echo "   already covers everything a down-migration would."
echo

if [[ $dryRun -eq 0 && -z $autoYes ]]; then
    echo -n " * Continue? (y/N) "
    read -r reply
    case $reply in
        [Yy]|[Yy][Ee][Ss]) ;;
        *) echo " * Aborted. Nothing was changed."; exit 0 ;;
    esac
fi

# ---------------------------------------------------------------------------
# ACT. Everything past here is destructive, and every arm records what it did
# into $did so the summary is a list an admin can paste into a bug report
# rather than a claim this script makes about itself.
# ---------------------------------------------------------------------------
did=()
skipped=()
note() { did+=("$1"); }
skip() { skipped+=("$1"); }
# --- 1. safety dump of the database we are about to drop --------------------
if [[ $canpredump -eq 1 ]]; then
    dots "Dumping the current database first"
    if [[ $dryRun -eq 1 ]]; then
        echo "Skipped (dry run)"
        note "would dump the current database to ${predump}"
    else
        mkdir -p "$dbbackupdir" >>$error_log 2>&1
        # NOT $sqloptionsuser. That string carries -s (silent), which is a
        # mysql CLIENT option -- mysqldump rejects it outright with
        # "unknown option '-s'", the safety dump fails, and the script stops
        # before touching anything. Correct behavior for a failed dump, but
        # it meant the revert could never run at all on any host where
        # mysqldump is mariadb-dump, which is every current MariaDB.
        # Found by bin/revert-rehearsal-lab.sh, not by reading.
        if mysqldump ${host} --user="${DB_user}" ${mysqlsslopt:-} \
            --password="${DB_password}" "${DB_name}" > "$predump" 2>>$error_log; then
            chmod 600 "$predump" >>$error_log 2>&1
            errorStat 0
            note "dumped the current 1.6 database to ${predump}"
        else
            echo "Failed"
            echo " * Could not dump the current database. See $error_log."
            echo " * Stopping here: the database has not been touched, so nothing"
            echo "   is lost. Fix the dump or re-run with mysqldump working."
            exit 1
        fi
    fi
else
    skip "no safety dump of the current database -- mysqldump is not installed"
fi

# --- 2. stop the daemons ----------------------------------------------------
# stopInitScript() rather than a hand-rolled loop: it already handles the
# systemd/OpenRC split ($systemctl, $initdpath) that #863 had to fix once, and
# it reads the same $serviceList the installer does, so a daemon added later is
# stopped here without this script being edited.
if [[ $dryRun -eq 1 ]]; then
    echo " * Would stop: $serviceList"
    note "would stop the FOG daemons"
else
    stopInitScript
    note "stopped the FOG daemons ($serviceList)"
fi

# --- 3. drop and restore the database ---------------------------------------
dots "Dropping the current tables"
if [[ $dryRun -eq 1 ]]; then
    echo "Skipped (dry run)"
    note "would drop every table in \`${DB_name}\` and restore ${dumpfile}"
else
    # Dropped by name rather than DROP DATABASE: the FOG user's grant is
    # `ALL PRIVILEGES ON ${DB_name}.*`, so the database object and its grants
    # survive, and a re-CREATE cannot land on a server where CREATE DATABASE
    # was not granted. FOREIGN_KEY_CHECKS off because 1.6 declares constraints
    # (ADR 0031) and SHOW TABLES is not dependency order.
    tables=$(mysql $sqloptionsuser --password="${DB_password}" -N -B --execute="SHOW TABLES" "${DB_name}" 2>>$error_log)
    if [[ -n $tables ]]; then
        droplist=$(printf '%s\n' "$tables" | sed 's/^/`/; s/$/`/' | paste -sd, -)
        if ! mysql $sqloptionsuser --password="${DB_password}" \
            --execute="SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS ${droplist}; SET FOREIGN_KEY_CHECKS=1;" \
            "${DB_name}" >>$error_log 2>&1; then
            echo "Failed"
            echo " * Could not drop the existing tables. See $error_log."
            echo " * The database is half-dropped at best. Restore from"
            echo "   ${predump} or from ${dumpfile} by hand before doing anything else."
            exit 1
        fi
    fi
    errorStat 0
    note "dropped $(printf '%s\n' "$tables" | grep -c .) tables from \`${DB_name}\`"

    dots "Restoring $(basename "$dumpfile")"
    if mysql $sqloptionsuser --password="${DB_password}" "${DB_name}" < "$dumpfile" >>$error_log 2>&1; then
        errorStat 0
        note "restored \`${DB_name}\` from ${dumpfile} (schema count ${dumpcount:-unknown})"
    else
        echo "Failed"
        echo " * The restore did not complete. See $error_log."
        echo " * \`${DB_name}\` now holds a PARTIAL restore. The dump is still at"
        echo "   ${dumpfile} and the pre-revert state is at ${predump}."
        exit 1
    fi
fi

# --- 4. the web tree --------------------------------------------------------
if [[ -n $webbackup ]]; then
    dots "Restoring the 1.5 web tree"
    if [[ $dryRun -eq 1 ]]; then
        echo "Skipped (dry run)"
        note "would move ${webdirdest} to ${webaside} and restore ${webbackup}"
    else
        # Moved aside, never deleted. The 1.6 tree is the only copy of anything
        # the admin dropped into it since the upgrade, and this script runs at
        # the point where nobody is thinking clearly.
        webstat=0
        if [[ -d ${webdirdest%/} ]]; then
            mv "${webdirdest%/}" "$webaside" >>$error_log 2>&1 || webstat=1
        fi
        [[ $webstat -eq 0 ]] && { mkdir -p "${webdirdest%/}" >>$error_log 2>&1 || webstat=1; }
        [[ $webstat -eq 0 ]] && { cp -RT "$webbackup" "${webdirdest%/}" >>$error_log 2>&1 || webstat=1; }
        if [[ $webstat -ne 0 ]]; then
            echo "Failed"
            echo " * Could not put the 1.5 web tree back. See $error_log."
            echo " * The database HAS been reverted. The 1.6 tree, if it was moved,"
            echo "   is at ${webaside}; the 1.5 tree is at ${webbackup}."
            echo " * Finish by running bin/installfog.sh from your 1.5 checkout."
            exit 1
        fi
        chown -R ${SVC_user}:${apacheuser} "${webdirdest%/}" >>$error_log 2>&1
        errorStat 0
        note "moved the 1.6 web tree to ${webaside} and restored ${webbackup} into ${webdirdest}"
    fi
else
    skip "the web tree -- no 1.5-era fog_web_*.BACKUP found; re-run bin/installfog.sh from your 1.5 checkout"
fi

# --- 5. .fogsettings --------------------------------------------------------
# There is no backup of .fogsettings anywhere: the installer MERGES the file in
# place (writeUpdateFile), it is not copied aside by backupDB or configureHttpd,
# and GH-1120 renamed all 79 managed keys to CATEGORY_lower_snake_case on the
# way to 1.6. So a reverted server is left with a settings file whose every key
# is spelled the way only 1.6 reads -- and 1.5's installer would then re-prompt
# for the OS, the docroot and the database password, and write defaults over an
# install that was fine.
#
# The fix is additive, which is what makes it safe both ways: .fogsettings is
# SOURCED in full, so appending the old spellings gives 1.5 what it reads while
# leaving the new keys exactly where 1.6's migrateDeprecatedKeys() expects them
# (it seeds an old value onto a new key only when the new key is empty, so the
# new spelling still wins if this server ends up back on 1.6). Nothing is
# rewritten and nothing is removed.
fogsettingsBak="${fogprogramdir}/.fogsettings.pre-revert_${timestamp}"
dots "Restoring the pre-1.6 .fogsettings key spellings"
if [[ $dryRun -eq 1 ]]; then
    echo "Skipped (dry run)"
    note "would copy .fogsettings to ${fogsettingsBak} and append the pre-1.6 key spellings"
else
    setstat=0
    cp -a "${fogprogramdir}/.fogsettings" "$fogsettingsBak" >>$error_log 2>&1 || setstat=1
    if [[ $setstat -eq 0 ]] && ! grep -q '^## FOG 1.5 compatibility keys' "${fogprogramdir}/.fogsettings"; then
        {
            echo ""
            echo "## FOG 1.5 compatibility keys, written by bin/revertfog.sh on $(date +%c)."
            echo "## GH-1120 renamed every managed key; 1.5 reads only these spellings."
            echo "## Harmless on 1.6 -- migrateDeprecatedKeys only reads them when the"
            echo "## CATEGORY_lower_snake_case key above is empty."
            # The inverse of migrateDeprecatedKeys() in lib/common/functions.sh,
            # for the keys 1.5 actually reads. Kept as new=old pairs so the two
            # lists can be diffed against each other by eye.
            for pair in \
                installtype=FOG_install_type osid=FOG_os_id osname=FOG_os_name \
                packages=FOG_packages installlang=FOG_install_lang \
                sendreports=FOG_send_reports fogupdateloaded=FOG_installed \
                copybackold=FOG_copy_back_old fog_update_channel=FOG_update_channel \
                fog_git_path=FOG_git_path \
                interface=NET_interface ipaddress=NET_fog_server_ip \
                submask=NET_subnet_mask hostname=NET_hostname \
                bldhcp=DHCP_enabled dhcpengine=DHCP_engine dhcpd=DHCP_service_name \
                plainrouter=DHCP_router dnsaddress=DHCP_dns_server_ip \
                startrange=DHCP_range_start endrange=DHCP_range_end \
                mysqldbname=DB_name snmysqlhost=DB_host snmysqluser=DB_user \
                snmysqlpass=DB_password snmysqlexternal=DB_external \
                backupPath=DB_backup_path \
                webserver=WEB_server_engine docroot=WEB_docroot webroot=WEB_root \
                php_ver=WEB_php_version httpproto=WEB_url_proto \
                httpsRedirect=WEB_https_redirect \
                storageLocation=STORAGE_image_share_path blexports=STORAGE_rebuild_nfs_exports \
                username=SVC_user password=SVC_password fwconfigure=SVC_firewall_control \
                sslpath=PKI_client_cert_dir; do
                oldkey="${pair%%=*}"
                newkey="${pair#*=}"
                printf "%s='%s'\n" "$oldkey" "${!newkey}"
            done
        } >> "${fogprogramdir}/.fogsettings" 2>>$error_log || setstat=1
    fi
    if [[ $setstat -ne 0 ]]; then
        echo "Failed"
        echo " * Could not update ${fogprogramdir}/.fogsettings. See $error_log."
        echo " * A copy of the original is at ${fogsettingsBak}."
    else
        errorStat 0
        note "copied .fogsettings to ${fogsettingsBak} and appended the pre-1.6 key spellings"
    fi
fi

# --- 6. the FOS kernels -----------------------------------------------------
# restorekernel.sh, not a reimplementation. It is the script that owns this:
# it knows the generation layout, reports the FOS release each file came from
# out of the xattr downloadfiles() stamps, chowns the result, and re-signs for
# Secure Boot when the signing key has rotated. Duplicating any of that here
# would give two answers to one question.
if [[ -n $kernelgen ]]; then
    if [[ $dryRun -eq 1 ]]; then
        echo " * Would run: ./restorekernel.sh --generation ${kernelGeneration} --yes"
        note "would restore FOS kernel generation ${kernelGeneration}"
    else
        echo " * Restoring FOS kernel generation ${kernelGeneration}:"
        if ./restorekernel.sh --generation "${kernelGeneration}" --yes; then
            note "restored FOS kernel generation ${kernelGeneration} via restorekernel.sh"
        else
            skip "the FOS kernels -- restorekernel.sh --generation ${kernelGeneration} failed; run it by hand"
        fi
    fi
else
    skip "the FOS kernels -- no generation ${kernelGeneration} is stored"
fi

# --- 7. start the daemons ---------------------------------------------------
if [[ $dryRun -eq 1 ]]; then
    echo " * Would start: $serviceList"
    note "would start the FOG daemons"
else
    startInitScript
    note "started the FOG daemons ($serviceList)"
fi

# ---------------------------------------------------------------------------
# SUMMARY
# ---------------------------------------------------------------------------
echo
if [[ $dryRun -eq 1 ]]; then
    echo " * DRY RUN complete. Nothing was changed. Planned:"
else
    echo " * Revert complete. What was done:"
fi
for line in "${did[@]}"; do
    echo "     - $line"
done
if [[ ${#skipped[@]} -gt 0 ]]; then
    echo
    echo " * What was NOT done:"
    for line in "${skipped[@]}"; do
        echo "     - $line"
    done
fi
echo
echo " * Next: run bin/installfog.sh from your 1.5 checkout. This script puts the"
echo "   data back; it does not change which code is deployed, and the 1.5"
echo "   installer is what rebuilds the vhost, the TFTP tree and the services"
echo "   against it."
echo " * Full log: $error_log"
