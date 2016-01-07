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
command -v lsb_release >/var/log/fog_error_${version}.log 2>&1
if [[ ! $? -eq 0 ]]; then
    case $linuxReleaseName in
        *[Dd][Ee][Bb][Ii][Aa][Nn]*|*[Bb][Uu][Nn][Tt][Uu]*)
            apt-get -yq install lsb_release >>/var/log/fog_error_${version}.log 2>&1
            ;;
        *[Cc][Ee][Nn][Tt][Oo][Ss]*|*[Rr][Ee][Dd]*[Hh][Aa][Tt]*|*[Ff][Ee][Dd][Oo][Rr][Aa]*)
            command -v dnf >>/var/log/fog_error_${version}.log 2>&1
            if [[ $? -eq 0 ]]; then
                dnf -y install redhat-lsb-core >>/var/log/fog_error_${version}.log 2>&1
            else
                yum -y install redhat-lsb-core >>/var/log/fog_error_${version}.log 2>&1
            fi
            ;;
    esac
fi
if [[ -z $OSVersion ]]; then
    OSVersion=$(lsb_release -r| awk -F'[^0-9]*' /^[Rr]elease\([^.]*\).*/'{print $2}')
fi
command -v systemctl >>/var/log/fog_error_${version}.log 2>&1
if [[ $? == 0 ]]; then
    systemctl="yes"
fi
installtype=""
ipaddress=""
interface=""
routeraddress=""
plainrouter=""
dnsaddress=""
dnsbootimage=""
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
storageftpuser=""
storageftppass=""
guessdefaults=1
doupdate=1
ignorehtmldoc=0
forcehttps="#"
clearScreen
if [[ -z $* ]]; then
    echo > "/var/log/foginstall.log"
    exec &> >(tee -a "/var/log/foginstall.log")
else
    if [[ $* != +(-h|-?|--help|--uninstall) ]]; then
        echo > "/var/log/foginstall.log"
        exec &> >(tee -a "/var/log/foginstall.log")
    fi
fi
displayBanner
display_center "Version: ${version} Installer/Updater"
echo
fogpriorconfig="$fogprogramdir/.fogsettings"
if [[ $doupdate -eq 1 ]]; then
    if [[ -f $fogpriorconfig ]]; then
        echo
        display_center " * Found FOG Settings from previous install at: $fogprogramdir/.fogsettings"
        echo -n " * Performing upgrade using these settings..."
        . "$fogpriorconfig"
        doOSSpecificIncludes
        . "$fogpriorconfig"
    fi
else
    echo
    display_center "FOG Installer will NOT attempt to upgrade from"
    display_center "previous version of FOG."
    echo
fi
optspec="h?dEUHSCKYyXxf:-:W:D:B:s:e:b:"
while getopts "$optspec" o; do
    case $o in
        -)
            case $OPTARG in
                help)
                    help
                    exit 0
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
grep -l webroot /opt/fog/.fogsettings >>/var/log/fog_error_${version}.log 2>&1
if [[ $? -eq 0 || ! -z $webroot ]]; then
    webroot=${webroot#'/'}
    webroot=${webroot%'/'}
    webroot=${webroot}/
elif [[ ! $? -eq 0 && -z $webroot ]]; then
    webroot="fog/"
fi
if [[ -z $backupPath ]]; then
    backupPath="/home/"
fi
backupPath="${backupPath%'/'}"
backupPath="${backupPath#'/'}"
backupPath="/$backupPath/"
if [[ ! $doupdate -eq 1 || ! $fogupdateloaded -eq 1 ]]; then
    . ../lib/common/input.sh
fi
echo
display_center "######################################################################"
display_center "#     FOG now has everything it needs for this setup, but please     #"
display_center "#   understand that this script will overwrite any setting you may   #"
display_center "#   have setup for services like DHCP, apache, pxe, tftp, and NFS.   #"
display_center "######################################################################"
display_center "# It is not recommended that you install this on a production system #"
display_center "#        as this script modifies many of your system settings.       #"
display_center "######################################################################"
display_center "#             This script should be run by the root user.            #"
display_center "#      It will prepend the running with sudo if root is not set      #"
display_center "######################################################################"
display_center "#           ** Notice ** FOG is difficult to setup securely          #"
display_center "#        SELinux and IPTables are usually asked to be disabled       #"
display_center "#           There have been strides in adding capabilities           #"
display_center "#          The recommendations would now be more appropriate         #"
display_center "#    to set SELinux to permissive and to disable firewall for now.   #"
display_center "#  You can find some methods to enable SELinux and maintain firewall #"
display_center "#   settings and ports. If you feel comfortable doing so please do   #"
display_center "######################################################################"
display_center "#            Please see our wiki for more information at:            #"
display_center "######################################################################"
display_center "#             https://wiki.fogproject.org/wiki/index.php             #"
display_center "######################################################################"
echo
display_center "Here are the settings FOG will use:"
display_center "Base Linux: $osname"
display_center "Detected Linux Distribution: $linuxReleaseName"
display_center "Server IP Address: $ipaddress"
display_center "Interface: $interface"
case $installtype in
    N)
        display_center "Installation Type: Normal Server"
        display_center "Donate: $donate"
        display_center "Internationalization: $installlang"
        display_center "Image Storage Location: $storageLocation"
        case $bldhcp in
            1)
                display_center "Using FOG DHCP: Yes"
                display_center "DHCP router Address: $plainrouter"
                display_center "DHCP DNS Address: $dnsbootimage"
                ;;
            *)
                display_center "Using FOG DHCP: No"
                display_center "DHCP will NOT be setup but you must setup your"
                display_center "current DHCP server to use FOG for PXE services."
                echo
                display_center "On a Linux DHCP server you must set: next-server"
                echo
                display_center "On a Windows DHCP server you must set options 066 and 067"
                echo
                display_center "Option 066 is the IP of the FOG Server: (e.g. $ipaddress)"
                display_center "Option 067 is the undionly.kpxe file: (e.g. undionly.kpxe)"
                ;;
        esac
        ;;
    S)
        display_center "Installation Type: Storage Node"
        display_center "Node IP Address: $ipaddress"
        display_center "MySQL Database Host: $snmysqlhost"
        display_center "MySQL Database User: $snmysqluser"
        ;;
esac
echo
while [[ -z $blGo ]]; do
    echo
    if [[ -z $autoaccept ]]; then
        echo -n " * Are you sure you wish to continue (Y/N) "
        read blGo
    else
        blGo="y"
    fi
    echo
    case $blGo in
        [Yy]|[Yy][Ee][Ss])
            display_center "Installation Started"
            echo
            display_center "Installing required packages, if this fails"
            display_center "make sure you have an active internet connection."
            echo
            if [[ $ignorehtmldoc -eq 1 ]]; then
                newpackagelist=""
                for z in $packages; do
                    if [[ $z != htmldoc ]]; then
                        newpackagelist="$newpackagelist $z"
                    fi
                done
                packages=$(trim $newpackagelist)
            fi
            if [[ $bldhcp == 0 ]]; then
                newpackagelist=""
                for z in $packages; do
                    if [[ $z != $dhcpname ]]; then
                        newpackagelist="$newpackagelist $z"
                    fi
                done
                packages=$(trim $newpackagelist)
            fi
            installPackages
            echo
            display_center "Confirming package installation."
            echo
            confirmPackageInstallation
            echo
            display_center "Configuring services."
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
                        if [[ -z $storageLocation ]]; then
                            storageLocation="/images"
                        fi
                        while [[ ! -d $storageLocation && $storageLocation != "/images" ]]; do
                            echo -n " * Please enter a valid directory for your storage location (/images) "
                            read storageLocation
                            if [[ -z $storageLocation ]]; then
                                storageLocation="/images"
                            fi
                        done
                        ;;
                esac
            fi
            case $installtype in
                [Ss])
                    packages=$(echo "$packages"|sed 's/[[:space:]].*dhcp.*[[:space:]]/ /')
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
                    if [[ $bluseralreadyexists == 1 ]]; then
                        echo
                        display_center "Upgrade complete!"
                        echo
                    else
                        echo
                        display_center "Setup complete!"
                        echo
                        echo
                        display_center "You still need to setup this node in the fog management "
                        display_center "portal.  You will need the username and password listed"
                        display_center "below."
                        echo
                        display_center "Management Server URL:"
                        display_center "http://${snmysqlhost}/fog"
                        echo
                        display_center "You will need this, write this down!"
                        display_center "Username: $storageftpuser"
                        display_center "Password: $storageftppass"
                        echo
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
                        if [[ ! -d $backupPath/fogDBbackups ]]; then
                            mkdir -p $backupPath/fogDBbackups >>/var/log/fog_error_${version}.log 2>&1
                        fi
                        wget --no-check-certificate -O $backupPath/fogDBbackups/fog_sql_${version}_$(date +"%Y%m%d_%I%M%S").sql "http://$ipaddress/$webroot/management/export.php?type=sqldump" >>/var/log/fog_error_${version}.log 2>&1
                    fi
                    errorStat $?
                    case $dbupdate in
                        [Yy]|[Yy][Ee][Ss])
                            dots "Updating Database"
                            wget -qO - --post-data="confirm=1" --no-proxy http://127.0.0.1/${webroot}management/index.php?node=schemaupdater >>/var/log/fog_error_${version}.log 2>&1 || wget -qO - --post-data="confirm=1" --no-proxy http://${ipaddress}/${webroot}management/index.php?node=schemaupdater >>/var/log/fog_error_${version}.log 2>&1
                            errorStat $?
                            ;;
                        *)
                            echo
                            display_center "You still need to install/update your database schema."
                            display_center "This can be done by opening a web browser and going to:"
                            echo
                            display_center "http://${ipaddress}/fog/management"
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
                    display_center "Setup complete!"
                    echo
                    display_center "You can now login to the FOG Management Portal using"
                    display_center "the information listed below.  The login information"
                    display_center "is only if this is the first install."
                    echo
                    display_center "This can be done by opening a web browser and going to:"
                    echo
                    display_center "http://${ipaddress}/${webroot}management"
                    echo
                    display_center "Default User Information"
                    display_center "Username: fog"
                    display_center "Password: password"
                    echo
                    ;;
            esac
            ;;
        [Nn]|[Nn][Oo])
            echo "FOG installer exited by user request"
            exit 1
            ;;
        *)
            echo
            echo "Sorry, answer not recognized"
            echo
            ;;
    esac
done
