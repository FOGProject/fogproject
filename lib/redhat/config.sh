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
        WEB_server_engine="apache"
        [[ -z ${FOG_packages} ]] && FOG_packages="apache apache-mod_fcgid apache-mod_php apache-mod_ssl attr cdrkit-genisoimage curl dhcp-server gcc gcc-aarch64-linux-gnu gcc-c++ git gzip htmldoc lftp m4 make mariadb mariadb-common mariadb-common-core mariadb-core net-tools nfs-utils perl perl-Crypt-PasswdMD5 php-cli php-curl php-fpm php-gd php-gettext php-ldap php-mbstring php-mysqlnd php-pcntl php-pdo php-pdo_mysql php-pecl-ssh2 tar tftp-server util-linux vsftpd wget"
        [[ -z $packageinstaller ]] && packageinstaller="urpmi --auto"
        [[ -z $packagelist ]] && packagelist="urpmq"
        [[ -z $packageupdater ]] && packageupdater="$packageinstaller"
        [[ -z $packmanUpdate ]] && packmanUpdate="urpmi.update -a"
        # Bulk forms of packageQuery/packagelist -- see loadPackageSets.
        pkgQueryAll() { rpm -qa --qf '%{NAME}\n' 2>/dev/null; }
        pkgListAll() { urpmq --list 2>/dev/null; }
        [[ -z $dhcpname ]] && dhcpname="dhcp-server"
        [[ -z $tftpdirdst ]] && tftpdirdst="/var/lib/tftpboot"
        [[ -z $nfsexportsopts ]] && nfsexportsopts="subtree_check"
        [[ -z $etcconf ]] && etcconf="/etc/httpd/conf/conf.d/fog.conf"
        ;;
    *)
        [[ -z ${WEB_server_engine} ]] && WEB_server_engine="httpd"
        [[ -z $etcconf ]] && etcconf="/etc/${WEB_server_engine}/conf.d/fog.conf"
        [[ -z ${FOG_packages} ]] && {
            if [[ $OSVersion -gt 7 ]]; then
                FOG_packages="attr curl dhcp-server gcc gcc-aarch64-linux-gnu gcc-c++ genisoimage git gzip lftp m4 make mod_fastcgi mod_ssl mtools mysql mysql-server net-tools nfs-utils openssl php php-cli php-common php-fpm php-gd php-json php-ldap php-mbstring php-mysqlnd php-process php-pecl-ssh2 syslinux tar tftp-server util-linux-user vsftpd wget xz-devel"
                [[ -z $dhcpname ]] && dhcpname="dhcp-server"
            else
                FOG_packages="attr curl dhcp gcc gcc-aarch64-linux-gnu gcc-c++ genisoimage git gzip lftp m4 make mod_fastcgi mod_ssl mtools mysql mysql-server net-tools nfs-utils openssl php php-cli php-common php-fpm php-gd php-ldap php-mbstring php-mysqlnd php-process php-pecl-ssh2 syslinux tar tftp-server util-linux vsftpd wget xz-devel"
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
        # Bulk forms of packageQuery/packagelist -- see loadPackageSets.
        #
        # `$pkginst list available` is deliberately not used for pkgListAll:
        # its output is columnar and wraps long names onto a second line, so
        # parsing it is guesswork. repoquery emits one bare name per line and
        # loads the repo metadata once instead of once per package. dnf carries
        # it as a subcommand; on yum it is the separate repoquery binary from
        # yum-utils, which may not be installed -- if neither resolves, nothing
        # is printed and pkgIsAvailable falls back to the per-package probe.
        #
        # -C reads the cache rather than refreshing it: $packmanUpdate has just
        # run, so the metadata is already current, and a cache-only read cannot
        # block on an unreachable mirror. A cold or empty cache yields nothing,
        # which is the fallback signal, not a wrong answer.
        pkgQueryAll() { rpm -qa --qf '%{NAME}\n' 2>/dev/null; }
        pkgListAll() {
            if [[ $pkginst == *dnf ]]; then
                $pkginst repoquery -q -C --available --qf '%{name}\n' 2>/dev/null
            elif command -v repoquery >/dev/null 2>&1; then
                repoquery -C -a --qf '%{name}' 2>/dev/null
            fi
        }
        [[ -z $dhcpname ]] && dhcpname="dhcp"
        ;;
esac
[[ -z $langPackages ]] && langPackages="iso-codes"
if [[ -z $webdirdest ]]; then
    if [[ -z ${WEB_docroot} ]]; then
        WEB_docroot="/var/www/html/"
        webdirdest="${WEB_docroot}fog/"
    elif [[ ${WEB_docroot} != *'fog'* ]]; then
        webdirdest="${WEB_docroot}fog/"
    else
        webdirdest="${WEB_docroot}/"
    fi
fi
[[ -z $webredirect ]] && webredirect="${webdirdest}/index.php"
[[ -z $apachelogdir ]] && apachelogdir="/var/log/${WEB_server_engine}"
if [[ ${WEB_server_engine} == httpd ]]; then
    [[ -z $apacheuser ]] && apacheuser="apache"
    httperrlog="error_log"
    httpacclog="access_log"
elif [[ ${WEB_server_engine} == nginx ]]; then
    [[ -z $apacheuser ]] && apacheuser="nginx"
    httperrlog="error.log"
    httpacclog="access.log"
fi
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/$httperrlog"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/$httpacclog"
[[ -z $phpfpm ]] && phpfpm="php-fpm"
[[ -z $phpini ]] && phpini="/etc/php.ini"
[[ -z ${STORAGE_image_share_path} ]] && STORAGE_image_share_path="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${STORAGE_image_share_path}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/tftpboot"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd/vsftpd.conf"
[[ -z ${DHCP_service_name} ]] && DHCP_service_name="dhcpd"
[[ -z $iscservice ]] && iscservice="dhcpd"
[[ -z $keapackage ]] && keapackage="kea"
[[ -z $keaservice ]] && keaservice="kea-dhcp4"
[[ -z $snapindir ]] && snapindir="$fogprogramdir/snapins"
