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
[[ $? -eq 0 ]] && repos="remi" || repos="remi,remi-php56,epel"
packageQuery="rpm -q \$x"
case $linuxReleaseName in
    *[Mm][Aa][Gg][Ee][Ii][Aa]*)
        packages="apache apache-mod_php php-gd php-cli php-gettext mariadb mariadb-common mariadb-core mariadb-common-core dhcp-server tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ htmldoc perl perl-Crypt-PasswdMD5 lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl php-fpm php-process"
        packageinstaller="urpmi --auto"
        packagelist="urpmq"
        packageupdater="$packageinstaller"
        packmanUpdate="urpmi.update -a"
        dhcpname="dhcp-server"
        tftpdirdst="/var/lib/tftpboot"
        nfsexportsopts="no_subtree_check"
        ;;
    *)
        packages="httpd php php-cli php-common php-gd mysql mysql-server dhcp tftp-server nfs-utils vsftpd net-tools wget xinetd tar gzip make m4 gcc gcc-c++ lftp php-mysqlnd curl php-mcrypt php-mbstring mod_ssl php-fpm php-process"
        command -v dnf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        if [[ $? -eq 0 ]]; then
            packageinstaller="dnf -y --enablerepo=$repos install"
            packagelist="dnf list --enablerepo=$repos"
            packageupdater="dnf -y --enablerepo=$repos update"
            packmanUpdate="dnf --enablerepo=$repos check-update"
        else
            packageinstaller="yum -y --enablerepo=$repos install"
            packagelist="yum --enablerepo=$repos list"
            packageupdater="yum -y --enablerepo=$repos update"
            packmanUpdate="yum check-update"
            command -v yum-config-manager >/dev/null 2>&1
            if [[ ! $? -eq 0 ]]; then
                $packageinstaller yum-utils >/dev/null 2>&1
            fi
            repoenable="yum-config-manager --enable"
        fi
        dhcpname="dhcp"
        ;;
esac
langPackages="iso-codes"
if [[ $systemctl == yes ]]; then
    initdpath="/usr/lib/systemd/system"
    initdsrc="../packages/systemd"
    if [[ -e /usr/lib/systemd/system/mariadb.service ]]; then
        ln -s /usr/lib/systemd/system/mariadb.service /usr/lib/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /usr/lib/systemd/system/mariadb.service /usr/lib/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /usr/lib/systemd/system/mariadb.service /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /usr/lib/systemd/system/mariadb.service /etc/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    elif [[ -e /usr/lib/systemd/system/mysqld.service ]]; then
        ln -s /usr/lib/systemd/system/mysqld.service /usr/lib/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /usr/lib/systemd/system/mysqld.service /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
    initdMCfullname="FOGMulticastManager.service"
    initdIRfullname="FOGImageReplicator.service"
    initdSDfullname="FOGScheduler.service"
    initdSRfullname="FOGSnapinReplicator.service"
    initdPHfullname="FOGPingHosts.service"
else
    initdpath="/etc/rc.d/init.d"
    initdsrc="../packages/init.d/redhat"
    initdMCfullname="FOGMulticastManager"
    initdIRfullname="FOGImageReplicator"
    initdSDfullname="FOGScheduler"
    initdSRfullname="FOGSnapinReplicator"
    initdPHfullname="FOGPingHosts"
fi
if [[ -z $docroot ]]; then
    docroot="/var/www/html/"
    webdirdest="${docroot}fog/"
elif [[ $docroot != *'fog'* ]]; then
    webdirdest="${docroot}fog/"
else
    webdirdest="${docroot}/"
fi
webredirect="${webdirdest}/index.php"
apacheuser="apache"
apachelogdir="/var/log/httpd"
apacheerrlog="$apachelogdir/error_log"
apacheacclog="$apachelogdir/access_log"
etcconf="/etc/httpd/conf.d/fog.conf"
phpini="/etc/php.ini"
storageLocation="/images"
storageLocationUpload="${storageLocation}/dev"
dhcpconfig="/etc/dhcpd.conf"
dhcpconfigother="/etc/dhcp/dhcpd.conf"
tftpdirdst="/tftpboot"
tftpconfig="/etc/xinetd.d/tftp"
ftpconfig="/etc/vsftpd/vsftpd.conf"
dhcpd="dhcpd"
snapindir="/opt/fog/snapins"
