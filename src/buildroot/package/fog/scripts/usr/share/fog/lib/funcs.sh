#!/bin/sh
. /usr/share/fog/lib/partition-funcs.sh;
REG_LOCAL_MACHINE_XP="/ntfs/WINDOWS/system32/config/system"
REG_LOCAL_MACHINE_7="/ntfs/Windows/System32/config/SYSTEM"
REG_HOSTNAME_KEY1_XP="\ControlSet001\Services\Tcpip\Parameters\NV Hostname"
REG_HOSTNAME_KEY2_XP="\ControlSet001\Services\Tcpip\Parameters\Hostname"
REG_HOSTNAME_KEY3_XP="\ControlSet001\Control\ComputerName\ComputerName\ComputerName"
REG_HOSTNAME_KEY4_XP="\ControlSet001\services\Tcpip\Parameters\NV Hostname"
REG_HOSTNAME_KEY5_XP="\ControlSet001\services\Tcpip\Parameters\Hostname"
REG_HOSTNAME_KEY1_7="\ControlSet001\Services\Tcpip\Parameters\NV Hostname"
REG_HOSTNAME_KEY2_7="\ControlSet001\Services\Tcpip\Parameters\Hostname"
REG_HOSTNAME_KEY3_7="\ControlSet001\Control\ComputerName\ComputerName\ComputerName"
REG_HOSTNAME_KEY4_7="\ControlSet001\services\Tcpip\Parameters\NV Hostname"
REG_HOSTNAME_KEY5_7="\ControlSet001\services\Tcpip\Parameters\Hostname"
REG_HOSTNAME_MOUNTED_DEVICES_7="\MountedDevices"
#If a sub shell gets involked and we lose kernel vars this will reimport them
$(for var in $(cat /proc/cmdline); do echo export $var | grep =; done)
dots() 
{
    max=45
    if [ -n "$1" ]; then
        len=${#1}
        if [ "$len" -gt "$max" ]; then
            echo -n " * ${1:0:max}"
        else
            echo -n " * ${1}"
            n=$((max - len))
            for ((x = 0; x < n; x++)); do
              printf %s .
            done
        fi
    fi
}
# $1 is the drive
enableWriteCache() 
{
	if [ -n "$1" ]; then
		dots "Checking write caching status on HDD";
		wcache=$(hdparm -i $1 2>/dev/null|sed '/WriteCache=/!d; s/^.*WriteCache=//; s/ .*$//');
		if [ "$wcache" == "enabled" ]; then
			echo "OK";
		elif [ "$wcache" == "disabled" ]; then
			hdparm -W 1 $1 2&1 >/dev/null;
			echo "Enabled";
		else
			echo "Unknown status $wcache";
		fi
	fi
}
# $1 is the partition
expandPartition() 
{
	if [ ! -n "$1" ]; then
		echo " * No partition";
		return;
	fi
	if [ -n "$fixed_size_partitions" ]; then
		local partNum=`echo $1 | sed -r 's/^[^0-9]+//g'`;
		is_fixed=`echo "$fixed_size_partitions" | egrep ':'${partNum}':|^'${partNum}':|:'${partNum}'$' | wc -l`;
		if [ "$is_fixed" == "1" ]; then
			dots "Not expanding ($1) fixed size";
			echo "Done";
			return;
		fi
	fi
	do_reset_flag="0"
	fstype=`fsTypeSetting $1`;
	if [ "$fstype" == "ntfs" ]; then
		dots "Resizing ntfs volume ($1)";
		ntfsresize $1 -f -b -P &>/dev/null << EOFNTFSRESTORE
Y
EOFNTFSRESTORE
		do_reset_flag="1"
	elif [ "$fstype" == "extfs" ]; then
		dots "Resizing $fstype volume ($1)";
		e2fsck -fp $1 &>/dev/null;
		resize2fs $1 &>/dev/null;
		e2fsck -fp $1 &>/dev/null; # prevent fsck at first boot of restored system
	else
		dots "Not expanding ($1 $fstype)";
	fi
	echo "Done";
	if [ "$do_reset_flag" == "1" ]; then
		resetFlag $1;
	fi
}
# $1 is the partition
fsTypeSetting()
{
	fstype=`blkid -po udev $1 | grep FS_TYPE | awk -F'=' '{print $2}'`;
	is_ext=`echo "$fstype" | egrep '^ext[234]$' | wc -l`;
	if [ "x${is_ext}" == "x1" ]; then
		echo "extfs";
	elif [ "$fstype" == "ntfs" ]; then
		echo "ntfs";
	elif [ "$fstype" == "vfat" ]; then
		echo "fat";
	elif [ "$fstype" == "hfsplus" ]; then
		echo "hfsp";
	elif [ "$fstype" == "swap" ]; then
		echo "swap";
	else
		echo "imager";
	fi
}
# $1 is the partition
# Returns the size in bytes.
getPartSize()
{
	block_part_tot=`blockdev --getsz $1`;
	part_block_size=`blockdev --getpbsz $1`;
	partsize=`awk "BEGIN{print $block_part_tot * $part_block_size}"`;
	echo $partsize;
}
# Returns the size in bytes.
getDiskSize()
{
	block_disk_tot=`blockdev --getsz $hd`;
	disk_block_size=`blockdev --getpbsz $hd`;
	disksize=`awk "BEGIN{print $block_disk_tot * $disk_block_size}"`;
	echo $disksize;
}
validResizeOS()
{
	#Valid OSID's are 1 XP, 2 Vista, 5 Win 7, 6 Win 8, 7 Win 8.1, and 50 Linux
	if [ "$osid" != "1" -a "$osid" != "2" -a "$osid" != "5" -a "$osid" != "6" -a "$osid" != "7" -a "$osid" != "50" ]; then
		handleError " * Invalid operating system id: $osname ($osid)!";
	fi
}
# $1 is the partition
# $2 is the fstypes file location
shrinkPartition() 
{
	if [ ! -n "$1" ]; then
		echo " * No partition";
		return;
	fi
	fstype=`fsTypeSetting $1`;
	addToFstypesFile "$2" "$1" "$fstype";
	if [ -n "$fixed_size_partitions" ]; then
		local partNum=`echo $1 | sed -r 's/^[^0-9]+//g'`;
		is_fixed=`echo "$fixed_size_partitions" | egrep ':'${partNum}':|^'${partNum}':|:'${partNum}'$' | wc -l`;
		if [ "$is_fixed" == "1" ]; then
			dots "Not shrinking ($1) fixed size";
			echo "Done";
			return;
		fi
	fi
	if [ "$fstype" == "ntfs" ]; then
		ntfsresizetest="ntfsresize -f -i -P $1";
		size=`$ntfsresizetest | grep "You might resize" | cut -d" " -f5`;
		if [ ! -n "$size" ]; then
			tmpoutput=`$ntfsresizetest`;
			handleError " * Fatal Error, Unable to determine possible ntfs size\n * To better help you debug we will run the ntfs resize\n\t but this time with full output, please wait!\n\t$tmpoutput";
		fi
		sizentfsresize=`expr $size '/' 1000`;
		sizentfsresize=`expr $sizentfsresize '+' 300000`;
		sizentfsresize=`expr $sizentfsresize '*' 1$percent '/' 100`;
		sizefd=`expr $sizentfsresize '*' 103 '/' 100`;
		echo "";
		echo " * Possible resize partition size: $sizentfsresize k";
		dots "Running resize test $1";
		tmpSuc=`ntfsresize -f -n -s ${sizentfsresize}k $1 << EOFNTFS
Y
EOFNTFS`
		success=`echo $tmpSuc | grep "ended successfully"`;
		too_big=`echo $tmpSuc | grep "bigger than the device size"`;
		ok_size=`echo $tmpSuc | grep "volume size is already OK"`;
		echo "Done";
		if [ -n "$too_big" ]; then
			echo " * Not resizing filesystem $1 (part too small)";
			do_resizefs=0;
			do_resizepart=0;
		elif [ -n "$ok_size" ]; then
			echo " * Not resizing filesystem $1 (already OK)";
			do_resizefs=0;
			do_resizepart=1;
		elif [ ! -n "$success" ]; then
			handleWarning "Resize test failed!\n $tmpSuc";
			do_resizefs=0;
			do_resizepart=0;
		else
			echo " * Resize test was successful";
			do_resizefs=1;
			do_resizepart=1;
		fi
		if [ "$do_resizefs" == "1" ]; then
			dots "Resizing filesystem";
			ntfsresize -f -s ${sizentfsresize}k $1 &>/dev/null << FORCEY
y
FORCEY
			echo "Done";
			resetFlag $1;
		fi
		if [ "$do_resizepart" == "1" ]; then
			dots "Resizing partition $1";
			if [ "$osid" == "1" -o "$osid" == "2" ]; then
				resizePartition "$1" "$sizentfsresize"
				if [ "$osid" == "2" ]; then
					correctVistaMBR $hd;
				fi
			elif [ "$win7partcnt" == "1" ]; then
				win7part1start=`parted -s $hd u kB print | sed -e '/^.1/!d' -e 's/^ [0-9]*[ ]*//' -e 's/kB  .*//' -e 's/\..*$//'`;
				if [ "$win7part1start" == "" ]; then
					handleError "Unable to determine disk start location.";
				fi
				adjustedfdsize=`expr $sizefd '+' $win7part1start`;
				resizePartition "$1" "$adjustedfdsize"
			elif [ "$win7partcnt" == "2" ]; then
				win7part2start=`parted -s $hd u kB print | sed -e '/^.2/!d' -e 's/^ [0-9]*[ ]*//' -e 's/kB  .*//' -e 's/\..*$//'`;
				if [ "$win7part2start" == "" ]; then
					handleError "Unable to determine disk start location.";
				fi
				adjustedfdsize=`expr $sizefd '+' $win7part2start`;
				resizePartition "$1" "$adjustedfdsize"
			else
				adjustedfdsize=`expr $sizefd '+' 1048576`;
				resizePartition "$1" "$adjustedfdsize"
			fi
			runPartprobe $hd;
		fi
	elif [ "$fstype" == "extfs" ]; then
		dots "Checking $fstype volume ($1)";
		e2fsck -fp $1 &>/dev/null;
		echo "Done";
		extminsizenum=`resize2fs -P $1 2>/dev/null | awk -F': ' '{print $2}'`;
		block_size=`dumpe2fs -h $1 2>/dev/null | grep "^Block size:" | awk '{print $3}'`;
		size=`expr $extminsizenum '*' $block_size`;
		sizeextresize=`expr $size '*' 103 '/' 100 '/' 1024`;
		echo "";
		echo " * Possible resize partition size: $sizeextresize k";
		sleep 3;
		dots "Shrinking $fstype volume ($1)";
		resize2fs $1 -M &>/dev/null;
		echo "Done";
		dots "Shrinking $1 partition";
		resizePartition "$1" "$sizeextresize"
		echo "Done";
		runPartprobe $hd;
		dots "Resizing $fstype volume ($1)";
		resize2fs $1 &>/dev/null;
		e2fsck -fp $1 &>/dev/null; # prevent fsck at first boot after uploaded system
	else
		dots "Not shrinking ($1 $fstype)";
	fi
	echo "Done";
}
# $1 is the part
resetFlag() 
{
	if [ -n "$1" ]; then
		fstype=`blkid -po udev $1 | grep FS_TYPE | awk -F'=' '{print $2}'`;
		if [ "$fstype" == "ntfs" ]; then
			dots "Clearing ntfs flag";
			ntfsfix -b -d $1 &>/dev/null;
			echo "Done";
		fi
	fi
}
# $1 is the disk
countNtfs() 
{
	local count=0;
	local fstype="";
	local part="";
	local parts="";
	if [ -n "$1" ]; then
		parts=`fogpartinfo --list-parts $1 2>/dev/null`;
		for part in $parts; do
			fstype=`fsTypeSetting $part`;
			if [ "$fstype" == "ntfs" ]; then
				count=`expr $count '+' 1`;
			fi
		done
	fi
	echo $count;
}
# $1 is the disk
countExtfs()
{
	local count=0;
	local fstype="";
	local part="";
	local parts="";
	if [ -n "$1" ]; then
		parts=`fogpartinfo --list-parts $1 2>/dev/null`;
		for part in $parts; do
			fstype=`fsTypeSetting $part`;
			if [ "$fstype" == "extfs" ]; then
				count=`expr $count '+' 1`;
			fi
		done
	fi
	echo $count;
}

setupDNS()
{
	echo "nameserver $1" > /etc/resolv.conf
}

# $1 = Source File
# $2 = Target
writeImage() 
{
	if [ "$imgFormat" = "1" ] || [ "$imgLegacy" = "1" ]; then
		#partimage
		mkfifo /tmp/pigz1;
		cat $1 > /tmp/pigz1 &
		gunzip -d -c < /tmp/pigz1 | partimage restore $2 stdin -f3 -b 2>/tmp/status.fog;
		rm /tmp/pigz1;
	else 
		# partclone
		mkfifo /tmp/pigz1;
		cat $1 > /tmp/pigz1 &
		gunzip -d -c < /tmp/pigz1 | partclone.restore --ignore_crc -O $2 -N -f 1 2>/tmp/status.fog;
		rm /tmp/pigz1;
	fi
}

# $1 = Target
writeImageMultiCast() 
{
	if [ "$imgFormat" = "1" ] || [ "$imgLegacy" = "1" ]; then
		#partimage
		udp-receiver --nokbd --portbase $port --mcast-rdv-address $storageip 2>/dev/null | gunzip -d -c | partimage -f3 -b restore $hd stdin 2>/tmp/status.fog;
	else 
		# partclone
		udp-receiver --nokbd --portbase $port --mcast-rdv-address $storageip 2>/dev/null | gunzip -d -c | partclone.restore --ignore_crc -O $1 -N -f 1 2>/tmp/status.fog;
	fi
}

changeHostname()
{
	if [ "$hostearly" == "1" ]; then
		dots "Changing hostname";
		mkdir /ntfs &>/dev/null
		ntfs-3g -o force,rw $part /ntfs &> /tmp/ntfs-mount-output
		regfile="";
		key1="";
		key2="";
		if [ "$osid" = "5" ] || [ "$osid" = "6" ] || [ "$osid" = "7" ]; then
			regfile=$REG_LOCAL_MACHINE_7
			key1=$REG_HOSTNAME_KEY1_7
			key2=$REG_HOSTNAME_KEY2_7
			key3=$REG_HOSTNAME_KEY3_7
			key4=$REG_HOSTNAME_KEY4_7
			key5=$REG_HOSTNAME_KEY5_7
		elif [ "$osid" = "1" ];	then
			regfile=$REG_LOCAL_MACHINE_XP
			key1=$REG_HOSTNAME_KEY1_XP
			key2=$REG_HOSTNAME_KEY2_XP
			key3=$REG_HOSTNAME_KEY3_XP
			key4=$REG_HOSTNAME_KEY4_XP
			key5=$REG_HOSTNAME_KEY5_XP
		fi
		reged -e $regfile &>/dev/null <<EOFREG
ed $key1
$hostname
ed $key2
$hostname
ed $key3
$hostname
ed $key4
$hostname
ed $key5
$hostname
q
y
EOFREG
		umount /ntfs &> /dev/null
		echo "Done";
	fi
}

fixWin7boot()
{
	dots "Backing up and replacing BCD";
	mkdir /bcdstore &>/dev/null;
	ntfs-3g -o force,rw $1 /bcdstore &> /tmp/bcdstore-mount-output;
	mv /bcdstore/Boot/BCD /bcdstore/Boot/BCD.bak;
	cp /usr/share/fog/BCD /bcdstore/Boot/BCD;
	umount /bcdstore;
	echo "Done";
}

clearMountedDevices()
{
	mkdir /ntfs &>/dev/null
	if [ "$osid" = "5" ] || [ "$osid" = "6" ] || [ "$osid" = "7" ]; then
		dots "Clearing mounted devices";
		ntfs-3g -o force,rw $win7sys /ntfs
		reged -e "$REG_LOCAL_MACHINE_7" &>/dev/null  <<EOFMOUNT
cd $REG_HOSTNAME_MOUNTED_DEVICES_7
delallv
q
y
EOFMOUNT
		echo "Done";		
		umount /ntfs
	fi
}
# $1 is the device name of the windows system partition
removePageFile()
{
	local part="$1";
	local fstype="";
	if [ "$part" != "" ]; then
		fstype=`fsTypeSetting $part`
	fi
	if [ "$fstype" != "ntfs" ]; then
		echo " * No ntfs file system to remove page file"
	elif [ "$osid" == "1" -o "$osid" == "2" -o "$osid" == "5" -o "$osid" == "6" -o "$osid" == "7" -o "$osid" == "50" ]; then
		if [ "$ignorepg" == "1" ]; then
			dots "Mounting device";
			mkdir /ntfs &>/dev/null;
			ntfs-3g -o force,rw $part /ntfs;
			if [ "$?" == "0" ]; then
				echo "Done";
				dots "Removing page file";
				rm -f "/ntfs/pagefile.sys";
				echo "Done";
				dots "Removing hibernate file";
				rm -f "/ntfs/hiberfil.sys";
				echo "Done";
				umount /ntfs;
			else
				echo "Failed";
			fi
		fi
	fi
}

doInventory()
{
	sysman=`dmidecode -s system-manufacturer`;
	sysproduct=`dmidecode -s system-product-name`;
	sysversion=`dmidecode -s system-version`;
	sysserial=`dmidecode -s system-serial-number`;
	systype=`dmidecode -t 3 | grep Type:`;
	biosversion=`dmidecode -s bios-version`;
	biosvendor=`dmidecode -s bios-vendor`;
	biosdate=`dmidecode -s bios-release-date`;
	mbman=`dmidecode -s baseboard-manufacturer`;
	mbproductname=`dmidecode -s baseboard-product-name`;
	mbversion=`dmidecode -s baseboard-version`;
	mbserial=`dmidecode -s baseboard-serial-number`;
	mbasset=`dmidecode -s baseboard-asset-tag`;
	cpuman=`dmidecode -s processor-manufacturer`;
	cpuversion=`dmidecode -s processor-version`;
	cpucurrent=`dmidecode -t 4 | grep 'Current Speed:' | head -n1`;
	cpumax=`dmidecode -t 4 | grep 'Max Speed:' | head -n1`;
	mem=`cat /proc/meminfo | grep MemTotal`;
	hdinfo=`hdparm -i $hd | grep Model=`;
	caseman=`dmidecode -s chassis-manufacturer`;
	casever=`dmidecode -s chassis-version`;
	caseserial=`dmidecode -s chassis-serial-number`;
	casesasset=`dmidecode -s chassis-asset-tag`;
	sysman64=`echo $sysman | base64`;
	sysproduct64=`echo $sysproduct | base64`;
	sysversion64=`echo $sysversion | base64`;
	sysserial64=`echo $sysserial | base64`;
	systype64=`echo $systype | base64`;
	biosversion64=`echo $biosversion | base64`;
	biosvendor64=`echo $biosvendor | base64`;
	biosdate64=`echo $biosdate | base64`;
	mbman64=`echo $mbman | base64`;
	mbproductname64=`echo $mbproductname | base64`;
	mbversion64=`echo $mbversion | base64`;
	mbserial64=`echo $mbserial | base64`;
	mbasset64=`echo $mbasset | base64`;
	cpuman64=`echo $cpuman | base64`;
	cpuversion64=`echo $cpuversion | base64`;
	cpucurrent64=`echo $cpucurrent | base64`;
	cpumax64=`echo $cpumax | base64`;
	mem64=`echo $mem | base64`;
	hdinfo64=`echo $hdinfo | base64`;
	caseman64=`echo $caseman | base64`;
	casever64=`echo $casever | base64`;
	caseserial64=`echo $caseserial | base64`;
	casesasset64=`echo $casesasset | base64`;	
}

determineOS()
{
	if [ -n "$1" ]; then
		if [ "$1" = "1" ]; then
			osname="Windows XP";
			mbrfile="/usr/share/fog/mbr/xp.mbr";
		elif [ "$1" = "2" ]; then
			osname="Windows Vista";
			mbrfile="/usr/share/fog/mbr/vista.mbr";
		elif [ "$1" = "3" ]; then
			osname="Windows 98";
			mbrfile="";
		elif [ "$1" = "4" ]; then
			osname="Windows (Other)";
			mbrfile="";
		elif [ "$1" = "5" ]; then
			osname="Windows 7";
			mbrfile="/usr/share/fog/mbr/win7.mbr";
			defaultpart2start="105906176B";
		elif [ "$1" = "6" ]; then
			osname="Windows 8";
			mbrfile="/usr/share/fog/mbr/win8.mbr";
			defaultpart2start="368050176B";
		elif [ "$1" = "7" ]; then
			osname="Windows 8.1";
			mbrfile="/usr/share/fog/mbr/win8.mbr";
			defaultpart2start="368050176B";
		elif [ "$1" = "50" ]; then
			osname="Linux";
			mbrfile="";
		elif [ "$1" = "99" ]; then
			osname="Other OS";
			mbrfile="";
		else
			handleError " * Invalid operating system id ($1)!";
		fi
	else
		handleError " * Unable to determine operating system type!";
	fi
}

clearScreen()
{
	if [ "$mode" != "debug" ]; then
		for i in $(seq 0 99); do
			echo "";
		done
	fi
}

sec2String()
{
	if [ $1 -gt 60 ]; then
		if [ $1 -gt 3600 ]; then
			if [ $1 -gt 216000 ]; then
				val=$(expr $1 "/" 216000);
				echo -n "$val days";
			else
				val=$(expr $1 "/" 3600);
				echo -n "$val hours";
			fi
		else
			val=$(expr $1 "/" 60);
			echo -n "$val min";
		fi
	else
		echo -n "$1 sec";
	fi
}

getSAMLoc()
{
	poss="/ntfs/WINDOWS/system32/config/SAM /ntfs/Windows/System32/config/SAM";
	for pth in $poss; do
		if [ -f $pth ]; then
			sam=$pth;
			return 0;
		fi
	done
	return 0;
}

getHardDisk()
{
	if [ -n "${fdrive}" ]; then
		hd="${fdrive}";
		return 0;
	else
		hd="";
		for i in `fogpartinfo --list-devices 2>/dev/null`; do
			hd="$i";
			return 0;
		done
		# Lets check and see if the partition shows up in /proc/partitions		
		for i in hda hdb hdc hdd hde hdf sda sdb sdc sdd sde sdf; do
			strData=`cat /proc/partitions | grep $i 2>/dev/null`;
			if [ -n "$strData" ]; then
				hd="/dev/$i";
				return 0;
			fi 
		done
		for i in hda hdb hdc hdd hde hdf sda sdb sdc sdd sde sdf; do		
			strData=`head -1 /dev/$i 2>/dev/null`;
			if [ -n "$strData" ]; then
				hd="/dev/$i";
				return 0;
			fi 
		done	
		# Failed, probably because there is no partition on the device
		for i in hda hdb hdc hdd hde hdf sda sdb sdc sdd sde sdf; do		
			strData=`fdisk -l | grep /dev/$i 2>/dev/null`;
			if [ -n "$strData" ]; then
				hd="/dev/$i";
				return 0;
			fi 
		done
	fi
	return 1;
}

correctVistaMBR()
{
	dots "Correcting Vista MBR";
	dd if=$1 of=/tmp.mbr count=1 bs=512 &>/dev/null
	xxd /tmp.mbr /tmp.mbr.txt &>/dev/null
	rm /tmp.mbr &>/dev/null
	fogmbrfix /tmp.mbr.txt /tmp.mbr.fix.txt &>/dev/null
	rm /tmp.mbr.txt &>/dev/null
	xxd -r /tmp.mbr.fix.txt /mbr.mbr &>/dev/null
	rm /tmp.mbr.fix.txt &>/dev/null
	dd if=/mbr.mbr of=$1 count=1 bs=512 &>/dev/null
	echo "Done";
}

displayBanner()
{
	echo "  +--------------------------------------------------------------------------+";
	echo "  |                                                                          |";
	echo "  |                       ..#######:.    ..,#,..     .::##::.                | ";
	echo "  |                  .:######          .:;####:......;#;..                   |";
	echo "  |                  ...##...        ...##;,;##::::.##...                    |";
	echo "  |                     ,#          ...##.....##:::##     ..::               |";
	echo "  |                     ##    .::###,,##.   . ##.::#.:######::.              |";
	echo "  |                  ...##:::###::....#. ..  .#...#. #...#:::.               |";
	echo "  |                  ..:####:..    ..##......##::##  ..  #                   |";
	echo "  |                      #  .      ...##:,;##;:::#: ... ##..                 |";
	echo "  |                     .#  .       .:;####;::::.##:::;#:..                  |";
	echo "  |                      #                     ..:;###..                     |";
	echo "  |                                                                          |";
	echo "  |                       Free Computer Imaging Solution                     |";
	echo "  |                               Version 1.2.0                              |";
	echo "  |                                                                          |";
	echo "  +--------------------------------------------------------------------------+";
	echo "  | Created by:                                                              |";
	echo "  |              Chuck Syperski                                              |";
	echo "  |              Jian Zhang                                                  |";
	echo "  |              Peter Gilchrist                                             |";
	echo "  |              Tom Elliott                                                 |";
	echo "  | Released under GPL Version 3                                             |";
	echo "  +--------------------------------------------------------------------------+";
	echo "";
	echo "";
}

handleError()
{
    echo "";
	echo " #############################################################################";
	echo " #                                                                           #";	
	echo " #                     An error has been detected!                           #";
	echo " #                                                                           #";	
	echo " #############################################################################";
	echo "";
	echo "";
	echo -e " $1";
	echo "";
	echo "";
	echo " #############################################################################";
	echo " #                                                                           #";	
	echo " #                  Computer will reboot in 1 minute.                        #";
	echo " #                                                                           #";	
	echo " #############################################################################";	
	sleep 60;
	exit 0;
}

handleWarning()
{
    echo "";
	echo " #############################################################################";
	echo " #                                                                           #";	
	echo " #                     A warning has been detected!                           #";
	echo " #                                                                           #";	
	echo " #############################################################################";
	echo "";
	echo "";
	echo -e " $1";
	echo "";
	echo "";
	echo " #############################################################################";
	echo " #                                                                           #";	
	echo " #                  Will continue in 1 minute.                               #";
	echo " #                                                                           #";	
	echo " #############################################################################";	
	sleep 60;
}

# $1 is the drive
runPartprobe()
{
	partprobe $1 &> /dev/null;
}

debugCommand()
{
	if [ "$mode" == "debug" ]; then
		echo $1 >> /tmp/cmdlist;
	fi
}

# uploadFormat
# Description:
# Tells the system what format to upload in, whether split or not.
# Expects first arguments to be the number of Cores.
# Expects second argument to be the fifo to send to.
# Expects part of the filename in the case of resizable
#    will append 000 001 002 automatically
uploadFormat()
{
	if [ ! -n "$1" ]; then
		echo "Missing Cores";
		return;
	elif [ ! -n "$2" ]; then
		echo "Missing file in file out";
		return;
	elif [ ! -n "$3" ]; then
		echo "Missing file name to store";
		return;
	fi
	if [ "$imgFormat" == "2" ]; then
		pigz -p $1 $PIGZ_COMP < $2 | split -a 3 -d -b 200m - ${3}. &
	else
		if [ "$imgType" == "n" ]; then
			pigz -p $1 $PIGZ_COMP < $2 > ${3}.000 &
		else
			pigz -p $1 $PIGZ_COMP < $2 > $3 &
		fi
	fi
}

# Thank you, fractal13 Code Base
#
# Save enough MBR and embedding area to capture all of GRUB
# Strategy is to capture EVERYTHING before the first partition.
# Then, leave a marker that this is a GRUB MBR for restoration.
# We could get away with less storage, but more details are required
# to parse the information correctly.  It would make the process
# more complicated.
#
# See the discussion about the diskboot.img and the sector list
# here: http://banane-krumm.de/bootloader/grub2.html
#
# Expects:
# the device name (e.g. /dev/sda) as the first parameter,
# the disk number (e.g. 1) as the second parameter
# the directory to store images in (e.g. /image/dev/xyz) as the third parameter
# 
saveGRUB()
{
	local disk="$1";
	local disk_number="$2";
	local imagePath="$3";
	local first=`sfdisk -d "${disk}" 2>/dev/null | \
		awk -F: '{print $2;}' | \
		awk -F, '{print $1;}' | \
		grep start= | \
		awk -F= 'BEGIN{start=1000000000;}{if($2 > 0 && $2 < start){start=$2;}}END{printf("%d\n", start);}'`;
	local count=$first;
	dd if="$disk" of="$imagePath/d${disk_number}.mbr" count="${count}" bs=512 &>/dev/null;
	touch "$imagePath/d${disk_number}.has_grub";
}

# Checks for the existence of the grub embedding area in the image directory.
# Echos 1 for true, and 0 for false.
#
# Expects:
# the device name (e.g. /dev/sda) as the first parameter,
# the disk number (e.g. 1) as the second parameter
# the directory images stored in (e.g. /image/xyz) as the third parameter
hasGRUB()
{
	local disk="$1";
	local disk_number="$2";
	local imagePath="$3";
	if [ -e "$imagePath/d${disk_number}.has_grub" ]; then
		echo "1";
	else
		echo "0";
	fi
}

# Restore the grub boot record and all of the embedding area data
# necessary for grub2.
#
# Expects:
# the device name (e.g. /dev/sda) as the first parameter,
# the disk number (e.g. 1) as the second parameter
# the directory images stored in (e.g. /image/xyz) as the third parameter
restoreGRUB()
{
	local disk="$1";
	local disk_number="$2";
	local imagePath="$3";
	local tmpMBR="${imagePath}/d${disk_number}.mbr";
	local count=`du -B 512 "${tmpMBR}" | awk '{print $1;}'`;
	dd if="${tmpMBR}" of="${disk}" bs=512 count="${count}" &>/dev/null;
}

debugPause()
{
	if [ "$mode" == "debug" ]; then
		echo 'Press [Enter] key to continue.';
		read -p "$*";
	fi
}
