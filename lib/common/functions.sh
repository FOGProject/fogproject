#!/bin/bash
#
#  FOG - Free, Open-Source Ghost is a computer imaging solution.
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
dots() {
    local pad=$(printf "%0.1s" "."{1..60})
    printf " * %s%*.*s" "$1" 0 $((60-${#1})) "$pad"
    return 0
}
# Create a symlink only when nothing already owns the destination.
#
# Bare `ln -s` logged "failed to create symbolic link ...: File exists" on every
# re-install, because the link survives from the previous run. Harmless, but it
# made a successful upgrade read as a failed one and sent at least one reporter
# chasing the installer instead of the real fault (forums topic 18204).
#
# `ln -sf` is deliberately not used: some distros own these paths themselves
# (Fedora ships /usr/lib/systemd/system/mysql.service), and clobbering a
# packaged file is worse than skipping a link we did not need to make.
linkIfAbsent() {
    local target="$1" link="$2"
    # A dangling link here is one we created ourselves on an older version, back
    # when the systemd unit sources below were missing their .service suffix. It
    # is useless to systemd -- and worse, one in /etc/systemd/system shadows the
    # working unit in /usr/lib -- so replace it. A real file, or a link that
    # resolves, is left strictly alone.
    if [[ -L $link && ! -e $link ]]; then
        rm -f "$link" >>$error_log 2>&1
    fi
    [[ -e $link || -L $link ]] && return 0
    ln -s "$target" "$link" >>$error_log 2>&1
}
# Maps a FOG update channel name to the git branch it tracks. Channel names
# match README.md's "Channel" table (Stable/Staging/Dev), not the informal
# "dev"/"beta" prose fog-docs used before that table existed -- see
# FOGProject/fogproject#1012. Codified here so bin/updatefog.sh and
# lib/common/config.sh share one mapping instead of each guessing at it.
channelToBranch() {
    case "$1" in
        stable) echo "stable" ;;
        staging) echo "dev-branch" ;;
        dev) echo "working-1.6" ;;
        *) return 1 ;;
    esac
}
# The inverse of channelToBranch(), used to derive a sensible fog_update_channel
# default from whatever branch happens to be checked out. Echoes nothing for a
# branch that is not one of the three channels -- a feature/PR branch has no
# channel, and guessing one would be worse than leaving it for the admin to set.
branchToChannel() {
    case "$1" in
        stable) echo "stable" ;;
        dev-branch) echo "staging" ;;
        working-1.6) echo "dev" ;;
        *) return 1 ;;
    esac
}
backupReports() {
    dots "Backing up user reports"
    [[ ! -d ../rpttmp/ ]] && mkdir ../rpttmp/ >>$error_log
    [[ -d $webdirdest/management/reports/ ]] && cp -a $webdirdest/management/reports/* ../rpttmp/ >>$error_log
    echo "Done"
    return 0
}
# Where backupPreservedCustomizations() stashes anything that has to outlive
# configureHttpd()'s rm -rf $webdirdest. Deliberately under $fogprogramdir,
# never inside $webdirdest -- that is the same "survives the wipe by
# construction" property $fogprogramdir/secureboot already relies on, rather
# than a copy that has to be re-made correctly every time.
#
# Resolved on CALL, not when this file is sourced. installfog.sh sources
# functions.sh at line ~93 but does not settle $fogprogramdir until config.sh
# runs several hundred lines later, so a top-level assignment here evaluated to
# "/customizations" and wrote the backups to the filesystem root. That is what
# the first real-server run actually did -- the sandbox never caught it because
# it always set $fogprogramdir before sourcing.
_resolveCustomizationsDir() {
    [[ -n $customizationsDir ]] && return 0
    local base="${fogprogramdir:-/opt/fog}"
    customizationsDir="${base%/}/customizations"
}
# Backs up whatever is actually customized under $webdirdest/service/ipxe/
# BEFORE configureHttpd() destroys that tree.
#
# This used to live only in bin/updatefog.sh (backupCustomizations, since
# removed), which meant a bare `./installfog.sh` upgrade -- the way most
# people upgrade -- silently got none of it. Running it from installfog.sh's
# own sequence is the point: the protection now applies to every run.
#
# $bgfile is intentionally NOT local: restorePreservedCustomizations() runs
# later in the same shell and needs the name that was actually backed up, not
# a re-read of the setting (which an admin could have changed mid-install).
backupPreservedCustomizations() {
    dots "Backing up customizations"
    _resolveCustomizationsDir
    local ipxedir="${webdirdest}service/ipxe"
    local f st=0
    # Severity is split deliberately, because errorStat() EXITS the installer
    # when $exitFail is unset -- which is every normal installfog.sh run:
    #
    #   $st   -> fatal. Only set when a customization we positively identified
    #            could not be copied to safety. Aborting here is the safe
    #            outcome: configureHttpd() has not wiped anything yet, so the
    #            admin's file is still sitting untouched where it always was.
    #   warn  -> non-fatal. An optional file we merely tried for. Killing an
    #            install over an unreadable legacy refind blob would be absurd.
    # A failed mkdir is not itself fatal -- if there is nothing to preserve,
    # nothing is lost. If there IS a background to preserve, the copy below
    # fails too and that is what stops the run.
    mkdir -p "$customizationsDir/ipxe-bg" "$customizationsDir/ipxe-legacy" >>$error_log 2>&1 || true

    # FOG_IPXE_BG_FILE is a real, GUI-editable globalSettings row (see
    # packages/web/commons/schema.php) whose whole purpose is letting an admin
    # rename the background file. Read the ACTUAL value rather than assuming
    # "bg.png", which is what the old hardcoded list got wrong.
    #
    # On a first-ever install globalSettings does not exist yet -- updateDB()
    # runs after configureHttpd() -- so this errors into $error_log and leaves
    # $bgfile empty, which is treated exactly like "nothing customized". No
    # special-casing needed for a fresh install.
    bgfile=$(mysql $sqloptionsuser --password="${snmysqlpass}" -N -B \
        --execute="SELECT settingValue FROM globalSettings WHERE settingKey='FOG_IPXE_BG_FILE'" \
        $mysqldbname 2>>$error_log)
    # Strip surrounding whitespace, and treat mysql's literal NULL output as
    # empty -- an unset settingValue comes back as the four characters "NULL"
    # under -N, which would otherwise be looked for as a filename.
    bgfile="${bgfile#"${bgfile%%[![:space:]]*}"}"
    bgfile="${bgfile%"${bgfile##*[![:space:]]}"}"
    [[ $bgfile == NULL ]] && bgfile=""
    # basename guards against a settingValue containing a path: this string
    # reaches a cp destination, and "../../something" would write outside the
    # backup directory entirely.
    [[ -n $bgfile ]] && bgfile=$(basename "$bgfile")
    if [[ -n $bgfile && -f "${ipxedir}/${bgfile}" ]]; then
        cp -f "${ipxedir}/${bgfile}" "${customizationsDir}/ipxe-bg/${bgfile}" >>$error_log 2>&1 || st=1
    fi

    local warn=0
    for f in refind.conf refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi; do
        [[ -f "${ipxedir}/${f}" ]] && { cp -f "${ipxedir}/${f}" "${customizationsDir}/ipxe-legacy/${f}" >>$error_log 2>&1 || warn=1; }
    done
    if [[ $st -ne 0 ]]; then
        echo "Failed"
        echo " * Could not copy the customized iPXE background (${bgfile}) to"
        echo "   ${customizationsDir}/ipxe-bg/."
        echo " * Stopping BEFORE the web tree is rebuilt, so your file is still"
        echo "   intact at ${ipxedir}/${bgfile}. Fix the permissions or free"
        echo "   space under ${customizationsDir} and re-run. See $error_log."
        exit 1
    fi

    # Snapshot the whole directory into a rotated generation, rather than a
    # fixed list of filenames. Two things fall out of that: a per-host custom
    # kernel/init (bootmenu.class.php's Host->get('kernel')/get('init')) is
    # captured without FOG ever having to learn its name, and a generation is
    # a complete, coherent set rather than an assortment.
    #
    # Bounded at $kernelBackupGenerations because this is otherwise unlimited
    # growth on disk the admin provisioned for images, not for history.
    [[ -z $kernelBackupGenerations || ! $kernelBackupGenerations =~ ^[0-9]+$ || $kernelBackupGenerations -lt 1 ]] && kernelBackupGenerations=3
    local kbdir="${customizationsDir}/kernel-backups" k
    if [[ -d $ipxedir ]]; then
        mkdir -p "$kbdir" >>$error_log 2>&1 || warn=1
        rm -rf "${kbdir}/gen-${kernelBackupGenerations}" >>$error_log 2>&1
        for ((k = kernelBackupGenerations - 1; k >= 1; k--)); do
            [[ -d "${kbdir}/gen-${k}" ]] && mv "${kbdir}/gen-${k}" "${kbdir}/gen-$((k + 1))" >>$error_log 2>&1
        done
        mkdir -p "${kbdir}/gen-1" >>$error_log 2>&1 || warn=1
        # cp -a preserves the version/tag_name xattrs downloadfiles() stamps on
        # each kernel, so every generation says which FOS release it came from
        # without a separate manifest to keep in sync.
        cp -a "${ipxedir}/." "${kbdir}/gen-1/" >>$error_log 2>&1 || warn=1
    fi

    [[ $warn -ne 0 ]] && echo -n "(some optional files could not be backed up) "
    errorStat 0
}
# Restores what backupPreservedCustomizations() saved, AFTER
# configureTFTPandPXE()'s downloadfiles() has re-laid the default-named
# kernel/init set.
#
# Deliberately does NOT restore the six default kernel/init names here -- the
# point of an update is to pick up the latest kernel. That is what the
# versioned backup provides an explicit, admin-invoked restore path for
# instead.
restorePreservedCustomizations() {
    dots "Restoring customizations"
    _resolveCustomizationsDir
    local ipxedir="${webdirdest}service/ipxe"
    local f st=0

    if [[ -n $bgfile && -f "${customizationsDir}/ipxe-bg/${bgfile}" ]]; then
        cp -f "${customizationsDir}/ipxe-bg/${bgfile}" "${ipxedir}/${bgfile}" >>$error_log 2>&1 || st=1
    fi
    for f in refind.conf refind.efi refind_x64.efi refind_ia32.efi refind_aa64.efi; do
        [[ -f "${customizationsDir}/ipxe-legacy/${f}" ]] && { cp -f "${customizationsDir}/ipxe-legacy/${f}" "${ipxedir}/${f}" >>$error_log 2>&1 || st=1; }
    done

    # Anything in the newest generation that is NOT one of the six names
    # downloadfiles() re-downloads is, by definition, a file FOG did not put
    # there and will never put back -- a per-host custom kernel or init. Those
    # are restored unconditionally.
    #
    # The six default names are deliberately NOT restored: the point of an
    # update is to pick up the new kernel. bin/restorekernel.sh is the
    # explicit, admin-invoked way back to an older one.
    #
    # $restoreKernelBackup is the single exception, set only by
    # --restore-kernel-backup, which revertUpdate() passes when it re-runs the
    # installer against the previous commit. An older commit wants the older
    # kernels too, and that is the behavior the retired _restorePreviousKernel()
    # used to provide on that path.
    local kbdir="${customizationsDir}/kernel-backups"
    local defaultnames=" bzImage bzImage32 arm_Image init.xz init_32.xz arm_init.cpio.gz "
    local bn
    if [[ -d "${kbdir}/gen-1" ]]; then
        for f in "${kbdir}/gen-1"/*; do
            [[ -f $f ]] || continue
            bn=$(basename "$f")
            if [[ $defaultnames != *" $bn "* || ${restoreKernelBackup:-0} -eq 1 ]]; then
                cp -a "$f" "${ipxedir}/${bn}" >>$error_log 2>&1 || st=1
            fi
        done
    fi
    [[ -d $ipxedir ]] && chown -R ${username}:${apacheuser} "$ipxedir" >>$error_log 2>&1
    # Never fatal, unlike the backup side. By this point configureHttpd() has
    # already rebuilt the web tree, so aborting would strand a nearly-complete
    # install and fix nothing -- and unlike the backup case, the files are NOT
    # lost: they are still sitting in $customizationsDir for the admin to put
    # back by hand. Say exactly that instead of dying.
    if [[ $st -ne 0 ]]; then
        echo "Failed"
        echo " * One or more customizations could not be restored to ${ipxedir}."
        echo " * Nothing was lost -- your files are still in ${customizationsDir}."
        echo "   Copy them back by hand once the install finishes. See $error_log."
        return 0
    fi
    errorStat 0
}
# GH-685: the MariaDB client library turns TLS on by default from 10.10.1
# onward and then refuses to connect at all when the server offers none --
# "ERROR 2026 (HY000): TLS/SSL error: SSL is required, but the server does not
# support it". Debian 12 and Ubuntu 24.04 both ship such a client, so a storage
# node installed on either could not reach an older master and the install died
# at "Checking connection to master database" with an empty error log.
#
# FOG's own web tier reaches the same database over PDO without TLS, so a
# plaintext-only master is not a reason to refuse the install. The fallback is
# only ever tried after the default attempt -- encrypted wherever the server
# offers it -- has already failed, so an install that was getting TLS keeps it.
#
# The flag differs by client: MySQL 8.4 dropped --skip-ssl and takes only
# --ssl-mode, while MariaDB before 11.4 has no --ssl-mode. Try both and keep
# whichever the installed client accepts. Empty means none was ever needed.
mysqlsslopt=""
detectMysqlSslOption() {
    local opt
    [[ -n $mysqlsslopt ]] && return 0
    for opt in "--ssl-mode=DISABLED" "--skip-ssl"; do
        if mysql "$@" $opt --execute="quit" >/dev/null 2>&1; then
            mysqlsslopt="$opt"
            return 0
        fi
    done
    return 1
}
checkDatabaseConnection() {
    dots "Checking connection to master database"
    [[ -n $snmysqlhost ]] && host="--host=$snmysqlhost"
    sqloptionsuser="${host} -s --user=${snmysqluser}"
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="quit" >/dev/null 2>&1
    local connected=$?
    # Only the whole option string is reusable, so widen $host too -- the
    # fogstorage checks later on build their own command line from it.
    if [[ $connected -ne 0 ]] && detectMysqlSslOption $sqloptionsuser --password="${snmysqlpass}"; then
        host="${host} ${mysqlsslopt}"
        sqloptionsuser="${sqloptionsuser} ${mysqlsslopt}"
        connected=0
        errorStat 0
        echo " * Note: the master database offers no TLS, so the installer will"
        echo "   connect unencrypted, the same way the FOG web tier already does."
        return 0
    fi
    errorStat $connected
}
registerStorageNode() {
    # GH-529: this defaulted to "/" while installfog.sh defaults to "/fog/", so
    # the two disagreed about where the app lives whenever webroot arrived
    # unset. Every fallback in this file now matches the installer's.
    [[ -z $webroot ]] && webroot="/fog/"
    dots "Checking if this node is registered"
    storageNodeExists=$(curl -X POST -d "ip=${ipaddress}" -d "fogverified" -kL ${httpproto}://${ipaddress}${webroot}/maintenance/check_node_exists.php -o -)
    echo "Done"
    if [[ $storageNodeExists != exists ]]; then
        [[ -z $maxClients ]] && maxClients=10
        dots "Node being registered"
        curl -s -k -X POST -d "newNode" -d "name=$(echo -n $ipaddress|base64)" -d "path=$(echo -n $storageLocation|base64)" -d "ftppath=$(echo -n $storageLocation|base64)" -d "snapinpath=$(echo -n $snapindir|base64)" -d "sslpath=$(echo -n $sslpath|base64)" -d "ip=$(echo -n $ipaddress|base64)" -d "maxClients=$(echo -n $maxClients|base64)" -d "user=$(echo -n $username|base64)" --data-urlencode "pass=$(echo -n $password|base64)" -d "interface=$(echo -n $interface|base64)" -d "bandwidth=1" -d "webroot=$(echo -n $webroot|base64)" -d "fogverified" ${httpproto}://${ipaddress}${webroot}/maintenance/create_update_node.php
        echo "Done"
    else
        echo " * Node is registered"
    fi
}
updateStorageNodeCredentials() {
    [[ -z $webroot ]] && webroot="/fog/"   # see registerStorageNode, GH-529
    dots "Ensuring node username and passwords match"
    curl -s -k -X POST -d "nodePass" -d "ip=$(echo -n $ipaddress|base64)" -d "user=$(echo -n $username|base64)" --data-urlencode "pass=$(echo -n $password|base64)" -d "fogverified" $httpproto://$ipaddress${webroot}/maintenance/create_update_node.php
    echo "Done"
}
# Mirrors fog_git_path/fog_update_channel/extraServerNames into globalSettings
# so the GUI can show them without SSH. Like fogprogramdir's mirror into
# /etc/fog/fog.conf (GH-850), these are RECORDS, not controls: .fogsettings
# stays the source of truth, and the next installfog.sh/updatefog.sh run
# overwrites whatever an admin may have hand-edited here through the generic
# Settings tab.
recordGitUpdateSettings() {
    dots "Recording fog_git_path/update channel/extra server names"
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_GIT_PATH', 'Filesystem path of the FOG git checkout on this server. Recorded automatically by installfog.sh/updatefog.sh -- editing it here has no effect on the next update.', \"$fog_git_path\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"$fog_git_path\"" $mysqldbname >>$error_log 2>&1
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_UPDATE_CHANNEL', 'Update channel this server tracks: stable, staging, or dev.', \"$fog_update_channel\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"$fog_update_channel\"" $mysqldbname >>$error_log 2>&1
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_EXTRA_SERVER_NAMES', 'Extra vhost/certificate name(s) this server answers to, beyond the primary hostname and detected IPs. Set via --extra-server-name -- editing it here has no effect on the next update.', \"$extraServerNames\", 'FOG Update') ON DUPLICATE KEY UPDATE settingValue=\"$extraServerNames\"" $mysqldbname >>$error_log 2>&1
    errorStat $?
}
backupDB() {
    # ---------------------------------------------------------
    # External Unprivileged Database Implementation
    # Skip database backup for external databases
    # ---------------------------------------------------------
    if [[ "${snmysqlexternal}" == "1" ]]; then
        echo " * Skipping database backup (External Database Mode)"
        return 0
    fi
    # ---------------------------------------------------------
    dots "Backing up database"
    # GH-314: the status checked below used to be whichever command ran last,
    # which made this claim success in two ways it should not have. With no
    # prior install there is no BACKUP directory, so the `if` was skipped and
    # the status read was the failed directory test. And when the curl DID run
    # and failed, the status read was jq's -- jq exits 0 on empty input, having
    # written a zero-byte file. That is the worse half: an upgrade would print
    # "Backing up database....Done" over an empty pre-upgrade dump.
    #
    # dbbackupstat is only set where a backup was actually attempted, so a fresh
    # install skips the step instead of reporting a failure it did not have.
    local dbbackupstat=0
    local dbbackupfile=""
    if [[ -d $backupPath/fog_web_${version}.BACKUP ]]; then
        [[ ! -d $backupPath/fogDBbackups ]] && mkdir -p $backupPath/fogDBbackups >>$error_log 2>&1
        url="${httpproto}://$ipaddress$webroot/maintenance/backup_db.php"
        dbbackupfile="$backupPath/fogDBbackups/fog_sql_${version}_$(date +"%Y%m%d_%I%M%S").sql"
        curl -skf "$url" | jq -r '. | ._content' > "$dbbackupfile"
        # Both halves of the pipeline matter, and so does the result: a dump
        # that is empty or the literal "null" is jq faithfully reporting that
        # the response had no _content.
        [[ ${PIPESTATUS[0]} -ne 0 || ${PIPESTATUS[1]} -ne 0 ]] && dbbackupstat=1
        [[ ! -s $dbbackupfile ]] && dbbackupstat=1
        [[ $(head -c 4 "$dbbackupfile" 2>/dev/null) == null ]] && dbbackupstat=1
    fi
    if [[ -z $dbbackupfile ]]; then
        # No prior install to back up. Saying "Done" over a step that never ran
        # is the same misreport this function was just fixed for.
        echo "Skipped"
    elif [[ $dbbackupstat -ne 0 ]]; then
        echo "Failed"
        if [[ -z $autoaccept ]]; then
            echo
            echo "    We were not able to backup the current database! Just press"
            echo "    [Enter] to proceed anyway or Ctrl+C to stop the installer."
            read
        fi
    else
        echo "Done"
    fi
}
# Prove the web tier actually renders a page before we trust anything that
# talks to it. A PHP fatal in the boot chain returns an empty (or truncated)
# 500, which reads as a blank white page in a browser and which every other
# check in this installer happily treats as success -- that is how an install
# can print "Setup complete" over a completely dead site.
# Refs https://forums.fogproject.org/topic/18204/
checkWebTier() {
    dots "Checking web server serves FOG"
    # No token on this probe. It is a pure liveness check -- 'schema' is an
    # unauthenticated node and a plain GET renders regardless -- and a token in
    # a query string lands in the web server access log and, on failure, in the
    # installer's tee'd stdout. The deploy itself uses the header channel.
    local probeUrl="${httpproto}://${ipaddress}${webroot}management/index.php?node=schema"
    local probeBody=$(mktemp)
    # We care whether bytes came back at all, not just about the status code,
    # because a pre-output fatal loses exactly that.
    curl -sS -k --noproxy '*' -m 30 -fL -o "$probeBody" "$probeUrl" >>$error_log 2>&1
    local probeStat=$?
    local probeSize=$(stat -c %s "$probeBody" 2>/dev/null)
    [[ -z $probeSize ]] && probeSize=0
    rm -f "$probeBody"
    if [[ $probeStat -eq 0 && $probeSize -gt 0 ]]; then
        echo "Done"
        return 0
    fi
    echo "Failed!"
    echo
    echo "   The web server did not return a usable page for:"
    echo "     $probeUrl"
    echo "   (curl exit ${probeStat}, ${probeSize} bytes of body)"
    echo
    echo "   An empty or truncated response with a 500 is almost always a PHP"
    echo "   fatal in the FOG boot chain rather than a database problem. In a"
    echo "   browser this looks like a blank white page. Check your web"
    echo "   server's error log."
    echo "   PHP in use: $(php -v 2>/dev/null | head -1)"
    echo
    [[ -z $exitFail ]] && exit 1
    return 1
}
# Read the schema version straight out of MySQL. Echoes the number, or nothing
# when the probe cannot run (external database mode, credentials we do not
# hold, table not created yet). Callers must treat empty as "unknown" and never
# as zero.
schemaVersionInDB() {
    [[ "${snmysqlexternal}" == "1" ]] && return 0
    [[ -z $sqloptionsuser ]] && return 0
    mysql $sqloptionsuser --password="${snmysqlpass}" -N -B --execute="SELECT vValue FROM \`${mysqldbname}\`.\`schemaVersion\` WHERE vID=1" 2>/dev/null | tail -1
}
# How many FOG users exist, i.e. is this an established install or a fresh one.
# Echoes the count, or NOTHING when the probe cannot run. Empty means unknown
# and must not be read as zero: guessing "fresh" would print a live token for
# an established install, and guessing "established" would leave a genuinely
# fresh install with no way to bootstrap. Callers show both instructions.
fogUserCount() {
    if [[ "${snmysqlexternal}" == "1" ]]; then
        [[ -z $snmysqlhost || -z $snmysqluser ]] && return 0
        mysql --host="${snmysqlhost}" --user="${snmysqluser}" --password="${snmysqlpass}" $mysqlsslopt -N -B --execute="SELECT COUNT(*) FROM \`${mysqldbname}\`.\`users\`" 2>/dev/null | tail -1
        return 0
    fi
    [[ -z $sqloptionsuser ]] && return 0
    mysql $sqloptionsuser --password="${snmysqlpass}" -N -B --execute="SELECT COUNT(*) FROM \`${mysqldbname}\`.\`users\`" 2>/dev/null | tail -1
}
# Confirm the deploy actually landed in the database. Neither update path used
# to prove anything: the automatic branch only checked curl's exit status, and
# the manual branch accepted any keypress -- so a failed schema update still
# marched on to "Setup complete".
verifySchemaDeploy() {
    local expected=$(grep -o "define('FOG_SCHEMA', *[0-9]*" $webdirdest/lib/fog/system.class.php 2>/dev/null | grep -o '[0-9]*$')
    local deployed=$(schemaVersionInDB)
    if [[ -z $expected || -z $deployed ]]; then
        echo " * Skipping schema verification (unable to read the schema version)"
        return 0
    fi
    dots "Verifying database schema"
    if [[ $deployed -ge $expected ]]; then
        echo "Done"
        return 0
    fi
    echo "Failed!"
    echo
    echo "   The database schema is still at version ${deployed}; this release"
    echo "   requires ${expected}. The update did not complete, so FOG will not"
    echo "   work correctly."
    echo
    echo "   Re-run this installer once the cause is resolved."
    echo
    [[ -z $exitFail ]] && exit 1
    return 1
}
updateDB() {
    # This substitution has to happen on BOTH paths. It used to sit inside the
    # [Yy] branch, and dbupdate is set in exactly one place (bin/installfog.sh,
    # under -y), so every interactive install baked the literal '/images/'
    # default into commons/schema.php instead of $storageLocation.
    local replace='s/[]"\/$&*.^|[]/\\&/g'
    local escstorageLocation=$(echo $storageLocation | sed -e $replace)
    sed -i -e "s/'\/images\/'/'$escstorageLocation'/g" $webdirdest/commons/schema.php
    # Same root cause, the other half: with dbupdate unset every interactive
    # install fell through to the manual browser path, which verifies nothing
    # and hands the install token out on stdout. Default to the automatic path
    # and make opting out deliberate. backupDB has already run by this point,
    # so the historical reason to pause here is covered.
    if [[ -z $dbupdate ]]; then
        if [[ -n $autoaccept || ! -t 0 ]]; then
            dbupdate="yes"
        else
            echo
            read -p " * Install/update the FOG database schema now? (Y/n) " dbupdate
            [[ -z $dbupdate ]] && dbupdate="yes"
        fi
    fi
    case $dbupdate in
        [Yy]|[Yy][Ee][Ss])
            dots "Updating Database"
            curl -X POST -H "X-Fog-Install-Token: ${installToken}" -d "schemaupdate=1" --noproxy '*' -fksL ${httpproto}://${ipaddress}${webroot}management/index.php?node=schema -o - >>$error_log 2>&1
            errorStat $?
            ;;
        *)
            echo
            echo " * You still need to install/update your database schema."
            echo " * This can be done by opening a web browser and going to:"
            echo
            # On an established install the URL token is refused (it is gated on
            # there being no users yet) and is not needed -- logging in as an
            # admin is the credential. Only a fresh install gets a secret on
            # screen. Failing toward the tokenized URL when the probe cannot run
            # is safe: it still requires possession of the token.
            local userCount=$(fogUserCount)
            if [[ -z $userCount || $userCount -gt 0 ]]; then
                echo "   ${httpproto}://${ipaddress}${webroot}management/index.php?node=schema"
                echo
                echo " * Log in as a FOG administrator there, then click"
                echo "   Install/Update now."
                echo
                # The login above is not always usable on an upgrade: it reads
                # the schema this deploy is about to create, so a model old
                # enough can fail it outright (GH-927). The token channel now
                # covers that case, but the secret is deliberately NOT echoed
                # here -- this text lands in the install log, and users paste
                # those into forum threads verbatim. Point at the file instead;
                # anyone who can read it is already root.
                echo " * If you cannot log in there, the schema can be deployed"
                echo "   directly using the token in:"
                echo "     ${webdirdest}lib/fog/config.class.php"
                echo "   (the FOG_SCHEMA_INSTALL_TOKEN line), with:"
                echo "     curl -X POST -H \"X-Fog-Install-Token: <token>\" \\"
                echo "       -d \"schemaupdate=1\" \\"
                echo "       \"${httpproto}://${ipaddress}${webroot}management/index.php?node=schema\""
            fi
            if [[ -z $userCount || $userCount -eq 0 ]]; then
                # Only a userless install can use the token, and only in a URL
                # that has to be typed once. Shown alongside the login
                # instruction when the user probe could not run, so we neither
                # publish a secret needlessly nor strand a fresh install.
                [[ -z $userCount ]] && echo " * If this is a brand new install with no FOG users yet, use:"
                echo "   ${httpproto}://${ipaddress}${webroot}management/index.php?node=schema&fogtoken=${installToken}"
            fi
            echo
            read -p " * Press [Enter] key when database is updated/installed."
            echo
            ;;
    esac
    verifySchemaDeploy
    # ---------------------------------------------------------
    # External Unprivileged Database Implementation
    # Bypass DB user management (fogstorage) requiring root GRANT
    # ---------------------------------------------------------
    if [[ "${snmysqlexternal}" == "1" ]]; then
        echo " * Skipping fogstorage DB user management (External Database Mode)"
        # Return cleanly, skipping the GRANT/ALTER commands below
        return 0
    fi
    # ---------------------------------------------------------
    dots "Update fogstorage database password"
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="INSERT INTO globalSettings (settingKey, settingDesc, settingValue, settingCategory) VALUES ('FOG_STORAGENODE_MYSQLPASS', 'This setting defines the password the storage nodes should use to connect to the fog server.', \"$snmysqlstoragepass\", 'FOG Storage Nodes') ON DUPLICATE KEY UPDATE settingValue=\"$snmysqlstoragepass\"" $mysqldbname >>$error_log 2>&1
    errorStat $?
    dots "Granting access to fogstorage database user"
    mysql ${host} -s --user=fogstorage --password="${snmysqlstoragepass}" --execute="INSERT INTO $mysqldbname.taskLog VALUES ( 0, '999test', 3, '127.0.0.1', NOW(), 'fog');" >/dev/null 2>&1
    connect_as_fogstorage=$?
    if [[ $connect_as_fogstorage -eq 0 ]]; then
        mysql $sqloptionsuser --password="${snmysqlpass}" --execute="DELETE FROM $mysqldbname.taskLog WHERE taskID='999test' AND ip='127.0.0.1';" >/dev/null 2>&1
        echo "Skipped"
        return
    fi

    # we still need to grant access for the fogstorage DB user
    # and therefore need root DB access
    mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >>$error_log 2>&1
    if [[ $? -ne 0 ]]; then
        echo
        echo "   To improve the overall security the installer will restrict"
        echo "   permissions for the *fogstorage* database user."
        echo "   Please provide the database *root* user password. Be asured"
        echo "   that this password will only be used while the FOG installer"
        echo -n "   is running and won't be stored anywhere: "
        read -rs snmysqlrootpass
        echo
        echo
        mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            echo "   Unable to connect to the database using the given password!"
            echo -n "   Try again: "
            read -rs snmysqlrootpass
            mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >/dev/null 2>&1
            if [[ $? -ne 0 ]]; then
                echo
                echo "   Failed! Terminating installer now."
                exit 1
            fi
        fi
    fi
    [[ ! -d ../tmp/ ]] && mkdir -p ../tmp/ >/dev/null 2>&1
    cat >../tmp/fog-db-grant-fogstorage-access.sql <<EOF
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ANSI' ;
GRANT SELECT ON $mysqldbname.* TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.hosts TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.inventory TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.multicastSessions TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.multicastSessionsAssoc TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.nfsGroupMembers TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.tasks TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.taskStates TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.taskLog TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.snapinTasks TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.snapinJobs TO 'fogstorage'@'%' ;
GRANT INSERT,UPDATE ON $mysqldbname.imagingLog TO 'fogstorage'@'%' ;
FLUSH PRIVILEGES ;
SET SQL_MODE=@OLD_SQL_MODE ;
EOF
    mysql $sqloptionsroot --password="${snmysqlrootpass}" <../tmp/fog-db-grant-fogstorage-access.sql >>$error_log 2>&1
    errorStat $?
}
validip() {
    local ip=$1
    local stat=1
    if [[ $ip =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
        OIFS=$IFS
        IFS='.'
        ip=($ip)
        IFS=$OIFS
        [[ ${ip[0]} -le 255 && ${ip[1]} -le 255 && ${ip[2]} -le 255 && ${ip[3]} -le 255 ]]
        stat=$?
    fi
    echo $stat
}
# Same calling convention as validip(): echo 0/1, checked via
# [[ $(validhostname "$x") -ne 0 ]]. RFC-1123-ish: dot-separated labels of
# alphanumerics/hyphens, no leading/trailing hyphen per label. Needed because
# --hostname/--extra-server-name are the first NON-interactive entry point for
# this value -- the interactive prompt in lib/common/newinput.sh has never
# validated what an admin types, but a CLI flag's value reaches a vhost config
# and an OpenSSL CSR config file unchecked, so it must be checked before either.
validhostname() {
    local h=$1
    [[ $h =~ ^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$ ]]
    echo $?
}
getCidr() {
    local cidr
    cidr=$(ip -f inet -o addr | grep $1 | awk -F'[ /]+' '/global/ {print $5}' | head -n2 | tail -n1)
    echo $cidr
}
mask2cidr() {
    local submask=$1
    nbits=0
    OIFS=$IFS
    IFS='.'
    for dec in $submask; do
        case $dec in
            255)
                let nbits+=8
                ;;
            254)
                let nbits+=7
                break
                ;;
            252)
                let nbits+=6
                break
                ;;
            248)
                let nbits+=5
                break
                ;;
            240)
                let nbits+=4
                break
                ;;
            224)
                let nbits+=3
                break
                ;;
            192)
                let nbits+=2
                break
                ;;
            128)
                let nbits+=1
                break
                ;;
            0)
                ;;
            *)
                echo "Error: $dec is not recognized"
                exit 1
                ;;
        esac
    done
    IFS=$OIFS
    echo "$nbits"
}
cidr2mask() {
    local i=""
    local mask=""
    local full_octets=$(($1/8))
    local partial_octet=$(($1%8))
    for ((i=0;i<4;i+=1)); do
        if [[ $i -lt $full_octets ]]; then
            mask+=255
        elif [[ $i -eq $full_octets ]]; then
            mask+=$((256 - 2**(8-$partial_octet)))
        else
            mask+=0
        fi
        test $i -lt 3 && mask+=.
    done
    echo $mask
}
mask2network() {
    local OIFS=$IFS
    IFS='.'
    read -r i1 i2 i3 i4 <<< "$1"
    read -r m1 m2 m3 m4 <<< "$2"
    IFS=$OIFS
    printf "%d.%d.%d.%d\n"  "$((i1 & m1))" "$((i2 & m2))" "$((i3 & m3))" "$((i4 & m4))"
}
# GH-667: everything in here used to be printed on STDOUT, including the
# failures. The sole consumers assign it through $( ), so an error message
# became the value -- "endrange=Invalid IP Passed" -- and got written verbatim
# into dhcpd.conf, which is precisely the "pasted into the dhcpd.conf file and
# causes the dhcp service to fail" in that report. Diagnostics go to stderr so
# they cannot be mistaken for data, and the callers now check what they got.
mask2broadcast() {
    # Broadcast is the network with every host bit set: net | ~mask, per octet.
    # Used as the fallback when an interface carries no brd flag at all.
    local OIFS=$IFS
    IFS='.'
    read -r n1 n2 n3 n4 <<< "$1"
    read -r m1 m2 m3 m4 <<< "$2"
    IFS=$OIFS
    printf "%d.%d.%d.%d\n" \
        "$((n1 | (255 - m1)))" "$((n2 | (255 - m2)))" \
        "$((n3 | (255 - m3)))" "$((n4 | (255 - m4)))"
}
interface2broadcast() {
    local interface=$1
    if [[ -z $interface ]]; then
        echo "No interface passed" >&2
        return 1
    fi
    # One address per line means one brd per line, so an interface carrying a
    # second address returned two. Take the first, matching the $ipaddress /
    # $ipaddresses contract from GH-954. Empty is a legitimate answer -- a /32
    # or a point-to-point link has no broadcast -- and the caller falls back.
    ip -4 addr show $interface | grep -oP 'brd \K\S+' | head -1
}
subtract1fromAddress() {
    local ip=$1
    if [[ -z $ip ]]; then
        echo "No IP Passed" >&2
        return 1
    fi
    if [[ ! $(validip $ip) -eq 0 ]]; then
        echo "Invalid IP Passed" >&2
        return 1
    fi
    local oIFS=$IFS
    IFS='.'
    read ip1 ip2 ip3 ip4 <<< "$ip"
    IFS=$oIFS
    if [[ $ip4 -gt 0 ]]; then
        let ip4-=1
    elif [[ $ip3 -gt 0 ]]; then
        let ip3-=1
        ip4=255
    elif [[ $ip2 -gt 0 ]]; then
        let ip2-=1
        ip3=255
        ip4=255
    elif [[ $ip1 -gt 0 ]]; then
        let ip1-=1
        ip2=255
        ip3=255
        ip4=255
    else
        echo "Invalid IP ranges were passed" >&2
        echo ${ip1}.${ip2}.${ip3}.${ip4}
        return 2
    fi
    echo ${ip1}.${ip2}.${ip3}.${ip4}
}
subtractFromAddress() {
    local ipaddress="$1"
    local decreaseby=$2
    local maxOctetValue=256
    local octet1=""
    local octet2=""
    local octet3=""
    local octet4=""
    local oIFS=$IFS
    IFS='.' read octet1 octet2 octet3 octet4 <<< "$ipaddress"
    IFS=$oIFS
    let octet4-=$decreaseby
    if [[ $octet4 -lt $maxOctetValue && $octet4 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    octet4=$(echo $octet4 | sed 's/-//g')
    numRollOver=$((octet4 / maxOctetValue))
    let octet4-=$((numRollOver * maxOctetValue))
    let octet3-=$numRollOver
    if [[ $octet3 -lt $maxOctetValue && $octet3 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    numRollOver=$((octet3 / maxOctetValue))
    let octet3-=$((numRollOver * maxOctetValue))
    let octet2-=$numRollOver
    if [[ $octet2 -lt $maxOctetValue && $octet2 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    numRollOver=$((octet2 / maxOctetValue))
    let octet2-=$((numRollOver * maxOctetValue))
    let octet1-=$numRollOver
    if [[ $octet1 -lt $maxOctetValue && $octet1 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4 | sed 's/-//g'
        return 0
    fi
    return 1
}
addToAddress() {
    local ipaddress="$1"
    local increaseby=$2
    local maxOctetValue=256
    local octet1=""
    local octet2=""
    local octet3=""
    local octet4=""
    local oIFS=$IFS
    IFS='.' read octet1 octet2 octet3 octet4 <<< "$ipaddress"
    IFS=$oIFS
    let octet4+=$increaseby
    if [[ $octet4 -lt $maxOctetValue && $octet4 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet4 / maxOctetValue))
    let octet4-=$((numRollOver * maxOctetValue))
    let octet3+=$numRollOver
    if [[ $octet3 -lt $maxOctetValue && $octet3 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet3 / maxOctetValue))
    let octet3-=$((numRollOver * maxOctetValue))
    let octet2+=$numRollOver
    if [[ $octet2 -lt $maxOctetValue && $octet2 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    numRollOver=$((octet2 / maxOctetValue))
    let octet2-=$((numRollOver * maxOctetValue))
    let octet1+=$numRollOver
    if [[ $octet1 -lt $maxOctetValue && $octet1 -ge 0 ]]; then
        printf "%d.%d.%d.%d\n" $octet1 $octet2 $octet3 $octet4
        return 0
    fi
    return 1
}
getAllNetworkInterfaces() {
    gatewayif=$(ip -4 route show | grep "^default via" | awk '{print $5}')
    if [[ -z ${gatewayif} ]]; then
        interfaces="$(ip -4 link | grep -v LOOPBACK | grep UP | awk -F': |@' '{print $2}' | tr '\n' ' ')"
    else
        interfaces="$gatewayif $(ip -4 link | grep -v LOOPBACK | grep UP | awk -F': |@' '{print $2}' | tr '\n' ' ' | sed 's/${gatewayif}//g')"
    fi
    echo -n $interfaces
}
listPackages() {
    if [[ $listPackages != 1 ]]; then
        return
    fi
    . ../lib/common/config.sh
    [[ -z $fogpriorconfig ]] && fogpriorconfig="$fogprogramdir/.fogsettings"
    if [[ -f $fogpriorconfig ]]; then
        . "$fogpriorconfig"
        case $osid in
            1)
                osname="Redhat"
                . ../lib/redhat/config.sh
                ;;
            2)
                osname="Debian"
                . ../lib/ubuntu/config.sh
                ;;
            3)
                # GH-447: osid 3 means Alpine here, but it meant Arch on the
                # 1.5 line. An Arch box upgrading from 1.5 carries osid=3 in
                # .fogsettings and would silently be configured as Alpine --
                # wrong package manager, wrong init system, wrong web server.
                # Catch it by what the machine actually is and move it to 4.
                if [[ $linuxReleaseName_lower == *arch* || $linuxReleaseName_lower == *manjaro* ]]; then
                    echo " * Recording this Arch install as osid 4 (it was 3 on FOG 1.5)"
                    osid=4
                    osname="Arch"
                    . ../lib/arch/config.sh
                else
                    osname="Alpine"
                    . ../lib/alpine/config.sh
                fi
                ;;
            4)
                osname="Arch"
                . ../lib/arch/config.sh
                ;;
        esac
    else
        case $linuxReleaseName_lower in
            *fedora*|*red*hat*|*centos*|*mageia*|*alma*|*rocky*)
                osid=1
                osname="Redhat"
                . ../lib/redhat/config.sh
                ;;
            *ubuntu*|*bian*|*mint*)
                osid=2
                osname="Debian"
                . ../lib/ubuntu/config.sh
                ;;
            *alpine*)
                osid=3
                osname="Alpine"
                . ../lib/alpine/config.sh
                ;;
            *arch*|*manjaro*)
                osid=4
                osname="Arch"
                . ../lib/arch/config.sh
                ;;
            *)
                echo "Could not define OS"
                exit 1
                ;;
        esac
    fi
    if [[ $ignorehtmldoc -eq 1 ]]; then
        [[ -z $newpackagelist ]] && newpackagelist=""
        for z in $packages; do
            [[ -$z != htmldoc ]] && newpackagelist="$newpackagelist $z"
        done
        packages=$(echo $newpackagelist)
    fi
    if [[ $bldhcp == 0 ]]; then
        [[ -z $newpackagelist ]] && newpackagelist=""
        for z in $packages; do
            [[ -$z != $dhcpname ]] && newpackagelist="$newpackagelist $z"
        done
        packages=$(echo $newpackagelist)
    fi
    case $installtype in
        [Ss])
            packages=$(echo $packages | sed -e 's/[-a-zA-Z]*dhcp[-a-zA-Z]*//g')
            ;;
    esac
    packages="$packages jq unzip attr ${webserver}"
    case $osid in
        1)
            packages="$packages php-bcmath bc"
            if [[ $installlang -eq 1 ]]; then
                packages="$packages php-intl"
                for i in fr de eu es pt zh en ja; do
                    packages="$packages glibc-langpack-${i}"
                done
            fi
            packages="${packages// mod_fastcgi/}"
            packages="${packages// mod_evasive/}"
            packages="${packages// php-mcrypt/}"
            case $linuxReleaseName_lower in
                *fedora*)
                    packages="$packages php-json"
                    packages="${packages// mysql / mariadb }"
                    packages="${packages// mysql-server / mariadb-server }"
                    packages="${packages// dhcp / dhcp-server }"
            esac
            ;;
        2)
            if [[ $webserver == "apache2" ]]; then
                packages="${packages// libapache2-mod-fastcgi/}"
                packages="${packages// libapache2-mod-evasive/}"
            fi
            packages="${packages// xinetd/}"
            packages="${packages// php-gettext/}"
            packages="${packages// php-php-gettext/}"
            if [[ $installlang -eq 1 ]]; then
                packages="$packages php-intl"
                if [[ $installlang -eq 1 ]]; then
                    for i in fr de eu es pt zh-hans en ja; do
                        packages="$packages language-pack-${i}"
                    done
                fi
            fi
            case $linuxReleaseName_lower in
                *ubuntu*|*mint*)
                    if [[ $OSVersion -gt 17 ]]; then
                        packages="${packages// libcurl3 / libcurl4 }"
                    fi
                    if [[ $OSVersion -gt 22 ]]; then
                        packages="${packages// libcurl4 / libcurl4t64 }"
                    fi
            esac
            ;;
        *bian*)
            if [[ $OSVersion -ge 10 ]]; then
                packages="${packages// libcurl3 / libcurl4 }"
                packages="${packages// mysql-client / mariadb-client}"
                packages="${packages// mysql-server / mariadb-server}"
            fi
            if [[ $OSVersion -ge 13 ]]; then
                packages="${packages// libcurl4 / libcurl4t64 }"
            fi
            ;;
    esac
    packages=$(echo ${packages[@]} | tr ' ' '\n' | sort -u | tr '\n' ' ')
    echo $packages;
    exit 0;
}
checkInternetConnection() {
    dots "Testing internet connection"
    DEBIAN_FRONTEND=noninteractive $packageinstaller curl >>$error_log 2>&1

    http_sites=("httpbin.org" "neverssl.com")
    https_sites=("github.com" "fogproject.org")
    dns_ok=0
    http_ok=0
    https_ok=0

    for dnsname in "${http_sites[@]}" "${https_sites[@]}"; do
        echo -n "Testing DNS name resolution (${dnsname})... " >> $error_log
        getent hosts ${dnsname} >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            echo "Failed" >> $error_log
            continue
        fi
        dns_ok=1
        echo "OK" >> $error_log
        break
    done
    if [[ $dns_ok -eq 0 ]]; then
        echo "Failed"
        echo
        echo "There seems to be a DNS problem. Check the contents of /etc/resolv.conf" | tee -a $error_log
        echo "If this is CentOS, RHEL, or Fedora or an other RH variant, also check" | tee -a $error_log
        echo "the DNS entries in /etc/sysconfig/network-scripts/ifcfg-*" | tee -a $error_log
        echo
        return
    fi
    for url in "${http_sites[@]}"; do
        echo -n "Testing HTTP connection (http://${url})... " >> $error_log
        curl --silent http://${url} >/dev/null 2>>$error_log
        if [[ $? -ne 0 ]]; then
            echo "Failed" >> $error_log
            continue
        fi
        http_ok=1
        echo "OK" >> $error_log
        break
    done
    for url in "${https_sites[@]}"; do
        echo -n "Testing HTTPS connection (https://${url})... " >> $error_log
        curl --silent -k https://${url} >/dev/null 2>>$error_log
        if [[ $? -ne 0 ]]; then
            echo "Failed" >> $error_log
            continue
        fi
        https_ok=1
        echo "OK" >> $error_log
        break
    done
    if [[ $http_ok -eq 0 && $https_ok -eq 0 ]]; then
        echo "Failed"
        echo
        echo "There was no interface with an active internet connection found." | tee -a $error_log
        echo "If you are using a proxy server, please export http_proxy and https_proxy or use .curlrc" | tee -a $error_log
        echo
        return
    fi
    echo "Done"
}
join() {
    local IFS="$1"
    shift
    echo "$*"
}
restoreReports() {
    dots "Restoring user reports"
    if [[ -d $webdirdest/management/reports ]]; then
        if [[ -d ../rpttmp/ ]]; then
            cp -a ../rpttmp/* $webdirdest/management/reports/
        fi
    fi
    errorStat $?
}
installFOGServices() {
    dots "Setting up FOG Services"
    mkdir -p $servicedst
    cp -Rf $servicesrc/* $servicedst/
    chmod +x -R $servicedst/
    mkdir -p $servicelogs
    errorStat $?
    dots "Creating FOG cache directory"
    mkdir -p $fogprogramdir/cache >>$error_log 2>&1
    # The settings-cache flush signal is written by the web tier AND by the CLI
    # daemons. The php-fpm worker does not always run as $apacheuser (e.g. on
    # RedHat/nginx the packaged pool keeps user=apache), so an $apacheuser-owned
    # dir is not reliably writable by the process that needs to touch the signal.
    # The file holds no secrets, so use /tmp-style sticky world-writable perms.
    chown ${username}:${apacheuser} $fogprogramdir/cache >>$error_log 2>&1
    chmod 1777 $fogprogramdir/cache >>$error_log 2>&1
    errorStat $?
    # GH-964: /opt/fog inherits usr_t, and httpd_t may READ usr_t but not write
    # it. The lab's audit log carried 74,406 httpd_t->usr_t:file denials and
    # every one of them was a write. Reads being allowed is what hides it:
    # nothing fails until something tries to write, so the install looks clean
    # and the settings-cache flush silently never happens.
    #
    # Labelled where the directory is created rather than in a sweep at the end,
    # so a relocated $fogprogramdir (GH-850) is labelled wherever it landed.
    setSELinuxContext "$fogprogramdir/cache" httpd_sys_rw_content_t
}
configureUDPCast() {
    dots "Setting up UDPCast"
    cur=$(pwd)
    [[ ! -d ../tmp/ ]] && mkdir -p ../tmp/ >/dev/null 2>&1
    cd ../tmp
    rm -rf $udpcastout
    tar xzf $udpcastsrc >>$error_log 2>&1
    cd $udpcastout
    grep -q 'BCM[0-9][0-9][0-9][0-9]' /proc/cpuinfo >>$error_log 2>&1
    if [[ $? -eq 0 ]]; then
        wget -qO config.guess "https://git.savannah.gnu.org/gitweb/?p=config.git;a=blob_plain;f=config.guess" >>$error_log 2>&1
        wget -qO config.sub "https://git.savannah.gnu.org/gitweb/?p=config.git;a=blob_plain;f=config.sub" >>$error_log 2>&1
        chmod +x config.guess config.sub >>$error_log 2>&1
    fi
    errorStat $?
    dots "Configuring UDPCast"
    ./configure >>$error_log 2>&1
    errorStat $?
    dots "Building UDPCast"
    make >>$error_log 2>&1
    errorStat $?
    dots "Installing UDPCast"
    make install >>$error_log 2>&1
    errorStat $?
    if [[ -f "/usr/local/sbin/udp-sender" ]]; then
        if [[ ! -f "/usr/sbin/udp-sender" ]]; then
            ln -sf "/usr/local/sbin/udp-sender" "/usr/sbin/udp-sender"
        fi
    elif [[ -f "/usr/sbin/udp-sender" ]]; then
        if [[ ! -f "/usr/local/sbin/udp-sender" ]]; then
            ln -sf "/usr/sbin/udp-sender" "/usr/local/sbin/udp-sender"
        fi
    fi
    cd $cur
}
configureFTP() {
    dots "Setting up and starting VSFTP Server"
    if [[ -f $ftpxinetd ]]; then
        mv $ftpxinetd ${ftpxinetd}.fogbackup
    fi
    vsftp=$(vsftpd -version 0>&1 | awk -F'version ' '{print $2}')
    vsvermaj=$(echo $vsftp | awk -F. '{print $1}')
    vsverbug=$(echo $vsftp | awk -F. '{print $3}')
    seccompsand=""
    allow_writeable_chroot=""
    if [[ $vsvermaj -gt 3 ]] || [[ $vsvermaj -eq 3 && $vsverbug -ge 2 ]]; then
        seccompsand="seccomp_sandbox=NO"
    fi
    mv -fv "${ftpconfig}" "${ftpconfig}.${timestamp}" >>$error_log 2>&1
    # GH-964 sibling: pin the passive data range. Without this vsftpd draws
    # from the ephemeral range, which cannot be opened in a firewall without
    # the nf_conntrack_ftp helper -- and modern kernels stopped auto-assigning
    # helpers, so relying on one is relying on something that is off by
    # default. Pinning the range is what lets configureFirewall() open exactly
    # the ports FTP will actually use. The bounds live in lib/common/config.sh
    # next to the multicast window so the daemon config and the firewall rules
    # cannot drift apart.
    echo -e  "max_per_ip=200\nanonymous_enable=NO\nlocal_enable=YES\nwrite_enable=YES\nlocal_umask=022\ndirmessage_enable=YES\nxferlog_enable=YES\nconnect_from_port_20=YES\nxferlog_std_format=YES\nlisten=YES\npam_service_name=vsftpd\nuserlist_enable=NO\nchmod_enable=YES\npasv_min_port=${ftppasvmin}\npasv_max_port=${ftppasvmax}\n$seccompsand" > "$ftpconfig"
    diffconfig "${ftpconfig}"
    case $systemctl in
        yes)
            systemctl is-enabled --quiet vsftpd && true || systemctl enable vsftpd >>$error_log 2>&1
            systemctl is-active --quiet vsftpd && systemctl stop vsftpd >>$error_log 2>&1 || true
            systemctl is-active --quiet vsftpd && true || systemctl start vsftpd >>$error_log 2>&1
            systemctl status vsftpd >>$error_log 2>&1
            ;;
        *)
            case $osid in
                2)
                    sysv-rc-conf vsftpd on >>$error_log 2>&1
                    service vsftpd stop >>$error_log 2>&1
                    service vsftpd start >>$error_log 2>&1
                    service vsftpd status >>$error_log 2>&1
                    ;;
                *)
                    chkconfig vsftpd on >>$error_log 2>&1
                    service vsftpd stop >>$error_log 2>&1
                    service vsftpd start >>$error_log 2>&1
                    service vsftpd status >>$error_log 2>&1
                    ;;
            esac
            ;;
    esac
    errorStat $?
}
configureDefaultiPXEfile() {
    dots 'Configuring default iPXE file'
    [[ -z $webroot ]] && webroot='/fog/'   # see registerStorageNode, GH-529
    # The `chain custom.ipxe || goto fog_default` first line is the supported
    # hook point for site-specific pre-boot behavior -- a boot delay, a prompt,
    # a local menu -- so an admin never has to hand-edit this file, which is
    # regenerated in full on every run and would lose those edits anyway.
    #
    # Safe when unused: per ipxe.org/cmd/chain a chain to a file that is not
    # there simply fails, the || fires, and boot proceeds exactly as before.
    # Safe when used: chain WITHOUT --replace returns control to the next line
    # once the chained script finishes normally, and the next line is
    # :fog_default, so execution falls straight through into FOG's own logic.
    # No "resume" convention to get wrong, and no way to loop back here.
    #
    # custom.ipxe lives at the TFTP root, which configureTFTPandPXE() only ever
    # snapshots and copies INTO -- it never deletes destination files absent
    # from the source tree. So the hook file survives updates structurally,
    # the same way the Secure Boot keys do by living outside $webdirdest, and
    # needs no backup/restore machinery of its own.
    echo -e "#!ipxe\nchain custom.ipxe || goto fog_default\n:fog_default\nset arch \${buildarch}\niseq \${arch} i386 && cpuid --ext 29 && set arch x86_64 ||\nparams\nparam mac0 \${net0/mac}\nparam arch \${arch}\nparam platform \${platform}\nparam product \${product}\nparam manufacturer \${product}\nparam ipxever \${version}\nparam filename \${filename}\nparam sysuuid \${uuid}\nisset \${net1/mac} && param mac1 \${net1/mac} || goto bootme\nisset \${net2/mac} && param mac2 \${net2/mac} || goto bootme\n:bootme\nchain ${httpproto}://$ipaddress${webroot}service/ipxe/boot.php##params" > "$tftpdirdst/default.ipxe"
    errorStat $?
}
prepareiPXEsource() {
    # Put the fog-ipxe checkout at $buildipxesrc ($fogprogramdir/ipxe) and pin
    # it to the same tag the binaries were downloaded from, so a locally built
    # binary and a downloaded one are the same build. Cloning an unpinned
    # default branch here would put us straight back to "two people install on
    # the same day and get different binaries", which is exactly what pinning
    # IPXEVER fixed upstream-side. See GH-957, GH-959.
    #
    # An existing checkout is reused rather than replaced. That is what makes
    # an offline install possible: pre-place this directory (and its build/
    # subdirectory of upstream clones) and nothing here needs the network.
    dots "Preparing iPXE build sources"
    if [[ -d $buildipxesrc/.git ]]; then
        git -C "$buildipxesrc" fetch --tags --force "$ipxegit" >>$error_log 2>&1
        if ! git -C "$buildipxesrc" checkout -q "$ipxeVer" >>$error_log 2>&1; then
            # Offline, or the tag does not exist yet. A usable checkout is
            # still a usable checkout -- do not fail an entire install over a
            # fetch that was only ever an update.
            echo "Skipped (using existing checkout)"
            return 0
        fi
        errorStat 0
        return 0
    fi
    if [[ -x $buildipxesrc/buildipxe.sh ]]; then
        # Pre-placed as a plain directory rather than a clone -- the documented
        # offline path. Leave it entirely alone.
        echo "Skipped (pre-placed sources)"
        return 0
    fi
    if ! git clone -q --branch "$ipxeVer" "$ipxegit" "$buildipxesrc" >>$error_log 2>&1; then
        # Guidance first: errorStat exits before returning unless $exitFail is
        # set, so anything printed after it is never seen.
        echo "Failed!"
        echo " * Could not obtain iPXE sources ($ipxeVer) from $ipxegit"
        echo " * For an offline install, place a fog-ipxe checkout at $buildipxesrc"
        [[ -z $exitFail ]] && exit 1
        return 1
    fi
    errorStat 0
}
fetchipxeasset() {
    # Download one fog-ipxe release tarball, verify its checksum, and unpack it
    # into the staging tree. Callers own the dots/messaging and decide whether a
    # failure is fatal, because the two assets differ exactly there: without the
    # binaries nothing PXE boots at all, while without the Secure Boot set every
    # client that boots today still boots.
    #
    # Retries by re-running sha256sum -c rather than trusting curl's exit code,
    # so a truncated or corrupted download is caught and refetched rather than
    # unpacked. --fail makes an HTTP error a failed fetch instead of an error
    # page written into the tarball, which the checksum then rejected ten times
    # over before giving up.
    local tarball="$1"
    local dest="$2"
    local url="${ipxeurl}/${ipxeVer}/${tarball}"
    [[ ! -d ../tmp/ ]] && mkdir -p ../tmp/ >>$error_log 2>&1
    local cwd=$(pwd)
    cd ../tmp/
    local checksum=1
    local cnt=0
    while [[ $checksum -ne 0 && $cnt -lt 10 ]]; do
        [[ -f ${tarball}.sha256 ]] && sha256sum -c ${tarball}.sha256 >>$error_log 2>&1
        checksum=$?
        if [[ $checksum -ne 0 ]]; then
            curl --silent -fkOL "$url" >>$error_log 2>&1
            curl --silent -fkOL "${url}.sha256" >>$error_log 2>&1
        fi
        let cnt+=1
    done
    if [[ $checksum -ne 0 ]]; then
        cd $cwd
        return 1
    fi
    mkdir -p "$dest" >>$error_log 2>&1
    tar -xzf "$tarball" -C "$dest" >>$error_log 2>&1
    local stat=$?
    cd $cwd
    return $stat
}
downloadipxe() {
    # iPXE binaries used to be 70 files committed to this repository. They are
    # now a release asset, verified and unpacked into the same staging tree the
    # copy loop in configureTFTPandPXE reads, so everything downstream is
    # unchanged. One tarball rather than 85 loose assets: release assets cannot
    # hold directories, and this tree has i386-efi/, arm64-efi/ and autoexec/
    # subdirectories worth keeping. See GH-959.
    local tarball="fog-ipxe-${ipxeVer}.tar.gz"
    local dest=$(readlink -f $tftpdirsrc)
    dots "Downloading iPXE binaries (${ipxeVer})"
    # An HTTPS install is about to rebuild these from source anyway, and the
    # rebuild writes to this same directory. Downloading first would be wasted
    # bandwidth on every such install.
    if [[ "x$httpproto" = "xhttps" ]]; then
        echo "Skipped (built locally)"
        return 0
    fi
    if ! fetchipxeasset "$tarball" "$dest"; then
        # Guidance first: errorStat exits before returning unless $exitFail is
        # set, so anything printed after it is never seen.
        echo "Failed!"
        echo " * Could not download $tarball from ${ipxeurl}/${ipxeVer}/"
        echo " * For an offline install, place the extracted binaries in $dest"
        [[ -z $exitFail ]] && exit 1
        return 1
    fi
    errorStat 0
}
downloadipxesecureboot() {
    # The Secure Boot chain needs binaries FOG cannot build, because they are
    # signed by keys FOG does not hold: Microsoft's, for the shim, and iPXE's,
    # for the loader it chains to. fog-ipxe republishes upstream's byte for
    # byte, hash- and signer-verified at release time, as a second asset. This
    # is what makes the chain available without every install reaching out to
    # two third-party release URLs unsupervised.
    #
    # It unpacks into the same staging tree as everything else, so it lands at
    # $tftpdirdst/secureboot/ through the copy loop already in
    # configureTFTPandPXE. Staged unconditionally and served to nobody: a client
    # only ever sees it if DHCP is pointed at secureboot/snponly-shimx64.efi,
    # so the cost of having it present is a few MB and the cost of NOT having it
    # is an admin hand-assembling a signed boot chain from two upstream projects.
    # See GH-960.
    local tarball="fog-ipxe-secureboot-${ipxeVer}.tar.gz"
    local dest=$(readlink -f $tftpdirsrc)
    dots "Downloading iPXE Secure Boot binaries (${ipxeVer})"
    # These are upstream's generic signed binaries, so they cannot carry this
    # server's CA -- which is the whole reason an HTTPS install rebuilds iPXE
    # locally. A signed binary cannot be rebuilt without invalidating the
    # signature, so Secure Boot and HTTPS are mutually exclusive here. Staging
    # them anyway would leave an admin a directory that looks usable and fails
    # at the client with a TLS error.
    if [[ "x$httpproto" = "xhttps" ]]; then
        echo "Skipped (not usable with HTTPS)"
        return 0
    fi
    # Deliberately NOT fatal, unlike downloadipxe above. A missing Secure Boot
    # set costs nothing to any client that boots today, so failing the whole
    # install over it would be a regression for every site that does not use it.
    if ! fetchipxeasset "$tarball" "$dest"; then
        echo "Skipped (unavailable)"
        echo " * Could not download $tarball from ${ipxeurl}/${ipxeVer}/"
        echo " * Secure Boot clients will not have a signed chain to boot; every"
        echo " *   other client is unaffected. Re-run the installer to retry."
        return 0
    fi
    errorStat 0
}
configureTFTPandPXE() {
    # Fills $tftpdirsrc, which is now a staging directory rather than tracked
    # build output, so this has to happen before anything reads from it.
    downloadipxe || return 1
    downloadipxesecureboot
    [[ -d ${tftpdirdst}.prev ]] && rm -rf ${tftpdirdst}.prev >>$error_log 2>&1
    [[ ! -d ${tftpdirdst} ]] && mkdir -p $tftpdirdst >>$error_log 2>&1
    [[ -e ${tftpdirdst}.fogbackup ]] && rm -rf ${tftpdirdst}.fogbackup >>$error_log 2>&1
    [[ -d $tftpdirdst && ! -d ${tftpdirdst}.prev ]] && mkdir -p ${tftpdirdst}.prev >>$error_log 2>&1
    [[ -d ${tftpdirdst}.prev ]] && cp -Rf $tftpdirdst/* ${tftpdirdst}.prev/ >>$error_log 2>&1
    sslpath=${sslpath//\/$}
    if [[ "x$httpproto" = "xhttps" ]]; then
        # The one case a release asset cannot serve: CERT=/TRUST= bake this
        # server's CA into the binary so iPXE can fetch boot.php over TLS,
        # which makes it a per-server artifact by definition. Everything else
        # about the build is identical everywhere, which is why every other
        # install just downloads. See GH-959.
        prepareiPXEsource || return 1
        dots "Compiling iPXE binaries trusting your SSL certificate"
        [[ -n $sslcachain ]] && ipxetrust="$sslcachain" || ipxetrust="$sslcapem"
        # Second argument is the output directory: build straight into the
        # staging tree the copy loop below already reads, so a locally built
        # binary lands exactly where a downloaded one would.
        "${buildipxesrc}/buildipxe.sh" "${ipxetrust}" "$(readlink -f $tftpdirsrc)" >>$workingdir/error_logs/fog_ipxe-build_${version}.log 2>&1
        errorStat $?
        cd $workingdir
    fi
    cd $tftpdirsrc
    find -type d -exec mkdir -p $tftpdirdst/{} \; >>$error_log 2>&1
    find -type f -exec cp -Rfv {} $tftpdirdst/{} \; >>$error_log 2>&1
    cd $workingdir
    # iPXE resolves the bare name "autoexec.ipxe" against its current working
    # URI -- the TFTP directory the running .efi was itself fetched from -- not
    # against a fixed path. So our EMBED-less binaries under autoexec/ look
    # inside autoexec/, and the Secure Boot chain's under secureboot/ look
    # inside secureboot/. Publish every such path.
    #
    # This is what the Secure Boot chain needs: upstream's signed snponly.efi
    # has no script compiled in, so without this it asks for a file that was
    # never created. See GH-960.
    #
    # Every directory an EMBED-less binary can be booted from therefore needs
    # its own copy. Hard link, not copies: they are meant to be one script, and
    # a link keeps them from drifting -- an admin who edits the boot logic
    # should not have to know how many copies exist.
    #
    # The root of $tftpdirdst is deliberately NOT in this list, and any root
    # copy left by an earlier release is removed below.
    #
    # Not a symlink -- some TFTP daemons refuse to follow those, and a hard link
    # is indistinguishable from a regular file to every daemon.
    #
    # Relinked unconditionally on every run. In practice the copy loop above
    # truncates the existing file in place and the link survives, but that is
    # cp's behaviour rather than a guarantee, and an admin who replaced the
    # file with an editor that writes-and-renames will have broken the link.
    # ln -f is idempotent, so re-running costs nothing and restores the
    # invariant either way.
    if [[ -f $tftpdirdst/autoexec/autoexec.ipxe ]]; then
        local autoexecpath
        for autoexecpath in \
            $tftpdirdst/autoexec/i386-efi/autoexec.ipxe \
            $tftpdirdst/autoexec/arm64-efi/autoexec.ipxe \
            $tftpdirdst/secureboot/autoexec.ipxe \
            $tftpdirdst/secureboot/arm64-efi/autoexec.ipxe; do
            # Skip rather than create: the secureboot directories only exist if
            # that asset was staged, and an autoexec.ipxe with no binary beside
            # it serves no one.
            [[ -d $(dirname $autoexecpath) ]] || continue
            ln -f $tftpdirdst/autoexec/autoexec.ipxe $autoexecpath >>$error_log 2>&1
        done
    fi
    # Remove any autoexec.ipxe at the root of $tftpdirdst. Earlier releases
    # linked one there. Dropping it from the list above is not enough on its
    # own -- an upgrade would leave the existing file in place and the install
    # would stay broken -- so it is deleted here.
    #
    # Nothing we ship at the root reads it. The root holds the EMBED-marked
    # binaries, which run their compiled-in script; only the EMBED-less ones
    # under autoexec/ and secureboot/ ever execute a downloaded autoexec.ipxe.
    #
    # Every EFI binary nonetheless *downloads* it. efi_probe() calls
    # efi_autoexec_load() unconditionally, which registers the script before any
    # driver is connected. An EMBED-marked binary then never executes it --
    # first_image() returns the embedded script, which sorts ahead of it -- so
    # nothing ever unregisters it. iPXE has no notion of an image that was
    # loaded but not wanted, and only fdt/shim images are ever hidden.
    #
    # At "boot", initrd_load_all() concatenates every registered non-hidden
    # image into the ramdisk in registration order. autoexec.ipxe registered
    # first, so the kernel is handed 2 KB of iPXE script where it expects the
    # head of init.xz, does not find the compression magic, falls back to
    # treating the blob as a legacy initrd, and panics with
    #
    #     VFS: Unable to mount root fs on "/dev/ram0" or unknown-block(1,0)
    #
    # See forums #18213. This costs nothing: the file is a hard link to
    # autoexec/autoexec.ipxe, so its content -- including any local edit, which
    # the link shares -- survives in every other published path.
    rm -f $tftpdirdst/autoexec.ipxe >>$error_log 2>&1
    chown -R $username $tftpdirdst >>$error_log 2>&1
    chown -R $username $webdirdest/service/ipxe >>$error_log 2>&1
    find $tftpdirdst -type d -exec chmod 755 {} \; >>$error_log 2>&1
    find $webdirdest -type d -exec chmod 755 {} \; >>$error_log 2>&1
    find $tftpdirdst ! -type d -exec chmod 655 {} \; >>$error_log 2>&1
    configureDefaultiPXEfile
    # GH-963: must come AFTER every file is in place, configureDefaultiPXEfile
    # included. restorecon only fixes what already exists, so anything written
    # after this call keeps whatever label it inherited.
    #
    # tftpd_t is permitted to read tftpdir_t, tftpdir_rw_t and var_t. The first
    # two are what the distro packages use; var_t covers Arch's /srv/tftp and
    # Alpine's /var/tftpboot, which resolve there and work as-is.
    setSELinuxContext "$tftpdirdst" tftpdir_t tftpdir_rw_t var_t
    dots 'Setting up and starting TFTP Server'
    case $systemctl in
        yes)
            # make sure xinetd is off for all systemd distros as we don't use it anymore
            systemctl is-enabled --quiet xinetd 2>/dev/null && systemctl disable xinetd >>$error_log 2>&1 || true
            systemctl is-active --quiet xinetd && systemctl stop xinetd >>$error_log 2>&1 || true
            if [[ -f /etc/xinetd.d/tftp ]]; then
                rm -f /etc/xinetd.d/tftp
            fi
            if [[ $osid -eq 2 && -f $tftpconfigupstartdefaults ]]; then
                mv -fv "$tftpconfigupstartdefaults" "${tftpconfigupstartdefaults}.${timestamp}" >>$error_log 2>&1
                echo -e "# /etc/default/tftpd-hpa\n# FOG Modified version\nTFTP_USERNAME=\"root\"\nTFTP_DIRECTORY=\"/tftpboot\"\nTFTP_ADDRESS=\":69\"\nTFTP_OPTIONS=\"${tftpAdvOpts:+$tftpAdvOpts }-s\"" > "$tftpconfigupstartdefaults"
                diffconfig "$tftpconfigupstartdefaults"
                systemctl is-enabled --quiet tftpd-hpa && true || systemctl enable tftpd-hpa >>$error_log 2>&1
                systemctl is-active --quiet tftpd-hpa && systemctl stop tftpd-hpa >>$error_log 2>&1 || true
                systemctl is-active --quiet tftpd-hpa && true || systemctl start tftpd-hpa >>$error_log 2>&1
                systemctl status tftpd-hpa >>$error_log 2>&1
            else
                if [[ -f /etc/systemd/system/fog-tftp.service ]]; then
                    mv -fv /etc/systemd/system/fog-tftp.service "/etc/systemd/system/fog-tftp.service.${timestamp}" >>$error_log 2>&1
                fi
                echo -e "[Unit]\nDescription=Tftp Server\nRequires=fog-tftp.socket\nDocumentation=man:in.tftpd\n\n[Service]\nExecStart=/usr/sbin/in.tftpd ${tftpAdvOpts:+$tftpAdvOpts }-s ${tftpdirdst}\nStandardInput=socket\n\n[Install]\nAlso=fog-tftp.socket" > /etc/systemd/system/fog-tftp.service
                diffconfig "/etc/systemd/system/fog-tftp.service"
                find /usr/lib/systemd/system -maxdepth 1 \( -name "tftp.socket" -o -name "tftpd.socket" \) -exec cp -v {} /etc/systemd/system/fog-tftp.socket \; -quit >>$error_log 2>&1
                systemctl daemon-reload
                systemctl is-enabled --quiet fog-tftp.socket && true || systemctl enable fog-tftp.socket >>$error_log 2>&1
                systemctl is-active --quiet fog-tftp.socket && systemctl stop fog-tftp.socket >>$error_log 2>&1 || true
                systemctl is-active --quiet fog-tftp.socket && true || systemctl start fog-tftp.socket >>$error_log 2>&1
                systemctl status fog-tftp.socket >>$error_log 2>&1
            fi
            ;;
        *)
            if [[ $osid -eq 2 && -f $tftpconfigupstartdefaults ]]; then
                mv -fv "$tftpconfigupstartdefaults" "${tftpconfigupstartdefaults}.${timestamp}" >>$error_log 2>&1
                echo -e "# /etc/default/tftpd-hpa\n# FOG Modified version\nTFTP_USERNAME=\"root\"\nTFTP_DIRECTORY=\"/tftpboot\"\nTFTP_ADDRESS=\":69\"\nTFTP_OPTIONS=\"${tftpAdvOpts:+$tftpAdvOpts }-s\"" > "$tftpconfigupstartdefaults"
                diffconfig "$tftpconfigupstartdefaults"
                sysv-rc-conf xinetd off >>$error_log 2>&1
                service xinetd stop >>$error_log 2>&1
                sysv-rc-conf tftpd-hpa on >>$error_log 2>&1
                service tftpd-hpa stop >>$error_log 2>&1
                service tftpd-hpa start >>$error_log 2>&1
            elif [[ $osid -eq 2 ]]; then
                sysv-rc-conf xinetd on >>$error_log 2>&1
                $initdpath/xinetd stop >>$error_log 2>&1
                $initdpath/xinetd start >>$error_log 2>&1
            elif [[ $osid -eq 3 ]]; then
                $initdpath/in.tftpd stop >>$error_log 2>&1
                $initdpath/in.tftpd start >>$error_log 2>&1
            else
                chkconfig xinetd on >>$error_log 2>&1
                service xinetd stop >>$error_log 2>&1
                service xinetd start >>$error_log 2>&1
                service xinetd status >>$error_log 2>&1
            fi
            ;;
    esac
    errorStat $?
}
configureMinHttpd() {
    configureHttpd
    echo "<?php" > "$webdirdest/management/index.php"
    echo "/**" >> "$webdirdest/management/index.php"
    echo " * The main index presenter" >> "$webdirdest/management/index.php"
    echo " *" >> "$webdirdest/management/index.php"
    echo " * PHP version 5" >> "$webdirdest/management/index.php"
    echo " *" >> "$webdirdest/management/index.php"
    echo " * @category Index_Page" >> "$webdirdest/management/index.php"
    echo " * @package  FOGProject" >> "$webdirdest/management/index.php"
    echo " * @author   Tom Elliott <tommygunsster@gmail.com>" >> "$webdirdest/management/index.php"
    echo " * @license  http://opensource.org/licenses/gpl-3.0 GPLv3" >> "$webdirdest/management/index.php"
    echo " * @link     https://fogproject.org" >> "$webdirdest/management/index.php"
    echo " */" >> "$webdirdest/management/index.php"
    echo "/**" >> "$webdirdest/management/index.php"
    echo " * The main index presenter" >> "$webdirdest/management/index.php"
    echo " *" >> "$webdirdest/management/index.php"
    echo " * @category Index_Page" >> "$webdirdest/management/index.php"
    echo " * @package  FOGProject" >> "$webdirdest/management/index.php"
    echo " * @author   Tom Elliott <tommygunsster@gmail.com>" >> "$webdirdest/management/index.php"
    echo " * @license  http://opensource.org/licenses/gpl-3.0 GPLv3" >> "$webdirdest/management/index.php"
    echo " * @link     https://fogproject.org" >> "$webdirdest/management/index.php"
    echo " */" >> "$webdirdest/management/index.php"
    echo "require '../commons/base.inc.php';" >> "$webdirdest/management/index.php"
    echo "require '../commons/text.php';" >> "$webdirdest/management/index.php"
    echo "ob_start();" >> "$webdirdest/management/index.php"
    echo "FOGCore::getClass('FOGPageManager')->render();" >> "$webdirdest/management/index.php"
    echo "ob_end_clean();" >> "$webdirdest/management/index.php"
    echo "die(_('This is a storage node, please do not access the web ui here!'));" >> "$webdirdest/management/index.php"
}
addOndrejRepo() {
    find /etc/apt/sources.list.d/ -name '*ondrej*' -exec rm -rf {} \; >>$error_log 2>&1
    DEBIAN_FRONTEND=noninteractive $packageinstaller python-software-properties >>$error_log 2>&1
    DEBIAN_FRONTEND=noninteractive $packageinstaller software-properties-common >>$error_log 2>&1
    DEBIAN_FRONTEND=noninteractive $packageinstaller ntpdate >>$error_log 2>&1
    ntpdate pool.ntp.org >>$error_log 2>&1
    locale-gen 'en_US.UTF-8' >>$error_log 2>&1
    LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y ppa:ondrej/php >>$error_log 2>&1
    [[ $webserver == "apache2" ]] && LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y ppa:ondrej/apache2 >>$error_log 2>&1
}
resolveDHCPEngine() {
    # Decide between Kea and ISC-DHCP for the optional FOG-hosted DHCP service.
    # Only relevant when FOG is actually building DHCP and the ISC package is
    # still in the install set (the storage-node and bldhcp=0 paths strip it in
    # doOSSpecificIncludes before we ever get here). Must run after repo setup
    # so the Kea availability probe sees enabled repos (e.g. EPEL on RHEL).
    [[ -z $keaconfig ]] && keaconfig="/etc/kea/kea-dhcp4.conf"
    [[ $bldhcp -eq 1 ]] || return 0
    local iscpkg="$dhcpname"
    [[ -n $iscpkg && $packages == *"$iscpkg"* ]] || return 0
    # Honor an explicit/persisted choice; an existing install is never switched.
    dhcpengine="${dhcpengine,,}"
    if [[ -z $dhcpengine ]]; then
        if pkgIsInstalled "$iscpkg"; then
            # A prior ISC install is left on ISC unless the admin opts in.
            dhcpengine="isc"
        elif [[ -n $keapackage ]] && pkgIsAvailable "$keapackage"; then
            dhcpengine="kea"
        else
            dhcpengine="isc"
        fi
    fi
    if [[ $dhcpengine == kea ]]; then
        if [[ -z $keapackage || -z $keaservice ]]; then
            echo " * Kea requested but not available for this OS; using ISC-DHCP"
            dhcpengine="isc"
        else
            packages="${packages//$iscpkg/$keapackage}"
            dhcpname="$keapackage"
            dhcpd="$keaservice"
            dhcpconfig="$keaconfig"
            dhcpconfigother=""
        fi
    fi
}
# Bulk package-state helpers.
#
# installPackages used to answer "is it installed?" and "does it exist?" with
# one subprocess per package -- ~80 packages x ($packageQuery + $packagelist),
# and on RHEL every `dnf list` reloads and reparses the full repo metadata, so
# the probing alone cost minutes before a single package was installed. Ask
# each question once for the whole system instead and answer the per-package
# questions from memory.
#
# These are shell functions the per-distro configs define, not the eval'd
# command strings the older $package* vars use, because they are pipelines: a
# pipeline eval'd out of a variable is exactly what silently broke Alpine's
# packageupdater ("apk update && apk upgrade" -- the && is not re-parsed as a
# control operator after expansion, so apk got it as a literal argument).
#
# $packageQuery/$packagelist stay for the handful of callers that run BEFORE
# the metadata refresh (the EPEL/Remi repo bootstrap in installPackages); a
# cached set would answer those from a repo list that does not exist yet.
declare -A pkgInstalledSet=()
declare -A pkgAvailableSet=()
pkgAvailableKnown=0
# Defaults for a distro config that has not defined the bulk primitives. This
# file is sourced before doOSSpecificIncludes, so a config that does define
# them overrides these. Degrading rather than erroring is deliberate: an empty
# installed set just means every package gets queued (package managers are
# idempotent), and an empty available set routes pkgIsAvailable back to the
# per-package probe.
pkgQueryAll() { :; }
pkgListAll() { :; }
loadInstalledSet() {
    local name
    pkgInstalledSet=()
    while read -r name; do
        [[ -n $name ]] && pkgInstalledSet[$name]=1
    done < <(pkgQueryAll 2>>$error_log)
}
loadAvailableSet() {
    local name
    pkgAvailableSet=()
    pkgAvailableKnown=0
    while read -r name; do
        [[ -n $name ]] && pkgAvailableSet[$name]=1
    done < <(pkgListAll 2>>$error_log)
    # An empty set means the bulk primitive is unusable here -- an old yum with
    # no repoquery, a repo that failed to sync. Leaving pkgAvailableKnown at 0
    # sends pkgIsAvailable back to the per-package probe, rather than having it
    # conclude that every package in the list is missing.
    [[ ${#pkgAvailableSet[@]} -gt 0 ]] && pkgAvailableKnown=1
    # An unusable bulk primitive is a fallback, not an installer failure.
    return 0
}
loadPackageSets() {
    loadInstalledSet
    loadAvailableSet
}
pkgIsInstalled() {
    [[ -n ${pkgInstalledSet[$1]} ]]
}
pkgIsAvailable() {
    if [[ $pkgAvailableKnown -eq 1 ]]; then
        [[ -n ${pkgAvailableSet[$1]} ]]
        return $?
    fi
    local x="$1"
    eval $packagelist "$x" >>$error_log 2>&1
}
# Echo the first name the repos actually carry / that is already on the box.
# Nothing echoed and non-zero returned when none of them match.
pkgFirstAvailable() {
    local p
    for p in "$@"; do
        pkgIsAvailable "$p" && { echo "$p"; return 0; }
    done
    return 1
}
pkgFirstInstalled() {
    local p
    for p in "$@"; do
        pkgIsInstalled "$p" && { echo "$p"; return 0; }
    done
    return 1
}
installPackages() {
    [[ $installlang -eq 1 ]] && packages="$packages gettext"
    packages="$packages jq"
    packages="$packages unzip"
    packages="$packages attr"
    # Secure Boot kernel signing is on by default, so sbsign/sbverify are a
    # baseline requirement rather than something the admin installs first.
    # The name splits by distro (sbsigntool on Debian/Alpine, sbsigntools on
    # RHEL/Arch), resolved in the alternatives case below. Where neither
    # exists the package loop skips it with "(Does not exist)" and
    # _resignKernels degrades to its existing warning.
    packages="$packages sbsigntool"
    # efitools builds the signed PK/KEK/db variable updates that the automatic
    # Secure Boot enrolment path writes on the client (_publishSecureBootAuthVars).
    # Same baseline reasoning as sbsigntool: the feature is on by default, so the
    # tooling it needs is not something the admin should have to know to install
    # first. Named "efitools" on every distro this installer supports, so unlike
    # sbsigntool it needs no alternatives entry; where it is genuinely absent the
    # package loop skips it and the builder degrades to its own warning.
    packages="$packages efitools"
    packages="${packages} ${webserver}"
    # -E, not -P: busybox grep has no -P at all, so on Alpine this printed a
    # full usage screen to the console and the sftp adjustment never ran. \s
    # and (?:...) are PCRE-only, hence [[:space:]] and a plain group. stderr is
    # dropped because a host with no sshd_config is normal, not an error.
    str=$(grep -E "Subsystem[[:space:]]+sftp[[:space:]]+/usr/(lib|libexec)/openssh/sftp-server" /etc/ssh/sshd_config 2>/dev/null)
    if [[ $? -eq 0 ]]; then
        dots "Adjusting sftp for ssh"
        sed -i -e "s#$str#Subsystem\tsftp\tinternal-sftp#g" /etc/ssh/sshd_config >>$error_log 2>&1
        systemctl enable sshd >/dev/null 2>&1
        systemctl restart sshd >/dev/null 2>&1
        echo "Done"
    fi
    dots "Adjusting repository (can take a long time for cleanup)"
    case $osid in
        1)
            packages="$packages php-bcmath bc"
            if [[ $installlang -eq 1 ]]; then
                packages="$packages php-intl"
                for i in fr de eu es pt zh en ja; do
                    packages="$packages glibc-langpack-${i}";
                done
            fi
            packages="${packages// mod_fastcgi/}"
            packages="${packages// mod_evasive/}"
            packages="${packages// php-mcrypt/}"
            packages="$packages php-pecl-ssh2"
            case $linuxReleaseName_lower in
                *fedora*)
                    packages="$packages php-json"
                    packages="${packages// mysql / mariadb }" >>$error_log 2>&1
                    packages="${packages// mysql-server / mariadb-server }" >>$error_log 2>&1
                    packages="${packages// dhcp / dhcp-server }" >>$error_log 2>&1
                    ;;
                *)
                    x="epel-release"
                    eval $packageQuery >>$error_log 2>&1
                    if [[ ! $? -eq 0 ]]; then
                        y="https://dl.fedoraproject.org/pub/epel/epel-release-latest-${OSVersion}.noarch.rpm"
                        $packageinstaller $y >>$error_log 2>&1
                        errorStat $? "skipOk"
                    fi
                    y="https://rpms.remirepo.net/enterprise/remi-release-${OSVersion}.rpm"
                    x="$(basename $y | awk -F[.] '{print $1}')*"
                    eval $packageQuery >>$error_log 2>&1
                    if [[ ! $? -eq 0 ]]; then
                        rpm -Uvh $y >>$error_log 2>&1
                        errorStat $? "skipOk"
                    fi
                    rpm --import "https://rpms.remirepo.net/enterprise/${OSVersion}/RPM-GPG-KEY-remi" >>$error_log 2>&1
                    errorStat $? "skipOk"
                    if [[ -n $repoenable ]]; then
                        if [[ $OSVersion -le 7 ]]; then
                            $repoenable epel >>$error_log 2>&1 || true
                            $repoenable remi >>$error_log 2>&1 || true
                            $repoenable remi-php72 >>$error_log 2>&1 || true
                        fi
                    fi
                    ;;
            esac
            ;;
        2)
            if [[ $webserver == "apache2" ]]; then
                packages="${packages// libapache2-mod-fastcgi/}"
                packages="${packages// libapache2-mod-evasive/}"
            fi
            packages="${packages// xinetd/}"
            packages="${packages// php-gettext/}"
            packages="${packages// php-php-gettext/}"
            packages="${packages} php-bcmath bc"
            packages="${packages} php-ssh2"
            if [[ $installlang -eq 1 ]]; then
                packages="$packages php-intl"
            fi
            case $linuxReleaseName_lower in
                *ubuntu*|*mint*)
                    if [[ $installlang -eq 1 ]]; then
                        for i in fr de eu es pt zh-hans en ja; do
                            packages="$packages language-pack-${i}";
                        done
                    fi
                    if [[ $OSVersion -gt 17 ]]; then
                        packages="${packages// libcurl3 / libcurl4 }">>$error_log 2>&1
                    fi
                    if [[ $OSVersion -gt 22 ]]; then
                        packages="${packages// libcurl4 / libcurl4t64 }">>$error_log 2>&1
                    fi
                    if [[ $linuxReleaseName_lower == +(*ubuntu*) && $OSVersion -ge 18 ]]; then
                        # Fix missing universe section for Ubuntu 18.04 LIVE
                        LANG='en_US.UTF-8' LC_ALL='en_US.UTF-8' add-apt-repository -y universe >>$error_log 2>&1
                        # check to see if we still have packages from deb.sury.org (a.k.a ondrej) installed and try to clean it up
                        dpkg -l | grep -q "deb\.sury\.org"
                        if [[ $? -eq 0 ]]; then
                            # make sure we have ondrej repos enabled to be able to use ppa-purge
                            addOndrejRepo
                            # use ppa-purge to not just remove the repo but also downgrade packages to Ubuntu original versions
                            DEBIAN_FRONTEND=noninteractive apt-get install -yq ppa-purge >>$error_log 2>&1
                            [[ $webserver == "apache2" ]] && ppa-purge -y ppa:ondrej/apache2 >>$error_log 2>&1
                            # for php we want to purge all packages first as we don't want ppa-purge to try downgrading those
                            DEBIAN_FRONTEND=noninteractive apt-get purge -yq 'php5*' 'php7*' 'libapache*' >>$error_log 2>&1
                            ppa-purge -y ppa:ondrej/php >>$error_log 2>&1
                            DEBIAN_FRONTEND=noninteractive apt-get purge -yq ppa-purge >>$error_log 2>&1
                        fi
                    else
                        addOndrejRepo
                    fi
                    ;;
                *bian*)
                    if [[ $OSVersion -ge 10 ]]; then
                        packages="${packages// libcurl3 / libcurl4 }">>$error_log 2>&1
                        packages="${packages// mysql-client / mariadb-client }">>$error_log 2>&1
                        packages="${packages// mysql-server / mariadb-server }">>$error_log 2>&1
                    fi
                    if [[ $OSVersion -ge 13 ]]; then
                        packages="${packages// libcurl4 / libcurl4t64 }">>$error_log 2>&1
                    fi
                    ;;
            esac
            ;;
        3)
            # Alpine has no unversioned "php-ssh2" -- the extension is
            # php<major><minor>-pecl-ssh2, matching the php${php_apk} module
            # names lib/alpine/config.sh already builds. The old name resolved
            # to "(Does not exist)" on every run, so Alpine silently shipped
            # without the ssh2 extension FOG needs for storage node access.
            packages="${packages} php${php_apk}-pecl-ssh2"
            sed -i '/\/v3\.15\/community$/s/^#[[:space:]]*//' /etc/apk/repositories
            ;;
    esac
    errorStat $?
    dots "Preparing Package Manager"
    $packmanUpdate >>$error_log 2>&1
    if [[ $osid -eq 2 ]]; then
        if [[ $? != 0 ]] && [[ $linuxReleaseName_lower == +(*ubuntu*|*mint*) ]]; then
            cp /etc/apt/sources.list /etc/apt/sources.list.original_fog_$(date +%s)
            sed -i -e 's/\/\/*archive.ubuntu.com\|\/\/*security.ubuntu.com/\/\/old-releases.ubuntu.com/g' /etc/apt/sources.list
            $packmanUpdate >>$error_log 2>&1
            if [[ $? != 0 ]]; then
                cp -f /etc/apt/sources.list.original_fog /etc/apt/sources.list >>$error_log 2>&1
                rm -f /etc/apt/sources.list.original_fog >>$error_log 2>&1
                false
            fi
        fi
    fi
    errorStat $?
    # Read the installed and available sets once, up front. Everything below --
    # the mysql/php variant picking, the "already installed" and "does not
    # exist" decisions, and resolveDHCPEngine's two probes -- is answered from
    # these two arrays instead of spawning a package-manager query per name.
    dots "Reading package state"
    loadPackageSets
    errorStat $?
    resolveDHCPEngine
    packages=$(echo ${packages[@]} | tr ' ' '\n' | sort -u | tr '\n' ' ')
    echo -e " * Packages to be installed:\n\n\t$packages\n\n"
    newPackList=""
    local toInstall=""
    local altpkg=""
    for x in $packages; do
        case $x in
            mysql|mariadb|mariadb-client|MariaDB-client)
                # Prefer whatever is already on the box, so a MySQL host is not
                # handed a MariaDB client (or the reverse) just because that is
                # what the repos list first.
                installed_sqlclient=$(pkgFirstInstalled $sqlclientlist)
                available_sqlclient=$(pkgFirstAvailable $sqlclientlist)
                [[ -n $installed_sqlclient ]] && x=$installed_sqlclient || x=$available_sqlclient
                ;;
            mysql-server|mariadb-server|MariaDB-server)
                installed_sqlserver=$(pkgFirstInstalled $sqlserverlist)
                available_sqlserver=$(pkgFirstAvailable $sqlserverlist)
                [[ -n $installed_sqlserver ]] && x=$installed_sqlserver || x=$available_sqlserver
                ;;
            php-json)
                altpkg=$(pkgFirstAvailable php-json php-common)
                [[ -n $altpkg ]] && x=$altpkg
                ;;
            sbsigntool)
                # Debian/Ubuntu/Alpine call it sbsigntool; Fedora/RHEL/Arch
                # call it sbsigntools. Leaving x alone when neither resolves
                # lets the pkgIsAvailable check below skip it cleanly.
                altpkg=$(pkgFirstAvailable sbsigntool sbsigntools)
                [[ -n $altpkg ]] && x=$altpkg
                ;;
            php-mysql*)
                altpkg=$(pkgFirstAvailable php-mysqlnd php-mysql)
                [[ -n $altpkg ]] && x=$altpkg
                # Only add mysqli package for osid 3 for better integration.
                # This used to end in a `break`, which -- sitting in a case and
                # not a loop -- would have broken out of the whole package loop
                # and abandoned every package after this one.
                [[ $osid -eq 3 ]] && pkgIsAvailable php-mysqli && x="php-mysqli"
                ;;
        esac
        # None of the alternatives resolved; there is nothing to install.
        [[ -z $x ]] && continue
        [[ $osid == 2 && -z $dhcpd && $x == +(*'dhcp'*) ]] && dhcpd=$x
        if pkgIsInstalled "$x"; then
            dots "Skipping package:   $x"
            echo "(Already Installed)"
            newPackList="$newPackList $x"
            continue
        fi
        if ! pkgIsAvailable "$x"; then
            dots "Skipping package:   $x"
            echo "(Does not exist)"
            continue
        fi
        newPackList="$newPackList $x"
        dots "Pending package:    $x"
        echo "(Queued)"
        [[ -z $toInstall ]] && toInstall="$x" || toInstall="$toInstall $x"
    done
    packages=$newPackList
    packages=$(echo ${packages[@]} | tr ' ' '\n' | sort -u | tr '\n' ' ')
    if [[ -n $toInstall ]]; then
        toInstall=$(echo ${toInstall[@]} | tr ' ' '\n' | sort -u | tr '\n' ' ')
        # One transaction rather than one per package: the dependency solve, the
        # rpm/dpkg run and the trigger pass all happen once instead of ~80
        # times. On Arch it also stops us running a partial-upgrade `-Syu` once
        # per package, which is the thing Arch tells you never to do.
        dots "Installing $(echo $toInstall | wc -w) packages"
        DEBIAN_FRONTEND=noninteractive $packageinstaller $toInstall >>$error_log 2>&1
        if [[ $? -eq 0 ]]; then
            echo "OK"
        else
            # A batch is all-or-nothing and the log does not say which name
            # poisoned it, so fall back to the old one-at-a-time pass. Slower,
            # but it names the package that actually failed.
            echo "Failed! (retrying individually)"
            local failedPack=""
            for x in $toInstall; do
                dots "Installing package: $x"
                DEBIAN_FRONTEND=noninteractive $packageinstaller $x >>$error_log 2>&1
                if [[ $? -eq 0 ]]; then
                    echo "OK"
                else
                    echo "Failed!"
                    [[ -z $failedPack ]] && failedPack="$x" || failedPack="$failedPack $x"
                fi
            done
            [[ -n $failedPack ]] && echo -e " * Packages that could not be installed:\n\n\t$failedPack\n"
        fi
    fi
    dots "Updating packages as needed"
    DEBIAN_FRONTEND=noninteractive $packageupdater $packages >>$error_log 2>&1
    if [[ $? -eq 0 ]]; then
        echo "OK"
    else
        # Non-fatal -- everything FOG needs is installed by this point, and the
        # upgrade pass is best-effort. It used to echo "OK" unconditionally,
        # which hid the failure completely (notably on Alpine, where the
        # updater command could not run at all).
        echo "Failed! (non-fatal, see $error_log)"
    fi
    # Alpine ships no unversioned `php` binary -- it is php83/php84/... tracking
    # $php_apk -- so this printed "command not found" and left $php_ver empty,
    # which then got persisted into .fogsettings as a managed key.
    local phpbin="php"
    [[ -n $php_apk ]] && command -v "php${php_apk}" >/dev/null 2>&1 && phpbin="php${php_apk}"
    export php_ver=$($phpbin -i | grep "PHP Version" | head -1 | cut -d' ' -f 4 | cut -d'.' -f1-2)
    [[ -z ${phpfpm} ]] && export phpfpm="php${php_ver}-fpm"
    [[ -z ${phpini} ]] && export phpini="/etc/php/$php_ver/fpm/php.ini"
}
confirmPackageInstallation() {
    # Re-read the installed set -- installPackages changed it -- then check
    # every name against that one snapshot instead of running a query per
    # package all over again.
    loadInstalledSet
    for x in $packages; do
        dots "Checking package: $x"
        pkgIsInstalled "$x"
        errorStat $?
    done
}
installSELinuxModule() {
    # GH-964: FOG's web tier needs outbound HTTP, FTP and SSH. httpd_t gets
    # none of them by default, and the boolean everyone reaches for --
    # httpd_can_network_connect -- grants name_connect on the `port_type`
    # ATTRIBUTE, i.e. every port type in the policy. Three ports wanted,
    # several hundred granted, permanently, to the daemon serving the web UI.
    #
    # ssh_port_t has no narrow boolean at any width, so there is no
    # combination of setsebool calls that covers FOG without the blanket one.
    # A three-rule policy module does, and FOG owns it. See packages/selinux.
    #
    # Built from source rather than shipped as a .pp, because a compiled
    # module is tied to the policy version of the machine that built it and
    # refuses to load on an older one. Compiling against the target's own
    # policy is what makes one source file work across Fedora, RHEL, Rocky
    # and Alma.
    #
    # checkmodule and semodule_package come from checkpolicy and
    # policycoreutils, so this needs nothing beyond what a host already has
    # to have for semanage to exist. fog.te is deliberately written without
    # refpolicy macros so that selinux-policy-devel -- which is not installed
    # by default on any distro FOG supports -- is not required.
    local src="../packages/selinux/fog.te"
    local workdir
    command -v selinuxenabled >>$error_log 2>&1 || return 0
    selinuxenabled || return 0
    [[ -f $src ]] || return 0
    dots "Installing FOG SELinux policy module"
    if ! command -v semodule >>$error_log 2>&1 \
        || ! command -v checkmodule >>$error_log 2>&1 \
        || ! command -v semodule_package >>$error_log 2>&1; then
        echo "Skipped (no policy tooling)"
        echo " * FOG's web tier needs outbound HTTP/FTP/SSH, which SELinux"
        echo " *   denies by default. Under enforcing this presents as storage"
        echo " *   node operations failing with nothing in FOG's own logs."
        echo " * Install checkpolicy and policycoreutils, then re-run the"
        echo " *   installer."
        return 0
    fi
    # Deliberately rebuilt and reinstalled every run rather than skipped when
    # `semodule -l` already lists fog. Skipping would mean a later version of
    # fog.te never reaches a server that has the old one -- the same
    # silently-not-upgraded failure the kernel re-signing exists to prevent.
    # semodule -i is idempotent and this costs a couple of seconds on an
    # operation nobody runs in a loop.
    workdir=$(mktemp -d) || { echo "Failed"; return 0; }
    # checkmodule writes its outputs beside its input, so build in the temp
    # directory rather than dropping fog.mod/fog.pp into the source tree.
    cp -f "$src" "$workdir/fog.te" >>$error_log 2>&1
    if ! (cd "$workdir" && checkmodule -M -m -o fog.mod fog.te \
        && semodule_package -o fog.pp -m fog.mod) >>$error_log 2>&1 \
        || ! semodule -i "$workdir/fog.pp" >>$error_log 2>&1; then
        rm -rf "$workdir"
        # Not fatal, for the same reason downloadipxesecureboot is not: an
        # otherwise good install should not abort over a policy module, and
        # on a permissive host none of this matters anyway.
        echo "Failed"
        echo " * Could not build or load the FOG SELinux module. See $error_log."
        echo " * Under SELinux enforcing, storage node operations over HTTP,"
        echo " *   FTP and SSH will be denied until this is resolved."
        echo " * The fog_share_t label steps below will also fail, because"
        echo " *   semanage cannot register a type the policy does not know."
        return 0
    fi
    rm -rf "$workdir"
    errorStat 0
}
setSELinuxContext() {
    # setSELinuxContext <directory> <type-to-register> <acceptable-type>...
    #
    # GH-963: the installer builds its directories with mkdir/cp, so they
    # inherit the parent's SELinux label instead of the one policy intends.
    # /tftpboot came out default_t, and the confined TFTP daemon has no rule
    # permitting tftpd_t to read that type -- so on an enforcing host every PXE
    # boot failed as a bare "file not found", with nothing in FOG's own logs and
    # only an AVC denial in the audit log to explain it. FOG's answer until now
    # was checkSELinux() offering to switch SELinux off machine-wide, which is a
    # very large hammer for one mislabelled directory.
    #
    # Policy almost always already knows the correct label -- FOG simply never
    # asked for it. So ask (restorecon), and only register a rule when policy
    # has no usable answer, which happens when the admin has relocated the
    # directory out of the packaged path.
    local dir="$1" register="$2"
    shift 2
    # $register is by definition acceptable -- it is what we are aiming for.
    local acceptable=" $register $* "
    # No SELinux at all -- Debian/Ubuntu/Arch/Alpine as shipped, or explicitly
    # disabled. Silent: this is the common case and not a problem.
    command -v selinuxenabled >>$error_log 2>&1 || return 0
    selinuxenabled || return 0
    [[ -d $dir ]] || return 0
    # No path in the label: dots() pads to a fixed 60 columns, and a relocated
    # $tftpdirdst -- the very case this branch exists for -- can overflow it and
    # collide with the OK/Failed. The failure output below names the path.
    dots "Setting SELinux context"
    if ! command -v restorecon >>$error_log 2>&1; then
        echo "Skipped (no restorecon)"
        return 0
    fi
    # matchpathcon reads the same file_contexts restorecon will, so this
    # predicts exactly what restorecon is about to do rather than guessing.
    local willbe=$(matchpathcon -n "$dir" 2>>$error_log | cut -d: -f3)
    if [[ $acceptable != *" $willbe "* ]]; then
        if command -v semanage >>$error_log 2>&1; then
            # A rule, not a one-off label, so the context is a property of the
            # path: it survives `restorecon /` and a full filesystem relabel,
            # which would otherwise silently undo the fix months later. -a
            # fails if a rule is already there, hence the -m fallback.
            semanage fcontext -a -t "$register" "${dir}(/.*)?" >>$error_log 2>&1 ||
                semanage fcontext -m -t "$register" "${dir}(/.*)?" >>$error_log 2>&1
        else
            # semanage lives in policycoreutils-python-utils, which is not
            # always installed. chcon applies the label now but has no rule
            # behind it, so a relabel reverts it. Better than leaving the daemon
            # unable to read anything -- but say so rather than imply it is done.
            # GH-964: check the post-condition rather than assuming, for the
            # same reason the restorecon path does. This branch used to echo
            # OK unconditionally, which was survivable when $register was
            # always a stock type; now that FOG registers fog_share_t, a
            # module that failed to load makes chcon fail outright and
            # reporting OK would hide it.
            chcon -R -t "$register" "$dir" >>$error_log 2>&1
            local chconis=$(ls -Zd "$dir" 2>>$error_log | awk '{print $1}' | cut -d: -f3)
            if [[ $acceptable == *" $chconis "* ]]; then
                echo "OK"
                echo " * Labelled with chcon only -- a filesystem relabel will undo this."
                echo " * Install policycoreutils-python-utils and re-run to make it permanent."
                return 0
            fi
            echo "Failed!"
            echo " * Could not label $dir as '$register' (it is '$chconis')."
            echo " * If '$register' is a FOG type, the policy module did not load."
            echo " * Install policycoreutils-python-utils and re-run the installer."
            return 0
        fi
    fi
    restorecon -RF "$dir" >>$error_log 2>&1
    # Check the post-condition rather than the exit code. restorecon returns 0
    # when it has no rule to apply, which is indistinguishable from success and
    # would report a still-unreadable directory as done -- the exact silence
    # that let GH-963 sit unnoticed. Ask what the label actually IS now.
    local nowis=$(ls -Zd "$dir" 2>>$error_log | awk '{print $1}' | cut -d: -f3)
    if [[ $acceptable == *" $nowis "* ]]; then
        errorStat 0
        return 0
    fi
    # Not fatal: on a permissive or disabled host this costs nothing, and an
    # install that otherwise succeeded should not be aborted over a label. But
    # it must be loud, because the symptom it causes has no other explanation.
    echo "Failed!"
    echo " * $dir is labelled '$nowis', which the daemon using it cannot read."
    echo " * Under SELinux enforcing this presents as a bare file-not-found at"
    echo " *   the client with nothing in FOG's logs -- check for AVC denials:"
    echo " *   ausearch -m avc -ts recent"
    echo " * Fix by hand with: restorecon -RFv $dir"
    return 0
}
checkSELinux() {
    command -v sestatus >>$error_log 2>&1
    exitcode=$?
    [[ $exitcode -ne 0 ]] && return
    currentmode=$(LANG=C sestatus | grep "^Current mode" | awk '{print $3}')
    configmode=$(LANG=C sestatus | grep "^Mode from config file" | awk '{print $5}')
    [[ "x$currentmode" != "xenforcing" && "x$configmode" != "xenforcing" ]] && return
    # GH-964 step 5. This prompt used to recommend permissive and default to
    # YES, which meant every unattended (-y) install switched SELinux off
    # machine-wide without anyone deciding to. That was defensible only while
    # FOG genuinely did not work enforcing. It now does:
    #
    #   GH-963       $tftpdirdst is labelled, so PXE boots
    #   GH-966/967   $fogprogramdir/cache, $snapindir and $storageLocation are
    #                labelled, so the web tier can write
    #   GH-968/969   fog_share_t, so vsftpd can read and write image and
    #                snapin storage as well as the web tier
    #   GH-966/967   packages/selinux/fog.te, so httpd_t may reach its own
    #                API, its nodes' FTP, and SSH
    #
    # and capture, deploy and replication have all been run on an enforcing
    # host. NFS never needed anything: its data path is in-kernel as kernel_t,
    # which has unconditional access to the file_type attribute.
    #
    # So the recommendation is inverted. Note this does NOT need to remember
    # the answer the way configureFirewall() does -- if the admin chooses
    # permissive, /etc/selinux/config says so, and the early return above
    # means this function never asks again. The system state is the memory.
    echo " * SELinux is enabled and enforcing on your system."
    echo " * FOG supports this. The installer labels its directories, installs"
    echo " * a small policy module for the ports the web tier needs, and has"
    echo " * been tested capturing, deploying and replicating under enforcing."
    echo " * Leaving it on is recommended and is now the default."
    echo " * If you hit trouble later, SELinux denials never appear in FOG's"
    echo " * own logs -- check for them with: ausearch -m avc -ts recent"
    echo -n " * Set SELinux permissive anyway? (y/N) "
    sedisable=""
    while [[ -z $sedisable ]]; do
        # Unattended installs keep enforcing. This is the half of GH-964 that
        # mattered most: -y used to answer "Y" here, so an admin who never saw
        # a prompt ended up with SELinux off across the whole machine.
        [[ -n $autoaccept ]] && sedisable="N" || read -r sedisable
        case $sedisable in
            [Yy]|[Yy][Ee][Ss])
                sedisable="Y"
                setenforce 0
                sed -i 's/^SELINUX=.*$/SELINUX=permissive/' /etc/selinux/config
                echo -e " * SELinux set permissive -- proceeding with installation...\n"
                ;;
            [Nn]|[Nn][Oo]|"")
                sedisable="N"
                echo "N"
                echo -e " * Leaving SELinux enforcing.\n"
                ;;
            *)
                sedisable=""
                echo " * Invalid input, please try again!"
                ;;
        esac
    done
}
configureFirewall() {
    # GH-964 sibling: FOG used to have exactly one answer to a running
    # firewall -- offer to switch it off. Worse, that offer was skipped
    # entirely under -y (fwdisable was hardcoded to "N"), so an unattended
    # install left the firewall in whatever state the box happened to be in
    # and every FOG service silently unreachable. Neither half was a
    # decision anyone made on purpose; it is the same shape as the SELinux
    # problem, where the fix was "configure it" rather than "turn it off".
    #
    # So: configure by default, in BOTH the attended and the unattended
    # path, and keep disabling as an explicit choice rather than the only
    # one.
    #
    # Runs late, unlike the checkFirewall() it replaces, for two reasons
    # that both used to be broken:
    #   - .fogsettings is not sourced until after the old call site, so a
    #     remembered choice could not be honoured. An admin who said "leave
    #     my firewall alone" got re-asked, or under -y re-ignored, on every
    #     single upgrade.
    #   - the port set depends on what was actually installed ($bldhcp,
    #     $noTftpBuild, $httpproto, $installtype), none of which is settled
    #     at the old call site.
    local action="$fwconfigure"
    local backend="" fwrunning=0

    # Detect. firewalld and ufw are asked directly; bare iptables is inferred
    # from a ruleset that is not the empty default (3 chains, 5 header lines,
    # all policies ACCEPT == untouched).
    if command -v firewall-cmd >>$error_log 2>&1 && [[ "x$(firewall-cmd --state 2>&1)" == "xrunning" ]]; then
        backend="firewalld"
        fwrunning=1
    elif command -v ufw >>$error_log 2>&1 && ufw status 2>/dev/null | grep -qi "^Status: active"; then
        backend="ufw"
        fwrunning=1
    elif command -v iptables >>$error_log 2>&1; then
        local rulesnum=$(iptables -L -n 2>/dev/null | wc -l)
        local policy=$(iptables -L -n 2>/dev/null | grep "^Chain" | grep -v "ACCEPT" -c)
        if [[ $rulesnum -ne 8 || $policy -ne 0 ]]; then
            backend="iptables"
            fwrunning=1
        fi
    fi
    [[ $fwrunning -ne 1 ]] && return 0

    # Decide. A remembered choice wins, then -y, then ask.
    if [[ -z $action ]]; then
        if [[ -n $autoaccept ]]; then
            action="configure"
        else
            echo
            echo " * A local firewall ($backend) is running. FOG needs a number of"
            echo " * ports open to serve PXE clients, storage nodes and the web UI."
            echo
            echo " *   1) Open the ports FOG needs (recommended)"
            echo " *   2) Disable the firewall entirely"
            echo " *   3) Leave it alone -- I will configure it myself"
            echo
            while [[ -z $action ]]; do
                echo -n " * Which would you like? (1/2/3) "
                read -r fwanswer
                case $fwanswer in
                    1|"") action="configure" ;;
                    2) action="disable" ;;
                    3) action="skip" ;;
                    *) echo " * Invalid input, please try again!" ;;
                esac
            done
        fi
    fi
    # Remembered so the next upgrade does not re-ask, and more importantly so
    # an admin who said "leave it alone" is not overridden by a later -y run.
    fwconfigure="$action"

    case $action in
        skip)
            dots "Configuring firewall"
            echo "Skipped (by request)"
            echo " * FOG will not be reachable until these are open:"
            _firewallPortList | while read -r p d; do echo " *   $p  $d"; done
            return 0
            ;;
        disable)
            dots "Disabling firewall"
            ufw stop >/dev/null 2>&1
            ufw disable >/dev/null 2>&1
            local svc
            for svc in ufw firewalld iptables; do
                systemctl is-active --quiet $svc && systemctl stop $svc >/dev/null 2>&1 || true
                systemctl is-enabled --quiet $svc 2>/dev/null && systemctl disable $svc >/dev/null 2>&1 || true
            done
            errorStat 0
            return 0
            ;;
    esac

    dots "Configuring firewall ($backend)"
    case $backend in
        firewalld) _configureFirewalld ;;
        ufw)       _configureUfw ;;
        iptables)  _reportIptables; return 0 ;;
    esac
}
_firewallPortList() {
    # Emits "<port>/<proto> <description>", one per line, for exactly the
    # services this install actually stood up. Single source of truth: the
    # firewalld path, the ufw path, the iptables instructions and the
    # "here is what you still need to open" message all read this.
    echo "80/tcp HTTP (web UI, client check-in, iPXE boot)"
    [[ $httpproto == https ]] && echo "443/tcp HTTPS (web UI, client check-in)"
    [[ $noTftpBuild != 1 ]] && echo "69/udp TFTP (PXE boot)"
    echo "21/tcp FTP (image/snapin replication, node operations)"
    # Passive data. vsftpd is pinned to this range by configureFTP() for
    # exactly this reason -- see the comment there.
    echo "${ftppasvmin}-${ftppasvmax}/tcp FTP passive data"
    # Unconditional: configureNFS() runs on BOTH the full-server and the
    # storage-node path, and a storage node exists precisely to serve images
    # over NFS. $blexports only controls whether the installer overwrites an
    # existing exports file -- it does not mean NFS is absent, so gating on it
    # here would leave every "keep my exports" install unable to image.
    echo "2049/tcp NFS (image capture/deploy)"
    echo "111/tcp RPC portmapper (NFS)"
    echo "111/udp RPC portmapper (NFS)"
    # configureNFS() pins mountd here; without that pin this port would be
    # random per boot and could not be firewalled at all.
    echo "20048/tcp NFS mountd"
    echo "20048/udp NFS mountd"
    [[ $bldhcp -eq 1 ]] && echo "67/udp DHCP (FOG is your DHCP server)"
    # udpcast multicast. UDPCAST_STARTINGPORT is the base and each concurrent
    # session consumes two ports, so the window is base .. base + 2*sessions.
    echo "${mcastportmin}-${mcastportmax}/udp Multicast (udpcast)"
}
_configureFirewalld() {
    local failed=0 p d
    # Named services rather than bare ports wherever one exists: a firewalld
    # service definition carries its conntrack helper with it, which is what
    # makes TFTP work at all. TFTP replies come from an ephemeral source port,
    # so a bare "--add-port=69/udp" opens the request and drops every packet
    # of the actual transfer. Modern kernels no longer auto-assign helpers,
    # so this is not something the box does for us.
    local svc
    for svc in http tftp ftp nfs mountd rpc-bind dhcp; do
        case $svc in
            tftp)     [[ $noTftpBuild == 1 ]] && continue ;;
            dhcp)     [[ $bldhcp -ne 1 ]] && continue ;;
        esac
        firewall-cmd --permanent --add-service=$svc >>$error_log 2>&1 || failed=1
    done
    [[ $httpproto == https ]] && { firewall-cmd --permanent --add-service=https >>$error_log 2>&1 || failed=1; }
    # No named service for these two.
    firewall-cmd --permanent --add-port=${ftppasvmin}-${ftppasvmax}/tcp >>$error_log 2>&1 || failed=1
    firewall-cmd --permanent --add-port=${mcastportmin}-${mcastportmax}/udp >>$error_log 2>&1 || failed=1
    firewall-cmd --reload >>$error_log 2>&1 || failed=1
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * Could not apply all firewall rules. See $error_log."
        _firewallAdvice
        return 0
    fi
    errorStat 0
    _firewallSummary "$(firewall-cmd --get-default-zone 2>/dev/null)"
}
_configureUfw() {
    local failed=0 p d
    # ufw has no service definitions and no helper handling, so everything is
    # an explicit port. TFTP's data transfer relies on nf_conntrack_tftp being
    # loaded -- ufw's stock before.rules already accepts RELATED,ESTABLISHED,
    # so the helper is the only missing piece. Persisted, because a reboot
    # without it breaks PXE and nothing says why.
    if [[ $noTftpBuild != 1 ]]; then
        echo "nf_conntrack_tftp" > /etc/modules-load.d/fog-conntrack.conf 2>>$error_log
        modprobe nf_conntrack_tftp >>$error_log 2>&1 || true
    fi
    while read -r p d; do
        ufw allow "$p" >>$error_log 2>&1 || failed=1
    done < <(_firewallPortList)
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * Could not apply all firewall rules. See $error_log."
        _firewallAdvice
        return 0
    fi
    errorStat 0
    _firewallSummary "ufw"
}
_reportIptables() {
    # Deliberately not applied. Persisting iptables rules is distro-specific
    # (the iptables-save target differs per distro and per package), and
    # inserting into a ruleset FOG did not create risks either landing after
    # an existing REJECT -- doing nothing, silently -- or breaking rules that
    # were working. Printing exactly what to run is more honest than a
    # half-working attempt, and the docs carry the persistence step.
    echo "Skipped (raw iptables)"
    echo " * FOG will not configure a raw iptables ruleset automatically --"
    echo " *   rule ordering and persistence are too distro-specific to do"
    echo " *   safely against a ruleset FOG did not create."
    echo " * Run these, then persist them the way your distro expects:"
    local p d proto port
    while read -r p d; do
        proto="${p##*/}"
        port="${p%/*}"
        echo " *   iptables -I INPUT -p $proto --dport ${port/-/:} -j ACCEPT"
    done < <(_firewallPortList)
    if [[ $noTftpBuild != 1 ]]; then
        echo " * TFTP also needs the conntrack helper, or PXE transfers stall:"
        echo " *   modprobe nf_conntrack_tftp"
    fi
    echo " * Full walkthrough: https://docs.fogproject.org/en/latest/kb/how-tos/firewall/"
}
_firewallSummary() {
    local where="$1"
    echo " * Opened the following in '${where}':"
    local p d
    while read -r p d; do
        echo " *   ${p}  ${d}"
    done < <(_firewallPortList)
    _firewallAdvice
}
_firewallAdvice() {
    # Said every time, including on success. Opening these on the default zone
    # is the only thing that works when PXE clients and storage nodes sit on
    # networks the installer cannot know about -- but it IS wider than many
    # sites want, and an admin who is never told cannot narrow it.
    echo " * These are open on all interfaces the default zone covers. To"
    echo " *   restrict them to your imaging subnet, see:"
    echo " *   https://docs.fogproject.org/en/latest/kb/how-tos/firewall/"
    echo " * FOG does NOT open the database port. If you run remote storage"
    echo " *   nodes against this server's database, open 3306/tcp to those"
    echo " *   nodes specifically -- not to everything."
}
displayOSChoices() {
    blFirst=1
    while [[ -z $osid ]]; do
        if [[ $fogupdateloaded -eq 1 && $blFirst -eq 1 ]]; then
            blFirst=0
        else
            osid=$strSuggestedOS
            if [[ -z $autoaccept && ! -z $osid ]]; then
                echo "  What version of Linux would you like to run the installation for?"
                echo
                echo "          1) Redhat Based Linux (Redhat, Alma, Rocky, CentOS, Mageia)"
                echo "          2) Debian Based Linux (Debian, Ubuntu, Kubuntu, Edubuntu)"
                # Alpine is listed honestly as incomplete rather than as a peer
                # of the two above. Its package list now resolves, and MariaDB,
                # nginx, php-fpm and TFTP all have OpenRC handling, as do FOG's
                # own eight daemons -- packages/init.d/alpine ships them and
                # they are installed, started and stopped by direct
                # /etc/init.d invocation. What is missing is narrower but still
                # real: nothing runs rc-update for those eight, so they do not
                # survive a reboot, and vsftpd, DHCP and NFS fall through to
                # chkconfig/service arms that do not exist on Alpine. See #863.
                echo "          3) Alpine Linux (experimental, some services not wired for OpenRC)"
                echo "          4) Arch Based Linux (Arch, Manjaro)"
                echo
                echo -n "  Choice: [$strSuggestedOS] "
                read osid
                case $osid in
                    "")
                        osid=$strSuggestedOS
                        break
                        ;;
                    1|2|3|4)
                        break
                        ;;
                    *)
                        echo "  Invalid input, please try again."
                        osid=""
                        ;;
                esac
            fi
        fi
    done
    doOSSpecificIncludes
}
doOSSpecificIncludes() {
    echo
    case $osid in
        1)
            echo -e "\n\n  Starting Redhat based Installation\n\n"
            osname="Redhat"
            . ../lib/redhat/config.sh
            ;;
        2)
            echo -e "\n\n  Starting Debian based Installation\n\n"
            osname="Debian"
            . ../lib/ubuntu/config.sh
            ;;
        3)
            echo -e "\n\n  Starting Alpine Installation\n\n"
            osname="Alpine"
            . ../lib/alpine/config.sh
            systemctl="no"
            ;;
        4)
            # Arch is systemd, so unlike Alpine it rides the same service
            # handling as Redhat and Debian and needs no override here.
            echo -e "\n\n  Starting Arch based Installation\n\n"
            osname="Arch"
            . ../lib/arch/config.sh
            ;;
        *)
            echo -e "  Sorry, answer not recognized\n\n"
            sleep 2
            osid=""
            ;;
    esac
    currentdir=$(pwd)
    case $currentdir in
        *$webdirdest*|*$tftpdirdst*)
            echo "Please change installation directory."
            echo "Running from here will fail."
            echo "You are in $currentdir which is a folder that will"
            echo "be moved during installation."
            exit 1
            ;;
    esac
}
errorStat() {
    local status=$1
    local skipOk=$2
    if [[ $status != 0 ]]; then
        echo "Failed!"
        if [[ -z $exitFail ]]; then
            echo
            echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
            echo "!! The installer was not able to run all the way to the end as   !!"
            echo "!! something has caused it to fail. The following few lines are  !!"
            echo "!! from the error log file which might help us figure out what's !!"
            echo "!! wrong. Please add this information when reporting an error.   !!"
            echo "!! As well you might want to take a look at the full error log   !!"
            echo "!! in $error_log !!"
            echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
            echo
            tail -n 5 $error_log
            exit $status
        fi
    fi
    [[ -z $skipOk ]] && echo "OK"
}
stopInitScript() {
    for serviceItem in $serviceList; do
        dots "Stopping $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl is-active --quiet $serviceItem && systemctl stop $serviceItem >>$error_log 2>&1 || true
        else
            [[ ! -x $initdpath/$serviceItem ]] && echo "OK" && continue
            $initdpath/$serviceItem status >/dev/null 2>&1 && $initdpath/$serviceItem stop >>$error_log 2>&1
        fi
        echo "OK"
    done
}
startInitScript() {
    for serviceItem in $serviceList; do
        dots "Starting $serviceItem Service"
        if [[ $systemctl == yes ]]; then
            systemctl is-active --quiet $serviceItem && true || systemctl start $serviceItem >>$error_log 2>&1
        else
            [[ ! -x $initdpath/$serviceItem ]] && continue
            $initdpath/$serviceItem status >/dev/null 2>&1 || $initdpath/$serviceItem start >>$error_log 2>&1
        fi
        errorStat $?
    done
}
enableInitScript() {
    for serviceItem in $serviceList; do
        case $systemctl in
            yes)
                dots "Setting permissions on $serviceItem script"
                chmod 644 $initdpath/$serviceItem >>$error_log 2>&1
                errorStat $?
                dots "Enabling $serviceItem Service"
                systemctl is-enabled --quiet $serviceItem && true || systemctl enable $serviceItem >>$error_log 2>&1
                if [[ ! $? -eq 0 && $osid -eq 2 ]]; then
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 2 >>$error_log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 3 >>$error_log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 4 >>$error_log 2>&1
                    update-rc.d $(echo $serviceItem | sed -e 's/[.]service//g') enable 5 >>$error_log 2>&1
                fi
                ;;
            *)
                dots "Setting $serviceItem script executable"
                chmod +x $initdpath/$serviceItem >>$error_log 2>&1
                errorStat $?
                case $osid in
                    1)
                        dots "Enabling $serviceItem Service"
                        chkconfig $serviceItem on >>$error_log 2>&1
                        ;;
                    2)
                        dots "Enabling $serviceItem Service"
                        sysv-rc-conf $serviceItem off >>$error_log 2>&1
                        sysv-rc-conf $serviceItem on >>$error_log 2>&1
                        case $linuxReleaseName_lower in
                            *ubuntu*|*mint*)
                                /usr/lib/insserv/insserv -r $initdpath/$serviceItem >>$error_log 2>&1
                                /usr/lib/insserv/insserv -d $initdpath/$serviceItem >>$error_log 2>&1
                                ;;
                            *)
                                insserv -r $initdpath/$serviceItem >>$error_log 2>&1
                                insserv -d $initdpath/$serviceItem >>$error_log 2>&1
                                ;;
                        esac
                        ;;
                esac
                ;;
        esac
        errorStat $?
    done
}
installInitScript() {
    dots "Installing FOG System Scripts"
    cp -f $initdsrc/* $initdpath/ >>$error_log 2>&1
    local cpstat=$? unitfile
    # GH-850: the shipped unit and init scripts hard-code /opt/fog/service in
    # their ExecStart/DAEMON/command lines and used to be copied verbatim, so a
    # non-default $servicedst installed cleanly and then every daemon failed to
    # start on a path that did not exist. This was already wrong before the base
    # path became configurable -- $servicedst has always been settable on its own.
    #
    # Rewrite the installed copies, never the sources: cp -f above restores the
    # /opt/fog original on every run, so the substitution stays idempotent and a
    # later re-run with a different path cannot compound.
    if [[ ${servicedst%/} != /opt/fog/service ]]; then
        for unitfile in $initdsrc/*; do
            sed -i "s|/opt/fog/service|${servicedst%/}|g" \
                "$initdpath/$(basename $unitfile)" >>$error_log 2>&1
        done
    fi
    # Guarded: on Alpine and other non-systemd hosts systemctl does not exist,
    # and the old `cp && systemctl daemon-reload` chain made errorStat report
    # this step as Failed purely because the reload could not run.
    [[ $systemctl == yes ]] && systemctl daemon-reload >>$error_log 2>&1
    errorStat $cpstat
    echo
    echo
    echo " * Configuring FOG System Services"
    echo
    echo
    enableInitScript
}
configureMySql() {
    # ---------------------------------------------------------
    # External Unprivileged Database Implementation
    # ---------------------------------------------------------
    if [[ "${snmysqlexternal}" == "1" ]]; then
        dots "Verifying external database connection"

        # Test connection and ensure the database exists and is accessible.
        # The database name is $mysqldbname; this read $snmysqldb, which is
        # never assigned anywhere, so the statement was always `USE ;` -- a
        # syntax error. The check could only ever fail, which made every
        # snmysqlexternal=1 install exit 1 here, and the error below named an
        # empty database.
        mysql -h "${snmysqlhost}" -u "${snmysqluser}" -p"${snmysqlpass}" -e "USE ${mysqldbname};" >/dev/null 2>&1
        local externalok=$?
        # GH-685: an external master without TLS is refused outright by a modern
        # MariaDB client. See detectMysqlSslOption.
        if [[ $externalok -ne 0 ]] && detectMysqlSslOption -h "${snmysqlhost}" -u "${snmysqluser}" -p"${snmysqlpass}"; then
            mysql -h "${snmysqlhost}" -u "${snmysqluser}" -p"${snmysqlpass}" $mysqlsslopt -e "USE ${mysqldbname};" >/dev/null 2>&1
            externalok=$?
        fi

        if [[ $externalok -ne 0 ]]; then
            echo "Failed!"
            echo " * Error: Cannot connect to the external database '${mysqldbname}' at '${snmysqlhost}'."
            echo " * Please verify your credentials in $fogprogramdir/.fogsettings and ensure the DB exists."
            exit 1
        fi

        echo "OK"

        # Return early to skip local DB configuration, preserving existing logic
        return 0
    fi
    # ---------------------------------------------------------
    stopInitScript
    dots "Setting up and starting MySQL"
    # Resolve exactly one unit name.
    #
    # `grep -o` prints one line per match, and `systemctl list-unit-files` lists
    # the mysql/mysqld alias symlinks alongside the real unit -- so the fallback
    # yielded a three-line $dbservice (it does on a stock Fedora box). Unquoted,
    # that word-split into `systemctl enable|stop|start` as three arguments, two
    # of them alias names rather than the real unit. The fallback is the
    # fresh-install path: the primary lookup only sees units already running, and
    # RedHat-family packages do not auto-start the DB.
    dbunits=$(systemctl list-units | grep -o -e "mariadb\.service" -e "mysqld\.service" -e "mysql\.service" | tr -d '@')
    [[ -z $dbunits ]] && dbunits=$(systemctl list-unit-files | grep -v bad | grep -o -e "mariadb\.service" -e "mysqld\.service" -e "mysql\.service" | tr -d '@')
    # Preference is explicit because grep cannot express it -- `-e` order does not
    # rank matches, it just reports whichever appeared first in the input. Real
    # unit first, aliases after.
    dbservice=""
    for dbcandidate in mariadb.service mysqld.service mysql.service; do
        if grep -qFx "$dbcandidate" <<<"$dbunits"; then
            dbservice=$dbcandidate
            break
        fi
    done
    # Switchout dbservice for alpine
    [[ $osid -eq 3 ]] && dbservice=$(rc-service -l | grep mariadb | head -1)
    for mysqlconf in $(grep -rl '.*skip-networking' /etc | grep -v init.d); do
        sed -i '/.*skip-networking/ s/^#*/#/' -i $mysqlconf >>$error_log 2>&1
    done
    for mysqlconf in `grep -rl '.*bind-address.*=.*127.0.0.1' /etc | grep -v init.d`; do
        sed -e '/.*bind-address.*=.*127.0.0.1/ s/^#*/#/' -i $mysqlconf >>$error_log 2>&1
    done
    if [[ $systemctl == yes ]]; then
        # Arch leaves the data directory to the admin -- its mariadb package
        # runs no equivalent of Debian's or RedHat's post-install setup. Start
        # the service without doing this and it aborts with "Can't open and
        # lock privilege tables: Table 'mysql.db' doesn't exist". The 1.5 line
        # had this under osid 3, when 3 meant Arch; it was dropped rather than
        # renumbered when 3 became Alpine. mariadb-install-db is the current
        # name, mysql_install_db the compatibility alias for older releases.
        if [[ $osid -eq 4 && ! -f /var/lib/mysql/mysql/db.MAD && ! -f /var/lib/mysql/mysql/db.frm ]]; then
            dots "Initializing the MariaDB data directory"
            mkdir -p /var/lib/mysql >>$error_log 2>&1
            chown -R mysql:mysql /var/lib/mysql >>$error_log 2>&1
            if command -v mariadb-install-db >/dev/null 2>&1; then
                mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql >>$error_log 2>&1
            else
                mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql >>$error_log 2>&1
            fi
            errorStat $?
        fi
        systemctl is-enabled --quiet "$dbservice" || systemctl enable "$dbservice" >>$error_log 2>&1
        systemctl is-active --quiet "$dbservice" && systemctl stop "$dbservice" >>$error_log 2>&1
        systemctl start "$dbservice" >>$error_log 2>&1
    else
        case $osid in
            1)
                chkconfig mysqld on >>$error_log 2>&1
                service mysqld start >>$error_log 2>&1
                ;;
            2)
                sysv-rc-conf mysql on >>$error_log 2>&1
                service mysql start >>$error_log 2>&1
                ;;
            3)
                rc-update add mariadb default >>$error_log 2>&1
                service mariadb setup >>$error_log 2>&1
                service mariadb start >>$error_log 2>&1
                ;;
        esac
    fi
    errorStat $?
    dots "Testing connection to database"
    # if someone still has DB user root set in .fogsettings we want to change that
    [[ "x$snmysqluser" == "xroot" ]] && snmysqluser='fogmaster'
    [[ -z $snmysqlpass ]] && snmysqlpass=$(generatePassword 20)
    [[ -n $snmysqlhost ]] && host="--host=$snmysqlhost"
    sqloptionsroot="${host} --user=root"
    sqloptionsuser="${host} -s --user=${snmysqluser}"
    # GH-685: a TLS-insisting client breaks every statement below, not just the
    # first, so settle the question once and bake the answer into the shared
    # option strings. Only TCP connections negotiate TLS -- an empty
    # $snmysqlhost means the local socket, where the question cannot arise.
    if [[ -n $snmysqlhost ]] \
        && ! mysql $sqloptionsuser --password="${snmysqlpass}" --execute="quit" >/dev/null 2>&1 \
        && detectMysqlSslOption $sqloptionsuser --password="${snmysqlpass}"; then
        host="${host} ${mysqlsslopt}"
        sqloptionsroot="${sqloptionsroot} ${mysqlsslopt}"
        sqloptionsuser="${sqloptionsuser} ${mysqlsslopt}"
    fi
    mysqladmin $host ping >/dev/null 2>&1 || mysqladmin $host ping >/dev/null 2>&1 || mysqladmin $host ping >/dev/null 2>&1
    errorStat $?

    dots "Setting up MySQL user and database"
    mysql $sqloptionsroot --execute="quit" >/dev/null 2>&1
    connect_as_root=$?
    if [[ $connect_as_root -eq 0 ]]; then
        # Try to detect if we can login to the database as root without a password
        # as there are many legacy installs with empty root password and we want to
        # make things more secure. Since MariaDB 10.1 the authentication plugin
        # called unix_socket is used by default for the DB root account and we want
        # to check if that is the case here first. In case it is a root login with
        # empty or without password is also possible but unix_socket makes it way
        # more secure and if it's set to unix_socket we don't mess with it!
        # MariaDB 10.4 introduced a new table called mysql.global_priv to keep the
        # login information. While mysql.user still exists mysql.global_priv is now
        # in charge. So we need to check that first.
        mysqlrootauth=$(mysql $sqloptionsroot --database=mysql --execute="SELECT * FROM global_priv WHERE Host='localhost' AND User='root' AND Priv LIKE '%unix_socket%'" 2>/dev/null)
        [[ -z $mysqlrootauth ]] && mysqlrootauth=$(mysql $sqloptionsroot --database=mysql --execute="SELECT Host,User,plugin FROM user WHERE Host='localhost' AND User='root' AND plugin='unix_socket'" 2>/dev/null)
        if [[ -z $mysqlrootauth && -z $autoaccept ]]; then
            echo
            echo "   The installer detected a blank database *root* password. This"
            echo "   is very common on a new install or if you upgrade from any"
            echo "   version of FOG before 1.5.8. To improve overall security we ask"
            echo "   you to supply an appropriate database *root* password now."
            echo
            echo "   NOTICE: Make sure you choose a good password but also one"
            echo "   you can remember or use a password manager to store it."
            echo "   The installer won't store the given password in any place"
            echo "   and it will be lost right after the installer finishes!"
            echo
            echo -n "   Please enter a new database *root* password to be set: "
            read -rs snmysqlrootpass
            echo
            echo
            if [[ -z $snmysqlrootpass ]]; then
                snmysqlrootpass=$(generatePassword 20)
                echo
                echo "   We don't accept a blank database *root* password anymore and"
                echo "   will generate a password for you to use. Please make sure"
                echo "   you save the following password in an appropriate place as"
                echo "   the installer won't store it for you."
                echo
                echo "   Database root password: $snmysqlrootpass"
                echo
                echo "   Press [Enter] to procede..."
                read -rs procede
                echo
                echo
            fi
            # WARN: Since MariaDB 10.3 (maybe earlier) setting a password when auth plugin is
            # set to unix_socket will actually switch to auth plugin mysql_native_password
            # automatically which was not the case in MariaDB 10.1 and is causing trouble.
            # So instead of SET PASSWORD we now use mysqladmin as it does not alter the
            # MariaDB auth plugin used.
            mysqladmin $sqloptionsroot password "${snmysqlrootpass}" >>$error_log 2>&1
        fi
        snmysqlstoragepass=$(mysql -s $sqloptionsroot --password="${snmysqlrootpass}" --execute="SELECT settingValue FROM globalSettings WHERE settingKey LIKE '%FOG_STORAGENODE_MYSQLPASS%'" $mysqldbname 2>/dev/null | tail -1)
    else
        snmysqlstoragepass=$(mysql $sqloptionsuser --password="${snmysqlpass}" --execute="SELECT settingValue FROM globalSettings WHERE settingKey LIKE '%FOG_STORAGENODE_MYSQLPASS%'" $mysqldbname 2>/dev/null | tail -1)
    fi
    mysql $sqloptionsuser --password="${snmysqlpass}" --execute="quit" >/dev/null 2>&1
    connect_as_fogmaster=$?
    mysql ${host} -s --user=fogstorage --password="${snmysqlstoragepass}" --execute="quit" >/dev/null 2>&1
    connect_as_fogstorage=$?
    if [[ $connect_as_fogmaster -eq 0 && $connect_as_fogstorage -eq 0 ]]; then
        echo "Skipped"
        return
    fi

    # If we reach this point it's clear that this install is not setup with
    # unpriviledged DB users yet and we need to have root DB access now.
    if [[ $connect_as_root -ne 0 ]]; then
        echo
        echo "   To improve the overall security the installer will create an"
        echo "   unpriviledged database user account for FOG's database access."
        echo "   Please provide the database *root* user password. Be asured"
        echo "   that this password will only be used while the FOG installer"
        echo -n "   is running and won't be stored anywhere: "
        read -rs snmysqlrootpass
        echo
        echo
        mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >/dev/null 2>&1
        if [[ $? -ne 0 ]]; then
            echo "   Unable to connect to the database using the given password!"
            echo -n "   Try again: "
            read -rs snmysqlrootpass
            mysql $sqloptionsroot --password="${snmysqlrootpass}" --execute="quit" >/dev/null 2>&1
            if [[ $? -ne 0 ]]; then
                echo
                echo "   Failed! Terminating installer now."
                exit 1
            fi
        fi
    fi

    snmysqlstoragepass=$(mysql -s $sqloptionsroot --password="${snmysqlrootpass}" --execute="SELECT settingValue FROM globalSettings WHERE settingKey LIKE '%FOG_STORAGENODE_MYSQLPASS%'" $mysqldbname 2>/dev/null | tail -1)
    # generate a new fogstorage password if it doesn't exist yet or if it's old style fs0123456789
    if [[ -z $snmysqlstoragepass ]]; then
        snmysqlstoragepass=$(generatePassword 20)
    elif [[ -n $(echo $snmysqlstoragepass | grep "^fs[0-9][0-9]*$") ]]; then
        snmysqlstoragepass=$(generatePassword 20)
        echo
        echo "   The current *fogstorage* database password does not meet high"
        echo "   security standards. We will generate a new password and update"
        echo "   all the settings on this FOG server for you. Please take note"
        echo "   of the following credentials that you need to manually update"
        echo "   on all your storage nodes' $fogprogramdir/.fogsettings configuration"
        echo "   files and re-run (!) the FOG installer:"
        echo "   snmysqluser='fogstorage'"
        echo "   snmysqlpass='${snmysqlstoragepass}'"
        echo
        if [[ -z $autoaccept ]]; then
            echo "   Press [Enter] to proceed after you noted down the credentials."
            read
        fi
    fi
    [[ ! -d ../tmp/ ]] && mkdir -p ../tmp/ >/dev/null 2>&1
    cat >../tmp/fog-db-and-user-setup.sql <<EOF
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ANSI' ;
DELETE FROM mysql.user WHERE User='' ;
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1') ;
DROP DATABASE IF EXISTS test ;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%' ;
CREATE DATABASE IF NOT EXISTS $mysqldbname ;
USE $mysqldbname ;
DROP PROCEDURE IF EXISTS $mysqldbname.create_user_if_not_exists ;
DELIMITER $$
CREATE PROCEDURE $mysqldbname.create_user_if_not_exists()
BEGIN
  DECLARE masteruser BIGINT DEFAULT 0 ;
  DECLARE storageuser BIGINT DEFAULT 0 ;

  SELECT COUNT(*) INTO masteruser FROM mysql.user
    WHERE User = '${snmysqluser}' and  Host = '${snmysqlhost}' ;
  IF masteruser > 0 THEN
    DROP USER '${snmysqluser}'@'${snmysqlhost}';
  END IF ;
  CREATE USER '${snmysqluser}'@'${snmysqlhost}' IDENTIFIED BY '${snmysqlpass}' ;
  GRANT ALL PRIVILEGES ON $mysqldbname.* TO '${snmysqluser}'@'${snmysqlhost}' ;

  SELECT COUNT(*) INTO storageuser FROM mysql.user
    WHERE User = 'fogstorage' and  Host = '%' ;
  IF storageuser > 0 THEN
    DROP USER 'fogstorage'@'%';
  END IF ;
  CREATE USER 'fogstorage'@'%' IDENTIFIED BY '${snmysqlstoragepass}' ;
END ;$$
DELIMITER ;
CALL $mysqldbname.create_user_if_not_exists() ;
DROP PROCEDURE IF EXISTS $mysqldbname.create_user_if_not_exists ;
FLUSH PRIVILEGES ;
SET SQL_MODE=@OLD_SQL_MODE ;
EOF
    mysql $sqloptionsroot --password="${snmysqlrootpass}" <../tmp/fog-db-and-user-setup.sql >>$error_log 2>&1
    errorStat $?
}
configureFOGService() {
    [[ ! -d $servicedst ]] && mkdir -p $servicedst >>$error_log 2>&1
    [[ ! -d $servicedst/etc ]] && mkdir -p $servicedst/etc >>$error_log 2>&1
    echo "<?php define('WEBROOT','${webdirdest}');" > $servicedst/etc/config.php
    startInitScript
}
configureNFS() {
    dots "Setting up NFS configuration file"
    if [[ -f "/etc/nfs.conf" ]]; then
        # Fix all set port=20048 back to default values
        sed -i '/^port=20048/ {s/^port=20048/# port=0/}' /etc/nfs.conf >>$error_log 2>&1
    fi
    # set port in nfs.conf.d directory
    if [[ -f "/etc/nfs.conf" && ! -d "/etc/nfs.conf.d/" ]]; then
        mkdir /etc/nfs.conf.d
    elif [[ -f "/usr/etc/nfs.conf" && ! -d "/usr/etc/nfs.conf.d/" ]]; then
        mkdir /usr/etc/nfs.conf.d
    fi
    if [[ -f "/etc/nfs.conf" && ! -f "/etc/nfs.conf.d/fog-nfs.conf" ]]; then
        cat > /etc/nfs.conf.d/fog-nfs.conf <<EOF
[mountd]
port=20048
EOF
    elif [[ -f "/usr/etc/nfs.conf" && ! -f "/usr/etc/nfs.conf.d/fog-nfs.conf" ]]; then
        cat > /usr/etc/nfs.conf.d/fog-nfs.conf <<EOF
[mountd]
port=20048
EOF
    fi
    errorStat $?
    dots "Setting up exports file"
    if [[ $blexports != 1 ]]; then
        echo "Skipped"
        if [[ -f "$nfsconfig" ]] && grep -q "no_root_squash" "$nfsconfig"; then
            echo
            echo "  ** WARNING: ${nfsconfig} still exports with no_root_squash."
            echo "  ** Captures land as root, so moving the image out of"
            echo "  ** ${storageLocation}/dev fails with '550 Rename failed'."
            echo "  ** Replace the ${storageLocation}/dev export options with:"
            echo "  **   all_squash,anonuid=$(id -u $username),anongid=$(id -g $username)"
            echo
        fi
    else
        mv -fv "${nfsconfig}" "${nfsconfig}.${timestamp}" >>$error_log 2>&1
        userId=$(id -u $username)
        groupId=$(id -g $username)
        echo -e "$storageLocation *(ro,sync,no_wdelay,subtree_check,insecure_locks,all_squash,anonuid=${userId},anongid=${groupId},fsid=0)\n$storageLocation/dev *(rw,async,no_wdelay,subtree_check,all_squash,anonuid=${userId},anongid=${groupId},fsid=1)" > "$nfsconfig"
        diffconfig "${nfsconfig}"
        errorStat $?
        dots "Setting up and starting RPCBind"
        if [[ $systemctl == yes ]]; then
            systemctl is-enabled --quiet rpcbind && true || systemctl enable rpcbind.service >>$error_log 2>&1
            systemctl is-active --quiet rpcbind && systemctl stop rpcbind.service >>$error_log 2>&1 || true
            systemctl is-active --quiet rpcbind && true || systemctl start rpcbind.service >>$error_log 2>&1
            systemctl status rpcbind.service >>$error_log 2>&1
        else
            case $osid in
                1)
                    chkconfig rpcbind on >>$error_log 2>&1
                    $initdpath/rpcbind stop >>$error_log 2>&1
                    $initdpath/rpcbind start >>$error_log 2>&1
                    $initdpath/rpcbind status >>$error_log 2>&1
                    ;;
            esac
        fi
        errorStat $?
        dots "Setting up and starting NFS Server"
        for nfsItem in $nfsservice; do
            if [[ $systemctl == yes ]]; then
                systemctl is-enabled --quiet $nfsItem && true || systemctl enable $nfsItem >>$error_log 2>&1
                systemctl is-active --quiet $nfsItem && systemctl stop $nfsItem >>$error_log 2>&1 || true
                systemctl is-active --quiet $nfsItem && true || systemctl start $nfsItem >>$error_log 2>&1
                systemctl status $nfsItem >>$error_log 2>&1
            else
                case $osid in
                    1)
                        chkconfig $nfsItem on >>$error_log 2>&1
                        $initdpath/$nfsItem stop >>$error_log 2>&1
                        $initdpath/$nfsItem start >>$error_log 2>&1
                        $initdpath/$nfsItem status >>$error_log 2>&1
                        ;;
                    2)
                        sysv-rc-conf $nfsItem on >>$error_log 2>&1
                        $initdpath/nfs-kernel-server stop >>$error_log 2>&1
                        $initdpath/nfs-kernel-server start >>$error_log 2>&1
                        ;;
                esac
            fi
            [[ $? -eq 0 ]] && break
        done
        errorStat $?
    fi
}
configureSnapins() {
    dots "Setting up FOG Snapins"
    mkdir -p $snapindir >>$error_log 2>&1
    if [[ -d $snapindir ]]; then
        chmod -R 775 $snapindir
        chown -R $username:$apacheuser $snapindir
    fi
    errorStat $?
    # GH-964: same usr_t problem as the cache directory, and here the write is
    # the whole point -- uploading a snapin through the web UI is a write into
    # this directory by httpd_t. Read-only labels would let the list render and
    # fail only on upload.
    #
    # fog_share_t rather than httpd_sys_rw_content_t because snapins replicate
    # between storage nodes over FTP (FOGService::replicateItems runs lftp), so
    # the RECEIVING node's vsftpd -- ftpd_t -- writes here too. ftpd_t gets
    # nothing at all on httpd_sys_rw_content_t unless ftpd_full_access is on,
    # and that boolean grants ftpd_t every file type on the box. See
    # packages/selinux/fog.te.
    setSELinuxContext "$snapindir" fog_share_t
}
configureUsers() {
    userexists=0
    [[ -z $username || "x$username" == "xfog" ]] && username='fogproject'
    dots "Setting up $username user"
    getent passwd $username > /dev/null
    if [[ $? -eq 0 ]]; then
        if [[ ! -f "$fogprogramdir/.fogsettings" && ! -x /home/$username/warnfogaccount.sh ]]; then
            echo "Already exists"
            echo
            echo "The account \"$username\" already exists but this seems to be a"
            echo "fresh install. We highly recommend to NOT create this account"
            echo "as it is supposed to be a system account. It is not meant to be"
            echo "used to login and work on the server!"
            echo
            echo "Please remove the account \"$username\" manually before running"
            echo "the installer again. Run: userdel $username"
            echo
            exit 1
        fi
        echo "Skipped"
    else
        # Debian's adduser and RedHat's (a symlink to useradd) both take these
        # long options, so they keep the path they always had. Two platforms
        # cannot: Arch ships no adduser whatsoever, and Alpine's is the busybox
        # applet, which has no --system at all -- so this call failed outright
        # on both. Fall through to useradd, which shadow provides everywhere
        # (it is in Alpine's package list for exactly this reason). --home-dir
        # does not create the directory the way adduser would, but the mkdir
        # below already did that unconditionally.
        if command -v adduser >/dev/null 2>&1 && adduser --help 2>&1 | grep -q -- '--system'; then
            adduser --system --shell /bin/bash --home /home/${username} ${username} >>$error_log 2>&1
        else
            useradd --system --shell /bin/bash --home-dir /home/${username} ${username} >>$error_log 2>&1
        fi
        retVal=$?
        [[ $retVal -eq 0 ]] && groupadd -f --system ${username} >>$error_log 2>&1 || errorStat $?
        retVal=$?
        [[ $retVal -eq 0 ]] && usermod -g ${username} -G ${username} ${username} >>$error_log 2>&1 || errorStat $?
        retVal=$?
        [[ $retVal -eq 0 ]] && mkdir -p /home/${username} >>$error_log 2>&1 || errorStat $?
        retVal=$?
        [[ $retVal -eq 0 ]] && touch /home/${username}/.bashrc >>$error_log 2>&1 || errorStat $?
        retVal=$?
        [[ $retVal -eq 0 ]] && chown $username:$username /home/${username} >>$error_log 2>&1 || errorStat $?
        errorStat $?
    fi
    dots "Locking $username as a system account"
    if [[ $osid -ne 3 ]]; then
        chsh -s /bin/bash $username >>$error_log 2>&1
    else
        sed -i -e "s|^\(${username}.*:\)[^:]*$|\1/bin/bash|g" /etc/passwd >>$error_log 2>&1
    fi
    textmessage="You seem to be using the '$username' system account to logon and work \non your FOG Server system.\n\nIt's NOT recommended to use this account! Please create a new\naccount for administrative tasks.\n\nIf you re-run the installer it would reset the '$username' account\npassword and therefore lock you out of the system!\n\nTake care,\nyour FOGProject team"
    grep -q "#exit 1" /home/$username/.bashrc >/dev/null 2>&1 || cat >>/home/$username/.bashrc <<EOF
echo -e "$textmessage"
#exit 1
EOF
    mkdir -p /home/$username/.config/autostart/
    cat >/home/$username/.config/autostart/warnfogaccount.desktop <<EOF
[Desktop Entry]
Type=Application
Name=Warn users to not use the $username account
Exec=/home/$username/warnfogaccount.sh
Comment=Warn users who use the $username system account to log on
EOF
    chown -R $username:$username /home/$username/.config/
    cat >/home/$username/warnfogaccount.sh <<EOF
#!/bin/bash
title="FOG System Account"
text="$textmessage"
z=\$(which zenity)
x=\$(which xmessage)
n=\$(which notify-send)
if [[ -x "\$z" ]]; then
    \$z --error --width=480 --text="\$text" --title="\$title"
elif [[ -x "\$x" ]]; then
    echo -e "\$text" | \$x -center -file -
else
    \$n -u critical "\$title" "\$(echo \$text | sed -e 's/ \\n/ /g')"
fi
EOF
    chmod 755 /home/$username/warnfogaccount.sh
    chown $username:$username /home/$username/warnfogaccount.sh
    errorStat $?
    dots "Setting up $username password"
    if [[ -z $password ]]; then
        # if we don't have a password from .fogsettings we check config.class.php as well
        if [[ -r $webdirdest/lib/fog/config.class.php ]]; then
            # extract password from old style config
            password=$(awk -F '"' -e '/TFTP_FTP_PASSWORD/,/);/{print $2}' $webdirdest/lib/fog/config.class.php | grep -v "^$")
            # if that didn't get us the password we try again new style
            [[ -z $password ]] && password=$(awk -F "'" -e '/TFTP_FTP_PASSWORD/{print $4}' $webdirdest/lib/fog/config.class.php)
        fi
    fi
    checkPasswordChars "$password"
    cnt=0
    ret=999
    while [[ $ret -ne 0 && $cnt -lt 10  ]]; do
        [[ -z $password || $ret -ne 999 ]] && password=$(generatePassword 20)
        echo -e "$password\n$password" | passwd $username >>$error_log 2>&1
        ret=$?
        let cnt+=1
    done
    errorStat $ret
    unset cnt
    unset ret
}
installUtilities() {
    dots "Installing FOG utilities"
    # GH-314: the utility scripts only ever existed inside the git checkout you
    # happened to install from, so there was no stable path to call them by --
    # FOGBackup in particular. Delete that checkout, move it, or clone a second
    # one and any cron or runbook referencing it breaks. reporting/report.sh
    # already got copied to $fogprogramdir; this does the same for the rest.
    #
    # lib/ comes along because the utils source lib/common/utils.sh, which
    # sources lib/common/functions.sh. Keeping both trees in their original
    # relative positions is what makes that sourcing keep working -- see the
    # BASH_SOURCE resolution at the top of those scripts.
    #
    # Removed first rather than copied over: these are installer-owned trees, so
    # a file dropped from a later release should not survive as a stale copy.
    local st=0
    rm -rf "$fogprogramdir/utils" "$fogprogramdir/lib" >>$error_log 2>&1
    mkdir -p "$fogprogramdir/utils" "$fogprogramdir/lib" >>$error_log 2>&1 || st=1
    cp -a "$workingdir/../utils/." "$fogprogramdir/utils/" >>$error_log 2>&1 || st=1
    cp -a "$workingdir/../lib/." "$fogprogramdir/lib/" >>$error_log 2>&1 || st=1
    find "$fogprogramdir/utils" "$fogprogramdir/lib" -type f -name '*.sh' \
        -exec chmod 755 {} \; >>$error_log 2>&1 || st=1
    errorStat $st
}
linkOptFogDir() {
    # GH-850: guard on `! -e` rather than the old `! -h`, and go through
    # linkIfAbsent. `! -h` is false for a *dangling* symlink, so a stale
    # /var/log/fog was never repaired; and where /var/log/fog already existed as
    # a real directory the bare `ln -s` did not fail, it created
    # /var/log/fog/log inside it. `! -e` is true for a dangling link (it follows
    # the link) and false for a real directory, which is what we want in both.
    if [[ ! -e /var/log/fog ]]; then
        dots "Linking FOG Logs to Linux Logs"
        linkIfAbsent "$servicelogs" /var/log/fog
        errorStat $?
    fi
    # GH-850: /etc/fog is a real directory now, so it can hold fog.conf -- the
    # pointer that tells the next installer run where this install lives. It
    # used to be a symlink to $servicedst/etc, which would have put that pointer
    # inside the very tree it exists to locate.
    #
    # Converting it is functionally inert: nothing in FOG reads through
    # /etc/fog. All eight daemons resolve the service config relative to their
    # own location (`dirname(realpath(__FILE__)).'/../etc/config.php'`), never
    # via /etc. The symlink was only ever an admin convenience, so config.php is
    # re-linked inside the new directory to keep the path admins know working.
    if [[ -L /etc/fog ]]; then
        dots "Converting /etc/fog to a real directory"
        rm -f /etc/fog >>$error_log 2>&1
        mkdir -p /etc/fog >>$error_log 2>&1
        errorStat $?
    fi
    [[ ! -d /etc/fog ]] && mkdir -p /etc/fog >>$error_log 2>&1
    dots "Linking FOG Service config /etc"
    linkIfAbsent "$servicedst/etc/config.php" /etc/fog/config.php
    errorStat $?
    dots "Recording FOG base path"
    # Sourced by the installer before lib/common/config.sh on the next run, so a
    # non-default base path survives an upgrade without having to be re-supplied
    # on the command line. Deliberately holds fogprogramdir and nothing else --
    # every other setting stays in $fogprogramdir/.fogsettings.
    {
        echo "## Written by the FOG installer -- DO NOT EDIT."
        echo "## Records where this FOG install lives so the installer can find"
        echo "## it again. To move it, re-run the installer with --fogprogramdir."
        echo "fogprogramdir='${fogprogramdir%/}'"
    } > /etc/fog/fog.conf 2>>$error_log
    errorStat $?
    local element=$webserver
    chmod -R 755 /var/log/$element >>$error_log 2>&1
    for i in $(find /var/log/ -type d -name 'php*fpm*' 2>>$error_log); do
        chmod -R 755 $i >>$error_log 2>&1
    done
    for i in $(find /var/log/ -type f -name 'php*fpm*' 2>>$error_log); do
        chmod -R 755 $i >>$error_log 2>&1
    done
}
configureStorage() {
    dots "Setting up storage"
    [[ ! -d $storageLocation ]] && mkdir $storageLocation >>$error_log 2>&1
    [[ ! -f $storageLocation/.mntcheck ]] && touch $storageLocation/.mntcheck >>$error_log 2>&1
    [[ ! -d $storageLocation/postdownloadscripts ]] && mkdir $storageLocation/postdownloadscripts >>$error_log 2>&1
    if [[ ! -f $storageLocation/postdownloadscripts/fog.postdownload ]]; then
        echo "#!/bin/bash" >"$storageLocation/postdownloadscripts/fog.postdownload"
        echo "## This file serves as a starting point to call your custom postimaging scripts." >>"$storageLocation/postdownloadscripts/fog.postdownload"
        echo "## <SCRIPTNAME> should be changed to the script you're planning to use." >>"$storageLocation/postdownloadscripts/fog.postdownload"
        echo "## Syntax of post download scripts are" >>"$storageLocation/postdownloadscripts/fog.postdownload"
        echo "#. \${postdownpath}<SCRIPTNAME>" >> "$storageLocation/postdownloadscripts/fog.postdownload"
    fi
    [[ ! -d $storageLocationCapture ]] && mkdir $storageLocationCapture >>$error_log 2>&1
    [[ ! -f $storageLocationCapture/.mntcheck ]] && touch $storageLocationCapture/.mntcheck >>$error_log 2>&1
    [[ ! -d $storageLocationCapture/postinitscripts ]] && mkdir $storageLocationCapture/postinitscripts >>$error_log 2>&1
    if [[ ! -f $storageLocationCapture/postinitscripts/fog.postinit ]]; then
        echo "#!/bin/bash" >"$storageLocationCapture/postinitscripts/fog.postinit"
        echo "## This file serves as a starting point to call your custom pre-imaging/post init loading scripts." >>"$storageLocationCapture/postinitscripts/fog.postinit"
        echo "## <SCRIPTNAME> should be changed to the script you're planning to use." >>"$storageLocationCapture/postinitscripts/fog.postinit"
        echo "## Syntax of post init scripts are" >>"$storageLocationCapture/postinitscripts/fog.postinit"
        echo "#. \${postinitpath}<SCRIPTNAME>" >>"$storageLocationCapture/postinitscripts/fog.postinit"
    else
        (head -1 "$storageLocationCapture/postinitscripts/fog.postinit" | grep -q '^#!/bin/bash') || sed -i '1i#!/bin/bash' "$storageLocationCapture/postinitscripts/fog.postinit" >/dev/null 2>&1
    fi
    chmod -R 775 $storageLocation $storageLocationCapture >>$error_log 2>&1
    chown -R $(id -u $username):$(id -g $username) $storageLocation $storageLocationCapture >>$error_log 2>&1
    errorStat $?
    # GH-964: on the lab this directory was unlabeled_t, which is worse than
    # merely wrong -- httpd_t gets getattr/open/search on it and nothing else,
    # so the web UI could not list an image directory, and unlabeled_t masks
    # whatever the real denial would have been.
    #
    # fog_share_t, FOG's own type, rather than a stock one. Two confined
    # daemons need this directory read-write: the web tier (httpd_t) and
    # vsftpd (ftpd_t), the latter as the receiving side of a replication run.
    # No stock type gives both without a blanket grant --
    # httpd_sys_rw_content_t needs ftpd_full_access (every file type on the
    # box) and public_content_rw_t needs the global *_anon_write booleans.
    # See packages/selinux/fog.te for the full reasoning.
    #
    # NFS is deliberately NOT a consideration, and needs no rule: the data
    # path is in-kernel as kernel_t, which has unconditional read+write on the
    # `file_type` attribute that fog_share_t carries. The lab's audit log
    # agrees -- zero nfsd_t denials across months of imaging.
    #
    # $storageLocationCapture is labelled separately because .fogsettings can
    # relocate it out from under $storageLocation, in which case the recursive
    # fcontext registered for $storageLocation would not cover it.
    setSELinuxContext "$storageLocation" fog_share_t
    setSELinuxContext "$storageLocationCapture" fog_share_t
}
clearScreen() {
    clear
}
writeUpdateFile() {
    sslpath="${sslpath//\/$}"
    tmpDte=$(date +%c)
    [[ -z $copybackold || $copybackold -lt 1 ]] && copybackold=0

    # GH-632: this assumed $fogprogramdir already existed. On a pristine system
    # it does not -- nothing creates it until `mkdir -p $fogprogramdir/cache`
    # much later -- so writing here before that point silently produced NOTHING:
    # the redirection fails, the function returns 0 anyway, and the caller has
    # no way to tell. That only ever mattered because the sole call site was at
    # the very end of the install; it is a trap for any earlier one.
    mkdir -p "$fogprogramdir" >>$error_log 2>&1

    # Managed keys, in the canonical order a freshly written file uses. This one
    # list drives both the fresh write and the in-place upgrade merge, so the two
    # can never drift apart again.
    local -a managedKeys=(
        ipaddress ipaddresses copybackold interface submask hostname routeraddress plainrouter
        dnsaddress username password osid osname dodhcp bldhcp dhcpd dhcpengine
        blexports installtype snmysqlexternal snmysqluser snmysqlpass snmysqlhost
        mysqldbname installlang storageLocation fogupdateloaded docroot webroot
        caCreated httpproto startrange endrange packages noTftpBuild tftpAdvOpts
        sslpath backupPath php_ver sslprivkey sslcakey sslcapem sslcachain
        externalca extcacert extcakey extcaroot sslcsr sslpubcert sendreports webserver
        # GH-850: recorded so `grep fogprogramdir .fogsettings` answers "where
        # does this install live" -- but it is a RECORD, not a control. The
        # installer re-asserts the value it resolved from /etc/fog/fog.conf or
        # --fogprogramdir after sourcing this file, because .fogsettings itself
        # lives at $fogprogramdir/.fogsettings and so cannot be what locates it.
        fogprogramdir
        # Persisted so every later upgrade re-signs the kernels without the
        # admin passing the flags again -- an upgrade that silently replaced
        # signed kernels with unsigned ones is the main way this setup breaks.
        # secureboot carries --no-secure-boot forward for the same reason: an
        # opt-out that reverted on the next upgrade would hand the admin back a
        # root-only key and a sudoers rule they had deliberately declined.
        secureBootKey secureBootCert secureboot
        # GH-964 sibling: what the admin chose for the local firewall
        # (configure/disable/skip). Persisted for the same reason the Secure
        # Boot keys are -- so an upgrade does not quietly undo a deliberate
        # decision. Without it, an admin who answered "leave it alone" would
        # be re-asked every upgrade, and under -y would simply be overridden.
        fwconfigure
        # fog_git_path is a RECORD like fogprogramdir (GH-850), not a control --
        # installfog.sh re-asserts the value it actually resolved after sourcing
        # this file (see resolvedfoggitpath), so a stale path left over from a
        # moved/re-cloned checkout does not silently point bin/updatefog.sh at a
        # directory that may no longer exist.
        #
        # fog_update_channel IS a genuine persisted preference -- which channel
        # to track -- closer to secureboot/fwconfigure above than to
        # fogprogramdir: an admin's choice of stable/staging/dev must carry forward
        # on every upgrade, not just on the run it was made.
        fog_git_path fog_update_channel
        # A genuine persisted preference like fog_update_channel above, not a
        # RECORD like fogprogramdir/fog_git_path -- an admin's extra vhost/cert
        # name(s) must carry forward on every upgrade, not just the run they
        # were set on.
        extraServerNames
        # Set by bin/setupacme.sh once it installs an ACME-issued leaf. Tells
        # createSSLCA() below to leave that leaf alone on every later run --
        # without this, the leaf gets silently regenerated from the ORIGINAL
        # CSR (stale public key) while the private key on disk is the ACME
        # key, producing a cert/key mismatch that stops the web server.
        acmeLeaf
        # How many prior kernel/init generations backupPreservedCustomizations()
        # keeps under customizations/kernel-backups. A genuine persisted
        # preference like fog_update_channel, not a record: an admin who chose
        # deeper history must keep it across every future upgrade, or the
        # generations they were relying on get evicted by the next run.
        kernelBackupGenerations
    )
    # Keys written by older installers that must be stripped on upgrade.
    local -a deprecatedKeys=( storageftpuser storageftppass bootfilename notpxedefaultfile php_verAdds )

    # Emit one "key='value'" line, single-quote-safe for any value (embedded
    # single quotes become '\''). fogupdateloaded stays unquoted+numeric to
    # match the historical file format.
    settingLine() {
        local key="$1" val
        case "$key" in
            fogupdateloaded) printf 'fogupdateloaded=%s\n' "${fogupdateloaded:-1}"; return ;;
            *) val="${!key}" ;;
        esac
        printf "%s='%s'\n" "$key" "${val//\'/\'\\\'\'}"
    }

    local key
    if [[ -f $fogprogramdir/.fogsettings ]] && \
        { grep -q "^## Start of FOG Settings" "$fogprogramdir/.fogsettings" || grep -q "^## Version:" "$fogprogramdir/.fogsettings"; }; then
        # Existing, valid file: update managed keys in place, strip deprecated
        # keys, refresh the version header, and leave every other line untouched.
        local managedLines depList
        managedLines=$(for key in "${managedKeys[@]}"; do settingLine "$key"; done)
        depList=$(printf '%s\n' "${deprecatedKeys[@]}")
        mline="$managedLines" deps="$depList" ver="$version" awk '
            BEGIN {
                n = split(ENVIRON["mline"], ml, "\n")
                for (i = 1; i <= n; i++) {
                    eq = index(ml[i], "=")
                    ORDER[i] = substr(ml[i], 1, eq - 1)
                    MAP[ORDER[i]] = ml[i]
                }
                m = split(ENVIRON["deps"], dl, "\n")
                for (i = 1; i <= m; i++) if (dl[i] != "") DEP[dl[i]] = 1
                verline = "## Version: " ENVIRON["ver"]
            }
            {
                if ($0 ~ /^## Version:/) { print verline; seenver = 1; next }
                eq = index($0, "=")
                key = (eq ? substr($0, 1, eq - 1) : "")
                if (key != "" && (key in DEP)) next
                if (key != "" && (key in MAP)) { print MAP[key]; SEEN[key] = 1; next }
                print
            }
            END {
                if (!seenver) print verline
                for (i = 1; i <= n; i++) if (!(ORDER[i] in SEEN)) print MAP[ORDER[i]]
            }
        ' "$fogprogramdir/.fogsettings" > "$fogprogramdir/.fogsettings.tmp" \
            && cat "$fogprogramdir/.fogsettings.tmp" > "$fogprogramdir/.fogsettings" \
            && rm -f "$fogprogramdir/.fogsettings.tmp"
    else
        # No file, or a file with no recognizable header: (re)write from scratch.
        # Fresh files default an empty snmysqlexternal to 0 (historical behavior;
        # the in-place upgrade path leaves it as-is).
        snmysqlexternal="${snmysqlexternal:-0}"
        {
            echo "## Start of FOG Settings"
            echo "## Created by the FOG Installer"
            echo "## Find more information about this file in the FOG Project wiki:"
            echo "##     https://wiki.fogproject.org/wiki/index.php?title=.fogsettings"
            echo "## Version: $version"
            echo "## Install time: $tmpDte"
            for key in "${managedKeys[@]}"; do settingLine "$key"; done
            echo "## End of FOG Settings"
        } > "$fogprogramdir/.fogsettings"
    fi
}
displayBanner() {
    echo
    echo "  =================================="
    echo "  ===        ====    =====      ===="
    echo "  ===  =========  ==  ===   ==   ==="
    echo "  ===  ========  ====  ==  ====  ==="
    echo "  ===  ========  ====  ==  ========="
    echo "  ===      ====  ====  ==  ========="
    echo "  ===  ========  ====  ==  ===   ==="
    echo "  ===  ========  ====  ==  ====  ==="
    echo "  ===  =========  ==  ===   ==   ==="
    echo "  ===  ==========    =====      ===="
    echo "  =================================="
    echo "  ===== Free Opensource Ghost ======"
    echo "  =================================="
    echo "  ============ Credits ============="
    echo "  = https://fogproject.org/Credits ="
    echo "  =================================="
    echo "  == Released under GPL Version 3 =="
    echo "  =================================="
    echo
}
# Returns 0 if the certificate at $1 is a CA certificate (basicConstraints CA:TRUE)
isCACert() {
    local crt="$1"
    if openssl x509 -in "$crt" -noout -ext basicConstraints >/dev/null 2>&1; then
        openssl x509 -in "$crt" -noout -ext basicConstraints 2>/dev/null | grep -qi "CA:TRUE"
    else
        # Older OpenSSL without the -ext option (e.g. some Alpine builds)
        openssl x509 -in "$crt" -noout -text 2>/dev/null | grep -A1 -i "Basic Constraints" | grep -qi "CA:TRUE"
    fi
}
# Validate the admin-supplied external/intermediate CA and import it into FOG's CA
# directory. On success sets sslcakey, sslcapem and sslcachain. Hard-fails the
# install on any validation error.
validateExternalCA() {
    local f haveSource=1
    for f in "$extcacert" "$extcakey" "$extcaroot"; do
        [[ -z $f || ! -r $f ]] && haveSource=0
    done
    if [[ $haveSource -eq 0 ]]; then
        # No readable source files this run; reuse a previously imported CA if present
        if [[ -e $sslpath/CA/.fogCA.pem && -e $sslpath/CA/.fogCA.key && -e $sslpath/CA/.fogCAchain.pem ]]; then
            sslcakey="$sslpath/CA/.fogCA.key"
            sslcapem="$sslpath/CA/.fogCA.pem"
            sslcachain="$sslpath/CA/.fogCAchain.pem"
            return 0
        fi
        echo "  External CA is enabled but a required file is missing or unreadable"
        echo "  and no previously imported CA was found:"
        echo "    intermediate cert: ${extcacert:-<unset>}"
        echo "    intermediate key:  ${extcakey:-<unset>}"
        echo "    root cert:         ${extcaroot:-<unset>}"
        echo "  Provide them via the installer prompts or the"
        echo "  --ca-cert/--ca-key/--ca-root options, then re-run the installer."
        exit 1
    fi
    dots "Validating external CA files"
    # The supplied private key must match the supplied intermediate certificate
    local certmod keymod
    certmod=$(openssl x509 -noout -modulus -in "$extcacert" 2>>$error_log | openssl md5 2>>$error_log)
    keymod=$(openssl rsa -noout -modulus -in "$extcakey" 2>>$error_log | openssl md5 2>>$error_log)
    if [[ -z $certmod || $certmod != $keymod ]]; then
        echo "Failed"
        echo "  The supplied CA private key ($extcakey) does not match the"
        echo "  supplied CA certificate ($extcacert)."
        exit 1
    fi
    # The supplied intermediate must actually be a CA certificate
    if ! isCACert "$extcacert"; then
        echo "Failed"
        echo "  The supplied certificate ($extcacert) is not a CA certificate"
        echo "  (basicConstraints CA:TRUE is required)."
        exit 1
    fi
    # The intermediate must chain up to the supplied root
    if ! openssl verify -CAfile "$extcaroot" "$extcacert" >>$error_log 2>&1; then
        echo "Failed"
        echo "  The intermediate CA ($extcacert) does not verify against the"
        echo "  supplied root CA ($extcaroot)."
        exit 1
    fi
    # Import into FOG's CA directory so signing and serial files stay writable and
    # the layout matches the self-signed case (.fogCA.pem is what fog-client pins)
    cp "$extcacert" "$sslpath/CA/.fogCA.pem" >>$error_log 2>&1
    cp "$extcakey" "$sslpath/CA/.fogCA.key" >>$error_log 2>&1
    cat "$extcaroot" "$extcacert" > "$sslpath/CA/.fogCAchain.pem" 2>>$error_log
    chmod 600 "$sslpath/CA/.fogCA.key" >>$error_log 2>&1
    sslcakey="$sslpath/CA/.fogCA.key"
    sslcapem="$sslpath/CA/.fogCA.pem"
    sslcachain="$sslpath/CA/.fogCAchain.pem"
    errorStat $?
    # If we are replacing the CA on a server that already issued a server cert, warn
    if [[ $caCreated == yes && -n $sslpubcert && -e $sslpubcert ]] && \
        ! openssl verify -CAfile "$sslcachain" "$sslpubcert" >>$error_log 2>&1; then
        echo
        echo "  ###################################################################"
        echo "  # WARNING: switching this FOG server to an external/intermediate   #"
        echo "  # CA. The web server certificate and iPXE binaries will be         #"
        echo "  # regenerated and signed by the new CA. Any host whose fog-client  #"
        echo "  # already pinned the previous FOG CA will not trust this server    #"
        echo "  # until it re-pins, and PXE clients must pull the rebuilt iPXE      #"
        echo "  # binaries. Re-run the fog-client installer and/or reboot PXE       #"
        echo "  # clients after this install completes.                            #"
        echo "  ###################################################################"
        echo
    fi
}
# GH-529: the vhost templates and the docroot symlink both need $webroot in a
# form other than the "/x/" one installfog.sh normalises to, so derive them in
# one place rather than in each consumer. Idempotent -- callers invoke it
# without caring whether an earlier one already has.
#
#   webroot     /myfog/    URL path, as stored in .fogsettings
#   webrootbare myfog      filesystem/link name, no slashes
#   webrootre   /myfog/    escaped for the nginx/apache regex contexts, where a
#                          dot is a legitimate path character but a wildcard
#
# The default is repeated here because functions.sh is also sourced by the
# utils scripts, which never run installfog.sh's normalisation.
normalizeWebroot() {
    [[ -z $webroot ]] && webroot="/fog/"
    webrootbare="${webroot#/}"
    webrootbare="${webrootbare%/}"
    webrootre=$(printf '%s' "$webroot" | sed 's/[.[\*^$()+?{|]/\\&/g')
}
# Emits the fastcgi body shared by the generic `location ~ \.php$` include and
# the maintenance/ location, which needs the same PHP handling but with an
# allow/deny in front of it. Kept in one place so the two cannot drift -- if
# they did, the maintenance location would stop passing PHP to fpm and nginx
# would fall back to serving the source of those files as a static download.
# $1 = target file to append to.
# Reduces $ipaddress to a single address, keeping the full set in $ipaddresses.
#
# GH-954: $ipaddress is built by `ip -4 addr show $interface`, which prints one
# line per address, so on a NIC carrying a second address it arrives multi-line.
# Roughly forty consumers treat it as one value, and the failures are silent or
# baffling: apache refused to start with "Invalid command '<second ip>'", the
# post-install probe URL came out malformed, and DHCP next-server and the iPXE
# chain target would have been handed to clients broken.
#
# Two earlier fixes -- certip for the certificate CN and confighostip for the
# config.class.php host constants, both under GH-650 -- patched single
# consumers. This settles the contract instead: $ipaddress is THE address,
# $ipaddresses is every address, and the handful of places that want the whole
# set ask for it by name.
#
# Called after .fogsettings is sourced as well as after fresh detection, because
# an install written by an older installer has the multi-line value persisted in
# .fogsettings and would otherwise reload it unrepaired.
normalizeIpAddress() {
    [[ -z $ipaddresses ]] && ipaddresses="$ipaddress"
    # Unquoted on purpose: word splitting collapses the newline- or
    # space-separated forms to one space-separated list, which is what both
    # `for ip in $ipaddresses` and awk want.
    ipaddresses=$(echo $ipaddresses)
    ipaddress=$(echo $ipaddresses | awk '{print $1}')
}
emitNginxPhpBody() {
    echo "    set \$phproot ${docroot};" >> "$1"
    echo "    root $docroot;" >> "$1"
    echo "    fastcgi_pass 127.0.0.1:9000;" >> "$1"
    echo "    fastcgi_index index.php;" >> "$1"
    echo "    include fastcgi.conf;" >> "$1"
    echo "    fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;" >> "$1"
    # The API supports HTTP basic auth, but nginx forwards only the
    # fastcgi_params whitelist and Authorization is not on it, so
    # PHP_AUTH_USER/PHP_AUTH_PW were never populated and basic auth
    # could not succeed.
    echo "    fastcgi_param HTTP_AUTHORIZATION \$http_authorization;" >> "$1"
    echo "    fastcgi_buffers 16 16k;" >> "$1"
    echo "    fastcgi_buffer_size 32k;" >> "$1"
}
FOG_MANAGED_BEGIN='# === FOG MANAGED BLOCK -- DO NOT EDIT BETWEEN THESE LINES (see docs/SUPPORTED_CUSTOMIZATIONS.md) ==='
FOG_MANAGED_END='# === END FOG MANAGED BLOCK ==='
# Replaces only the FOG-owned region of $1 with the contents of $2, leaving
# anything the admin added outside that region byte-for-byte intact.
#
# Why a marked block instead of a template file: every vhost write site in
# createSSLCA() is inline bash echo/heredoc, branching on webserver family, OS
# family, SSL on/off and IPv4/IPv6. Extracting all of that into substitutable
# template assets would mean maintaining two representations of the same
# config forever. Wrapping the existing, unchanged generation in two marker
# lines gets the property that actually matters -- FOG can keep improving what
# it owns without discarding what the admin owns.
#
# '#' is a comment in both nginx and Apache syntax, so the markers are inert
# in either file.
#
# Three cases, deliberately no fourth: no file -> write one; both markers
# present -> replace between them; anything else (no markers, or only one
# because a previous run died mid-write or someone hand-edited) -> append a
# fresh block and touch nothing that was already there. Never guess at a
# partial patch.
spliceManagedBlock() {
    local conffile="$1" contentfile="$2" priorfile="$3"
    # $3 names where the file's PREVIOUS content lives, when the caller has
    # already moved it aside. createSSLCA() does exactly that -- it runs
    # `mv -fv $etcconf $etcconf.$timestamp` before generating, so diffconfig()
    # has something to compare against -- which means by the time we are called
    # $conffile does not exist and the admin's content is in the backup.
    #
    # Missing this is what made the first real-server test wipe a hand-added
    # vhost block: every sandbox test had called this against a file that was
    # still in place, so the "no file" branch never ran when it mattered.
    if [[ ! -f "$conffile" && -n $priorfile && -f "$priorfile" ]]; then
        cp -f "$priorfile" "$conffile" 2>/dev/null
    fi
    if [[ ! -f "$conffile" ]]; then
        { echo "$FOG_MANAGED_BEGIN"; cat "$contentfile"; echo "$FOG_MANAGED_END"; } > "$conffile"
        return $?
    fi
    if grep -qF "$FOG_MANAGED_BEGIN" "$conffile" && grep -qF "$FOG_MANAGED_END" "$conffile"; then
        local tmp="${conffile}.fogsplice.$$"
        awk -v b="$FOG_MANAGED_BEGIN" -v e="$FOG_MANAGED_END" -v cf="$contentfile" '
            $0 == b { print; while ((getline line < cf) > 0) print line; close(cf); skip=1; next }
            $0 == e { print; skip=0; next }
            !skip   { print }
        ' "$conffile" > "$tmp" && mv -f "$tmp" "$conffile"
        return $?
    fi
    { echo "$FOG_MANAGED_BEGIN"; cat "$contentfile"; echo "$FOG_MANAGED_END"; } >> "$conffile"
}
# Redirects the vhost generation that follows into a scratch file, so the ~260
# existing `>> "$etcconf"` lines below need no edit at all: they keep writing
# to $etcconf, which now names the scratch file. endManagedVhost() splices
# that content into the real file and restores the variable.
#
# Rewriting every one of those write sites individually would have been the
# obvious way and the wrong one -- 260-odd near-identical mechanical edits is
# exactly where a missed line hides, and a missed line writes half a vhost to
# the wrong path.
beginManagedVhost() {
    vhostfinal="$etcconf"
    # Callers mv the original to $etcconf.$timestamp just above this, so that
    # is where the admin's previous content is. Remember it -- it is the base
    # the new block gets spliced into.
    vhostprior="${etcconf}.${timestamp}"
    etcconf="${etcconf}.fogblock.$$"
    : > "$etcconf"
}
endManagedVhost() {
    local generated="$etcconf"
    etcconf="$vhostfinal"
    spliceManagedBlock "$etcconf" "$generated" "$vhostprior"
    local st=$?
    rm -f "$generated" >>$error_log 2>&1
    unset vhostfinal vhostprior
    return $st
}
createSSLCA() {
    # This function also emits the web server vhost further down, and those
    # nginx location / apache LocationMatch blocks used to hardcode ^/fog/ --
    # so a custom -W/--webroot installed the files somewhere the web server was
    # never told to serve.
    normalizeWebroot
    if [[ -z $sslpath ]]; then
        sslpath="$snapindir/ssl"
    fi
    sslpath=${sslpath//\/$}
    [[ ! -d $sslpath ]] && mkdir -p $sslpath >>$error_log 2>&1
    [[ ! -d $sslpath/CA ]] && mkdir -p $sslpath/CA >>$error_log 2>&1
    if [[ $externalca == yes ]]; then
        validateExternalCA
    else
        [[ -z $sslcakey ]] && sslcakey="$sslpath/CA/.fogCA.key"
        [[ -z $sslcapem ]] && sslcapem="$sslpath/CA/.fogCA.pem"
        if [[ $recreateCA == yes || $caCreated != yes || ! -e $sslcakey ]]; then
            dots "Creating SSL CA"
            openssl genrsa -out $sslcakey 4096 >>$error_log 2>&1
            openssl req -x509 -new -sha512 -nodes -key $sslcakey -days 3650 -out $sslcapem >>$error_log 2>&1 << EOF
.
.
.
.
.
FOG Server CA
.
EOF
            errorStat $?
        fi
        sslcachain="$sslcapem"
    fi
    [[ -z $sslprivkey ]] && sslprivkey="$sslpath/.srvprivate.key"
    [[ -z $sslcsr ]] && sslcsr="$sslpath/fog.csr"
    # An interface can carry several IPs, so $ipaddress may arrive as a list:
    # newline-separated from fresh detection, or space-separated when read back
    # from .fogsettings. A certificate has a single subject, so the first IP
    # becomes the CN while every IP is added as a subjectAltName so the cert
    # validates on each address.
    certip="$ipaddress"
    sanentries=""
    sancount=0
    for ip in $ipaddresses; do
        sancount=$((sancount + 1))
        [[ -n $sanentries ]] && sanentries="${sanentries}"$'\n'
        sanentries="${sanentries}IP.${sancount} = ${ip}"
    done
    dnscount=1
    dnsSanEntries=""
    for extraname in $extraServerNames; do
        dnscount=$((dnscount + 1))
        dnsSanEntries="${dnsSanEntries}"$'\n'"DNS.${dnscount} = ${extraname}"
    done
    if [[ $recreateKeys == yes || $recreateCA == yes || $caCreated != yes || ! -e $sslpath || ! -e $sslprivkey ]]; then
        dots "Creating SSL Private Key"
        if [[ $(validip $certip) -ne 0 ]]; then
            echo -e "\n"
            echo "  You seem to be using a DNS name instead of an IP address."
            echo "  This would cause an error when generating SSL key and certs"
            echo "  and so we will stop here! Please adjust variable 'ipaddress'"
            echo "  in .fogsettings file if this is an update and make sure you"
            echo "  provide an IP address when re-running the installer."
            exit 1
        fi
        mkdir -p $sslpath >>$error_log 2>&1
        openssl genrsa -out $sslprivkey 4096 >>$error_log 2>&1
        cat > $sslpath/req.cnf << EOF
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = yes
[req_distinguished_name]
CN = $certip
[v3_req]
subjectAltName = @alt_names
[alt_names]
$sanentries
DNS.1 = $hostname$dnsSanEntries
EOF
        openssl req -new -sha512 -key $sslprivkey -out $sslcsr -config $sslpath/req.cnf >>$error_log 2>&1 << EOF
$certip
EOF
        errorStat $?
    fi
    [[ ! -e $sslpath/.fogCA.key ]] && ln -sf $sslcakey $sslpath/CA/.fogCA.key >>$error_log 2>&1
    [[ ! -e $sslpath/.fogCA.pem ]] && ln -sf $sslcapem $sslpath/CA/.fogCA.pem >>$error_log 2>&1
    [[ ! -e $sslpath/fog.csr ]] && ln -sf $sslcsr $sslpath/fog.csr >>$error_log 2>&1
    [[ ! -e $sslpath/.srvprivate.key ]] && ln -sf $sslprivkey $sslpath/.srvprivate.key >>$error_log 2>&1
    mkdir -p $webdirdest/management/other/ssl >>$error_log 2>&1
    cat > $sslpath/ca.cnf << EOF
[v3_ca]
subjectAltName = @alt_names
[alt_names]
$sanentries
DNS.1 = $hostname$dnsSanEntries
EOF
    [[ -z $sslpubcert ]] && sslpubcert="$webdirdest/management/other/ssl/srvpublic.crt"
    if [[ $acmeLeaf == yes && $recreateKeys != yes && $recreateCA != yes ]]; then
        echo " * Leaf certificate is ACME-managed (see bin/setupacme.sh) -- leaving it in place."
        echo "   Re-run bin/setupacme.sh if you changed --hostname/--extra-server-name."
    elif [[ ! -x $sslpubcert ]]; then
        dots "Creating SSL Certificate"
        openssl x509 -req -in $sslcsr -CA $sslcapem -CAkey $sslcakey -CAcreateserial -out $sslpubcert -days 3650 -extensions v3_ca -extfile $sslpath/ca.cnf >>$error_log 2>&1
        errorStat $?
    fi
    [[ ! -e $webdirdest/management/other/ssl/srvpublic.crt ]] && ln -sf $sslpubcert $webdirdest/management/other/ssl/srvpublic.crt >>$error_log 2>&1
    dots "Creating auth pub key and cert"
    cp $sslcapem $webdirdest/management/other/ca.cert.pem >>$error_log 2>&1
    openssl x509 -outform der -in $webdirdest/management/other/ca.cert.pem -out $webdirdest/management/other/ca.cert.der >>$error_log 2>&1
    errorStat $?
    dots "Resetting SSL Permissions"
    chown -R $apacheuser:$apacheuser $webdirdest/management/other >>$error_log 2>&1
    errorStat $?
    [[ $httpproto == https ]] && sslenabled=" (Forced SSL)" || sslenabled=" (normal)"
    # $extraServerNames is a space-joined string (see --extra-server-name).
    # Computed once here and reused by both the nginx server_name lines below
    # and Apache's vhostaliases, so an admin's extra name(s) reach every vhost
    # block this function writes, not just one.
    extraServerNamesSuffix=""
    for extraname in $extraServerNames; do
        extraServerNamesSuffix="${extraServerNamesSuffix} ${extraname}"
    done
    case $webserver in
        nginx)
            case $novhost in
                [Yy]|[Yy][Ee][Ss])
                    echo "Skipped"
                    ;;
                *)
                    dots "Setting up Nginx virtualhost${sslenabled}"
                    [[ -z $phploc ]] && phploc="$fogprogramdir/php.loc"
                    # Long asset caching is safe: FOG stamps css/js URLs with
                    # ?ver= (FOG_BCACHE_VER), so upgrades bust browser caches.
                    # HTTP/2 only if the binary has ngx_http_v2_module; then
                    # nginx >= 1.25.1 wants "http2 on;" while older versions
                    # only understand the deprecated "listen ... http2" flag.
                    nginxver=$(nginx -v 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
                    nginxhttp2listen=""
                    nginxhttp2directive=""
                    if nginx -V 2>&1 | grep -q 'http_v2_module'; then
                        if [[ -n $nginxver && $(printf '%s\n' "1.25.1" "$nginxver" | sort -V | head -1) == "1.25.1" ]]; then
                            nginxhttp2directive="    http2 on;"
                        else
                            nginxhttp2listen=" http2"
                        fi
                    fi
                    echo 'location ~ \.php$ {' > "$phploc"
                    emitNginxPhpBody "$phploc"
                    echo "}" >> "$phploc"
                    # Apache's branch below backs up $etcconf the same way before
                    # rewriting it, which is what lets its own diffconfig call
                    # further down actually detect a change; nginx was calling
                    # diffconfig without ever taking this backup first, so it was
                    # comparing the new file to nothing and never fired.
                    mv -fv "${etcconf}" "${etcconf}.${timestamp}" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    # Everything below writes to the scratch file; endManagedVhost
                    # splices it into the real one before nginx -t sees it.
                    beginManagedVhost
                    echo "server {" > "$etcconf"
                    echo "    listen 80;" >> "$etcconf"
                    echo "    server_name $ipaddresses $hostname${extraServerNamesSuffix};" >> "$etcconf"
                    if [[ $httpproto != https ]]; then
                        echo "    root ${docroot};" >> "$etcconf"
                        echo "    index index.html index.htm index.php;" >> "$etcconf"
                        echo "    client_max_body_size 3000m;" >> "$etcconf"
                        echo "    gzip on;" >> "$etcconf"
                        echo "    gzip_types text/css text/javascript application/javascript application/json image/svg+xml;" >> "$etcconf"
                        echo "    gzip_min_length 1024;" >> "$etcconf"
                        echo "    gzip_comp_level 5;" >> "$etcconf"
                        echo "    gzip_vary on;" >> "$etcconf"
                        echo "    error_page 500 502 503 504 /50x.html;" >> "$etcconf"
                        # maintenance/ holds installer-only endpoints (a full DB dump,
                        # storage-node create/update). They gate themselves on the
                        # request being same-machine, but the directory is only removed
                        # when an install RUNS TO COMPLETION -- one that dies partway
                        # leaves them on disk indefinitely. Deny them here too.
                        #
                        # This has to precede the generic php include: nginx tries regex
                        # locations in order and the first match wins, so placed after,
                        # `location ~ \.php$` would take these URLs and the allow/deny
                        # would never run. It also has to repeat the fastcgi body (a
                        # location cannot be nested inside another) or nginx, finding no
                        # handler, would serve the PHP source as a static file.
                        echo "    location ~ ^${webrootre}maintenance/ {" >> "$etcconf"
                        echo "        allow 127.0.0.1;" >> "$etcconf"
                        echo "        allow ::1;" >> "$etcconf"
                        for ip in $ipaddresses; do
                            echo "        allow ${ip};" >> "$etcconf"
                        done
                        echo "        deny all;" >> "$etcconf"
                        emitNginxPhpBody "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    include ${phploc};" >> "$etcconf"
                        echo "    location = /50x.html {" >> "$etcconf"
                        echo "        root /var/lib/nginx/html;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~* ^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$ {" >> "$etcconf"
                        echo "        expires 30d;" >> "$etcconf"
                        echo "        try_files \$uri =404;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~ ^${webrootre}(.*)\$ {" >> "$etcconf"
                        echo "        try_files \$uri \$uri/ ${webroot}api/index.php;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    proxy_cookie_domain ~(?P<secure_domain>([-0-9a-z]+\.)?[-0-9a-z]+\.[a-z]+)$ \"$secure_domain; secure\";" >> "$etcconf"
                        echo "}" >> "$etcconf"
                        # Creates the diffie helman param file.
                        if [[ ! -f $sslpath/dhparam.pem ]]; then
                            openssl dhparam -dsaparam -out $sslpath/dhparam.pem 4096 >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        fi
                        echo "server {" >> "$etcconf"
                        echo "    listen $ipaddress:443 ssl${nginxhttp2listen};" >> "$etcconf"
                        echo "    server_name $ipaddresses $hostname${extraServerNamesSuffix};" >> "$etcconf"
                        echo "    root ${docroot};" >> "$etcconf"
                        echo "    index index.html index.htm index.php;" >> "$etcconf"
                        echo "    client_max_body_size 3000m;" >> "$etcconf"
                        echo "    ssl_protocols TLSv1.2 TLSv1.3;" >> "$etcconf"
                        echo "    ssl_prefer_server_ciphers off;" >> "$etcconf"
                        echo "    ssl_dhparam ${sslpath}/dhparam.pem;" >> "$etcconf"
                        echo "    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305;" >> "$etcconf"
                        echo "    ssl_certificate $sslpubcert;" >> "$etcconf"
                        echo "    ssl_certificate_key $sslprivkey;" >> "$etcconf"
                        echo "    ssl_session_timeout 1d;" >> "$etcconf"
                        echo "    ssl_session_cache shared:SSL:50m;" >> "$etcconf"
                        echo "    add_header Strict-Transport-Security max-age=15768000;" >> "$etcconf"
                        [[ -n $nginxhttp2directive ]] && echo "$nginxhttp2directive" >> "$etcconf"
                        echo "    gzip on;" >> "$etcconf"
                        echo "    gzip_types text/css text/javascript application/javascript application/json image/svg+xml;" >> "$etcconf"
                        echo "    gzip_min_length 1024;" >> "$etcconf"
                        echo "    gzip_comp_level 5;" >> "$etcconf"
                        echo "    gzip_vary on;" >> "$etcconf"
                        echo "    error_page 500 502 503 504 /50x.html;" >> "$etcconf"
                        # See the first server block -- installer-only, same-machine only.
                        echo "    location ~ ^${webrootre}maintenance/ {" >> "$etcconf"
                        echo "        allow 127.0.0.1;" >> "$etcconf"
                        echo "        allow ::1;" >> "$etcconf"
                        for ip in $ipaddresses; do
                            echo "        allow ${ip};" >> "$etcconf"
                        done
                        echo "        deny all;" >> "$etcconf"
                        emitNginxPhpBody "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    include ${phploc};" >> "$etcconf"
                        echo "    location = /50x.html {" >> "$etcconf"
                        echo "        root /var/lib/nginx/html;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~* ^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$ {" >> "$etcconf"
                        echo "        expires 30d;" >> "$etcconf"
                        echo "        try_files \$uri =404;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~ ^${webrootre}(.*)\$ {" >> "$etcconf"
                        echo "        try_files \$uri \$uri/ ${webroot}api/index.php;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    proxy_cookie_domain ~(?P<secure_domain>([-0-9a-z]+\.)?[-0-9a-z]+\.[a-z]+)$ \"$secure_domain; secure\";" >> "$etcconf"
                        echo "}" >> "$etcconf"
                    else
                        echo "    return 308 https://\$host\$request_uri;" >> "$etcconf"
                        echo "}" >> "$etcconf"
                        echo "Continued (See Below)"
                        # Creates the diffie helman param file.
                        if [[ ! -f $sslpath/dhparam.pem ]]; then
                            dots "Creating DHParam file"
                            openssl dhparam -dsaparam -out $sslpath/dhparam.pem 4096 >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            echo "Done"
                        fi
                        dots "Setting up Nginx virtualhost${sslenabled}"
                        # In Apache we have SSLCertificateFile, SSLCertificateKeyFile, and SSLCACertificateFile.
                        #  SSLCertificateFile is the public certificate created by the CA
                        #  SSLCACertificateFile is the public certificate of the CA
                        #  SSLCertificateKeyFile is the private key that generated the public certificate.
                        # In NGINX we have ssl_certificate and ssl_certificate_key
                        #  The ssl_certificate is the concatenated form of the CA Certificate and the public certificate generated.
                        #  The ssl_certificate_key is the private key.
                        # This generates the concatenated version of the CA and Public certificate.
                        if [[ ! -x $webdirdest/management/other/ssl/srvchained.crt ]]; then
                            cat $webdirdest/management/other/{ca.cert.pem,ssl/srvpublic.crt} >> $webdirdest/management/other/ssl/srvchained.crt
                        fi
                        echo "server {" >> "$etcconf"
                        echo "    listen $ipaddress:443 ssl${nginxhttp2listen};" >> "$etcconf"
                        echo "    server_name $ipaddresses $hostname${extraServerNamesSuffix};" >> "$etcconf"
                        echo "    root ${docroot};" >> "$etcconf"
                        echo "    index index.html index.htm index.php;" >> "$etcconf"
                        echo "    client_max_body_size 3000m;" >> "$etcconf"
                        echo "    ssl_protocols TLSv1.2 TLSv1.3;" >> "$etcconf"
                        echo "    ssl_prefer_server_ciphers off;" >> "$etcconf"
                        echo "    ssl_dhparam ${sslpath}/dhparam.pem;" >> "$etcconf"
                        echo "    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305;" >> "$etcconf"
                        echo "    ssl_certificate $sslpubcert;" >> "$etcconf"
                        echo "    ssl_certificate_key $sslprivkey;" >> "$etcconf"
                        echo "    ssl_session_timeout 1d;" >> "$etcconf"
                        echo "    ssl_session_cache shared:SSL:50m;" >> "$etcconf"
                        echo "    add_header Strict-Transport-Security max-age=15768000;" >> "$etcconf"
                        [[ -n $nginxhttp2directive ]] && echo "$nginxhttp2directive" >> "$etcconf"
                        echo "    gzip on;" >> "$etcconf"
                        echo "    gzip_types text/css text/javascript application/javascript application/json image/svg+xml;" >> "$etcconf"
                        echo "    gzip_min_length 1024;" >> "$etcconf"
                        echo "    gzip_comp_level 5;" >> "$etcconf"
                        echo "    gzip_vary on;" >> "$etcconf"
                        echo "    error_page 500 502 503 504 /50x.html;" >> "$etcconf"
                        # See the first server block -- installer-only, same-machine only.
                        echo "    location ~ ^${webrootre}maintenance/ {" >> "$etcconf"
                        echo "        allow 127.0.0.1;" >> "$etcconf"
                        echo "        allow ::1;" >> "$etcconf"
                        for ip in $ipaddresses; do
                            echo "        allow ${ip};" >> "$etcconf"
                        done
                        echo "        deny all;" >> "$etcconf"
                        emitNginxPhpBody "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    include ${phploc};" >> "$etcconf"
                        echo "    location = /50x.html {" >> "$etcconf"
                        echo "        root /var/lib/nginx/html;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~* ^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$ {" >> "$etcconf"
                        echo "        expires 30d;" >> "$etcconf"
                        echo "        try_files \$uri =404;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    location ~ ^${webrootre}(.*)\$ {" >> "$etcconf"
                        echo "        try_files \$uri \$uri/ ${webroot}api/index.php;" >> "$etcconf"
                        echo "    }" >> "$etcconf"
                        echo "    proxy_cookie_domain ~(?P<secure_domain>([-0-9a-z]+\.)?[-0-9a-z]+\.[a-z]+)$ \"$secure_domain; secure\";" >> "$etcconf"
                        echo "}" >> "$etcconf"
                        # Going to add display errors but only if debugmode is configured
                        # also going to loop through all php*.ini files in /etc/ and change accordingly
                        if [[ ${DEBUGMODE} == true ]];then
                            phpinifiles=$(find /etc/ -type f -name php*.ini)
                            for i in $phpinifiles; do
                                sed -i "s/display_errors = Off/display_errors = On/g" $i >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            done
                        fi
                    fi
                    # Splice BEFORE nginx -t: that tests the real file on disk,
                    # so it has to be the spliced result, not the scratch copy.
                    endManagedVhost
                    echo "Done"
                    dots "Testing nginx configuration"
                    nginx -t >> $workingdir/error_logs/fog_error_${version}.log 2>&1
                    diffconfig "${etcconf}"
                    errorStat $?
                    # Self-referential link so /fog/fog/... resolves. $webdirdest
                    # carries a trailing slash, hence the basename.
                    linkIfAbsent $webdirdest ${webdirdest%/}/$(basename $webdirdest)
                    ;;
            esac
            ;;
        httpd|apache*)
            dots "Setting up Apache virtual host${sslenabled}"
            case $novhost in
                [Yy]|[Yy][Ee][Ss])
                    echo "Skipped"
                    ;;
                *)
                    if [[ $osid -eq 2 ]]; then
                        a2dissite 001-fog >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2ensite 000-default >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    fi
                    # GH-650: $ipaddress is one address per line (see the
                    # `ip -4 addr show` in lib/common/input.sh), so a NIC
                    # carrying a second address emitted
                    #     ServerName 10.0.0.1
                    #     10.0.0.2
                    # and apache refused to start with "Invalid command
                    # '10.0.0.2'", failing the install at "Starting and
                    # checking status of web services". ServerName takes
                    # exactly one name; the extras go on ServerAlias, which is
                    # variadic, so a multi-homed server still answers to every
                    # address it has.
                    vhostname="$ipaddress"
                    vhostaliases=$(echo $ipaddresses | awk '{for (i = 2; i <= NF; i++) printf " %s", $i}')
                    vhostaliases="${vhostaliases}${extraServerNamesSuffix}"
                    mv -fv "${etcconf}" "${etcconf}.${timestamp}" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    # See the nginx branch above -- same scratch-file swap, so
                    # none of the write sites below change.
                    beginManagedVhost
                    echo "<VirtualHost *:80>" > "$etcconf"
                    echo "    <FilesMatch \"\.php\$\">" >> "$etcconf"
                    if [[ $osid -eq 1 && $OSVersion -lt 7 ]]; then
                        echo "        SetHandler application/x-httpd-php" >> "$etcconf"
                    else
                        echo "        SetHandler \"proxy:fcgi://127.0.0.1:9000/\"" >> "$etcconf"
                    fi
                    echo "    </FilesMatch>" >> "$etcconf"
                    # The API supports HTTP basic auth, but proxy_fcgi drops
                    # the Authorization header, so PHP_AUTH_USER/PHP_AUTH_PW
                    # were never populated and basic auth could not succeed.
                    # SetEnvIf is used in preference to CGIPassAuth because
                    # CGIPassAuth needs httpd >= 2.4.13 and an unknown
                    # directive stops Apache from starting at all; SetEnvIf
                    # works on every 2.4 and is a no-op under mod_php.
                    echo "    SetEnvIf Authorization \"(.+)\" HTTP_AUTHORIZATION=\$1" >> "$etcconf"
                    echo "    KeepAlive Off" >> "$etcconf"
                    echo "    ServerName $vhostname" >> "$etcconf"
                    echo "    ServerAlias ${hostname}${vhostaliases}" >> "$etcconf"
                    # maintenance/ holds installer-only endpoints (a full DB dump,
                    # storage-node create/update). Each one gates itself on the
                    # request being same-machine, but the directory is only removed
                    # when an install RUNS TO COMPLETION -- an install that dies
                    # partway leaves them on disk indefinitely. Deny them at the
                    # web server too, so a file added there later without its own
                    # check is not exposed by that omission alone.
                    #
                    # LocationMatch, not Directory: the tree is also published at
                    # ${docroot}/${webrootbare} via a symlink, and Directory does
                    # not follow symlinks, so a Directory rule would miss that
                    # path entirely. Require local matches loopback and the case
                    # where client and server address are the same -- which is how
                    # the installer calls in.
                    echo "    <LocationMatch \"^${webrootre}maintenance/\">" >> "$etcconf"
                    echo "        Require local" >> "$etcconf"
                    echo "    </LocationMatch>" >> "$etcconf"
                    echo "    DocumentRoot $docroot" >> "$etcconf"
                    if [[ $httpproto == https ]]; then
                        echo "    RewriteEngine On" >> "$etcconf"
                        echo "    RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)" >> "$etcconf"
                        echo "    RewriteRule .* - [F]" >> "$etcconf"
                        echo "    RewriteRule /management/other/ca.cert.der$ - [L]" >> "$etcconf"
                        echo "    RewriteCond %{HTTPS} off" >> "$etcconf"
                        # GH-978: ^/?(.*)$ rather than (.*). In vhost context a
                        # RewriteRule pattern is matched against the URL-path
                        # WITH its leading slash, so (.*) captured "/fog/..."
                        # and the substitution emitted a Location of
                        # https://host//fog/... -- a doubled slash. The bare
                        # (.*) form is .htaccess idiom, where the leading slash
                        # is stripped for you; it has been wrong here since
                        # 2017. Apache's MergeSlashes normally hides it, which
                        # is why it went unreported for so long.
                        echo "    RewriteRule ^/?(.*)\$ https://%{HTTP_HOST}/\$1 [R,L]" >> "$etcconf"
                        echo "</VirtualHost>" >> "$etcconf"
                        echo "<VirtualHost *:443>" >> "$etcconf"
                        echo "    KeepAlive Off" >> "$etcconf"
                        echo "    <FilesMatch \"\.php\$\">" >> "$etcconf"
                        if [[ $osid -eq 1 && $OSVersion -lt 7 ]]; then
                            echo "        SetHandler application/x-httpd-php" >> "$etcconf"
                        else
                            echo "        SetHandler \"proxy:fcgi://127.0.0.1:9000/\"" >> "$etcconf"
                        fi
                        echo "    </FilesMatch>" >> "$etcconf"
                        # Keeps API basic auth working; see the :80 vhost.
                        echo "    SetEnvIf Authorization \"(.+)\" HTTP_AUTHORIZATION=\$1" >> "$etcconf"
                        echo "    ServerName $vhostname" >> "$etcconf"
                        echo "    ServerAlias ${hostname}${vhostaliases}" >> "$etcconf"
                        # See the :80 vhost -- installer-only, same-machine only.
                        echo "    <LocationMatch \"^${webrootre}maintenance/\">" >> "$etcconf"
                        echo "        Require local" >> "$etcconf"
                        echo "    </LocationMatch>" >> "$etcconf"
                        echo "    DocumentRoot $docroot" >> "$etcconf"
                        echo "    SSLEngine On" >> "$etcconf"
                        echo "    SSLProtocol -all +TLSv1.2" >> "$etcconf"
                        echo "    SSLCipherSuite HIGH:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305:!MEDIUM:!LOW" >> "$etcconf"
                        echo "    SSLHonorCipherOrder On" >> "$etcconf"
                        echo "    SSLSessionTickets Off" >> "$etcconf"
                        echo "    SSLCertificateFile $sslpubcert" >> "$etcconf"
                        echo "    SSLCertificateKeyFile $sslprivkey" >> "$etcconf"
                        echo "    SSLCACertificateFile $sslcachain" >> "$etcconf"
                        echo "    <IfModule http2_module>" >> "$etcconf"
                        echo "        Protocols h2 http/1.1" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <Directory $webdirdest>" >> "$etcconf"
                        echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                        echo "    </Directory>" >> "$etcconf"
                        # GH-529: apache does NOT resolve symlinks when matching
                        # <Directory>, so the block above covers the real tree
                        # but not the path a custom webroot is published at --
                        # leaving a bare "/mywebroot/" with no DirectoryIndex
                        # and a 403. Emit the published path too when it is a
                        # different string.
                        if [[ ${docroot%/}/${webrootbare} != ${webdirdest%/} && -n $webrootbare ]]; then
                            echo "    <Directory ${docroot%/}/${webrootbare}>" >> "$etcconf"
                            echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                            echo "    </Directory>" >> "$etcconf"
                        fi
                        echo "    <IfModule mod_deflate.c>" >> "$etcconf"
                        echo "        <IfModule mod_filter.c>" >> "$etcconf"
                        echo "            AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml" >> "$etcconf"
                        echo "        </IfModule>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <IfModule mod_expires.c>" >> "$etcconf"
                        echo "        <LocationMatch \"^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$\">" >> "$etcconf"
                        echo "            ExpiresActive On" >> "$etcconf"
                        echo "            ExpiresDefault \"access plus 30 days\"" >> "$etcconf"
                        echo "        </LocationMatch>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    Timeout 600" >> "$etcconf"
                        echo "    ProxyTimeout 600" >> "$etcconf"
                        echo "    RewriteEngine On" >> "$etcconf"
                        echo "    RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)" >> "$etcconf"
                        echo "    RewriteRule .* - [F]" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-d" >> "$etcconf"
                        echo "    RewriteRule ^${webrootre}(.*)$ ${webroot}api/index.php [QSA,L]" >> "$etcconf"
                        echo "</VirtualHost>" >> "$etcconf"
                    else
                        echo "    <Directory $webdirdest>" >> "$etcconf"
                        echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                        echo "    </Directory>" >> "$etcconf"
                        # GH-529: apache does NOT resolve symlinks when matching
                        # <Directory>, so the block above covers the real tree
                        # but not the path a custom webroot is published at --
                        # leaving a bare "/mywebroot/" with no DirectoryIndex
                        # and a 403. Emit the published path too when it is a
                        # different string.
                        if [[ ${docroot%/}/${webrootbare} != ${webdirdest%/} && -n $webrootbare ]]; then
                            echo "    <Directory ${docroot%/}/${webrootbare}>" >> "$etcconf"
                            echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                            echo "    </Directory>" >> "$etcconf"
                        fi
                        echo "    <IfModule mod_deflate.c>" >> "$etcconf"
                        echo "        <IfModule mod_filter.c>" >> "$etcconf"
                        echo "            AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml" >> "$etcconf"
                        echo "        </IfModule>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <IfModule mod_expires.c>" >> "$etcconf"
                        echo "        <LocationMatch \"^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$\">" >> "$etcconf"
                        echo "            ExpiresActive On" >> "$etcconf"
                        echo "            ExpiresDefault \"access plus 30 days\"" >> "$etcconf"
                        echo "        </LocationMatch>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    Timeout 600" >> "$etcconf"
                        echo "    ProxyTimeout 600" >> "$etcconf"
                        echo "    RewriteEngine On" >> "$etcconf"
                        echo "    RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)" >> "$etcconf"
                        echo "    RewriteRule .* - [F]" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-d" >> "$etcconf"
                        echo "    RewriteRule ^${webrootre}(.*)$ ${webroot}api/index.php [QSA,L]" >> "$etcconf"
                        echo "</VirtualHost>" >> "$etcconf"
                        echo "<VirtualHost *:443>" >> "$etcconf"
                        echo "    KeepAlive Off" >> "$etcconf"
                        echo "    <FilesMatch \"\.php\$\">" >> "$etcconf"
                        if [[ $osid -eq 1 && $OSVersion -lt 7 ]]; then
                            echo "        SetHandler application/x-httpd-php" >> "$etcconf"
                        else
                            echo "        SetHandler \"proxy:fcgi://127.0.0.1:9000/\"" >> "$etcconf"
                        fi
                        echo "    </FilesMatch>" >> "$etcconf"
                        # Keeps API basic auth working; see the :80 vhost.
                        echo "    SetEnvIf Authorization \"(.+)\" HTTP_AUTHORIZATION=\$1" >> "$etcconf"
                        echo "    ServerName $vhostname" >> "$etcconf"
                        echo "    ServerAlias ${hostname}${vhostaliases}" >> "$etcconf"
                        # See the :80 vhost -- installer-only, same-machine only.
                        echo "    <LocationMatch \"^${webrootre}maintenance/\">" >> "$etcconf"
                        echo "        Require local" >> "$etcconf"
                        echo "    </LocationMatch>" >> "$etcconf"
                        echo "    DocumentRoot $docroot" >> "$etcconf"
                        echo "    SSLEngine On" >> "$etcconf"
                        echo "    SSLProtocol -all +TLSv1.2" >> "$etcconf"
                        echo "    SSLCipherSuite HIGH:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:DHE-RSA-CHACHA20-POLY1305:!MEDIUM:!LOW" >> "$etcconf"
                        echo "    SSLHonorCipherOrder Off" >> "$etcconf"
                        echo "    SSLSessionTickets Off" >> "$etcconf"
                        echo "    SSLCertificateFile $sslpubcert" >> "$etcconf"
                        echo "    SSLCertificateKeyFile $sslprivkey" >> "$etcconf"
                        echo "    SSLCACertificateFile $sslcachain" >> "$etcconf"
                        echo "    <IfModule http2_module>" >> "$etcconf"
                        echo "        Protocols h2 http/1.1" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <Directory $webdirdest>" >> "$etcconf"
                        echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                        echo "    </Directory>" >> "$etcconf"
                        # GH-529: apache does NOT resolve symlinks when matching
                        # <Directory>, so the block above covers the real tree
                        # but not the path a custom webroot is published at --
                        # leaving a bare "/mywebroot/" with no DirectoryIndex
                        # and a 403. Emit the published path too when it is a
                        # different string.
                        if [[ ${docroot%/}/${webrootbare} != ${webdirdest%/} && -n $webrootbare ]]; then
                            echo "    <Directory ${docroot%/}/${webrootbare}>" >> "$etcconf"
                            echo "        DirectoryIndex index.php index.html index.htm" >> "$etcconf"
                            echo "    </Directory>" >> "$etcconf"
                        fi
                        echo "    <IfModule mod_deflate.c>" >> "$etcconf"
                        echo "        <IfModule mod_filter.c>" >> "$etcconf"
                        echo "            AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json image/svg+xml" >> "$etcconf"
                        echo "        </IfModule>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    <IfModule mod_expires.c>" >> "$etcconf"
                        echo "        <LocationMatch \"^${webrootre}management/(?!other/).+\.(css|js|png|jpg|gif|svg|ico|woff2?|ttf|eot)\$\">" >> "$etcconf"
                        echo "            ExpiresActive On" >> "$etcconf"
                        echo "            ExpiresDefault \"access plus 30 days\"" >> "$etcconf"
                        echo "        </LocationMatch>" >> "$etcconf"
                        echo "    </IfModule>" >> "$etcconf"
                        echo "    Timeout 600" >> "$etcconf"
                        echo "    ProxyTimeout 600" >> "$etcconf"
                        echo "    RewriteEngine On" >> "$etcconf"
                        echo "    RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)" >> "$etcconf"
                        echo "    RewriteRule .* - [F]" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-f" >> "$etcconf"
                        echo "    RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_FILENAME} !-d" >> "$etcconf"
                        echo "    RewriteRule ^${webrootre}(.*)$ ${webroot}api/index.php [QSA,L]" >> "$etcconf"
                        echo "</VirtualHost>" >> "$etcconf"
                    fi
                    endManagedVhost
                    diffconfig "${etcconf}"
                    errorStat $?
                    # Self-referential link so /fog/fog/... resolves. $webdirdest
                    # carries a trailing slash, hence the basename.
                    linkIfAbsent $webdirdest ${webdirdest%/}/$(basename $webdirdest)
                    if [[ $osid -eq 2 ]]; then
                        # No `a2enmod php` here. Debian/Ubuntu name that module
                        # php<version> (php7.4, php8.3), never plain php, so the
                        # call only ever printed "ERROR: Module php does not
                        # exist!" -- the line that made a working install look
                        # broken in forums topic 18204. Enabling mod_php would be
                        # wrong anyway: FOG serves PHP through FPM via proxy_fcgi
                        # below, and mod_php forces mpm_prefork, which conflicts.
                        a2enmod proxy_fcgi setenvif >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2enmod rewrite >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2enmod expires deflate filter >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2enmod ssl >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2ensite "001-fog" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        a2dissite "000-default" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    fi
                    ;;
            esac
            ;;
        *) ;;
    esac
    dots "Configuring PHP FPM"
    case $osid in
        1)
            phpfpmconf='/etc/php-fpm.d/www.conf';
            ;;
        2)
            phpfpmconf="/etc/php/$php_ver/fpm/pool.d/www.conf"
            ;;
        3)
            # Alpine's pool lives beside its php.ini, under the versioned
            # directory (/etc/php83, /etc/php84, ...). The Arch path that used
            # to be here matched nothing on Alpine, so none of the tuning below
            # was ever applied.
            phpfpmconf="$(dirname $phpini)/php-fpm.d/www.conf"
            ;;
        4)
            phpfpmconf='/etc/php/php-fpm.d/www.conf'
            ;;
    esac
    if [[ -n $phpfpmconf ]]; then
        # The pool runs the PHP that IS FOG -- nginx only ever serves static
        # files and proxies everything else here -- so it has to run as the user
        # FOG owns its files with. The packaged default disagrees on every nginx
        # install: RedHat/Fedora ship user=apache, Arch user=http, Debian/Ubuntu
        # user=www-data, while $apacheuser is "nginx" on all of them.
        #
        # That mismatch is why an $apacheuser-owned directory is not reliably
        # writable by the process that needs it. It is the reason
        # $fogprogramdir/cache is 1777 rather than $apacheuser-owned, and it is
        # what made the Secure Boot staging directory (0750 nginx:nginx)
        # unreachable to a pool running as apache -- kernel updates failed there
        # with nothing but "Failed to open temp file", which names neither
        # php-fpm nor the directory.
        #
        # Set unconditionally rather than only under nginx: where the packaged
        # user already equals $apacheuser this is a no-op, and pinning it makes
        # the pool's identity explicit instead of silently inherited from the
        # distro packaging, which is free to change it.
        #
        # The patterns cannot catch listen.owner/listen.group -- those start
        # "listen.", so the anchored "user"/"group" match skips them -- and the
        # listen socket is TCP (below) anyway, so their values do not matter.
        sed -i "s/^[;[:space:]]*user[[:space:]]*=.*/user = ${apacheuser}/" $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i "s/^[;[:space:]]*group[[:space:]]*=.*/group = ${apacheuser}/" $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        # Changing the pool user orphans whatever the previous worker owned, and
        # the session directory is the one that bites immediately: PHP writes
        # sessions there AS the pool user, so a pool that can no longer read its
        # own session files logs every admin out and refuses new logins, with
        # nothing in the browser pointing at php-fpm. Re-own it to match.
        #
        # Derived from the pool's own php_value override first, then php.ini,
        # rather than from a hardcoded list of distro paths that would rot.
        phpsessdir=$(sed -n "s/^[;[:space:]]*php_value\[session\.save_path\][[:space:]]*=[[:space:]]*//p" $phpfpmconf | tail -1 | tr -d '"')
        [[ -z $phpsessdir ]] && phpsessdir=$(sed -n "s/^[;[:space:]]*session\.save_path[[:space:]]*=[[:space:]]*//p" $phpini 2>/dev/null | tail -1 | tr -d '"')
        # Guarded hard because this is a recursive chown running as root: it must
        # be an absolute path, must exist, must not be /, and must actually look
        # like a session directory. A parse that goes wrong resolves to nothing
        # rather than to a recursive chown of somewhere that matters.
        if [[ -n $phpsessdir && $phpsessdir == /* && $phpsessdir != "/" && -d $phpsessdir && $phpsessdir == *session* ]]; then
            chown -R ${apacheuser}:${apacheuser} "$phpsessdir" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        fi
        sed -i 's/listen = .*/listen = 127.0.0.1:9000/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/^[;]pm\.max_requests = .*/pm.max_requests = 2000/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/^[;]php_admin_value\[memory_limit\] = .*/php_admin_value[memory_limit] = 256M/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/pm\.max_children = .*/pm.max_children = 50/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/pm\.min_spare_servers = .*/pm.min_spare_servers = 5/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/pm\.max_spare_servers = .*/pm.max_spare_servers = 10/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        sed -i 's/pm\.start_servers = .*/pm.start_servers = 5/g' $phpfpmconf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    echo "Done"
    dots "Starting and checking status of web services"
    case $systemctl in
        yes)
            case $osid in
                2)
                    systemctl is-active --quiet $webserver $phpfpm && systemctl stop $webserver $phpfpm >>$error_log 2>&1 || true
                    systemctl is-active --quiet $webserver $phpfpm && true || systemctl start $webserver $phpfpm >>$error_log 2>&1
                    systemctl status $webserver $phpfpm >>$error_log 2>&1
                    ;;
                *)
                    systemctl is-active --quiet $webserver php-fpm && systemctl stop $webserver php-fpm >>$error_log 2>&1 || true
                    sleep 1
                    systemctl is-active --quiet $webserver php-fpm && true || systemctl start $webserver php-fpm >>$error_log 2>&1
                    sleep 1
                    systemctl status $webserver php-fpm >>$error_log 2>&1
                    ;;
            esac
            ;;
        *)
            case $osid in
                2)
                    service $webserver stop >>$error_log 2>&1
                    service $webserver start >>$error_log 2>&1
                    service $phpfpm stop >>$error_log 2>&1
                    service $phpfpm start >>$error_log 2>&1
                    service $webserver status >>$error_log 2>&1
                    service $phpfpm status >>$error_log 2>&1
                    ;;
                3)
                    rc-service nginx stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service nginx start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service $phpfpm stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service $phpfpm start >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service nginx status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    rc-service $phpfpm status >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
                *)
                    service $webserver stop >>$error_log 2>&1
                    service $webserver start >>$error_log 2>&1
                    service php-fpm stop >>$error_log 2>&1
                    service php-fpm start >>$error_log 2>&1
                    service $webserver status >>$error_log 2>&1
                    service php-fpm status >>$error_log 2>&1
                    ;;
            esac
            ;;
    esac
    errorStat $?
    caCreated="yes"
}
configureHttpd() {
    normalizeWebroot
    dots "Stopping web service"
    case $systemctl in
        yes)
            case $osid in
                1)
                    systemctl is-active --quiet $webserver php-fpm && systemctl stop $webserver php-fpm >>$error_log 2>&1 || true
                    ;;
                2)
                    systemctl is-active --quiet $webserver php${php_ver}-fpm && systemctl stop $webserver php${php_ver}-fpm >>$error_log 2>&1 || true
                    ;;
                4)
                    systemctl is-active --quiet $webserver $phpfpm && systemctl stop $webserver $phpfpm >>$error_log 2>&1 || true
                    ;;
            esac
            errorStat $?
            ;;
        *)
            case $osid in
                1)
                    service $webserver stop >>$error_log 2>&1
                    service php-fpm stop >>$error_log 2>&1
                    errorStat $?
                    ;;
                2)
                    service $webserver stop >>$error_log 2>&1
                    service php${php_ver}-fpm stop >>$error_log 2>&1
                    errorStat $?
                    ;;
                3)
                    rc-service nginx stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    errorStat $?
                    service php-fpm${php_ver} stop >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
            ;;
    esac
    dots "Setting up Apache and PHP files"
    if [[ ! -f $phpini ]]; then
        echo "Failed"
        echo "   ###########################################"
        echo "   #                                         #"
        echo "   #      PHP Failed to install properly     #"
        echo "   #                                         #"
        echo "   ###########################################"
        echo
        echo "   Could not find $phpini!"
        exit 1
    fi
    if [[ $osid -eq 4 ]]; then
        # Arch ships httpd.conf with almost every module commented out and
        # nothing enabled beyond a bare static server, so FOG has to turn on
        # what it needs. PHP runs through php-fpm over mod_proxy_fcgi, which is
        # why event stays and prefork/worker go: mod_php would force prefork and
        # is the reason the recipe in GH-447 could not have worked as written.
        if [[ ! -f $httpdconf ]]; then
            echo "Failed"
            echo "   Could not find $httpdconf!"
            exit 1
        fi
        sed -i '/LoadModule mpm_event_module modules\/mod_mpm_event.so/s/^#//g' $httpdconf >>$error_log 2>&1
        sed -i '/LoadModule mpm_prefork_module modules\/mod_mpm_prefork.so/s/^/#/g' $httpdconf >>$error_log 2>&1
        sed -i '/LoadModule mpm_worker_module modules\/mod_mpm_worker.so/s/^/#/g' $httpdconf >>$error_log 2>&1
        for archmod in proxy_html xml2enc proxy proxy_http proxy_fcgi socache_shmcb ssl rewrite; do
            sed -i "/LoadModule ${archmod}_module modules\/mod_${archmod}.so/s/^#//g" $httpdconf >>$error_log 2>&1
        done
        unset archmod
        grep -q "^Include conf/extra/fog\.conf" $httpdconf 2>/dev/null || \
            echo -e "# FOG Virtual Host\nListen 443\nInclude conf/extra/fog.conf" >>$httpdconf
    fi
    # Uncommenting ";extension=" lines is Arch's php.ini convention -- it builds
    # nearly everything into the php package and ships it all disabled. Alpine
    # enables its modules with drop-ins under conf.d instead, so these are
    # no-ops there; osid 3 keeps them only so its behaviour is unchanged.
    if [[ $osid -eq 3 || $osid -eq 4 ]]; then
        sed -i 's/;extension=bcmath/extension=bcmath/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=curl/extension=curl/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=ftp/extension=ftp/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=gd/extension=gd/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=gettext/extension=gettext/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=ldap/extension=ldap/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=mysqli/extension=mysqli/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=openssl/extension=openssl/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=pdo_mysql/extension=pdo_mysql/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=posix/extension=posix/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=sockets/extension=sockets/g' $phpini >>$error_log 2>&1
        sed -i 's/;extension=zip/extension=zip/g' $phpini >>$error_log 2>&1
        sed -i 's/^open_basedir\ =/;open_basedir\ =/g' $phpini >>$error_log 2>&1
    fi
    sed -i 's/post_max_size\ \=\ 8M/post_max_size\ \=\ 3000M/g' $phpini >>$error_log 2>&1
    sed -i 's/upload_max_filesize\ \=\ 2M/upload_max_filesize\ \=\ 3000M/g' $phpini >>$error_log 2>&1
    sed -i 's/.*max_input_vars\ \=.*$/max_input_vars\ \=\ 250000/g' $phpini >>$error_log 2>&1
    errorStat $?
    dots "Testing and removing symbolic links if found"
    if [[ -h ${docroot}fog ]]; then
        rm -f ${docroot}fog >>$error_log 2>&1
    fi
    if [[ -h ${docroot}${webroot} ]]; then
        rm -f ${docroot}${webroot} >>$error_log 2>&1
    fi
    errorStat $?
    dots "Backing up old data"
    if [[ -d $backupPath/fog_web_${version}.BACKUP ]]; then
        rm -rf $backupPath/fog_web_${version}.BACKUP >>$error_log 2>&1
    fi
    if [[ -d $webdirdest ]]; then
        cp -RT "$webdirdest" "${backupPath}/fog_web_${version}.BACKUP" >>$error_log 2>&1
        rm -rf ${backupPath}/fog_web_${version}.BACKUP/lib/plugins/accesscontrol
        rm -rf "$webdirdest" >>$error_log 2>&1
    fi
    if [[ $osid -eq 2 ]]; then
        # GH-953: this removed ${docroot} -- the whole document root, taking any
        # other site sharing it with FOG. It only reaches the rm when the
        # rm -rf "$webdirdest" above failed to remove the same directory, so it
        # was a recovery path that deleted the parent of what it could not
        # delete. Only the fog directory was ever meant to go.
        if [[ -d ${docroot}fog ]]; then
            rm -rf ${docroot}fog >>$error_log 2>&1
        fi
    fi
    mkdir -p "$webdirdest" >>$error_log 2>&1
    if [[ -d $docroot && ! -h ${docroot}fog ]] || [[ ! -d ${docroot}fog ]]; then
        ln -s $webdirdest  ${docroot}/fog >>$error_log 2>&1
    fi
    # GH-529: $webdirdest is a filesystem path and is always "<docroot>/fog";
    # $webroot is the URL path the web server publishes. With the default
    # "/fog/" the two coincide, which is why nothing ever linked them -- and
    # why -W/--webroot produced a vhost pointing at a URL with nothing behind
    # it. Publish the tree at the requested path as well. The removal of
    # ${docroot}${webroot} earlier in this function has always expected this
    # link to exist; it just was not being created.
    if [[ ${docroot%/}/${webrootbare} != ${webdirdest%/} && -n $webrootbare ]]; then
        linkIfAbsent "${webdirdest%/}" "${docroot%/}/${webrootbare}"
    fi
    errorStat $?
    if [[ $copybackold -gt 0 ]]; then
        if [[ -d ${backupPath}/fog_web_${version}.BACKUP ]]; then
            dots "Copying back old web folder as is";
            cp -Rf ${backupPath}/fog_web_${version}.BACKUP/* $webdirdest/
            errorStat $?
            dots "Ensuring all classes are lowercased"
            for i in $(find $webdirdest -type f -name "*[A-Z]*\.class\.php" -o -name "*[A-Z]*\.event\.php" -o -name "*[A-Z]*\.hook\.php" 2>>$error_log); do
                mv "$i" "$(echo $i | tr A-Z a-z)" >>$error_log 2>&1
            done
            errorStat $?
        fi
    fi
    dots "Copying new files to web folder"
    cp -Rf $webdirsrc/* $webdirdest/
    errorStat $?
    for i in $(find $backupPath/fog_web_${version}.BACKUP/management/other/ -maxdepth 1 -type f -not -name gpl-3.0.txt -a -not -name index.php -a -not -name 'ca.*' 2>>$error_log); do
        cp -Rf $i ${webdirdest}/management/other/ >>$error_log 2>&1
    done
    if [[ $installlang -eq 1 ]]; then
        dots "Creating the language binaries"
        langpath="${webdirdest}/management/languages"
        languagesfound=$(find $langpath -maxdepth 1 -type d -exec basename {} \; | awk -F. '/\./ {print $1}' 2>>$error_log)
        languagemogen "$languagesfound" "$langpath"
        echo "Done"
    fi
    # Generate a per-install schema bootstrap token written into config.class.php
    # as FOG_SCHEMA_INSTALL_TOKEN. It lets the installer deploy the schema before
    # any FOG user/database exists without leaving the endpoint open to anonymous
    # callers. Reused by updateDB() (called after this) for the deploy request.
    installToken=$(openssl rand -hex 32 2>/dev/null)
    [[ -z $installToken ]] && installToken=$(tr -dc 'a-f0-9' < /dev/urandom 2>/dev/null | head -c 64)
    dots "Creating config file"
    # Same multi-IP input as GH-650: on a multi-homed interface $ipaddress is a
    # list, and these four constants each want one host. Interpolating the list
    # is valid PHP -- it just yields a two-line string -- so the install reports
    # success and then every TFTP/FTP/storage/WOL connection targets a hostname
    # that cannot resolve. Use the same first address the certificate's CN uses,
    # so the host FOG advertises and the host its cert is issued for agree.
    confighostip="$ipaddress"
    phpescsnmysqlpass="${snmysqlpass//\\/\\\\}";   # Replace every \ with \\ ...
    phpescsnmysqlpass="${phpescsnmysqlpass//\'/\\\'}"   # and then every ' with \' for full PHP escaping
    # Derive the master's network CIDR (e.g. 192.168.1.0/24) from the chosen
    # interface so the default storage group can trust node-to-node status
    # calls from the local subnet out of the box. Stays empty (no extra trust)
    # if it cannot be derived; the schema migration then leaves the group alone.
    storageDefaultCidr=""
    storageTrustPrefix=$(getCidr "$interface")
    if [[ -n $ipaddress && -n $storageTrustPrefix ]]; then
        storageTrustMask=$(cidr2mask "$storageTrustPrefix" 2>/dev/null)
        storageTrustNetwork=$(mask2network "$ipaddress" "$storageTrustMask" 2>/dev/null)
        [[ -n $storageTrustNetwork ]] && storageDefaultCidr="${storageTrustNetwork}/${storageTrustPrefix}"
    fi
    echo "<?php
/**
 * The main configuration FOG uses.
 *
 * PHP Version 5
 *
 * Constructs the configuration we need to run FOG.
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * The main configuration FOG uses.
 *
 * @category Config
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Config
{
    /**
     * Calls the required functions to define items
     *
     * @return void
     */
    public function __construct()
    {
        global \$node;
        self::_dbSettings();
        self::_svcSetting();
        if (\$node == 'schema') {
            self::_initSetting();
        }
    }
    /**
     * Defines the database settings for FOG
     *
     * @return void
     */
    private static function _dbSettings()
    {
        define('DATABASE_TYPE', 'mysql'); // mysql or oracle
        define('DATABASE_HOST', '$snmysqlhost');
        define('DATABASE_NAME', '$mysqldbname');
        define('DATABASE_USERNAME', '$snmysqluser');
        define('DATABASE_PASSWORD', '$phpescsnmysqlpass');
        // Per-install secret allowing the schema deploy endpoint to run before
        // any user/database exists. Presented back by the installer; required
        // for any unauthenticated schema operation.
        define('FOG_SCHEMA_INSTALL_TOKEN', '$installToken');
    }
    /**
     * Defines the service settings
     *
     * @return void
     */
    private static function _svcSetting()
    {
        define('UDPSENDERPATH', '/usr/local/sbin/udp-sender');
        define('MULTICASTINTERFACE', '${interface}');
        define('UDPSENDER_MAXWAIT', null);
    }
    /**
     * Initial values if fresh install are set here
     * NOTE: These values are only used on initial
     * installation to set the database values.
     * If this is an upgrade, they do not change
     * the values within the Database.
     * Please use FOG Configuration->FOG Settings
     * to change these values after everything is
     * setup.
     *
     * @return void
     */
    private static function _initSetting()
    {
        define('TFTP_HOST', \"${confighostip}\");
        define('TFTP_FTP_USERNAME', \"${username}\");
        define('TFTP_FTP_PASSWORD', '${password}');
        define('TFTP_PXE_KERNEL_DIR', \"${webdirdest}/service/ipxe/\");
        define('PXE_KERNEL', 'bzImage');
        define('PXE_KERNEL_RAMDISK', 275000);
        define('USE_SLOPPY_NAME_LOOKUPS', true);
        define('MEMTEST_KERNEL', 'memtest.bin');
        define('PXE_IMAGE', 'init.xz');
        define('STORAGE_HOST', \"${confighostip}\");
        define('STORAGE_FTP_USERNAME', \"${username}\");
        define('STORAGE_FTP_PASSWORD', '${password}');
        define('STORAGE_DATADIR', '${storageLocation}/');
        define('STORAGE_DATADIR_CAPTURE', '${storageLocationCapture}');
        define('STORAGE_BANDWIDTHPATH', '${webroot}status/bandwidth.php');
        define('STORAGE_INTERFACE', '${interface}');
        define('STORAGE_DEFAULT_CIDR', \"${storageDefaultCidr}\");
        define('CAPTURERESIZEPCT', 7);
        define('WEB_HOST', \"${confighostip}\");
        define('WEB_ROOT', '${webroot}');
        define('WOL_HOST', \"${confighostip}\");
        define('WOL_PATH', '/${webroot}wol/wol.php');
        define('WOL_INTERFACE', \"${interface}\");
        define('SNAPINDIR', \"${snapindir}/\");
        define('QUEUESIZE', '10');
        define('CHECKIN_TIMEOUT', 600);
        define('USER_MINPASSLENGTH', 4);
        define('NFS_ETH_MONITOR', \"${interface}\");
        define('UDPCAST_INTERFACE', \"${interface}\");
        // Must be an even number! recommended between 49152 to 65535
        define('UDPCAST_STARTINGPORT', 63100);
        define('FOG_MULTICAST_MAX_SESSIONS', 64);
        define('FOG_JPGRAPH_VERSION', '2.3');
        define('FOG_REPORT_DIR', './reports/');
        define('FOG_CAPTUREIGNOREPAGEHIBER', true);
        define('FOG_THEME', 'default/fog.css');
    }
}" > "${webdirdest}/lib/fog/config.class.php"
    errorStat $?
    dots "Creating paths file"
    # GH-850: hand the installer's $fogprogramdir to the PHP runtime so
    # FOG_BASE_DIR is no longer a hardcoded string in system.class.php.
    #
    # This is a separate file from config.class.php on purpose. FOG_BASE_DIR is
    # needed by Initiator BEFORE the class autoloader is registered (the boot
    # file-list cache lives under FOG_CACHE_DIR), and config.class.php is a
    # class -- it only loads lazily, far too late. A plain define-only include
    # is the one thing that can be pulled in that early.
    #
    # Regenerated on every install/upgrade. If it is missing (pre-850 install,
    # or a hand-copied web tree) the PHP side falls back to /opt/fog, which is
    # exactly the behaviour before this change.
    echo "<?php
/**
 * Filesystem paths chosen at install time.
 *
 * GENERATED BY THE FOG INSTALLER -- DO NOT EDIT.
 * Rewritten on every install/upgrade from \$fogprogramdir. To change the base
 * path, re-run the installer; editing this file alone will not move anything.
 *
 * Loaded by commons/init.php before the autoloader, so it must contain
 * defines and nothing else.
 */
define('FOG_BASE_DIR', '${fogprogramdir%/}');" > "${webdirdest}/commons/fogpaths.php"
    errorStat $?
    dots "Creating redirection index file"
    if [[ ! -f ${docroot}/index.php ]]; then
        echo "<?php
header('Location: ${webroot}index.php');
die();
?>" > ${docroot}/index.php && chown ${apacheuser}:${apacheuser} ${docroot}/index.php
        errorStat $?
    else
        echo "Skipped"
    fi
    downloadfiles
    if [[ $osid -eq 2 ]]; then
        php -m | grep mysqlnd >>$error_log 2>&1
        if [[ ! $? -eq 0 ]]; then
            phpenmod mysqlnd >>$error_log 2>&1
            if [[ ! $? -eq 0 ]]; then
                if [[ -e /etc/php${php_ver}/conf.d/mysqlnd.ini ]]; then
                    cp -f "/etc/php${php_ver}/conf.d/mysqlnd.ini" "/etc/php${php_ver}/mods-available/php${php_ver}-mysqlnd.ini" >>$error_log 2>&1
                    phpenmod mysqlnd >>$error_log 2>&1
                fi
            fi
        fi
    fi
    dots "Enabling $webserver and fpm services on boot"
    if [[ $osid -eq 2 ]]; then
        if [[ $systemctl == yes ]]; then
            systemctl is-enabled --quiet $webserver && true || systemctl enable $webserver >>$error_log 2>&1
            systemctl is-enabled --quiet $phpfpm && true || systemctl enable $phpfpm >>$error_log 2>&1
        else
            sysv-rc-conf $webserver on >>$error_log 2>&1
            sysv-rc-conf $phpfpm on >>$error_log 2>&1
        fi
    elif [[ $systemctl == yes ]]; then
        systemctl is-enabled --quiet $webserver php-fpm && true || systemctl enable $webserver php-fpm >>$error_log 2>&1
    elif [[ $osid -eq 3 ]]; then
        # Alpine's unit is versioned (php-fpm83, php-fpm84), which is exactly
        # what $phpfpm holds; a bare "php-fpm" matches nothing there.
        rc-update add $phpfpm >>$error_log 2>&1
        rc-update add $webserver >>$error_log 2>&1
    else
        chkconfig php-fpm on >>$error_log 2>&1
        chkconfig $webserver on >>$error_log 2>&1
    fi
    errorStat $?
    createSSLCA
    dots "Changing permissions on apache log files"
    chmod +rx $apachelogdir
    chmod +rx $apacheerrlog
    chmod +rx $apacheacclog
    chown -R ${apacheuser}:${apacheuser} $webdirdest
    touch $webdirdest/fog_login_accepted.log
    touch $webdirdest/fog_login_failed.log
    chown ${apacheuser}:${apacheuser} $webdirdest/fog_login_*.log
    chmod 0200 $webdirdest/fog_login_*.log
    errorStat $?
    [[ -d /var/www/html/ && ! -e /var/www/html/fog/ ]] && ln -s "$webdirdest" /var/www/html/
    [[ -d /var/www/ && ! -e /var/www/fog ]] && ln -s "$webdirdest" /var/www/
    chown -R ${apacheuser}:${apacheuser} "$webdirdest"
    chown -R ${username}:${apacheuser} "$webdirdest/service/ipxe"
}
downloadfiles() {
    local copypath=""
    dots "Downloading kernel, init and fog-client binaries"
    clientVer="$(awk -F\' /"define\('FOG_CLIENT_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"
    fosURL="https://github.com/FOGProject/fos/releases/download"
    fileversions=$(curl -sL -H "Accept: application/vnd.github+json" 'https://api.github.com/repos/FOGProject/fos/releases/latest' | jq '.tag_name, .body' | paste -sd '|')
    tag_name="$(echo $fileversions | awk -F'|' '{print $1}')"
    fileversion="$(echo $fileversions | awk -F'|' '{print $2}')"
    kern_version=$(echo -e $fileversion | sed -n 's/.*Linux kernel \([0-9.]*\).*/\1/p')
    build_version=$(echo -e $fileversion | sed -n 's/.*Buildroot \([0-9.]*\).*/\1/p')
    fosLatestURL="https://github.com/FOGProject/fos/releases/latest/download"
    fogclientURL="https://github.com/FOGProject/fog-client/releases/download"
    [[ ! -d ../tmp/  ]] && mkdir -p ../tmp/ >/dev/null 2>&1
    cwd=$(pwd)
    cd ../tmp/
    if [[ $version =~ ^[0-9]\.[0-9]\.[0-9]+$ ]]; then
        urls=( "${fosURL}/${version}/init.xz" "${fosURL}/${version}/init_32.xz" "${fosURL}/${version}/bzImage" "${fosURL}/${version}/bzImage32" "${fogclientURL}/${clientVer}/FOGService.msi" "${fogclientURL}/${clientVer}/SmartInstaller.exe" )
        urls+=( "${fosURL}/${version}/arm_init.cpio.gz" "${fosURL}/${version}/arm_Image" )
    else
        urls=( "${fosLatestURL}/init.xz" "${fosLatestURL}/init_32.xz" "${fosLatestURL}/bzImage" "${fosLatestURL}/bzImage32" "${fogclientURL}/${clientVer}/FOGService.msi" "${fogclientURL}/${clientVer}/SmartInstaller.exe" )
        urls+=( "${fosLatestURL}/arm_init.cpio.gz" "${fosLatestURL}/arm_Image" )
    fi
    for url in "${urls[@]}"; do
        checksum=1
        cnt=0
        filename=$(basename -- "$url")
        hashfile="${filename}.sha256"
        baseurl=$(dirname -- "$url")
        hashurl="${baseurl}/${hashfile}"
        # make sure we download the most recent hash file to start with
        if [[ -f $hashfile && ! $version =~ ^[0-9]\.[0-9]\.[0-9]+$ ]]; then
            rm -f $hashfile
            curl --silent -kOL $hashurl >>$error_log 2>&1
        fi
        while [[ $checksum -ne 0 && $cnt -lt 10 ]]; do
            [[ -f $hashfile ]] && sha256sum -c $hashfile >>$error_log 2>&1
            checksum=$?
            if [[ $checksum -ne 0 ]]; then
                curl --silent -kOL $url >>$error_log
                curl --silent -kOL $hashurl >>$error_log
            fi
            let cnt+=1
        done
        if [[ $checksum -ne 0 ]]; then
            echo " * Could not download $filename properly"
            [[ -z $exitFail ]] && exit 1
        fi
    done
    echo "Done"
    dots "Copying binaries to destination paths"
    cp -vf ${copypath}bzImage ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $kern_version ${webdirdest}/service/ipxe/bzImage >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/bzImage >>$error_log 2>&1 || errorStat $?
    cp -vf ${copypath}bzImage32 ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $kern_version ${webdirdest}/service/ipxe/bzImage32 >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/bzImage32 >>$error_log 2>&1 || errorStat $?
    cp -vf ${copypath}arm_Image ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $kern_version ${webdirdest}/service/ipxe/arm_Image >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/arm_Image >>$error_log 2>&1 || errorStat $?
    cp -vf ${copypath}init.xz ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $build_version ${webdirdest}/service/ipxe/init.xz >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/init.xz >>$error_log 2>&1 || errorStat $?
    cp -vf ${copypath}init_32.xz ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $build_version ${webdirdest}/service/ipxe/init_32.xz >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/init_32.xz >>$error_log 2>&1 || errorStat $?
    cp -vf ${copypath}arm_init.cpio.gz ${webdirdest}/service/ipxe/ >>$error_log 2>&1 || errorStat $?
    attr -s version -V $build_version ${webdirdest}/service/ipxe/arm_init.cpio.gz >>$error_log 2>&1 || errorStat $?
    attr -s tag_name -V $tag_name ${webdirdest}/service/ipxe/arm_init.cpio.gz >>$error_log 2>&1 || errorStat $?
    cp -vf ${copypath}FOGService.msi ${copypath}SmartInstaller.exe ${webdirdest}/client/ >>$error_log 2>&1
    errorStat $?
    cd $cwd
    _ensureSecureBootKeys
    _ensureSecureBootPlatformKeys
    _resignKernels
    _installSecureBootSigner
    _publishSecureBootKit
    _publishSecureBootAuthVars
}
# Copy an admin-supplied Secure Boot pair somewhere the installer cannot
# destroy, and point $secureBootKey/$secureBootCert at the copy.
#
# The gap this closes: --secure-boot-key/--secure-boot-cert are persisted to
# .fogsettings verbatim and _ensureSecureBootKeys() then trusts that path
# forever, but nothing ever copies the file anywhere. An admin who parks the
# pair under $webdirdest -- not unreasonable, it is where the enrolment kit is
# published -- loses it to configureHttpd()'s rm -rf $webdirdest, in the SAME
# run that first accepted the flags, before _resignKernels() ever reads it.
#
# Copied to admin-MOK.* rather than MOK.*, deliberately. MOK.key/MOK.pem are
# where _ensureSecureBootKeys() keeps FOG's OWN generated pair, and that pair
# is never regenerated precisely because every client that already enrolled it
# would be stranded. Writing an admin's key over that path would destroy it
# with no backup and no way back. Continuity across later runs comes from
# .fogsettings holding the new path, not from reusing the filename.
#
# The original file the admin pointed at is never modified -- this only
# decides which copy gets used from here on. Idempotent: once .fogsettings
# records the copy, every later run sees a path already under
# $fogprogramdir/secureboot and does nothing.
preserveSecureBootAdminFiles() {
    [[ -z $secureBootKey || -z $secureBootCert ]] && return 0
    local keydir="${fogprogramdir}/secureboot"
    local destkey="${keydir}/admin-MOK.key"
    local destcert="${keydir}/admin-MOK.pem"
    local st=0

    # Already somewhere this installer never deletes -- including FOG's own
    # generated pair, which must be left exactly where it is.
    case "$(readlink -f "$secureBootKey" 2>/dev/null)" in
        "$(readlink -f "$keydir" 2>/dev/null)"/*) return 0 ;;
    esac

    dots "Preserving admin-supplied Secure Boot key"
    mkdir -p "$keydir" >>$error_log 2>&1 || st=1
    chown root:root "$keydir" >>$error_log 2>&1
    chmod 0700 "$keydir" >>$error_log 2>&1
    cp -f "$secureBootKey" "$destkey" >>$error_log 2>&1 || st=1
    cp -f "$secureBootCert" "$destcert" >>$error_log 2>&1 || st=1
    if [[ $st -ne 0 ]]; then
        echo "Failed"
        echo " * Could not copy the Secure Boot signing pair into ${keydir}."
        echo " * Leaving --secure-boot-key/--secure-boot-cert pointed at the"
        echo "   originals. If either lives under ${webdirdest}, MOVE IT NOW --"
        echo "   the web tree is rebuilt later in this run. See $error_log."
        return 0
    fi
    chown root:root "$destkey" "$destcert" >>$error_log 2>&1
    # Key restricted, certificate public by design -- it is the thing handed
    # out for enrolment. Mirrors _ensureSecureBootKeys()'s own permissions.
    chmod 0600 "$destkey" >>$error_log 2>&1
    chmod 0644 "$destcert" >>$error_log 2>&1
    secureBootKey="$destkey"
    secureBootCert="$destcert"
    errorStat 0
}
# Generate the Secure Boot signing key when the admin has not supplied one.
#
# Signing used to require --secure-boot-key/--secure-boot-cert, which meant it
# was off unless someone already knew to ask for it -- so on a stock server the
# Secure Boot page had no fingerprint to show and no enrolment kit to hand out,
# and the feature was effectively invisible. Generating a key by default makes
# it present everywhere; enrolling it on a client is still a deliberate act by
# someone physically at the machine, so defaulting this on grants no trust by
# itself.
#
# The key NEVER regenerates once it exists. A fresh key silently invalidates
# enrolment on every machine that already trusted the old one, and nothing
# surfaces that until a client fails to boot -- long after the install that
# caused it. So an existing pair is always reused, and --recreate-keys
# deliberately does not reach this.
_ensureSecureBootKeys() {
    local keydir="${fogprogramdir}/secureboot"
    local key="${keydir}/MOK.key"
    local cert="${keydir}/MOK.pem"

    # Explicit opt-out. Left unset rather than half-set, so every downstream
    # function's existing "no key configured" branch does the right thing.
    # Defaulted and string-compared on purpose: an unset $secureboot under
    # `-eq` is arithmetic, evaluates empty as 0, and would silently opt every
    # caller that reaches here without config.sh straight out of the feature.
    if [[ ${secureboot:-1} == 0 ]]; then
        secureBootKey=""
        secureBootCert=""
        return 0
    fi
    # An admin-supplied pair always wins and is never touched or overwritten.
    [[ -n $secureBootKey && -n $secureBootCert ]] && return 0
    if [[ -f $key && -f $cert ]]; then
        secureBootKey="$key"
        secureBootCert="$cert"
        return 0
    fi

    dots "Generating Secure Boot signing key"
    mkdir -p "$keydir" >>$error_log 2>&1
    # 0700 root:root. The web user signs through the fog-sign-kernel sudo
    # helper and must never be able to read the key itself -- that separation
    # is the whole reason the helper takes no arguments. $fogprogramdir is
    # never inside $webdirdest, so nothing here is web-reachable either.
    chown root:root "$keydir" >>$error_log 2>&1
    chmod 0700 "$keydir" >>$error_log 2>&1
    # Written as a config file rather than passed with -addext: -addext needs
    # OpenSSL 1.1.1+, and the older RHEL variants this installer still supports
    # ship 1.0.2, where it fails with an unhelpful usage error. The same reason
    # createSSLCA() writes req.cnf/ca.cnf instead of using -addext.
    cat > "${keydir}/mok.cnf" << EOF
[ req ]
distinguished_name = req_dn
prompt             = no
x509_extensions    = v3_mok

[ req_dn ]
CN = FOG Project Secure Boot Signing

[ v3_mok ]
basicConstraints = critical,CA:FALSE
extendedKeyUsage = codeSigning
subjectKeyIdentifier = hash
EOF
    if ! openssl req -x509 -new -nodes -newkey rsa:2048 -sha256 -days 3650 \
            -config "${keydir}/mok.cnf" -keyout "$key" -out "$cert" \
            >>$error_log 2>&1; then
        echo "Failed"
        echo " * Could not generate a Secure Boot signing key. The FOS kernels"
        echo "   will be left unsigned and Secure Boot clients will not boot."
        echo "   See $error_log."
        rm -f "$key" "$cert" >>$error_log 2>&1
        secureBootKey=""
        secureBootCert=""
        return 0
    fi
    chown root:root "$key" "$cert" >>$error_log 2>&1
    chmod 0600 "$key" >>$error_log 2>&1
    # The certificate is public by design -- it is the thing published in the
    # enrolment kit -- so only the key is restricted.
    chmod 0644 "$cert" >>$error_log 2>&1
    secureBootKey="$key"
    secureBootCert="$cert"
    echo "Done"
}
# Generate this server's Secure Boot PLATFORM keys (PK and KEK).
#
# Separate from _ensureSecureBootKeys because these are a different kind of key
# doing a different job. MOK.key signs FOS kernels. PK/KEK sign nothing that ever
# executes -- they exist only to authorise updates to a client's own Secure Boot
# databases, which is what makes the automatic (Setup Mode) enrolment path in
# fos ADR-0009 possible at all.
#
# Why the server needs its own PK when a client in Setup Mode enforces nothing:
# once the client leaves Setup Mode it is in User Mode with OUR PK, and from that
# point the UEFI spec requires a KEK-signed update to touch db and a PK-signed
# update to touch KEK. Holding those keys is what lets this same server push a db
# change to an already-enrolled fleet later without another firmware trip. A
# server that enrolled a throwaway PK would strand every client it enrolled.
#
# Like the MOK key, these NEVER regenerate once they exist -- a new PK is not
# accepted by any client already carrying the old one, and the failure surfaces
# as an unbootable machine long after the install that caused it.
_ensureSecureBootPlatformKeys() {
    local keydir="${fogprogramdir}/secureboot"
    local pkKey="${keydir}/PK.key"
    local pkCert="${keydir}/PK.pem"
    local kekKey="${keydir}/KEK.key"
    local kekCert="${keydir}/KEK.pem"
    local subject

    secureBootPKKey=""
    secureBootPKCert=""
    secureBootKEKKey=""
    secureBootKEKCert=""

    # No signing key means the whole feature is opted out; there is nothing for
    # a platform key to authorise.
    [[ -z $secureBootKey || -z $secureBootCert ]] && return 0

    if [[ -f $pkKey && -f $pkCert && -f $kekKey && -f $kekCert ]]; then
        secureBootPKKey="$pkKey"
        secureBootPKCert="$pkCert"
        secureBootKEKKey="$kekKey"
        secureBootKEKCert="$kekCert"
        return 0
    fi

    dots "Generating Secure Boot platform keys"
    mkdir -p "$keydir" >>$error_log 2>&1
    chown root:root "$keydir" >>$error_log 2>&1
    chmod 0700 "$keydir" >>$error_log 2>&1

    # Named after the server so an admin standing at a client's firmware screen
    # can tell WHICH FOG server owns the platform key it is now carrying. With a
    # generic CN, a site running two FOG servers has no way to tell them apart
    # from the machine that got enrolled.
    subject="FOG Project (${hostname:-$(hostname)})"
    # 4096-bit and no extendedKeyUsage: these are trust anchors in a firmware
    # database, not code-signing certificates, and some firmware rejects a PK
    # carrying a codeSigning EKU. 3650 days matches the MOK key -- an expired PK
    # does not stop a client booting (UEFI does not check validity dates on db
    # entries) but it does confuse tooling.
    if ! openssl req -x509 -new -nodes -newkey rsa:4096 -sha256 -days 3650 \
            -subj "/CN=${subject} Platform Key/" \
            -keyout "$pkKey" -out "$pkCert" >>$error_log 2>&1 ||
       ! openssl req -x509 -new -nodes -newkey rsa:4096 -sha256 -days 3650 \
            -subj "/CN=${subject} Key Exchange Key/" \
            -keyout "$kekKey" -out "$kekCert" >>$error_log 2>&1; then
        echo "Failed"
        echo " * Could not generate the Secure Boot platform keys. Automatic"
        echo "   enrolment will be unavailable; the MOK paths are unaffected."
        echo "   See $error_log."
        rm -f "$pkKey" "$pkCert" "$kekKey" "$kekCert" >>$error_log 2>&1
        return 0
    fi
    chown root:root "$pkKey" "$pkCert" "$kekKey" "$kekCert" >>$error_log 2>&1
    chmod 0600 "$pkKey" "$kekKey" >>$error_log 2>&1
    chmod 0644 "$pkCert" "$kekCert" >>$error_log 2>&1
    secureBootPKKey="$pkKey"
    secureBootPKCert="$pkCert"
    secureBootKEKKey="$kekKey"
    secureBootKEKCert="$kekCert"
    echo "Done"
}
# Publish the MOK enrolment kit under the web root.
#
# Only the *certificate* is published -- it is public by design, and is the
# thing you are meant to distribute. The private key stays where the admin put
# it and is never copied anywhere near the web root; that separation is the one
# thing in this feature that must not be got wrong.
_publishSecureBootKit() {
    local kitdir="${webdirdest}/service/secureboot"

    if [[ -z $secureBootCert ]]; then
        rm -rf "$kitdir" >>$error_log 2>&1
        return 0
    fi

    dots "Publishing Secure Boot enrolment kit"
    mkdir -p "$kitdir" >>$error_log 2>&1
    # A DER copy of the certificate is what mokutil wants. Accept a PEM cert
    # too, since openssl is happy to produce either and admins mix them up.
    if openssl x509 -in "$secureBootCert" -inform der -noout >/dev/null 2>&1; then
        cp -f "$secureBootCert" "${kitdir}/MOK.der" >>$error_log 2>&1
    elif openssl x509 -in "$secureBootCert" -outform der -out "${kitdir}/MOK.der" >>$error_log 2>&1; then
        :
    else
        echo "Failed"
        echo " * Could not read $secureBootCert as a certificate."
        return 0
    fi
    cp -f ../packages/secureboot/fog-enroll-mok.sh "${kitdir}/" >>$error_log 2>&1
    cp -f ../packages/secureboot/fog-enroll-mok.desktop "${kitdir}/" >>$error_log 2>&1
    # MokManager, for the "Enroll Secure Boot Key" PXE menu item. BootMenu
    # chains to it over $_booturl, which is the WEB root
    # (http://<server>/fog/service) -- but downloadipxesecureboot stages these
    # binaries under $tftpdirdst/secureboot, which the web server does not
    # serve. Without this copy the menu item resolves to a 403/404 on every
    # architecture and falls straight into its own error branch.
    #
    # Copied rather than linked: the TFTP tree may be on a different
    # filesystem, and it is two small binaries.
    local mmsrc="${tftpdirdst%/}/secureboot"
    if [[ -f ${mmsrc}/mmx64.efi ]]; then
        cp -f "${mmsrc}/mmx64.efi" "${kitdir}/" >>$error_log 2>&1
    fi
    if [[ -f ${mmsrc}/arm64-efi/mmaa64.efi ]]; then
        mkdir -p "${kitdir}/arm64-efi" >>$error_log 2>&1
        cp -f "${mmsrc}/arm64-efi/mmaa64.efi" "${kitdir}/arm64-efi/" >>$error_log 2>&1
    fi
    # Keep the directory from being browsable, matching service/ipxe.
    echo '<?php header("HTTP/1.1 404 Not Found");' > "${kitdir}/index.php"
    chmod 0644 "${kitdir}"/MOK.der "${kitdir}"/*.desktop "${kitdir}"/index.php >>$error_log 2>&1
    # Guarded rather than globbed blind: an HTTPS install stages no Secure Boot
    # binaries at all (downloadipxesecureboot skips it), so an unguarded
    # chmod would log a "No such file" for every run on those servers.
    [[ -f ${kitdir}/mmx64.efi ]] && chmod 0644 "${kitdir}/mmx64.efi" >>$error_log 2>&1
    [[ -f ${kitdir}/arm64-efi/mmaa64.efi ]] && chmod 0644 "${kitdir}/arm64-efi/mmaa64.efi" >>$error_log 2>&1
    chmod 0755 "${kitdir}/fog-enroll-mok.sh" >>$error_log 2>&1
    chown -R "${apacheuser}":"${apacheuser}" "$kitdir" >>$error_log 2>&1
    echo "Done"
}
# Build and publish the signed PK/KEK/db variable updates.
#
# These are what a client in Setup Mode writes to enrol this server's
# certificate automatically -- no MokManager, no password, no USB stick. See fos
# ADR-0009 for why Setup Mode is the only path that scales.
#
# Runs AFTER _publishSecureBootKit deliberately: the kit's `chown -R` would
# otherwise be the last thing to touch the directory, and the ordering of who
# owns what would depend on which function happened to run last. This function
# owns the permissions on the files it writes.
#
# Nothing published here is secret -- .auth blobs are public certificates plus
# signatures over them. The private keys stay in $fogprogramdir/secureboot.
_publishSecureBootAuthVars() {
    local kitdir="${webdirdest}/service/secureboot"
    local msdst="${fogprogramdir}/secureboot/mscerts"
    local helper="${fogprogramdir}/bin/fog-build-sb-authvars"
    local conf="${fogprogramdir}/.fog-secureboot"

    # No platform keys means no automatic path. Clear any blobs from a previous
    # install rather than leaving stale ones a client would happily enrol: an
    # .auth signed by a key this server no longer holds enrols a platform the
    # server can never update again.
    if [[ -z $secureBootPKKey || -z $secureBootKEKKey ]]; then
        rm -f "$helper" "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
        return 0
    fi

    dots "Publishing Secure Boot variable updates"
    if ! command -v cert-to-efi-sig-list >/dev/null 2>&1 ||
       ! command -v sign-efi-sig-list >/dev/null 2>&1; then
        echo "Skipped"
        echo " * efitools is not installed, so the automatic Secure Boot"
        echo "   enrolment blobs were not built. Install efitools and re-run the"
        echo "   installer. The MOK enrolment paths are unaffected."
        rm -f "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
        return 0
    fi

    # Microsoft's published CA certificates, staged root-owned next to the keys
    # rather than under the web root. They are public, but the builder reads
    # them to decide what a client will trust forever after, so a web-writable
    # copy would be a way to influence that decision from the web tier.
    mkdir -p "$msdst" >>$error_log 2>&1
    cp -f ../packages/secureboot/mscerts/* "$msdst"/ >>$error_log 2>&1
    chown -R root:root "$msdst" >>$error_log 2>&1
    chmod 0755 "$msdst" >>$error_log 2>&1
    chmod 0644 "$msdst"/* >>$error_log 2>&1

    # 0700 and no sudoers rule, unlike fog-sign-kernel: nothing but root ever
    # runs this, so the web user should not even be able to execute it.
    mkdir -p "${fogprogramdir}/bin" >>$error_log 2>&1
    install -o root -g root -m 0700 ../packages/secureboot/fog-build-sb-authvars \
        "$helper" >>$error_log 2>&1 || {
        echo "Failed"
        return 0
    }
    sed -i "s|^CONF=.*|CONF=\"${conf}\"|" "$helper" >>$error_log 2>&1
    if ! grep -qxF "CONF=\"${conf}\"" "$helper"; then
        echo "Failed"
        echo " * Could not set the config path in $helper."
        return 0
    fi

    mkdir -p "$kitdir" >>$error_log 2>&1
    if ! "$helper" >>$error_log 2>&1; then
        echo "Failed"
        echo " * Could not build the Secure Boot variable updates, so automatic"
        echo "   enrolment will be unavailable. The MOK enrolment paths are"
        echo "   unaffected. See $error_log."
        rm -f "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
        return 0
    fi
    chown "${apacheuser}":"${apacheuser}" "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
    chmod 0644 "${kitdir}"/{PK,KEK,db}.auth >>$error_log 2>&1
    echo "Done"
}
# Normalise --secure-boot-cert to PEM and echo the path.
#
# sbsign and sbverify read certificates with PEM_read_bio_X509 and reject DER
# outright:
#
#   $ sbsign --key MOK.priv --cert MOK.der --output out.efi in.efi
#   Can't load certificate from file 'MOK.der'
#   error:0480006C:PEM routines:get_name:no start line ... Expecting: CERTIFICATE
#
# mokutil and MokManager want the opposite -- DER. So an admin following any
# Secure Boot guide ends up holding one of each, with nothing telling them which
# tool takes which, and handing the wrong one to --secure-boot-cert fails at
# signing time with an OpenSSL error that says nothing about the format.
#
# Convert once here rather than pushing the distinction onto the user: the flag
# accepts either, _resignKernels and the signing helper get PEM, and
# _publishSecureBootKit still converts to DER for enrolment.
_secureBootCertPem() {
    local pem="${fogprogramdir}/.fog-secureboot.pem"
    [[ -z $secureBootCert ]] && return 1
    mkdir -p "$fogprogramdir" >>$error_log 2>&1
    if openssl x509 -in "$secureBootCert" -inform pem -noout >/dev/null 2>&1; then
        cp -f "$secureBootCert" "$pem" >>$error_log 2>&1 || return 1
    elif ! openssl x509 -in "$secureBootCert" -inform der -outform pem \
            -out "$pem" >>$error_log 2>&1; then
        return 1
    fi
    # World-readable on purpose: a certificate is public, and it is the private
    # key next to it that the 0600 config protects.
    chown root:root "$pem" >>$error_log 2>&1
    chmod 0644 "$pem" >>$error_log 2>&1
    echo "$pem"
}
# Re-sign the FOS kernels for UEFI Secure Boot.
#
# The kernels above are downloaded unsigned on every install AND every upgrade,
# so without this a working Secure Boot setup silently stops booting the moment
# someone updates FOG. That is the single most common way the setup breaks.
#
# No-op unless both secureBootKey and secureBootCert are configured. Missing
# sbsign is a warning rather than a failure: the rest of the install is fine and
# aborting it would be a worse outcome than an unsigned kernel the admin is told
# about.
# Install the root-only signing helper the web UI calls through sudo.
#
# The Kernel Update page runs as the web user, which must never be able to read
# the signing key -- a web compromise would otherwise walk off with it. So the
# key stays root-only and the web user gets a single, argument-less command it
# may run as root.
#
# Removes the helper, its config and the sudoers rule again when signing is
# turned off, so disabling the feature actually disables the privilege.
_installSecureBootSigner() {
    # $fogprogramdir, not a "/opt/fog" literal: GH-850 made the base path
    # installer-driven, and hardcoding it here would scatter the helper, its
    # config and the staging directory back into /opt/fog on a server installed
    # anywhere else.
    local bindir="${fogprogramdir}/bin"
    local helper="${bindir}/fog-sign-kernel"
    local conf="${fogprogramdir}/.fog-secureboot"
    local stagedir="${fogprogramdir}/secureboot-staging"
    local sudoersfile="/etc/sudoers.d/fog-secureboot"
    local certpem

    if [[ -z $secureBootKey || -z $secureBootCert ]]; then
        rm -f "$helper" "$conf" "$sudoersfile" >>$error_log 2>&1
        return 0
    fi

    dots "Installing Secure Boot signing helper"
    certpem=$(_secureBootCertPem) || {
        echo "Failed"
        echo " * Could not read $secureBootCert as a certificate (PEM or DER)."
        return 0
    }
    mkdir -p "$bindir" >>$error_log 2>&1
    install -o root -g root -m 0755 ../packages/secureboot/fog-sign-kernel "$helper" >>$error_log 2>&1 || {
        echo "Failed"
        return 0
    }
    # Point the helper at this install's config. It takes no arguments on
    # purpose -- that is what stops a compromised web server naming its own key
    # -- so the path has to be baked in here rather than passed at call time.
    # Quoted: $fogprogramdir may contain a space, and `CONF=/a/fog custom/x`
    # assigns only "/a/fog" and then tries to RUN "custom/x". bash -n does not
    # catch that -- it is valid syntax, just not what anyone meant.
    sed -i "s|^CONF=.*|CONF=\"${conf}\"|" "$helper" >>$error_log 2>&1
    if ! grep -qxF "CONF=\"${conf}\"" "$helper"; then
        echo "Failed"
        echo " * Could not set the config path in $helper."
        return 0
    fi
    # Root-owned, root-readable only: the web user learns nothing about where
    # the key lives, and cannot rewrite these paths to point somewhere else.
    # SECUREBOOT_CERT is the normalised PEM -- sbsign cannot read DER.
    #
    # The PK/KEK/mscerts/authvars lines are for fog-build-sb-authvars, not for
    # fog-sign-kernel, which ignores them. They live in the same file because
    # this function is the only writer of it -- a second config would have to be
    # kept in step with this one, and the failure mode of them drifting apart is
    # a signing helper and a variable builder disagreeing about which key is the
    # server's. Written unconditionally-or-not-at-all: an empty value here makes
    # the builder refuse with "config is incomplete", which is the right answer
    # when the platform keys could not be generated.
    {
        echo "SECUREBOOT_KEY=${secureBootKey}"
        echo "SECUREBOOT_CERT=${certpem}"
        echo "SECUREBOOT_STAGING=${stagedir}"
        echo "SECUREBOOT_PK_KEY=${secureBootPKKey}"
        echo "SECUREBOOT_PK_CERT=${secureBootPKCert}"
        echo "SECUREBOOT_KEK_KEY=${secureBootKEKKey}"
        echo "SECUREBOOT_KEK_CERT=${secureBootKEKCert}"
        echo "SECUREBOOT_MSCERTS=${fogprogramdir}/secureboot/mscerts"
        echo "SECUREBOOT_AUTHVARS=${webdirdest}/service/secureboot"
    } > "$conf"
    chown root:root "$conf" >>$error_log 2>&1
    chmod 0600 "$conf" >>$error_log 2>&1

    # The web user owns only the staging directory -- it has to write the
    # downloaded kernel there and read the signed result back.
    mkdir -p "$stagedir" >>$error_log 2>&1
    chown "${apacheuser}":"${apacheuser}" "$stagedir" >>$error_log 2>&1
    chmod 0750 "$stagedir" >>$error_log 2>&1

    # Validate before installing: a malformed sudoers drop-in breaks sudo for
    # the whole machine, which is a far worse outcome than no signing.
    echo "${apacheuser} ALL=(root) NOPASSWD: ${helper}" > "${sudoersfile}.tmp"
    chmod 0440 "${sudoersfile}.tmp" >>$error_log 2>&1
    if visudo -cqf "${sudoersfile}.tmp" >>$error_log 2>&1; then
        mv -f "${sudoersfile}.tmp" "$sudoersfile" >>$error_log 2>&1
        chown root:root "$sudoersfile" >>$error_log 2>&1
        echo "Done"
    else
        rm -f "${sudoersfile}.tmp" >>$error_log 2>&1
        echo "Failed"
        echo " * Refusing to install an invalid sudoers rule; the web Kernel"
        echo "   Update page will download unsigned kernels. See $error_log."
    fi
}
_resignKernels() {
    [[ -z $secureBootKey || -z $secureBootCert ]] && return 0
    if ! command -v sbsign >/dev/null 2>&1 || ! command -v sbverify >/dev/null 2>&1; then
        echo " * WARNING: Secure Boot signing configured but sbsign/sbverify are not installed."
        echo "   Install sbsigntool (Debian/Ubuntu) or sbsigntools (RHEL/Fedora)"
        echo "   and re-run the installer, or Secure Boot clients will not boot."
        return 0
    fi
    dots "Signing FOS kernels for Secure Boot"
    local kernel kpath failed=0 certpem
    # sbsign/sbverify take PEM only; the admin may well have handed us the DER
    # copy that mokutil wanted. See _secureBootCertPem().
    certpem=$(_secureBootCertPem) || {
        echo "Failed"
        echo " * Could not read $secureBootCert as a certificate (PEM or DER)."
        echo "   Secure Boot clients will not boot until this is fixed."
        return 0
    }
    for kernel in bzImage bzImage32 arm_Image; do
        kpath="${webdirdest}/service/ipxe/${kernel}"
        [[ -f $kpath ]] || continue
        # Already carrying our signature means nothing was re-downloaded since
        # the last run, so there is nothing to do. Skipping here is what keeps a
        # second invocation from stacking a second signature on the same image.
        sbverify --cert "$certpem" "$kpath" >/dev/null 2>&1 && continue
        # Otherwise this is a freshly downloaded, unsigned kernel. Snapshot it,
        # because sbsign will not cleanly re-sign an already-signed image and the
        # next run needs an unsigned original to work from. The snapshot has to
        # be refreshed every time rather than kept forever, or an upgrade would
        # re-sign the *previous* version over the new one.
        cp -af "$kpath" "${kpath}.unsigned" >>$error_log 2>&1
        if sbsign --key "$secureBootKey" --cert "$certpem" \
                --output "$kpath" "${kpath}.unsigned" >>$error_log 2>&1; then
            chown "${username}" "$kpath" >>$error_log 2>&1
        else
            failed=1
        fi
    done
    if [[ $failed -ne 0 ]]; then
        echo "Failed"
        echo " * At least one kernel could not be signed. See $error_log."
        echo "   Secure Boot clients will not boot until this is fixed."
        return 0
    fi
    echo "Done"
}
# The architecture -> boot-file mapping below intentionally mirrors the ISC
# "class" blocks in the ISC branch of configureDHCP(). Keep the two in sync.
# Hoisted into a helper so the live Kea config (configureKeaDHCP) and the
# copy-ready sample (writeKeaSample) can never drift apart.
_keaBaseClasses() {
    cat <<'EOFCLS'
        {
            "name": "FOG-Legacy-BIOS",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00000'",
            "boot-file-name": "undionly.kkpxe"
        },
        {
            "name": "FOG-UEFI-32-2",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00002'",
            "boot-file-name": "i386-efi/snponly.efi"
        },
        {
            "name": "FOG-UEFI-32-1",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00006'",
            "boot-file-name": "i386-efi/snponly.efi"
        },
        {
            "name": "FOG-UEFI-64-1",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00007'",
            "boot-file-name": "snponly.efi"
        },
        {
            "name": "FOG-UEFI-64-2",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00008'",
            "boot-file-name": "snponly.efi"
        },
        {
            "name": "FOG-UEFI-64-3",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00009'",
            "boot-file-name": "snponly.efi"
        },
        {
            "name": "FOG-UEFI-ARM64",
            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00011'",
            "boot-file-name": "arm64-efi/snponly.efi"
        },
        {
            "name": "FOG-Surface-Pro-4",
            "test": "substring(option[60].hex,0,32) == 'PXEClient:Arch:00007:UNDI:003016'",
            "boot-file-name": "snponly.efi"
        }
EOFCLS
}
# A Secure Boot class, emitted commented out.
#
# Still commented out, but for one reason now rather than two: DHCP option 93
# carries the client architecture and nothing else, so a request cannot tell us
# whether Secure Boot is on. A site has to opt specific machines in.
#
# The second reason is gone. This used to point at ipxe-shimx64.efi because
# upstream published only an all-drivers signed iPXE -- the build that takes the
# NIC over from the firmware and hangs on some hardware -- and there was no
# signed snponly equivalent. ipxe/ipxe#1776 closed 2026-08-02 and iPXE 2.0.0
# ships a signed x86_64-sb/snponly.efi, staged by fog-ipxe since v2.0.0-fog.3.
# So this now matches what FOG serves every other UEFI client: snponly.
#
# Why the boot file names the shim and not the loader: ipxe/shim carries a
# fork-only patch (automatic_next_path(), ipxe/shim 1b02ba2c) that strips a
# "-shim[arch]" infix from the path it was ITSELF fetched from and loads that,
# out of the same directory. So snponly-shimx64.efi fetches snponly.efi and
# ipxe-shimx64.efi fetches ipxe.efi -- from one signed binary staged under both
# names. Do not conclude from `strings` that the loader is hardcoded to
# ipxe.efi; that is only the DEFAULT_LOADER the patch hooks.
#
# If this chain loads but the network never comes up, the firmware's own UEFI
# SNP is at fault -- switch the name to secureboot/ipxe-shimx64.efi for iPXE's
# built-in drivers. That is the fallback the Secure Boot config page documents,
# and it is purely a DHCP change with nothing to rename server-side.
#
# The binaries are staged at $tftpdir/secureboot by downloadipxesecureboot()
# on every install, so the path below always exists.
_keaSecureBootClassCommented() {
    cat <<'EOFSBC'
#        Secure Boot clients. Uncomment, add a leading comma to the entry
#        above, and narrow the test to the machines whose firmware trusts this
#        server's certificate -- by subnet, MAC or a client class of your own.
#        {
#            "name": "FOG-UEFI-64-SecureBoot",
#            "test": "substring(option[60].hex,0,20) == 'PXEClient:Arch:00007'",
#            "boot-file-name": "secureboot/snponly-shimx64.efi"
#        }
EOFSBC
}
_keaAppleClass() {
    cat <<'EOFAPL'
        {
            "name": "FOG-Apple-Intel-Netboot",
            "test": "substring(option[60].text,0,14) == 'AAPLBSDPC/i386'",
            "boot-file-name": "snponly.efi",
            "option-data": [
                { "code": 43, "csv-format": false, "data": "01:01:01:04:02:80:00:07:04:81:00:05:2a:09:0D:81:00:05:2a:08:69:50:58:45:2d:46:4f:47" }
            ]
        }
EOFAPL
}
_writeKeaConfig() {
    # $1 = target file, $2 = client-classes block. Reads $interface, $ipaddress,
    # $network, $cidr, $startrange, $endrange and $optdata from the caller's scope.
    cat > "$1" <<EOFKEA
{
    "Dhcp4": {
        "interfaces-config": { "interfaces": [ "$interface" ] },
        "lease-database": { "type": "memfile", "lfc-interval": 3600 },
        "valid-lifetime": 21600,
        "max-valid-lifetime": 43200,
        "next-server": "$ipaddress",
        "option-data": [
            { "name": "tftp-server-name", "data": "$ipaddress" }
        ],
        "subnet4": [
            {
                "id": 1,
                "subnet": "$network/$cidr",
                "pools": [ { "pool": "$startrange - $endrange" } ],
                "option-data": [
$optdata
                ]
            }
        ],
        "client-classes": [
$2
        ]
    }
}
EOFKEA
}
configureKeaDHCP() {
    local cidr=$(mask2cidr $submask)
    local target="$dhcpconfig"
    local tmp="${target}.fogtmp"
    [[ -d $(dirname "$target") ]] || mkdir -p "$(dirname "$target")" >>$error_log 2>&1
    [[ -f $target ]] && mv -fv "$target" "${target}.${timestamp}" >>$error_log 2>&1
    local optdata="                { \"name\": \"subnet-mask\", \"data\": \"$submask\" }"
    [[ $(validip $routeraddress) -eq 0 ]] && optdata="${optdata},
                { \"name\": \"routers\", \"data\": \"$routeraddress\" }"
    [[ $(validip $dnsaddress) -eq 0 ]] && optdata="${optdata},
                { \"name\": \"domain-name-servers\", \"data\": \"$dnsaddress\" }"
    local baseclasses
    baseclasses=$(_keaBaseClasses)
    local appleclass
    appleclass=$(_keaAppleClass)
    # Tier 1: base classes must validate or we refuse to start a broken server.
    _writeKeaConfig "$target" "$baseclasses"
    if [[ ! -s $target ]]; then
        echo "Failed"
        echo "Kea base configuration could not be written to $target (verify $(dirname "$target") is a writable directory); see $error_log"
        return 1
    fi
    if command -v kea-dhcp4 >/dev/null 2>&1; then
        if ! kea-dhcp4 -t "$target" >>$error_log 2>&1; then
            echo "Failed"
            echo "Kea base configuration failed validation (kea-dhcp4 -t); see $error_log"
            return 1
        fi
        # Tier 2: best-effort Apple BSDP; drop if Kea rejects it.
        _writeKeaConfig "$tmp" "${baseclasses},
${appleclass}"
        if kea-dhcp4 -t "$tmp" >>$error_log 2>&1; then
            mv -f "$tmp" "$target"
        else
            rm -f "$tmp"
            echo ""
            echo " * Note: Apple Intel netboot (BSDP) is not supported under Kea and was skipped"
        fi
    else
        echo " * Warning: kea-dhcp4 not found; wrote config without validation" >>$error_log 2>&1
    fi
    diffconfig "$target"
    return 0
}
writeKeaSample() {
    # For admins who run a dedicated/external Kea DHCP server (FOG is NOT hosting
    # DHCP): drop a ready-to-copy kea-dhcp4.conf next to the FOG web root so they
    # have a working starting point instead of hand-writing one. Not activated and
    # no service is touched here -- it is a reference file for their DHCP server.
    local target="${webdirdest%/}/kea-dhcp4.conf.fog-sample"
    [[ -z $webdirdest ]] && target="/etc/kea/kea-dhcp4.conf.fog-sample"
    [[ -d $(dirname "$target") ]] || return 0
    local sampleip
    sampleip=$(ip -4 -o addr show $interface | awk -F'([ /])+' '/global/ {print $4}')
    [[ -z $sampleip ]] && sampleip="$ipaddress"
    [[ -z $submask ]] && submask=$(cidr2mask $(getCidr $interface))
    local network=$(mask2network $sampleip $submask)
    local cidr=$(mask2cidr $submask)
    local startrange=$(addToAddress $network 10)
    # GH-667: an interface with no brd flag, or any failure inside these
    # helpers, used to leave endrange holding an error string that went
    # straight into the generated config. Fall back to the broadcast computed
    # from the network and mask we already have.
    local broadcast=$(interface2broadcast $interface)
    [[ $(validip $broadcast) -ne 0 ]] && broadcast=$(mask2broadcast $network $submask)
    local endrange=$(subtract1fromAddress $broadcast)
    [[ $(validip $endrange) -ne 0 ]] && endrange=$(subtract1fromAddress $(mask2broadcast $network $submask))
    local optdata="                { \"name\": \"subnet-mask\", \"data\": \"$submask\" }"
    [[ $(validip $routeraddress) -eq 0 ]] && optdata="${optdata},
                { \"name\": \"routers\", \"data\": \"$routeraddress\" }"
    [[ $(validip $dnsaddress) -eq 0 ]] && optdata="${optdata},
                { \"name\": \"domain-name-servers\", \"data\": \"$dnsaddress\" }"
    # Full reference: base classes + Apple BSDP, plus a commented-out Secure
    # Boot class. The admin can trim as needed.
    _writeKeaConfig "$target" "$(_keaBaseClasses),
$(_keaAppleClass)
$(_keaSecureBootClassCommented)"
    if [[ -s $target ]]; then
        echo
        echo " * A sample Kea DHCP config for a dedicated/external DHCP server was"
        echo " | written to: $target"
        echo " | Copy it to your DHCP server as /etc/kea/kea-dhcp4.conf and adjust the"
        echo " | subnet/pool/routers/domain-name-servers to match that network."
        echo " | next-server is already set to this FOG server ($ipaddress)."
    fi
}
configureDHCP() {
    if [[ $bldhcp -eq 1 && $dhcpengine == kea ]]; then
        dots "Setting up and starting DHCP Server (Kea)"
    else
        case $linuxReleaseName_lower in
            *debian*)
                if [[ $bldhcp -eq 1 ]]; then
                    dots "Setting up and starting DHCP Server (incl. debian 9 fix)"
                    sed -i.fog "s/INTERFACESv4=\"\"/INTERFACESv4=\"$interface\"/g" /etc/default/isc-dhcp-server
                else
                    dots "Setting up and starting DHCP Server"
                fi
                ;;
            *)
                dots "Setting up and starting DHCP Server"
                ;;
        esac
    fi
    case $bldhcp in
        1)
            # GH-954: one line per address, so a second address on the NIC
            # made this multi-line and every consumer below it wrong.
            serverip=$(ip -4 -o addr show $interface | awk -F'([ /])+' '/global/ {print $4}' | head -1)
            [[ -z $serverip ]] && serverip=$(/sbin/ifconfig $interface | grep -oE 'inet[:]? addr[:]?([0-9]{1,3}\.){3}[0-9]{1,3}' | awk -F'(inet[:]? ?addr[:]?)' '{print $2}')
            [[ -z $submask ]] && submask=$(cidr2mask $(getCidr $interface))
            network=$(mask2network $serverip $submask)
            [[ -z $startrange ]] && startrange=$(addToAddress $network 10)
            # GH-667: same guard as writeKeaSample -- never let a helper's
            # failure become the value that lands in dhcpd.conf.
            if [[ -z $endrange ]]; then
                broadcast=$(interface2broadcast $interface)
                [[ $(validip $broadcast) -ne 0 ]] && broadcast=$(mask2broadcast $network $submask)
                endrange=$(subtract1fromAddress $broadcast)
                [[ $(validip $endrange) -ne 0 ]] && endrange=$(subtract1fromAddress $(mask2broadcast $network $submask))
            fi
            [[ ! $(validip $routeraddress) -eq 0 ]] && routeraddress=$(echo $routeraddress | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            [[ ! $(validip $dnsaddress) -eq 0 ]] && dnsaddress=$(echo $dnsaddress | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            if [[ $dhcpengine == kea ]]; then
                if ! configureKeaDHCP; then
                    # Honor -X/--exitFail: a Kea config failure must not abort
                    # the whole installer, or later steps (TFTP/PXE) never run.
                    [[ -z $exitFail ]] && exit 1
                    return
                fi
            else
            [[ -f $dhcpconfig ]] && dhcptouse=$dhcpconfig
            [[ -f $dhcpconfigother ]] && dhcptouse=$dhcpconfigother
            if [[ -z $dhcptouse || ! -f $dhcptouse ]]; then
                echo "Failed"
                echo "Could not find dhcp config file"
                # Honor -X/--exitFail: same as the Kea branch, don't abort the
                # whole installer or later steps (TFTP/PXE) never run.
                [[ -z $exitFail ]] && exit 1
                return
            fi
            mv -fv "${dhcptouse}" "${dhcptouse}.${timestamp}" >>$error_log 2>&1
            echo "# DHCP Server Configuration file\n#see /usr/share/doc/dhcp*/dhcpd.conf.sample" > $dhcptouse
            echo "# This file was created by FOG" >> "$dhcptouse"
            echo "#Definition of PXE-specific options" >> "$dhcptouse"
            echo "# Code 1: Multicast IP Address of bootfile" >> "$dhcptouse"
            echo "# Code 2: UDP Port that client should monitor for MTFTP Responses" >> "$dhcptouse"
            echo "# Code 3: UDP Port that MTFTP servers are using to listen for MTFTP requests" >> "$dhcptouse"
            echo "# Code 4: Number of seconds a client must listen for activity before trying" >> "$dhcptouse"
            echo "#         to start a new MTFTP transfer" >> "$dhcptouse"
            echo "# Code 5: Number of seconds a client must listen before trying to restart" >> "$dhcptouse"
            echo "#         a MTFTP transfer" >> "$dhcptouse"
            echo "option space PXE;" >> "$dhcptouse"
            echo "option PXE.mtftp-ip code 1 = ip-address;" >> "$dhcptouse"
            echo "option PXE.mtftp-cport code 2 = unsigned integer 16;" >> "$dhcptouse"
            echo "option PXE.mtftp-sport code 3 = unsigned integer 16;" >> "$dhcptouse"
            echo "option PXE.mtftp-tmout code 4 = unsigned integer 8;" >> "$dhcptouse"
            echo "option PXE.mtftp-delay code 5 = unsigned integer 8;" >> "$dhcptouse"
            echo "option arch code 93 = unsigned integer 16;" >> "$dhcptouse"
            echo "use-host-decl-names on;" >> "$dhcptouse"
            echo "ddns-update-style interim;" >> "$dhcptouse"
            echo "ignore client-updates;" >> "$dhcptouse"
            echo "# Specify subnet of ether device you do NOT want service." >> "$dhcptouse"
            echo "# For systems with two or more ethernet devices." >> "$dhcptouse"
            echo "# subnet 136.165.0.0 netmask 255.255.0.0 {}" >> "$dhcptouse"
            echo "subnet $network netmask $submask{" >> "$dhcptouse"
            echo "    option subnet-mask $submask;" >> "$dhcptouse"
            echo "    range dynamic-bootp $startrange $endrange;" >> "$dhcptouse"
            echo "    default-lease-time 21600;" >> "$dhcptouse"
            echo "    max-lease-time 43200;" >> "$dhcptouse"
            [[ ! $(validip $routeraddress) -eq 0 ]] && routeraddress=$(echo $routeraddress | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            [[ ! $(validip $dnsaddress) -eq 0 ]] && dnsaddress=$(echo $dnsaddress | grep -oE "\b([0-9]{1,3}\.){3}[0-9]{1,3}\b")
            [[ $(validip $routeraddress) -eq 0 ]] && echo "    option routers $routeraddress;" >> "$dhcptouse" || echo "    #option routers 0.0.0.0" >> "$dhcptouse"
            [[ $(validip $dnsaddress) -eq 0 ]] && echo "    option domain-name-servers $dnsaddress;" >> "$dhcptouse" || echo "    #option domain-name-servers 0.0.0.0" >> "$dhcptouse"
            echo "    next-server $ipaddress;" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"Legacy\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00000\";" >> "$dhcptouse"
            echo "    filename \"undionly.kkpxe\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-32-2\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00002\";" >> "$dhcptouse"
            echo "    filename \"i386-efi/snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-32-1\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00006\";" >> "$dhcptouse"
            echo "    filename \"i386-efi/snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-64-1\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00007\";" >> "$dhcptouse"
            echo "    filename \"snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-64-2\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00008\";" >> "$dhcptouse"
            echo "    filename \"snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-64-3\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00009\";" >> "$dhcptouse"
            echo "    filename \"snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"UEFI-ARM64\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00011\";" >> "$dhcptouse"
            echo "    filename \"arm64-efi/snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"SURFACE-PRO-4\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 32) = \"PXEClient:Arch:00007:UNDI:003016\";" >> "$dhcptouse"
            echo "    filename \"snponly.efi\";" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            echo "class \"Apple-Intel-Netboot\" {" >> "$dhcptouse"
            echo "    match if substring(option vendor-class-identifier, 0, 14) = \"AAPLBSDPC/i386\";" >> "$dhcptouse"
            echo "    option dhcp-parameter-request-list 1,3,17,43,60;" >> "$dhcptouse"
            echo "    if (option dhcp-message-type = 8) {" >> "$dhcptouse"
            echo "        option vendor-class-identifier \"AAPLBSDPC\";" >> "$dhcptouse"
            echo "        if (substring(option vendor-encapsulated-options, 0, 3) = 01:01:01) {" >> "$dhcptouse"
            echo "            # BSDP List" >> "$dhcptouse"
            echo "            option vendor-encapsulated-options 01:01:01:04:02:80:00:07:04:81:00:05:2a:09:0D:81:00:05:2a:08:69:50:58:45:2d:46:4f:47;" >> "$dhcptouse"
            echo "            filename \"snponly.efi\";" >> "$dhcptouse"
            echo "        }" >> "$dhcptouse"
            echo "    }" >> "$dhcptouse"
            echo "}" >> "$dhcptouse"
            # Secure Boot clients, commented out on purpose -- see the note on
            # _keaSecureBootClassCommented() for the full reasoning, including
            # why the boot file names the shim rather than the loader. Option 93
            # cannot tell us whether Secure Boot is on, so this has to be opted
            # into per machine rather than applied to every UEFI client.
            echo "# Secure Boot clients. Uncomment and narrow the match to the machines" >> "$dhcptouse"
            echo "# whose firmware trusts this server's certificate. Swap snponly- for" >> "$dhcptouse"
            echo "# ipxe- if the chain loads but the network never comes up." >> "$dhcptouse"
            echo "#class \"FOG-UEFI-64-SecureBoot\" {" >> "$dhcptouse"
            echo "#    match if substring(option vendor-class-identifier, 0, 20) = \"PXEClient:Arch:00007\";" >> "$dhcptouse"
            echo "#    filename \"secureboot/snponly-shimx64.efi\";" >> "$dhcptouse"
            echo "#}" >> "$dhcptouse"
            diffconfig "${dhcptouse}"
            # Non-fatal syntax check; ISC has historically started without one.
            if command -v dhcpd >/dev/null 2>&1; then
                dhcpd -t -cf "$dhcptouse" >>$error_log 2>&1 || echo " * Warning: dhcpd -t reported issues with $dhcptouse (see $error_log)" >>$error_log 2>&1
            fi
            fi
            # When FOG owns DHCP, make sure the other engine is not also bound to
            # port 67 (covers an admin switching engines on an existing box).
            otherdhcp=""
            [[ $dhcpengine == kea ]] && otherdhcp="$iscservice" || otherdhcp="$keaservice"
            if [[ -n $otherdhcp && $systemctl == yes ]]; then
                systemctl is-active --quiet $otherdhcp && systemctl stop $otherdhcp >>$error_log 2>&1
                systemctl is-enabled --quiet $otherdhcp && systemctl disable $otherdhcp >>$error_log 2>&1
            fi
            case $systemctl in
                yes)
                    systemctl is-enabled --quiet $dhcpd && true || systemctl enable $dhcpd >>$error_log 2>&1
                    systemctl is-active --quiet $dhcpd && systemctl stop $dhcpd >>$error_log 2>&1 || false
                    systemctl is-active --quiet $dhcpd && true || systemctl start $dhcpd >>$error_log 2>&1
                    systemctl status $dhcpd >>$error_log 2>&1
                    ;;
                *)
                    case $osid in
                        1)
                            chkconfig $dhcpd on >>$error_log 2>&1
                            service $dhcpd stop >>$error_log 2>&1
                            service $dhcpd start >>$error_log 2>&1
                            service $dhcpd status >>$error_log 2>&1
                            ;;
                        2)
                            sysv-rc-conf $dhcpd on >>$error_log 2>&1
                            /etc/init.d/$dhcpd stop >>$error_log 2>&1
                            /etc/init.d/$dhcpd start >>$error_log 2>&1
                            ;;
                    esac
                    ;;
            esac
            errorStat $?
            ;;
        *)
            echo "Skipped"
            writeKeaSample
            ;;
    esac
}
vercomp() {
    [[ $1 == $2 ]] && return 0
    local IFS=.
    local i ver1=($1) ver2=($2)
    for ((i=${#ver1[@]}; i<${#ver2}; i++)); do
        ver1[i]=0
    done
    for ((i=0; i<${#ver1[@]}; i++)); do
        [[ -z ${ver2[i]} ]] && ver2[i]=0
        if ((10#${ver1[i]} > 10#${ver2[i]})); then
            return 1
        fi
        if ((10#${ver1[i]} < 10#${ver2[i]})); then
            return 2
        fi
    done
    return 0
}
languagemogen() {
    local languages="$1"
    local langpath="$2"
    local IFS=$'\n'
    local lang=''
    for lang in ${languages[@]}; do
        [[ ! -d "${langpath}/${lang}.UTF-8" ]] && continue
        msgfmt -o \
            "${langpath}/${lang}.UTF-8/LC_MESSAGES/messages.mo" \
            "${langpath}/${lang}.UTF-8/LC_MESSAGES/messages.po" \
            >>$error_log 2>&1
    done
}
generatePassword() {
    local length="$1"
    [[ $length -ge 12 && $length -le 128 ]] || length=20

    while [[ ${#genpassword} -lt $((length-1)) || -z $special ]]; do
        newchar=$(head -c1 /dev/urandom | tr -dc '0-9a-zA-Z!#$%&()*+,-./:;<=>?@[]^_{|}~')
        if [[ -n $(echo $newchar | tr -dc '!#$%&()*+,-./:;<=>?@[]^_{|}~') ]]; then
            special=${newchar}
        elif [[ ${#genpassword} -lt $((length-1)) ]]; then
            genpassword=${genpassword}${newchar}
        fi
    done
    # 9$(date +%N) seems weird but it's important because date may return
    # a leading 0 causing modulo to fail on reading it as octal number
    position=$(( 9$(date +%N) % $length ))
    # inject the special character at a random position
    echo ${genpassword::($position)}$special${genpassword:($position)}
}
checkPasswordChars() {
    checkpass="$(echo "$1" | tr -d '0-9a-zA-Z!#$%&()*+,-./:;<=>?@[]^_{|}~')"
    if [[ -n "$checkpass" ]]; then
        echo "Failed"
        echo ""
        echo "# The fog system account password includes characters we cannot properly"
        echo "# handle. Please remove the following character(s) in line password= of"
        echo "# your .fogsettings file before re-running the installer: $checkpass"
        echo ""
        exit 1
    fi
}
diffconfig() {
    local conffile="$1"
    [[ ! -f "${conffile}.${timestamp}" ]] && return 0
    diff -q "${conffile}" "${conffile}.${timestamp}" >>$error_log 2>&1
    if [[ $? -eq 0 ]]; then
        rm -f "${conffile}.${timestamp}" >>$error_log 2>&1
    else
        backupconfig="${backupconfig} ${conffile}"
    fi
}
setupFogReporting() {
    [[ $sendreports == "N" ]] && return
    local reportingdir="$fogprogramdir/reporting"
    local rreports="$reportingdir/report.sh"
    dots "Setting up FOG External Reporting"
    # Make sure required directories exist
    mkdir -p $reportingdir >>$error_log 2>&1
    mkdir -p /var/log/fog >>$error_log 2>&1
    # If the report settings file does not exist, create it.
    if [[ ! -f $reportingdir/settings ]]; then
        /usr/bin/awk -f $workingdir/../utils/reporting/reportingcronrandom.awk >> $reportingdir/settings
    fi
    # Pull in our reporting settings
    source $reportingdir/settings >>$error_log 2>&1

    crondfile="/etc/cron.d/fog_reporting"
    mv -fv "${crondfile}" "${crondfile}.${timestamp}" >>$error_log 2>&1
    # Build the cron.d file
    cat > ${crondfile} <<END_OF_REPORTING_FILE
SHELL=/bin/bash
PATH=${PATH}
${minute_of_hour} ${hour_of_day} * * ${day_of_week} ${user_to_run_as} ${rreports} >> ${reporting_log} 2>&1
END_OF_REPORTING_FILE
    diffconfig "${crondfile}"
    # If the reporting script exists, create a backup of it.
    mv -fv "${rreports}" "${rreports}.${timestamp}" >>$error_log 2>&1
    # Copy the new reporting script
    cp $workingdir/../utils/reporting/report.sh ${rreports} >>$error_log 2>&1
    # List change into backupconfig variable
    diffconfig "${rreports}"
    chmod +x ${rreports} >>$error_log 2>&1
    echo "Done"
}
