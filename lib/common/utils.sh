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
# GH-850: find the install before reading its settings. .fogsettings lives at
# $fogprogramdir/.fogsettings, so the base path has to come from somewhere else
# -- /etc/fog/fog.conf, written by the installer. /opt/fog when it is absent.
[[ -z $fogprogramdir && -r /etc/fog/fog.conf ]] && . /etc/fog/fog.conf
[[ -z $fogprogramdir ]] && fogprogramdir="/opt/fog"
fogprogramdir="${fogprogramdir%/}"
[[ ! -f $fogprogramdir/.fogsettings ]] && handleError "    No fog settings found so nothing to work from" 1
resolvedfogprogramdir="$fogprogramdir"
. $fogprogramdir/.fogsettings
# .fogsettings records fogprogramdir but does not control it -- a stale line in
# there must not point us at a different tree than the one we just read.
fogprogramdir="$resolvedfogprogramdir"
[[ ! -d ${WEB_docroot} ]] && handleError "    No web folder found" 2
case ${FOG_os_id} in
    1|2)
        if [[ -z ${WEB_docroot} ]]; then
            WEB_docroot="/var/www/html/"
            webdirdest="${WEB_docroot}fog/"
        elif [[ ${WEB_docroot} != *'fog'* ]]; then
            webdirdest="${WEB_docroot}fog/"
        else
            webdirdest="${WEB_docroot}/"
        fi
        # GH-953: the /var/www/ fallback for osid 2 that used to live here is
        # gone -- see the note in lib/ubuntu/config.sh. It could only have
        # disagreed with the running install anyway: docroot comes from the
        # .fogsettings sourced above, and line 19 already refuses to run if it
        # does not exist.
        ;;
    3)
        if [[ -z ${WEB_docroot} ]]; then
            WEB_docroot="/var/www/html/"
            webdirdest="${WEB_docroot}fog/"
        elif [[ ${WEB_docroot} != *'fog'* ]]; then
            webdirdest="${WEB_docroot}fog/"
        else
            webdirdest="${WEB_docroot}/"
        fi
        ;;
esac
[[ ! -d $webdirdest ]] && handleError "    No fog web directory found" 3
# Reads the tree ALREADY INSTALLED, which on an upgrade is whatever the
# previous release laid down -- so both spellings have to be tried. Core
# became PSR-4 on working-1.6 and the version file moved from
# lib/fog/system.class.php to src/Base/System.php; a server installed before
# that still has the old one, and this is the file that tells us so.
configpath=${webdirdest}src/Base/System.php
[[ ! -f $configpath ]] && configpath=${webdirdest}lib/fog/system.class.php
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
