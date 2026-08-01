#!/bin/bash
# GH-314: `.` resolves a relative path against the CALLER's cwd, not this
# script's location, so this only ever worked if you cd'd into the util's own
# directory first. Resolve against our own path so the utils can be invoked by
# absolute path from anywhere -- which is the point of installing them to
# $fogprogramdir.
. "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/functions.sh"
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
        # GH-953: the /var/www/ fallback for osid 2 that used to live here is
        # gone -- see the note in lib/ubuntu/config.sh. It could only have
        # disagreed with the running install anyway: docroot comes from the
        # .fogsettings sourced above, and this script already refuses to run if
        # that path does not exist.
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
