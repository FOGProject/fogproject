#!/bin/bash
workingdir="$(pwd)/../../"
. $workingdir/lib/common/functions.sh
. $workingdir/lib/common/config.sh
version="$(awk -F\' /"define\('FOG_VERSION'[,](.*)"/'{print $4}' $workingdir/packages/web/lib/fog/system.class.php | tr -d '[[:space:]]')"
clearScreen
displayBanner
echo "   Version: ${version} Backup"
echo
if [[ ! -r $fogprogramdir/.fogsettings ]]; then
    IMAGEDIR="/images"
    SNAPINDIR="/opt/fog/snapins"
    REPORTDIR="/var/www/html/fog/management/reports"
    MYSQL_USER="root"
    MYSQL_PASSWORD=""
    MYSQL_HOST="127.0.0.1"
    MYSQL_DATABASE="fog"
else
    . $fogprogramdir/.fogsettings
    [[ -n $storageLocation ]] && IMAGEDIR="$storageLocation" || IMAGEDIR="/images"
    SNAPINDIR="/opt/fog/snapins"
    REPORT="${docroot}${webroot}/management/reports"
    [[ -n $snmysqluser ]] && MYSQL_USER="$snmysqluser" || MYSQL_USER="root"
    [[ -n $snmysqlhost ]] && MYSQL_HOST="$(echo $snmysqlhost | sed 's/p[:]//g')" || MYSQL_HOST="127.0.0.1"
    [[ -n $snmysqlpass ]] && MYSQL_PASSWORD="$snmysqlpass" || MYSQL_PASSWORD=""
    MYSQL_DATABASE="fog"
fi
usage() {
	echo "  FOG Backup Usage:"
	echo
	echo " $0 backuplocation"
	echo "      backuplocation is the path where you would like to store your backup files"
    [[ -n $1 && $1 -eq 2 ]] && echo "      Backup location must exist prior"
    exit $1
}
clear
[[ -z $1 ]] && usage 1
[[ ! -d $1 ]] && usage 2
sleep 2
echo
echo "  This script is only tested on Fedora!"
echo
sleep 1
echo "   Using backup directory: $1"
echo
sleep 1
backupdir="$1/"
echo
starttime=$(date +%D%t%r)
echo "   Task started at: $starttime"
echo
[[ ! -d $backupdir/images || ! -d $backupdir/mysql || ! -d $backupdir/snapins || -d $backupdir/reports ]] && mkdir -p $backupdir/{images,mysql,snapins,reports} >/dev/null 2>&1
dots " * Backing up MySQL database"
mysqldump -h"\'$MYSQL_HOST\'" -u"\'$MYSQL_USER\'" -p"\'$MYSQL_PASSWORD\'" --allow-keywords -f $MYSQL_DATABASE > $backupdir/mysql/fog.sql
errorStat $?
echo "Done"
dots " * Backing up images"
[[ -d $IMAGEDIR/ ]] && cp -au $IMAGEDIR/ $backupdir/images/ || false
errorStat $?
echo "Done"
dots " * Backing up snapins"
[[ -d $SNAPINDIR/ ]] && cp -au $SNAPINDIR/ $backupdir/snapins/ || false
errorStat $?
dots " * Backup up reports"
[[ -d $REPORTDIR/ ]] && cp -au $REPORTDIR/ $backupdir/reports/ || false
errorStat $?
echo
endTime=$(date +%D%t%r)
echo " * Task completed at: $endTime"
echo
exit 0
