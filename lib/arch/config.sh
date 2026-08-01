#!/bin/bash
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
# mod_fastcgi, php-ssh2 and xinetd were all dropped from the Arch repositories
# and would fail the whole transaction. php-apache is deliberately not here
# either: it is mod_php, which forces the prefork MPM, and FOG drives PHP
# through php-fpm and mod_proxy_fcgi under event instead. That is also what
# makes PHP 8 a non-issue -- the mod_php recipe in GH-447 would have fought the
# configuration rather than fixing it.
#
# Arch splits almost nothing out of the php package: everything FOG needs
# except gd is already built in, just commented out in php.ini, which
# configureHttpd uncomments.
[[ -z $packages ]] && packages="attr bc cdrtools curl dhcp gcc git gzip lftp m4 make mariadb mariadb-clients net-tools nfs-utils openssh openssl perl perl-crypt-passwdmd5 php php-fpm php-gd syslinux tar tftp-hpa vsftpd wget xz"
# -Syu, not -Sy. Arch does not support partial upgrades: refreshing the package
# databases and then installing against a system that has not been upgraded
# produces binaries linked to a newer glibc than the one on disk. Installing
# php that way yields "libm.so.6: version GLIBC_2.44 not found" and a php that
# will not run at all -- which looks exactly like a broken FOG install.
[[ -z $packageinstaller ]] && packageinstaller="pacman -Syu --noconfirm"
[[ -z $packagelist ]] && packagelist="pacman -Si"
[[ -z $packageupdater ]] && packageupdater="pacman -Syu --noconfirm"
[[ -z $packmanUpdate ]] && packmanUpdate="$packageinstaller"
[[ -z $packageQuery ]] && packageQuery="pacman -Q \$x"
[[ -z $langPackages ]] && langPackages="iso-codes"
[[ -z $dhcpname ]] && dhcpname="dhcp"
if [[ -z $webdirdest ]]; then
    if [[ -z $docroot ]]; then
        docroot="/srv/http/"
        webdirdest="${docroot}fog/"
    elif [[ "$docroot" != *'fog'* ]]; then
        webdirdest="${docroot}fog/"
    else
        webdirdest="${docroot}/"
    fi
fi
# Arch is the one distro where the web server's package and its service have
# different names: the package is `apache`, the unit is httpd.service.
# Everywhere else the two coincide, which is why $webserver is used as both.
# It is the *service* name that matters here -- every systemctl call in
# configureHttpd is built from it -- so $webserver is httpd and the package is
# added by name at the end of this file instead of through $webserver.
[[ -z $webserver ]] && webserver="httpd"
[[ -z $webredirect ]] && webredirect="${webdirdest}/index.php"
# "apache" is accepted alongside "httpd" so a .fogsettings written before the
# service/package split above still resolves to Arch's real paths instead of
# falling through to the nginx guess below and inventing /etc/apache/.
if [[ $webserver == "httpd" || $webserver == "apache" ]]; then
    [[ -z $apacheuser ]] && apacheuser="http"
    [[ -z $apachelogdir ]] && apachelogdir="/var/log/httpd"
    [[ -z $httpdconf ]] && httpdconf="/etc/httpd/conf/httpd.conf"
    [[ -z $etcconf ]] && etcconf="/etc/httpd/conf/extra/fog.conf"
else
    # This is all just a guess, will most likely need a ton of refinement
    [[ -z $apacheuser ]] && apacheuser="nginx"
    [[ -z $apachelogdir ]] && apachelogdir="/var/log/$webserver"
    [[ -z $httpdconf ]] && httpdconf="/etc/$webserver/conf/httpd.conf"
    [[ -z $etcconf ]] && etcconf="/etc/$webserver/conf/extra/fog.conf"
fi
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/error_log"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/access_log"
[[ -z $phpini ]] && phpini="/etc/php/php.ini"
[[ -z $storageLocation ]] && storageLocation="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${storageLocation}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/srv/tftp"
# Arch runs tftp-hpa from tftpd.service, which sources /etc/conf.d/tftpd for
# $TFTPD_ARGS. FOG does not actually use either -- configureTFTPandPXE writes
# its own fog-tftp.service and reuses the distro's tftpd.socket -- but xinetd,
# which this used to name, is no longer packaged for Arch at all, so pointing
# at the file Arch really would use is at least honest.
[[ -z $tftpconfig ]] && tftpconfig="/etc/conf.d/tftpd"
[[ -z $ftpxinetd ]] && ftpxinetd="/etc/xinetd.d/vsftpd"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd.conf"
[[ -z $dhcpd ]] && dhcpd="dhcpd4"
[[ -z $iscservice ]] && iscservice="dhcpd4"
[[ -z $keapackage ]] && keapackage="kea"
[[ -z $keaservice ]] && keaservice="kea-dhcp4"
[[ -z $snapindir ]] && snapindir="$fogprogramdir/snapins"
# Arch's fpm unit is plain php-fpm.service. Left unset, installPackages would
# derive "php8.4-fpm" from the dotted version the php binary reports, which is
# the Debian naming and matches nothing here.
[[ -z $phpfpm ]] && phpfpm="php-fpm"
# Not ${webserver} -- see the note by the webserver assignment above.
packages="${packages} apache"
