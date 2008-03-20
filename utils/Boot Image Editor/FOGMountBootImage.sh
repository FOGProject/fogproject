#!/bin/sh

echo ;
echo "This script is only tested on Fedora";
echo ;

mkdir /tmp/tmpMnt >/dev/null 2>&1;

echo -n "Coping boot image...";
cp /tftpboot/fog/images/init.gz /tmp/init.gz >/dev/null 2>&1;
echo  "Done";

echo -n "Unzipping image...";
cd /tmp
gunzip init.gz >/dev/null 2>&1;
echo "Done";

echo -n "Mounting boot image...";
mount -o loop /tmp/init /tmp/tmpMnt; 
echo "Done";

echo "Launching nautilus...";

nautilus --no-desktop /tmp/tmpMnt &

sleep 3;

echo "Nautilus should be up soon...";
echo ;
echo "Press enter when you are done modifing the boot image to replace it with the original file from the tftp directory.";
echo ;
echo "Press Enter when you are ready.";
echo ;
read whatever;
echo -n "Unmounting image...";
umount /tmp/tmpMnt;
echo "Done";

echo -n "GZipping image...";
gzip -9 init;
echo "Done";

echo -n "Coping file...";
cp -f init.gz /tftpboot/fog/images/init.gz;
echo "Done";

