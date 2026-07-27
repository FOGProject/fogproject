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
[[ -z $username || "x$username" == "xfog" ]] && username="fogproject"
[[ -z $webdirsrc ]] && webdirsrc="../packages/web"
[[ -z $tftpdirsrc ]] && tftpdirsrc="../packages/tftp"
[[ -z $buildipxesrc  ]] && buildipxesrc="../utils/FOGiPXE"
fog_udpversion="20250223"
[[ -z $udpcastsrc ]] && udpcastsrc="../packages/udpcast-${fog_udpversion}.tar.gz"
[[ -z $udpcastout ]] && udpcastout="udpcast-${fog_udpversion}"
[[ -z $servicesrc ]] && servicesrc="../packages/service"
[[ -z $servicedst ]] && servicedst="/opt/fog/service"
[[ -z $servicelogs ]] && servicelogs="/opt/fog/log"
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
[[ -z $nfsconfig ]] && nfsconfig="/etc/exports"
[[ -z $nfsservice ]] && nfsservice="nfs-server nfs-kernel-server nfs"
[[ -z $sqlclientlist ]] && sqlclientlist="mariadb-client mariadb MariaDB-client mysql"
[[ -z $sqlserverlist ]] && sqlserverlist="mariadb-galera-server mariadb-server MariaDB-Galera-server MariaDB-server mysql-server"
command -v systemctl >>$error_log 2>&1
exitcode=$?
grep systemd /proc/1/comm >>$error_log 2>&1
bootcode=$?
[[ $exitcode -eq 0 && $bootcode -eq 0 && -z $systemctl ]] && systemctl="yes"
if [[ $systemctl == yes ]]; then
    initdsrc="../packages/systemd"
    initdMCfullname="FOGMulticastManager.service"
    initdIRfullname="FOGImageReplicator.service"
    initdSDfullname="FOGScheduler.service"
    initdSRfullname="FOGSnapinReplicator.service"
    initdSHfullname="FOGSnapinHash.service"
    initdPHfullname="FOGPingHosts.service"
    initdISfullname="FOGImageSize.service"
    initdFDfullname="FOGFileDeleter.service"
    case $linuxReleaseName_lower in
        *ubuntu*|*bian*|*mint*)
            initdpath="/lib/systemd/system"
            ;;
        *)
            initdpath="/usr/lib/systemd/system"
            ;;
    esac
    # Alias mysql/mysqld onto whichever DB unit the distro actually ships, so the
    # $dbservice lookup in functions.sh finds a name whatever the packaging.
    #
    # The /etc/systemd/system sources previously read `$initdpath/mariadb` with no
    # .service suffix, which produced a *dangling* link. That is not cosmetic:
    # /etc/systemd/system takes precedence over $initdpath, so a broken link there
    # shadows the distro's working alias and systemd reports the unit as "bad" --
    # which is what the `grep -v bad` guard in functions.sh is working around.
    # linkIfAbsent() replaces such a link when it finds one, healing installs that
    # already ran an affected version. Refs forums topic 18204.
    if [[ -e $initdpath/mariadb.service ]]; then
        linkIfAbsent $initdpath/mariadb.service $initdpath/mysql.service
        linkIfAbsent $initdpath/mariadb.service $initdpath/mysqld.service
        linkIfAbsent $initdpath/mariadb.service /etc/systemd/system/mysql.service
        linkIfAbsent $initdpath/mariadb.service /etc/systemd/system/mysqld.service
    elif [[ -e $initdpath/mysqld.service ]]; then
        linkIfAbsent $initdpath/mysqld.service $initdpath/mysql.service
        linkIfAbsent $initdpath/mysqld.service /etc/systemd/system/mysql.service
    fi
else
    initdpath="/etc/init.d"
    initdMCfullname="FOGMulticastManager"
    initdIRfullname="FOGImageReplicator"
    initdSDfullname="FOGScheduler"
    initdSRfullname="FOGSnapinReplicator"
    initdSHfullname="FOGSnapinHash"
    initdPHfullname="FOGPingHosts"
    initdISfullname="FOGImageSize"
    initdFDfullname="FOGFileDeleter"
    case $linuxReleaseName_lower in
        *ubuntu*|*bian*|*mint*)
            initdsrc="../packages/init.d/ubuntu"
            ;;
        *alpine*)
            initdsrc="../packages/init.d/alpine"
            ;;
        *)
            initdsrc="../packages/init.d/redhat"
            ;;
    esac
fi
serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname $initdSHfullname $initdISfullname $initdFDfullname"
