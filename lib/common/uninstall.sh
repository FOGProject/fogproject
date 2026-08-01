#  FOG is a computer imaging solution.
#  Copyright (C) 2007  Chuck Syperski & Jian Zhang
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#   any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# FOG uninstaller.
#
# --uninstall was advertised in the usage text for years while its handler was
# a bare `exit 0`. This implements it.
#
# The governing rule is: remove what FOG installed, keep what FOG stored.
# Everything FOG owns outright goes unconditionally. Data -- the database,
# images, snapins, the SSL CA, the Linux account -- survives unless explicitly
# purged, so an accidental uninstall is recoverable by reinstalling over the
# surviving data. That doubles as a genuinely useful "rebuild the server, keep
# my hosts" path.
#
# The SSL CA deserves specific mention: it lives at $sslpath, which is under
# $fogprogramdir/snapins. That means a naive `rm -rf $fogprogramdir` destroys
# it as a side effect, and losing .fogCA.key/.fogCA.pem permanently breaks
# every deployed fog-client and every PXE binary pinned to that CA -- there is
# no recovery short of reinstalling every client by hand. So $fogprogramdir is
# never removed wholesale; its subdirectories are removed individually and
# snapins/ is left behind unless purged.
#
# Packages (mariadb, nginx, nfs-kernel-server, php-*) are never touched. They
# are routinely shared with other workloads and the installer does not record
# which it installed versus found already present.

# Files FOG replaces wholesale rather than appending to (exports, vsftpd.conf,
# dhcpd.conf, and on some distros the webserver config). The installer moves
# the incumbent to "<file>.<timestamp>" before writing its own version, so the
# genuine pre-FOG original is the OLDEST such backup -- newer ones are just
# FOG's own previous versions. Restore that, and set the current FOG version
# aside rather than deleting it, so nothing is destroyed outright.
restorePreFogConfig() {
    local target="$1" oldest
    [[ -z $target || ! -f $target ]] && return 0
    oldest=$(ls -1 "${target}".[0-9]* 2>/dev/null | sort -t. -k2 -n | head -1)
    if [[ -z $oldest ]]; then
        echo "     ! no pre-FOG backup for ${target}, leaving it as-is"
        return 0
    fi
    mv -f "$target" "${target}.fog-uninstall.${timestamp}" >>$error_log 2>&1
    cp -a "$oldest" "$target" >>$error_log 2>&1
    echo "     restored ${target} from $(basename $oldest)"
}

# Print a path only if it exists, so the confirmation list reflects reality
# rather than everything the installer could ever have created.
_ifPresent() {
    local p="$1" label="$2"
    [[ -z $p ]] && return 0
    [[ -e $p || -L $p ]] && echo "     ${label:-$p}"
}

uninstallFOG() {
    local reply st plan_db plan_images plan_snapins plan_ssl plan_user

    # Without .fogsettings we cannot know where anything was put -- which
    # docroot, which storage location, which database. Guessing here would mean
    # deleting paths that may belong to something else entirely.
    if [[ ! -f $fogprogramdir/.fogsettings ]]; then
        echo
        echo " * No FOG installation found."
        echo "   Expected $fogprogramdir/.fogsettings, which records what this"
        echo "   install created. Without it there is nothing safe to remove."
        echo "   If FOG lives elsewhere, pass --fogprogramdir </path>."
        echo
        exit 1
    fi

    [[ -z $webdirdest ]] && webdirdest="${docroot}fog/"

    echo
    echo "  ##################################################################"
    echo "  #                        FOG UNINSTALLER                          #"
    echo "  ##################################################################"
    echo
    echo "   The following will be REMOVED:"
    echo
    echo "     FOG services: $serviceList"
    _ifPresent "$servicedst"
    _ifPresent "$servicelogs"
    _ifPresent "$fogprogramdir/cache"
    _ifPresent "$fogprogramdir/reporting"
    _ifPresent "$fogprogramdir/php.loc"
    _ifPresent "$fogprogramdir/.fogsettings"
    _ifPresent "$webdirdest"
    _ifPresent "/etc/fog"
    _ifPresent "/var/log/fog"
    _ifPresent "/etc/cron.d/fog_reporting"
    _ifPresent "/etc/nfs.conf.d/fog-nfs.conf"
    _ifPresent "/usr/etc/nfs.conf.d/fog-nfs.conf"
    # $etcconf is FOG's own vhost on most distros (fog.conf / 001-fog.conf) but
    # on Alpine it is /etc/nginx/http.d/default.conf -- the distro's default
    # config. Deleting that would leave nginx with no server block at all, so
    # anything not named for FOG gets restored instead of removed.
    case $(basename "$etcconf" 2>/dev/null) in
        fog.conf|001-fog.conf) _ifPresent "$etcconf" ;;
    esac
    [[ -n $tftpdirdst && -d $tftpdirdst ]] && echo "     contents of $tftpdirdst (PXE binaries and boot menus)"
    echo
    echo "   The following will be RESTORED to their pre-FOG state:"
    echo
    echo "     $nfsconfig, $ftpconfig${dhcpconfig:+, $dhcpconfig}"
    case $(basename "$etcconf" 2>/dev/null) in
        fog.conf|001-fog.conf) ;;
        *) [[ -n $etcconf ]] && echo "     $etcconf" ;;
    esac
    echo "     (the current versions are kept as <file>.fog-uninstall.$timestamp)"
    echo

    plan_db="KEPT"; plan_images="KEPT"; plan_snapins="KEPT"
    plan_ssl="KEPT"; plan_user="KEPT"
    [[ $purgedb == 1 ]] && plan_db="DROPPED"
    [[ $purgeimages == 1 ]] && plan_images="DELETED"
    [[ $purgesnapins == 1 ]] && plan_snapins="DELETED"
    [[ $purgessl == 1 ]] && plan_ssl="DELETED"
    [[ $purgeuser == 1 ]] && plan_user="DELETED"

    echo "   Your data:"
    echo
    printf '     %-42s %s\n' "database '$mysqldbname'" "$plan_db"
    printf '     %-42s %s\n' "images in $storageLocation" "$plan_images"
    printf '     %-42s %s\n' "snapins in $snapindir" "$plan_snapins"
    printf '     %-42s %s\n' "SSL CA in $sslpath" "$plan_ssl"
    printf '     %-42s %s\n' "Linux account '$username'" "$plan_user"
    echo
    if [[ $plan_ssl == DELETED ]]; then
        echo "   ** Removing the SSL CA permanently breaks every deployed"
        echo "   ** fog-client and every PXE binary signed against it. There"
        echo "   ** is no recovery; each client must be reinstalled by hand."
        echo
    fi
    echo "   Packages (webserver, database, NFS, PHP) are NOT removed."
    echo

    if [[ $uninstalldryrun == 1 ]]; then
        echo " * Dry run, nothing was changed."
        echo
        exit 0
    fi

    # -Y/--autoaccept deliberately does NOT satisfy this. That flag is already
    # embedded in plenty of people's install automation, and it must never be
    # enough to trigger a destructive uninstall by accident. --force exists for
    # automation that genuinely means it.
    if [[ $uninstallforce != 1 ]]; then
        echo "   Type this server's hostname ($hostname) to proceed, anything else to abort."
        echo
        read -r -p "   > " reply
        if [[ $reply != "$hostname" ]]; then
            echo
            echo " * Aborted, nothing was changed."
            echo
            exit 0
        fi
        echo
    fi

    # Always dump the database first, whatever the purge flags say. It is cheap
    # next to the cost of being wrong, and it is the only part of this that
    # cannot be reconstructed from the FOG sources.
    dots "Backing up database"
    if [[ -d $backupPath ]]; then
        mysqldump ${snmysqlhost:+-h $snmysqlhost} -u "${snmysqluser}" \
            -p"${snmysqlpass}" "${mysqldbname}" \
            > "${backupPath%/}/fog_uninstall_${mysqldbname}_${timestamp}.sql" 2>>$error_log
        errorStat $?
    else
        echo "Skipped (no $backupPath)"
    fi

    dots "Stopping and disabling FOG services"
    st=0
    for serviceItem in $serviceList; do
        if [[ $systemctl == yes ]]; then
            systemctl stop $serviceItem >>$error_log 2>&1
            systemctl disable $serviceItem >>$error_log 2>&1
        elif [[ -x $initdpath/$serviceItem ]]; then
            $initdpath/$serviceItem stop >>$error_log 2>&1
        fi
        rm -f "$initdpath/$serviceItem" >>$error_log 2>&1 || st=1
    done
    # Same trap as below: a false `[[ $systemctl == yes ]] &&` would leave $? = 1
    # and errorStat would abort the uninstall on every non-systemd host.
    if [[ $systemctl == yes ]]; then
        systemctl daemon-reload >>$error_log 2>&1
    fi
    errorStat $st

    dots "Removing FOG program files"
    # Individually, never `rm -rf $fogprogramdir` -- see the note at the top of
    # this file about the CA living under snapins/.
    rm -rf "$servicedst" "$servicelogs" "$fogprogramdir/cache" \
        "$fogprogramdir/reporting" >>$error_log 2>&1
    rm -f "$fogprogramdir/php.loc" "$fogprogramdir/.fogsettings" >>$error_log 2>&1
    errorStat $?

    # $st accumulates the status of the removals that actually matter. Do NOT
    # end one of these blocks with `[[ test ]] && command; errorStat $?` -- when
    # the test is false the && returns 1, errorStat reports the step as Failed,
    # and the installer aborts mid-uninstall. That is not hypothetical: it
    # stopped the uninstall three steps in during testing, because ${docroot}fog
    # is a real directory rather than a symlink on a normal install.
    dots "Removing FOG web files"
    st=0
    rm -rf "$webdirdest" >>$error_log 2>&1 || st=1
    if [[ -L ${docroot}fog ]]; then
        rm -f "${docroot}fog" >>$error_log 2>&1 || st=1
    fi
    errorStat $st

    dots "Removing FOG system entries"
    st=0
    rm -rf /etc/fog >>$error_log 2>&1 || st=1
    if [[ -L /var/log/fog ]]; then
        rm -f /var/log/fog >>$error_log 2>&1 || st=1
    fi
    rm -f /etc/cron.d/fog_reporting >>$error_log 2>&1 || st=1
    rm -f /etc/nfs.conf.d/fog-nfs.conf /usr/etc/nfs.conf.d/fog-nfs.conf >>$error_log 2>&1
    case $(basename "$etcconf" 2>/dev/null) in
        fog.conf|001-fog.conf)
            rm -f "$etcconf" >>$error_log 2>&1 || st=1
            [[ $osid -eq 2 ]] && a2dissite 001-fog >>$error_log 2>&1
            ;;
    esac
    if [[ -n $tftpdirdst && -d $tftpdirdst ]]; then
        rm -rf "${tftpdirdst:?}"/* >>$error_log 2>&1
    fi
    errorStat $st

    echo " * Restoring pre-FOG configuration files"
    restorePreFogConfig "$nfsconfig"
    restorePreFogConfig "$ftpconfig"
    [[ -n $dhcpconfig ]] && restorePreFogConfig "$dhcpconfig"
    case $(basename "$etcconf" 2>/dev/null) in
        fog.conf|001-fog.conf) ;;
        *) restorePreFogConfig "$etcconf" ;;
    esac

    if [[ $purgedb == 1 ]]; then
        dots "Dropping database '$mysqldbname'"
        mysql ${snmysqlhost:+-h $snmysqlhost} -u "${snmysqluser}" \
            -p"${snmysqlpass}" --execute="DROP DATABASE IF EXISTS \`${mysqldbname}\`;" \
            >>$error_log 2>&1
        errorStat $?
    fi
    if [[ $purgeimages == 1 ]]; then
        dots "Removing images"
        rm -rf "${storageLocation:?}" >>$error_log 2>&1
        errorStat $?
    fi
    if [[ $purgesnapins == 1 ]]; then
        dots "Removing snapins"
        rm -rf "${snapindir:?}" >>$error_log 2>&1
        errorStat $?
    fi
    if [[ $purgessl == 1 ]]; then
        dots "Removing SSL CA"
        rm -rf "${sslpath:?}" >>$error_log 2>&1
        errorStat $?
    fi
    # Left until last: the account owns the paths above, so removing it first
    # would leave them orphaned mid-run if something failed.
    if [[ $purgeuser == 1 ]]; then
        dots "Removing '$username' account"
        userdel -r "$username" >>$error_log 2>&1
        errorStat $?
    fi

    # Only now, and only if nothing was left behind (snapins and the CA
    # normally survive, so this usually does nothing).
    rmdir "$fogprogramdir" >/dev/null 2>&1

    echo
    echo " * FOG has been uninstalled."
    echo
    [[ $plan_db == KEPT ]] && echo "   Database '$mysqldbname' was kept."
    [[ $plan_images == KEPT ]] && echo "   Images in $storageLocation were kept."
    [[ $plan_ssl == KEPT ]] && echo "   The SSL CA in $sslpath was kept."
    echo "   Reinstalling over what remains will pick it all back up."
    echo
    echo "   Packages were not removed. Restart or reconfigure your webserver,"
    echo "   NFS and DHCP services if this host serves anything else."
    echo
    exit 0
}
