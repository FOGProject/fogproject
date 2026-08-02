#  FOG is a computer imaging solution.
#  Copyright (C) 2007  Chuck Syperski & Jian Zhang
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#   any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.
[[ -z $username || "x$username" == "xfog" ]] && username="fogproject"
[[ -z $webdirsrc ]] && webdirsrc="../packages/web"
[[ -z $tftpdirsrc ]] && tftpdirsrc="../packages/tftp"
# iPXE now lives in its own repository and its binaries arrive as a release
# asset, the same way the FOS kernels already do. packages/tftp is therefore a
# staging directory the installer fills at runtime rather than 22 MB of build
# output carried in git. $buildipxesrc is where the source checkout lands when
# an HTTPS install has to rebuild with its own CA baked in -- under
# $fogprogramdir so it is findable, survives the extracted tarball being
# deleted, and gives an offline site one path to pre-populate. See GH-959.
[[ -z $ipxegit ]] && ipxegit="https://github.com/FOGProject/fog-ipxe"
[[ -z $ipxeurl ]] && ipxeurl="${ipxegit}/releases/download"
# Pinned in system.class.php alongside FOG_CLIENT_VERSION, for the same reason:
# a given FOG release ships a known iPXE, and bumping it is a deliberate edit
# rather than whatever happened to be tagged the day someone installed.
[[ -z $ipxeVer ]] && ipxeVer="$(awk -F\' /"define\('FOG_IPXE_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php 2>/dev/null | tr -d '[[:space:]]')"
[[ -z $ipxeVer ]] && ipxeVer="v2.0.0-fog.1"
fog_udpversion="20250223"
[[ -z $udpcastsrc ]] && udpcastsrc="../packages/udpcast-${fog_udpversion}.tar.gz"
[[ -z $udpcastout ]] && udpcastout="udpcast-${fog_udpversion}"
[[ -z $servicesrc ]] && servicesrc="../packages/service"
# fogprogramdir is the single source of truth for the FOG base path and must be
# resolved BEFORE anything derived from it -- it used to be set two lines later,
# which is why servicedst/servicelogs carried their own "/opt/fog" literals and
# a non-default base dir silently split across two trees. See GH-850.
# Note: this cannot yet be overridden from .fogsettings, because .fogsettings
# itself lives at $fogprogramdir/.fogsettings; establishing it out-of-band is
# tracked as the follow-up to GH-850.
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
# Must follow fogprogramdir, not precede it -- see the note above.
[[ -z $buildipxesrc ]] && buildipxesrc="$fogprogramdir/ipxe"
[[ -z $servicedst ]] && servicedst="$fogprogramdir/service"
[[ -z $servicelogs ]] && servicelogs="$fogprogramdir/log"
# Secure Boot signing is on by default: _ensureSecureBootKeys generates a
# signing key when the admin has not supplied one, so a stock server always has
# a fingerprint and an enrolment kit to hand out. --no-secure-boot sets this to
# 0, and because .fogsettings is sourced before this file, that choice survives
# an upgrade rather than being silently re-enabled.
[[ -z $secureboot ]] && secureboot=1
[[ -z $nfsconfig ]] && nfsconfig="/etc/exports"
[[ -z $nfsservice ]] && nfsservice="nfs-server nfs-kernel-server nfs"
[[ -z $sqlclientlist ]] && sqlclientlist="mariadb-client mariadb MariaDB-client mysql"
[[ -z $sqlserverlist ]] && sqlserverlist="mariadb-galera-server mariadb-server MariaDB-Galera-server MariaDB-server mysql-server"
command -v systemctl >>$error_log 2>&1
exitcode=$?
grep systemd /proc/1/comm >>$error_log 2>&1
bootcode=$?
[[ $exitcode -eq 0 && $bootcode -eq 0 && -z $systemctl ]] && systemctl="yes"
if [[ $systemctl == yes ]]; then
    initdsrc="../packages/systemd"
    initdMCfullname="FOGMulticastManager.service"
    initdIRfullname="FOGImageReplicator.service"
    initdSDfullname="FOGScheduler.service"
    initdSRfullname="FOGSnapinReplicator.service"
    initdSHfullname="FOGSnapinHash.service"
    initdPHfullname="FOGPingHosts.service"
    initdISfullname="FOGImageSize.service"
    initdFDfullname="FOGFileDeleter.service"
    case $linuxReleaseName_lower in
        *ubuntu*|*bian*|*mint*)
            initdpath="/lib/systemd/system"
            ;;
        *)
            initdpath="/usr/lib/systemd/system"
            ;;
    esac
    # Alias mysql/mysqld onto whichever DB unit the distro actually ships, so the
    # $dbservice lookup in functions.sh finds a name whatever the packaging.
    #
    # The /etc/systemd/system sources previously read `$initdpath/mariadb` with no
    # .service suffix, which produced a *dangling* link. That is not cosmetic:
    # /etc/systemd/system takes precedence over $initdpath, so a broken link there
    # shadows the distro's working alias and systemd reports the unit as "bad" --
    # which is what the `grep -v bad` guard in functions.sh is working around.
    # linkIfAbsent() replaces such a link when it finds one, healing installs that
    # already ran an affected version. Refs forums topic 18204.
    if [[ -e $initdpath/mariadb.service ]]; then
        linkIfAbsent $initdpath/mariadb.service $initdpath/mysql.service
        linkIfAbsent $initdpath/mariadb.service $initdpath/mysqld.service
        linkIfAbsent $initdpath/mariadb.service /etc/systemd/system/mysql.service
        linkIfAbsent $initdpath/mariadb.service /etc/systemd/system/mysqld.service
    elif [[ -e $initdpath/mysqld.service ]]; then
        linkIfAbsent $initdpath/mysqld.service $initdpath/mysql.service
        linkIfAbsent $initdpath/mysqld.service /etc/systemd/system/mysql.service
    fi
else
    initdpath="/etc/init.d"
    initdMCfullname="FOGMulticastManager"
    initdIRfullname="FOGImageReplicator"
    initdSDfullname="FOGScheduler"
    initdSRfullname="FOGSnapinReplicator"
    initdSHfullname="FOGSnapinHash"
    initdPHfullname="FOGPingHosts"
    initdISfullname="FOGImageSize"
    initdFDfullname="FOGFileDeleter"
    case $linuxReleaseName_lower in
        *ubuntu*|*bian*|*mint*)
            initdsrc="../packages/init.d/ubuntu"
            ;;
        *alpine*)
            initdsrc="../packages/init.d/alpine"
            ;;
        *)
            initdsrc="../packages/init.d/redhat"
            ;;
    esac
fi
serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname $initdSHfullname $initdISfullname $initdFDfullname"
# GH-964 sibling: port windows the installer both configures a service to use
# and opens in the firewall. They live here, together, because the two have to
# agree -- a passive range pinned in vsftpd.conf but not opened, or opened but
# not pinned, fails in a way that looks like a network fault rather than a
# configuration one.
#
# FTP passive data. vsftpd otherwise picks from the ephemeral range, which
# cannot be firewalled without the nf_conntrack_ftp helper -- and modern
# kernels no longer auto-assign helpers. Pinning it is what makes FTP
# firewallable at all. Chosen above the default ephemeral range (32768-60999)
# so it cannot collide with an outbound socket. 101 ports is 101 concurrent
# transfers, well past what replication does.
[[ -z $ftppasvmin ]] && ftppasvmin=65000
[[ -z $ftppasvmax ]] && ftppasvmax=65100
# udpcast multicast. Mirrors UDPCAST_STARTINGPORT and FOG_MULTICAST_MAX_SESSIONS
# as written into config.class.php: each concurrent session consumes two ports
# from the base, so the window is base .. base + 2 * sessions.
[[ -z $mcastportmin ]] && mcastportmin=63100
[[ -z $mcastportmax ]] && mcastportmax=$((mcastportmin + 128))
