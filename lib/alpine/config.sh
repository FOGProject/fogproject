#!/bin/bash
# lib/alpine/config.sh
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
[[ -z $packages ]] && packages="openrc nginx bc cdrkit curl gcc g++ git gzip lftp m4 make mariadb mariadb-client net-tools nfs-utils openssh openssl perl perl-crypt-passwdmd5 php7 php7-session php7-fpm php7-mbstring php7-mcrypt php7-soap php7-openssl php7-gmp php7-pdo_odbc php7-json php7-dom php7-pdo php7-zip php7-mysqli php7-sqlite3 php7-apcu php7-pdo_pgsql php7-bcmath php7-gd php7-odbc php7-pdo_mysql php7-pdo_sqlite php7-gettext php7-xmlreader php7-xmlrpc php7-bz2 php7-iconv php7-pdo_dblib php7-curl php7-sockets php7-mysqli php7-ctype syslinux tar tftp-hpa vsftpd wget xz"
[[ -z $packageinstaller ]] && packageinstaller="apk add"
[[ -z $packagelist ]] && packagelist="apk info"
[[ -z $packageupdater ]] && packageupdater="apk update && apk upgrade"
[[ -z $packmanUpdate ]] && packmanUpdate="$packageinstaller"
[[ -z $packageQuery ]] && packageQuery="apk info -e \$x "
[[ -z $langPackages ]] && langPackages="iso-codes"
[[ -z $dhcpname ]] && dhcpname=""
if [[ -z $webdirdest ]]; then
    if [[ -z $docroot ]]; then
        docroot="/var/www/"
        webdirdest="${docroot}fog/"
    elif [[ "$docroot" != *'fog'* ]]; then
        webdirdest="${docroot}fog/"
    else
        webdirdest="${docroot}/"
    fi
fi
[[ -z $webredirect ]] && webredirect="${webdirdest}/index.php"
[[ -z $apacheuser ]] && apacheuser="nginx"
[[ -z $apachelogdir ]] && apachelogdir="/var/log/nginx"
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/error.log"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/access.log"
[[ -z $httpdconf ]] && httpdconf="/etc/nginx/nginx.conf"
[[ -z $etcconf ]] && etcconf="/etc/nginx/http.d/default.conf"
[[ -z $phpini ]] && phpini="/etc/php7/php.ini"
[[ -z $storageLocation ]] && storageLocation="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${storageLocation}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/var/tftpboot"
[[ -z $tftpconfig ]] && tftpconfig="/etc/xinetd.d/tftpd"
[[ -z $ftpxinetd ]] && ftpxinetd="/etc/xinetd.d/vsftpd"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd.conf"
[[ -z $dhcpd ]] && dhcpd="dhcpd4"
[[ -z $snapindir ]] && snapindir="/opt/fog/snapins"
[[ -z $php_ver ]] && php_ver="7"
[[ -z $phpfpm ]] && phpfpm="php-fpm${php_ver}"
