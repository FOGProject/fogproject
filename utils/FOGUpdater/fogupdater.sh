#!/bin/bash
. ../../lib/common/utils.sh
[[ -z $downloaddir ]] && downloaddir="/opt/"
echo " ***************************************************************"
echo " *                         ** Notice **                        *"
echo " ***************************************************************"
echo " *                                                             *"
echo " * Your FOG server may go offline during this upgrade process! *"
echo " *                                                             *"
echo " ***************************************************************"
dots "Checking latest version"
if [[ -z $trunk ]]; then
    latest=$(wget --no-check-certificate -qO - --post-data="stable" https://fogproject.org/version/index.php)
    latest=$(echo $latest | $(pwd)/jq32 .stable)
else
    latest=$(wget --no-check-certificate -qO - --post-data="dev" https://fogproject.org/version/index.php)
    latest=$(echo $latest | $(pwd)/jq32 .dev)
fi
[[ -z $latest ]] && errorStat 1
echo "Done"
latest=${latest//\"}
echo " * Latest FOG Version: $latest"
if [[ -z $trunk ]]; then
    [[ -z $updatemirrors ]] && updatemirrors="http://internap.dl.sourceforge.net/sourceforge/freeghost/ http://voxel.dl.sourceforge.net/sourceforge/freeghost/ http://kent.dl.sourceforge.net/sourceforge/freeghost/ http://heanet.dl.sourceforge.net/sourceforge/freeghost/"
    [[ $version == $latest ]] && handleError " * You are already up to date!" 0
    echo "   You are not running the latest stable version"
    echo " * Preparing to upgrade"
    echo " * Attempting to download latest stable to $downloaddir"
else
    [[ -z $updatemirrors ]] && updatemirrors="https://github.com/fogproject/fogproject/tarball"
    [[ $version == $latest ]] && handleError " * You are already up to date!" 0
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
if [[ ! -z $trunk ]]; then
    extract=$(basename $fileplace)
    extract=$(echo $extract | sed 's/\.tar\.gz//g')
    mkdir $downloaddir/$extract >/dev/null 2>&1
fi
tar -xzf $fileplace -C $downloaddir/$extract >/dev/null 2>&1
errorStat $?
cd $cwd
echo "Done"
echo
[[ -z $trunk ]] && cd $downloaddir/fog_$latest/bin || cd $downloaddir/fog_$latest/*/bin
./installfog.sh -y
