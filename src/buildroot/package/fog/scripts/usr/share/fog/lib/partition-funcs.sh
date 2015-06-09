#!/bin/bash

#
# These functions are for dealing with resizing of partitions.
# They currently work for MBR and Extended partition tables.
# THE DO NOT WORK FOR GPT.
# It is assumed that at most 1 extended partition will exist,
# with any number of logical partitions.
# Requires the sfdisk tool.
# Assumes that sfdisk's "unit: sectors" means 512 byte sectors.
#

# $1 is the name of the disk drive
# $2 is name of file to save to.
saveSfdiskPartitions() {
	sfdisk -d "$1" 2>/dev/null > "$2";
}

# $1 is the name of the disk drive
# $2 is name of file to load from.
applySfdiskPartitions() {
	sfdisk "$1" &>/dev/null < "$2";
}


# $1 is the name of the disk drive
hasExtendedPartition() {
	sfdisk -d "$1" 2>/dev/null | egrep 'Id= [5f]' | wc -l
}

# $1 is the name of the partition device (e.g. /dev/sda3)
saveEBR() {
	local part_number=`echo $1 | sed -r 's/^[^0-9]+//g'`;
	local disk=`echo $part | sed -r 's/[0-9]+$//g'`;
	# Leaving the grep in place due to forward slashes
	local part_type=`sfdisk -d "$disk" 2>/dev/null | grep ^$1 | awk -F[,=] '{print $6+0}'`;
	if [ "$part_type" == "5" -o "$part_type" == "f" -o "$part_number" -ge 5 ]; then
		dots "Saving EBR for ($1)";
		dd if=$1 of=/tmp/d1p${part_number}.ebr bs=512 count=1 &> /dev/null;
		echo "Done";
	fi
}

# $1 is the name of the partition device (e.g. /dev/sda3)
restoreEBR() {
	local part_number=`echo $1 | sed -r 's/^[^0-9]+//g'`;
	local disk=`echo $part | sed -r 's/[0-9]+$//g'`;
	# Leaving the grep in place due to forward slashes
	local part_type=`sfdisk -d "$disk" 2>/dev/null | grep ^$1 | awk -F[,=] '{print $6+0}'`;
	if [ "$part_type" == "5" -o "$part_type" == "f" -o "$part_number" -ge 5 ]; then
		if [ -e "/tmp/d1p${part_number}.ebr" ]; then
			dots "Restoring EBR for ($1)";
			dd of=$1 if=/tmp/d1p${part_number}.ebr bs=512 count=1 &> /dev/null;
			echo "Done";
		fi
	fi
}

# $1 is the location of the file to store uuids in
# $2 is the partition device name
saveSwapUUID() {
	local uuid=`blkid $2 | awk -F\" '{print $2}'`;
	if [ -n "$uuid" ]; then
		echo " * Saving UUID ($uuid) for ($2)";
		echo "$2 $uuid" >> "$1";
	fi
}

# $1 is the location of the file uuids are stored in
# $2 is the partition device name
makeSwapSystem() {
	local uuid="";
	local option="";
	local disk=`echo $2 | sed -r 's/[0-9]+$//g'`;
	local part_type="0";
	local hasgpt=`hasGPT $disk`;
	if [ "$hasgpt" == "1" ]; then
		# don't have a good way to test, as ubuntu installer
		# doesn't set the GPT partition type correctly.
		# so, only format as swap if uuid exists.
		if [ -e "$1" ]; then
			uuid=`egrep "^$2" "$1" | awk '{print $2;}'`;
		fi
		if [ -n "$uuid" ]; then
			part_type="82";
		fi
	else
		# Leaving the grep in place due to forward slashes
		part_type=`sfdisk -d "$disk" 2>/dev/null | grep ^$2 | awk -F[,=] '{print $6+0}'`;
	fi
	if [ "$part_type" == "82" ]; then
		if [ -e "$1" ]; then
			uuid=`egrep "^$2" "$1" | awk '{print $2;}'`;
		fi
		if [ -n "$uuid" ]; then
			option="-U $uuid";
		fi
		echo " * Restoring swap partition: $2";
		mkswap $option $2 &> /dev/null;
	fi
}

# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
resizePartition() {
	local part="$1";
	local size="$2";
	local disk=`echo $part | sed -r 's/[0-9]+$//g'`;
	local imagePath="$3";
	local tmp_file="/tmp/sfdisk.$$";
	local tmp_file2="/tmp/sfdisk2.$$";
	saveSfdiskPartitions $disk $tmp_file;
	processSfdisk $tmp_file resize $part $size > $tmp_file2;
	if [ "$?" == "0" ]; then
		applySfdiskPartitions $disk $tmp_file2;
	fi
	udevadm --settle &>/dev/null;
	blockdev --rereadpt $disk &>/dev/null;
	mv $tmp_file $imagePath/d1.original.partitions &>/dev/null;
	mv $tmp_file2 $imagePath/d1.minimum.partitions &>/dev/null;
}

# $1 is the disk device (e.g. /dev/sda)
# $2 is the name of the original sfdisk -d output file used as a template
# $3 is the : separated list of fixed size partitions (e.g. 1:2)
#	 swap partitions are automatically added.  Empty string is
#	 ok.
fillDiskWithPartitions() {
	local disk_size=`blockdev --getsize64 "$1" | awk '{printf("%d\n",$1/1024);}'`;
	local tmp_file2="/tmp/sfdisk2.$$";
	processSfdisk "$2" filldisk "$1" "$disk_size" "$3" > $tmp_file2;
	if [ "$?" == "0" ]; then
		applySfdiskPartitions $1 $tmp_file2;
	fi
	udevadm --settle &>/dev/null;
	blockdev --rereadpt $1 &>/dev/null;
	rm -f $tmp_file2;
}


#
#  processSfdisk() processes the output of sfdisk -d
#  and creates a new sfdisk -d like output, applying
#  the requested action.  Read below to see the actions
#
# $1 the name of a file that is the output of sfdisk -d
# $2 is the action "resize|other?"
# $3 is the first parameter
# $4 is the second parameter
# ...
#
# actions:
# processSfdisk foo.sfdisk resize /dev/sda1 100000
#	foo.sfdisk = sfdisk -d output
#	resize = action
#	/dev/sda1 = partition to modify
#	100000 = 1024 byte blocks size to make it
#	output: new sfdisk -d like output
#
# processSfdisk foo.sfdisk move /dev/sda1 100000
#	foo.sfdisk = sfdisk -d output
#	move = action
#	/dev/sda1 = partition to modify
#	100000 = 1024 byte blocks size to move it to
#	output: new sfdisk -d like output
#
# processSfdisk foo.sfdisk filldisk /dev/sda 100000 1:3:6
#	foo.sfdisk = sfdisk -d output
#	filldisk = action
#	/dev/sda = disk to modify
#	100000 = 1024 byte blocks size of disk
#	1:3:6 = partition numbers that are fixed in size, : separated
#	output: new sfdisk -d like output
#
# example file data	   
# /dev/sda1 : start=	 2048, size=   204800, Id= 7, bootable
# /dev/sda2 : start=   206848, size= 50573312, Id= 7
# /dev/sda3 : start= 50780160, size=	 2048, Id=83
# /dev/sda4 : start= 50784254, size= 16322562, Id= 5
# /dev/sda5 : start= 50784256, size=  7811072, Id=83
# /dev/sda6 : start= 58597376, size=  8509440, Id=82
#
processSfdisk() {
	local data="$1";
	local minstart=`awk -F'[ ,]+' '/start/{if ($4) print $4}' $data | sort -n | head -1`;
	if [[ "$osid" == +([1-2]) ]]; then
		local chunksize="512";
		local minstart="63";
	else
		if [ "$minstart" == "63" ]; then
			local chunksize="512";
		else
			local chunksize="2048";
		fi
	fi
	local awkArgs="-v CHUNK_SIZE=$chunksize -v MIN_START=$minstart";
	awkArgs="${awkArgs} -v action=$2 -v target=$3 -v sizePos=$4";
	if [ -n "$5" ]; then
		awkArgs="${awkArgs} -v fixedList=$5"
	fi
	# process with external awk script
	/usr/share/fog/lib/procsfdisk.awk $awkArgs $data;
}

#
# GPT Functions below
#

# $1 : device name of drive
getPartitionTableType() {
	local disk="$1";
	local mbr=`gdisk -l $disk | awk '/^\ *MBR:/{print $2}'`;
	local gpt=`gdisk -l $disk | awk '/^\ *GPT:/{print $2}'`;
	local type="";
	local mbrtype="";
	local gpttype="";
	if [ "$mbr" == "present" ]; then
		mbrtype="MBR";
	elif [ "$mbr" == "hybrid" ]; then
		mbrtype="HYBRID";
	elif [ "$mbr" == "protective" ]; then
		mbrtype="";
	elif [ "$mbr" == "not" ]; then
		mbrtype="";
	fi

	if [ "$gpt" == "present" ]; then
		gpttype="GPT";
	elif [ "$gpt" == "not" ]; then
		gpttype="";
	fi
	if [ -n "$gpttype" -a -n "$mbrtype" ]; then
		type="${gpttype}-${mbrtype}"
	elif [ -n "$gpttype" ]; then
		type="${gpttype}"
	elif [ -n "$mbrtype" ]; then
		type="${gpttype}"
	else
		type="";
	fi
	echo "$type"
}

# $1 : device name of drive
hasHybridMBR() {
	local disk="$1";
	local mbr=`gdisk -l $disk | awk '/^\ *MBR:/{print $2}'`;
	if [ "$mbr" == "hybrid" ]; then
		echo "1";
	else
		echo "0";
	fi
}

# $1 : device name of drive
hasGPT() {
	local disk="$1";
	local gpt=`gdisk -l $disk | awk '/^\ *GPT:/{print $2}'`;
	if [ "$gpt" == "present" ]; then
		echo "1";
	elif [ "$gpt" == "not" ]; then
		echo "0";
	fi
}

# Local Variables:
# indent-tabs-mode: t
# sh-basic-offset: 4
# sh-indentation: 4
# tab-width: 4
# End:
