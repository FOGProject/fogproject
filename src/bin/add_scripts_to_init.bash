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

mountpoint="tmp-loop-mount"
scriptdir="${FOGROOT}/src/buildroot/package/fog/scripts/bin"
libdir="${FOGROOT}/src/buildroot/package/fog/scripts/usr/share/fog/lib"
initdir="${FOGROOT}/packages/web/service/ipxe"
scripts="fog.upload fog.download"
libs="funcs.sh partition-funcs.sh"

if [ ! -d "$scriptdir" -o ! -d "$libdir" -o ! -d "$initdir" ]; then
    echo "Misconfigured fogroot: $FOGROOT"
    exit 1
fi

pushd $scriptdir >> /dev/null
scripts=`ls -1`
popd >> /dev/null
pushd $libdir >> /dev/null
libs=`ls -1`
popd >> /dev/null

if [ ! -d $mountpoint ]; then
    mkdir $mountpoint
fi

xzcat ${initdir}/init.xz > init-64
mount -o loop init-64 $mountpoint

for s in $scripts; do
    if [ -r ${scriptdir}/${s} ]; then
        cp -f ${scriptdir}/${s} $mountpoint/bin/${s}
    else
        echo "${scriptdir}/${s} isn't there."
    fi
done
for l in $libs; do
    if [ -r ${libdir}/${l} ]; then
        cp -f ${libdir}/${l} $mountpoint/usr/share/fog/lib/${l}
    else
        echo "${libdir}/${l} isn't there."
    fi
done

rm -f $mountpoint/bin/*~
rm -f $mountpoint/usr/share/fog/lib/*~
umount $mountpoint
xz -C crc32 -z -c init-64 > ${initdir}/init.xz
rm init-64


# repeat for 32bit

xzcat ${initdir}/init_32.xz > init-32
mount -o loop init-32 $mountpoint

for s in $scripts; do
    if [ -r ${scriptdir}/${s} ]; then
        cp -f ${scriptdir}/${s} $mountpoint/bin/${s}
    else
        echo "${scriptdir}/${s} isn't there."
    fi
done
for l in $libs; do
    if [ -r ${libdir}/${l} ]; then
        cp -f ${libdir}/${l} $mountpoint/usr/share/fog/lib/${l}
    else
        echo "${libdir}/${l} isn't there."
    fi
done

rm -f $mountpoint/bin/*~
rm -f $mountpoint/usr/share/fog/lib/*~
umount $mountpoint
xz -C crc32 -z -c init-32 > ${initdir}/init_32.xz
rm init-32

if [ -d $mountpoint ]; then
    rmdir $mountpoint
fi

exit 0
