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
[[ -z $packageQuery ]] && packageQuery="dpkg -l \$x | grep '^ii'"
if [[ $linuxReleaseName == +(*[Bb][Ii][Aa][Nn]*) ]]; then
    sysvrcconf="sysv-rc-conf"
    case $OSVersion in
        8)
            php_ver="5"
            ;;
        9)
            php_ver="7.0"
            x="*php5*"
            ;;
        10)
            php_ver="7.3"
            x="*php5* *php7.0*"
            ;;
    esac
    old_php=$(eval $packageQuery 2>/dev/null | awk '{print $2}' | tr '\n' ' ')
    if [[ -n "$old_php" ]]; then
        dots "Removing old PHP version before installing the new one"
        DEBIAN_FRONTEND=noninteractive apt-get purge -yq ${old_php} >/dev/null 2>&1
        [[ $? -ne 0 ]] && echo "Failed" || echo "Done"
        apt-get clean -yq >/dev/null 2>&1
    fi
elif [[ $linuxReleaseName == +(*[Uu][Bb][Uu][Nn][Tt][Uu]*|*[Mm][Ii][Nn][Tt]*) ]]; then
    DEBIAN_FRONTEND=noninteractive apt-get purge -yq sysv-rc-conf >/dev/null 2>&1
    case $OSVersion in
        20)
            php_ver="7.4"
            ;;
        19)
            php_ver="7.3"
            ;;
        18)
            php_ver="7.2"
            ;;
        *)
            sysvrcconf="sysv-rc-conf"
            php_ver="7.1"
            x="*php5* *php-5*"
            eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            if [[ $? -ne 0 ]]; then
                if [[ $autoaccept != yes ]]; then
                    echo " *** Detected a potential need to reinstall apache and php files."
                    echo " *** This will remove the /etc/php* and /etc/apache2* directories"
                    echo " ***  and remove/purge the apache and php files from this system."
                    echo " *** If you're okay with this please type Y, anything else will"
                    echo " ***  continue the installation, but may mean you will need to"
                    echo " ***  remove the files later and make proper changes as "
                    echo " ***  necessary. (Y/N): "
                    read dummy
                else
                    dummy="y"
                fi
                case $dummy in
                    [Yy])
                        dots "Removing apache and php files"
                        rm -rf /etc/php* /etc/apache2*
                        echo "Done"
                        dots "Stopping web services"
                        if [[ $systemctl == yes ]]; then
                            systemctl is-active --quiet apache2 && systemctl stop apache2 >/dev/null 2>&1 || true
                        fi
                        [[ ! $? -eq 0 ]] && echo "Failed" || echo "Done"
                        dots "Removing the apache and php packages"
                        DEBIAN_FRONTEND=noninteractive apt-get purge -yq 'apache2*' 'php5*' 'php7*' 'libapache*' >/dev/null 2>&1
                        [[ ! $? -eq 0 ]] && echo "Failed" || echo "Done"
                        apt-get clean -yq >/dev/null 2>&1
                        ;;
                esac
            fi
    esac
else
    [[ -z $php_ver ]] && php_ver=5
fi
[[ -z $php_verAdds ]] && php_verAdds="-${php_ver}"
[[ $php_ver == 5 ]] && php_verAdds="-5.6"
[[ $php_ver != 5 ]] && phpcmd="php" || phpcmd="php5"
[[ -z $phpfpm ]] && phpfpm="php${php_ver}-fpm"
[[ -z $phpldap ]] && phpldap="php${php_ver}-ldap"
[[ -z $phpcmd ]] && phpcmd="php"
case $linuxReleaseName in
    *[Uu][Bb][Uu][Nn][Tt][Uu]*|*[Bb][Ii][Aa][Nn]*|*[Mm][Ii][Nn][Tt]*)
        if [[ -z $packages ]]; then
            x="mysql-server"
            eval $packageQuery >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            [[ $? -eq 0 ]] && db_packages="mysql-client mysql-server" || db_packages="mariadb-client mariadb-server"
            packages="apache2 build-essential cpp curl g++ gawk gcc genisoimage git gzip htmldoc isc-dhcp-server isolinux lftp libapache2-mod-fastcgi libapache2-mod-php${php_ver} libc6 libcurl3 liblzma-dev m4 ${db_packages} net-tools nfs-kernel-server openssh-server $phpfpm php-gettext php${php_ver} php${php_ver}-cli php${php_ver}-curl php${php_ver}-gd php${php_ver}-json $phpldap php${php_ver}-mysql php${php_ver}-mysqlnd ${sysvrcconf} tar tftpd-hpa tftp-hpa vsftpd wget xinetd zlib1g"
        else
            # make sure we update all the php version numbers with those specified above
            packages=${packages//php[0-9]\.[0-9]/php${php_ver}}
        fi
        [[ -z $packageinstaller ]] && packageinstaller="apt-get -yq install -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"
        [[ -z $packagelist ]] && packagelist="apt-cache pkgnames | grep"
        [[ -z $packageupdater ]] && packageupdater="apt-get -yq upgrade -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"
        [[ -z $packmanUpdate ]] && packmanUpdate="apt-get update"
        [[ -z $dhcpname ]] && dhcpname="isc-dhcp-server"
        [[ -z $olddhcpname ]] && olddhcpname="dhcp3-server"
        ;;
esac
[[ -z $langPackages ]] && langPackages="language-pack-it language-pack-en language-pack-es language-pack-zh-hans"
[[ $php_ver != 5 ]] && packages="$packages php${php_ver}-mbstring"
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
[[ $php_ver != 5 ]] && phpini="/etc/$phpcmd/$php_ver/fpm/php.ini" || phpini="/etc/$phpcmd/fpm/php.ini"
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
