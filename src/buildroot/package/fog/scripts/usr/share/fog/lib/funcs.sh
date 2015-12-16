#!/bin/bash
. /usr/share/fog/lib/partition-funcs.sh
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
# 1 to turn on massive debugging of partition table restoration
ismajordebug=0
#If a sub shell gets involked and we lose kernel vars this will reimport them
$(for var in $(cat /proc/cmdline); do echo export $var | grep =; done)
dots() {
    local pad=$(printf "%0.1s" "."{1..45})
    printf " * %s%*.*s" "$1" 0 $((45-${#1})) "$pad"
}
# Get All Active MAC Addresses
getMACAddresses() {
    local lomac="00:00:00:00:00:00"
    echo $(cat /sys/class/net/*/address | grep -v $lomac | tr '\n' '|' | sed s/.$//g)
}
# verify that there is a network interface
verifyNetworkConnection() {
    dots "Verifying network interface configuration"
    local count=$(/sbin/ip addr | awk -F'[ /]+' '/global/ {print $3}' | wc -l)
    if [[ -z $count || $count -lt 1 ]]; then
        local count=$(/sbin/ifconfig -a | awk '/(cast)/ {print $2}' | cut -d ':' -f2 | head -n2 | tail -n1 | wc -l)
    fi
    if [[ -z $count || $count -lt 1 ]]; then
        echo "Failed"
        handleError "No network interfaces found."
    fi
    echo "Done"
}
# $1 is the drive
enableWriteCache()  {
    if [[ -n $1 ]]; then
        dots "Checking write caching status on HDD"
        wcache=$(hdparm -i $1 >/dev/null 2>&1|awk -F= /write-caching.*=/'{print $2}' | tr -d "[[:space:]]")
        if [[ $wcache != nonsupported ]]; then
            hdparm -W1 $1 >/dev/null 2>&1
            echo "Enabled"
        else
            echo "Not Supported"
        fi
        debugPause
    fi
}
# $1 is the partition
expandPartition() {
    if [[ ! -n $1 ]]; then
        echo " * No partition"
        return
    fi
    if [[ -n $fixed_size_partitions ]]; then
        local partNum=$(getPartitionNumber $1)
        is_fixed=$(echo $fixed_size_partitions | egrep "(${partNum}|^${partNum}|${partNum}$)" | wc -l)
        if [[ $is_fixed == 1 ]]; then
            dots "Not expanding ($1) fixed size"
            echo "Done"
            debugPause
            return
        fi
    fi
    do_reset_flag="0"
    fstype=$(fsTypeSetting $1)
    case "$fstype" in
        ntfs)
            dots "Resizing ntfs volume ($1)"
            ntfsresize $1 -f -b -P &>/dev/null << EOFNTFSRESTORE
Y
EOFNTFSRESTORE
            do_reset_flag="1"
            ;;
        extfs)
            dots "Resizing $fstype volume ($1)"
            e2fsck -fp $1 &>/dev/null
            resize2fs $1 &>/dev/null
            e2fsck -fp $1 &>/dev/null
            ;;
        *)
            dots "Not expanding ($1 $fstype)"
            ;;
    esac
    runPartprobe "$hd"
    echo "Done"
    debugPause
    if [ "$do_reset_flag" == "1" ]; then
        resetFlag "$1"
    fi
}
# $1 is the partition
fsTypeSetting() {
    fstype=`blkid -po udev $1 | awk -F= /FS_TYPE=/'{print $2}'`;
    is_ext=`echo "$fstype" | egrep '^ext[234]$' | wc -l`;
    if [ "x${is_ext}" == "x1" ]; then
        echo "extfs";
    fi
    case "$fstype" in
        ntfs)
            echo "ntfs"
            ;;
        vfat)
            echo "fat"
            ;;
        hfsplus)
            echo "hfsp"
            ;;
        btrfs)
            echo "btrfs"
            ;;
        swap)
            echo "swap"
            ;;
        *)
            echo "imager"
            ;;
    esac
}
# $1 is the partition
getPartType() {
    echo $(blkid -po udev $1 | awk -F'=' /PART_ENTRY_TYPE/'{print $2}')
}
# $1 is the partition
getPartitionEntryScheme() {
    echo $(blkid -po udev $1 | awk -F'=' /PART_ENTRY_SCHEME/'{print $2}')
}
# $1 is the partition
partitionIsDosExtended() {
    scheme=$(getPartitionEntryScheme $1)
    debugEcho "scheme = $scheme" 1>&2
    if [[ $scheme == dos ]]; then
        parttype=$(getPartType $1)
        debugEcho "parttype = $parttype" 1>&2
        if [[ $parttype == +(0x5|0xf) ]]; then
            echo "yes"
        else
            echo "no"
        fi
    else
        echo "no"
    fi
}
# $1 is the partition
# Returns the size in bytes.
getPartSize() {
    block_part_tot=$(blockdev --getsz $1);
    part_block_size=$(blockdev --getpbsz $1);
    echo $(awk "BEGIN{print $block_part_tot * $part_block_size}")
}
# Returns the size in bytes.
getDiskSize() {
    block_disk_tot=$(blockdev --getsz $hd);
    disk_block_size=$(blockdev --getpbsz $hd);
    echo $(awk "BEGIN{print $block_disk_tot * $disk_block_size}")
}
validResizeOS() {
    #Valid OSID's are 1 XP, 2 Vista, 5 Win 7, 6 Win 8, 7 Win 8.1, and 50 Linux
    if [[ $osid != +([1-2]|[5-7]|9|50) ]]; then
        handleError " * Invalid operating system id: $osname ($osid)!"
    fi
}
# $1 is the partition
# $2 is the fstypes file location
shrinkPartition() {
    if [[ ! -n $1 ]]; then
        echo " * No partition"
        return
    fi
    fstype=$(fsTypeSetting $1)
    echo "$1 $fstype" >> "$2"
    if [ -n "$fixed_size_partitions" ]; then
        local partNum=$(getPartitionNumber $1)
        is_fixed=$(echo "$fixed_size_partitions" | egrep ':'${partNum}':|^'${partNum}':|:'${partNum}'$' | wc -l);
        if [[ $is_fixed == 1 ]]; then
            dots "Not shrinking ($1) fixed size"
            echo "Done"
            debugPause
            return 0
        fi
    fi
    case "$fstype" in
        ntfs)
            size=$(ntfsresize -f -i -P $1 | grep "You might resize" | cut -d" " -f5)
            if [[ ! -n $size ]]; then
                tmpoutput=$(ntfsresize -f -i -P $1)
                handleError " * Fatal Error, Unable to determine possible ntfs size\n * To better help you debug we will run the ntfs resize\n\t but this time with full output, please wait!\n\t$tmpoutput"
            fi
            sizentfsresize=$(expr $size '/' 1000)
            sizentfsresize=$(expr $sizentfsresize '+' 300000)
            sizentfsresize=$(expr $sizentfsresize '*' 1$percent '/' 100)
            sizefd=$(expr $sizentfsresize '*' 103 '/' 100)
            echo ""
            echo " * Possible resize partition size: $sizentfsresize k"
            dots "Running resize test $1"
            tmpSuc=$(ntfsresize -f -n -s ${sizentfsresize}k $1 << "EOFNTFS"
Y
EOFNTFS
)
            success=$(echo $tmpSuc | grep "ended successfully")
            too_big=$(echo $tmpSuc | grep "bigger than the device size")
            ok_size=$(echo $tmpSuc | grep "volume size is already OK")
            echo "Done"
            debugPause
            if [[ -n $too_big ]]; then
                echo " * Not resizing filesystem $1 (part too small)"
                do_resizefs=0
                do_resizepart=0
            elif [[ -n $ok_size ]]; then
                echo " * Not resizing filesystem $1 (already OK)"
                do_resizefs=0
                do_resizepart=1
            elif [[ ! -n $success ]]; then
                handleWarning "Resize test failed!\n $tmpSuc"
                do_resizefs=0
                do_resizepart=0
            else
                echo " * Resize test was successful"
                do_resizefs=1
                do_resizepart=1
            fi
            debugPause
            if [[ $do_resizefs == 1 ]]; then
                dots "Resizing filesystem"
                ntfsresize -f -s ${sizentfsresize}k $1 &>/dev/null << FORCEY
y
FORCEY
                echo "Done"
                debugPause
                resetFlag "$1"
            fi
            if [[ $do_resizepart == 1 ]]; then
                dots "Resizing partition $1"
                if [[ $osid == +([1-2]) ]]; then
                    resizePartition "$1" "$sizentfsresize" "$imagePath"
                    if [[ $osid == 2 ]]; then
                        correctVistaMBR "$hd"
                    fi
                elif [[ $win7partcnt == 1 ]]; then
                    win7part1start=$(parted -s $hd u kB print | sed -e '/^.1/!d' -e 's/^ [0-9]*[ ]*//' -e 's/kB  .*//' -e 's/\..*$//')
                    if [[ -z $win7part1start ]]; then
                        echo "Failed"
                        debugPause
                        handleError "Unable to determine disk start location."
                    fi
                    adjustedfdsize=$(expr $sizefd '+' $win7part1start)
                    resizePartition "$1" "$adjustedfdsize" "$imagePath"
                elif [[ $win7partcnt == 2 ]]; then
                    win7part2start=$(parted -s $hd u kB print | sed -e '/^.2/!d' -e 's/^ [0-9]*[ ]*//' -e 's/kB  .*//' -e 's/\..*$//')
                    if [[ -z $win7part2start ]]; then
                        echo "Failed"
                        debugPause
                        handleError "Unable to determine disk start location."
                    fi
                    adjustedfdsize=$(expr $sizefd '+' $win7part2start)
                    resizePartition "$1" "$adjustedfdsize" "$imagePath"
                else
                    adjustedfdsize=$(expr $sizefd '+' 1048576)
                    resizePartition "$1" "$adjustedfdsize" "$imagePath"
                fi
                echo "Done"
                debugPause
            fi
            ;;
        extfs)
            dots "Checking $fstype volume ($1)"
            e2fsck -fp $1 &>/dev/null
            status=$?
            echo "Done"
            if [[ $status -gt 3 ]]; then
                handleError "e2fsck failed with exit code $status."
            fi
            debugPause
            extminsizenum=$(resize2fs -P $1 2>/dev/null | awk -F': ' '{print $2}')
            block_size=$(dumpe2fs -h $1 2>/dev/null | awk /^Block\ size:/'{print $3}')
            size=$(expr $extminsizenum '*' $block_size)
            sizeextresize=$(expr $size '*' 103 '/' 100 '/' 1024)
            echo ""
            echo " * Possible resize partition size: $sizeextresize k"
            if [[ -z $sizeextresize ]]; then
                handleError "Error calculating the new size of extfs ($1)."
            fi
            usleep 3000000
            dots "Shrinking $fstype volume ($1)"
            resize2fs $1 -M &>/dev/null
            echo "Done"
            debugPause
            dots "Shrinking $1 partition"
            resizePartition "$1" "$sizeextresize" "$imagePath"
            echo "Done"
            debugPause
            dots "Resizing $fstype volume ($1)"
            resize2fs $1 &>/dev/null
            e2fsck -fp $1 &>/dev/null
            echo "Done"
            debugPause
            ;;
        *)
            echo " * Not shrinking ($1 $fstype)"
            debugPause
            ;;
    esac
}
# $1 is the part
resetFlag() {
    if [[ -n $1 ]]; then
        fstype=$(blkid -po udev $1 | awk -F= /FS_TYPE=/'{print $2}')
        if [[ $fstype == ntfs ]]; then
            dots "Clearing ntfs flag"
            ntfsfix -b -d $1 &>/dev/null
            echo "Done"
            debugPause
        fi
    fi
}
# $1 is the disk
countNtfs() {
    local count=0
    local fstype=""
    local disk="$1"
    if [[ -n $1 ]]; then
        getPartitions $disk
        for part in $parts; do
            fstype=$(fsTypeSetting $part)
            if [[ $fstype == ntfs ]]; then
                count=$(expr $count '+' 1)
            fi
        done
    fi
    echo $count
}
# $1 is the disk
countExtfs() {
    local count=0
    local fstype=""
    local disk="$1"
    if [[ -n $disk ]]; then
        getPartitions $disk
        for part in $parts; do
            fstype=$(fsTypeSetting $part)
            if [[ $fstype == extfs ]]; then
                count=$(expr $count '+' 1)
            fi
        done
    fi
    echo $count
}
# $1 = Source File
# $2 = Target
writeImage()  {
    mkfifo /tmp/pigz1
    if [[ $mc == yes ]]; then
        udp-receiver --nokbd --portbase $port --ttl 32 --mcast-rdv-address $storageip 2>/dev/null > /tmp/pigz1 &
    else
        cat $1 > /tmp/pigz1 &
    fi
    if [[ $imgFormat == 1 || $imgLegacy == 1 ]]; then
        #partimage
        pigz -d -c < /tmp/pigz1 | partimage restore $2 stdin -f3 -b 2>/tmp/status.fog
    else
        # partclone
        pigz -d -c < /tmp/pigz1 | partclone.restore --ignore_crc -O $2 -N -f 1 2>/tmp/status.fog
    fi
    if [[ $? != 0 ]]; then
        handleError "Image failed to restore"
    fi
    rm /tmp/pigz1
}
# $1 = DriveName  (e.g. /dev/sdb)
# $2 = DriveNumber  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
getValidRestorePartitions() {
    local disk="$1"
    local driveNum="$2"
    local imagePath="$3"
    local valid_parts=""
    local imgpart=""
    getPartitions $disk
    for part in $parts; do
        partNum=$(getPartitionNumber $part)
        imgpart="$imagePath/d${driveNum}p${partNum}.img*"
        if [[ $(ls $imgpart 1> /dev/null 2>&1; echo $?) != 0 ]]; then
            valid_parts="$valid_parts $part"
        fi
    done
    echo $valid_parts
}
# $1 = DriveName  (e.g. /dev/sdb)
# $2 = DriveNumber  (e.g. 1)
# $3 = ImagePath  (e.g. /net/foo)
# $4 = ImagePartitionType  (e.g. all, mbr, 1, 2, 3, etc.)
makeAllSwapSystems() {
    local disk="$1"
    local driveNum="$2"
    local imagePath="$3"
    local imgPartitionType="$4"
    local swapuuidfilename=$(swapUUIDFileName "$imagePath" "${driveNum}")
    getPartitions $disk
    for part in $parts; do
        partNum=$(getPartitionNumber $part)
        if [[ $imgPartitionType == all || $imgPartitionType == $partNum ]]; then
            makeSwapSystem "$swapuuidfilename" "$part"
        fi
    done
    debugPause
    runPartprobe "$disk"
}
changeHostname() {
    if [[ $hostearly == 1 && ! -z $hostname ]]; then
        dots "Changing hostname"
        mkdir /ntfs &>/dev/null
        ntfs-3g -o force,rw $part /ntfs &> /tmp/ntfs-mount-output
        regfile=""
        key1=""
        key2=""
        if [[ $osid == +([5-7]|9) ]]; then
            regfile=$REG_LOCAL_MACHINE_7
            key1=$REG_HOSTNAME_KEY1_7
            key2=$REG_HOSTNAME_KEY2_7
            key3=$REG_HOSTNAME_KEY3_7
            key4=$REG_HOSTNAME_KEY4_7
            key5=$REG_HOSTNAME_KEY5_7
        elif [[ $osid == 1 ]];	then
            regfile=$REG_LOCAL_MACHINE_XP
            key1=$REG_HOSTNAME_KEY1_XP
            key2=$REG_HOSTNAME_KEY2_XP
            key3=$REG_HOSTNAME_KEY3_XP
            key4=$REG_HOSTNAME_KEY4_XP
            key5=$REG_HOSTNAME_KEY5_XP
        fi
        reged -e $regfile &>/dev/null << EOFREG
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
        echo "Done"
        debugPause
    fi
}
fixWin7boot() {
    local fstype=$(fsTypeSetting $1)
    if [[ $osid == +([5-7]|9) ]]; then
        dots "Backing up and replacing BCD"
        if [[ $fstype == ntfs ]]; then
            mkdir /bcdstore &>/dev/null
            ntfs-3g -o force,rw $1 /bcdstore
            if [[ -f /bcdstore/Boot/BCD ]]; then
                mv /bcdstore/Boot/BCD{,.bak} >/dev/null 2>&1
                cp /usr/share/fog/BCD /bcdstore/Boot/BCD
                umount /bcdstore
                echo "Done"
            else
                umount /bcdstore
                echo "BCD not present"
            fi
        else
            echo "Not NTFS filesystem"
        fi
    fi
    debugPause
}
clearMountedDevices() {
    mkdir /ntfs &>/dev/null
    if [[ $osid == +([5-7]|9) ]]; then
        fstype=$(fsTypeSetting $1)
        dots "Clearing part ($1)"
        if [[ $fstype == ntfs ]]; then
            ntfs-3g -o force,rw $1 /ntfs
            if [[ -f $REG_LOCAL_MACHINE_7 ]]; then
                reged -e "$REG_LOCAL_MACHINE_7" &>/dev/null << EOFMOUNT
cd $REG_HOSTNAME_MOUNTED_DEVICES_7
delallv
q
y
EOFMOUNT
                echo "Done"
            else
                echo "No reg found"
            fi
            umount /ntfs
        else
            echo "Not valid ntfs"
        fi
        debugPause
    fi
}
# $1 is the device name of the windows system partition
removePageFile() {
    local part="$1"
    local fstype=""
    if [[ ! -z $part ]]; then
        fstype=$(fsTypeSetting $part)
    fi
    if [[ $fstype != ntfs ]]; then
        echo " * No ntfs file system on ($part) to remove page file"
        debugPause
    elif [[ $osid == +([1-2]|[5-7]|9|50) ]]; then
        if [[ $ignorepg == 1 ]]; then
            dots "Mounting partition ($part)"
            mkdir /ntfs &>/dev/null
            ntfs-3g -o force,rw $part /ntfs
            if [[ $? == 0 ]]; then
                echo "Done"
                debugPause
                dots "Removing page file"
                if [[ -f /ntfs/pagefile.sys ]]; then
                    rm -f "/ntfs/pagefile.sys" >/dev/null 2>&1
                    echo "Done"
                else
                    echo "No pagefile found"
                fi
                debugPause
                dots "Removing hibernate file"
                if [[ -f /ntfs/hiberfil.sys ]]; then
                    rm -f "/ntfs/hiberfil.sys" >/dev/null 2>&1
                    echo "Done"
                else
                    echo "No hibernate found"
                fi
                resetFlag "$part"
                umount /ntfs
            else
                echo "Failed"
            fi
            debugPause
        fi
    fi
}
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
    hdinfo=$(hdparm -i $hd | grep Model=)
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
determineOS() {
    local osID="$1"
    if [[ -z $osID ]]; then
        handleError " * Unable to determine operating system type!";
    fi
    case "$osID" in
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
            handleError " * Invalid OS ID ($osID)"
            ;;
    esac
}
clearScreen() {
    if [[ $mode != debug ]]; then
        for i in $(seq 0 99); do echo "";done
    fi
}
sec2String() {
    local T="$1"
    local d=$((T/60/60/24))
    local H=$((T/60/60%24))
    local i=$((T/60%60))
    local s=$((T%60))
    (($d > 0)) && printf '%d days ' $d
    (($H > 0)) && printf '%d hours ' $H
    (($i > 0)) && printf '%d minutes ' $i
    (($s > 0)) && printf '%d seconds ' $s
}
getSAMLoc() {
    local path=''
    local paths="/ntfs/WINDOWS/system32/config/SAM /ntfs/Windows/System32/config/SAM"
    for path in $paths; do
        if [[ -f $path ]]; then
            sam=$path;
            return 0
        fi
    done
    return 0
}
# $1 is the partition to search for.
getPartitionCount() {
    echo $(lsblk -pno KNAME ${1}|wc -l)
}
# $1 is the partition to grab the disk from
getDiskFromPartition() {
    local partition="$1"
    echo $partition | sed 's/p\?[0-9]\+$//g'
}
# $1 is the partition to get the partition number for
getPartitionNumber() {
    local partition="$1"
    echo $partition | grep -o '[0-9]*$'
}
# $1 is the partition to search for.
getPartitions() {
    local disk="$1"
    if [[ -z $disk ]]; then
        local disk=$hd
    fi
    parts=$(lsblk -pno KNAME,MAJ:MIN -x KNAME | awk -F'[ :]+' '{
    if (($1 ~ /[0-9]$/) && ($2 == "3" || $2 == "8" || $2 == "9" || $2 == "179" || $2 == "259") && ($3 > 0))
        print $1
    }' | grep $disk | sort -V)
}
# Gets the hard drive on the host
# Note: This function makes a best guess
getHardDisk() {
    if [[ -n $fdrive ]]; then
        hd="$fdrive"
        return 0
    else
        disks=$(lsblk -dpno KNAME,MAJ:MIN -x KNAME | awk -F'[ :]+' '{
        if ($2 == "3" || $2 == "8" || $2 == "9" || $2 == "179" || $2 == "259")
            print $1
        }')
        if [[ -z "$disks" ]]; then
            handleError "Cannot find disk on system"
        fi
        if [[ -z $1 || $1 != true ]]; then
            hd=$(echo $disks|head -n1)
            return 0
        elif [[ $1 == true ]]; then
            return 0
        fi
    fi
    return 1
}
# Initialize hard drive by formatting it
# Note: This probably should not be used
# $1 is the drive that should be initialized (Required)
initHardDisk() {
    if [[ ! -n $1 ]]; then
        handleError "No hard drive argument provided to initHardDisk"
    fi
    local disk="$1";
    clearPartitionTables "$disk"
    dots "Creating disk with new label"
    parted -s $disk mklabel msdos
    echo "Done"
    debugPause
    dots "Initializing $disk with NTFS partition"
    parted -s $disk -a opt mkpart primary ntfs 2048s -- -1s &>/dev/null
    runPartprobe "$disk"
    getPartitions $disk
    for part in $parts; do
        mkfs.ntfs -Q -q $part
        if [[ $? == 0 ]]; then
            echo "Done"
            debugPause
            return 0
        else
            echo "Failed"
            debugPause
            handleError "Failed to initialize"
        fi
    done
}
correctVistaMBR() {
    dots "Correcting Vista MBR"
    dd if=$1 of=/tmp.mbr count=1 bs=512 &>/dev/null
    xxd /tmp.mbr /tmp.mbr.txt &>/dev/null
    rm /tmp.mbr &>/dev/null
    fogmbrfix /tmp.mbr.txt /tmp.mbr.fix.txt &>/dev/null
    rm /tmp.mbr.txt &>/dev/null
    xxd -r /tmp.mbr.fix.txt /mbr.mbr &>/dev/null
    rm /tmp.mbr.fix.txt &>/dev/null
    dd if=/mbr.mbr of=$1 count=1 bs=512 &>/dev/null
    echo "Done"
    debugPause
}
display_center() {
    local columns=$(tput cols)
    local line="$1"
    local newline=""
    if [[ -z $2 ]]; then
        newline="\n"
    fi
    printf "%*s$newline" $(((${#line}+columns)/2)) "$line"
}
display_right() {
    local columns="$(tput cols)"
    local line="$1"
    printf "%*s\n" 0 $columns "$line"
}
displayBanner() {
    version=$(wget -q -O - http://${web}service/getversion.php)
    echo
    echo
    display_center "+------------------------------------------+"
    display_center "|     ..#######:.    ..,#,..     .::##::.  |"
    display_center "|.:######          .:;####:......;#;..     |"
    display_center "|...##...        ...##;,;##::::.##...      |"
    display_center "|   ,#          ...##.....##:::##     ..:: |"
    display_center "|   ##    .::###,,##.   . ##.::#.:######::.|"
    display_center "|...##:::###::....#. ..  .#...#. #...#:::. |"
    display_center "|..:####:..    ..##......##::##  ..  #     |"
    display_center "|    #  .      ...##:,;##;:::#: ... ##..   |"
    display_center "|   .#  .       .:;####;::::.##:::;#:..    |"
    display_center "|    #                     ..:;###..       |"
    display_center "|                                          |"
    display_center "+------------------------------------------+"
    display_center "|      Free Computer Imaging Solution      |"
    display_center "+------------------------------------------+"
    display_center "|  Credits: http://fogproject.org/Credits  |"
    display_center "|       http://fogproject.org/Credits      |"
    display_center "|       Released under GPL Version 3       |"
    display_center "+------------------------------------------+"
    display_center "Version: $version"
    echo
    echo
}
handleError() {
    echo
    echo
    echo "##############################################################################"
    echo "#                                                                            #"
    echo "#                         An error has been detected!                        #"
    echo "#                                                                            #"
    echo "##############################################################################"
    echo
    echo
    display_center "$1"
    #
    # expand the file systems in the restored partitions
    #
    # Windows 7, 8, 8.1:
    # Windows 2000/XP, Vista:
    # Linux:
    if [[ $2 == yes ]]; then
        if [[ $osid == +([1-2]|[5-7]|9|50) ]]; then
            getPartitions $hd
            for part in $parts; do
                expandPartition "$part"
            done
        fi
    fi
    echo
    echo
    echo "##############################################################################"
    echo "#                                                                            #"
    echo "#                      Computer will reboot in 1 minute                      #";
    echo "#                                                                            #"
    echo "##############################################################################"
    usleep 60000000
    debugPause
    exit 0
}
handleWarning() {
    echo
    echo "##############################################################################"
    echo "#                                                                            #"
    echo "#                        A warning has been detected!                        #"
    echo "#                                                                            #"
    echo "##############################################################################"
    echo
    echo
    display_center "$1"
    echo
    echo
    echo "##############################################################################"
    echo "#                                                                            #"
    echo "#                          Will continue in 1 minute                         #"
    echo "#                                                                            #"
    echo "##############################################################################"
    usleep 60000000
    debugPause
}
# $1 is the drive
runPartprobe() {
    udevadm settle
    blockdev --rereadpt $1 >/dev/null 2>&1
    if [[ $? != 0 ]]; then
        handleError "Failed to read back partitions"
    fi
}
debugCommand() {
    if [[ $mode == debug ]]; then
        echo $1 >> /tmp/cmdlist
    fi
}
# uploadFormat
# Description:
# Tells the system what format to upload in, whether split or not.
# Expects first arguments to be the number of Cores.
# Expects second argument to be the fifo to send to.
# Expects part of the filename in the case of resizable
#    will append 000 001 002 automatically
uploadFormat() {
    if [[ ! -n $2 ]]; then
        echo "Missing file in file out"
        return
    elif [[ ! -n $3 ]]; then
        echo "Missing file name to store"
        return
    fi
    if [[ $imgFormat == 2 ]]; then
        pigz $PIGZ_COMP < $2 | split -a 3 -d -b 200m - ${3}. &
    else
        if [[ $imgType == n ]]; then
            pigz $PIGZ_COMP < $2 > ${3}.000 &
        else
            pigz $PIGZ_COMP < $2 > $3 &
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
saveGRUB() {
    local disk="$1"
    local disk_number="$2"
    local imagePath="$3"
    # Determine the number of sectors to copy
    # Hack Note: print $4+0 causes the column to be interpretted as a number
    #            so the comma is tossed
    local count=$(sfdisk -d "${disk}" 2>/dev/null | \
        awk /start=\ *[1-9]/'{print $4+0}' | sort -n | head -n1)
    local has_grub=$(dd if=$1 bs=512 count=1 2>&1 | grep GRUB)
    if [[ ! -z $has_grub ]]; then
        local hasgrubfilename=$(hasGrubFileName "${imagePath}" "${disk_number}")
        touch "$hasgrubfilename"
    fi
    # Ensure that no more than 1MiB of data is copied (already have this size used elsewhere)
    if [[ $count -gt 2048 ]]; then
        count=2048
    fi
    local mbrfilename=$(MBRFileName "${imagePath}" "${disk_number}")
    dd if="$disk" of="$mbrfilename" count="${count}" bs=512 &>/dev/null
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
    local hasgrubfilename=$(hasGrubFileName "${imagePath}" "${disk_number}")
    if [[ -e $hasgrubfilename ]]; then
        echo "1"
    else
        echo "0"
    fi
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
    local tmpMBR=$(MBRFileName "${imagePath}" "${disk_number}")
    local count=$(du -B 512 "${tmpMBR}" | awk '{print $1;}')
    if [[ $count == 8 ]]; then
        count=1
    fi
    dd if="${tmpMBR}" of="${disk}" bs=512 count="${count}" &>/dev/null
    runPartprobe "$disk"
}
debugPause() {
    if [[ -n $isdebug || $mode == debug ]]; then
        echo
        display_center 'Press [Enter] key to continue'
        read -p "$*"
    fi
}
debugEcho() {
    if [[ -n $isdebug || $mode == debug ]]; then
        echo "$*"
    fi
}
majorDebugEcho() {
    if [[ $ismajordebug -gt 1 ]]; then
        echo "$*"
    fi
}
majorDebugPause() {
    if [[ $ismajordebug -gt 0 ]]; then
        echo
        display_center 'Press [Enter] key to continue'
        read -p "$*"
    fi
}
swapUUIDFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local filename="${imagePath}/d${intDisk}.original.swapuuids"
    echo "$filename"
}
sfdiskPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local filename="${imagePath}/d${intDisk}.partitions"
    echo "$filename"
}
sfdiskLegacyOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local filename="${imagePath}/d${intDisk}.original.partitions"
    echo "$filename"
}
sfdiskMinimumPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local filename="${imagePath}/d${intDisk}.minimum.partitions"
    echo "$filename"
}
sfdiskOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    sfdiskPartitionFileName "$imagePath" "$intDisk"
}
sgdiskOriginalPartitionFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local filename="${imagePath}/d${intDisk}.sgdisk.original.partitions"
    echo "$filename"
}
fixedSizePartitionsFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local filename="${imagePath}/d${intDisk}.fixed_size_partitions"
    echo "$filename"
}
hasGrubFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local filename="${imagePath}/d${intDisk}.has_grub"
    echo "$filename"
}
MBRFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local filename="${imagePath}/d${intDisk}.mbr"
    echo "$filename"
}
EBRFileName() {
    local imagePath="$1"  # e.g. /net/dev/foo
    local intDisk="$2"    # e.g. 1
    local intPart="$3"    # e.g. 5
    local filename="${imagePath}/d${intDisk}p${intPart}.ebr"
    echo "$filename"
}
tmpEBRFileName() {
    EBRFileName "/tmp" "$1" "$2"
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
    local intDisk="$2"                 # e.g. 1
    local imagePath="$3"               # e.g. /net/dev/foo
    local osid="$4"                    # e.g. 50
    local imgPartitionType="$5"        # e.g. all, mbr, 1, 2, ...
    local hasgpt=$(hasGPT "${disk}")   # e.g. 0 or 1
    local have_extended_partition="0"  # e.g. 0 or 1-n (extended partition count)
    if [[ $hasgpt == 0 ]]; then
        have_extended_partition=$(sfdisk -l "$disk" 2>/dev/null | egrep "^${disk}.* (Extended|W95 Ext'd \(LBA\))$" | wc -l)
    else
        have_extended_partition="0"
    fi
    runPartprobe "$disk"
    if [[ $imgPartitionType == all || $imgPartitionType == mbr ]]; then
        if [[ $hasgpt == 0 ]]; then
            if [[ $osid == 50 && $intDisk == 1 ]]; then
                dots "Saving Partition Tables and GRUB (MBR)"
            else
                dots "Saving Partition Tables (MBR)"
            fi
            saveGRUB "${disk}" "${intDisk}" "${imagePath}"
            echo "Done"
            if [[ $have_extended_partition -ge 1 ]]; then
                local sfpartitionfilename=$(sfdiskPartitionFileName "$imagePath" "$intDisk")
                sfdisk -d $disk 2>/dev/null > "${sfpartitionfilename}"
                saveAllEBRs "$disk" "$intDisk" "$imagePath"
            fi
        else
            dots "Saving Partition Tables (GPT)"
            sgdisk -b $imagePath/d${intDisk}.mbr $disk >/dev/null 2>&1
            if [[ $? -ne 0 ]]; then
                handleError "Error trying to save GPT partition tables."
            fi
            rm -f "${sfpartitionfilename}"
            echo "Done"
        fi
    else
        dots "Skipping partition tables and MBR"
        echo "Done"
    fi
    runPartprobe "$disk"
    debugPause
}
clearPartitionTables() {
    local disk=$1
    dots "Erasing current MBR/GPT Tables"
    sgdisk -Z $disk >/dev/null
    local status="$?"
    if [[ $status -eq 0 ]]; then
        echo "Done"
    elif [[ $status -eq 2 ]]; then
        # An output message from sgdisk probably brought us down to the next line.
        echo "Corrupted partition table was erased.  Everything should be fine now."
    else
        handleError "Error trying to erase partition tables."
    fi
    runPartprobe "$disk"
    debugPause
}
restorePartitionTablesAndBootLoaders() {
    local disk="$1"
    local intDisk="$2"
    local imagePath="$3"
    local osid="$4"
    local imgPartitionType="$5"
    local tmpMBR=""
    local has_GRUB=""
    local mbrsize=""
    if [[ $imgPartitionType == all || $imgPartitionType == mbr ]]; then
        clearPartitionTables $disk
        majorDebugEcho "Partition table should be empty now."
        majorDebugShowCurrentPartitionTable "$disk" "$intDisk"
        majorDebugPause
        tmpMBR=$(MBRFileName "$imagePath" "${intDisk}")
        has_GRUB=$(hasGRUB "${disk}" "${intDisk}" "${imagePath}")
        mbrsize=$(ls -l $tmpMBR | awk '{print $5}')
        if [[ -f $tmpMBR ]]; then
            local table_type=$(getDesiredPartitionTableType "${imagePath}" "${intDisk}")
            majorDebugEcho "Trying to restore to $table_type partition table."
            if [[ $table_type == GPT || $mbrsize != +(1048576|512|32256) ]]; then
                dots "Restoring Partition Tables (GPT)"
                sgdisk -gel $tmpMBR $disk >/dev/null 2>&1
                if [[ $? -ne 0 ]]; then
                    handleError "Error trying to restore GPT partition tables."
                fi
                global_gptcheck="yes"
                echo "Done"
            else
                if [[ $osid == 50 ]]; then
                    dots "Restoring Partition Tables and GRUB (MBR)"
                else
                    dots "Restoring Partition Tables (MBR)"
                fi
                restoreGRUB "${disk}" "${intDisk}" "${imagePath}"
                echo "Done"
                majorDebugShowCurrentPartitionTable "$disk" "$intDisk"
                majorDebugPause
                if [[ $(ls -1 ${imagePath}/*.ebr 2>/dev/null | wc -l) -gt 0 ]]; then
                    restoreAllEBRs "${disk}" "${intDisk}" "${imagePath}" "${imgPartitionType}"
                fi
                local sfpartitionfilename=$(sfdiskPartitionFileName "$imagePath" "$intDisk")
                local sflegacypartitionfilename=$(sfdiskLegacyOriginalPartitionFileName "$imagePath" "$intDisk")
                if [[ -e $sfpartitionfilename ]]; then
                    debugPause
                    dots "Extended partitions"
                    sfdisk $disk < "${sfpartitionfilename}" &>/dev/null
                    echo "Done"
                elif [[ -e $sflegacypartitionfilename ]]; then
                    debugPause
                    dots "Extended partitions (legacy)"
                    sfdisk $disk < "${sflegacypartitionfilename}" &>/dev/null
                    echo "Done"
                else
                    debugPause
                    dots "No extended partitions"
                    echo "Done"
                fi
            fi
            runPartprobe "$disk"
            majorDebugShowCurrentPartitionTable "$disk" "$intDisk"
            majorDebugPause
            debugPause
            usleep 3000000
        else
            handleError "Image Store Corrupt: Unable to locate MBR."
        fi
    else
        dots "Skipping partition tables and MBR"
        echo "Done"
        debugPause
    fi
}
savePartition() {
    local part="$1"
    local intDisk="$2"
    local imagePath="$3"
    local cores="$4"
    local imgPartitionType="$5"
    local partNum=""
    local fstype=""
    local parttype=""
    local imgpart=""
    local fifoname="/tmp/pigz1"
    partNum=$(getPartitionNumber $part)
    if [[ $imgPartitionType == all || $imgPartitionType == $partNum ]]; then
        if [[ ! -e $fifoname ]]; then
            mkfifo $fifoname
        fi
        echo " * Processing Partition: $part ($partNum)"
        fstype="$(fsTypeSetting $part)"
        parttype="$(getPartType $part)"
        if [[ $fstype != 'swap' && $parttype != '0x5' && $parttype != '0xf' ]]; then
            # normal filesystem data on partition
            echo " * Using partclone.${fstype}"
            usleep 5000000
            imgpart="$imagePath/d${intDisk}p${partNum}.img"
            uploadFormat "$cores" "$fifoname" "$imgpart"
            partclone.$fstype -fsck-src-part-y -c -s $part -O $fifoname -N -f 1 2>/tmp/status.fog
            mv $imgpart.000 $imgpart 2>/dev/null
            debugPause
            clear
            echo " * Image uploaded"
        else
            if [[ $parttype == 0x5 || $parttype == 0xf ]]; then
                # extended partition, the EBR should have been saved with the partition table
                echo " * Not uploading content of extended partition"
                # leave an empty file to make restorePartition happy
                local ebrfilename=$(EBRFileName "${imagePath}" "${intDisk}" "${partNum}")
                touch "$ebrfilename"
            elif [[ $fstype == swap ]]; then
                echo " * Saving swap parition UUID"
                local swapuuidfilename=$(swapUUIDFileName "${imagePath}" "${intDisk}")
                saveSwapUUID "$swapuuidfilename" "$part"
            else
                handleError "Unexpected condition in savePartition."
            fi
        fi
        rm $fifoname
    else
        dots "Skipping partition $partNum"
        echo "Done"
        debugPause
    fi
}
restorePartition() {
    if [[ -z $1 ]]; then
        handleError "No partition sent to process"
    else
        local part="$1"
    fi
    if [[ -z $2 ]]; then
        local intDisk="1"
    else
        local intDisk="$2"
    fi
    if [[ -z $3 ]]; then
        local imagePath=$imagePath
    else
        local imagePath="$3"
    fi
    if [[ -z "$4" ]]; then
        local imgPartitionType="$imgPartitionType"
    else
        local imgPartitionType="$5"
    fi
    local partNum=""
    local imgpart=""
    partNum=$(getPartitionNumber $part)
    echo " * Processing Partition: $part ($partNum)"
    if [[ $imgPartitionType == all || $imgPartitionType == $partNum ]]; then
        if [[ $imgType == dd ]]; then
            imgpart="$imagePath/$img"
        else
            if [[ -f $imagePath ]]; then
                imgpart="$imagePath"
            elif [[ $win7partcnt == 1 && -f $imagePath/sys.img.000 ]]; then
                imgpart="$imagePath/sys.img.*"
            elif [[ $win7partcnt == 2 && -f $imagePath/sys.img.000 && -f $imagePath/rec.img.000 ]]; then
                if [[ $partNum == 1 ]]; then
                    imgpart="$imagePath/rec.img.000"
                elif [[ $partNum == 2 ]]; then
                    imgpart="$imagePath/sys.img.*"
                fi
            elif [[ $win7partcnt == 3 ]]; then
                if [[ $partNum == 1 ]]; then
                    imgpart="$imagePath/rec.img.000"
                elif [[ $partNum == 2 ]]; then
                    imgpart="$imagePath/rec.img.001"
                elif [[ $partNum == 3 ]]; then
                    imgpart="$imagePath/sys.img.*"
                fi
            else
                imgpart="${imagePath}/d${intDisk}p${partNum}.img*"
            fi
        fi
        usleep 2000000
        if [[ $(ls $imgpart 1> /dev/null 2>&1; echo $?) != 0 ]]; then
            local ebrfilename=$(EBRFileName "${imagePath}" "${intDisk}" "${partNum}")
            if [[ -e $ebrfilename ]]; then
                # extended partition, the EBR should have been restored with the partition table
                echo " * Not downloading content of extended partition"
            else
                echo " * Partition File Missing: $imgpart"
            fi
        else
            writeImage "$imgpart" "$part"
            debugPause
        fi
        runPartprobe "$hd"
        resetFlag "$part"
    else
        dots "Skipping partition $partNum"
        echo "Done"
        debugPause
    fi
}
gptorMBRSave() {
    local disk="$1"
    runPartprobe $disk
    local gptormbr=$(gdisk -l $disk | awk /^\ *GPT:/'{print $2}')
    if [[ $gptormbr == not ]]; then
        dots "Saving MBR or MBR/Grub"
        saveGRUB "$disk" "1" "$2"
        echo "Done"
        debugPause
    else
        dots "Saving Partition Tables (GPT)"
        sgdisk -b $imagePath/d1.mbr $disk >/dev/null
        if [[ ! $? -eq 0 ]]; then
            echo "Failed"
            debugPause
            runFixparts "$disk"
            gptorMBRSave "$disk" "$2"
        else
            echo "Done"
            debugPause
        fi
    fi
}
runFixparts() {
    dots "Attempting fixparts"
    fixparts $1 << EOF
y
w
y
EOF
    if [[ $? != 0 ]]; then
        echo "Failed"
        debugPause
        handleError "Could not fix partition layout" "yes"
    else
        runPartprobe "$1"
        echo "Done"
        debugPause
    fi
}
killStatusReporter() {
    dots "Stopping FOG Status Reporter"
    $(kill -9 $statusReporter) >/dev/null 2>&1
    echo "Done"
}
