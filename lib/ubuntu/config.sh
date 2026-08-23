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
[[ -z ${WEB_server_engine} ]] && WEB_server_engine="apache2"
if [[ $linuxReleaseName_lower == +(*bian*) ]]; then
    # Debian 13+ (Trixie) is systemd-only and dropped the sysv-rc-conf package,
    # which is never used on a systemctl system anyway. Omit it there.
    if [[ "$OSVersion" -ge 13 ]] 2>/dev/null; then
        sysvrcconf=""
    else
        sysvrcconf="sysv-rc-conf"
    fi
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
        if [[ -z ${FOG_packages} ]]; then
            x="mysql-server"
            eval $packageQuery >>$error_log 2>&1
            [[ $? -eq 0 ]] && db_packages="mysql-client mysql-server" || db_packages="mariadb-client mariadb-server"
            if [[ ${WEB_server_engine} == "apache2" ]]; then
                libapache="libapache2-mod-fastcgi libapache2-mod-php"
            fi
            FOG_packages="attr build-essential cpp curl g++ gawk gcc gcc-aarch64-linux-gnu genisoimage git gzip htmldoc isc-dhcp-server isolinux lftp ${libapache} libc6 libcurl3 liblzma-dev m4 ${db_packages} net-tools nfs-kernel-server openssh-server php-fpm php php-cli php-curl php-gd php-json php-ldap php-mbstring php-mysql php-ssh2 ${sysvrcconf} tar tftpd-hpa tftp-hpa vsftpd wget zlib1g"
        else
            # make sure we update the package list to not use specific version numbers anymore
            FOG_packages=${FOG_packages//php[0-9]\.[0-9]/php}
            # Debian 13+ dropped sysv-rc-conf; strip it from cached package lists (upgrades)
            [[ $linuxReleaseName_lower == +(*bian*) && "$OSVersion" -ge 13 ]] 2>/dev/null && FOG_packages="${FOG_packages//sysv-rc-conf/}"
        fi
        [[ -z $packageinstaller ]] && packageinstaller="apt-get -yq install -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"
        [[ -z $packagelist ]] && packagelist="apt-cache pkgnames | grep"
        # `apt-get upgrade <names>` does NOT restrict itself to those names --
        # it upgrades every upgradable package on the box, so installing FOG
        # quietly dist-upgraded the admin's server. `install --only-upgrade`
        # does what the step was named for: upgrade these packages, and skip
        # any of them that are not installed.
        [[ -z $packageupdater ]] && packageupdater="apt-get -yq install --only-upgrade -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"
        [[ -z $packmanUpdate ]] && packmanUpdate="apt-get update"
        # Bulk forms of packageQuery/packagelist -- see loadPackageSets.
        #
        # ${Status} expands to three words ("install ok installed"), so $4 is
        # the current state. Keying on that rather than on the "ii" pair
        # packageQuery greps for means a package the admin has put on hold
        # ("hi ok installed") counts as installed, instead of being reinstalled
        # on every run.
        pkgQueryAll() {
            dpkg-query -W -f='${Package} ${Status}\n' 2>/dev/null | awk '$4 == "installed" { print $1 }'
        }
        pkgListAll() { apt-cache pkgnames 2>/dev/null; }
        ;;
esac
[[ -z $langPackages ]] && langPackages="language-pack-it language-pack-en language-pack-es language-pack-zh-hans"
if [[ -z $webdirdest ]]; then
    if [[ -z ${WEB_docroot} ]]; then
        WEB_docroot="/var/www/html/"
        webdirdest="${WEB_docroot}fog/"
    elif [[ "${WEB_docroot}" != *'fog'* ]]; then
        webdirdest="${WEB_docroot}fog/"
    else
        webdirdest="${WEB_docroot}/"
    fi
    # GH-953: there used to be a fallback to /var/www/ here when /var/www/html
    # did not exist. This file is sourced long before installPackages, and
    # installing apache2/nginx is what creates /var/www/html -- so the test
    # answered differently on the first run than on every run after it, and the
    # two runs installed to two different trees. /var/www/html has been the
    # Debian docroot since Debian 8; the directory merely not existing yet says
    # nothing about the platform, and mkdir -p "$webdirdest" creates it later
    # in configureHttpd anyway. Existing installs keep their own path -- docroot
    # is a managed key in .fogsettings, so it never reaches this branch.
fi
[[ -z $webredirect ]] && webredirect="${WEB_docroot}/index.php"
if [[ ${WEB_server_engine} == apache2 ]]; then
    [[ -z $apacheuser ]] && apacheuser="www-data"
else
    [[ -z $apacheuser ]] && apacheuser="nginx"
fi
[[ -z $apachelogdir ]] && apachelogdir="/var/log/${WEB_server_engine}"
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/error.log"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/access.log"
# This will likely need adjustment as apache2 is only known one for now
[[ -z $etcconf ]] && etcconf="/etc/${WEB_server_engine}/sites-available/001-fog.conf"
[[ -z ${STORAGE_image_share_path} ]] && STORAGE_image_share_path="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${STORAGE_image_share_path}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcp3/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/tftpboot"
[[ -z $tftpconfigupstartdefaults ]] && tftpconfigupstartdefaults="/etc/default/tftpd-hpa"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd.conf"
[[ -z $snapindir ]] && snapindir="$fogprogramdir/snapins"
[[ -z ${DHCP_service_name} ]] && DHCP_service_name="isc-dhcp-server"
[[ -z $dhcpname ]] && dhcpname="isc-dhcp-server"
[[ -z $iscservice ]] && iscservice="isc-dhcp-server"
[[ -z $keapackage ]] && keapackage="kea-dhcp4-server"
[[ -z $keaservice ]] && keaservice="kea-dhcp4-server"
