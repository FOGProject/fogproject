#!/bin/bash

FOGROOT="$1"

if [ `whoami` != "root" ]; then
    echo "Must run as root."
    exit 1
fi

if [ ! -d "$FOGROOT" ]; then
    echo "usage: $0 fogrootdir"
    exit 1
fi

initdir="${FOGROOT}/packages/web/service/ipxe"
installdir="/var/www/fog/service/ipxe"

if [ ! -d "$initdir" ]; then
    echo "Misconfigured fogroot: $FOGROOT"
    exit 1
fi

if [ ! -d "$installdir" ]; then
    echo "Misconfigured installdir: $installdir"
    exit 1
fi

cp ${initdir}/init.xz ${installdir}/init.xz
cp ${initdir}/init_32.xz ${installdir}/init_32.xz

ls -l ${installdir}

exit 0
