#!/bin/sh

COMMONWEBROOTS="/var/www/html /var/www";
UPDATEMIRRORS="http://internap.dl.sourceforge.net/sourceforge/freeghost/ http://voxel.dl.sourceforge.net/sourceforge/freeghost/ http://kent.dl.sourceforge.net/sourceforge/freeghost/ http://heanet.dl.sourceforge.net/sourceforge/freeghost/";
DOWNLOADDIR="/opt/";
CONFIGPATH="fog/lib/fog/Config.class.php";

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
	echo "  #     Updater Version 1.0                 #";
	echo "  #                                         #";
	echo "  #     Created by:                         #";
	echo "  #         Chuck Syperski                  #";	
	echo "  #         Jian Zhang                      #";
	echo "  #                                         #";		
	echo "  #     GNU GPL Version 3                   #";		
	echo "  ###########################################";
	echo "";
}

clear;

displayBanner;

echo "";
echo "";
echo " Notice:  Your FOG server may go offline during this upgrade process!";
echo "";
echo "";
sleep 5;
echo -ne " Checking running version: \t\t";
blWebRootFound=0;
webRoot="";
ver="";
for wroot in $COMMONWEBROOTS
do
	if [ "$blWebRootFound" = "0" ]
	then
		if [ -d "$wroot" ]
		then
			if [ -f "$wroot/fog/lib/fog/Config.class.php" ]
			then
				fle="$wroot/fog/lib/fog/Config.class.php";
				ver=`cat "$fle" | grep "FOG_VERSION" | cut -d"," -f2 | cut -d"\"" -f2`
				if [ "$ver" != "" ]
				then
					blWebRootFound=1;
					webRoot=$wroot;
					echo $ver;
				fi
			fi
		fi
	fi
done 

if [ "$blWebRootFound" = "1" ]
then
	latest=`wget -O - http://freeghost.sourceforge.net/version/version.php 2>/dev/null`
	echo -ne " Checking latest version: \t\t";
	if [ "$latest" != "" ]
	then
		echo $latest;
		
		if [ "$latest" != "$ver" ]
		then
			echo "";
			echo " A new version of fog has been released...";
			echo "";		
			sleep 2;
			echo " Attempting to download FOG to $DOWNLOADDIR";
			echo "";
			sleep 2;
			blDownloaded=0;
			for url in $UPDATEMIRRORS
			do
				if [ "$blDownloaded" = "0" ] 
				then
					u="${url}fog_${latest}.tar.gz";
					echo " Trying mirror: ";
					echo "      $u";
					echo "";
					lf="${DOWNLOADDIR}fog_${latest}.tar.gz"
					wget -O "$lf" $u;	
					
					if [ "$?" = "0" ]
					then

						if [ -f "$lf" ]
						then
							echo ""
							echo " Download complete";
							echo ""			
							blDownloaded=1;
							echo -ne " Extracting package:\t\t";
							cd ${DOWNLOADDIR};
							tar xvzf $lf >/dev/null 2>&1;
							echo "OK";
							
							cd "${DOWNLOADDIR}fog_${latest}/bin";
							./installfog.sh
										
						else
							echo ""
							echo " Download Failed!";
							echo ""							
						fi
					else
						echo " Download failed!";
					fi
				fi
			done	
		else
			echo "";
			echo " Your FOG server is up to date";
			echo "";
		fi
	else
		echo "Unable to determine latest version.";
	fi
else
	echo "Unable to determine current version.";
fi
echo "";
echo "";

