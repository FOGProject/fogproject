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
[[ -z $packages ]] && packages="apache bc cdrtools curl dhcp gcc gzip lftp m4 make mariadb mod_fastcgi net-tools nfs-utils openssh openssl perl perl-crypt-passwdmd5 php php-apache php-fpm php-gd php-mcrypt syslinux tar tftp-hpa vsftpd wget xinetd xz"
[[ -z $packageinstaller ]] && packageinstaller="pacman -Sy --noconfirm"
[[ -z $packagelist ]] && packagelist="pacman -Si"
[[ -z $packageupdater ]] && packageupdater="pacman -Syu --noconfirm"
[[ -z $packmanUpdate ]] && packmanUpdate="$packageinstaller"
[[ -z $packageQuery ]] && packageQuery="pacman -Q \$x"
[[ -z $langPackages ]] && langPackages="iso-codes"
[[ -z $dhcpname ]] && dhcpname="dhcp"
if [[ -z $webdirdest ]]; then
    if [[ -z $docroot ]]; then
        docroot="/srv/http/"
        webdirdest="${docroot}fog/"
    elif [[ "$docroot" != *'fog'* ]]; then
        webdirdest="${docroot}fog/"
    else
        webdirdest="${docroot}/"
    fi
fi
[[ -z $webredirect ]] && webredirect="${webdirdest}/index.php"
[[ -z $apacheuser ]] && apacheuser="http"
[[ -z $apachelogdir ]] && apachelogdir="/var/log/httpd"
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/error_log"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/access_log"
[[ -z $etcconf ]] && etcconf="/etc/httpd/conf/extra/fog.conf"
[[ -z $phpini ]] && phpini="/etc/php/php.ini"
[[ -z $storageLocation ]] && storageLocation="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${storageLocation}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/srv/tftp"
[[ -z $tftpconfig ]] && tftpconfig="/etc/xinetd.d/tftpd"
[[ -z $ftpxinetd ]] && ftpxinetd="/etc/xinetd.d/vsftpd"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd.conf"
[[ -z $dhcpd ]] && dhcpd="dhcpd4"
[[ -z $snapindir ]] && snapindir="/opt/fog/snapins"
