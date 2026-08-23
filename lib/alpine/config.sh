#!/bin/bash
# lib/alpine/config.sh
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
# Alpine ships no unversioned "php" package, and the newest major moves with
# the release: 3.20 tops out at php83, 3.21 and 3.22 add php84, edge carries
# php85. This list used to name php7 packages, which Alpine has dropped
# entirely -- all 32 of them failed to resolve, so no Alpine install could get
# past package installation at all.
#
# Pinning a replacement would just reintroduce the same rot, so pick the newest
# php8x the configured repositories actually offer and build the names from it.
# $php_apk is deliberately NOT one of writeUpdateFile's managed keys: being
# recomputed on every run is what lets an existing install follow Alpine
# forward instead of freezing on whatever was current the day it was set up.
if [[ -z $php_apk ]]; then
    for _apkphp in 89 88 87 86 85 84 83 82 81 80; do
        if apk search -x "php${_apkphp}" 2>/dev/null | grep -q .; then
            php_apk="$_apkphp"
            break
        fi
    done
    unset _apkphp
    # Nothing resolved -- most likely no package index yet. php83 is present on
    # every currently supported Alpine, so it is the safest thing to guess.
    [[ -z $php_apk ]] && php_apk="83"
fi
if [[ -z ${FOG_packages} ]]; then
    # "mariadb-server" rather than Alpine's own name for it, which is plain
    # "mariadb". installPackages() maps a handful of database package names
    # onto $sqlclientlist / $sqlserverlist so that a MySQL host is not handed a
    # MariaDB client, and bare "mariadb" is matched by the CLIENT arm -- it is
    # the client package on Fedora. Alpine is the one distro that ships both
    # "mariadb" (server) and "mariadb-client", so the mapping resolved this
    # entry to mariadb-client and the SERVER was never installed at all. The
    # install then ran all the way to "Setting up and starting MySQL" before
    # dying on an empty datadir. Naming the server slot instead routes it
    # through $sqlserverlist, which now ends in "mariadb". See #863.
    FOG_packages="bash bc cdrkit curl gcc g++ git gzip lftp m4 make mariadb-server mariadb-client net-tools nfs-utils openrc openssh openssl perl perl-crypt-passwdmd5 shadow syslinux tar tftp-hpa vsftpd wget xmessage xz"
    # Only the extensions FOG actually uses. The old list also carried a pile
    # that either never applied here (odbc, pdo_odbc, pdo_pgsql, sqlite3,
    # pdo_sqlite, pdo_dblib, apcu, soap, gmp, bz2, zip) or cannot exist under
    # PHP 8 at all: mcrypt was removed in 7.2, xmlrpc moved to PECL in 8.0, and
    # json is now built into the core.
    #
    # shadow is in the base list above because busybox provides neither
    # groupadd nor usermod, and configureUsers calls both unconditionally.
    # ftp is in this list because FOGFTP's $data default is `FTP_BINARY`, a
    # constant defined by ext/ftp -- so without the extension every single page
    # dies at boot with "Undefined constant FOG\FTP_BINARY" before anything is
    # rendered. It is compiled into the php package on Debian, RHEL and Arch,
    # which is why nothing else here names it; Alpine splits it out. See #863.
    for _apkmod in fpm session openssl mbstring ctype iconv curl gd gettext \
        bcmath sockets pcntl posix dom simplexml xmlreader ldap mysqli pdo \
        pdo_mysql opcache phar fileinfo ftp; do
        FOG_packages="${FOG_packages} php${php_apk}-${_apkmod}"
    done
    unset _apkmod
    FOG_packages="php${php_apk} ${FOG_packages}"
    # Alpine keeps the OpenRC init scripts in separate -openrc subpackages.
    # Install the daemon alone and /etc/init.d/<name> simply does not exist, so
    # every rc-service call later in the install fails with nothing to start.
    # None of these were listed before. (php-fpm is the exception: php8x-fpm
    # ships its own init script, there is no php8x-fpm-openrc.)
    FOG_packages="${FOG_packages} tftp-hpa-openrc vsftpd-openrc mariadb-openrc nfs-utils-openrc"
    # ca-certificates, not ca-certificates-bundle. The minimal Alpine image
    # carries only the bundle, which is the trusted roots and nothing else --
    # no /usr/local/share/ca-certificates and no update-ca-certificates. FOG
    # publishes its own CA into the system trust store so that the server can
    # verify the certificate it just issued itself, and with no tool to do it
    # the install failed its own reachability check with "TLS verification
    # failed (curl 60)". See #863.
    FOG_packages="${FOG_packages} ca-certificates"
    # linux-headers, because the installer BUILDS udpcast from source and
    # socklib.c includes <linux/types.h>. glibc distributions ship the kernel
    # UAPI headers with libc-dev; musl does not, so gcc alone is not enough and
    # the build died with "fatal error: linux/types.h: No such file or
    # directory" -- taking multicast with it. See #863.
    FOG_packages="${FOG_packages} linux-headers"
    # Alpine dropped ISC dhcp-server after 3.20: on 3.21+ the `dhcp` package
    # still resolves but ships no files at all, and dhcp-openrc is gone. Kea is
    # the only DHCP server Alpine carries now and it is present as far back as
    # 3.20, so Alpine goes Kea-only rather than carrying a split that would be
    # dead on every current release. FOG already knows how to drive Kea (GH-730).
    FOG_packages="${FOG_packages} kea kea-dhcp4"
fi
[[ -z $packageinstaller ]] && packageinstaller="apk add"
[[ -z $packagelist ]] && packagelist="apk info"
# This was "apk update && apk upgrade". These are run as $packageupdater with
# arguments appended, and a && inside a variable is NOT re-parsed as a control
# operator after expansion -- apk received it as a literal argument and the
# whole step failed every time, silently, because the caller echoed "OK"
# regardless. The index refresh belongs in packmanUpdate, which is the step
# named for it and which previously ran a no-op "apk add" with no arguments.
[[ -z $packageupdater ]] && packageupdater="apk upgrade"
[[ -z $packmanUpdate ]] && packmanUpdate="apk update"
[[ -z $packageQuery ]] && packageQuery="apk info -e \$x "
# Bulk forms of packageQuery/packagelist -- see loadPackageSets.
pkgQueryAll() { apk info 2>/dev/null; }
pkgListAll() { apk search -q 2>/dev/null; }
[[ -z $langPackages ]] && langPackages="iso-codes"
# $dhcpname names the DHCP *package*, and it is what the engine selection in
# configureDhcpEngine keys on -- it bails out entirely unless ${FOG_packages}
# contains it. Alpine has no ISC option left to choose between (see the
# Kea-only note above), so the slot is filled by Kea and the selection settles
# on Kea without a decision to make. Naming the dhcpd *service* here, as this
# used to, meant the string never matched the package list and the engine
# switch was skipped, leaving Alpine pointed at an ISC daemon it cannot install.
[[ -z $dhcpname ]] && dhcpname="kea-dhcp4"
if [[ -z $webdirdest ]]; then
    if [[ -z ${WEB_docroot} ]]; then
        WEB_docroot="/var/www/"
        webdirdest="${WEB_docroot}fog/"
    elif [[ "${WEB_docroot}" != *'fog'* ]]; then
        webdirdest="${WEB_docroot}fog/"
    else
        webdirdest="${WEB_docroot}/"
    fi
fi
[[ -z $webredirect ]] && webredirect="${webdirdest}/index.php"
[[ -z $apacheuser ]] && apacheuser="nginx"
[[ -z $apachelogdir ]] && apachelogdir="/var/log/nginx"
[[ -z $apacheerrlog ]] && apacheerrlog="$apachelogdir/error.log"
[[ -z $apacheacclog ]] && apacheacclog="$apachelogdir/access.log"
[[ -z $httpdconf ]] && httpdconf="/etc/nginx/nginx.conf"
[[ -z $etcconf ]] && etcconf="/etc/nginx/http.d/default.conf"
[[ -z $phpini ]] && phpini="/etc/php${php_apk}/php.ini"
[[ -z ${STORAGE_image_share_path} ]] && STORAGE_image_share_path="/images"
[[ -z $storageLocationCapture ]] && storageLocationCapture="${STORAGE_image_share_path}/dev"
[[ -z $dhcpconfig ]] && dhcpconfig="/etc/dhcpd.conf"
[[ -z $dhcpconfigother ]] && dhcpconfigother="/etc/dhcp/dhcpd.conf"
[[ -z $tftpdirdst ]] && tftpdirdst="/var/tftpboot"
# Alpine drives tftp-hpa from OpenRC via /etc/conf.d/in.tftpd, not xinetd,
# which it does not even package. These pointed at Arch's xinetd layout.
[[ -z $tftpconfig ]] && tftpconfig="/etc/conf.d/in.tftpd"
[[ -z $ftpconfig ]] && ftpconfig="/etc/vsftpd.conf"
# OpenRC service names, as installed by the -openrc subpackages above. dhcpd4
# is Arch's name for the ISC daemon; Alpine calls it dhcpd.
[[ -z ${DHCP_service_name} ]] && DHCP_service_name="dhcpd"
[[ -z $iscservice ]] && iscservice="dhcpd"
# kea-dhcp4 is the package that actually carries /etc/init.d/kea-dhcp4 and
# /etc/kea/kea-dhcp4.conf; plain "kea" is the meta/library package and brings
# neither. See the Kea-only note by the package list above.
[[ -z $keapackage ]] && keapackage="kea-dhcp4"
[[ -z $keaservice ]] && keaservice="kea-dhcp4"
[[ -z ${DHCP_engine} ]] && DHCP_engine="kea"
[[ -z $snapindir ]] && snapindir="$fogprogramdir/snapins"
# Alpine's fpm service is php-fpm83, not php-fpm8.3: it takes the undotted
# package suffix. ${WEB_php_version} is left alone deliberately -- installPackages
# overwrites it with the dotted version reported by the php binary, which is
# what the Debian paths want, and the two must not be conflated.
[[ -z $phpfpm ]] && phpfpm="php-fpm${php_apk}"
[[ -z ${WEB_server_engine} ]] && WEB_server_engine="nginx"
FOG_packages="${FOG_packages} ${WEB_server_engine}"
