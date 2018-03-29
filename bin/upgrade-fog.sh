#!/bin/bash
echo
echo ===========================================================
echo Stopping nginx
echo ===========================================================
echo

systemctl stop nginx

echo
echo ===========================================================
echo Upgrading FOG
echo ==========================================================
echo

cd /opt/fogproject
git pull
cd /opt/fogproject/bin
./installfog.sh -y

echo
echo ===========================================================
echo Reconfiguring Web Server
echo ===========================================================
echo

systemctl stop httpd
systemctl disable httpd
systemctl start nginx

echo
