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
workingdir=$(pwd)
if [[ ! $EUID -eq 0 ]]; then
    exec sudo $0 $@ || echo "FOG Installation must be run as root user"
    exit 1 # Fail Sudo
fi
. ../lib/common/functions.sh
help() {
    echo -e "Usage: $0 [-h?dEUuHSCKYXT] [-f <filename>]"
    echo -e "\t\t[-D </directory/to/document/root/>] [-c <sslPath>]"
    echo -e "\t\t[-W <webroot/to/fog/after/docroot/>] [-B </backup/path/>]"
    echo -e "\t\t[-s <192.168.1.10>] [-e <192.168.1.254>] [-b <undionly.kpxe>]"
    echo -e "\t-h -? --help\t\t\tDisplay this info"
    echo -e "\t-o    --oldcopy\t\t\tCopy back old data"
    echo -e "\t-d    --no-defaults\t\tDon't guess defaults"
    echo -e "\t-U    --no-upgrade\t\tDon't attempt to upgrade"
    echo -e "\t-H    --no-htmldoc\t\tNo htmldoc, means no PDFs"
    echo -e "\t-S    --force-https\t\tForce HTTPS redirect"
    echo -e "\t-C    --recreate-CA\t\tRecreate the CA Keys"
    echo -e "\t-K    --recreate-keys\t\tRecreate the SSL Keys"
    echo -e "\t-Y -y --autoaccept\t\tAuto accept defaults and install"
    echo -e "\t-f    --file\t\t\tUse different update file"
    echo -e "\t-c    --ssl-file\t\tSpecify the ssl path"
    echo -e "\t               \t\t\t\tdefaults to /opt/fog/snapins/ssl"
    echo -e "\t-D    --docroot\t\t\tSpecify the Apache Docroot for fog"
    echo -e "\t               \t\t\t\tdefaults to OS DocumentRoot"
    echo -e "\t-W    --webroot\t\t\tSpecify the web root url want fog to use"
    echo -e "\t            \t\t\t\t(E.G. http://127.0.0.1/fog,"
    echo -e "\t            \t\t\t\t      http://127.0.0.1/)"
    echo -e "\t            \t\t\t\tDefaults to /fog/"
    echo -e "\t-B    --backuppath\t\tSpecify the backup path"
    echo -e "\t      --uninstall\t\tUninstall FOG"
    echo -e "\t-s    --startrange\t\tDHCP Start range"
    echo -e "\t-e    --endrange\t\tDHCP End range"
    echo -e "\t-b    --bootfile\t\tDHCP Boot file"
    echo -e "\t-E    --no-exportbuild\t\tSkip building nfs file"
    echo -e "\t-X    --exitFail\t\tDo not exit if item fails"
    echo -e "\t-T    --no-tftpbuild\t\tDo not rebuild the tftpd config file"
    echo -e "\t-P    --no-pxedefault\t\tDo not overwrite pxe default file"
    echo -e "\t-F    --no-vhost\t\tDo not overwrite vhost file"
    exit 0
}
optspec="h?odEUHSCKYyXxTPFf:c:-:W:D:B:s:e:b:"
while getopts "$optspec" o; do
    case $o in
        -)
            case $OPTARG in
                help)
                    help
                    exit 0
                    ;;
                uninstall)
                    exit 0
                    ;;
                ssl-path)
                    ssslpath="${OPTARG}"
                    ssslpath="${sslpath#'/'}"
                    ssslpath="${sslpath%'/'}"
                    sslpath="/${sslpath}/"
                    ;;
                no-vhost)
                    novhost="y"
                    ;;
                no-defaults)
                    guessdefaults=0
                    ;;
                no-upgrade)
                    doupdate=0
                    ;;
                no-htmldoc)
                    signorehtmldoc=1
                    ;;
                force-https)
                    sforcehttps="yes"
                    ;;
                recreate-keys)
                    srecreateKeys="yes"
                    ;;
                recreate-[Cc][Aa])
                    srecreateCA="yes"
                    ;;
                autoaccept)
                    autoaccept="yes"
                    dbupdate="yes"
                    ;;
                docroot)
                    sdocroot="${OPTARG}"
                    sdocroot="${docroot#'/'}"
                    sdocroot="${docroot%'/'}"
                    sdocroot="/${docroot}/"
                    ;;
                oldcopy)
                    scopybackold=1
                    ;;
                webroot)
                    if [[ $OPTARG != *('/')* ]]; then
                        echo -e "-$OPTARG needs a url path for access either / or /fog for example.\n\n\t\tfor example if you access fog using http://127.0.0.1/ without any trail\n\t\tset the path to /"
                        help
                        exit 2
                    fi
                    swebroot="${OPTARG}"
                    swebroot="${webroot#'/'}"
                    swebroot="${webroot%'/'}"
                    ;;
                file)
                    if [[ -f $OPTARG ]]; then
                        fogpriorconfig=$OPTARG
                    else
                        echo "--$OPTARG requires file after"
                        help
                        exit 3
                    fi
                    ;;
                backuppath)
                    if [[ ! -d $OPTARG ]]; then
                        echo "Path must be an existing directory"
                        help
                        exit 4
                    fi
                    sbackupPath=$OPTARG
                    ;;
                startrange)
                    if [[ $(validip $OPTARG) != 0 ]]; then
                        echo "Invalid ip passed"
                        help
                        exit 5
                    fi
                    sstartrange=$OPTARG
                    ;;
                endrange)
                    if [[ $(validip $OPTARG) != 0 ]]; then
                        echo "Invalid ip passed"
                        help
                        exit 6
                    fi
                    sendrange=$OPTARG
                    ;;
                bootfile)
                    sbootfilename=$OPTARG
                    ;;
                no-exportbuild)
                    sblexports=0
                    ;;
                exitFail)
                    sexitFail=1
                    ;;
                no-tftpbuild)
                    snoTftpBuild="true"
                    ;;
                no-pxedefault)
                    snotpxedefaultfile="true"
                    ;;
                *)
                    if [[ $OPTERR == 1 && ${optspec:0:1} != : ]]; then
                        echo "Unknown option: --${OPTARG}"
                        help
                        exit 7
                    fi
                    ;;
            esac
            ;;
        h|'?')
            help
            exit 0
            ;;
        o)
            scopybackold=1
            ;;
        c)
            ssslpath="${OPTARG}"
            ssslpath="${sslpath#'/'}"
            ssslpath="${sslpath%'/'}"
            ssslpath="/${sslpath}/"
            ;;
        d)
            guessdefaults=0
            ;;
        U)
            doupdate=0
            ;;
        H)
            signorehtmldoc=1
            ;;
        S)
            sforcehttps="yes"
            ;;
        K)
            srecreateKeys="yes"
            ;;
        C)
            srecreateCA="yes"
            ;;
        [yY])
            autoaccept="yes"
            dbupdate="yes"
            ;;
        F)
            novhost="y"
            ;;
        D)
            sdocroot=$OPTARG
            sdocroot=${docroot#'/'}
            sdocroot=${docroot%'/'}
            sdocroot=/${docroot}/
            ;;
        W)
            if [[ $OPTARG != *('/')* ]]; then
                echo -e "-$OPTARG needs a url path for access either / or /fog for example.\n\n\t\tfor example if you access fog using http://127.0.0.1/ without any trail\n\t\tset the path to /"
                help
                exit 2
            fi
            swebroot=$OPTARG
            swebroot=${webroot#'/'}
            swebroot=${webroot%'/'}
            ;;
        f)
            if [[ ! -f $OPTARG ]]; then
                echo "-$OPTARG requires a file to follow"
                help
                exit 3
            fi
            fogpriorconfig=$OPTARG
            ;;
        B)
            if [[ ! -d $OPTARG ]]; then
                echo "Path must be an existing directory"
                help
                exit 4
            fi
            sbackupPath=$OPTARG
            ;;
        s)
            if [[ $(validip $OPTARG) != 0 ]]; then
                echo "Invalid ip passed"
                help
                exit 5
            fi
            sstartrange=$OPTARG
            ;;
        e)
            if [[ $(validip $OPTARG) != 0 ]]; then
                echo "Invalid ip passed"
                help
                exit 6
            fi
            sendrange=$OPTARG
            ;;
        b)
            sbootfilename=$OPTARG
            ;;
        E)
            sblexports=0
            ;;
        X)
            exitFail=1
            ;;
        T)
            snoTftpBuild="true"
            ;;
        P)
            snotpxedefaultfile="true"
            ;;
        :)
            echo "Option -$OPTARG requires a value"
            help
            exit 8
            ;;
        *)
            if [[ $OPTERR == 1 && ${optspec:0:1} != : ]]; then
                echo "Unknown option: -$OPTARG"
                help
                exit 7
            fi
            ;;
    esac
done
[[ -z $version ]] && version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"
[[ -z $OS ]] && OS=$(uname -s)
if [[ $OS =~ ^[^Ll][^Ii][^Nn][^Uu][^Xx] ]]; then
    echo "We do not currently support Installation on non-Linux Operating Systems"
    exit 2 # Fail OS Check
else
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
fi
[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
echo "Installing LSB_Release as needed"
dots "Attempting to get release information"
command -v lsb_release >$workingdir/error_logs/fog_error_${version}.log 2>&1
exitcode=$?
if [[ ! $exitcode -eq 0 ]]; then
    case $linuxReleaseName in
        *[Bb][Ii][Aa][Nn]*|*[Uu][Bb][Uu][Nn][Tt][Uu]*|*[Mm][Ii][Nn][Tt]*)
            apt-get -yq install lsb-release >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            ;;
        *[Cc][Ee][Nn][Tt][Oo][Ss]*|*[Rr][Ee][Dd]*[Hh][Aa][Tt]*|*[Ff][Ee][Dd][Oo][Rr][Aa]*)
            command -v dnf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            exitcode=$?
            case $exitcode in
                0)
                    dnf -y install redhat-lsb-core >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
                *)
                    yum -y install redhat-lsb-core >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    ;;
            esac
            ;;
        *[Aa][Rr][Cc][Hh]*)
            pacman -Sy --noconfirm lsb-release >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            ;;
    esac
fi
[[ -z $OSVersion ]] && OSVersion=$(lsb_release -r| awk -F'[^0-9]*' /^[Rr]elease\([^.]*\).*/'{print $2}')
echo "Done"
. ../lib/common/config.sh
[[ -z $dnsaddress ]] && dnsaddress=""
[[ -z $username ]] && username=""
[[ -z $password ]] && password=""
[[ -z $osid ]] && osid=""
[[ -z $osname ]] && osname=""
[[ -z $dodhcp ]] && dodhcp=""
[[ -z $bldhcp ]] && bldhcp=""
[[ -z $installtype ]] && installtype=""
[[ -z $interface ]] && interface="" #interface=$(getFirstGoodInterface)
[[ -z $ipaddress  ]] && ipaddress="" #ipaddress=$(/sbin/ip addr show $interface | awk -F'[ /]+' '/global/ {print $3}')
[[ -z $routeraddress ]] && routeraddress="" #routeraddress=$(/sbin/ip route | awk "/$interface/ && /via/ {print \$3}")
[[ -z $plainrouter ]] && plainrouter="" #plainrouter=$routeraddress
[[ -z $blexports ]] && blexports=1
[[ -z $installlang ]] && installlang=0
[[ -z $bluseralreadyexists ]] && bluseralreadyexists=0
[[ -z $guessdefaults ]] && guessdefaults=1
[[ -z $doupdate ]] && doupdate=1
[[ -z $ignorehtmldoc ]] && ignorehtmldoc=0
[[ -z $forcehttps ]] && forcehttps="#"
[[ -z $fogpriorconfig ]] && fogpriorconfig="$fogprogramdir/.fogsettings"
#clearScreen
if [[ -z $* || $* != +(-h|-?|--help|--uninstall) ]]; then
    echo > "$workingdir/error_logs/foginstall.log"
    exec &> >(tee -a "$workingdir/error_logs/foginstall.log")
fi
displayBanner
echo -e "   Version: $version Installer/Updater\n"
case $doupdate in
    1)
        if [[ -f $fogpriorconfig ]]; then
            echo -e "\n * Found FOG Settings from previous install at: $fogprogramdir/.fogsettings\n"
            echo -n " * Performing upgrade using these settings"
            . "$fogpriorconfig"
            doOSSpecificIncludes
            [[ -n $sblexports ]] && blexports=$sblexports
            [[ -n $snotpxedefaultfile ]] && notpxedefaultfile=$snotpxedefaultfile
            [[ -n $snoTftpBuild ]] && noTftpBuild=$snoTftpBuild
            [[ -n $sbootfilename ]] && bootfilename=$sbootfilename
            [[ -n $sendrange ]] && endrange=$sendrange
            [[ -n $sstartrange ]] && startrange=$sstartrange
            [[ -n $sbackupPath ]] && backupPath=$sbackupPath
            [[ -n $swebroot ]] && webroot=$swebroot
            [[ -n $sdocroot ]] && docroot=$sdocroot
            [[ -n $srecreateCA ]] && recreateCA=$srecreateCA
            [[ -n $srecreateKeys ]] && recreateKeys=$srecreateKeys
            [[ -n $sforcehttps ]] && forcehttps=$sforcehttps
            [[ -n $signorehtmldoc ]] && ignorehtmldoc=$signorehtmldoc
            [[ -n $ssslpath ]] && sslpath=$ssslpath
            [[ -n $scopybackold ]] && copybackold=$scopybackold
        fi
        ;;
    *)
        echo -e "\n * FOG Installer will NOT attempt to upgrade from\n    previous version of FOG."
        ;;
esac
[[ -f $fogpriorconfig ]] && grep -l webroot $fogpriorconfig >>$workingdir/error_logs/fog_error_${version}.log 2>&1
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
[[ -z $bootfilename ]] && bootfilename="undionly.kpxe"
[[ ! $doupdate -eq 1 || ! $fogupdateloaded -eq 1 ]] && . ../lib/common/input.sh
fullrelease="1.4.4"
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
echo "   #           ** Notice ** FOG is difficult to setup securely          #"
echo "   #        SELinux and IPTables are usually asked to be disabled       #"
echo "   #           There have been strides in adding capabilities           #"
echo "   #          The recommendations would now be more appropriate         #"
echo "   #    to set SELinux to permissive and to disable firewall for now.   #"
echo "   #  You can find some methods to enable SELinux and maintain firewall #"
echo "   #   settings and ports. If you feel comfortable doing so please do   #"
echo "   ######################################################################"
echo "   #            Please see our wiki for more information at:            #"
echo "   ######################################################################"
echo "   #             https://wiki.fogproject.org/wiki/index.php             #"
echo "   ######################################################################"
echo
echo " * Here are the settings FOG will use:"
echo " * Base Linux: $osname"
echo " * Detected Linux Distribution: $linuxReleaseName"
echo " * Server IP Address: $ipaddress"
echo " * Server Subnet Mask: $submask"
echo " * Interface: $interface"
case $installtype in
    N)
        echo " * Installation Type: Normal Server"
        echo " * Internationalization: $installlang"
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
                echo " * Option 067/filename is the bootfile: (e.g. $bootfilename)"
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
            echo " * Installing required packages, if this fails"
            echo " | make sure you have an active internet connection."
            echo
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
                    if [[ $bluseralreadyexists == 1 ]]; then
                        echo
                        echo "\n * Upgrade complete\n"
                        echo
                    else
                        registerStorageNode
                        updateStorageNodeCredentials
                        echo
                        echo " * Setup complete"
                        echo
                        echo
                        echo " * You still need to setup this node in the fog management "
                        echo " | portal. You will need the username and password listed"
                        echo " | below."
                        echo
                        echo " * Management Server URL:"
                        echo "   http://fog-server${webroot}"
                        echo
                        echo "   You will need this, write this down!"
                        echo "   Username:  $username"
                        echo "   Password:  $password"
                        echo "   Interface: $interface"
                        echo "   Address:   $ipaddress"
                        echo
                    fi
                    ;;
                [Nn])
                    configureMySql
                    backupReports
                    configureHttpd
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
                    updateStorageNodeCredentials
                    echo
                    echo " * Setup complete"
                    echo
                    echo "   You can now login to the FOG Management Portal using"
                    echo "   the information listed below.  The login information"
                    echo "   is only if this is the first install."
                    echo
                    echo "   This can be done by opening a web browser and going to:"
                    echo
                    echo "   http://${ipaddress}${webroot}management"
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
