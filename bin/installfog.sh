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
bindir=$(dirname $(readlink -f "$BASH_SOURCE"))
cd $bindir
workingdir=$(pwd)

if [[ ! $EUID -eq 0 ]]; then
    echo "FOG Installation must be run as root user"
    exit 1 # Fail Sudo
fi

which adduser >/dev/null 2>&1
if [[ $? -eq 1 || $(echo $PATH | grep -o "sbin" | wc -l) -lt 2 ]]; then
    echo "Please switch to a proper root environment to run the installer!"
    echo "Use 'sudo -i' or 'su -' (skip the ' and note the hyphen at the end"
    echo "of the su command as it is important to load root's environment)."
    exit 1
fi

[[ -z $OS ]] && OS=$(uname -s)
if [[ ! $(echo "$OS" | tr [:upper:] [:lower:]) =~ "linux" ]]; then
    echo "We do not currently support Installation on non-Linux Operating Systems"
    exit 2 # Fail OS Check
fi

[[ -z $version ]] && version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"
[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
error_log=${workingdir}/error_logs/fog_error_${version}.log
timestamp=$(date +%s)
backupconfig=""
. ../lib/common/functions.sh
. ../lib/common/uninstall.sh
usage() {
    echo -e "Usage: $0 [-h?odEUHSCKYyXTFl] [-f <filename>] [-N <databasename>]"
    echo -e "\t\t[-D </directory/to/document/root/>] [-c <ssl-path>]"
    echo -e "\t\t[-W <webroot/to/fog/after/docroot/>] [-B </backup/path/>]"
    echo -e "\t\t[-s <192.168.1.10>] [-e <192.168.1.254>]"
    echo -e "\t-h -? --help\t\t\tDisplay this info"
    echo -e "\t-o    --oldcopy\t\t\tCopy back old data"
    echo -e "\t-d    --no-defaults\t\tDon't guess defaults"
    echo -e "\t-U    --no-upgrade\t\tDon't attempt to upgrade"
    echo -e "\t-H    --no-htmldoc\t\tNo htmldoc, means no PDFs"
    echo -e "\t-S    --force-https\t\tForce HTTPS for all comunication"
    echo -e "\t-C    --recreate-CA\t\tRecreate the CA Keys"
    echo -e "\t-K    --recreate-keys\t\tRecreate the SSL Keys"
    echo -e "\t      --external-ca\t\tSign FOG's server certificate with an"
    echo -e "\t                  \t\t\texisting external/intermediate CA instead"
    echo -e "\t                  \t\t\tof generating a self-signed CA"
    echo -e "\t      --ca-cert\t\t\tPath to the intermediate CA certificate (PEM)"
    echo -e "\t      --ca-key\t\t\tPath to the intermediate CA private key (PEM)"
    echo -e "\t      --ca-root\t\t\tPath to the root CA certificate (PEM)"
    echo -e "\t-Y -y --autoaccept\t\tAuto accept defaults and install"
    echo -e "\t-f    --file\t\t\tUse different update file"
    echo -e "\t-c    --ssl-path\t\tSpecify the ssl path"
    echo -e "\t               \t\t\t\tdefaults to /opt/fog/snapins/ssl"
    echo -e "\t-D    --docroot\t\t\tSpecify the Apache Docroot for fog"
    echo -e "\t               \t\t\t\tdefaults to OS DocumentRoot"
    echo -e "\t-W    --webroot\t\t\tSpecify the web root url want fog to use"
    echo -e "\t            \t\t\t\t(E.G. http://127.0.0.1/fog,"
    echo -e "\t            \t\t\t\t      http://127.0.0.1/)"
    echo -e "\t            \t\t\t\tDefaults to /fog/"
    echo -e "\t      --fogprogramdir\t\tSpecify the FOG base directory"
    echo -e "\t               \t\t\t\tdefaults to /opt/fog"
    echo -e "\t               \t\t\t\tremembered in /etc/fog/fog.conf, so it"
    echo -e "\t               \t\t\t\tonly needs giving on a first install"
    echo -e "\t-N    --mysqldbname\t\tSpecify the FOG database name"
    echo -e "\t               \t\t\t\tdefaults to fog"
    echo -e "\t-B    --backuppath\t\tSpecify the backup path"
    echo -e "\t      --uninstall\t\tUninstall FOG. Removes FOG's own files,"
    echo -e "\t               \t\t\t\tservices and config, and restores the"
    echo -e "\t               \t\t\t\tfiles FOG replaced. Your database,"
    echo -e "\t               \t\t\t\timages, snapins, SSL CA and the fog"
    echo -e "\t               \t\t\t\taccount are KEPT unless purged below."
    echo -e "\t               \t\t\t\tPackages are never removed."
    echo -e "\t      --dry-run\t\t\tWith --uninstall, list what would be"
    echo -e "\t               \t\t\t\tremoved and exit without changing anything"
    echo -e "\t      --force\t\t\tWith --uninstall, skip the typed"
    echo -e "\t               \t\t\t\tconfirmation (-Y does NOT skip it)"
    echo -e "\t      --purge-db\t\tAlso drop the FOG database"
    echo -e "\t      --purge-images\t\tAlso delete the image storage"
    echo -e "\t      --purge-snapins\t\tAlso delete the snapins"
    echo -e "\t      --purge-ssl\t\tAlso delete the SSL CA. This permanently"
    echo -e "\t               \t\t\t\tbreaks every deployed fog-client"
    echo -e "\t      --purge-user\t\tAlso delete the fog Linux account"
    echo -e "\t      --purge-all\t\tAll of the --purge-* options above"
    echo -e "\t-s    --startrange\t\tDHCP Start range"
    echo -e "\t-e    --endrange\t\tDHCP End range"
    echo -e "\t-E    --no-exportbuild\t\tSkip building nfs file"
    echo -e "\t-X    --exitFail\t\tDo not exit if item fails"
    echo -e "\t-T    --no-tftpbuild\t\tDo not rebuild the tftpd config file"
    echo -e "\t-F    --no-vhost\t\tDo not overwrite vhost file"
    echo -e "\t-l    --list-packages\t\tList of the basic packages FOG needs for install or is currently installed for FOG"
    exit 0
}

shortopts="h?odEUHSCKYyXTFf:c:W:D:B:s:e:N:l"
longopts="help,uninstall,purge-db,purge-images,purge-snapins,purge-ssl,purge-user,purge-all,dry-run,force,mysqldbname:,ssl-path:,oldcopy,no-vhost,no-defaults,no-upgrade,no-htmldoc,force-https,recreate-keys,recreate-CA,recreate-Ca,recreate-cA,recreate-ca,external-ca,ca-cert:,ca-key:,ca-root:,autoaccept,file:,docroot:,webroot:,backuppath:,startrange:,endrange:,no-exportbuild,exitFail,no-tftpbuild,list-packages,fogprogramdir:"

optargs=$(getopt -o $shortopts -l $longopts -n "$0" -- "$@")
[[ $? -ne 0 ]] && usage
eval set -- "$optargs"

while :; do
    case $1 in
		-h | -\? | --help)
			usage
			exit 0
			;;
		--uninstall)
			# Only flags the intent. The uninstall itself runs further down,
			# once .fogsettings and the distro config have been read -- it
			# cannot know what to remove before then.
			douninstall=1
			shift
			;;
		--purge-db|--purge-images|--purge-snapins|--purge-ssl|--purge-user)
			printf -v "purge${1#--purge-}" 1
			shift
			;;
		--purge-all)
			purgedb=1; purgeimages=1; purgesnapins=1; purgessl=1; purgeuser=1
			shift
			;;
		--dry-run)
			uninstalldryrun=1
			shift
			;;
		--force)
			uninstallforce=1
			shift
			;;
        -c | --ssl-path)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                ssslpath="${2}"
                ssslpath="${ssslpath#'/'}"
                ssslpath="${ssslpath%'/'}"
                ssslpath="/${ssslpath}/"
            else
                echo "Error: Missing argument for --$1"
                usage
                exit 9
            fi
            shift 2
            ;;
        --fogprogramdir)
            # GH-850: the FOG base directory. Needed on a FIRST install to a
            # non-default path; afterwards /etc/fog/fog.conf remembers it, so
            # upgrades do not have to repeat the flag.
            if [[ -n "${2}" ]] && [[ "${2}" == /* ]]; then
                sfogprogramdir="${2%/}"
            else
                echo "Error: --fogprogramdir requires an absolute path"
                usage
                exit 9
            fi
            shift 2
            ;;
        -o | --oldcopy)
            scopybackold=1
            shift
			;;
        -d | --no-defaults)
            guessdefaults=0
            shift
            ;;
        -U | --no-upgrade)
            doupdate=0
            shift
            ;;
        -H | --no-htmldoc)
            signorehtmldoc=1
            shift
            ;;
        -S | --force-https)
            shttpproto="https"
            shift
            ;;
        -K | --recreate-keys)
            srecreateKeys="yes"
            shift
            ;;
        -C | --recreate-[Cc][Aa])
            srecreateCA="yes"
            shift
            ;;
        --external-ca)
            sexternalca="yes"
            shift
            ;;
        --ca-cert)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sextcacert="${2}"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
            fi
            shift 2
            ;;
        --ca-key)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sextcakey="${2}"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
            fi
            shift 2
            ;;
        --ca-root)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sextcaroot="${2}"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
            fi
            shift 2
            ;;
        -y | -Y | --autoaccept)
            autoaccept="yes"
            dbupdate="yes"
            shift
            ;;
        -f | --file)
            if [[ -f $2 ]]; then
                fogpriorconfig=$2
            else
                echo "$1 requires file after"
                usage
                exit 3
            fi
            shift 2
            ;;
        -D | --docroot)
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                sdocroot="${2}"
                sdocroot="${sdocroot#'/'}"
                sdocroot="${sdocroot%'/'}"
                sdocroot="/${sdocroot}/"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
			fi
            shift 2
            ;;
        -W | --webroot)
            if [[ $2 != */* ]]; then
                echo -e "$1 needs a url path for access either / or /fog.\n\t\tFor example if you access fog using http://127.0.0.1/ without\n\t\tany trail, set the path to /"
                usage
                exit 2
            fi
            swebroot="${2}"
            swebroot="${swebroot#'/'}"
            swebroot="${swebroot%'/'}"
            # Store the FINAL "/x/" form here rather than the bare "x". Two
            # separate bugs came out of not doing so, both because the
            # normalisation further down only runs on the upgrade path (it is
            # gated on grepping an existing .fogsettings):
            #
            #   -W /      stripped to "", and the application tested `-n
            #             $swebroot`, so the one case the help text exists to
            #             document was discarded and fell back to /fog/.
            #   -W /fog   on a FRESH install left webroot as "fog" with no
            #             slashes, producing URLs like http://1.2.3.4fogmanagement.
            #
            # swebrootset records that the flag was given, separately from its
            # value, so an empty value still counts.
            swebrootset=1
            [[ -z $swebroot ]] && swebroot="/" || swebroot="/${swebroot}/"
            shift 2
            ;;
        -N | --mysqldbname)
            # -N has been declared in $shortopts, and documented in the usage
            # synopsis as [-N <databasename>], since 0c9064e52 -- but never had
            # a handler, so it hung the option loop. The application half
            # (smysqldbname -> mysqldbname) was already in place below.
            if [[ -n "${2}" ]] && [[ "${2}" != -* ]]; then
                smysqldbname="${2}"
            else
                echo "Error: Missing argument for $1"
                usage
                exit 9
            fi
            shift 2
            ;;
        -B | --backuppath)
            if [[ ! -d $2 ]]; then
                echo "Path must be an existing directory"
                usage
                exit 4
            fi
            sbackupPath=$2
            shift 2
            ;;
        -s | --startrange)
            if [[ $(validip $2) != 0 ]]; then
                echo "Invalid ip passed"
                usage
                exit 5
            fi
            sstartrange=$2
            sdodhcp="Y"
            sbldhcp=1
            shift 2
            ;;
        -e | --endrange)
            if [[ $(validip $2) != 0 ]]; then
                echo "Invalid ip passed"
                usage
                exit 6
            fi
            sendrange=$2
            sdodhcp="Y"
            sbldhcp=1
            shift 2
            ;;
        -E | --no-exportbuild)
            sblexports=0
            shift
            ;;
        -X | --exitFail)
            sexitFail=1
            shift
            ;;
        -T | --no-tftpbuild)
            snoTftpBuild="true"
            shift
            ;;
        -F | --no-vhost)
            novhost="y"
            shift
            ;;
        -l | --list-packages)
            listPackages=1
            shift
            ;;
        --)
            shift
            break
            ;;
        *)
            # Nothing below shifts $1, so an option that getopt accepts but no
            # branch above handles used to spin here forever at 100% CPU rather
            # than failing. That was reachable for every letter left in
            # $shortopts after its handler was removed. Fail loudly instead.
            echo "Error: unhandled option '$1'. This is an installer bug --"
            echo "please report it at https://github.com/FOGProject/fogproject/issues"
            exit 10
            ;;
    esac
done


if [[ -f /etc/os-release ]]; then
    [[ -z $linuxReleaseName ]] && linuxReleaseName=$(sed -n 's/^NAME=\(.*\)/\1/p' /etc/os-release | tr -d '"')
    [[ -z $OSVersion ]] && OSVersion=$(sed -n 's/^VERSION_ID=\([^.]*\).*/\1/p' /etc/os-release | tr -d '"')
elif [[ -f /etc/redhat-release ]]; then
    [[ -z $linuxReleaseName ]] && linuxReleaseName=$(cat /etc/redhat-release | awk '{print $1}')
    [[ -z $OSVersion ]] && OSVersion=$(cat /etc/redhat-release | sed s/.*release\ // | sed s/\ .*// | awk -F. '{print $1}')
elif [[ -f /etc/debian_version ]]; then
    [[ -z $linuxReleaseName ]] && linuxReleaseName='Debian'
    [[ -z $OSVersion ]] && OSVersion=$(cat /etc/debian_version)
fi

linuxReleaseName_lower=$(echo "$linuxReleaseName" | tr [:upper:] [:lower:])
listPackages

echo "Installing LSB_Release as needed"
dots "Attempting to get release information"
command -v lsb_release >$error_log 2>&1
exitcode=$?
if [[ ! $exitcode -eq 0 ]]; then
    case $linuxReleaseName_lower in
        *bian*|*ubuntu*|*mint*)
            apt-get -yq install lsb-release >>$error_log 2>&1
            ;;
        *centos*|*red*hat*|*fedora*|*alma*|*rocky*)
            command -v dnf >>$error_log 2>&1
            exitcode=$?
            case $exitcode in
                0)
                    dnf -y install redhat-lsb-core >>$error_log 2>&1
                    exitcode=$?
                    ;;
                *)
                    yum -y install redhat-lsb-core >>$error_log 2>&1
                    exitcode=$?
                    ;;
            esac
            if [[ ! $exitcode -eq 0 ]]; then
                command -v dnf >>$error_log 2>&1
                exitcode=$?
                case $exitcode in
                    0)
                        dnf -y install lsb-release >>$error_log 2>&1
                        exitcode=$?
                        ;;
                    *)
                        yum -y install lsb-release >>$error_log 2>&1
                        exitcode=$?
                        ;;
                esac
            fi
            ;;

        *[Aa][Ll][Pp][Ii][Nn][Ee]*)
            ;;
    esac
fi
[[ -z $OSVersion ]] && OSVersion=$(lsb_release -rs| awk -F'.' '{print $1}')
[[ -z $OSMinorVersion ]] && OSMinorVersion=$(lsb_release -rs| awk -F'.' '{print $2}')
echo "Done"
# GH-850: establish the FOG base directory BEFORE lib/common/config.sh, because
# servicedst/servicelogs/snapindir and the .fogsettings location all derive from
# it. Precedence, highest first:
#
#   1. --fogprogramdir           explicit, this run
#   2. an exported $fogprogramdir  scripted installs
#   3. /etc/fog/fog.conf         what the last install recorded
#   4. /opt/fog                  the default, applied by config.sh
#
# .fogsettings is deliberately NOT in that list: it lives at
# $fogprogramdir/.fogsettings, so it cannot be what tells us where to look.
# That is why the pointer file exists.
[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -n $sfogprogramdir ]] && fogprogramdir="$sfogprogramdir"
fogprogramdir="${fogprogramdir%/}"
. ../lib/common/config.sh
# Captured after config.sh so the /opt/fog default is included, and re-asserted
# once .fogsettings has been sourced below. A stale fogprogramdir line in that
# file must not silently relocate the install half-way through a run, after
# config.sh has already derived servicedst/servicelogs from it.
resolvedfogprogramdir="$fogprogramdir"
[[ -z $dnsaddress ]] && dnsaddress=""
[[ -z $username ]] && username=""
[[ -z $password ]] && password=""
[[ -z $osid ]] && osid=""
[[ -z $osname ]] && osname=""
[[ -z $dodhcp ]] && dodhcp=""
[[ -z $bldhcp ]] && bldhcp=""
[[ -z $installtype ]] && installtype=""
[[ -z $interface ]] && interface=""
[[ -z $ipaddress ]] && ipaddress=""
[[ -z $hostname ]] && hostname=""
[[ -z $routeraddress ]] && routeraddress=""
[[ -z $plainrouter ]] && plainrouter=""
[[ -z $blexports ]] && blexports=1
[[ -z $installlang ]] && installlang=0
[[ -z $bluseralreadyexists ]] && bluseralreadyexists=0
[[ -z $guessdefaults ]] && guessdefaults=1
[[ -z $doupdate ]] && doupdate=1
[[ -z $ignorehtmldoc ]] && ignorehtmldoc=0
[[ -z $httpproto ]] && httpproto="http"
[[ -z $externalca ]] && externalca="no"
[[ -z $mysqldbname ]] && mysqldbname="fog"
[[ -z $tftpAdvOpts ]] && tftpAdvOpts=""
[[ -z $fogpriorconfig ]] && fogpriorconfig="$fogprogramdir/.fogsettings"
#clearScreen
if [[ -z $* || $* != +(-h|-?|--help|--uninstall) ]]; then
    echo > "$workingdir/error_logs/foginstall.log"
    exec &> >(tee -a "$workingdir/error_logs/foginstall.log")
fi
displayBanner
echo -e "   Version: $version Installer/Updater\n"
checkSELinux
checkFirewall
case $doupdate in
    1)
        if [[ -f $fogpriorconfig ]]; then
            echo -e "\n * Found FOG Settings from previous install at: $fogprogramdir/.fogsettings\n"
            echo -n " * Performing upgrade using these settings"
            . "$fogpriorconfig"
            # GH-850: .fogsettings records fogprogramdir but does not control
            # it (see writeFogSettings). Re-assert before doOSSpecificIncludes,
            # which derives snapindir from it.
            fogprogramdir="$resolvedfogprogramdir"
            doOSSpecificIncludes
            # This was `blexports=$blexports` -- a self-assignment that did
            # nothing, so -E was silently discarded on upgrades: the handler
            # wrote blexports directly and the .fogsettings sourced just above
            # overwrote it (blexports is a managed key). -E/-s/-e now use the
            # s-prefixed shadows every other flag uses, which is what
            # 0d49b78e1 introduced the convention for.
            [[ -n $sblexports ]] && blexports=$sblexports
            [[ -n $snoTftpBuild ]] && noTftpBuild=$snoTftpBuild
            [[ -n $sbackupPath ]] && backupPath=$sbackupPath
            [[ -n $swebrootset ]] && webroot=$swebroot
            [[ -n $sdocroot ]] && docroot=$sdocroot
            [[ -n $signorehtmldoc ]] && ignorehtmldoc=$signorehtmldoc
            [[ -n $scopybackold ]] && copybackold=$scopybackold
        fi
        ;;
    *)
        echo -e "\n * FOG Installer will NOT attempt to upgrade from\n    previous version of FOG."
        ;;
esac
# evaluation of command line options
[[ -n $shttpproto ]] && httpproto=$shttpproto
[[ -n $sstartrange ]] && startrange=$sstartrange
[[ -n $sendrange ]] && endrange=$sendrange
# -s/-e imply "set DHCP up". These were written directly by the handlers, so on
# an upgrade the .fogsettings sourced above overwrote them (both are managed
# keys) and the ranges were accepted while DHCP configuration stayed off.
[[ -n $sdodhcp ]] && dodhcp=$sdodhcp
[[ -n $sbldhcp ]] && bldhcp=$sbldhcp
[[ -n $ssslpath ]] && sslpath=$ssslpath
[[ -n $srecreateCA ]] && recreateCA=$srecreateCA
[[ -n $srecreateKeys ]] && recreateKeys=$srecreateKeys
[[ -n $sexternalca ]] && externalca=$sexternalca
[[ -n $sextcacert ]] && extcacert=$sextcacert
[[ -n $sextcakey ]] && extcakey=$sextcakey
[[ -n $sextcaroot ]] && extcaroot=$sextcaroot
[[ -n $sdocroot ]] && docroot=$sdocroot
[[ -n $swebrootset ]] && webroot=$swebroot
[[ -n $sbackupPath ]] && backupPath=$sbackupPath
[[ -n $sexitFail ]] && exitFail=$sexitFail
[[ -n $snoTftpBuild ]] && noTftpBuild=$snoTftpBuild

[[ -f $fogpriorconfig ]] && grep -l webroot $fogpriorconfig >>$error_log 2>&1
case $? in
    0)
        if [[ -n $webroot ]]; then
            webroot=${webroot#'/'}
            webroot=${webroot%'/'}
            [[ -z $webroot ]] && webroot="/" || webroot="/${webroot}/"
        fi
        ;;
    *)
        [[ -z $webroot ]] && webroot="/fog/"
        ;;
esac
if [[ -z $backupPath ]]; then
    backupPath="/home/"
    backupPath="${backupPath%'/'}"
    backupPath="${backupPath#'/'}"
    backupPath="/$backupPath/"
fi
[[ -n $smysqldbname ]] && mysqldbname=$smysqldbname
# --uninstall runs here: late enough that .fogsettings and the distro config
# have been read (both are needed to know what to remove -- docroot, storage
# location, service list, config paths), but before input.sh, which would
# otherwise start prompting about an install nobody asked for.
if [[ $douninstall -eq 1 ]]; then
    # Normally both are already loaded by the upgrade path above. They are not
    # if -U/--no-upgrade was also given, so load them here rather than
    # uninstalling with half the paths unset.
    if [[ -z $osid ]]; then
        [[ -f $fogprogramdir/.fogsettings ]] && . "$fogprogramdir/.fogsettings"
        [[ -n $osid ]] && doOSSpecificIncludes >/dev/null
    fi
    uninstallFOG
fi
[[ ! $doupdate -eq 1 || ! $fogupdateloaded -eq 1 ]] && . ../lib/common/input.sh
# ask user input for newly added options like hostname etc.
. ../lib/common/newinput.sh
# GH-954: after BOTH paths that can set $ipaddress -- fresh detection in
# input.sh, and .fogsettings sourced earlier on an upgrade. An install written
# by an older installer has the multi-line value persisted, so normalizing only
# at detection would leave every upgrade carrying the broken form forward.
normalizeIpAddress
echo
echo "   ######################################################################"
echo "   #     FOG now has everything it needs for this setup, but please     #"
echo "   #   understand that this script will overwrite any setting you may   #"
echo "   #   have setup for services like DHCP, apache, pxe, tftp, and NFS.   #"
echo "   ######################################################################"
echo "   # It is not recommended that you install this on a production system #"
echo "   #        as this script modifies many of your system settings.       #"
echo "   ######################################################################"
echo "   #             This script should be run by the root user.            #"
echo "   #      It will prepend the running with sudo if root is not set      #"
echo "   ######################################################################"
echo "   #            Please see our wiki for more information at:            #"
echo "   ######################################################################"
echo "   #             https://wiki.fogproject.org/wiki/index.php             #"
echo "   ######################################################################"
echo
echo " * Here are the settings FOG will use:"
echo " * Base Linux: $osname"
echo " * Detected Linux Distribution: $linuxReleaseName"
echo " * Interface: $interface"
echo " * Server IP Address: $ipaddress"
echo " * Server Subnet Mask: $submask"
echo " * Hostname: $hostname"
echo
case $installtype in
    N)
        echo " * Installation Type: Normal Server"
        echo -n " * Internationalization: "
        case $installlang in
            1)
                echo "Yes"
                ;;
            *)
                echo "No"
                ;;
        esac
        echo " * Image Storage Location: $storageLocation"
        case $bldhcp in
            1)
                echo " * Using FOG DHCP: Yes"
                echo " * DHCP router Address: $plainrouter"
                ;;
            *)
                echo " * Using FOG DHCP: No"
                echo " * DHCP will NOT be setup but you must setup your"
                echo " | current DHCP server to use FOG for PXE services."
                echo
                echo " * On a Linux DHCP server you must set: next-server and filename"
                echo
                echo " * On a Windows DHCP server you must set options 066 and 067"
                echo
                echo " * Option 066/next-server is the IP of the FOG Server: (e.g. $ipaddress)"
                echo " * Option 067/filename is the bootfile: (e.g. undionly.kkpxe or snponly.efi)"
                ;;
        esac
        ;;
    S)
        echo " * Installation Type: Storage Node"
        echo " * Node IP Address: $ipaddress"
        echo " * MySQL Database Host: $snmysqlhost"
        echo " * MySQL Database User: $snmysqluser"
        ;;
esac
echo -n " * Send OS Name, OS Version, and FOG Version: "
case $sendreports in
    Y)
        echo "Yes"
        ;;
    *)
        echo "No"
        ;;
esac
echo
while [[ -z $blGo ]]; do
    echo
    [[ -n $autoaccept ]] && blGo="y"
    if [[ -z $autoaccept ]]; then
        echo -n " * Are you sure you wish to continue (Y/N) "
        read blGo
    fi
    echo
    case $blGo in
        [Yy]|[Yy][Ee][Ss])
            echo " * Installation Started"
            echo
            checkInternetConnection
            if [[ $ignorehtmldoc -eq 1 ]]; then
                [[ -z $newpackagelist ]] && newpackagelist=""
                for z in $packages; do
                    [[ $z != htmldoc ]] && newpackagelist="$newpackagelist $z"
                done
                packages="$(echo $newpackagelist)"
            fi
            if [[ $bldhcp == 0 ]]; then
                [[ -z $newpackagelist ]] && newpackagelist=""
                for z in $packages; do
                    [[ $z != $dhcpname ]] && newpackagelist="$newpackagelist $z"
                done
                packages="$(echo $newpackagelist)"
            fi
            case $installtype in
                [Ss])
                    packages=$(echo $packages | sed -e 's/[-a-zA-Z]*dhcp[-a-zA-Z]*//g')
                    ;;
            esac
            installPackages
            echo
            echo " * Confirming package installation"
            echo
            confirmPackageInstallation
            echo
            echo " * Configuring services"
            echo
            if [[ -z $storageLocation ]]; then
                case $autoaccept in
                    [Yy]|[Yy][Ee][Ss])
                        storageLocation="/images"
                        ;;
                    *)
                        echo
                        echo -n " * What is the storage location for your images directory? (/images) "
                        read storageLocation
                        [[ -z $storageLocation ]] && storageLocation="/images"
                        while [[ ! -d $storageLocation && $storageLocation != "/images" ]]; do
                            echo -n " * Please enter a valid directory for your storage location (/images) "
                            read storageLocation
                            [[ -z $storageLocation ]] && storageLocation="/images"
                        done
                        ;;
                esac
            fi
            configureUsers
            case $installtype in
                [Ss])
                    checkDatabaseConnection
                    backupReports
                    configureMinHttpd
                    configureStorage
                    configureDHCP
                    configureTFTPandPXE
                    configureFTP
                    configureSnapins
                    configureUDPCast
                    installInitScript
                    installFOGServices
                    configureFOGService
                    configureNFS
                    writeUpdateFile
                    linkOptFogDir
                    installUtilities
                    if [[ $bluseralreadyexists == 1 ]]; then
                        echo
                        echo "\n * Upgrade complete\n"
                        echo
                    else
                        registerStorageNode
                        updateStorageNodeCredentials
                        [[ -n $snmysqlhost ]] && fogserver=$snmysqlhost || fogserver="fog-server"
                        echo
                        echo " * Setup complete"
                        echo
                        echo
                        echo " * You still need to setup this node in the fog management "
                        echo " | portal. You will need the username and password listed"
                        echo " | below."
                        echo
                        echo " * Management Server URL:"
                        echo "   ${httpproto}://${fogserver}${webroot}"
                        echo
                        echo "   You will need this, write this down!"
                        echo "   IP Address:          $ipaddress"
                        echo "   Interface:           $interface"
                        echo "   Management Username: $username"
                        echo "   Management Password: $password"
                        echo
                    fi
                    ;;
                [Nn])
                    configureMySql
                    backupReports
                    configureHttpd
                    checkWebTier
                    backupDB
                    updateDB
                    configureStorage
                    configureDHCP
                    configureTFTPandPXE
                    configureFTP
                    configureSnapins
                    configureUDPCast
                    installInitScript
                    installFOGServices
                    configureFOGService
                    configureNFS
                    writeUpdateFile
                    linkOptFogDir
                    installUtilities
                    updateStorageNodeCredentials
                    setupFogReporting
                    echo
                    echo " * Setup complete"
                    echo
                    echo "   You can now login to the FOG Management Portal using"
                    echo "   the information listed below.  The login information"
                    echo "   is only if this is the first install."
                    echo
                    echo "   This can be done by opening a web browser and going to:"
                    echo
                    echo "   ${httpproto}://${ipaddress}${webroot}management"
                    echo
                    echo "   Default User Information"
                    echo "   Username: fog"
                    echo "   Password: password"
                    echo
                    ;;
            esac
            [[ -d $webdirdest/maintenance ]] && rm -rf $webdirdest/maintenance
            ;;
        [Nn]|[Nn][Oo])
            echo " * FOG installer exited by user request"
            exit 0
            ;;
        *)
            echo
            echo " * Sorry, answer not recognized"
            echo
            exit 1
            ;;
    esac
done
if [[ -n "${backupconfig}" ]]; then
    echo " * Changed configurations:"
    echo
    echo "   The FOG installer changed configuration files and created the"
    echo "   following backup files from your original files:"
    for conffile in ${backupconfig}; do
        echo "   * ${conffile} <=> ${conffile}.${timestamp}"
    done
    echo
fi
