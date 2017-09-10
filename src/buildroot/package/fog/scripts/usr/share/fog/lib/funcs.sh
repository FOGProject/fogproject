#!/bin/bash
. /usr/share/fog/lib/partition-funcs.sh
REG_LOCAL_MACHINE_XP="/ntfs/WINDOWS/system32/config/system"
REG_LOCAL_MACHINE_7="/ntfs/Windows/System32/config/SYSTEM"
# 1 to turn on massive debugging of partition table restoration
[[ -z $ismajordebug ]] && ismajordebug=0
#If a sub shell gets invoked and we lose kernel vars this will reimport them
for var in $(cat /proc/cmdline); do
	var=$(echo "${var}" | awk -F= '{name=$1; gsub(/[+][_][+]/," ",$2); gsub(/"/,"\\\"", $2); value=$2; if (length($2) == 0 || $0 !~ /=/) {print "";} else {printf("%s=%s", name, value)}}')
    [[ -z $var ]] && continue;
    eval "export ${var}"
done
### If USB Boot device we need a way to get the kernel args properly
[[ $boottype == usb && -f /tmp/hinfo.txt ]] && . /tmp/hinfo.txt
# Below Are non parameterized functions
# These functions will run without any arguments
#
# Clears thes creen unless its a debug task
clearScreen() {
    case $isdebug in
        [Yy][Ee][Ss]|[Yy])
            clear
            ;;
    esac
}
# Displays the nice banner along with the running version
displayBanner() {
    version=$(curl -Lks ${web}service/getversion.php 2>/dev/null)
    echo "   =================================="
    echo "   ===        ====    =====      ===="
    echo "   ===  =========  ==  ===   ==   ==="
    echo "   ===  ========  ====  ==  ====  ==="
    echo "   ===  ========  ====  ==  ========="
    echo "   ===      ====  ====  ==  ========="
    echo "   ===  ========  ====  ==  ===   ==="
    echo "   ===  ========  ====  ==  ====  ==="
    echo "   ===  =========  ==  ===   ==   ==="
    echo "   ===  ==========    =====      ===="
    echo "   =================================="
    echo "   ===== Free Opensource Ghost ======"
    echo "   =================================="
    echo "   ============ Credits ============="
    echo "   = https://fogproject.org/Credits ="
    echo "   =================================="
    echo "   == Released under GPL Version 3 =="
    echo "   =================================="
    echo "   Version: $version"
}
# Gets all system mac addresses except for loopback
getMACAddresses() {
    read ifaces <<< $(/sbin/ip -4 -o addr | awk -F'([ /])+' '/global/ {print $2}' | tr '[:space:]' '|' | sed -e 's/^[|]//g' -e 's/[|]$//g')
    read mac_addresses <<< $(/sbin/ip -0 -o addr | awk "/$ifaces/ {print \$11}" | tr '[:space:]' '|' | sed -e 's/^[|]//g' -e 's/[|]$//g')
    echo $mac_addresses
}
# Verifies that there is a network interface
verifyNetworkConnection() {
    dots "Verifying network interface configuration"
    local count=$(/sbin/ip addr | awk -F'[ /]+' '/global/{print $3}' | wc -l)
    if [[ -z $count || $count -lt 1 ]]; then
        echo "Failed"
        debugPause
        handleError "No network interfaces found (${FUNCNAME[0]})\n   Args Passed: $*"
    fi
    echo "Done"
    debugPause
}
# Verifies that the OS is valid for resizing
validResizeOS() {
    [[ $osid != @([1-2]|4|[5-7]|9|50|51) ]] && handleError " * Invalid operating system id: $osname ($osid) (${FUNCNAME[0]})\n   Args Passed: $*"
}
# Gets the information from the system for inventory
doInventory() {
    sysman=$(dmidecode -s system-manufacturer)
    sysproduct=$(dmidecode -s system-product-name)
    sysversion=$(dmidecode -s system-version)
    sysserial=$(dmidecode -s system-serial-number)
    sysuuid=$(dmidecode -s system-uuid)
    sysuuid=${sysuuid,,}
    systype=$(dmidecode -t 3 | grep Type:)
    biosversion=$(dmidecode -s bios-version)
    biosvendor=$(dmidecode -s bios-vendor)
    biosdate=$(dmidecode -s bios-release-date)
    mbman=$(dmidecode -s baseboard-manufacturer)
    mbproductname=$(dmidecode -s baseboard-product-name)
    mbversion=$(dmidecode -s baseboard-version)
    mbserial=$(dmidecode -s baseboard-serial-number)
    mbasset=$(dmidecode -s baseboard-asset-tag)
    cpuman=$(dmidecode -s processor-manufacturer)
    cpuversion=$(dmidecode -s processor-version)
    cpucurrent=$(dmidecode -t 4 | grep 'Current Speed:' | head -n1)
    cpumax=$(dmidecode -t 4 | grep 'Max Speed:' | head -n1)
    mem=$(cat /proc/meminfo | grep MemTotal | tr -d \\0)
    hdinfo=$(hdparm -i $hd 2>/dev/null | grep Model=)
    caseman=$(dmidecode -s chassis-manufacturer)
    casever=$(dmidecode -s chassis-version)
    caseserial=$(dmidecode -s chassis-serial-number)
    casesasset=$(dmidecode -s chassis-asset-tag)
    sysman64=$(echo $sysman | base64)
    sysproduct64=$(echo $sysproduct | base64)
    sysversion64=$(echo $sysversion | base64)
    sysserial64=$(echo $sysserial | base64)
    sysuuid64=$(echo $sysuuid | base64)
    systype64=$(echo $systype | base64)
    biosversion64=$(echo $biosversion | base64)
    biosvendor64=$(echo $biosvendor | base64)
    biosdate64=$(echo $biosdate | base64)
    mbman64=$(echo $mbman | base64)
    mbproductname64=$(echo $mbproductname | base64)
    mbversion64=$(echo $mbversion | base64)
    mbserial64=$(echo $mbserial | base64)
    mbasset64=$(echo $mbasset | base64)
    cpuman64=$(echo $cpuman | base64)
    cpuversion64=$(echo $cpuversion | base64)
    cpucurrent64=$(echo $cpucurrent | base64)
    cpumax64=$(echo $cpumax | base64)
    mem64=$(echo $mem | base64)
    hdinfo64=$(echo $hdinfo | base64)
    caseman64=$(echo $caseman | base64)
    casever64=$(echo $casever | base64)
    caseserial64=$(echo $caseserial | base64)
    casesasset64=$(echo $casesasset | base64)
}
# Gets the location of the SAM registry if found
getSAMLoc() {
    local path=""
    local paths="/ntfs/WINDOWS/system32/config/SAM /ntfs/Windows/System32/config/SAM"
    for path in $paths; do
        [[ ! -f $path ]] && continue
        sam="$path" && break
    done
}
# Appends dots to the end of string up to 50 characters.
# Makes the output more aligned and organized.
#
# $1 String to append dots to
dots() {
    local str="$*"
    [[ -z $str ]] && handleError "No string passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local pad=$(printf "%0.1s" "."{1..50})
    printf " * %s%*.*s" "$str" 0 $((50-${#str})) "$pad"
}
# Enables write caching on the disk passed
# If the disk does not support write caching this does nothing
#
# $1 is the drive
enableWriteCache()  {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    wcache=$(hdparm -W $disk 2>/dev/null | tr -d '[[:space:]]' | awk -F= '/.*write-caching=/{print $2}')
    if [[ -z $wcache || $wcache == notsupported ]]; then
        echo " * Write caching not supported"
        debugPause
        return
    fi
    dots "Enabling write cache"
    hdparm -W1 $disk >/dev/null 2>&1
    case $? in
        0)
            echo "Enabled"
            ;;
        *)
            echo "Failed"
            debugPause
            handleWarning "Could not set caching status (${FUNCNAME[0]})"
            return
            ;;
    esac
    debugPause
}
# Expands partitions, as needed/capable
#
# $1 is the partition
# $2 is the fixed size partitions (can be empty)
expandPartition() {
    local part="$1"
    local fixed="$2"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local disk=""
    local part_number=0
    getDiskFromPartition "$part"
    getPartitionNumber "$part"
    local is_fixed=$(echo $fixed | awk "/(^$part_number:|:$part_number:|:$part_number$|^$part_number$)/{print 1}")
    if [[ $is_fixed -eq 1 ]]; then
        echo " * Not expanding ($part) fixed size"
        debugPause
        return
    fi
    local fstype=""
    fsTypeSetting $part
    case $fstype in
        ntfs)
            dots "Resizing $fstype volume ($part)"
            yes | ntfsresize $part -fbP >/tmp/tmpoutput.txt 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not resize $part (${FUNCNAME[0]})\n   Info: $(cat /tmp/tmpoutput.txt)\n   Args Passed: $*"
                    ;;
            esac
            debugPause
            resetFlag "$part"
            ;;
        extfs)
            dots "Resizing $fstype volume ($part)"
            e2fsck -fp $part >/tmp/e2fsck.txt 2>&1
            case $? in
                0)
                    ;;
                *)
                    e2fsck -fy $part >>/tmp/e2fsck.txt 2>&1
                    if [[ $? -gt 0 ]]; then
                        echo "Failed"
                        debugPause
                        handleError "Could not check before resize (${FUNCNAME[0]})\n   Info: $(cat /tmp/e2fsck.txt)\n   Args Passed: $*"
                    fi
                    ;;
            esac
            resize2fs $part >/tmp/resize2fs.txt 2>&1
            case $? in
                0)
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not resize $part (${FUNCNAME[0]})\n   Info: $(cat /tmp/resize2fs.txt)\n   Args Passed: $*"
                    ;;
            esac
            e2fsck -fp $part >/tmp/e2fsck.txt 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    e2fsck -fy $part >>/tmp/e2fsck.txt 2>&1
                    if [[ $? -gt 0 ]]; then
                        echo "Failed"
                        debugPause
                        handleError "Could not check after resize (${FUNCNAME[0]})\n   Info: $(cat /tmp/e2fsck.txt)\n   Args Passed: $*"
                    fi
                    echo "Done"
                    ;;
            esac
            ;;
        *)
            echo " * Not expanding ($part -- $fstype)"
            debugPause
            ;;
    esac
    debugPause
    runPartprobe "$disk"
}
# Gets the filesystem type of the partition passed
#
# $1 is the partition
fsTypeSetting() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local blk_fs=$(blkid -po udev $part | awk -F= /FS_TYPE=/'{print $2}')
    case $blk_fs in
        btrfs)
            fstype="btrfs"
            ;;
        ext[2-4])
            fstype="extfs"
            ;;
        hfsplus)
            fstype="hfsp"
            ;;
        ntfs)
            fstype="ntfs"
            ;;
        swap)
            fstype="swap"
            ;;
        vfat)
            fstype="fat"
            ;;
        xfs)
            fstype="xfs"
            ;;
        *)
            fstype="imager"
            ;;
    esac
}
# Gets the disk part table UUID
#
# $1 is the disk
getDiskUUID() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    diskuuid=$(blkid -po udev $disk | awk -F= '/PART_TABLE_UUID=/{print $2}')
}
# Gets the partition entry name
#
# $1 is the partition
getPartName() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    partname=$(blkid -po udev $part | awk -F= '/PART_ENTRY_NAME=/{print $2}')
}
# Gets the partition entry type
#
# $1 is the partition
getPartType() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    parttype=$(blkid -po udev $part | awk -F= '/PART_ENTRY_TYPE=/{print $2}')
}
# Gets the partition fs UUID
#
# $1 is the partition
getPartFSUUID() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    partfsuuid=$(blkid -po udev $part | awk -F= '/FS_UUID=/{print $2}')
}
# Gets the partition entry UUID
#
# $1 is the partition
getPartUUID() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    partuuid=$(blkid -po udev $part | awk -F= '/PART_ENTRY_UUID=/{print $2}')
}
# Gets the entry schemed (dos, gpt, etc...)
#
# $1 is the partition
getPartitionEntryScheme() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    scheme=$(blkid -po udev $part | awk -F= '/PART_ENTRY_SCHEME=/{print $2}')
}
# Checks if the partition is dos extended (mbr with logical parts)
#
# $1 is the partition
partitionIsDosExtended() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local scheme=""
    getPartitionEntryScheme "$part"
    debugEcho "scheme = $scheme" 1>&2
    case $scheme in
        dos)
            echo "no"
            ;;
        *)
            local parttype=""
            getPartType "$part"
            debugEcho "parttype = $parttype" 1>&2
            [[ $parttype == +(0x5|0xf) ]] && echo "yes" || echo "no"
            ;;
    esac
    debugPause
}
# Returns the block size of a partition
#
# $1 is the partition
# $2 is the variable to set
getPartBlockSize() {
    local part="$1"
    local varVar="$2"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $varVar ]] && handleError "No variable to set passed (${FUNCNAME[0]})\n   Args Passed: $*"
    printf -v "$varVar" $(blockdev --getpbsz $part)
}
# Prepares location info for uploads
#
# $1 is the image path
prepareUploadLocation() {
    local imagePath="$1"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    dots "Preparing backup location"
    if [[ ! -d $imagePath ]]; then
        mkdir -p $imagePath >/dev/null 2>&1
        case $? in
            0)
                ;;
            *)
                echo "Failed"
                debugPause
                handleError "Failed to create image capture path (${FUNCNAME[0]})\n   Args Passed: $*"
                ;;
        esac
    fi
    echo "Done"
    debugPause
    dots "Setting permission on $imagePath"
    chmod -R 777 $imagePath >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Failed to set permissions (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    debugPause
    dots "Removing any pre-existing files"
    rm -Rf $imagePath/* >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not clean files (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    debugPause
}
# Shrinks partitions for upload (resizable images only)
#
# $1 is the partition
# $2 is the fstypes file location
# $3 is the fixed partition numbers empty ok
shrinkPartition() {
    local part="$1"
    local fstypefile="$2"
    local fixed="$3"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $fstypefile ]] && handleError "No type file passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local disk=""
    local part_number=0
    getDiskFromPartition "$part"
    getPartitionNumber "$part"
    local is_fixed=$(echo $fixed | awk "/(^$part_number:|:$part_number:|:$part_number$|^$part_number$)/{print 1}")
    if [[ $is_fixed -eq 1 ]]; then
        echo " * Not shrinking ($part) fixed size"
        debugPause
        return
    fi
    local fstype=""
    fsTypeSetting "$part"
    echo "$part $fstype" >> $fstypefile
    local size=0
    local tmpoutput=""
    local sizentfsresize=0
    local sizeextresize=0
    local tmp_success=""
    local test_string=""
    local do_resizefs=0
    local do_resizepart=0
    local extminsize=0
    local block_size=0
    local sizeextresize=0
    local adjustedfdsize=0
    local part_block_size=0
    case $fstype in
        ntfs)
            local label=$(getPartitionLabel "$part")
            if [[ $label =~ [Rr][Ee][Cc][Oo][Vv][Ee][Rr][Yy] ]]; then
                echo "$(cat "$imagePath/d1.fixed_size_partitions" | tr -d \\0):${part_number}" > "$imagePath/d1.fixed_size_partitions"
                echo " * Not shrinking ($part) recovery partition"
                debugPause
                return
            fi
            if [[ $label =~ [Rr][Ee][Ss][Ee][Rr][Vv][Ee][Dd] ]]; then
                echo "$(cat "$imagePath/d1.fixed_size_partitions" | tr -d \\0):${part_number}" > "$imagePath/d1.fixed_size_partitions"
                echo " * Not shrinking ($part) reserved partitions"
                debugPause
                return
            fi
            ntfsresize -fivP $part >/tmp/tmpoutput.txt 2>&1
            if [[ ! $? -eq 0 ]]; then
                echo " * Not shrinking ($part) trying fixed size"
                debugPause
                echo "$(cat "$imagePath/d1.fixed_size_partitions" | tr -d \\0):${part_number}" > "$imagePath/d1.fixed_size_partitions"
                return
                #handleError " * (${FUNCNAME[0]})\n    Args Passed: $*\n\nFatal Error, unable to find size data out on $part. Cmd: ntfsresize -f -i -v -P $part"
            fi
            tmpoutput=$(cat /tmp/tmpoutput.txt | tr -d \\0)
            size=$(cat /tmp/tmpoutput.txt | tr -d \\0 | sed -n 's/.*you might resize at\s\+\([0-9]\+\).*$/\1/pi')
            [[ -z $size ]] && handleError " * (${FUNCNAME[0]})\n   Args Passed: $*\n\nFatal Error, Unable to determine possible ntfs size\n * To better help you debug we will run the ntfs resize\n\t but this time with full output, please wait!\n\t $(cat /tmp/tmpoutput.txt | tr -d \\0)"
            rm /tmp/tmpoutput.txt >/dev/null 2>&1
            local sizeadd=$(calculate "${percent}/100*${size}/1024")
            sizentfsresize=$(calculate "${size}/1024+${sizeadd}")
            echo " * Possible resize partition size: ${sizentfsresize}k"
            dots "Running resize test $part"
            yes | ntfsresize -fns ${sizentfsresize}k ${part} >/tmp/tmpoutput.txt 2>&1
            local ntfsstatus="$?"
            tmpoutput=$(cat /tmp/tmpoutput.txt | tr -d \\0)
            test_string=$(cat /tmp/tmpoutput.txt | egrep -io "(ended successfully|bigger than the device size|volume size is already OK)" | tr -d '[[:space:]]' | tr -d \\0)
            echo "Done"
            debugPause
            rm /tmp/tmpoutput.txt >/dev/null 2>&1
            case $test_string in
                endedsuccessfully)
                    echo " * Resize test was successful"
                    do_resizefs=1
                    do_resizepart=1
                    ntfsstatus=0
                    ;;
                biggerthanthedevicesize)
                    echo " * Not resizing filesystem $part (part too small)"
                    echo "$(cat ${imagePath}/d1.fixed_size_partitions | tr -d \\0):${part_number}" > "$imagePath/d1.fixed_size_partitions"
                    ntfsstatus=0
                    ;;
                volumesizeisalreadyOK)
                    echo " * Not resizing filesystem $part (already OK)"
                    do_resizepart=1
                    ntfsstatus=0
                    ;;
            esac
            [[ ! $ntfsstatus -eq 0 ]] && handleError "Resize test failed!\n    Info: $tmpoutput\n    (${FUNCNAME[0]})\n    Args Passed: $*"
            if [[ $do_resizefs -eq 1 ]]; then
                debugPause
                dots "Resizing filesystem"
                yes | ntfsresize -fs ${sizentfsresize}k ${part} >/tmp/output.txt 2>&1
                case $? in
                    0)
                        echo "Done"
                        ;;
                    *)
                        echo "Failed"
                        debugPause
                        handleError "Could not resize disk (${FUNCNAME[0]})\n   Info: $(cat /tmp/output.txt)\n   Args Passed: $*"
                        ;;
                esac
            fi
            if [[ $do_resizepart -eq 1 ]]; then
                debugPause
                dots "Resizing partition $part"
                getPartBlockSize "$part" "part_block_size"
                case $osid in
                    [1-2]|4)
                        resizePartition "$part" "$(calculate "$sizentfsresize*1024")" "$imagePath"
                        [[ $osid -eq 2 ]] && correctVistaMBR "$disk"
                        ;;
                    [5-7]|9)
                        [[ $part_number -eq $win7partcnt ]] && part_start=$(blkid -po udev $part 2>/dev/null | awk -F= '/PART_ENTRY_OFFSET=/{printf("%.0f\n",$2*'$part_block_size'/1000)}') || part_start=1048576
                        if [[ -z $part_start || $part_start -lt 1 ]]; then
                            echo "Failed"
                            debugPause
                            handleError "Unable to determine disk start location (${FUNCNAME[0]})\n   Args Passed: $*"
                        fi
                        adjustedfdsize=$(calculate "${sizentfsresize}*1024")
                        resizePartition "$part" "$adjustedfdsize" "$imagePath"
                        ;;
                esac
                echo "Done"
            fi
            resetFlag "$part"
            ;;
        extfs)
            dots "Checking $fstype volume ($part)"
            e2fsck -fp $part >/tmp/e2fsck.txt 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "e2fsck failed to check $part (${FUNCNAME[0]})\n   Info: $(cat /tmp/e2fsck.txt)\n   Args Passed: $*"
                    ;;
            esac
            debugPause
            extminsize=$(resize2fs -P $part 2>/dev/null | awk -F': ' '{print $2}')
            block_size=$(dumpe2fs -h $part 2>/dev/null | awk /^Block\ size:/'{print $3}')
            size=$(calculate "${extminsize}*${block_size}")
            local sizeadd=$(calculate "${percent}/100*${size}")
            sizeextresize=$(calculate "${size}+${sizeadd}")
            [[ -z $sizeextresize || $sizeextresize -lt 1 ]] && handleError "Error calculating the new size of extfs ($part) (${FUNCNAME[0]})\n   Args Passed: $*"
            dots "Shrinking $fstype volume ($part)"
            resize2fs $part -M >/tmp/resize2fs.txt 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not shrink $fstype volume ($part) (${FUNCNAME[0]})\n   Info: $(cat /tmp/resize2fs.txt)\n   Args Passed: $*"
                    ;;
            esac
            debugPause
            dots "Shrinking $part partition"
            resizePartition "$part" "$sizeextresize" "$imagePath"
            echo "Done"
            debugPause
            e2fsck -fp $part >/tmp/e2fsck.txt 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    e2fsck -fy $part >>/tmp/e2fsck.txt 2>&1
                    if [[ $? -gt 0 ]]; then
                        echo "Failed"
                        debugPause
                        handleError "Could not check expanded volume ($part) (${FUNCNAME[0]})\n   Info: $(cat /tmp/e2fsck.txt)\n   Args Passed: $*"
                    fi
                    echo "Done"
                    ;;
            esac
            ;;
        *)
            echo " * Not shrinking ($part $fstype)"
            ;;
    esac
    debugPause
}
# Resets the dirty bits on a partition
#
# $1 is the part
resetFlag() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local fstype=""
    fsTypeSetting "$part"
    case $fstype in
        ntfs)
            dots "Clearing ntfs flag"
            ntfsfix -b -d $part >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    ;;
            esac
            ;;
    esac
}
# Counts the partitions containing the fs type as passed
#
# $1 is the disk
# $2 is the part type to look for
# $3 is the variable to store the count into. This is
#    a variable variable
countPartTypes() {
    local disk="$1"
    local parttype="$2"
    local varVar="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $parttype ]] && handleError "No partition type passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $varVar ]] && handleError "No variable to set passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local count=0
    local fstype=""
    local parts=""
    local part=""
    getPartitions "$disk"
    for part in $parts; do
        fsTypeSetting "$part"
        case $fstype in
            $parttype)
                let count+=1
                ;;
        esac
    done
    printf -v "$varVar" "$count"
}
# Writes the image to the disk
#
# $1 = Source File
# $2 = Target
# $3 = mc task or not (not required)
writeImage()  {
    local file="$1"
    local target="$2"
    local mc="$3"
    [[ -z $target ]] && handleError "No target to place image passed (${FUNCNAME[0]})\n   Args Passed: $*"
    mkfifo /tmp/pigz1
    case $mc in
        yes)
            if [[ -z $mcastrdv ]]; then
                udp-receiver --nokbd --portbase $port --ttl 32 --mcast-rdv-address $storageip 2>/dev/null >/tmp/pigz1 &
            else
                udp-receiver --nokbd --portbase $port --ttl 32 --mcast-rdv-address $mcastrdv 2>/dev/null >/tmp/pigz1 &
            fi
            ;;
        *)
            [[ -z $file ]] && handleError "No source file passed (${FUNCNAME[0]})\n   Args Passed: $*"
            cat $file >/tmp/pigz1 &
            ;;
    esac
    local format=$imgLegacy
    [[ -z $format ]] && format=$imgFormat
    case $format in
        5|6)
            # ZSTD Compressed image.
            echo " * Imaging using Partclone (zstd)"
            zstdmt -dc </tmp/pigz1 | partclone.restore -n "Storage Location $storage, Image name $img" --ignore_crc -O ${target} -Nf 1
            ;;
        3|4)
            # Uncompressed partclone
            echo " * Imaging using Partclone (uncompressed)"
            cat </tmp/pigz1 | partclone.restore -n "Storage Location $storage, Image name $img" --ignore_crc -O ${target} -Nf 1
            # If this fails, try from compressed form.
            #[[ ! $? -eq 0 ]] && zstdmt -dc </tmp/pigz1 | partclone.restore --ignore_crc -O ${target} -N -f 1 || true
            ;;
        1)
            # Partimage
            echo " * Imaging using Partimage (gzip)"
            #zstdmt -dc </tmp/pigz1 | partimage restore ${target} stdin -f3 -b 2>/tmp/status.fog
            pigz -dc </tmp/pigz1 | partimage restore ${target} stdin -f3 -b 2>/tmp/status.fog
            ;;
        0|2)
            # GZIP Compressed partclone
            echo " * Imaging using Partclone (gzip)"
            #zstdmt -dc </tmp/pigz1 | partclone.restore -n "Storage Location $storage, Image name $img" --ignore_crc -O ${target} -N -f 1
            pigz -dc </tmp/pigz1 | partclone.restore -n "Storage Location $storage, Image name $img" --ignore_crc -O ${target} -N -f 1
            # If this fails, try uncompressed form.
            #[[ ! $? -eq 0 ]] && cat </tmp/pigz1 | partclone.restore --ignore_crc -O ${target} -N -f 1 || true
            ;;
    esac
    exitcode=$?
    [[ ! $exitcode -eq 0 ]] && handleWarning "Image failed to restore and exited with exit code $exitcode (${FUNCNAME[0]})\n   Info: $(cat /tmp/partclone.log)\n   Args Passed: $*"
    rm -rf /tmp/pigz1 >/dev/null 2>&1
}
# Gets the valid restore parts. They're only
#    valid if the partition data exists for
#    the partitions on the server
#
# $1 = Disk  (e.g. /dev/sdb)
# $2 = Disk number  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
getValidRestorePartitions() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    local setrestoreparts="$4"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local valid_parts=""
    local parts=""
    local part=""
    local imgpart=""
    local part_number=0
    getPartitions "$disk"
    for part in $parts; do
        getPartitionNumber "$part"
        [[ $imgPartitionType != all && $imgPartitionType != $part_number ]] && continue
        case $osid in
            [1-2])
                [[ ! -f $imagePath ]] && imgpart="$imagePath/d${disk_number}p${part_number}.img*" || imgpart="$imagePath"
                ;;
            4|[5-7]|9)
                [[ ! -f $imagePath/sys.img.000 ]] && imgpart="$imagePath/d${disk_number}p${part_number}.img*"
                if [[ -z $imgpart ]]; then
                    case $win7partcnt in
                        1)
                            [[ $part_number -eq 1 ]] && imgpart="$imagePath/sys.img.*"
                            ;;
                        2)
                            [[ $part_number -eq 1 ]] && imgpart="$imagePath/rec.img.000"
                            [[ $part_number -eq 2 ]] && imgpart="$imagePath/sys.img.*"
                            ;;
                        3)
                            [[ $part_number -eq 1 ]] && imgpart="$imagePath/rec.img.000"
                            [[ $part_number -eq 2 ]] && imgpart="$imagePath/rec.img.001"
                            [[ $part_number -eq 3 ]] && imgpart="$imagePath/sys.img.*"
                            ;;
                    esac
                fi
                ;;
            *)
                imgpart="$imagePath/d${disk_number}p${part_number}.img*"
                ;;
        esac
        ls $imgpart >/dev/null 2>&1
        [[ $? -eq 0 ]] && valid_parts="$valid_parts $part"
    done
    [[ -z $setrestoreparts ]] && restoreparts=$(echo $valid_parts | uniq | sort -V) || restoreparts="$(echo $setrestoreparts | uniq | sort -V)"
}
# Makes all swap partitions and sets uuid's in linux setups
#
# $1 = Disk  (e.g. /dev/sdb)
# $2 = Disk number  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
# $4 = ImagePartitionType  (e.g. all, mbr, 1, 2, 3, etc.)
makeAllSwapSystems() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    local imgPartitionType="$4"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imgPartitionType ]] && handleError "No image partition type passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local swapuuidfilename=""
    swapUUIDFileName "$imagePath" "$disk_number"
    local parts=""
    local part=""
    local part_number=0
    getPartitions "$disk"
    for part in $parts; do
        getPartitionNumber "$part"
        [[ $imgPartitionType == all || $imgPartitionType -eq $part_number ]] && makeSwapSystem "$swapuuidfilename" "$part"
    done
    runPartprobe "$disk"
}
# Changes the hostname on windows systems
#
# $1 = Partition
changeHostname() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $hostname || $hostearly -eq 0 ]] && return
    REG_HOSTNAME_KEY1="\ControlSet001\Services\Tcpip\Parameters\NV Hostname"
    REG_HOSTNAME_KEY2="\ControlSet001\Services\Tcpip\Parameters\Hostname"
    REG_HOSTNAME_KEY3="\ControlSet001\Services\Tcpip\Parameters\NV HostName"
    REG_HOSTNAME_KEY4="\ControlSet001\Services\Tcpip\Parameters\HostName"
    REG_HOSTNAME_KEY5="\ControlSet001\Control\ComputerName\ActiveComputerName\ComputerName"
    REG_HOSTNAME_KEY6="\ControlSet001\Control\ComputerName\ComputerName\ComputerName"
    REG_HOSTNAME_KEY7="\ControlSet001\services\Tcpip\Parameters\NV Hostname"
    REG_HOSTNAME_KEY8="\ControlSet001\services\Tcpip\Parameters\Hostname"
    REG_HOSTNAME_KEY9="\ControlSet001\services\Tcpip\Parameters\NV HostName"
    REG_HOSTNAME_KEY10="\ControlSet001\services\Tcpip\Parameters\HostName"
    REG_HOSTNAME_KEY11="\CurrentControlSet\Services\Tcpip\Parameters\NV Hostname"
    REG_HOSTNAME_KEY12="\CurrentControlSet\Services\Tcpip\Parameters\Hostname"
    REG_HOSTNAME_KEY13="\CurrentControlSet\Services\Tcpip\Parameters\NV HostName"
    REG_HOSTNAME_KEY14="\CurrentControlSet\Services\Tcpip\Parameters\HostName"
    REG_HOSTNAME_KEY15="\CurrentControlSet\Control\ComputerName\ActiveComputerName\ComputerName"
    REG_HOSTNAME_KEY16="\CurrentControlSet\Control\ComputerName\ComputerName\ComputerName"
    REG_HOSTNAME_KEY17="\CurrentControlSet\services\Tcpip\Parameters\NV Hostname"
    REG_HOSTNAME_KEY18="\CurrentControlSet\services\Tcpip\Parameters\Hostname"
    REG_HOSTNAME_KEY19="\CurrentControlSet\services\Tcpip\Parameters\NV HostName"
    REG_HOSTNAME_KEY20="\CurrentControlSet\services\Tcpip\Parameters\HostName"
    dots "Mounting directory"
    if [[ ! -d /ntfs ]]; then
        mkdir -p /ntfs >/dev/null 2>&1
        if [[ ! $? -eq 0 ]]; then
            echo "Failed"
            debugPause
            handleError " * Could not create mount location (${FUNCNAME[0]})\n    Args Passed: $*"
        fi
    fi
    umount /ntfs >/dev/null 2>&1
    ntfs-3g -o remove_hiberfile,rw $part /ntfs >/tmp/ntfs-mount-output 2>&1
    case $? in
        0)
            echo "Done"
            debugPause
            ;;
        *)
            echo "Failed"
            debugPause
            handleError " * Could not mount $part (${FUNCNAME[0]})\n    Args Passed: $*\n    Reason: $(cat /tmp/ntfs-mount-output | tr -d \\0)"
            ;;
    esac
    if [[ ! -f /usr/share/fog/lib/EOFREG ]]; then
        key1="$REG_HOSTNAME_KEY1"
        key2="$REG_HOSTNAME_KEY2"
        key3="$REG_HOSTNAME_KEY3"
        key4="$REG_HOSTNAME_KEY4"
        key5="$REG_HOSTNAME_KEY5"
        key6="$REG_HOSTNAME_KEY6"
        key7="$REG_HOSTNAME_KEY7"
        key8="$REG_HOSTNAME_KEY8"
        key9="$REG_HOSTNAME_KEY9"
        key10="$REG_HOSTNAME_KEY10"
        key11="$REG_HOSTNAME_KEY11"
        key12="$REG_HOSTNAME_KEY12"
        key13="$REG_HOSTNAME_KEY13"
        key14="$REG_HOSTNAME_KEY14"
        key15="$REG_HOSTNAME_KEY15"
        key16="$REG_HOSTNAME_KEY16"
        key17="$REG_HOSTNAME_KEY17"
        key18="$REG_HOSTNAME_KEY18"
        key19="$REG_HOSTNAME_KEY19"
        key20="$REG_HOSTNAME_KEY20"
        case $osid in
            1)
                regfile="$REG_LOCAL_MACHINE_XP"
                ;;
            2|4|[5-7]|9)
                regfile="$REG_LOCAL_MACHINE_7"
                ;;
        esac
        echo "ed $key1" >/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key2" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key3" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key4" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key5" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key6" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key7" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key8" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key9" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key10" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key11" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key12" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key13" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key14" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key15" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key16" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key17" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key18" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key19" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "ed $key20" >>/usr/share/fog/lib/EOFREG
        echo "$hostname" >>/usr/share/fog/lib/EOFREG
        echo "q" >> /usr/share/fog/lib/EOFREG
        echo "y" >> /usr/share/fog/lib/EOFREG
        echo >> /usr/share/fog/lib/EOFREG
    fi
    if [[ -e $regfile ]]; then
        dots "Changing hostname"
        reged -e $regfile < /usr/share/fog/lib/EOFREG >/dev/null 2>&1
        case $? in
            [0-2])
                echo "Done"
                debugPause
                ;;
            *)
                echo "Failed"
                debugPause
                umount /ntfs >/dev/null 2>&1
                echo " * Failed to change hostname"
                return
                ;;
        esac
    fi
    rm -rf /usr/share/fog/lib/EOFREG
    umount /ntfs >/dev/null 2>&1
}
# Fixes windows 7/8 boot, though may need
#    to be updated to only impact windows 7
#    in which case we need a more dynamic method
#
# $1 is the partition
fixWin7boot() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ $osid != [5-7] ]] && return
    local fstype=""
    fsTypeSetting "$part"
    [[ $fstype != ntfs ]] && return
    dots "Mounting partition"
    if [[ ! -d /bcdstore ]]; then
        mkdir -p /bcdstore >/dev/null 2>&1
        case $? in
            0)
                ;;
            *)
                echo "Failed"
                debugPause
                handleError " * Could not create mount location (${FUNCNAME[0]})\n    Args Passed: $*"
                ;;
        esac
    fi
    ntfs-3g -o remove_hiberfile,rw $part /bcdstore >/tmp/ntfs-mount-output 2>&1
    case $? in
        0)
            echo "Done"
            debugPause
            ;;
        *)
            echo "Failed"
            debugPause
            handleError " * Could not mount $part (${FUNCNAME[0]})\n    Args Passed: $*\n    Reason: $(cat /tmp/ntfs-mount-output | tr -d \\0)"
            ;;
    esac
    if [[ ! -f /bcdstore/Boot/BCD ]]; then
        umount /bcdstore >/dev/null 2>&1
        return
    fi
    dots "Backing up and replacing BCD"
    mv /bcdstore/Boot/BCD{,.bak} >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            umount /bcdstore >/dev/null 2>&1
            echo " * Could not create backup"
            return
            ;;
    esac
    cp /usr/share/fog/BCD /bcdstore/Boot/BCD >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            debugPause
            umount /bcdstore >/dev/null 2>&1
            ;;
        *)
            echo "Failed"
            debugPause
            umount /bcdstore >/dev/null 2>&1
            echo " * Could not copy our bcd file"
            return
            ;;
    esac
    umount /bcdstore >/dev/null 2>&1
}
# Clears out windows hiber and page files
#
# $1 is the partition
clearMountedDevices() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    if [[ ! -d /ntfs ]]; then
        mkdir -p /ntfs >/dev/null 2>&1
        case $? in
            0)
                umount /ntfs >/dev/null 2>&1
                ;;
            *)
                handleError "Could not create mount point /ntfs (${FUNCNAME[0]})\n   Args Passed: $*"
                ;;
        esac
    fi
    case $osid in
        4|[5-7]|9)
            local fstype=""
            fsTypeSetting "$part"
            REG_HOSTNAME_MOUNTED_DEVICES_7="\MountedDevices"
            if [[ ! -f /usr/share/fog/lib/EOFMOUNT ]]; then
                echo "cd $REG_HOSTNAME_MOUNTED_DEVICES_7" >/usr/share/fog/lib/EOFMOUNT
                echo "dellallv" >>/usr/share/fog/lib/EOFMOUNT
                echo "q" >>/usr/share/fog/lib/EOFMOUNT
                echo "y" >>/usr/share/fog/lib/EOFMOUNT
                echo >> /usr/share/fog/lib/EOFMOUNT
            fi
            case $fstype in
                ntfs)
                    dots "Clearing part ($part)"
                    ntfs-3g -o remove_hiberfile,rw $part /ntfs >/tmp/ntfs-mount-output 2>&1
                    case $? in
                        0)
                            ;;
                        *)
                            echo "Failed"
                            debugPause
                            handleError " * Could not mount $part (${FUNCNAME[0]})\n    Args Passed: $*\n    Reason: $(cat /tmp/ntfs-mount-output | tr -d \\0)"
                            ;;
                    esac
                    if [[ ! -f $REG_LOCAL_MACHINE_7 ]]; then
                        echo "Reg file not found"
                        debugPause
                        umount /ntfs >/dev/null 2>&1
                        return
                    fi
                    reged -e $REG_LOCAL_MACHINE_7 </usr/share/fog/lib/EOFMOUNT >/dev/null 2>&1
                    case $? in
                        [0-2])
                            echo "Done"
                            debugPause
                            umount /ntfs >/dev/null 2>&1
                            ;;
                        *)
                            echo "Failed"
                            debugPause
                            /umount /ntfs >/dev/null 2>&1
                            echo " * Could not clear partition $part"
                            return
                            ;;
                    esac
                    ;;
            esac
            ;;
    esac
}
# Only removes the page file
#
# $1 is the device name of the windows system partition
removePageFile() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local fstype=""
    fsTypeSetting "$part"
    [[ ! $ignorepg -eq 1 ]] && return
    case $osid in
        [1-2]|4|[5-7]|[9]|50|51)
            case $fstype in
                ntfs)
                    dots "Mounting partition ($part)"
                    if [[ ! -d /ntfs ]]; then
                        mkdir -p /ntfs >/dev/null 2>&1
                        case $? in
                            0)
                                ;;
                            *)
                                echo "Failed"
                                debugPause
                                handleError " * Could not create mount location (${FUNCNAME[0]})\n    Args Passed: $*"
                                ;;
                        esac
                    fi
                    umount /ntfs >/dev/null 2>&1
                    ntfs-3g -o remove_hiberfile,rw $part /ntfs >/tmp/ntfs-mount-output 2>&1
                    case $? in
                        0)
                            echo "Done"
                            debugPause
                            ;;
                        *)
                            echo "Failed"
                            debugPause
                            handleError " * Could not mount $part (${FUNCNAME[0]})\n    Args Passed: $*\n    Reason: $(cat /tmp/ntfs-mount-output | tr -d \\0)"
                            ;;
                    esac
                    if [[ -f /ntfs/pagefile.sys ]]; then
                        dots "Removing page file"
                        rm -rf /ntfs/pagefile.sys >/dev/null 2>&1
                        case $? in
                            0)
                                echo "Done"
                                debugPause
                                ;;
                            *)
                                echo "Failed"
                                debugPause
                                echo " * Could not delete the page file"
                                ;;
                        esac
                    fi
                    if [[ -f /ntfs/hiberfil.sys ]]; then
                        dots "Removing hibernate file"
                        rm -rf /ntfs/hiberfil.sys >/dev/null 2>&1
                        case $? in
                            0)
                                echo "Done"
                                debugPause
                                ;;
                            *)
                                echo "Failed"
                                debugPause
                                umount /ntfs >/dev/null 2>&1
                                echo " * Could not delete the hibernate file"
                                ;;
                        esac
                    fi
                    umount /ntfs >/dev/null 2>&1
                    ;;
            esac
            ;;
    esac
}
# Sets OS mbr, as needed, and returns the Name
#    based on the OS id passed.
#
# $1 the osid to determine the os and mbr
determineOS() {
    local osid="$1"
    [[ -z $osid ]] && handleError "No os id passed (${FUNCNAME[0]})\n   Args Passed: $*"
    case $osid in
        1)
            osname="Windows XP"
            mbrfile="/usr/share/fog/mbr/xp.mbr"
            ;;
        2)
            osname="Windows Vista"
            mbrfile="/usr/share/fog/mbr/vista.mbr"
            ;;
        3)
            osname="Windows 98"
            mbrfile=""
            ;;
        4)
            osname="Windows (Other)"
            mbrfile=""
            ;;
        5)
            osname="Windows 7"
            mbrfile="/usr/share/fog/mbr/win7.mbr"
            defaultpart2start="206848s"
            ;;
        6)
            osname="Windows 8"
            mbrfile="/usr/share/fog/mbr/win8.mbr"
            defaultpart2start="718848s"
            ;;
        7)
            osname="Windows 8.1"
            mbrfile="/usr/share/fog/mbr/win8.mbr"
            defaultpart2start="718848s"
            ;;
        8)
            osname="Apple Mac OS"
            mbrfile=""
            ;;
        9)
            osname="Windows 10"
            mbrfile=""
            ;;
        50)
            osname="Linux"
            mbrfile=""
            ;;
        51)
            osname="Chromium OS"
            mbrfile=""
            ;;
        99)
            osname="Other OS"
            mbrfile=""
            ;;
        *)
            handleError " * Invalid OS ID ($osid) (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
}
# Converts the string (seconds) passed to human understanding
#
# $1 the seconds to convert
sec2string() {
    local T="$1"
    [[ -z $T ]] && handleError "No string passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local d=$((T/60/60/24))
    local H=$((T/60/60%24))
    local i=$((T/60%60))
    local s=$((T%60))
    local dayspace=''
    local hourspace=''
    local minspace=''
    [[ $H > 0 ]] && dayspace=' '
    [[ $i > 0 ]] && hourspace=':'
    [[ $s > 0 ]] && minspace=':'
    (($d > 0)) && printf '%d day%s' "$d" "$dayspace"
    (($H > 0)) && printf '%d%s' "$H" "$hourspace"
    (($i > 0)) && printf '%d%s' "$i" "$minspace"
    (($s > 0)) && printf '%d' "$s"
}
# Returns the disk based off the partition passed
#
# $1 is the partition to grab the disk from
getDiskFromPartition() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    disk=$(echo $part | sed 's/p\?[0-9]\+$//g')
}
# Returns the number of the partition passed
#
# $1 is the partition to get the partition number for
getPartitionNumber() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    part_number=$(echo $part | grep -o '[0-9]*$')
}
# $1 is the partition to search for.
getPartitions() {
    local disk="$1"
    [[ -z $disk ]] && disk="$hd"
    [[ -z $disk ]] && handleError "No disk found (${FUNCNAME[0]})\n   Args Passed: $*"
    parts=$(lsblk -I 3,8,9,179,202,253,259 -lpno KNAME,TYPE $disk | awk '{if ($2 ~ /part/ || $2 ~ /md/) print $1}' | sort -V | uniq)
}
# Gets the hard drive on the host
# Note: This function makes a best guess
getHardDisk() {
    [[ -n $fdrive ]] && hd=$(echo $fdrive)
    [[ -n $hd ]] && return
    local devs=$(lsblk -dpno KNAME -I 3,8,9,179,202,253,259 | uniq | sort -V)
    disks=$(echo $devs)
    [[ -z $disks ]] && handleError "Cannot find disk on system (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ $1 == true ]] && return
    for hd in $disks; do
        break
    done
}
# Finds the hard drive info and set's up the type
findHDDInfo() {
    case $imgType in
        [Nn]|mps|dd)
            dots "Looking for Hard Disk"
            getHardDisk
            if [[ -z $hd ]]; then
                echo "Failed"
                debugPause
                handleError "Could not find hard disk ($0)\n   Args Passed: $*"
            fi
            echo "Done"
            debugPause
            case $type in
                down)
                    diskSize=$(lsblk --bytes -dplno SIZE -I 3,8,9,179,259 $hd)
                    [[ $diskSize -gt 2199023255552 ]] && layPartSize="2tB"
                    echo " * Using Disk: $hd"
                    [[ $imgType == +([nN]) ]] && validResizeOS
                    enableWriteCache "$hd"
                    ;;
                up)
                    dots "Reading Partition Tables"
                    runPartprobe "$hd"
                    getPartitions "$hd"
                    if [[ -z $parts ]]; then
                        echo "Failed"
                        debugPause
                        handleError "Could not find partitions ($0)\n    Args Passed: $*"
                    fi
                    echo "Done"
                    debugPause
                    ;;
            esac
            echo " * Using Hard Disk: $hd"
            ;;
        mpa)
            dots "Looking for Hard Disks"
            getHardDisk "true"
            if [[ -z $disks ]]; then
                echo "Failed"
                debugPause
                handleError "Could not find any disks ($0)\n   Args Passed: $*"
            fi
            echo "Done"
            debugPause
            case $type in
                up)
                    for disk in $disks; do
                        dots "Reading Partition Tables on $disk"
                        getPartitions "$disk"
                        if [[ -z $parts ]]; then
                            echo "Failed"
                            debugPause
                            echo " * No partitions for disk $disk"
                            debugPause
                            continue
                        fi
                        echo "Done"
                        debugPause
                    done
                    ;;
            esac
            echo " * Using Disks: $disks"
            ;;
    esac
}

# Imaging complete
completeTasking() {
    case $type in
        up)
            chmod -R 777 "$imagePath" >/dev/null 2>&1
            killStatusReporter
            . /bin/fog.imgcomplete
            ;;
        down)
            killStatusReporter
            if [[ -f /images/postdownloadscripts/fog.postdownload ]]; then
                postdownpath="/images/postdownloadscripts/"
                . ${postdownpath}fog.postdownload
            fi
            [[ $capone -eq 1 ]] && exit 0
            if [[ $osid == +([1-2]|4|[5-7]|9) ]]; then
                for disk in $disks; do
                    getPartitions "$disk"
                    for part in $parts; do
                        fsTypeSetting "$part"
                        [[ $fstype == ntfs ]] && changeHostname "$part"
                    done
                done
            fi
            . /bin/fog.imgcomplete
            ;;
    esac
}
# Corrects mbr layout for Vista OS
#
# $1 is the disk to correct for
correctVistaMBR() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    dots "Correcting Vista MBR"
    dd if=$disk of=/tmp.mbr count=1 bs=512 >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not create backup (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    xxd /tmp.mbr /tmp.mbr.txt >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "xxd command failed (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    rm /tmp.mbr >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Couldn't remove /tmp.mbr file (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    fogmbrfix /tmp.mbr.txt /tmp.mbr.fix.txt >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "fogmbrfix failed to operate (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    rm /tmp.mbr.txt >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not remove the text file (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    xxd -r /tmp.mbr.fix.txt /mbr.mbr >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not run second xxd command (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    rm /tmp.mbr.fix.txt >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not remove the fix file (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    dd if=/mbr.mbr of="$disk" count=1 bs=512 >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not apply fixed MBR (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    debugPause
}
# Prints an error with visible information
#
# $1 is the string to inform what went wrong
handleError() {
    local str="$1"
    local parts=""
    local part=""
    echo "##############################################################################"
    echo "#                                                                            #"
    echo "#                         An error has been detected!                        #"
    echo "#                                                                            #"
    echo "##############################################################################"
    echo -e "$str\n"
    echo "Kernel variables and settings:"
    cat /proc/cmdline | sed 's/ad.*=.* //g'
    #
    # expand the file systems in the restored partitions
    #
    # Windows 7, 8, 8.1:
    # Windows 2000/XP, Vista:
    # Linux:
    if [[ -n $2 ]]; then
        case $osid in
            [1-2]|4|[5-7]|9|50|51)
                if [[ -n "$hd" ]]; then
                    getPartitions "$hd"
                    for part in $parts; do
                        expandPartition "$part"
                    done
                fi
                ;;
        esac
    fi
    if [[ -z $isdebug ]]; then
        echo "##############################################################################"
        echo "#                                                                            #"
        echo "#                      Computer will reboot in 1 minute                      #"
        echo "#                                                                            #"
        echo "##############################################################################"
        usleep 60000000
    else
        debugPause
    fi
    exit 1
}
# Prints a visible banner describing an issue but not breaking
#
# $1 The string to inform the user what the problem is
handleWarning() {
    local str="$1"
    echo "##############################################################################"
    echo "#                                                                            #"
    echo "#                        A warning has been detected!                        #"
    echo "#                                                                            #"
    echo "##############################################################################"
    echo -e "$str"
    echo "##############################################################################"
    echo "#                                                                            #"
    echo "#                          Will continue in 1 minute                         #"
    echo "#                                                                            #"
    echo "##############################################################################"
    usleep 60000000
    debugPause
}
# Re-reads the partition table of the disk passed
#
# $1 is the disk
runPartprobe() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    umount /ntfs /bcdstore >/dev/null 2>&1
    udevadm settle
    blockdev --rereadpt $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to read back partitions (${FUNCNAME[0]})\n   Args Passed: $*"
}
# Sends a command list to a file for use when debugging
#
# $1 The string of the command needed to run.
debugCommand() {
    local str="$1"
    case $isdebug in
        [Yy][Ee][Ss]|[Yy])
            echo -e "$str" >> /tmp/cmdlist
            ;;
    esac
}
# Escapes the passed item where needed
#
# $1 the item that needs to be escaped
escapeItem() {
    local item="$1"
    echo $item | sed -r 's%/%\\/%g'
}
# uploadFormat
# Description:
# Tells the system what format to upload in, whether split or not.
# Expects first argument to be the fifo to send to.
# Expects part of the filename in the case of resizable
#    will append 000 001 002 automatically
#
# $1 The fifo name (file in file out)
# $2 The file to upload into on the server
uploadFormat() {
    local fifo="$1"
    local file="$2"
    [[ -z $fifo ]] && handleError "Missing file in file out (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $file ]] && handleError "Missing file name to store (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ ! -e $fifo ]] && mkfifo $fifo >/dev/null 2>&1
    local cores=$(nproc)
    cores=$((cores - 1))
    [[ $cores -lt 1 ]] && cores=1
    case $imgFormat in
        6)
            # ZSTD Split files compressed.
            zstdmt --ultra $PIGZ_COMP < $fifo | split -a 3 -d -b 200m - ${file}. &
            ;;
        5)
            # ZSTD compressed.
            zstdmt --ultra $PIGZ_COMP < $fifo > ${file}.000 &
            ;;
        4)
            # Split files uncompressed.
            cat $fifo | split -a 3 -d -b 200m - ${file}. &
            ;;
        3)
            # Uncompressed.
            cat $fifo > ${file}.000 &
            ;;
        2)
            # GZip/piGZ Split file compressed.
            pigz $PIGZ_COMP < $fifo | split -a 3 -d -b 200m - ${file}. &
            ;;
        *)
            # GZip/piGZ Compressed.
            pigz $PIGZ_COMP < $fifo > ${file}.000 &
        ;;
esac
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
# $1 is the disk
# $2 is the disk number
# $3 is the image path to save the file to.
# $4 is the determinator of sgdisk use or not
saveGRUB() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    local sgdisk="$4"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    # Determine the number of sectors to copy
    # Hack Note: print $4+0 causes the column to be interpretted as a number
    #            so the comma is tossed
    local count=$(sfdisk -d $disk 2>/dev/null | awk /start=\ *[1-9]/'{print $4+0}' | sort -n | head -n1)
    local has_grub=$(dd if=$disk bs=512 count=1 2>&1 | grep -i 'grub')
    local hasgrubfilename=""
    if [[ -n $has_grub ]]; then
        hasGrubFileName "$imagePath" "$disk_number" "$sgdisk"
        touch $hasgrubfilename
    fi
    # Ensure that no more than 1MiB of data is copied (already have this size used elsewhere)
    [[ $count -gt 2048 ]] && count=2048
    [[ $count -eq 63 ]] && count=1
    local mbrfilename=""
    MBRFileName "$imagePath" "$disk_number" "mbrfilename" "$sgdisk"
    dd if=$disk of=$mbrfilename count=$count bs=512 >/dev/null 2>&1
}
# Checks for the existence of the grub embedding area in the image directory.
# Echos 1 for true, and 0 for false.
#
# Expects:
# the device name (e.g. /dev/sda) as the first parameter,
# the disk number (e.g. 1) as the second parameter
# the directory images stored in (e.g. /image/xyz) as the third parameter
# $1 is the disk
# $2 is the disk number
# $3 is the image path
# $4 is the sgdisk determinator
hasGRUB() {
    local disk_number="$1"
    local imagePath="$2"
    local sgdisk="$3"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local hasgrubfilename=""
    hasGrubFileName "$imagePath" "$disk_number" "$sgdisk"
    hasGRUB=0
    [[ -e $hasgrubfilename ]] && hasGRUB=1
}
# Restore the grub boot record and all of the embedding area data
# necessary for grub2.
#
# Expects:
# the device name (e.g. /dev/sda) as the first parameter,
# the disk number (e.g. 1) as the second parameter
# the directory images stored in (e.g. /image/xyz) as the third parameter
# $1 is the disk
# $2 is the disk number
# $3 is the image path
# $4 is the sgdisk determinator
restoreGRUB() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    local sgdisk="$4"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local tmpMBR=""
    MBRFileName "$imagePath" "$disk_number" "tmpMBR" "$sgdisk"
    local count=$(du -B 512 $tmpMBR | awk '{print $1}')
    [[ $count -eq 8 ]] && count=1
    dd if=$tmpMBR of=$disk bs=512 count=$count >/dev/null 2>&1
    runPartprobe "$disk"
}
# Waits for enter if system is debug type
debugPause() {
    case $isdebug in
        [Yy][Ee][Ss]|[Yy])
            echo " * Press [Enter] key to continue"
            read  -p "$*"
            ;;
        *)
            return
            ;;
    esac
}
debugEcho() {
    local str="$*"
    case $isdebug in
        [Yy][Ee][Ss]|[Yy])
            echo "$str"
            ;;
        *)
            return
            ;;
    esac
}
majorDebugEcho() {
    [[ $ismajordebug -gt 1 ]] && echo "$*"
}
majorDebugPause() {
    [[ ! $ismajordebug -gt 0 ]] && return
    echo " * Press [Enter] key to continue"
    read -p "$*"
}
swapUUIDFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    swapuuidfilename="$imagePath/d${disk_number}.original.swapuuids"
}
mainUUIDFileName() {
    local imagePath="$1"
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    mainuuidfilename="$imagePath/d${disk_number}.original.uuids"
}
sfdiskPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    sfdiskoriginalpartitionfilename="$imagePath/d${disk_number}.partitions"
}
sfdiskLegacyOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    sfdisklegacyoriginalpartitionfilename="$imagePath/d${disk_number}.original.partitions"
}
sfdiskMinimumPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    sfdiskminimumpartitionfilename="$imagePath/d${disk_number}.minimum.partitions"
}
sfdiskOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    sfdiskPartitionFileName "$imagePath" "$disk_number"
}
sgdiskOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    sgdiskoriginalpartitionfilename="$imagePath/d${disk_number}.sgdisk.original.partitions"
}
fixedSizePartitionsFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    fixed_size_file="$imagePath/d${disk_number}.fixed_size_partitions"
}
hasGrubFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    local sgdisk="$3"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    hasgrubfilename="$imagePath/d${disk_number}.has_grub"
    [[ -n $sgdisk ]] && hasgrubfilename="$imagePath/d${disk_number}.grub.mbr"
}
MBRFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    local varVar="$3"
    local sgdisk="$4"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $varVar ]] && handleError "No variable to set passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local mbr=""
    local hasGRUB=0
    hasGRUB "$disk_number" "$imagePath" "$sgdisk"
    [[ -n $sgdisk && $hasGRUB -eq 1 ]] && mbr="$imagePath/d${disk_number}.grub.mbr" || mbr="$imagePath/d${disk_number}.mbr"
    case $type in
        down)
            [[ ! -f $mbr && -n $mbrfile ]] && mbr="$mbrfile"
            printf -v "$varVar" "$mbr"
            [[ -z $mbr ]] && handleError "Image store corrupt, unable to locate MBR, no default file specified (${FUNCNAME[0]})\n    Args Passed: $*\n    $varVar Variable set to: ${!varVar}"
            [[ ! -f $mbr ]] && handleError "Image store corrupt, unable to locate MBR, no file found (${FUNCNAME[0]})\n    Args Passed: $*\n    Variable set to: ${!varVar}\n    $varVar Variable set to: ${!varVar}"
            ;;
        up)
            printf -v "$varVar" "$mbr"
            ;;
    esac
}
EBRFileName() {
    local path="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    local part_number="$3"    # e.g. 5
    [[ -z $path ]] && handleError "No path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $part_number ]] && ebrfilename="" || ebrfilename="$path/d${disk_number}p${part_number}.ebr"
}
tmpEBRFileName() {
    local disk_number="$1"
    local part_number="$2"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $part_number ]] && handleError "No partition number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local ebrfilename=""
    EBRFileName "/tmp" "$disk_number" "$disk_number"
    tmpebrfilename="$ebrfilename"
}
#
# Works for MBR/DOS or GPT style partition tables
# Only saves PT information if the type is "all" or "mbr"
#
# For MBR/DOS style PT
#   Saves the MBR as everything before the start of the first partition (512+ bytes)
#      This includes the DOS MBR or GRUB.  Don't know about other bootloaders
#      This includes the 4 primary partitions
#   The EBR of extended and logical partitions is actually the first 512 bytes of
#      the partition, so we don't need to save/restore them here.
#
#
savePartitionTablesAndBootLoaders() {
    local disk="$1"                    # e.g. /dev/sda
    local disk_number="$2"                 # e.g. 1
    local imagePath="$3"               # e.g. /net/dev/foo
    local osid="$4"                    # e.g. 50
    local imgPartitionType="$5"
    local sfdiskfilename="$6"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $osid ]] && handleError "No osid passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imgPartitionType ]] && handleError "No img part type passed (${FUNCNAME[0]})\n   Args Passed: $*"
    if [[ -z $sfdiskfilename ]]; then
        sfdiskPartitionFileName "$imagePath" "$disk_number"
        sfdiskfilename="$sfdiskoriginalpartitionfilename"
    fi
    local hasgpt=0
    hasGPT "$disk"
    local have_extended_partition=0  # e.g. 0 or 1-n (extended partition count)
    local strdots=""
    [[ $hasgpt -eq 0 ]] && have_extended_partition=$(sfdisk -l $disk 2>/dev/null | egrep "^${disk}.* (Extended|W95 Ext'd \(LBA\))$" | wc -l)
    runPartprobe "$disk"
    case $hasgpt in
        0)
            strdots="Saving Partition Tables (MBR)"
            case $osid in
                4|50|51)
                    [[ $disk_number -eq 1 ]] && strdots="Saving Partition Tables and GRUB (MBR)"
                    ;;
            esac
            dots "$strdots"
            saveGRUB "$disk" "$disk_number" "$imagePath"
            sfdisk -d $disk 2>/dev/null > $sfdiskfilename
            echo "Done"
            debugPause
            [[ $have_extended_partition -ge 1 ]] && saveAllEBRs "$disk" "$disk_number" "$imagePath"
            echo "Done"
            ;;
        1)
            dots "Saving Partition Tables (GPT)"
            saveGRUB "$disk" "$disk_number" "$imagePath" "true"
            sgdisk -b "$imagePath/d${disk_number}.mbr" $disk >/dev/null 2>&1
            if [[ ! $? -eq 0 ]]; then
                echo "Failed"
                debugPause
                handleError "Error trying to save GPT partition tables (${FUNCNAME[0]})\n   Args Passed: $*"
            fi
            sfdisk -d $disk 2>/dev/null > $sfdiskfilename
            echo "Done"
            ;;
    esac
    runPartprobe "$disk"
    debugPause
}
clearPartitionTables() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ $nombr -eq 1 ]] && return
    dots "Erasing current MBR/GPT Tables"
    sgdisk -Z $disk >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        2)
            echo "Done, but cleared corrupted partition."
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Error trying to erase partition tables (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    runPartprobe "$disk"
    debugPause
}
# Restores the partition tables and boot loaders
#
# $1 is the disk
# $2 is the disk number
# $3 is the image path
# $4 is the osid
# $5 is the image partition type
restorePartitionTablesAndBootLoaders() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    local osid="$4"
    local imgPartitionType="$5"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $osid ]] && handleError "No osid passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imgPartitionType ]] && handleError "No image part type passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local tmpMBR=""
    local strdots=""
    if [[ $nombr -eq 1 ]]; then
        echo " * Skipping partition tables and MBR"
        debugPause
        return
    fi
    clearPartitionTables "$disk"
    majorDebugEcho "Partition table should be empty now."
    majorDebugShowCurrentPartitionTable "$disk" "$disk_number"
    majorDebugPause
    MBRFileName "$imagePath" "$disk_number" "tmpMBR"
    [[ ! -f $tmpMBR ]] && handleError "Image Store Corrupt: Unable to locate MBR (${FUNCNAME[0]})\n   Args Passed: $*"
    local table_type=""
    getDesiredPartitionTableType "$imagePath" "$disk_number"
    majorDebugEcho "Trying to restore to $table_type partition table."
    if [[ $table_type == GPT ]]; then
        dots "Restoring Partition Tables (GPT)"
        restoreGRUB "$disk" "$disk_number" "$imagePath" "true"
        sgdisk -gl $tmpMBR $disk >/dev/null 2>&1
        sgdiskexit="$?"
        if [[ ! $sgdiskexit -eq 0 ]]; then
            echo "Failed"
            debugPause
            handleError "Error trying to restore GPT partition tables (${FUNCNAME[0]})\n   Args Passed: $*\n    CMD Tried: sgdisk -gl $tmpMBR $disk\n    Exit returned code: $sgdiskexit"
        fi
        global_gptcheck="yes"
        echo "Done"
    else
        case $osid in
            50|51)
                strdots="Restoring Partition Tables and GRUB (MBR)"
                ;;
            *)
                strdots="Restoring Partition Tables (MBR)"
                ;;
        esac
        dots "$strdots"
        restoreGRUB "$disk" "$disk_number" "$imagePath"
        echo "Done"
        debugPause
        majorDebugShowCurrentPartitionTable "$disk" "$disk_number"
        majorDebugPause
        ebrcount=$(ls -1 $imagePath/*.ebr 2>/dev/null | wc -l)
        [[ $ebrcount -gt 0 ]] && restoreAllEBRs "$disk" "$disk_number" "$imagePath" "$imgPartitionType"
        local sfdiskoriginalpartitionfilename=""
        local sfdisklegacyoriginalpartitionfilename=""
        sfdiskPartitionFileName "$imagePath" "$disk_number"
        sfdiskLegacyOriginalPartitionFileName "$imagePath" "$disk_number"
        if [[ -r $sfdiskoriginalpartitionfilename ]]; then
            dots "Inserting Extended partitions (Original)"
            sfdisk $disk < $sfdiskoriginalpartitionfilename >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    ;;
            esac
        elif [[ -e $sfdisklegacyoriginalpartitionfilename ]]; then
            dots "Inserting Extended partitions (Legacy)"
            sfdisk $disk < $sfdisklegacyoriginalpartitionfilename >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    ;;
            esac
        else
            echo " * No extended partitions"
        fi
    fi
    debugPause
    runPartprobe "$disk"
    majorDebugShowCurrentPartitionTable "$disk" "$disk_number"
    majorDebugPause
}
savePartition() {
    local part="$1"
    local disk_number="$2"
    local imagePath="$3"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local part_number=0
    getPartitionNumber "$part"
    local fstype=""
    local parttype=""
    local imgpart=""
    local fifoname="/tmp/pigz1"
    if [[ $imgPartitionType != all && $imgPartitionType != $part_number ]]; then
        echo " * Skipping partition $part ($part_number)"
        debugPause
        return
    fi
    echo " * Processing Partition: $part ($part_number)"
    debugPause
    fsTypeSetting "$part"
    getPartType "$part"
    local ebrfilename=""
    local swapuuidfilename=""
    case $fstype in
        swap)
            echo " * Saving swap partition UUID"
            swapUUIDFileName "$imagePath" "$disk_number"
            saveSwapUUID "$swapuuidfilename" "$part"
            ;;
        *)
            case $parttype in
                0x5|0xf)
                    echo " * Not capturing content of extended partition"
                    debugPause
                    EBRFileName "$imagePath" "$disk_number" "$part_number"
                    touch "$ebrfilename"
                    ;;
                *)
                    echo " * Using partclone.$fstype"
                    debugPause
                    imgpart="$imagePath/d${disk_number}p${part_number}.img"
                    uploadFormat "$fifoname" "$imgpart"
                    partclone.$fstype -n "Storage Location $storage, Image name $img" -cs $part -O $fifoname -Nf 1
                    exitcode=$?
                    case $exitcode in
                        0)
                            mv ${imgpart}.000 $imgpart >/dev/null 2>&1
                            echo " * Image Captured"
                            debugPause
                            ;;
                        *)
                            handleError "Failed to complete capture (${FUNCNAME[0]})\n   Args Passed: $*\n    Exit code: $exitcode\n    Maybe check the fog server\n      to ensure disk space is good to go?"
                            ;;
                    esac
                    ;;
            esac
            ;;
    esac
    rm -rf $fifoname >/dev/null 2>&1
}
restorePartition() {
    local part="$1"
    local disk_number="$2"
    local imagePath="$3"
    local mc="$4"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    if [[ $imgPartitionType != all && $imgPartitionType != $part_number ]]; then
        echo " * Skipping partition: $part ($part_number)"
        debugPause
        return
    fi
    local imgpart=""
    local ebrfilename=""
    local disk=""
    local part_number=0
    getDiskFromPartition "$part"
    getPartitionNumber "$part"
    echo " * Processing Partition: $part ($part_number)"
    debugPause
    case $imgType in
        dd)
            imgpart="$imagePath"
            ;;
        n|mps|mpa)
            case $osid in
                [1-2])
                    [[ -f $imagePath ]] && imgpart="$imagePath" || imgpart="$imagePath/d${disk_number}p${part_number}.img*"
                    ;;
                4|8|50|51)
                    imgpart="$imagePath/d${disk_number}p${part_number}.img*"
                    ;;
                [5-7]|9)
                    [[ ! -f $imagePath/sys.img.000 ]] && imgpart="$imagePath/d${disk_number}p${part_number}.img*"
                    if [[ -z $imgpart ]] ;then
                        [[ -r $imagePath/sys.img.000 ]] && win7partcnt=1
                        [[ -r $imagePath/rec.img.000 ]] && win7partcnt=2
                        [[ -r $imagePath/rec.img.001 ]] && win7partcnt=3
                        case $win7partcnt in
                            1)
                                imgpart="$imagePath/sys.img.*"
                                ;;
                            2)
                                case $part_number in
                                    1)
                                        imgpart="$imagePath/rec.img.000"
                                        ;;
                                    2)
                                        imgpart="$imagePath/sys.img.*"
                                        ;;
                                esac
                                ;;
                            3)
                                case $part_number in
                                    1)
                                        imgpart="$imagePath/rec.img.000"
                                        ;;
                                    2)
                                        imgpart="$imagePath/rec.img.001"
                                        ;;
                                    3)
                                        imgpart="$imagePath/sys.img.*"
                                        ;;
                                esac
                                ;;
                        esac
                    fi
                    ;;
            esac
            ;;
        *)
            handleError "Invalid Image Type $imgType (${FUNCNAME[0]})\n   Args Passed: $*"
            ;;
    esac
    ls $imgpart >/dev/null 2>&1
    if [[ ! $? -eq 0 ]]; then
        EBRFileName "$imagePath" "$disk_number" "$part_number"
        [[ -e $ebrfilename ]] && echo " * Not deploying content of extended partition" || echo " * Partition File Missing: $imgpart"
        runPartprobe "$disk"
        return
    fi
    writeImage "$imgpart" "$part" "$mc"
    runPartprobe "$disk"
    resetFlag "$part"
}
runFixparts() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    echo
    dots "Attempting fixparts"
    fixparts $disk </usr/share/fog/lib/EOFFIXPARTS >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not fix partition layout (${FUNCNAME[0]})\n   Args Passed: $*" "yes"
            ;;
    esac
    debugPause
    runPartprobe "$disk"
}
killStatusReporter() {
    dots "Stopping FOG Status Reporter"
    kill -9 $statusReporter >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            ;;
    esac
    debugPause
}
prepareResizeDownloadPartitions() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    local osid="$4"
    local imgPartitionType="$5"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $osid ]] && handleError "No osid passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imgPartitionType ]] && handleError "No image partition type  passed (${FUNCNAME[0]})\n   Args Passed: $*"
    if [[ $nombr -eq 1 ]]; then
        echo -e " * Skipping partition preperation\n"
        debugPause
        return
    fi
    restorePartitionTablesAndBootLoaders "$disk" "$disk_number" "$imagePath" "$osid" "$imgPartitionType"
    local do_fill=0
    fillDiskWithPartitionsIsOK "$disk" "$imagePath" "$disk_number"
    majorDebugEcho "Filling disk = $do_fill"
    dots "Attempting to expand/fill partitions"
    if [[ $do_fill -eq 0 ]]; then
        echo "Failed"
        debugPause
        handleError "Fatal Error: Could not resize partitions (${FUNCNAME[0]})\n   Args Passed: $*"
    fi
    fillDiskWithPartitions "$disk" "$imagePath" "$disk_number"
    echo "Done"
    debugPause
    runPartprobe "$disk"
}
# $1 is the disks
# $2 is the image path
# $3 is the image partition type (either all or partition number)
# $4 is the flag to say whether this is multicast or not
performRestore() {
    local disks="$1"
    local disk=""
    local imagePath="$2"
    local imgPartitionType="$3"
    local mc="$4"
    [[ -z $disks ]] && handleError "No disks passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $imgPartitionType ]] && handleError "No partition type passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local disk_number=1
    local part_number=0
    local restoreparts=""
    local mainuuidfilename=""
    [[ $imgType =~ [Nn] ]] && local tmpebrfilename=""
    for disk in $disks; do
        mainuuidfilename=""
        mainUUIDFileName "$imagePath" "$disk_number"
        getValidRestorePartitions "$disk" "$disk_number" "$imagePath" "$restoreparts"
        [[ -z $restoreparts ]] && handleError "No image file(s) found that would match the partition(s) to be restored (${FUNCNAME[0]})\n   Args Passed: $*"
        for restorepart in $restoreparts; do
            getPartitionNumber "$restorepart"
            [[ $imgType =~ [Nn] ]] && tmpEBRFileName "$disk_number" "$part_number"
            restorePartition "$restorepart" "$disk_number" "$imagePath" "$mc"
            [[ $imgType =~ [Nn] ]] && restoreEBR "$restorepart" "$tmpebrfilename"
            [[ $imgType =~ [Nn] ]] && expandPartition "$restorepart" "$fixed_size_partitions"
            [[ $osid == +([5-7]) && $imgType =~ [Nn] ]] && fixWin7boot "$restorepart"
        done
        restoreparts=""
        echo " * Resetting UUIDs for $disk"
        debugPause
        restoreUUIDInformation "$disk" "$mainuuidfilename" "$disk_number" "$imagePath"
        echo " * Resetting swap systems"
        debugPause
        makeAllSwapSystems "$disk" "$disk_number" "$imagePath" "$imgPartitionType"
        let disk_number+=1
    done
}
# Gets the file system identifier.
# $1 is the partition to get.
getFSID() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local disk
    getDiskFromPartition "$part"
    fsid="$(sfdisk -d "$disk" |  grep "$part" | sed -n 's/.*Id=\([0-9]\+\).*\(,\|\).*/\1/p')"
}
# Gets any lvm layouts.
# $1 is the partition to search within.
getLVM() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    vgscan >/dev/null 2>&1
    local vggroup
    getVolumeGroup "${part}"
    [[ -z $vggroup ]] && return
    changeVolumeGroup "${vggroup}"
    read lvmGUID lvmSIZE <<< $(vgs --noheadings -v ${vggroup} --units s 2>/dev/null | awk '{printf("%s %s", $9, gensub(/[Ss]/,"","g",$7))}')
}
# Gets the volume group name/label.
# $1 The partition to check on.
getVolumeGroup() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})\n   Args Passed: $*"
    vggroup=$(pvs --noheadings ${part} | sed -n "s|.*${part}[[:space:]]\+\([A-Za-z0-9_-]\+\)[[:space:]]\+.*|\1|p")
}
# Changes to volume group
# $1 The group name to change to.
changeVolumeGroup() {
    local vggroup="$1"
    [[ -z $vggroup ]] && handleError "No group name passed (${FUNCNAME[0]})\n   Args Passed: $*"
    vgchange -a y "$vggroup"
}
# Get's volume labels from volume group.
# $1 The group to get logical volumes from.
getLogicalVolumes() {
    local vggroup="$1"
    [[ -z $vggroup ]] && handleError "No group name passed (${FUNCNAME[0]})\n   Args Passed: $*"
    local lvs
    local lgvol
    lgvols=""
    lvs=$(lvs --noheadings ${vggroup} | sed -n 's|[[:space:]]\+\([A-Za-z0-9_-]\+\)[[:space:]]\+.*|\1|p')
    for lgvol in ${lvs}; do
        lgvols=(${lgvols} ${lgvol})
    done
}
# Get's volume device mapper.
# $1 The volume to get
# $2 The group to get
getLGDevice() {
    local lgvol="$1"
    local lggroup="$2"
    [[ -z $lgvol ]] && handleError "No volume device passed (${FUNCNAME[0]})\n   Args Passed: $*"
    [[ -z $lggroup ]] && handleError "No volume group passed (${FUNCNAME[0]})\n   Args Passed: $*"
    lgdev="/dev/mapper/${lggroup}-${lgvol}"
    read lgvUUID lgvSIZE <<< $(lvs --noheadings -v ${lggroup} --units s 2>/dev/null | awk '/'${lgvol}'/ {printf("%s %s", $5, gensub(/[Ss]/,"","g",$10))}')
}
# Trims character from string
# $1 The variable to trim
trim() {
    local var="$1"
    var="${var#${var%%[![:space:]]*}}"
    var="${var%${var##*[![:space:]]}}"
    echo -n "$var"
}
# Calculates information
calculate() {
    echo $(awk 'BEGIN{printf "%.0f\n", '$*'}')
}
