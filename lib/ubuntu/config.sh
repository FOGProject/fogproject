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
            packages="apache2 build-essential cpp curl g++ gawk gcc gcc-aarch64-linux-gnu genisoimage git gzip htmldoc isc-dhcp-server isolinux lftp libapache2-mod-fastcgi libapache2-mod-php libc6 libcurl3 liblzma-dev m4 ${db_packages} net-tools nfs-kernel-server openssh-server php-fpm php php-cli php-curl php-gd php-json php-ldap php-mbstring php-mysql php-mysqlnd tar tftpd-hpa tftp-hpa vsftpd wget zlib1g"
        else
            # make sure we update the package list to not use specific version numbers anymore
            packages=${packages//php[0-9]\.[0-9]/php}
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
    if [[ -z $docroot ]]; then
        docroot="/var/www/html/"
        webdirdest="${docroot}fog/"
    elif [[ "$docroot" != *'fog'* ]]; then
        webdirdest="${docroot}fog/"
    else
        webdirdest="${docroot}/"
    fi
    # GH-953: there used to be a fallback to /var/www/ here when /var/www/html
    # did not exist. This file is sourced long before installPackages, and
    # installing apache2/nginx is what creates /var/www/html -- so the test
    # answered differently on a fresh box than on one that already had a web
    # server, and identical machines installed to two different trees.
    # /var/www/html has been the Debian docroot since Debian 8; the directory
    # merely not existing yet says nothing about the platform, and
    # mkdir -p "$webdirdest" creates it later in configureHttpd anyway.
    # Existing installs keep their own path -- docroot is a managed key in
    # .fogsettings, so it never reaches this branch.
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
[[ -z $iscservice ]] && iscservice="isc-dhcp-server"
[[ -z $keapackage ]] && keapackage="kea-dhcp4-server"
[[ -z $keaservice ]] && keaservice="kea-dhcp4-server"
