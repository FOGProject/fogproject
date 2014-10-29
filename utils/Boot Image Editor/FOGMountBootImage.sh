#!/bin/sh
mkdir /tmp/tmpMnt >/dev/null 2>&1;
currentuser=`whoami`;
if [ "$currentuser" != "root" ]
then
	echo "Warning!!!!!!!!"
	echo " This script isn't running as root!";
	echo "Warning!!!!!!!!"
	sleep 5;
fi
if [ -d "/var/www/fog" ]; then
	$webroot = "/var/www/fog";
elif [ -d "/var/www/html/fog" ]; then
	$webroot = "/var/www/html/fog";
fi
echo -n "Copying boot image...";
cp $webroot/service/ipxe/init.xz /tmp/init.xz >/dev/null 2>&1;
echo  "Done";
echo -n "Uncompressing image...";
cd /tmp
xz --decompress init.xz >/dev/null 2>&1;
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
echo -n "Compressing image...";
xz -C crc32 -z -c init > init.xz;
echo "Done";
echo -n "Copying file...";
cp -f init.xz $webroot/service/ipxe/init.xz;
echo "Done";
