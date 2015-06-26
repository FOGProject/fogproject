#
#  FOG - Free, Open-Source Ghost is a computer imaging solution.
#  Copyright (C) 2007  Chuck Syperski & Jian Zhang
#
#   This program is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#    any later version.
#
#   This program is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#
#
dots() {
    max=60
    if [ -n "$1" ]; then
        n=`expr $max - ${#1}`
        echo -n " * ${1:0:max}"
        if [ "$n" -gt 0 ]; then
            for dot in `seq $n`; do
                printf %s .
            done
        fi
    fi
}
warnRoot() {
    currentuser=`whoami`;
    if [ "$currentuser" != "root" ]; then
        echo
        echo "  This installation script should be run as"
        echo "  user \"root\".  You are currenly running ";
        echo "  as $currentuser.  "
        echo
        echo -n "  Do you wish to continue? [N] "
        read ignoreroot;
        if [ "$ignoreroot" = "" ]; then
            ignoreroot="N";
        else
            case "$ignoreroot" in
				[yY]*)
                    ignoreroot="Y";
                ;;
                [nN]*)
                    ignoreroot="N";
                ;;
                *)
                    ignoreroot="N";
                ;;
            esac
        fi
        if [ "$ignoreroot" = "N" ]; then
            echo " Exiting...";
            echo
            exit 1;
        fi
    fi
}
installUtils() {
    dots "Setting up FOG Utils"
    mkdir -p ${fogutilsdir} >/dev/null 2>&1
    cp -Rf ${fogutilsdirsrc}/* "${fogutilsdir}" >/dev/null 2>&1
    chown -R ${apacheuser} ${fogutilsdir} >/dev/null 2>&1
    chmod -R 700 ${fogutilsdir} >/dev/null 2>&1
    errorStat $?
}
help() {
    echo "";
    echo "  Usage: ./installfog.sh [options]";
    echo "       Options:";
    echo "             --help              Displays this message";
    echo "             --no-defaults       Don't guess default values";
    echo "             --no-upgrade        Don't attempt to upgrade";
    echo "				       from previous version.";
    echo "             --uninstall         Not yet supported";
    echo "             --no-htmldoc        Don't try to install htmldoc";
    echo "                                 (You won't be able to create pdf reports)"
    echo "             --force-https       Force https over http";
    echo "             --recreate-vhost    Force recreation of the vhost";
    echo "             --recreate-keys     Force recreation of the ssl keys";
    echo "             --recreate-CA       Force recreation of the CA keys";
    echo "";
}
backupReports() {
    dots "Backing up user reports"
    if [ ! -d "../rpttmp/" ]; then
        mkdir "../rpttmp/"
    fi
    if [ -d "${webdirdest}/management/reports" ]; then
        cp -a ${webdirdest}/management/reports/* "../rpttmp/" >/dev/null 2>&1
    fi
    errorStat $?
}
restoreReports() {
    dots "Restoring user reports"
    if [ -d "${webdirdest}/management/reports" ]; then
        if [ -d "../rpttmp/" ]; then
            cp -a ../rpttmp/* ${webdirdest}/management/reports/ >/dev/null 2>&1;
        fi
    fi
    errorStat $?
}
installFOGServices() {
    dots "Setting up FOG Services"
    mkdir -p ${servicedst} >/dev/null 2>&1
    cp -Rf ${servicesrc}/* ${servicedst}/ >/dev/null 2>&1
    mkdir -p ${servicelogs} >/dev/null 2>&1
    errorStat $?
}
configureUDPCast() {
    dots "Setting up UDPCast"
    cp -Rf "${udpcastsrc}" "${udpcasttmp}"
    cur=`pwd`
    cd /tmp
    tar xvzf "${udpcasttmp}"  >/dev/null 2>&1
    cd ${udpcastout}
    errorStat $?
    dots "Configuring UDPCast"
    ./configure >/dev/null 2>&1
    errorStat $?
    dots "Building UDPCast"
    make >/dev/null 2>&1
    errorStat $?
    dots "Installing UDPCast"
    make install >/dev/null 2>&1;
    errorStat $?
    cd $cur
}
displayOSChoices() {
    blFirst="1";
    while [ "$osid" = "" ]; do
        if [ "$fogupdateloaded" = "1" -a  "$osid" != "" -a "$blFirst" = "1" ]; then
            blFirst="0";
        else
            echo "  What version of Linux would you like to run the installation for?"
            echo "";
            echo "          1) Redhat Based Linux (Redhat, CentOS, Mageia)";
            echo "          2) Debian Based Linux (Debian, Ubuntu, Kubuntu, Edubuntu)";
            echo "          3) Arch Linux";
            echo "";
            echo -n "  Choice: [${strSuggestedOS}]";
            read osid;
        fi
        if [ "$osid" = "" ]; then
            if [ "$strSuggestedOS" != "" ]; then
                osid=$strSuggestedOS;
            fi
        fi
        doOSSpecificIncludes;
    done
}
doOSSpecificIncludes() {
    echo "";
    case "$osid" in
        "1")
            echo -e "\n\n  Starting Redhat based Installation\n\n"
            osname="Redhat"
            . ../lib/redhat/functions.sh
            . ../lib/redhat/config.sh
        ;;
        "2")
            echo -e "\n\n  Starting Debian based Installation\n\n"
            osname="Debian"
            . ../lib/ubuntu/functions.sh
            . ../lib/ubuntu/config.sh
        ;;
        "3")
            echo -e "\n\n  Starting Arch Installation\n\n"
            osname="Arch"
            . ../lib/arch/functions.sh
            . ../lib/arch/config.sh
            systemctl="yes"
        ;;
        *)
            echo -e "  Sorry, answer not recognized\n\n"
            sleep 2;
            osid="";
        ;;
    esac
    currentdir=`pwd`;
    if [ "${currentdir/$webdirdest}" != "$currentdir" -o "${currentdir/$tftpdirdst}" != "$currentdir" ];then
        echo "Please change installation directory.";
        echo "Running from here will fail.";
        echo "You are in $currentdir which is a folder that will";
        echo "be moved during installation.";
        exit 1;
    fi
}
errorStat() {
    if [ "$1" != "0" -a -z "$2" ]; then
        echo "Failed!"
        exit 1
    fi
    echo "OK"
}
stopInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname"
    for serviceItem in $serviceList; do
        dots "Stopping $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl stop $serviceItem >/dev/null 2>&1
        else
            $initdpath/$serviceItem stop >/dev/null 2>&1
        fi
        echo "OK"
    done
}
startInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname"
    for serviceItem in $serviceList; do
        dots "Starting $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl start $serviceItem >/dev/null 2>&1
        else
            $initdpath/$serviceItem start >/dev/null 2>&1
        fi
        errorStat $?
    done
}
enableInitScript() {
    serviceList="$initdMCfullname $initdIRfullname $initdSRfullname $initdSDfullname"
    for serviceItem in $serviceList; do
        dots "Setting $serviceItem script executable"
        chmod 755 $initdpath/$serviceItem >/dev/null 2>&1
        errorStat $?
        dots "Enabling $serviceItem Service"
        if [ "$systemctl" == "yes" ]; then
            systemctl enable $serviceItem >/dev/null 2>&1
        elif [ "$osid" -eq 2 ]; then
            sysv-rc-conf $serviceItem on >/dev/null 2>&1
            if [[ "$linuxReleaseName" == +(*'buntu'*) ]]; then
                /usr/lib/insserv/insserv -d $initdpath/$serviceItem >/dev/null 2>&1
            else
                insserv -d $initdpath/$serviceItem >/dev/null 2>&1
            fi
        elif [ "$osid" -eq 1 ]; then
            chkconfig $serviceItem on >/dev/null 2>&1
        fi
        errorStat $?
    done
}
installInitScript() {
    dots "Installing FOG System Scripts"
    cp -f $initdsrc/* $initdpath/ >/dev/null 2>&1
    errorStat $?
    echo -e "\n\n  * Configuring FOG System Services\n\n"
    enableInitScript
}
configureMySql() {
	stopInitScript
    dots "Setting up and starting MySQL"
    if [ "$systemctl" == "yes" ]; then
		systemctl="yes";
		systemctl enable mariadb.service >/dev/null 2>&1 && \
		systemctl restart mariadb.service >/dev/null 2>&1 && \
		systemctl status mariadb.service >/dev/null 2>&1
		if [ "$?" != "0" ]; then
			systemctl enable mysql.service >/dev/null 2>&1 && \
			systemctl restart mysql.service >/dev/null 2>&1 && \
			systemctl status mysql.service >/dev/null 2>&1
		fi
    elif [ "$osid" -eq 2 ]; then
        sysv-rc-conf mysql on >/dev/null 2>&1 && \
        service mysql stop >/dev/null 2>&1 && \
        service mysql start >/dev/null 2>&1
	else
		chkconfig mysqld on >/dev/null 2>&1 && \
		service mysqld restart >/dev/null 2>&1 && \
		service mysqld status >/dev/null 2>&1
	fi
    errorStat $?
}
configureFOGService() {
	echo "<?php
define( \"WEBROOT\", \"${webdirdest}\" );" > ${servicedst}/etc/config.php
    startInitScript
}
configureNFS() {
    echo -e "$storageLocation *(ro,sync,no_wdelay,no_subtree_check,insecure_locks,no_root_squash,insecure,fsid=0)\n$storageLocation/dev *(rw,async,no_wdelay,no_subtree_check,no_root_squash,insecure,fsid=1)" > "$nfsconfig";
    dots "Setting up and starting RPCBind";
    if [ "$systemctl" == "yes" ]; then
        systemctl enable rpcbind.service >/dev/null 2>&1 && \
        systemctl restart rpcbind.service >/dev/null 2>&1 && \
        systemctl status rpcbind.service >/dev/null 2>&1
    elif [ "$osid" -eq 2 ]; then
        true
    else
        chkconfig rpcbind on >/dev/null 2>&1 && \
        $initdpath/rpcbind restart >/dev/null 2>&1 && \
        $initdpath/rpcbind status >/dev/null 2>&1
    fi
    errorStat $?
    dots "Setting up and starting NFS Server..."
    for nfsItem in $nfsservice; do
        if [ "$systemctl" == "yes" ]; then
            systemctl enable $nfsItem >/dev/null 2>&1 && \
            systemctl restart $nfsItem >/dev/null 2>&1 && \
            systemctl status $nfsItem >/dev/null 2>&1
        else
            if [ "$osid" == 2 ]; then
                sysv-rc-conf $nfsItem on >/dev/null 2>&1 && \
                $initdpath/nfs-kernel-server stop >/dev/null 2>&1 && \
                $initdpath/nfs-kernel-server start >/dev/null 2>&1
            else
                chkconfig $nfsItem on >/dev/null 2>&1 && \
                $initdpath/$nfsItem restart >/dev/null 2>&1 && \
                $initdpath/$nfsItem status >/dev/null 2>&1
            fi
        fi
        if [ "$?" -eq 0 ]; then
            break
        fi
    done
    errorStat $?
}
configureSnapins() {
    dots "Setting up FOG Snapins"
    mkdir -p $snapindir >/dev/null 2>&1
    if [ -d "$snapindir" ]; then
        chmod 775 $snapindir
        chown -R fog:${apacheuser} ${snapindir}
    fi
    errorStat $?
}
configureUsers() {
    getent passwd $username > /dev/null;
    if [ $? != 0 ] || [ "$doupdate" != "1" ]; then
        dots "Setting up fog user";
        # Consider this a temporary security fix
        password=`dd if=/dev/urandom bs=1 count=9 2>/dev/null | base64`
        if [ "$installtype" = "S" ]; then
            # save everyone wrist injuries
            storageftpuser=${username};
            storageftppass=${password};
        else
            storageftpuser=${storageftpuser};
            storageftppass=${storageftppass};
            if [ -z "$storageftpuser" ]; then
                storageftpuser='fog';
            fi
            if [ -z "$storageftppass" ]; then
                storageftppass=${password};
            fi
        fi
        if [ $password != "" ]; then
            useradd -s "/bin/bash" -d "/home/${username}" ${username} >/dev/null 2>&1;
            if [ "$?" = "0" ]; then
                passwd ${username} >/dev/null 2>&1 << EOF
${password}
${password}
EOF
                mkdir "/home/${username}" >/dev/null 2>&1;
                chown -R ${username} "/home/${username}" >/dev/null 2>&1;
                echo "...OK";
            else
                if [ -f "${webdirdest}/lib/fog/Config.class.php" ]; then
                    password=`cat ${webdirdest}/lib/fog/Config.class.php | grep TFTP_FTP_PASSWORD | cut -d"," -f2 | cut -d"\"" -f2`;
                fi
                echo "...Exists";
                bluseralreadyexists="1";
            fi
            true
        else
            false
        fi
        errorStat $?
    fi
    if [ -z "$password" -a -z "$storageftppass" ]; then
        dots "Setting password for FOG User"
        # Consider this a temporary security fix
        password=`dd if=/dev/urandom bs=1 count=9 2>/dev/null | base64`
        passwd ${username} >/dev/null 2>&1 << EOF
${password}
${password}
EOF
        errorStat $?
        storageftpuser=$username
        storageftppass=$password
        echo -e "  * New password set for:\nusername: $username\npassword: $password\n"
        sleep 10;
    fi
}
linkOptFogDir() {
    if [ ! -h "/var/log/fog" ]; then
        dots "Linking FOG Logs to Linux Logs"
        ln -s "/opt/fog/log" "/var/log/fog" >/dev/null 2>&1
        errorStat $?
    fi
    if [ ! -h "/etc/fog" ]; then
        dots "Linking FOG Service config /etc"
        ln -s "/opt/fog/service/etc" "/etc/fog" >/dev/null 2>&1
        errorStat $?
    fi
}
configureStorage() {
    dots "Setting up storage";
    if [ ! -d "$storage" ]; then
        mkdir "$storage" >/dev/null 2>&1
        touch "$storage/.mntcheck" >/dev/null 2>&1
        chmod -R 777 "$storage" >/dev/null 2>&1
    fi
    if [ ! -d "$storage/postdownloadscripts" ]; then
        mkdir "$storage/postdownloadscripts" >/dev/null 2>&1
        if [ ! -f "$storage/postdownloadscripts/fog.postdownload" ]; then
            echo "#!/bin/sh
## This file serves as a starting point to call your custom postimaging scripts.
## <SCRIPTNAME> should be changed to the script you're planning to use.
## Syntax of post download scripts are
#. \${postdownpath}<SCRIPTNAME>" > "$storage/postdownloadscripts/fog.postdownload";
        fi
        chmod -R 777 "$storage" >/dev/null 2>&1
    fi
    if [ ! -d "$storageupload" ]; then
        mkdir "$storageupload" >/dev/null 2>&1
        touch "$storageupload/.mntcheck" >/dev/null 2>&1
        chmod -R 777 "$storageupload" >/dev/null 2>&1
    fi
    errorStat $?
}
clearScreen() {
    clear
}
writeUpdateFile() {
    tmpDte=`date +%c`;
    echo "## Created by the FOG Installer
## Version: $version
## Install time: $tmpDte

ipaddress=\"$ipaddress\";
interface=\"$interface\";
routeraddress=\"$routeraddress\";
plainrouter=\"$plainrouter\";
dnsaddress=\"$dnsaddress\";
dnsbootimage=\"$dnsbootimage\";
password=\"$password\";
osid=\"$osid\";
osname=\"$osname\";
dodhcp=\"$dodhcp\";
bldhcp=\"$bldhcp\";
installtype=\"$installtype\";
snmysqluser=\"$snmysqluser\"
snmysqlpass=\"$snmysqlpass\";
snmysqlhost=\"$snmysqlhost\";
installlang=\"$installlang\";
donate=\"$donate\";
storageLocation=\"$storageLocation\";
mysql_conntype=\"$mysql_conntype\";
fogupdateloaded=\"1\";
storageftpuser=\"$storageftpuser\";
storageftppass=\"$storageftppass\";
" > "$fogprogramdir/.fogsettings";
}
displayBanner() {
    echo
    echo "       ..#######:.    ..,#,..     .::##::.   ";
    echo "  .:######          .:;####:......;#;..      ";
    echo "  ...##...        ...##;,;##::::.##...       ";
    echo "     ,#          ...##.....##:::##     ..::  ";
    echo "     ##    .::###,,##.   . ##.::#.:######::. ";
    echo "  ...##:::###::....#. ..  .#...#. #...#:::.  ";
    echo "  ..:####:..    ..##......##::##  ..  #      ";
    echo "      #  .      ...##:,;##;:::#: ... ##..    ";
    echo "     .#  .       .:;####;::::.##:::;#:..     ";
    echo "      #                     ..:;###..        ";
    echo
    echo "  ###########################################";
    echo "  #     FOG                                 #";
    echo "  #     Free Computer Imaging Solution      #";
    echo "  #                                         #";
    echo "  #     http://www.fogproject.org/          #";
    echo "  #                                         #";
    echo "  #     Credits:                            #";
    echo "  #     http://fogproject.org/Credits       #"
    echo "  #     GNU GPL Version 3                   #";
    echo "  ###########################################";
    echo
}
createSSLCA() {
    if [ "$recreateCA" == "yes" -o "$caCreated" != "yes" -o ! -e "/opt/fog/snapins/CA" -o ! -e "/opt/fog/snapins/CA/.fogCA.key" ]; then
        mkdir -p "/opt/fog/snapins/CA" >/dev/null 2>&1
        dots "Creating SSL CA"
        openssl genrsa -out "/opt/fog/snapins/CA/.fogCA.key" 4096 >/dev/null 2>&1
        openssl req -x509 -new -nodes -key /opt/fog/snapins/CA/.fogCA.key -days 3650 -out /opt/fog/snapins/CA/.fogCA.pem >/dev/null 2>&1 << EOF
.
.
.
.
.
FOG Server CA
.
EOF
        errorStat $?
    fi
    if [ "$recreateKeys" == "yes" -o "$recreateCA" == "yes" -o "$caCreated" != "yes" -o ! -e "/opt/fog/snapins/ssl" -o ! -e "/opt/fog/snapins/ssl/.srvprivate.key" ]; then
        dots "Creating SSL Private Key"
        mkdir -p /opt/fog/snapins/ssl &>/dev/null
        openssl genrsa -out "/opt/fog/snapins/ssl/.srvprivate.key" 4096 >/dev/null 2>&1
        openssl req -new -key "/opt/fog/snapins/ssl/.srvprivate.key" -out "/opt/fog/snapins/ssl/fog.csr" >/dev/null 2>&1 << EOF
.
.
.
.
.
$ipaddress
.


EOF
        errorStat $?
    fi
    dots "Creating SSL Certificate"
    mkdir -p $webdirdest/management/other/ssl >/dev/null 2>&1
    openssl x509 -req -in "/opt/fog/snapins/ssl/fog.csr" -CA "/opt/fog/snapins/CA/.fogCA.pem" -CAkey "/opt/fog/snapins/CA/.fogCA.key" -CAcreateserial -out "$webdirdest/management/other/ssl/srvpublic.crt" -days 3650 >/dev/null 2>&1
    errorStat $?
    dots "Creating auth pub key and cert"
    cp /opt/fog/snapins/CA/.fogCA.pem $webdirdest/management/other/ca.cert.pem >/dev/null 2>&1
    openssl x509 -outform der -in $webdirdest/management/other/ca.cert.pem -out $webdirdest/management/other/ca.cert.der >/dev/null 2>&1
    errorStat $?
    dots "Resetting SSL Permissions"
    chown -R $apacheuser:$apacheuser $webdirdest/management/other >/dev/null 2>&1
    errorStat $?
    dots "Setting up SSL FOG Server"
    echo "<VirtualHost *:80>
    ServerName $ipaddress
    DocumentRoot $docroot
    ${forcehttps}RewriteEngine On
    ${forcehttps}RewriteRule /management/other/ca.cert.der$ - [L]
    ${forcehttps}RewriteRule /management/ https://%{HTTP_HOST}%{REQUEST_URI}%{QUERY_STRING} [R,L]
</VirtualHost>
<VirtualHost *:443>
    Servername $ipaddress
    DocumentRoot $docroot
    SSLEngine On
    SSLCertificateFile $webdirdest/management/other/ssl/srvpublic.crt
    SSLCertificateKeyFile /opt/fog/snapins/ssl/.srvprivate.key
    SSLCertificateChainFile $webdirdest/management/other/ca.cert.der
</VirtualHost>" > "$etcconf";
    errorStat $?
    dots "Restarting Apache2 for fog vhost"
    if [ "$osid" -eq 2 ]; then
        a2enmod rewrite >/dev/null 2>&1
        a2enmod ssl >/dev/null 2>&1
        a2ensite "001-fog" >/dev/null 2>&1
        if [ "$systemctl" == "yes" ]; then
            systemctl restart apache2 php5-fpm >/dev/null 2>&1
            sleep 2
            systemctl status apache2 php5-fpm >/dev/null 2>&1
        else
            service apache2 restart >/dev/null 2>&1
            sleep 2
            service apache2 status >/dev/null 2>&1
        fi
    elif [ "$systemctl" == "yes" ]; then
        systemctl restart httpd php-fpm >/dev/null 2>&1
        sleep 2
        systemctl status httpd php-fpm >/dev/null 2>&1
    else
        service httpd restart >/dev/null 2>&1
        service php-fpm restart >/dev/null 2>&1
        sleep 2
        service httpd status >/dev/null 2>&1
        service php-fpm status >/dev/null 2>&1
    fi
    errorStat $?
    echo "caCreated=\"yes\"" >> "$fogprogramdir/.fogsettings";
}
