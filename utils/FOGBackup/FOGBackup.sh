#!/bin/sh

IMAGEDIR="/images";

SNAPINDIR="/opt/fog/snapins";

REPORTDIR="/var/www/html/fog/management/reports";

MYSQL_USER="root";
MYSQL_PASSWORD="";
MYSQL_HOST="localhost";
MYSQL_DATABASE="fog";

displayBanner()
{
	echo "        ___           ___           ___      ";
	echo "       /\  \         /\  \         /\  \     ";
	echo "      /::\  \       /::\  \       /::\  \    ";
	echo "     /:/\:\  \     /:/\:\  \     /:/\:\  \   ";
	echo "    /::\-\:\  \   /:/  \:\  \   /:/  \:\  \  ";
	echo "   /:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\ ";
	echo "   \/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/ ";
	echo "        \:\__\    \:\  /:/  /   \:\ \:\__\   ";
	echo "         \/__/     \:\/:/  /     \:\/:/  /   ";
	echo "                    \::/  /       \::/  /    ";
	echo "                     \/__/         \/__/     ";
	echo "";
	echo "  ###########################################";
	echo "  #     Free Computer Imaging Solution      #";
	echo "  #                                         #";
	echo "  #     Backup Version 1.0                  #";
	echo "  #                                         #";
	echo "  #     Created by:                         #";
	echo "  #         Chuck Syperski                  #";	
	echo "  #         Jian Zhang                      #";
	echo "  #                                         #";		
	echo "  #     GNU GPL Version 3                   #";		
	echo "  ###########################################";
	echo "";
	
}

usage()
{
	echo "  FOG Backup Usage:";
	echo "";
	echo " ./FOGBackup backuplocation";
	echo "      backuplocation is the path where you would like to store your backup files";
}

clear;

if [ -n "$1" ]; then
	if [ -d "$1" ]; then
		displayBanner;
		sleep 2;
		echo "";
		echo "  This script is only tested on Fedora!";
		echo "";
		sleep 1;
		echo "  Using backup directory:";
		echo "        $1";
		echo "";
		sleep 1;
		backupdir="${1}/";
		
		echo "";
		starttime=`date +%D%t%r`;
		echo "  Task started at: $starttime";
		echo "";
		
		mkdir "${backupdir}/images" "${backupdir}/mysql" "${backupdir}/snapins" "${backupdir}/reports" 2>/dev/null
		
		echo -n "  Backing up MySql Database:            ";
		mysqldump --host=${MYSQL_HOST} --user=${MYSQL_USER} --password=${MYSQL_PASSWORD} --allow-keywords -f ${MYSQL_DATABASE} > "${backupdir}/mysql/fog.sql"
		echo " [ OK ]";
		
		echo -n "  Backing up Images:                    ";
		if [ -d "${IMAGEDIR}/" ]
		then
			cp -au "${IMAGEDIR}/" "${backupdir}/images/"
			echo " [ OK ]";		
		else
			echo " [FAIL]";
			echo "  Image directory not found: ${IMAGEDIR}/";
			echo "";
		fi

		echo -n "  Backing up Snapins:                   ";
		if [ -d "${SNAPINDIR}/" ]
		then		
			cp -au "${SNAPINDIR}/" "${backupdir}/snapins/"
			echo " [ OK ]";	
		else
			echo " [FAIL]";
			echo "  Image directory not found: ${SNAPINDIR}/";		
			echo "";			
		fi
		
		echo -n "  Backing up Reports:                   ";
		if [ -d "${REPORTDIR}/" ]
		then			
			cp -au "${REPORTDIR}/" "${backupdir}/reports/"
			echo " [ OK ]";			
		else
			echo " [FAIL]";
			echo "  Image directory not found: ${REPORTDIR}/";
			echo "";		
		fi
		
		echo "";
		endtime=`date +%D%t%r`;
		echo "  Task completed at: $endtime";
		echo "";		
		exit 0;
	else
		echo "Fatal Error: Unable to locate backup directory:";
		echo "     $1";
		echo "This directory must exist before FOG backup can run!";
	fi
else
	usage;
fi
exit 1;
