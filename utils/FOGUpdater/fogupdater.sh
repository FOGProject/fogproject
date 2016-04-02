#!/bin/bash
. ../../lib/common/functions.sh
handleError() {
    echo "$1"
    exit $2
}
[[ ! -f /opt/fog/.fogsettings ]] && handleError "   No fog settings found so nothing to update" 1
. /opt/fog/.fogsettings
[[ ! -d $docroot ]] && handleError "   No web folder found" 2
case $osid in
    1)
        if [[ -z $docroot ]]; then
            docroot="/var/www/html/"
            webdirdest="${docroot}fog/"
        elif [[ $docroot != *'fog'* ]]; then
            webdirdest="${docroot}fog/"
        else
            webdirdest="${docroot}/"
        fi
        ;;
    2)
        if [[ -z $docroot ]]; then
            docroot="/var/www/html/"
            webdirdest="${docroot}fog/"
        elif [[ "$docroot" != *'fog'* ]]; then
            webdirdest="${docroot}fog/"
        else
            webdirdest="${docroot}/"
        fi
        if [[ $docroot == /var/www/html/ && ! -d $docroot ]]; then
            docroot="/var/www/"
            webdirdest="${docroot}fog/"
        fi
        ;;
    3)
        if [ -z "$docroot" ]; then
            docroot="/srv/http/"
            webdirdest="${docroot}fog/"
        elif [[ "$docroot" != *'fog'* ]]; then
            webdirdest="${docroot}fog/"
        else
            webdirdest="${docroot}/"
        fi
        ;;
esac
[[ ! -d $webdirdest ]] && handleError "   No fog web directory found" 3
[[ -f ${webdirdest}lib/fog/system.class.php ]] && configpath=${webdirdest}lib/fog/system.class.php || configpath=${webdirdest}lib/fog/System.class.php
[[ ! -f $configpath ]] && handleError "   No config file found" 4
OS=$(uname -s)
case $OS in
    [Ll][Ii][Nn][Uu][Xx])
        [[ -z $downloaddir ]] && downloaddir="/opt/"
        clear
        displayBanner
        echo "   ***************************************************************"
        echo "   *                         ** Notice **                        *"
        echo "   ***************************************************************"
        echo "   *                                                             *"
        echo "   * Your FOG server may go offline during this upgrade process! *"
        echo "   *                                                             *"
        echo "   ***************************************************************"
        dots "Checking running version"
        version=$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' $configpath | tr -d '[[:space:]]')
        [[ -z $version ]] && errorStat 1
        echo "Done"
        echo "Current FOG Version: $version"
        dots "Checking latest version"
        [[ -z $trunk ]] && latest=$(wget --no-check-certificate -qO - --post-data="stable" https://fogproject.org/version/index.php) || latest=$(wget --no-check-certificate -qO - --post-data="dev" https://fogproject.org/version/index.php)
        [[ -z $latest ]] && errorStat 1
        echo "Done"
        echo "Latest FOG Version: $latest"
        if [[ -z $trunk ]]; then
            [[ -z $updatemirrors ]] && updatemirrors="http://internap.dl.sourceforge.net/sourceforge/freeghost/ http://voxel.dl.sourceforge.net/sourceforge/freeghost/ http://kent.dl.sourceforge.net/sourceforge/freeghost/ http://heanet.dl.sourceforge.net/sourceforge/freeghost/"
            [[ $(echo $version) == $(echo $latest) ]] && handleError " * You are already up to date!" 0
            echo "   You are not running the latest stable version"
            echo " * Preparing to upgrade"
            echo " * Attempting to download latest stable to $downloaddir"
        else
            [[ -z $updatemirrors ]] && updatemirrors="https://github.com/fogproject/fogproject/tarball"
            [[ $(echo $version) == $(echo $latest) ]] && handleError " * You are already up to date!" 0
            echo "   You are not running the latest dev version"
            echo " * Preparing to upgrade"
            echo " * Attempting to download latest dev version to $downloaddir"
        fi
        downloaded=""
        for url in $updatemirrors; do
            echo " * Trying mirror $url"
            dots "Attempting Download"
            fileplace="$downloaddir/fog_${latest}.tar.gz"
            [[ -z $trunk ]] && filedownload="fog_${latest}.tar.gz" || filedownload='dev-branch'
            wget --no-check-certificate -qO $fileplace $url/$filedownload >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    downloaded=1
                    break
                    ;;
                *)
                    echo "Failed"
                    continue
                    ;;
            esac
        done
        [[ -z $downloaded ]] && handleError "   Failed to download current file" 5
        echo
        echo
        echo " * Extracting package $fileplace"
        echo
        echo
        dots "Extracting"
        cwd=$(pwd)
        cd $download
        extract=$(basename $fileplace)
        extract=$(echo $extract | sed -i 's/\.tar\.gz//g')
        tar -xzf $fileplace -C $downloaddir/$extract >/dev/null 2>&1
        errorStat $?
        cd $cwd
        echo "Done"
        echo
        cd $downloaddir/fog_$latest/bin
        ./installfog.sh -y
        ;;
    *)
        handleError "   We only support installation on Linux OS's" 6
        ;;
esac
