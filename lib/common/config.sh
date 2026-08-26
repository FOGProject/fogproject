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
[[ -z ${SVC_user} || "x${SVC_user}" == "xfog" ]] && SVC_user="fogproject"
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
[[ -z $ipxeVer ]] && ipxeVer="v2.0.0-fog.6"
# The bundled plugins are a separate repository too (ADR 0009), pinned and
# fetched exactly like iPXE above. packages/web/lib/plugins is therefore a
# staging directory the installer fills, not a tree carried in git, so a plugin
# can be released without a FOG release and a FOG release still ships a known
# set. bin/fetch-plugins.sh does the work and is runnable on its own, which is
# what a developer wanting plugins in a fresh clone uses.
[[ -z $pluginsgit ]] && pluginsgit="https://github.com/FOGProject/fog-plugins"
[[ -z $pluginsurl ]] && pluginsurl="${pluginsgit}/releases/download"
[[ -z $pluginsVer ]] && pluginsVer="$(awk -F\' /"define\('FOG_PLUGINS_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php 2>/dev/null | tr -d '[[:space:]]')"
[[ -z $pluginsVer ]] && pluginsVer="v1.6.0"
# Bounds for every network fetch the installer makes, and the answer
# checkInternetConnection works out for the fetches that follow it. Overridable
# from .fogsettings for a link slow enough that fifteen seconds is genuinely too
# short, which is the only reason to raise them -- they exist so an unreachable
# host costs seconds instead of libcurl's 300 second default connect timeout.
# internet_ok starts optimistic so any path that reaches a download without
# having run the check behaves exactly as it did before.
[[ -z $inetConnectTimeout ]] && inetConnectTimeout=5
[[ -z $inetMaxTime ]] && inetMaxTime=15
[[ -z $internet_ok ]] && internet_ok=1
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
[[ -z ${PKI_sb_enabled} ]] && PKI_sb_enabled="yes"
# Anchoring this server's own CA in this server's own trust store is on by
# default: without it every HTTPS call made ON the FOG server TO the FOG server
# fails to verify, including the ones inside FOG that have no way to be handed
# a CA file. FOG always anchors its own CA here (GH-1120 retired --no-ca-trust:
# a server that cannot verify its own certificate is not a supported state, and
# the opt-out mostly produced installs that failed in confusing ways later).
[[ -z $nfsconfig ]] && nfsconfig="/etc/exports"
[[ -z $nfsservice ]] && nfsservice="nfs-server nfs-kernel-server nfs"
[[ -z $sqlclientlist ]] && sqlclientlist="mariadb-client mariadb MariaDB-client mysql"
# "mariadb" is last deliberately. It is the SERVER package name on Alpine,
# but on Fedora/RHEL it is the CLIENT -- which is why it also appears in
# $sqlclientlist above. Every other distro here carries one of the earlier,
# unambiguous *-server names, so pkgFirstAvailable settles on that one and
# never reaches this entry; Alpine, which has none of them, does. See #863.
[[ -z $sqlserverlist ]] && sqlserverlist="mariadb-galera-server mariadb-server MariaDB-Galera-server MariaDB-server mysql-server mariadb"
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
    initdPRfullname="FOGPluginRunner.service"
    initdRTfullname="FOGRetentionRunner.service"
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
    initdPRfullname="FOGPluginRunner"
    initdRTfullname="FOGRetentionRunner"
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
serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname $initdPHfullname $initdSHfullname $initdISfullname $initdFDfullname $initdPRfullname $initdRTfullname"
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
# fog_git_path is where THIS running copy of the installer/updater actually
# lives -- not a memory of a prior location. $workingdir is already this
# checkout's bin/ directory by the time config.sh is sourced (both
# installfog.sh and updatefog.sh cd there and set it before sourcing this
# file), so the checkout root is always workingdir's parent. Recomputed
# unconditionally (no `[[ -z ]]` guard) because, like fogprogramdir, it is a
# RECORD once persisted to .fogsettings, not a control -- see writeUpdateFile.
[[ -n $workingdir ]] && FOG_git_path="$(cd "$workingdir/.." && pwd)"
# fog_update_channel IS a genuine persisted preference (see writeUpdateFile),
# so this default only matters on a first install -- .fogsettings carries an
# admin's actual choice forward on every upgrade after that. Derived from
# whatever branch is already checked out; left unset if that is not one of
# the three known channel branches (e.g. a feature/PR branch, or no git repo
# at all for a tarball install) rather than guessing.
[[ -z ${FOG_update_channel} ]] && FOG_update_channel="$(branchToChannel "$(git -C "${FOG_git_path}" rev-parse --abbrev-ref HEAD 2>/dev/null)" 2>/dev/null)"
# Fold a value stored under the retired stable/staging/dev vocabulary to its
# canonical spelling (GH-1279), so writeUpdateFile persists the new one and the
# admin's file stops disagreeing with the docs. Only ever rewrites a value that
# normalizeChannel RECOGNISES -- anything else is left exactly as found, because
# an unknown value is either a typo the admin should see or a channel a newer
# installer knows about, and silently blanking either would be worse than
# leaving it to fail loudly in channelToBranch.
if [[ -n ${FOG_update_channel} ]]; then
    _canonicalChannel="$(normalizeChannel "${FOG_update_channel}" 2>/dev/null)" \
        && FOG_update_channel="${_canonicalChannel}"
    unset _canonicalChannel
fi
