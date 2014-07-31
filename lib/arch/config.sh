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

# Arch Config Settings

# pacman packages to install 
#packages="apache php-apache php-gd php mariadb dhcp tftp-hpa nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc htmldoc perl perl-crypt-passwdmd5 lftp clamav";
packages="apache php-fpm php-gd php mariadb dhcp tftp-hpa nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc perl perl-crypt-passwdmd5 lftp clamav";
storageNodePackages="apache php-fpm php mariadb nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp";
packageinstaller="pacman -Sy --noconfirm";
langPackages="iso-codes";
dhcpname="dhcp";
nfsservice="nfs-server";

# where do the init scripts go?
initdpath="/usr/lib/systemd/system";
initdsrc="../packages/systemd";
initdMCfullname="FOGMulticastManager.service";
initdIRfullname="FOGImageReplicator.service";
initdSDfullname="FOGScheduler.service";

# where do the php files go?
webdirdest="/srv/http/fog";
webredirect="${webdirdest}/index.php";
apacheuser="http";

# where do we store the image files?
storage="/images";
storageupload="/images/dev";

# DHCP config file location
dhcpconfig="/etc/dhcpd.conf";
dhcpconfigother="/etc/dhcp/dhcpd.conf";

# where do the tftp files go?
tftpdirdst="/srv/tftp"

# where is the tftpd config file?
tftpconfig="/usr/lib/systemd/system/tftpd.service";

# where is the ftp server config file?
ftpconfig="/etc/xinetd.d/vsftpd"

# where is the nfs exports file?
nfsconfig="/etc/exports";

# where do snapins go?
snapindir="/opt/fog/snapins";

#where is freshclam's config file
freshdb="/var/lib/clamav/";
#freshdb="/var/clamav/";
freshwebroot="${webdirdest}/av/";
freshconf="/etc/clamav/freshclam.conf";
#freshcron="/etc/sysconfig/freshclam"
freshcron="/usr/bin/freshclam"

