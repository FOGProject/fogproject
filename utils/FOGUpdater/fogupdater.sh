#!/bin/bash
. ../../lib/common/functions.sh
handleError() {
    echo "$1"
    exit $2
}
[[ ! -f /opt/fog/.fogsettings ]] && handleError "   No fog settings found so nothing to update" 1
. /opt/fog/.fogsettings
[[ ! -d $docroot ]] && handleError "   No web folder found" 2
[[ ! -d ${docroot}${webroot} ]] && handleError "   No fog web directory found" 3
[[ ! -f ${docroot}${webroot}config.class.php && ! -f ${docroot}${webroot}Config.class.php ]] && handleError "   No config file found" 4
[[ -f ${docroot}${webroot}config.class.php ]] && configpath=${docroot}${webroot} || configpath=${docroot}${webroot}Config.class.php
OS=$(uname -s)
case $OS in
    [Ll][Ii][Nn][Uu][Xx])
        [[ -z $downloaddir ]] && downloaddir="/opt/"
        [[ -z $updatemirrors ]] && updatemirrors="http://internap.dl.sourceforge.net/sourceforge/freeghost/ http://voxel.dl.sourceforge.net/sourceforge/freeghost/ http://kent.dl.sourceforge.net/sourceforge/freeghost/ http://heanet.dl.sourceforge.net/sourceforge/freeghost/"
        clear
        displayBanner
        echo
        echo
        echo "   ***************************************************************"
        echo "   *                         ** Notice **                        *"
        echo "   ***************************************************************"
        echo "   *                                                             *"
        echo "   * Your FOG server may go offline during this upgrade process! *"
        echo "   *                                                             *"
        echo "   ***************************************************************"
        echo
        echo
        sleep 5
        dots " * Checking running version"
        version=$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' $configpath | tr -d '[[:space:]]')
        [[ -z $version ]] && errorStat 1
        echo "Done"
        echo
        echo
        echo " * Current FOG Version: $version"
        echo
        echo
        dots " * Checking latest version"
        [[ -z $trunk ]] && latest=$(wget --no-check-certificate -qO - --post-data="stable" https://fogproject.org/version/index.php) || latest=$(wget --no-check-certificate -qO - --post-data="dev" https://fogproject.org/version/index.php)
        [[ -z $latest ]] && errorStat 1
        echo "Done"
        echo
        echo
        echo " * Latest FOG Version: $latest"
        echo
        echo
        if [[ -z $trunk ]]; then
            [[ $(trim $version) == $(trim $latest) ]] && handleError " * You are already up to date!" 0
            echo "   You are not running the latest stable version"
            echo
            echo
            sleep 3
            echo " * Preparing to upgrade"
            echo " * Attempting to download latest stable to $downloaddir"
            echo
            echo
            sleep 3
            downloaded=""
            for url in $updatemirrors; do
                echo " * Trying mirror $url"
                dots " * Attempting Download"
                fileplace=$downloaddir/fog_${latest}.tar.gz
                filedownload=$url/fog_${latest}.tar.gz
                wget --no-check-certificate -qO $fileplace $filedownload >/dev/null 2>&1
                case $? in
                    0)
                        echo "Done"
                        dowloaded=1
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
            dots " * Extracting"
            cwd=$(pwd)
            cd $download
            tar -xzf $fileplace >/dev/null 2>&1
            errorStat $?
            cd $cwd
            echo "Done"
            echo
            cd $downloaddir/fog_$latest/bin
            ./installfog.sh -y
        fi
        ;;
    *)
        handleError "   We only support installation on Linux OS's" 6
        ;;
esac
