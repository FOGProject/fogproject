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
#
#

# Ubuntu Config Settings

# apt-get packages to install
packages="apache2 php5 php5-json php5-gd php5-cli php5-mysql php5-curl mysql-server mysql-client isc-dhcp-server tftpd-hpa tftp-hpa nfs-kernel-server vsftpd net-tools wget xinetd  sysv-rc-conf tar gzip build-essential cpp gcc g++ m4 htmldoc lftp openssh-server php-gettext clamav-freshclam";
storageNodePackages="apache2 php5 php5-json php5-cli php5-mysql php5-curl mysql-client nfs-kernel-server vsftpd net-tools wget xinetd sysv-rc-conf tar gzip build-essential cpp gcc g++ m4 lftp php-gettext";
langPackages="language-pack-it language-pack-en language-pack-es language-pack-zh-hans";
dhcpname="isc-dhcp-server";
olddhcpname="dhcp3-server";

# where do the init scripts go?
initdpath="/etc/init.d";
initdsrc="../packages/init.d/ubuntu";
initdMCfullname="FOGMulticastManager";
initdIRfullname="FOGImageReplicator";
initdSDfullname="FOGScheduler";

# where do the php files go?
webdirdest="/var/www/fog";
apacheuser="www-data";

# where do we store the image files?
storage="/images";
storageupload="/images/dev";

# DHCP config file location
dhcpconfig="/etc/dhcp/dhcpd.conf";
olddhcpconfig="/etc/dhcp3/dhcpd.conf";

# where do the tftp files go?
tftpdirdst="/tftpboot"

# where is the tftpd config file?
tftpconfig="/etc/xinetd.d/tftp";
tftpconfigupstartconf="/etc/init/tftpd-hpa.conf"
tftpconfigupstartdefaults="/etc/default/tftpd-hpa"

# where is the ftp server config file?
ftpconfig="/etc/vsftpd.conf"

# where is the nfs exports file?
nfsconfig="/etc/exports";

# where do snapins go?
snapindir="/opt/fog/snapins";

#where is freshclam's config file
freshinit="/etc/init.d/clamav-freshclam";
freshdb="/var/lib/clamav/";
freshwebroot="${webdirdest}/av/";
