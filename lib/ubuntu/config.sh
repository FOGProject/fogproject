#!/bin/bash
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
[[ -z $packageQuery ]] && packageQuery="dpkg -l \$x | grep '^ii'"
case $linuxReleaseName_lower in
    *ubuntu*|*bian*|*mint*)
        if [[ -z $packages ]]; then
            x="mysql-server"
            eval $packageQuery >>$error_log 2>&1
            [[ $? -eq 0 ]] && db_packages="mysql-client mysql-server" || db_packages="mariadb-client mariadb-server"
            packages="apache2 build-essential cpp curl g++ gawk gcc genisoimage git gzip htmldoc isc-dhcp-server isolinux lftp libapache2-mod-fastcgi libapache2-mod-php libc6 libcurl3 liblzma-dev m4 ${db_packages} net-tools nfs-kernel-server openssh-server php-fpm php php-cli php-curl php-gd php-json php-ldap php-mbstring php-mysql php-mysqlnd tar tftpd-hpa tftp-hpa vsftpd wget zlib1g"
        else
            # make sure we update the package list to not use specific version numbers anymore
            packages=${packages//php[0-9]\.[0-9]/php}
        fi
        [[ -z $packageinstaller ]] && packageinstaller="apt-get -yq install -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"
        [[ -z $packagelist ]] && packagelist="apt-cache pkgnames | grep"
        [[ -z $packageupdater ]] && packageupdater="apt-get -yq upgrade -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"
        [[ -z $packmanUpdate ]] && packmanUpdate="apt-get update"
        ;;
esac
[[ -z $langPackages ]] && langPackages="language-pack-it language-pack-en language-pack-es language-pack-zh-hans"
if [[ -z $webdirdest ]]; then
    if [[ -z $docroot ]]; then
        docroot="/var/www/html/"
        webdirdest="${docroot}fog/"
    elif [[ "$docroot" != *'fog'* ]]; then
        webdirdest="${docroot}fog/"
    else
        webdirdest="${docroot}/"
    fi
    if [[ $docroot == /var/www/html/ && ! -d $docroot ]]; then
        docroot="/var/www/"
        webdirdest="${docroot}fog/"
    fi
fi
[[ -z $webredirect ]] && webredirect="$docroot/index.php"
[[ -z $apacheuser ]] && apacheuser="www-data"
[[ -z $apachelogdir ]] && apachelogdir="/var/log/apache2"
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/error.log"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/access.log"
[[ -z $etcconf ]] && etcconf="/etc/apache2/sites-available/001-fog.conf"
[[ -z $storageLocation ]] && storageLocation="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${storageLocation}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcp3/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/tftpboot"
[[ -z $tftpconfigupstartdefaults ]] && tftpconfigupstartdefaults="/etc/default/tftpd-hpa"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd.conf"
[[ -z $snapindir ]] && snapindir="/opt/fog/snapins"
[[ -z $dhcpd ]] && dhcpd="isc-dhcp-server"
[[ -z $dhcpname ]] && dhcpname="isc-dhcp-server"
