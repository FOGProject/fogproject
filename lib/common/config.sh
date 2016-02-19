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
command -v dnf >/dev/null 2>&1
[[ $? -eq 0 ]] && repos="remi,remi-php56" || repos="remi,remiphp-56,epel"
username="fog"
dbuser="root"
dbpass=""
dbhost="p:localhost"
webdirsrc="../packages/web"
tftpdirsrc="../packages/tftp"
udpcastsrc="../packages/udpcast-20120424.tar.gz"
udpcasttmp="/tmp/udpcast.tar.gz"
udpcastout="udpcast-20120424"
servicesrc="../packages/service"
servicedst="/opt/fog/service"
servicelogs="/opt/fog/log"
fogprogramdir="/opt/fog"
nfsconfig="/etc/exports"
nfsservice="nfs-server nfs-kernel-server nfs"
sqlclientlist="mysql mariadb MariaDB-client"
sqlserverlist="mysql-server mariadb-server mariadb-galera-server MariaDB-server MariaDB-Galera-server"
schemaversion="181"
if [[ $systemctl == yes ]]; then
    initdsrc="../packages/systemd"
    initdMCfullname="FOGMulticastManager.service"
    initdIRfullname="FOGImageReplicator.service"
    initdSDfullname="FOGScheduler.service"
    initdSRfullname="FOGSnapinReplicator.service"
    case $linuxReleaseName in
        *[Uu][Bb][Uu][Nn][Tt][Uu]*|*[Dd][Ee][Bb][Ii][Aa][Nn]*)
            initdpath="/lib/systemd/system"
            ;;
        *)
            initdpath="/usr/lib/systemd/system"
            ;;
    esac
fi
