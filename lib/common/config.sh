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
[[ -z $username || "x$username" = "xfog" ]] && username="fogproject"
[[ -z $webdirsrc ]] && webdirsrc="../packages/web"
[[ -z $tftpdirsrc ]] && tftpdirsrc="../packages/tftp"
[[ -z $buildipxesrc ]] && buildipxesrc="../utils/FOGiPXE"
[[ -z $udpcastsrc ]] && udpcastsrc="../packages/udpcast-20120424.tar.gz"
[[ -z $udpcasttmp ]] && udpcasttmp="/tmp/udpcast.tar.gz"
[[ -z $udpcastout ]] && udpcastout="udpcast-20120424"
[[ -z $servicesrc ]] && servicesrc="../packages/service"
[[ -z $servicedst ]] && servicedst="/opt/fog/service"
[[ -z $servicelogs ]] && servicelogs="/opt/fog/log"
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
[[ -z $nfsconfig ]] && nfsconfig="/etc/exports"
[[ -z $nfsservice ]] && nfsservice="nfs-server nfs-kernel-server nfs"
[[ -z $sqlclientlist ]] && sqlclientlist="mysql mariadb MariaDB-client"
[[ -z $sqlserverlist ]] && sqlserverlist="mysql-server mariadb-server mariadb-galera-server MariaDB-server MariaDB-Galera-server"
command -v systemctl >>$workingdir/error_logs/fog_error_${version}.log 2>&1
exitcode=$?
ps -p 1 -o comm= | grep systemd >>$workingdir/error_logs/fog_error_${version}.log 2>&1
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
    case $linuxReleaseName in
        *[Uu][Bb][Uu][Nn][Tt][Uu]*|*[Bb][Ii][Aa][Nn]*|*[Mm][Ii][Nn][Tt]*)
            initdpath="/lib/systemd/system"
            ;;
        *)
            initdpath="/usr/lib/systemd/system"
            ;;
    esac
    if [[ -e $initdpath/mariadb.service ]]; then
        ln -s $initdpath/{mariadb,mysql}.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s $initdpath/{mariadb,mysqld}.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s $initdpath/mariadb /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s $initdpath/mariadb /etc/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    elif [[ -e $initdpath/mysqld.service ]]; then
        ln -s $initdpath/mysql{d,}.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s $initdpath/mysqld.service /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
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
    case $linuxReleaseName in
        *[Uu][Bb][Uu][Nn][Tt][Uu]*|*[Bb][Ii][Aa][Nn]*|*[Mm][Ii][Nn][Tt]*)
            initdsrc="../packages/init.d/ubuntu"
            ;;
        *)
            initdsrc="../packages/init.d/redhat"
            ;;
    esac
fi
serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname $initdSHfullname $initdISfullname"
