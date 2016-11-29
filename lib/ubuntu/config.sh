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
[[ -z $repo ]] && repo="php"
[[ -z $php_ver ]] && php_ver=5
[[ -z $php_verAdds ]] && php_verAdds="-5.6"
if [[ $linuxReleaseName == +(*[Bb][Uu][Nn][Tt][Uu]*) ]]; then
    if [[ -z $php_ver || $php_ver == 5 || $php_ver == '5.6' ]]; then
        echo " *** Detected a potential need to reinstall apache and php files."
        echo " *** This will remove the /etc/php* and /etc/apache2* directories"
        echo " ***  and remove/purge the apache and php files from this system."
        echo " *** If you're okay with this please type Y, anything else will"
        echo " ***  break the installation and you will have to remove the files yourself"
        echo -n " ***  and make proper changes as necessary. (Y/N): "
        read dummy
        case $dummy in
            [Yy])
                dots "Removing apache and php files"
                rm -rf /etc/php* /etc/apache2*
                echo "Done"
                dots "Stopping web services"
                command -v systemctl && systemctl="yes"
                [[ -z $systemctl ]] && systemctl stop apache2 >/dev/null 2>&1 || service apache2 stop >/dev/null 2>&1
                [[ ! $? -eq 0 ]] && echo "Failed" || echo "Done"
                dots "Removing the apache and php packages"
                DEBIAN_FRONTEND=noninteractive apt-get purge -yq 'apache2*' 'php5*' 'php7*' 'libapache*' >/dev/null 2>&1
                [[ ! $? -eq 0 ]] && echo "Failed" || echo "Done"
                dots "Resetting our variables to specify php version 7.1"
                php_ver="7.1"
                php_verAdds="-7.1"
                phpfpm="php${php_ver}-fpm"
                phpldap="php${php_ver}-ldap"
                phpcmd="php"
                packages="apache2 build-essential cpp curl g++ gawk gcc gzip htmldoc isc-dhcp-server lftp libapache2-mod-fastcgi libapache2-mod-php${php_ver} libc6 libcurl3 m4 mysql-client mysql-server net-tools nfs-kernel-server openssh-server $phpfpm php-gettext php${php_ver} php${php_ver}-cli php${php_ver}-curl php${php_ver}-gd php${php_ver}-json $phpldap php${php_ver}-mcrypt php${php_ver}-mysql php${php_ver}-mysqlnd sysv-rc-conf tar tftpd-hpa tftp-hpa vsftpd wget xinetd zlib1g"
                echo "Done"
                ;;
        esac
    fi
fi
if [[ -z $phpcmd ]]; then
    [[ $php_ver != 5 ]] && phpcmd="php" || phpcmd="php5"
    [[ -z $phpfpm ]] && phpfpm="php${php_ver}-fpm" || phpfpm="php5-fpm"
fi
[[ -z $packageQuery ]] && packageQuery="dpkg -l \$x | grep '^ii'"
case $linuxReleaseName in
    *[Dd][Ee][Bb][Ii][Aa][Nn]*|*[Bb][Uu][Nn][Tt][Uu]*)
        [[ -z $packages ]] && packages="apache2 build-essential cpp curl g++ gawk gcc gzip htmldoc isc-dhcp-server lftp libapache2-mod-fastcgi libapache2-mod-php${php_ver} libc6 libcurl3 m4 mysql-client mysql-server net-tools nfs-kernel-server openssh-server $phpfpm php-gettext php${php_ver} php${php_ver}-cli php${php_ver}-curl php${php_ver}-gd php${php_ver}-json $phpldap php${php_ver}-mcrypt php${php_ver}-mysql php${php_ver}-mysqlnd sysv-rc-conf tar tftpd-hpa tftp-hpa vsftpd wget xinetd zlib1g"
        [[ -z $packageinstaller ]] && packageinstaller="apt-get -yq install -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"
        [[ -z $packagelist ]] && packagelist="apt-cache pkgnames | grep"
        [[ -z $packageupdater ]] && packageupdater="apt-get -yq upgrade -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"
        [[ -z $packmanUpdate ]] && packmanUpdate="apt-get update"
        [[ -z $dhcpname ]] && dhcpname="isc-dhcp-server"
        [[ -z $olddhcpname ]] && olddhcpname="dhcp3-server"
        ;;
esac
[[ $php_ver != 5 ]] && packages="$packages php${php_ver}-mbstring"
[[ -z $langPackages ]] && langPackages="language-pack-it language-pack-en language-pack-es language-pack-zh-hans"
if [[ $systemctl == yes ]]; then
    if [[ -e /lib/systemd/system/mariadb.service ]]; then
        ln -s /lib/systemd/system/mariadb.service /lib/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /lib/systemd/system/mariadb.service /lib/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /lib/systemd/system/mariadb.service /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /lib/systemd/system/mariadb.service /etc/systemd/system/mysqld.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    elif [[ -e /lib/systemd/system/mysqld.service ]]; then
        ln -s /lib/systemd/system/mysqld.service /usr/lib/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
        ln -s /lib/systemd/system/mysqld.service /etc/systemd/system/mysql.service >>$workingdir/error_logs/fog_error_${version}.log 2>&1
    fi
else
    initdpath="/etc/init.d"
    initdsrc="../packages/init.d/ubuntu"
    initdMCfullname="FOGMulticastManager"
    initdIRfullname="FOGImageReplicator"
    initdSDfullname="FOGScheduler"
    initdSRfullname="FOGSnapinReplicator"
    initdPHfullname="FOGPingHosts"
    initdSHfullname="FOGSnapinHash"
fi
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
[[ $php_ver != 5 ]] && phpini="/etc/$phpcmd/$php_ver/apache2/php.ini" || phpini="/etc/$phpcmd/apache2/php.ini"
[[ -z $storageLocation ]] && storageLocation="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${storageLocation}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcp3/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/tftpboot"
[[ -z $tftpconfig ]] && tftpconfig="/etc/xinetd.d/tftp"
[[ -z $tftpconfigupstartconf ]] && tftpconfigupstartconf="/etc/init/tftpd-hpa.conf"
[[ -z $tftpconfigupstartdefaults ]] && tftpconfigupstartdefaults="/etc/default/tftpd-hpa"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd.conf"
[[ -z $snapindir ]] && snapindir="/opt/fog/snapins"
[[ -z $jsontest ]] && jsontest="php${php_ver}-json php${php_ver}-common"
if [[ -z $dhcpd ]]; then
    if [[ -e /etc/init.d/$dhcpname ]]; then
        dhcpd=$dhcpname
    elif [[ -e /etc/init.d/$olddhcpname ]]; then
        dhcpd=$olddhcpname
    fi
fi
