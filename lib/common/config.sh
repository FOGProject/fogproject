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
[[ -z $username ]] && username="fog"
[[ -z $snmysqlhost ]] && snmysqlhost="127.0.0.1"
[[ -z $snmysqluser ]] && snmysqluser="root"
[[ -z $snmysqlpass ]] && snmysqlpass=""
[[ -z $webdirsrc ]] && webdirsrc="../packages/web"
[[ -z $tftpdirsrc ]] && tftpdirsrc="../packages/tftp"
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
if [[ $systemctl == yes ]]; then
    case $linuxReleaseName in
        *[Uu][Bb][Uu][Nn][Tt][Uu]*|*[Dd][Ee][Bb][Ii][Aa][Nn]*)
            initdpath="/lib/systemd/system"
            ;;
        *)
            initdpath="/usr/lib/systemd/system"
            ;;
    esac
    initdsrc="../packages/systemd"
    initdMCfullname="FOGMulticastManager.service"
    initdIRfullname="FOGImageReplicator.service"
    initdSDfullname="FOGScheduler.service"
    initdSRfullname="FOGSnapinReplicator.service"
    initdPHfullname="FOGPingHosts.service"
fi
