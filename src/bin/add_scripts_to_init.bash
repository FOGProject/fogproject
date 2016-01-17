#!/bin/bash
scriptTransfer() {
    for s in $scripts; do
        [[ -r $scriptdir/$s ]] && cp -f $scriptdir/$s $mountpoint/bin/$s || echo "$scriptdir/$s is not there"
    done
    for l in $libs; do
        [[ -r $libdir/$l ]] && cp -f $libdir/$l $mountpoint/usr/share/fog/lib/$l || echo "$libdir/$l is not there"
    done
}
handleError() {
    echo "$1"
    echo $2
}
[[ $(whoami) != "root" ]] && handleError "Must run as root" 1
FOGROOT="$1"
[[ ! -d $FOGROOT ]] && handleError "Usage: $0 fogrootdir" 1
mountpoint="tmp-loop-mount"
scriptdir="$FOGROOT/src/buildroot/package/fog/scripts/bin"
[[ ! -d $scriptdir ]] && handleError "Cannot find script location" 1
libdir="$FOGROOT/src/buildroot/package/fog/scripts/usr/share/fog/lib"
[[ ! -d $libdir ]] && handleError "Cannot find lib location" 1
initdir="/var/www/fog/service/ipxe"
[[ ! -d $initdir ]] && initdir="/var/www/html/fog/service/ipxe"
[[ ! -d $initdir ]] && initdir="/srv/http/fog/service/ipxe"
[[ ! -d $initdir ]] && handleError "Could not locate install directory" 1
scripts="fog.upload fog.download"
libs="funcs.sh partition-funcs.sh procsfdisk.awk"
pushd $scriptdir >> /dev/null
scripts=$(ls -1)
popd >> /dev/null
pushd $libdir >> /dev/null
libs=$(ls -1)
popd >> /dev/null
[[ ! -d $mountpoint ]] && mkdir -p $mountpoint >/dev/null 2>&1
[[ ! -d $mountpoint ]] && handleError "Failed to create mount point" 1
echo "uncompressing 64-bit image"
xzcat $initdir/init.xz > init-64
mount -o loop init-64 $mountpoint
scriptTransfer
rm -f $mountpoint/bin/*~ >/dev/null 2>&1
rm -f $mountpoint/usr/share/fog/lib/*~ >/dev/null 2>&1
umount $mountpoint >/dev/null 2>&1
echo "compressing 64-bit image"
xz -C crc32 -z -c init-64 > $initdir/init.xz
rm init-64 >/dev/null 2>&1
# repeat for 32bit
echo "uncompressing 32-bit image"
xzcat $initdir/init_32.xz > init-32
mount -o loop init-32 $mountpoint
scriptTransfer
rm -f $mountpoint/bin/*~ >/dev/null 2>&1
rm -f $mountpoint/usr/share/fog/lib/*~ >/dev/null 2>&1
umount $mountpoint >/dev/null 2>&1
echo "compressing 32-bit image"
xz -C crc32 -z -c init-32 > ${initdir}/init_32.xz
rm init-32
[[ -d $mountpoint ]] && rmdir $mountpoint >/dev/null 2>&1
exit 0
