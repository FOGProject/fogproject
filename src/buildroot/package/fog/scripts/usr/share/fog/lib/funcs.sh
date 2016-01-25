#!/bin/bash
. /usr/share/fog/lib/partition-funcs.sh
REG_LOCAL_MACHINE_XP="/ntfs/WINDOWS/system32/config/system"
REG_LOCAL_MACHINE_7="/ntfs/Windows/System32/config/SYSTEM"
# 1 to turn on massive debugging of partition table restoration
ismajordebug=0
#If a sub shell gets invoked and we lose kernel vars this will reimport them
$(for var in $(cat /proc/cmdline); do echo export "$var" | grep =; done)
# Below Are non parameterized functions
# These functions will run without any arguments
#
# Clears thes creen unless its a debug task
clearScreen() {
    [[ $mode != +(*debug*) && -n $isdebug ]] && clear
}
# Displays the nice banner along with the running version
displayBanner() {
    version=$(wget -qO - http://${web}service/getversion.php 2>/dev/null)
    echo "   +------------------------------------------+"
    echo "   |     ..#######:.    ..,#,..     .::##::.  |"
    echo "   |.:######          .:;####:......;#;..     |"
    echo "   |...##...        ...##;,;##::::.##...      |"
    echo "   |   ,#          ...##.....##:::##     ..:: |"
    echo "   |   ##    .::###,,##.   . ##.::#.:######::.|"
    echo "   |...##:::###::....#. ..  .#...#. #...#:::. |"
    echo "   |..:####:..    ..##......##::##  ..  #     |"
    echo "   |    #  .      ...##:,;##;:::#: ... ##..   |"
    echo "   |   .#  .       .:;####;::::.##:::;#:..    |"
    echo "   |    #                     ..:;###..       |"
    echo "   |                                          |"
    echo "   +------------------------------------------+"
    echo "   |      Free Computer Imaging Solution      |"
    echo "   +------------------------------------------+"
    echo "   |  Credits: http://fogproject.org/Credits  |"
    echo "   |       http://fogproject.org/Credits      |"
    echo "   |       Released under GPL Version 3       |"
    echo "   +------------------------------------------+"
    echo "   Version: $version"
}
# Gets all system mac addresses except for loopback
getMACAddresses() {
    local lomac="00:00:00:00:00:00"
    cat /sys/class/net/*/address | grep -v $lomac | tr '\n' '|' | sed s/.$//g
}
# Verifies that there is a network interface
verifyNetworkConnection() {
    dots "Verifying network interface configuration"
    local count=$(/sbin/ip addr | awk -F'[ /]+' '/global/{print $3}' | wc -l)
    if [[ -z $count || $count -lt 1 ]]; then
        echo "Failed"
        debugPause
        handleError "No network interfaces found (${FUNCNAME[0]})"
    fi
    echo "Done"
    debugPause
}
# Verifies that the OS is valid for resizing
validResizeOS() {
    [[ $osid != +([1-2]|[5-7]|9|50) ]] && handleError " * Invalid operating system id: $osname ($osid) (${FUNCNAME[0]})"
}
# Gets the information from the system for inventory
doInventory() {
    sysman=$(dmidecode -s system-manufacturer)
    sysproduct=$(dmidecode -s system-product-name)
    sysversion=$(dmidecode -s system-version)
    sysserial=$(dmidecode -s system-serial-number)
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
    mem=$(cat /proc/meminfo | grep MemTotal)
    hdinfo=$(hdparm -i $hd 2>/dev/null | grep Model=)
    caseman=$(dmidecode -s chassis-manufacturer)
    casever=$(dmidecode -s chassis-version)
    caseserial=$(dmidecode -s chassis-serial-number)
    casesasset=$(dmidecode -s chassis-asset-tag)
    sysman64=$(echo $sysman | base64)
    sysproduct64=$(echo $sysproduct | base64)
    sysversion64=$(echo $sysversion | base64)
    sysserial64=$(echo $sysserial | base64)
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
    [[ -z $str ]] && handleError "No string passed (${FUNCNAME[0]})"
    local pad=$(printf "%0.1s" "."{1..50})
    printf " * %s%*.*s" "$str" 0 $((50-${#str})) "$pad"
}
# Enables write caching on the disk passed
# If the disk does not support write caching this does nothing
#
# $1 is the drive
enableWriteCache()  {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    wcache=$(hdparm -W $disk 2>/dev/null | tr -d '[[:space:]]' | awk -F= '/.*write-caching=/{print $2}')
    if [[ $wcache == notsupported ]]; then
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
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    local disk=""
    local part_number=0
    getDiskFromPartition "$part"
    getPartitionNumber "$part"
    local is_fixed=$(echo $fixed | awk "/(^$part_number:|:$part_number:|:$part_number$)/{print 1}")
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
            ntfsresize $part -f -b -P </usr/share/fog/lib/EOFNTFSRESTORE >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not resize $part (${FUNCNAME[0]})"
                    ;;
            esac
            debugPause
            resetFlag "$part"
            ;;
        extfs)
            dots "Resizing $fstype volume ($part)"
            e2fsck -fp $part >/dev/null 2>&1
            case $? in
                0)
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not check before resize (${FUNCNAME[0]})"
                    ;;
            esac
            resize2fs $part >/dev/null 2>&1
            case $? in
                0)
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not resize $part (${FUNCNAME[0]})"
                    ;;
            esac
            e2fsck -fp $part >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not check after resize (${FUNCNAME[0]})"
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
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    local blk_fs=$(blkid -po udev $part | awk -F= /FS_TYPE=/'{print $2}')
    case $blk_fs in
        ext[2-4])
            fstype="extfs"
            ;;
        ntfs)
            fstype="ntfs"
            ;;
        vfat)
            fstype="fat"
            ;;
        hfsplus)
            fstype="hfsp"
            ;;
        btrfs)
            fstype="btrfs"
            ;;
        swap)
            fstype="swap"
            ;;
        *)
            fstype="imager"
            ;;
    esac
}
# Gets the partition type
#
# $1 is the partition
getPartType() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    parttype=$(blkid -po udev $part | awk -F= '/PART_ENTRY_TYPE=/{print $2}')
}
# Gets the entry schemed (dos, gpt, etc...)
#
# $1 is the partition
getPartitionEntryScheme() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    scheme=$(blkid -po udev $part | awk -F= '/PART_ENTRY_SCHEME=/{print $2}')
}
# Checks if the partition is dos extended (mbr with logical parts)
#
# $1 is the partition
partitionIsDosExtended() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
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
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $varVar ]] && handleError "No variable to set passed (${FUNCNAME[0]})"
    printf -v "$varVar" $(blockdev --getpbsz $part)
}
# Prepares location info for uploads
#
# $1 is the image path
prepareUploadLocation() {
    local imagePath="$1"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    dots "Preparing backup location"
    if [[ ! -d $imagePath ]]; then
        mkdir -p $imagePath >/dev/null 2>&1
        case $? in
            0)
                ;;
            *)
                echo "Failed"
                debugPause
                handleError "Failed to create image upload path (${FUNCNAME[0]})"
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
            handleError "Failed to set permissions (${FUNCNAME[0]})"
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
            handleError "Could not clean files (${FUNCNAME[0]})"
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
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $fstypefile ]] && handleError "No type file passed (${FUNCNAME[0]})"
    local disk=""
    local part_number=0
    getDiskFromPartition "$part"
    getPartitionNumber "$part"
    local is_fixed=$(echo $fixed | awk "/(^$part_number:|:$part_number:|:$part_number$)/{print 1}")
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
    local sizefd=0
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
            size=$(ntfsresize -f -i -P $part | grep "You might resize" | cut -d" " -f5)
            if [[ -z $size ]]; then
                tmpoutput=$(ntfsresize -f -i -P $part)
                handleError " * (${FUNCNAME[0]}) Fatal Error, Unable to determine possible ntfs size\n * To better help you debug we will run the ntfs resize\n\t but this time with full output, please wait!\n\t$tmpoutput"
            fi
            sizentfsresize=$((size / 1000))
            let sizentfsresize+=300000
            sizentfsresize=$((sizentfsresize * 1${percent} / 100))
            sizefd=$((sizentfsresize * 103 / 100))
            echo " * Possible resize partition size: $sizentfsresize k"
            dots "Running resize test $part"
            tmp_success=$(ntfsresize -f -n -s ${sizentfsresize}k $part </usr/share/fog/lib/EOFNTFSRESTORE)
            test_string=$(echo $tmp_success | egrep -o "(ended successfully|bigger than the device size|volume size is already OK)" | tr -d '[[:space:]]')
            echo "Done"
            debugPause
            case $test_string in
                endedsuccessfully)
                    echo " * Resize test was successful"
                    do_resizefs=1
                    do_resizepart=1
                    ;;
                biggerthanthedevicesize)
                    echo " * Not resizing filesystem $part (part too small)"
                    ;;
                volumesizeisalreadyOK)
                    echo " * Not resizing filesystem $part (already OK)"
                    do_resizepart=1
                    ;;
                *)
                    handleError "Resize test failed!\n $tmp_success (${FUNCNAME[0]})"
                    ;;
            esac
            if [[ $do_resizefs -eq 1 ]]; then
                debugPause
                dots "Resizing filesystem"
                ntfsresize -f -s ${sizentfsresize}k $part < /usr/share/fog/lib/EOFNTFS >/dev/null 2>&1
                case $? in
                    0)
                        echo "Done"
                        ;;
                    *)
                        echo "Failed"
                        debugPause
                        handleError "Could not resize disk (${FUNCNAME[0]})"
                        ;;
                esac
            fi
            if [[ $do_resizepart -eq 1 ]]; then
                debugPause
                dots "Resizing partition $part"
                getPartBlockSize "$part" "part_block_size"
                case $osid in
                    [1-2])
                        resizePartition "$part" "$sizentfsresize" "$imagePath"
                        [[ $osid -eq 2 ]] && correctVistaMBR "$disk"
                        ;;
                    [5-7]|9)
                        [[ $part_number -eq $win7partcnt ]] && part_start=$(blkid -po udev $part 2>/dev/null | awk -F= '/PART_ENTRY_OFFSET=/{printf("%.0f\n",$2*'$part_block_size'/1000)}') || part_start=1048576
                        if [[ -z $part_start || $part_start -lt 1 ]]; then
                            echo "Failed"
                            debugPause
                            handleError "Unable to determine disk start location (${FUNCNAME[0]})"
                        fi
                        adjustedfdsize=$((sizefd + part_start))
                        resizePartition "$part" "$adjustedfdsize" "$imagePath"
                        ;;
                esac
                echo "Done"
            fi
            resetFlag "$part"
            ;;
        extfs)
            dots "Checking $fstype volume ($part)"
            e2fsck -fp $part >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "e2fsck failed to check $part (${FUNCNAME[0]})"
                    ;;
            esac
            debugPause
            extminsize=$(resize2fs -P $part 2>/dev/null | awk -F': ' '{print $2}')
            block_size=$(dumpe2fs -h $part 2>/dev/null | awk /^Block\ size:/'{print $3}')
            size=$((extminsize * block_size))
            sizeextresize=$((size * 103 / 100 / 1024))
            [[ -z $sizeextresize || $sizeextresize -lt 1 ]] && handleError "Error calculating the new size of extfs ($part) (${FUNCNAME[0]})"
            dots "Shrinking $fstype volume ($part)"
            resize2fs $part -M >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not shrink $fstype volume ($part) (${FUNCNAME[0]})"
                    ;;
            esac
            debugPause
            dots "Shrinking $part partition"
            resizePartition "$part" "$sizeextresize" "$imagePath"
            echo "Done"
            debugPause
            dots "Resizing $fstype volume ($part)"
            resize2fs $part >/dev/null 2>&1
            case $? in
                0)
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could resize $fstype volume ($part) (${FUNCNAME[0]})"
                    ;;
            esac
            e2fsck -fp $part >/dev/null 2>&1
            case $? in
                0)
                    echo "Done"
                    ;;
                *)
                    echo "Failed"
                    debugPause
                    handleError "Could not check expanded volume ($part) (${FUNCNAME[0]})"
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
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $parttype ]] && handleError "No partition type passed (${FUNCNAME[0]})"
    [[ -z $varVar ]] && handleError "No variable to set passed (${FUNCNAME[0]})"
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
writeImage()  {
    local file="$1"
    local target="$2"
    local mc="$3"
    [[ -z $target ]] && handleError "No target to place image passed (${FUNCNAME[0]})"
    mkfifo /tmp/pigz1
    case $mc in
        yes)
            udp-receiver --nokbd --portbase $port --ttl 32 --mcast-rdv-address $storageip 2>/dev/null >/tmp/pigz1 &
            ;;
        *)
            [[ -z $file ]] && handleError "No source file passed (${FUNCNAME[0]})"
            cat $file >/tmp/pigz1 &
            ;;
    esac
    if [[ $imgFormat -eq 1 || $imgLegacy -eq 1 ]]; then
        echo " * Imaging using Partimage"
        pigz -d -c </tmp/pigz1 | partimage restore $target stdin -f3 -b 2>/tmp/status.fog
    else
        echo " * Imaging using Partclone"
        pigz -d -c </tmp/pigz1 | partclone.restore --ignore_crc -O $target -N -f 1 2>/tmp/status.fog
    fi
    [[ ! $? -eq 0 ]] && handleError "Image failed to restore and exited with exit code $? (${FUNCNAME[0]})"
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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local valid_parts=""
    local parts=""
    local part=""
    local imgpart=""
    local part_number=0
    getPartitions "$disk"
    for part in $parts; do
        getPartitionNumber "$part"
        [[ ! -f $imagePath ]] && imgpart="$imagePath/d${disk_number}p${part_number}.img*" || imgpart="$imagePath"
        ls $imgpart >/dev/null 2>&1
        [[ $? -eq 0 ]] && valid_parts="$valid_parts $part"
    done
    [[ -z $restoreparts ]] && restoreparts=$(echo $valid_parts | uniq | sort -V)
}
# $1 = Disk  (e.g. /dev/sdb)
# $2 = Disk number  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
# $4 = ImagePartitionType  (e.g. all, mbr, 1, 2, 3, etc.)
makeAllSwapSystems() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    local imgPartitionType="$4"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $imgPartitionType ]] && handleError "No image partition type passed (${FUNCNAME[0]})"
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
# $1 = Partition
changeHostname() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $hostname || $hostearly -eq 0 ]] && return
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
    dots "Mounting directory"
    if [[ ! -d /ntfs ]]; then
        mkdir -p /ntfs >/dev/null 2>&1
        if [[ ! $? -eq 0 ]]; then
            echo "Failed"
            debugPause
            echo " * Could not create mount location"
            return
        fi
    else
        umount /ntfs >/dev/null 2>&1
    fi
    ntfs-3g -o force,rw $part /ntfs >/tmp/ntfs-mount-output 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            echo " * Could not mount $part to /ntfs"
            return
            ;;
    esac
    debugPause
    if [[ ! -f /usr/share/fog/lib/EOFREG ]]; then
        case $osid in
            1)
                regfile="$REG_LOCAL_MACHINE_XP"
                key1="$REG_HOSTNAME_KEY1_XP"
                key2="$REG_HOSTNAME_KEY2_XP"
                key3="$REG_HOSTNAME_KEY3_XP"
                key4="$REG_HOSTNAME_KEY4_XP"
                key5="$REG_HOSTNAME_KEY5_XP"
                ;;
            [2]|[5-7]|9)
                regfile="$REG_LOCAL_MACHINE_7"
                key1="$REG_HOSTNAME_KEY1_7"
                key2="$REG_HOSTNAME_KEY2_7"
                key3="$REG_HOSTNAME_KEY3_7"
                key4="$REG_HOSTNAME_KEY4_7"
                key5="$REG_HOSTNAME_KEY5_7"
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
        echo "q" >> /usr/share/fog/lib/EOFREG
        echo "y" >> /usr/share/fog/lib/EOFREG
        echo >> /usr/share/fog/lib/EOFREG
    fi
    dots "Changing hostname"
    if [[ ! -e $regfile ]]; then
        echo "Failed"
        debugPause
        umount /ntfs >/dev/null 2>&1
        echo " * File does not exist"
        return
    fi
    reged -e $regfile </usr/share/fog/lib/EOFREG >/dev/null 2>&1
    case $? in
        [0-2])
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            umount /ntfs >/dev/null 2>&1
            echo " * Failed to change hostname"
            return
            ;;
    esac
    rm -rf /usr/share/fog/lib/EOFREG
    umount /ntfs >/dev/null 2>&1
    debugPause
}
fixWin7boot() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    case $osid in
        [5-7]|[9])
            local fstype=""
            fsTypeSetting "$part"
            case $fstype in
                ntfs)
                    dots "Mounting partition"
                    if [[ ! -d /bcdstore ]]; then
                        mkdir -p /bcdstore >/dev/null 2>&1
                        case $? in
                            0)
                                ;;
                            *)
                                echo "Failed"
                                debugPause
                                echo " * Could not create mount location"
                                return
                                ;;
                        esac
                    fi
                    ntfs-3g -o force,rw $part /bcdstore >/tmp/ntfs-mount-output 2>&1
                    case $? in
                        0)
                            echo "Done"
                            ;;
                        *)
                            echo "Failed"
                            debugPause
                            echo " * Could not mount $part to /bcdstore"
                            return
                            ;;
                    esac
                    debugPause
                    dots "Backing up and replacing BCD"
                    if [[ ! -f /bcdstore/Boot/BCD ]]; then
                        echo "Does not exist"
                        debugPause
                        umount /bcdstore >/dev/null 2>&1
                        return
                    fi
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
                    ;;
                *)
                    echo " * Not NTFS Partition"
                    debugPause
                    return
                    ;;
            esac
            ;;
        *)
            echo " * Not a valid bcd necessary OS"
            debugPause
            return
            ;;
    esac
    umount /bcdstore >/dev/null 2>&1
    debugPause
}
clearMountedDevices() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    if [[ ! -d /ntfs ]]; then
        mkdir -p /ntfs >/dev/null 2>&1
        case $? in
            0)
                umount /ntfs >/dev/null 2>&1
                ;;
            *)
                handleError "Could not create mount point /ntfs (${FUNCNAME[0]})"
                ;;
        esac
    fi
    case $osid in
        [5-7]|9)
            local fstype=""
            fsTypeSetting "$part"
            if [[ ! -f /usr/share/fog/lib/EOFMOUNT ]]; then
                echo "cd $REG_HOSTNAME_MOUNTED_DEVICES_7" >/usr/share/fog/lib/EOFMOUNT
                echo "dellallv" >>/usr/share/fog/lib/EOFMOUNT
                echo "q" >>/usr/share/fog/lib/EOFMOUNT
                echo "y" >>/usr/share/fog/lib/EOFMOUNT
                echo >> /usr/share/fog/lib/EOFMOUNT
            fi
            dots "Clearing part ($part)"
            case $fstype in
                ntfs)
                    ntfs-3g -o force,rw $part /ntfs >/dev/null 2>&1
                    case $? in
                        0)
                            ;;
                        *)
                            echo "Failed"
                            debugPause
                            echo " * Failed to mount partition to clear"
                            return
                            ;;
                    esac
                    if [[ ! -f $REG_LOCAL_MACHINE_7 ]]; then
                        echo "Reg file not found"
                        debugPause
                        umount /ntfs >/dev/null 2>&1
                        return
                    fi
                    reged -e </usr/share/fog/lib/EOFMOUNT >/dev/null 2>&1
                    case $? in
                        [0-2])
                            echo "Done"
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
                *)
                    echo "Not NTFS partition"
                    ;;
            esac
            ;;
        *)
            echo " * Not proper OS type"
            ;;
    esac
    debugPause
}
# $1 is the device name of the windows system partition
removePageFile() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    local fstype=""
    fsTypeSetting "$part"
    [[ ! $ignorepg -eq 1 ]] && return
    case $osid in
        [1-2]|[5-7]|[9]|50)
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
                                echo " * Could not create mount location"
                                return
                                ;;
                        esac
                    fi
                    umount /ntfs >/dev/null 2>&1
                    ntfs-3g -o force,rw $part /ntfs >/dev/null 2>&1
                    case $? in
                        0)
                            echo "Done"
                            ;;
                        *)
                            echo "Failed"
                            debugPause
                            echo " * Could not mount to location"
                            return
                            ;;
                    esac
                    debugPause
                    dots "Removing page file"
                    if [[ ! -f /ntfs/pagefile.sys ]]; then
                        echo "Doesn't exist"
                        debugPause
                    else
                        rm -rf /ntfs/pagefile.sys >/dev/null 2>&1
                        case $? in
                            0)
                                echo "Done"
                                ;;
                            *)
                                echo "Failed"
                                debugPause
                                echo " * Could not delete the page file"
                                ;;
                        esac
                        debugPause
                    fi
                    dots "Removing hibernate file"
                    if [[ ! -f /ntfs/hiberfil.sys ]]; then
                        echo "Doesn't exist"
                    else
                        rm -rf /ntfs/hiberfil.sys >/dev/null 2>&1
                        case $? in
                            0)
                                echo "Done"
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
                *)
                    echo " * Not an NTFS file system"
                    ;;
            esac
            ;;
        *)
            echo " * Not necessary for this OSID $osid"
            ;;
    esac
    debugPause
}
# Sets OS mbr, as needed, and returns the Name
#    based on the OS id passed.
determineOS() {
    local osid="$1"
    [[ -z $osid ]] && handleError "No os id passed (${FUNCNAME[0]})"
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
            defaultpart2start="105906176B"
            ;;
        6)
            osname="Windows 8"
            mbrfile="/usr/share/fog/mbr/win8.mbr"
            defaultpart2start="368050176B"
            ;;
        7)
            osname="Windows 8.1"
            mbrfile="/usr/share/fog/mbr/win8.mbr"
            defaultpart2start="368050176B"
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
        99)
            osname="Other OS"
            mbrfile=""
            ;;
        *)
            handleError " * Invalid OS ID ($osid) (${FUNCNAME[0]})"
            ;;
    esac
}
# Converts the string (seconds) passed to human understanding
#
# $1 the seconds to convert
sec2string() {
    local T="$1"
    [[ -z $T ]] && handleError "No string passed (${FUNCNAME[0]})"
    local d=$((T/60/60/24))
    local H=$((T/60/60%24))
    local i=$((T/60%60))
    local s=$((T%60))
    (($d > 0)) && printf '%d days ' "$d"
    (($H > 0)) && printf '%d hours ' "$H"
    (($i > 0)) && printf '%d minutes ' "$i"
    (($s > 0)) && printf '%d seconds ' "$s"
}
# Returns the disk based off the partition passed
#
# $1 is the partition to grab the disk from
getDiskFromPartition() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    disk=$(echo $part | sed 's/p\?[0-9]\+$//g')
}
# Returns the number of the partition passed
#
# $1 is the partition to get the partition number for
getPartitionNumber() {
    local part="$1"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    part_number=$(echo $part | grep -o '[0-9]*$')
}
# $1 is the partition to search for.
getPartitions() {
    local disk="$1"
    [[ -z $disk ]] && disk="$hd"
    [[ -z $disk ]] && handleError "No disk found (${FUNCNAME[0]})"
    parts=$(lsblk -I 3,8,9,179,259 -lpno KNAME,TYPE $disk | awk '{if ($2 ~ /part/) print $1}' | sort -V | uniq)
}
# Gets the hard drive on the host
# Note: This function makes a best guess
getHardDisk() {
    [[ -n $fdrive ]] && hd=$(echo $fdrive)
    [[ -n $hd ]] && return
    local devs=$(lsblk -dpno KNAME -I 3,8,9,179,259 | sort -V | uniq)
    disks=$(echo $devs)
    [[ -z $disks ]] && handleError "Cannot find disk on system (${FUNCNAME[0]})"
    [[ $1 == true ]] && return
    hd=$(echo $disks | head -n1)
    hd=$(echo $hd)
}
# Initialize hard drive by formatting it
# Note: This probably should not be used
#
# $1 is the drive that should be initialized (Required)
initHardDisk() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    local parts=""
    local part=""
    clearPartitionTables "$disk"
    dots "Creating disk with new label"
    parted -s $disk mklabel msdos >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Failed to set label of $disk (${FUNCNAME[0]})"
            ;;
    esac
    debugPause
    dots "Initializing $disk with NTFS partition"
    parted -s $disk -a opt mkpart primary ntfs 2048s -- -1s >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Failed to create partition (${FUNCNAME[0]})"
            ;;
    esac
    debugPause
    runPartprobe "$disk"
    dots "Formatting initialized partition"
    getPartitions "$disk"
    for part in $parts; do
        mkfs.ntfs -Qq $part >/dev/null 2>&1
        case $? in
            0)
                ;;
            *)
                echo "Failed"
                debugPause
                handleError "Failed to initialize (${FUNCNAME[0]})"
                ;;
        esac
    done
    echo "Done"
    debugPause
}
# Corrects mbr layout for Vista OS
#
# $1 is the disk to correct for
correctVistaMBR() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    dots "Correcting Vista MBR"
    dd if=$disk of=/tmp.mbr count=1 bs=512 >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not create backup (${FUNCNAME[0]})"
            ;;
    esac
    xxd /tmp.mbr /tmp.mbr.txt >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "xxd command failed (${FUNCNAME[0]})"
            ;;
    esac
    rm /tmp.mbr >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Couldn't remove /tmp.mbr file (${FUNCNAME[0]})"
            ;;
    esac
    fogmbrfix /tmp.mbr.txt /tmp.mbr.fix.txt >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "fogmbrfix failed to operate (${FUNCNAME[0]})"
            ;;
    esac
    rm /tmp.mbr.txt >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not remove the text file (${FUNCNAME[0]})"
            ;;
    esac
    xxd -r /tmp.mbr.fix.txt /mbr.mbr >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not run second xxd command (${FUNCNAME[0]})"
            ;;
    esac
    rm /tmp.mbr.fix.txt >/dev/null 2>&1
    case $? in
        0)
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not remove the fix file (${FUNCNAME[0]})"
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
            handleError "Could not apply fixed MBR (${FUNCNAME[0]})"
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
    echo "$str"
    #
    # expand the file systems in the restored partitions
    #
    # Windows 7, 8, 8.1:
    # Windows 2000/XP, Vista:
    # Linux:
    if [[ -n $2 ]]; then
        case $osid in
            [1-2]|[5-7]|9|50)
                if [[ -n "$hd" ]]; then
                    getPartitions "$hd"
                    for part in $parts; do
                        expandPartition "$part"
                    done
                fi
                ;;
        esac
    fi
    if [[ -z $isdebug && $mode != +(*debug*) ]]; then
        echo "##############################################################################"
        echo "#                                                                            #"
        echo "#                      Computer will reboot in 1 minute                      #";
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
    echo "$str"
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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    umount /ntfs /bcdstore >/dev/null 2>&1
    udevadm settle
    blockdev --rereadpt $disk >/dev/null 2>&1
    [[ ! $? -eq 0 ]] && handleError "Failed to read back partitions (${FUNCNAME[0]})"
}
# Sends a command list to a file for use when debugging
#
# $1 The string of the command needed to run.
debugCommand() {
    [[ $mode =~ +(*debug*) || -n $isdebug ]] && echo "$1" >> /tmp/cmdlist
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
    [[ -z $fifo ]] && handleError "Missing file in file out (${FUNCNAME[0]})"
    [[ -z $file ]] && handleError "Missing file name to store (${FUNCNAME[0]})"
    [[ ! -e $fifo ]] && mkfifo $fifo >/dev/null 2>&1
    case $imgFormat in
        2)
            pigz $PIGZ_COMP < $fifo | split -a 3 -d -b 200m - ${file}. &
            ;;
        *)
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
saveGRUB() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    # Determine the number of sectors to copy
    # Hack Note: print $4+0 causes the column to be interpretted as a number
    #            so the comma is tossed
    local count=$(sfdisk -d $disk 2>/dev/null | awk /start=\ *[1-9]/'{print $4+0}' | sort -n | head -n1)
    local has_grub=$(dd if=$disk bs=512 count=1 2>&1 | grep GRUB)
    local hasgrubfilename=""
    if [[ -n $has_grub ]]; then
        hasGrubFileName "$imagePath" "$disk_number"
        touch $hasgrubfilename
    fi
    # Ensure that no more than 1MiB of data is copied (already have this size used elsewhere)
    [[ $count -gt 2048 ]] && count=2048
    local mbrfilename=""
    MBRFileName "$imagePath" "$disk_number" "mbrfilename"
    dd if=$disk of=$mbrfilename count=$count bs=512 >/dev/null 2>&1
}
# Checks for the existence of the grub embedding area in the image directory.
# Echos 1 for true, and 0 for false.
#
# Expects:
# the device name (e.g. /dev/sda) as the first parameter,
# the disk number (e.g. 1) as the second parameter
# the directory images stored in (e.g. /image/xyz) as the third parameter
hasGRUB() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local hasgrubfilename=""
    hasGrubFileName "$imagePath" "$disk_number"
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
restoreGRUB() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local tmpMBR=""
    MBRFileName "$imagePath" "$disk_number" "tmpMBR"
    local count=$(du -B 512 $tmpMBR | awk '{print $1}')
    [[ $count -eq 8 ]] && count=1
    dd if=$tmpMBR of=$disk bs=512 count=$count >/dev/null 2>&1
    runPartprobe "$disk"
}
debugPause() {
    [[ -z $isdebug && $mode != debug ]] && return
    echo " * Press [Enter] key to continue"
    read -p "$*"
}
debugEcho() {
    [[ -n $isdebug || $mode == debug ]] && echo "$*"
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
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    swapuuidfilename="$imagePath/d${disk_number}.original.swapuuids"
}
sfdiskPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    sfdiskoriginalpartitionfilename="$imagePath/d${disk_number}.partitions"
}
sfdiskLegacyOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    sfdisklegacyoriginalpartitionfilename="$imagePath/d${disk_number}.original.partitions"
}
sfdiskMinimumPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    sfdiskminimumpartitionfilename="$imagePath/d${disk_number}.minimum.partitions"
}
sfdiskOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    sfdiskPartitionFileName "$imagePath" "$disk_number"
}
sgdiskOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    sgdiskoriginalpartitionfilename="$imagePath/d${disk_number}.sgdisk.original.partitions"
}
fixedSizePartitionsFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    fixed_size_file="$imagePath/d${disk_number}.fixed_size_partitions"
}
hasGrubFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    hasgrubfilename="$imagePath/d${disk_number}.has_grub"
}
MBRFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    local varVar="$3"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    [[ -z $varVar ]] && handleError "No variable to set passed (${FUNCNAME[0]})"
    case $osid in
        [1-2])
            printf -v "$varVar" "$mbrfile"
            ;;
        [5-7]|9)
            [[ -f $imagePath/sys.img.000 ]] && printf -v "$varVar" "$mbrfile" || printf -v "$varVar" "$imagePath/d${disk_number}.mbr"
            ;;
        *)
            printf -v "$varVar" "$imagePath/d${disk_number}.mbr"
            ;;
    esac
}
EBRFileName() {
    local path="$1"  # e.g. /net/dev/foo
    local disk_number="$2"    # e.g. 1
    local part_number="$3"    # e.g. 5
    [[ -z $path ]] && handleError "No path passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    [[ -z $part_number ]] && handleError "No partition number passed (${FUNCNAME[0]})"
    ebrfilename="$path/d${disk_number}p${part_number}.ebr"
}
tmpEBRFileName() {
    local disk_number="$1"
    local part_number="$2"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    [[ -z $part_number ]] && handleError "No partition number passed (${FUNCNAME[0]})"
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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $osid ]] && handleError "No osid passed (${FUNCNAME[0]})"
    local hasgpt=0
    hasGPT "$disk"
    local have_extended_partition=0  # e.g. 0 or 1-n (extended partition count)
    local strdots=""
    [[ $hasgpt -eq 0 ]] && have_extended_partition=$(sfdisk -l $disk 2>/dev/null | egrep "^${disk}.* (Extended|W95 Ext'd \(LBA\))$" | wc -l)
    runPartprobe "$disk"
    if [[ $imgPartitionType != all && $imgPartitionType != mbr ]]; then
        echo " * Skipping partition tables and MBR"
        debugPause
        runPartprobe "$disk"
        return
    fi
    case $hasgpt in
        0)
            strdots="Saving Partition Tables (MBR)"
            case $osid in
                50)
                    [[ $disk_number -eq 1 ]] && strdots="Saving Partition Tables and GRUB (MBR)"
                    ;;
            esac
            dots "$strdots"
            saveGRUB "$disk" "$disk_number" "$imagePath"
            echo "Done"
            if [[ $have_extended_partition -ge 1 ]]; then
                local sfdiskoriginalpartitionfilename=""
                sfdiskPartitionFileName "$imagePath" "$disk_number"
                sfdisk -d $disk 2>/dev/null > $sfdiskoriginalpartitionfilename
                saveAllEBRs "$disk" "$disk_number" "$imagePath"
            fi
            ;;
        1)
            dots "Saving Partition Tables (GPT)"
            sgdisk -b "$imagePath/d${disk_number}.mbr" $disk >/dev/null 2>&1
            if [[ ! $? -eq 0 ]]; then
                echo "Failed"
                debugPause
                handleError "Error trying to save GPT partition tables (${FUNCNAME[0]})"
            fi
            echo "Done"
            ;;
    esac
    runPartprobe "$disk"
    debugPause
}
clearPartitionTables() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
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
            handleError "Error trying to erase partition tables (${FUNCNAME[0]})"
            ;;
    esac
    runPartprobe "$disk"
    debugPause
}
restorePartitionTablesAndBootLoaders() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    local tmpMBR=""
    local hasGRUB=0
    local mbrsize=""
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
    hasGRUB "$disk" "$disk_number" "$imagePath"
    mbrsize=$(ls -l $tmpMBR 2>/dev/null | awk '{print $5}')
    [[ ! -f $tmpMBR ]] && handleError "Image Store Corrupt: Unable to locate MBR (${FUNCNAME[0]})"
    local table_type=""
    getDesiredPartitionTableType "$imagePath" "$disk_number"
    majorDebugEcho "Trying to restore to $table_type partition table."
    if [[ $table_type == GPT || $mbrsize != +(1048576|512|32256) ]]; then
        dots "Restoring Partition Tables (GPT)"
        sgdisk -gel $tmpMBR $disk >/dev/null 2>&1
        [[ ! $? -eq 0 ]] && handleError "Error trying to restore GPT partition tables (${FUNCNAME[0]})"
        global_gptcheck="yes"
        echo "Done"
    else
        case $osid in
            50)
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
            dots "Inserting Extended partitions"
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
            dots "Extended partitions (legacy)"
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
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No drive number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
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
                    echo " * Not uploading content of extended partition"
                    debugPause
                    EBRFileName "$imagePath" "$disk_number" "$part_number"
                    touch "$ebrfilename"
                    ;;
                *)
                    echo " * Using partclone.$fstype"
                    debugPause
                    imgpart="$imagePath/d${disk_number}p${part_number}.img"
                    uploadFormat "$fifoname" "$imgpart"
                    partclone.$fstype -fsck-src-part-y -c -s $part -O $fifoname -N -f 1 2>/tmp/status.fog
                    case $? in
                        0)
                            mv ${imgpart}.000 $imgpart >/dev/null 2>&1
                            echo " * Image Uploaded"
                            ;;
                        *)
                            handleError "Failed to complete upload (${FUNCNAME[0]})"
                            ;;
                    esac
                    ;;
            esac
            ;;
    esac
    rm -rf $fifoname >/dev/null 2>&1
    debugPause
}
restorePartition() {
    local part="$1"
    local disk_number="$2"
    local imagePath="$3"
    local mc="$4"
    [[ -z $part ]] && handleError "No partition passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
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
            imgpart="$imagePath/$img"
            ;;
        n|mps|mpa)
            case $osid in
                [1-2])
                    imgpart="$imagePath"
                    ;;
                50)
                    imgpart="$imagePath/d${disk_number}p${part_number}.img*"
                    ;;
                [5-7]|9)
                    [[ ! -f $imagePath/sys.img.000 ]] && imgpart="$imagePath/d${disk_number}p${part_number}.img*"
                    if [[ -z $imgpart ]] ;then
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
            handleError "Invalid Image Type $imgType (${FUNCNAME[0]})"
            ;;
    esac
    ls $imgpart >/dev/null 2>&1
    if [[ ! $? -eq 0 ]]; then
        EBRFileName "$imagePath" "$disk_number" "$part_number"
        [[ -e $ebrfilename ]] && echo " * Not downloading content of extended partition" || echo " * Partition File Missing: $imgpart"
        runPartprobe "$disk"
        return
    fi
    writeImage "$imgpart" "$part" "$mc"
    runPartprobe "$disk"
    resetFlag "$part"
}
gptorMBRSave() {
    local disk="$1"
    local disk_number="$2"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number  passed (${FUNCNAME[0]})"
    local strdots=""
    runPartprobe "$disk"
    local gptormbr=$(gdisk -l $disk | awk /^\ *GPT:/'{print $2}')
    case $gptormbr in
        not)
            case $osid in
                50)
                    strdots="Saving MBR/Grub"
                    ;;
                *)
                    strdots="Saving MBR"
                    ;;
            esac
            dots "$strdots"
            saveGRUB "$disk" 1 "$2"
            echo "Done"
            ;;
        *)
            dots "Saving Partition Tables (GPT)"
            sgdisk -b "$imagePath/d1.mbr" "$disk" >/dev/null 2>&1
            if [[ ! $? -eq 0 ]]; then
                echo "Failed"
                debugPause
                runFixparts "$disk"
                gptorMBRSave "$disk" "$2"
                return
            fi
            echo "Done"
            ;;
    esac
    debugPause
}
runFixparts() {
    local disk="$1"
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    dots "Attempting fixparts"
    fixparts $disk </usr/share/fog/lib/EOFFIXPARTS >/dev/null 2>&1
    case $? in
        0)
            echo "Done"
            ;;
        *)
            echo "Failed"
            debugPause
            handleError "Could not fix partition layout (${FUNCNAME[0]})" "yes"
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
    [[ -z $disk ]] && handleError "No disk passed (${FUNCNAME[0]})"
    [[ -z $disk_number ]] && handleError "No disk number passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $osid ]] && handleError "No osid passed (${FUNCNAME[0]})"
    [[ -z $imgPartitionType ]] && handleError "No image partition type  passed (${FUNCNAME[0]})"
    restorePartitionTablesAndBootLoaders "$disk" "$disk_number" "$imagePath" "$osid" "$imgPartitionType"
    local do_fill=0
    fillDiskWithPartitionsIsOK "$disk" "$imagePath" "$disk_number"
    majorDebugEcho "Filling disk = $do_fill"
    dots "Attempting to expand/fill partitions"
    if [[ $do_fill -eq 0 ]]; then
        echo "Failed"
        debugPause
        handleError "Fatal Error: Could not resize partitions (${FUNCNAME[0]})"
    fi
    fillDiskWithPartitions "$disk" "$imagePath" "$disk_number"
    echo "Done"
    debugPause
    runPartprobe "$disk"
}
# $1 is the disks
# $2 is the image path
# $3 is the image partition type (either all or partition number)
performRestore() {
    local disks="$1"
    local disk=""
    local imagePath="$2"
    local imgPartitionType="$3"
    [[ -z $disks ]] && handleError "No disks passed (${FUNCNAME[0]})"
    [[ -z $imagePath ]] && handleError "No image path passed (${FUNCNAME[0]})"
    [[ -z $imgPartitionType ]] && handleError "No partition type passed (${FUNCNAME[0]})"
    local disk_number=1
    local part_number=0
    local restoreparts=""
    [[ $imgType =~ [Nn] ]] && local tmpebrfilename=""
    for disk in $disks; do
        getValidRestorePartitions "$disk" "$disk_number" "$imagePath"
        for restorepart in $restoreparts; do
            getPartitionNumber "$restorepart"
            [[ $imgPartitionType != all && $imgPartitionType != $part_number ]] && continue
            [[ $imgType =~ [Nn] ]] && tmpEBRFileName "$disk_number" "$part_number"
            restorePartition "$restorepart" "$disk_number" "$imagePath"
            [[ $imgType =~ [Nn] ]] && restoreEBR "$restorepart" "$tmpebrfilename"
            [[ $imgType =~ [Nn] ]] && expandPartition "$restorepart" "$fixed_size_partitions"
            case $osid in
                [1-2]|[5-7]|9)
                    [[ $imgType =~ [Nn] ]] && fixWin7boot "$restorepart"
                    ;;
            esac
        done
        makeAllSwapSystems "$disk" "$disk_number" "$imagePath" "$imgPartitionType"
        let disk_number+=1
    done
}
