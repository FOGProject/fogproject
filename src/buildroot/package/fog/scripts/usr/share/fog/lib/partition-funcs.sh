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
    sfdisk -d "$1" 2>/dev/null > "$2"
    if [[ $? != 0 ]]; then
        majorDebugEcho "sfdisk failed in saveSfdiskPartitions"
    fi
}

# $1 is the name of the disk drive
# $2 is name of file to load from.
applySfdiskPartitions() {
    sfdisk "$1" &>/dev/null < "$2"
    if [[ $? != 0 ]]; then
        majorDebugEcho "sfdisk failed in applySfdiskPartitions"
    fi
}

# $1 is the name of the disk drive
# $2 is name of file to load from.
restoreSfdiskPartitions() {
    applySfdiskPartitions "$1" "$2"
    fdisk "$1" &>/dev/null << EOFRESTOREPART
w
EOFRESTOREPART
    if [[ $? != 0 ]]; then
        majorDebugEcho "fdisk failed in restoreSfdiskPartitions"
    fi
}


# $1 is the name of the disk drive
hasExtendedPartition() {
    local disk="$1"
    sfdisk -d "$disk" 2>/dev/null | egrep '(Id|type)=\ *[5f]' | wc -l
    if [[ $? != 0 ]]; then
        majorDebugEcho "sfdisk failed in hasExtendedPartition"
    fi
}

# $1 is the name of the partition device (e.g. /dev/sda3)
partitionHasEBR() {
    local part="$1"
    local partNum=$(getPartitionNumber $part)
    local disk=$(getDiskFromPartition $part)
    local part_type=$(sfdisk -d $disk 2>/dev/null | grep ^$part | awk -F[,=] '{print $6}')
    if [[ $part_type == 5 || $part_type == f || $partNum -ge 5 ]]; then
        echo "1"
    else
        echo "0"
    fi
}

# $1 is the name of the partition device (e.g. /dev/sda3)
# $2 is the name of the file to save to (e.g. /net/dev/foo/d1p4.ebr)
saveEBR() {
    local part="$1"
    local dstfilename="$2"
    local disk=$(getDiskFromPartition $part)
    local table_type=$(getPartitionTableType $disk)
    if [[ $table_type != MBR ]]; then
        return
    fi
    # Leaving the grep in place due to forward slashes
    local part_type=$(sfdisk -d $disk 2>/dev/null | grep ^$part | awk -F[,=] '{print $6}')
    if [[ $(partitionHasEBR $part) -gt 0 ]]; then
        dots "Saving EBR for ($part)"
        dd if="$part" of="$dstfilename" bs=512 count=1 &> /dev/null
        echo "Done"
    fi
}

# $1 = DriveName  (e.g. /dev/sdb)
# $2 = DriveNumber  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
saveAllEBRs() {
    local disk="$1"
    local driveNum="$2"
    local imagePath="$3"
    for part in $(getPartitions $disk); do
        partNum=$(getPartitionNumber $part)
        ebrfilename=$(EBRFileName $imagePath $driveNum $partNum)
        saveEBR "$part" "$ebrfilename"
    done
}

# $1 is the name of the partition device (e.g. /dev/sda3)
# $2 is the name of the file to restore from (e.g. /net/foo/d1p4.ebr)
restoreEBR() {
    local part="$1"
    local srcfilename="$2"
    local disk=$(getDiskFromPartition $part)
    local table_type=$(getPartitionTableType $disk)
    if [[ $table_type != MBR ]]; then
        return
    fi
    # Leaving the grep in place due to forward slashes
    local part_type=$(sfdisk -d $disk 2>/dev/null | grep ^$part | awk -F[,=] '{print $6}')
    if [[ $(partitionHasEBR $part) -gt 0 ]]; then
        if [[ -e $srcfilename ]]; then
            dots "Restoring EBR for ($part)"
            dd of=$part if=$srcfilename bs=512 count=1 &> /dev/null
            echo "Done"
        fi
    fi
}

# $1 = DriveName  (e.g. /dev/sdb)
# $2 = DriveNumber  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
# $4 = ImagePartitionType  (e.g. all, mbr, 1, 2, 3, etc.)
restoreAllEBRs() {
    local disk="$1"
    local driveNum="$2"
    local imagePath="$3"
    local imgPartitionType="$4"
    for part in $(getPartitions $disk); do
        partNum=$(getPartitionNumber $part)
        if [[ $imgPartitionType == all || $imgPartitionType == $partNum ]]; then
            local ebrfilename=$(EBRFileName $imagePath $driveNum $partNum)
            restoreEBR "$part" "$ebrfilename"
        fi
    done
    runPartprobe "$disk"
}

# $1 is the name of the partition device (e.g. /dev/sda3)
partitionIsSwap() {
    local part="$1"
    local fstype=$(fsTypeSetting $part)
    if [[ $fstype == swap ]]; then
        echo "1"
    else
        echo "0"
    fi
}

# $1 is the location of the file to store uuids in
# $2 is the partition device name
saveSwapUUID() {
    local is_swap=$(partitionIsSwap $2)
    if [[ $is_swap != 0 ]]; then
        local uuid=$(blkid -s UUID $2 | cut -d\" -f2)
        if [ -n "$uuid" ]; then
            echo " * Saving UUID ($uuid) for ($2)"
            echo "$2 $uuid" >> "$1"
        fi
    fi
}

# Linux swap partition strategy:
#
# Upload:
#
# In "n" mode, the empty swapUUIDFileName is created first.  Then as each
# partition is saved, if it is swap then saveSwapUUID is called.
# In "mps" and "mpa" mode, savePartition is called for each partition.
# savePartition then calles saveSwapUUID if the partition is swap.
#
# When uploading an image, the swapUUIDFileName (e.g. /images/foo/d1.original.swapuuids)
# is created.  For $imgPartitionType == "all", all swap partition UUIDs are saved.
# For $imgPartitionType == "$partnum", the partition's UUID is saved, if it is a swap partition.
# For all others, the swapUUIDFileName will not exist, or will be empty.
#
#
# Download:
#
# When downloading an image, makeAllSwapSystems will be called.
# In "n" mode this is done for those images without special configurations,
#   after normal partition restoration.
# In "mps" mode this is always done
# In "mpa" mode this is always done, for all disks.
# makeAllSwapSystems will determine using
# $imagePartitionType == "all" or == "$partnum" whether to
# process the swapUUIDFileName contents.  For each matching partition,
# mkswap is used, and the UUID is set appropriately.
#
# Relevant functions:
# swapUUIDFileName ImagePath DriveNumber
#   echos the standardized name for the UUID filename
# partitionIsSwap PartitionName
#   echos 1 or 0 if fsTypeSetting says partition is or is not a swap partition.
# makeSwapSystem SwapUUIDFileName PartitionName
#   if it finds partition in UUID file, then calls mkswap
# makeAllSwapSystems DriveName DriveNumber ImagePath ImagePartitionType
#   checks ImagePartitionType for a match before calling makeSwapSystem
# saveSwapUUID SwapUUIDFileName PartitionName
#   checks if paritionIsSwap, if so, obtains UUID and saves it
# saveAllSwapUUIDs DriveName DriveNumber ImagePath
#   checks all partitions if partitionIsSwap, calles saveSwapUUID
# savePartition:
#   calls saveSwapUUID for swap partitions
#
#

# $1 = DriveName  (e.g. /dev/sdb)
# $2 = DriveNumber  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
saveAllSwapUUIDs() {
    local disk="$1"
    local driveNum="$2"
    local imagePath="$3"
    local partNum=""
    local swapfilename=$(swapUUIDFileName $imagePath $driveNum)
    for part in $(getPartitions $disk); do
        if [[ $(partitionIsSwap $part) != 0 ]]; then
            saveSwapUUID "$swapfilename" "$part"
        fi
    done
}


# $1 is the location of the file uuids are stored in
# $2 is the partition device name
makeSwapSystem() {
    local uuid=""
    local option=""
    local disk=$(getDiskFromPartition $2)
    local part_type="0"
    local hasgpt=$(hasGPT $disk)
    if [[ $hasgpt == 1 ]]; then
        # don't have a good way to test, as ubuntu installer
        # doesn't set the GPT partition type correctly.
        # so, only format as swap if uuid exists.
        if [ -e "$1" ]; then
            uuid=$(egrep "^$2" "$1" | awk '{print $2;}')
        fi
        if [ -n "$uuid" ]; then
            part_type="82"
        fi
    else
        # Leaving the grep in place due to forward slashes
        part_type=$(sfdisk -d "$disk" 2>/dev/null | grep ^$2 | awk -F[,=] '{print $6}')
    fi
    if [[ $part_type == 82 ]]; then
        if [[ -e $1 ]]; then
            uuid=$(egrep "^$2" "$1" | awk '{print $2;}')
        fi
        if [[ -n $uuid ]]; then
            option="-U $uuid"
        fi
        echo " * Restoring swap partition: $2"
        mkswap $option $2 &> /dev/null
    fi
}

# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
# $3 is the image path (e.g. /net/dev/foo)
resizeSfdiskPartition() {
    local part="$1"
    local size="$2"
    local disk=$(getDiskFromPartition $part)
    local imagePath="$3"
    local tmp_file="/tmp/sfdisk.$$"
    local tmp_file2="/tmp/sfdisk2.$$"
    saveSfdiskPartitions $disk $tmp_file
    processSfdisk $tmp_file resize $part $size > $tmp_file2;
    if [[ $? == 0 ]]; then
        applySfdiskPartitions $disk $tmp_file2
    fi
    mv $tmp_file2 $(sfdiskMinimumPartitionFileName $imagePath 1) &>/dev/null
}

# $1 is the disk device (e.g. /dev/sda)
# $2 is the name of the original sfdisk -d output file used as a template
# $3 is the : separated list of fixed size partitions (e.g. 1:2)
#	 swap partitions are automatically added.  Empty string is
#	 ok.
fillSfdiskWithPartitions() {
    local disk="$1"
    local disk_size=$(blockdev --getsize64 $disk | awk '{printf("%d\n",$1/1024);}');
    local tmp_file2="/tmp/sfdisk2.$$"
    processSfdisk "$2" filldisk "$disk" "$disk_size" "$3" > $tmp_file2
    if [[ $ismajordebug -gt 0 ]]; then
        majorDebugEcho "Trying to fill with the disk with these partititions:"
        cat $tmp_file2
        majorDebugPause
    fi
    if [[ $? == 0 ]]; then
        applySfdiskPartitions $1 $tmp_file2
    fi
    runPartprobe $1
    rm -f $tmp_file2
    majorDebugEcho "Applied the preceding table."
    majorDebugShowCurrentPartitionTable "$1" "1"
    majorDebugPause
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
    local data="$1"
    local minstart=$(awk -F'[ ,]+' '/start/{if ($4) print $4}' $data | sort -n | head -1)
    if [[ $osid == +([1-2]) ]]; then
        local chunksize="512"
        local minstart="63"
    else
        if [[ $minstart == 63 ]]; then
            local chunksize="512"
        else
            local chunksize="2048"
        fi
    fi
    local awkArgs="-v CHUNK_SIZE=$chunksize -v MIN_START=$minstart"
    awkArgs="${awkArgs} -v action=$2 -v target=$3 -v sizePos=$4"
    if [[ -n $5 ]]; then
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
    if [ "$mbr" == "present" -o  "$mbr" == "MBR" ]; then
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
    elif [ "$gpt" == "damaged" ]; then
        gpttype="GPT";
    fi

    if [ -n "$gpttype" -a -n "$mbrtype" ]; then
        type="${gpttype}-${mbrtype}"
    elif [ -n "$gpttype" ]; then
        type="${gpttype}"
    elif [ -n "$mbrtype" ]; then
        type="${mbrtype}"
    else
        type="unknown";
    fi
    echo "$type"
}


#
# Detect the desired partition table type,
# using the available files in imagePath, don't rely
# on the actual disk.
#
# Assumes GPT or MBR.  Uses first 8 bytes of second block
# which should hold "EFI PART".  (https://en.wikipedia.org/wiki/GUID_Partition_Table#Partition_table_header_.28LBA_1.29)
#
# $1 : imagePath   (e.g. /images/foo)
# $2 : disk number (e.g. 1)
getDesiredPartitionTableType() {
    local imagePath="$1";
    local intDisk="$2";
    local type="unknown";
    local mbrfile=`MBRFileName "${imagePath}" "${intDisk}"`;
    if [ -r "$mbrfile" ]; then
        local tmpfile="/tmp/gptsig";
        dd skip=512 bs=1 if="${mbrfile}" of="${tmpfile}" count=8 >/dev/null 2>&1;
        touch $tmpfile;
        local gptsig=`cat $tmpfile`;
        if [ "$gptsig" == "EFI PART" ]; then
            type="GPT";
        else
            type="MBR";
        fi
    fi

    echo $type;
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

# $1 is the name of the disk drive
# $2 is name of file to save to.
saveSgdiskPartitions() {
    local disk="$1";
    local filename="$2";
    getPartitions $disk
    local partNum="";
    rm -f $filename;
    sgdisk -p "$disk" | \
    awk '/^Logical sector size:/{sectorsize=$4;} /Disk identifier \(GUID\):/{diskcode=$4;}  /^First usable sector is/{split($5, a, ",", seps); first=a[1]; last=$10;}  /^Partitions will be aligned on/{split($6, a, "-", seps); boundary=a[1];}  /^ *[0-9]+ +/{partnum=$1; start=$2; end=$3; code=$6; print "part:" partnum ":" start ":" end ":" code;}  END{print "'$disk':" sectorsize ":" diskcode ":" first ":" last ":" boundary}' \
    >> $filename;
    for part in $parts; do
        partNum=`getPartitionNumber $part`;
        sgdisk -i "$partNum" "$disk" | \
        awk '/^Partition GUID code:/{typecode=$4;} /Partition unique GUID:/{partcode=$4;} /^Partition name:/{name=$3; for(i=4;i<=NF;i++) {name = name " " $i}} /^First sector:/{first=$3;} /^Last sector:/{last=$3;} END{print "'$part':" typecode ":" partcode ":" first ":" last ":" name;}' \
        | sed -r "s/'//g" \
        >> $filename;
    done
}

# $1 is the name of the disk drive
# $2 is name of file to restore from.
restoreSgdiskPartitions() {
    local disk="$1";
    local filename="$2";
    local parts=`egrep "^${disk}[0-9]+:" $filename | awk -F: '{print $1;}'`;
    local part="";
    local escape_disk=`echo "$disk" | sed -r 's%/%\\\\/%g'`;
    local diskguid=`awk -F: '/^'"$escape_disk"':/{print $3;}' $filename`;

    # wipe out the partition table, then restore
    sgdisk --zap-all "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to restore partitions (zap)";
    fi
    sgdisk --disk-guid "$diskguid" "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to restore partitions (disk guid)";
    fi

    for part in $(egrep "^${disk}[0-9]+:" $filename | awk -F: '{print $1;}'); do
        local part_number=$(getPartitionNumber $part);
        local escape_part=`echo "$part" | sed -r 's%/%\\\\/%g'`;
        local partstart=`awk -F: '/^'"$escape_part"':/{print $4;}' $filename`;
        local partend=`awk -F: '/^'"$escape_part"':/{print $5;}' $filename`;
        local parttype=`awk -F: '/^'"$escape_part"':/{print $2;}' $filename`;
        local partcode=`awk -F: '/^'"$escape_part"':/{print $3;}' $filename`;
        local partname=`awk -F: '/^'"$escape_part"':/{print $6;}' $filename`;

        sgdisk --new "$part_number:$partstart:$partend" "$disk" >/dev/null 2>&1;
        if [ "$?" != 0 ]; then
            handleError "Failed to restore partition (add)";
        fi
        sgdisk --change-name "$part_number:$partname" "$disk" >/dev/null 2>&1;
        if [ "$?" != 0 ]; then
            handleError "Failed to restore partition (name)";
        fi
        sgdisk --typecode "$part_number:$parttype" "$disk" >/dev/null 2>&1;
        if [ "$?" != 0 ]; then
            handleError "Failed to restore partition (type)";
        fi
        sgdisk --partition-guid "$part_number:$partcode" "$disk" >/dev/null 2>&1;
        if [ "$?" != 0 ]; then
            handleError "Failed to restore partition (GUID)";
        fi
    done
}

#
#
# $1 is the name of the disk drive
# $2 is name of file with original partition layout.
# $3 is the : separated list of fixed size partitions (e.g. 1:2)
#	 Empty string is ok.
fillSgdiskWithPartitions() {
    local disk="$1";
    local filename="$2";
    local fixed_size_partitions="$3";

    # get initial information from partition text file
    local part="";
    local escape_disk=`echo "$disk" | sed -r 's%/%\\\\/%g'`;
    local sectorsize=`awk -F: '/^'"$escape_disk"':/{print $2;}' $filename`;
    local boundary=`awk -F: '/^'"$escape_disk"':/{print $6;}' $filename`;

    # get disk size, but give margin for backup GPT (32 sectors)
    local disk_size=`blockdev --getsize64 "$disk" | awk '{printf("%d\n",$1/'"$sectorsize"');}'`;
    ((disk_size -= 32));

    # find first partition, and leave its starting position as is
    local tmppartfile="/tmp/partitionorder";
    local first_start=$disk_size;
    rm -f $tmppartfile;
    for part in $(getPartitions $disk); do
        local escape_part=`echo "$part" | sed -r 's%/%\\\\/%g'`;
        local partstart=`awk -F: '/^'"$escape_part"':/{print $4;}' $filename`;
        if [ -n "$partstart" -a "$partstart" -lt "$first_start" ]; then
            first_start=$partstart;
        fi
        echo "$partstart $part" >> $tmppartfile;
    done

    # find ordering of partitions on the disk
    # this is important for final processing so the partitions are stored in the right order
    parts=`sort -n $tmppartfile | awk '{print $2;}' | tr '\n' ' '`;
    rm -f $tmppartfile;


    # find number of sectors that were fixed and variable under old disk
    local original_variable=0;
    local original_fixed=$first_start;  # pre-first partition is fixed
    for part in $parts; do
        local part_number=`echo $part | sed -r 's/^[^0-9]+//g'`;
        local escape_part=`echo "$part" | sed -r 's%/%\\\\/%g'`;
        local partstart=`awk -F: '/^'"$escape_part"':/{print $4;}' $filename`;
        local partend=`awk -F: '/^'"$escape_part"':/{print $5;}' $filename`;
        local part_size=`expr "$partend" "-" "$partstart" "+" "1"`;
        local is_fixed=`echo "${fixed_size_partitions}" | awk -F: '{for(i=1;i<=NF;i++) {print $i;}}' | egrep '^'"$part_number"'$' | wc -l`;
        if [ "$is_fixed" == "0" ]; then
            ((original_variable += part_size))
        else
            ((original_fixed += part_size))
        fi
    done

    # find amount of disk fixed and variable under new disk
    local new_fixed=$original_fixed;
    local new_variable=`expr "$disk_size" "-" "$original_fixed"`;

    # wipe out the partition table, to start from scratch
    local diskguid=`awk -F: '/^'"$escape_disk"':/{print $3;}' $filename`;
    sgdisk --zap-all "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to fill partitions (zap)";
    fi
    sgdisk --disk-guid "$diskguid" "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to fill partitions (disk guid)";
    fi

    # find new start, size, end for all partitions, and create them
    local g_start="$first_start";
    for part in $parts; do
        local part_number=`echo $part | sed -r 's/^[^0-9]+//g'`;
        local escape_part=`echo "$part" | sed -r 's%/%\\\\/%g'`;
        local partstart=`awk -F: '/^'"$escape_part"':/{print $4;}' $filename`;
        local partend=`awk -F: '/^'"$escape_part"':/{print $5;}' $filename`;
        local parttype=`awk -F: '/^'"$escape_part"':/{print $2;}' $filename`;
        local partcode=`awk -F: '/^'"$escape_part"':/{print $3;}' $filename`;
        local partname=`awk -F: '/^'"$escape_part"':/{print $6;}' $filename`;
        local part_size=`expr "$partend" "-" "$partstart" "+" "1"`;
        local is_fixed=`echo "${fixed_size_partitions}" | awk -F: '{for(i=1;i<=NF;i++) {print $i;}}' | egrep '^'"$part_number"'$' | wc -l`;
        local new_size=$part_size;
        local remainder=0;
        if [ "$is_fixed" == "0" ]; then
            ((new_size = part_size * new_variable / original_variable))
            ((remainder = new_size % boundary))
            # make sure new size is a multiple of boundary, if variable sized partition
            if [ "$remainder" -gt 0 ]; then
                ((new_size -= remainder))
            fi
        fi
        local new_start=$g_start;
        local new_end=0;
        ((new_end = new_start + new_size - 1));
        # don't go past end of drive
        if [ "$new_end" -gt "$disk_size" ]; then
            new_end=$disk_size;
        fi

        sgdisk --new "$part_number:$new_start:$new_end" "$disk" >/dev/null 2>&1;
        if [ "$?" != 0 ]; then
            handleError "Failed to fill partition (add)";
        fi
        sgdisk --change-name "$part_number:$partname" "$disk" >/dev/null 2>&1;
        if [ "$?" != 0 ]; then
            handleError "Failed to fill partition (name)";
        fi
        sgdisk --typecode "$part_number:$parttype" "$disk" >/dev/null 2>&1;
        if [ "$?" != 0 ]; then
            handleError "Failed to fill partition (type)";
        fi
        sgdisk --partition-guid "$part_number:$partcode" "$disk" >/dev/null 2>&1;
        if [ "$?" != 0 ]; then
            handleError "Failed to fill partition (GUID)";
        fi

        # make new start for next partition.  make sure it's a multiple of boundary
        ((g_start += new_size))
        ((remainder = g_start % boundary))
        if [ "$remainder" -gt 0 ]; then
            ((g_start += boundary - remainder))
        fi
    done

    return
}


# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
# $3 is the image path (e.g. /net/dev/foo)
resizeSgdiskPartition() {
    local part="$1";
    local size="$2";
    local imagePath="$3";
    local disk=`echo $part | sed -r 's/[0-9]+$//g'`;
    local part_number=`echo $part | sed -r 's/^[^0-9]+//g'`;


    local escape_disk=`echo "$disk" | sed -r 's%/%\\\\/%g'`;
    local escape_part=`echo "$part" | sed -r 's%/%\\\\/%g'`;
    local filename="/tmp/sgdisk.partitions";
    saveSgdiskPartitions "$disk" "$filename";
    local sectorsize=`awk -F: '/^'"$escape_disk"':/{print $2;}' $filename`;
    local boundary=`awk -F: '/^'"$escape_disk"':/{print $6;}' $filename`;
    local partstart=`awk -F: '/^'"$escape_part"':/{print $4;}' $filename`;
    local parttype=`awk -F: '/^'"$escape_part"':/{print $2;}' $filename`;
    local partcode=`awk -F: '/^'"$escape_part"':/{print $3;}' $filename`;
    local partname=`awk -F: '/^'"$escape_part"':/{print $6;}' $filename`;
    local newsize=`expr $size '*' 1024 '/' $sectorsize`;
    local remainder=`expr $newsize '%' $boundary`;
    if [ "$remainder" -gt 0 ]; then
        newsize=`expr "$newsize" "-" "(" "(" "$newsize" "+" "$boundary" ")" "%" "$boundary" ")"`;
    fi
    local partend=`expr "$partstart" "+" "$newsize"`;

    sgdisk --delete "$part_number" "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to resize partition (delete)";
    fi
    sgdisk --new "$part_number:$partstart:$partend" "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to resize partition (add)";
    fi
    sgdisk --change-name "$part_number:$partname" "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to resize partition (name)";
    fi
    sgdisk --typecode "$part_number:$parttype" "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to resize partition (TYPE)";
    fi
    sgdisk --partition-guid "$part_number:$partcode" "$disk" >/dev/null 2>&1;
    if [ "$?" != 0 ]; then
        handleError "Failed to resize partition (GUID)";
    fi

    rm -f $filename;
}


#
# Detect the partition table type, then call the correct
# resizePartition function
#
# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
# $3 is the image path (e.g. /net/dev/foo)
resizePartition() {
    local part="$1";
    local size="$2";
    local imagePath="$3";
    local disk=`echo $part | sed -r 's/[0-9]+$//g'`;
    local table_type=`getPartitionTableType "$disk"`;

    if [ "$table_type" == "MBR" ]; then
        resizeSfdiskPartition "$part" "$size" "$imagePath";
    elif [ "$table_type" == "GPT" ]; then
        resizeSgdiskPartition "$part" "$size" "$imagePath";
    else
        handleError "Unexpected partition table type: $table_type";
    fi

    # make sure kernel knows about the changes
    runPartprobe "$disk";
}

#
# Detect the partition table type, then save all relevant
# partition information
#
# $1 : device name of the drive
# $2 : imagePath
# $3 : disk number
saveOriginalPartitions() {
    local disk="$1";
    local imagePath="$2";
    local intDisk="$3";
    local table_type=`getPartitionTableType "$disk"`;
    if [ "$table_type" == "MBR" ]; then
        local filename=`sfdiskOriginalPartitionFileName "$imagePath" "$intDisk"`;
        saveSfdiskPartitions "$disk" "$filename";
    elif [ "$table_type" == "GPT" ]; then
        local filename=`sgdiskOriginalPartitionFileName "$imagePath" "$intDisk"`;
        saveSgdiskPartitions "$disk" "$filename";
    else
        handleError "Unexpected partition table type: $table_type";
    fi
}

#
# Detect the partition table type, then restore partition
# sizes, using saved partition information
#
# $1 : device name of the drive
# $2 : imagePath
# $3 : disk number
restoreOriginalPartitions() {
    local disk="$1";
    local imagePath="$2";
    local intDisk="$3";
    local table_type=`getPartitionTableType "$disk"`;
    if [ "$table_type" == "MBR" ]; then
        local filename=`sfdiskOriginalPartitionFileName "$imagePath" "$intDisk"`;
        restoreSfdiskPartitions "$disk" "$filename";
    elif [ "$table_type" == "GPT" ]; then
        local filename=`sgdiskOriginalPartitionFileName "$imagePath" "$intDisk"`;
        restoreSgdiskPartitions "$disk" "$filename";
    else
        handleError "Unexpected partition table type: $table_type";
    fi

    # make sure kernel knows about the changes
    runPartprobe "$disk";
}

#
# Detect the partition table type, the fill the disk with
# the partitions, using the correct routine.
#
# $1 : the disk device (e.g. /dev/sda)
# $2 : imagePath   (e.g. /images/foo)
# $3 : disk number (e.g. 1)
fillDiskWithPartitions() {
    local disk="$1";
    local imagePath="$2";
    local intDisk="$3";
    local fixed_size_partitions="";
    local fsfilename=`fixedSizePartitionsFileName "${imagePath}" "${intDisk}"`;
    if [ -r "$fsfilename" ]; then
        fixed_size_partitions=`cat "$fsfilename"`;
    fi
    local table_type=`getDesiredPartitionTableType "$imagePath" "$intDisk"`;

    if [ "$table_type" == "MBR" ]; then
        local filename=`sfdiskOriginalPartitionFileName "$imagePath" "$intDisk"`;
        local legacyfilename=`sfdiskLegacyOriginalPartitionFileName "$imagePath" "$intDisk"`;
        if [ ! -r "$filename" ]; then
            filename="$legacyfilename";
        fi
        fillSfdiskWithPartitions "$disk" "$filename" "$fixed_size_partitions";
    elif [ "$table_type" == "GPT" ]; then
        local filename=`sgdiskOriginalPartitionFileName "$imagePath" "$intDisk"`;
        fillSgdiskWithPartitions "$disk" "$filename" "$fixed_size_partitions";
    else
        handleError "Unexpected partition table type: $table_type";
    fi

    # make sure kernel knows about the changes
    runPartprobe "$disk";
}

#
# Check if it will be ok to call fillDiskWithPartitions
#
# $1 : the disk device (e.g. /dev/sda)
# $2 : imagePath   (e.g. /images/foo)
# $3 : disk number (e.g. 1)
fillDiskWithPartitionsIsOK() {
    local disk="$1";
    local imagePath="$2";
    local intDisk="$3";
    local table_type=`getDesiredPartitionTableType "$imagePath" "$intDisk"`;
    local result="0";

    if [ "$table_type" == "MBR" ]; then
        local filename=`sfdiskOriginalPartitionFileName "$imagePath" "$intDisk"`;
        local legacyfilename=`sfdiskLegacyOriginalPartitionFileName "$imagePath" "$intDisk"`;
        if [ -r "$filename" -o -r "$legacyfilename" ]; then
            result="1";
        fi
    elif [ "$table_type" == "GPT" ]; then
        local filename=`sgdiskOriginalPartitionFileName "$imagePath" "$intDisk"`;
        if [ -r "$filename" ]; then
            result="1";
        fi
    fi
    echo "$result";
}


#
# Show the current partition table
#
# $1 : the disk device (e.g. /dev/sda)
# $2 : disk number (e.g. 1)
majorDebugShowCurrentPartitionTable() {
    if [ "$ismajordebug" -le 0 ]; then
        return;
    fi

    local disk="$1";
    local intDisk="$2";
    local table_type=`getDesiredPartitionTableType "$imagePath" "$intDisk"`;
    echo "";
    echo "Current partition table:";
    if [ "$table_type" == "MBR" ]; then
        sfdisk -d "$disk";
    elif [ "$table_type" == "GPT" ]; then
        sgdisk -p "$disk";
    fi
    echo "";
}

# Local Variables:
# indent-tabs-mode: t
# sh-basic-offset: 4
# sh-indentation: 4
# tab-width: 4
# End:
