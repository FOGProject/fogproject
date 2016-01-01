#!/bin/bash
. ../../lib/common/functions.sh
if [[ ! -f /opt/fog/.fogsettings ]]; then
    echo "No fog settings found so nothing to update"
    exit 1
fi
. /opt/fog/.fogsettings
if [[ ! -d $docroot ]]; then
    echo "No web folder found"
    exit 1
fi
if [[ ! -d ${docroot}${webroot} ]]; then
    echo "No fog web directory found"
    exit 1
fi
if [[ ! -f ${docroot}${webroot}config.class.php && ! -f ${docroot}${webroot}Config.class.php ]]; then
    echo "No config file found"
    exit 1
fi
if [[ -f ${docroot}${webroot}config.class.php ]]; then
    configpath=${docroot}${webroot}config.class.php
else
    configpath=${docroot}${webroot}Config.class.php
fi
OS=$(uname -s)
case $OS in
    [Ll][Ii][Nn][Uu][Xx])
        if [[ -z $downloaddir ]]; then
            downloaddir="/opt/"
        fi
        if [[ -z $updatemirrors ]]; then
            updatemirrors="http://internap.dl.sourceforge.net/sourceforge/freeghost/ http://voxel.dl.sourceforge.net/sourceforge/freeghost/ http://kent.dl.sourceforge.net/sourceforge/freeghost/ http://heanet.dl.sourceforge.net/sourceforge/freeghost/"
        fi
        clear
        displayBanner
        echo
        echo
        display_center "***************************************************************"
        display_center "*                         ** Notice **                        *"
        display_center "***************************************************************"
        display_center "*                                                             *"
        display_center "* Your FOG server may go offline during this upgrade process! *"
        display_center "*                                                             *"
        display_center "***************************************************************"
        echo
        echo
        sleep 5
        dots " * Checking running version"
        version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' $configpath | tr -d '[[:space:]]')"
        if [[ -z $version ]]; then
            false
            errorStat $?
        fi
        echo "Done"
        echo
        echo
        display_center "Current FOG Version: $version"
        echo
        echo
        dots " * Checking latest version"
        if [[ -z $trunk ]]; then
            latest=$(wget --no-check-certificate -qO - --post-data="stable" https://fogproject.org/version/index.php)
        else
            latest=$(wget --no-check-certificate -qO - --post-data="dev" https://fogproject.org/version/index.php)
        fi
        if [[ -z $latest ]]; then
            false
            errorStat $?
        fi
        echo "Done"
        echo
        echo
        display_center "Latest FOG Version: $latest"
        echo
        echo
        if [[ -z $trunk ]]; then
            if [[ $(trim $version) == $(trim $latest) ]]; then
                echo "You are already up to date"
                exit 0
            fi
            display_center "You are not running the latest stable version"
            echo
            echo
            sleep 3
            display_center "Preparing to upgrade"
            display_center "Attempting to download latest stable to $downloaddir"
            echo
            echo
            sleep 3
            downloaded=""
            for url in $updatemirrors; do
                echo " * Trying mirror $url"
                dots " * Attempting Download"
                fileplace=$downloaddir/fog_${latest}.tar.gz
                filedownload=$url/fog_${latest}.tar.gz
                wget --no-check-certificate -qO $fileplace $filedownload
                if [[ ! $? -eq 0 ]]; then
                    echo "Failed"
                    continue
                fi
                echo "Done"
                downloaded=1
                break
            done
            if [[ -z $downloaded ]]; then
                echo "Failed to download current file"
                exit 1
            fi
            echo
            echo
            display_center "Extracting package $fileplace"
            echo
            echo
            dots " * Extracting"
            cwd=$(pwd)
            cd $download
            tar -xzf $fileplace >/dev/null 2>&1
            errorStat $?
            echo "Done"
            echo
            dots
            cd $downloaddir/fog_${latest}/bin
            ./installfog.sh -y
        fi
        ;;
    *)
        echo "We do not currently support installation on non-linux operating systems"
        exit 1
        ;;
esac
