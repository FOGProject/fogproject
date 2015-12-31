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
fogutilsdir="$fogprogramdir/utils"
fogutilsdirsrc="../packages/utils"
nfsconfig="/etc/exports"
nfsservice="nfs nfs-server nfs-kernel-server"
version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"
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
