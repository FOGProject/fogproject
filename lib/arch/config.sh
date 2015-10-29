#!/bin/bash
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
#packages="apache php-apache php-gd php mariadb dhcp tftp-hpa nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc htmldoc perl perl-crypt-passwdmd5 lftp"
packages="apache php-fpm php-gd php mariadb dhcp tftp-hpa nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc perl perl-crypt-passwdmd5 lftp curl openssl openssh php-mcrypt"
storageNodePackages="apache php-fpm php mariadb nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp curl openssl openssh php-mcrypt"
packageinstaller="pacman -Sy --noconfirm"
packagelist="pacman -Si"
packageupdater="pacman -Syu --noconfirm"
packmanUpdate="$packageinstaller"
langPackages="iso-codes"
dhcpname="dhcp"
# where do the php files go?
if [ -z "$docroot" ]; then
    docroot="/srv/http/"
    webdirdest="${docroot}fog/"
elif [[ "$docroot" != *'fog'* ]]; then
    webdirdest="${docroot}fog/"
else
    webdirdest="${docroot}/"
fi
webredirect="${webdirdest}/index.php"
apacheuser="http"
apachelogdir="/var/log/httpd"
apacheerrlog="$apachelogdir/error_log"
apacheacclog="$apachelogdir/access_log"
etcconf="/etc/httpd/conf/extra/fog.conf"
phpini="/etc/php/php.ini"
initdpath="/usr/lib/systemd/system";
initdsrc="../packages/systemd";
if [[ -e /usr/lib/systemd/system/mariadb.service ]]; then
    ln -s /usr/lib/systemd/system/mariadb.service /usr/lib/systemd/system/mysql.service >/dev/null 2>&1
    ln -s /usr/lib/systemd/system/mariadb.service /usr/lib/systemd/system/mysqld.service >/dev/null 2>&1
    ln -s /usr/lib/systemd/system/mariadb.service /etc/systemd/system/mysql.service >/dev/null 2>&1
    ln -s /usr/lib/systemd/system/mariadb.service /etc/systemd/system/mysqld.service >/dev/null 2>&1
elif [[ -e /usr/lib/systemd/system/mysqld.service ]]; then
    ln -s /usr/lib/systemd/system/mysqld.service /usr/lib/systemd/system/mysql.service >/dev/null 2>&1
    ln -s /usr/lib/systemd/system/mysqld.service /etc/systemd/system/mysql.service >/dev/null 2>&1
fi
initdMCfullname="FOGMulticastManager.service";
initdIRfullname="FOGImageReplicator.service";
initdSDfullname="FOGScheduler.service";
initdSRfullname="FOGSnapinReplicator.service";
initdPHfullname="FOGPingHosts.service";

# where do we store the image files?
storage="/images"
storageupload="/images/dev"

# DHCP config file location
dhcpconfig="/etc/dhcpd.conf"
dhcpconfigother="/etc/dhcp/dhcpd.conf"

# where do the tftp files go?
tftpdirdst="/srv/tftp"

# where is the tftpd config file?
tftpconfig="/etc/xinetd.d/tftpd"

# where is the ftp server config file?
ftpxinetd="/etc/xinetd.d/vsftpd"
ftpconfig="/etc/vsftpd.conf"
dhcpd="dhcpd"
# where do snapins go?
snapindir="/opt/fog/snapins"
