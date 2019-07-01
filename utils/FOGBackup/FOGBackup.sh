#!/bin/bash
usage() {
    echo -e "Usage: $0 [-h?] [-B </backup/path/>]"
    echo -e "\t-h -? --help\t\t\tDisplay this info"
    echo -e "\t-B -b --backuppath\t\tSpecify the backup path.\n\t\tIf not set will use backupPath from fog settings plus fog_backup_DATE."
    echo -e "\t --no-reports\t\tOmit backup of reports"
    echo -e "\t --no-snapins\t\tOmit backup of snapins"
    echo -e "\t --no-images\t\tOmit backup of images"
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
                no-reports)
                    noBackupReports=1
                    ;;
                no-snapins)
                    noBackupSnapins=1
                    ;;
                no-images)
                    noBackupImages=1
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
backupDir="$backupPath/$backupDate"
cd $backupPath
countBackup=`ls | grep $backupDate | wc -l`
backupDir="${backupDir}_$countBackup"
[[ ! -d $backupDir ]] && mkdir -p $backupDir/{images,mysql,snapins,reports,logs} >/dev/null 2>&1
[[ ! -d $backupDir/images || $backupDir/mysql || $backupDir/snapins || $backupDir/reports || $backupDir/logs ]] && mkdir -p $backupDir/{images,mysql,snapins,reports,logs} >/dev/null 2>&1
echo " * Backup location: $backupDir"
backupDB() {
    dots "Backing up database"
    wget --no-check-certificate --post-data="nojson=1" -O $backupDir/mysql/fog.sql "http://$ipaddress/$webroot/management/export.php?type=sql" 2>>$backupDir/logs/error.log 1>>$backupDir/logs/progress.log 2>&1
    stat=$?
    if [[ ! $stat -eq 0 ]]; then
        echo "Failed"
        handleError "Could not create/download sql backup file" 12
    fi
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
    reportLocation="$webdirdest/lib/reports"
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
echo " * Started backup at: $starttime"
backupDB
[[ "$noBackupReports" -ne 1 ]] && backupReports
[[ "$noBackupSnapins" -ne 1 ]] && backupSnapins
[[ "$noBackupImages" -ne 1 ]] && backupImages
endtime=$(date +%D%t%r)
echo " * Completed backup at: $endtime"
