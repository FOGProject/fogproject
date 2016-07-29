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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to save to (${FUNCNAME[0]})\n   Args Passed: $*"
    sfdisk -d $disk 2>/dev/null > $file
    [[ ! $? -eq 0 ]] && majorDebugEcho "sfdisk failed in (${FUNCNAME[0]})"
}
# $1 is the name of the disk drive
# $2 is name of file to save to.
saveUUIDInformation() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to save to passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local hasgpt=0
    hasGPT "$disk"
    [[ $hasgpt -eq 0 ]] && return
    rm -f $file
    touch $file
    local diskuuid=""
    local partuuid=""
    local partfsuuid=""
    local parts=""
    local part=""
    local part_number=""
    local strtoadd=""
    local is_swap=0
    getDiskUUID "$disk"
    echo "$disk $diskuuid" >> $file
    getPartitions "$disk"
    for part in $parts; do
        getPartitionNumber "$part"
        partitionIsSwap "$part"
        [[ $is_swap -gt 0 ]] && continue
        getPartUUID "$part"
        getPartFSUUID "$part"
        [[ -n $partfsuuid ]] && strtoadd="$part $part_number:$partfsuuid"
        [[ -n $partuuid ]] &&  strtoadd="$strtoadd $part_number:$partuuid"
        echo "$strtoadd" >> $file
        strtoadd=""
    done
}
# $1 is the name of the disk drive
# $2 is name of file to restore from
restoreUUIDInformation() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to load from passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ ! -r $file ]] && return
    local diskuuid=""
    local partuuid=""
    local escape_disk=$(escapeItem $disk)
    local escape_part=""
    local is_swap=0
    diskuuid=$(awk "/^$escape_disk\ /{print \$2}" $file)
    [[ -n $diskuuid ]] && sgdisk -U $diskuuid $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to set disk guid (sgdisk -U) (${FUNCNAME[0]})\n   Args Passed: $*"
    getPartitions "$disk"
    for part in $parts; do
        partitionIsSwap "$part"
        [[ $is_swap -gt 0 ]] && continue
        escape_part=$(escapeItem $part)
        local oIFS=$IFS
        local IFS=$'\n'
        read partuuid parttype <<< $(awk "/^$escape_part\ /{printf(\"%s\n%s\",\$2,\$3)}" $file)
        IFS=$oIFS
        [[ -n $parttype ]] && sgdisk -t $parttype $disk >/dev/null 2>&1 || true
        [[ ! $? -eq 0 ]] && handleError " Failed to set partition type (sgdisk -t) (${FUNCNAME[0]})\n   Args Passed: $*"
        [[ -n $partuuid ]] && sgdisk -u $partuuid $disk >/dev/null 2>&1 || true
        [[ ! $? -eq 0 ]] && handleError "Failed to set partition guid (sgdisk -u) (${FUNCNAME[0]})\n   Args Passed: $*"
    done
}
# $1 is the name of the disk drive
# $2 is name of file to load from.
applySfdiskPartitions() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})\n   Args Passed: $*"
    sfdisk $disk < $file >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && majorDebugEcho "sfdisk failed in (${FUNCNAME[0]})"
}
# $1 is the name of the disk drive
# $2 is the name of file to load from.
applySgdiskPartitions() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local escape_disk=$(escapeItem $disk)
    local diskguid=$(awk -F: "/^$escape_disk:/{print \$3}" $file)
    sgdisk -Z $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to restore partitions (sgdisk -Z) (${FUNCNAME[0]})\n   Args Passed: $*"
    sgdisk -U $diskguid $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to restore partitions (sgdisk -U) (${FUNCNAME[0]})\n   Args Passed: $*"
    local parts=""
    local part=""
    local part_number=""
    local escape_part=""
    local partstart=""
    local partend=""
    local parttype=""
    local partcode=""
    local partname=""
    local awk_part_vars=""
    getPartitions "$disk"
    for part in $parts; do
        escape_part=$(escapeItem $part)
        getParititionNumber "$part"
        awk_part_vars=$(awk -F: "/^$escape_part:/{printf(\"%d %d %d %d\",\$3,\$4,\$5,\$6)}" $file)
        read partcode partstart partend partname <<< $awk_part_vars
        parttype=$(awk -F: "/^part:$part_number:/{print \$5}" $file)
        sgdisk -n $part_number:$partstart:$partend $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to restore partition (sgdisk -n) (${FUNCNAME[0]})\n   Args Passed: $*"
        sgdisk -c $part_number:$partname $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to restore partition (sgdisk -c) (${FUNCNAME[0]})\n   Args Passed: $*"
        sgdisk -t $part_number:$parttype $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to restore partition (sgdisk -t) (${FUNCNAME[0]})\n   Args Passed: $*"
        sgdisk -u $part_number:$partcode $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Failed to restore partition (sgdisk -u) (${FUNCNAME[0]})\n   Args Passed: $*"
    done
}
# $1 is the name of the disk drive
# $2 is name of file to load from.
restoreSfdiskPartitions() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})\n   Args Passed: $*"
    applySfdiskPartitions "$disk" "$file"
    fdisk $disk < /usr/share/fog/lib/EOFRESTOREPART >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && majorDebugEcho "fdisk failed in (${FUNCNAME[0]})"
}
# $1 is the name of the disk drive
# $2 is name of file to restore from.
restoreSgdiskPartitions() {
    local disk="$1"
    local file="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to restore from (${FUNCNAME[0]})\n   Args Passed: $*"
    applySgdiskPartitions "$disk" "$file"
}
# $1 is the name of the disk drive
hasExtendedPartition() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    sfdisk -d $disk 2>/dev/null | egrep '(Id|type)=\ *[5f]' | wc -l
    [[ ! $? -eq 0 ]] && majorDebugEcho "sfdisk failed in (${FUNCNAME[0]})"
}
# $1 is the name of the partition device (e.g. /dev/sda3)
partitionHasEBR() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local part_number=0
    local disk=""
    local parttype=""
    getDiskFromPartition "$part"
    getPartitionNumber "$part"
    getPartType "$part"
    hasEBR=0
    [[ $part_number -ge 5 ]] && hasEBR=1
    [[ $parttype == +(0x5|0xf) ]] && hasEBR=1
}
# $1 is the name of the partition device (e.g. /dev/sda3)
# $2 is the name of the file to save to (e.g. /net/dev/foo/d1p4.ebr)
saveEBR() {
    local part="$1"
    local file="$2"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local disk=""
    getDiskFromPartition "$part"
    local table_type=""
    getPartitionTableType "$disk"
    [[ $table_type != MBR ]] && return
    local hasEBR=0
    partitionHasEBR "$part"
    [[ ! $hasEBR -gt 0 ]] && return
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
            handleError "Could not backup EBR (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
}
# $1 = DriveName  (e.g. /dev/sdb)
# $2 = DriveNumber  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
saveAllEBRs() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local parts=""
    local part=""
    local part_number=0
    local ebrfilename=""
    getPartitions "$disk"
    for part in $parts; do
        getPartitionNumber "$part"
        EBRFileName "$imagePath" "$disk_number" "$part_number"
        saveEBR "$part" "$ebrfilename"
    done
}
# $1 is the name of the partition device (e.g. /dev/sda3)
# $2 is the name of the file to restore from (e.g. /net/foo/d1p4.ebr)
restoreEBR() {
    local part="$1"
    local file="$2"
    [[ -z $part ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to restore from passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local disk=""
    local table_type=""
    getDiskFromPartition "$part"
    getPartitionTableType "$disk"
    [[ $table_type != MBR ]] && return
    local hasEBR=0
    partitionHasEBR "$part"
    [[ ! $hasEBR -gt 0 ]] && return
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
            handleError "Could not reload EBR data (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
}
# $1 = DriveName  (e.g. /dev/sdb)
# $2 = DriveNumber  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
# $4 = ImagePartitionType  (e.g. all, mbr, 1, 2, 3, etc.)
restoreAllEBRs() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    local imgPartitionType="$4"
    local ebffilename=""
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imgPartitionType ]] && handleError "No partition type passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local parts=""
    local part=""
    local part_number=0
    local ebrfilename=""
    getPartitions "$disk"
    for part in $parts; do
        getPartitionNumber "$part"
        [[ $imgPartitionType != all && $imgPartitionType != $part_number ]] && continue
        EBRFileName "$imagePath" "$disk_number" "$part_number"
        restoreEBR "$part" "$ebrfilename"
    done
    runPartprobe "$disk"
}
# $1 is the name of the partition device (e.g. /dev/sda3)
partitionIsSwap() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local fstype=""
    fsTypeSetting "$part"
    is_swap=0
    [[ $fstype == swap ]] && is_swap=1
}
# $1 is the location of the file to store uuids in
# $2 is the partition device name
saveSwapUUID() {
    local file="$1"
    local part="$2"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to receive from passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local is_swap=0
    partitionIsSwap "$part"
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
    local disk_number="$2"
    local imagePath="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local swapuuidfilename=""
    swapUUIDFileName "$imagePath" "$disk_number"
    local parts=""
    local part=""
    local is_swap=0
    getPartitions "$disk"
    for part in $parts; do
        partitionIsSwap "$part"
        [[ $is_swap -eq 0 ]] && continue
        saveSwapUUID "$swapuuidfilename" "$part"
    done
}
# $1 is the location of the file uuids are stored in
# $2 is the partition device name
makeSwapSystem() {
    local file="$1"
    local part="$2"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local uuid=""
    local option=""
    local disk=""
    getDiskFromPartition "$part"
    local parttype=0
    local hasgpt=""
    local escape_part=$(escapeItem $part)
    hasGPT "$disk"
    case $hasgpt in
        1)
            uuid=$(awk "/^$escape_part/{print \$2}" $file)
            [[ -n $uuid ]] && parttype=82
            ;;
        0)
            parttype=$(sfdisk -d $disk 2>/dev/null | awk -F[,=] "/^$escape_part/{print \$6}")
            ;;
    esac
    [[ ! $parttype -eq 82 ]] && return
    [[ -n $uuid ]] && option="-U $uuid"
    dots "Restoring swap partition"
    mkswap $option $part >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not create swap on $part (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    debugPause
}
# $1 is the partition device (e.g. /dev/sda1)
# $2 is the new desired size in 1024 (1k) blocks
# $3 is the image path (e.g. /net/dev/foo)
resizeSfdiskPartition() {
    local part="$1"
    local size="$2"
    local imagePath="$3"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $size ]] && handleError "No desired size passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local disk=""
    getDiskFromPartition "$part"
    local tmp_file="/tmp/sfdisk.$$"
    local tmp_file2="/tmp/sfdisk2.$$"
    saveSfdiskPartitions "$disk" "$tmp_file"
    processSfdisk "$tmp_file" resize "$part" "$size" > "$tmp_file2"
    if [[ $ismajordebug -gt 0 ]]; then
        majorDebugEcho "Trying to fill the disk with these partitions:"
        cat $tmp_file2
        majorDebugPause
    fi
    applySfdiskPartitions "$disk" "$tmp_file2"
    local sfdiskminimumpartitionfilename=""
    sfdiskMinimumPartitionFileName "$imagePath" 1
    saveSfdiskPartitions "$disk" "$imagePath"
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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "No file to use passed (${FUNCNAME[0]})\n   Args Passed: $*"
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
    [[ -z $data ]] && handleError "No data passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $action ]] && handleError "No action passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $target ]] && handleError "Device (disk or partition) not passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $size ]] && handleError "No desired size passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local minstart=$(awk -F'[ ,]+' '/start/{if ($4) print $4}' $data | sort -n | head -1)
    local chunksize=""
    getPartBlockSize "$disk" "chunksize"
    case $osid in
        [1-2])
            [[ -z $minstart ]] && chunksize=512
            [[ -z $minstart ]] && minstart=63
            ;;
    esac
    local awkArgs="-v CHUNK_SIZE=$chunksize -v MIN_START=$minstart"
    awkArgs="$awkArgs -v action=$action -v target=$target -v sizePos=$size"
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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
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
    [[ -z $gpttype && -z $mbrtype ]] && handleError "Cannot determine partition type (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -n $gpttype && -n $mbrtype ]] && table_type="$gpttype-$mbrtype"
    [[ -n $gpttype && -z $mbrtype ]] && table_type="$gpttype"
    [[ -z $gpttype && -n $mbrtype ]] && table_type="$mbrtype"
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
    local disk_number="$2"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    table_type="MBR"
    local mbrfilename=""
    MBRFileName "$imagePath" "$disk_number" "mbrfilename"
    [[ ! -r $mbrfilename ]] && return
    local tmpfile="/tmp/gptsig"
    dd skip=512 bs=1 if=$mbrfilename of=$tmpfile count=8 >/dev/null 2>&1
    touch $tmpfile
    local gptsig=$(cat $tmpfile)
    [[ $gptsig == "EFI PART" ]] && table_type="GPT"
}
# $1 : device name of drive
hasHybridMBR() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local mbr=$(gdisk -l $disk | awk '/^\ *MBR:/{print $2}')
    [[ $mbr == hybrid ]] && echo 1 || echo 0
}
# $1 : device name of drive
hasGPT() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local gpt=$(gdisk -l $disk | awk -F'[(: )]' '/GPT:/ {print $5}')
    [[ $gpt == present ]] &&  hasgpt=1
    [[ $gpt == not ]] && hasgpt=0
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
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $size ]] && handleError "No size passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local disk=""
    local table_type=""
    getDiskFromPartition "$part"
    getPartitionTableType "$disk"
    case $table_type in
        MBR|GPT)
            local sfdiskoriginalpartitionfilename=""
            local sfdisklegacyoriginalpartitionfilename=""
            resizeSfdiskPartition "$part" "$size" "$imagePath"
            ;;
        *)
            handleError "Unexpected partition table type: $table_type (${FUNCNAME[0]})\n   Args Passed: $*"
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
    local disk_number="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local table_type=""
    getPartitionTableType "$disk"
    case $table_type in
        MBR|GPT)
            local sfdiskoriginalpartitionfilename=""
            sfdiskOriginalPartitionFileName "$imagePath" "$disk_number"
            saveSfdiskPartitions "$disk" "$sfdiskoriginalpartitionfilename"
            ;;
        GPT-MBR)
            echo "Failed"
            debugPause
            runFixparts "$disk"
            dots "Retrying to save partition table"
            saveOriginalPartitions "$disk" "$imagePath" "$disk_number"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Unexpected partition table type: $table_type (${FUNCNAME[0]})\n   Args Passed: $*"
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
    local disk_number="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local table_type=""
    getPartitionTableType "$disk"
    case $table_type in
        MBR|GPT)
            local sfdiskoriginalpartitionfilename=""
            local sfdisklegacyoriginalpartitionfilename=""
            local sgdiskoriginalpartitionfilename=""
            local cmdtorun='restoreSfdiskPartitions'
            sfdiskOriginalPartitionFileName "$imagePath" "$disk_number"
            sfdiskLegacyOriginalPartitionFileName "$imagePath" "$disk_number"
            sgdiskOriginalPartitionFileName "$imagePath" "$disk_number"
            local filename="$sfdiskoriginalpartitionfilename"
            [[ ! -r $filename ]] && filename="$sfdisklegacyoriginalpartitionfilename"
            [[ ! -r $filename ]] && filename="$sgdiskoriginalpartitionfilename" && cmdtorun='restoreSgdiskPartitions'
            [[ ! -r $filename ]] && handleError "Failed to find a restore file (${FUNCNAME[0]})\n   Args Passed: $*"
            $cmdtorun "$disk" "$filename"
            ;;
        *)
            handleError "Unexpected partition table type: $table_type (${FUNCNAME[0]})\n   Args Passed: $*"
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
    local disk_number="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local fixed_size_file=""
    fixedSizePartitionsFileName "$imagePath" "$disk_number"
    [[ -r $fixed_size_file ]] && fixed_size_partitions=$(cat $fixed_size_file)
    local table_type=""
    getDesiredPartitionTableType "$imagePath" "$disk_number"
    local sfdiskoriginalpartitionfilename=""
    local sfdisklegacyoriginalpartitionfilename=""
    local sgdiskoriginalpartitionfilename=""
    case $table_type in
        MBR|GPT)
            sfdiskOriginalPartitionFileName "$imagePath" "$disk_number"
            sfdiskLegacyOriginalPartitionFileName "$imagePath" "$disk_number"
            sgdiskOriginalPartitionFileName "$imagePath" "$disk_number"
            local filename="$sfdiskoriginalpartitionfilename"
            local cmdtorun='fillSfdiskWithPartitions'
            [[ ! -r $filename ]] && filename="$sfdisklegacyoriginalpartitionfilename"
            [[ ! -r $filename ]] && filename="$sgdiskoriginalpartitionfilename"
            [[ $filename == $sgdiskoriginalpartitionfilename ]] && cmdtorun='fillSgdiskWithPartitions'
            [[ ! -r $filename ]] && handleError "Failed to find a restore file (${FUNCNAME[0]})\n   Args Passed: $*"
            $cmdtorun "$disk" "$filename" "$fixed_size_partitions"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Unexpected partition table type: $table_type (${FUNCNAME[0]})\n   Args Passed: $*"
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
    local disk_number="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local table_type=""
    getDesiredPartitionTableType "$imagePath" "$disk_number"
    local filename=""
    local sfdiskoriginalpartitionfilename=""
    local sfdisklegacyoriginalpartitionfilename=""
    local sgdiskoriginalpartitionfilename=""
    do_fill=1
    case $table_type in
        MBR|GPT)
            sfdiskOriginalPartitionFileName "$imagePath" "$disk_number"
            sfdiskLegacyOriginalPartitionFileName "$imagePath" "$disk_number"
            sgdiskOriginalPartitionFileName "$imagePath" "$disk_number"
            filename="$sfdiskoriginalpartitionfilename"
            [[ ! -r $filename ]] && filename="$sfdisklegacyoriginalpartitionfilename"
            [[ ! -r $filename ]] && filename="$sgdiskoriginalpartitionfilename"
            [[ ! -r $filename ]] && do_fill=0
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
    local disk_number="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local table_type=""
    getDesiredPartitionTableType "$imagePath" "$disk_number"
    echo "Current partition table:"
    case $table_type in
        MBR|GPT)
            sfdisk -d $disk
            ;;
    esac
}
