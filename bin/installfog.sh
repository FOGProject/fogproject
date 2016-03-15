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
case "$EUID" in
    0)
        ;;
    *)
        exec sudo $0 $@ || echo "FOG Installation must be run as root user"
        exit 1
        ;;
esac
. ../lib/common/functions.sh
. ../lib/common/config.sh
version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' ../packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"
OS=$(uname -s)
if [[ $OS != Linux ]]; then
    echo "We do not currently support Installation on non-Linux Operating Systems"
    exit 1
else
    if [[ -f /etc/os-release ]]; then
        linuxReleaseName=$(sed -n 's/^NAME=\(.*\)/\1/p' /etc/os-release | tr -d '"')
        OSVersion=$(sed -n 's/^VERSION_ID=\([^.]*\).*/\1/p' /etc/os-release | tr -d '"')
    elif [[ -f /etc/redhat-release ]]; then
        linuxReleaseName=$(cat /etc/redhat-release | awk '{print $1}')
        OSVersion=$(cat /etc/redhat-release | sed s/.*release\ // | sed s/\ .*//)
    elif [[ -f /etc/debian_version ]]; then
        linuxReleaseName='Debian'
        OSVersion=$(cat /etc/debian_version)
    fi
fi
[[ ! -d ./error_logs/ ]] && mkdir -p ./error_logs >/dev/null 2>&1
command -v lsb_release >$workingdir/error_logs/fog_error_${version}.log 2>&1
if [[ ! $? -eq 0 ]]; then
    case $linuxReleaseName in
        *[Dd][Ee][Bb][Ii][Aa][Nn]*|*[Bb][Uu][Nn][Tt][Uu]*)
            apt-get -yq install lsb_release >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            ;;
        *[Cc][Ee][Nn][Tt][Oo][Ss]*|*[Rr][Ee][Dd]*[Hh][Aa][Tt]*|*[Ff][Ee][Dd][Oo][Rr][Aa]*)
            command -v dnf >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            if [[ $? -eq 0 ]]; then
                dnf -y install redhat-lsb-core >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            else
                yum -y install redhat-lsb-core >>$workingdir/error_logs/fog_error_${version}.log 2>&1
            fi
            ;;
    esac
fi
[[ -z $OSVersion ]] && OSVersion=$(lsb_release -r| awk -F'[^0-9]*' /^[Rr]elease\([^.]*\).*/'{print $2}')
command -v systemctl >>$workingdir/error_logs/fog_error_${version}.log 2>&1
[[ $? -eq 0 ]] && systemctl="yes"
installtype=""
ipaddress=""
interface=""
routeraddress=""
plainrouter=""
dnsaddress=""
dnsbootimage=""
username=""
password=""
osid=""
osname=""
dodhcp=""
bldhcp=""
blexports=1
snmysqluser=""
snmysqlpass=""
snmysqlhost=""
installlang=""
bluseralreadyexists=0
guessdefaults=1
doupdate=1
ignorehtmldoc=0
forcehttps="#"
clearScreen
if [[ -z $* ]]; then
    echo > "$workingdir/error_logs/foginstall.log"
    exec &> >(tee -a "$workingdir/error_logs/foginstall.log")
else
    if [[ $* != +(-h|-?|--help|--uninstall) ]]; then
        echo > "$workingdir/error_logs/foginstall.log"
        exec &> >(tee -a "$workingdir/error_logs/foginstall.log")
    fi
fi
displayBanner
echo "   Version: ${version} Installer/Updater"
echo
fogpriorconfig="$fogprogramdir/.fogsettings"
if [[ $doupdate -eq 1 ]]; then
    if [[ -f $fogpriorconfig ]]; then
        echo
        echo " * Found FOG Settings from previous install at: $fogprogramdir/.fogsettings"
        echo -n " * Performing upgrade using these settings"
        . "$fogpriorconfig"
        doOSSpecificIncludes
        . "$fogpriorconfig"
    fi
else
    echo
    echo " * FOG Installer will NOT attempt to upgrade from"
    echo "   previous version of FOG."
    echo
fi
optspec="h?dEUHSCKYyXxTPf:c:-:W:D:B:s:e:b:"
while getopts "$optspec" o; do
    case $o in
        -)
            case $OPTARG in
                help)
                    help
                    exit 0
                    ;;
                ssl-path)
                    sslpath="${OPTARG}"
                    sslpath="${sslpath#'/'}"
                    sslpath="${sslpath%'/'}"
                    sslpath="/${sslpath}/"
                    ;;
                no-defaults)
                    guessdefaults=0
                    ;;
                no-upgrade)
                    doupdate=0
                    ;;
                no-htmldoc)
                    ignorehtmldoc=1
                    ;;
                force-https)
                    forcehttps="yes"
                    ;;
                recreate-keys)
                    recreateKeys="yes"
                    ;;
                recreate-[Cc][Aa])
                    recreateCA="yes"
                    ;;
                autoaccept)
                    autoaccept="yes"
                    dbupdate="yes"
                    ;;
                docroot)
                    docroot="${OPTARG}"
                    docroot="${docroot#'/'}"
                    docroot="${docroot%'/'}"
                    docroot="/${docroot}/"
                    ;;
                webroot)
                    webroot="${OPTARG}"
                    webroot="${webroot#'/'}"
                    webroot="${webroot%'/'}"
                    ;;
                uninstall)
                    uninstall
                    exit
                    ;;
                file)
                    if [[ -f $OPTARG ]]; then
                        fogpriorconfig=$OPTARG
                    else
                        echo "--$OPTARG requires file after"
                        help
                        exit 1
                    fi
                    ;;
                backuppath)
                    if [[ ! -d $OPTARG ]]; then
                        echo "Path must be an existing directory"
                        help
                        exit 1
                    fi
                    backupPath=$OPTARG
                    ;;
                startrange)
                    if [[ $(validip $OPTARG) != 0 ]]; then
                        echo "Invalid ip passed"
                        help
                        exit 1
                    fi
                    startrange=$OPTARG
                    ;;
                endrange)
                    if [[ $(validip $OPTARG) != 0 ]]; then
                        echo "Invalid ip passed"
                        help
                        exit 1
                    fi
                    endrange=$OPTARG
                    ;;
                bootfile)
                    bootfilename=$OPTARG
                    ;;
                no-exportbuild)
                    blexports=0
                    ;;
                exitFail)
                    exitFail=1
                    ;;
                no-tftpbuild)
                    noTftpBuild="true"
                    ;;
                no-pxedefault)
                    notpxedefaultfile="true"
                    ;;
                *)
                    if [[ $OPTERR == 1 && ${optspec:0:1} != : ]]; then
                        echo "Unknown option: --${OPTARG}"
                        help
                        exit 1
                    fi
                    ;;
            esac
            ;;
        h|'?')
            help
            exit 0
            ;;
        d)
            guessdefaults=0
            ;;
        U)
            doupdate=0
            ;;
        H)
            ignorehtmldoc=1
            ;;
        S)
            forcehttps="yes"
            ;;
        K)
            recreateKeys="yes"
            ;;
        C)
            recreateCA="yes"
            ;;
        [yY])
            autoaccept="yes"
            dbupdate="yes"
            ;;
        D)
            docroot=$OPTARG
            docroot=${docroot#'/'}
            docroot=${docroot%'/'}
            docroot=/${docroot}/
            ;;
        W)
            if [[ $OPTARG != *('/')* ]]; then
                echo -e "-$OPTARG needs a url path for access either / or /fog for example.\n\n\t\tfor example if you access fog using http://127.0.0.1/ without any trail\n\t\tset the path to /"
                help
                exit 1
            fi
            webroot=$OPTARG
            webroot=${webroot#'/'}
            webroot=${webroot%'/'}
            ;;
        f)
            if [[ ! -f $OPTARG ]]; then
                echo "-$OPTARG requires a file to follow"
                help
                exit 1
            fi
            fogpriorconfig=$OPTARG
            ;;
        B)
            if [[ ! -d $OPTARG ]]; then
                echo "Path must be an existing directory"
                help
                exit 1
            fi
            backupPath=$OPTARG
            ;;
        s)
            if [[ $(validip $OPTARG) != 0 ]]; then
                echo "Invalid ip passed"
                help
                exit 1
            fi
            startrange=$OPTARG
            ;;
        e)
            if [[ $(validip $OPTARG) != 0 ]]; then
                echo "Invalid ip passed"
                help
                exit 1
            fi
            endrange=$OPTARG
            ;;
        b)
            bootfilename=$OPTARG
            ;;
        E)
            blexports=0
            ;;
        X)
            exitFail=1
            ;;
        T)
            noTftpBuild="true"
            ;;
        P)
            notpxedefaultfile="true"
            ;;
        c)
            sslpath="${OPTARG}"
            sslpath="${sslpath#'/'}"
            sslpath="${sslpath%'/'}"
            sslpath="/${sslpath}/"
            ;;
        :)
            echo "Option -$OPTARG requires a value"
            help
            exit 1
            ;;
        *)
            if [[ $OPTERR == 1 && ${optspec:0:1} != ":" ]]; then
                echo "Unknown option: -$OPTARG"
                help
                exit 1
            fi
            ;;
    esac
done
grep -l webroot /opt/fog/.fogsettings >>$workingdir/error_logs/fog_error_${version}.log 2>&1
case $? in
    0)
        if [[ -n $webroot ]]; then
            webroot=${webroot#'/'}
            webroot=${webroot%'/'}
            webroot="${webroot}/"
        fi
        ;;
    *)
        [[ -z $webroot ]] && webroot="fog/"
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
        echo " * Donate: $donate"
        echo " * Internationalization: $installlang"
        echo " * Image Storage Location: $storageLocation"
        case $bldhcp in
            1)
                echo " * Using FOG DHCP: Yes"
                echo " * DHCP router Address: $plainrouter"
                echo " * DHCP DNS Address: $dnsbootimage"
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
                newpackagelist=""
                for z in $packages; do
                    [[ $z != htmldoc ]] && newpackagelist="$newpackagelist $z"
                done
                packages="$(echo $newpackagelist)"
            fi
            if [[ $bldhcp == 0 ]]; then
                newpackagelist=""
                for z in $packages; do
                    [[ $z != $dhcpname ]] && newpackagelist="$newpackagelist $z"
                done
                packages="$(echo $newpackagelist)"
            fi
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
                        [[ -z $storageLoction ]] && storageLocation="/images"
                        while [[ ! -d $storageLocation && $storageLocation != "/images" ]]; do
                            echo -n " * Please enter a valid directory for your storage location (/images) "
                            read storageLocation
                            [[ -z $storageLocation ]] && storageLocation="/images"
                        done
                        ;;
                esac
            fi
            case $installtype in
                [Ss])
                    packages=$(echo $packages | sed 's/[a-zA-Z-]*dhcp[-a-zA-Z]*//g')
                    configureUsers
                    configureMinHttpd
                    configureStorage
                    configureTFTPandPXE
                    configureFTP
                    configureUDPCast
                    installInitScript
                    installFOGServices
                    configureFOGService
                    configureNFS
                    writeUpdateFile
                    linkOptFogDir
                    if [[ $bluseralreadyexists == 1 ]]; then
                        echo
                        echo " * Upgrade complete"
                        echo
                    else
                        echo
                        echo " * Setup complete"
                        echo
                        echo
                        echo " * You still need to setup this node in the fog management "
                        echo " | portal. You will need the username and password listed"
                        echo " | below."
                        echo
                        echo " * Management Server URL:"
                        echo "   http://${snmysqlhost}/fog"
                        echo
                        echo "   You will need this, write this down!"
                        echo "   Username: $username"
                        echo "   Password: $password"
                        echo
                    fi
                    ;;
                [Nn])
                    configureUsers
                    configureMySql
                    backupReports
                    configureHttpd
                    dots "Backing up database"
                    if [[ -d $backupPath/fog_web_${version}.BACKUP ]]; then
                        [[ ! -d $backupPath/fogDBbackups ]] && mkdir -p $backupPath/fogDBbackups >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                        wget --no-check-certificate -O $backupPath/fogDBbackups/fog_sql_${version}_$(date +"%Y%m%d_%I%M%S").sql "http://$ipaddress/$webroot/management/export.php?type=sql" >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                    fi
                    errorStat $?
                    case $dbupdate in
                        [Yy]|[Yy][Ee][Ss])
                            dots "Updating Database"
                            wget -qO - --post-data="confirm=1" --no-proxy http://127.0.0.1/${webroot}management/index.php?node=schemaupdater >>$workingdir/error_logs/fog_error_${version}.log 2>&1 || wget -qO - --post-data="confirm=1" --no-proxy http://${ipaddress}/${webroot}management/index.php?node=schemaupdater >>$workingdir/error_logs/fog_error_${version}.log 2>&1
                            errorStat $?
                            ;;
                        *)
                            echo
                            echo " * You still need to install/update your database schema."
                            echo " * This can be done by opening a web browser and going to:"
                            echo
                            echo "   http://${ipaddress}/fog/management"
                            echo
                            read -p " * Press [Enter] key when database is updated/installed."
                            echo
                            ;;
                    esac
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
                    echo
                    echo " * Setup complete"
                    echo
                    echo "   You can now login to the FOG Management Portal using"
                    echo "   the information listed below.  The login information"
                    echo "   is only if this is the first install."
                    echo
                    echo "   This can be done by opening a web browser and going to:"
                    echo
                    echo "   http://${ipaddress}/${webroot}management"
                    echo
                    echo "   Default User Information"
                    echo "   Username: fog"
                    echo "   Password: password"
                    echo
                    ;;
            esac
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
