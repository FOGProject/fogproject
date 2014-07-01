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
    packages="apache apache-mod_php php-gd php-cli php-gettext mariadb mariadb-common mariadb-core mariadb-common-core php-mysql dhcp-server tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ htmldoc perl perl-Crypt-PasswdMD5 lftp clamav";
    storageNodePackages="apache apache-mod_php php-cli php-gettext mariadb mariadb-core mariadb-common mariadb-common-core php-mysql nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp";
    packageinstaller="urpmi --auto";

elif [ "linuxReleaseName" == "Fedora" ];
then
    # Fedora
    packages="httpd php php-cli php-common php-gd php-mysql mysql mysql-server dhcp tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ lftp";
    storageNodePackages="httpd php php-cli php-common php-gd php-mysql mysql nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp";
    packageinstaller="yum -y install";
else
    # CentOS or Other  PCLinuxOS uses apt-rpm  
    packages="httpd php php-cli php-common php-gd php-mysql mysql mysql-server dhcp tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ lftp clamav";
    storageNodePackages="httpd php php-cli php-common php-gd php-mysql mysql nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp";
    packageinstaller="yum -y install";
fi
    
langPackages="iso-codes";
dhcpname="dhcp";
nfsservice="nfs";

# where do the init scripts go?
initdpath="/etc/rc.d/init.d";
initdsrc="../packages/init.d/redhat";
initdMCfullname="FOGMulticastManager";
initdIRfullname="FOGImageReplicator";
initdSDfullname="FOGScheduler";

# where do the php files go?
webdirdest="/var/www/html/fog";
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

#where is freshclam's config file
#freshdb="/var/lib/clamav/";
freshdb="/var/clamav/";
freshwebroot="${webdirdest}/av/";
freshconf="/etc/freshclam.conf";
#freshcron="/etc/sysconfig/freshclam"
freshcron="/usr/bin/freshclam"

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
