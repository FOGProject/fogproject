#!/bin/bash
usage() {
    echo -e "Usage: $0 [-h?] [-D] [-R] [-S] [-I] [-B </backup/path/>]"
    echo -e "\t-h -? --help\t\t\tDisplay this info"
    echo -e "\t-B -b --backuppath\t\tSpecify the backup path.\n\t\tIf not set will use backupPath from fog settings plus fog_backup_DATE."
    echo -e "\t-D -d --no-database\t\tOmit backup of the database."
    echo -e "\t-R -r --no-reports\t\tOmit backup of reports."
    echo -e "\t-S -s --no-snapins\t\tOmit backup of snapins."
    echo -e "\t-I -i --no-images\t\tOmit backup of images."
}
# GH-314: resolve against this script's own location rather than the caller's
# cwd, so /opt/fog/utils/FOGBackup/FOGBackup.sh works from any directory.
. "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/../../lib/common/utils.sh"
optspec="Hh?DdIiRrSsB:b:-:"
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
                no-database)
                    noBackupDB=1
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
        [Dd])
            noBackupDB=1
            ;;
        [Rr])
            noBackupReports=1
            ;;
        [Ss])
            noBackupSnapins=1
            ;;
        [Ii])
            noBackupImages=1
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
backupDB() {
    dots "Backing up database"
    # GH-314: this fetched management/export.php, which is not a database
    # endpoint at all -- it calls checkAuthAndCSRF() and then exports whatever
    # report is sitting in $_SESSION['foglastreport']. A plain wget satisfies
    # neither the session nor the CSRF check, so the script died here and never
    # reached reports, snapins or images.
    #
    # It could use maintenance/backup_db.php, but that endpoint is a plain
    # unauthenticated database dump over HTTP, and building a second consumer
    # on top of it is the wrong direction.
    #
    # FOGBackup runs on the server and .fogsettings already carries the database
    # credentials, so dump directly. No web tier in the path also means a backup
    # still works when the web tier is broken, which is when you most want one.
    if ! command -v mysqldump >/dev/null 2>&1; then
        echo "Failed"
        handleError "mysqldump is not installed, cannot back up the database" 19
    fi
    # Credentials go in a 0600 defaults file rather than on the command line,
    # where any local user could read them out of ps while the dump runs.
    # Escaped the same way as the installer escapes it for PHP: a my.cnf value
    # takes backslash escapes, and an unquoted '#' would start a comment.
    local defaults escpass
    defaults=$(mktemp) || handleError "Could not create a temporary file" 20
    chmod 600 "$defaults"
    escpass="${snmysqlpass//\\/\\\\}"
    escpass="${escpass//\"/\\\"}"
    printf '[client]\nhost=%s\nuser=%s\npassword="%s"\n' \
        "${snmysqlhost:-localhost}" "$snmysqluser" "$escpass" > "$defaults"
    # --single-transaction so a live server is not locked for the duration;
    # --quick so a large tasks/imaging history is streamed rather than buffered.
    mysqldump --defaults-extra-file="$defaults" --single-transaction --quick \
        "${mysqldbname:-fog}" > "$backupDir/mysql/fog.sql" \
        2>>$backupDir/logs/error.log
    stat=$?
    rm -f "$defaults"
    # A failed dump can still leave a zero-byte file behind, and reporting
    # success over an empty backup is worse than reporting the failure.
    if [[ ! $stat -eq 0 || ! -s $backupDir/mysql/fog.sql ]]; then
        echo "Failed"
        handleError "Could not create sql backup file" 12
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
    # This step had no dots() line, so its "Done" printed on its own with
    # nothing saying what had been done.
    dots "Backing up reports"
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
# GH-314: backupDB used to run unconditionally, so a storage node -- which has
# no database of its own -- could never get past it to back up its images.
[[ $noBackupDB -eq 1 ]] || backupDB
[[ $noBackupReports -eq 1 ]] || backupReports
[[ $noBackupSnapins -eq 1 ]] || backupSnapins
[[ $noBackupImages -eq 1 ]] || backupImages
endtime=$(date +%D%t%r)
echo " * Completed backup at: $endtime"
