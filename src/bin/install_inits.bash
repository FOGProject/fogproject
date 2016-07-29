#!/bin/bash
handleError() {
    echo "$1"
    echo $2
}
[[ $(whoami) != "root" ]] && handleError "Must run as root" 1
FOGROOT="$1"
[[ ! -d $FOGROOT ]] && handleError "Usage: $0 fogrootdir" 1
initdir="$FOGROOT/packages/web/service/ipxe"
[[ ! -d $initdir ]] && handleError "Cannot find init path" 1
installdir="/var/www/fog/service/ipxe"
[[ ! -d $installdir ]] && installdir="/var/www/html/fog/service/ipxe"
[[ ! -d $installdir ]] && installdir="/srv/http/fog/service/ipxe"
[[ ! -d $installdir ]] && handleError "Cannot find install directory" 1
cp ${initdir}/init.xz ${installdir}/init.xz
cp ${initdir}/init_32.xz ${installdir}/init_32.xz
exit 0
