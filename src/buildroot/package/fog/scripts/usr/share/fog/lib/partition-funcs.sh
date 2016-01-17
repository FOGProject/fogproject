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
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to save to (${FUNCNAME[0]})"
    sfdisk -d $disk 2>/dev/null > $file
    [[ ! $? -eq 0 ]] && majorDebugEcho "sfdisk failed in (${FUNCNAME[0]})"
}
# $1 is the name of the disk drive
# $2 is name of file to load from.
applySfdiskPartitions() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})"
    sfdisk $disk < $file >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && majorDebugEcho "sfdisk failed in (${FUNCNAME[0]})"
}
# $1 is the name of the disk drive
# $2 is name of file to load from.
restoreSfdiskPartitions() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})"
    applySfdiskPartitions "$disk" "$file"
    fdisk $disk < /usr/share/fog/lib/EOFRESTOREPART >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && majorDebugEcho "fdisk failed in (${FUNCNAME[0]})"
}
# $1 is the name of the disk drive
hasExtendedPartition() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    sfdisk -d $disk 2>/dev/null | egrep '(Id|type)=\ *[5f]' | wc -l
    [[ ! $? -eq 0 ]] && majorDebugEcho "sfdisk failed in (${FUNCNAME[0]})"
}
# $1 is the name of the partition device (e.g. /dev/sda3)
partitionHasEBR() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    local partNum=$(getPartitionNumber $part)
    local disk=$(getDiskFromPartition $part)
    local part_type=$(sfdisk -d $disk 2>/dev/null | grep ^$part | awk -F[,=] '{print $6}')
    [[ $part_type -eq 5 || $part_type == f || $partNum -ge 5 ]] && echo 1 || echo 0
}
# $1 is the name of the partition device (e.g. /dev/sda3)
# $2 is the name of the file to save to (e.g. /net/dev/foo/d1p4.ebr)
saveEBR() {
    local part="$1"
    local file="$2"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})"
    local disk=$(getDiskFromPartition $part)
    local table_type=$(getPartitionTableType $disk)
    [[ $table_type != MBR ]] && return
    # Leaving the grep in place due to forward slashes
    local part_type=$(sfdisk -d $disk 2>/dev/null | grep ^$part | awk -F[,=] '{print $6}')
    [[ ! $(partitionHasEBR $part) -gt 0 ]] && return
    dots "Saving EBR for ($part)"
    dd if=$part of=$file bs=512 count=1 >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            debugPause
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not backup EBR (${FUNCNAME[0]})"
            ;;
    esac
}
# $1 = DriveName  (e.g. /dev/sdb)
# $2 = DriveNumber  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
saveAllEBRs() {
    local disk="$1"
    local driveNum="$2"
    local imagePath="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $driveNum ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    getPartitions "$disk"
    for part in $parts; do
        partNum=$(getPartitionNumber $part)
        ebrfilename=$(EBRFileName $imagePath $driveNum $partNum)
        saveEBR "$part" "$ebrfilename"
    done
}
# $1 is the name of the partition device (e.g. /dev/sda3)
# $2 is the name of the file to restore from (e.g. /net/foo/d1p4.ebr)
restoreEBR() {
    local part="$1"
    local file="$2"
    local disk=$(getDiskFromPartition $part)
    local table_type=$(getPartitionTableType $disk)
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to restore from passed (${FUNCNAME[0]})"
    [[ $table_type != MBR ]] && return
    # Leaving the grep in place due to forward slashes
    local part_type=$(sfdisk -d $disk 2>/dev/null | grep ^$part | awk -F[,=] '{print $6}')
    [[ ! $(partitionHasEBR $part) -gt 0 ]] && return
    [[ ! -e $file ]] && return
    dots "Restoring EBR for ($part)"
    dd of=$part if=$file bs=512 count=1 >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            debugPause
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not reload EBR data (${FUNCNAME[0]}"
            ;;
    esac
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
    local ebffilename=""
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $driveNum ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePaht ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $imgPartitionType ]] && handleError "No partition type passed (${FUNCNAME[0]})"
    getPartitions "$disk"
    for part in $parts; do
        partNum=$(getPartitionNumber $part)
        [[ $imgPartitionType != all && $imgPartitionType != $partNum ]] && continue
        ebrfilename=$(EBRFileName $imagePath $driveNum $partNum)
        restoreEBR "$part" "$ebrfilename"
    done
    runPartprobe "$disk"
}
# $1 is the name of the partition device (e.g. /dev/sda3)
partitionIsSwap() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    local fstype=$(fsTypeSetting $part)
    [[ $fstype == swap ]] && echo 1 || echo 0
}
# $1 is the location of the file to store uuids in
# $2 is the partition device name
saveSwapUUID() {
    local file="$1"
    local part="$2"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})"
    local is_swap=$(partitionIsSwap $part)
    [[ $is_swap -eq 0 ]] && return
    local uuid=$(blkid -s UUID $2 | cut -d\" -f2)
    [[ -z $uuid ]] && return
    echo " * Saving UUID ($uuid) for ($part)"
    echo "$part $uuid" >> $file
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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $driveNum ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local partNum=""
    local swapfilename=$(swapUUIDFileName $imagePath $driveNum)
    getPartitions "$disk"
    for part in $parts; do
        [[ $(partitionIsSwap $part) -eq 0 ]] && continue
        saveSwapUUID "$swapfilename" "$part"
    done
}
# $1 is the location of the file uuids are stored in
# $2 is the partition device name
makeSwapSystem() {
    local file="$1"
    local part="$2"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file passed (${FUNCNAME[0]})"
    local uuid=""
    local option=""
    local disk=$(getDiskFromPartition $part)
    local part_type=0
    local hasgpt=$(hasGPT $disk)
    case $hasgpt in
        1)
            uuid=$(egrep "^$part" $file | awk '{print $2}')
            [[ -z $uuid ]] && handleError "Failed to get uuid (${FUNCNAME[0]})"
            part_type=82
            ;;
        *)
            part_type=$(sfdisk -d $disk 2>/dev/null | grep ^$2 | awk -F[,=] '{print $6}')
            ;;
    esac
    [[ ! $part_type -eq 82 ]] && return
    uuid=$(egrep "^$2" "$1" | awk '{print $2;}')
    [[ -z $uuid ]] && handleError "Failed to get uuid (${FUNCNAME[0]})"
    option="-U $uuid"
    dots "Restoring swap partition"
    mkswap $option $part >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            debugPause
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not create swap on $part (${FUNCNAME[0]})"
            ;;
    esac
}
# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
# $3 is the image path (e.g. /net/dev/foo)
resizeSfdiskPartition() {
    local part="$1"
    local size="$2"
    local imagePath="$3"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $size ]] && handleError "No desired size passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local disk=$(getDiskFromPartition $part)
    local tmp_file="/tmp/sfdisk.$$"
    local tmp_file2="/tmp/sfdisk2.$$"
    saveSfdiskPartitions "$disk" "$tmp_file"
    processSfdisk "$tmp_file" resize "$part" "$size" > "$tmp_file2"
    [[ $? -eq 0 ]] && applySfdiskPartitions "$disk" "$tmp_file2"
    mv $tmp_file2 $(sfdiskMinimumPartitionFileName $imagePath 1) >/dev/null 2>&1
}
# $1 is the disk device (e.g. /dev/sda)
# $2 is the name of the original sfdisk -d output file used as a template
# $3 is the : separated list of fixed size partitions (e.g. 1:2)
#	 swap partitions are automatically added.  Empty string is
#	 ok.
fillSfdiskWithPartitions() {
    local disk="$1"
    local file="$2"
    local fixed="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to use passed (${FUNCNAME[0]})"
    local disk_size=$(blockdev --getsize64 $disk | awk '{printf("%d\n",$1/1024);}')
    local tmp_file2="/tmp/sfdisk2.$$"
    processSfdisk "$file" filldisk "$disk" "$disk_size" "$fixed" > "$tmp_file2"
    if [[ $ismajordebug -gt 0 ]]; then
        majorDebugEcho "Trying to fill with the disk with these partititions:"
        cat $tmp_file2
        majorDebugPause
    fi
    [[ $? -eq 0 ]] && applySfdiskPartitions "$disk" "$tmp_file2"
    runPartprobe "$disk"
    rm -f $tmp_file2
    majorDebugEcho "Applied the preceding table."
    majorDebugShowCurrentPartitionTable "$disk" 1
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
    local action="$2"
    local target="$3"
    local size="$4"
    local fixed="$5"
    [[ -z $data ]] && handleError "No data passed (${FUNCNAME[0]})"
    [[ -z $action ]] && handleError "No action passed (${FUNCNAME[0]})"
    [[ -z $target ]] && handleError "Device (disk or partition) not passed (${FUNCNAME[0]})"
    [[ -z $size ]] && handleError "No desired size passed (${FUNCNAME[0]})"
    local minstart=$(awk -F'[ ,]+' '/start/{if ($4) print $4}' $data | sort -n | head -1)
    local chunksize=""
    local minstart=""
    case $osid in
        [1-2])
            chunksize=512
            minstart=63
            ;;
        *)
            [[ $minstart -eq 63 ]] && chunksize=512 || chunksize=2048
            ;;
    esac
    local awkArgs="-v CHUNK_SIZE=$chunksize -v MIN_START=$minstart"
    awkArgs="${awkArgs} -v action=$action -v target=$target -v sizePos=$size"
    [[ -n $fixed ]] && awkArgs="$awkArgs -v fixedList=$fixed"
    # process with external awk script
    /usr/share/fog/lib/procsfdisk.awk $awkArgs $data
}
#
# GPT Functions below
#
# $1 : device name of drive
getPartitionTableType() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    local mbr=$(gdisk -l $disk | awk '/^\ *MBR:/{print $2}')
    local gpt=$(gdisk -l $disk | awk '/^\ *GPT:/{print $2}')
    local type=""
    local mbrtype=""
    local gpttype=""
    case $mbr in
        present|MBR)
            mbrtype="MBR"
            ;;
        hybrid)
            mbrtype="HYBRID"
            ;;
        protective|not)
            mbrtype=""
            ;;
    esac
    case $gpt in
        present|damaged)
            gpttype="GPT"
            ;;
        not)
            gpttype=""
            ;;
    esac
    [[ -z $gpttype && -z $mbrtype ]] && handleError "Cannot determine partition type (${FUNCNAME[0]})"
    [[ -n $gpttype && -n $mbrtype ]] && echo "$gpttype-$mbrtype"
    [[ -n $gpttype && -z $mbrtype ]] && echo "$gpttype"
    [[ -z $gpttype && -n $mbrtype ]] && echo "$mbrtype"
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
    local imagePath="$1"
    local intDisk="$2"
    [[ -z $intDisk ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local type="unknown"
    local mbrfile=$(MBRFileName $imagePath $intDisk)
    [[ ! -r $mbrfile ]] && return
    local tmpfile="/tmp/gptsig"
    dd skip=512 bs=1 if=$mbrfile of=$tmpfile count=8 >/dev/null 2>&1
    touch $tmpfile
    local gptsig=$(cat $tmpfile)
    [[ $gptsig == "EFI PART" ]] && echo "GPT" || echo "MBR"
}
# $1 : device name of drive
hasHybridMBR() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    local mbr=$(gdisk -l $disk | awk '/^\ *MBR:/{print $2}')
    [[ $mbr == hybrid ]] && echo 1 || echo 0
}
# $1 : device name of drive
hasGPT() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    local present=0
    local gpt=$(gdisk -l $disk | awk '/^\ *GPT:/{print $2}')
    [[ $gpt == present ]] &&  present=1
    [[ $gpt == not ]] && present=0
    echo $present
}
# $1 is the name of the disk drive
# $2 is name of file to save to.
saveSgdiskPartitions() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to save to passed (${FUNCNAME[0]})"
    getPartitions "$disk"
    local partNum=""
    rm -f $file
    sgdisk -p $disk | \
    awk '/^Logical sector size:/{sectorsize=$4;} /Disk identifier \(GUID\):/{diskcode=$4;}  /^First usable sector is/{split($5, a, ",", seps); first=a[1]; last=$10;}  /^Partitions will be aligned on/{split($6, a, "-", seps); boundary=a[1];}  /^ *[0-9]+ +/{partnum=$1; start=$2; end=$3; code=$6; print "part:" partnum ":" start ":" end ":" code;}  END{print "'$disk':" sectorsize ":" diskcode ":" first ":" last ":" boundary}' \
    >> $file
    for part in $parts; do
        partNum=$(getPartitionNumber $part)
        sgdisk -i $partNum $disk | \
        awk '/^Partition GUID code:/{typecode=$4;} /Partition unique GUID:/{partcode=$4;} /^Partition name:/{name=$3; for(i=4;i<=NF;i++) {name = name " " $i}} /^First sector:/{first=$3;} /^Last sector:/{last=$3;} END{print "'$part':" typecode ":" partcode ":" first ":" last ":" name;}' \
        | sed -r "s/'//g" \
        >> $file
    done
}
# $1 is the name of the disk drive
# $2 is name of file to restore from.
restoreSgdiskPartitions() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file to restore from (${FUNCNAME[0]})"
    local parts=$(egrep "^${disk}[0-9]+:" $file | awk -F: '{print $1}')
    local part=""
    local escape_disk=$(echo $disk | sed -r 's%/%\\\\/%g')
    local diskguid=$(awk -F: '/^'"$escape_disk"':/{print $3}' $file)
    # wipe out the partition table, then restore
    sgdisk --zap-all $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to restore partitions (zap) (${FUNCNAME[0]})"
    sgdisk --disk-guid $diskguid $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to restore partitions (disk guid) (${FUNCNAME[0]})"
    for part in $(egrep "^${disk}[0-9]+:" $file | awk -F: '{print $1}'); do
        local part_number=$(getPartitionNumber $part)
        local escape_part=$(echo $part | sed -r 's%/%\\\\/%g')
        local partstart=$(awk -F: '/^'"$escape_part"':/{print $4}' $file)
        local partend=$(awk -F: '/^'"$escape_part"':/{print $5}' $file)
        local parttype=$(awk -F: '/^'"$escape_part"':/{print $2}' $file)
        local partcode=$(awk -F: '/^'"$escape_part"':/{print $3}' $file)
        local partname=$(awk -F: '/^'"$escape_part"':/{print $6}' $file)
        sgdisk --new $part_number:$partstart:$partend $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to restore partition (add) (${FUNCNAME[0]})"
        sgdisk --change-name $part_number:$partname $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to restore partition (name) (${FUNCNAME[0]})"
        sgdisk --typecode $part_number:$parttype $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to restore partition (type) (${FUNCNAME[0]})"
        sgdisk --partition-guid $part_number:$partcode $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to restore partition (GUID) ${FUNCNAME[0]})"
    done
}
#
#
# $1 is the name of the disk drive
# $2 is name of file with original partition layout.
# $3 is the : separated list of fixed size partitions (e.g. 1:2)
#	 Empty string is ok.
fillSgdiskWithPartitions() {
    local disk="$1"
    local file="$2"
    local fixed_size_partitions="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "No file passed (${FUNCNAME[0]})"
    # get initial information from partition text file
    local part=""
    local escape_disk=$(echo $disk | sed -r 's%/%\\\\/%g')
    local sectorsize=$(awk -F: '/^'"$escape_disk"':/{print $2}' $file)
    local boundary=$(awk -F: '/^'"$escape_disk"':/{print $6}' $file)
    # get disk size, but give margin for backup GPT (32 sectors)
    local disk_size=$(blockdev --getsize64 $disk | awk '{printf("%d\n",$1/'"$sectorsize"')}')
    let disk_size-=32
    # find first partition, and leave its starting position as is
    local tmppartfile="/tmp/partitionorder"
    local first_start="$disk_size"
    rm -f $tmppartfile
    getPartitions "$disk"
    for part in $parts; do
        local escape_part=$(echo $part | sed -r 's%/%\\\\/%g')
        local partstart=$(awk -F: '/^'"$escape_part"':/{print $4}' $file)
        [[ -n $partstart && $partstart -lt $first_start ]] && first_start="$partstart"
        echo "$partstart $part" >> $tmppartfile
    done
    # find ordering of partitions on the disk
    # this is important for final processing so the partitions are stored in the right order
    parts=$(sort -n $tmppartfile | awk '{print $2}' | tr '\n' ' ')
    rm -f $tmppartfile
    # find number of sectors that were fixed and variable under old disk
    local original_variable=0
    local original_fixed="$first_start"  # pre-first partition is fixed
    for part in $parts; do
        local part_number=$(echo $part | sed -r 's/^[^0-9]+//g')
        local escape_part=$(echo $part | sed -r 's%/%\\\\/%g')
        local partstart=$(awk -F: '/^'"$escape_part"':/{print $4}' $file)
        local partend=$(awk -F: '/^'"$escape_part"':/{print $5}' $file)
        local part_size=$(($partend - $partstart + 1))
        local is_fixed=$(echo $fixed_size_partitions | awk -F: '{for(i=1;i<=NF;i++) {print $i}}' | egrep '^'"$part_number"'$' | wc -l)
        [[ $is_fixed -eq 0 ]] && let original_variable+="$part_size" || let original_fixed+="$part_size"
    done
    # find amount of disk fixed and variable under new disk
    local new_fixed="$original_fixed"
    local new_variable=$((disk_size - original_fixed))
    # wipe out the partition table, to start from scratch
    local diskguid=$(awk -F: '/^'"$escape_disk"':/{print $3}' $filename)
    sgdisk --zap-all $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to fill partitions (zap) (${FUNCNAME[0]})"
    sgdisk --disk-guid $diskguid $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to fill partitions (disk guid) (${FUNCNAME[0]})"
    # find new start, size, end for all partitions, and create them
    local g_start="$first_start"
    for part in $parts; do
        local part_number=$(echo $part | sed -r 's/^[^0-9]+//g')
        local escape_part=$(echo $part | sed -r 's%/%\\\\/%g')
        local partstart=$(awk -F: '/^'"$escape_part"':/{print $4}' $file)
        local partend=$(awk -F: '/^'"$escape_part"':/{print $5}' $file)
        local parttype=$(awk -F: '/^'"$escape_part"':/{print $2}' $file)
        local partcode=$(awk -F: '/^'"$escape_part"':/{print $3}' $file)
        local partname=$(awk -F: '/^'"$escape_part"':/{print $6}' $file)
        local part_size=$(($partend - $partstart + 1))
        local is_fixed=$(echo $fixed_size_partitions | awk -F: '{for(i=1;i<=NF;i++) {print $i}}' | egrep '^'"$part_number"'$' | wc -l)
        local new_size="$part_size"
        local remainder=0
        if [[ $is_fixed -eq 0 ]]; then
            new_size=$((part_size * new_variable / original_variable))
            remainder=$((new_size % boundary))
            [[ $remainder -gt 0 ]] && let new_size-="$remainder"
        fi
        local new_start="$g_start"
        local new_end=0
        new_end=$((new_start + new_size - 1))
        [[ $new_end -gt $disk_size ]] && new_end="$disk_size"
        sgdisk --new $part_number:$new_start:$new_end $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to fill partition (add) (${FUNCNAME[0]})"
        sgdisk --change-name $part_number:$partname $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to fill partition (name) (${FUNCNAME[0]})"
        sgdisk --typecode $part_number:$parttype $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to fill partition (type) (${FUNCNAME[0]})"
        sgdisk --partition-guid $part_number:$partcode $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to fill partition (GUID) (${FUNCNAME[0]})"
        let g_start+="$new_size"
        remainder=$((g_start % boundary))
        [[ $remainder -gt 0 ]] && let g_start+=$((boundary - remainder))
    done
}
# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
# $3 is the image path (e.g. /net/dev/foo)
resizeSgdiskPartition() {
    local part="$1"
    local size="$2"
    local imagePath="$3"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $size ]] && handleError "No desired size passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local disk=$(echo $part | sed -r 's/[0-9]+$//g')
    local part_number=$(echo $part | sed -r 's/^[^0-9]+//g')
    local escape_disk=$(echo $disk | sed -r 's%/%\\\\/%g')
    local escape_part=$(echo $part | sed -r 's%/%\\\\/%g')
    local filename="/tmp/sgdisk.partitions"
    saveSgdiskPartitions "$disk" "$filename"
    local sectorsize=$(awk -F: '/^'"$escape_disk"':/{print $2}' $filename)
    local boundary=$(awk -F: '/^'"$escape_disk"':/{print $6}' $filename)
    local partstart=$(awk -F: '/^'"$escape_part"':/{print $4}' $filename)
    local parttype=$(awk -F: '/^'"$escape_part"':/{print $2}' $filename)
    local partcode=$(awk -F: '/^'"$escape_part"':/{print $3}' $filename)
    local partname=$(awk -F: '/^'"$escape_part"':/{print $6}' $filename)
    local newsize=$((size * 1024 / sectorsize))
    local remainder=$((newsize % boundary))
    [[ $remainder -gt 0 ]] && newsize=$((newsize - $(($((newsize + boundary)) % boundary))))
    local partend=$((partstart + newsize))
    sgdisk --delete $part_number $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to resize partition (delete) (${FUNCNAME[0]})"
    sgdisk --new $part_number:$partstart:$partend $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to resize partition (add) (${FUNCNAME[0]})"
    sgdisk --change-name $part_number:$partname $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to resize partition (name) (${FUNCNAME[0]})"
    sgdisk --typecode $part_number:$parttype $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to resize partition (type) (${FUNCNAME[0]})"
    sgdisk --partition-guid $part_number:$partcode $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to resize partition (GUID) (${FUNCNAME[0]})"
    rm -f $filename
}
#
# Detect the partition table type, then call the correct
# resizePartition function
#
# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
# $3 is the image path (e.g. /net/dev/foo)
resizePartition() {
    local part="$1"
    local size="$2"
    local imagePath="$3"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $size ]] && handleError "No size passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local disk=$(getDiskFromPartition $part)
    local table_type=$(getPartitionTableType $disk)
    case $table_type in
        MBR)
            resizeSfdiskPartition "$part" "$size" "$imagePath"
            ;;
        GPT)
            resizeSgdiskPartition "$part" "$size" "$imagePath"
            ;;
        *)
            handleError "Unexpected partition table type: $table_type (${FUNCNAME[0]})"
            ;;
    esac
    # make sure kernel knows about the changes
    runPartprobe "$disk"
}
#
# Detect the partition table type, then save all relevant
# partition information
#
# $1 : device name of the drive
# $2 : imagePath
# $3 : disk number
saveOriginalPartitions() {
    local disk="$1"
    local imagePath="$2"
    local intDisk="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $intDisk ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    local table_type=$(getPartitionTableType $disk)
    local filename=""
    case $table_type in
        MBR)
            filename=$(sfdiskOriginalPartitionFileName $imagePath $intDisk)
            saveSfdiskPartitions "$disk" "$filename"
            ;;
        GPT)
            filename=$(sgdiskOriginalPartitionFileName $imagePath $intDisk)
            saveSgdiskPartitions "$disk" "$filename"
            ;;
        *)
            handleError "Unexpected partition table type: $table_type (${FUNCNAME[0]})"
            ;;
    esac
    runPartprobe "$disk"
}
#
# Detect the partition table type, then restore partition
# sizes, using saved partition information
#
# $1 : device name of the drive
# $2 : imagePath
# $3 : disk number
restoreOriginalPartitions() {
    local disk="$1"
    local imagePath="$2"
    local intDisk="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $intDisk ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    local table_type=$(getPartitionTableType $disk)
    case $table_type in
        MBR)
            filename=$(sfdiskOriginalPartitionFileName $imagePath $intDisk)
            restoreSfdiskPartitions "$disk" "$filename"
            ;;
        GPT)
            filename=$(sgdiskOriginalPartitionFileName $imagePath $intDisk)
            restoreSgdiskPartitions "$disk" "$filename"
            ;;
        *)
            handleError "Unexpected partition table type: $table_type (${FUNCNAME[0]})"
            ;;
    esac
    # make sure kernel knows about the changes
    runPartprobe "$disk"
}
#
# Detect the partition table type, the fill the disk with
# the partitions, using the correct routine.
#
# $1 : the disk device (e.g. /dev/sda)
# $2 : imagePath   (e.g. /images/foo)
# $3 : disk number (e.g. 1)
fillDiskWithPartitions() {
    local disk="$1"
    local imagePath="$2"
    local intDisk="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $intDisk ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    local fixed_size_partitions=""
    local fsfilename=$(fixedSizePartitionsFileName $imagePath $intDisk)
    [[ -r $fsfilename ]] && fixed_size_partitions=$(cat $fsfilename)
    local table_type=$(getDesiredPartitionTableType $imagePath $intDisk)
    local filename=""
    local legacyfilename=""
    case $table_type in
        MBR)
            filename=$(sfdiskOriginalPartitionFileName $imagePath $intDisk)
            legacyfilename=$(sfdiskLegacyOriginalPartitionFileName $imagePath $intDisk)
            [[ ! -r $filename ]] && filename="$legacyfilename"
            fillSfdiskWithPartitions "$disk" "$filename" "$fixed_size_partitions"
            ;;
        GPT)
            filename=$(sgdiskOriginalPartitionFileName $imagePath $intDisk)
            fillSgdiskWithPartitions "$disk" "$filename" "$fixed_size_partitions"
            ;;
        *)
            handleError "Unexpected partition table type: $table_type (${FUNCNAME[0]})"
            ;;
    esac
    # make sure kernel knows about the changes
    runPartprobe "$disk"
}
#
# Check if it will be ok to call fillDiskWithPartitions
#
# $1 : the disk device (e.g. /dev/sda)
# $2 : imagePath   (e.g. /images/foo)
# $3 : disk number (e.g. 1)
fillDiskWithPartitionsIsOK() {
    local disk="$1"
    local imagePath="$2"
    local intDisk="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $intDisk ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    local table_type=$(getDesiredPartitionTableType $imagePath $intDisk)
    local filename=""
    local legacyfilename=""
    case $table_type in
        MBR)
            filename=$(sfdiskOriginalPartitionFileName $imagePath $intDisk)
            legacyfilename=$(sfdiskLegacyOriginalPartitionFileName $imagePath)
            [[ -r $filename || -r $legacyfilename ]] && echo 1 || echo 0
            ;;
        GPT)
            filename=$(sgdiskOriginalPartitionFileName $imagePath $intDisk)
            [[ -r $filename ]] && echo 1 || echo 0
            ;;
        *)
            echo 0
            ;;
    esac
}
#
# Show the current partition table
#
# $1 : the disk device (e.g. /dev/sda)
# $2 : disk number (e.g. 1)
majorDebugShowCurrentPartitionTable() {
    [[ $ismajordebug -le 0 ]] && return
    local disk="$1"
    local intDisk="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $intDisk ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    local table_type=$(getDesiredPartitionTableType $imagePath $intDisk)
    echo "Current partition table:"
    case $table_type in
        MBR)
            sfdisk -d $disk
            ;;
        GPT)
            sgdisk -p $disk
            ;;
    esac
}
