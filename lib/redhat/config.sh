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
command -v dnf >/dev/null 2>&1
[[ $? -eq 0 ]] && repos="remi,remi-php56,epel" || repos="remi,remi-php56,epel"
[[ -z $packageQuery ]] && packageQuery="rpm -q \$x"
case $linuxReleaseName in
    *[Mm][Aa][Gg][Ee][Ii][Aa]*)
        [[ -z $packages ]] && packages="apache apache-mod_php php-gd php-cli php-gettext mariadb mariadb-common mariadb-core mariadb-common-core dhcp-server tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ htmldoc perl perl-Crypt-PasswdMD5 lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl php-fpm php-process mod_fastcgi"
        [[ -z $packageinstaller ]] && packageinstaller="urpmi --auto"
        [[ -z $packagelist ]] && packagelist="urpmq"
        [[ -z $packageupdater ]] && packageupdater="$packageinstaller"
        [[ -z $packmanUpdate ]] && packmanUpdate="urpmi.update -a"
        [[ -z $dhcpname ]] && dhcpname="dhcp-server"
        [[ -z $tftpdirdst ]] && tftpdirdst="/var/lib/tftpboot"
        [[ -z $nfsexportsopts ]] && nfsexportsopts="no_subtree_check"
        ;;
    *)
        [[ -z $packages ]] && packages="httpd php php-cli php-common php-gd mysql mysql-server dhcp tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl php-fpm php-process mod_fastcgi"
        command -v dnf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ $? -eq 0 ]]; then
            [[ -z $packageinstaller ]] && packageinstaller="dnf -y --enablerepo=$repos install"
            [[ -z $packagelist ]] && packagelist="dnf list --enablerepo=$repos"
            [[ -z $packageupdater ]] && packageupdater="dnf -y --enablerepo=$repos update"
            [[ -z $packageUpdate ]] && packmanUpdate="dnf --enablerepo=$repos check-update"
        else
            [[ -z $packageinstaller ]] && packageinstaller="yum -y --enablerepo=$repos install"
            [[ -z $packagelist ]] && packagelist="yum --enablerepo=$repos list"
            [[ -z $packageupdater ]] && packageupdater="yum -y --enablerepo=$repos update"
            [[ -z $packmanUpdate ]] && packmanUpdate="yum check-update"
            command -v yum-config-manager >/dev/null 2>&1
            [[ ! $? -eq 0 ]] && $packageinstaller yum-utils >/dev/null 2>&1
            command -v yum-config-manager >/dev/null 2>&1
            [[ $? -eq 0 ]] && repoenable="yum-config-manager --enable"
        fi
        [[ -z $dhcpname ]] && dhcpname="dhcp"
        ;;
esac
[[ -z $langPackages ]] && langPackages="iso-codes"
if [[ $systemctl == yes ]]; then
    if [[ -e /usr/lib/systemd/system/mariadb.service ]]; then
        ln -s /usr/lib/systemd/system/mariadb.service /usr/lib/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /usr/lib/systemd/system/mariadb.service /usr/lib/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /usr/lib/systemd/system/mariadb.service /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /usr/lib/systemd/system/mariadb.service /etc/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    elif [[ -e /usr/lib/systemd/system/mysqld.service ]]; then
        ln -s /usr/lib/systemd/system/mysqld.service /usr/lib/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /usr/lib/systemd/system/mysqld.service /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
else
    initdpath="/etc/rc.d/init.d"
    initdsrc="../packages/init.d/redhat"
    initdMCfullname="FOGMulticastManager"
    initdIRfullname="FOGImageReplicator"
    initdSDfullname="FOGScheduler"
    initdSRfullname="FOGSnapinReplicator"
    initdPHfullname="FOGPingHosts"
fi
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
[[ -z $etcconf ]] && etcconf="/etc/httpd/conf.d/fog.conf"
[[ -z $phpini ]] && phpini="/etc/php.ini"
[[ -z $storageLocation ]] && storageLocation="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${storageLocation}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/tftpboot"
[[ -z $tftpconfig ]] && tftpconfig="/etc/xinetd.d/tftp"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd/vsftpd.conf"
[[ -z $dhcp ]] && dhcpd="dhcpd"
[[ -z $snapindir ]] && snapindir="/opt/fog/snapins"
