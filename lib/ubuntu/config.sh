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
[[ -z $webserver ]] && webserver="apache2"
if [[ $linuxReleaseName_lower == +(*bian*) ]]; then
    sysvrcconf="sysv-rc-conf"
elif [[ $linuxReleaseName_lower == +(*ubuntu*|*mint*) ]]; then
    DEBIAN_FRONTEND=noninteractive apt-get purge -yq sysv-rc-conf >/dev/null 2>&1
    case $OSVersion in
        16)
            sysvrcconf="sysv-rc-conf"
            ;;
    esac
fi
case $linuxReleaseName_lower in
    *ubuntu*|*bian*|*mint*)
        if [[ -z $packages ]]; then
            x="mysql-server"
            eval $packageQuery >>$error_log 2>&1
            [[ $? -eq 0 ]] && db_packages="mysql-client mysql-server" || db_packages="mariadb-client mariadb-server"
            if [[ $webserver == "apache2" ]]; then
                libapache="libapache2-mod-fastcgi libapache2-mod-php"
            fi
            packages="attr build-essential cpp curl g++ gawk gcc gcc-aarch64-linux-gnu genisoimage git gzip htmldoc isc-dhcp-server isolinux lftp ${libapache} libc6 libcurl3 liblzma-dev m4 ${db_packages} net-tools nfs-kernel-server openssh-server php-fpm php php-cli php-curl php-gd php-json php-ldap php-mbstring php-mysql php-mysqlnd php-ssh2 ${sysvrcconf} tar tftpd-hpa tftp-hpa vsftpd wget zlib1g"
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
if [[ $webserver == apache2 ]]; then
    [[ -z $apacheuser ]] && apacheuser="www-data"
else
    [[ -z $apacheuser ]] && apacheuser="nginx"
fi
[[ -z $apachelogdir ]] && apachelogdir="/var/log/$webserver"
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/error.log"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/access.log"
# This will likely need adjustment as apache2 is only known one for now
[[ -z $etcconf ]] && etcconf="/etc/$webserver/sites-available/001-fog.conf"
[[ -z $storageLocation ]] && storageLocation="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${storageLocation}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcp3/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/tftpboot"
[[ -z $tftpconfigupstartdefaults ]] && tftpconfigupstartdefaults="/etc/default/tftpd-hpa"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd.conf"
[[ -z $snapindir ]] && snapindir="/opt/fog/snapins"
[[ -z $dhcpd ]] && dhcpd="isc-dhcp-server"
