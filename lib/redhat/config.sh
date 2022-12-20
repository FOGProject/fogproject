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
[[ -z $packageQuery ]] && packageQuery="rpm -q \$x"
case $linuxReleaseName_lower in
    *mageia*)
        [[ -z $packages ]] && packages="apache apache-mod_fcgid apache-mod_php apache-mod_ssl cdrkit-genisoimage curl dhcp-server gcc gcc-c++ git gzip htmldoc lftp m4 make mariadb mariadb-common mariadb-common-core mariadb-core net-tools nfs-utils perl perl-Crypt-PasswdMD5 php-cli php-curl php-fpm php-gd php-gettext php-ldap php-mbstring php-mysqlnd php-pcntl php-pdo php-pdo_mysql tar tftp-server util-linux vsftpd wget"
        [[ -z $packageinstaller ]] && packageinstaller="urpmi --auto"
        [[ -z $packagelist ]] && packagelist="urpmq"
        [[ -z $packageupdater ]] && packageupdater="$packageinstaller"
        [[ -z $packmanUpdate ]] && packmanUpdate="urpmi.update -a"
        [[ -z $dhcpname ]] && dhcpname="dhcp-server"
        [[ -z $tftpdirdst ]] && tftpdirdst="/var/lib/tftpboot"
        [[ -z $nfsexportsopts ]] && nfsexportsopts="no_subtree_check"
        [[ -z $etcconf ]] && etcconf="/etc/httpd/conf/conf.d/fog.conf"
        ;;
    *)
        [[ -z $etcconf ]] && etcconf="/etc/httpd/conf.d/fog.conf"
        [[ -z $packages ]] && {
            if [[ $OSVersion -gt 7 ]]; then
                packages="curl dhcp-server gcc gcc-c++ genisoimage git gzip httpd lftp m4 make mod_fastcgi mod_ssl mtools mysql mysql-server net-tools nfs-utils openssl php php-cli php-common php-fpm php-gd php-json php-ldap php-mbstring php-mysqlnd php-process syslinux tar tftp-server util-linux-user vsftpd wget xz-devel"
                [[ -z $dhcpname ]] && dhcpname="dhcp-server"
            else
                packages="curl dhcp gcc gcc-c++ genisoimage git gzip httpd lftp m4 make mod_fastcgi mod_ssl mtools mysql mysql-server net-tools nfs-utils openssl php php-cli php-common php-fpm php-gd php-ldap php-mbstring php-mysqlnd php-process syslinux tar tftp-server util-linux vsftpd wget xz-devel"
            fi
        }
        pkginst=$(command -v dnf)
        if [[ -n $pkginst ]]; then
            [[ -z $repoenable ]] && repoenable="dnf config-manager --set-enabled"
        else
            pkginst=$(command -v yum)
            if [[ -z $pkginst ]]; then
                echo " ### NO PACKAGE MANAGER FOUND ###"
                exit 1
            fi
            [[ -z $repoenable ]] && repoenable="yum-config-manager --enable"
            command -v yum-config-manager >/dev/null 2>&1
            [[ ! $? -eq 0 ]] && $pkginst -y install yum-utils >/dev/null 2>&1
        fi
        [[ -z $packageinstaller ]] && packageinstaller="$pkginst -y install"
        [[ -z $packagelist ]] && packagelist="$pkginst list"
        [[ -z $packageupdater ]] && packageupdater="$pkginst -y update"
        [[ -z $packmanUpdate ]] && packmanUpdate="$pkginst -y check-update"
        [[ -z $dhcpname ]] && dhcpname="dhcp"
        ;;
esac
[[ -z $langPackages ]] && langPackages="iso-codes"
if [[ -z $webdirdest ]]; then
    if [[ -z $docroot ]]; then
        docroot="/var/www/html/"
        webdirdest="${docroot}fog/"
    elif [[ $docroot != *'fog'* ]]; then
        webdirdest="${docroot}fog/"
    else
        webdirdest="${docroot}/"
    fi
fi
[[ -z $webredirect ]] && webredirect="${webdirdest}/index.php"
[[ -z $apacheuser ]] && apacheuser="apache"
[[ -z $apachelogdir ]] && apachelogdir="/var/log/httpd"
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/error_log"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/access_log"
[[ -z $phpfpm ]] && phpfpm="php-fpm"
[[ -z $phpini ]] && phpini="/etc/php.ini"
[[ -z $storageLocation ]] && storageLocation="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${storageLocation}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/tftpboot"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd/vsftpd.conf"
[[ -z $dhcpd ]] && dhcpd="dhcpd"
[[ -z $snapindir ]] && snapindir="/opt/fog/snapins"
