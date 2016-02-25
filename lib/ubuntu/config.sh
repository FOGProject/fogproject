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
[[ -z $php_ver ]] && php_ver=5
[[ -z $php_verAdds ]] && php_verAdds="-5.6"
[[ $php_ver != 5 ]] && repo="php${php_verAdds}" || repo="php${php_ver}${php_verAdds}"
[[ $php_ver != 5 ]] && phpcmd="php" || phpcmd="php5"
[[ $php_ver != 5 ]] && phpfpm="php${php_ver}-fpm" || phpfpm="php5-fpm"
packageQuery="dpkg -l \$x | grep '^ii'"
case $linuxReleaseName in
    *[Dd][Ee][Bb][Ii][Aa][Nn]*|*[Bb][Uu][Nn][Tt][Uu]*)
        packages="apache2 php${php_ver} php${php_ver}-json php${php_ver}-gd php${php_ver}-cli php${php_ver}-curl mysql-server mysql-client isc-dhcp-server tftpd-hpa tftp-hpa nfs-kernel-server vsftpd net-tools wget xinetd  sysv-rc-conf tar gzip build-essential cpp gcc g++ m4 htmldoc lftp openssh-server php-gettext php${php_ver}-mcrypt php${php_ver}-mysqlnd curl libc6 libcurl3 zlib1g php${php_ver}-fpm libapache2-mod-php${php_ver}"
        packageinstaller="apt-get -yq install -o Dpkg::='--force-confdef' -o Dpkg::Options::='--force-confold'"
        packagelist="apt-cache pkgnames | grep"
        packageupdater="apt-get -yq upgrade -o Dpkg::='--force-confdef' -o Dpkg::Options::='--force-confold'"
        packmanUpdate="apt-get update"
        dhcpname="isc-dhcp-server"
        olddhcpname="dhcp3-server"
        ;;
esac
[[ $php_ver != 5 ]] && packages="$packages php${php_ver}-mbstring"
langPackages="language-pack-it language-pack-en language-pack-es language-pack-zh-hans"
if [[ $systemctl == yes ]]; then
	initdpath="/lib/systemd/system"
	initdsrc="../packages/systemd"
    if [[ -e /lib/systemd/system/mariadb.service ]]; then
        ln -s /lib/systemd/system/mariadb.service /lib/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /lib/systemd/system/mariadb.service /lib/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /lib/systemd/system/mariadb.service /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /lib/systemd/system/mariadb.service /etc/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
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
	initdpath="/etc/init.d"
	initdsrc="../packages/init.d/ubuntu"
	initdMCfullname="FOGMulticastManager"
	initdIRfullname="FOGImageReplicator"
	initdSDfullname="FOGScheduler"
	initdSRfullname="FOGSnapinReplicator"
	initdPHfullname="FOGPingHosts"
fi
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
webredirect="$docroot/index.php"
apacheuser="www-data"
apachelogdir="/var/log/apache2"
apacheerrlog="$apachelogdir/error.log"
apacheacclog="$apachelogdir/access.log"
etcconf="/etc/apache2/sites-available/001-fog.conf"
[[ $php_ver != 5 ]] && phpini="/etc/$phpcmd/$php_ver/apache2/php.ini" || phpini="/etc/$phpcmd/apache2/php.ini"
storageLocation="/images"
storageLocationUpload="${storageLocation}/dev"
dhcpconfig="/etc/dhcp3/dhcpd.conf"
dhcpconfigother="/etc/dhcp/dhcpd.conf"
tftpdirdst="/tftpboot"
tftpconfig="/etc/xinetd.d/tftp"
tftpconfigupstartconf="/etc/init/tftpd-hpa.conf"
tftpconfigupstartdefaults="/etc/default/tftpd-hpa"
ftpconfig="/etc/vsftpd.conf"
snapindir="/opt/fog/snapins"
jsontest="php${php_ver}-json php${php_ver}-common"
if [[ -e /etc/init.d/$dhcpname ]]; then
    dhcpd=$dhcpname
elif [[ -e /etc/init.d/$olddhcpname ]]; then
    dhcpd=$olddhcpname
fi
