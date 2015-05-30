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
#
#

# Yum packages to install

if [ "$linuxReleaseName" == "Mageia" ];
then
    # Mageia 
    packages="apache apache-mod_php php-gd php-cli php-gettext mariadb mariadb-common mariadb-core mariadb-common-core dhcp-server tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ htmldoc perl perl-Crypt-PasswdMD5 lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl";
    storageNodePackages="apache apache-mod_php php-cli php-gettext mariadb mariadb-core mariadb-common mariadb-common-core nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl";
    packageinstaller="urpmi --auto";

elif [ "$linuxReleaseName" == "Fedora" ];
then
    # Fedora
    packages="httpd php php-cli php-common php-gd mysql mysql-server dhcp tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl";
    storageNodePackages="httpd php php-cli php-common php-gd php-mysqlnd mysql nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp curl php-mcrypt php-mbstring mod_ssl";
    packageinstaller="yum -y --enablerepo=remi,remi-php56 install";
else
    # CentOS or Other  PCLinuxOS uses apt-rpm  
    packages="httpd php php-cli php-common php-gd mysql mysql-server dhcp tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl";
    storageNodePackages="httpd php php-cli php-common php-gd mysql nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl";
    packageinstaller="yum -y --enablerepo=remi,remi-php56 install";
fi
RHVER=`awk '{for(i=1;i<=NF;i++) if ($i == "release") print $(i+1)}' /etc/redhat-release`;
langPackages="iso-codes";
dhcpname="dhcp";
nfsservice="nfs";

# where do the init scripts go?
if [ "$RHVER" -ge 15 -a "$linuxReleaseName" == "Fedora" ] || [ "$RHVER" -ge 7 -a "$linuxReleaseName" != "Fedora" -a "$linuxReleaseName" != "Mageia" ]; then
	initdpath="/usr/lib/systemd/system";
	initdsrc="../packages/systemd";
	initdMCfullname="FOGMulticastManager.service";
	initdIRfullname="FOGImageReplicator.service";
	initdSDfullname="FOGScheduler.service";
	initdSRfullname="FOGSnapinReplicator.service";
else
	initdpath="/etc/rc.d/init.d";
	initdsrc="../packages/init.d/redhat";
	initdMCfullname="FOGMulticastManager";
	initdIRfullname="FOGImageReplicator";
	initdSDfullname="FOGScheduler";
	initdSRfullname="FOGSnapinReplicator";
fi

# where do the php files go?
docroot="/var/www/html/";
webdirdest="${docroot}fog";
webredirect="${webdirdest}/index.php";
apacheuser="apache";

# where do we store the image files?
storage="/images";
storageupload="/images/dev";

# DHCP config file location
dhcpconfig="/etc/dhcpd.conf";
dhcpconfigother="/etc/dhcp/dhcpd.conf";

# where do the tftp files go?
tftpdirdst="/tftpboot"

# where is the tftpd config file?
tftpconfig="/etc/xinetd.d/tftp";

# where is the ftp server config file?
ftpconfig="/etc/vsftpd/vsftpd.conf"

# where is the nfs exports file?
nfsconfig="/etc/exports";

# where do snapins go?
snapindir="/opt/fog/snapins";

# Distribution specific changes
if [ "$linuxReleaseName" == "Mageia" ];
then
    #dhcpd package name
    dhcpname="dhcp-server";
    # where do the tftp files go?
    tftpdirdst="/var/lib/tftpboot";
    # NFS service name
    nfsservice="nfs-server";
    # NFS Subtree Check needed
    nfsexportsopts="no_subtree_check";
fi
