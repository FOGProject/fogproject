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
packages="apache php-fpm php-gd php mariadb dhcp tftp-hpa nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc perl perl-crypt-passwdmd5 lftp php-mysqlnd curl"
storageNodePackages="apache php-fpm php mariadb nfs-utils vsftpd xinetd tar gzip make m4 gcc gcc-c++ lftp php-mysqlnd curl"
packageinstaller="pacman -Sy --noconfirm"
packagelist="pacman -Si"
packageupdater="pacman -Syu"
packmanUpdate="$packageinstaller"
langPackages="iso-codes"
dhcpname="dhcp"
# where do the php files go?
if [ -z "$docroot" ]; then
    docroot="/srv/httpd/"
    webdirdest="${docroot}fog"
elif [[ "$docroot" != *'fog'* ]]; then
    webdirdest="${docroot}fog"
else
    webdirdest="${docroot}"
fi
webredirect="${webdirdest}/index.php"
apacheuser="http"
apachelogdir="/var/log/httpd"
apacheerrlog="$apachelogdir/error_log"
apacheacclog="$apachelogdir/access_log"
etcconf="/etc/httpd/conf.d/fog.conf"
phpini="/etc/php/php.ini"

# where do we store the image files?
storage="/images"
storageupload="/images/dev"

# DHCP config file location
dhcpconfig="/etc/dhcpd.conf"
dhcpconfigother="/etc/dhcp/dhcpd.conf"

# where do the tftp files go?
tftpdirdst="/srv/tftp"

# where is the tftpd config file?
tftpconfig="/usr/lib/systemd/system/tftpd.service"

# where is the ftp server config file?
ftpconfig="/etc/xinetd.d/vsftpd"
dhcpd="dhcpd"
# where do snapins go?
snapindir="/opt/fog/snapins"
