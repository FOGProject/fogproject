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
#
#
# Include all the common installer stuff for all distros
case "$EUID" in
    0) ;;
    *) exec sudo $0 $@ || echo FOG Installation must be run as root user; exit 1 ;;
esac
. ../lib/common/functions.sh
. ../lib/common/config.sh
OS=`uname -s`
if [ "$OS" != "Linux" ]; then
    echo "We do not currently support Installation on non-Linux Operating Systems"
    exit 1
else
    if [ -f /etc/os-release ]; then
        linuxReleaseName=`sed -n 's/^NAME=\(.*\)/\1/p' /etc/os-release | tr -d '"'`
        OSVersion=`sed -n 's/^VERSION_ID=\([^.]*\).*/\1/p' /etc/os-release | tr -d '"'`
    elif [ -f /etc/redhat-release ]; then
        linuxReleaseName=`cat /etc/redhat-release | awk '{print $1}'`;
        OSVersion=`cat /etc/redhat-release | sed s/.*release\ // | sed s/\ .*//`
    elif [ -f /etc/debian_version ]; then
        linuxReleaseName='Debian'
        OSVersion=`cat /etc/debian_version`
    fi
fi
if [ -z "$OSVersion" ]; then
    if [[ "$linuxReleaseName" == +(*[Dd]'ebian'*|*'buntu'*) ]]; then
        apt-get install lsb_release >/dev/null 2>&1
    elif [[ "$linuxReleaseName" == +(*[Cc]'ent'[Oo][Ss]*|*[Rr]'ed'*[Hh]'at'*|*[Ff]'edora'*) ]]; then
        yum -y install redhat-lsb-core >/dev/null 2>&1
    fi
    OSVersion=`lsb_release -r| awk -F'[^0-9]*' /^[Rr]elease\([^.]*\).*/'{print $2}'`;
fi
OSVersion=`echo $OSVersion | cut -d '.' -f1`
if [[ "$OSVersion" -ge 7 && "$linuxReleaseName" == +(*[Cc]'ent'[Oo][Ss]*|*[Rr]'ed'*[Hh]'at'*) ]] || [[ "$OSVersion" -ge 15 && "$linuxReleaseName" == +(*[Ff]'edora'*|*'buntu'*) ]] || [[ "$OSVersion" -ge 8 && "$linuxReleaseName" == +(*[Dd]'ebian'*) ]]; then
    systemctl="yes";
fi
installtype="";
ipaddress="";
interface="";
routeraddress="";
plainrouter="";
dnsaddress="";
dnsbootimage="";
password="";
osid="";
osname="";
dodhcp="";
bldhcp="";
blexports=1;
# new with version 0.24
snmysqluser=""
snmysqlpass="";
snmysqlhost="";
# new with version 0.29
installlang="";
bluseralreadyexists="0";
storageftpuser="";
storageftppass="";
#argument options
guessdefaults="1";
#do update if the .fogsettings file exists
doupdate="1";
#ignore htmldoc
ignorehtmldoc="0";
forcehttps="#";
clearScreen;
# process arguments
if [ -z "$*" ]; then
    echo > "/var/log/foginstall.log"
    exec &> >(tee -a "/var/log/foginstall.log")
else
    if [[ "$*" != +(-h|-?|--help|--uninstall) ]]; then
        echo > "/var/log/foginstall.log"
        exec &> >(tee -a "/var/log/foginstall.log")
    fi
fi
displayBanner;
echo -e "  Version: ${version} Installer/Updater\n";
fogpriorconfig="$fogprogramdir/.fogsettings"
if [ "$doupdate" = "1" ]; then
    if [ -f "$fogpriorconfig" ]; then
        echo "";
        echo "  * Found FOG Settings from previous install at: $fogprogramdir/.fogsettings";
        echo -n "  * Performing upgrade using these settings...";
        . "$fogpriorconfig";
        doOSSpecificIncludes;
        . "$fogpriorconfig";
    fi
else
    echo "";
    echo "  FOG Installer will NOT attempt to upgrade from";
    echo "  previous version of FOG.";
    echo "";
fi
optspec="h?dEUHSCKYyf:-:W:D:B:s:e:b:"
while getopts "$optspec" o; do
    #long options
    case "${o}" in
        -)
        case "${OPTARG}" in
            help) help; exit 0 ;;
            no-defaults) guessdefaults=0 ;;
            no-upgrade) doupdate=0 ;;
            no-htmldoc) ignorehtmldoc=1 ;;
            force-https) forcehttps="yes" ;;
            recreate-keys) recreateKeys="yes" ;;
            recreate-[Cc][Aa]) recreateCA="yes" ;;
            autoaccept) autoaccept="yes"; dbupdate="yes" ;;
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
            uninstall) uninstall; exit ;;
            file)
            if [ -f "${OPTARG}" ]; then
                fogpriorconfig="${OPTARG}"
            else
                echo "--${OPTARG} requires file after"
                help
                exit 1
            fi
            ;;
            backuppath)
            if [ ! -d "${OPTARG}" ]; then
                echo "Path must be an existing directory"
                help
                exit 1
            fi
            backupPath="${OPTARG}"
            ;;
            startrange)
            if [ `validip ${OPTARG}` != 0 ]; then
                echo "Invalid ip passed"
                help
                exit 1
            fi
            startrange="${OPTARG}"
            ;;
            endrange)
            if [ `validip ${OPTARG}` != 0 ]; then
                echo "Invalid ip passed"
                help
                exit 1
            fi
            endrange="${OPTARG}"
            ;;
            bootfile) bootfilename="${OPTARG}" ;;
            no-exportbuild) blexports=0 ;;
            *)
            if [ "$OPTERR" = 1 -a "${optspec:0:1}" != ":" ]; then
                echo "Unknown option: --${OPTARG}"
                help
                exit 1
            fi
            ;;
        esac
        ;;
        #short options
        h|'?') help; exit 0 ;;
        d) guessdefaults=0 ;;
        U) doupdate=0 ;;
        H) ignorehtmldoc=1 ;;
        S) forcehttps="yes" ;;
        K) recreateKeys="yes" ;;
        C) recreateCA="yes" ;;
        [yY]) autoaccept="yes"; dbupdate="yes" ;;
        D)
        docroot="${OPTARG}"
        docroot="${docroot#'/'}"
        docroot="${docroot%'/'}"
        docroot="/${docroot}/"
        ;;
        W)
        if [[ "${OPTARG}" != *('/')* ]]; then
            echo -e "-$OPTARG needs a url path for access either / or /fog for example.\n\n\t\tfor example if you access fog using http://127.0.0.1/ without any trail\n\t\tset the path to /"
            help
            exit 1
        fi
        webroot="${OPTARG}"
        webroot="${webroot#'/'}"
        webroot="${webroot%'/'}"
        ;;
        f)
        if [ ! -f "${OPTARG}" ]; then
            echo -${OPTARG} requires a file to follow
			help
			exit 1
        fi
        fogpriorconfig="${OPTARG}"
        ;;
        B)
        if [ ! -d "${OPTARG}" ]; then
            echo "Path must be an existing directory"
            help
            exit 1
        fi
        backupPath="${OPTARG}"
        ;;
        s)
        if [ "`validip ${OPTARG}`" != 0 ]; then
            echo "Invalid ip passed"
            help
            exit 1
        fi
        startrange="${OPTARG}"
        ;;
        e)
        if [ "`validip ${OPTARG}`" != 0 ]; then
            echo "Invalid ip passed"
            help
            exit 1
        fi
        endrange="${OPTARG}"
        ;;
        b) bootfilename="${OPTARG}" ;;
        E) blexports=0 ;;
        :) echo "Option -${OPTARG} requires a value"; help; exit 1 ;;
        *)
        if [ "$OPTERR" = 1 -a "${optspec:0:1}" != ":" ]; then
            echo "Unknown option: -${OPTARG}"
            help
            exit 1
        fi
        ;;
    esac
done
webrootexists=`grep -l 'webroot' "/opt/fog/.fogsettings" >/dev/null 2>&1; echo $?`;
if [ "$webrootexists" != 0 -a -z "$webroot" ]; then
    webroot="fog/";
elif [ "$webrootexists" -eq 0 -o ! -z "$webroot" ]; then
    webroot="${webroot#'/'}"
    webroot="${webroot%'/'}"
    webroot="${webroot}/"
fi
if [ -z "$backupPath" ]; then
    backupPath="/home/";
else
    backupPath="${backupPath%'/'}"
    backupPath="${backupPath#'/'}"
    backupPath="/$backupPath"
fi
. ../lib/common/input.sh
if [ "$installtype" = "N" ]; then
    echo "";
    echo "  #####################################################################";
    echo "";
    echo "  FOG now has everything it needs to setup your server, but please"
    echo "  understand that this script will overwrite any setting you may"
    echo "  have setup for services like DHCP, apache, pxe, tftp, and NFS."
    echo "";
    echo "  It is not recommended that you install this on a production system";
    echo "  as this script modifies many of your system settings.";
    echo "";
    echo "  This script should be run by the root user on Redhat or with sudo on Ubuntu."
    echo
    echo "  ** Notice ** Redhat users will need to disable SELinux and iptables in "
    echo "  order to use FOG"
    echo "  Please see our wiki for more information at http://www.fogproject.org/wiki"
    echo
    echo "";
    echo "  Here are the settings FOG will use:";
    echo "         Base Linux: ${osname}";
    echo "         Detected Linux Distribution: ${linuxReleaseName}";
    echo "         Installation Type: Normal Server";
    echo "         Server IP Address: ${ipaddress}";
    echo "         DHCP router Address: ${plainrouter}";
    echo "         DHCP DNS Address: ${dnsbootimage}";
    echo "         Interface: ${interface}";
    echo "         Using FOG DHCP: ${bldhcp}";
    echo "         Internationalization: ${installlang}";
    echo "         Image Storage Location: ${storageLocation}";
    echo "         MySQL Connection Type: MYSQLI_ASYNC";
    echo "         Donate: ${donate}";
    echo "";
elif [ "$installtype" = "S" ]; then
    echo "";
    echo "  #####################################################################";
    echo "";
    echo "  FOG now has everything it needs to setup your storage node, but please"
    echo "  understand that this script will overwrite any setting you may"
    echo "  have setup for services like FTP, and NFS."
    echo "";
    echo "  It is not recommended that you install this on a production system";
    echo "  as this script modifies many of your system settings.";
    echo "";
    echo "  This script should be run by the root user on Fedora, or with sudo on Ubuntu."
    echo "";
    echo "  Here are the settings FOG will use:";
    echo "         Base Linux: ${osname}";
    echo "         Detected Linux Distribution: ${linuxReleaseName}";
    echo "         Installation Type: Storage Node";
    echo "         Server IP Address: ${ipaddress}";
    echo "         Interface: ${interface}";
    echo "         MySql Database Host: ${snmysqlhost}";
    echo "         MySql Database User: ${snmysqluser}";
    echo "         MySql Database Password: [Protected]";
    echo "";
fi
if [ "$bldhcp" == "0" ]; then
    echo "         DHCP will NOT be setup but you must setup your";
    echo "         current DHCP server to use FOG for PXE services.";
    echo ;
    echo "         On a Linux DHCP server you must set:";
    echo "             next-server";
    echo ;
    echo "         On a Windows DHCP server you must set:";
    echo "             option 066 & 067";
    echo;
    echo "		   Option 066 is the IP of the FOG Server: (e.g. ${ipaddress})";
    echo "		   Option 067 is the undionly.kpxe file: (e.g. undionly.kpxe)";
fi;
while [ "$blGo" = "" ]; do
    echo
    if [ -z "$autoaccept" ]; then
        echo -n "  Are you sure you wish to continue (Y/N) ";
        read blGo;
    else
        blGo="y";
    fi
    echo "";
    case "$blGo" in
        [yY]*)
        echo "  Installation Started...";
        echo "";
        echo "  Installing required packages, if this fails";
        echo "  make sure you have an active internet connection.";
        echo "";
        # Which package list do we use?
        if [ "$installtype" = "S" ]; then
            packages=$storageNodePackages;
        fi
        if [ "${ignorehtmldoc}" = "1" ]; then
            newpackagelist="";
            for z in $packages; do
                if [ "$z" != "htmldoc" ]; then
                    newpackagelist="${newpackagelist} $z";
                fi
            done
            packages=$newpackagelist;
        fi
        if [ "${bldhcp}" = "0" ]; then
            newpackagelist="";
            for z in $packages; do
                if [ "$z" != "$dhcpname" ]; then
                    newpackagelist="${newpackagelist} $z";
                fi
            done
            packages=$newpackagelist;
        fi
        installPackages;
        echo "";
        echo "  Confirming package installation.";
        echo "";
        confirmPackageInstallation;
        echo "";
        echo "  Configuring services.";
        echo "";
        if [ ! -n "$storageLocation" -a -z "$autoaccept" ]; then
            echo "";
            echo -n "     What is the storage location for your images directory? (/images) ";
            read storageLocation;
            if [ "$storageLocation" == "" ]; then
                storageLocation="/images";
            fi
        elif [ ! -n "$storageLocation" -a "$autoaccept" == "yes" ]; then
            storageLocation="/images";
        fi
        if [ "$installtype" = "S" ]; then
            # Storage Node installation
            configureUsers;
            configureMinHttpd;
            configureStorage;
            configureFTP;
            configureUDPCast;
            installInitScript;
            installFOGServices;
            configureFOGService;
            configureNFS;
            writeUpdateFile;
            # Removed because this puts a file in the /tftpboot directory
            # which doesn't exist and there isn't a tftp server installed
            #configureDefaultiPXEfile;
            if [ "$bluseralreadyexists" = "1" ]; then
                echo "";
                echo "  Upgrade complete!";
                echo "";
            else
                echo "";
                echo "  Setup complete!";
                echo "";
                echo "";
                echo "  You still need to setup this node in the fog management ";
                echo "  portal.  You will need the username and password listed";
                echo "  below.";
                echo "";
                echo "  Management Server URL:  ";
                echo "      http://${snmysqlhost}/fog";
                echo "";
                echo "  You will need this, write this down!";
                echo "      Username:  ${storageftpuser}";
                echo "      Password:  ${storageftppass}";
                echo ""
                echo "";
            fi
        else
            # Normal installation
            configureUsers;
            configureMySql;
            backupReports;
            configureHttpd;
            dots "Backuping up database"
            if [ -d "$backupPath/fog_web_${version}.BACKUP" ]; then
                wget --no-check-certificate -O $backupPath/fogDBbackups/fog_sql_${version}_$(date +"%Y%m%d_%I%M%S").sql >/dev/null 2>&1
            fi
            errorStat $?
            if [ "$installtype" == "N" -a -z "$dbupdate" ]; then
                echo
                echo "  You still need to install/update your database schema.";
                echo "  This can be done by opening a web browser and going to:";
                echo "";
                echo "      http://${ipaddress}/fog/management";
                echo "";
                read -p "  Press [Enter] key when database is updated/installed.";
                echo
            elif [ "$installtype" == "N" -a "$dbupdate" == "yes" ]; then
                dots "Updating Database"
                wget -O - --post-data="confirm=1" --no-proxy http://127.0.0.1/${webroot}management/index.php?node=schemaupdater >/dev/null 2>&1 ||
                wget -O - --post-data="confirm=1" --no-proxy http://${ipaddress}/${webroot}management/index.php?node=schemaupdater >/dev/null 2>&1
                errorStat $?
            fi
            #restoreReports;
            configureStorage;
            configureDHCP;
            configureTFTPandPXE;
            configureFTP;
            #configureSudo;
            configureSnapins;
            configureUDPCast;
            installInitScript;
            installFOGServices;
            installUtils;
            configureFOGService;
            #sendInstallationNotice;
            configureNFS;
            writeUpdateFile;
            linkOptFogDir;
            echo "";
            echo "  Setup complete!";
            echo "";
            echo "  You can now login to the FOG Management Portal using";
            echo "  the information listed below.  The login information";
            echo "  is only if this is the first install.";
            echo "";
            echo "  This can be done by opening a web browser and going to:";
            echo "";
            echo "      http://${ipaddress}/fog/management";
            echo ""
            echo "      Default User:";
            echo "             Username: fog";
            echo "             Password: password";
            echo "";
        fi
        ;;
        [nN]*)
        echo "  FOG installer exited by user request."
        exit 1;
        ;;
        *)
        echo "";
        echo "  Sorry, answer not recognized."
        echo "";
        ;;
    esac
done
