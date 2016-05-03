#!/bin/bash
usage() {
    echo -e "Usage: $0 [-h?] [-B </backup/path/>]"
    echo -e "\t-h -? --help\t\t\tDisplay this info"
    echo -e "\t-B -b --backuppath\t\tSpecify the backup path.\n\t\tIf not set will use backupPath from fog settings plus fog_backup_DATE."
}
. ../../lib/common/utils.sh
optspec="h?B:b:-:"
while getopts "$optspec" o; do
    case $o in
        -)
            case $OPTARG in
                help)
                    usage
                    exit 0
                    ;;
                backuppath)
                    if [[ ! -d $OPTARG ]]; then
                        usage
                        handleError "Path must be an existing directory" 8
                    fi
                    backupPath=$OPTARG
                    ;;
                *)
                    if [[ $OPTERR -eq 1 && ${optspec:0:1} != : ]]; then
                        usage
                        handleError "Unknown option: --${OPTARG}" 9
                    fi
                    ;;
            esac
            ;;
        [Hh]|'?')
            usage
            exit 0
            ;;
        [Bb])
            if [[ ! -d $OPTARG ]]; then
                usage
                handleError "Path must be an existing directory" 8
            fi
            backupPath=$OPTARG
            ;;
        :)
            usage
            handleError "Option -$OPTARG requires a value" 10
            ;;
        *)
            if [[ $OPTERR -eq 1 && ${optspec:0:1} != : ]]; then
                usage
                handleError "Unknown option: -${OPTARG}" 9
            fi
            ;;
    esac
done
if [[ -z $backupPath ]]; then
    usage
    handleError "A path to backup the data must be set." 11
fi
if [[ ! -d $backupPath ]]; then
    usage
    handleError "Path must be an existing directory" 8
fi
backupDate=$(date +"%Y%m%d");
backupDirO="$backupPath/$backupDate"
backupDir="$backupPath/$backupDate"
while [[ -d $backupDir ]]; do
    countBackup=`ls | grep $backupDirO | wc -l`
    backupDir="${backupDir}_$countBackup"
done
[[ ! -d $backupDir ]] && mkdir -p $backupDir/{images,mysql,snapins,reports,logs} >/dev/null 2>&1
[[ ! -d $backupDir/images || $backupDir/mysql || $backupDir/snapins || $backupDir/reports || $backupDir/logs ]] && mkdir -p $backupDir/{images,mysql,snapins,reports,logs} >/dev/null 2>&1
backupDB() {
    dots "Backing up database"
    wget --no-check-certificate -O $backupDir/mysql/fog.sql "http://$ipaddress/$webroot/management/export.php?type=sql" 2>>$backupDir/logs/error.log 1>>$backupDir/logs/progress.log 2>&1
    stat=$?
    if [[ ! $stat -eq 0 ]]; then
        echo "Failed"
        handleError "Could not create/download sql backup file" 12
    fi
    echo "Done"
}
backupImages() {
    imageLocation=$storageLocation
    [[ ! -d $imageLocation ]] && handleError "Images location:$imageLocation does not exist on this server" 15
    dots "Backing up images"
    cp -auv $imageLocation $backupDir/images/ 2>>$backupDir/logs/error.log 1>>$backupDir/logs/progress.log 2>&1
    stat=$?
    if [[ ! $stat -eq 0 ]]; then
        echo "Failed"
        handleError "Could not backup images" 13
    fi
    echo "Done"
}
backupSnapins() {
    [[ -z $snapinLocation ]] && snapinLocation='/opt/fog/snapins'
    [[ ! -d $snapinLocation ]] && handleError "Snapins location:$snapinLocation does not exist on this server. Please add snapinLocation='/path/to/snapins' to .fogsettings." 16
    dots "Backing up snapins"
    cp -auv $snapinLocation/ $backupDir/snapins/ 2>>$backupDir/logs/error.log 1>>$backupDir/logs/progress.log 2>&1
    stat=$?
    if [[ ! $stat -eq 0 ]]; then
        echo "Failed"
        handleError "Could not backup snapins" 14
    fi
    echo "Done"
}
backupReports() {
    reportLocation="$webdirdest/management/reports"
    [[ ! -d $reportLocation ]] && handleError "Reports location: $reportLocation does not exist on this server" 18
    cp -auv $reportLocation/ $backupDir/reports/ 2>>$backupDir/logs/error.log 1>>$backupDir/logs/progress.log 2>&1
    stat=$?
    if [[ ! $stat -eq 0 ]]; then
        echo "Failed"
        handleError "Could not backup reports" 17
    fi
    echo "Done"
}
starttime=$(date +%D%t%r)
echo "Started backup at: $starttime"
backupDB
backupReports
backupSnapins
backupImages
endtime=$(date +%D%t%r)
echo "Completed backup at: $endtime"
