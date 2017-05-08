#!/bin/bash
. ../../lib/common/functions.sh
handleError() {
    echo "$1"
    exit $2
}
[[ ! -f /opt/fog/.fogsettings ]] && handleError "    No fog settings found so nothing to work from" 1
. /opt/fog/.fogsettings
[[ ! -d $docroot ]] && handleError "    No web folder found" 2
case $osid in
    1|2)
        if [[ -z $docroot ]]; then
            docroot="/var/www/html/"
            webdirdest="${docroot}fog/"
        elif [[ $docroot != *'fog'* ]]; then
            webdirdest="${docroot}fog/"
        else
            webdirdest="${docroot}/"
        fi
        if [[ $osid -eq 2 ]]; then
            if [[ $docroot == /var/www/html/ && ! -d $docroot ]]; then
                docroot="/var/www/"
                webdirdest="${docroot}fog/"
            fi
        fi
        ;;
    3)
        if [[ -z $docroot ]]; then
            docroot="/var/www/html/"
            webdirdest="${docroot}fog/"
        elif [[ $docroot != *'fog'* ]]; then
            webdirdest="${docroot}fog/"
        else
            webdirdest="${docroot}/"
        fi
        ;;
esac
[[ ! -d $webdirdest ]] && handleError "    No fog web directory found" 3
[[ -f ${webdirdest}lib/fog/system.class.php ]] && configpath=${webdirdest}lib/fog/system.class.php || configpath=${webdirdest}lib/fog/System.clss.php
[[ ! -f $configpath ]] && handleError "    No config file found" 4
OS=$(uname -s)
[[ $OS =~ ^[^Ll][^Ii][^Nn][^Uu][^Xx]$ ]] && handleError "    We only support these utilities on Linux OS's" 6
clear
displayBanner
dots "Checking running version"
version=$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' $configpath | tr -d '[[:space:]]')
[[ -z $version ]] && (echo "Failed" && handleError "Could not find version of FOG" 7)
echo "Done"
echo " * Running FOG Version: $version"
